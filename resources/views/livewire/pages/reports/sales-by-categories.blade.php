<?php

use App\Livewire\Concerns\InteractsWithReportCriteria;
use App\Models\Category;
use App\Models\CreditMemoLine;
use App\Models\Customer;
use App\Models\SalesOrderLine;
use App\Services\DocumentPdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Sales Report by Categories')] class extends Component
{
    use InteractsWithReportCriteria;

    public string $customerId = '';

    public string $categoryId = '';

    public string $textFilter = '';

    public function mount(): void
    {
        $this->initReportCriteria('reports.sales-by-categories');
    }

    public function applyCriteria(): void
    {
        $this->resetErrorBag();
        $this->resolveDateWindow();
        if ($this->customerId !== '' && ! ctype_digit((string) $this->customerId)) {
            $this->customerId = '';
        }
        if ($this->categoryId !== '' && ! ctype_digit((string) $this->categoryId)) {
            $this->categoryId = '';
        }
        $this->textFilter = '';
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
            foreach ($group['rows'] as $row) {
                $csv[] = [
                    $code, $name, $row['category'], $row['sub'],
                    $this->money($row['sold_qty']), $this->money($row['sold_total']),
                    $this->money($row['ret_qty']), $this->money($row['ret_total']),
                    $this->money($row['total_sales']),
                ];
            }
        }

        return $this->streamReportCsv('sales-by-categories', [
            'Customer ID', 'Customer', 'Category', 'Sub Category',
            'Sold', 'Sold Total', 'Returned', 'Returned Total', 'Total Sales',
        ], $csv);
    }

    public function downloadPdf(DocumentPdfService $pdfs): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $headers = ['Category', 'Sub Category', 'Sold', 'Total', 'Returned', 'Total', 'Total Sales'];
        $numCols = [2, 3, 4, 5, 6];
        $sections = [];
        foreach ($data['groups'] as $group) {
            $name = $group['customer']?->company_name ?: 'Unknown';
            $rows = [];
            foreach ($group['rows'] as $row) {
                $rows[] = [
                    $row['category'], $row['sub'],
                    number_format((float) $row['sold_qty'], 0),
                    $this->moneyLabel($row['sold_total']),
                    number_format((float) $row['ret_qty'], 0),
                    $this->moneyLabel($row['ret_total']),
                    $this->moneyLabel($row['total_sales']),
                ];
            }
            $rows[] = [
                '_totals' => true, 'Totals for '.$name, '',
                number_format($group['totals']['sold_qty'], 0),
                $this->moneyLabel($group['totals']['sold_total']),
                number_format($group['totals']['ret_qty'], 0),
                $this->moneyLabel($group['totals']['ret_total']),
                $this->moneyLabel($group['totals']['total_sales']),
            ];
            $sections[] = [
                'title' => $name,
                'subtitle' => $group['customer']?->customer_id,
                'headers' => $headers,
                'numCols' => $numCols,
                'rows' => $rows,
            ];
        }

        return $this->streamReportPdf($pdfs, [
            'title' => 'Sales Report By Categories',
            'period' => $this->periodLabel(),
            'sections' => $sections,
        ], 'sales-by-categories');
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('company_name')
            ->get(['id', 'customer_id', 'company_name']);
        $categories = Category::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $groups = collect();

        if ($this->reportReady) {
            $cid = $this->customerId !== '' ? (int) $this->customerId : null;
            $catId = $this->categoryId !== '' ? (int) $this->categoryId : null;

            $lines = SalesOrderLine::query()
                ->with(['salesOrder.customer', 'item.category', 'item.subcategory'])
                ->whereHas('salesOrder', function ($q) use ($companyId, $cid) {
                    $q->where('company_id', $companyId)
                        ->when($cid, fn ($q2) => $q2->where('customer_id', $cid))
                        ->whereDate('order_date', '>=', $this->dateFrom)
                        ->whereDate('order_date', '<=', $this->dateTo);
                })
                ->when($catId, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('category_id', $catId)))
                ->limit(10000)
                ->get();

            $returns = CreditMemoLine::query()
                ->with(['creditMemo.customer', 'item.category', 'item.subcategory'])
                ->whereHas('creditMemo', function ($q) use ($companyId, $cid) {
                    $q->where('company_id', $companyId)
                        ->when($cid, fn ($q2) => $q2->where('customer_id', $cid))
                        ->whereDate('memo_date', '>=', $this->dateFrom)
                        ->whereDate('memo_date', '<=', $this->dateTo);
                })
                ->when($catId, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('category_id', $catId)))
                ->limit(5000)
                ->get();

            // Aggregate: customer_id => "category|sub" => buckets
            $bucket = [];
            foreach ($lines as $line) {
                $custId = (int) ($line->salesOrder?->customer_id ?: 0);
                $catName = $line->item?->category?->name ?: 'Uncategorized';
                $subName = $line->item?->subcategory?->name ?: '—';
                $key = $catName."\0".$subName;
                $bucket[$custId][$key] ??= [
                    'category' => $catName,
                    'sub' => $subName,
                    'sold_qty' => 0.0,
                    'sold_total' => 0.0,
                    'ret_qty' => 0.0,
                    'ret_total' => 0.0,
                    'customer' => $line->salesOrder?->customer,
                ];
                $bucket[$custId][$key]['sold_qty'] += (float) $line->qty_ordered;
                $bucket[$custId][$key]['sold_total'] += (float) $line->line_total;
            }
            foreach ($returns as $line) {
                $custId = (int) ($line->creditMemo?->customer_id ?: 0);
                $catName = $line->item?->category?->name ?: 'Uncategorized';
                $subName = $line->item?->subcategory?->name ?: '—';
                $key = $catName."\0".$subName;
                $bucket[$custId][$key] ??= [
                    'category' => $catName,
                    'sub' => $subName,
                    'sold_qty' => 0.0,
                    'sold_total' => 0.0,
                    'ret_qty' => 0.0,
                    'ret_total' => 0.0,
                    'customer' => $line->creditMemo?->customer,
                ];
                $bucket[$custId][$key]['ret_qty'] += (float) $line->qty;
                $bucket[$custId][$key]['ret_total'] += (float) $line->line_total;
            }

            $filter = mb_strtolower(trim($this->textFilter));
            $groups = collect($bucket)->map(function ($rows, $custId) use ($filter) {
                $customer = collect($rows)->first()['customer'] ?? null;
                $list = collect($rows)->map(function ($r) {
                    $r['total_sales'] = (float) $r['sold_total'] - (float) $r['ret_total'];

                    return $r;
                })->values();
                if ($filter !== '') {
                    $list = $list->filter(function ($r) use ($filter) {
                        return str_contains(mb_strtolower($r['category'].' '.$r['sub']), $filter);
                    })->values();
                }
                $list = $list->sortBy([
                    fn ($r) => mb_strtoupper($r['category']),
                    fn ($r) => mb_strtoupper($r['sub']),
                ])->values();

                return [
                    'customer' => $customer,
                    'rows' => $list,
                    'totals' => [
                        'sold_qty' => (float) $list->sum('sold_qty'),
                        'sold_total' => (float) $list->sum('sold_total'),
                        'ret_qty' => (float) $list->sum('ret_qty'),
                        'ret_total' => (float) $list->sum('ret_total'),
                        'total_sales' => (float) $list->sum('total_sales'),
                    ],
                ];
            })
                ->filter(fn ($g) => $g['rows']->isNotEmpty())
                ->sortBy(fn ($g) => mb_strtoupper((string) ($g['customer']?->company_name ?? '')))
                ->values();
        }

        return [
            'customers' => $customers,
            'categories' => $categories,
            'groups' => $groups,
            'periodLabel' => $this->periodLabel(),
        ];
    }
}; ?>

