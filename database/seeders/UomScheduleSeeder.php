<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\UomSchedule;
use Illuminate\Database\Seeder;

class UomScheduleSeeder extends Seeder
{
    /**
     * @return array<int, array{code:string,name:string,base_uom:string}>
     */
    public static function definitions(): array
    {
        return [
            ['code' => 'EA', 'name' => 'Each', 'base_uom' => 'EA'],
            ['code' => 'BX', 'name' => 'Box', 'base_uom' => 'BX'],
            ['code' => 'CS', 'name' => 'Case', 'base_uom' => 'CS'],
            ['code' => 'CTN', 'name' => 'Carton', 'base_uom' => 'CTN'],
            ['code' => 'PK', 'name' => 'Pack', 'base_uom' => 'PK'],
            ['code' => 'DZ', 'name' => 'Dozen', 'base_uom' => 'DZ'],
            ['code' => 'LB', 'name' => 'Pound', 'base_uom' => 'LB'],
            ['code' => 'KG', 'name' => 'Kilogram', 'base_uom' => 'KG'],
            ['code' => 'PLT', 'name' => 'Pallet', 'base_uom' => 'PLT'],
            ['code' => 'BAG', 'name' => 'Bag', 'base_uom' => 'BAG'],
            ['code' => 'EA-BX', 'name' => 'Each / Box', 'base_uom' => 'EA'],
            ['code' => 'BX-CS', 'name' => 'Box / Case', 'base_uom' => 'BX'],
        ];
    }

    public function run(): void
    {
        $companies = Company::query()->orderBy('id')->get();
        if ($companies->isEmpty()) {
            $this->command?->warn('No companies found — skipped UOM seeding.');

            return;
        }

        foreach ($companies as $company) {
            $created = 0;
            foreach (self::definitions() as $uom) {
                $row = UomSchedule::query()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $uom['code']],
                    ['name' => $uom['name'], 'base_uom' => $uom['base_uom'], 'is_active' => true]
                );
                if ($row->wasRecentlyCreated) {
                    $created++;
                }
            }
            $this->command?->info("Company {$company->code}: {$created} new UOM schedule(s).");
        }
    }
}
