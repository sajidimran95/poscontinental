<?php

use App\Livewire\Concerns\InteractsWithReportCriteria;
use App\Models\SalesOrder;
use App\Services\DocumentPdfService;
use App\Support\StickCount;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Sales Report by Stick Count')] class extends Component
{
    use InteractsWithReportCriteria;

    public function mount(): void
    {
        $this->initReportCriteria('reports.sales-by-stick-count');
    }

    public function applyCriteria(): void
    {
        $this->resetErrorBag();
        $this->resolveDateWindow();
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
        foreach ($data['rows'] as $r) {
            $csv[] = [
                optional($r['date'])?->format('Y-m-d'),
                $r['inv'], $r['order'], $r['account'], $r['tax_id'], $r['customer'], $r['sticks'],
            ];
        }
        $csv[] = ['', '', '', '', '', 'Overall Totals', $data['grandSticks']];

        return $this->streamReportCsv('sales-by-stick-count', [
            'Date', 'Inv. No.', 'Order No.', 'Account No.', 'Tax ID', 'Customer', 'Stick Count',
        ], $csv);
    }

    public function downloadPdf(DocumentPdfService $pdfs): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $headers = ['Date', 'Inv. No.', 'Order No.', 'Account No.', 'Tax ID', 'Customer', 'Stick Count'];
        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                optional($r['date'])?->format('n/j/Y'),
                $r['inv'], $r['order'], $r['account'], $r['tax_id'], $r['customer'],
                number_format($r['sticks']),
            ];
        }
        $rows[] = ['_grand' => true, 'Overall Totals', '', '', '', '', '', number_format($data['grandSticks'])];

        return $this->streamReportPdf($pdfs, [
            'title' => 'Sales Report By Stick Count',
            'period' => $this->periodLabel(),
            'sections' => [['headers' => $headers, 'numCols' => [6], 'rows' => $rows]],
        ], 'sales-by-stick-count');
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $rows = collect();
        $grandSticks = 0;

        if ($this->reportReady) {
            $orders = SalesOrder::query()
                ->with(['customer', 'invoice', 'lines.item'])
                ->where('company_id', $companyId)
                ->whereDate('order_date', '>=', $this->dateFrom)
                ->whereDate('order_date', '<=', $this->dateTo)
                ->whereHas('invoice')
                ->orderByDesc('order_date')
                ->limit(5000)
                ->get()
                ->sortByDesc(fn ($o) => (string) ($o->invoice?->invoice_number ?? ''))
                ->values();

            $rows = $orders->map(function ($o) use (&$grandSticks) {
                $sticks = 0;
                foreach ($o->lines as $line) {
                    $sticks += StickCount::forLine($line->item, $line->qty_ordered);
                }
                $grandSticks += $sticks;

                return [
                    'date' => $o->invoice?->invoice_date ?? $o->order_date,
                    'inv' => $o->invoice?->invoice_number,
                    'order' => $o->order_number,
                    'account' => $o->customer?->customer_id,
                    'tax_id' => $o->customer?->fein_no,
                    'customer' => $o->customer?->company_name,
                    'sticks' => $sticks,
                ];
            });
        }

        return [
            'rows' => $rows,
            'grandSticks' => $grandSticks,
            'periodLabel' => $this->periodLabel(),
        ];
    }
}; ?>

<div class="sbr-root">
    @include('livewire.pages.reports.partials.report-styles')
    <div class="sbr-page">
        <x-action-bar title="Sales Report by Stick Count">
            <x-slot:trailing>
                @include('livewire.pages.reports.partials.report-actions', ['ready' => $reportReady])
            </x-slot:trailing>
        </x-action-bar>
        <div class="sbr-body">
            @if ($reportReady)
                <div class="sbr-toolbar no-print">
                    <span>Period: <strong>{{ $periodLabel }}</strong></span>
                </div>
                <div class="sbr-report sbr-report-wide">
                    <table class="sbr-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Inv. No.</th>
                                <th>Order No.</th>
                                <th>Account No.</th>
                                <th>Tax ID</th>
                                <th>Customer</th>
                                <th class="col-num">Stick Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td>{{ optional($r['date'])?->format('n/j/Y') }}</td>
                                    <td>{{ $r['inv'] }}</td>
                                    <td>{{ $r['order'] }}</td>
                                    <td>{{ $r['account'] }}</td>
                                    <td>{{ $r['tax_id'] }}</td>
                                    <td>{{ $r['customer'] }}</td>
                                    <td class="col-num">{{ number_format($r['sticks']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="sbr-empty">No invoices with stick count in range.</td></tr>
                            @endforelse
                            <tr class="sbr-totals-row sbr-grand-row">
                                <td colspan="6" class="sbr-totals-label">Overall Totals</td>
                                <td class="col-num">{{ number_format($grandSticks) }}</td>
                            </tr>
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