@php
    $money = fn ($n) => '$'.number_format((float) $n, 2);
    $num = fn ($n) => number_format((float) $n, 0);
@endphp

<div class="sbr-root">
    @include('livewire.pages.reports.partials.report-styles')
    <div class="sbr-page">
        <x-action-bar title="Sales Report by Categories">
            <x-slot:trailing>
                @include('livewire.pages.reports.partials.report-actions', ['ready' => $reportReady])
            </x-slot:trailing>
        </x-action-bar>
        <div class="sbr-body">
            @if ($reportReady)
                <div class="sbr-toolbar no-print">
                    <span>Period: <strong>{{ $periodLabel }}</strong></span>
                    <input type="search" class="sbr-select sbr-filter-box" wire:model.live.debounce.300ms="textFilter" placeholder="Filter results…" />
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
                            </header>
                            <table class="sbr-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Sub Category</th>
                                        <th class="col-num">Sold</th>
                                        <th class="col-num">Total</th>
                                        <th class="col-num">Returned</th>
                                        <th class="col-num">Total</th>
                                        <th class="col-num">Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['category'] }}</td>
                                            <td>{{ $row['sub'] }}</td>
                                            <td class="col-num">{{ $num($row['sold_qty']) }}</td>
                                            <td class="col-num">{{ $money($row['sold_total']) }}</td>
                                            <td class="col-num">{{ $num($row['ret_qty']) }}</td>
                                            <td class="col-num">{{ $money($row['ret_total']) }}</td>
                                            <td class="col-num">{{ $money($row['total_sales']) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="sbr-totals-row">
                                        <td colspan="2" class="sbr-totals-label">Totals for {{ $name }}</td>
                                        <td class="col-num">{{ $num($group['totals']['sold_qty']) }}</td>
                                        <td class="col-num">{{ $money($group['totals']['sold_total']) }}</td>
                                        <td class="col-num">{{ $num($group['totals']['ret_qty']) }}</td>
                                        <td class="col-num">{{ $money($group['totals']['ret_total']) }}</td>
                                        <td class="col-num">{{ $money($group['totals']['total_sales']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    @empty
                        <div class="sbr-empty">No category sales found for the selected criteria.</div>
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
        'rcShowCategory' => true,
    ])
</div>
