<?php

use App\Livewire\Concerns\InteractsWithReportCriteria;
use App\Models\Customer;
use App\Models\SalesOrderLine;
use App\Services\DocumentPdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Sales Report by Item')] class extends Component
{
    use InteractsWithReportCriteria;

    public string $customerId = '';

    /** Optional item filter (master Item). Empty = All. */
    public string $itemId = '';

    public function mount(): void
    {
        $this->initReportCriteria('reports.sales-by-item');
    }

    public function applyCriteria(): void
    {
        $this->resetErrorBag();
        $this->resolveDateWindow();
        if ($this->customerId !== '' && ! ctype_digit((string) $this->customerId)) {
            $this->customerId = '';
        }
        if ($this->itemId !== '' && ! ctype_digit((string) $this->itemId)) {
            $this->itemId = '';
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
            $name = $group['customer']?->company_name ?? '';
            $code = $group['customer']?->customer_id ?? '';
            foreach ($group['rows'] as $line) {
                $csv[] = [
                    $code, $name,
                    optional($line->salesOrder?->order_date)?->format('Y-m-d'),
                    $line->salesOrder?->order_number,
                    $line->item_code,
                    $line->description,
                    $this->money($line->qty_ordered),
                    $line->uom,
                    $this->money($line->price),
                    $this->money($line->line_total),
                ];
            }
        }

        return $this->streamReportCsv('sales-by-item', [
            'Customer ID', 'Customer', 'Date', 'Order No.', 'Item Code', 'Description',
            'Qty', 'U/M', 'Price', 'Total',
        ], $csv);
    }

    public function downloadPdf(DocumentPdfService $pdfs): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $headers = ['Date', 'Order No.', 'Item Code', 'Description', 'Qty', 'U/M', 'Price', 'Total'];
        $numCols = [4, 6, 7];
        $sections = [];
        foreach ($data['groups'] as $group) {
            $name = $group['customer']?->company_name ?: 'Unknown';
            $rows = [];
            foreach ($group['rows'] as $line) {
                $rows[] = [
                    optional($line->salesOrder?->order_date)?->format('n/j/Y'),
                    $line->salesOrder?->order_number,
                    $line->item_code,
                    $line->description,
                    number_format((float) $line->qty_ordered, 2),
                    $line->uom,
                    $this->moneyLabel($line->price),
                    $this->moneyLabel($line->line_total),
                ];
            }
            $rows[] = [
                '_totals' => true, 'Totals for '.$name, '', '', '',
                number_format($group['totals']['qty'], 2), '', '', $this->moneyLabel($group['totals']['total']),
            ];
            $sections[] = [
                'title' => $name,
                'subtitle' => $group['customer']?->customer_id,
                'headers' => $headers,
                'numCols' => $numCols,
                'rows' => $rows,
            ];
        }
        if ($data['grand']['count'] > 0) {
            $sections[] = [
                'headers' => $headers, 'numCols' => $numCols,
                'rows' => [[
                    '_grand' => true, 'Overall Totals', '', '', '',
                    number_format($data['grand']['qty'], 2), '', '', $this->moneyLabel($data['grand']['total']),
                ]],
            ];
        }

        return $this->streamReportPdf($pdfs, [
            'title' => 'Sales Report By Item',
            'period' => $this->periodLabel(),
            'sections' => $sections,
        ], 'sales-by-item');
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('company_name')
            ->get(['id', 'customer_id', 'company_name']);

        $items = \App\Models\Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('item_code')
            ->limit(3000)
            ->get(['id', 'item_code', 'description']);

        $groups = collect();
        $grand = ['qty' => 0.0, 'total' => 0.0, 'count' => 0];

        if ($this->reportReady) {
            $cid = $this->customerId !== '' ? (int) $this->customerId : null;
            $iid = $this->itemId !== '' ? (int) $this->itemId : null;
            $lines = SalesOrderLine::query()
                ->with(['salesOrder.customer'])
                ->whereHas('salesOrder', function ($q) use ($companyId, $cid) {
                    $q->where('company_id', $companyId)
                        ->when($cid, fn ($q2) => $q2->where('customer_id', $cid))
                        ->whereDate('order_date', '>=', $this->dateFrom)
                        ->whereDate('order_date', '<=', $this->dateTo);
                })
                ->when($iid, fn ($q) => $q->where('item_id', $iid))
                ->orderBy('id')
                ->limit(10000)
                ->get()
                // Customer A–Z, then newest orders first within customer
                ->sortBy([
                    fn ($l) => mb_strtoupper((string) ($l->salesOrder?->customer?->company_name ?? '')),
                    fn ($l) => -((int) optional($l->salesOrder?->order_date)?->format('Ymd') ?: 0),
                    fn ($l) => -((int) preg_replace('/\D/', '', (string) ($l->salesOrder?->order_number ?? '0'))),
                    fn ($l) => (int) $l->line_no,
                ])
                ->values();

            $groups = $lines->groupBy(fn ($l) => (int) ($l->salesOrder?->customer_id ?: 0))
                ->map(function ($rows) use (&$grand) {
                    $customer = $rows->first()?->salesOrder?->customer;
                    $totals = [
                        'qty' => (float) $rows->sum(fn ($l) => (float) $l->qty_ordered),
                        'total' => (float) $rows->sum(fn ($l) => (float) $l->line_total),
                    ];
                    $grand['qty'] += $totals['qty'];
                    $grand['total'] += $totals['total'];
                    $grand['count'] += $rows->count();

                    return ['customer' => $customer, 'rows' => $rows, 'totals' => $totals];
                })
                ->sortBy(fn ($g) => mb_strtoupper((string) ($g['customer']?->company_name ?? '')))
                ->values();
        }

        return [
            'customers' => $customers,
            'items' => $items,
            'groups' => $groups,
            'grand' => $grand,
            'periodLabel' => $this->periodLabel(),
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
        <x-action-bar title="Sales Report by Item">
            <x-slot:trailing>
                @include('livewire.pages.reports.partials.report-actions', ['ready' => $reportReady])
            </x-slot:trailing>
        </x-action-bar>
        <div class="sbr-body">
            @if ($reportReady)
                <div class="sbr-toolbar no-print">
                    <span>Period: <strong>{{ $periodLabel }}</strong></span>
                    <span>{{ number_format($grand['count']) }} lines · {{ $money($grand['total']) }}</span>
                </div>
                <div class="sbr-report sbr-report-wide">
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
                                        <th>Order No.</th>
                                        <th>Item Code</th>
                                        <th>Item Description</th>
                                        <th class="col-num">Quantity</th>
                                        <th>U/M</th>
                                        <th class="col-num">Price</th>
                                        <th class="col-num">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['rows'] as $line)
                                        <tr>
                                            <td>{{ optional($line->salesOrder?->order_date)?->format('n/j/Y') }}</td>
                                            <td>{{ $line->salesOrder?->order_number }}</td>
                                            <td>{{ $line->item_code }}</td>
                                            <td class="col-desc">{{ $line->description }}</td>
                                            <td class="col-num">{{ $qty($line->qty_ordered) }}</td>
                                            <td>{{ $line->uom }}</td>
                                            <td class="col-num">{{ $money($line->price) }}</td>
                                            <td class="col-num">{{ $money($line->line_total) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="sbr-totals-row">
                                        <td colspan="4" class="sbr-totals-label">Totals for {{ $name }}</td>
                                        <td class="col-num">{{ $qty($group['totals']['qty']) }}</td>
                                        <td></td>
                                        <td></td>
                                        <td class="col-num">{{ $money($group['totals']['total']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    @empty
                        <div class="sbr-empty">No item lines found for the selected criteria.</div>
                    @endforelse
                    @if ($groups->isNotEmpty())
                        <table class="sbr-table">
                            <tr class="sbr-totals-row sbr-grand-row">
                                <td colspan="4" class="sbr-totals-label">Overall Totals</td>
                                <td class="col-num">{{ $qty($grand['qty']) }}</td>
                                <td></td>
                                <td></td>
                                <td class="col-num">{{ $money($grand['total']) }}</td>
                            </tr>
                        </table>
                    @endif
                </div>
            @else
                <div class="sbr-placeholder no-print">Choose <strong>Report Criteria…</strong></div>
            @endif
        </div>
    </div>
    @include('livewire.pages.reports.partials.report-criteria', [
        'rcTitle' => 'Report Criteria',
        'rcShowCustomer' => true,
        'rcShowItem' => true,
    ])
</div>
