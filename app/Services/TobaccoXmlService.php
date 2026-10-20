<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditMemo;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\TobaccoStampInventory;
use App\Support\TobaccoItem;
use Illuminate\Support\Collection;
use RuntimeException;
use SimpleXMLElement;

/**
 * Continental Wholesale MSA tobacco returns (Michigan Treasury Tobacco XML Guide):
 * 1. CigaretteSecondaryWholesalerReturn
 * 2. TobaccoSecondaryWholesalerReturn
 * 3. CigaretteUnclassifiedAcquirerReturn (+ stamp inventories R1–R6)
 * 4. TobaccoUnclassifiedAcquirerReturn
 */
class TobaccoXmlService
{
    /**
     * @param  Collection<int, InventoryReceiving>  $receivings
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, CreditMemo>  $creditMemos
     */
    public function build(
        ?Company $company,
        string $filerType,
        string $product,
        string $periodStart,
        string $periodEnd,
        Collection $receivings,
        Collection $invoices,
        Collection $creditMemos,
        ?TobaccoStampInventory $stamps = null,
        string $processType = 'T',
    ): string {
        $product = $product === 'otp' ? 'otp' : 'cigarettes';
        $filerType = $filerType === 'unclassified_acquirer' ? 'unclassified_acquirer' : 'secondary_wholesaler';
        $processType = strtoupper($processType) === 'P' ? 'P' : 'T';

        $issues = $this->readinessIssues($company, $filerType, $product, $stamps);
        if ($issues !== []) {
            throw new RuntimeException(implode(' ', $issues));
        }

        $fein = preg_replace('/\D+/', '', (string) $company->fein_no);
        $license = $company->msaLicenseNumber($product);
        $transmitter = preg_replace('/\D+/', '', (string) ($company->transmitter_account_number ?: $fein));
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $transmissionId = $fein.now()->format('YmdHis');

        $rootXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Transmission xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' xmlns="http://www.irs.gov/efile"'
            .' xsi:schemaLocation="http://www.irs.gov/efile ./ExtendedCommon/Transmission.xsd">'
            .'</Transmission>';
        $xml = new SimpleXMLElement($rootXml);

        $header = $xml->addChild('TransmissionHeader');
        // Portal schema (http://www.irs.gov/efile) requires this attribute on the header.
        $header->addAttribute('recordCount', '1');
        $header->addChild('Jurisdiction', 'MI');
        $header->addChild('TransmissionId', $transmissionId);
        $header->addChild('Timestamp', $timestamp);
        // Transmitter is a complex element: the portal expects <StateEIN> (or ETIN/EFIN) inside it, not text.
        $header->addChild('Transmitter')->addChild('StateEIN', $transmitter);
        $header->addChild('ProcessType', $processType);

        $filing = $xml->addChild('TobCigFiling');
        // SubmissionId is max 20 chars: keep the trailing 20 digits so the full YmdHis timestamp stays in it.
        $filing->addChild('SubmissionId', substr($transmissionId, -20));

        $returnHeader = $filing->addChild('TobaccoCigaretteHeader');
        $returnHeader->addChild('Jurisdiction', 'MI');
        $returnHeader->addChild('Timestamp', $timestamp);
        $returnHeader->addChild('TaxPeriodEndDate', $periodEnd);
        // AmendedReturnIndicator is an IRS CheckboxType (only "X" allowed); omit it on original returns.
        $returnHeader->addChild('TypeOfFiling', 'Original');
        $filer = $returnHeader->addChild('Filer');
        $filer->addChild('FEIN', $fein);

        $schedules = $this->buildScheduleRows($filerType, $product, $receivings, $invoices, $creditMemos);
        $returnNode = $filing->addChild($this->returnElementName($filerType, $product));
        // reportCurrency is a required attribute on the return, not a child element.
        $returnNode->addAttribute('reportCurrency', 'USD');
        $returnNode->addChild('StateLicenseNumber', $license);

        // Each schedule row is its own <CigSndWholeSchedule>/<TobSndWholeSchedule>/... element (no <Schedule> wrapper).
        $scheduleName = $this->scheduleParentName($filerType, $product);
        foreach ($schedules as $row) {
            $node = $returnNode->addChild($scheduleName);
            foreach ($row as $key => $value) {
                if ($key === 'Address') {
                    $addr = $node->addChild('Address');
                    $us = $addr->addChild('USAddress');
                    $us->addChild('AddressLine1', htmlspecialchars((string) ($value['AddressLine1'] ?? ''), ENT_XML1));
                    $us->addChild('City', htmlspecialchars((string) ($value['City'] ?? ''), ENT_XML1));
                    $us->addChild('State', htmlspecialchars((string) ($value['State'] ?? 'MI'), ENT_XML1));
                    $us->addChild('ZIPCode', htmlspecialchars((string) ($value['ZIPCode'] ?? ''), ENT_XML1));

                    continue;
                }
                $node->addChild($key, htmlspecialchars((string) $value, ENT_XML1));
            }
        }

        if ($filerType === 'unclassified_acquirer' && $product === 'cigarettes' && $stamps) {
            $this->appendStampInventory($returnNode, $stamps);
        }

        // Pretty-print like the accepted Michigan sample so portal errors point at a real line number.
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML() ?: '');

