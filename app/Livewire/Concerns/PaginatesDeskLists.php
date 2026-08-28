<?php

namespace App\Livewire\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

trait PaginatesDeskLists
{
    /**
     * Paginate without running a heavy COUNT on every Livewire request.
     * Count is cached briefly when filters are stable (no free-text search).
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
