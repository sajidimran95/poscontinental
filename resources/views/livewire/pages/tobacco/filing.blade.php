<?php

use App\Models\CreditMemo;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\TobaccoStampInventory;
use App\Services\TobaccoProductSalesFileService;
use App\Services\TobaccoXmlService;
use App\Services\TobaccoXmlValidator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app'), Title('MSA Report')] class extends Component
{
    /** cig_sw | otp_sw | cig_ua | otp_ua | msa_report */
    public string $return_type = 'cig_sw';

    public string $process_type = 'T';

    public string $period_start = '';

    public string $period_end = '';

    /** @var list<string> */
    public array $validation_errors = [];

    public ?string $validation_status = null;

    public int $purchase_rows = 0;

    public int $sale_rows = 0;

    public int $return_rows = 0;

    public bool $includes_stamps = false;

    public function mount(): void
    {
        // Full month for MSA / sales file (invoice dates), not forced to "today".
        $this->period_start = now()->startOfMonth()->toDateString();
        $this->period_end = now()->endOfMonth()->toDateString();
        $this->refreshPreviewCounts();
    }

    public function updatedReturnType(): void
    {
        $this->validation_status = null;
        $this->validation_errors = [];
        $this->refreshPreviewCounts();
    }

    public function updatedPeriodStart(): void
    {
        $this->refreshPreviewCounts();
    }

    public function updatedPeriodEnd(): void
    {
        $this->refreshPreviewCounts();
    }

    public function with(): array
    {
        $isFileReport = $this->isMsaFileReport();
        [$filer, $product] = $this->resolveReturn();
        $xmlService = app(TobaccoXmlService::class);
        $codes = $isFileReport
            ? ['purchases' => '—', 'sales' => 'Sales', 'returns' => '—']
            : $xmlService->scheduleCodes($filer, $product);
        $needsStamps = ! $isFileReport && $filer === 'unclassified_acquirer' && $product === 'cigarettes';
        $stamps = null;

        if ($needsStamps && $this->period_start && $this->period_end) {
            $stamps = TobaccoStampInventory::query()
                ->where('company_id', (int) auth()->user()->company_id)
                ->whereDate('period_start', '>=', $this->period_start)
                ->whereDate('period_end', '<=', $this->period_end)
                ->latest('id')
                ->first();
            $this->includes_stamps = (bool) $stamps;
        } else {
            $this->includes_stamps = false;
        }

        $returnOptions = [
            'cig_sw' => [
                'title' => 'Cigarette Secondary Wholesaler Return',
                'desc' => 'Purchases, sales, and returns for tax-paid cigarette inventory',
                'schedules' => 'C101B · C108C · C101C',
            ],
            'otp_sw' => [
                'title' => 'OTP Secondary Wholesaler Return',
                'desc' => 'Same coverage for OTP and premium cigar products',
                'schedules' => 'T101B · T108C · T101C',
            ],
            'cig_ua' => [
                'title' => 'Cigarette Unclassified Acquirer Return',
                'desc' => 'Full purchase/sale/return tracking plus stamp inventory (affixed and unaffixed)',
                'schedules' => 'C101A · C108C · C101C + stamps R1–R6',
            ],
            'otp_ua' => [
                'title' => 'OTP Unclassified Acquirer Return',
                'desc' => 'Same coverage for OTP and premium cigar products',
                'schedules' => 'T101A · T108C · T101C',
            ],
            'msa_report' => [
                'title' => 'MSA Report',
                'desc' => 'Product sales for the selected period, with company and customer location and state',
                'schedules' => 'Product sales file',
            ],
        ];

        return [
            'scheduleCodes' => $codes,
            'returnOptions' => $returnOptions,
            'needsStamps' => $needsStamps,
            'isFileReport' => $isFileReport,
            'selectedReturn' => $returnOptions[$this->return_type] ?? null,
            'readinessIssues' => $isFileReport
                ? []
                : $xmlService->readinessIssues(auth()->user()->company, $filer, $product, $stamps),
        ];
    }

    private function isMsaFileReport(): bool
    {
        return $this->return_type === 'msa_report';
    }

    private function refreshPreviewCounts(): void
    {
        if ($this->period_start === '' || $this->period_end === '') {
            return;
        }

        if ($this->isMsaFileReport()) {
            try {
                $companyId = (int) auth()->user()->company_id;
                $invoices = Invoice::query()
                    ->with(['salesOrder.lines.item'])
                    ->where('company_id', $companyId)
                    ->whereBetween('invoice_date', [$this->period_start, $this->period_end])
                    ->get();
                $sales = 0;
                $tobSales = 0;
                foreach ($invoices as $invoice) {
                    foreach ($invoice->salesOrder?->lines ?? [] as $line) {
                        $item = $line->item;
                        if (! $item) {
                            continue;
                        }
                        $qty = (float) (($line->qty_shipped ?? 0) > 0 ? $line->qty_shipped : $line->qty_ordered);
                        if ($qty == 0.0) {
                            continue;
                        }
                        $sales++;
                        $type = trim((string) ($item->tobacco_product_type ?? ''));
                        $isTob = $type !== ''
                            || filled($item->tobacco_brand_code)
                            || (int) ($item->tobacco_stick_count ?? 0) > 0
                            || (float) ($item->tobacco_total_oz ?? 0) > 0;
                        if ($isTob) {
                            $tobSales++;
                        }
                    }
                }
                // Prefer tobacco lines when any exist; otherwise count all invoiced sale lines.
                $this->purchase_rows = 0;
                $this->sale_rows = $tobSales > 0 ? $tobSales : $sales;
                $this->return_rows = 0;
            } catch (\Throwable) {
                $this->purchase_rows = 0;
                $this->sale_rows = 0;
                $this->return_rows = 0;
            }

            return;
        }

        try {
            [$filer, $product] = $this->resolveReturn();
            $xmlService = app(TobaccoXmlService::class);
            [$receivings, $invoices, $creditMemos] = array_slice($this->loadPeriodData(), 0, 3);
            $rows = $xmlService->buildScheduleRows($filer, $product, $receivings, $invoices, $creditMemos, false);
            $codes = $xmlService->scheduleCodes($filer, $product);
            $this->purchase_rows = count(array_filter($rows, fn ($r) => ($r['ScheduleCode'] ?? '') === $codes['purchases']));
            $this->sale_rows = count(array_filter($rows, fn ($r) => ($r['ScheduleCode'] ?? '') === $codes['sales']));
            $this->return_rows = count(array_filter($rows, fn ($r) => ($r['ScheduleCode'] ?? '') === $codes['returns']));
        } catch (\Throwable) {
            $this->purchase_rows = 0;
            $this->sale_rows = 0;
            $this->return_rows = 0;
        }
    }

    public function validateXml(TobaccoXmlService $xmlService, TobaccoXmlValidator $validator): void
    {
        if ($this->isMsaFileReport()) {
            $this->validation_status = 'valid';
            $this->validation_errors = [];

            return;
        }

        try {
            $payload = $this->buildPayload($xmlService);
        } catch (\Throwable $e) {
            $this->validation_status = 'invalid';
            $this->validation_errors = [$e->getMessage()];

            return;
        }

        $result = $validator->validate($payload);
        $this->validation_errors = $result['errors'];
        $this->validation_status = $result['valid'] ? 'valid' : 'invalid';
    }

    public function downloadXml(TobaccoXmlService $xmlService, TobaccoXmlValidator $validator): ?StreamedResponse
    {
        if ($this->isMsaFileReport()) {
            return $this->downloadAllSalesFile(app(TobaccoProductSalesFileService::class));
        }

        try {
            $payload = $this->buildPayload($xmlService);
        } catch (\Throwable $e) {
            $this->validation_status = 'invalid';
            $this->validation_errors = [$e->getMessage()];

            return null;
        }

        $result = $validator->validate($payload);
        $this->validation_errors = $result['errors'];
        $this->validation_status = $result['valid'] ? 'valid' : 'invalid';

        if (! $result['valid']) {
            return null;
        }

        [$filer, $product] = $this->resolveReturn();
        $filename = 'msa-'.$filer.'-'.$product.'-'.$this->period_start.'.xml';

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, ['Content-Type' => 'application/xml']);
    }

    public function downloadTxt(TobaccoXmlService $xmlService): StreamedResponse
    {
        if ($this->isMsaFileReport()) {
            return $this->downloadAllSalesFile(app(TobaccoProductSalesFileService::class));
        }

        $text = $this->buildTextPayload($xmlService);
        [$filer, $product] = $this->resolveReturn();
        $filename = 'msa-'.$filer.'-'.$product.'-'.$this->period_start.'.txt';

        return response()->streamDownload(function () use ($text) {
            echo $text;
        }, $filename, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Fixed-width TOB sales file — same design/data layout as JAPS_POS_TOB_*.txt */
    public function downloadAllSalesFile(TobaccoProductSalesFileService $fileService): StreamedResponse
    {
        $this->return_type = 'msa_report';

        $company = auth()->user()->company()->first() ?? auth()->user()->company;
        $companyId = (int) auth()->user()->company_id;

        $invoices = Invoice::query()
            ->with(['customer', 'salesOrder.lines.item'])
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$this->period_start, $this->period_end])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $payload = $fileService->build($company, $this->period_start, $this->period_end, $invoices);
        $filename = $fileService->filename($company, $this->period_end);

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** @return array{0: string, 1: string} */
    private function resolveReturn(): array
    {
        return match ($this->return_type) {
            'otp_sw' => ['secondary_wholesaler', 'otp'],
            'cig_ua' => ['unclassified_acquirer', 'cigarettes'],
            'otp_ua' => ['unclassified_acquirer', 'otp'],
            default => ['secondary_wholesaler', 'cigarettes'],
        };
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: ?TobaccoStampInventory}
     */
    private function loadPeriodData(): array
    {
        $companyId = (int) auth()->user()->company_id;
        [$filer, $product] = $this->resolveReturn();

        $receivings = InventoryReceiving::query()
            ->with(['supplier', 'lines.item'])
            ->where('company_id', $companyId)
            ->whereBetween('receipt_date', [$this->period_start, $this->period_end])
            ->get();

        $invoices = Invoice::query()
            ->with(['customer', 'salesOrder.lines.item'])
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$this->period_start, $this->period_end])
            ->get();

        $creditMemos = CreditMemo::query()
            ->with(['customer', 'lines.item'])
            ->where('company_id', $companyId)
            ->whereBetween('memo_date', [$this->period_start, $this->period_end])
            ->get();

        $stamps = null;
        $this->includes_stamps = false;
        if ($filer === 'unclassified_acquirer' && $product === 'cigarettes') {
            $stamps = TobaccoStampInventory::query()
                ->where('company_id', $companyId)
                ->whereDate('period_start', '>=', $this->period_start)
                ->whereDate('period_end', '<=', $this->period_end)
                ->latest('id')
                ->first();
            $this->includes_stamps = (bool) $stamps;
        }

        return [$receivings, $invoices, $creditMemos, $stamps];
    }

    private function refreshCounts(TobaccoXmlService $xmlService, string $xml): void
    {
        [$filer, $product] = $this->resolveReturn();
        $codes = $xmlService->scheduleCodes($filer, $product);
        $this->purchase_rows = substr_count($xml, '<ScheduleCode>'.$codes['purchases'].'</ScheduleCode>');
        $this->sale_rows = substr_count($xml, '<ScheduleCode>'.$codes['sales'].'</ScheduleCode>');
        $this->return_rows = substr_count($xml, '<ScheduleCode>'.$codes['returns'].'</ScheduleCode>');
    }

    private function buildTextPayload(TobaccoXmlService $xmlService): string
    {
        [$receivings, $invoices, $creditMemos, $stamps] = $this->loadPeriodData();
        [$filer, $product] = $this->resolveReturn();

        $text = $xmlService->buildTextReport(
            auth()->user()->company,
            $filer,
            $product,
            $this->period_start,
            $this->period_end,
            $receivings,
            $invoices,
            $creditMemos,
            $stamps,
            $this->process_type,
        );

        $xml = $xmlService->build(
            auth()->user()->company,
            $filer,
            $product,
            $this->period_start,
            $this->period_end,
            $receivings,
            $invoices,
            $creditMemos,
            $stamps,
            $this->process_type,
        );
        $this->refreshCounts($xmlService, $xml);

        return $text;
    }

    private function buildPayload(TobaccoXmlService $xmlService): string
    {
        [$receivings, $invoices, $creditMemos, $stamps] = $this->loadPeriodData();
        [$filer, $product] = $this->resolveReturn();

        $xml = $xmlService->build(
            auth()->user()->company,
            $filer,
            $product,
            $this->period_start,
            $this->period_end,
            $receivings,
            $invoices,
            $creditMemos,
            $stamps,
            $this->process_type,
        );
        $this->refreshCounts($xmlService, $xml);

        return $xml;
    }
}; ?>

