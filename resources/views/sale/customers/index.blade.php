@extends('sale.layout')
@section('title', 'Customers')
@section('header', 'Customers')
@section('content')
<div class="sale-page-tool">
    <form method="GET" action="{{ route('sale.customers') }}" id="saleCustomerSearchForm" class="flex-1 flex gap-2 min-w-0">
        <input type="search" name="q" id="saleCustomerSearch" value="{{ $term }}" class="sale-input !py-2.5" placeholder="Search" autocomplete="off">
    </form>
    @if($canCreate)
        <a href="{{ route('sale.customers.create') }}" class="sale-btn-sm shrink-0 !bg-rose-600">+</a>
    @endif
</div>
<div class="text-xs text-slate-400 mb-2">Results: {{ $customers->total() }}</div>

<div id="saleCustomerResults">
<div class="space-y-2">
    @forelse($customers as $customer)
        @php
            $addr = trim(implode(', ', array_filter([
                $customer->shipping_address ?: $customer->address_line_1,
                $customer->city,
            ])));
            $limit = $customer->credit_limit;
        @endphp
        <button type="button" class="sale-cust-card w-full text-left" data-cust="{{ $customer->id }}" data-name="{{ e($customer->supplier_business_name ?: $customer->name) }}" data-addr="{{ e($addr) }}" data-acct="{{ e($customer->contact_id) }}" data-mobile="{{ e($customer->mobile) }}" data-limit="{{ $limit === null ? '0.00' : number_format((float) $limit, 2, '.', '') }}">
            <div class="sale-cust-card__top">
                <div class="font-extrabold text-[15px]">{{ $customer->supplier_business_name ?: $customer->name }}</div>
                <span class="sale-cust-card__chev">›</span>
            </div>
            @if($addr !== '')
                <div class="sale-cust-card__addr">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    {{ $addr }}
                </div>
            @endif
            <div class="flex justify-between text-xs text-slate-500 mt-2 gap-2">
                <span>Limit: ${{ $limit === null ? '0.00' : number_format((float) $limit, 2) }}</span>
                <span>Last order: ${{ number_format((float) ($customer->last_order_total ?? 0), 2) }}</span>
            </div>
            @if($customer->contact_id)
                <div class="mt-2"><span class="sale-badge sale-badge--draft">Acct #: {{ $customer->contact_id }}</span></div>
            @endif
        </button>
    @empty
        <div class="sale-card sale-empty">
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

<div id="saleCustSheet" class="sale-sheet" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__head is-center">
            <div class="sale-sheet__title">Options</div>
            <button type="button" class="sale-sheet__close" id="saleCustClose" aria-label="Close">×</button>
        </div>
        <p id="saleCustWho" class="text-center text-sm text-slate-500 px-4 -mt-1 mb-1"></p>
        <div class="sale-sheet__body !pt-1" id="saleCustMenu">
            <a id="saleCustOrder" class="sale-act-row" href="#">
                <svg viewBox="0 0 24 24"><path d="M8 4h8v3H8z"/><path d="M6 7h12v13H6z"/><path d="M9 11h6M9 15h4"/></svg>
                Create Order
            </a>
            <a id="saleCustHistory" class="sale-act-row" href="#">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></svg>
                History
            </a>
            <button type="button" class="sale-act-row" id="saleCustDetails">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M5 19c1.5-3.5 4-5 7-5s5.5 1.5 7 5"/></svg>
                Customer Details
            </button>
            <a id="saleCustDirections" class="sale-act-row" href="#" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                Directions
            </a>
        </div>
        <div class="sale-sheet__body !pt-1" id="saleCustDetailsPane" hidden>
            <div class="px-4 pb-3 text-sm text-slate-600 space-y-2">
                <div><b>Account</b><div id="saleCustDAcct">—</div></div>
                <div><b>Mobile</b><div id="saleCustDMobile">—</div></div>
                <div><b>Address</b><div id="saleCustDAddr">—</div></div>
                <div><b>Credit limit</b><div id="saleCustDLimit">—</div></div>
            </div>
            <button type="button" class="sale-act-row" id="saleCustDetailsBack">Back to Options</button>
        </div>
        <div class="sale-sheet__foot">
            <button type="button" class="sale-more-cancel" id="saleCustCancel">CANCEL</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('saleCustomerSearchForm');
    const input = document.getElementById('saleCustomerSearch');
    const sheet = document.getElementById('saleCustSheet');
    const orderLink = document.getElementById('saleCustOrder');
    const histLink = document.getElementById('saleCustHistory');
    const dirLink = document.getElementById('saleCustDirections');
    const who = document.getElementById('saleCustWho');
    const menu = document.getElementById('saleCustMenu');
    const detailsPane = document.getElementById('saleCustDetailsPane');
    const createBase = @json(route('sale.orders.create'));
    const histBase = @json(route('sale.orders'));
    let lastAddr = '';

    function hideSheet() {
        if (typeof saleCloseSheet === 'function') saleCloseSheet('saleCustSheet');
        else if (sheet) { sheet.hidden = true; sheet.classList.remove('is-open'); }
    }
    function showMenu() {
        if (menu) menu.hidden = false;
        if (detailsPane) detailsPane.hidden = true;
    }
    function showSheet(card) {
        if (!sheet) return;
        const id = card.getAttribute('data-cust');
        const addr = card.getAttribute('data-addr') || '';
        lastAddr = addr;
        if (sheet) sheet.setAttribute('data-open-id', id);
        const addVid = new URLSearchParams(window.location.search).get('add');
        const q = '?contact_id=' + encodeURIComponent(id);
        if (who) who.textContent = card.getAttribute('data-name') || '';
        orderLink.href = createBase + q + (addVid ? '&add=' + encodeURIComponent(addVid) : '');
        histLink.href = histBase + '?q=' + encodeURIComponent(card.getAttribute('data-name') || '');
        dirLink.href = addr
            ? 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(addr)
            : '#';
        document.getElementById('saleCustDAcct').textContent = card.getAttribute('data-acct') || '—';
        document.getElementById('saleCustDMobile').textContent = card.getAttribute('data-mobile') || '—';
        document.getElementById('saleCustDAddr').textContent = addr || '—';
        document.getElementById('saleCustDLimit').textContent = '$' + (card.getAttribute('data-limit') || '0.00');
        showMenu();
        if (typeof saleOpenSheet === 'function') saleOpenSheet('saleCustSheet');
        else { sheet.hidden = false; sheet.classList.add('is-open'); document.body.appendChild(sheet); }
    }
    document.addEventListener('click', function (e) {
        const card = e.target.closest('[data-cust]');
        if (card) {
            e.preventDefault();
            showSheet(card);
        }
    });
    const close = document.getElementById('saleCustClose');
    const cancel = document.getElementById('saleCustCancel');
    if (close) close.addEventListener('click', hideSheet);
    if (cancel) cancel.addEventListener('click', hideSheet);
    if (sheet) sheet.addEventListener('click', (e) => { if (e.target === sheet) hideSheet(); });
    const detailsBtn = document.getElementById('saleCustDetails');
    const detailsBack = document.getElementById('saleCustDetailsBack');
    if (detailsBtn) detailsBtn.addEventListener('click', function () {
        if (menu) menu.hidden = true;
        if (detailsPane) detailsPane.hidden = false;
    });
    if (detailsBack) detailsBack.addEventListener('click', showMenu);
    if (dirLink) dirLink.addEventListener('click', function (e) {
        if (!lastAddr) {
            e.preventDefault();
            alert('No address on this customer');
        }
    });

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
            if (err.name !== 'AbortError') window.location.href = url;
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
