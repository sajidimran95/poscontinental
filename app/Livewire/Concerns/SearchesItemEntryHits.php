<?php

namespace App\Livewire\Concerns;

use App\Models\Item;
use App\Services\ItemPriceHistoryService;
use App\Support\ItemSearch;

/**
 * Sales-order style type-to-search hits under the item scan bar.
 */
trait SearchesItemEntryHits
{
    /** @var list<array{id: int, item_code: string, description: ?string, price: string}> */
    public array $entryHits = [];

    public function searchEntryHits(?string $code = null): void
    {
        $q = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? '')) ?? '');

        if (mb_strlen($q) < 2) {
            $this->entryHits = [];

            return;
        }

        $rows = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('is_inactive', false);

        ItemSearch::constrain($rows, $q);

        $this->entryHits = $rows
            ->orderBy('item_code')
            ->limit(12)
            ->get(['id', 'item_code', 'description', 'list_price'])
            ->map(fn (Item $item) => [
                'id' => (int) $item->id,
                'item_code' => (string) $item->item_code,
                'description' => $item->description,
                'price' => number_format((float) $item->list_price, 2, '.', ''),
                'price_updated' => false,
                'previous_price' => null,
                'current_price' => null,
                'price_update_message' => null,
            ])
            ->all();

        $alerts = app(ItemPriceHistoryService::class)->salesAlertsForItemIds(
            array_column($this->entryHits, 'id')
        );
        foreach ($this->entryHits as $i => $hit) {
            $id = (int) $hit['id'];
            if (! isset($alerts[$id])) {
                continue;
            }
            $alert = $alerts[$id];
            // Keep sell price; for sales alerts prefer the new list price, for cost keep original list.
            $displayPrice = ($alert['alert_type'] ?? '') === 'sales'
                ? (float) ($alert['current_price'] ?? $hit['price'])
                : (float) ($alert['list_price'] ?? $hit['price']);
            $this->entryHits[$i] = array_merge($hit, $alert, [
                'price' => number_format($displayPrice, 2, '.', ''),
            ]);
        }
    }

    public function pickEntryHit(int $itemId): void
    {
        $this->entryHits = [];
        $this->pickBrowseItem($itemId);
    }
}
