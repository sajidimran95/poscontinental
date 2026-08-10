<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditMemo;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\TobaccoStampInventory;
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
            .'<Transmission xmlns="http://www.irs.gov/efile"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' xsi:schemaLocation="http://www.irs.gov/efile ./ExtendedCommon/Transmission.xsd"/>';
        $xml = new SimpleXMLElement($rootXml);

        $header = $xml->addChild('TransmissionHeader');
        $header->addChild('Jurisdiction', 'MI');
        $header->addChild('TransmissionID', $transmissionId);
        $header->addChild('Timestamp', $timestamp);
        $header->addChild('Transmitter', $transmitter);
        $header->addChild('ProcessType', $processType);

        $filing = $xml->addChild('TobCigFiling');
        $filing->addChild('SubmissionId', substr(preg_replace('/\D+/', '', $transmissionId) ?: '1', 0, 20));

        $returnHeader = $filing->addChild('TobaccoCigaretteHeader');
        $returnHeader->addChild('Jurisdiction', 'MI');
        $returnHeader->addChild('Timestamp', $timestamp);
        $returnHeader->addChild('TaxPeriodEndDate', $periodEnd);
        $returnHeader->addChild('TypeOfFiling', 'Original');
        $filer = $returnHeader->addChild('Filer');
        $filer->addChild('FEIN', $fein);

        $schedules = $this->buildScheduleRows($filerType, $product, $receivings, $invoices, $creditMemos);
        $returnNode = $filing->addChild($this->returnElementName($filerType, $product));
        $returnNode->addChild('reportCurrency', 'USD');
        $returnNode->addChild('StateLicenseNumber', $license);

        $scheduleParent = $returnNode->addChild($this->scheduleParentName($filerType, $product));
        foreach ($schedules as $row) {
            $node = $scheduleParent->addChild('Schedule');
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

        return $xml->asXML() ?: '';
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

        if (strlen($fein) < 9) {
            $issues[] = 'Company FEIN is required (File → Company Settings).';
        }
        if ($license === '') {
            $issues[] = $licenseLabel.' is required (File → Company Settings) for this MSA report.';
        }
        if ($transmitter === '' && strlen($fein) < 9) {
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
                );
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    protected function itemMatchesProduct(?Item $item, string $product): bool
    {
        if (! $item) {
            return false;
        }

        $type = strtolower(trim((string) ($item->tobacco_product_type ?? '')));
        if ($type === '') {
            return filled($item->tobacco_brand_code);
        }

        if ($product === 'cigarettes') {
            return in_array($type, ['cigarettes', 'cigarette', 'cig'], true);
        }

        return in_array($type, ['otp', 'other_tobacco', 'pc1', 'premium_cigar', 'ryo'], true);
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
    ): ?array {
        $dateReceivedOrSold = $dateReceivedOrSold ?: ($invoiceDate ?: now()->toDateString());
        $invoiceDate = $invoiceDate ?: $dateReceivedOrSold;
        $partyFein = preg_replace('/\D+/', '', (string) $fein);
        if (strlen($partyFein) < 9) {
            if ($strictFein) {
                throw new RuntimeException(
                    'Supplier/customer FEIN is required on all MSA schedule parties. Missing FEIN for: '.($name ?: 'UNKNOWN')
                );
            }

            return null;
        }

        $brand = strtoupper(trim((string) ($item?->tobacco_brand_code ?: ($product === 'cigarettes' ? 'CIG' : 'OTP'))));
        $partyName = mb_substr((string) ($name ?: 'UNKNOWN'), 0, 50);
        $invoiceNo = mb_substr($invoiceNumber, 0, 30);
        $addr = [
            'AddressLine1' => mb_substr((string) ($address ?: 'UNKNOWN'), 0, 255),
            'City' => mb_substr((string) ($city ?: 'UNKNOWN'), 0, 22),
            'State' => strtoupper(mb_substr((string) ($state ?: 'MI'), 0, 2)),
            'ZIPCode' => preg_replace('/\D+/', '', (string) $zip) ?: '00000',
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
