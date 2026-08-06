<?php

use App\Livewire\Concerns\InteractsWithReportCriteria;
use App\Models\InventoryReceivingLine;
use App\Models\Item;
use App\Models\Supplier;
use App\Services\DocumentPdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Purchases Report by Item')] class extends Component
{
    use InteractsWithReportCriteria;

    public string $supplierId = '';

    public string $itemId = '';

    public function mount(): void
    {
        $this->initReportCriteria('reports.purchases-by-item');
    }

    public function applyCriteria(): void
    {
        $this->resetErrorBag();
        $this->resolveDateWindow();
        if ($this->supplierId !== '' && ! ctype_digit((string) $this->supplierId)) {
            $this->supplierId = '';
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
            $sname = $group['supplier']?->name ?? '';
            $scode = $group['supplier']?->supplier_id ?? '';
            foreach ($group['rows'] as $r) {
                $csv[] = [
                    $scode, $sname,
                    optional($r['date'])?->format('Y-m-d'),
                    $r['po'], $r['item_code'], $r['description'],
                    $this->money($r['qty']), $r['uom'],
                    $this->money($r['price']), $this->money($r['total']),
                ];
            }
        }
        $csv[] = ['', '', '', '', '', 'Overall Totals', $this->money($data['grand']['qty']), '', '', $this->money($data['grand']['total'])];

        return $this->streamReportCsv('purchases-by-item', [
            'Supplier ID', 'Supplier', 'Date Received', 'PO No.', 'Item Code', 'Description',
            'Qty', 'U/M', 'Price', 'Total',
        ], $csv);
    }

    public function downloadPdf(DocumentPdfService $pdfs): mixed
    {
        if (! $this->requireReportReady()) {
            return null;
        }
        $data = $this->with();
        $headers = ['Date Received', 'PO No.', 'Item Code', 'Description', 'Qty', 'U/M', 'Price', 'Total'];
        $numCols = [4, 6, 7];
        $sections = [];
        foreach ($data['groups'] as $group) {
            $sname = $group['supplier']?->name ?: 'Unknown Supplier';
            $rows = [];
            foreach ($group['rows'] as $r) {
                $rows[] = [
                    optional($r['date'])?->format('n/j/Y'),
                    $r['po'], $r['item_code'], $r['description'],
                    number_format((float) $r['qty'], 2),
                    $r['uom'],
                    $this->moneyLabel($r['price']),
                    $this->moneyLabel($r['total']),
                ];
            }
            $rows[] = [
                '_totals' => true, 'Totals for '.$sname, '', '', '',
                number_format($group['totals']['qty'], 2), '', '', $this->moneyLabel($group['totals']['total']),
            ];
            $sections[] = [
                'title' => $sname,
                'subtitle' => $group['supplier']?->supplier_id,
                'headers' => $headers,
                'numCols' => $numCols,
                'rows' => $rows,
            ];
        }
        $sections[] = [
            'headers' => $headers, 'numCols' => $numCols,
            'rows' => [[
                '_grand' => true, 'Overall Totals', '', '', '',
                number_format($data['grand']['qty'], 2), '', '', $this->moneyLabel($data['grand']['total']),
            ]],
        ];

        return $this->streamReportPdf($pdfs, [
            'title' => 'Purchases Report by Item',
            'period' => $this->periodLabel(),
            'sections' => $sections,
        ], 'purchases-by-item');
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $suppliers = Supplier::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('name')
            ->get(['id', 'supplier_id', 'name']);
        $items = Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->orderBy('item_code')
            ->limit(2000)
            ->get(['id', 'item_code', 'description']);

        $groups = collect();
        $grand = ['qty' => 0.0, 'total' => 0.0];

        if ($this->reportReady) {
            $sid = $this->supplierId !== '' ? (int) $this->supplierId : null;
            $iid = $this->itemId !== '' ? (int) $this->itemId : null;

            $lines = InventoryReceivingLine::query()
                ->with(['receiving.supplier', 'receiving.purchaseOrder', 'item'])
                ->whereHas('receiving', function ($q) use ($companyId, $sid) {
                    $q->where('company_id', $companyId)
                        ->when($sid, fn ($q2) => $q2->where('supplier_id', $sid))
                        ->whereDate('receipt_date', '>=', $this->dateFrom)
                        ->whereDate('receipt_date', '<=', $this->dateTo);
                })
                ->when($iid, fn ($q) => $q->where('item_id', $iid))
                ->limit(10000)
                ->get();

            $groups = $lines->groupBy(fn ($l) => (int) ($l->receiving?->supplier_id ?: 0))
                ->map(function ($rows) use (&$grand) {
                    $supplier = $rows->first()?->receiving?->supplier;
                    $list = $rows->map(function ($l) {
                        $qty = (float) $l->qty_received;
                        $price = (float) $l->unit_cost;
                        $total = $qty * $price;

                        return [
                            'date' => $l->receiving?->receipt_date,
                            'po' => $l->receiving?->purchaseOrder?->po_number,
                            'item_code' => $l->item_code,
                            'description' => $l->description,
                            'qty' => $qty,
                            'uom' => $l->uom,
                            'price' => $price,
                            'total' => $total,
                        ];
                    })->sortBy(fn ($r) => optional($r['date'])?->format('Y-m-d').' '.($r['item_code'] ?? ''))->values();

                    $totals = [
                        'qty' => (float) $list->sum('qty'),
                        'total' => (float) $list->sum('total'),
                    ];
                    $grand['qty'] += $totals['qty'];
                    $grand['total'] += $totals['total'];

                    return [
                        'supplier' => $supplier,
                        'rows' => $list,
                        'totals' => $totals,
                    ];
                })
                ->sortBy(fn ($g) => mb_strtoupper((string) ($g['supplier']?->name ?? '')))
                ->values();
        }

        return [
            'suppliers' => $suppliers,
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
        <x-action-bar title="Purchases Report by Item">
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
                    @forelse ($groups as $group)
                        @php $sup = $group['supplier']; $sname = $sup?->name ?: 'Unknown Supplier'; @endphp
                        <section class="sbr-customer">
                            <header class="sbr-customer-head">
                                <div class="sbr-customer-title">
                                    <span class="sbr-customer-name">{{ $sname }}</span>
                                    <span class="sbr-customer-id">{{ $sup?->supplier_id }}</span>
                                </div>
                            </header>
                            <table class="sbr-table">
                                <thead>
                                    <tr>
                                        <th>Date Received</th>
                                        <th>PO No.</th>
                                        <th>Item Code</th>
                                        <th>Item Description</th>
                                        <th class="col-num">Quantity</th>
                                        <th>U/M</th>
                                        <th class="col-num">Price</th>
                                        <th class="col-num">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['rows'] as $r)
                                        <tr>
                                            <td>{{ optional($r['date'])?->format('n/j/Y') }}</td>
                                            <td>{{ $r['po'] }}</td>
                                            <td>{{ $r['item_code'] }}</td>
                                            <td class="col-desc">{{ $r['description'] }}</td>
                                            <td class="col-num">{{ $qty($r['qty']) }}</td>
                                            <td>{{ $r['uom'] }}</td>
                                            <td class="col-num">{{ $money($r['price']) }}</td>
                                            <td class="col-num">{{ $money($r['total']) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="sbr-totals-row">
                                        <td colspan="4" class="sbr-totals-label">Totals for {{ $sname }}</td>
                                        <td class="col-num">{{ $qty($group['totals']['qty']) }}</td>
                                        <td></td>
                                        <td></td>
                                        <td class="col-num">{{ $money($group['totals']['total']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    @empty
                        <div class="sbr-empty">No purchase receives found.</div>
                    @endforelse
                    <table class="sbr-table">
                        <tr class="sbr-totals-row sbr-grand-row">
                            <td colspan="4" class="sbr-totals-label">Overall Totals</td>
                            <td class="col-num">{{ $qty($grand['qty']) }}</td>
                            <td></td>
                            <td></td>
                            <td class="col-num">{{ $money($grand['total']) }}</td>
                        </tr>
                    </table>
                </div>
            @else
                <div class="sbr-placeholder no-print">Choose <strong>Report Criteria…</strong></div>
            @endif
        </div>
    </div>
    @include('livewire.pages.reports.partials.report-criteria', [
        'rcTitle' => 'Report Criteria',
        'rcShowItem' => true,
        'rcShowSupplier' => true,
    ])
</div>
