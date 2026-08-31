<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Shared item search: case-insensitive, word tokens (any order), partial match.
 */
final class ItemSearch
{
    /**
     * Filter an items query (Eloquent Item or query builder on `items`).
     */
    public static function constrain($query, ?string $search): void
    {
        $tokens = self::tokens($search);
        if ($tokens === []) {
            return;
        }

        $eloquent = $query instanceof EloquentBuilder;

        $query->where(function ($inner) use ($tokens, $eloquent) {
            foreach ($tokens as $token) {
                $like = '%'.self::escapeLike($token).'%';
                $inner->where(function ($w) use ($like, $eloquent) {
                    $w->whereRaw('LOWER(item_code) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(description) LIKE LOWER(?)', [$like])
                        ->orWhereRaw("LOWER(IFNULL(extended_description, '')) LIKE LOWER(?)", [$like])
                        ->orWhereRaw("LOWER(IFNULL(primary_upc, '')) LIKE LOWER(?)", [$like])
                        ->orWhereRaw("LOWER(IFNULL(manufacturer, '')) LIKE LOWER(?)", [$like]);

                    if ($eloquent) {
                        $w->orWhereHas('upcs', fn ($upc) => $upc->whereRaw('LOWER(upc) LIKE LOWER(?)', [$like]));
                    } else {
                        $w->orWhereExists(function ($sub) use ($like) {
                            $sub->selectRaw('1')
                                ->from('item_upcs')
                                ->whereColumn('item_upcs.item_id', 'items.id')
                                ->whereRaw('LOWER(item_upcs.upc) LIKE LOWER(?)', [$like]);
                        });
                    }
                });
            }
        });
    }

    /**
     * Filter document lines that only have item_code + description.
     */
    public static function constrainCodeDescription($query, ?string $search): void
    {
        $tokens = self::tokens($search);
        if ($tokens === []) {
            return;
        }

        $query->where(function ($inner) use ($tokens) {
            foreach ($tokens as $token) {
                $like = '%'.self::escapeLike($token).'%';
                $inner->where(function ($w) use ($like) {
                    $w->whereRaw('LOWER(item_code) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(description) LIKE LOWER(?)', [$like]);
                });
            }
        });
    }

    public static function constrainColumn($query, string $column, ?string $search): void
    {
        $tokens = self::tokens($search);
        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $token) {
            $query->whereRaw('LOWER('.$column.') LIKE LOWER(?)', ['%'.self::escapeLike($token).'%']);
        }
    }

    /** @return list<string> */
    public static function tokens(?string $search): array
    {
        $raw = trim((string) $search);
        if ($raw === '') {
            return [];
        }

        return preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
