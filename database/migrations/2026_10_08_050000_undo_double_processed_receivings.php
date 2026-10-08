<?php

use App\Models\InventoryReceiving;
use App\Services\InventoryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A receiving processed in the app for a PO that a Chief-imported Processed receipt had already
 * fully received (Chief receipts never bumped PO line qty_received, so the PO looked open).
 * Takes the duplicate stock back out, writes reversal journal rows, and removes the receiving.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(InventoryService::class);

        $candidates = DB::table('inventory_receivings as r')
            ->where('r.status', 'Processed')
            ->whereNotNull('r.purchase_order_id')
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('inventory_journal_entries as j')
                    ->where('j.source_type', InventoryReceiving::class)
                    ->whereColumn('j.source_id', 'r.id');
            })
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('inventory_receivings as c')
                    ->whereColumn('c.purchase_order_id', 'r.purchase_order_id')
                    ->whereColumn('c.id', '<', 'r.id')
                    ->where('c.status', 'Processed')
                    ->whereNotExists(function ($j) {
                        $j->selectRaw('1')
                            ->from('inventory_journal_entries as j2')
                            ->where('j2.source_type', InventoryReceiving::class)
                            ->whereColumn('j2.source_id', 'c.id');
                    });
            })
            ->get(['r.id', 'r.purchase_order_id', 'r.receipt_number', 'r.company_id']);

        foreach ($candidates as $rec) {
            $ordered = DB::table('purchase_order_lines')
                ->where('purchase_order_id', $rec->purchase_order_id)
                ->pluck('qty_ordered', 'id');

            $coveredByOthers = DB::table('inventory_receiving_lines as l')
                ->join('inventory_receivings as o', 'o.id', '=', 'l.inventory_receiving_id')
                ->where('o.purchase_order_id', $rec->purchase_order_id)
                ->where('o.status', 'Processed')
                ->where('o.id', '!=', $rec->id)
                ->whereNotNull('l.purchase_order_line_id')
                ->groupBy('l.purchase_order_line_id')
                ->selectRaw('l.purchase_order_line_id as po_line_id, SUM(l.qty_received) as qty')
                ->pluck('qty', 'po_line_id');

            if ($ordered->isEmpty() || (float) $ordered->sum() <= 0) {
                continue;
            }
            $alreadyFull = $ordered->every(fn ($qty, $lineId) => (float) ($coveredByOthers[$lineId] ?? 0) + 0.0001 >= (float) $qty);
            if (! $alreadyFull) {
                continue;
            }

            DB::transaction(function () use ($rec, $ordered, $coveredByOthers, $service) {
                $entries = DB::table('inventory_journal_entries')
                    ->where('source_type', InventoryReceiving::class)
                    ->where('source_id', $rec->id)
                    ->get();

                $itemIds = [];
                foreach ($entries as $e) {
                    $item = DB::table('items')->where('id', $e->item_id)->lockForUpdate()->first(['id', 'quantity_in_stock']);
                    if (! $item) {
                        continue;
                    }
                    $newQty = (float) $item->quantity_in_stock - (float) $e->qty_change;
                    DB::table('items')->where('id', $item->id)->update(['quantity_in_stock' => $newQty]);
                    DB::table('inventory_journal_entries')->insert([
                        'company_id' => $e->company_id,
                        'item_id' => $e->item_id,
                        'site_id' => $e->site_id,
                        'source_type' => InventoryReceiving::class,
                        'source_id' => $rec->id,
                        'reference' => $rec->receipt_number,
                        'qty_change' => -1 * (float) $e->qty_change,
                        'qty_after' => $newQty,
                        'unit_cost' => $e->unit_cost,
                        'user_id' => null,
                        'notes' => 'Duplicate receiving removed (PO already received in Chief)',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $itemIds[] = (int) $e->item_id;
                }

                foreach ($ordered as $lineId => $qty) {
                    DB::table('purchase_order_lines')->where('id', $lineId)->update([
                        'qty_received' => (float) ($coveredByOthers[$lineId] ?? 0),
                    ]);
                }
                DB::table('purchase_orders')->where('id', $rec->purchase_order_id)->update(['status' => 'Received']);

                DB::table('inventory_receiving_lines')->where('inventory_receiving_id', $rec->id)->delete();
                DB::table('inventory_receivings')->where('id', $rec->id)->delete();

                if ($itemIds !== []) {
                    $service->syncOnOrderQty(array_values(array_unique($itemIds)));
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
