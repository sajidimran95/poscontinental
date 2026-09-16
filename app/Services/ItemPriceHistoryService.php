<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPriceHistory;
use App\Models\ItemSupplier;
use Illuminate\Support\Carbon;

class ItemPriceHistoryService
{
    /** Days to surface “sales price updated” alerts on search / add-to-cart. */
    public const ALERT_DAYS = 7;

    public function record(
        Item $item,
        string $changeType,
        string $source,
        ?float $costBefore = null,
        ?float $costAfter = null,
        ?float $listBefore = null,
        ?float $listAfter = null,
        ?int $sourceId = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $userId = null,
    ): ?ItemPriceHistory {
        $costChanged = $costBefore !== null && $costAfter !== null
            && abs((float) $costBefore - (float) $costAfter) > 0.00005;
        $listChanged = $listBefore !== null && $listAfter !== null
            && abs((float) $listBefore - (float) $listAfter) > 0.00005;

        if ($changeType === ItemPriceHistory::TYPE_COST && ! $costChanged) {
            return null;
        }
        if ($changeType === ItemPriceHistory::TYPE_SALES && ! $listChanged) {
            return null;
        }
        if ($changeType === ItemPriceHistory::TYPE_BOTH && ! $costChanged && ! $listChanged) {
            return null;
        }

        if ($changeType === ItemPriceHistory::TYPE_BOTH) {
            if ($costChanged && $listChanged) {
                // keep both
            } elseif ($costChanged) {
                $changeType = ItemPriceHistory::TYPE_COST;
            } elseif ($listChanged) {
                $changeType = ItemPriceHistory::TYPE_SALES;
            }
        }

        return ItemPriceHistory::query()->create([
            'company_id' => (int) $item->company_id,
            'item_id' => (int) $item->id,
            'user_id' => $userId ?? auth()->id(),
            'change_type' => $changeType,
            'source' => $source,
            'source_id' => $sourceId,
            'reference' => $reference,
            'cost_before' => $costChanged ? round((float) $costBefore, 4) : null,
            'cost_after' => $costChanged ? round((float) $costAfter, 4) : null,
            'list_price_before' => $listChanged ? round((float) $listBefore, 4) : null,
            'list_price_after' => $listChanged ? round((float) $listAfter, 4) : null,
            'notes' => $notes,
        ]);
    }

    /**
     * Update item PO/current/standard cost from a purchase order line (does not change sales price).
     */
    public function applyPoUnitCost(Item $item, float $newCost, ?int $poId = null, ?string $poNumber = null, ?int $supplierId = null): bool
    {
        if ($newCost < 0) {
            return false;
        }

        $oldCost = (float) ($item->current_cost ?: $item->standard_cost ?: $item->last_cost);
        if (abs($oldCost - $newCost) <= 0.00005
            && abs((float) $item->standard_cost - $newCost) <= 0.00005
            && abs((float) $item->current_cost - $newCost) <= 0.00005) {
            return false;
        }

        $oldAvg = (float) $item->average_cost;
        $qty = (float) $item->quantity_in_stock;
        if ($qty <= 0 || $oldAvg <= 0) {
            $newAvg = $newCost;
        } else {
            // PO does not add stock yet; keep avg unless it was zero / stale
            $newAvg = $oldAvg;
        }

        $item->update([
            'current_cost' => round($newCost, 4),
            'last_cost' => round($newCost, 4),
            'standard_cost' => round($newCost, 4),
            'average_cost' => round($newAvg > 0 ? $newAvg : $newCost, 4),
        ]);

        if ($supplierId) {
            $link = ItemSupplier::query()
                ->where('item_id', $item->id)
                ->where('supplier_id', $supplierId)
                ->first();
            if ($link) {
                $prevAvg = (float) ($link->avg_cost ?: 0);
                $supAvg = $prevAvg > 0 ? round(($prevAvg + $newCost) / 2, 4) : round($newCost, 4);
                $link->update([
                    'last_cost' => round($newCost, 4),
                    'avg_cost' => $supAvg,
                    'last_received_at' => $link->last_received_at,
                ]);
            }
        }

        $list = (float) $item->list_price;
        $this->record(
            $item->fresh(),
            ItemPriceHistory::TYPE_COST,
            ItemPriceHistory::SOURCE_PO,
            $oldCost,
            $newCost,
            null,
            null,
            $poId,
            $poNumber,
            sprintf(
                'PO cost updated from purchase order ($%s → $%s). Sales price is still $%s — update List Price manually if needed.',
                number_format($oldCost, 2),
                number_format($newCost, 2),
                number_format($list, 2)
            ),
        );

        return true;
    }

