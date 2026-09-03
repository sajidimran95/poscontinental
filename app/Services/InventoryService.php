<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditMemo;
use App\Models\InventoryJournalEntry;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReturnToVendor;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockCount;
use App\Support\StockPolicy;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function processReceiving(InventoryReceiving $receiving): void
    {
        DB::transaction(function () use ($receiving) {
            $receiving = InventoryReceiving::query()->lockForUpdate()->find($receiving->id);
            if (! $receiving || $receiving->status === 'Processed') {
                return;
            }

            $receiving->load(['lines', 'purchaseOrder.lines']);
            $siteId = $receiving->site_id;

            foreach ($receiving->lines as $line) {
                if (! $line->item_id || (float) $line->qty_received <= 0) {
                    continue;
                }

                $item = Item::query()->lockForUpdate()->find($line->item_id);
                if (! $item) {
                    continue;
                }

                $qty = (float) $line->qty_received;
                $cost = (float) $line->unit_cost;
                $oldQty = (float) $item->quantity_in_stock;
                $oldAvg = (float) $item->average_cost;
                $newQty = $oldQty + $qty;
                // Recovering from oversell (-10) + purchase 100 → 90; cost weights only positive landings.
                if ($oldQty <= 0) {
                    $newAvg = $cost;
                } elseif ($newQty > 0) {
                    $newAvg = (($oldQty * $oldAvg) + ($qty * $cost)) / $newQty;
                } else {
                    $newAvg = $cost;
                }

                $item->update([
                    'quantity_in_stock' => $newQty,
                    'current_cost' => $cost,
                    'last_cost' => $cost,
                    'average_cost' => round($newAvg, 4),
                    'last_received_at' => $receiving->receipt_date ?? now()->toDateString(),
                ]);

                InventoryJournalEntry::query()->create([
                    'company_id' => $receiving->company_id,
                    'item_id' => $item->id,
                    'site_id' => $siteId,
                    'source_type' => InventoryReceiving::class,
                    'source_id' => $receiving->id,
                    'reference' => $receiving->receipt_number,
                    'qty_change' => $qty,
                    'qty_after' => $newQty,
                    'unit_cost' => $cost,
                    'user_id' => auth()->id(),
                    'notes' => 'Inventory Receiving',
                ]);

                if ($line->purchase_order_line_id) {
                    $poLine = $receiving->purchaseOrder?->lines->firstWhere('id', $line->purchase_order_line_id);
                    if ($poLine) {
                        $poLine->update([
                            'qty_received' => (float) $poLine->qty_received + $qty,
                        ]);
                    }
                }
            }

            if ($receiving->purchase_order_id) {
                $po = PurchaseOrder::query()->with('lines')->find($receiving->purchase_order_id);
                if ($po) {
                    $ordered = (float) $po->lines->sum('qty_ordered');
                    $received = (float) $po->lines->sum('qty_received');
                    $status = $received <= 0 ? 'New' : ($received + 0.0001 >= $ordered ? 'Received' : 'Partially Received');
                    $po->update(['status' => $status]);
                }
            }

            $receiving->update([
                'status' => 'Processed',
                'processed_at' => now(),
            ]);

            $this->syncOnOrderQty(
                $receiving->lines->pluck('item_id')->filter()->all()
            );
        });
    }

    /**
     * Undo a processed receiving: remove received qty from on-hand and roll back PO received qty.
     */
    public function reverseReceiving(InventoryReceiving $receiving): void
    {
        DB::transaction(function () use ($receiving) {
            $receiving = InventoryReceiving::query()->lockForUpdate()->find($receiving->id);
            if (! $receiving || $receiving->status !== 'Processed') {
                return;
            }

            $receiving->load(['lines', 'purchaseOrder.lines']);
            $siteId = $receiving->site_id;

            foreach ($receiving->lines as $line) {
                if (! $line->item_id || (float) $line->qty_received <= 0) {
                    continue;
                }

                $item = Item::query()->lockForUpdate()->find($line->item_id);
                if (! $item) {
                    continue;
                }

                $qty = (float) $line->qty_received;
                $onHand = (float) $item->quantity_in_stock;
                if ($qty > $onHand + 0.0001) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'receiving' => $item->item_code.' cannot reverse receiving — only '.number_format($onHand, 2).' in stock (need '.number_format($qty, 2).').',
                    ]);
                }

                $newQty = $onHand - $qty;
                $item->update(['quantity_in_stock' => $newQty]);

                InventoryJournalEntry::query()->create([
                    'company_id' => $receiving->company_id,
                    'item_id' => $item->id,
                    'site_id' => $siteId,
                    'source_type' => InventoryReceiving::class,
                    'source_id' => $receiving->id,
                    'reference' => $receiving->receipt_number,
                    'qty_change' => -$qty,
                    'qty_after' => $newQty,
                    'unit_cost' => $line->unit_cost,
                    'user_id' => auth()->id(),
                    'notes' => 'Receiving deleted / reversed',
                ]);

                if ($line->purchase_order_line_id) {
                    $poLine = $receiving->purchaseOrder?->lines->firstWhere('id', $line->purchase_order_line_id);
                    if ($poLine) {
                        $poLine->update([
                            'qty_received' => max(0, (float) $poLine->qty_received - $qty),
                        ]);
                    }
                }
            }

            if ($receiving->purchase_order_id) {
                $po = PurchaseOrder::query()->with('lines')->find($receiving->purchase_order_id);
                if ($po) {
                    $ordered = (float) $po->lines->sum('qty_ordered');
                    $received = (float) $po->lines->sum('qty_received');
                    $status = $received <= 0 ? 'New' : ($received + 0.0001 >= $ordered ? 'Received' : 'Partially Received');
                    $po->update(['status' => $status]);
                }
            }

            $receiving->update([
                'status' => 'New',
                'processed_at' => null,
            ]);

            $this->syncOnOrderQty(
                $receiving->lines->pluck('item_id')->filter()->all()
            );
        });
    }

    public function processRtv(ReturnToVendor $rtv): void
    {
        DB::transaction(function () use ($rtv) {
            $rtv = ReturnToVendor::query()->lockForUpdate()->find($rtv->id);
            if (! $rtv || $rtv->status === 'Returned') {
                return;
            }

            $rtv->load('lines');

            foreach ($rtv->lines as $line) {
                if (! $line->item_id || (float) $line->qty <= 0) {
                    continue;
                }

                $item = Item::query()->lockForUpdate()->find($line->item_id);
                if (! $item) {
                    continue;
                }

                $qty = (float) $line->qty;
                $onHand = (float) $item->quantity_in_stock;
                if ($qty > $onHand + 0.0001) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'rtv' => $item->item_code.' RTV qty exceeds on-hand stock ('.number_format($onHand, 2).').',
                    ]);
                }
                $newQty = $onHand - $qty;
                $item->update(['quantity_in_stock' => $newQty]);

                InventoryJournalEntry::query()->create([
                    'company_id' => $rtv->company_id,
                    'item_id' => $item->id,
                    'site_id' => $rtv->site_id,
                    'source_type' => ReturnToVendor::class,
                    'source_id' => $rtv->id,
                    'reference' => $rtv->rtv_number,
                    'qty_change' => -$qty,
                    'qty_after' => $newQty,
                    'unit_cost' => $line->unit_cost,
                    'user_id' => auth()->id(),
                    'notes' => 'Return to Vendor',
                ]);
            }

            $rtv->update([
                'status' => 'Returned',
                'processed_at' => now(),
            ]);
        });
    }

    public function processStockCount(StockCount $count): void
    {
        DB::transaction(function () use ($count) {
            $count = StockCount::query()->lockForUpdate()->find($count->id);
            if (! $count || $count->status === 'Processed') {
                return;
            }

            $count->load('lines');

            foreach ($count->lines as $line) {
                if (! $line->item_id || $line->counted === null) {
                    continue;
                }

                $item = Item::query()->lockForUpdate()->find($line->item_id);
                if (! $item) {
                    continue;
                }

                $counted = (float) $line->counted;
                $oldQty = (float) $item->quantity_in_stock;
                $delta = $counted - $oldQty;

                $item->update([
                    'quantity_in_stock' => $counted,
                    'last_count_date' => $count->date_processed ?? now()->toDateString(),
                ]);

                InventoryJournalEntry::query()->create([
                    'company_id' => $count->company_id,
                    'item_id' => $item->id,
                    'site_id' => $count->site_id,
                    'source_type' => StockCount::class,
                    'source_id' => $count->id,
                    'reference' => $count->stock_count_no,
                    'qty_change' => $delta,
                    'qty_after' => $counted,
                    'unit_cost' => $item->current_cost,
                    'user_id' => auth()->id(),
                    'notes' => 'Stock Count variance',
                ]);
            }

            $count->update([
                'status' => 'Processed',
                'date_processed' => now(),
                'processed_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Decrease on-hand when a sales order is invoiced.
     * After this, allocated is cleared for those lines so Available = In stock.
     */
    public function applyInvoiceStock(SalesOrder $order, Invoice $invoice): void
    {
        $order->loadMissing('lines');

        foreach ($order->lines as $line) {
            if (! $line->item_id) {
                continue;
            }

            $qty = (float) $line->qty_shipped;
            if ($qty <= 0) {
                $qty = (float) $line->qty_ordered;
            }
            if ($qty <= 0) {
                continue;
            }

            $item = Item::query()->lockForUpdate()->find($line->item_id);
            if (! $item) {
                continue;
            }

            $onHand = (float) $item->quantity_in_stock;
            $company = Company::query()->find($order->company_id);
            $err = StockPolicy::invoiceQtyError($item, $qty, $onHand, $company);
            if ($err) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'invoice' => $err,
                ]);
            }

            $newQty = $onHand - $qty;
            $item->update([
                'quantity_in_stock' => $newQty,
                'last_sold_at' => $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
            ]);

            if ((float) $line->qty_shipped <= 0) {
                $line->update(['qty_shipped' => $qty]);
            }

            InventoryJournalEntry::query()->create([
                'company_id' => $order->company_id,
                'item_id' => $item->id,
                'site_id' => $order->ship_from_site_id,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'reference' => $invoice->invoice_number,
                'qty_change' => -$qty,
                'qty_after' => $newQty,
                'unit_cost' => $item->current_cost,
                'user_id' => auth()->id(),
                'notes' => 'Sales Invoice '.$invoice->invoice_number.' (SO '.$order->order_number.')',
            ]);
        }

        $order->update(['status' => 'Invoiced']);
        $this->syncAllocatedQty($order->lines->pluck('item_id')->filter()->all());
    }

    /**
     * Put stock back and reopen the sales order when an invoice is voided.
     */
    public function reverseInvoiceStock(SalesOrder $order, Invoice $invoice): void
    {
        $order->loadMissing('lines');

        foreach ($order->lines as $line) {
            if (! $line->item_id) {
                continue;
            }

            $qty = (float) $line->qty_shipped;
            if ($qty <= 0) {
                $qty = (float) $line->qty_ordered;
            }
            if ($qty <= 0) {
                continue;
            }

            $item = Item::query()->lockForUpdate()->find($line->item_id);
            if (! $item) {
                continue;
            }

            $newQty = (float) $item->quantity_in_stock + $qty;
            $item->update(['quantity_in_stock' => $newQty]);

            InventoryJournalEntry::query()->create([
                'company_id' => $order->company_id,
                'item_id' => $item->id,
                'site_id' => $order->ship_from_site_id,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'reference' => $invoice->invoice_number,
                'qty_change' => $qty,
                'qty_after' => $newQty,
                'unit_cost' => $item->current_cost,
                'user_id' => auth()->id(),
                'notes' => 'Void Invoice '.$invoice->invoice_number.' (SO '.$order->order_number.')',
            ]);
        }

        $this->syncAllocatedQty($order->lines->pluck('item_id')->filter()->all());
    }

    /**
     * Sales order (no invoice): never changes In stock. Rebuilds allocated so Available updates.
     * Invoice edit: In stock goes up when a line is removed, down when qty is added; Available follows.
     *
     * @param  array<int, float>  $oldQtyByItemId
     */
    public function applyOrderQtyRevision(SalesOrder $order, array $oldQtyByItemId, ?Invoice $invoice = null): void
    {
        $order->loadMissing('lines');
        $newByItem = [];
        foreach ($order->lines as $line) {
            if (! $line->item_id) {
                continue;
            }
            $qty = (float) $line->qty_ordered;
            if ($qty <= 0) {
                continue;
            }
            $id = (int) $line->item_id;
            $newByItem[$id] = ($newByItem[$id] ?? 0) + $qty;
        }

        $idMap = [];
        foreach ($oldQtyByItemId as $id => $_) {
            $idMap[(int) $id] = true;
        }
        foreach ($newByItem as $id => $_) {
            $idMap[(int) $id] = true;
        }
        $itemIds = array_keys($idMap);
        if ($itemIds === []) {
            return;
        }

        if ($invoice) {
            $changed = [];
            foreach ($itemIds as $itemId) {
                $delta = (float) ($newByItem[$itemId] ?? 0) - (float) ($oldQtyByItemId[$itemId] ?? 0);
                if (abs($delta) < 0.0001) {
                    continue;
                }
                $changed[(int) $itemId] = $delta;
            }

            if ($changed !== []) {
                $company = Company::query()->find($order->company_id);
                $items = Item::query()->whereIn('id', array_keys($changed))->lockForUpdate()->get()->keyBy('id');
                foreach ($changed as $itemId => $delta) {
                    $item = $items->get($itemId);
                    if (! $item) {
                        continue;
                    }

                    $onHand = (float) $item->quantity_in_stock;
                    if ($delta > 0) {
                        $err = StockPolicy::invoiceQtyError($item, $delta, $onHand, $company);
                        if ($err) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'invoice' => $err,
                            ]);
                        }
                    }

                    $newQty = $onHand - $delta;
                    $item->update(['quantity_in_stock' => $newQty]);

                    InventoryJournalEntry::query()->create([
                        'company_id' => $order->company_id,
                        'item_id' => $item->id,
                        'site_id' => $order->ship_from_site_id,
                        'source_type' => Invoice::class,
                        'source_id' => $invoice->id,
                        'reference' => $invoice->invoice_number,
                        'qty_change' => -$delta,
                        'qty_after' => $newQty,
                        'unit_cost' => $item->current_cost,
                        'user_id' => auth()->id(),
                        'notes' => 'Invoice revision '.$invoice->invoice_number.' (SO '.$order->order_number.')',
                    ]);
                }
            }
        }

        $this->syncAllocatedQty($itemIds);
    }

    /**
     * After an invoiced order is edited, apply the qty difference to on-hand.
     *
     * @param  array<int, float>  $oldShippedByItemId
     */
    public function applyInvoiceRevisionStock(SalesOrder $order, array $oldShippedByItemId, Invoice $invoice): void
    {
        $this->applyOrderQtyRevision($order, $oldShippedByItemId, $invoice);
    }

    /**
     * Increase on-hand when a credit memo is created as a customer return / restock.
     */
    public function applyCreditMemoStock(CreditMemo $memo): void
    {
        if (! $memo->restock_inventory) {
            return;
        }

        $memo->loadMissing('lines');

        foreach ($memo->lines as $line) {
            if ((float) $line->qty <= 0) {
                continue;
            }

            $item = $line->item_id
                ? Item::query()->lockForUpdate()->find($line->item_id)
                : Item::query()
                    ->where('company_id', $memo->company_id)
                    ->where('item_code', $line->item_code)
                    ->lockForUpdate()
                    ->first();
            if (! $item) {
                continue;
            }

            $qty = (float) $line->qty;
            $newQty = (float) $item->quantity_in_stock + $qty;
            $item->update(['quantity_in_stock' => $newQty]);

            InventoryJournalEntry::query()->create([
                'company_id' => $memo->company_id,
                'item_id' => $item->id,
                'site_id' => null,
                'source_type' => CreditMemo::class,
                'source_id' => $memo->id,
                'reference' => $memo->memo_number,
                'qty_change' => $qty,
                'qty_after' => $newQty,
                'unit_cost' => $item->current_cost,
                'user_id' => auth()->id(),
                'notes' => 'Customer return restock '.$memo->memo_number,
            ]);
        }

        $this->syncAllocatedQty($memo->lines->pluck('item_id')->filter()->all());
    }

    /**
     * Manual stock adjustment (set new on-hand or apply a delta). Writes inventory journal.
     *
     * @param  'set'|'change'  $mode
     */
    public function applyManualAdjustment(
        Item $item,
        string $mode,
        float $value,
        string $notes = '',
        ?int $siteId = null,
    ): void {
        DB::transaction(function () use ($item, $mode, $value, $notes, $siteId) {
            $item = Item::query()->lockForUpdate()->find($item->id);
            if (! $item) {
                return;
            }

            $oldQty = (float) $item->quantity_in_stock;
            if ($mode === 'set') {
                $newQty = $value;
            } else {
                $newQty = $oldQty + $value;
            }

            $delta = $newQty - $oldQty;
            if (abs($delta) < 0.0000001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'adjustQty' => 'No stock change — new quantity matches on-hand.',
                ]);
            }

            $item->update(['quantity_in_stock' => $newQty]);

            $ref = 'ADJ-'.now()->format('YmdHis');
            $noteText = trim($notes) !== ''
                ? trim($notes)
                : ($mode === 'set'
                    ? 'Manual set qty (was '.number_format($oldQty, 2).')'
                    : 'Manual stock adjustment');

            InventoryJournalEntry::query()->create([
                'company_id' => $item->company_id,
                'item_id' => $item->id,
                'site_id' => $siteId,
                'source_type' => 'stock_adjustment',
                'source_id' => null,
                'reference' => $ref,
                'qty_change' => $delta,
                'qty_after' => $newQty,
                'unit_cost' => $item->current_cost,
                'user_id' => auth()->id(),
                'notes' => $noteText,
            ]);
        });
    }

    /**
     * Recalculate allocated_qty from open (non-invoiced) sales order lines.
     *
     * @param  list<int>|int  $itemIds
     */
    public function syncAllocatedQty(int|array $itemIds): void
    {
        $ids = array_values(array_unique(array_filter(is_array($itemIds) ? $itemIds : [$itemIds])));
        if ($ids === []) {
            return;
        }

        $allocated = SalesOrderLine::query()
            ->selectRaw('sales_order_lines.item_id, SUM(COALESCE(sales_order_lines.qty_ordered, 0)) as qty')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->whereIn('sales_order_lines.item_id', $ids)
            ->whereNotIn('sales_orders.status', ['Invoiced', 'Cancelled', 'Closed', 'Void'])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.sales_order_id', 'sales_orders.id');
            })
            ->groupBy('sales_order_lines.item_id')
            ->pluck('qty', 'item_id');

        $case = '';
        foreach ($ids as $itemId) {
            $id = (int) $itemId;
            $qty = round((float) ($allocated[$itemId] ?? 0), 4);
            $case .= ' WHEN '.$id.' THEN '.$qty;
        }
        if ($case !== '') {
            Item::query()->whereIn('id', $ids)->update([
                'allocated_qty' => DB::raw('CASE id'.$case.' ELSE allocated_qty END'),
            ]);
        }
    }

    /**
     * Recalculate on_order_qty from open purchase order lines (not yet received).
     *
     * @param  list<int>|int  $itemIds
     */
    public function syncOnOrderQty(int|array $itemIds): void
    {
        $ids = array_values(array_unique(array_filter(is_array($itemIds) ? $itemIds : [$itemIds])));
        if ($ids === []) {
            return;
        }

        $onOrder = PurchaseOrderLine::query()
            ->selectRaw('purchase_order_lines.item_id, SUM(CASE WHEN COALESCE(purchase_order_lines.qty_ordered,0) - COALESCE(purchase_order_lines.qty_received,0) > 0 THEN COALESCE(purchase_order_lines.qty_ordered,0) - COALESCE(purchase_order_lines.qty_received,0) ELSE 0 END) as qty')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereIn('purchase_order_lines.item_id', $ids)
            ->whereNotIn('purchase_orders.status', ['Received', 'Cancelled', 'Closed', 'Void'])
            ->groupBy('purchase_order_lines.item_id')
            ->pluck('qty', 'item_id');

        foreach ($ids as $itemId) {
            Item::query()->whereKey($itemId)->update([
                'on_order_qty' => round((float) ($onOrder[$itemId] ?? 0), 4),
            ]);
        }
    }
}
