<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CreditMemo;
use App\Models\CreditMemoLine;
use App\Models\Customer;
use App\Models\InventoryReceiving;
use App\Models\InventoryReceivingLine;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Supplier;
use App\Models\TobaccoStampInventory;
use App\Services\TobaccoXmlService;
use App\Services\TobaccoXmlValidator;
use Tests\TestCase;

/**
 * Generated returns must pass the official Michigan schemas (resources/xsd/michigan-tobacco/official),
 * including with real-world addresses, ZIP+4 and free-text brand codes.
 */
class TobaccoXmlServiceTest extends TestCase
{
    public function test_all_returns_pass_official_michigan_schema(): void
    {
        foreach (['secondary_wholesaler', 'unclassified_acquirer'] as $filerType) {
            foreach (['cigarettes', 'otp'] as $product) {
                $xml = $this->build($filerType, $product);
                $result = app(TobaccoXmlValidator::class)->validate($xml);

                $this->assertTrue($result['valid'], "{$filerType}/{$product}:\n".implode("\n", $result['errors']));
                $this->assertGreaterThan(0, substr_count($xml, 'Schedule>'), "{$filerType}/{$product} has no schedule rows");
            }
        }
    }

    public function test_address_city_zip_and_brand_are_normalized(): void
    {
        $xml = $this->build('secondary_wholesaler', 'cigarettes');

        $this->assertStringContainsString('<AddressLine1>1234 W 8 Mile Rd Suite 5</AddressLine1>', $xml);
        $this->assertStringContainsString('<AddressLine1>500 Main St</AddressLine1>', $xml);
        $this->assertStringContainsString('<City>St Clair Shores</City>', $xml);
        $this->assertStringContainsString('<ZIPCode>480801234</ZIPCode>', $xml);
        $this->assertStringContainsString('<BrandCode>CIG</BrandCode>', $xml);
        $this->assertStringNotContainsString('Marlboro', $xml);
    }

    public function test_submission_id_keeps_full_timestamp(): void
    {
        $this->travelTo('2026-08-31 23:59:58');

        $xml = $this->build('secondary_wholesaler', 'otp');

        $this->assertMatchesRegularExpression('#<SubmissionId>\d{6}20260831235958</SubmissionId>#', $xml);
    }

    public function test_short_brand_code_is_zero_padded(): void
    {
        $xml = $this->build('secondary_wholesaler', 'otp');

        $this->assertStringContainsString('<BrandCode>075</BrandCode>', $xml);
    }

    private function build(string $filerType, string $product): string
    {
        $company = (new Company)->forceFill([
            'name' => 'Continental Wholesale',
            'fein_no' => '38-1234567',
            'secondary_tob_number' => '1001',
            'secondary_cig_number' => '1002',
            'transmitter_account_number' => '12345678',
        ]);

        $items = [
            (new Item)->forceFill(['state_reporting' => 1, 'tobacco_product_type' => 'cigarettes', 'tobacco_brand_code' => 'Marlboro', 'cigarette_pack_size' => 20]),
            (new Item)->forceFill(['state_reporting' => 1, 'tobacco_product_type' => 'otp', 'tobacco_brand_code' => 'OTP']),
            (new Item)->forceFill(['state_reporting' => 1, 'tobacco_product_type' => 'ryo', 'tobacco_brand_code' => '75', 'tobacco_total_oz' => 6]),
        ];

        $customer = (new Customer)->forceFill([
            'company_name' => "Joe's Gas & Mart, LLC",
            'fein_no' => '12-3456789',
            'address' => '1234 W. 8 Mile Rd., Suite #5',
            'city' => 'St. Clair Shores',
            'state' => 'mi',
            'zip_code' => '48080-1234',
        ]);
        $supplier = (new Supplier)->forceFill([
            'name' => 'Core-Mark Midcontinent',
            'fein_no' => '987654321',
            'address' => '  500 Main St  ',
            'city' => 'Sault Ste. Marie',
            'state' => 'MI',
            'zip_code' => '49783',
        ]);

        $soLines = collect($items)->map(fn (Item $item) => (new SalesOrderLine)->forceFill(['qty_ordered' => 10, 'price' => 9.5])->setRelation('item', $item));
        $receivingLines = collect($items)->map(fn (Item $item) => (new InventoryReceivingLine)->forceFill(['qty_received' => 50, 'unit_cost' => 7.25])->setRelation('item', $item));
        $memoLines = collect($items)->map(fn (Item $item) => (new CreditMemoLine)->forceFill(['qty' => 2, 'price' => 9.5])->setRelation('item', $item));

        $invoice = (new Invoice)->forceFill(['invoice_number' => 'INV-1001', 'invoice_date' => '2026-08-15'])
            ->setRelation('customer', $customer)
            ->setRelation('salesOrder', (new SalesOrder)->setRelation('lines', $soLines));
        $receiving = (new InventoryReceiving)->forceFill(['receipt_number' => 'RCV-77', 'receipt_date' => '2026-08-03'])
            ->setRelation('supplier', $supplier)
            ->setRelation('lines', $receivingLines);
        $memo = (new CreditMemo)->forceFill(['memo_number' => 'CM-9', 'memo_date' => '2026-08-20'])
            ->setRelation('customer', $customer)
            ->setRelation('lines', $memoLines);
        $stamps = (new TobaccoStampInventory)->forceFill(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);

        return app(TobaccoXmlService::class)->build(
            $company,
            $filerType,
            $product,
            '2026-08-01',
            '2026-08-31',
            collect([$receiving]),
            collect([$invoice]),
            collect([$memo]),
            $stamps,
        );
    }
}
