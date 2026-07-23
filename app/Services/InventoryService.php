<?php

namespace App\Services;

use App\Models\CreditMemo;
use App\Models\InventoryJournalEntry;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\ReturnToVendor;
use App\Models\SalesOrder;
use App\Models\StockCount;
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
                $newAvg = $newQty > 0
                    ? (($oldQty * $oldAvg) + ($qty * $cost)) / $newQty
                    : $cost;

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
     * Decrease on-hand when a sales order is invoiced (shipped qty, else ordered).
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
            if ($qty > $onHand + 0.0001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'invoice' => $item->item_code.' cannot invoice qty '.number_format($qty, 2).' — only '.number_format($onHand, 2).' in stock.',
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
            if (! $line->item_id || (float) $line->qty <= 0) {
                continue;
            }

            $item = Item::query()->lockForUpdate()->find($line->item_id);
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
                'notes' => 'Credit Memo restock',
            ]);
        }
    }
}
