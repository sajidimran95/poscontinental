@extends('sale.layout')
@section('title', 'Order Products')
@section('header', 'Order Products')
@section('content')
<input type="hidden" id="prodLoc" value="{{ $default_location }}">

<style>
.pp-catalog {
    --ink: #17221E;
    --ink-soft: #56645F;
    --paper: #F6F5F1;
    --card: #FFFFFF;
    --line: #E3E1DA;
    --teal: #175C4C;
    --teal-dark: #0E3F34;
    --amber-soft: #FBEBD6;
    --red: #B4432C;
    font-family: Manrope, Inter, Arial, sans-serif;
    color: var(--ink);
    background: var(--paper);
    margin: -12px -12px 0;
    min-height: 60vh;
    min-width: 0;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    width: 100%;
    overflow: hidden;
}
body.sale-page-products .sale-desk-shell,
body.sale-page-products .sale-desk-main,
body.sale-page-products .sale-main-app,
body.sale-page-products .sale-page {
    min-width: 0 !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
}
.pp-toolbar {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 20px; background: var(--paper);
    border-bottom: 1px solid var(--line);
}
.pp-search {
    flex: 1; display: flex; align-items: center; gap: 8px;
    background: var(--card); border: 1px solid var(--line);
    border-radius: 10px; padding: 9px 13px; min-width: 0;
}
.pp-search input {
    border: none; outline: none; font-family: inherit;
    font-size: 13.5px; flex: 1; background: transparent; color: var(--ink); min-width: 0;
}
.pp-search input::placeholder { color: #9AA39F; }
.pp-icon {
    width: 38px; height: 38px; border-radius: 10px;
    border: 1px solid var(--line); background: var(--card);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0; color: var(--ink);
}
.pp-icon.on { background: var(--teal); border-color: var(--teal); color: #fff; }
.pp-sort {
    display: flex; align-items: center; gap: 6px;
    border-radius: 10px; border: 1px solid var(--line); background: var(--card);
    padding: 0 12px; height: 38px; font-size: 12.5px; font-weight: 600;
    color: var(--ink); flex-shrink: 0; white-space: nowrap; cursor: pointer; font-family: inherit;
}
.pp-chips {
    display: flex;
    gap: 8px;
    padding: 10px 20px;
    overflow-x: auto;
    overflow-y: hidden;
    flex-shrink: 0;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    background: var(--paper);
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #175C4C #E3E1DA;
    padding-bottom: 8px;
}
.pp-chips::-webkit-scrollbar { height: 6px; display: block; }
.pp-chips::-webkit-scrollbar-thumb { background: #175C4C; border-radius: 999px; }
.pp-chips::-webkit-scrollbar-track { background: #E3E1DA; border-radius: 999px; }
.pp-chip {
    padding: 6px 13px; border-radius: 999px; font-size: 12px; font-weight: 600;
    white-space: nowrap; background: var(--card); border: 1px solid var(--line);
    color: var(--ink-soft); flex-shrink: 0; cursor: pointer; font-family: inherit;
}
.pp-chip.on { background: var(--teal-dark); color: #fff; border-color: var(--teal-dark); }
.pp-scroll { flex: 1; padding: 4px 20px 16px; }
.pp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.pp-catalog.is-list .pp-grid { grid-template-columns: 1fr; }
.pp-card {
    background: var(--card); border: 1px solid var(--line); border-radius: 14px;
    padding: 13px; display: flex; flex-direction: row-reverse; gap: 12px;
}
.pp-card.is-oos { opacity: .55; }
.pp-img {
    width: 118px; height: 118px; border-radius: 10px; background: var(--amber-soft);
    flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.pp-img img { width: 100%; height: 100%; object-fit: cover; }
.pp-img svg { width: 42px; height: 42px; color: var(--teal-dark); opacity: .7; }
.pp-body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pp-name { font-size: 13.5px; font-weight: 700; color: var(--ink); line-height: 1.25; }
.pp-pack { font-size: 11px; color: var(--ink-soft); margin-top: 2px; }
.pp-code { font-size: 10px; color: var(--ink-soft); font-family: 'IBM Plex Mono', ui-monospace, monospace; letter-spacing: .3px; margin-top: 5px; }
.pp-price-row { margin-top: 7px; display: flex; align-items: baseline; gap: 7px; }
.pp-price { font-size: 15px; font-weight: 800; color: var(--teal-dark); white-space: nowrap; }
.pp-unit-price { font-size: 10.5px; color: var(--ink-soft); font-family: 'IBM Plex Mono', ui-monospace, monospace; }
.pp-last { font-size: 10.5px; color: #8C9690; margin-top: 4px; background: none; border: 0; padding: 0; text-align: left; font-family: inherit; }
.pp-last b { color: var(--ink-soft); font-weight: 700; }
.pp-last.clickable { cursor: pointer; text-decoration: underline; text-decoration-color: #C7CFC9; text-underline-offset: 2px; }
.pp-units { display: flex; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; margin-top: 8px; align-self: flex-start; }
.pp-units button { border: none; background: var(--paper); font-family: inherit; font-size: 10.5px; font-weight: 700; color: var(--ink-soft); padding: 5px 10px; cursor: pointer; }
.pp-units button.sel { background: var(--teal); color: #fff; }
.pp-bottom { margin-top: auto; display: flex; align-items: center; justify-content: space-between; padding-top: 9px; }
.pp-step { display: flex; align-items: center; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
.pp-step button { width: 27px; height: 27px; border: none; background: var(--paper); font-size: 14px; font-weight: 700; color: var(--teal-dark); cursor: pointer; font-family: inherit; }
.pp-step button:disabled { cursor: not-allowed; }
.pp-qty { width: 30px; text-align: center; font-size: 12.5px; font-weight: 700; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
.pp-total { font-size: 13px; font-weight: 800; color: var(--ink); font-family: 'IBM Plex Mono', ui-monospace, monospace; }
.pp-total.zero { color: #B7BEB9; }
.pp-oos { font-size: 10px; font-weight: 700; color: var(--red); background: #FBEAE5; padding: 2px 7px; border-radius: 5px; align-self: flex-start; margin-top: 6px; }
.pp-empty { text-align: center; color: var(--ink-soft); padding: 40px 12px; font-weight: 600; }
.pp-cart {
    display: flex; align-items: center; justify-content: space-between;
    background: var(--teal-dark); color: #fff; margin: 0 20px 10px;
    padding: 11px 15px; border-radius: 12px; text-decoration: none;
}
.pp-cart-left { font-size: 12.5px; font-weight: 700; }
.pp-cart-left span { color: rgba(255,255,255,.65); font-weight: 500; }
.pp-cart-total { font-size: 15px; font-weight: 800; font-family: 'IBM Plex Mono', ui-monospace, monospace; }
.pp-overlay {
    position: fixed; inset: 0; background: rgba(10,18,15,.45);
    display: none; align-items: center; justify-content: center; z-index: 80;
}
.pp-overlay.show { display: flex; }
.pp-popup { background: #fff; border-radius: 14px; width: 82%; max-width: 340px; max-height: 70%; overflow-y: auto; padding: 16px; }
.pp-popup-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.pp-popup-title { font-size: 13.5px; font-weight: 800; line-height: 1.3; padding-right: 8px; }
.pp-popup-close { width: 26px; height: 26px; border-radius: 50%; border: none; background: var(--paper); color: var(--ink-soft); font-weight: 700; cursor: pointer; }
@media (max-width: 720px) {
    .pp-grid { gap: 9px; }
    .pp-card { padding: 9px; gap: 8px; flex-direction: column; }
    .pp-img { width: 100%; height: 64px; }
    .pp-name { font-size: 11.5px; }
    .pp-pack { font-size: 9.5px; }
    .pp-price { font-size: 12.5px; }
    .pp-unit-price, .pp-last { font-size: 9px; }
    .pp-code { font-size: 8.5px; }
    .pp-units button { font-size: 9px; padding: 4px 7px; }
    .pp-step button { width: 23px; height: 23px; font-size: 12px; }
    .pp-qty { width: 22px; font-size: 11px; }
    .pp-total { font-size: 11px; }
    .pp-sort .pp-sort-label { display: none; }
}
</style>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;700&display=swap">

<div class="pp-catalog" id="ppCatalog">
    <div class="pp-toolbar">
        <div class="pp-search">
            <span aria-hidden="true">&#128269;</span>
            <input type="search" id="prodQ" placeholder="Search products or scan barcode" autocomplete="off">
        </div>
        <button type="button" class="pp-icon" id="ppScan" title="Scan barcode" aria-label="Scan barcode">&#9638;</button>
        <button type="button" class="pp-icon on" id="ppGrid" title="Grid view" aria-label="Grid view">&#9638;&#9638;</button>
        <button type="button" class="pp-icon" id="ppList" title="List view" aria-label="List view">&#9776;</button>
        <button type="button" class="pp-sort" id="ppSort"><span class="pp-sort-label">Sort</span> &#8645;</button>
    </div>
    <div class="pp-chips" id="prodCatChips">
        <button type="button" class="pp-chip on" data-cat="">All Items</button>
        @foreach($categories as $cat)
            <button type="button" class="pp-chip" data-cat="{{ $cat['id'] }}">{{ $cat['name'] }}</button>
        @endforeach
    </div>
    <div class="pp-chips" id="prodSubChips" hidden></div>
    <div class="pp-scroll">
        <div class="pp-grid" id="prodList"></div>
        <p id="prodEmpty" class="pp-empty" hidden>No products found</p>
    </div>
    <a class="pp-cart" id="ppCart" href="{{ route('sale.orders.create') }}">
        <div class="pp-cart-left"><span id="ppCartItems">0 items</span> <span id="ppCartProducts">· 0 products</span></div>
        <div class="pp-cart-total" id="ppCartTotal">$0.00</div>
    </a>
</div>

<div class="pp-overlay" id="ppHistory">
    <div class="pp-popup" id="ppHistoryBox"></div>
</div>
<select id="prodCat" hidden><option value=""></option>@foreach($categories as $cat)<option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>@endforeach</select>
<select id="prodSub" hidden><option value=""></option></select>
@endsection

@push('scripts')
<script>
(function () {
    const categories = @json($categoriesJson);
    const orderUrl = @json(route('sale.orders.create'));
    const api = @json(route('sale.api.products'));
    const loc = document.getElementById('prodLoc');
    const q = document.getElementById('prodQ');
    const cat = document.getElementById('prodCat');
    const sub = document.getElementById('prodSub');
    const list = document.getElementById('prodList');
    const empty = document.getElementById('prodEmpty');
    const catChips = document.getElementById('prodCatChips');
    const subChips = document.getElementById('prodSubChips');
    const catalog = document.getElementById('ppCatalog');
    const cartItems = document.getElementById('ppCartItems');
    const cartProducts = document.getElementById('ppCartProducts');
    const cartTotal = document.getElementById('ppCartTotal');
    const history = document.getElementById('ppHistory');
    const historyBox = document.getElementById('ppHistoryBox');
    const lines = {};
    let sort = 'name';
    let timer = null;
    let rows = [];

    function money(n) { return '$' + (Number(n) || 0).toFixed(2); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function caseUnit(r) {
        const units = Array.isArray(r.units) ? r.units : [];
        const sorted = units.slice().sort((a, b) => (Number(b.multiplier) || 1) - (Number(a.multiplier) || 1));
        const pack = sorted.find(u => (Number(u.multiplier) || 1) > 1) || sorted[0] || null;
        const piece = units.find(u => (Number(u.multiplier) || 1) === 1) || { id: r.unit_id, name: 'Piece', multiplier: 1 };
        return { pack, piece, units };
    }
    function activePrice(r, line) {
        const m = line.unit === 'piece' ? 1 : (Number(line.multiplier) || 1);
        const base = Number(r.base_price != null ? r.base_price : r.price) || 0;
        return line.unit === 'piece' ? base : base * m;
    }
    function inStock(r) {
        return Number(r.enable_stock) !== 1 || Number(r.stock) > 0;
    }

    function paintCart() {
        let items = 0, products = 0, total = 0;
        Object.keys(lines).forEach(id => {
            const line = lines[id];
            if (!line.qty) return;
            const r = rows.find(x => String(x.variation_id) === String(id));
            if (!r) return;
            products += 1;
            items += line.qty;
            total += line.qty * activePrice(r, line);
        });
        cartItems.textContent = items + (items === 1 ? ' item' : ' items');
        cartProducts.textContent = '· ' + products + (products === 1 ? ' product' : ' products');
        cartTotal.textContent = money(total);
    }

    function render() {
        const sorted = rows.slice().sort((a, b) => {
            if (sort === 'price') return (Number(a.price) || 0) - (Number(b.price) || 0);
            return String(a.name).localeCompare(String(b.name));
        });
        list.innerHTML = '';
        empty.hidden = sorted.length > 0;
        empty.textContent = loc.value ? 'No products found' : 'Set a default location in Account first';
        sorted.forEach(r => {
            const id = String(r.variation_id);
            const cu = caseUnit(r);
            if (!lines[id]) {
                lines[id] = {
                    qty: 0,
                    unit: cu.pack && (Number(cu.pack.multiplier) || 1) > 1 ? 'case' : 'piece',
                    multiplier: cu.pack ? (Number(cu.pack.multiplier) || 1) : 1,
                    unitId: cu.pack ? cu.pack.id : r.unit_id
                };
            }
            const line = lines[id];
            const oos = !inStock(r);
            const price = activePrice(r, line);
            const piece = (Number(r.base_price != null ? r.base_price : r.price) || 0);
            const packName = cu.pack && (Number(cu.pack.multiplier) || 1) > 1
                ? (cu.pack.name + ' of ' + (Number(cu.pack.multiplier) || 1))
                : (r.unit_name || '');
            const el = document.createElement('div');
            el.className = 'pp-card' + (oos ? ' is-oos' : '');
            const thumb = r.has_image
                ? '<img src="' + esc(r.image) + '" alt="">'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="m21 15-4.5-4.5L9 18"/></svg>';
            const toggle = cu.pack && cu.piece && String(cu.pack.id) !== String(cu.piece.id)
                ? '<div class="pp-units"><button type="button" data-unit="case" class="' + (line.unit === 'case' ? 'sel' : '') + '">' + esc(cu.pack.name) + '</button><button type="button" data-unit="piece" class="' + (line.unit === 'piece' ? 'sel' : '') + '">' + esc(cu.piece.name) + '</button></div>'
                : '';
            el.innerHTML =
                '<div class="pp-img">' + thumb + '</div>' +
                '<div class="pp-body">' +
                    '<div class="pp-name">' + esc(r.name) + '</div>' +
                    (packName ? '<div class="pp-pack">' + esc(packName) + '</div>' : '') +
                    (r.sku ? '<div class="pp-code">Code: ' + esc(r.sku) + '</div>' : '') +
                    (oos ? '<div class="pp-oos">OUT OF STOCK</div>' : (
                        '<div class="pp-price-row"><div class="pp-price">' + money(price) + (line.unit === 'piece' ? '/pc' : '') + '</div>' +
                        '<div class="pp-unit-price">' + money(piece) + '/pc</div></div>' +
                        '<div class="pp-last">No prior orders</div>' + toggle
                    )) +
                    '<div class="pp-bottom"><div class="pp-step">' +
                        '<button type="button" data-act="minus"' + (oos ? ' disabled' : '') + '>&#8722;</button>' +
                        '<div class="pp-qty">' + line.qty + '</div>' +
                        '<button type="button" data-act="plus"' + (oos ? ' disabled' : '') + '>+</button>' +
                    '</div><div class="pp-total' + (line.qty ? '' : ' zero') + '">' + money(line.qty * price) + '</div></div>' +
                '</div>';
            el.addEventListener('click', (e) => {
                const act = e.target.closest('[data-act]');
                const unitBtn = e.target.closest('[data-unit]');
                if (act && !oos) {
                    if (act.dataset.act === 'plus') line.qty++;
                    if (act.dataset.act === 'minus' && line.qty > 0) line.qty--;
                    render();
                } else if (unitBtn && !oos) {
                    line.unit = unitBtn.dataset.unit;
                    if (line.unit === 'piece') {
                        line.multiplier = 1;
                        line.unitId = cu.piece.id;
                    } else {
                        line.multiplier = Number(cu.pack.multiplier) || 1;
                        line.unitId = cu.pack.id;
                    }
                    render();
                }
            });
            list.appendChild(el);
        });
        paintCart();
    }

    function fillSubs() {
        const id = cat.value;
        sub.innerHTML = '<option value=""></option>';
        subChips.innerHTML = '';
        subChips.hidden = true;
        if (!id) return;
        const found = categories.find(c => String(c.id) === String(id));
        const subs = found ? found.sub_categories : [];
        if (!subs.length) return;
        const all = document.createElement('button');
        all.type = 'button';
        all.className = 'pp-chip' + (sub.value ? '' : ' on');
        all.dataset.sub = '';
        all.textContent = 'All';
        subChips.appendChild(all);
        subs.forEach(s => {
            const o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.name;
            sub.appendChild(o);
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'pp-chip' + (String(sub.value) === String(s.id) ? ' on' : '');
            chip.dataset.sub = s.id;
            chip.textContent = s.name;
            subChips.appendChild(chip);
        });
        subChips.hidden = false;
    }

    async function load() {
        catChips.querySelectorAll('.pp-chip').forEach(chip => {
            chip.classList.toggle('on', String(chip.dataset.cat || '') === String(cat.value || ''));
        });
        if (!loc.value) { rows = []; render(); return; }
        let url = api + '?location_id=' + encodeURIComponent(loc.value) + '&limit=80';
        const term = q.value.trim();
        if (term) url += '&q=' + encodeURIComponent(term);
        if (sub.value) url += '&sub_category_id=' + encodeURIComponent(sub.value);
        else if (cat.value) url += '&category_id=' + encodeURIComponent(cat.value);
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        rows = await res.json();
        render();
    }

    catChips.addEventListener('click', (e) => {
        const chip = e.target.closest('.pp-chip');
        if (!chip) return;
        cat.value = chip.dataset.cat || '';
        sub.value = '';
        fillSubs();
        load();
    });
    subChips.addEventListener('click', (e) => {
        const chip = e.target.closest('.pp-chip');
        if (!chip) return;
        sub.value = chip.dataset.sub || '';
        fillSubs();
        load();
    });
    q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
    document.getElementById('ppScan').addEventListener('click', () => q.focus());
    document.getElementById('ppGrid').addEventListener('click', () => {
        catalog.classList.remove('is-list');
        document.getElementById('ppGrid').classList.add('on');
        document.getElementById('ppList').classList.remove('on');
    });
    document.getElementById('ppList').addEventListener('click', () => {
        catalog.classList.add('is-list');
        document.getElementById('ppList').classList.add('on');
        document.getElementById('ppGrid').classList.remove('on');
    });
    document.getElementById('ppSort').addEventListener('click', () => {
        sort = sort === 'name' ? 'price' : 'name';
        render();
    });
    document.getElementById('ppCart').addEventListener('click', (e) => {
        const picked = Object.keys(lines).filter(id => lines[id].qty > 0);
        if (!picked.length) return;
        e.preventDefault();
        const first = picked[0];
        const line = lines[first];
        const params = new URLSearchParams({ add: first, qty: String(line.qty) });
        if (line.unitId) params.set('sub_unit_id', String(line.unitId));
        window.location = orderUrl + '?' + params.toString();
    });
    history.addEventListener('click', (e) => { if (e.target === history) history.classList.remove('show'); });

    fillSubs();
    load();
})();
</script>
@endpush
