<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

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

    public static function matchesProduct(?Item $item, string $product): bool
    {
        if (! self::isTobacco($item)) {
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

    public static function constrainCigarettesQuery(Builder $query): void
    {
        self::constrainItemQuery($query);
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

    public static function constrainItemQuery(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where(function (Builder $q2) {
                $q2->whereNotNull('tobacco_product_type')->where('tobacco_product_type', '!=', '');
            })->orWhere(function (Builder $q2) {
                $q2->whereNotNull('tobacco_brand_code')->where('tobacco_brand_code', '!=', '');
            })->orWhere('tobacco_stick_count', '>', 0)
                ->orWhere('tobacco_total_oz', '>', 0)
                ->orWhereHas('category', fn (Builder $c) => self::constrainNameQuery($c))
                ->orWhereHas('subcategory', fn (Builder $s) => self::constrainNameQuery($s));
        });
    }

    private static function constrainNameQuery(Builder $query): void
    {
        $query->where(function (Builder $q) {
            foreach (self::NAME_NEEDLES as $needle) {
                $q->orWhereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%']);
            }
        });
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
}