    /**
     * After inventory receive: log cost change (item cost fields already updated by caller).
     */
    public function recordReceivingCostChange(
        Item $item,
        float $oldCost,
        float $newCost,
        int $receivingId,
        ?string $receiptNumber = null,
    ): void {
        $list = (float) $item->list_price;
        $this->record(
            $item,
            ItemPriceHistory::TYPE_COST,
            ItemPriceHistory::SOURCE_RECEIVING,
            $oldCost,
            $newCost,
            null,
            null,
            $receivingId,
            $receiptNumber,
            sprintf(
                'Cost updated from inventory receiving ($%s → $%s). Sales price is still $%s — update List Price manually if needed.',
                number_format($oldCost, 2),
                number_format($newCost, 2),
                number_format($list, 2)
            ),
        );
    }

    public function latestSalesPriceChange(Item $item, ?int $withinDays = self::ALERT_DAYS): ?ItemPriceHistory
    {
        $q = ItemPriceHistory::query()
            ->where('item_id', $item->id)
            ->whereIn('change_type', [ItemPriceHistory::TYPE_SALES, ItemPriceHistory::TYPE_BOTH])
            ->whereNotNull('list_price_before')
            ->whereNotNull('list_price_after')
            ->orderByDesc('id');

        if ($withinDays !== null) {
            $q->where('created_at', '>=', Carbon::now()->subDays($withinDays));
        }

        return $q->first();
    }

    public function latestCostChange(Item $item, ?int $withinDays = self::ALERT_DAYS): ?ItemPriceHistory
    {
        $q = ItemPriceHistory::query()
            ->where('item_id', $item->id)
            ->whereIn('change_type', [ItemPriceHistory::TYPE_COST, ItemPriceHistory::TYPE_BOTH])
            ->whereNotNull('cost_before')
            ->whereNotNull('cost_after')
            ->orderByDesc('id');

        if ($withinDays !== null) {
            $q->where('created_at', '>=', Carbon::now()->subDays($withinDays));
        }

        return $q->first();
    }

    /**
     * Alert for cart/search: sales price change preferred, else recent PO/cost increase.
     *
     * @return array{
     *   price_updated:bool,
     *   alert_type:string,
     *   previous_price:?float,
     *   current_price:?float,
     *   previous_cost:?float,
     *   current_cost:?float,
     *   list_price:?float,
     *   price_updated_at:?string,
     *   price_update_message:?string
     * }|null
     */
    public function salesAlertPayload(Item $item): ?array
    {
        $sales = $this->latestSalesPriceChange($item);
        if ($sales) {
            $prev = (float) $sales->list_price_before;
            $curr = (float) $sales->list_price_after;
            $when = optional($sales->created_at)->format('M j, Y');

            return [
                'price_updated' => true,
                'alert_type' => 'sales',
                'previous_price' => $prev,
                'current_price' => $curr,
                'previous_cost' => null,
                'current_cost' => null,
                'list_price' => $curr,
                'price_updated_at' => optional($sales->created_at)->toIso8601String(),
                'price_update_message' => sprintf(
                    'Sales price updated %s: $%s → $%s',
                    $when ?: 'recently',
                    number_format($prev, 2),
                    number_format($curr, 2)
                ),
            ];
        }

        $cost = $this->latestCostChange($item);
        if (! $cost) {
            return null;
        }

        $prev = (float) $cost->cost_before;
        $curr = (float) $cost->cost_after;
        if ($curr <= $prev) {
            // Still alert decreases, but wording differs
        }
        $when = optional($cost->created_at)->format('M j, Y');
        $list = (float) $item->list_price;
        $direction = $curr > $prev ? 'increased' : ($curr < $prev ? 'decreased' : 'changed');

        return [
            'price_updated' => true,
            'alert_type' => 'cost',
            'previous_price' => $prev,
            'current_price' => $curr,
            'previous_cost' => $prev,
            'current_cost' => $curr,
            'list_price' => $list,
            'price_updated_at' => optional($cost->created_at)->toIso8601String(),
            'price_update_message' => sprintf(
                'PO/cost %s %s: $%s → $%s. Sales price is $%s — update List Price if needed.',
                $direction,
                $when ?: 'recently',
                number_format($prev, 2),
                number_format($curr, 2),
                number_format($list, 2)
            ),
        ];
    }

