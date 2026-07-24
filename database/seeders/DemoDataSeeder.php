<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\PaymentTerm;
use App\Models\PriceLevel;
use App\Models\PricingMethod;
use App\Models\PurchaseOrder;
use App\Models\ReturnToVendor;
use App\Models\RouteLookup;
use App\Models\SalesOrder;
use App\Models\ShipVia;
use App\Models\Site;
use App\Models\Subcategory;
use App\Models\Supplier;
use App\Models\TaxSchedule;
use App\Models\UomSchedule;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'CWI')->first()
            ?? Company::query()->first();

        if (! $company) {
            $this->command?->error('No company found. Run DatabaseSeeder first.');

            return;
        }

        $companyId = (int) $company->id;
        $salesRepId = User::query()->where('company_id', $companyId)->where('email', 'sales@continental.local')->value('id')
            ?? User::query()->where('company_id', $companyId)->value('id');

        $this->seedPriceLevels($companyId);
        $tree = $this->seedDepartments($companyId);
        $lookups = $this->lookupIds($companyId);
        $this->seedSuppliers($companyId);
        $this->seedCustomers($companyId, $lookups, $salesRepId);
        $this->seedItems($companyId, $tree, $lookups);
        $this->seedDocuments($companyId, $lookups, $salesRepId);

        $this->command?->info('Demo data seeded (master + SO / PO / Receiving / Invoice / RTV).');
    }

    protected function seedPriceLevels(int $companyId): void
    {
        foreach ([
            ['code' => 'WS', 'name' => 'Wholesale'],
            ['code' => 'RET', 'name' => 'Retail'],
            ['code' => 'CHN', 'name' => 'Chain'],
            ['code' => 'VIP', 'name' => 'VIP / Preferred'],
        ] as $level) {
            PriceLevel::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $level['code']],
                ['name' => $level['name'], 'is_active' => true]
            );
        }
    }

    /**
     * @return array{depts: array<string, Department>, cats: array<string, Category>, subs: array<string, Subcategory>}
     */
    protected function seedDepartments(int $companyId): array
    {
        $depts = [];
        $cats = [];
        $subs = [];

        $structure = [
            'TOB' => [
                'name' => 'Tobacco',
                'cats' => [
                    'CIG' => ['name' => 'Cigarettes', 'subs' => ['CARTON' => 'Cartons', 'PACK' => 'Packs']],
                    'OTP' => ['name' => 'Other Tobacco', 'subs' => ['CIGAR' => 'Cigars', 'CHEW' => 'Chew']],
                ],
            ],
            'BEV' => [
                'name' => 'Beverages',
                'cats' => [
                    'SODA' => ['name' => 'Soda', 'subs' => ['CAN' => 'Cans', 'BTL' => 'Bottles']],
                    'WATER' => ['name' => 'Water', 'subs' => ['CASE' => 'Cases']],
                ],
            ],
            'SNK' => [
                'name' => 'Snacks',
                'cats' => [
                    'CHIP' => ['name' => 'Chips', 'subs' => ['BAG' => 'Bags']],
                    'CANDY' => ['name' => 'Candy', 'subs' => ['BX' => 'Boxes']],
                ],
            ],
            'GRO' => [
                'name' => 'Grocery',
                'cats' => [
                    'DRY' => ['name' => 'Dry Goods', 'subs' => ['CS' => 'Cases']],
                ],
            ],
        ];

        foreach ($structure as $deptCode => $deptDef) {
            $dept = Department::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $deptCode],
                ['name' => $deptDef['name'], 'is_active' => true]
            );
            $depts[$deptCode] = $dept;

            foreach ($deptDef['cats'] as $catCode => $catDef) {
                $cat = Category::query()->firstOrCreate(
                    ['company_id' => $companyId, 'code' => $catCode],
                    ['department_id' => $dept->id, 'name' => $catDef['name'], 'is_active' => true]
                );
                $cats[$catCode] = $cat;

                foreach ($catDef['subs'] as $subCode => $subName) {
                    $subs[$subCode] = Subcategory::query()->firstOrCreate(
                        ['company_id' => $companyId, 'code' => $subCode],
                        ['category_id' => $cat->id, 'name' => $subName, 'is_active' => true]
                    );
                }
            }
        }

        return compact('depts', 'cats', 'subs');
    }

    /**
     * @return array{term:?int, route:?int, tax:?int, pricing:?int, wholesale:?int, retail:?int, chain:?int, vip:?int}
     */
    protected function lookupIds(int $companyId): array
    {
        return [
            'term' => PaymentTerm::query()->where('company_id', $companyId)->value('id'),
            'route' => RouteLookup::query()->where('company_id', $companyId)->value('id'),
            'tax' => TaxSchedule::query()->where('company_id', $companyId)->value('id'),
            'pricing' => PricingMethod::query()->where('company_id', $companyId)->value('id'),
            'wholesale' => PriceLevel::query()->where('company_id', $companyId)->where('code', 'WS')->value('id'),
            'retail' => PriceLevel::query()->where('company_id', $companyId)->where('code', 'RET')->value('id'),
            'chain' => PriceLevel::query()->where('company_id', $companyId)->where('code', 'CHN')->value('id'),
            'vip' => PriceLevel::query()->where('company_id', $companyId)->where('code', 'VIP')->value('id'),
        ];
    }

    protected function seedSuppliers(int $companyId): void
    {
        foreach ([
            [
                'supplier_id' => 'SUP-ALTRIA',
                'name' => 'Altria Distribution',
                'contact_name' => 'Mike Chen',
                'address' => '6601 W Broad St',
                'city' => 'Richmond',
                'state' => 'VA',
                'zip_code' => '23230',
                'phone1' => '804-555-0101',
                'email' => 'orders@altria-demo.local',
                'is_tobacco_supplier' => true,
                'fein_no' => '54-1234567',
            ],
            [
                'supplier_id' => 'SUP-PEPSI',
                'name' => 'Pepsi Bottling Demo',
                'contact_name' => 'Sara Lopez',
                'address' => '700 Anderson Hill Rd',
                'city' => 'Purchase',
                'state' => 'NY',
                'zip_code' => '10577',
                'phone1' => '914-555-0202',
                'email' => 'sales@pepsi-demo.local',
                'is_tobacco_supplier' => false,
                'fein_no' => '13-9876543',
            ],
            [
                'supplier_id' => 'SUP-FRITO',
                'name' => 'Frito-Lay Wholesale',
                'contact_name' => 'James Okonkwo',
                'address' => '7701 Legacy Dr',
                'city' => 'Plano',
                'state' => 'TX',
                'zip_code' => '75024',
                'phone1' => '972-555-0303',
                'email' => 'wholesale@fritolay-demo.local',
                'is_tobacco_supplier' => false,
                'fein_no' => '75-5551212',
            ],
            [
                'supplier_id' => 'SUP-GEN',
                'name' => 'General Merchandise Co',
                'contact_name' => 'Amy Park',
                'address' => '1200 Industrial Pkwy',
                'city' => 'Detroit',
                'state' => 'MI',
                'zip_code' => '48201',
                'phone1' => '313-555-0404',
                'email' => 'buy@gmc-demo.local',
                'is_tobacco_supplier' => false,
                'fein_no' => '38-4443322',
            ],
        ] as $row) {
            Supplier::query()->firstOrCreate(
                ['company_id' => $companyId, 'supplier_id' => $row['supplier_id']],
                array_merge($row, [
                    'company_id' => $companyId,
                    'country' => 'US',
                    'is_inactive' => false,
                ])
            );
        }
    }

    /**
     * @param  array{term:?int, route:?int, wholesale:?int, retail:?int, chain:?int, vip:?int}  $lookups
     */
    protected function seedCustomers(int $companyId, array $lookups, ?int $salesRepId): void
    {
        foreach ([
            [
                'customer_id' => 'C1001',
                'company_name' => 'Metro Convenience Mart',
                'contact' => 'Raj Patel',
                'address' => '450 Woodward Ave',
                'city' => 'Detroit',
                'state' => 'MI',
                'zip_code' => '48226',
                'telephone' => '313-555-1001',
                'email' => 'metro@demo.local',
                'price_level_id' => $lookups['wholesale'],
                'customer_category' => 'Convenience',
                'account_type' => 'Open Account',
                'credit_limit' => 15000,
                'balance' => 1250.50,
                'fein_no' => '38-1112233',
            ],
            [
                'customer_id' => 'C1002',
                'company_name' => 'Quick Stop Fuels',
                'contact' => 'Lisa Nguyen',
                'address' => '88 Telegraph Rd',
                'city' => 'Southfield',
                'state' => 'MI',
                'zip_code' => '48033',
                'telephone' => '248-555-1002',
                'email' => 'quickstop@demo.local',
                'price_level_id' => $lookups['retail'],
                'customer_category' => 'Retail',
                'account_type' => 'COD',
                'credit_limit' => 5000,
                'balance' => 0,
                'fein_no' => '38-2223344',
            ],
            [
                'customer_id' => 'C1003',
                'company_name' => 'Great Lakes Chain Stores',
                'contact' => 'Tom Bradley',
                'address' => '2100 Corporate Dr',
                'city' => 'Troy',
                'state' => 'MI',
                'zip_code' => '48084',
                'telephone' => '248-555-1003',
                'email' => 'ap@glchain-demo.local',
                'price_level_id' => $lookups['chain'],
                'customer_category' => 'Chain',
                'account_type' => 'Open Account',
                'credit_limit' => 75000,
                'balance' => 8420.00,
                'fein_no' => '38-3334455',
            ],
            [
                'customer_id' => 'C1004',
                'company_name' => 'Harbor Wholesale Dist.',
                'contact' => 'Nina Kowalski',
                'address' => '15 Dock St',
                'city' => 'Port Huron',
                'state' => 'MI',
                'zip_code' => '48060',
                'telephone' => '810-555-1004',
                'email' => 'orders@harbor-demo.local',
                'price_level_id' => $lookups['vip'],
                'customer_category' => 'Distributor',
                'account_type' => 'Open Account',
                'credit_limit' => 100000,
                'balance' => 2200.00,
                'fein_no' => '38-4445566',
            ],
            [
                'customer_id' => 'C1005',
                'company_name' => 'Corner Smoke Shop',
                'contact' => 'Omar Hassan',
                'address' => '301 Gratiot Ave',
                'city' => 'Detroit',
                'state' => 'MI',
                'zip_code' => '48226',
                'telephone' => '313-555-1005',
                'email' => 'corner@demo.local',
                'price_level_id' => $lookups['wholesale'],
                'customer_category' => 'Wholesale',
                'account_type' => 'Cash',
                'credit_limit' => 2500,
                'balance' => 0,
                'fein_no' => '38-5556677',
            ],
        ] as $row) {
            Customer::query()->firstOrCreate(
                ['company_id' => $companyId, 'customer_id' => $row['customer_id']],
                array_merge($row, [
                    'company_id' => $companyId,
                    'country' => 'US',
                    'is_inactive' => false,
                    'payment_term_id' => $lookups['term'],
                    'delivery_route_id' => $lookups['route'],
                    'sales_rep_id' => $salesRepId,
                    'lead_source' => 'Sales Call',
                    'customer_since' => now()->subMonths(18)->toDateString(),
                    'number_of_orders' => 12,
                    'total_sales' => 18500,
                ])
            );
        }
    }

    /**
     * @param  array{depts: array<string, Department>, cats: array<string, Category>, subs: array<string, Subcategory>}  $tree
     * @param  array{tax:?int, pricing:?int, wholesale:?int, retail:?int, chain:?int, vip:?int}  $lookups
     */
    protected function seedItems(int $companyId, array $tree, array $lookups): void
    {
        $uomCtn = UomSchedule::query()->where('company_id', $companyId)->where('code', 'CTN')->value('id');
        $uomCs = UomSchedule::query()->where('company_id', $companyId)->where('code', 'CS')->value('id');
        $uomEa = UomSchedule::query()->where('company_id', $companyId)->where('code', 'EA')->value('id');
        $uomBag = UomSchedule::query()->where('company_id', $companyId)->where('code', 'BAG')->value('id');

        $items = [
            [
                'item_code' => 'MARL-RED-CTN',
                'description' => 'Marlboro Red Carton',
                'dept' => 'TOB', 'cat' => 'CIG', 'sub' => 'CARTON',
                'uom' => 'CTN', 'uom_id' => $uomCtn,
                'list' => 72.50, 'cost' => 57.25, 'qty' => 120, 'upc' => '028200003123',
                'prices' => [
                    [null, 'CTN', 72.50],
                    [$lookups['wholesale'], 'CTN', 70.00],
                    [$lookups['retail'], 'CTN', 78.00],
                    [$lookups['chain'], 'CTN', 68.50],
                    [$lookups['vip'], 'CTN', 66.00],
                ],
            ],
            [
                'item_code' => 'MARL-GOLD-CTN',
                'description' => 'Marlboro Gold Carton',
                'dept' => 'TOB', 'cat' => 'CIG', 'sub' => 'CARTON',
                'uom' => 'CTN', 'uom_id' => $uomCtn,
                'list' => 71.00, 'cost' => 56.00, 'qty' => 85, 'upc' => '028200003456',
                'prices' => [
                    [null, 'CTN', 71.00],
                    [$lookups['wholesale'], 'CTN', 68.50],
                    [$lookups['chain'], 'CTN', 67.00],
                ],
            ],
            [
                'item_code' => 'NEWP-MENT-CTN',
                'description' => 'Newport Menthol Carton',
                'dept' => 'TOB', 'cat' => 'CIG', 'sub' => 'CARTON',
                'uom' => 'CTN', 'uom_id' => $uomCtn,
                'list' => 74.00, 'cost' => 58.50, 'qty' => 64, 'upc' => '026200009988',
                'prices' => [
                    [null, 'CTN', 74.00],
                    [$lookups['wholesale'], 'CTN', 71.50],
                ],
            ],
            [
                'item_code' => 'PEPSI-12PK-CS',
                'description' => 'Pepsi 12oz 12-Pack Case',
                'dept' => 'BEV', 'cat' => 'SODA', 'sub' => 'CAN',
                'uom' => 'CS', 'uom_id' => $uomCs,
                'list' => 18.50, 'cost' => 12.25, 'qty' => 240, 'upc' => '012000001111',
                'prices' => [
                    [null, 'CS', 18.50],
                    [$lookups['wholesale'], 'CS', 17.25],
                    [$lookups['retail'], 'CS', 19.99],
                    [$lookups['chain'], 'CS', 16.50],
                ],
            ],
            [
                'item_code' => 'COKE-12PK-CS',
                'description' => 'Coca-Cola 12oz 12-Pack Case',
                'dept' => 'BEV', 'cat' => 'SODA', 'sub' => 'CAN',
                'uom' => 'CS', 'uom_id' => $uomCs,
                'list' => 18.75, 'cost' => 12.40, 'qty' => 200, 'upc' => '049000001122',
                'prices' => [
                    [null, 'CS', 18.75],
                    [$lookups['wholesale'], 'CS', 17.50],
                    [$lookups['chain'], 'CS', 16.75],
                ],
            ],
            [
                'item_code' => 'WATER-24PK',
                'description' => 'Purified Water 16.9oz 24-Pack',
                'dept' => 'BEV', 'cat' => 'WATER', 'sub' => 'CASE',
                'uom' => 'CS', 'uom_id' => $uomCs,
                'list' => 6.99, 'cost' => 4.10, 'qty' => 350, 'upc' => '078742001234',
                'prices' => [
                    [null, 'CS', 6.99],
                    [$lookups['wholesale'], 'CS', 6.25],
                    [$lookups['retail'], 'CS', 7.49],
                ],
            ],
            [
                'item_code' => 'LAYS-CLASSIC',
                'description' => "Lay's Classic Potato Chips 8oz",
                'dept' => 'SNK', 'cat' => 'CHIP', 'sub' => 'BAG',
                'uom' => 'BAG', 'uom_id' => $uomBag,
                'list' => 3.49, 'cost' => 2.10, 'qty' => 480, 'upc' => '028400001001',
                'prices' => [
                    [null, 'BAG', 3.49],
                    [$lookups['wholesale'], 'BAG', 3.15],
                    [$lookups['chain'], 'BAG', 2.99],
                ],
            ],
            [
                'item_code' => 'DORITOS-NACH',
                'description' => 'Doritos Nacho Cheese 9.25oz',
                'dept' => 'SNK', 'cat' => 'CHIP', 'sub' => 'BAG',
                'uom' => 'BAG', 'uom_id' => $uomBag,
                'list' => 3.79, 'cost' => 2.25, 'qty' => 360, 'upc' => '028400002002',
                'prices' => [
                    [null, 'BAG', 3.79],
                    [$lookups['wholesale'], 'BAG', 3.40],
                ],
            ],
            [
                'item_code' => 'SNICKERS-BX',
                'description' => 'Snickers Bar Box (24ct)',
                'dept' => 'SNK', 'cat' => 'CANDY', 'sub' => 'BX',
                'uom' => 'BX', 'uom_id' => $uomEa,
                'list' => 28.00, 'cost' => 18.50, 'qty' => 90, 'upc' => '040000003003',
                'prices' => [
                    [null, 'BX', 28.00],
                    [$lookups['wholesale'], 'BX', 26.00],
                    [$lookups['vip'], 'BX', 24.50],
                ],
            ],
            [
                'item_code' => 'PAPER-TOWEL',
                'description' => 'Paper Towels 6-Roll Pack',
                'dept' => 'GRO', 'cat' => 'DRY', 'sub' => 'CS',
                'uom' => 'EA', 'uom_id' => $uomEa,
                'list' => 12.99, 'cost' => 8.20, 'qty' => 150, 'upc' => '037000004004',
                'prices' => [
                    [null, 'EA', 12.99],
                    [$lookups['wholesale'], 'EA', 11.50],
                    [$lookups['retail'], 'EA', 13.99],
                ],
            ],
            [
                'item_code' => 'LOW-STOCK-01',
                'description' => 'Demo Low Stock Item',
                'dept' => 'GRO', 'cat' => 'DRY', 'sub' => 'CS',
                'uom' => 'EA', 'uom_id' => $uomEa,
                'list' => 9.99, 'cost' => 5.00, 'qty' => 3, 'upc' => '999000000001',
                'reorder' => 10,
                'prices' => [
                    [null, 'EA', 9.99],
                ],
            ],
        ];

        foreach ($items as $def) {
            $dept = $tree['depts'][$def['dept']] ?? null;
            $cat = $tree['cats'][$def['cat']] ?? null;
            $sub = $tree['subs'][$def['sub']] ?? null;

            $item = Item::query()->firstOrCreate(
                ['company_id' => $companyId, 'item_code' => $def['item_code']],
                [
                    'item_type' => 'Standard Item',
                    'description' => $def['description'],
                    'list_price' => $def['list'],
                    'msrp' => round($def['list'] * 1.2, 2),
                    'standard_cost' => $def['cost'],
                    'current_cost' => $def['cost'],
                    'last_cost' => $def['cost'],
                    'average_cost' => $def['cost'],
                    'quantity_in_stock' => $def['qty'],
                    'allocated_qty' => 0,
                    'reorder_point' => $def['reorder'] ?? 12,
                    'restock_level' => ($def['reorder'] ?? 12) * 4,
                    'lead_time_days' => 3,
                    'department_id' => $dept?->id,
                    'category_id' => $cat?->id,
                    'subcategory_id' => $sub?->id,
                    'uom_schedule_id' => $def['uom_id'],
                    'tax_schedule_id' => $lookups['tax'],
                    'pricing_method_id' => $lookups['pricing'],
                    'unit_of_measure' => $def['uom'],
                    'primary_upc' => $def['upc'],
                    'barcode_format' => 'UPC-A',
                    'item_tracking' => 'None',
                    'can_sell' => true,
                    'can_order' => true,
                    'allow_back_order' => true,
                    'is_inactive' => false,
                ]
            );

            if ($item->wasRecentlyCreated || $item->prices()->count() === 0) {
                if ($item->upcs()->count() === 0) {
                    $item->upcs()->create([
                        'upc' => $def['upc'],
                        'is_primary' => true,
                        'sort_order' => 0,
                    ]);
                }

                foreach ($def['prices'] as $i => [$levelId, $uom, $price]) {
                    $exists = $item->prices()
                        ->where('uom', $uom)
                        ->where('price_level_id', $levelId)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    $item->prices()->create([
                        'price_level_id' => $levelId,
                        'uom' => $uom,
                        'price' => $price,
                        'alias_code' => $i === 0 ? $def['item_code'] : null,
                        'sort_order' => $i,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array{term:?int, route:?int, tax:?int, pricing:?int, wholesale:?int, retail:?int, chain:?int, vip:?int}  $lookups
     */
    protected function seedDocuments(int $companyId, array $lookups, ?int $salesRepId): void
    {
        $siteId = Site::query()->where('company_id', $companyId)->value('id');
        $shipViaId = ShipVia::query()->where('company_id', $companyId)->value('id');
        $adminId = User::query()->where('company_id', $companyId)->where('email', 'admin@gmail.com')->value('id')
            ?? $salesRepId;

        $items = Item::query()
            ->where('company_id', $companyId)
            ->whereIn('item_code', [
                'MARL-RED-CTN', 'MARL-GOLD-CTN', 'PEPSI-12PK-CS', 'COKE-12PK-CS',
                'LAYS-CLASSIC', 'WATER-24PK', 'SNICKERS-BX', 'PAPER-TOWEL',
            ])
            ->get()
            ->keyBy('item_code');

        if ($items->isEmpty()) {
            $this->command?->warn('No demo items found — skipped documents.');

            return;
        }

        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->whereIn('customer_id', ['C1001', 'C1002', 'C1003', 'C1004'])
            ->get()
            ->keyBy('customer_id');

        $suppliers = Supplier::query()
            ->where('company_id', $companyId)
            ->whereIn('supplier_id', ['SUP-ALTRIA', 'SUP-PEPSI', 'SUP-FRITO', 'SUP-GEN'])
            ->get()
            ->keyBy('supplier_id');

        $this->seedPurchaseOrdersAndReceivings($companyId, $items, $suppliers, $lookups, $siteId, $shipViaId, $adminId);
        $this->seedSalesOrdersAndInvoices($companyId, $items, $customers, $lookups, $siteId, $shipViaId, $salesRepId, $adminId);
        $this->seedRtv($companyId, $items, $suppliers, $siteId, $adminId);

        $this->command?->info('Demo documents: POs, receivings, sales orders, invoices, RTV.');
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Item>  $items
     * @param  \Illuminate\Support\Collection<string, Supplier>  $suppliers
     * @param  array{term:?int}  $lookups
     */
    protected function seedPurchaseOrdersAndReceivings(
        int $companyId,
        $items,
        $suppliers,
        array $lookups,
        ?int $siteId,
        ?int $shipViaId,
        ?int $adminId,
    ): void {
        // PO open (not received)
        $poOpen = $this->firstOrCreatePo($companyId, 'DEMO-PO-1001', [
            'order_type' => 'Purchase Order',
            'status' => 'New',
            'supplier_id' => $suppliers->get('SUP-PEPSI')?->id,
            'buyer_id' => $adminId,
            'ship_to_site_id' => $siteId,
            'payment_term_id' => $lookups['term'],
            'ship_via_id' => $shipViaId,
            'requisition_date' => now()->subDays(5)->toDateString(),
            'required_date' => now()->addDays(7)->toDateString(),
            'comments' => 'Demo open PO — not received yet',
        ], [
            ['PEPSI-12PK-CS', 40],
            ['COKE-12PK-CS', 30],
            ['WATER-24PK', 50],
        ], $items);

        // PO partially received
        $poPartial = $this->firstOrCreatePo($companyId, 'DEMO-PO-1002', [
            'order_type' => 'Purchase Order',
            'status' => 'Partially Received',
            'supplier_id' => $suppliers->get('SUP-FRITO')?->id,
            'buyer_id' => $adminId,
            'ship_to_site_id' => $siteId,
            'payment_term_id' => $lookups['term'],
            'ship_via_id' => $shipViaId,
            'requisition_date' => now()->subDays(10)->toDateString(),
            'required_date' => now()->addDays(2)->toDateString(),
            'comments' => 'Demo partial receive PO',
        ], [
            ['LAYS-CLASSIC', 100],
            ['SNICKERS-BX', 20],
        ], $items);

        // Receiving NEW (draft) against open PO
        if ($poOpen && ! InventoryReceiving::query()->where('company_id', $companyId)->where('receipt_number', 'DEMO-RCV-2001')->exists()) {
            $rcv = InventoryReceiving::query()->create([
                'company_id' => $companyId,
                'receipt_number' => 'DEMO-RCV-2001',
                'receipt_date' => now()->toDateString(),
                'purchase_order_id' => $poOpen->id,
                'reference_no' => 'ASN-DEMO-1',
                'status' => 'New',
                'supplier_id' => $poOpen->supplier_id,
                'buyer_id' => $adminId,
                'site_id' => $siteId,
                'comments' => 'Demo receiving — not processed',
            ]);
            $lineNo = 1;
            foreach ($poOpen->lines as $poLine) {
                $rcv->lines()->create([
                    'purchase_order_line_id' => $poLine->id,
                    'item_id' => $poLine->item_id,
                    'item_code' => $poLine->item_code,
                    'description' => $poLine->description,
                    'uom' => $poLine->uom,
                    'qty_ordered' => $poLine->qty_ordered,
                    'qty_received' => $poLine->qty_ordered,
                    'unit_cost' => $poLine->unit_cost,
                    'line_no' => $lineNo++,
                ]);
            }
        }

        // Receiving PROCESSED against partial PO (half of first line)
        if ($poPartial && ! InventoryReceiving::query()->where('company_id', $companyId)->where('receipt_number', 'DEMO-RCV-2002')->exists()) {
            $poPartial->load('lines');
            $rcv = InventoryReceiving::query()->create([
                'company_id' => $companyId,
                'receipt_number' => 'DEMO-RCV-2002',
                'receipt_date' => now()->subDays(2)->toDateString(),
                'purchase_order_id' => $poPartial->id,
                'reference_no' => 'BOL-DEMO-2',
                'status' => 'New',
                'supplier_id' => $poPartial->supplier_id,
                'buyer_id' => $adminId,
                'site_id' => $siteId,
                'comments' => 'Demo processed receiving',
            ]);

            $lineNo = 1;
            foreach ($poPartial->lines as $poLine) {
                $qty = max(1, round((float) $poLine->qty_ordered / 2, 0));
                $rcv->lines()->create([
                    'purchase_order_line_id' => $poLine->id,
                    'item_id' => $poLine->item_id,
                    'item_code' => $poLine->item_code,
                    'description' => $poLine->description,
                    'uom' => $poLine->uom,
                    'qty_ordered' => $poLine->qty_ordered,
                    'qty_received' => $qty,
                    'unit_cost' => $poLine->unit_cost,
                    'line_no' => $lineNo++,
                ]);
            }

            app(InventoryService::class)->processReceiving($rcv->fresh('lines'));
        }

        // Standalone fully received PO + processed receipt
        $poDone = $this->firstOrCreatePo($companyId, 'DEMO-PO-1003', [
            'order_type' => 'Purchase Order',
            'status' => 'New',
            'supplier_id' => $suppliers->get('SUP-GEN')?->id,
            'buyer_id' => $adminId,
            'ship_to_site_id' => $siteId,
            'payment_term_id' => $lookups['term'],
            'ship_via_id' => $shipViaId,
            'requisition_date' => now()->subDays(20)->toDateString(),
            'required_date' => now()->subDays(5)->toDateString(),
            'comments' => 'Demo fully received PO',
        ], [
            ['PAPER-TOWEL', 25],
        ], $items);

        if ($poDone && ! InventoryReceiving::query()->where('company_id', $companyId)->where('receipt_number', 'DEMO-RCV-2003')->exists()) {
            $poDone->load('lines');
            $rcv = InventoryReceiving::query()->create([
                'company_id' => $companyId,
                'receipt_number' => 'DEMO-RCV-2003',
                'receipt_date' => now()->subDays(4)->toDateString(),
                'purchase_order_id' => $poDone->id,
                'status' => 'New',
                'supplier_id' => $poDone->supplier_id,
                'buyer_id' => $adminId,
                'site_id' => $siteId,
                'comments' => 'Demo full receive',
            ]);
            $lineNo = 1;
            foreach ($poDone->lines as $poLine) {
                $rcv->lines()->create([
                    'purchase_order_line_id' => $poLine->id,
                    'item_id' => $poLine->item_id,
                    'item_code' => $poLine->item_code,
                    'description' => $poLine->description,
                    'uom' => $poLine->uom,
                    'qty_ordered' => $poLine->qty_ordered,
                    'qty_received' => $poLine->qty_ordered,
                    'unit_cost' => $poLine->unit_cost,
                    'line_no' => $lineNo++,
                ]);
            }
            app(InventoryService::class)->processReceiving($rcv->fresh('lines'));
        }
    }

    /**
     * @param  list<array{0:string,1:float|int}>  $lines
     * @param  \Illuminate\Support\Collection<string, Item>  $items
     */
    protected function firstOrCreatePo(int $companyId, string $poNumber, array $header, array $lines, $items): ?PurchaseOrder
    {
        $existing = PurchaseOrder::query()->where('company_id', $companyId)->where('po_number', $poNumber)->first();
        if ($existing) {
            return $existing->load('lines');
        }

        $built = [];
        $subtotal = 0.0;
        $lineNo = 1;
        foreach ($lines as [$code, $qty]) {
            $item = $items->get($code);
            if (! $item) {
                continue;
            }
            $cost = (float) $item->current_cost;
            $ext = round($qty * $cost, 4);
            $subtotal += $ext;
            $built[] = [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'uom' => $item->unit_of_measure ?: 'EA',
                'qty_ordered' => $qty,
                'qty_received' => 0,
                'unit_cost' => $cost,
                'extended_cost' => $ext,
                'line_no' => $lineNo++,
            ];
        }

        if ($built === []) {
            return null;
        }

        return DB::transaction(function () use ($companyId, $poNumber, $header, $built, $subtotal) {
            $po = PurchaseOrder::query()->create(array_merge($header, [
                'company_id' => $companyId,
                'po_number' => $poNumber,
                'subtotal' => $subtotal,
                'trade_discount' => 0,
                'freight' => 0,
                'miscellaneous' => 0,
                'tax' => 0,
                'total' => $subtotal,
            ]));
            foreach ($built as $line) {
                $po->lines()->create($line);
            }

            return $po->load('lines');
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Item>  $items
     * @param  \Illuminate\Support\Collection<string, Customer>  $customers
     * @param  array{term:?int, route:?int}  $lookups
     */
    protected function seedSalesOrdersAndInvoices(
        int $companyId,
        $items,
        $customers,
        array $lookups,
        ?int $siteId,
        ?int $shipViaId,
        ?int $salesRepId,
        ?int $adminId,
    ): void {
        $inventory = app(InventoryService::class);

        // Open SO
        $soOpen = $this->firstOrCreateSo($companyId, 'DEMO-SO-3001', [
            'order_type' => 'Sales Order',
            'status' => 'New',
            'priority' => 'Normal',
            'customer_id' => $customers->get('C1001')?->id,
            'sales_rep_id' => $salesRepId,
            'payment_term_id' => $lookups['term'],
            'route_id' => $lookups['route'],
            'ship_via_id' => $shipViaId,
            'ship_from_site_id' => $siteId,
            'order_date' => now()->subDays(1)->toDateString(),
            'required_date' => now()->addDays(3)->toDateString(),
            'customer_po_no' => 'PO-CUST-7781',
            'comments' => 'Demo open sales order',
            'created_by' => $adminId,
        ], [
            ['MARL-RED-CTN', 5, 70.00],
            ['PEPSI-12PK-CS', 10, 17.25],
            ['LAYS-CLASSIC', 24, 3.15],
        ], $items, $customers->get('C1001'));

        if ($soOpen) {
            $inventory->syncAllocatedQty($soOpen->lines->pluck('item_id')->filter()->all());
        }

        // Another open SO
        $soOpen2 = $this->firstOrCreateSo($companyId, 'DEMO-SO-3002', [
            'order_type' => 'Sales Order',
            'status' => 'Open',
            'priority' => 'High',
            'customer_id' => $customers->get('C1003')?->id,
            'sales_rep_id' => $salesRepId,
            'payment_term_id' => $lookups['term'],
            'route_id' => $lookups['route'],
            'ship_via_id' => $shipViaId,
            'ship_from_site_id' => $siteId,
            'order_date' => now()->toDateString(),
            'required_date' => now()->addDays(1)->toDateString(),
            'customer_po_no' => 'GL-99201',
            'comments' => 'Demo chain order — open',
            'created_by' => $adminId,
        ], [
            ['COKE-12PK-CS', 20, 16.75],
            ['WATER-24PK', 40, 6.25],
            ['SNICKERS-BX', 6, 26.00],
        ], $items, $customers->get('C1003'));

        if ($soOpen2) {
            $inventory->syncAllocatedQty($soOpen2->lines->pluck('item_id')->filter()->all());
        }

        // SO to invoice (created as New, then invoiced once)
        $soInvoice = $this->firstOrCreateSo($companyId, 'DEMO-SO-3003', [
            'order_type' => 'Sales Order',
            'status' => 'New',
            'priority' => 'Normal',
            'customer_id' => $customers->get('C1002')?->id,
            'sales_rep_id' => $salesRepId,
            'payment_term_id' => $lookups['term'],
            'route_id' => $lookups['route'],
            'ship_via_id' => $shipViaId,
            'ship_from_site_id' => $siteId,
            'order_date' => now()->subDays(7)->toDateString(),
            'required_date' => now()->subDays(5)->toDateString(),
            'customer_po_no' => 'QS-441',
            'comments' => 'Demo invoiced order',
            'created_by' => $adminId,
        ], [
            ['MARL-GOLD-CTN', 2, 68.50],
            ['PAPER-TOWEL', 4, 11.50],
        ], $items, $customers->get('C1002'));

        if ($soInvoice && $soInvoice->status !== 'Invoiced' && ! $soInvoice->invoice) {
            DB::transaction(function () use ($soInvoice, $inventory, $companyId, $adminId) {
                $order = SalesOrder::query()->with('lines')->lockForUpdate()->find($soInvoice->id);
                if (! $order || $order->status === 'Invoiced') {
                    return;
                }

                $lineDiscount = (float) $order->lines->sum('discount');
                $invoice = Invoice::query()->create([
                    'company_id' => $companyId,
                    'invoice_number' => 'DEMO-INV-4001',
                    'invoice_date' => now()->subDays(5)->toDateString(),
                    'sales_order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'status' => 'NOT PAID',
                    'subtotal' => $order->subtotal,
                    'total_discount' => $lineDiscount,
                    'trade_discount' => $order->trade_discount,
                    'freight' => $order->freight,
                    'miscellaneous' => $order->miscellaneous,
                    'tax' => $order->tax,
                    'invoice_total' => $order->total,
                ]);

                $inventory->applyInvoiceStock($order, $invoice);
                $order->update(['status' => 'Invoiced']);

                InvoicePayment::query()->create([
                    'invoice_id' => $invoice->id,
                    'payment_date' => now()->subDays(3)->toDateString(),
                    'payment_method' => 'Check',
                    'amount' => round((float) $invoice->invoice_total / 2, 2),
                    'comments' => 'Demo partial payment',
                    'user_id' => $adminId,
                ]);
                $invoice->update(['status' => 'NOT PAID']);
            });
        }

        // Fully paid invoiced SO
        $soPaid = $this->firstOrCreateSo($companyId, 'DEMO-SO-3004', [
            'order_type' => 'Sales Order',
            'status' => 'New',
            'priority' => 'Normal',
            'customer_id' => $customers->get('C1004')?->id,
            'sales_rep_id' => $salesRepId,
            'payment_term_id' => $lookups['term'],
            'route_id' => $lookups['route'],
            'ship_via_id' => $shipViaId,
            'ship_from_site_id' => $siteId,
            'order_date' => now()->subDays(14)->toDateString(),
            'required_date' => now()->subDays(12)->toDateString(),
            'customer_po_no' => 'HW-100',
            'comments' => 'Demo paid invoice',
            'created_by' => $adminId,
        ], [
            ['WATER-24PK', 12, 6.00],
            ['LAYS-CLASSIC', 12, 2.99],
        ], $items, $customers->get('C1004'));

        if ($soPaid && $soPaid->status !== 'Invoiced' && ! $soPaid->invoice) {
            DB::transaction(function () use ($soPaid, $inventory, $companyId, $adminId) {
                $order = SalesOrder::query()->with('lines')->lockForUpdate()->find($soPaid->id);
                if (! $order || $order->status === 'Invoiced') {
                    return;
                }

                $invoice = Invoice::query()->create([
                    'company_id' => $companyId,
                    'invoice_number' => 'DEMO-INV-4002',
                    'invoice_date' => now()->subDays(12)->toDateString(),
                    'sales_order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'status' => 'PAID',
                    'subtotal' => $order->subtotal,
                    'total_discount' => 0,
                    'trade_discount' => $order->trade_discount,
                    'freight' => $order->freight,
                    'miscellaneous' => $order->miscellaneous,
                    'tax' => $order->tax,
                    'invoice_total' => $order->total,
                ]);

                $inventory->applyInvoiceStock($order, $invoice);
                $order->update(['status' => 'Invoiced']);

                InvoicePayment::query()->create([
                    'invoice_id' => $invoice->id,
                    'payment_date' => now()->subDays(10)->toDateString(),
                    'payment_method' => 'ACH',
                    'amount' => $invoice->invoice_total,
                    'comments' => 'Demo full payment',
                    'user_id' => $adminId,
                ]);
            });
        }
    }

    /**
     * @param  list<array{0:string,1:float|int,2:float}>  $lines
     * @param  \Illuminate\Support\Collection<string, Item>  $items
     */
    protected function firstOrCreateSo(
        int $companyId,
        string $orderNumber,
        array $header,
        array $lines,
        $items,
        ?Customer $customer,
    ): ?SalesOrder {
        $existing = SalesOrder::query()
            ->with(['lines', 'invoice'])
            ->where('company_id', $companyId)
            ->where('order_number', $orderNumber)
            ->first();
        if ($existing) {
            return $existing;
        }

        $built = [];
        $subtotal = 0.0;
        $lineNo = 1;
        foreach ($lines as [$code, $qty, $price]) {
            $item = $items->get($code);
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
                'qty_shipped' => 0,
                'price' => $price,
                'discount' => 0,
                'line_total' => $ext,
                'line_no' => $lineNo++,
            ];
        }

        if ($built === [] || ! $customer) {
            return null;
        }

        $tax = round($subtotal * 0.06, 2);
        $total = $subtotal + $tax;

        return DB::transaction(function () use ($companyId, $orderNumber, $header, $built, $subtotal, $tax, $total, $customer) {
            $order = SalesOrder::query()->create(array_merge($header, [
                'company_id' => $companyId,
                'order_number' => $orderNumber,
                'bill_to_name' => $customer->company_name,
                'bill_to_phone' => $customer->telephone,
                'bill_to_address' => $customer->address,
                'bill_to_city' => $customer->city,
                'bill_to_state' => $customer->state,
                'bill_to_zip' => $customer->zip_code,
                'ship_to_name' => $customer->company_name,
                'ship_to_phone' => $customer->telephone,
                'ship_to_address' => $customer->address,
                'ship_to_city' => $customer->city,
                'ship_to_state' => $customer->state,
                'ship_to_zip' => $customer->zip_code,
                'subtotal' => $subtotal,
                'trade_discount' => 0,
                'freight' => 0,
                'miscellaneous' => 0,
                'tax' => $tax,
                'total' => $total,
            ]));

            foreach ($built as $line) {
                $order->lines()->create($line);
            }

            return $order->load(['lines', 'invoice']);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Item>  $items
     * @param  \Illuminate\Support\Collection<string, Supplier>  $suppliers
     */
    protected function seedRtv(int $companyId, $items, $suppliers, ?int $siteId, ?int $adminId): void
    {
        if (ReturnToVendor::query()->where('company_id', $companyId)->where('rtv_number', 'DEMO-RTV-5001')->exists()) {
            return;
        }

        $item = $items->get('LAYS-CLASSIC');
        $supplier = $suppliers->get('SUP-FRITO');
        if (! $item || ! $supplier) {
            return;
        }

        $qty = 5;
        $cost = (float) $item->current_cost;
        $ext = round($qty * $cost, 4);

        $rtv = ReturnToVendor::query()->create([
            'company_id' => $companyId,
            'rtv_number' => 'DEMO-RTV-5001',
            'rtv_date' => now()->subDay()->toDateString(),
            'status' => 'New',
            'reference_no' => 'DMG-DEMO',
            'supplier_id' => $supplier->id,
            'requested_by_id' => $adminId,
            'site_id' => $siteId,
            'comments' => 'Demo RTV — damaged bags (not processed)',
            'subtotal' => $ext,
            'discount' => 0,
            'freight' => 0,
            'total' => $ext,
        ]);

        $rtv->lines()->create([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'description' => $item->description,
            'uom' => $item->unit_of_measure ?: 'BAG',
            'qty' => $qty,
            'unit_cost' => $cost,
            'extended_cost' => $ext,
            'line_no' => 1,
        ]);
    }
}