<div class="msa-report-page">
    <x-action-bar title="MSA Report" />

    <div class="msa-report-body">
        <div class="msa-report-hero">
            <div>
                <h2 class="msa-report-title">Continental Wholesale — MSA Tobacco Report</h2>
                <p class="msa-report-sub">Michigan Treasury e-Services returns · Secondary Wholesaler &amp; Unclassified Acquirer (Cigarettes + OTP)</p>
            </div>
            <div class="msa-report-actions">
                @if ($isFileReport)
                    <button type="button" wire:click="downloadAllSalesFile" class="desk-btn desk-btn-primary">Download MSA Report</button>
                @else
                    <button type="button" wire:click="validateXml" class="desk-btn">Validate XML</button>
                    <button type="button" wire:click="downloadTxt" class="desk-btn desk-btn-primary">Download TXT</button>
                    <button type="button" wire:click="downloadXml" class="desk-btn">Download XML</button>
                @endif
                @if ($needsStamps)
                    <a href="{{ route('inventory.stamp-inventory') }}" wire:navigate class="desk-btn">Stamp Inventory</a>
                @endif
            </div>
        </div>

        @if (! empty($readinessIssues))
            <div class="msa-alert msa-alert-err" role="alert">
                <strong>Complete before XML download</strong>
                <ul>
                    @foreach ($readinessIssues as $issue)
                        <li>{{ $issue }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($validation_status === 'valid')
            <div class="msa-alert msa-alert-ok" role="status">XML passed schema validation for the selected MSA return.</div>
        @elseif ($validation_status === 'invalid')
            <div class="msa-alert msa-alert-err" role="alert">
                <strong>Validation failed</strong>
                <ul>
                    @foreach ($validation_errors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="msa-report-grid">
            <section class="msa-card msa-card-returns" aria-label="Select MSA return">
                <div class="msa-card-head">
                    <h3>Select return type</h3>
                </div>
                <div class="msa-return-list" role="radiogroup" aria-label="MSA return type">
                    @foreach ($returnOptions as $key => $opt)
                        <label @class(['msa-return-option', 'is-active' => $return_type === $key])>
                            <input type="radio" wire:model.live="return_type" value="{{ $key }}" class="msa-return-input" />
                            <span class="msa-return-radio" aria-hidden="true"></span>
                            <span class="msa-return-copy">
                                <span class="msa-return-title">{{ $opt['title'] }}</span>
                                <span class="msa-return-desc">{{ $opt['desc'] }}</span>
                                <span class="msa-return-codes">{{ $opt['schedules'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="msa-card" aria-label="Period and options">
                <div class="msa-card-head">
                    <h3>Period &amp; options</h3>
                </div>
                <div class="msa-field-grid">
                    <label class="msa-field">
                        <span>Period from</span>
                        <input type="date" wire:model.live="period_start" class="desk-input" />
                    </label>
                    <label class="msa-field">
                        <span>Period to</span>
                        <input type="date" wire:model.live="period_end" class="desk-input" />
                    </label>
                    <label class="msa-field">
                        <span>Process type</span>
                        <select wire:model="process_type" class="desk-select">
                            <option value="T">Test (T)</option>
                            <option value="P">Production (P)</option>
                        </select>
                    </label>
                </div>
                <p class="msa-period-hint">
                    Period uses invoice dates for the selected month or range.
                </p>

                @if ($selectedReturn)
                    <div class="msa-selected-box">
                        <div class="msa-selected-label">Selected</div>
                        <div class="msa-selected-title">{{ $selectedReturn['title'] }}</div>
                        <div class="msa-selected-desc">{{ $selectedReturn['desc'] }}</div>
                        @if (! $isFileReport)
                            <div class="msa-selected-meta">
                                Schedules: <strong>{{ $scheduleCodes['purchases'] }}</strong> purchases ·
                                <strong>{{ $scheduleCodes['sales'] }}</strong> sales ·
                                <strong>{{ $scheduleCodes['returns'] }}</strong> returns
                            </div>
                        @endif
                    </div>
                @endif

                @if ($needsStamps && ! $includes_stamps)
                    <div class="msa-stamp-warn">
                        Stamp inventory is required for this return. Save a period in
                        <a href="{{ route('inventory.stamp-inventory') }}" wire:navigate>Stamp Inventory</a>
                        before downloading Production XML.
                    </div>
                @endif
            </section>
        </div>

        <section class="msa-stats" aria-label="Period summary">
            <div class="msa-stat">
                <span class="msa-stat-lbl">Purchases</span>
                <strong class="msa-stat-val">{{ $purchase_rows }}</strong>
                <span class="msa-stat-code">{{ $scheduleCodes['purchases'] }}</span>
            </div>
            <div class="msa-stat">
                <span class="msa-stat-lbl">Sales</span>
                <strong class="msa-stat-val">{{ $sale_rows }}</strong>
                <span class="msa-stat-code">{{ $scheduleCodes['sales'] }}</span>
            </div>
            <div class="msa-stat">
                <span class="msa-stat-lbl">Returns</span>
                <strong class="msa-stat-val">{{ $return_rows }}</strong>
                <span class="msa-stat-code">{{ $scheduleCodes['returns'] }}</span>
            </div>
            <div class="msa-stat">
                <span class="msa-stat-lbl">Stamp inventory</span>
                <strong class="msa-stat-val">
                    @if (! $needsStamps)
                        N/A
                    @elseif ($includes_stamps)
                        Yes
                    @else
                        No
                    @endif
                </strong>
                <span class="msa-stat-code">{{ $needsStamps ? 'R1–R6' : '—' }}</span>
            </div>
        </section>

        <p class="msa-footnote">
            <strong>Company FEIN</strong> (File → Company Settings) = filer identity in the XML header.
            <strong>Secondary Tob Number</strong> = StateLicenseNumber on OTP / tobacco MSA returns.
            <strong>Secondary Cig Number</strong> = StateLicenseNumber on cigarette MSA returns.
            <strong>Supplier FEIN</strong> = purchase schedule parties.
            <strong>Customer FEIN</strong> = sales/return schedule parties.
            Items need tobacco type / brand code. Period data comes from receipts, invoices, and credit memos.
            <strong>MSA Report</strong> uses company and party <strong>state</strong> (and address/city/zip) from Company Settings and customer records.
        </p>
    </div>
</div>
