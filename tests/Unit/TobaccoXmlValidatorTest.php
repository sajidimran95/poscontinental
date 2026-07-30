<?php

namespace Tests\Unit;

use App\Services\TobaccoXmlValidator;
use Tests\TestCase;

class TobaccoXmlValidatorTest extends TestCase
{
    public function test_valid_msa_secondary_cigarette_return_passes_xsd(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Transmission xmlns="http://www.irs.gov/efile">
  <TransmissionHeader>
    <Jurisdiction>MI</Jurisdiction>
    <TransmissionID>12345678920260730120000</TransmissionID>
    <Timestamp>2026-07-30T12:00:00Z</Timestamp>
    <Transmitter>123456789</Transmitter>
    <ProcessType>T</ProcessType>
  </TransmissionHeader>
  <TobCigFiling>
    <SubmissionId>12345678920260730120000</SubmissionId>
    <TobaccoCigaretteHeader>
      <Jurisdiction>MI</Jurisdiction>
      <Timestamp>2026-07-30T12:00:00Z</Timestamp>
      <TaxPeriodEndDate>2026-07-31</TaxPeriodEndDate>
      <TypeOfFiling>Original</TypeOfFiling>
      <Filer>
        <FEIN>123456789</FEIN>
      </Filer>
    </TobaccoCigaretteHeader>
    <CigaretteSecondaryWholesalerReturn>
      <reportCurrency>USD</reportCurrency>
      <StateLicenseNumber>1001</StateLicenseNumber>
      <CigSndWholeSchedule>
        <Schedule>
          <ScheduleCode>C108C</ScheduleCode>
          <DateReceived>2026-07-15</DateReceived>
          <InvoiceDate>2026-07-15</InvoiceDate>
          <InvoiceNumber>INV-1001</InvoiceNumber>
          <PurchaserSellerFEIN>112233445</PurchaserSellerFEIN>
          <PurchaserSellerName>Retail Customer</PurchaserSellerName>
          <BrandCode>CIG</BrandCode>
          <CigPackSize>20</CigPackSize>
          <PackCount>10</PackCount>
          <Address>
            <USAddress>
              <AddressLine1>200 Oak Ave</AddressLine1>
              <City>Ann Arbor</City>
              <State>MI</State>
              <ZIPCode>48104</ZIPCode>
            </USAddress>
          </Address>
        </Schedule>
      </CigSndWholeSchedule>
    </CigaretteSecondaryWholesalerReturn>
  </TobCigFiling>
</Transmission>
XML;

        $result = app(TobaccoXmlValidator::class)->validate($xml);

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
        $this->assertSame([], $result['errors']);
    }

    public function test_otp_secondary_return_root_is_tobacco_secondary_wholesaler(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Transmission xmlns="http://www.irs.gov/efile">
  <TransmissionHeader>
    <Jurisdiction>MI</Jurisdiction>
    <TransmissionID>12345678920260730120000</TransmissionID>
    <Timestamp>2026-07-30T12:00:00Z</Timestamp>
    <Transmitter>123456789</Transmitter>
    <ProcessType>T</ProcessType>
  </TransmissionHeader>
  <TobCigFiling>
    <SubmissionId>12345678920260730120000</SubmissionId>
    <TobaccoCigaretteHeader>
      <Jurisdiction>MI</Jurisdiction>
      <Timestamp>2026-07-30T12:00:00Z</Timestamp>
      <TaxPeriodEndDate>2026-07-31</TaxPeriodEndDate>
      <TypeOfFiling>Original</TypeOfFiling>
      <Filer>
        <FEIN>123456789</FEIN>
      </Filer>
    </TobaccoCigaretteHeader>
    <TobaccoSecondaryWholesalerReturn>
      <reportCurrency>USD</reportCurrency>
      <StateLicenseNumber>1001</StateLicenseNumber>
      <TobSndWholeSchedule>
        <Schedule>
          <ScheduleCode>T108C</ScheduleCode>
          <DateReceivedOrSold>2026-07-15</DateReceivedOrSold>
          <InvoiceDate>2026-07-15</InvoiceDate>
          <InvoiceNumber>INV-2001</InvoiceNumber>
          <PurchaserSellerFEIN>112233445</PurchaserSellerFEIN>
          <PurchaserSellerName>Retail Customer</PurchaserSellerName>
          <BrandCode>OTP</BrandCode>
          <WholesalePrice>90.00</WholesalePrice>
          <Address>
            <USAddress>
              <AddressLine1>200 Oak Ave</AddressLine1>
              <City>Ann Arbor</City>
              <State>MI</State>
              <ZIPCode>48104</ZIPCode>
            </USAddress>
          </Address>
        </Schedule>
      </TobSndWholeSchedule>
    </TobaccoSecondaryWholesalerReturn>
  </TobCigFiling>
</Transmission>
XML;

        $result = app(TobaccoXmlValidator::class)->validate($xml);

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
    }

    public function test_invalid_root_element_fails_xsd(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<WrongRoot xmlns="http://www.irs.gov/efile"/>
XML;

        $result = app(TobaccoXmlValidator::class)->validate($xml);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}
