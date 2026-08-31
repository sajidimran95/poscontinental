<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\Site;
use App\Models\User;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dummy tobacco invoices for last completed Sun–Sat week so MSA export can be verified
 * against requirment/NASHVILLE_GOODS_DISTRIBUTION_TOB_17033752_20260808.txt layout.
 *
 * php artisan db:seed --class=MsaDummySalesSeeder
 */
class MsaDummySalesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();
        if (! $company) {
            $this->command?->error('No company found.');

            return;
        }

        $companyId = (int) $company->id;
        $weekStart = now()->startOfDay()->startOfWeek(Carbon::SUNDAY)->subWeek();
        $weekEnd = $weekStart->copy()->addDays(6);

        $company->fill([
            'name' => $company->name ?: 'Continental Wholesale Inc',
            'address' => $company->address ?: '3802 TRADE CENTER DR',
            'city' => $company->city ?: 'ANN ARBOR',
            'state' => $company->state ?: 'MI',
            'zip_code' => $company->zip_code ?: '48108',
            'phone' => $company->phone ?: '7346773510',
            'email' => $company->email ?: 'office@continentalwholesale.test',
            'contact_name' => $company->contact_name ?: 'Emran Hossain',
            'fein_no' => $company->fein_no ?: '38-1234567',
            'secondary_tob_number' => $company->secondary_tob_number ?: '17033752',
        ])->save();

        $siteId = Site::query()->where('company_id', $companyId)->value('id');
        $userId = User::query()->where('company_id', $companyId)->value('id');

        $items = $this->seedTobaccoItems($companyId);
        $customers = $this->seedCustomers($companyId);

        $this->seedInvoice(
            $companyId,
            $siteId,
            $userId,
            'MSA-SO-9001',
            'MSA-INV-9001',
            $customers['MSA-C08'],
            $weekStart->copy()->addDays(1)->toDateString(),
            [
                ['MARL-RED-CTN', 2, 72.50],
                ['WHITE-OWL-2PK', 6, 1.19],
                ['BERI-CRUSH-5PC', 3, 12.00],
            ],
            $items
        );

        $this->seedInvoice(
            $companyId,
            $siteId,
            $userId,
            'MSA-SO-9002',
            'MSA-INV-9002',
            $customers['MSA-C16'],
            $weekStart->copy()->addDays(3)->toDateString(),
            [
                ['BLACK-MILD-50', 1, 13.00],
                ['BERI-CRUSH-5PC', 4, 12.00],
                ['NEWP-MENT-CTN', 2, 74.00],
            ],
            $items
        );

        $this->command?->info(
            'MSA dummy tobacco sales seeded for '.$weekStart->toDateString().' → '.$weekEnd->toDateString()
            .'. Open Reports → MSA Report, pick that week, download the TOB file.'
        );
    }

    /**
     * @return array<string, Item>
     */
    protected function seedTobaccoItems(int $companyId): array
    {
        $defs = [
            [
                'item_code' => 'MARL-RED-CTN',
                'description' => 'Marlboro Red Carton',
                'primary_upc' => '028200003123',
                'list_price' => 72.50,
                'current_cost' => 57.25,
                'tobacco_product_type' => 'cigarettes',
                'cigarette_pack_size' => 200,
                'tobacco_stick_count' => 200,
                'tobacco_brand_code' => 'MARL',
            ],
            [
                'item_code' => 'NEWP-MENT-CTN',
                'description' => 'Newport Menthol Carton',
                'primary_upc' => '026200009988',
                'list_price' => 74.00,
                'current_cost' => 58.50,
                'tobacco_product_type' => 'cigarettes',
                'cigarette_pack_size' => 200,
                'tobacco_stick_count' => 200,
                'tobacco_brand_code' => 'NEWP',
            ],
            [
                'item_code' => 'WHITE-OWL-2PK',
                'description' => 'White Owl Foilfresh 2 Cigarillos',
                'primary_upc' => '031700239971',
                'list_price' => 1.19,
                'current_cost' => 0.70,
                'tobacco_product_type' => 'cigarillo',
                'cigarette_pack_size' => 60,
                'tobacco_stick_count' => 2,
                'tobacco_brand_code' => 'WOWL',
                'manu_promotion_code' => 'PROMO',
            ],
            [
                'item_code' => 'BERI-CRUSH-5PC',
                'description' => 'Beri Crush 50000 Puffs Disposable Vape 5PC - Cherry',
                'primary_upc' => '0731723403372',
                'list_price' => 12.00,
                'current_cost' => 7.50,
                'tobacco_product_type' => 'vape',
                'cigarette_pack_size' => 5,
                'tobacco_stick_count' => 1,
                'tobacco_brand_code' => 'BERI',
            ],
            [
                'item_code' => 'BLACK-MILD-50',
                'description' => 'Black & Mild Filter Tip 50 Pipe Tobacco Cigars',
                'primary_upc' => '070137513377',
                'list_price' => 13.00,
                'current_cost' => 8.25,
                'tobacco_product_type' => 'pipe',
                'cigarette_pack_size' => 1,
                'tobacco_stick_count' => 50,
                'tobacco_brand_code' => 'BMIL',
            ],
        ];

        $out = [];
        foreach ($defs as $def) {
            $item = Item::query()->updateOrCreate(
                ['company_id' => $companyId, 'item_code' => $def['item_code']],
                array_merge($def, [
                    'company_id' => $companyId,
                    'standard_cost' => $def['current_cost'],
                    'quantity_in_stock' => 500,
                    'can_sell' => true,
                    'can_order' => true,
                    'is_inactive' => false,
                    'msa_reporting' => true,
                    'state_reporting' => true,
                    'unit_of_measure' => 'EA',
                ])
            );
            $out[$def['item_code']] = $item;
        }

        return $out;
    }

    /**
     * @return array<string, Customer>
     */
    protected function seedCustomers(int $companyId): array
    {
        $defs = [
            'MSA-C08' => [
                'customer_id' => 'MSA-C08',
                'company_name' => '4 Way Market',
                'address' => '1401 Fatherland St',
                'city' => 'Ann Arbor',
                'state' => 'MI',
                'zip_code' => '48104',
                'telephone' => '7345550108',
                'fein_no' => '38-5550108',
            ],
            'MSA-C16' => [
                'customer_id' => 'MSA-C16',
                'company_name' => 'Cloud 9 LLC',
                'address' => '220 Packard St',
                'city' => 'Ann Arbor',
                'state' => 'MI',
                'zip_code' => '48108',
                'telephone' => '7345550116',
                'fein_no' => '38-5550116',
            ],
        ];

        $out = [];
        foreach ($defs as $key => $def) {
            $out[$key] = Customer::query()->updateOrCreate(
                ['company_id' => $companyId, 'customer_id' => $def['customer_id']],
                array_merge($def, [
                    'company_id' => $companyId,
                    'is_inactive' => false,
                ])
            );
        }

        return $out;
    }

    /**
     * @param  array<string, Item>  $items
     * @param  list<array{0:string,1:float|int,2:float}>  $lines
     */
    protected function seedInvoice(
        int $companyId,
        ?int $siteId,
        ?int $userId,
        string $soNumber,
        string $invNumber,
        Customer $customer,
        string $date,
        array $lines,
        array $items,
    ): void {
        if (Invoice::query()->where('company_id', $companyId)->where('invoice_number', $invNumber)->exists()) {
            return;
        }

        DB::transaction(function () use ($companyId, $siteId, $userId, $soNumber, $invNumber, $customer, $date, $lines, $items) {
            $built = [];
            $subtotal = 0.0;
            $lineNo = 1;
            foreach ($lines as [$code, $qty, $price]) {
                $item = $items[$code] ?? null;
                if (! $item) {
                    continue;
                }
                $ext = round($qty * $price, 4);
                $subtotal += $ext;
                $built[] = [
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'uom' => $item->unit_of_measure ?: 'EA',
                    'qty_ordered' => $qty,
                    'qty_shipped' => $qty,
                    'price' => $price,
                    'discount' => 0,
                    'line_total' => $ext,
                    'line_no' => $lineNo++,
                ];
            }

            if ($built === []) {
                return;
            }

            $order = SalesOrder::query()->create([
                'company_id' => $companyId,
                'order_number' => $soNumber,
                'order_type' => 'Sales Order',
                'status' => 'Invoiced',
                'priority' => 'Normal',
                'customer_id' => $customer->id,
                'bill_to_name' => $customer->company_name,
                'bill_to_address' => $customer->address,
                'bill_to_city' => $customer->city,
                'bill_to_state' => $customer->state,
                'bill_to_zip' => $customer->zip_code,
                'ship_to_name' => $customer->company_name,
                'ship_to_address' => $customer->address,
                'ship_to_city' => $customer->city,
                'ship_to_state' => $customer->state,
                'ship_to_zip' => $customer->zip_code,
                'order_date' => $date,
                'ship_from_site_id' => $siteId,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'created_by' => $userId,
            ]);
            foreach ($built as $line) {
                $order->lines()->create($line);
            }

            $invoice = Invoice::query()->create([
                'company_id' => $companyId,
                'invoice_number' => $invNumber,
                'invoice_date' => $date,
                'sales_order_id' => $order->id,
                'customer_id' => $customer->id,
                'status' => 'PAID',
                'subtotal' => $subtotal,
                'invoice_total' => $subtotal,
            ]);

            app(InventoryService::class)->applyInvoiceStock($order->fresh('lines'), $invoice);
        });
    }
}
