@extends('sale.layout')
@section('title', 'Products')
@section('header', 'Products')
@section('content')
<input type="hidden" id="prodLoc" value="{{ $default_location }}">

<div class="sale-prod-app">
    <div class="sale-prod-app__toolbar">
        <div class="sale-prod-app__search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
            <input type="search" id="prodQ" placeholder="Search name / SKU" autocomplete="off">
            <button type="button" id="prodFilterBtn" class="sale-prod-app__filter-btn" aria-label="Filter">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                <span id="prodFilterDot" class="sale-prod-app__dot hidden"></span>
            </button>
        </div>

        <div class="sale-prod-app__chips" id="prodCatChips">
            <button type="button" class="sale-chip active" data-cat="">All</button>
            @foreach($categories as $cat)
                <button type="button" class="sale-chip" data-cat="{{ $cat['id'] }}">{{ $cat['name'] }}</button>
            @endforeach
        </div>

        <div class="sale-prod-app__chips sale-prod-app__chips--sub hidden" id="prodSubChips"></div>

        <div class="sale-prod-app__meta">
            <span id="prodCount">0 products</span>
            <button type="button" id="prodClearFilters" class="sale-prod-app__clear hidden">Clear filters</button>
        </div>
    </div>

    <div id="prodList" class="sale-prod-app__list"></div>
    <p id="prodEmpty" class="sale-prod-app__empty hidden">No products found</p>
</div>

