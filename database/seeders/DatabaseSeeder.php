<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CigaretteTaxClass;
use App\Models\Company;
use App\Models\CustomerLookupOption;
use App\Models\Department;
use App\Models\DiscountSchedule;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\PaymentTerm;
use App\Models\PriceLevel;
use App\Models\PricingMethod;
use App\Models\PurchaseLimitSchedule;
use App\Models\Role;
use App\Models\RouteLookup;
use App\Models\ShipVia;
use App\Models\Site;
use App\Models\Subcategory;
use App\Models\TaxSchedule;
use App\Models\UomSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['code' => 'CWI'],
            ['name' => 'Continental Wholesale Inc', 'is_active' => true]
        );

        $site = Site::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'WS'],
            ['name' => 'Wholesale Site', 'is_active' => true]
        );

        foreach ([
            ['name' => 'admin', 'label' => 'Administrator'],
            ['name' => 'sales_rep', 'label' => 'Sales Rep'],
            ['name' => 'buyer', 'label' => 'Buyer'],
            ['name' => 'warehouse', 'label' => 'Warehouse'],
        ] as $role) {
            Role::query()->firstOrCreate(
                ['name' => $role['name']],
                ['label' => $role['label']]
            );
        }

        $adminRole = Role::query()->where('name', 'admin')->first();
        $salesRole = Role::query()->where('name', 'sales_rep')->first();

        User::query()->firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'company_id' => $company->id,
                'site_id' => $site->id,
                'role_id' => $adminRole?->id,
                'name' => 'POS Admin',
                'username' => 'admin@gmail.com',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'sales@continental.local'],
            [
                'company_id' => $company->id,
                'site_id' => $site->id,
                'role_id' => $salesRole?->id,
                'name' => 'Sales Rep',
                'username' => 'sales',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $this->seedLookups($company->id);
        $this->seedSampleItems($company->id);
        $this->call(DemoDataSeeder::class);

        $this->command?->info('Database seed completed (safe — skips existing records).');
    }

    protected function seedSampleItems(int $companyId): void
    {
        $dept = Department::query()->where('company_id', $companyId)->where('code', 'TOB')->first();
        $cat = Category::query()->where('company_id', $companyId)->where('code', 'CIG')->first();
        $sub = Subcategory::query()->where('company_id', $companyId)->where('code', 'CARTON')->first();
        $uom = UomSchedule::query()->where('company_id', $companyId)->where('code', 'CTN')->first()
            ?? UomSchedule::query()->where('company_id', $companyId)->first();
        $tax = TaxSchedule::query()->where('company_id', $companyId)->first();
        $pricing = PricingMethod::query()->where('company_id', $companyId)->first();

        $item = Item::query()->firstOrCreate(
            ['company_id' => $companyId, 'item_code' => 'MARL-RED-CTN'],
            [
                'item_type' => 'Standard Item',
                'class' => 'CIG',
                'description' => 'Marlboro Red Carton',
                'extended_description' => 'Premium cigarette carton — wholesale pack.',
                'product_highlights' => "Full flavor\nCarton of 10 packs",
                'list_price' => 72.50,
                'msrp' => 89.99,
                'standard_cost' => 58.00,
                'current_cost' => 57.25,
                'last_cost' => 57.25,
                'average_cost' => 57.80,
                'quantity_in_stock' => 120,
                'reorder_point' => 24,
                'restock_level' => 96,
                'lead_time_days' => 3,
                'department_id' => $dept?->id,
                'category_id' => $cat?->id,
                'subcategory_id' => $sub?->id,
                'uom_schedule_id' => $uom?->id,
                'tax_schedule_id' => $tax?->id,
                'pricing_method_id' => $pricing?->id,
                'unit_of_measure' => 'CTN',
                'primary_upc' => '028200003123',
                'barcode_format' => 'UPC-A',
                'item_tracking' => 'None',
                'can_sell' => true,
                'can_order' => true,
                'allow_back_order' => true,
            ]
        );

        if ($item->wasRecentlyCreated) {
            $item->upcs()->create(['upc' => '028200003123', 'is_primary' => true, 'sort_order' => 0]);
            $item->prices()->create(['uom' => 'CTN', 'price' => 72.50, 'alias_code' => 'MARL-RED', 'sort_order' => 0]);
            $item->prices()->create(['uom' => 'PK', 'price' => 7.50, 'alias_code' => null, 'sort_order' => 1]);
        }
    }

    protected function seedLookups(int $companyId): void
    {
        $dept = Department::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'TOB'],
            ['name' => 'Tobacco', 'is_active' => true]
        );

        $cat = Category::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'CIG'],
            ['department_id' => $dept->id, 'name' => 'Cigarettes', 'is_active' => true]
        );

        Subcategory::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'CARTON'],
            ['category_id' => $cat->id, 'name' => 'Cartons', 'is_active' => true]
        );

        foreach ([
            ['code' => 'STD', 'name' => 'Standard Item'],
            ['code' => 'KIT', 'name' => 'Kit'],
            ['code' => 'NONINV', 'name' => 'Non-Inventory'],
            ['code' => 'SVC', 'name' => 'Service'],
        ] as $type) {
            ItemType::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $type['code']],
                ['name' => $type['name'], 'is_active' => true]
            );
        }

        foreach (UomScheduleSeeder::definitions() as $uom) {
            UomSchedule::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $uom['code']],
                ['name' => $uom['name'], 'base_uom' => $uom['base_uom'], 'is_active' => true]
            );
        }

        RouteLookup::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'CITY'],
            ['name' => 'City', 'is_active' => true]
        );

        TaxSchedule::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'STD'],
            ['name' => 'Standard Tax', 'rate' => 0.06, 'is_active' => true]
        );

        PricingMethod::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'FLAT'],
            ['name' => 'Flat Amount', 'is_active' => true]
        );

        PaymentTerm::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'N30'],
            ['name' => 'Net 30', 'days_due' => 30, 'is_active' => true]
        );

        ShipVia::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'TRUCK'],
            ['name' => 'Truck', 'is_active' => true]
        );

        PriceLevel::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'WS'],
            ['name' => 'Wholesale', 'is_active' => true]
        );

        DiscountSchedule::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'NONE'],
            ['name' => 'No Discount', 'is_active' => true]
        );

        CigaretteTaxClass::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'STD'],
            ['name' => 'Standard Cigarette Tax', 'is_active' => true]
        );

        PurchaseLimitSchedule::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'NONE'],
            ['name' => 'No Limit', 'is_active' => true]
        );

        $lookups = [
            'lead_source' => ['Walk-in', 'Referral', 'Website', 'Trade Show', 'Sales Call'],
            'customer_category' => ['Wholesale', 'Retail', 'Chain', 'Convenience', 'Distributor'],
            'account_type' => ['Open Account', 'COD', 'Credit Card', 'Cash'],
        ];

        foreach ($lookups as $type => $names) {
            foreach ($names as $name) {
                $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 12)) ?: null;
                CustomerLookupOption::query()->firstOrCreate(
                    [
                        'company_id' => $companyId,
                        'type' => $type,
                        'name' => $name,
                    ],
                    [
                        'code' => $code,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
