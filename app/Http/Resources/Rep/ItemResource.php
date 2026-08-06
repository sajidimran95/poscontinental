<?php

namespace App\Http\Resources\Rep;

use App\Models\Item;
use App\Support\ItemPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Item */
class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $priceLevelId = $request->attributes->get('rep_price_level_id');
        $priceLevelId = $priceLevelId !== null ? (int) $priceLevelId : null;

        $unitPrice = ItemPricing::resolve(
            $this->resource,
            $priceLevelId,
            $this->unit_of_measure
        );

        $isNew = $this->resource->isNew();

        return [
            'id' => $this->id,
            'item_code' => $this->item_code,
            'description' => $this->description,
            'extended_description' => $this->extended_description,
            'unit_of_measure' => $this->unit_of_measure,
            'brand' => $this->manufacturer,
            'list_price' => (float) $this->list_price,
            'unit_price' => $unitPrice,
            'available_qty' => (float) $this->available_quantity,
            'is_new' => (bool) $isNew,
            'primary_upc' => $this->primary_upc,
            'department_id' => $this->department_id,
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'code' => $this->department->code,
                'name' => $this->department->name,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name' => $this->category->name,
            ] : null),
            'subcategory' => $this->whenLoaded('subcategory', fn () => $this->subcategory ? [
                'id' => $this->subcategory->id,
                'code' => $this->subcategory->code,
                'name' => $this->subcategory->name,
            ] : null),
            'thumbnail_url' => filled($this->thumbnail_path)
                ? url('/media/'.$this->thumbnail_path)
                : (filled($this->image_path) ? url('/media/'.$this->image_path) : null),
        ];
    }
}
