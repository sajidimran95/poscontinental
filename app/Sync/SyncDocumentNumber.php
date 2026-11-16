<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/**
 * Document numbers that stay unique across online + offline desktops.
 * Desktop PCs prepend SyncNode::invoicePrefix() (e.g. PC2-243074).
 */
class SyncDocumentNumber
{
    public static function next(
        string $modelClass,
        int $companyId,
        string $column,
        int $defaultStart,
        ?callable $queryTweaks = null,
    ): string {
        $prefix = SyncNode::invoicePrefix();

        $query = $modelClass::query()->where('company_id', $companyId);
        if ($queryTweaks) {
            $queryTweaks($query);
        }

        if ($prefix !== '') {
            $query->where($column, 'like', $prefix.'%');
        } else {
            // Server keeps unprefixed numeric numbers; ignore desktop-prefixed rows.
            $query->whereRaw($column." REGEXP '^[0-9]+$'");
        }

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $last = (clone $query)->orderByDesc('id')->value($column);
        $n = $last
            ? ((int) preg_replace('/\D/', '', (string) substr((string) $last, strlen($prefix)))) + 1
            : $defaultStart;

        if ($n < 1) {
            $n = $defaultStart;
        }

        while (
            $modelClass::query()
                ->where('company_id', $companyId)
                ->where($column, $prefix.(string) $n)
                ->exists()
        ) {
            $n++;
        }

        return $prefix.(string) $n;
    }

    public static function nextMaxNumeric(
        string $modelClass,
        int $companyId,
        string $column,
        int $defaultStart,
    ): string {
        $prefix = SyncNode::invoicePrefix();

        if ($prefix !== '') {
            $max = (int) $modelClass::query()
                ->where('company_id', $companyId)
                ->where($column, 'like', $prefix.'%')
                ->selectRaw('MAX(CAST(SUBSTRING('.$column.', ?) AS UNSIGNED)) as m', [strlen($prefix) + 1])
                ->value('m');
        } else {
            $max = (int) $modelClass::query()
                ->where('company_id', $companyId)
                ->whereRaw($column." REGEXP '^[0-9]+$'")
                ->max(DB::raw('CAST('.$column.' AS UNSIGNED)'));
        }

        $n = $max > 0 ? $max + 1 : $defaultStart;

        while (
            $modelClass::query()
                ->where('company_id', $companyId)
                ->where($column, $prefix.(string) $n)
                ->exists()
        ) {
            $n++;
        }

        return $prefix.(string) $n;
    }
}
