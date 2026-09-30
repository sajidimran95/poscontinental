<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeskListSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_desk_list_can_be_searched(): void
    {
        $company = Company::query()->create(['code' => 'TST', 'name' => 'Test Co', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));

        $pages = [
            'pages.sales.orders.index', 'pages.sales.customers.index', 'pages.sales.invoices.index',
            'pages.sales.credit-memos.index', 'pages.purchasing.suppliers.index', 'pages.purchasing.orders.index',
            'pages.purchasing.receivings.index', 'pages.purchasing.rtv.index', 'pages.inventory.items.index',
            'pages.inventory.stock-counts.index', 'pages.inventory.bulk-pricing', 'pages.admin.users',
            'pages.admin.email-logs', 'pages.reports.price-list', 'pages.delivery.men', 'pages.delivery.assign',
        ];
        $failures = [];
        foreach ($pages as $page) {
            try {
                Volt::test($page)->set('search', 'abc');
            } catch (\Throwable $e) {
                $failures[] = $page.' => '.get_class($e).': '.substr($e->getMessage(), 0, 300).' @ '.basename($e->getFile()).':'.$e->getLine();
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
