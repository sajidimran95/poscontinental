<?php

use App\Livewire\Concerns\InteractsWithReportCriteria;
use App\Models\SalesOrder;
use App\Services\DocumentPdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Sales Report by Totals')] class extends Component
{
    use InteractsWithReportCriteria;

    public string $textFilter = '';

    public function mount(): void
    {
        $this->initReportCriteria('reports.sales-by-totals');
    }

    public function applyCriteria(): void
    {
        $this->resetErrorBag();
        $this->resolveDateWindow();
        $this->textFilter = '';
        $this->reportReady = true;
        $this->showCriteria = false;
    }

    /**
     * Item discounts column: invoice total_discount when billed, else sum of line discounts.
     * Total column: stored sales_orders.total (Subtotal − trade + freight + misc + tax).
     * Line item discounts are already in Subtotal, so Total ≠ Subtotal − Item Disc + Tax alone.
     */
    private function mapOrderRow(SalesOrder $o): array
    {
        $lineDiscounts = $o->relationLoaded('lines')
            ? (float) $o->lines->sum(fn ($l) => (float) $l->discount)
            : 0.0;
        $itemDiscounts = $o->invoice
            ? (float) $o->invoice->total_discount
            : $lineDiscounts;

        return [
            'date' => $o->order_date,
            'inv' => $o->invoice?->invoice_number,
            'order' => $o->order_number,
            'account' => $o->customer?->customer_id,
            'customer' => $o->customer?->company_name,
            'subtotal' => (float) $o->subtotal,
            'item_discounts' => $itemDiscounts,
            'tax' => (float) $o->tax,
            'total' => (float) $o->total,
        ];
    }

    public function downloadCsv(): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $csv = [];
        foreach ($data['rows'] as $r) {
            $csv[] = [
                optional($r['date'])?->format('Y-m-d'),
                $r['inv'], $r['order'], $r['account'], $r['customer'],
                $this->money($r['subtotal']), $this->money($r['item_discounts']),
                $this->money($r['tax']), $this->money($r['total']),
            ];
        }

        return $this->streamReportCsv('sales-by-totals', [
            'Date', 'Inv. No.', 'Order No.', 'Account No.', 'Customer',
            'Subtotal', 'Item Discounts', 'Tax', 'Total',
        ], $csv);
    }

    public function downloadPdf(DocumentPdfService $pdfs): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $headers = ['Date', 'Inv. No.', 'Order No.', 'Account No.', 'Customer', 'Subtotal', 'Item Disc', 'Tax', 'Total'];
        $numCols = [5, 6, 7, 8];
        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                optional($r['date'])?->format('n/j/Y'),
                $r['inv'], $r['order'], $r['account'], $r['customer'],
                $this->moneyLabel($r['subtotal']),
                $this->moneyLabel($r['item_discounts']),
                $this->moneyLabel($r['tax']),
                $this->moneyLabel($r['total']),
            ];
        }
        if ($data['grand']['count'] > 0) {
            $rows[] = [
                '_grand' => true, 'Overall Totals', '', '', '', '',
                $this->moneyLabel($data['grand']['subtotal']),
                $this->moneyLabel($data['grand']['item_discounts']),
                $this->moneyLabel($data['grand']['tax']),
                $this->moneyLabel($data['grand']['total']),
            ];
        }

        return $this->streamReportPdf($pdfs, [
            'title' => 'Sales Report By Totals',
            'period' => $this->periodLabel(),
            'sections' => [[
                'headers' => $headers,
                'numCols' => $numCols,
                'rows' => $rows,
            ]],
        ], 'sales-by-totals');
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $rows = collect();
        $grand = [
            'subtotal' => 0.0, 'item_discounts' => 0.0, 'tax' => 0.0, 'total' => 0.0, 'count' => 0,
        ];

        if ($this->reportReady) {
            // Newest first (date desc, order no desc)
            $orders = SalesOrder::query()
                ->with(['customer', 'invoice', 'lines'])
                ->where('company_id', $companyId)
                ->whereDate('order_date', '>=', $this->dateFrom)
                ->whereDate('order_date', '<=', $this->dateTo)
                ->orderByDesc('order_date')
                ->orderByDesc('order_number')
                ->limit(5000)
                ->get();

            $filter = mb_strtolower(trim($this->textFilter));
            $rows = $orders->map(fn (SalesOrder $o) => $this->mapOrderRow($o))
                ->when($filter !== '', function ($col) use ($filter) {
                    return $col->filter(function ($r) use ($filter) {
                        $hay = mb_strtolower(implode(' ', [
                            $r['inv'], $r['order'], $r['account'], $r['customer'],
                        ]));

                        return str_contains($hay, $filter);
                    })->values();
                });

            foreach ($rows as $r) {
                $grand['subtotal'] += $r['subtotal'];
                $grand['item_discounts'] += $r['item_discounts'];
                $grand['tax'] += $r['tax'];
                $grand['total'] += $r['total'];
                $grand['count']++;
            }
        }

        return [
            'rows' => $rows,
            'grand' => $grand,
            'periodLabel' => $this->periodLabel(),
        ];
    }
}; ?>

