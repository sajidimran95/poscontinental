<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tobacco detection for MSA / Michigan returns.
 * Most imported items have empty tobacco_* fields; category/subcategory is the source of truth.
 */
class TobaccoItem
{
    /**
     * @var list<string>
     */
    private const NAME_NEEDLES = [
        'tobacco',
        'cigaret',
        'cigar',
        'snuff',
        'pipe',
        'nic pou',
        'nicotine',
        'vape',
        'disposable',
        'e-cig',
        'electronic',
        'ryo',
        'tube',
    ];

    public static function isTobacco(?Item $item): bool
    {
        if (! $item) {
            return false;
        }

        if (self::hasExplicitTobaccoFields($item)) {
            return true;
        }

        return self::inferredKind($item) !== null;
    }

    public static function reportsMsa(?Item $item): bool
    {
        return (bool) ($item?->msa_reporting);
    }

    public static function reportsState(?Item $item): bool
    {
        return (bool) ($item?->state_reporting);
    }

    public static function isReportable(?Item $item, string $report = 'state'): bool
    {
        return $report === 'msa'
            ? self::reportsMsa($item)
            : self::reportsState($item);
    }

    public static function matchesProduct(?Item $item, string $product, string $report = 'state'): bool
    {
        if (! self::isReportable($item, $report)) {
            return false;
        }

        $kind = self::kind($item);

        if ($product === 'cigarettes') {
            return $kind === 'cigarettes';
        }

        return ! in_array($kind, ['cigarettes', 'vape', 'ecig'], true);
    }

    public static function kind(?Item $item): string
    {
        if (! $item) {
            return 'otp';
        }

        $type = strtolower(trim((string) ($item->tobacco_product_type ?? '')));
        if ($type !== '') {
            if (in_array($type, ['cigarettes', 'cigarette', 'cig'], true)) {
                return 'cigarettes';
            }

            return $type;
        }

        return self::inferredKind($item) ?? 'otp';
    }

    /**
     * Item form tobacco type (cigarettes / otp / pc1 / ryo) inferred from category.
     */
    public static function suggestedFormType(?Item $item): ?string
    {
        if (! self::isTobacco($item)) {
            return null;
        }

        return match (self::kind($item)) {
            'cigarettes', 'cigarette', 'cig' => 'cigarettes',
            'ryo' => 'ryo',
            'pc1', 'premium_cigar' => 'pc1',
            default => 'otp',
        };
    }

    public static function constrainCigarettesQuery(Builder $query, string $report = 'state'): void
    {
        self::constrainItemQuery($query, $report);
        $query->where(function (Builder $q) {
            $q->whereIn('tobacco_product_type', ['cigarettes', 'cigarette', 'cig'])
                ->orWhereHas('category', function (Builder $c) {
                    $c->whereRaw("LOWER(name) LIKE '%cigaret%'")
                        ->whereRaw("LOWER(name) NOT LIKE '%electronic%'");
                })
                ->orWhereHas('subcategory', function (Builder $s) {
                    $s->whereRaw("LOWER(name) LIKE '%cigaret%'")
                        ->whereRaw("LOWER(name) NOT LIKE '%electronic%'");
                });
        });
    }

    public static function constrainItemQuery(Builder $query, string $report = 'state'): void
    {
        $query->where($report === 'msa' ? 'msa_reporting' : 'state_reporting', true);
    }

    private static function hasExplicitTobaccoFields(Item $item): bool
    {
        $type = strtolower(trim((string) ($item->tobacco_product_type ?? '')));
        if ($type !== '') {
            return true;
        }

        return filled($item->tobacco_brand_code)
            || (int) ($item->tobacco_stick_count ?? 0) > 0
            || (float) ($item->tobacco_total_oz ?? 0) > 0;
    }

    private static function inferredKind(Item $item): ?string
    {
        $haystack = strtolower(trim(
            (string) ($item->category?->name ?? '').' '.(string) ($item->subcategory?->name ?? '')
        ));

        if ($haystack === '') {
            return null;
        }

        if (
            str_contains($haystack, 'electronic')
            || str_contains($haystack, 'vape')
            || str_contains($haystack, 'disposable')
            || str_contains($haystack, 'e-cig')
        ) {
            return 'vape';
        }

        if (str_contains($haystack, 'cigaret')) {
            return 'cigarettes';
        }

        if (str_contains($haystack, 'cigar')) {
            return 'cigarillo';
        }

        if (str_contains($haystack, 'pipe')) {
            return 'pipe';
        }

        if (str_contains($haystack, 'ryo') || str_contains($haystack, 'tube')) {
            return 'ryo';
        }

        if (
            str_contains($haystack, 'tobacco')
            || str_contains($haystack, 'snuff')
            || str_contains($haystack, 'nic pou')
            || str_contains($haystack, 'nicotine')
        ) {
            return 'otp';
        }

        return null;
    }

    /**
     * Tobacco item IDs for a company (cached per request).
     * Uses joins instead of orWhereHas so MSA page counts stay fast.
     *
     * @return list<int>
     */
    public static function itemIds(int $companyId, string $mode = 'all', string $report = 'msa'): array
    {
        $key = $companyId.'|'.$mode.'|'.$report;
        static $memo = [];
        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $flag = $report === 'state' ? 'state_reporting' : 'msa_reporting';

        $q = DB::table('items')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoin('subcategories', 'subcategories.id', '=', 'items.subcategory_id')
            ->where('items.company_id', $companyId)
            ->where('items.'.$flag, true);

        if ($mode === 'cigarettes') {
            $q->where(function ($w) {
                $w->whereIn('items.tobacco_product_type', ['cigarettes', 'cigarette', 'cig'])
                    ->orWhere(function ($x) {
                        $x->whereRaw("LOWER(categories.name) LIKE '%cigaret%'")
                            ->whereRaw("LOWER(categories.name) NOT LIKE '%electronic%'");
                    })
                    ->orWhere(function ($x) {
                        $x->whereRaw("LOWER(subcategories.name) LIKE '%cigaret%'")
                            ->whereRaw("LOWER(subcategories.name) NOT LIKE '%electronic%'");
                    });
            });
        }

        $ids = array_values(array_unique(array_map('intval', $q->pluck('items.id')->all())));
        $memo[$key] = $ids;

        return $ids;
    }
}
