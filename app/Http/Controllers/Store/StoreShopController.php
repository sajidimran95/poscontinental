<?php

namespace App\Http\Controllers\Store;

use App\Models\EcomPromotion;
use App\Models\EcomWishlist;
use App\Models\Item;
use App\Support\Store\StoreCatalog;
use App\Support\Store\StoreContext;
use App\Support\Store\StorePromotions;
use Illuminate\Http\Request;

class StoreShopController extends StoreController
{
    /**
     * @param  iterable<EcomPromotion>|null  $promoScope  limit the listing to products covered by these promotions
     * @param  array<string, mixed>  $extra  extra view data (promotion cards, heading subtitle)
     */
    public function index(Request $request, ?string $heading = null, ?iterable $promoScope = null, array $extra = [])
    {
        $companyId = $this->companyId();
        $categories = StoreCatalog::categories($companyId);
        $brands = StoreCatalog::brands($companyId);
        $priceSql = StoreCatalog::listPriceSql();

        $query = StoreCatalog::query($companyId)->with(['prices', 'category']);
        if ($promoScope !== null) {
            StorePromotions::constrain($query, $promoScope);
        }

        $activeCat = $request->filled('category') ? $categories->firstWhere('id', (int) $request->input('category')) : null;
        if ($request->filled('category')) {
            $query->where('items.category_id', (int) $request->input('category'));
        }
        $activeSub = null;
        if ($request->filled('sub')) {
            $query->where('items.subcategory_id', (int) $request->input('sub'));
            $activeSub = $activeCat?->sub_categories->firstWhere('id', (int) $request->input('sub'));
        }
        if ($request->filled('brand')) {
            $brand = $brands->firstWhere('id', (string) $request->input('brand'));
            $query->whereIn('items.manufacturer', $brand->raw ?? ['__none__']);
        }
        if (trim((string) $request->input('q')) !== '') {
            $query->looseSearch(trim((string) $request->input('q')));
        }
        if ($request->boolean('new')) {
            $query->newItems();
        }
        if ($request->boolean('in_stock')) {
            $query->whereRaw('(items.quantity_in_stock - items.allocated_qty) > 0');
        }
        if ($request->filled('min_price')) {
            $query->whereRaw($priceSql.' >= ?', [(float) $request->input('min_price')]);
        }
        if ($request->filled('max_price')) {
            $query->whereRaw($priceSql.' <= ?', [(float) $request->input('max_price')]);
        }

        $sort = (string) $request->input('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderByRaw($priceSql.' ASC'),
            'price_desc' => $query->orderByRaw($priceSql.' DESC'),
            'name' => $query->orderBy('items.description'),
            default => $query->orderByDesc('items.id'),
        };

        $page = $query->paginate(16)->withQueryString();
        $products = $page->setCollection(StoreCatalog::presentMany($page->getCollection(), $this->customer(), $this->company()));

        if ($heading === null) {
            $heading = match (true) {
                $activeSub !== null => $activeSub->name,
                $activeCat !== null => $activeCat->name,
                $request->filled('brand') => (string) $request->input('brand'),
                $request->boolean('new') => 'New Arrivals',
                $request->filled('q') => 'Search: '.$request->input('q'),
                default => 'Shop All Products',
            };
        }

        return view('store.shop.index', [
            'products' => $products,
            'categories' => $categories,
            'subcategories' => $activeCat?->sub_categories ?? collect(),
            'brands' => $brands,
            'sort' => $sort,
            'heading' => $heading,
            'heading_sub' => $extra['heading_sub'] ?? null,
            'promotion_cards' => $extra['promotion_cards'] ?? null,
            'active_promotion_id' => $extra['active_promotion_id'] ?? null,
        ]);
    }

    /** All products on a running promotion; without admin promotions, in-stock items and the top categories. */
    public function promotions(Request $request)
    {
        $companyId = $this->companyId();
        $running = StorePromotions::running($companyId);
        $extra = [
            'promotion_cards' => StorePromotions::cardsOrAuto($companyId),
            'heading_sub' => 'Current deals from '.StoreContext::shopInfo()['name'],
        ];

        if ($running->isEmpty()) {
            $request->merge(['in_stock' => 1]);

            return $this->index($request, 'Promotions', null, $extra);
        }

        return $this->index($request, 'Promotions', $running, $extra);
    }

    public function promotion(Request $request, string $id)
    {
        $companyId = $this->companyId();
        $promo = StorePromotions::running($companyId)->firstWhere('id', (int) $id);
        abort_unless($promo, 404);

        return $this->index($request, $promo->title, [$promo], [
            'promotion_cards' => StorePromotions::cards($companyId),
            'active_promotion_id' => (int) $promo->id,
            'heading_sub' => $promo->subtitle,
        ]);
    }

    public function category(Request $request, string $slug)
    {
        $request->merge(['category' => (int) $slug]);

        return $this->index($request);
    }

    public function brands()
    {
        return view('store.shop.brands', ['brands' => StoreCatalog::brands($this->companyId())]);
    }

    public function product(Request $request, string $slug)
    {
        $companyId = $this->companyId();
        $customer = $this->customer();
        $company = $this->company();

        $item = StoreCatalog::query($companyId)->with(['prices', 'category', 'taxSchedule'])->whereKey((int) $slug)->firstOrFail();
        $product = StoreCatalog::present($item, $customer, $company);

        $related = StoreCatalog::query($companyId)->with(['prices', 'category'])
            ->where('items.id', '!=', $item->id)
            ->when($item->subcategory_id, fn ($q) => $q->where('items.subcategory_id', $item->subcategory_id),
                fn ($q) => $q->where('items.category_id', $item->category_id))
            ->orderByRaw('(items.quantity_in_stock - items.allocated_qty) > 0 DESC')
            ->orderByDesc('items.id')
            ->limit(4)
            ->get();

        $recentIds = collect(session('ecom_recent', []))->reject(fn ($id) => (int) $id === (int) $item->id)->take(4)->values()->all();
        session(['ecom_recent' => array_slice(array_merge([(int) $item->id], $recentIds), 0, 8)]);
        $recent = $recentIds
            ? StoreCatalog::query($companyId)->with(['prices', 'category'])->whereIn('items.id', $recentIds)->get()
                ->sortBy(fn (Item $i) => array_search((int) $i->id, $recentIds, true))->values()
            : collect();

        return view('store.product.show', [
            'product' => $product,
            'related' => StoreCatalog::presentMany($related, $customer, $company),
            'recently_viewed' => StoreCatalog::presentMany($recent, $customer, $company),
            'customer_pricing' => $customer !== null,
            'in_wishlist' => $customer ? EcomWishlist::query()->where('customer_id', $customer->id)->where('item_id', $item->id)->exists() : false,
        ]);
    }
}
