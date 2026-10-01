<?php

namespace App\Livewire\Concerns;

/**
 * "Check all" in the item browse popup: every item matching the current search/filters, not just loaded rows.
 */
trait ChecksAllBrowseItems
{
    public const BROWSE_CHECK_ALL_LIMIT = 1000;

    /**
     * @return list<int>
     */
    public function browseAllMatchingIds(): array
    {
        $companyId = (int) auth()->user()->company_id;

        return $this->browseBaseQuery($companyId)
            ->orderBy('id')
            ->limit(self::BROWSE_CHECK_ALL_LIMIT)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
