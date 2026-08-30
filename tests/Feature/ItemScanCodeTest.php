<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemUpc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemScanCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_typed_code_does_not_add_a_different_sku(): void
    {
        $company = Company::query()->create([
            'code' => 'SCN',
            'name' => 'Scan Co',
            'is_active' => true,
        ]);

        $other = Item::query()->create([
            'company_id' => $company->id,
            'item_code' => '2234b',
            'description' => 'Other item',
            'can_sell' => true,
            'is_inactive' => false,
            'primary_upc' => '12',
        ]);
        ItemUpc::query()->create([
            'item_id' => $other->id,
            'upc' => '12',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $this->assertNull(Item::findByScanCode((int) $company->id, '12', 'sell'));
        $this->assertSame('2234b', Item::findByScanCode((int) $company->id, '2234b', 'sell')?->item_code);
    }

    public function test_full_barcode_still_matches_upc(): void
    {
        $company = Company::query()->create([
            'code' => 'UPC',
            'name' => 'Upc Co',
            'is_active' => true,
        ]);

        $item = Item::query()->create([
            'company_id' => $company->id,
            'item_code' => '2234b',
            'description' => 'Barcoded item',
            'can_sell' => true,
            'is_inactive' => false,
            'primary_upc' => '012345678905',
        ]);

        $found = Item::findByScanCode((int) $company->id, '012345678905', 'sell');
        $this->assertNotNull($found);
        $this->assertTrue($found->is($item));
    }
}
