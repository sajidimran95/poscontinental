<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Shared item search: the exact typed text (words in the typed order) must appear at the start
 * of a word in code / description / manufacturer, or prefix a UPC. "ice" finds "ICE BAGS" but
 * not "PRICE"; "red bull" finds "RED BULL 8OZ" but not "BULL ... RED". Exact code/UPC rank first.
 */
final class ItemSearch
{
    /**
     * Filter an items query (Eloquent Item or query builder on `items`).
     */
    public static function constrain($query, ?string $search): void
    {
        $phrase = self::phrase($search);
        if ($phrase === '') {
            return;
        }

        $eloquent = $query instanceof EloquentBuilder;
        $table = self::tableName($query);
        $prefix = self::escapeLike($phrase).'%';

        $query->where(function ($w) use ($phrase, $prefix, $eloquent, $table) {
            self::whereWordStart($w, $table.'.item_code', $phrase);
            self::whereWordStart($w, $table.'.description', $phrase, 'or');
            self::whereWordStart($w, "IFNULL({$table}.manufacturer, '')", $phrase, 'or');
            $w->orWhereRaw("LOWER(IFNULL({$table}.primary_upc, '')) LIKE LOWER(?)", [$prefix]);

            if ($eloquent) {
                $w->orWhereHas('upcs', fn ($upc) => $upc->whereRaw('LOWER(upc) LIKE LOWER(?)', [$prefix]));
            } else {
                $w->orWhereExists(function ($sub) use ($prefix, $table) {
                    $sub->selectRaw('1')
                        ->from('item_upcs')
                        ->whereColumn('item_upcs.item_id', $table.'.id')
                        ->whereRaw('LOWER(item_upcs.upc) LIKE LOWER(?)', [$prefix]);
                });
            }
        });
    }

    /**
     * Filter document lines that only have item_code + description.
     */
    public static function constrainCodeDescription($query, ?string $search): void
    {
        $phrase = self::phrase($search);
        if ($phrase === '') {
            return;
        }

        $query->where(function ($w) use ($phrase) {
            self::whereWordStart($w, 'item_code', $phrase);
            self::whereWordStart($w, 'description', $phrase, 'or');
        });
    }

    public static function phrase(?string $search): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $search) ?? ''));
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

    /**
     * Most relevant first: exact code/UPC, code starts with, description starts with,
     * description has the whole phrase at a word start, then the rest.
     */
    public static function orderByRelevance($query, ?string $search, string $codeColumn = 'item_code', string $descColumn = 'description', ?string $upcColumn = 'primary_upc'): void
    {
        $phrase = self::phrase($search);
        if ($phrase === '') {
            return;
        }

        $prefix = self::escapeLike($phrase).'%';
        $body = self::phraseRegex($phrase);
        $upcExact = $upcColumn ? "OR LOWER(IFNULL({$upcColumn}, '')) = ?" : '';
        $sql = "CASE
            WHEN LOWER({$codeColumn}) = ? {$upcExact} THEN 0
            WHEN LOWER({$codeColumn}) LIKE ? THEN 1
            WHEN LOWER(TRIM(IFNULL({$descColumn}, ''))) REGEXP ? THEN 2
            WHEN LOWER(TRIM(IFNULL({$descColumn}, ''))) REGEXP ? THEN 3
            WHEN LOWER(IFNULL({$descColumn}, '')) REGEXP ? THEN 4
            ELSE 5 END";
        $bindings = array_merge(
            [$phrase],
            $upcColumn ? [$phrase] : [],
            [$prefix, '^'.$body.'$', '^'.$body, self::wordStartPattern($phrase)]
        );

        $query->orderByRaw($sql, $bindings);
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

    /** MySQL REGEXP: phrase at the start of the text or right after a non letter/digit. */
    public static function wordStartPattern(string $phrase): string
    {
        return '(^|[^[:alnum:]])'.self::phraseRegex($phrase);
    }

    /** Escaped phrase; a typed space matches one or more spaces. */
    protected static function phraseRegex(string $phrase): string
    {
        $words = preg_split('/\s+/u', self::phrase($phrase), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $escaped = array_map(
            fn ($w) => preg_replace('/[.\\\\+*?\[\]^$(){}=!<>|:\-#\/]/', '\\\\$0', $w),
            $words
        );

        return implode('[[:space:]]+', $escaped);
    }

    protected static function whereWordStart($query, string $columnSql, string $token, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $words = preg_split('/\s+/u', self::phrase($token), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $like = '%'.implode('%', array_map(fn ($w) => self::escapeLike($w), $words)).'%';
        $query->{$method}(function ($q) use ($columnSql, $like, $token) {
            $q->whereRaw("LOWER({$columnSql}) LIKE LOWER(?)", [$like])
                ->whereRaw("LOWER({$columnSql}) REGEXP ?", [self::wordStartPattern($token)]);
        });
    }

    protected static function tableName($query): string
    {
        $from = $query instanceof EloquentBuilder ? $query->getQuery()->from : ($query->from ?? 'items');
        $from = is_string($from) ? trim(preg_split('/\s+as\s+/i', $from)[1] ?? $from) : 'items';

        return $from !== '' ? $from : 'items';
    }
}
