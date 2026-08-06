<?php

use App\Livewire\Concerns\InteractsWithReportCriteria;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrderLine;
use App\Services\DocumentPdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Sales Report by Manufacturer')] class extends Component
{
    use InteractsWithReportCriteria;

    public string $customerId = '';

    public string $manufacturer = '';

    public function mount(): void
    {
        $this->initReportCriteria('reports.sales-by-manufacturer');
    }

    public function applyCriteria(): void
    {
        $this->resetErrorBag();
        $this->resolveDateWindow();
        if ($this->customerId !== '' && ! ctype_digit((string) $this->customerId)) {
            $this->customerId = '';
        }
        $this->reportReady = true;
        $this->showCriteria = false;
    }

    public function downloadCsv(): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $csv = [];
        foreach ($data['groups'] as $group) {
            $cname = $group['customer']?->company_name ?? '';
            $ccode = $group['customer']?->customer_id ?? '';
            foreach ($group['manufacturers'] as $mfr) {
                foreach ($mfr['rows'] as $line) {
                    $csv[] = [
                        $ccode, $cname, $mfr['name'],
                        optional($line->salesOrder?->order_date)?->format('Y-m-d'),
                        $line->item_code, $line->description,
                        $this->money($line->qty_ordered), $line->uom,
                        $this->money($line->price), $this->money($line->line_total),
                    ];
                }
            }
        }

        return $this->streamReportCsv('sales-by-manufacturer', [
            'Customer ID', 'Customer', 'Manufacturer', 'Date', 'Item Code', 'Description',
            'Qty', 'U/M', 'Price', 'Total',
        ], $csv);
    }

    public function downloadPdf(DocumentPdfService $pdfs): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $headers = ['Date', 'Item Code', 'Description', 'Qty', 'U/M', 'Price', 'Total'];
        $numCols = [3, 5, 6];
        $sections = [];
        foreach ($data['groups'] as $group) {
            $name = $group['customer']?->company_name ?: 'Unknown';
            foreach ($group['manufacturers'] as $mfr) {
                $rows = [];
                foreach ($mfr['rows'] as $line) {
                    $rows[] = [
                        optional($line->salesOrder?->order_date)?->format('n/j/Y'),
                        $line->item_code, $line->description,
                        number_format((float) $line->qty_ordered, 2),
                        $line->uom,
                        $this->moneyLabel($line->price),
                        $this->moneyLabel($line->line_total),
                    ];
                }
                $rows[] = [
                    '_totals' => true, 'Totals for '.$mfr['name'], '', '',
                    number_format($mfr['totals']['qty'], 2), '', '', $this->moneyLabel($mfr['totals']['total']),
                ];
                $sections[] = [
                    'title' => $name,
                    'subtitle' => $group['customer']?->customer_id,
                    'heading' => $mfr['name'],
                    'headers' => $headers,
                    'numCols' => $numCols,
                    'rows' => $rows,
                ];
            }
            $sections[] = [
                'headers' => $headers, 'numCols' => $numCols,
                'rows' => [[
                    '_grand' => true, 'Totals for '.$name, '', '',
                    number_format($group['totals']['qty'], 2), '', '', $this->moneyLabel($group['totals']['total']),
                ]],
            ];
        }

        return $this->streamReportPdf($pdfs, [
            'title' => 'Sales Report By Manufacturer',
            'period' => $this->periodLabel(),
            'sections' => $sections,
        ], 'sales-by-manufacturer');
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('company_name')
            ->get(['id', 'customer_id', 'company_name']);
        $manufacturers = Item::query()
            ->where('company_id', $companyId)
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer');

        $groups = collect();
        $grand = ['qty' => 0.0, 'total' => 0.0];

        if ($this->reportReady) {
            $cid = $this->customerId !== '' ? (int) $this->customerId : null;
            $mfr = trim($this->manufacturer);

            $lines = SalesOrderLine::query()
                ->with(['salesOrder.customer', 'item'])
                ->whereHas('salesOrder', function ($q) use ($companyId, $cid) {
                    $q->where('company_id', $companyId)
                        ->when($cid, fn ($q2) => $q2->where('customer_id', $cid))
                        ->whereDate('order_date', '>=', $this->dateFrom)
                        ->whereDate('order_date', '<=', $this->dateTo);
                })
                ->when($mfr !== '', fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('manufacturer', $mfr)))
                ->limit(10000)
                ->get();

            $byCust = $lines->groupBy(fn ($l) => (int) ($l->salesOrder?->customer_id ?: 0));

            $groups = $byCust->map(function ($custLines) use (&$grand) {
                $customer = $custLines->first()?->salesOrder?->customer;
                $byMfr = $custLines->groupBy(fn ($l) => (string) ($l->item?->manufacturer ?: '(No Manufacturer)'));
                $mfrGroups = $byMfr->map(function ($rows, $mfrName) {
                    return [
                        'name' => $mfrName,
                        'rows' => $rows->sortBy([
                            fn ($l) => -((int) optional($l->salesOrder?->order_date)?->format('Ymd') ?: 0),
                            fn ($l) => (string) $l->item_code,
                        ])->values(),
                        'totals' => [
                            'qty' => (float) $rows->sum(fn ($l) => (float) $l->qty_ordered),
                            'total' => (float) $rows->sum(fn ($l) => (float) $l->line_total),
                        ],
                    ];
                })->sortBy(fn ($g) => mb_strtoupper($g['name']))->values();

                $custTotals = [
                    'qty' => (float) $mfrGroups->sum(fn ($g) => $g['totals']['qty']),
                    'total' => (float) $mfrGroups->sum(fn ($g) => $g['totals']['total']),
                ];
                $grand['qty'] += $custTotals['qty'];
                $grand['total'] += $custTotals['total'];

                return [
                    'customer' => $customer,
                    'manufacturers' => $mfrGroups,
                    'totals' => $custTotals,
                ];
            })
                ->sortBy(fn ($g) => mb_strtoupper((string) ($g['customer']?->company_name ?? '')))
                ->values();
        }

        return [
            'customers' => $customers,
            'manufacturers' => $manufacturers,
            'groups' => $groups,
            'grand' => $grand,
            'periodLabel' => $this->periodLabel(),
            'filterManufacturer' => $this->manufacturer,
        ];
    }
}; ?>