        return $dom->saveXML() ?: '';
    }

    /**
     * @return list<string>
     */
    public function readinessIssues(
        ?Company $company,
        string $filerType,
        string $product,
        ?TobaccoStampInventory $stamps = null,
    ): array {
        $issues = [];
        $product = $product === 'otp' ? 'otp' : 'cigarettes';
        $fein = preg_replace('/\D+/', '', (string) ($company?->fein_no ?? ''));
        $license = $company?->msaLicenseNumber($product) ?? '';
        $licenseLabel = $company?->msaLicenseLabel($product) ?? ($product === 'otp' ? 'Secondary Tob Number' : 'Secondary Cig Number');
        $transmitter = preg_replace('/\D+/', '', (string) ($company?->transmitter_account_number ?? ''));

        if (strlen($fein) !== 9) {
            $issues[] = 'Company FEIN must be 9 digits (File → Company Settings).';
        }
        if ($license === '') {
            $issues[] = $licenseLabel.' is required (File → Company Settings) for this MSA report.';
        }
        if ($transmitter === '' && strlen($fein) !== 9) {
            $issues[] = 'Transmitter (State Employer Account Number) is required.';
        }
        if ($filerType === 'unclassified_acquirer' && $product === 'cigarettes' && ! $stamps) {
            $issues[] = 'Stamp inventory for this period is required for Cigarette Unclassified Acquirer.';
        }

        return $issues;
    }

    public function returnElementName(string $filerType, string $product): string
    {
        return match (true) {
            $filerType === 'secondary_wholesaler' && $product === 'cigarettes' => 'CigaretteSecondaryWholesalerReturn',
            $filerType === 'secondary_wholesaler' && $product === 'otp' => 'TobaccoSecondaryWholesalerReturn',
            $filerType === 'unclassified_acquirer' && $product === 'cigarettes' => 'CigaretteUnclassifiedAcquirerReturn',
            default => 'TobaccoUnclassifiedAcquirerReturn',
        };
    }

    public function scheduleParentName(string $filerType, string $product): string
    {
        return match (true) {
            $filerType === 'secondary_wholesaler' && $product === 'cigarettes' => 'CigSndWholeSchedule',
            $filerType === 'secondary_wholesaler' && $product === 'otp' => 'TobSndWholeSchedule',
            $filerType === 'unclassified_acquirer' && $product === 'cigarettes' => 'CigUnAcqSchedule',
            default => 'TobUnAcqSchedule',
        };
    }

    /**
     * @return array{purchases: string, sales: string, returns: string}
     */
    public function scheduleCodes(string $filerType, string $product): array
    {
        if ($product === 'cigarettes') {
            return $filerType === 'unclassified_acquirer'
                ? ['purchases' => 'C101A', 'sales' => 'C108C', 'returns' => 'C101C']
                : ['purchases' => 'C101B', 'sales' => 'C108C', 'returns' => 'C101C'];
        }

        return $filerType === 'unclassified_acquirer'
            ? ['purchases' => 'T101A', 'sales' => 'T108C', 'returns' => 'T101C']
            : ['purchases' => 'T101B', 'sales' => 'T108C', 'returns' => 'T101C'];
    }

    /**
     * @param  Collection<int, InventoryReceiving>  $receivings
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, CreditMemo>  $creditMemos
     */
    public function buildTextReport(
        ?Company $company,
        string $filerType,
        string $product,
        string $periodStart,
        string $periodEnd,
        Collection $receivings,
        Collection $invoices,
        Collection $creditMemos,
        ?TobaccoStampInventory $stamps = null,
        string $processType = 'T',
    ): string {
        $product = $product === 'otp' ? 'otp' : 'cigarettes';
        $filerType = $filerType === 'unclassified_acquirer' ? 'unclassified_acquirer' : 'secondary_wholesaler';
        $processType = strtoupper($processType) === 'P' ? 'P' : 'T';
        $codes = $this->scheduleCodes($filerType, $product);
        $rows = $this->buildScheduleRows($filerType, $product, $receivings, $invoices, $creditMemos, false);
        $returnName = $this->returnElementName($filerType, $product);

        $lines = [];
        $lines[] = 'CONTINENTAL WHOLESALE — MSA TOBACCO REPORT';
        $lines[] = str_repeat('=', 72);
        $lines[] = 'Company: '.($company?->name ?? '');
        $lines[] = 'FEIN: '.($company?->fein_no ?? '');
        // OTP (tobacco) return → Secondary Tob Number; cigarette return → Secondary Cig Number.
        $lines[] = ($company?->msaLicenseLabel($product) ?? ($product === 'otp' ? 'Secondary Tob Number' : 'Secondary Cig Number'))
            .': '.($company?->msaLicenseNumber($product) ?? '');
        $lines[] = 'Return: '.$returnName;
        $lines[] = 'Filer Type: '.$filerType;
        $lines[] = 'Product: '.$product;
        $lines[] = 'Process Type: '.$processType.' ('.($processType === 'P' ? 'Production' : 'Test').')';
        $lines[] = 'Period: '.$periodStart.' to '.$periodEnd;
        $lines[] = 'Generated: '.now()->toDateTimeString();
        $lines[] = 'Schedule Codes: Purchases='.$codes['purchases'].' Sales='.$codes['sales'].' Returns='.$codes['returns'];
        $lines[] = str_repeat('-', 72);

        $sections = [
            $codes['purchases'] => 'PURCHASES',
            $codes['sales'] => 'SALES',
            $codes['returns'] => 'RETURNS',
        ];

        foreach ($sections as $code => $title) {
            $sectionRows = array_values(array_filter($rows, fn ($r) => ($r['ScheduleCode'] ?? '') === $code));
            $lines[] = '';
            $lines[] = $title.' ('.$code.') — '.count($sectionRows).' row(s)';
            $lines[] = str_repeat('-', 72);
            if ($sectionRows === []) {
                $lines[] = '(none)';

                continue;
            }
            $lines[] = sprintf(
                '%-10s %-12s %-14s %-12s %-22s %-8s %12s',
                'Date',
                'Invoice',
                'FEIN',
                'Brand',
                'Party',
                'Qty',
                $product === 'otp' ? 'Wholesale' : 'Packs'
            );
            foreach ($sectionRows as $row) {
                $qty = $row['PackCount'] ?? $row['StickCount'] ?? $row['TotalOz'] ?? '';
                $lines[] = sprintf(
                    '%-10s %-12s %-14s %-12s %-22s %-8s %12s',
                    $row['InvoiceDate'] ?? '',
                    mb_substr((string) ($row['InvoiceNumber'] ?? ''), 0, 12),
                    $row['PurchaserSellerFEIN'] ?? '',
                    $row['BrandCode'] ?? '',
                    mb_substr((string) ($row['PurchaserSellerName'] ?? ''), 0, 22),
                    $qty,
                    $product === 'otp' ? ($row['WholesalePrice'] ?? '0.00') : ($row['PackCount'] ?? '')
                );
            }
        }

        if ($filerType === 'unclassified_acquirer' && $product === 'cigarettes') {
            $lines[] = '';
            $lines[] = 'STAMP INVENTORY (AFFIXED / UNAFFIXED R1–R6)';
            $lines[] = str_repeat('-', 72);
            if (! $stamps) {
                $lines[] = '(no stamp inventory saved for this period)';
            } else {
                $matrix = $stamps->matrix();
                $lines[] = 'Period: '.optional($stamps->period_start)?->format('Y-m-d').' to '.optional($stamps->period_end)?->format('Y-m-d');
                $lines[] = sprintf('%-12s %8s %8s %8s %8s %8s %8s', 'Bucket', 'R1', 'R2', 'R3', 'R4', 'R5', 'R6');
                foreach ([
                    'Begin Unaff' => $matrix['beginning_unaffixed'],
                    'End Unaff' => $matrix['ending_unaffixed'],
                    'Begin Aff' => $matrix['beginning_affixed'],
                    'End Aff' => $matrix['ending_affixed'],
                ] as $label => $counts) {
                    $lines[] = sprintf(
                        '%-12s %8d %8d %8d %8d %8d %8d',
                        $label,
                        $counts['R1'] ?? 0,
                        $counts['R2'] ?? 0,
                        $counts['R3'] ?? 0,
                        $counts['R4'] ?? 0,
                        $counts['R5'] ?? 0,
                        $counts['R6'] ?? 0,
                    );
                }
            }
        }

        $lines[] = '';
        $lines[] = str_repeat('=', 72);
        $lines[] = 'End of MSA report';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  Collection<int, InventoryReceiving>  $receivings
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, CreditMemo>  $creditMemos
     * @return list<array<string, mixed>>
     */
    public function buildScheduleRows(
        string $filerType,
        string $product,
        Collection $receivings,
        Collection $invoices,
        Collection $creditMemos,
        bool $strictFein = true,
    ): array {
        $codes = $this->scheduleCodes($filerType, $product);
        $rows = [];
        $missingFeinNames = [];

        foreach ($receivings as $receiving) {
            $supplier = $receiving->supplier;
            foreach ($receiving->lines as $line) {
                $item = $line->item;
                if (! $this->itemMatchesProduct($item, $product)) {
                    continue;
                }
                $qty = (float) ($line->qty_received ?: $line->qty_ordered);
                if ($qty <= 0) {
                    continue;
                }
                $row = $this->scheduleRow(
                    $codes['purchases'],
                    optional($receiving->receipt_date)?->format('Y-m-d') ?: '',
                    optional($receiving->receipt_date)?->format('Y-m-d') ?: '',
                    (string) ($receiving->receipt_number ?: $receiving->id),
                    $supplier?->fein_no,
                    $supplier?->name,
                    $supplier?->address,
                    $supplier?->city,
                    $supplier?->state,
                    $supplier?->zip_code,
                    $item,
                    $qty,
                    (float) ($line->unit_cost ?? 0),
                    $product,
                    $strictFein,
                    $missingFeinNames,
                );
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        foreach ($invoices as $invoice) {
            $customer = $invoice->customer;
            $invoiceDate = optional($invoice->invoice_date)?->format('Y-m-d') ?: '';
            foreach ($invoice->salesOrder?->lines ?? [] as $line) {
                $item = $line->item;
                if (! $this->itemMatchesProduct($item, $product)) {
                    continue;
                }
                $qty = (float) $line->qty_ordered;
                if ($qty <= 0) {
                    continue;
                }
                $row = $this->scheduleRow(
                    $codes['sales'],
                    $invoiceDate,
                    $invoiceDate,
                    (string) $invoice->invoice_number,
                    $customer?->fein_no,
                    $customer?->company_name ?: $customer?->contact,
                    $customer?->address,
                    $customer?->city,
                    $customer?->state,
                    $customer?->zip_code,
                    $item,
                    $qty,
                    (float) $line->price,
                    $product,
                    $strictFein,
                    $missingFeinNames,
                );
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        foreach ($creditMemos as $memo) {
            $customer = $memo->customer;
            $memoDate = optional($memo->memo_date)?->format('Y-m-d') ?: '';
            foreach ($memo->lines as $line) {
                $item = $line->item;
                if (! $this->itemMatchesProduct($item, $product)) {
                    continue;
                }
                $qty = (float) $line->qty;
                if ($qty <= 0) {
                    continue;
                }
                $row = $this->scheduleRow(
                    $codes['returns'],
                    $memoDate,
                    $memoDate,
                    (string) $memo->memo_number,
                    $customer?->fein_no,
                    $customer?->company_name ?: $customer?->contact,
                    $customer?->address,
                    $customer?->city,
                    $customer?->state,
                    $customer?->zip_code,
                    $item,
                    $qty,
                    (float) $line->price,
                    $product,
                    $strictFein,
                    $missingFeinNames,
                );
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        if ($strictFein && $missingFeinNames !== []) {
            $names = array_values(array_unique($missingFeinNames));
            throw new RuntimeException(
                'A 9-digit FEIN is required for every supplier and customer on this MSA report. Add FEIN on Sales → Customers (or Purchasing → Suppliers) for: '.implode(', ', $names).'.'
            );
        }

        return $this->mergeDuplicateRows($rows);
    }

    /**
     * Michigan rejects a return when two schedule rows share Invoice + Brand Code + FEIN (+ Pack Size for cigarettes)
     * ("combination matches prior schedule"), so lines of one invoice with the same brand are summed into one row.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mergeDuplicateRows(array $rows): array
    {
        $merged = [];

        foreach ($rows as $row) {
            $key = implode('|', [
                $row['ScheduleCode'] ?? '',
                strtoupper((string) ($row['InvoiceNumber'] ?? '')),
                $row['BrandCode'] ?? '',
                $row['PurchaserSellerFEIN'] ?? '',
                $row['CigPackSize'] ?? '',
            ]);

            if (! isset($merged[$key])) {
                $merged[$key] = $row;

                continue;
            }

            $existing = $merged[$key];
            $address = $existing['Address'] ?? null;
            unset($existing['Address']);

            if (isset($row['PackCount'])) {
                $existing['PackCount'] = (string) ((int) ($existing['PackCount'] ?? 0) + (int) $row['PackCount']);
            }
            if (isset($row['WholesalePrice'])) {
                $existing['WholesalePrice'] = number_format((float) ($existing['WholesalePrice'] ?? 0) + (float) $row['WholesalePrice'], 2, '.', '');
            }
            if (isset($row['TotalOz']) || isset($existing['TotalOz'])) {
                $existing['TotalOz'] = number_format((float) ($existing['TotalOz'] ?? 0) + (float) ($row['TotalOz'] ?? 0), 2, '.', '');
            }
            if (isset($row['StickCount']) || isset($existing['StickCount'])) {
                $existing['StickCount'] = (string) ((int) ($existing['StickCount'] ?? 0) + (int) ($row['StickCount'] ?? 0));
            }

            // Keep XSD element order: TotalOz before StickCount, Address last.
            foreach (['TotalOz', 'StickCount'] as $field) {
                if (isset($existing[$field])) {
                    $value = $existing[$field];
                    unset($existing[$field]);
                    $existing[$field] = $value;
                }
            }
            if ($address !== null) {
                $existing['Address'] = $address;
            }

            $merged[$key] = $existing;
        }

        return array_values($merged);
    }

    protected function itemMatchesProduct(?Item $item, string $product): bool
    {
        return TobaccoItem::matchesProduct($item, $product);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function scheduleRow(
        string $scheduleCode,
        string $dateReceivedOrSold,
        string $invoiceDate,
        string $invoiceNumber,
        ?string $fein,
        ?string $name,
        ?string $address,
        ?string $city,
        ?string $state,
        ?string $zip,
        ?Item $item,
        float $qty,
        float $unitPrice,
        string $product,
        bool $strictFein = true,
        array &$missingFeinNames = [],
    ): ?array {
        $dateReceivedOrSold = $dateReceivedOrSold ?: ($invoiceDate ?: now()->toDateString());
        $invoiceDate = $invoiceDate ?: $dateReceivedOrSold;
        $partyFein = preg_replace('/\D+/', '', (string) $fein);
        if (strlen($partyFein) !== 9) {
            if ($strictFein) {
                $missingFeinNames[] = trim((string) ($name ?: 'UNKNOWN')) ?: 'UNKNOWN';
            }

            return null;
        }

        $brand = $this->brandCode($item, $product);
        $partyName = mb_substr((string) ($name ?: 'UNKNOWN'), 0, 50);
        $invoiceNo = mb_substr($invoiceNumber, 0, 30);
        $addr = [
            'AddressLine1' => $this->streetAddress($address),
            'City' => $this->city($city),
            'State' => strtoupper(mb_substr(trim((string) ($state ?: 'MI')), 0, 2)),
            'ZIPCode' => $this->zipCode($zip),
        ];

        if ($product === 'cigarettes') {
            return [
                'ScheduleCode' => $scheduleCode,
                'DateReceived' => $dateReceivedOrSold,
                'InvoiceDate' => $invoiceDate,
                'InvoiceNumber' => $invoiceNo,
                'PurchaserSellerFEIN' => $partyFein,
                'PurchaserSellerName' => $partyName,
                'BrandCode' => mb_substr($brand, 0, 16),
                'CigPackSize' => (string) $this->cigarettePackSize($item),
                'PackCount' => (string) max(1, (int) round($qty)),
                'Address' => $addr,
            ];
        }

        $row = [
            'ScheduleCode' => $scheduleCode,
            'DateReceivedOrSold' => $dateReceivedOrSold,
            'InvoiceDate' => $invoiceDate,
            'InvoiceNumber' => $invoiceNo,
            'PurchaserSellerFEIN' => $partyFein,
            'PurchaserSellerName' => $partyName,
            'BrandCode' => mb_substr($brand, 0, 16),
            'WholesalePrice' => number_format(round($qty * $unitPrice, 2), 2, '.', ''),
        ];

        $type = strtolower((string) ($item?->tobacco_product_type ?? ''));
        if (in_array($type, ['ryo'], true) || (float) ($item?->tobacco_total_oz ?? 0) > 0) {
            $row['TotalOz'] = number_format(max(0, (float) ($item?->tobacco_total_oz ?: 0) * $qty), 2, '.', '');
        }
        if (in_array($type, ['pc1', 'premium_cigar'], true) || (int) ($item?->tobacco_stick_count ?? 0) > 0) {
            $row['StickCount'] = (string) max(0, (int) (($item?->tobacco_stick_count ?: 0) * $qty));
        }
        $row['Address'] = $addr;

        return $row;
    }

    /**
     * BrandCode is a closed list in MI_TobCigEnumerations.xsd (TobCigBrandCode); anything else rejects the whole file,
     * so unknown codes fall back to the generic CIG / OTP code.
     */
    protected function brandCode(?Item $item, string $product): string
    {
        $default = $product === 'cigarettes' ? 'CIG' : 'OTP';
        $code = strtoupper(trim((string) ($item?->tobacco_brand_code ?? '')));
        if (preg_match('/^\d{1,2}$/', $code)) {
            $code = str_pad($code, 3, '0', STR_PAD_LEFT);
        }

        return preg_match('/^(034|058|075|092|093|095|096|097|098|100|101|102|106|CIG|OTP|PC1)(M1|M2)?$/', $code) ? $code : $default;
    }

    /**
     * TobCigStreetAddressType only allows letters, digits, "-", "/" and single spaces (no commas, leading/trailing spaces),
     * so "1234 W. 8 Mile Rd., Suite #5" becomes "1234 W 8 Mile Rd Suite 5".
     */
    protected function streetAddress(?string $address): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^A-Za-z0-9\-\/ ]+/', ' ', (string) $address)));
        $clean = trim((string) preg_replace('/^[^A-Za-z0-9]+/', '', mb_substr($clean, 0, 255)));

        return $clean !== '' ? $clean : 'UNKNOWN';
    }

    /**
     * TobCigCityType: letters with single spaces, max 22 chars, must end in a letter ("St. Clair Shores" is kept as "St Clair Shores").
     */
    protected function city(?string $city): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^A-Za-z ]+/', ' ', (string) $city)));
        $clean = trim(mb_substr($clean, 0, 22));

        return $clean !== '' ? $clean : 'UNKNOWN';
    }

    /**
     * ZIPCodeType: 5 or 9 digits ("48080-1234" → "480801234").
     */
    protected function zipCode(?string $zip): string
    {
        $digits = preg_replace('/\D+/', '', (string) $zip) ?: '';

        return match (true) {
            strlen($digits) >= 9 => substr($digits, 0, 9),
            strlen($digits) >= 5 => substr($digits, 0, 5),
            default => '00000',
        };
    }

    protected function cigarettePackSize(?Item $item): int
    {
        $packSize = (int) ($item?->cigarette_pack_size ?: 20);

        return in_array($packSize, [20, 25], true) ? $packSize : 20;
    }

    protected function appendStampInventory(SimpleXMLElement $returnNode, TobaccoStampInventory $stamps): void
    {
        $matrix = $stamps->matrix();

        foreach ([
            'BeginningUnaffixedInventory' => 'beginning_unaffixed',
            'EndingUnaffixedInventory' => 'ending_unaffixed',
            'BeginningAffixedInventory' => 'beginning_affixed',
            'EndingAffixedInventory' => 'ending_affixed',
        ] as $tag => $bucket) {
            $node = $returnNode->addChild($tag);
            foreach ($matrix[$bucket] as $code => $qty) {
                $node->addChild($code, (string) $qty);
            }
        }
    }
}
