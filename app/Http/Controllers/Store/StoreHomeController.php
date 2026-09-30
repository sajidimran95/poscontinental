<?php

namespace App\Http\Controllers\Store;

use App\Models\Item;
use App\Support\Store\StoreCatalog;
use App\Support\Store\StoreContext;
use App\Support\Store\StorePromotions;

class StoreHomeController extends StoreController
{
    /** Site root: storefront when the online store is on, otherwise the POS login. */
    public function root()
    {
        return StoreContext::enabled() ? $this->index() : redirect('/login');
    }

    public function index()
    {
        $companyId = $this->companyId();
        $company = $this->company();
        $customer = $this->customer();
        $base = fn () => StoreCatalog::query($companyId)->with(['prices', 'category']);

        $featuredItems = $base()->whereRaw('(items.quantity_in_stock - items.allocated_qty) > 0')
            ->whereNotNull('last_sold_at')
            ->orderByDesc('last_sold_at')
            ->limit(8)
            ->get();
        if ($featuredItems->isEmpty()) {
            $featuredItems = $base()->orderByDesc('id')->limit(8)->get();
        }

        $newItems = $base()->newItems()->orderByDesc('created_at')->limit(8)->get();
        if ($newItems->isEmpty()) {
            $newItems = $base()->orderByDesc('id')->limit(8)->get();
        }

        $bestIds = StoreCatalog::bestSellerIds($companyId);
        $bestItems = $bestIds
            ? $base()->whereIn('items.id', $bestIds)->get()->sortBy(fn (Item $i) => array_search((int) $i->id, $bestIds, true))->values()
            : collect();
        if ($bestItems->isEmpty()) {
            $bestItems = $featuredItems;
        }

        $categories = StoreCatalog::navCategories($companyId, 12);
        $promos = StorePromotions::cardsOrAuto($companyId, 5);

        $findCat = fn (string $needle) => optional($categories->first(fn ($c) => stripos($c->name, $needle) !== false))->id;
        $heroLinks = array_filter([
            'tobacco' => ($id = $findCat('tobacco')) ? route('ecommerce.category', $id) : null,
            'candy' => ($id = $findCat('candy') ?? $findCat('snack')) ? route('ecommerce.category', $id) : null,
        ]);

        return view('store.home.index', [
            'featured' => StoreCatalog::presentMany($featuredItems, $customer, $company),
            'new_arrivals' => StoreCatalog::presentMany($newItems, $customer, $company),
            'best_sellers' => StoreCatalog::presentMany($bestItems, $customer, $company),
            'categories' => $categories,
            'promos' => $promos,
            'hero_links' => $heroLinks,
        ]);
    }
}
