@extends('sale.layout')
@section('title', !empty($edit_order) ? 'Edit order' : 'Create order')
@section('header', !empty($edit_order) ? 'Edit order' : 'Create order')
@section('content')
<form method="POST" action="{{ !empty($edit_order) ? route('sale.orders.update', $edit_order->id) : route('sale.orders.store') }}" id="saleOrderForm" class="sale-create-form">
    @csrf
    @if(!empty($edit_order))
        @method('PUT')
    @endif
    <input type="hidden" name="contact_id" id="contact_id" value="{{ old('contact_id', $default_customer['id'] ?? '') }}" required>
    <input type="hidden" name="order_mode" id="order_mode" value="new_order">
    <div id="productsJson" class="hidden"></div>

    {{-- STEP 0: Select customer (layout like app — uses existing customers API) --}}
    @if(empty($edit_order))
    <div id="stepCustomer" class="sale-pick-customer">
        <div class="sale-order-build__bar">
            <div class="sale-order-build__titles min-w-0">
                <div class="sale-order-build__title">Create Order</div>
                <div class="sale-order-build__sub">Select Customer</div>
            </div>
            <button type="button" class="sale-order-build__park hidden" id="parkedOpenBtnCustomer">PARKED</button>
        </div>

        <div class="sale-pick-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" id="customerSearch" class="sale-pick-search__input" placeholder="Search Customer Name" autocomplete="off">
        </div>

        <div id="customerResults" class="sale-pick-list"></div>
    </div>
    @endif

    {{-- STEP 1: Build order (after customer) — Pickup/Delivery/Shipping + SKU/scan + catalog --}}
    <div id="stepCart" class="sale-order-build" @if(empty($edit_order)) hidden @endif>
        <div class="sale-order-build__bar">
            <button type="button" id="backToCustomerBtn" class="sale-order-build__iconbtn" aria-label="Back" @if(!empty($edit_order)) hidden @endif>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div class="sale-order-build__titles min-w-0">
                <div class="sale-order-build__title">Create Order</div>
                <div class="sale-order-build__sub truncate" id="orderCustomerName">{{ $default_customer['text'] ?? ($edit_order->contact->supplier_business_name ?? $edit_order->contact->name ?? '') }}</div>
            </div>
            <div class="sale-order-build__actions">
            @if(empty($edit_order))
            <button type="button" class="sale-order-build__park" id="parkSaleBtn" title="Park this sale">PARK</button>
            @endif
            <button type="button" class="sale-order-build__submit" id="goShippingBtn">SUBMIT</button>
            </div>
        </div>

        <div class="sale-order-build__body">
            <div class="sale-sku-block">
                <div class="sale-sku-row">
                    <div class="sale-sku-modes" role="group" aria-label="Search mode">
                        <button type="button" class="sale-sku-mode is-active" id="skuModeScan" title="Scan / SKU" aria-pressed="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8h1M10 8h2M14 8h3M7 12h3M12 12h1M15 12h2M7 16h2M11 16h4"/></svg>
                        </button>
                        <button type="button" class="sale-sku-mode" id="skuModeText" title="Search by name" aria-pressed="false">
                            <span class="font-extrabold text-sm leading-none">T</span>
                        </button>
                    </div>
                    <div class="sale-sku-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="search" id="productSearch" class="sale-sku-search__input" placeholder="Enter SKU" autocomplete="off" enterkeyhint="search">
                    </div>
                    <button type="button" class="sale-sku-mode sale-sku-camera" id="skuCameraBtn" title="Camera scan" aria-label="Camera scan">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.2"/></svg>
                    </button>
                </div>
                <div id="productResults" class="sale-prod-results hidden"></div>
            </div>

            <div class="sale-order-list-head">
                <strong>Order List</strong>
                <div class="flex items-center gap-3">
                    <button type="button" id="parkedOpenBtnCart" class="sale-order-list-head__link hidden">Parked</button>
                    <button type="button" id="catalogOpenBtn" class="sale-order-list-head__link">Product Catalog</button>
                </div>
            </div>

            <div class="sale-cart-scroll" id="cartScroll">
                <div id="cartEmpty" class="sale-cart-empty">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-slate-300 mx-auto mb-2"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.4a2 2 0 0 0 2-1.5L21 8H7"/></svg>
                    <div class="text-sm font-semibold text-slate-400">Order list is empty</div>
                    <div class="text-xs text-slate-400 mt-1">Scan SKU or open Product Catalog</div>
                </div>
                <div id="cartLines" class="sale-cart-lines"></div>
            </div>

            <div class="sale-order-build__total">
                <span>Total</span>
                <strong id="cartTotal" class="tabular-nums">$0.00</strong>
            </div>
        </div>

        <input type="hidden" name="payment_type" value="due">
        {{-- Keep selected customer chip for change (edit / change customer) --}}
        <button type="button" id="customerSelected" class="hidden" aria-hidden="true">
            <span id="customerLabel"></span>
        </button>
        <div id="customerSearchWrap" class="hidden">
            @if(!empty($edit_order))
                <input type="search" id="customerSearchEdit" class="sale-input" placeholder="Search customer name / mobile" autocomplete="off">
                <div id="customerResultsEdit" class="mt-2 border border-sale-line rounded-xl bg-white max-h-40 overflow-auto hidden"></div>
            @endif
        </div>
    </div>

    {{-- STEP 2: Shipping info (same fields as desk sales order) --}}
    <div id="stepShipping" class="sale-ship-flow" hidden>
        <button type="button" id="backToCartBtn" class="inline-flex items-center gap-1.5 text-sm font-bold text-sale mb-3">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
            Back to cart
        </button>

        <div class="sale-card space-y-3 mb-3">
            <div class="sale-sec-title !mb-1">
                <span class="sale-sec-title__ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                </span>
                Shipping info
            </div>
            <div class="flex justify-between text-sm font-bold mb-1">
                <span class="text-slate-500">Order total</span>
                <span id="shipTotal" class="tabular-nums text-sale">$0.00</span>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_to_address_id">Ship to</label>
                <select name="ship_to_address_id" id="ship_to_address_id" class="sale-input">
                    <option value="">Billing address</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_to_name">Name</label>
                <input type="text" name="ship_to_name" id="ship_to_name" class="sale-input" value="{{ old('ship_to_name', $edit_order->ship_to_name ?? '') }}">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_to_address">Address</label>
                <textarea name="ship_to_address" id="ship_to_address" rows="2" class="sale-input">{{ old('ship_to_address', $edit_order->ship_to_address ?? '') }}</textarea>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_to_city">City</label>
                    <input type="text" name="ship_to_city" id="ship_to_city" class="sale-input" value="{{ old('ship_to_city', $edit_order->ship_to_city ?? '') }}">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_to_state">State</label>
                    <input type="text" name="ship_to_state" id="ship_to_state" class="sale-input" value="{{ old('ship_to_state', $edit_order->ship_to_state ?? '') }}">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_to_zip">ZIP</label>
                    <input type="text" name="ship_to_zip" id="ship_to_zip" class="sale-input" value="{{ old('ship_to_zip', $edit_order->ship_to_zip ?? '') }}">
                </div>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_to_phone">Phone</label>
                <input type="text" name="ship_to_phone" id="ship_to_phone" class="sale-input" value="{{ old('ship_to_phone', $edit_order->ship_to_phone ?? '') }}">
            </div>

            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="location_id">Ship from</label>
                <select name="location_id" id="location_id" class="sale-input" required>
                    @foreach(($locations ?? []) as $lid => $lname)
                        <option value="{{ $lid }}" @selected((string) $lid === (string) old('location_id', $default_location))>{{ $lname }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_via_id">Ship via</label>
                <select name="ship_via_id" id="ship_via_id" class="sale-input">
                    <option value="">—</option>
                    @foreach(($ship_vias ?? []) as $sv)
                        <option value="{{ $sv->id }}" @selected((string) $sv->id === (string) old('ship_via_id', $edit_order->ship_via_id ?? ''))>{{ $sv->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="payment_term_id">Payment terms</label>
                <select name="payment_term_id" id="payment_term_id" class="sale-input">
                    <option value="">—</option>
                    @foreach(($payment_terms ?? []) as $pt)
                        <option value="{{ $pt->id }}" @selected((string) $pt->id === (string) old('payment_term_id', $edit_order->payment_term_id ?? ''))>{{ $pt->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="route_id">Route</label>
                <select name="route_id" id="route_id" class="sale-input">
                    <option value="">—</option>
                    @foreach(($routes ?? []) as $route)
                        <option value="{{ $route->id }}" @selected((string) $route->id === (string) old('route_id', $edit_order->route_id ?? ''))>{{ $route->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="ship_date">Ship date</label>
                <input type="date" name="ship_date" id="ship_date" class="sale-input" value="{{ old('ship_date', !empty($edit_order) && $edit_order->ship_date ? $edit_order->ship_date->format('Y-m-d') : '') }}">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="sale_note">Comments</label>
                <textarea name="sale_note" id="sale_note" rows="2" class="sale-input" placeholder="Order comments">{{ old('sale_note', $edit_order->comments ?? $edit_order->additional_notes ?? '') }}</textarea>
            </div>
        </div>

        <div class="sale-create-bar">
            <button type="submit" class="sale-btn !w-full inline-flex items-center justify-center gap-2" id="submitOrderBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                {{ !empty($edit_order) ? 'Save order' : 'Create order' }}
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<div id="catalogModal" class="sale-catalog" hidden>
    <div class="sale-catalog__panel">
        <div class="sale-catalog__head">
            <button type="button" id="catalogBackBtn" class="sale-catalog__back hidden" aria-label="Back">‹</button>
            <div class="font-extrabold text-base truncate flex-1" id="catalogTitle">Catalog</div>
            <button type="button" id="catalogCloseBtn" class="sale-catalog__close" aria-label="Close">×</button>
        </div>
        <div id="catalogBody" class="sale-catalog__body"></div>
    </div>
</div>
<div id="parkedModal" class="sale-catalog" hidden>
    <div class="sale-catalog__panel">
        <div class="sale-catalog__head">
            <div class="font-extrabold text-base truncate flex-1">Parked sales</div>
            <button type="button" id="parkedCloseBtn" class="sale-catalog__close" aria-label="Close">×</button>
        </div>
        <div id="parkedBody" class="sale-catalog__body"></div>
    </div>
</div>
<div id="saleScanOverlay" class="sale-scan-overlay" hidden>
    <div class="sale-scan-overlay__bar">
        <div class="font-extrabold">Camera scan</div>
        <button type="button" id="saleScanCloseBtn" class="sale-catalog__close" aria-label="Close scanner">×</button>
    </div>
    <p class="sale-scan-overlay__hint">Point the camera at a barcode. Each scan adds the item to this order.</p>
    <div id="saleScanRegion" class="sale-scan-overlay__video"></div>
    <video id="saleScanVideo" class="sale-scan-overlay__native" playsinline muted hidden></video>
    <div id="saleScanStatus" class="sale-scan-overlay__status"></div>
</div>
<script>
(function () {
    const cart = [];
    const customerSearch = document.getElementById('customerSearch');
    const customerResults = document.getElementById('customerResults');
    const customerLabel = document.getElementById('customerLabel');
    const customerSelected = document.getElementById('customerSelected');
    const customerSearchWrap = document.getElementById('customerSearchWrap');
    const contactId = document.getElementById('contact_id');
    const productSearch = document.getElementById('productSearch');
    const productResults = document.getElementById('productResults');
    const cartLines = document.getElementById('cartLines');
    const cartEmpty = document.getElementById('cartEmpty');
    const cartTotal = document.getElementById('cartTotal');
    const shipTotal = document.getElementById('shipTotal');
    const locationId = document.getElementById('location_id');
    const shipToSelect = document.getElementById('ship_to_address_id');
    const shipToName = document.getElementById('ship_to_name');
    const shipToAddress = document.getElementById('ship_to_address');
    const shipToCity = document.getElementById('ship_to_city');
    const shipToState = document.getElementById('ship_to_state');
    const shipToZip = document.getElementById('ship_to_zip');
    const shipToPhone = document.getElementById('ship_to_phone');
    const paymentTermId = document.getElementById('payment_term_id');
    const routeId = document.getElementById('route_id');
    const form = document.getElementById('saleOrderForm');
    const productsJson = document.getElementById('productsJson');
    const stepCart = document.getElementById('stepCart');
    const stepShipping = document.getElementById('stepShipping');
    let custTimer = null, prodTimer = null;
    let savedShipAddr = '';
    let lastCustomer = { id: '', text: '', shipAddr: '' };

    const catalogModal = document.getElementById('catalogModal');
    const catalogBody = document.getElementById('catalogBody');
    const catalogTitle = document.getElementById('catalogTitle');
    const catalogBackBtn = document.getElementById('catalogBackBtn');
    let catalogTree = null;
    let catalogStack = [];

    function money(n) { return '$' + (Number(n) || 0).toFixed(2); }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function cartSum() {
        return cart.reduce((s, l) => s + (l.quantity * l.unit_price), 0);
    }

    function addToCart(r, silent, skipRender) {
        if (!r || !r.variation_id) return;
        const vid = Number(r.variation_id);
        const existingIdx = cart.findIndex(c => Number(c.variation_id) === vid);
        if (existingIdx >= 0) {
            cart[existingIdx].quantity = +cart[existingIdx].quantity + (Number(r.quantity) || 1);
            if (typeof r.allow_decimal !== 'undefined') {
                cart[existingIdx].allow_decimal = !!Number(r.allow_decimal);
            }
            if (r.transaction_sell_lines_id && !cart[existingIdx].transaction_sell_lines_id) {
                cart[existingIdx].transaction_sell_lines_id = r.transaction_sell_lines_id;
            }
            cart.unshift(cart.splice(existingIdx, 1)[0]);
        } else {
            cart.unshift({
                product_id: Number(r.product_id),
                variation_id: vid,
                transaction_sell_lines_id: r.transaction_sell_lines_id || null,
                name: r.name,
                unit_price: Number(r.price) || 0,
                quantity: Number(r.quantity) || 1,
                enable_stock: r.enable_stock,
                product_type: r.product_type,
                allow_decimal: !!Number(r.allow_decimal),
            });
        }
        if (!skipRender) renderCart();
        if (!silent) showAddedMsg();
    }

    function showAddedMsg() {
        let el = document.getElementById('saleAddedMsg');
        if (!el) {
            el = document.createElement('div');
            el.id = 'saleAddedMsg';
            el.className = 'sale-added-msg';
            document.body.appendChild(el);
        }
        el.textContent = 'Added';
        el.hidden = false;
        clearTimeout(showAddedMsg._t);
        showAddedMsg._t = setTimeout(() => { el.hidden = true; }, 1400);
    }

    function cartQty(variationId) {
        const line = cart.find(c => Number(c.variation_id) === Number(variationId));
        if (!line) return 0;
        return formatQty(line);
    }

    function formatQty(line) {
        const q = Number(line.quantity) || 0;
        if (line.allow_decimal) {
            return String(Math.round(q * 100) / 100);
        }
        return String(Math.max(0, Math.round(q)));
    }

    function changeQty(i, delta) {
        const line = cart[i];
        if (!line) return;
        if (delta < 0 && Number(line.quantity) <= 1) return;
        let next = Number(line.quantity) + delta;
        if (!line.allow_decimal) {
            next = Math.round(next);
            if (next < 1) return;
        } else {
            next = Math.round(next * 100) / 100;
            if (next < 1) return;
        }
        line.quantity = next;
        renderCart();
    }

    function setQtyFromInput(i, raw) {
        const line = cart[i];
        if (!line) return;
        let q = parseFloat(String(raw).replace(',', '.'));
        if (!isFinite(q)) q = 1;
        if (!line.allow_decimal) {
            q = Math.round(q);
        } else {
            q = Math.round(q * 100) / 100;
        }
        if (q < 1) q = 1;
        line.quantity = q;
        renderCart();
    }

    const stepCustomer = document.getElementById('stepCustomer');
    const customerSearchEdit = document.getElementById('customerSearchEdit');
    const customerResultsEdit = document.getElementById('customerResultsEdit');

    function showCustomerStep() {
        if (!stepCustomer) return;
        stepCustomer.hidden = false;
        stepCart.hidden = true;
        stepShipping.hidden = true;
        document.body.classList.add('sale-picking-customer');
        document.body.classList.remove('sale-building-order');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function fillShipFields(addr) {
        if (!addr) return;
        if (shipToName) shipToName.value = addr.name || '';
        if (shipToAddress) shipToAddress.value = addr.address || '';
        if (shipToCity) shipToCity.value = addr.city || '';
        if (shipToState) shipToState.value = addr.state || '';
        if (shipToZip) shipToZip.value = addr.zip || '';
        if (shipToPhone) shipToPhone.value = addr.telephone || '';
    }

    function fillShipSelect(addresses, selectedId) {
        lastCustomer.addresses = addresses || [];
        if (!shipToSelect) return;
        const keepId = selectedId != null && selectedId !== '' ? String(selectedId) : '';
        shipToSelect.innerHTML = '<option value="">Billing address</option>';
        (addresses || []).forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            opt.textContent = (a.name || ('Ship-To #' + a.id)) + (a.is_primary ? ' (primary)' : '');
            shipToSelect.appendChild(opt);
        });
        if (keepId && [...shipToSelect.options].some(o => o.value === keepId)) {
            shipToSelect.value = keepId;
        } else if (addresses && addresses.length) {
            const primary = addresses.find(a => a.is_primary) || addresses[0];
            shipToSelect.value = String(primary.id);
        }
    }

    async function loadCustomerShipping(customerId, preferExisting) {
        if (!customerId) return;
        try {
            const res = await fetch(@json(url('/sale/api/customers')) + '/' + customerId + '/shipping', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) return;
            const data = await res.json();
            const existingId = @json(old('ship_to_address_id', optional($edit_order ?? null)->ship_to_address_id));
            fillShipSelect(data.shipping_addresses || [], preferExisting ? existingId : (data.default_ship && data.default_ship.id));
            if (!preferExisting) {
                fillShipFields(data.default_ship);
                if (paymentTermId && data.payment_term_id && !paymentTermId.value) paymentTermId.value = data.payment_term_id;
                if (routeId && data.route_id && !routeId.value) routeId.value = data.route_id;
            } else if (shipToName && !shipToName.value && data.default_ship) {
                fillShipFields(data.default_ship);
            }
        } catch (err) {
            console.error(err);
        }
    }

    function showCartStep() {
        if (stepCustomer) stepCustomer.hidden = true;
        stepCart.hidden = false;
        stepShipping.hidden = true;
        document.body.classList.remove('sale-picking-customer');
        document.body.classList.add('sale-building-order');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function setCustomer(id, text, shipAddr, goToCart, row) {
        contactId.value = id || '';
        const orderCustomerName = document.getElementById('orderCustomerName');
        if (id) {
            lastCustomer = { id: String(id), text: text || '', shipAddr: shipAddr || '', addresses: (row && row.shipping_addresses) || [] };
            if (customerLabel) customerLabel.textContent = text;
            if (orderCustomerName) orderCustomerName.textContent = text;
            // Keep chip hidden — customer name shows in order header only
            if (customerSelected) {
                customerSelected.classList.add('hidden');
                customerSelected.setAttribute('aria-hidden', 'true');
            }
            if (customerSearchWrap) customerSearchWrap.classList.add('hidden');
            if (customerSearch) customerSearch.value = '';
            if (customerResults && !stepCustomer) {
                customerResults.classList.add('hidden');
                customerResults.innerHTML = '';
            }
            if (customerResultsEdit) {
                customerResultsEdit.classList.add('hidden');
                customerResultsEdit.innerHTML = '';
            }
            savedShipAddr = shipAddr || '';
            try {
                if (row && Array.isArray(row.shipping_addresses)) {
                    fillShipSelect(row.shipping_addresses, row.default_ship && row.default_ship.id);
                    fillShipFields(row.default_ship);
                    if (paymentTermId && row.payment_term_id) paymentTermId.value = row.payment_term_id;
                    if (routeId && row.route_id) routeId.value = row.route_id;
                } else if (id) {
                    loadCustomerShipping(id, false);
                }
            } catch (err) {
                console.error(err);
            }
            if (goToCart !== false && stepCustomer) {
                showCartStep();
            }
        } else {
            lastCustomer = { id: '', text: '', shipAddr: '' };
            if (customerLabel) customerLabel.textContent = '';
            if (orderCustomerName) orderCustomerName.textContent = '';
            if (customerSelected) customerSelected.classList.add('hidden');
            savedShipAddr = '';
            if (stepCustomer) showCustomerStep();
        }
    }

    function openCustomerSearch() {
        if (stepCustomer) {
            showCustomerStep();
            if (customerSearch) customerSearch.focus();
            return;
        }
        customerSelected.classList.add('hidden');
        if (customerSearchWrap) customerSearchWrap.classList.remove('hidden');
        if (customerSearchEdit) {
            customerSearchEdit.value = '';
            customerSearchEdit.focus();
        }
    }

    customerSelected.addEventListener('click', openCustomerSearch);

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderCustomerRows(rows, targetEl, pickFn) {
        targetEl.innerHTML = '';
        if (!rows.length) {
            targetEl.innerHTML = '<div class="sale-pick-empty">No customers found</div>';
            targetEl.classList.remove('hidden');
            return;
        }
        rows.forEach(r => {
            const a = document.createElement('button');
            a.type = 'button';
            if (targetEl === customerResults && stepCustomer) {
                a.className = 'sale-pick-row';
                a.innerHTML = `
                    <span class="sale-pick-avatar">${esc(r.initials || 'C')}</span>
                    <span class="sale-pick-meta min-w-0">
                        <span class="sale-pick-name">${esc(r.display_name || r.text || '')}</span>
                        <span class="sale-pick-addr">${esc(r.address || r.mobile || '')}</span>
                    </span>
                    <span class="sale-pick-chev" aria-hidden="true">›</span>`;
            } else {
                a.className = 'cust-pick w-full text-left px-3 py-2.5 text-sm border-b border-slate-100 font-semibold';
                a.textContent = r.text;
            }
            a.onclick = () => pickFn(r);
            targetEl.appendChild(a);
        });
        targetEl.classList.remove('hidden');
    }

    async function loadCustomers(q, targetEl, pickFn) {
        const rows = await fetchJson(@json(route('sale.api.customers')) + '?q=' + encodeURIComponent(q || ''));
        renderCustomerRows(rows, targetEl, pickFn);
    }

    if (customerSearch && customerResults) {
        customerSearch.addEventListener('input', () => {
            clearTimeout(custTimer);
            custTimer = setTimeout(() => {
                loadCustomers(customerSearch.value.trim(), customerResults, (r) => {
                    setCustomer(r.id, r.text, r.shipping_address || r.address || '', true, r);
                });
            }, 250);
        });
        if (stepCustomer) document.body.classList.add('sale-picking-customer');
    }

    if (customerSearchEdit && customerResultsEdit) {
        customerSearchEdit.addEventListener('input', () => {
            clearTimeout(custTimer);
            custTimer = setTimeout(() => {
                loadCustomers(customerSearchEdit.value.trim(), customerResultsEdit, (r) => {
                    setCustomer(r.id, r.text, r.shipping_address || r.address || '', false, r);
                    customerSearchWrap.classList.add('hidden');
                });
            }, 250);
        });
    }

    @if(!empty($default_customer))
    setCustomer(
        {{ (int) $default_customer['id'] }},
        @json($default_customer['text']),
        @json($default_customer['shipping_address'] ?? ''),
        true
    );
    @elseif(!empty($edit_order))
    document.body.classList.remove('sale-picking-customer');
    document.body.classList.add('sale-building-order');
    loadCustomerShipping({{ (int) $edit_order->customer_id }}, true);
    @endif

    function renderCart() {
        cartLines.innerHTML = '';
        const total = cartSum();
        cartEmpty.classList.toggle('hidden', cart.length > 0);
        cart.forEach((line, idx) => {
            const lineTot = line.quantity * line.unit_price;
            const el = document.createElement('div');
            el.className = 'sale-cart-item';
            el.innerHTML = `
                <div class="sale-cart-item__top">
                    <div class="min-w-0">
                        <div class="font-bold text-sm leading-snug break-words">${escapeHtml(line.name)}</div>
                        <div class="text-xs text-slate-500 mt-0.5">${money(line.unit_price)} each</div>
                    </div>
                    <button type="button" data-rm="${idx}" class="sale-cart-item__rm" aria-label="Remove">×</button>
                </div>
                <div class="sale-cart-item__qty">
                    <button type="button" data-dec="${idx}" class="sale-qty-btn"${Number(line.quantity) <= 1 ? ' disabled' : ''}>−</button>
                    <input type="text" inputmode="${line.allow_decimal ? 'decimal' : 'numeric'}" pattern="${line.allow_decimal ? '[0-9]*[.,]?[0-9]*' : '[0-9]*'}" value="${formatQty(line)}" data-qty="${idx}" class="sale-qty-input" autocomplete="off">
                    <button type="button" data-inc="${idx}" class="sale-qty-btn">+</button>
                    <span class="ml-auto font-extrabold tabular-nums text-sm text-sale">${money(lineTot)}</span>
                </div>`;
            cartLines.appendChild(el);
        });
        cartTotal.textContent = money(total);
        shipTotal.textContent = money(total);

        cartLines.querySelectorAll('[data-rm]').forEach(b => b.onclick = () => { cart.splice(+b.dataset.rm, 1); renderCart(); });
        cartLines.querySelectorAll('[data-inc]').forEach(b => b.onclick = () => changeQty(+b.dataset.inc, 1));
        cartLines.querySelectorAll('[data-dec]').forEach(b => b.onclick = () => changeQty(+b.dataset.dec, -1));
        cartLines.querySelectorAll('[data-qty]').forEach(inp => {
            inp.addEventListener('focus', () => inp.select());
            inp.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    inp.blur();
                }
            });
            inp.addEventListener('blur', () => setQtyFromInput(+inp.dataset.qty, inp.value));
        });
    }

    async function fetchJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) return [];
        const data = await res.json();
        if (Array.isArray(data)) return data;
        if (Array.isArray(data.data)) return data.data;
        if (Array.isArray(data.items)) return data.items;
        return [];
    }

    function productApiUrl(extra) {
        const params = new URLSearchParams(extra || {});
        if (locationId.value) params.set('location_id', locationId.value);
        if (contactId.value) params.set('contact_id', contactId.value);
        return @json(route('sale.api.products')) + '?' + params.toString();
    }

    if (customerSearch && customerResults && stepCustomer && !contactId.value) {
        loadCustomers('', customerResults, (r) => {
            setCustomer(r.id, r.text, r.shipping_address || r.address || '', true, r);
        });
    }

    productSearch.addEventListener('input', () => {
        clearTimeout(prodTimer);
        prodTimer = setTimeout(async () => {
            const q = productSearch.value.trim();
            if (q.length < 1) { productResults.classList.add('hidden'); productResults.innerHTML = ''; return; }
            const rows = await fetchJson(productApiUrl({ q: q }));
            productResults.innerHTML = '';
            if (!rows.length) {
                productResults.innerHTML = '<div class="px-3 py-3 text-sm text-slate-400">No products</div>';
                productResults.classList.remove('hidden');
                return;
            }
            // Scan mode: exact SKU match adds immediately when only one hit
            if (skuMode === 'scan' && rows.length === 1) {
                const exact = rows[0];
                const sku = String(exact.sku || '').toLowerCase();
                if (sku && sku === q.toLowerCase()) {
                    addToCart(exact);
                    productSearch.value = '';
                    productResults.classList.add('hidden');
                    productResults.innerHTML = '';
                    return;
                }
            }
            rows.forEach(r => {
                const a = document.createElement('button');
                a.type = 'button';
                a.className = 'product-pick w-full text-left px-3 py-2.5 text-sm border-b border-slate-100';
                a.innerHTML = `<div class="font-semibold">${r.name}</div><div class="text-xs text-slate-500">${money(r.price)} · Stock ${r.stock}</div>`;
                a.onclick = () => {
                    addToCart(r);
                    productSearch.value = '';
                    productResults.classList.add('hidden');
                    productResults.innerHTML = '';
                };
                productResults.appendChild(a);
            });
            productResults.classList.remove('hidden');
        }, 250);
    });

    productSearch.addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const q = productSearch.value.trim();
        if (!q) return;
        const rows = await fetchJson(productApiUrl({ q: q }));
        if (!rows.length) {
            productResults.innerHTML = '<div class="px-3 py-3 text-sm text-slate-400">No products</div>';
            productResults.classList.remove('hidden');
            return;
        }
        const exact = rows.find(r => String(r.sku || '').toLowerCase() === q.toLowerCase()) || (rows.length === 1 ? rows[0] : null);
        if (exact && skuMode === 'scan') {
            addToCart(exact);
            productSearch.value = '';
            productResults.classList.add('hidden');
            productResults.innerHTML = '';
            return;
        }
        productResults.innerHTML = '';
        rows.forEach(r => {
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'product-pick w-full text-left px-3 py-2.5 text-sm border-b border-slate-100';
            a.innerHTML = `<div class="font-semibold">${r.name}</div><div class="text-xs text-slate-500">${money(r.price)} · Stock ${r.stock}</div>`;
            a.onclick = () => {
                addToCart(r);
                productSearch.value = '';
                productResults.classList.add('hidden');
                productResults.innerHTML = '';
            };
            productResults.appendChild(a);
        });
        productResults.classList.remove('hidden');
    });

    document.getElementById('goShippingBtn').addEventListener('click', () => {
        if (!contactId.value) { alert('Select a customer'); return; }
        if (!cart.length) { alert('Add at least one product'); return; }
        if (!shipToAddress.value.trim() && contactId.value) {
            loadCustomerShipping(contactId.value, false);
        }
        shipTotal.textContent = money(cartSum());
        if (stepCustomer) stepCustomer.hidden = true;
        stepCart.hidden = true;
        stepShipping.hidden = false;
        document.body.classList.remove('sale-picking-customer');
        document.body.classList.remove('sale-building-order');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.getElementById('backToCartBtn').addEventListener('click', () => {
        stepShipping.hidden = true;
        stepCart.hidden = false;
        if (stepCustomer) stepCustomer.hidden = true;
        document.body.classList.remove('sale-picking-customer');
        document.body.classList.add('sale-building-order');
    });

    const backToCustomerBtn = document.getElementById('backToCustomerBtn');
    if (backToCustomerBtn) {
        backToCustomerBtn.addEventListener('click', () => showCustomerStep());
    }

    if (shipToSelect) {
        shipToSelect.addEventListener('change', () => {
            const id = shipToSelect.value;
            if (!id) return;
            const optAddr = lastCustomer.addresses && lastCustomer.addresses.find(a => String(a.id) === String(id));
            if (optAddr) fillShipFields(optAddr);
            else loadCustomerShipping(contactId.value, true);
        });
    }

    // SKU scan vs name search (same products API)
    let skuMode = 'scan';
    const skuModeScan = document.getElementById('skuModeScan');
    const skuModeText = document.getElementById('skuModeText');
    function setSkuMode(mode) {
        skuMode = mode;
        if (skuModeScan) {
            skuModeScan.classList.toggle('is-active', mode === 'scan');
            skuModeScan.setAttribute('aria-pressed', mode === 'scan' ? 'true' : 'false');
        }
        if (skuModeText) {
            skuModeText.classList.toggle('is-active', mode === 'text');
            skuModeText.setAttribute('aria-pressed', mode === 'text' ? 'true' : 'false');
        }
        if (productSearch) {
            productSearch.placeholder = mode === 'scan' ? 'Enter SKU' : 'Search product name / SKU';
            productSearch.focus();
        }
    }
    if (skuModeScan) skuModeScan.addEventListener('click', () => setSkuMode('scan'));
    if (skuModeText) skuModeText.addEventListener('click', () => setSkuMode('text'));

    // Catalog
    function openCatalog() {
        catalogModal.hidden = false;
        catalogStack = [{ level: 'cats', title: 'Categories' }];
        renderCatalog();
    }
    function closeCatalog() { catalogModal.hidden = true; }

    async function ensureCatalogTree() {
        if (!catalogTree) catalogTree = await fetchJson(@json(route('sale.api.categories')));
        return catalogTree;
    }

    async function renderCatalog() {
        const state = catalogStack[catalogStack.length - 1];
        catalogTitle.textContent = state.title;
        catalogBackBtn.classList.toggle('hidden', catalogStack.length <= 1);
        catalogBody.innerHTML = '<div class="px-3 py-4 text-sm text-slate-400">Loading…</div>';

        const tree = await ensureCatalogTree();
        if (state.level === 'cats') {
            if (!tree.length) {
                catalogBody.innerHTML = '<div class="px-3 py-4 text-sm text-slate-400">No categories</div>';
                return;
            }
            catalogBody.innerHTML = '';
            tree.forEach(cat => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'sale-catalog__row';
                b.innerHTML = `<span>${cat.name}</span><span class="sale-catalog__chev">›</span>`;
                b.onclick = () => {
                    const viaDept = cat.via_department ? 1 : 0;
                    if (cat.sub_categories && cat.sub_categories.length) {
                        catalogStack.push({ level: 'subs', title: cat.name, catId: cat.id, subs: cat.sub_categories, viaDept });
                    } else {
                        catalogStack.push({ level: 'products', title: cat.name, catId: cat.id, subId: 0, viaDept });
                    }
                    renderCatalog();
                };
                catalogBody.appendChild(b);
            });
            return;
        }

        if (state.level === 'subs') {
            catalogBody.innerHTML = '';
            const allBtn = document.createElement('button');
            allBtn.type = 'button';
            allBtn.className = 'sale-catalog__row sale-catalog__row--all';
            allBtn.innerHTML = `<span>All in ${state.title}</span><span class="sale-catalog__chev">›</span>`;
            allBtn.onclick = () => {
                catalogStack.push({ level: 'products', title: state.title, catId: state.catId, subId: 0, viaDept: state.viaDept || 0 });
                renderCatalog();
            };
            catalogBody.appendChild(allBtn);
            (state.subs || []).forEach(sub => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'sale-catalog__row';
                b.innerHTML = `<span>${sub.name}</span><span class="sale-catalog__chev">›</span>`;
                b.onclick = () => {
                    catalogStack.push({ level: 'products', title: sub.name, catId: state.catId, subId: sub.id, viaDept: state.viaDept || 0 });
                    renderCatalog();
                };
                catalogBody.appendChild(b);
            });
            return;
        }

        const extra = { limit: 80 };
        if (state.subId) extra.sub_category_id = state.subId;
        if (state.catId) extra.category_id = state.catId;
        if (state.viaDept) extra.via_department = 1;
        renderProductRows(await fetchJson(productApiUrl(extra)));
    }

    function renderProductRows(rows) {
        if (!rows.length) {
            catalogBody.innerHTML = '<div class="px-3 py-4 text-sm text-slate-400">No products</div>';
            return;
        }
        catalogBody.innerHTML = '';
        rows.forEach(r => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'sale-catalog__row sale-catalog__prod';
            const qty = cartQty(r.variation_id);
            b.innerHTML = `<div class="min-w-0"><div class="font-bold text-sm truncate">${r.name}</div><div class="text-xs text-slate-500">${money(r.price)} · Stock ${r.stock}</div></div><span class="sale-catalog__add">${qty || '+'}</span>`;
            b.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                addToCart(r);
                const addEl = b.querySelector('.sale-catalog__add');
                if (addEl) addEl.textContent = String(cartQty(r.variation_id) || '+');
            };
            catalogBody.appendChild(b);
        });
    }

    document.getElementById('catalogOpenBtn').addEventListener('click', openCatalog);
    document.getElementById('catalogCloseBtn').addEventListener('click', closeCatalog);
    catalogBackBtn.addEventListener('click', () => {
        if (catalogStack.length > 1) { catalogStack.pop(); renderCatalog(); }
    });
    catalogModal.addEventListener('click', (e) => { if (e.target === catalogModal) closeCatalog(); });

    form.addEventListener('submit', (e) => {
        if (!contactId.value) { e.preventDefault(); alert('Select a customer'); return; }
        if (!cart.length) { e.preventDefault(); alert('Add at least one product'); return; }
        if (!locationId || !locationId.value) { e.preventDefault(); alert('Select ship from location'); return; }
        productsJson.innerHTML = '';
        cart.forEach((line, i) => {
            ['product_id', 'variation_id', 'quantity', 'unit_price', 'transaction_sell_lines_id'].forEach(k => {
                if (k === 'transaction_sell_lines_id' && !line[k]) return;
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = `products[${i}][${k}]`;
                inp.value = line[k];
                productsJson.appendChild(inp);
            });
        });
    });

    (async function prefillFromQuery() {
        const editLines = @json($edit_lines ?? []);
        if (editLines.length) {
            editLines.forEach(r => addToCart(r, true));
            return;
        }
        const params = new URLSearchParams(window.location.search);
        const vid = params.get('add');
        if (!vid) return;
        const rows = await fetchJson(productApiUrl({ variation_id: vid }));
        if (rows[0]) addToCart(rows[0]);
    })();

    renderCart();

    const isEditOrder = @json(!empty($edit_order));
    const parkedListUrl = @json(route('sale.api.parked_sales'));
    const parkedModal = document.getElementById('parkedModal');
    const parkedBody = document.getElementById('parkedBody');
    const parkSaleBtn = document.getElementById('parkSaleBtn');
    const parkedOpenBtnCart = document.getElementById('parkedOpenBtnCart');
    const parkedOpenBtnCustomer = document.getElementById('parkedOpenBtnCustomer');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function csrfHeaders(json) {
        const h = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
        };
        if (json) h['Content-Type'] = 'application/json';
        return h;
    }

    function collectShipping() {
        return {
            ship_to_address_id: shipToSelect ? shipToSelect.value : '',
            ship_to_name: shipToName ? shipToName.value : '',
            ship_to_address: shipToAddress ? shipToAddress.value : '',
            ship_to_city: shipToCity ? shipToCity.value : '',
            ship_to_state: shipToState ? shipToState.value : '',
            ship_to_zip: shipToZip ? shipToZip.value : '',
            ship_to_phone: shipToPhone ? shipToPhone.value : '',
            ship_via_id: document.getElementById('ship_via_id')?.value || '',
            payment_term_id: paymentTermId ? paymentTermId.value : '',
            route_id: routeId ? routeId.value : '',
            ship_date: document.getElementById('ship_date')?.value || '',
            sale_note: document.getElementById('sale_note')?.value || '',
        };
    }

    function applyShipping(ship) {
        if (!ship) return;
        if (shipToSelect && ship.ship_to_address_id != null) shipToSelect.value = String(ship.ship_to_address_id || '');
        if (shipToName && ship.ship_to_name != null) shipToName.value = ship.ship_to_name;
        if (shipToAddress && ship.ship_to_address != null) shipToAddress.value = ship.ship_to_address;
        if (shipToCity && ship.ship_to_city != null) shipToCity.value = ship.ship_to_city;
        if (shipToState && ship.ship_to_state != null) shipToState.value = ship.ship_to_state;
        if (shipToZip && ship.ship_to_zip != null) shipToZip.value = ship.ship_to_zip;
        if (shipToPhone && ship.ship_to_phone != null) shipToPhone.value = ship.ship_to_phone;
        const via = document.getElementById('ship_via_id');
        if (via && ship.ship_via_id != null) via.value = String(ship.ship_via_id || '');
        if (paymentTermId && ship.payment_term_id != null) paymentTermId.value = String(ship.payment_term_id || '');
        if (routeId && ship.route_id != null) routeId.value = String(ship.route_id || '');
        const sd = document.getElementById('ship_date');
        if (sd && ship.ship_date != null) sd.value = ship.ship_date;
        const note = document.getElementById('sale_note');
        if (note && ship.sale_note != null) note.value = ship.sale_note;
        if (locationId && ship.location_id) locationId.value = String(ship.location_id);
    }

    function updateParkedChips(rows) {
        const n = Array.isArray(rows) ? rows.length : 0;
        const label = n ? ('Parked (' + n + ')') : 'Parked sales';
        [parkedOpenBtnCart, parkedOpenBtnCustomer].forEach(btn => {
            if (!btn || isEditOrder) return;
            btn.textContent = label;
            btn.classList.toggle('hidden', n === 0 && btn === parkedOpenBtnCart);
            if (btn === parkedOpenBtnCustomer) btn.classList.toggle('hidden', n === 0);
        });
    }

    async function loadParkedList() {
        if (isEditOrder) return [];
        try {
            const res = await fetch(parkedListUrl, { headers: csrfHeaders(false) });
            if (!res.ok) return [];
            const rows = await res.json();
            updateParkedChips(rows);
            return Array.isArray(rows) ? rows : [];
        } catch (e) {
            return [];
        }
    }

    async function openParkedModal() {
        if (!parkedModal || isEditOrder) return;
        parkedModal.hidden = false;
        parkedBody.innerHTML = '<div class="px-3 py-4 text-sm text-slate-400">Loading…</div>';
        const rows = await loadParkedList();
        if (!rows.length) {
            parkedBody.innerHTML = '<div class="px-3 py-4 text-sm text-slate-400">No parked sales</div>';
            return;
        }
        parkedBody.innerHTML = '';
        rows.forEach(r => {
            const row = document.createElement('div');
            row.className = 'sale-parked-row';
            row.innerHTML = `
                <button type="button" class="sale-parked-row__main" data-recall="${r.id}">
                    <div class="font-bold text-sm truncate">${escapeHtml(r.customer_label || 'Customer')}</div>
                    <div class="text-xs text-slate-500">${r.line_count} item(s) · ${money(r.total)}</div>
                </button>
                <button type="button" class="sale-parked-row__del" data-discard="${r.id}" aria-label="Discard">×</button>`;
            parkedBody.appendChild(row);
        });
        parkedBody.querySelectorAll('[data-recall]').forEach(b => b.onclick = () => recallParked(+b.dataset.recall));
        parkedBody.querySelectorAll('[data-discard]').forEach(b => b.onclick = () => discardParked(+b.dataset.discard));
    }

    function closeParkedModal() {
        if (parkedModal) parkedModal.hidden = true;
    }

    async function parkCurrentSale() {
        if (isEditOrder) return;
        if (!contactId.value) { alert('Select a customer first'); return; }
        if (!cart.length) { alert('Add at least one product before parking'); return; }
        const res = await fetch(parkedListUrl, {
            method: 'POST',
            headers: csrfHeaders(true),
            body: JSON.stringify({
                customer_id: Number(contactId.value),
                customer_label: (document.getElementById('orderCustomerName') || {}).textContent || '',
                location_id: locationId && locationId.value ? Number(locationId.value) : null,
                lines: cart.map(l => ({
                    product_id: l.product_id,
                    variation_id: l.variation_id,
                    name: l.name,
                    unit_price: l.unit_price,
                    quantity: l.quantity,
                    allow_decimal: l.allow_decimal ? 1 : 0,
                    enable_stock: l.enable_stock,
                    product_type: l.product_type,
                })),
                shipping: collectShipping(),
            }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Could not park this sale');
            return;
        }
        cart.splice(0, cart.length);
        renderCart();
        setCustomer('', '', '', false);
        showCustomerStep();
        showAddedMsg();
        const el = document.getElementById('saleAddedMsg');
        if (el) el.textContent = 'Sale parked';
        await loadParkedList();
    }

    async function recallParked(id) {
        const res = await fetch(parkedListUrl + '/' + id, { headers: csrfHeaders(false) });
        if (!res.ok) { alert('Could not open parked sale'); return; }
        const data = await res.json();
        const payload = data.payload || {};
        const lines = payload.lines || [];
        if (payload.customer_id) {
            setCustomer(payload.customer_id, payload.customer_label || data.customer_label || '', '', true);
        }
        cart.splice(0, cart.length);
        lines.forEach(l => addToCart({
            product_id: l.product_id,
            variation_id: l.variation_id,
            name: l.name,
            price: l.unit_price,
            quantity: l.quantity,
            allow_decimal: l.allow_decimal,
            enable_stock: l.enable_stock,
            product_type: l.product_type,
        }, true, true));
        applyShipping(Object.assign({}, payload.shipping || {}, { location_id: payload.location_id }));
        renderCart();
        closeParkedModal();
        showCartStep();
        await fetch(parkedListUrl + '/' + id, { method: 'DELETE', headers: csrfHeaders(false) });
        await loadParkedList();
    }

    async function discardParked(id) {
        if (!confirm('Discard this parked sale?')) return;
        await fetch(parkedListUrl + '/' + id, { method: 'DELETE', headers: csrfHeaders(false) });
        await openParkedModal();
        await loadParkedList();
    }

    if (parkSaleBtn) parkSaleBtn.addEventListener('click', parkCurrentSale);
    if (parkedOpenBtnCart) parkedOpenBtnCart.addEventListener('click', openParkedModal);
    if (parkedOpenBtnCustomer) parkedOpenBtnCustomer.addEventListener('click', openParkedModal);
    const parkedCloseBtn = document.getElementById('parkedCloseBtn');
    if (parkedCloseBtn) parkedCloseBtn.addEventListener('click', closeParkedModal);
    if (parkedModal) parkedModal.addEventListener('click', (e) => { if (e.target === parkedModal) closeParkedModal(); });
    loadParkedList();

    async function addFromScanCode(code) {
        const q = String(code || '').trim();
        if (!q) return false;
        const rows = await fetchJson(productApiUrl({ q: q, scan: 1 }));
        if (!rows.length) {
            const status = document.getElementById('saleScanStatus');
            if (status) status.textContent = 'No item for ' + q;
            return false;
        }
        addToCart(rows[0]);
        const status = document.getElementById('saleScanStatus');
        if (status) status.textContent = 'Added ' + (rows[0].name || q);
        return true;
    }

    const scanOverlay = document.getElementById('saleScanOverlay');
    const scanVideo = document.getElementById('saleScanVideo');
    const scanRegion = document.getElementById('saleScanRegion');
    const skuCameraBtn = document.getElementById('skuCameraBtn');
    let scanStop = null;
    let lastScanCode = '';
    let lastScanAt = 0;
    let html5Qr = null;

    function loadScriptOnce(src, flag) {
        return new Promise((resolve, reject) => {
            if (window[flag]) return resolve();
            const existing = document.querySelector('script[data-sale-scan="1"]');
            if (existing) {
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', reject);
                return;
            }
            const s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.dataset.saleScan = '1';
            s.onload = () => resolve();
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    async function onDecodedBarcode(text) {
        const code = String(text || '').trim();
        const now = Date.now();
        if (!code) return;
        if (code === lastScanCode && (now - lastScanAt) < 1600) return;
        lastScanCode = code;
        lastScanAt = now;
        try { navigator.vibrate && navigator.vibrate(40); } catch (e) {}
        await addFromScanCode(code);
    }

    async function startNativeBarcode() {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices) return false;
        const Detector = window.BarcodeDetector;
        let formats = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39'];
        try {
            if (Detector.getSupportedFormats) {
                const supported = await Detector.getSupportedFormats();
                formats = formats.filter(f => supported.includes(f));
            }
        } catch (e) {}
        const detector = new Detector({ formats });
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        scanVideo.hidden = false;
        scanRegion.hidden = true;
        scanVideo.srcObject = stream;
        await scanVideo.play();
        let alive = true;
        const tick = async () => {
            if (!alive) return;
            try {
                const codes = await detector.detect(scanVideo);
                if (codes && codes[0] && codes[0].rawValue) await onDecodedBarcode(codes[0].rawValue);
            } catch (e) {}
            if (alive) requestAnimationFrame(tick);
        };
        tick();
        scanStop = () => {
            alive = false;
            stream.getTracks().forEach(t => t.stop());
            scanVideo.srcObject = null;
            scanVideo.hidden = true;
            scanRegion.hidden = false;
        };
        return true;
    }

    async function startHtml5Scan() {
        await loadScriptOnce('https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js', 'Html5Qrcode');
        if (!window.Html5Qrcode) throw new Error('Scanner library failed to load');
        scanVideo.hidden = true;
        scanRegion.hidden = false;
        scanRegion.innerHTML = '';
        html5Qr = new window.Html5Qrcode('saleScanRegion');
        const formats = window.Html5QrcodeSupportedFormats ? [
            window.Html5QrcodeSupportedFormats.EAN_13,
            window.Html5QrcodeSupportedFormats.EAN_8,
            window.Html5QrcodeSupportedFormats.UPC_A,
            window.Html5QrcodeSupportedFormats.UPC_E,
            window.Html5QrcodeSupportedFormats.CODE_128,
            window.Html5QrcodeSupportedFormats.CODE_39,
        ] : undefined;
        await html5Qr.start(
            { facingMode: 'environment' },
            { fps: 8, qrbox: { width: 280, height: 140 }, formatsToSupport: formats },
            (txt) => { onDecodedBarcode(txt); },
            () => {}
        );
        scanStop = async () => {
            try { if (html5Qr) await html5Qr.stop(); } catch (e) {}
            try { if (html5Qr) await html5Qr.clear(); } catch (e) {}
            html5Qr = null;
        };
    }

    async function openCameraScan() {
        if (!scanOverlay) return;
        if (skuMode !== 'scan') setSkuMode('scan');
        scanOverlay.hidden = false;
        const status = document.getElementById('saleScanStatus');
        if (status) status.textContent = 'Starting camera…';
        try {
            let started = false;
            try {
                started = await startNativeBarcode();
            } catch (e) {
                started = false;
            }
            if (!started) await startHtml5Scan();
            if (status) status.textContent = 'Ready — scan a barcode';
        } catch (err) {
            if (status) status.textContent = 'Camera unavailable. Allow camera permission, or type the SKU.';
        }
    }

    async function closeCameraScan() {
        if (scanStop) {
            try { await scanStop(); } catch (e) {}
            scanStop = null;
        }
        if (scanOverlay) scanOverlay.hidden = true;
    }

    if (skuCameraBtn) skuCameraBtn.addEventListener('click', openCameraScan);
    const saleScanCloseBtn = document.getElementById('saleScanCloseBtn');
    if (saleScanCloseBtn) saleScanCloseBtn.addEventListener('click', closeCameraScan);

})();
</script>
@endpush
