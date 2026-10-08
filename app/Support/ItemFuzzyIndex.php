<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Typo-tolerant fallback for item search: when the exact search finds nothing, words that are
 * 1–2 letters off ("rok salt", "cokies") still find the item. Only used after an exact miss.
 */
final class ItemFuzzyIndex
{
    private const CACHE_TTL = 600;

    private const MAX_RESULTS = 300;

    public static function cacheKey(?int $companyId): string
    {
        return 'item_fuzzy_vocab_v1:'.($companyId ?? 'all');
    }

    public static function forget(?int $companyId): void
    {
        Cache::forget(self::cacheKey($companyId));
        Cache::forget(self::cacheKey(null));
    }

    /** True when the phrase has at least one word worth fuzzing (letters, 3+ chars). */
    public static function eligible(string $phrase): bool
    {
        foreach (self::words($phrase) as $w) {
            if (mb_strlen($w) >= 3 && preg_match('/\p{L}/u', $w)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Item ids ranked closest first (fewest letter differences).
     *
     * @return array<int, int> item_id => total score (lower is closer)
     */
    public static function match(string $phrase, ?int $companyId = null): array
    {
        $tokens = self::words($phrase);
        if ($tokens === [] || ! self::eligible($phrase)) {
            return [];
        }

        $vocab = self::vocabulary($companyId);
        if ($vocab === []) {
            return [];
        }

        $totals = null;
        foreach ($tokens as $token) {
            $perItem = [];
            foreach (self::matchingWords($token, $vocab) as $word => $dist) {
                foreach (explode(',', $vocab[$word]) as $id) {
                    $id = (int) $id;
                    if (! isset($perItem[$id]) || $perItem[$id] > $dist) {
                        $perItem[$id] = $dist;
                    }
                }
            }
            if ($perItem === []) {
                return [];
            }

            if ($totals === null) {
                $totals = $perItem;
            } else {
                $next = [];
                foreach ($totals as $id => $sum) {
                    if (isset($perItem[$id])) {
                        $next[$id] = $sum + $perItem[$id];
                    }
                }
                $totals = $next;
            }
            if ($totals === []) {
                return [];
            }
        }

        asort($totals);

        return array_slice($totals, 0, self::MAX_RESULTS, true);
    }

    /** Item codes for the fuzzy matches (for document-line tables that only store item_code). */
    public static function matchCodes(string $phrase, ?int $companyId = null): array
    {
        $ranked = self::match($phrase, $companyId);
        if ($ranked === []) {
            return [];
        }

        $codes = DB::table('items')->whereIn('id', array_keys($ranked))->pluck('item_code', 'id');

        $out = [];
        foreach (array_keys($ranked) as $id) {
            if (isset($codes[$id])) {
                $out[] = (string) $codes[$id];
            }
        }

        return $out;
    }

    /** @return array<string, int> vocabulary word => score */
    private static function matchingWords(string $token, array $vocab): array
    {
        $len = mb_strlen($token);
        $fuzzy = $len >= 3 && preg_match('/\p{L}/u', $token);
        $max = ! $fuzzy ? 0 : ($len <= 4 ? 1 : 2);

        // Score = letters off × 10 + how far the whole word is from what was typed (tie-break: "rok" → ROCK before ROLO).
        $out = [];
        foreach ($vocab as $word => $_ids) {
            $word = (string) $word;
            if (str_starts_with($word, $token)) {
                $out[$word] = min(9, mb_strlen($word) - $len);

                continue;
            }
            if (! $fuzzy) {
                continue;
            }
            $wlen = mb_strlen($word);
            if ($wlen < $len - $max) {
                continue;
            }

            $best = $max + 1;
            $from = max(1, $len - $max);
            $to = min($wlen, $len + $max);
            for ($l = $from; $l <= $to; $l++) {
                $d = levenshtein($token, mb_substr($word, 0, $l));
                if ($d < $best) {
                    $best = $d;
                    if ($best === 1 && $max === 1) {
                        break;
                    }
                }
            }
            if ($best <= $max) {
                $out[$word] = $best * 10 + min(9, levenshtein($token, $word));
            }
        }

        return $out;
    }

    /** @return array<string, string> word => comma separated item ids */
    private static function vocabulary(?int $companyId): array
    {
        return Cache::remember(self::cacheKey($companyId), self::CACHE_TTL, function () use ($companyId) {
            $map = [];
            DB::table('items')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->select(['id', 'item_code', 'description', 'manufacturer'])
                ->orderBy('id')
                ->chunk(2000, function ($rows) use (&$map) {
                    foreach ($rows as $row) {
                        $text = $row->item_code.' '.$row->description.' '.$row->manufacturer;
                        foreach (array_unique(self::words($text)) as $w) {
                            if (mb_strlen($w) < 2) {
                                continue;
                            }
                            $map[$w][] = $row->id;
                        }
                    }
                });

            return array_map(fn ($ids) => implode(',', $ids), $map);
        });
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
