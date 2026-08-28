@extends('customer.layout')
@section('title', 'Price Check')
@section('content')
<div class="flex items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="ca-page-title">Price Check</h1>
        <div class="ca-page-sub">{{ $initials }}</div>
    </div>
    <button type="button" id="priceScanBtn" class="inline-flex items-center gap-2 text-white font-extrabold rounded-2xl px-4 py-2.5 text-sm shadow-lg" style="background:linear-gradient(135deg,#e11d48,#be123c);box-shadow:0 10px 24px rgba(225,29,72,.3)" title="Scan with camera">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M7 4H5a1 1 0 0 0-1 1v2"/><path d="M17 4h2a1 1 0 0 1 1 1v2"/><path d="M7 20H5a1 1 0 0 1-1-1v-2"/><path d="M17 20h2a1 1 0 0 0 1-1v-2"/></svg>
        SCAN
    </button>
</div>

@if($locations->count())
<div class="mb-3">
    <select id="priceLocation" class="w-full border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold bg-white">
        @foreach($locations as $id => $name)
            <option value="{{ $id }}" @selected($id == $default_location)>{{ $name }}</option>
        @endforeach
    </select>
</div>
@endif

<div class="relative mb-4">
    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔍</span>
    <input type="search" id="priceSearch" class="ca-input" placeholder="Enter Product Name / SKU" autocomplete="off">
</div>

<div id="priceResults" class="space-y-3"></div>
<div id="priceEmpty" class="ca-card text-center text-slate-400 py-16 text-sm">Search or scan a product to see price</div>
@endsection

@push('scripts')
<script>
(function () {
    const search = document.getElementById('priceSearch');
    const results = document.getElementById('priceResults');
    const empty = document.getElementById('priceEmpty');
    const loc = document.getElementById('priceLocation');
    const api = @json(route('customer.api.products'));
    let timer = null;

    function money(n) { return '$' + (Number(n) || 0).toFixed(2); }

    async function run(q) {
        q = (q || '').trim();
        if (!q) {
            results.innerHTML = '';
            empty.style.display = '';
            return;
        }
        empty.style.display = 'none';
        const url = api + '?q=' + encodeURIComponent(q) + '&location_id=' + encodeURIComponent(loc ? loc.value : '');
        const rows = await fetch(url, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => []);
        results.innerHTML = '';
        if (!rows.length) {
            results.innerHTML = '<div class="ca-card text-sm text-slate-400 text-center py-6">No products found</div>';
            return;
        }
        rows.forEach(r => {
            const el = document.createElement('div');
            el.className = 'ca-card flex gap-3 items-center';
            el.innerHTML = '<img src="" class="w-14 h-14 rounded-xl object-cover bg-slate-100"/><div class="min-w-0 flex-1"><div class="font-bold text-sm"></div><div class="text-xs text-slate-500"></div><div class="text-red-600 font-extrabold mt-1"></div></div>';
            el.querySelector('img').src = r.image || '';
            el.querySelector('.font-bold').textContent = r.name;
            el.querySelector('.text-xs').textContent = 'SKU: ' + (r.sku || '-');
            el.querySelector('.text-red-600').textContent = money(r.price);
            results.appendChild(el);
        });
    }

    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => run(search.value), 250);
    });
    search.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); run(search.value); }
    });

    document.getElementById('priceScanBtn')?.addEventListener('click', () => {
        if (!window.CustomerCameraScan) {
            alert('Camera scanner is not available.');
            return;
        }
        window.CustomerCameraScan.start((code) => {
            search.value = code;
            run(code);
        });
    });
})();
</script>
@endpush
