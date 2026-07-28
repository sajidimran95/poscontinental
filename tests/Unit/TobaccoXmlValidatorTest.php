<?php

namespace Tests\Unit;

use App\Services\TobaccoXmlValidator;
use Tests\TestCase;

class TobaccoXmlValidatorTest extends TestCase
{
    public function test_valid_michigan_tobacco_return_passes_xsd(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<MichiganTobaccoReturn xmlns="http://www.michigan.gov/treasury/tobacco" filerType="secondary_wholesaler" product="cigarettes" schemaVersion="1.1">
  <CompanyName>Continental Wholesale Inc</CompanyName>
  <CompanyFEIN>12-3456789</CompanyFEIN>
  <PeriodStart>2026-07-01</PeriodStart>
  <PeriodEnd>2026-07-31</PeriodEnd>
  <GeneratedAt>2026-07-28T12:00:00+00:00</GeneratedAt>
  <FilingType>secondary_wholesaler</FilingType>
  <ProductType>cigarettes</ProductType>
  <PurchaserSellers>
    <Seller>
      <Name>Acme Supplier</Name>
      <PurchaserSellerFEIN>98-7654321</PurchaserSellerFEIN>
      <Address>100 Main St</Address>
      <City>Detroit</City>
      <State>MI</State>
      <ZIP>48201</ZIP>
    </Seller>
    <Buyer>
      <Name>Retail Customer</Name>
      <PurchaserSellerFEIN>11-2233445</PurchaserSellerFEIN>
      <Address>200 Oak Ave</Address>
      <City>Ann Arbor</City>
      <State>MI</State>
      <ZIP>48104</ZIP>
    </Buyer>
  </PurchaserSellers>
  <Sales>
    <Sale>
      <InvoiceNo>INV-1001</InvoiceNo>
      <InvoiceDate>2026-07-15</InvoiceDate>
      <CustomerFEIN>11-2233445</CustomerFEIN>
      <CustomerName>Retail Customer</CustomerName>
      <OrderNo>SO-5001</OrderNo>
      <WholesaleTotal>1250.00</WholesaleTotal>
      <TaxAmount>0.00</TaxAmount>
      <Lines>
        <Line>
          <ItemCode>BRAND-A</ItemCode>
          <Description>Brand A Carton</Description>
          <Qty>10.0000</Qty>
          <UnitPrice>125.0000</UnitPrice>
          <Extended>1250.00</Extended>
          <UOM>CTN</UOM>
        </Line>
      </Lines>
    </Sale>
  </Sales>
</MichiganTobaccoReturn>
XML;

        $result = app(TobaccoXmlValidator::class)->validate($xml);

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
        $this->assertSame([], $result['errors']);
    }

    public function test_invalid_root_element_fails_xsd(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<WrongRoot xmlns="http://www.michigan.gov/treasury/tobacco"/>
XML;

        $result = app(TobaccoXmlValidator::class)->validate($xml);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}