@php
    $money = fn ($n) => '$'.number_format((float) $n, 2);
    $qty = fn ($n) => number_format((float) $n, 2);
@endphp

<div class="sbr-root">
    @include('livewire.pages.reports.partials.report-styles')
    <div class="sbr-page">
        <x-action-bar title="Sales Report by Manufacturer">
            <x-slot:trailing>
                @include('livewire.pages.reports.partials.report-actions', ['ready' => $reportReady])
            </x-slot:trailing>
        </x-action-bar>
        <div class="sbr-body">
            @if ($reportReady)
                <div class="sbr-toolbar no-print">
                    <span>Period: <strong>{{ $periodLabel }}</strong></span>
                    <span>Manufacturer: <strong>{{ $filterManufacturer !== '' ? $filterManufacturer : 'All' }}</strong></span>
                </div>
                <div class="sbr-report sbr-report-wide">
                    @forelse ($groups as $group)
                        @php
                            $cust = $group['customer'];
                            $name = $cust?->company_name ?: 'Unknown Customer';
                            $code = $cust?->customer_id ?: '—';
                        @endphp
                        <section class="sbr-customer">
                            <header class="sbr-customer-head">
                                <div class="sbr-customer-title">
                                    <span class="sbr-customer-name">{{ $name }}</span>
                                    <span class="sbr-customer-id">{{ $code }}</span>
                                </div>
                                @if ($filterManufacturer !== '')
                                    <div class="sbr-customer-line">Manufacturer: {{ $filterManufacturer }}</div>
                                @endif
                            </header>
                            @foreach ($group['manufacturers'] as $mfr)
                                <div class="sbr-mfr-head">{{ $mfr['name'] }}</div>
                                <table class="sbr-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Item Code</th>
                                            <th>Item Description</th>
                                            <th class="col-num">Quantity</th>
                                            <th>U/M</th>
                                            <th class="col-num">Price</th>
                                            <th class="col-num">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($mfr['rows'] as $line)
                                            <tr>
                                                <td>{{ optional($line->salesOrder?->order_date)?->format('n/j/Y') }}</td>
                                                <td>{{ $line->item_code }}</td>
                                                <td class="col-desc">{{ $line->description }}</td>
                                                <td class="col-num">{{ $qty($line->qty_ordered) }}</td>
                                                <td>{{ $line->uom }}</td>
                                                <td class="col-num">{{ $money($line->price) }}</td>
                                                <td class="col-num">{{ $money($line->line_total) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="sbr-totals-row">
                                            <td colspan="3" class="sbr-totals-label">Totals for {{ $mfr['name'] }}</td>
                                            <td class="col-num">{{ $qty($mfr['totals']['qty']) }}</td>
                                            <td></td>
                                            <td></td>
                                            <td class="col-num">{{ $money($mfr['totals']['total']) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            @endforeach
                            <table class="sbr-table" style="margin-top:.5rem">
                                <tr class="sbr-totals-row sbr-grand-row">
                                    <td colspan="3" class="sbr-totals-label">Totals for {{ $name }}</td>
                                    <td class="col-num">{{ $qty($group['totals']['qty']) }}</td>
                                    <td></td>
                                    <td></td>
                                    <td class="col-num">{{ $money($group['totals']['total']) }}</td>
                                </tr>
                            </table>
                        </section>
                    @empty
                        <div class="sbr-empty">No manufacturer sales found.</div>
                    @endforelse
                </div>
            @else
                <div class="sbr-placeholder no-print">Choose <strong>Report Criteria…</strong></div>
            @endif
        </div>
    </div>
    @include('livewire.pages.reports.partials.report-criteria', [
        'rcTitle' => 'Report Criteria',
        'rcShowCustomer' => true,
        'rcShowManufacturer' => true,
    ])
</div>
