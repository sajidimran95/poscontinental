<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $items = Item::query()
            ->with(['department:id,code,name', 'category:id,code,name', 'subcategory:id,code,name'])
            ->where('company_id', $customer->company_id)
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('manufacturer', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term);
                });
            })
            ->when($request->filled('brand'), fn ($q) => $q->where('manufacturer', $request->string('brand')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('subcategory_id'), fn ($q) => $q->where('subcategory_id', $request->integer('subcategory_id')))
            ->when($request->boolean('new_only'), fn ($q) => $q->newItems())
            ->orderBy('item_code')
            ->paginate(min(100, max(1, $request->integer('per_page', 50))));

        $items->getCollection()->transform(fn (Item $item) => $this->itemPayload($item, $customer));

        return response()->json($items);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $item = Item::query()
            ->with(['department:id,code,name', 'category:id,code,name', 'subcategory:id,code,name', 'prices'])
            ->where('company_id', $customer->company_id)
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->findOrFail($id);

        return response()->json($this->itemPayload($item, $customer, true));
    }

    /** @return array<string, mixed> */
    private function itemPayload(Item $item, Customer $customer, bool $detail = false): array
    {
        $price = (float) $item->list_price;
        $isNew = $item->created_at && $item->created_at->gte(now()->subDays(30));

        $payload = [
            'id' => $item->id,
            'item_code' => $item->item_code,
            'description' => $item->description,
            'unit_of_measure' => $item->unit_of_measure,
            'brand' => $item->manufacturer,
            'list_price' => $price,
            'price' => $price,
            'price_level_id' => $customer->price_level_id,
            'is_new' => $isNew,
            'department' => $item->department ? [
                'id' => $item->department->id,
                'code' => $item->department->code,
                'name' => $item->department->name,
            ] : null,
            'category' => $item->category ? [
                'id' => $item->category->id,
                'code' => $item->category->code,
                'name' => $item->category->name,
            ] : null,
            'subcategory' => $item->subcategory ? [
                'id' => $item->subcategory->id,
                'code' => $item->subcategory->code,
                'name' => $item->subcategory->name,
            ] : null,
        ];

        if ($detail) {
            $payload['extended_description'] = $item->extended_description;
            $payload['primary_upc'] = $item->primary_upc;
            $payload['prices'] = $item->prices->map(fn ($p) => [
                'uom' => $p->uom,
                'price' => (float) $p->price,
                'alias_code' => $p->alias_code,
            ])->values();
        }

        return $payload;
    }
}