{{-- Filter bottom sheet --}}
<div id="prodFilterSheet" class="sale-sheet" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__handle"></div>
        <div class="sale-sheet__head">
            <div class="font-extrabold text-base">Filter products</div>
            <button type="button" id="prodFilterClose" class="sale-sheet__close" aria-label="Close">×</button>
        </div>
        <div class="sale-sheet__body">
            <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Category</div>
                <select id="prodCat" class="sale-input">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Subcategory</div>
                <select id="prodSub" class="sale-input" disabled>
                    <option value="">All</option>
                </select>
            </div>
        </div>
        <div class="sale-sheet__foot">
            <button type="button" id="prodFilterReset" class="sale-btn-ghost !w-auto !px-4">Reset</button>
            <button type="button" id="prodFilterApply" class="sale-btn !w-auto !px-6">Apply</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const categories = @json($categoriesJson);
    const loc = document.getElementById('prodLoc');
    const q = document.getElementById('prodQ');
    const cat = document.getElementById('prodCat');
    const sub = document.getElementById('prodSub');
    const list = document.getElementById('prodList');
    const empty = document.getElementById('prodEmpty');
    const catChips = document.getElementById('prodCatChips');
    const subChips = document.getElementById('prodSubChips');
    const countEl = document.getElementById('prodCount');
    const clearBtn = document.getElementById('prodClearFilters');
    const filterDot = document.getElementById('prodFilterDot');
    const sheet = document.getElementById('prodFilterSheet');
    let timer = null;

    function money(n) { return '$' + (Number(n) || 0).toFixed(2); }

    function syncCatChips() {
        catChips.querySelectorAll('.sale-chip').forEach(chip => {
            chip.classList.toggle('active', String(chip.dataset.cat || '') === String(cat.value || ''));
        });
    }

    function fillSubs(keepValue) {
        const id = cat.value;
        const prev = keepValue ? sub.value : '';
        sub.innerHTML = '<option value="">All</option>';
        subChips.innerHTML = '';
        if (!id) {
            sub.disabled = true;
            subChips.classList.add('hidden');
            return;
        }
        const found = categories.find(c => String(c.id) === String(id));
        const subs = found ? found.sub_categories : [];
        if (!subs.length) {
            sub.disabled = true;
            subChips.classList.add('hidden');
            return;
        }
        sub.disabled = false;
        const allChip = document.createElement('button');
        allChip.type = 'button';
        allChip.className = 'sale-chip' + (!prev ? ' active' : '');
        allChip.dataset.sub = '';
        allChip.textContent = 'All';
        subChips.appendChild(allChip);
        subs.forEach(s => {
            const o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.name;
            sub.appendChild(o);
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'sale-chip' + (String(prev) === String(s.id) ? ' active' : '');
            chip.dataset.sub = s.id;
            chip.textContent = s.name;
            subChips.appendChild(chip);
        });
        if (prev && [...sub.options].some(o => String(o.value) === String(prev))) {
            sub.value = prev;
        } else {
            sub.value = '';
        }
        subChips.classList.remove('hidden');
        syncSubChips();
    }

    function syncSubChips() {
        subChips.querySelectorAll('.sale-chip').forEach(chip => {
            chip.classList.toggle('active', String(chip.dataset.sub || '') === String(sub.value || ''));
        });
    }

    function updateFilterUi() {
        const active = !!(cat.value || sub.value || q.value.trim());
        filterDot.classList.toggle('hidden', !(cat.value || sub.value));
        clearBtn.classList.toggle('hidden', !active);
        syncCatChips();
        syncSubChips();
    }

    async function load() {
        updateFilterUi();
        let url = @json(route('sale.api.products')) + '?limit=80';
        if (loc.value) url += '&location_id=' + encodeURIComponent(loc.value);
        const term = q.value.trim();
        if (term) url += '&q=' + encodeURIComponent(term);
        if (sub.value) url += '&sub_category_id=' + encodeURIComponent(sub.value);
        else if (cat.value) url += '&category_id=' + encodeURIComponent(cat.value);
        const selectedCat = categories.find(c => String(c.id) === String(cat.value));
        if (selectedCat && selectedCat.via_department) url += '&via_department=1';

        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        const rows = Array.isArray(data) ? data : (Array.isArray(data.data) ? data.data : []);
        list.innerHTML = '';
        countEl.textContent = rows.length + (rows.length === 1 ? ' product' : ' products');
        if (!rows.length) {
            empty.classList.remove('hidden');
            empty.textContent = 'No products found';
            return;
        }
        empty.classList.add('hidden');
        rows.forEach(r => {
            const el = document.createElement('div');
            el.className = 'sale-prod-card';
            const thumb = r.has_image
                ? `<img src="${r.image}" alt="" loading="lazy">`
                : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="m21 15-4.5-4.5L9 18"/></svg>`;
            el.innerHTML = `
                <div class="sale-prod-card__thumb ${r.has_image ? '' : 'is-placeholder'}">${thumb}</div>
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-sm leading-snug truncate">${r.name}</div>
                    <div class="text-xs text-slate-500 mt-1">${money(r.price)} · Stock ${r.stock}</div>
                </div>
                <a class="sale-prod-card__add" href="${@json(route('sale.orders.create'))}?add=${r.variation_id}">Add</a>`;
            list.appendChild(el);
        });
    }

    catChips.addEventListener('click', (e) => {
        const chip = e.target.closest('.sale-chip');
        if (!chip) return;
        cat.value = chip.dataset.cat || '';
        fillSubs(false);
        load();
    });

    subChips.addEventListener('click', (e) => {
        const chip = e.target.closest('.sale-chip');
        if (!chip) return;
        sub.value = chip.dataset.sub || '';
        load();
    });

    document.getElementById('prodFilterBtn').addEventListener('click', () => { sheet.hidden = false; });
    document.getElementById('prodFilterClose').addEventListener('click', () => { sheet.hidden = true; });
    sheet.addEventListener('click', (e) => { if (e.target === sheet) sheet.hidden = true; });
    document.getElementById('prodFilterApply').addEventListener('click', () => {
        fillSubs(true);
        sheet.hidden = true;
        load();
    });
    document.getElementById('prodFilterReset').addEventListener('click', () => {
        cat.value = '';
        fillSubs(false);
        q.value = '';
        sheet.hidden = true;
        load();
    });
    clearBtn.addEventListener('click', () => {
        cat.value = '';
        fillSubs(false);
        q.value = '';
        load();
    });

    cat.addEventListener('change', () => fillSubs(false));
    q.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(load, 250);
    });

    fillSubs(false);
    load();
})();
</script>
@endpush
