<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Services\DocumentPdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app'), Title('Sales Report')] class extends Component
{
    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public ?int $customerId = null;

    public string $viewMode = 'orders';

    public function mount(): void
    {
        if ($this->dateFrom === '') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
        }
        if ($this->dateTo === '') {
            $this->dateTo = now()->toDateString();
        }
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $orders = SalesOrder::query()
            ->with(['customer', 'invoice'])
            ->where('company_id', $companyId)
            ->when($this->customerId, fn ($q) => $q->where('customer_id', $this->customerId))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('order_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('order_date', '<=', $this->dateTo))
            ->orderByDesc('order_date')
            ->limit(500)
            ->get();

        $invoices = Invoice::query()
            ->with(['customer', 'salesOrder'])
            ->where('company_id', $companyId)
            ->when($this->customerId, fn ($q) => $q->where('customer_id', $this->customerId))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('invoice_date', '<=', $this->dateTo))
            ->orderByDesc('invoice_date')
            ->limit(500)
            ->get();

        return [
            'customers' => Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->orderBy('company_name')
                ->get(['id', 'customer_id', 'company_name']),
            'orders' => $orders,
            'invoices' => $invoices,
            'orderTotal' => $orders->sum(fn ($o) => (float) $o->total),
            'invoiceTotal' => $invoices->sum(fn ($i) => (float) $i->invoice_total),
        ];
    }

    public function downloadCsv(): StreamedResponse
    {
        $companyId = auth()->user()->company_id;
        $mode = $this->viewMode;
        $dateFrom = $this->dateFrom;
        $dateTo = $this->dateTo;
        $customerId = $this->customerId;

        $filename = 'sales-report-'.$mode.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($companyId, $mode, $dateFrom, $dateTo, $customerId) {
            $out = fopen('php://output', 'w');

            if ($mode === 'invoices') {
                fputcsv($out, ['Invoice No', 'Invoice Date', 'Order No', 'Customer', 'Status', 'Invoice Total', 'Balance']);
                Invoice::query()
                    ->with(['customer', 'salesOrder', 'payments', 'credits'])
                    ->where('company_id', $companyId)
                    ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                    ->when($dateFrom, fn ($q) => $q->whereDate('invoice_date', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('invoice_date', '<=', $dateTo))
                    ->orderBy('invoice_date')
                    ->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $inv) {
                            fputcsv($out, [
                                $inv->invoice_number,
                                optional($inv->invoice_date)?->format('Y-m-d'),
                                $inv->salesOrder?->order_number,
                                $inv->customer?->company_name,
                                $inv->status,
                                number_format((float) $inv->invoice_total, 2, '.', ''),
                                number_format($inv->invoice_balance, 2, '.', ''),
                            ]);
                        }
                    });
            } else {
                fputcsv($out, ['Order No', 'Order Date', 'Customer', 'Status', 'Total']);
                SalesOrder::query()
                    ->with('customer')
                    ->where('company_id', $companyId)
                    ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                    ->when($dateFrom, fn ($q) => $q->whereDate('order_date', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('order_date', '<=', $dateTo))
                    ->orderBy('order_date')
                    ->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $ord) {
                            fputcsv($out, [
                                $ord->order_number,
                                optional($ord->order_date)?->format('Y-m-d'),
                                $ord->customer?->company_name,
                                $ord->status,
                                number_format((float) $ord->total, 2, '.', ''),
                            ]);
                        }
                    });
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function downloadPdf(DocumentPdfService $pdfs): Response
    {
        $companyId = auth()->user()->company_id;

        if ($this->viewMode === 'invoices') {
            $rows = Invoice::query()
                ->with(['customer', 'salesOrder'])
                ->where('company_id', $companyId)
                ->when($this->customerId, fn ($q) => $q->where('customer_id', $this->customerId))
                ->when($this->dateFrom, fn ($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
                ->when($this->dateTo, fn ($q) => $q->whereDate('invoice_date', '<=', $this->dateTo))
                ->orderBy('invoice_date')
                ->limit(1000)
                ->get();
            $grandTotal = $rows->sum(fn ($i) => (float) $i->invoice_total);
        } else {
            $rows = SalesOrder::query()
                ->with('customer')
                ->where('company_id', $companyId)
                ->when($this->customerId, fn ($q) => $q->where('customer_id', $this->customerId))
                ->when($this->dateFrom, fn ($q) => $q->whereDate('order_date', '>=', $this->dateFrom))
                ->when($this->dateTo, fn ($q) => $q->whereDate('order_date', '<=', $this->dateTo))
                ->orderBy('order_date')
                ->limit(1000)
                ->get();
            $grandTotal = $rows->sum(fn ($o) => (float) $o->total);
        }

        return $pdfs->salesReportPdf([
            'mode' => $this->viewMode,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'rows' => $rows,
            'grandTotal' => $grandTotal,
        ], auth()->user())->download('sales-report-'.$this->viewMode.'-'.now()->format('Ymd-His').'.pdf');
    }
}; ?>

<div class="flex gap-2 h-full">
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Sales Report" />
        <div class="flex flex-wrap items-end gap-2 px-2 py-2 bg-slate-100 border-b border-slate-300">
            <div>
                <label class="block text-xs text-slate-600">From</label>
                <input type="date" wire:model.live="dateFrom" class="chief-input" />
            </div>
            <div>
                <label class="block text-xs text-slate-600">To</label>
                <input type="date" wire:model.live="dateTo" class="chief-input" />
            </div>
            <div>
                <label class="block text-xs text-slate-600">Customer</label>
                <select wire:model.live="customerId" class="chief-input w-56">
                    <option value="">All customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">View</label>
                <select wire:model.live="viewMode" class="chief-input w-36">
                    <option value="orders">Orders</option>
                    <option value="invoices">Invoices</option>
                </select>
            </div>
            <button type="button" wire:click="downloadCsv" class="chief-btn">Download CSV</button>
            <button type="button" wire:click="downloadPdf" class="chief-btn-primary">Download PDF</button>
        </div>

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white flex justify-between">
            <span>{{ $viewMode === 'invoices' ? 'Invoices' : 'Orders' }}</span>
            <span class="text-sm font-normal">
                @if ($viewMode === 'invoices')
                    Total: <strong>${{ number_format($invoiceTotal, 2) }}</strong>
                @else
                    Total: <strong>${{ number_format($orderTotal, 2) }}</strong>
                @endif
            </span>
        </div>

        <div class="chief-grid flex-1 overflow-auto">
            @if ($viewMode === 'invoices')
                <table>
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Invoice Date</th>
                            <th>Order No</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="text-right">Invoice Total</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $inv)
                            <tr>
                                <td class="font-mono">{{ $inv->invoice_number }}</td>
                                <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
                                <td class="font-mono">{{ $inv->salesOrder?->order_number }}</td>
                                <td>{{ $inv->customer?->company_name }}</td>
                                <td>{{ $inv->status }}</td>
                                <td class="text-right">${{ number_format($inv->invoice_total, 2) }}</td>
                                <td class="text-right">${{ number_format($inv->invoice_balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-2 py-6 text-slate-500">No invoices in range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Order Date</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $ord)
                            <tr>
                                <td class="font-mono">{{ $ord->order_number }}</td>
                                <td>{{ optional($ord->order_date)?->format('n/j/Y') }}</td>
                                <td>{{ $ord->customer?->company_name }}</td>
                                <td>{{ $ord->status }}</td>
                                <td class="text-right">${{ number_format($ord->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-2 py-6 text-slate-500">No orders in range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
        <x-record-count :count="$viewMode === 'invoices' ? $invoices->count() : $orders->count()" />
    </div>
</div>
