<?php

namespace App\Support\Store;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Support\ItemMedia;
use App\Support\ItemPricing;
use App\Support\StockPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreCatalog
{
    /** Manufacturer values that are placeholders, not real brands. */
    private const NON_BRANDS = ['', '0', 'OTHER', 'N/A', 'NONE'];

    /** Every active, sellable, priced item of the store company. */
    public static function query(int $companyId): Builder
    {
        return Item::query()
            ->where('items.company_id', $companyId)
            ->where('items.is_inactive', false)
            ->where('items.can_sell', true)
            ->whereRaw(static::listPriceSql().' > 0');
    }

    /** SQL for the general (no price level) sell price, falling back to list price. */
    public static function listPriceSql(): string
    {
        return '(COALESCE((SELECT MIN(ip.price) FROM item_prices ip WHERE ip.item_id = items.id AND ip.price_level_id IS NULL), items.list_price, 0))';
    }

    public static function brandLabel(?string $manufacturer): ?string
    {
        $m = trim((string) $manufacturer);

        return in_array(strtoupper($m), self::NON_BRANDS, true) ? null : preg_replace('/\s+/', ' ', $m);
    }

    /** "DRINKS" -> "Drinks"; mixed-case names like "MI Cigarettes" are kept. */
    public static function displayName(string $name): string
    {
        $name = trim($name);

        return mb_strlen($name) > 3 && $name === mb_strtoupper($name) ? Str::title(mb_strtolower($name)) : $name;
    }

    public static function present(Item $item, ?Customer $customer, ?Company $company): StoreProduct
    {
        $uom = $item->unit_of_measure ?: null;
        $list = ItemPricing::resolve($item, null, $uom);
        $price = $customer
            ? ItemPricing::resolve($item, $customer->price_level_id ? (int) $customer->price_level_id : null, $uom, (int) $customer->id)
            : $list;

        // A promotion never raises the price: customers keep their level price when it is lower.
        $promo = StorePromotions::bestFor($item, $list, (int) $item->company_id);
        if ($promo?->hasDiscount()) {
            $price = min($price, $promo->discountedPrice($list));
        }

        $inStock = $item->available_quantity > 0;
        $category = $item->relationLoaded('category') ? $item->category : null;
        $upc = trim((string) ($item->primary_upc ?? ''));

        return new StoreProduct(
            id: (int) $item->id,
            slug: (string) $item->id,
            sku: (string) $item->item_code,
            name: trim((string) ($item->description ?: $item->item_code)),
            brand: static::brandLabel($item->manufacturer),
            image_url: static::imageUrl($item),
            price: round($price, 2),
            compare: ($customer || $promo) && $list > $price ? round($list, 2) : null,
            is_new: $item->isNew(),
            pack: $uom ? 'Sold per '.$uom : 'Sold each',
            uom: $uom,
            in_stock: $inStock,
            can_order: $inStock || StockPolicy::allowsOversell($company, $item),
            category_id: $category?->id,
            category_name: $category ? static::displayName((string) $category->name) : null,
            short_description: filled($item->product_highlights) ? trim((string) $item->product_highlights) : null,
            description: filled($item->extended_description) ? trim((string) $item->extended_description) : null,
            upc: $upc !== '' ? $upc : null,
            tax_rate: (float) ($item->relationLoaded('taxSchedule') ? ($item->taxSchedule?->rate ?? 0) : 0),
            promo_badge: $promo?->badge(),
        );
    }

    /**
     * @param  iterable<Item>  $items
     * @return Collection<int, StoreProduct>
     */
    public static function presentMany(iterable $items, ?Customer $customer, ?Company $company): Collection
    {
        return collect($items)->map(fn (Item $item) => static::present($item, $customer, $company))->values();
    }

    public static function imageUrl(Item $item): string
    {
        $url = filled($item->image_path) ? ItemMedia::url($item->image_path) : null;

        return $url ?: asset('img/default.png');
    }

    /**
     * Top categories by sellable item count, with their subcategories (desktop menu, chips, footer).
     *
     * @return Collection<int, object>
     */
    public static function navCategories(int $companyId, int $limit = 6): Collection
    {
        return static::categories($companyId)->sortByDesc('products_count')->take($limit)->values();
    }

    public const HEADER_CATEGORY_LIMIT = 6;

    /**
     * Store header menu: the admin's chosen categories (in chosen order), or the top categories when none are chosen.
     *
     * @return Collection<int, object>
     */
    public static function headerCategories(int $companyId): Collection
    {
        $company = StoreContext::company();
        $chosen = $company && (int) $company->id === $companyId
            ? $company->ecommerce_nav_category_ids
            : Company::query()->whereKey($companyId)->first()?->ecommerce_nav_category_ids;
        $chosen = array_values(array_filter(array_map('intval', (array) $chosen)));

        if ($chosen === []) {
            return static::navCategories($companyId, static::HEADER_CATEGORY_LIMIT);
        }

        $byId = static::categories($companyId)->keyBy('id');

        return collect($chosen)
            ->map(fn (int $id) => $byId->get($id))
            ->filter()
            ->take(static::HEADER_CATEGORY_LIMIT)
            ->values();
    }

    /**
     * Active categories that have sellable items.
     *
     * @return Collection<int, object>
     */
    public static function categories(int $companyId): Collection
    {
        $rows = Cache::remember('store.categories.'.$companyId, now()->addMinutes(10), function () use ($companyId) {
            $counts = static::query($companyId)
                ->whereNotNull('category_id')
                ->select('category_id', DB::raw('COUNT(*) as n'))
                ->groupBy('category_id')
                ->pluck('n', 'category_id');

            $subCounts = static::query($companyId)
                ->whereNotNull('subcategory_id')
                ->select('subcategory_id', DB::raw('COUNT(*) as n'))
                ->groupBy('subcategory_id')
                ->pluck('n', 'subcategory_id');

            return Category::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereIn('id', $counts->keys())
                ->with(['subcategories' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (Category $c) => [
                    'id' => (int) $c->id,
                    'name' => static::displayName((string) $c->name),
                    'products_count' => (int) ($counts[$c->id] ?? 0),
                    'subs' => $c->subcategories
                        ->filter(fn ($s) => ($subCounts[$s->id] ?? 0) > 0)
                        ->map(fn ($s) => ['id' => (int) $s->id, 'name' => static::displayName((string) $s->name)])
                        ->values()
                        ->all(),
                ])
                ->all();
        });

        return collect($rows)->map(fn (array $r) => (object) [
            'id' => $r['id'],
            'name' => $r['name'],
            'products_count' => $r['products_count'],
            'sub_categories' => collect($r['subs'])->map(fn ($s) => (object) $s),
        ]);
    }

    /**
     * Brands (item manufacturer) with sellable item counts. `id` is the brand name used in ?brand=.
     *
     * @return Collection<int, object>
     */
    public static function brands(int $companyId): Collection
    {
        $rows = Cache::remember('store.brands.'.$companyId, now()->addMinutes(10), function () use ($companyId) {
            return static::query($companyId)
                ->whereNotNull('manufacturer')
                ->select('manufacturer', DB::raw('COUNT(*) as n'))
                ->groupBy('manufacturer')
                ->get()
                ->map(fn ($r) => ['raw' => (string) $r->manufacturer, 'label' => static::brandLabel($r->manufacturer), 'n' => (int) $r->n])
                ->filter(fn ($r) => $r['label'] !== null)
                ->all();
        });

        return collect($rows)
            ->groupBy('label')
            ->map(fn ($group, $label) => (object) [
                'id' => (string) $label,
                'name' => (string) $label,
                'raw' => $group->pluck('raw')->all(),
                'products_count' => (int) $group->sum('n'),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Most ordered items of the last 30 days (same cache as the Sales app home screen).
     *
     * @return list<int>
     */
    public static function bestSellerIds(int $companyId): array
    {
        return Cache::remember('sale.home.top_sellers.'.$companyId, now()->addHours(6), function () use ($companyId) {
            return DB::table('sales_order_lines as l')
                ->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')
                ->where('o.company_id', $companyId)
                ->where('o.created_at', '>=', now()->subDays(30))
                ->whereNotNull('l.item_id')
                ->groupBy('l.item_id')
                ->orderByRaw('SUM(l.qty_ordered) DESC')
                ->limit(8)
                ->pluck('l.item_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        });
    }

    public static function forgetCache(int $companyId): void
    {
        Cache::forget('store.categories.'.$companyId);
        Cache::forget('store.brands.'.$companyId);
    }
}
