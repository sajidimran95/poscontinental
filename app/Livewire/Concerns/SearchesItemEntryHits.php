<?php

namespace App\Livewire\Concerns;

use App\Models\Item;
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
            ])
            ->all();
    }

    public function pickEntryHit(int $itemId): void
    {
        $this->entryHits = [];
        $this->pickBrowseItem($itemId);
    }
}
