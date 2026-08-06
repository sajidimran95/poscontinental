<?php

use App\Livewire\Concerns\InteractsWithReportCriteria;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Services\DocumentPdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Sales Report by Customer')] class extends Component
{
    use InteractsWithReportCriteria;

    public string $customerId = '';

    public function mount(): void
    {
        $this->initReportCriteria('reports.sales-by-customer');
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

    /** @return array{groups: \Illuminate\Support\Collection, grand: array<string, float|int>} */
    private function reportData(): array
    {
        $companyId = auth()->user()->company_id;
        $grand = [
            'subtotal' => 0.0, 'miscellaneous' => 0.0, 'freight' => 0.0,
            'trade_discount' => 0.0, 'item_discounts' => 0.0, 'total' => 0.0, 'count' => 0,
        ];
        $cid = $this->customerId !== '' ? (int) $this->customerId : null;
        $orders = SalesOrder::query()
            ->with(['customer', 'invoice'])
            ->where('company_id', $companyId)
            ->when($cid, fn ($q) => $q->where('customer_id', $cid))
            ->whereDate('order_date', '>=', $this->dateFrom)
            ->whereDate('order_date', '<=', $this->dateTo)
            ->orderBy('order_date')
            ->orderBy('order_number')
            ->limit(5000)
            ->get()
            ->sortBy([
                fn ($o) => mb_strtoupper((string) ($o->customer?->company_name ?? '')),
                // Newest first within customer
                fn ($o) => -((int) optional($o->order_date)?->format('Ymd') ?: 0),
                fn ($o) => -((int) preg_replace('/\D/', '', (string) $o->order_number)),
            ])
            ->values();

        $groups = $orders->groupBy(fn ($o) => (int) ($o->customer_id ?: 0))
            ->map(function ($rows) use (&$grand) {
                $customer = $rows->first()?->customer;
                $totals = [
                    'subtotal' => (float) $rows->sum(fn ($o) => (float) $o->subtotal),
                    'miscellaneous' => (float) $rows->sum(fn ($o) => (float) $o->miscellaneous),
                    'freight' => (float) $rows->sum(fn ($o) => (float) $o->freight),
                    'trade_discount' => (float) $rows->sum(fn ($o) => (float) $o->trade_discount),
                    'item_discounts' => (float) $rows->sum(fn ($o) => (float) ($o->invoice?->total_discount ?? 0)),
                    'total' => (float) $rows->sum(fn ($o) => (float) $o->total),
                ];
                foreach ($totals as $k => $v) {
                    $grand[$k] += $v;
                }
                $grand['count'] += $rows->count();

                return ['customer' => $customer, 'rows' => $rows, 'totals' => $totals];
            })
            ->sortBy(fn ($g) => mb_strtoupper((string) ($g['customer']?->company_name ?? '')))
            ->values();

        return ['groups' => $groups, 'grand' => $grand];
    }

    public function downloadCsv(): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->reportData();
        $csvRows = [];
        foreach ($data['groups'] as $group) {
            $name = $group['customer']?->company_name ?? 'Unknown';
            $code = $group['customer']?->customer_id ?? '';
            foreach ($group['rows'] as $ord) {
                $csvRows[] = [
                    $code, $name,
                    optional($ord->order_date)?->format('Y-m-d'),
                    $ord->invoice?->invoice_number,
                    $ord->order_number,
                    $this->money($ord->subtotal),
                    $this->money($ord->miscellaneous),
                    $this->money($ord->freight),
                    $this->money($ord->trade_discount),
                    $this->money($ord->invoice?->total_discount ?? 0),
                    $this->money($ord->total),
                ];
            }
        }

        return $this->streamReportCsv('sales-by-customer', [
            'Customer ID', 'Customer', 'Date', 'Inv. No.', 'Order No.',
            'Subtotal', 'Miscellaneous', 'Freight', 'Trade Discount', 'Item Discounts', 'Total',
        ], $csvRows);
    }

    public function downloadPdf(DocumentPdfService $pdfs): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->reportData();
        $headers = ['Date', 'Inv. No.', 'Order No.', 'Subtotal', 'Misc', 'Freight', 'Trade Disc', 'Item Disc', 'Total'];
        $numCols = [3, 4, 5, 6, 7, 8];
        $sections = [];
        foreach ($data['groups'] as $group) {
            $cust = $group['customer'];
            $name = $cust?->company_name ?: 'Unknown Customer';
            $rows = [];
            foreach ($group['rows'] as $ord) {
                $rows[] = [
                    optional($ord->order_date)?->format('n/j/Y'),
                    $ord->invoice?->invoice_number ?? '',
                    $ord->order_number,
                    $this->moneyLabel($ord->subtotal),
                    $this->moneyLabel($ord->miscellaneous),
                    $this->moneyLabel($ord->freight),
                    $this->moneyLabel($ord->trade_discount),
                    $this->moneyLabel($ord->invoice?->total_discount ?? 0),
                    $this->moneyLabel($ord->total),
                ];
            }
            $rows[] = [
                '_totals' => true,
                'Totals for '.$name, '', '',
                $this->moneyLabel($group['totals']['subtotal']),
                $this->moneyLabel($group['totals']['miscellaneous']),
                $this->moneyLabel($group['totals']['freight']),
                $this->moneyLabel($group['totals']['trade_discount']),
                $this->moneyLabel($group['totals']['item_discounts']),
                $this->moneyLabel($group['totals']['total']),
            ];
            $lines = array_filter([
                trim((string) ($cust?->address ?? '')),
                ($cust?->city && $cust?->state)
                    ? $cust->city.', '.$cust->state.($cust->zip_code ? ' '.$cust->zip_code : '')
                    : null,
                $cust?->telephone ?: $cust?->mobile,
            ]);
            $sections[] = [
                'title' => $name,
                'subtitle' => $cust?->customer_id,
                'lines' => array_values($lines),
                'headers' => $headers,
                'numCols' => $numCols,
                'rows' => $rows,
            ];
        }
        if ($data['grand']['count'] > 0) {
            $sections[] = [
                'headers' => $headers,
                'numCols' => $numCols,
                'rows' => [[
                    '_grand' => true,
                    'Overall Totals ('.$data['grand']['count'].' orders)', '', '',
                    $this->moneyLabel($data['grand']['subtotal']),
                    $this->moneyLabel($data['grand']['miscellaneous']),
                    $this->moneyLabel($data['grand']['freight']),
                    $this->moneyLabel($data['grand']['trade_discount']),
                    $this->moneyLabel($data['grand']['item_discounts']),
                    $this->moneyLabel($data['grand']['total']),
                ]],
            ];
        }

        return $this->streamReportPdf($pdfs, [
            'title' => 'Sales Report By Customer',
            'period' => $this->periodLabel(),
            'sections' => $sections,
        ], 'sales-by-customer');
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('company_name')
            ->get(['id', 'customer_id', 'company_name']);

        $groups = collect();
        $grand = [
            'subtotal' => 0.0, 'miscellaneous' => 0.0, 'freight' => 0.0,
            'trade_discount' => 0.0, 'item_discounts' => 0.0, 'total' => 0.0, 'count' => 0,
        ];

        if ($this->reportReady) {
            $data = $this->reportData();
            $groups = $data['groups'];
            $grand = $data['grand'];
        }

        return [
            'customers' => $customers,
            'groups' => $groups,
            'grand' => $grand,
            'periodLabel' => $this->periodLabel(),
        ];
    }
}; ?>

