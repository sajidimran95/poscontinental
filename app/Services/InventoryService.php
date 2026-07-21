<?php

namespace App\Services;

use App\Models\InventoryJournalEntry;
use App\Models\InventoryReceiving;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\ReturnToVendor;
use App\Models\StockCount;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function processReceiving(InventoryReceiving $receiving): void
    {
        if ($receiving->status === 'Processed') {
            return;
        }

        DB::transaction(function () use ($receiving) {
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
        if ($rtv->status === 'Returned') {
            return;
        }

        DB::transaction(function () use ($rtv) {
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
                $newQty = max(0, (float) $item->quantity_in_stock - $qty);
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
        if ($count->status === 'Processed') {
            return;
        }

        DB::transaction(function () use ($count) {
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
                'date_processed' => now()->toDateString(),
            ]);
        });
    }
}
