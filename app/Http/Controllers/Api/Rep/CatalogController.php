<?php

namespace App\Http\Controllers\Api\Rep;

use App\Http\Controllers\Controller;
use App\Http\Resources\Rep\ItemResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Item;
use App\Models\Subcategory;
use App\Models\User;
use App\Services\Rep\SalesRepScope;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Item catalog (same Items data as desktop). Optional customer_id for price level pricing.
     */
    public function items(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        $priceLevelId = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::query()->findOrFail($request->integer('customer_id'));
            SalesRepScope::assertCustomerAccess($user, $customer);
            $priceLevelId = $customer->price_level_id ? (int) $customer->price_level_id : null;
        }
        $request->attributes->set('rep_price_level_id', $priceLevelId);

        $query = Item::query()
            ->with(['prices', 'department:id,code,name', 'category:id,code,name', 'subcategory:id,code,name'])
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term)
                        ->orWhere('manufacturer', 'like', $term);
                });
            })
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('subcategory_id'), fn ($q) => $q->where('subcategory_id', $request->integer('subcategory_id')))
            ->when($request->filled('brand'), function ($q) use ($request) {
                $brand = '%'.$request->string('brand').'%';
                $q->where('manufacturer', 'like', $brand);
            })
            ->when($request->boolean('new_only'), fn ($q) => $q->newItems())
            ->orderBy('item_code');

        $paginator = $query->paginate($perPage);

        return ApiResponse::paginated(
            $paginator,
            fn (Item $item) => (new ItemResource($item))->resolve($request)
        );
    }

    public function showItem(Request $request, Item $item): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((int) $item->company_id === (int) $user->company_id, 404);
        abort_unless(! $item->is_inactive && $item->can_sell, 404);

        if ($request->filled('customer_id')) {
            $customer = Customer::query()->findOrFail($request->integer('customer_id'));
            SalesRepScope::assertCustomerAccess($user, $customer);
            $request->attributes->set('rep_price_level_id', $customer->price_level_id);
        }

        $item->load(['prices', 'department', 'category', 'subcategory']);

        return ApiResponse::success([
            'item' => (new ItemResource($item))->resolve($request),
        ]);
    }

    public function departments(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $rows = Department::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return ApiResponse::success(['departments' => $rows]);
    }

    public function categories(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $rows = Category::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->orderBy('name')
            ->get(['id', 'department_id', 'code', 'name']);

        return ApiResponse::success(['categories' => $rows]);
    }

    public function subcategories(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $rows = Subcategory::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->get(['id', 'category_id', 'code', 'name']);

        return ApiResponse::success(['subcategories' => $rows]);
    }

    public function brands(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $brands = Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer')
            ->values();

        return ApiResponse::success(['brands' => $brands]);
    }
}