@php $money = fn ($n) => '$'.number_format((float) $n, 2); @endphp

<div class="sbr-root">
    @include('livewire.pages.reports.partials.report-styles')

    <div class="sbr-page">
        <x-action-bar title="Sales Report by Customer">
            <x-slot:trailing>
                @include('livewire.pages.reports.partials.report-actions', ['ready' => $reportReady])
            </x-slot:trailing>
        </x-action-bar>

        <div class="sbr-body">
            @if ($reportReady)
                <div class="sbr-toolbar no-print">
                    <span>Period: <strong>{{ $periodLabel }}</strong></span>
                    <span>{{ number_format($grand['count']) }} order(s) · Grand total {{ $money($grand['total']) }}</span>
                </div>
                <div class="sbr-report">
                    @forelse ($groups as $group)
                        @php
                            $cust = $group['customer'];
                            $name = $cust?->company_name ?: 'Unknown Customer';
                            $code = $cust?->customer_id ?: '—';
                            $addr = trim((string) ($cust?->address ?? ''));
                            $cityLine = ($cust?->city && $cust?->state)
                                ? $cust->city.', '.$cust->state.($cust->zip_code ? ' '.$cust->zip_code : '')
                                : (string) ($cust?->city ?? '');
                            $phone = $cust?->telephone ?: ($cust?->mobile ?: '');
                        @endphp
                        <section class="sbr-customer">
                            <header class="sbr-customer-head">
                                <div class="sbr-customer-title">
                                    <span class="sbr-customer-name">{{ $name }}</span>
                                    <span class="sbr-customer-id">{{ $code }}</span>
                                </div>
                                @if ($addr !== '')<div class="sbr-customer-line">{{ $addr }}</div>@endif
                                @if ($cityLine !== '')<div class="sbr-customer-line">{{ $cityLine }}</div>@endif
                                @if ($phone !== '')<div class="sbr-customer-line">{{ $phone }}</div>@endif
                            </header>
                            <table class="sbr-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Inv. No.</th>
                                        <th>Order No.</th>
                                        <th class="col-num">Subtotal</th>
                                        <th class="col-num">Miscellaneous</th>
                                        <th class="col-num">Freight</th>
                                        <th class="col-num">Trade Discount</th>
                                        <th class="col-num">Item Discounts</th>
                                        <th class="col-num">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['rows'] as $ord)
                                        <tr>
                                            <td>{{ optional($ord->order_date)?->format('n/j/Y') }}</td>
                                            <td>{{ $ord->invoice?->invoice_number }}</td>
                                            <td>{{ $ord->order_number }}</td>
                                            <td class="col-num">{{ $money($ord->subtotal) }}</td>
                                            <td class="col-num">{{ $money($ord->miscellaneous) }}</td>
                                            <td class="col-num">{{ $money($ord->freight) }}</td>
                                            <td class="col-num">{{ $money($ord->trade_discount) }}</td>
                                            <td class="col-num">{{ $money($ord->invoice?->total_discount ?? 0) }}</td>
                                            <td class="col-num">{{ $money($ord->total) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="sbr-totals-row">
                                        <td colspan="3" class="sbr-totals-label">Totals for {{ $name }}</td>
                                        <td class="col-num">{{ $money($group['totals']['subtotal']) }}</td>
                                        <td class="col-num">{{ $money($group['totals']['miscellaneous']) }}</td>
                                        <td class="col-num">{{ $money($group['totals']['freight']) }}</td>
                                        <td class="col-num">{{ $money($group['totals']['trade_discount']) }}</td>
                                        <td class="col-num">{{ $money($group['totals']['item_discounts']) }}</td>
                                        <td class="col-num">{{ $money($group['totals']['total']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    @empty
                        <div class="sbr-empty">No sales orders found for the selected criteria.</div>
                    @endforelse

                    @if ($groups->isNotEmpty())
                        <table class="sbr-table">
                            <tr class="sbr-totals-row sbr-grand-row">
                                <td colspan="3" class="sbr-totals-label">Overall Totals ({{ number_format($grand['count']) }} orders)</td>
                                <td class="col-num">{{ $money($grand['subtotal']) }}</td>
                                <td class="col-num">{{ $money($grand['miscellaneous']) }}</td>
                                <td class="col-num">{{ $money($grand['freight']) }}</td>
                                <td class="col-num">{{ $money($grand['trade_discount']) }}</td>
                                <td class="col-num">{{ $money($grand['item_discounts']) }}</td>
                                <td class="col-num">{{ $money($grand['total']) }}</td>
                            </tr>
                        </table>
                    @endif
                </div>
            @else
                <div class="sbr-placeholder no-print">Choose <strong>Report Criteria…</strong> or wait for the criteria dialog.</div>
            @endif
        </div>
    </div>

    @include('livewire.pages.reports.partials.report-criteria', [
        'rcTitle' => 'Report Criteria',
        'rcShowCustomer' => true,
    ])
</div>
