@extends('sale.layout')
@section('title', 'Orders')
@section('header', 'Order list')
@section('content')
<div class="sale-page-tool">
    <span class="sale-chip" id="saleOrderCount">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
        {{ $orders->total() }} order(s)
    </span>
    <a href="{{ route('sale.orders.create') }}" class="sale-btn-sm lg:hidden">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
        Create
    </a>
</div>

<form method="GET" action="{{ route('sale.orders') }}" id="saleOrderSearchForm" class="sale-card !p-3 mb-3 space-y-2">
    <input type="hidden" name="status" value="{{ $status ?? '' }}">
    <input type="search" name="q" id="saleOrderSearch" value="{{ $q ?? '' }}" class="sale-input" placeholder="Search customer / order / invoice no" autocomplete="off">
    <div class="sale-prod-app__chips" id="saleOrderChips">
        @php $curStatus = $status ?? ''; @endphp
        <a href="{{ route('sale.orders', array_filter(['q' => $q ?? ''])) }}" class="sale-chip {{ $curStatus === '' ? 'active' : '' }}">All</a>
        <a href="{{ route('sale.orders', array_filter(['q' => $q ?? '', 'status' => 'sale'])) }}" class="sale-chip {{ $curStatus === 'sale' ? 'active' : '' }}">Sale</a>
        <a href="{{ route('sale.orders', array_filter(['q' => $q ?? '', 'status' => 'return'])) }}" class="sale-chip {{ $curStatus === 'return' ? 'active' : '' }}">Return</a>
        <a href="{{ route('sale.orders', array_filter(['q' => $q ?? '', 'status' => 'invoiced'])) }}" class="sale-chip {{ $curStatus === 'invoiced' ? 'active' : '' }}">Invoiced</a>
    </div>
</form>

<div id="saleOrderResults">
<div class="space-y-2">
    @forelse($orders as $order)
        @php
            $displayStatus = $order->sale_status ?? 'sale';
            if ($displayStatus === 'invoiced') {
                $badge = 'sale-badge--completed';
                $badgeLabel = 'Invoiced';
            } elseif ($displayStatus === 'return') {
                $badge = 'sale-badge--draft';
                $badgeLabel = 'Return';
            } else {
                $badge = 'sale-badge--ordered';
                $badgeLabel = 'Sale';
            }
        @endphp
        <div class="sale-order-row !items-stretch">
            <a href="{{ route('sale.orders.show', $order->id) }}" class="sale-order-row__ico shrink-0 self-center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
            </a>
            <a href="{{ route('sale.orders.show', $order->id) }}" class="sale-order-row__body min-w-0 flex-1 no-underline text-inherit">
                <div class="font-extrabold text-[15px] truncate">{{ $order->invoice_no }}</div>
                <div class="mt-0.5"><span class="sale-badge sale-badge--ordered">{{ $order->sourceLabel() }}</span></div>
                @if(!empty($order->converted_invoice_no))
                    <div class="text-xs font-bold text-sale mt-0.5">Invoice {{ $order->converted_invoice_no }}</div>
                @endif
                <div class="text-sm text-slate-500 truncate mt-0.5">
                    {{ optional($order->contact)->supplier_business_name ?: optional($order->contact)->name ?: 'Customer' }}
                </div>
                @if(optional($order->contact)->mobile)
                    <div class="text-xs text-slate-400 mt-0.5">{{ $order->contact->mobile }}</div>
                @endif
                <div class="text-xs text-slate-400 mt-1">
                    {{ \Carbon\Carbon::parse($order->transaction_date)->format('M j, Y g:i A') }}
                </div>
            </a>
            <div class="sale-order-row__meta shrink-0">
                <div class="font-extrabold tabular-nums">${{ number_format($order->sale_display_total ?? $order->final_total, 2) }}</div>
                <span class="sale-badge {{ $badge }} mt-1">{{ $badgeLabel }}</span>
            </div>
            <div class="sale-order-actions">
                <a href="{{ route('sale.orders.show', $order->id) }}" class="sale-act sale-act--view" title="View">View</a>
                @if(!empty($order->can_show_edit) || !empty($order->can_edit))
                    @if(!empty($order->can_edit))
                        <a href="{{ route('sale.orders.edit', $order->id) }}" class="sale-act sale-act--edit" title="Edit">Edit</a>
                    @else
                        <span class="sale-act sale-act--edit is-disabled" title="Invoiced — cannot edit" aria-disabled="true">Edit</span>
                    @endif
                @endif
                <a href="{{ route('sale.orders.invoice', $order->id) }}" target="_blank" class="sale-act sale-act--dl" title="Invoice">Invoice</a>
                <form method="POST" action="{{ route('sale.orders.destroy', $order->id) }}" onsubmit="return confirm('Delete order {{ $order->invoice_no }}?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sale-act sale-act--del" title="Delete">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="sale-card sale-empty">
            <div class="sale-empty__ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            </div>
            <p class="text-slate-500 text-sm mb-4">No orders yet.</p>
            <a href="{{ route('sale.orders.create') }}" class="sale-btn inline-block w-auto px-6">Create first order</a>
        </div>
    @endforelse
</div>

@if($orders->hasPages())
    <div class="mt-4">
        {{ $orders->onEachSide(1)->links('sale.partials.pagination') }}
    </div>
@endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('saleOrderSearchForm');
    const input = document.getElementById('saleOrderSearch');
    if (!form || !input) return;

    let timer = null;
    let abort = null;

    function urlFromForm() {
        const data = new FormData(form);
        const params = new URLSearchParams();
        data.forEach(function (value, key) {
            if (String(value).trim() !== '') {
                params.set(key, String(value).trim());
            }
        });
        const qs = params.toString();
        return form.action + (qs ? ('?' + qs) : '');
    }

    function liveSearch() {
        const url = urlFromForm();
        if (abort) abort.abort();
        abort = new AbortController();
        history.replaceState({}, '', url);
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            signal: abort.signal
        }).then(function (res) { return res.text(); }).then(function (html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextResults = doc.getElementById('saleOrderResults');
            const nextCount = doc.getElementById('saleOrderCount');
            const nextChips = doc.getElementById('saleOrderChips');
            const results = document.getElementById('saleOrderResults');
            const count = document.getElementById('saleOrderCount');
            const chips = document.getElementById('saleOrderChips');
            if (results && nextResults) results.innerHTML = nextResults.innerHTML;
            if (count && nextCount) count.innerHTML = nextCount.innerHTML;
            if (chips && nextChips) chips.innerHTML = nextChips.innerHTML;
        }).catch(function (err) {
            if (err.name !== 'AbortError') {
                window.location.href = url;
            }
        });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(liveSearch, 250);
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(timer);
        liveSearch();
    });
})();
</script>
@endpush
