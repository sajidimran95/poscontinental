<?php

namespace App\Support\Store;

use App\Models\EcomPromotion;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Running storefront promotions and which one applies to an item.
 */
class StorePromotions
{
    /**
     * Running promotions of the company (cached for the current request), each with
     * `brand_raw` (manufacturer values) and `item_ids` resolved.
     *
     * @return Collection<int, EcomPromotion>
     */
    public static function running(int $companyId): Collection
    {
        $key = 'store.promotions.'.$companyId;
        $cached = request()->attributes->get($key);
        if ($cached instanceof Collection) {
            return $cached;
        }

        $promos = EcomPromotion::query()
            ->where('company_id', $companyId)
            ->running()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->each(fn (EcomPromotion $p) => static::resolveScope($p, $companyId));

        request()->attributes->set($key, $promos);

        return $promos;
    }

    /** Set `item_ids` and `brand_raw` (manufacturer values behind the brand label) on a promotion. */
    public static function resolveScope(EcomPromotion $promo, int $companyId): EcomPromotion
    {
        $promo->setAttribute('item_ids', $promo->target === 'items'
            ? $promo->items()->pluck('items.id')->map(fn ($id) => (int) $id)->all()
            : []);
        $promo->setAttribute('brand_raw', $promo->target === 'brand' && filled($promo->brand)
            ? (StoreCatalog::brands($companyId)->firstWhere('id', $promo->brand)->raw ?? [$promo->brand])
            : []);

        return $promo;
    }

    public static function forget(int $companyId): void
    {
        request()->attributes->remove('store.promotions.'.$companyId);
    }

    /** @param  EcomPromotion  $promo  with scope resolved (see resolveScope) */
    public static function covers(EcomPromotion $promo, Item $item): bool
    {
        return match ($promo->target) {
            'category' => $promo->category_id && (int) $item->category_id === (int) $promo->category_id,
            'brand' => in_array((string) $item->manufacturer, $promo->brand_raw ?? [], true),
            'items' => in_array((int) $item->id, $promo->item_ids ?? [], true),
            default => false,
        };
    }

    /** The promotion giving this item the lowest price (or the first showcase promotion covering it). */
    public static function bestFor(Item $item, float $listPrice, int $companyId): ?EcomPromotion
    {
        $best = null;
        $bestPrice = null;
        foreach (static::running($companyId) as $promo) {
            if (! static::covers($promo, $item)) {
                continue;
            }
            $price = $promo->discountedPrice($listPrice);
            if ($best === null || $price < $bestPrice) {
                $best = $promo;
                $bestPrice = $price;
            }
        }

        return $best;
    }

    /**
     * Limit an item query to products covered by the given promotions.
     *
     * @param  iterable<EcomPromotion>  $promos
     */
    public static function constrain(Builder $query, iterable $promos): Builder
    {
        $categoryIds = [];
        $manufacturers = [];
        $itemIds = [];
        foreach ($promos as $p) {
            match ($p->target) {
                'category' => $p->category_id ? $categoryIds[] = (int) $p->category_id : null,
                'brand' => array_push($manufacturers, ...($p->brand_raw ?? [])),
                'items' => array_push($itemIds, ...($p->item_ids ?? [])),
                default => null,
            };
        }

        if (! $categoryIds && ! $manufacturers && ! $itemIds) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($categoryIds, $manufacturers, $itemIds) {
            if ($categoryIds) {
                $q->orWhereIn('items.category_id', array_unique($categoryIds));
            }
            if ($manufacturers) {
                $q->orWhereIn('items.manufacturer', array_unique($manufacturers));
            }
            if ($itemIds) {
                $q->orWhereIn('items.id', array_unique($itemIds));
            }
        });
    }

    /** Number of sellable products a promotion covers. */
    public static function productCount(EcomPromotion $promo, int $companyId): int
    {
        return static::constrain(StoreCatalog::query($companyId), [$promo])->count();
    }

    /**
     * Admin promotions when any are running, otherwise automatic cards for the top categories.
     *
     * @return list<array{id:int,href:string,title:string,sub:?string,tag:?string,count:int}>
     */
    public static function cardsOrAuto(int $companyId, ?int $limit = null): array
    {
        return static::cards($companyId, $limit) ?: static::autoCards($companyId);
    }

    /**
     * Fallback promotion cards: the 5 biggest categories.
     *
     * @return list<array{id:int,href:string,title:string,sub:?string,tag:?string,count:int}>
     */
    public static function autoCards(int $companyId): array
    {
        $tags = ['HOT', 'TOP', 'NEW', 'DEALS', 'STOCK UP'];

        return StoreCatalog::navCategories($companyId, count($tags))
            ->values()
            ->map(fn ($cat, $i) => [
                'id' => 0,
                'href' => route('ecommerce.category', $cat->id),
                'title' => $cat->name,
                'sub' => null,
                'tag' => $tags[$i],
                'count' => (int) $cat->products_count,
            ])
            ->all();
    }

    /**
     * Card data for running admin promotions.
     *
     * @return list<array{id:int,href:string,title:string,sub:?string,tag:?string,count:int}>
     */
    public static function cards(int $companyId, ?int $limit = null): array
    {
        return static::running($companyId)
            ->when($limit, fn ($c) => $c->take($limit))
            ->map(fn (EcomPromotion $p) => [
                'id' => (int) $p->id,
                'href' => route('ecommerce.promotion', $p->id),
                'title' => $p->title,
                'sub' => $p->subtitle,
                'tag' => $p->badge() ?? 'PROMO',
                'count' => static::productCount($p, $companyId),
            ])
            ->values()
            ->all();
    }
}
