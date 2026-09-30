<?php

namespace App\Support\Store;

/**
 * Read-only product card / detail data for storefront views.
 */
class StoreProduct
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $sku,
        public string $name,
        public ?string $brand,
        public string $image_url,
        public float $price,
        public ?float $compare,
        public bool $is_new,
        public string $pack,
        public ?string $uom,
        public bool $in_stock,
        public bool $can_order,
        public ?int $category_id = null,
        public ?string $category_name = null,
        public ?string $short_description = null,
        public ?string $description = null,
        public ?string $upc = null,
        public float $tax_rate = 0.0,
        public ?string $promo_badge = null,
    ) {}
}
