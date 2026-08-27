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
        $this->assertStringNotContainsString('66666666', substr($hid, 0, 20));
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

    public function test_tot_uses_msa_distributor_id_not_secondary_tob(): void
    {
        $company = $this->company([
            'msa_distributor_id' => '17000299',
            'secondary_tob_number' => '666666666',
        ]);

        $file = app(TobaccoProductSalesFileService::class)->build(
            $company,
            '2026-08-09',
            '2026-08-15',
            collect(),
            'all'
        );
        $tot = collect(explode("\r\n", $file))->first(fn (string $line) => str_starts_with($line, 'TOT'));

        $this->assertNotNull($tot);
        $this->assertSame('TOT17000299', substr($tot, 0, 11));
        $this->assertStringNotContainsString('666666666', substr($tot, 0, 20));
    }

    public function test_build_requires_msa_distributor_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(TobaccoProductSalesFileService::class)->build(
            $this->company([
                'msa_distributor_id' => null,
                'secondary_tob_number' => '666666666',
            ]),
            '2026-08-09',
            '2026-08-15',
            collect(),
            'all'
        );
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

    public function test_bid_uses_gtin14_pack_size_and_on_hand_not_price(): void
    {
        $file = $this->sampleFile(new Item([
            'item_code' => 'MARL',
            'primary_upc' => '4254418',
            'description' => 'MARLBORO BOX KING',
            'tobacco_product_type' => 'cigarettes',
            'cigarette_pack_size' => 20,
            'list_price' => 33.49,
            'quantity_in_stock' => 12,
        ]));

        $bid = collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'BID'));
        $this->assertSame(TobaccoProductSalesFileService::BID_LEN, strlen($bid));
        $this->assertSame('00000004254418', substr($bid, 3, 14));
        $this->assertSame('00000004254418', substr($bid, 17, 14));
        $this->assertSame('000020', substr($bid, 131, 6));
        $this->assertSame('003', substr($bid, 247, 3));
        $this->assertSame('00000000012', substr($bid, 250, 11));
        $this->assertStringNotContainsString('00000033490', $bid);
    }

    public function test_bid_description_is_capped_at_50_chars_for_msa(): void
    {
        $long = 'BREEZE PRIME 6000 PUFF HONEYDEW PINEAPPLE 5CT (NO RETURN ALLOWED)';
        $this->assertGreaterThan(50, strlen($long));

        $file = $this->sampleFile(new Item([
            'item_code' => 'BRZ1',
            'primary_upc' => '012345678901',
            'description' => $long,
            'tobacco_product_type' => 'otp',
            'list_price' => 10,
            'quantity_in_stock' => 1,
        ]));

        $bid = collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'BID'));
        $this->assertNotEmpty($bid);

        $descSlot = substr($bid, TobaccoProductSalesFileService::BID_DESC_AT, TobaccoProductSalesFileService::BID_DESC_LEN);
        $this->assertSame(TobaccoProductSalesFileService::BID_DESC_LEN, strlen($descSlot));
        $this->assertLessThanOrEqual(50, strlen(rtrim($descSlot)));
        $this->assertSame(strtoupper(substr($long, 0, 50)), substr($descSlot, 0, 50));
        // Chars 51–100 of the description slot must be blank (no spill into promo area).
        $this->assertSame(str_repeat(' ', 50), substr($descSlot, 50, 50));
        $this->assertStringNotContainsString('RETURN ALLOWED', substr($bid, 81, 50));
    }

    public function test_deal_text_in_item_description_sets_msa_promo_flag_and_fields(): void
    {
        $file = $this->sampleFile(new Item([
            'item_code' => 'HAV1',
            'primary_upc' => '012345678901',
            'description' => 'HAVANA 2/$1.99 MILK N COOKIE 20CT',
            'tobacco_product_type' => 'otp',
            'list_price' => 1.99,
            'quantity_in_stock' => 20,
        ]));

        $bid = collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'BID'));
        $this->assertSame('Y', substr($bid, 137, 1));
        $this->assertSame('PROMO', rtrim(substr($bid, 138, 6)));
        $this->assertSame('2/$1.99', rtrim(substr($bid, TobaccoProductSalesFileService::BID_PROMO_AT, 41)));

        $swisher = $this->sampleFile(new Item([
            'item_code' => 'SW1',
            'primary_upc' => '012345678902',
            'description' => 'SWISHER CIG SWEET 30/2 FOR $1.39',
            'tobacco_product_type' => 'otp',
            'list_price' => 1.39,
        ]));
        $bid2 = collect(preg_split("/\r\n|\n/", $swisher))->first(fn ($line) => str_starts_with($line, 'BID'));
        $this->assertSame('Y', substr($bid2, 137, 1));
        $this->assertStringContainsString('30/2 FOR $1.39', substr($bid2, TobaccoProductSalesFileService::BID_PROMO_AT, 41));
    }

    public function test_non_promo_item_keeps_n_flag_and_blank_promo_fields(): void
    {
        $file = $this->sampleFile(new Item([
            'item_code' => 'MARL',
            'primary_upc' => '28200135704',
            'description' => 'MARLBORO BOX KING',
            'tobacco_product_type' => 'cigarettes',
            'list_price' => 10,
        ]));

        $bid = collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'BID'));
        $this->assertSame('N', substr($bid, 137, 1));
        $this->assertSame(str_repeat(' ', 6), substr($bid, 138, 6));
        $this->assertSame(str_repeat(' ', 41), substr($bid, TobaccoProductSalesFileService::BID_PROMO_AT, 41));
    }

    public function test_pur_puts_dollars_in_002_not_004(): void
    {
        $file = $this->sampleFile(new Item([
            'item_code' => 'MARL',
            'primary_upc' => '28200135704',
            'description' => 'MARLBORO BOX KING',
            'tobacco_product_type' => 'cigarettes',
            'list_price' => 10,
        ]), qty: 2, price: 13.29);

        $pur = collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'PUR'));
        $this->assertSame(TobaccoProductSalesFileService::PUR_LEN, strlen($pur));
        $this->assertSame('001', substr($pur, 102, 3));
        $this->assertSame('00000000002', substr($pur, 105, 11));
        $this->assertSame('002', substr($pur, 116, 3));
        $this->assertSame('00000002658', substr($pur, 119, 11));
        $this->assertSame('004', substr($pur, 130, 3));
        $this->assertSame('00000000000', substr($pur, 133, 11));
    }

    public function test_hemp_wrap_is_excluded_from_tob_file(): void
    {
        $file = $this->sampleFile(new Item([
            'item_code' => 'ZZH',
            'primary_upc' => '036000291452',
            'description' => 'ZIG ZAG HEMP WRAP',
            'tobacco_product_type' => 'otp',
        ]));

        $this->assertNull(collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'BID')));
        $this->assertNull(collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'PUR')));
    }

    public function test_short_customer_phone_is_blank_not_zero_padded(): void
    {
        $item = new Item([
            'item_code' => 'CIG1',
            'primary_upc' => '012345678901',
            'description' => 'MARLBORO BOX KING',
            'tobacco_product_type' => 'cigarettes',
        ]);
        $item->id = 3;
        $item->setRelation('category', new Category(['name' => 'MI Cigarettes']));

        $line = new SalesOrderLine(['qty_ordered' => 1, 'qty_shipped' => 1, 'price' => 5, 'discount' => 0]);
        $line->setRelation('item', $item);
        $order = new SalesOrder;
        $order->setRelation('lines', collect([$line]));

        $customer = new Customer([
            'customer_id' => 'C2',
            'company_name' => 'SAMAHA OIL COMPANY',
            'address' => '3891 PLATT RD.',
            'city' => 'ANN ARBOR',
            'state' => 'MI',
            'zip_code' => '48108',
            'telephone' => '2',
        ]);
        $customer->id = 2;

        $invoice = new Invoice(['invoice_date' => '2026-08-12']);
        $invoice->setRelation('customer', $customer);
        $invoice->setRelation('salesOrder', $order);

        $file = app(TobaccoProductSalesFileService::class)->build(
            $this->company(['msa_distributor_id' => '17000299']),
            '2026-08-09',
            '2026-08-15',
            collect([$invoice]),
            'all'
        );
        $sid = collect(preg_split("/\r\n|\n/", $file))->first(fn ($line) => str_starts_with($line, 'SID'));

        $this->assertSame(str_repeat(' ', 10), substr($sid, 201, 10));
        $this->assertStringNotContainsString('0000000002', $sid);
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

    private function sampleFile(Item $item, float $qty = 1, float $price = 10): string
    {
        $item->id = $item->id ?: 9;
        if (! $item->relationLoaded('category')) {
            $item->setRelation('category', new Category(['name' => 'MI Cigarettes']));
        }

        $line = new SalesOrderLine([
            'qty_ordered' => $qty,
            'qty_shipped' => $qty,
            'price' => $price,
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

        return app(TobaccoProductSalesFileService::class)->build(
            $this->company(['msa_distributor_id' => '17000299']),
            '2026-08-09',
            '2026-08-15',
            collect([$invoice]),
            'all'
        );
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
