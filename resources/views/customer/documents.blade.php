@extends('customer.layout')
@section('title', 'Documents')
@section('content')
<div class="mb-5">
    <h1 class="ca-page-title">Documents</h1>
    <div class="ca-page-sub">{{ $initials }}</div>
</div>

<div class="flex gap-2 mb-4 p-1 rounded-2xl bg-white/70 border border-slate-200/80" id="docsTabs">
    <button type="button" class="docs-tab flex-1 px-4 py-2.5 rounded-xl bg-gradient-to-br from-rose-500 to-rose-700 text-white text-sm font-extrabold shadow-md shadow-rose-500/20" data-tab="invoices">Invoices</button>
    <button type="button" class="docs-tab flex-1 px-4 py-2.5 rounded-xl bg-transparent text-slate-600 text-sm font-bold" data-tab="tax">Tax Reports</button>
</div>

{{-- ========== INVOICES TAB ========== --}}
<div id="tabInvoices">
<form method="GET" action="{{ route('customer.documents') }}" id="docsFilterForm" class="mb-4">
    <input type="hidden" name="tab" value="invoices">
    <div class="text-xs font-bold text-slate-500 mb-2">Filter By Date:</div>
    <div class="grid grid-cols-2 gap-2 mb-3">
        <div>
            <label class="block text-[11px] font-bold text-slate-400 mb-1">From</label>
            <input type="date" name="start_date" value="{{ $start ?? '' }}"
                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold bg-white">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-400 mb-1">To</label>
            <input type="date" name="end_date" value="{{ $end ?? '' }}"
                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold bg-white">
        </div>
    </div>
    <div class="flex gap-2 mb-3">
        <button type="submit" class="flex-1 ca-btn !py-2.5 text-sm">Apply</button>
        <a href="{{ route('customer.documents') }}" class="flex-1 text-center border border-slate-200 rounded-xl py-2.5 text-sm font-bold text-slate-600 bg-white">Show All</a>
    </div>
    <div class="text-xs font-bold text-slate-500 mb-2">Invoice No</div>
    <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔍</span>
        <input type="search" name="q" id="invoiceSearch" value="{{ $q ?? '' }}" class="ca-input" placeholder="Search invoice" autocomplete="off">
    </div>
</form>

@if(!empty($hasDateFilter))
    <div class="mb-3 text-xs font-bold text-red-600">
        Showing {{ $invoices->count() }} invoice(s)
        @if(!empty($start)) from {{ \Carbon\Carbon::parse($start)->format('M d, Y') }} @endif
        @if(!empty($end)) to {{ \Carbon\Carbon::parse($end)->format('M d, Y') }} @endif
    </div>
@else
    <div class="mb-3 text-xs font-bold text-slate-500">All invoices ({{ $invoices->count() }})</div>
@endif

<div class="space-y-4" id="invoiceList">
@forelse($grouped as $dateKey => $dayInvoices)
    <div class="date-group" data-date="{{ $dateKey }}">
        <div class="text-xs font-extrabold text-slate-500 uppercase tracking-wide mb-2 px-1">
            📅 {{ $dateKey === 'undated' ? 'No date' : \Carbon\Carbon::parse($dateKey)->format('D, M d Y') }}
            <span class="font-semibold normal-case text-slate-400">({{ $dayInvoices->count() }})</span>
        </div>
        <div class="space-y-3">
        @foreach($dayInvoices as $inv)
            @php
                $paid = (float) ($inv->total_paid ?? 0);
                $total = (float) $inv->final_total;
                $status = $paid <= 0 ? 'UNPAID' : ($paid + 0.0001 >= $total ? 'PAID' : 'PARTIAL');
                $badge = $status === 'PAID' ? 'ca-badge--paid' : ($status === 'UNPAID' ? 'ca-badge--due' : 'ca-badge--partial');
            @endphp
            <div class="ca-card flex items-center gap-3 invoice-row" data-no="{{ strtolower($inv->invoice_no) }}">
                <div class="w-11 h-11 rounded-full bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">📄</div>
                <div class="min-w-0 flex-1">
                    <div class="font-extrabold">{{ $inv->invoice_no }}</div>
                    <div class="text-xs text-red-600 font-semibold mt-0.5">📅 {{ \Carbon\Carbon::parse($inv->transaction_date)->format('M d Y') }}</div>
                    <div class="mt-1 flex items-center gap-2 flex-wrap">
                        <span class="ca-badge {{ $badge }}">{{ $status }}</span>
                        <span class="text-sm font-bold tabular-nums">${{ number_format($total, 2) }}</span>
                    </div>
                </div>
                <a href="{{ route('customer.documents.invoice', $inv->id) }}"
                   class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0"
                   title="View Invoice">👁</a>
            </div>
        @endforeach
        </div>
    </div>
