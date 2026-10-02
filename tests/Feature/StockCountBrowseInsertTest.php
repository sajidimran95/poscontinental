<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class StockCountBrowseInsertTest extends TestCase
{
    use RefreshDatabase;

    public function test_insert_checked_adds_all_items_with_blank_counted(): void
    {
        $company = Company::query()->create(['code' => 'TST', 'name' => 'Test Co', 'is_active' => true, 'allow_negative_stock' => true]);
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));
        Site::query()->create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'is_active' => true]);

        $ids = [];
        foreach (['A1', 'B2', 'C3'] as $i => $code) {
            $ids[] = Item::query()->create([
                'company_id' => $company->id,
                'item_code' => $code,
                'description' => 'Item '.$code,
                'quantity_in_stock' => 10 - ($i * 20), // 10, -10, -30
                'allocated_qty' => 1,
                'list_price' => 1,
                'unit_of_measure' => 'BX',
                'can_sell' => true,
                'is_inactive' => false,
            ])->id;
        }

        $page = Volt::test('pages.inventory.stock-counts.form')
            ->call('insertBrowseChecked', $ids);

        $lines = collect($page->get('lines'))->filter(fn ($l) => filled($l['item_code'] ?? null))->values();
        $this->assertCount(3, $lines);
        $this->assertSame('A1', $lines[0]['item_code']);
        $this->assertSame('B2', $lines[1]['item_code']);
        $this->assertSame('C3', $lines[2]['item_code']);
        $this->assertSame('0', $lines[0]['counted']);
        $this->assertNull($lines[0]['count_time']);
        $this->assertSame('-10.0000', $lines[1]['in_stock']);
        $page->assertSee('3 items added to the count');
    }
}
