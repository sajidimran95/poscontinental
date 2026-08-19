<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\TobaccoProductSalesFileService;
use Tests\TestCase;

class TobaccoProductSalesFileServiceTest extends TestCase
{
    public function test_hid_msa_id_comes_from_company_settings(): void
    {
        $company = $this->company([
            'msa_distributor_id' => '17000299',
            'secondary_tob_number' => '6666666666',
            'secondary_cig_number' => '888888888',
        ]);

        $hid = $this->hidLine($company);

        $this->assertSame(TobaccoProductSalesFileService::HID_LEN, strlen($hid));
        $this->assertSame('HID', substr($hid, 0, 3));
        $this->assertSame('17000299', substr($hid, 3, 8));
        $this->assertSame('TOB TW', substr($hid, 11, 6));
        $this->assertStringContainsString('CONTINENTAL WHOLESALE', substr($hid, 25, 32));
        $this->assertSame('3802 TRADE CENTER DR', trim(substr($hid, 57, 90)));
        $this->assertSame('7345550100', substr($hid, 231, 10));
        $this->assertNotSame(str_repeat(' ', 20), substr($hid, 186, 20));
        $this->assertNotSame(str_repeat(' ', 20), substr($hid, 206, 20));
    }

    public function test_hid_msa_id_changes_when_company_setting_changes(): void
    {
        $company = $this->company(['msa_distributor_id' => '55112233']);

        $this->assertSame('55112233', substr($this->hidLine($company), 3, 8));
    }

    public function test_filename_uses_msa_distributor_id(): void
    {
        $company = $this->company(['msa_distributor_id' => '17000299']);

        $name = app(TobaccoProductSalesFileService::class)->filename($company, '2026-08-15', 'all');

        $this->assertStringContainsString('_17000299_', $name);
    }

    public function test_sid_includes_purchaser_name_and_address(): void
    {
        $company = $this->company([
            'msa_distributor_id' => '17000299',
        ]);

        $item = new Item([
            'item_code' => 'CIG1',
            'primary_upc' => '012345678901',
            'description' => 'TEST CIGARETTE',
            'tobacco_product_type' => 'cigarettes',
            'list_price' => 10,
        ]);
        $item->id = 9;
        $item->setRelation('category', new Category(['name' => 'MI Cigarettes']));

        $line = new SalesOrderLine([
            'qty_ordered' => 4,
            'qty_shipped' => 4,
            'price' => 10,
            'discount' => 0,
        ]);
        $line->setRelation('item', $item);

        $order = new SalesOrder;
        $order->setRelation('lines', collect([$line]));

        $customer = new Customer([
            'customer_id' => 'C88',
            'company_name' => 'SMOKE SHOP LLC',
            'address' => '100 MAIN ST',
            'city' => 'DETROIT',
            'state' => 'MI',
            'zip_code' => '48201',
            'telephone' => '3135559999',
        ]);
        $customer->id = 88;

        $invoice = new Invoice(['invoice_date' => '2026-08-12']);
        $invoice->setRelation('customer', $customer);
        $invoice->setRelation('salesOrder', $order);

        $file = app(TobaccoProductSalesFileService::class)->build(
            $company,
            '2026-08-09',
            '2026-08-15',
            collect([$invoice]),
            'all'
        );

        $sid = collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'SID'));

        $this->assertNotEmpty($sid);
        $this->assertSame(TobaccoProductSalesFileService::SID_LEN, strlen($sid));
        $this->assertStringContainsString('SMOKE SHOP LLC', substr($sid, 27, 32));
        $this->assertStringContainsString('100 MAIN ST', substr($sid, 67, 90));
        $this->assertStringContainsString('DETROIT', substr($sid, 157, 25));
        $this->assertStringNotContainsString('3802 TRADE CENTER DR', $sid);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function company(array $extra = []): Company
    {
        return new Company(array_merge([
            'name' => 'Continental Wholesale Inc',
            'address' => '3802 TRADE CENTER DR',
            'city' => 'ANN ARBOR',
            'state' => 'MI',
            'zip_code' => '48108',
            'phone' => '734-555-0100',
            'fax' => '734-555-0199',
            'email' => 'office@example.com',
            'contact_name' => 'Office Manager',
            'fein_no' => '383576375',
        ], $extra));
    }

    private function hidLine(Company $company): string
    {
        $file = app(TobaccoProductSalesFileService::class)->build(
            $company,
            '2026-08-09',
            '2026-08-15',
            collect(),
            'all'
        );

        return explode("\r\n", $file)[0];
    }
}
