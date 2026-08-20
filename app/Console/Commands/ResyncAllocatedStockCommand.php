<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Item;
use App\Models\SalesOrderLine;
use App\Services\InventoryService;
use Illuminate\Console\Command;

class ResyncAllocatedStockCommand extends Command
{
    protected $signature = 'inventory:resync-allocated {--company= : Company ID (default: all)}';

    protected $description = 'Rebuild items.allocated_qty from open sales orders';

    public function handle(InventoryService $inventory): int
    {
        $companyOpt = $this->option('company');
        $companies = $companyOpt
            ? Company::query()->whereKey((int) $companyOpt)->get()
            : Company::query()->orderBy('id')->get();

        if ($companies->isEmpty()) {
            $this->error('No company found.');

            return self::FAILURE;
        }

        foreach ($companies as $company) {
            $companyId = (int) $company->id;
            $before = Item::query()->where('company_id', $companyId)->where('allocated_qty', '>', 0)->count();

            $ids = Item::query()
                ->where('company_id', $companyId)
                ->where('allocated_qty', '>', 0)
                ->pluck('id')
                ->merge(
                    SalesOrderLine::query()
                        ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                        ->where('sales_orders.company_id', $companyId)
                        ->whereNotIn('sales_orders.status', ['Invoiced', 'Cancelled', 'Closed', 'Void'])
                        ->whereNotNull('sales_order_lines.item_id')
                        ->distinct()
                        ->pluck('sales_order_lines.item_id')
                )
                ->unique()
                ->values()
                ->all();

            foreach (array_chunk($ids, 250) as $chunk) {
                $inventory->syncAllocatedQty($chunk);
            }

            $after = Item::query()->where('company_id', $companyId)->where('allocated_qty', '>', 0)->count();
            $this->info("Company {$companyId}: recalculated ".count($ids)." items; allocated {$before} → {$after}");
        }

        return self::SUCCESS;
    }
}
