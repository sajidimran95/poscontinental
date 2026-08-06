<?php

use App\Livewire\Concerns\InteractsWithReportCriteria;
use App\Models\InventoryReceiving;
use App\Models\Supplier;
use App\Services\DocumentPdfService;
use App\Support\StickCount;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Purchases Report by Stick Count')] class extends Component
{
    use InteractsWithReportCriteria;

    public string $supplierId = '';

    public function mount(): void
    {
        $this->initReportCriteria('reports.purchases-by-stick-count');
    }

    public function applyCriteria(): void
    {
        $this->resetErrorBag();
        $this->resolveDateWindow();
        if ($this->supplierId !== '' && ! ctype_digit((string) $this->supplierId)) {
            $this->supplierId = '';
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
        foreach ($data['rows'] as $r) {
            $csv[] = [
                optional($r['date'])?->format('Y-m-d'),
                $r['po'], $r['account'], $r['tax_id'], $r['vendor'], $r['sticks'],
            ];
        }
        $csv[] = ['', '', '', '', 'Overall Totals', $data['grandSticks']];

        return $this->streamReportCsv('purchases-by-stick-count', [
            'Date', 'PO No.', 'Account No.', 'Tax ID', 'Vendor/Supplier', 'Stick Count',
        ], $csv);
    }

    public function downloadPdf(DocumentPdfService $pdfs): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $headers = ['Date', 'PO No.', 'Account No.', 'Tax ID', 'Vendor/Supplier', 'Stick Count'];
        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                optional($r['date'])?->format('n/j/Y'),
                $r['po'], $r['account'], $r['tax_id'], $r['vendor'],
                number_format($r['sticks']),
            ];
        }
        $rows[] = ['_grand' => true, 'Overall Totals', '', '', '', '', number_format($data['grandSticks'])];

        return $this->streamReportPdf($pdfs, [
            'title' => 'Purchases Report by Stick Count',
            'period' => $this->periodLabel(),
            'sections' => [['headers' => $headers, 'numCols' => [5], 'rows' => $rows]],
        ], 'purchases-by-stick-count');
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $suppliers = Supplier::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('name')
            ->get(['id', 'supplier_id', 'name']);

        $rows = collect();
        $grandSticks = 0;

        if ($this->reportReady) {
            $sid = $this->supplierId !== '' ? (int) $this->supplierId : null;
            $receipts = InventoryReceiving::query()
                ->with(['supplier', 'purchaseOrder', 'lines.item'])
                ->where('company_id', $companyId)
                ->when($sid, fn ($q) => $q->where('supplier_id', $sid))
                ->whereDate('receipt_date', '>=', $this->dateFrom)
                ->whereDate('receipt_date', '<=', $this->dateTo)
                ->orderBy('receipt_date')
                ->limit(5000)
                ->get();

            $rows = $receipts->map(function ($rec) use (&$grandSticks) {
                $sticks = 0;
                foreach ($rec->lines as $line) {
                    $sticks += StickCount::forLine($line->item, $line->qty_received);
                }
                $grandSticks += $sticks;

                return [
                    'date' => $rec->receipt_date,
                    'po' => $rec->purchaseOrder?->po_number,
                    'account' => $rec->supplier?->supplier_id,
                    'tax_id' => $rec->supplier?->fein_no,
                    'vendor' => $rec->supplier?->name,
                    'sticks' => $sticks,
                ];
            });
        }

        return [
            'suppliers' => $suppliers,
            'rows' => $rows,
            'grandSticks' => $grandSticks,
            'periodLabel' => $this->periodLabel(),
        ];
    }
}; ?>

<div class="sbr-root">
    @include('livewire.pages.reports.partials.report-styles')
    <div class="sbr-page">
        <x-action-bar title="Purchases Report by Stick Count">
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
                                <th>PO No.</th>
                                <th>Account No.</th>
                                <th>Tax ID</th>
                                <th>Vendor/Supplier</th>
                                <th class="col-num">Stick Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td>{{ optional($r['date'])?->format('n/j/Y') }}</td>
                                    <td>{{ $r['po'] }}</td>
                                    <td>{{ $r['account'] }}</td>
                                    <td>{{ $r['tax_id'] }}</td>
                                    <td>{{ $r['vendor'] }}</td>
                                    <td class="col-num">{{ number_format($r['sticks']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="sbr-empty">No receiving data for the selected criteria.</td></tr>
                            @endforelse
                            <tr class="sbr-totals-row sbr-grand-row">
                                <td colspan="5" class="sbr-totals-label">Overall Totals</td>
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
    @include('livewire.pages.reports.partials.report-criteria', [
        'rcTitle' => 'Report Criteria',
        'rcShowSupplier' => true,
    ])
</div>