@empty
    <div class="ca-card text-center text-slate-400 py-10 text-sm">No invoices found</div>
@endforelse
</div>
</div>

{{-- ========== TAX REPORTS TAB ========== --}}
<div id="tabTax" class="hidden">
    <form method="GET" action="{{ route('customer.documents') }}" class="mb-4">
        <input type="hidden" name="tab" value="tax">
        <div class="text-xs font-bold text-slate-500 mb-2">Tax Report Date Range:</div>
        <div class="grid grid-cols-2 gap-2 mb-3">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">From</label>
                <input type="date" name="start_date" value="{{ $start ?? '' }}"
                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold bg-white">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">To</label>
                <input type="date" name="end_date" value="{{ $end ?? '' }}"
                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold bg-white">
            </div>
        </div>
        <button type="submit" class="ca-btn !py-2.5 text-sm">Apply</button>
    </form>

    @php
        $taxTotal = (float) ($taxSummary['tax_total'] ?? 0);
        $salesTotal = (float) ($taxSummary['sales_total'] ?? 0);
        $invoiceCount = (int) ($taxSummary['invoice_count'] ?? 0);
        $paidTotal = (float) ($taxSummary['paid_total'] ?? 0);
    @endphp

    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="ca-card">
            <div class="text-xs font-semibold text-slate-500">Invoices</div>
            <div class="text-2xl font-extrabold mt-1">{{ $invoiceCount }}</div>
        </div>
        <div class="ca-card">
            <div class="text-xs font-semibold text-slate-500">Sales Total</div>
            <div class="text-2xl font-extrabold mt-1 tabular-nums">${{ number_format($salesTotal, 2) }}</div>
        </div>
        <div class="ca-card">
            <div class="text-xs font-semibold text-slate-500">Tax Total</div>
            <div class="text-2xl font-extrabold mt-1 tabular-nums text-red-600">${{ number_format($taxTotal, 2) }}</div>
        </div>
        <div class="ca-card">
            <div class="text-xs font-semibold text-slate-500">Paid Total</div>
            <div class="text-2xl font-extrabold mt-1 tabular-nums">${{ number_format($paidTotal, 2) }}</div>
        </div>
    </div>

    <div class="ca-card">
        <div class="font-extrabold mb-3">Tax by Invoice</div>
        <div class="space-y-2">
        @forelse($invoices as $inv)
            <div class="flex justify-between gap-3 text-sm border-b border-slate-50 pb-2">
                <div class="min-w-0">
                    <div class="font-bold">{{ $inv->invoice_no }}</div>
                    <div class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($inv->transaction_date)->format('M d, Y') }}</div>
                </div>
                <div class="text-right">
                    <div class="font-extrabold text-red-600 tabular-nums">${{ number_format((float) $inv->tax_amount, 2) }}</div>
                    <div class="text-xs text-slate-500 tabular-nums">Sale ${{ number_format((float) $inv->final_total, 2) }}</div>
                </div>
            </div>
        @empty
            <div class="text-sm text-slate-400 text-center py-6">No tax data in this range</div>
        @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const tabs = document.querySelectorAll('.docs-tab');
    const inv = document.getElementById('tabInvoices');
    const tax = document.getElementById('tabTax');
    const initial = @json(request('tab', 'invoices'));

    function setTab(name) {
        tabs.forEach((btn) => {
            const on = btn.dataset.tab === name;
            if (on) {
                btn.className = 'docs-tab flex-1 px-4 py-2.5 rounded-xl text-white text-sm font-extrabold shadow-md';
                btn.style.background = 'linear-gradient(135deg,#e11d48,#be123c)';
                btn.style.boxShadow = '0 8px 18px rgba(225,29,72,.25)';
            } else {
                btn.className = 'docs-tab flex-1 px-4 py-2.5 rounded-xl bg-transparent text-slate-600 text-sm font-bold';
                btn.style.background = 'transparent';
                btn.style.boxShadow = 'none';
            }
        });
        inv?.classList.toggle('hidden', name !== 'invoices');
        tax?.classList.toggle('hidden', name !== 'tax');
    }

    tabs.forEach((btn) => btn.addEventListener('click', () => setTab(btn.dataset.tab)));
    setTab(initial === 'tax' ? 'tax' : 'invoices');

    document.getElementById('invoiceSearch')?.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.date-group').forEach((group) => {
            let visible = 0;
            group.querySelectorAll('.invoice-row').forEach((row) => {
                const show = !q || (row.dataset.no || '').includes(q);
                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });
            group.classList.toggle('hidden', visible === 0);
        });
    });
})();
</script>
@endpush