    /**
     * Merge alert fields into a product API array.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mergeIntoProductPayload(array $payload, Item $item): array
    {
        $alert = $this->salesAlertPayload($item);
        if (! $alert) {
            $payload['price_updated'] = false;
            $payload['alert_type'] = null;

            return $payload;
        }

        return array_merge($payload, $alert);
    }

    /**
     * Latest sales or cost alerts for many items (one query each).
     *
     * @param  list<int>  $itemIds
     * @return array<int, array<string, mixed>>
     */
    public function salesAlertsForItemIds(array $itemIds, ?int $withinDays = self::ALERT_DAYS): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($ids === []) {
            return [];
        }

        $since = $withinDays !== null ? Carbon::now()->subDays($withinDays) : null;
        $items = Item::query()->whereIn('id', $ids)->get(['id', 'list_price'])->keyBy('id');
        $out = [];

        $salesRows = ItemPriceHistory::query()
            ->whereIn('item_id', $ids)
            ->whereIn('change_type', [ItemPriceHistory::TYPE_SALES, ItemPriceHistory::TYPE_BOTH])
            ->whereNotNull('list_price_before')
            ->whereNotNull('list_price_after')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->orderByDesc('id')
            ->get();

        foreach ($salesRows as $row) {
            $id = (int) $row->item_id;
            if (isset($out[$id])) {
                continue;
            }
            $prev = (float) $row->list_price_before;
            $curr = (float) $row->list_price_after;
            $when = optional($row->created_at)->format('M j, Y');
            $out[$id] = [
                'price_updated' => true,
                'alert_type' => 'sales',
                'previous_price' => $prev,
                'current_price' => $curr,
                'previous_cost' => null,
                'current_cost' => null,
                'list_price' => $curr,
                'price_updated_at' => optional($row->created_at)->toIso8601String(),
                'price_update_message' => sprintf(
                    'Sales price updated %s: $%s → $%s',
                    $when ?: 'recently',
                    number_format($prev, 2),
                    number_format($curr, 2)
                ),
            ];
        }

        $costRows = ItemPriceHistory::query()
            ->whereIn('item_id', $ids)
            ->whereIn('change_type', [ItemPriceHistory::TYPE_COST, ItemPriceHistory::TYPE_BOTH])
            ->whereNotNull('cost_before')
            ->whereNotNull('cost_after')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->orderByDesc('id')
            ->get();

        foreach ($costRows as $row) {
            $id = (int) $row->item_id;
            if (isset($out[$id])) {
                continue;
            }
            $prev = (float) $row->cost_before;
            $curr = (float) $row->cost_after;
            $when = optional($row->created_at)->format('M j, Y');
            $list = (float) ($items->get($id)?->list_price ?? 0);
            $direction = $curr > $prev ? 'increased' : ($curr < $prev ? 'decreased' : 'changed');
            $out[$id] = [
                'price_updated' => true,
                'alert_type' => 'cost',
                'previous_price' => $prev,
                'current_price' => $curr,
                'previous_cost' => $prev,
                'current_cost' => $curr,
                'list_price' => $list,
                'price_updated_at' => optional($row->created_at)->toIso8601String(),
                'price_update_message' => sprintf(
                    'PO/cost %s %s: $%s → $%s. Sales price is $%s — update List Price if needed.',
                    $direction,
                    $when ?: 'recently',
                    number_format($prev, 2),
                    number_format($curr, 2),
                    number_format($list, 2)
                ),
            ];
        }

        return $out;
    }
}
