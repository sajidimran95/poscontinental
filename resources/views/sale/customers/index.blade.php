@extends('sale.layout')
@section('title', 'Customers')
@section('header', 'Customers')
@section('content')
<div class="sale-page-tool">
    <form method="GET" action="{{ route('sale.customers') }}" id="saleCustomerSearchForm" class="flex-1 flex gap-2 min-w-0">
        <input type="search" name="q" id="saleCustomerSearch" value="{{ $term }}" class="sale-input !py-2.5" placeholder="Search name / mobile" autocomplete="off">
    </form>
    @if($canCreate)
        <a href="{{ route('sale.customers.create') }}" class="sale-btn-sm shrink-0">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
            Add
        </a>
    @endif
</div>

<div id="saleCustomerResults">
<div class="space-y-2">
    @forelse($customers as $customer)
        <div class="sale-order-row">
            <span class="sale-order-row__ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg>
            </span>
            <div class="sale-order-row__body">
                <div class="font-extrabold text-[15px] truncate">
                    {{ $customer->supplier_business_name ?: $customer->name }}
                </div>
                @if($customer->supplier_business_name && $customer->name)
                    <div class="text-sm text-slate-500 truncate">{{ $customer->name }}</div>
                @endif
                @if($customer->mobile)
                    <div class="text-xs text-slate-400 mt-1">{{ $customer->mobile }}</div>
                @endif
                @if($customer->email)
                    <div class="text-xs text-slate-400">{{ $customer->email }}</div>
                @endif
            </div>
            <div class="sale-order-row__meta text-xs text-slate-400">
                @if($customer->contact_id)
                    #{{ $customer->contact_id }}
                @endif
            </div>
        </div>
    @empty
        <div class="sale-card sale-empty">
            <div class="sale-empty__ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg>
            </div>
            <p class="text-slate-500 text-sm mb-4">No customers found.</p>
            @if($canCreate)
                <a href="{{ route('sale.customers.create') }}" class="sale-btn inline-block w-auto px-6">Add customer</a>
            @endif
        </div>
    @endforelse
</div>

@if($canList && $customers->hasPages())
    <div class="mt-4">
        {{ $customers->onEachSide(1)->links('sale.partials.pagination') }}
    </div>
@endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('saleCustomerSearchForm');
    const input = document.getElementById('saleCustomerSearch');
    if (!form || !input) return;

    let timer = null;
    let abort = null;

    function urlFromForm() {
        const q = (input.value || '').trim();
        const url = new URL(form.action, window.location.origin);
        if (q) url.searchParams.set('q', q);
        else url.searchParams.delete('q');
        return url.toString();
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
            const next = doc.getElementById('saleCustomerResults');
            const results = document.getElementById('saleCustomerResults');
            if (results && next) results.innerHTML = next.innerHTML;
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
