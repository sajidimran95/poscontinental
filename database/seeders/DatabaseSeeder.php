<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CigaretteTaxClass;
use App\Models\Company;
use App\Models\CustomerLookupOption;
use App\Models\Department;
use App\Models\DiscountSchedule;
use App\Models\Item;
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
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->create([
            'code' => 'CWI',
            'name' => 'Continental Wholesale Inc',
            'is_active' => true,
        ]);

        $site = Site::query()->create([
            'company_id' => $company->id,
            'code' => 'WS',
            'name' => 'Wholesale Site',
            'is_active' => true,
        ]);

        $roles = [
            ['name' => 'admin', 'label' => 'Administrator'],
            ['name' => 'sales_rep', 'label' => 'Sales Rep'],
            ['name' => 'buyer', 'label' => 'Buyer'],
            ['name' => 'warehouse', 'label' => 'Warehouse'],
        ];

        foreach ($roles as $role) {
            Role::query()->create($role);
        }

        $adminRole = Role::query()->where('name', 'admin')->first();
        $salesRole = Role::query()->where('name', 'sales_rep')->first();

        User::query()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'role_id' => $adminRole->id,
            'name' => 'Yousef Imran',
            'username' => 'yimran',
            'email' => 'yimran@continental.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        User::query()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'role_id' => $salesRole->id,
            'name' => 'Sales Rep',
            'username' => 'sales',
            'email' => 'sales@continental.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->seedLookups($company->id);
        $this->seedSampleItems($company->id);
    }

    protected function seedSampleItems(int $companyId): void
    {
        $dept = Department::query()->where('company_id', $companyId)->where('code', 'TOB')->first();
        $cat = Category::query()->where('company_id', $companyId)->where('code', 'CIG')->first();
        $sub = Subcategory::query()->where('company_id', $companyId)->where('code', 'CARTON')->first();
        $uom = UomSchedule::query()->where('company_id', $companyId)->first();
        $tax = TaxSchedule::query()->where('company_id', $companyId)->first();
        $pricing = PricingMethod::query()->where('company_id', $companyId)->first();

        $item = Item::query()->create([
            'company_id' => $companyId,
            'item_code' => 'MARL-RED-CTN',
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
        ]);

        $item->upcs()->create(['upc' => '028200003123', 'is_primary' => true, 'sort_order' => 0]);
        $item->prices()->create(['uom' => 'CTN', 'price' => 72.50, 'alias_code' => 'MARL-RED', 'sort_order' => 0]);
        $item->prices()->create(['uom' => 'PK', 'price' => 7.50, 'alias_code' => null, 'sort_order' => 1]);
    }

    protected function seedLookups(int $companyId): void
    {
        $dept = Department::query()->create([
            'company_id' => $companyId,
            'code' => 'TOB',
            'name' => 'Tobacco',
        ]);

        $cat = Category::query()->create([
            'company_id' => $companyId,
            'department_id' => $dept->id,
            'code' => 'CIG',
            'name' => 'Cigarettes',
        ]);

        Subcategory::query()->create([
            'company_id' => $companyId,
            'category_id' => $cat->id,
            'code' => 'CARTON',
            'name' => 'Cartons',
        ]);

        UomSchedule::query()->create([
            'company_id' => $companyId,
            'code' => 'EA-BX',
            'name' => 'Each / Box',
            'base_uom' => 'EA',
        ]);

        RouteLookup::query()->create([
            'company_id' => $companyId,
            'code' => 'CITY',
            'name' => 'City',
        ]);

        TaxSchedule::query()->create([
            'company_id' => $companyId,
            'code' => 'STD',
            'name' => 'Standard Tax',
            'rate' => 0.06,
        ]);

        PricingMethod::query()->create([
            'company_id' => $companyId,
            'code' => 'FLAT',
            'name' => 'Flat Amount',
        ]);

        PaymentTerm::query()->create([
            'company_id' => $companyId,
            'code' => 'N30',
            'name' => 'Net 30',
            'days_due' => 30,
        ]);

        ShipVia::query()->create([
            'company_id' => $companyId,
            'code' => 'TRUCK',
            'name' => 'Truck',
        ]);

        PriceLevel::query()->create([
            'company_id' => $companyId,
            'code' => 'WS',
            'name' => 'Wholesale',
        ]);

        DiscountSchedule::query()->create([
            'company_id' => $companyId,
            'code' => 'NONE',
            'name' => 'No Discount',
        ]);

        CigaretteTaxClass::query()->create([
            'company_id' => $companyId,
            'code' => 'STD',
            'name' => 'Standard Cigarette Tax',
        ]);

        PurchaseLimitSchedule::query()->create([
            'company_id' => $companyId,
            'code' => 'NONE',
            'name' => 'No Limit',
        ]);

        $lookups = [
            'lead_source' => ['Walk-in', 'Referral', 'Website', 'Trade Show', 'Sales Call'],
            'customer_category' => ['Wholesale', 'Retail', 'Chain', 'Convenience', 'Distributor'],
            'account_type' => ['Open Account', 'COD', 'Credit Card', 'Cash'],
        ];

        foreach ($lookups as $type => $names) {
            foreach ($names as $name) {
                CustomerLookupOption::query()->create([
                    'company_id' => $companyId,
                    'type' => $type,
                    'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 12)) ?: null,
                    'name' => $name,
                    'is_active' => true,
                ]);
            }
        }
    }
}
