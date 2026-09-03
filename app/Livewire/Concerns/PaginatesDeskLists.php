<?php

namespace App\Livewire\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait PaginatesDeskLists
{
    public int $listLimit = 40;

    protected function deskListPageSize(): int
    {
        return 40;
    }

    public function loadMoreList(): void
    {
        $this->listLimit += $this->deskListPageSize();
    }

    public function resetPage(): void
    {
        $this->resetDeskList();
    }

    protected function resetDeskList(): void
    {
        $this->listLimit = $this->deskListPageSize();
    }

    /**
     * Server-side infinite scroll: first N rows, then load more on scroll.
     *
     * @return array{rows: Collection, hasMore: bool, shown: int}
     */
    protected function scrollDeskList($query): array
    {
        $take = max($this->deskListPageSize(), (int) $this->listLimit);
        $rows = (clone $query)->limit($take + 1)->get();
        $hasMore = $rows->count() > $take;
        $items = $hasMore ? $rows->take($take)->values() : $rows;

        return [
            'rows' => $items,
            'hasMore' => $hasMore,
            'shown' => $items->count(),
        ];
    }

    /**
     * @deprecated Use scrollDeskList() for desk lists.
     */
    protected function paginateDeskList($query, string $cacheKey, int $perPage = 50, int $ttl = 20): LengthAwarePaginatorContract
    {
        $page = method_exists($this, 'getPage') ? max(1, (int) $this->getPage()) : 1;
        $pageName = method_exists($this, 'getPageName') ? $this->getPageName() : 'page';

        $countQuery = clone $query;
        if (method_exists($countQuery, 'setEagerLoads')) {
            $countQuery->setEagerLoads([]);
        }

        $rememberKey = $cacheKey.'.'.md5($countQuery->toSql().'|'.json_encode($countQuery->getBindings()));
        $total = $ttl > 0
            ? (int) Cache::remember($rememberKey, $ttl, fn () => (int) $countQuery->toBase()->getCountForPagination())
            : (int) $countQuery->toBase()->getCountForPagination();

        $rows = $query->forPage($page, $perPage)->get();

        return new LengthAwarePaginator($rows, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }
}