@php $money = fn ($n) => '$'.number_format((float) $n, 2); @endphp

<div class="sbr-root">
    @include('livewire.pages.reports.partials.report-styles')
    <div class="sbr-page">
        <x-action-bar title="Sales Report by Totals">
            <x-slot:trailing>
                @include('livewire.pages.reports.partials.report-actions', ['ready' => $reportReady])
            </x-slot:trailing>
        </x-action-bar>
        <div class="sbr-body">
            @if ($reportReady)
                <div class="sbr-toolbar no-print">
                    <span>Period: <strong>{{ $periodLabel }}</strong></span>
                    <input type="search" class="sbr-select sbr-filter-box" wire:model.live.debounce.300ms="textFilter" placeholder="Filter results…" />
                    <span>{{ number_format($grand['count']) }} row(s)</span>
                    <span class="text-slate-500" title="Stored order total = Subtotal − Trade + Freight + Misc + Tax. Line discounts are already in Subtotal.">
                        Total = saved order amount (not Subtotal − Item Disc + Tax alone)
                    </span>
                </div>
                <div class="sbr-report sbr-report-wide">
                    <table class="sbr-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Inv. No.</th>
                                <th>Order No.</th>
                                <th>Account No.</th>
                                <th>Customer</th>
                                <th class="col-num">Subtotal</th>
                                <th class="col-num">Item Discounts</th>
                                <th class="col-num">Tax</th>
                                <th class="col-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td>{{ optional($r['date'])?->format('n/j/Y') }}</td>
                                    <td>{{ $r['inv'] }}</td>
                                    <td>{{ $r['order'] }}</td>
                                    <td>{{ $r['account'] }}</td>
                                    <td>{{ $r['customer'] }}</td>
                                    <td class="col-num">{{ $money($r['subtotal']) }}</td>
                                    <td class="col-num">{{ $money($r['item_discounts']) }}</td>
                                    <td class="col-num">{{ $money($r['tax']) }}</td>
                                    <td class="col-num">{{ $money($r['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="sbr-empty">No orders found.</td></tr>
                            @endforelse
                            @if ($rows->isNotEmpty())
                                <tr class="sbr-totals-row sbr-grand-row">
                                    <td colspan="5" class="sbr-totals-label">Overall Totals</td>
                                    <td class="col-num">{{ $money($grand['subtotal']) }}</td>
                                    <td class="col-num">{{ $money($grand['item_discounts']) }}</td>
                                    <td class="col-num">{{ $money($grand['tax']) }}</td>
                                    <td class="col-num">{{ $money($grand['total']) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @else
                <div class="sbr-placeholder no-print">Choose <strong>Report Criteria…</strong></div>
            @endif
        </div>
    </div>
    @include('livewire.pages.reports.partials.report-criteria', ['rcTitle' => 'Report Criteria'])
</div>
