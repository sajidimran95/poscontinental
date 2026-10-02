<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ItemUomDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_edit_show_uom_from_pricing_when_item_uom_blank(): void
    {
        $company = Company::query()->create(['code' => 'TST', 'name' => 'Test Co', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));

        $withPrice = Item::query()->create([
            'company_id' => $company->id,
            'item_code' => 'UOM1',
            'description' => 'UOM from price',
            'unit_of_measure' => null,
            'list_price' => 5,
            'can_sell' => true,
            'is_inactive' => false,
        ]);
        ItemPrice::query()->create(['item_id' => $withPrice->id, 'uom' => 'BX', 'price' => 5, 'sort_order' => 0]);

        $noPrice = Item::query()->create([
            'company_id' => $company->id,
            'item_code' => 'UOM2',
            'description' => 'UOM default EA',
            'unit_of_measure' => null,
            'list_price' => 0,
            'can_sell' => true,
            'is_inactive' => false,
        ]);

        $this->assertSame('BX', $withPrice->fresh()->displayUom());
        $this->assertSame('EA', $noPrice->fresh()->displayUom());

        Volt::test('pages.inventory.items.index')->assertSee('BX')->assertSee('EA');

        $this->get(route('inventory.items.edit', $withPrice))->assertOk()->assertSee('BX');
        $this->get(route('inventory.items.edit', $noPrice))->assertOk()->assertSee('EA');
    }
}
