<?php

use App\Models\CreditMemo;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\TobaccoStampInventory;
use App\Services\TobaccoXmlService;
use App\Services\TobaccoXmlValidator;
use App\Support\TobaccoItem;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app'), Title('MSA Report')] class extends Component
{
    /** cig_sw | otp_sw | cig_ua | otp_ua | msa_report */
    public string $return_type = 'msa_report';

    public string $process_type = 'T';

    public string $period_start = '';

    public string $period_end = '';

    /** Guard re-entry when snapping Sun–Sat week. */
    public bool $syncingWeek = false;

    /** @var list<string> */
    public array $validation_errors = [];

    public ?string $validation_status = null;

    public int $purchase_rows = 0;

    public int $sale_rows = 0;

    public int $return_rows = 0;

    public int $cig_sale_rows = 0;

    public int $otp_sale_rows = 0;

    public bool $includes_stamps = false;

    /**
     * Show a page alert instead of the Laravel debug screen.
     */
    public function exception(\Throwable $e, callable $stopPropagation): void
    {
        if (! $e instanceof \RuntimeException && ! $e instanceof \InvalidArgumentException) {
            return;
        }

        $this->validation_status = 'invalid';
        $this->validation_errors = [$this->professionalMsaMessage($e)];
        $stopPropagation();
    }

    private function professionalMsaMessage(\Throwable $e): string
    {
        $msg = trim($e->getMessage());
        if ($msg === '') {
            return 'Unable to generate this report. Check company, customer, and supplier MSA settings, then try again.';
        }

        if (stripos($msg, 'FEIN') !== false) {
            return $msg;
        }

        return $msg;
    }

    public function mount(): void
    {
        $this->applyCurrentMonthToToday();
        $this->refreshPreviewCounts();
    }

    public function updatedReturnType(): void
    {
        $this->validation_status = null;
        $this->validation_errors = [];
        $this->refreshPreviewCounts();
    }

    /**
     * Manual date fields: any past range (no auto week-snap).
     * Only caps future / incomplete present day.
     */
    public function updatedPeriodStart(): void
    {
        if ($this->syncingWeek) {
            return;
        }
        $this->normalizeManualPeriod(preferStart: true);
        $this->refreshPreviewCounts();
    }

    public function updatedPeriodEnd(): void
    {
        if ($this->syncingWeek) {
            return;
        }
        $this->normalizeManualPeriod(preferStart: false);
        $this->refreshPreviewCounts();
    }

    /**
     * Keep free date range valid: both dates set, start ≤ end, none after max past day.
     */
    private function normalizeManualPeriod(bool $preferStart): void
    {
        $this->syncingWeek = true;

        try {
            $max = $this->maxAllowedDate();

            $start = $this->parseDateOr($this->period_start, $this->currentMonthStart());
            $end = $this->parseDateOr($this->period_end, $this->maxAllowedDate());

            if ($start->gt($max)) {
                $start = $max->copy();
            }
            if ($end->gt($max)) {
                $end = $max->copy();
            }

            if ($start->gt($end)) {
                if ($preferStart) {
                    $end = $start->copy();
                } else {
                    $start = $end->copy();
                }
            }

            $this->period_start = $start->toDateString();
            $this->period_end = $end->toDateString();
        } finally {
            $this->syncingWeek = false;
        }
    }

    private function parseDateOr(?string $value, Carbon $fallback): Carbon
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return $fallback->copy()->startOfDay();
    }

    /** Latest calendar day allowed (today). Future dates are blocked. */
    private function maxAllowedDate(): Carbon
    {
        return $this->businessToday();
    }

    private function businessToday(): Carbon
    {
        return Carbon::parse(UserTimezone::now()->toDateString())->startOfDay();
    }

    private function currentMonthStart(): Carbon
    {
        return $this->businessToday()->copy()->startOfMonth();
    }

    /** Default / reset: 1st of this month through today. */
    public function applyCurrentMonthToToday(): void
    {
        $this->syncingWeek = true;

        try {
            $today = $this->maxAllowedDate();
            $this->period_start = $today->copy()->startOfMonth()->toDateString();
            $this->period_end = $today->toDateString();
        } finally {
            $this->syncingWeek = false;
        }
    }

    public function useThisMonth(): void
    {
        $this->applyCurrentMonthToToday();
        $this->refreshPreviewCounts();
    }

    /**
     * Start of the latest fully completed week (previous Sunday).
     * Used as default and for week nav buttons.
     */
    private function latestCompletedWeekStart(): Carbon
    {
        return $this->businessToday()->copy()->startOfWeek(Carbon::SUNDAY)->subWeek();
    }

    /**
     * Set period to the calendar week containing $anchor:
     * Sunday (start) through Saturday (end).
     * Only completed past weeks — never present or future.
     */
    public function applySundayToSaturdayWeek(Carbon|string|null $anchor = null): void
    {
        $this->syncingWeek = true;

        try {
            if ($anchor instanceof Carbon) {
                $day = $anchor->copy()->startOfDay();
            } elseif (is_string($anchor) && trim($anchor) !== '') {
                $day = $this->parseDateOr($anchor, $this->latestCompletedWeekStart());
            } else {
                $day = $this->latestCompletedWeekStart();
            }

            // Week starts Sunday, ends Saturday.
            $start = $day->copy()->startOfWeek(Carbon::SUNDAY);
            $end = $start->copy()->addDays(6);

            // Cap at last completed week (not this week / not future).
            $maxStart = $this->latestCompletedWeekStart();
            if ($start->gt($maxStart)) {
                $start = $maxStart->copy();
                $end = $start->copy()->addDays(6);
            }

            $this->period_start = $start->toDateString();
            $this->period_end = $end->toDateString();
        } finally {
            $this->syncingWeek = false;
        }
    }

    /** Jump to last completed week (latest allowed for week nav). */
    public function useLastWeek(): void
    {
        $this->applySundayToSaturdayWeek($this->latestCompletedWeekStart());
        $this->refreshPreviewCounts();
    }

    /** @deprecated Use useLastWeek — kept so old wire:click still works. */
    public function useCurrentWeek(): void
    {
        $this->useLastWeek();
    }

    /** Previous Sunday–Saturday week (from period start’s week). */
    public function previousWeek(): void
    {
        $start = $this->period_start !== ''
            ? Carbon::parse($this->period_start)->startOfWeek(Carbon::SUNDAY)->subWeek()
            : $this->latestCompletedWeekStart()->subWeek();
        $this->applySundayToSaturdayWeek($start);
        $this->refreshPreviewCounts();
    }

    /** Next Sunday–Saturday week (stops at last completed week). */
    public function nextWeek(): void
    {
        if (! $this->canGoNextWeek()) {
            $this->useLastWeek();

            return;
        }

        $start = $this->period_start !== ''
            ? Carbon::parse($this->period_start)->startOfWeek(Carbon::SUNDAY)->addWeek()
            : $this->latestCompletedWeekStart();
        $this->applySundayToSaturdayWeek($start);
        $this->refreshPreviewCounts();
    }

    /** True when period start’s week is before last completed week. */
    public function canGoNextWeek(): bool
    {
        if ($this->period_start === '') {
            return false;
        }

        $selectedStart = Carbon::parse($this->period_start)->startOfDay()->startOfWeek(Carbon::SUNDAY);

        return $selectedStart->lt($this->latestCompletedWeekStart());
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
        $this->includes_stamps = false;

        $company = auth()->user()->company;
        $tobLicense = $company?->msaLicenseNumber('otp') ?: '—';
        $cigLicense = $company?->msaLicenseNumber('cigarettes') ?: '—';
        $msaDistributorId = $company?->msaDistributorId() ?: '—';
        $sellerName = trim((string) ($company?->name ?? ''));
        $sellerAddress = trim(implode(', ', array_filter([
            (string) ($company?->address ?? ''),
            (string) ($company?->city ?? ''),
            trim((string) ($company?->state ?? '').' '.(string) ($company?->zip_code ?? '')),
        ])));
        $activeLicense = $product === 'otp' ? $tobLicense : $cigLicense;
        $activeLicenseLabel = $company?->msaLicenseLabel($product) ?? ($product === 'otp' ? 'Secondary Tob Number' : 'Secondary Cig Number');

        $returnOptions = [
            'cig_sw' => [
                'title' => 'Cigarette Secondary Wholesaler Return',
                'desc' => 'Tax-paid cigarette purchases, sales, and returns — StateLicenseNumber = Secondary Cig Number ('.$cigLicense.')',
                'schedules' => 'C101B · C108C · C101C',
            ],
            'otp_sw' => [
                'title' => 'OTP Secondary Wholesaler Return',
                'desc' => 'Tax-paid OTP / premium cigar purchases, sales, and returns — StateLicenseNumber = Secondary Tob Number ('.$tobLicense.')',
                'schedules' => 'T101B · T108C · T101C',
            ],
            'cig_ua' => [
                'title' => 'Cigarette Unclassified Acquirer Return',
                'desc' => 'Unstamped purchases, sales, returns, and stamp inventory R1–R6 — Secondary Cig Number ('.$cigLicense.')',
                'schedules' => 'C101A · C108C · C101C + stamps R1–R6',
            ],
            'otp_ua' => [
                'title' => 'OTP Unclassified Acquirer Return',
                'desc' => 'OTP purchases, sales, and returns — Secondary Tob Number ('.$tobLicense.')',
                'schedules' => 'T101A · T108C · T101C',
            ],
            'msa_report' => [
                'title' => 'MSA Report',
                'desc' => '',
                'schedules' => '',
            ],
        ];

        return [
            'scheduleCodes' => $codes,
            'returnOptions' => $returnOptions,
            'needsStamps' => $needsStamps,
            'isFileReport' => $isFileReport,
            'selectedReturn' => $returnOptions[$this->return_type] ?? null,
            'canGoNextWeek' => $this->canGoNextWeek(),
            'maxPeriodDate' => $this->maxAllowedDate()->toDateString(),
            'tobLicense' => $tobLicense,
            'cigLicense' => $cigLicense,
            'msaDistributorId' => $msaDistributorId,
            'sellerName' => $sellerName,
            'sellerAddress' => $sellerAddress,
            'activeLicense' => $activeLicense,
            'activeLicenseLabel' => $activeLicenseLabel,
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
                $allIds = Item::query()->select('id');
                TobaccoItem::constrainItemQuery($allIds);
                $cigIds = Item::query()->select('id');
                TobaccoItem::constrainCigarettesQuery($cigIds);

                $this->purchase_rows = 0;
                $this->cig_sale_rows = $this->countInvoiceLinesForItemIds($companyId, $cigIds);
                $this->sale_rows = $this->countInvoiceLinesForItemIds($companyId, $allIds);
                $this->otp_sale_rows = max(0, $this->sale_rows - $this->cig_sale_rows);
                $this->return_rows = 0;
            } catch (\Throwable) {
                $this->purchase_rows = 0;
                $this->sale_rows = 0;
                $this->cig_sale_rows = 0;
                $this->otp_sale_rows = 0;
                $this->return_rows = 0;
            }

            return;
        }

        try {
            [$filer, $product] = $this->resolveReturn();
            $companyId = (int) auth()->user()->company_id;
            $itemIds = Item::query()->select('id');
            if ($product === 'otp') {
                TobaccoItem::constrainItemQuery($itemIds);
                $cigIds = Item::query()->select('id');
                TobaccoItem::constrainCigarettesQuery($cigIds);
                $itemIds->whereNotIn('id', $cigIds);
            } else {
                TobaccoItem::constrainCigarettesQuery($itemIds);
            }

            $this->purchase_rows = $this->countReceivingLinesForItemIds($companyId, $itemIds->clone());
            $this->sale_rows = $this->countInvoiceLinesForItemIds($companyId, $itemIds->clone());
            $this->return_rows = $this->countCreditMemoLinesForItemIds($companyId, $itemIds->clone());
        } catch (\Throwable) {
            $this->purchase_rows = 0;
            $this->sale_rows = 0;
            $this->return_rows = 0;
        }
    }

    private function countInvoiceLinesForItemIds(int $companyId, $itemIds): int
    {
        return (int) DB::table('sales_order_lines as l')
            ->join('invoices as inv', 'inv.sales_order_id', '=', 'l.sales_order_id')
            ->where('inv.company_id', $companyId)
            ->whereDate('inv.invoice_date', '>=', $this->period_start)
            ->whereDate('inv.invoice_date', '<=', $this->period_end)
            ->whereIn('l.item_id', $itemIds)
            ->where(function ($q) {
                $q->where('l.qty_shipped', '>', 0)->orWhere('l.qty_ordered', '>', 0);
            })
            ->count();
    }

    private function countReceivingLinesForItemIds(int $companyId, $itemIds): int
    {
        return (int) DB::table('inventory_receiving_lines as l')
            ->join('inventory_receivings as r', 'r.id', '=', 'l.inventory_receiving_id')
            ->where('r.company_id', $companyId)
            ->whereDate('r.receipt_date', '>=', $this->period_start)
            ->whereDate('r.receipt_date', '<=', $this->period_end)
            ->whereIn('l.item_id', $itemIds)
            ->where(function ($q) {
                $q->where('l.qty_received', '>', 0)->orWhere('l.qty_ordered', '>', 0);
            })
            ->count();
    }

    private function countCreditMemoLinesForItemIds(int $companyId, $itemIds): int
    {
        return (int) DB::table('credit_memo_lines as l')
            ->join('credit_memos as m', 'm.id', '=', 'l.credit_memo_id')
            ->where('m.company_id', $companyId)
            ->whereDate('m.memo_date', '>=', $this->period_start)
            ->whereDate('m.memo_date', '<=', $this->period_end)
            ->whereIn('l.item_id', $itemIds)
            ->where('l.qty', '>', 0)
            ->count();
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

    public function downloadXml(TobaccoXmlService $xmlService, TobaccoXmlValidator $validator): StreamedResponse|RedirectResponse|null
    {
        if ($this->isMsaFileReport()) {
            return $this->downloadAllSalesFile();
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
        $filename = $this->msaDownloadFilename($product, 'xml');

        $this->skipRender();

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, ['Content-Type' => 'application/xml']);
    }

    public function downloadAllSalesFile(): RedirectResponse
    {
        $this->return_type = 'msa_report';
        $this->normalizeManualPeriod(preferStart: true);

        return redirect()->route('reports.msa.file', [
            'from' => $this->period_start,
            'to' => $this->period_end,
        ]);
    }

    private function msaDownloadFilename(string $product, string $ext): string
    {
        $license = auth()->user()->company?->msaLicenseNumber($product) ?: '0';
        $tag = $product === 'otp' ? 'tob' : 'cig';

        return 'msa-'.$tag.'-'.$license.'-'.$this->period_end.'.'.$ext;
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
            ->with(['supplier', 'lines.item.category', 'lines.item.subcategory'])
            ->where('company_id', $companyId)
            ->whereDate('receipt_date', '>=', $this->period_start)
            ->whereDate('receipt_date', '<=', $this->period_end)
            ->get();

        $invoices = Invoice::query()
            ->with(['customer', 'salesOrder.lines.item.category', 'salesOrder.lines.item.subcategory'])
            ->where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $this->period_start)
            ->whereDate('invoice_date', '<=', $this->period_end)
            ->get();

        $creditMemos = CreditMemo::query()
            ->with(['customer', 'lines.item.category', 'lines.item.subcategory'])
            ->where('company_id', $companyId)
            ->whereDate('memo_date', '>=', $this->period_start)
            ->whereDate('memo_date', '<=', $this->period_end)
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
                    <a href="{{ route('reports.msa.file', ['from' => $period_start, 'to' => $period_end]) }}" class="desk-btn desk-btn-primary">Download MSA Report</a>
                @else
                    <button type="button" wire:click="validateXml" class="desk-btn">Validate XML</button>
                    <button type="button" wire:click="downloadXml" class="desk-btn desk-btn-primary">Download XML</button>
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
                <strong>Cannot generate report</strong>
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
                        <label @class(['msa-return-option', 'is-active' => $return_type === $key]) wire:loading.class="is-busy">
                            <input type="radio" wire:model.live="return_type" value="{{ $key }}" class="msa-return-input" wire:loading.attr="disabled" />
                            <span class="msa-return-radio" aria-hidden="true"></span>
                            <span class="msa-return-copy">
                                <span class="msa-return-title">{{ $opt['title'] }}</span>
                                @if ($opt['desc'] !== '')
                                    <span class="msa-return-desc">{{ $opt['desc'] }}</span>
                                @endif
                                @if ($opt['schedules'] !== '')
                                    <span class="msa-return-codes">{{ $opt['schedules'] }}</span>
                                @endif
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
                        <input type="date" wire:model.live="period_start" class="desk-input" max="{{ $maxPeriodDate }}" />
                    </label>
                    <label class="msa-field">
                        <span>Period to</span>
                        <input type="date" wire:model.live="period_end" class="desk-input" max="{{ $maxPeriodDate }}" />
                    </label>
                    <label class="msa-field">
                        <span>Process type</span>
                        <select wire:model="process_type" class="desk-select">
                            <option value="T">Test (T)</option>
                            <option value="P">Production (P)</option>
                        </select>
                    </label>
                </div>
                <div class="msa-week-nav" style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.55rem">
                    <button type="button" wire:click="useThisMonth" class="desk-btn desk-btn-sm">This month (1 → today)</button>
                    <button type="button" wire:click="previousWeek" class="desk-btn desk-btn-sm">← Prev week</button>
                    <button type="button" wire:click="useLastWeek" class="desk-btn desk-btn-sm">Last week</button>
                    <button
                        type="button"
                        wire:click="nextWeek"
                        class="desk-btn desk-btn-sm"
                        @disabled(! $canGoNextWeek)
                        title="{{ $canGoNextWeek ? 'Next week' : 'Already on last completed week' }}"
                    >Next week →</button>
                </div>
                <p class="msa-period-hint">
                    Default is <strong>this month, day 1 through today</strong>.
                    Change <strong>Period from / Period to</strong> to any dates you want — download uses those dates.
                    Future dates are not allowed. Week buttons are optional.
                </p>

                @if ($selectedReturn)
                    <div class="msa-selected-box">
                        <div class="msa-selected-label">Selected</div>
                        <div class="msa-selected-title">{{ $selectedReturn['title'] }}</div>
                        @if (($selectedReturn['desc'] ?? '') !== '')
                            <div class="msa-selected-desc">{{ $selectedReturn['desc'] }}</div>
                        @endif
                        @if (! $isFileReport)
                            <div class="msa-selected-meta">
                                {{ $activeLicenseLabel }}: <strong>{{ $activeLicense }}</strong>
                            </div>
                            <div class="msa-selected-meta">
                                Schedules: <strong>{{ $scheduleCodes['purchases'] }}</strong> purchases ·
                                <strong>{{ $scheduleCodes['sales'] }}</strong> sales ·
                                <strong>{{ $scheduleCodes['returns'] }}</strong> returns
                            </div>
                        @else
                            <div class="msa-selected-meta">
                                MSA ID: <strong>{{ $msaDistributorId }}</strong>
                            </div>
                            <div class="msa-selected-meta">
                                Seller: <strong>{{ $sellerName }}</strong>
                                @if ($sellerAddress !== '')
                                    · {{ $sellerAddress }}
                                @endif
                            </div>
                            <div class="msa-selected-meta">
                                Purchaser: customer name, address, city, state, zip, and phone from each sale
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
            @if ($isFileReport)
                <div class="msa-stat">
                    <span class="msa-stat-lbl">All tobacco sales</span>
                    <strong class="msa-stat-val">{{ $sale_rows }}</strong>
                    <span class="msa-stat-code">Cig + OTP</span>
                </div>
                <div class="msa-stat">
                    <span class="msa-stat-lbl">Cigarette sales</span>
                    <strong class="msa-stat-val">{{ $cig_sale_rows }}</strong>
                    <span class="msa-stat-code">Cigarettes</span>
                </div>
                <div class="msa-stat">
                    <span class="msa-stat-lbl">OTP / tobacco sales</span>
                    <strong class="msa-stat-val">{{ $otp_sale_rows }}</strong>
                    <span class="msa-stat-code">OTP</span>
                </div>
            @else
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
                    <span class="msa-stat-lbl">License</span>
                    <strong class="msa-stat-val" style="font-size:1rem">{{ $activeLicense }}</strong>
                    <span class="msa-stat-code">{{ $activeLicenseLabel }}</span>
                </div>
            @endif
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
    </div>
</div>
