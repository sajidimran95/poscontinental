@extends('sale.layout')
@section('title', !empty($edit_order) ? 'Edit order' : 'Create order')
@section('header', !empty($edit_order) ? 'Edit order' : 'Create order')
@section('content')
<style>
body.sale-page-create { background: #F6F5F1; }
body.sale-building-order .sale-order-build,
body.sale-building-order .sale-order-build__body,
body.sale-building-order .sale-main-app,
body.sale-building-order .sale-page { background: #F6F5F1 !important; }
.pp-topbar {
    background: #0E3F34; color: #fff;
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px; flex-shrink: 0;
}
.pp-topbar__left, .pp-topbar__right { display: flex; align-items: center; gap: 12px; }
.pp-sync {
    width: 32px; height: 32px; border-radius: 50%; border: 0;
    background: rgba(255,255,255,.12); color: #fff; font-size: 15px; cursor: pointer;
}
.pp-biz { font-weight: 800; font-size: 16px; }
.pp-bizsub { font-size: 10.5px; color: rgba(255,255,255,.6); font-weight: 500; }
.pp-sales { font-size: 12.5px; font-weight: 600; }
.pp-avatar {
    width: 32px; height: 32px; border-radius: 50%; background: #D98A2B; color: #0E3F34;
    display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px;
}
body.sale-page-create .pp-toolbar { padding: 0; gap: 8px; }
body.sale-page-create .pp-search {
    flex: 1; display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #E3E1DA; border-radius: 10px; padding: 9px 13px; min-width: 0;
}
body.sale-page-create .pp-search input {
    border: 0; outline: 0; background: transparent; flex: 1; min-width: 0; font-size: 13.5px; color: #17221E;
}
body.sale-page-create .pp-icon {
    width: 38px; height: 38px; border-radius: 10px; border: 1px solid #E3E1DA; background: #fff;
    display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;
    color: #17221E;
}
body.sale-page-create .pp-icon.on { background: #175C4C; border-color: #175C4C; color: #fff; }
body.sale-page-create .pp-icon svg { display: block; }
body.sale-page-create .pp-sort {
    display: flex; align-items: center; gap: 6px; height: 38px; padding: 0 12px;
    border-radius: 10px; border: 1px solid #E3E1DA; background: #fff;
    font-size: 12.5px; font-weight: 600; color: #17221E; cursor: pointer;
}
body.sale-page-create .sale-ois-chips.pp-chips {
    display: flex; gap: 8px; overflow-x: auto; flex-shrink: 0; min-width: 0; width: 100%;
    padding: 2px 0 8px; scrollbar-width: thin; scrollbar-color: #175C4C #E3E1DA;
}
body.sale-page-create .sale-ois-chip {
    background: #fff; border: 1px solid #E3E1DA; color: #56645F; border-radius: 999px;
    padding: 6px 13px; font-size: 12px; font-weight: 600; flex-shrink: 0;
}
body.sale-page-create .sale-ois-chip.is-on { background: #0E3F34; border-color: #0E3F34; color: #fff; }
body.sale-page-create .pp-cart {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    background: #0E3F34; color: #fff; border: 0; border-radius: 12px;
    padding: 11px 15px; width: 100% !important; min-width: 0 !important;
    cursor: pointer; text-align: left; box-sizing: border-box;
}
body.sale-page-create .pp-cart.is-off { opacity: .55; }
body.sale-page-create .pp-cart-left { font-size: 12.5px; font-weight: 700; flex: 1 1 auto; min-width: 0; }
body.sale-page-create .pp-cart-left span { color: rgba(255,255,255,.65); font-weight: 500; }
body.sale-page-create .pp-cart-total { font-size: 15px; font-weight: 800; font-family: ui-monospace, monospace; color: #fff; }
body.sale-page-create .pp-cart-go { display: flex !important; align-items: center; gap: 10px; flex: 0 0 auto; }
body.sale-page-create .pp-cart-cta {
    background: #fff; color: #0E3F34; border-radius: 999px;
    padding: 6px 12px; font-size: 12.5px; font-weight: 800;
    white-space: nowrap;
}
body.sale-page-create .pp-price-input {
    width: 88px; max-width: 100%;
    border: 1px solid #C9E6DE; border-radius: 8px;
    padding: 4px 8px; font-size: 15px; font-weight: 800; color: #0E3F34;
    background: #F3FAF7; font-family: ui-monospace, monospace;
}
body.sale-page-create #goShippingBtn.pp-cart { flex-shrink: 0; width: 100% !important; min-width: 0 !important; }
body.sale-page-create .sale-cart-lines { background: transparent; }
body.sale-page-create .sale-cart-lines.is-view-list { grid-template-columns: 1fr !important; }
body.sale-page-create .sale-order-build__body { padding: 12px 20px 10px; gap: 8px; }
@media (max-width: 720px) {
    .pp-sales, .pp-sort-label { display: none; }
}
.sale-co-card.sale-checkout__fields { padding: 14px; }
#coShipOther { display: flex; flex-direction: column; gap: 8px; }
#coShipOther[hidden], #coLastWrap[hidden] { display: none !important; }
</style>
<form method="POST" action="{{ !empty($edit_order) ? route('sale.orders.update', $edit_order->id) : route('sale.orders.store') }}" id="saleOrderForm" class="sale-create-form">
    @csrf
    @if(!empty($edit_order))
        @method('PUT')
    @endif
    <input type="hidden" name="contact_id" id="contact_id" value="{{ old('contact_id', $default_customer['id'] ?? '') }}" required>
    <input type="hidden" name="location_id" id="location_id" value="{{ old('location_id', $default_location) }}" required>
    <input type="hidden" name="shipping_status" value="ordered">
    {{-- Existing project modes: New Order = sales_order, Estimate = quotation, Back Order = sales_order --}}
    <input type="hidden" name="order_mode" id="order_mode" value="{{ old('order_mode', 'new_order') }}">
    <div id="productsJson" class="hidden"></div>

    {{-- Customer is chosen from Customers → Create Order --}}
    <div id="stepCart" class="sale-order-build">
        <div class="pp-topbar">
            <div class="pp-topbar__left">
                <button type="button" id="backToCustomerBtn" class="pp-sync" aria-label="Back">&#8635;</button>
                <div>
                    <div class="pp-biz">{{ $companyName ?? '' }}</div>
                    <div class="pp-bizsub">Synced</div>
                </div>
            </div>
            <div class="pp-topbar__right">
                <span class="hidden" id="orderCustomerName">{{ $default_customer['text'] ?? ($edit_order->contact->supplier_business_name ?? $edit_order->contact->name ?? '') }}</span>
                @if(empty($edit_order))
                    <button type="button" class="pp-cart-cta hidden" id="parkedOpenBtn" style="border:0;cursor:pointer">Parked</button>
                @endif
                <span class="pp-sales">{{ $userName ?? '' }}</span>
                <span class="pp-avatar">{{ strtoupper(mb_substr((string) ($userName ?? ''), 0, 1)) }}</span>
                <button type="button" class="hidden" id="orderMoreBtn" aria-label="More"></button>
            </div>
        </div>

        <div class="sale-order-build__body">
            <div class="sale-ois-toolbar pp-toolbar">
                <div class="sale-ois-search pp-search">
                    <span>&#128269;</span>
                    <input type="search" id="productSearch" placeholder="Search products or scan barcode" autocomplete="off">
                </div>
                <button type="button" class="pp-icon" id="orderScanBtn" aria-label="Scan barcode" title="Scan barcode">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 7V5a2 2 0 0 1 2-2h2"/>
                        <path d="M17 3h2a2 2 0 0 1 2 2v2"/>
                        <path d="M21 17v2a2 2 0 0 1-2 2h-2"/>
                        <path d="M7 21H5a2 2 0 0 1-2-2v-2"/>
                        <path d="M7 8v8M11 8v8M14 8v8M17 8v8"/>
                    </svg>
                </button>
                <button type="button" class="pp-icon on" id="orderGridBtn" aria-label="Grid view">&#9638;&#9638;</button>
                <button type="button" class="pp-icon" id="orderListBtn" aria-label="List view">&#9776;</button>
                <button type="button" class="pp-sort" id="orderSortBtn" aria-label="Sort" onclick="return saleOpenSheet('orderSortSheet', event)"><span class="pp-sort-label">Sort</span> &#8645;</button>
                <button type="button" class="hidden" id="orderViewBtn" aria-label="View"></button>
                <button type="button" class="hidden" id="orderFilterBtn" aria-label="Filter"></button>
            </div>
            <div class="sale-ois-chips pp-chips" id="orderCatChips" aria-label="Categories">
                <button type="button" class="sale-ois-chip pp-chip is-on on" data-cat="">All Items</button>
            </div>
            <div id="orderResultCount" class="hidden">Results: 0</div>
            <div id="productResults" class="sale-prod-results hidden"></div>
            <button type="button" id="skuModeScan" class="hidden" tabindex="-1"></button>
            <input type="checkbox" id="useLastQtyToggle" class="hidden" tabindex="-1">
            <div id="lastQtyStatus" hidden></div>
            <div class="sale-cart-scroll" id="cartScroll">
                <div id="cartEmpty" class="sale-cart-empty hidden"></div>
                <div id="cartLines" class="sale-cart-lines is-view-item"></div>
            </div>

            <button type="button" class="pp-cart" id="goShippingBtn">
                <div class="pp-cart-left" id="cartCountLabel">0 items <span>· 0 products</span></div>
                <div class="pp-cart-go">
                    <span class="pp-cart-total" id="cartTotal">$0.00</span>
                    <span class="pp-cart-cta">Check out</span>
                </div>
            </button>
        </div>

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

    <div id="stepCheckout" class="sale-checkout" hidden>
        <div class="sale-checkout__bar">
            <button type="button" id="backFromCheckoutBtn" class="sale-order-build__iconbtn" aria-label="Close">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
            <div class="sale-checkout__title">Order Check Out</div>
            <button type="button" class="sale-order-build__iconbtn" id="checkoutMoreBtn" aria-label="More">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg>
            </button>
        </div>
        <div class="sale-checkout__body">
            <div class="sale-co-card">
                <div class="sale-checkout__name" id="coName"></div>
                <div class="sale-checkout__acct" id="coAcct"></div>
                <div class="sale-checkout__addr" id="coAddr"></div>
            </div>
            <div class="sale-checkout__stats">
                <div class="sale-co-stat"><strong id="coLimit">$0.00</strong><span>Limit</span></div>
                <div class="sale-co-stat"><strong id="coLast">$0.00</strong><span>Last order</span></div>
                <div class="sale-co-stat"><strong id="coOpen">$0.00</strong><span>Balance</span></div>
            </div>
            <div class="sale-checkout__meta">
                <div>Order # <b id="coOrderNo">{{ $checkout['order_number'] ?? '' }}</b></div>
                <div>Date <b id="coDate">{{ !empty($edit_order) && $edit_order->order_date ? $edit_order->order_date->format('m/d/Y') : now()->format('m/d/Y') }}</b></div>
                <div>Terms <b id="coTerms">—</b></div>
                <div id="coLastWrap" @if(empty($default_customer['last_order_date'])) hidden @endif>Last order <b id="coLastDate">{{ $default_customer['last_order_date'] ?? '' }}</b></div>
            </div>
            <div class="sale-co-card">
                <div class="sale-checkout__lines-title">Order summary</div>
                <div id="coLines" class="sale-checkout__lines"></div>
                <div id="coTotals" class="sale-checkout__totals" hidden>
                    <div class="sale-checkout__tot"><span>Subtotal</span><strong id="coSubtotal">$0.00</strong></div>
                    <div class="sale-checkout__tot is-disc" id="coDiscRow" hidden><span>Item discount</span><strong id="coDisc">−$0.00</strong></div>
                    <div class="sale-checkout__tot is-total"><span>Total</span><strong id="coTotalInline">$0.00</strong></div>
                </div>
            </div>
            @php
                $co = $checkout ?? [];
                $coShipTo = (int) old('ship_to_address_id', $co['ship_to_address_id'] ?? 0);
                $coShipAddrs = $default_customer['shipping_addresses'] ?? [];
                $coOther = $co['ship_to'] ?? [];
            @endphp
            <div class="sale-co-card sale-checkout__fields">
                <div class="sale-checkout__lines-title !mb-0">Ship To</div>
                <label>Ship-to address
                    <select name="ship_to_address_id" id="ship_to_address_id" class="sale-input">
                        <option value="0" @selected($coShipTo === 0)>Billing address</option>
                        @foreach($coShipAddrs as $sa)
                            <option value="{{ $sa['id'] }}" @selected($coShipTo === (int) $sa['id'])>{{ $sa['name'] }}{{ !empty($sa['is_primary']) ? ' (Primary)' : '' }}</option>
                        @endforeach
                        <option value="-2" @selected($coShipTo === -2)>Other address…</option>
                    </select>
                </label>
                <div id="coShipPreview" class="text-[13px] font-semibold text-slate-600 leading-snug -mt-1"></div>
                <div id="coShipOther" @if($coShipTo !== -2) hidden @endif>
                    <input type="text" name="ship_to_name" class="sale-input" placeholder="Ship-to name" value="{{ old('ship_to_name', $coOther['name'] ?? '') }}">
                    <input type="text" name="ship_to_address" class="sale-input" placeholder="Street address" value="{{ old('ship_to_address', $coOther['address'] ?? '') }}">
                    <div class="grid grid-cols-3 gap-2">
                        <input type="text" name="ship_to_city" class="sale-input" placeholder="City" value="{{ old('ship_to_city', $coOther['city'] ?? '') }}">
                        <input type="text" name="ship_to_state" class="sale-input" placeholder="State" value="{{ old('ship_to_state', $coOther['state'] ?? '') }}">
                        <input type="text" name="ship_to_zip" class="sale-input" placeholder="Zip" value="{{ old('ship_to_zip', $coOther['zip'] ?? '') }}">
                    </div>
                    <input type="tel" name="ship_to_phone" class="sale-input" placeholder="Phone" value="{{ old('ship_to_phone', $coOther['phone'] ?? '') }}">
                </div>
                <input type="hidden" name="shipping_address" id="shipping_address" value="{{ old('shipping_address', $edit_order->shipping_address ?? ($default_customer['address'] ?? $default_customer['shipping_address'] ?? '')) }}">
            </div>
            <div class="sale-co-card sale-checkout__fields">
                <div class="sale-checkout__lines-title !mb-0">Order details</div>
                <div class="grid grid-cols-2 gap-3">
                    <label>Terms
                        <select name="payment_term_id" id="payment_term_id" class="sale-input">
                            <option value="">—</option>
                            @foreach(($payment_terms ?? []) as $pt)
                                <option value="{{ $pt->id }}" @selected((int) old('payment_term_id', $co['payment_term_id'] ?? 0) === (int) $pt->id)>{{ $pt->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Ship Via
                        <select name="ship_via_id" id="ship_via_id" class="sale-input">
                            <option value="">—</option>
                            @foreach(($ship_vias ?? []) as $sv)
                                <option value="{{ $sv->id }}" @selected((int) old('ship_via_id', $co['ship_via_id'] ?? 0) === (int) $sv->id)>{{ $sv->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Route
                        <select name="route_id" id="route_id" class="sale-input">
                            <option value="">—</option>
                            @foreach(($routes ?? []) as $rt)
                                <option value="{{ $rt->id }}" @selected((int) old('route_id', $co['route_id'] ?? 0) === (int) $rt->id)>{{ $rt->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Ship date
                        <input type="date" name="ship_date" id="ship_date" class="sale-input" value="{{ old('ship_date', $co['ship_date'] ?? '') }}">
                    </label>
                    @if(count($locations ?? []) > 1)
                        <label>Warehouse
                            <select id="coLocation" class="sale-input">
                                @foreach($locations as $locId => $locName)
                                    <option value="{{ $locId }}" @selected((int) $default_location === (int) $locId)>{{ $locName }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </div>
                <label>Comments
                    <textarea name="sale_note" id="sale_note" rows="2" class="sale-input" placeholder="Order comments">{{ old('sale_note', $edit_order->additional_notes ?? '') }}</textarea>
                </label>
            </div>
        </div>
        <div class="sale-order-build__total">
            <div>
                <div class="sale-order-build__total-label">TOTAL</div>
                <strong id="coTotal" class="tabular-nums">$0.00</strong>
            </div>
            <button type="button" class="sale-order-build__checkout" id="placeOrderBtn">Place Order</button>
        </div>
    </div>

    {{-- Shipping / notes live on checkout summary --}}
    <span id="shipTotal" class="hidden"></span>
    <button type="submit" class="hidden" id="submitOrderBtn">{{ !empty($edit_order) ? 'Save order' : 'Create order' }}</button>
</form>
@endsection

@push('scripts')
<div id="saleScanScreen" class="sale-scan" hidden>
    <div class="sale-scan__bar">
        <button type="button" class="sale-scan__back" id="saleScanBack" aria-label="Back">‹</button>
        <div class="sale-scan__title">Scan your barcode</div>
        <button type="button" class="sale-scan__torch" id="saleScanTorchBtn" hidden title="Light">Light</button>
    </div>
    <div class="sale-scan__cam">
        <div id="saleScanReader"></div>
        <div class="sale-scan__laser" aria-hidden="true"></div>
    </div>
    <div class="sale-scan__status" id="saleScanStatus"></div>
    <div class="sale-scan__guide" aria-hidden="true">
        <div class="sale-scan__guide-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 8V6h3M17 6h3v2M20 16v2h-3M7 18H4v-2"/><path d="M7 9h10M7 12h10M7 15h6"/></svg>
        </div>
        <span class="sale-scan__chev">›</span>
        <div class="sale-scan__guide-ico is-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="6" width="14" height="12" rx="1"/><path d="M5 12h14" stroke="#e53935"/></svg>
        </div>
        <span class="sale-scan__chev">›</span>
        <div class="sale-scan__guide-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="6" y="4" width="12" height="16" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
        </div>
    </div>
    <div class="sale-scan__empty" id="saleScanEmpty">Your scanned products will be displayed here.</div>
    <div class="sale-scan__list" id="saleScanList" hidden></div>
    <div class="sale-order-build__total">
        <div>
            <div class="sale-order-build__total-label">TOTAL</div>
            <strong id="saleScanTotal">$0.00</strong>
        </div>
        <button type="button" class="sale-order-build__checkout is-off" id="saleScanCheckout">Check Out</button>
    </div>
    <div id="saleScanMiss" class="sale-scan-miss" hidden>
        <div class="sale-scan-miss__card">
            <p class="sale-scan-miss__title">Item not found</p>
            <p class="sale-scan-miss__text" id="saleScanMissText">This barcode is not in the system.</p>
            <button type="button" class="sale-scan-miss__ok" id="saleScanMissOk">OK — scan next</button>
        </div>
    </div>
</div>
<div id="salePriceUpdateAlert" class="sale-scan-miss sale-price-alert" hidden>
    <div class="sale-scan-miss__card">
        <p class="sale-scan-miss__title" id="salePriceUpdateAlertTitle">Sales price updated</p>
        <p class="sale-scan-miss__text" id="salePriceUpdateAlertText"></p>
        <button type="button" class="sale-scan-miss__ok" id="salePriceUpdateAlertOk">OK</button>
    </div>
</div>
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
<div id="orderHistorySheet" class="sale-sheet sale-ohist" hidden>
    <div class="sale-sheet__panel sale-ohist__panel">
        <div class="sale-ohist__head">
            <div class="sale-ohist__head-text">
                <div class="sale-ohist__name" id="orderHistoryTitle">Product</div>
                <div class="sale-ohist__sub">Order history</div>
            </div>
            <button type="button" class="sale-ohist__close" onclick="saleCloseSheet('orderHistorySheet')" aria-label="Close">×</button>
        </div>
        <div class="sale-ohist__body" id="orderHistoryBody"></div>
    </div>
</div>
<div id="parkedSheet" class="sale-sheet" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__head is-center">
            <div class="sale-sheet__title">Parked sales</div>
            <button type="button" class="sale-sheet__close" onclick="saleCloseSheet('parkedSheet')" aria-label="Close">×</button>
        </div>
        <div class="sale-sheet__body !pt-1" id="parkedBody"></div>
        <div class="sale-sheet__foot">
            <button type="button" class="sale-more-cancel" onclick="saleCloseSheet('parkedSheet')">CANCEL</button>
        </div>
    </div>
</div>
<div id="orderViewSheet" class="sale-sheet" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__head is-center">
            <div class="sale-sheet__title">View</div>
            <button type="button" class="sale-sheet__close" id="orderViewClose" onclick="return saleCloseSheet('orderViewSheet')" aria-label="Close">×</button>
        </div>
        <div class="sale-sheet__body sale-view-sheet">
            <button type="button" class="sale-view-opt is-on" data-view="item">Item</button>
            <button type="button" class="sale-view-opt" data-view="large">Large</button>
            <button type="button" class="sale-view-opt" data-view="medium">Medium</button>
            <button type="button" class="sale-view-opt" data-view="details">Details</button>
            <button type="button" class="sale-view-opt" data-view="list">List</button>
        </div>
        <div class="sale-sheet__foot">
            <button type="button" class="sale-more-cancel" id="orderViewCancel" onclick="return saleCloseSheet('orderViewSheet')">CANCEL</button>
        </div>
    </div>
</div>
<div id="orderSortSheet" class="sale-sheet" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__head is-center">
            <div class="sale-sheet__title">Sort</div>
            <button type="button" class="sale-sheet__close" onclick="saleCloseSheet('orderSortSheet')" aria-label="Close">×</button>
        </div>
        <div class="sale-sheet__body sale-view-sheet">
            <button type="button" class="sale-view-opt is-on" data-sort="name_asc">Name A–Z</button>
            <button type="button" class="sale-view-opt" data-sort="name_desc">Name Z–A</button>
            <button type="button" class="sale-view-opt" data-sort="price_asc">Price: low to high</button>
            <button type="button" class="sale-view-opt" data-sort="price_desc">Price: high to low</button>
            <button type="button" class="sale-view-opt" data-sort="stock">In stock first</button>
        </div>
        <div class="sale-sheet__foot">
            <button type="button" class="sale-more-cancel" onclick="saleCloseSheet('orderSortSheet')">CANCEL</button>
        </div>
    </div>
</div>
<div id="orderFilterSheet" class="sale-sheet" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__head is-center">
            <div class="sale-sheet__title">Filter</div>
            <button type="button" class="sale-sheet__close" onclick="saleCloseSheet('orderFilterSheet')" aria-label="Close">×</button>
        </div>
        <div class="sale-sheet__body" style="padding: 8px 16px 16px">
            <div class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Category</div>
            <select id="orderFilterCat" class="sale-input mb-4">
                <option value="">All categories</option>
            </select>
            <div class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Subcategory</div>
            <select id="orderFilterSub" class="sale-input" disabled>
                <option value="">All</option>
            </select>
        </div>
        <div class="sale-sheet__foot" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 16px">
            <button type="button" class="sale-btn-ghost !w-auto !px-4" id="orderFilterReset">Reset</button>
            <button type="button" class="sale-btn !w-auto !px-6" id="orderFilterApply">Apply</button>
        </div>
    </div>
</div>
<div id="orderMoreSheet" class="sale-sheet" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__head is-center">
            <div class="sale-sheet__title">More</div>
            <button type="button" class="sale-sheet__close" id="orderMoreClose" aria-label="Close">×</button>
        </div>
        <div class="sale-sheet__body !pt-1">
            <button type="button" class="sale-act-row" id="orderMoreScan">
                <svg viewBox="0 0 24 24"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/><path d="M12 9v1"/></svg>
                Scan
            </button>
            <button type="button" class="sale-act-row" id="orderMoreDocs">
                <svg viewBox="0 0 24 24"><path d="M4 20h16a1 1 0 0 0 1-1V8l-5-5H5a1 1 0 0 0-1 1v15a1 1 0 0 0 1 1z"/><path d="M15 3v5h5"/></svg>
                Documents
            </button>
        </div>
        <div class="sale-sheet__foot">
            <button type="button" class="sale-more-cancel" id="orderMoreCancel">CANCEL</button>
        </div>
    </div>
</div>
<div id="checkoutMoreSheet" class="sale-sheet sale-sheet--dark" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__head is-center">
            <div class="sale-sheet__title">Options</div>
            <button type="button" class="sale-sheet__close" id="checkoutMoreClose" aria-label="Close">×</button>
        </div>
        <div class="sale-sheet__body !pt-1">
            <a class="sale-act-row" id="coHistory" href="{{ route('sale.orders') }}">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></svg>
                History
            </a>
            <button type="button" class="sale-act-row" id="coShare">
                <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
                Share
            </button>
            <button type="button" class="sale-act-row" id="coPrint">
                <svg viewBox="0 0 24 24"><path d="M6 9V3h12v6"/><rect x="4" y="9" width="16" height="8" rx="1"/><path d="M6 17h12v4H6z"/></svg>
                Print
            </button>
            <button type="button" class="sale-act-row" id="coDocs">
                <svg viewBox="0 0 24 24"><path d="M4 20h16a1 1 0 0 0 1-1V8l-5-5H5a1 1 0 0 0-1 1v15a1 1 0 0 0 1 1z"/><path d="M15 3v5h5"/></svg>
                Documents
            </button>
        </div>
        <div class="sale-sheet__foot">
            <button type="button" class="sale-more-cancel" id="checkoutMoreCancel">CANCEL</button>
        </div>
    </div>
</div>
<div id="orderCreditSheet" class="sale-sheet sale-credit-modal" hidden>
    <div class="sale-credit-modal__panel">
        <div class="sale-credit-modal__icon" aria-hidden="true">!</div>
        <div class="sale-credit-modal__title">Warning!!</div>
        <p class="sale-credit-modal__msg" id="orderCreditMsg">Customer has credit limit 0</p>
        <button type="button" class="sale-credit-modal__accept" id="orderCreditAccept">Accept</button>
    </div>
</div>
<div id="saleAttnSheet" class="sale-sheet sale-credit-modal sale-attn-modal" hidden>
    <div class="sale-attn-panel">
        <div class="sale-attn-ico">!</div>
        <div class="sale-attn-title">Attention!</div>
        <p class="sale-attn-msg" id="saleAttnMsg">Do you want to save this order?</p>
        <div class="sale-attn-btns">
            <button type="button" class="sale-attn-no" id="saleAttnNo">NO</button>
            <button type="button" class="sale-attn-yes" id="saleAttnYes">YES</button>
        </div>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('.sale-sheet').forEach(function (el) {
        document.body.appendChild(el);
    });
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
    const VIEW_MODES = ['item', 'large', 'medium', 'details', 'list'];
    window.__saleProductView = 'item';
    function applyProductView(mode, closeSheet) {
        const lines = document.getElementById('cartLines');
        const step = document.getElementById('stepCart');
        if (!VIEW_MODES.includes(mode)) mode = 'item';
        window.__saleProductView = mode;
        if (lines) {
            lines.classList.remove('is-view-item', 'is-view-large', 'is-view-medium', 'is-view-details', 'is-view-list');
            lines.classList.add('is-view-' + mode);
        }
        if (step) step.setAttribute('data-product-view', mode);
        try { localStorage.setItem('saleProductView', mode); } catch (e) {}
        document.querySelectorAll('.sale-view-opt').forEach((btn) => {
            btn.classList.toggle('is-on', btn.getAttribute('data-view') === mode);
        });
        if (closeSheet !== false) {
            const sheet = document.getElementById('orderViewSheet');
            if (sheet) {
                sheet.hidden = true;
                sheet.classList.remove('is-open');
            }
        }
    }
    window.applyProductView = applyProductView;
    document.getElementById('orderGridBtn')?.addEventListener('click', () => {
        applyProductView('item', false);
        document.getElementById('orderGridBtn').classList.add('on');
        document.getElementById('orderListBtn').classList.remove('on');
    });
    document.getElementById('orderListBtn')?.addEventListener('click', () => {
        applyProductView('list', false);
        document.getElementById('orderListBtn').classList.add('on');
        document.getElementById('orderGridBtn').classList.remove('on');
    });
    document.getElementById('orderScanBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        startSaleScan();
    });
    document.getElementById('orderViewBtn')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const sheet = document.getElementById('orderViewSheet');
        if (!sheet) return;
        document.body.appendChild(sheet);
        sheet.hidden = false;
    }, true);
    const cartEmpty = document.getElementById('cartEmpty');
    const cartTotal = document.getElementById('cartTotal');
    const shipTotal = document.getElementById('shipTotal');
    const locationId = document.getElementById('location_id');
    const shippingAddress = document.getElementById('shipping_address');
    const form = document.getElementById('saleOrderForm');
    const productsJson = document.getElementById('productsJson');
    const stepCart = document.getElementById('stepCart');
    const stepShipping = document.getElementById('stepShipping');
    const stepCheckout = document.getElementById('stepCheckout');
    let custTimer = null, prodTimer = null;
    let savedShipAddr = '';
    let lastCustomer = { id: '', text: '', shipAddr: '', credit: null, acct: '', address: '' };
    let browseRows = [];
    let productSort = 'name_asc';
    let orderCatId = '';
    let orderSubId = '';
    let orderCategories = [];
    let lastBuyMap = {};
    let creditAfterAccept = null;

    const catalogModal = document.getElementById('catalogModal');
    const catalogBody = document.getElementById('catalogBody');
    const catalogTitle = document.getElementById('catalogTitle');
    const catalogBackBtn = document.getElementById('catalogBackBtn');
    let catalogTree = null;
    let catalogStack = [];
    let scanSessionKeys = {}; // only show items scanned in this scan session

    function scanLineKey(l) {
        return String(l.variation_id || '') + ':' + String(l.sub_unit_id || 0);
    }

    function syncScanUi() {
        const totEl = document.getElementById('saleScanTotal');
        const btn = document.getElementById('saleScanCheckout');
        const empty = document.getElementById('saleScanEmpty');
        const list = document.getElementById('saleScanList');
        const scanned = cart.filter((l) => Number(l.quantity) > 0 && scanSessionKeys[scanLineKey(l)]);
        const scanTotal = scanned.reduce((s, l) => s + (Number(l.quantity) * Number(l.unit_price)), 0);
        if (totEl) totEl.textContent = money(scanTotal);
        if (btn) btn.classList.toggle('is-off', scanned.length < 1 && cartSum() <= 0);
        if (!scanned.length) {
            if (empty) empty.hidden = false;
            if (list) { list.hidden = true; list.innerHTML = ''; }
            return;
        }
        if (empty) empty.hidden = true;
        if (list) {
            list.hidden = false;
            list.innerHTML = scanned.map((l) => `
                <div class="sale-scan__row">
                    <div>
                        <div class="sale-scan__row-name">${escapeHtml(l.name)}</div>
                        <div class="sale-scan__row-meta">${escapeHtml(l.sku || '')} · ${fmtQty(l.quantity)} ${escapeHtml(lineUnitName(l))}${(Number(l.multiplier) || 1) > 1 ? ' = ' + fmtQty(linePcs(l)) + ' Pc' : ''}</div>
                    </div>
                    <div class="sale-scan__row-name">${money(l.quantity * l.unit_price)}</div>
                </div>`).join('');
        }
    }

    function scanProductUrl(params) {
        let url = @json(route('sale.api.products')) + '?contact_id=' + encodeURIComponent((contactId && contactId.value) || '')
            + '&location_id=' + encodeURIComponent((locationId && locationId.value) || '');
        Object.keys(params || {}).forEach((k) => { url += '&' + k + '=' + encodeURIComponent(params[k]); });
        return url;
    }

    const scanCache = Object.create(null);
    const scanInflight = Object.create(null);

    function setScanStatus(msg) {
        const status = document.getElementById('saleScanStatus');
        if (status) status.textContent = msg;
    }

    function addScannedItem(item) {
        const key = String(item.variation_id) + ':' + String(Number(unitMeta(item).unit.id) || 0);
        const qtyOf = () => {
            const line = cart.find((l) => scanLineKey(l) === key);
            return line ? Number(line.quantity) || 0 : 0;
        };
        const before = qtyOf();
        addToCart(item);
        if (qtyOf() <= before) {
            setScanStatus('Out of stock — ' + (item.name || item.sku || ''));
            return false;
        }
        scanSessionKeys[key] = true;
        syncScanUi();
        setScanStatus('Added ' + (item.name || item.sku || ''));
        return true;
    }

    let lastAddedCode = '';
    let lastAddedAt = 0;

    async function addFromScanCode(code, quietMiss) {
        const q = String(code || '').trim();
        if (!q) return false;
        const now = Date.now();
        if (q === lastAddedCode && (now - lastAddedAt) < 1200) {
            return true;
        }
        if (scanInflight[q]) {
            const cached = await scanInflight[q];
            return !!cached;
        }
        setScanStatus('Looking up ' + q + '…');
        if (scanCache[q]) {
            lastAddedCode = q;
            lastAddedAt = Date.now();
            return addScannedItem(scanCache[q]);
        }
        const pending = (async () => {
            let rows = await fetchJson(scanProductUrl({ q: q, scan: 1 }));
            let item = rows && rows[0] ? rows[0] : null;
            if (!item && /^\d{8,14}$/.test(q)) {
                rows = await fetchJson(scanProductUrl({ q: q }));
                item = rows && rows[0] ? rows[0] : null;
            }
            if (item) {
                scanCache[q] = item;
                const digits = q.replace(/\D+/g, '');
                if (digits.length === 13 && digits.charAt(0) === '0') scanCache[digits.slice(1)] = item;
                if (digits.length === 12) scanCache['0' + digits] = item;
            }
            return item;
        })().catch(() => null).finally(() => { delete scanInflight[q]; });
        scanInflight[q] = pending;
        const item = await pending;
        if (!item) {
            if (quietMiss) return false;
            setScanStatus('Hold still — no item for ' + q);
            if (window.notifyAppItemNotFound) {
                window.notifyAppItemNotFound(q);
            } else {
                alert('Item not found: ' + q + '\n\nThe scanned code does not match any item in the system.');
            }
            return false;
        }
        if (q === lastAddedCode && (Date.now() - lastAddedAt) < 1200) {
            return true;
        }
        lastAddedCode = q;
        lastAddedAt = Date.now();
        return addScannedItem(item);
    }

    const scanScreen = document.getElementById('saleScanScreen');
    let scanStop = null;
    let scanPaused = false;
    let lastMissCode = '';
    const saleScanMiss = document.getElementById('saleScanMiss');
    const saleScanMissText = document.getElementById('saleScanMissText');
    const saleScanMissOk = document.getElementById('saleScanMissOk');
    const html5ScanSrc = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';

    function isIosSaleScan() {
        const ua = navigator.userAgent || '';
        const iPhone = /iPad|iPhone|iPod/.test(ua);
        const iPadOs = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
        return iPhone || iPadOs;
    }

    function loadScriptOnce(src, flag) {
        return new Promise((resolve, reject) => {
            if (window[flag]) return resolve();
            const existing = document.querySelector('script[src="' + src + '"]');
            if (existing) {
                if (window[flag]) return resolve();
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', reject);
                return;
            }
            const s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = () => resolve();
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    const preloadScan = () => { loadScriptOnce(html5ScanSrc, 'Html5Qrcode').catch(() => {}); };
    if ('requestIdleCallback' in window) requestIdleCallback(preloadScan, { timeout: 2000 });
    else setTimeout(preloadScan, 600);

    function onDecodedBarcode(text) {
        if (scanPaused) return;
        let code = String(text || '').trim().replace(/[\x00-\x1F\x7F]+/g, '');
        if (/^\][A-Za-z0-9]{2}/.test(code)) code = code.slice(3);
        const digits = code.replace(/\D+/g, '');
        if (digits.length >= 8 && digits.length <= 14) code = digits;
        if (!code) return;
        const now = Date.now();
        if (scanInflight[code]) return;
        if (code === lastMissCode && (now - lastAddedAt) < 2200) return;
        if (code === lastAddedCode && (now - lastAddedAt) < 900) return;
        try { navigator.vibrate && navigator.vibrate(25); } catch (e) {}
        addFromScanCode(code);
    }

    let scanTorchOn = false;
    let html5Qr = null;

    function bindHtml5Torch() {
        const btn = document.getElementById('saleScanTorchBtn');
        if (!btn || !html5Qr) {
            if (btn) btn.hidden = true;
            return;
        }
        let caps = {};
        try {
            caps = html5Qr.getRunningTrackCapabilities ? html5Qr.getRunningTrackCapabilities() : {};
        } catch (e) {}
        if (!caps.torch) {
            btn.hidden = true;
            return;
        }
        btn.hidden = false;
        scanTorchOn = false;
        btn.textContent = 'Light';
        btn.onclick = async () => {
            scanTorchOn = !scanTorchOn;
            try {
                await html5Qr.applyVideoConstraints({ advanced: [{ torch: scanTorchOn }] });
                btn.textContent = scanTorchOn ? 'Light on' : 'Light';
            } catch (e) {
                scanTorchOn = false;
                btn.textContent = 'Light';
            }
        };
    }

    async function startSharedCamera() {
        await loadScriptOnce(html5ScanSrc, 'Html5Qrcode');
        if (!window.Html5Qrcode) throw new Error('Scanner library failed to load');
        const region = document.getElementById('saleScanReader');
        if (region) region.innerHTML = '';
        html5Qr = new window.Html5Qrcode('saleScanReader');
        const F = window.Html5QrcodeSupportedFormats;
        const formats = F ? [
            F.UPC_A, F.UPC_E, F.EAN_13, F.EAN_8,
            F.CODE_128, F.CODE_39, F.ITF, F.CODABAR,
        ].filter((v) => typeof v !== 'undefined') : undefined;
        const native = !isIosSaleScan();
        await html5Qr.start(
            { facingMode: 'environment' },
            {
                fps: native ? 24 : 20,
                disableFlip: true,
                useBarCodeDetectorIfSupported: native,
                experimentalFeatures: { useBarCodeDetectorIfSupported: native },
                formatsToSupport: formats,
            },
            (txt) => { onDecodedBarcode(txt); },
            () => {}
        );
        try {
            if (typeof html5Qr.applyVideoConstraints === 'function') {
                await html5Qr.applyVideoConstraints({
                    advanced: [{ focusMode: 'continuous' }],
                });
            }
        } catch (e) {}
        bindHtml5Torch();
        scanStop = async () => {
            try { if (html5Qr) await html5Qr.stop(); } catch (e) {}
            try { if (html5Qr) await html5Qr.clear(); } catch (e) {}
            html5Qr = null;
        };
    }

    async function resumeScanAfterMiss() {
        window.stopPosScanMissAlarm && window.stopPosScanMissAlarm();
        if (saleScanMiss) saleScanMiss.hidden = true;
        lastAddedCode = lastMissCode;
        lastAddedAt = Date.now();
        setScanStatus('Ready — scan next barcode');
        if (isIosSaleScan() && scanScreen && !scanScreen.hidden) {
            if (scanStop) {
                try { await scanStop(); } catch (e) {}
                scanStop = null;
            }
            try {
                await startSharedCamera();
            } catch (e) {
                setScanStatus('Camera paused. Close and open scan again.');
            }
        }
        scanPaused = false;
    }

    window.addEventListener('app-item-not-found', function (e) {
        if (!scanScreen || scanScreen.hidden) return;
        e.preventDefault();
        scanPaused = true;
        lastMissCode = String(e.detail && e.detail.code ? e.detail.code : '').trim();
        if (saleScanMissText) {
            saleScanMissText.textContent = lastMissCode
                ? ('No item for ' + lastMissCode + '. Tap OK to scan the next barcode.')
                : 'This barcode is not in the system. Tap OK to scan the next barcode.';
        }
        if (saleScanMiss) saleScanMiss.hidden = false;
    });

    if (saleScanMissOk) saleScanMissOk.addEventListener('click', resumeScanAfterMiss);

    async function startSaleScan() {
        if (!scanScreen) return;
        document.body.appendChild(scanScreen);
        if (skuMode !== 'scan') setSkuMode('scan');
        // Fresh scan session — do not auto-list existing cart items
        scanSessionKeys = {};
        syncScanUi();
        scanPaused = false;
        lastMissCode = '';
        if (saleScanMiss) saleScanMiss.hidden = true;
        scanScreen.hidden = false;
        setScanStatus('Starting camera…');
        try {
            await startSharedCamera();
            setScanStatus('Ready — scan a barcode');
        } catch (err) {
            setScanStatus('Camera unavailable. Allow camera permission, or type the SKU.');
        }
    }

    async function stopSaleScan() {
        scanPaused = false;
        lastMissCode = '';
        if (saleScanMiss) saleScanMiss.hidden = true;
        window.stopPosScanMissAlarm && window.stopPosScanMissAlarm();
        if (scanStop) {
            try { await scanStop(); } catch (e) {}
            scanStop = null;
        }
        scanSessionKeys = {};
        if (scanScreen) scanScreen.hidden = true;
        const torchBtn = document.getElementById('saleScanTorchBtn');
        if (torchBtn) {
            torchBtn.hidden = true;
            torchBtn.textContent = 'Light';
        }
        scanTorchOn = false;
    }

    document.getElementById('saleScanBack')?.addEventListener('click', () => stopSaleScan());
    document.getElementById('saleScanCheckout')?.addEventListener('click', async () => {
        if (cartSum() <= 0) return;
        await stopSaleScan();
        document.getElementById('goShippingBtn')?.click();
    });

    function money(n) { return '$' + (Number(n) || 0).toFixed(2); }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function smallestUnit(units) {
        const list = (Array.isArray(units) ? units.slice() : []).sort(
            (a, b) => Number(a.multiplier || 1) - Number(b.multiplier || 1)
        );
        return list.find((u) => Number(u.multiplier || 1) === 1) || list[0] || null;
    }

    function unitMeta(r) {
        const units = Array.isArray(r.units) ? r.units : [];
        const piece = smallestUnit(units);
        const defaultId = r.sub_unit_id || (piece && piece.id) || r.unit_id;
        const unit = units.find(u => Number(u.id) === Number(defaultId)) || piece || units[0] || {
            id: r.unit_id || 0,
            name: r.unit_name || 'Pc',
            multiplier: 1,
            allow_decimal: r.allow_decimal
        };
        const multiplier = Number(unit.multiplier) || 1;
        const basePrice = (typeof r.base_price !== 'undefined' && r.base_price !== null)
            ? Number(r.base_price)
            : (Number(r.price) / (multiplier || 1));
        return { units, unit, multiplier, basePrice, unitPrice: basePrice * multiplier };
    }

    function hasPackUnits(units) {
        if (!Array.isArray(units) || units.length < 2) return false;
        const ids = new Set(units.map((u) => String(u.id)));
        return ids.size > 1;
    }

    function isOutOfStock(line) {
        return Number(line.enable_stock) === 1 && Number(line.stock) <= 0;
    }

    function qtyExceedsStock(line, qty) {
        if (Number(line.enable_stock) !== 1) return false;
        const need = Number(qty) * (Number(line.multiplier) || 1);
        return need > Number(line.stock) + 0.0001;
    }

    function applyUnit(variationId, unitId) {
        const vid = Number(variationId);
        const uid = Number(unitId);
        const r = browseRows.find((x) => Number(x.variation_id) === vid);
        if (r) {
            r.sub_unit_id = uid;
            const piece = smallestUnit(r.units);
            if (!r.units || !r.units.length) {
                /* keep going */
            }
        }
        cartLines.querySelectorAll('[data-unit-vid]').forEach((btn) => {
            if (Number(btn.getAttribute('data-unit-vid')) !== vid) return;
            btn.classList.toggle('is-on', Number(btn.getAttribute('data-unit-id')) === uid);
        });
        const i = cart.findIndex((c) => Number(c.variation_id) === vid);
        if (i >= 0) {
            if (r && Array.isArray(r.units) && r.units.length) {
                cart[i].units = r.units;
                if (cart[i].base_price == null) cart[i].base_price = unitMeta(r).basePrice;
            }
            setCartUnit(i, uid);
            return;
        }
        renderCart();
    }

    let unitTapLock = 0;
    cartLines.addEventListener('click', function (e) {
        const b = e.target.closest('[data-unit-vid]');
        if (!b || !cartLines.contains(b)) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        const now = Date.now();
        if (now - unitTapLock < 350) return;
        unitTapLock = now;
        applyUnit(b.getAttribute('data-unit-vid'), b.getAttribute('data-unit-id'));
    }, true);

    function packToggleHtml(units, selectedId, variationId, basePrice) {
        const seen = new Set();
        const list = (Array.isArray(units) ? units : []).filter((u) => {
            const id = String(u.id);
            if (seen.has(id)) return false;
            seen.add(id);
            return true;
        });
        if (list.length < 2) return '';
        list.sort((a, b) => Number(a.multiplier || 1) - Number(b.multiplier || 1));
        const piece = list[0];
        const selected = list.some((u) => Number(u.id) === Number(selectedId))
            ? selectedId
            : (piece && piece.id);
        const base = Number(basePrice) || 0;
        return `<div class="sale-ois-units"><div class="sale-ois-unitbtns" role="group">${list.map((u, i) => {
            const on = Number(u.id) === Number(selected);
            const up = base * (Number(u.multiplier) || 1);
            const label = escapeHtml((u.name || '').trim() || ('Unit ' + (i + 1)));
            const kind = i === 0 ? 'piece' : 'pack';
            return `<button type="button" class="sale-ois-unitbtn sale-ois-unitbtn--${kind}${on ? ' is-on' : ''}" data-unit-vid="${variationId}" data-unit-id="${u.id}"><span>${label}</span><span class="sale-ois-unitbtn__price">${money(up)}</span></button>`;
        }).join('')}</div></div>`;
    }

    async function loadLastBuys() {
        lastBuyMap = {};
        if (!contactId || !contactId.value) return;
        try {
            const rows = await fetchJson(@json(route('sale.api.last_purchases')) + '?contact_id=' + encodeURIComponent(contactId.value) + '&location_id=' + encodeURIComponent(locationId.value));
            rows.forEach((r) => { lastBuyMap[String(r.variation_id)] = r; });
        } catch (e) {}
    }

    async function openOrderHistory(variationId, name) {
        const body = document.getElementById('orderHistoryBody');
        const title = document.getElementById('orderHistoryTitle');
        if (title) title.textContent = name || 'Product';
        if (body) body.innerHTML = '<div class="sale-ohist__empty">Loading…</div>';
        saleOpenSheet('orderHistorySheet');
        try {
            const url = @json(route('sale.api.product_history')) + '?contact_id=' + encodeURIComponent(contactId.value) + '&variation_id=' + encodeURIComponent(variationId);
            const rows = await fetchJson(url);
            if (!body) return;
            if (!rows.length) {
                body.innerHTML = '<div class="sale-ohist__empty">No previous orders for this product.</div>';
                return;
            }
            body.innerHTML = rows.map((h) => {
                const unit = (h.unit_name || 'Pc').toString();
                return `<div class="sale-ohist__row">
                    <div>
                        <div class="sale-ohist__date">${escapeHtml(h.date || '')}</div>
                        ${h.order_number ? `<div class="sale-ohist__sub">#${escapeHtml(h.order_number)}</div>` : ''}
                    </div>
                    <div class="sale-ohist__meta"><span class="sale-ohist__qty">${fmtQty(h.quantity)}</span> ${escapeHtml(unit)} · <span class="sale-ohist__amt">${money(h.line_total)}</span></div>
                </div>`;
            }).join('');
        } catch (e) {
            if (body) body.innerHTML = '<div class="sale-ohist__empty">Could not load history.</div>';
        }
    }

    function priceLine(r) {
        const m = unitMeta(r);
        let s = money(m.unitPrice) + ' / ' + escapeHtml(m.unit.name || 'Pc');
        if (m.multiplier > 1) s += ' (' + escapeHtml(m.unit.name) + ' x ' + m.multiplier + ')';
        return s;
    }

    function cartKey(variationId, subUnitId) {
        return String(variationId) + ':' + String(subUnitId || 0);
    }

    function cartSum() {
        return cart.reduce((s, l) => s + (l.quantity * l.unit_price), 0);
    }

    let allowLeave = false;
    let pendingLeave = null;
    let skipPersist = true;
    const DRAFT_KEY = 'sale_draft_cart';
    const parkedUrl = @json(route('sale.api.parked_sales'));
    const customersUrl = @json(route('sale.customers'));
    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
    function jsonHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        };
    }
    function draftPayload() {
        return {
            contact_id: contactId ? contactId.value : '',
            contact_name: lastCustomer.display || lastCustomer.text || '',
            cart: cart.filter((l) => Number(l.quantity) > 0),
            shipping_address: shippingAddress ? shippingAddress.value : '',
            checkout: checkoutFieldValues(),
        };
    }
    const CHECKOUT_FIELDS = ['ship_to_address_id', 'ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip', 'payment_term_id', 'ship_via_id', 'route_id', 'ship_date', 'sale_note'];
    function checkoutFieldValues() {
        const out = {};
        CHECKOUT_FIELDS.forEach((n) => {
            const el = form.querySelector('[name="' + n + '"]');
            if (el) out[n] = el.value;
        });
        return out;
    }
    function applyCheckoutFields(values) {
        if (!values || typeof values !== 'object') return;
        CHECKOUT_FIELDS.forEach((n) => {
            const el = form.querySelector('[name="' + n + '"]');
            if (!el || values[n] == null) return;
            if (el.tagName === 'SELECT' && !Array.from(el.options).some((o) => o.value === String(values[n]))) return;
            el.value = values[n];
        });
        document.getElementById('ship_to_address_id')?.dispatchEvent(new Event('change'));
    }
    function persistCart(force) {
        if (typeof isEdit !== 'undefined' && isEdit) return;
        if (!force && (skipPersist || !cartHasItems())) return;
        try {
            const raw = JSON.stringify(draftPayload());
            localStorage.setItem(DRAFT_KEY, raw);
            sessionStorage.setItem(DRAFT_KEY, raw);
            sessionStorage.removeItem('sale_cart_cleared');
        } catch (e) {}
    }
    function clearDraftStorage() {
        try {
            localStorage.removeItem(DRAFT_KEY);
            sessionStorage.removeItem(DRAFT_KEY);
            sessionStorage.setItem('sale_cart_cleared', String(contactId && contactId.value ? contactId.value : '1'));
        } catch (e) {}
    }
    function applyDraft(data) {
        if (!data || !Array.isArray(data.cart) || !data.cart.length) return false;
        if (contactId.value && data.contact_id && String(data.contact_id) !== String(contactId.value)) return false;
        data.cart.forEach((r) => addToCart(r, true, true));
        if (shippingAddress && data.shipping_address) shippingAddress.value = data.shipping_address;
        applyCheckoutFields(data.checkout);
        syncTerms();
        return true;
    }
    function restoreCart() {
        if (typeof isEdit !== 'undefined' && isEdit) return;
        try {
            const cleared = sessionStorage.getItem('sale_cart_cleared');
            if (cleared && (!contactId.value || cleared === '1' || String(cleared) === String(contactId.value))) {
                clearDraftStorage();
                // Keep cleared marker until a new item is added / draft saved
                sessionStorage.setItem('sale_cart_cleared', String(contactId && contactId.value ? contactId.value : '1'));
                return;
            }
        } catch (e) {}
        try {
            const raw = localStorage.getItem(DRAFT_KEY) || sessionStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            applyDraft(JSON.parse(raw));
        } catch (e) {}
    }
    /** "Save" parks the cart as a Parked sale (recall it from the Parked button). */
    async function parkCart() {
        const lines = cart.filter((l) => Number(l.quantity) > 0).map((l) => ({
            product_id: Number(l.product_id) || Number(l.variation_id),
            variation_id: Number(l.variation_id),
            name: String(l.name || ''),
            unit_price: Number(l.unit_price) || 0,
            quantity: Number(l.quantity),
            allow_decimal: l.allow_decimal ? 1 : 0,
        }));
        if (!lines.length || !contactId.value) return;
        try {
            const res = await fetch(parkedUrl, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    customer_id: Number(contactId.value),
                    customer_label: lastCustomer.display || lastCustomer.text || '',
                    location_id: Number(locationId.value) || null,
                    lines,
                    shipping: checkoutFieldValues(),
                }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                alert(err.message || 'Could not park this sale.');
                return;
            }
            clearDraftStorage();
        } catch (e) {}
    }
    async function clearDraftServer() {
        clearDraftStorage();
    }
    async function loadParked() {
        const btn = document.getElementById('parkedOpenBtn');
        if (!btn || isEdit || !contactId.value) return [];
        try {
            const rows = (await fetchJson(parkedUrl)).filter((r) => String(r.customer_id) === String(contactId.value));
            btn.textContent = 'Parked (' + rows.length + ')';
            btn.classList.toggle('hidden', rows.length === 0);
            return rows;
        } catch (e) {
            return [];
        }
    }
    async function openParked() {
        const body = document.getElementById('parkedBody');
        if (!body) return;
        body.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">Loading…</div>';
        saleOpenSheet('parkedSheet');
        const rows = await loadParked();
        if (!rows.length) {
            body.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No parked sales</div>';
            return;
        }
        body.innerHTML = rows.map((r) => {
            const when = r.updated_at ? new Date(r.updated_at).toLocaleString() : '';
            return `<div class="sale-act-row" style="justify-content:space-between">
                <button type="button" data-recall="${r.id}" style="all:unset;cursor:pointer;flex:1;min-width:0">
                    <b>${escapeHtml(String(r.line_count))} items · ${money(r.total)}</b>
                    <div class="text-xs text-slate-400">${escapeHtml(when)}</div>
                </button>
                <button type="button" data-discard="${r.id}" class="sale-act sale-act--del">Discard</button>
            </div>`;
        }).join('');
        body.querySelectorAll('[data-recall]').forEach((b) => b.onclick = () => recallParked(b.dataset.recall));
        body.querySelectorAll('[data-discard]').forEach((b) => b.onclick = () => discardParked(b.dataset.discard));
    }
    async function recallParked(id) {
        let row;
        try { row = await fetchJson(parkedUrl + '/' + id); } catch (e) { alert('Could not open parked sale'); return; }
        const p = (row && row.payload) || {};
        cart.length = 0;
        const ids = (p.lines || []).map((l) => l.variation_id).filter(Boolean);
        let fresh = {};
        if (ids.length) {
            try {
                const found = await Promise.all(ids.map((vid) => fetchJson(@json(route('sale.api.products')) + '?contact_id=' + encodeURIComponent(contactId.value) + '&variation_id=' + encodeURIComponent(vid))));
                found.forEach((rows) => { if (rows[0]) fresh[String(rows[0].variation_id)] = rows[0]; });
            } catch (e) {}
        }
        (p.lines || []).forEach((l) => {
            const base = fresh[String(l.variation_id)] || l;
            addToCart(Object.assign({}, base, { quantity: Number(l.quantity) || 1 }), true, true);
            const line = cart.find((c) => Number(c.variation_id) === Number(l.variation_id));
            if (line && l.unit_price != null) line.unit_price = Number(l.unit_price);
        });
        applyCheckoutFields(p.shipping);
        renderCart();
        saleCloseSheet('parkedSheet');
        await fetch(parkedUrl + '/' + id, { method: 'DELETE', headers: jsonHeaders(), credentials: 'same-origin' }).catch(() => {});
        loadParked();
    }
    async function discardParked(id) {
        if (!confirm('Discard this parked sale?')) return;
        await fetch(parkedUrl + '/' + id, { method: 'DELETE', headers: jsonHeaders(), credentials: 'same-origin' }).catch(() => {});
        openParked();
    }
    document.getElementById('parkedOpenBtn')?.addEventListener('click', openParked);
    function cartHasItems() {
        return cart.some((l) => Number(l.quantity) > 0);
    }
    let attnLeaveOnNo = false;
    let pendingLeaveNo = null;
    function hideAttn() {
        const el = document.getElementById('saleAttnSheet');
        if (el) el.hidden = true;
        pendingLeave = null;
        pendingLeaveNo = null;
        attnLeaveOnNo = false;
    }
    function confirmLeave(go, opts) {
        opts = opts || {};
        if (!cartHasItems()) {
            allowLeave = true;
            go();
            return;
        }
        pendingLeave = go;
        pendingLeaveNo = typeof opts.onNo === 'function' ? opts.onNo : null;
        attnLeaveOnNo = !!opts.leaveOnNo || !!pendingLeaveNo;
        const msgEl = document.getElementById('saleAttnMsg');
        if (msgEl) {
            msgEl.textContent = opts.msg || 'Do you want to save this order?';
        }
        const el = document.getElementById('saleAttnSheet');
        if (el) el.hidden = false;
    }
    function checkoutClosePrompt() {
        confirmLeave(() => { location.href = customersUrl; }, {
            msg: 'Do you want to save this order?',
            onNo: () => showCartStep(),
        });
    }

    function addToCart(r, silent, skipRender) {
        if (!r || !r.variation_id) return;
        if (isOutOfStock(r)) return;
        const meta = unitMeta(r);
        const vid = Number(r.variation_id);
        const subId = Number(meta.unit.id) || 0;
        const existingIdx = cart.findIndex(c => Number(c.variation_id) === vid && Number(c.sub_unit_id || 0) === subId);
        if (existingIdx >= 0) {
            const next = +cart[existingIdx].quantity + (Number(r.quantity) || 1);
            if (qtyExceedsStock(cart[existingIdx], next)) return;
            cart[existingIdx].quantity = next;
            if (typeof r.allow_decimal !== 'undefined') {
                cart[existingIdx].allow_decimal = !!Number(meta.unit.allow_decimal);
            }
            if (r.transaction_sell_lines_id && !cart[existingIdx].transaction_sell_lines_id) {
                cart[existingIdx].transaction_sell_lines_id = r.transaction_sell_lines_id;
            }
            if (cart[existingIdx].catalog_base_price == null) {
                cart[existingIdx].catalog_base_price = (r.catalog_base_price != null ? Number(r.catalog_base_price) : meta.basePrice);
            }
            if (cart[existingIdx].list_unit_price == null) {
                cart[existingIdx].list_unit_price = (r.list_unit_price != null ? Number(r.list_unit_price) : Number(cart[existingIdx].unit_price) || meta.unitPrice);
            }
            cart.unshift(cart.splice(existingIdx, 1)[0]);
        } else {
            cart.unshift({
                product_id: Number(r.product_id),
                variation_id: vid,
                transaction_sell_lines_id: r.transaction_sell_lines_id || null,
                name: r.name,
                sku: r.sku || '',
                image: r.image || '',
                has_image: !!r.has_image,
                stock: Number(r.stock) || 0,
                category: r.category || '',
                base_price: meta.basePrice,
                catalog_base_price: (r.catalog_base_price != null ? Number(r.catalog_base_price) : meta.basePrice),
                list_unit_price: (r.list_unit_price != null ? Number(r.list_unit_price) : meta.unitPrice),
                unit_price: meta.unitPrice,
                quantity: Number(r.quantity) || 1,
                enable_stock: r.enable_stock,
                product_type: r.product_type,
                allow_decimal: !!Number(meta.unit.allow_decimal),
                units: meta.units,
                unit_id: Number(r.unit_id) || subId,
                unit_name: meta.unit.name,
                sub_unit_id: subId,
                multiplier: meta.multiplier,
            });
        }
        if (!skipRender) renderCart();
        if (!silent) {
            showAddedMsg();
            showPriceUpdateAlert(r);
        }
    }

    function showPriceUpdateAlert(r) {
        if (!r || !r.price_updated) return;
        const isCost = r.alert_type === 'cost';
        const msg = r.price_update_message
            || (isCost
                ? `PO/cost updated: $${Number(r.previous_price || 0).toFixed(2)} → $${Number(r.current_price || 0).toFixed(2)}. Update sales price if needed.`
                : `Sales price updated: $${Number(r.previous_price || 0).toFixed(2)} → $${Number(r.current_price || 0).toFixed(2)}`);
        const overlay = document.getElementById('salePriceUpdateAlert');
        const titleEl = document.getElementById('salePriceUpdateAlertTitle');
        const textEl = document.getElementById('salePriceUpdateAlertText');
        const okBtn = document.getElementById('salePriceUpdateAlertOk');
        if (!overlay || !titleEl || !textEl) return;
        document.body.appendChild(overlay);
        titleEl.textContent = isCost ? 'PO / Cost updated' : 'Sales price updated';
        textEl.textContent = msg;
        overlay.hidden = false;
        const close = () => { overlay.hidden = true; };
        if (okBtn) okBtn.onclick = close;
        overlay.onclick = (e) => { if (e.target === overlay) close(); };
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

    function cartQty(variationId, subUnitId) {
        const line = (typeof subUnitId === 'undefined')
            ? cart.find(c => Number(c.variation_id) === Number(variationId))
            : cart.find(c => Number(c.variation_id) === Number(variationId) && Number(c.sub_unit_id || 0) === Number(subUnitId || 0));
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
        let next = Number(line.quantity) + delta;
        if (!line.allow_decimal) {
            next = Math.round(next);
        } else {
            next = Math.round(next * 100) / 100;
        }
        if (next < 1) {
            cart.splice(i, 1);
            renderCart();
            return;
        }
        if (qtyExceedsStock(line, next)) return;
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
        if (q < 1) {
            cart.splice(i, 1);
            renderCart();
            return;
        }
        if (qtyExceedsStock(line, q)) q = Math.max(0, Math.floor(Number(line.stock) / (Number(line.multiplier) || 1)));
        if (q < 1) {
            cart.splice(i, 1);
            renderCart();
            return;
        }
        line.quantity = q;
        renderCart();
    }

    function setCartUnit(i, unitId) {
        const line = cart[i];
        if (!line) return false;
        if ((!line.units || !line.units.length) && browseRows.length) {
            const src = browseRows.find((x) => Number(x.variation_id) === Number(line.variation_id));
            if (src && src.units) {
                line.units = src.units;
                if (line.base_price == null) line.base_price = unitMeta(src).basePrice;
            }
        }
        const unit = (line.units || []).find(u => Number(u.id) === Number(unitId));
        if (!unit) {
            renderCart();
            return false;
        }
        if (Number(line.sub_unit_id) === Number(unit.id)) {
            renderCart();
            return true;
        }
        const oldMult = Number(line.multiplier) || 1;
        const newMult = Number(unit.multiplier) || 1;
        const baseQty = Number(line.quantity) * oldMult;
        let newQty = newMult > 0 ? (baseQty / newMult) : baseQty;
        if (!Number(unit.allow_decimal)) newQty = Math.round(newQty);
        else newQty = Math.round(newQty * 100) / 100;
        if (newQty < 0.0001) newQty = 0;
        const other = cart.findIndex((c, idx) => idx !== i && Number(c.variation_id) === Number(line.variation_id) && Number(c.sub_unit_id) === Number(unit.id));
        line.sub_unit_id = Number(unit.id);
        line.unit_name = unit.name;
        line.multiplier = newMult;
        line.allow_decimal = !!Number(unit.allow_decimal);
        if (line.catalog_base_price == null) {
            line.catalog_base_price = Number(line.base_price) || 0;
        }
        if (line.list_unit_price == null) {
            line.list_unit_price = Number(line.unit_price) || (Number(line.catalog_base_price) * oldMult);
        } else {
            // Keep list price aligned to new pack size from catalog piece price
            line.list_unit_price = Number(line.catalog_base_price) * newMult;
        }
        line.unit_price = Number(line.base_price) * newMult;
        line.quantity = newQty;
        if (qtyExceedsStock(line, line.quantity)) {
            const maxQ = Math.floor(Number(line.stock) / (newMult || 1));
            line.quantity = Math.max(0, maxQ);
        }
        if (other >= 0) {
            cart[other].quantity = Number(cart[other].quantity) + Number(line.quantity);
            cart.splice(i, 1);
        } else if (Number(line.quantity) < 1 && !Number(unit.allow_decimal)) {
            cart.splice(i, 1);
        }
        renderCart();
    }

    const stepCustomer = document.getElementById('stepCustomer');
    const orderModeInput = document.getElementById('order_mode');
    const isEdit = @json(!empty($edit_order));
    const canEditOrderPrice = @json((bool) ($canEditPrice ?? false));
    const customerSearchEdit = document.getElementById('customerSearchEdit');
    const customerResultsEdit = document.getElementById('customerResultsEdit');

    function syncOrderMode() {
        if (!orderModeInput) return;
        const checked = document.querySelector('input[name="order_mode_ui"]:checked');
        if (checked) orderModeInput.value = checked.value;
        const submitBtn = document.getElementById('submitOrderBtn');
        if (!submitBtn || isEdit) return;
        const mode = orderModeInput.value;
        const label = mode === 'estimate' ? 'Create estimate' : (mode === 'back_order' ? 'Create back order' : 'Create order');
        const svgHtml = submitBtn.querySelector('svg') ? submitBtn.querySelector('svg').outerHTML : '';
        submitBtn.innerHTML = svgHtml + ' ' + label;
        // Keep cart-step SUBMIT label as-is (matches mobile create-order UI)
    }

    document.querySelectorAll('input[name="order_mode_ui"]').forEach(r => {
        r.addEventListener('change', syncOrderMode);
    });
    syncOrderMode();

    function creditLimitText(lim) {
        if (lim === null || typeof lim === 'undefined' || lim === '' || Number(lim) === 0) {
            return 'Customer has credit limit 0';
        }
        return 'Customer has credit limit $' + Number(lim).toFixed(2);
    }

    function hideCreditWarning() {
        const sheet = document.getElementById('orderCreditSheet');
        if (sheet) sheet.hidden = true;
    }

    function showCreditWarning(lim, afterAccept) {
        creditAfterAccept = afterAccept || null;
        const msg = document.getElementById('orderCreditMsg');
        const sheet = document.getElementById('orderCreditSheet');
        if (msg) msg.textContent = creditLimitText(lim);
        if (sheet) sheet.hidden = false;
    }

    function showCustomerStep() {
        window.location.href = @json(route('sale.customers'));
    }

    function showCartStep() {
        if (stepCustomer) stepCustomer.hidden = true;
        stepCart.hidden = false;
        if (stepShipping) stepShipping.hidden = true;
        if (stepCheckout) stepCheckout.hidden = true;
        document.body.classList.remove('sale-picking-customer');
        document.body.classList.add('sale-building-order');
        document.body.classList.remove('sale-checking-out');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        loadBrowse(productSearch ? productSearch.value.trim() : '');
    }

    async function loadBrowse(q) {
        if (!locationId || !locationId.value) {
            browseRows = [];
            renderCart();
            return;
        }
        let url = @json(route('sale.api.products')) + '?contact_id=' + encodeURIComponent((contactId && contactId.value) || '') + '&location_id=' + encodeURIComponent(locationId.value) + '&limit=80';
        if (q) url += '&q=' + encodeURIComponent(q);
        if (orderSubId) url += '&sub_category_id=' + encodeURIComponent(orderSubId);
        else if (orderCatId) url += '&category_id=' + encodeURIComponent(orderCatId);
        try {
            browseRows = await fetchJson(url);
            browseRows.forEach((row) => {
                const piece = smallestUnit(row.units);
                if (piece && (row.sub_unit_id == null || row.sub_unit_id === '')) {
                    row.sub_unit_id = piece.id;
                }
            });
        } catch (err) {
            browseRows = [];
        }
        renderCart();
    }

    function setCustomer(id, text, shipAddr, goToCart, creditLimit, extra) {
        contactId.value = id || '';
        const orderCustomerName = document.getElementById('orderCustomerName');
        if (id) {
            lastCustomer = {
                id: String(id),
                text: text || '',
                shipAddr: shipAddr || '',
                credit: creditLimit,
                acct: extra && extra.acct ? extra.acct : '',
                address: (extra && extra.address) ? extra.address : (shipAddr || ''),
                display: (extra && extra.display_name) ? extra.display_name : (text || ''),
                open: (extra && extra.open_balance != null) ? Number(extra.open_balance) : null,
                lastTotal: (extra && extra.last_order_total != null) ? Number(extra.last_order_total) : null,
            };
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
            if (savedShipAddr && shippingAddress && !shippingAddress.value.trim()) {
                shippingAddress.value = savedShipAddr;
            }
            if (goToCart !== false && !isEdit) {
                showCreditWarning(creditLimit, () => showCartStep());
            }
            loadLastBuys().then(() => renderCart());
        } else {
            lastCustomer = { id: '', text: '', shipAddr: '', credit: null, acct: '', address: '', display: '' };
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

    if (customerSelected) customerSelected.addEventListener('click', openCustomerSearch);

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
                    setCustomer(r.id, r.text, r.shipping_address || r.address || '', true, r.credit_limit, r);
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
                    setCustomer(r.id, r.text, r.shipping_address || r.address || '', false, r.credit_limit, r);
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
        true,
        @json($default_customer['credit_limit'] ?? null),
        {!! json_encode([
            'acct' => $default_customer['acct'] ?? '',
            'display_name' => $default_customer['display_name'] ?? '',
            'address' => $default_customer['address'] ?? ($default_customer['shipping_address'] ?? ''),
            'open_balance' => $default_customer['open_balance'] ?? null,
            'last_order_total' => $default_customer['last_order_total'] ?? null,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    );
    @elseif(!empty($edit_order))
    document.body.classList.remove('sale-picking-customer');
    document.body.classList.add('sale-building-order');
    @endif

    function renderCart() {
        cartLines.innerHTML = '';
        const total = cartSum();
        cartEmpty.classList.add('hidden');
        const rows = browseRows.length ? browseRows.slice() : [];
        rows.sort(function (a, b) {
            if (productSort === 'name_desc') return String(b.name || '').localeCompare(String(a.name || ''));
            if (productSort === 'price_asc') return Number(a.price || 0) - Number(b.price || 0);
            if (productSort === 'price_desc') return Number(b.price || 0) - Number(a.price || 0);
            if (productSort === 'stock') {
                const as = (Number(a.enable_stock) === 1 && Number(a.stock) <= 0) ? 1 : 0;
                const bs = (Number(b.enable_stock) === 1 && Number(b.stock) <= 0) ? 1 : 0;
                return as - bs;
            }
            return String(a.name || '').localeCompare(String(b.name || ''));
        });
        const display = rows.length ? rows.map((r) => {
            const meta = unitMeta(r);
            const found = cart.find(c => Number(c.variation_id) === Number(r.variation_id) && Number(c.sub_unit_id || 0) === Number(meta.unit.id || 0));
            if (found) return found;
            return {
                product_id: r.product_id,
                variation_id: r.variation_id,
                name: r.name,
                sku: r.sku || '',
                image: r.image || '',
                has_image: !!r.has_image,
                stock: r.stock,
                enable_stock: r.enable_stock,
                category_name: r.category_name || '',
                base_price: meta.basePrice,
                catalog_base_price: (r.catalog_base_price != null ? Number(r.catalog_base_price) : meta.basePrice),
                list_unit_price: (r.list_unit_price != null ? Number(r.list_unit_price) : meta.unitPrice),
                unit_price: meta.unitPrice,
                quantity: 0,
                units: meta.units,
                unit_name: meta.unit.name,
                sub_unit_id: meta.unit.id,
                multiplier: meta.multiplier,
                allow_decimal: !!Number(meta.unit.allow_decimal),
                _browse: r,
            };
        }) : cart;
        const countEl = document.getElementById('orderResultCount');
        if (countEl) countEl.textContent = 'Results: ' + (browseRows.length || cart.length);
        display.forEach((line, idx) => {
            const cartIdx = cart.findIndex(c => Number(c.variation_id) === Number(line.variation_id) && Number(c.sub_unit_id || 0) === Number(line.sub_unit_id || 0));
            const qty = cartIdx >= 0 ? cart[cartIdx].quantity : 0;
            const unitPrice = cartIdx >= 0 ? cart[cartIdx].unit_price : line.unit_price;
            const lineTot = qty * unitPrice;
            const units = line.units || [];
            const q = (productSearch.value || '').trim();
            const highlightName = (name) => {
                const safe = escapeHtml(name);
                if (!q) return safe;
                const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return safe.replace(new RegExp(esc, 'ig'), (m) => `<mark class="sale-ois-hit">${m}</mark>`);
            };
            const inStock = !isOutOfStock(line);
            const oos = !inStock;
            const last = lastBuyMap[String(line.variation_id)] || null;
            const thumb = line.has_image && line.image
                ? `<img src="${escapeHtml(line.image)}" alt="">`
                : `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m21 15-4.5-4.5L9 18"/></svg>`;
            const el = document.createElement('div');
            el.className = 'pp-card' + (oos ? ' is-oos' : '');
            const toggle = packToggleHtml(units, line.sub_unit_id, line.variation_id, line.base_price);
            const unitLabel = (line.unit_name || '').trim();
            const piece = Number((cartIdx >= 0 ? cart[cartIdx].base_price : line.base_price) || unitPrice) || 0;
            const lastBtn = last
                ? `<button type="button" class="pp-last" data-hist="${line.variation_id}" data-hist-name="${escapeHtml(line.name)}">Last ordered: <b>${escapeHtml(String(last.last_date || ''))}</b> ›</button>`
                : `<div class="pp-last">No prior orders</div>`;
            const priceHtml = canEditOrderPrice && cartIdx >= 0
                ? `<input type="number" min="0" step="0.01" class="pp-price-input" data-price="${cartIdx}" value="${Number(unitPrice).toFixed(2)}" inputmode="decimal">`
                : `<div class="pp-price">${money(unitPrice)}</div>`;
            el.innerHTML = `
                <div class="pp-img">${thumb}</div>
                <div class="pp-body">
                    <div class="pp-name">${highlightName(line.name)}</div>
                    ${unitLabel ? `<div class="pp-pack">${escapeHtml(unitLabel)}</div>` : ''}
                    ${line.sku ? `<div class="pp-code">Code: ${escapeHtml(line.sku)}</div>` : ''}
                    ${oos ? `<div class="pp-oos">OUT OF STOCK</div>` : `
                        <div class="pp-price-row">${priceHtml}<div class="pp-unit-price">${money(piece)}/pc</div></div>
                        ${lastBtn}
                        ${toggle}
                    `}
                    <div class="pp-bottom">
                        <div class="sale-ois-qty">
                            <button type="button" class="is-minus" ${oos || cartIdx < 0 ? 'disabled' : 'data-dec="'+cartIdx+'"'}>−</button>
                            <input type="text" value="${qty}" ${oos ? 'disabled' : (cartIdx >= 0 ? 'data-qty="'+cartIdx+'"' : '')} class="sale-qty-input" autocomplete="off">
                            <button type="button" class="is-plus" ${oos ? 'disabled' : (cartIdx >= 0 ? 'data-inc="'+cartIdx+'"' : 'data-add-b="'+idx+'"')}>+</button>
                        </div>
                        <div class="pp-total${qty ? '' : ' zero'}">${money(lineTot)}</div>
                    </div>
                </div>`;
            cartLines.appendChild(el);
        });
        cartTotal.textContent = money(total);
        shipTotal.textContent = money(total);
        const countLabel = document.getElementById('cartCountLabel');
        if (countLabel) {
            const itemN = cart.reduce((s, l) => s + Number(l.quantity || 0), 0);
            const prodN = new Set(cart.filter((l) => Number(l.quantity) > 0).map((l) => String(l.variation_id))).size;
            countLabel.innerHTML = fmtQty(itemN) + ' items <span>· ' + prodN + ' products</span>';
        }
        const checkoutBtn = document.getElementById('goShippingBtn');
        if (checkoutBtn) checkoutBtn.classList.toggle('is-off', total <= 0);
        syncScanUi();
        applyProductView(window.__saleProductView || 'item', false);

        cartLines.querySelectorAll('[data-add-b]').forEach(b => b.onclick = () => {
            if (Date.now() - unitTapLock < 400) return;
            addToCart(browseRows[+b.dataset.addB]);
        });
        cartLines.querySelectorAll('[data-inc]').forEach(b => b.onclick = () => {
            if (Date.now() - unitTapLock < 400) return;
            changeQty(+b.dataset.inc, 1);
        });
        cartLines.querySelectorAll('[data-dec]').forEach(b => b.onclick = () => changeQty(+b.dataset.dec, -1));
        cartLines.querySelectorAll('[data-hist]').forEach((b) => {
            b.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openOrderHistory(b.getAttribute('data-hist'), b.getAttribute('data-hist-name') || '');
            });
        });
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
        cartLines.querySelectorAll('input[data-price]').forEach(inp => {
            inp.addEventListener('focus', () => {
                inp.select();
                const i = +inp.dataset.price;
                if (!cart[i]) return;
                // Lock original list price before any edit (POS-style discount base)
                if (cart[i].list_unit_price == null) {
                    cart[i].list_unit_price = Number(cart[i].unit_price) || 0;
                }
                if (cart[i].catalog_base_price == null) {
                    const mult = Number(cart[i].multiplier) || 1;
                    cart[i].catalog_base_price = Number(cart[i].base_price) || ((Number(cart[i].unit_price) || 0) / mult);
                }
            });
            inp.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    inp.blur();
                }
            });
            inp.addEventListener('click', (e) => e.stopPropagation());
            inp.addEventListener('blur', () => {
                if (!canEditOrderPrice) return;
                const i = +inp.dataset.price;
                if (!cart[i]) return;
                let price = parseFloat(String(inp.value).replace(/[^0-9.]/g, ''));
                if (!isFinite(price) || price < 0) price = 0;
                price = Math.round(price * 100) / 100;
                const mult = Number(cart[i].multiplier) || 1;
                if (cart[i].list_unit_price == null) {
                    cart[i].list_unit_price = Number(cart[i].unit_price) || price;
                }
                if (cart[i].catalog_base_price == null) {
                    cart[i].catalog_base_price = (Number(cart[i].unit_price) || price) / (mult || 1);
                }
                cart[i].unit_price = price;
                cart[i].base_price = mult > 0 ? (price / mult) : price;
                renderCart();
            });
        });
        cartLines.querySelectorAll('input[data-unit]').forEach(inp => {
            inp.addEventListener('change', () => setCartUnit(+inp.dataset.unit, inp.value));
        });
        cartLines.querySelectorAll('input[data-browse-unit]').forEach(inp => {
            inp.addEventListener('change', () => {
                const r = browseRows[+inp.dataset.browseUnit];
                if (!r) return;
                r.sub_unit_id = inp.value;
                renderCart();
            });
        });
        if (!skipPersist) persistCart();
    }

    async function fetchJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        return Array.isArray(data) ? data : [];
    }

    if (customerSearch && customerResults && stepCustomer && !contactId.value) {
        loadCustomers('', customerResults, (r) => {
            setCustomer(r.id, r.text, r.shipping_address || r.address || '', true, r.credit_limit, r);
        });
    }

    if (productSearch) {
    productSearch.addEventListener('input', () => {
        clearTimeout(prodTimer);
        prodTimer = setTimeout(() => {
            if (productResults) {
                productResults.classList.add('hidden');
                productResults.innerHTML = '';
            }
            loadBrowse(productSearch.value.trim());
        }, 250);
    });
    }

    productSearch.addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        clearTimeout(prodTimer);
        const q = productSearch.value.trim();
        if (!q) return;
        if (skuMode === 'scan') {
            const isBarcode = /^\d{8,14}$/.test(q);
            const added = await addFromScanCode(q, !isBarcode);
            if (added || isBarcode) {
                productSearch.value = '';
                if (productResults) {
                    productResults.classList.add('hidden');
                    productResults.innerHTML = '';
                }
                loadBrowse('');
                return;
            }
        }
        const rows = await fetchJson(@json(route('sale.api.products')) + '?contact_id=' + encodeURIComponent((contactId && contactId.value) || '') + '&q=' + encodeURIComponent(q) + '&location_id=' + encodeURIComponent(locationId.value));
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
        productResults.classList.add('pp-grid');
        productResults.innerHTML = '';
        rows.forEach(r => {
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'pp-card product-pick';
            const thumb = r.has_image ? `<img src="${escapeHtml(r.image)}" alt="">` : '';
            a.innerHTML = `<div class="pp-img">${thumb}</div><div class="pp-body"><div class="pp-name">${escapeHtml(r.name)}</div>${r.sku ? `<div class="pp-code">Code: ${escapeHtml(r.sku)}</div>` : ''}<div class="pp-price">${priceLine(r)}</div></div>`;
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

    const termSelect = document.getElementById('payment_term_id');
    function termLabel() {
        const opt = termSelect && termSelect.value ? termSelect.options[termSelect.selectedIndex] : null;
        return opt ? opt.textContent.trim() : '—';
    }
    function syncTerms() {
        const terms = document.getElementById('coTerms');
        if (terms) terms.textContent = termLabel();
    }
    function fmtQty(n) {
        const x = Number(n);
        if (!isFinite(x)) return '0';
        return Math.abs(x - Math.round(x)) < 0.0001 ? String(Math.round(x)) : String(Math.round(x * 1000) / 1000);
    }
    function lineUnitName(l) {
        return (l.unit_name || 'Pc').toString();
    }
    function linePcs(l) {
        return Number(l.quantity) * (Number(l.multiplier) || 1);
    }
    function unitMixText(lines) {
        const parts = lines.map((l) => fmtQty(l.quantity) + ' ' + lineUnitName(l));
        const pcs = lines.reduce((s, l) => s + linePcs(l), 0);
        const hasPack = lines.some((l) => (Number(l.multiplier) || 1) > 1);
        let t = parts.join(' + ');
        if (hasPack) t += '  =  ' + fmtQty(pcs) + ' Pc';
        return t;
    }

    async function fillCheckoutSummary() {
        const soldTotal = cartSum();
        let listTotal = 0;
        let discTotal = 0;
        const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setTxt('coName', lastCustomer.display || lastCustomer.text || '');
        setTxt('coAcct', lastCustomer.acct ? ('Acct #: ' + lastCustomer.acct) : '');
        setTxt('coAddr', lastCustomer.address || lastCustomer.shipAddr || '');
        const lim = lastCustomer.credit;
        setTxt('coLimit', (lim === null || typeof lim === 'undefined' || lim === '') ? '$0.00' : money(lim));
        setTxt('coTotal', money(soldTotal));
        setTxt('coTerms', termLabel());
        const lines = document.getElementById('coLines');
        if (lines) {
            const groups = [];
            const idx = {};
            cart.filter((l) => Number(l.quantity) > 0).forEach((l) => {
                const key = String(l.variation_id || l.product_id || l.name);
                if (typeof idx[key] === 'undefined') {
                    idx[key] = groups.length;
                    groups.push({ name: l.name, rows: [], sold: 0, list: 0 });
                }
                const g = groups[idx[key]];
                g.rows.push(l);
                const qty = Number(l.quantity) || 0;
                const soldUnit = Number(l.unit_price) || 0;
                const catalogBase = Number(l.catalog_base_price != null ? l.catalog_base_price : l.base_price) || 0;
                const mult = Number(l.multiplier) || 1;
                let listUnit = (l.list_unit_price != null && l.list_unit_price !== '')
                    ? Number(l.list_unit_price)
                    : (catalogBase * mult);
                if (!isFinite(listUnit) || listUnit < soldUnit) listUnit = soldUnit;
                g.sold += qty * soldUnit;
                g.list += qty * listUnit;
                listTotal += qty * listUnit;
                discTotal += Math.max(0, qty * (listUnit - soldUnit));
            });
            lines.innerHTML = groups.map((g) => {
                const disc = Math.max(0, Math.round((g.list - g.sold) * 100) / 100);
                const pct = g.list > 0 ? Math.round((disc / g.list) * 1000) / 10 : 0;
                const priceSide = disc > 0.009
                    ? `<span class="sale-checkout__list">${money(g.list)}</span><strong>${money(g.sold)}</strong><em>Disc −${money(disc)}${pct > 0 ? ` (${pct}%)` : ''}</em>`
                    : `<strong>${money(g.sold)}</strong>`;
                return `<div class="sale-checkout__line">
                    <div>${escapeHtml(g.name)}<span>${escapeHtml(unitMixText(g.rows))}</span></div>
                    <div class="sale-checkout__price">${priceSide}</div>
                </div>`;
            }).join('');
        }
        const totals = document.getElementById('coTotals');
        const discRow = document.getElementById('coDiscRow');
        if (totals) totals.hidden = false;
        setTxt('coSubtotal', money(listTotal > 0 ? listTotal : soldTotal));
        setTxt('coDisc', '−' + money(discTotal));
        setTxt('coTotalInline', money(soldTotal));
        if (discRow) discRow.hidden = !(discTotal > 0.009);
        setTxt('coLast', money(lastCustomer.lastTotal || 0));
        setTxt('coOpen', money(lastCustomer.open || 0));
        syncShipTo();
    }
    async function showCheckoutStep() {
        if (!contactId.value) { alert('Select a customer'); return; }
        const hasQty = cart.some((l) => Number(l.quantity) > 0);
        if (!hasQty) { alert('Add at least one product'); return; }
        if (stepCustomer) stepCustomer.hidden = true;
        stepCart.hidden = true;
        if (stepShipping) stepShipping.hidden = true;
        if (stepCheckout) stepCheckout.hidden = false;
        document.body.classList.remove('sale-picking-customer', 'sale-building-order');
        document.body.classList.add('sale-checking-out');
        await fillCheckoutSummary();
    }

    document.getElementById('goShippingBtn').addEventListener('click', () => {
        showCheckoutStep();
    });

    document.getElementById('backToCartBtn')?.addEventListener('click', () => {
        showCartStep();
    });
    document.getElementById('backFromCheckoutBtn')?.addEventListener('click', checkoutClosePrompt);
    document.getElementById('placeOrderBtn')?.addEventListener('click', () => {
        syncShipTo();
        if (shipToSelect && shipToSelect.value === '-2') {
            const street = form.querySelector('[name="ship_to_address"]');
            if (street && !street.value.trim()) {
                alert('Enter the ship-to street address.');
                street.focus();
                return;
            }
        }
        form.requestSubmit();
    });

    // Continental order header: Ship-To, Terms, Ship Via, Route, Warehouse
    const shipToSelect = document.getElementById('ship_to_address_id');
    const shipToAddrs = @json(collect($default_customer['shipping_addresses'] ?? [])->keyBy('id'));
    const billAddr = {!! json_encode($default_customer['bill_to'] ?? (object) [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    function shipToText() {
        const v = shipToSelect ? shipToSelect.value : '0';
        if (v === '-2') {
            const get = (n) => (form.querySelector('[name="' + n + '"]')?.value || '').trim();
            return [get('ship_to_address'), get('ship_to_city'), get('ship_to_state'), get('ship_to_zip')].filter(Boolean).join(', ');
        }
        const a = shipToAddrs[v];
        const src = a || billAddr;
        return [src.address, src.city, src.state, src.zip].filter(Boolean).join(', ');
    }
    function syncShipTo() {
        if (!shipToSelect) return;
        const v = shipToSelect.value;
        const other = document.getElementById('coShipOther');
        if (other) other.hidden = v !== '-2';
        const text = shipToText();
        const preview = document.getElementById('coShipPreview');
        if (preview) {
            const a = shipToAddrs[v];
            const name = v === '-2' ? '' : (a ? a.name : billAddr.name);
            preview.textContent = v === '-2' ? '' : [name, text].filter(Boolean).join(' — ');
        }
        if (shippingAddress) shippingAddress.value = text;
        setTxt2('coAddr', text || lastCustomer.address || '');
    }
    function setTxt2(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }
    shipToSelect?.addEventListener('change', syncShipTo);
    document.getElementById('coShipOther')?.addEventListener('input', syncShipTo);
    termSelect?.addEventListener('change', syncTerms);
    document.getElementById('coLocation')?.addEventListener('change', (e) => {
        locationId.value = e.target.value;
    });
    syncShipTo();
    syncTerms();

    async function clearCart(keepUi) {
        cart.length = 0;
        clearDraftStorage();
        await clearDraftServer();
        if (!keepUi) renderCart();
    }

    async function discardCartAndLeave(go) {
        await clearCart(true);
        allowLeave = true;
        if (typeof go === 'function') go();
    }

    const backToCustomerBtn = document.getElementById('backToCustomerBtn');
    if (backToCustomerBtn) {
        backToCustomerBtn.addEventListener('click', () => confirmLeave(() => showCustomerStep(), {
            msg: 'Do you want to save this order?',
            leaveOnNo: true,
        }));
    }
    document.getElementById('saleAttnNo')?.addEventListener('click', async () => {
        const go = pendingLeave;
        const goNo = pendingLeaveNo;
        const leave = attnLeaveOnNo;
        hideAttn();
        if (typeof goNo === 'function') {
            goNo();
        } else if (leave && typeof go === 'function') {
            // NO = do not save — discard draft so cart does not come back
            await discardCartAndLeave(go);
        }
    });
    document.getElementById('saleAttnYes')?.addEventListener('click', async () => {
        await parkCart();
        allowLeave = true;
        const go = pendingLeave;
        hideAttn();
        if (typeof go === 'function') go();
    });
    document.getElementById('saleAttnSheet')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) hideAttn();
    });
    document.getElementById('saleSyncBtn')?.addEventListener('click', (e) => {
        if (allowLeave || !cartHasItems()) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        confirmLeave(() => {
            allowLeave = true;
            if (typeof window.saleAppSync === 'function') window.saleAppSync();
            else location.reload();
        }, {
            msg: 'Do you want to save this order?',
            leaveOnNo: true,
        });
    }, true);
    document.querySelectorAll('a.sale-tab, a.sale-side-link, header a[href]').forEach((a) => {
        a.addEventListener('click', (e) => {
            if (allowLeave || !cartHasItems()) return;
            const href = a.getAttribute('href');
            if (!href || href.startsWith('#') || href === location.href) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            confirmLeave(() => { location.href = href; }, {
                msg: 'Do you want to save this order?',
                leaveOnNo: true,
            });
        }, true);
    });
    try { history.pushState({ saleOrderDraft: 1 }, ''); } catch (err) {}
    window.addEventListener('popstate', (e) => {
        if (allowLeave || !cartHasItems()) return;
        history.pushState({ saleOrderDraft: 1 }, '');
        confirmLeave(() => {
            allowLeave = true;
            history.go(-2);
        }, {
            msg: 'Do you want to save this order?',
            leaveOnNo: true,
        });
    });

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

    // Last purchased quantities (existing sell/SO history)
    const useLastQtyToggle = document.getElementById('useLastQtyToggle');
    const lastQtyStatus = document.getElementById('lastQtyStatus');
    function setLastQtyStatus(msg, isError) {
        if (!lastQtyStatus) return;
        if (!msg) {
            lastQtyStatus.hidden = true;
            lastQtyStatus.textContent = '';
            lastQtyStatus.classList.remove('is-error');
            return;
        }
        lastQtyStatus.hidden = false;
        lastQtyStatus.textContent = msg;
        lastQtyStatus.classList.toggle('is-error', !!isError);
    }
    if (useLastQtyToggle) {
        useLastQtyToggle.addEventListener('change', async () => {
            if (!useLastQtyToggle.checked) {
                setLastQtyStatus('');
                return;
            }
            if (!contactId || !contactId.value) {
                useLastQtyToggle.checked = false;
                setLastQtyStatus('Select a customer first', true);
                return;
            }
            useLastQtyToggle.disabled = true;
            setLastQtyStatus('Loading last purchased quantities…');
            try {
                const loc = (locationId && locationId.value) ? locationId.value : '';
                const rows = await fetchJson(
                    @json(route('sale.api.last_purchases'))
                    + '?contact_id=' + encodeURIComponent(contactId.value)
                    + '&location_id=' + encodeURIComponent(loc)
                );
                if (!rows.length) {
                    useLastQtyToggle.checked = false;
                    setLastQtyStatus('No previous purchases for this customer', true);
                    return;
                }
                // Replace list with last order lines (system-wise fill)
                cart.length = 0;
                rows.forEach(r => {
                    addToCart(r, true, true);
                });
                renderCart();
                const scroll = document.getElementById('cartScroll');
                if (scroll) scroll.scrollTop = 0;
                setLastQtyStatus(rows.length + ' item(s) loaded from last purchase');
                showAddedMsg();
            } catch (err) {
                console.error(err);
                useLastQtyToggle.checked = false;
                setLastQtyStatus('Could not load last purchases', true);
            } finally {
                useLastQtyToggle.disabled = false;
            }
        });
    }

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
        catalogBody.classList.remove('pp-grid');
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
                    if (cat.sub_categories && cat.sub_categories.length) {
                        catalogStack.push({ level: 'subs', title: cat.name, catId: cat.id, subs: cat.sub_categories });
                    } else {
                        catalogStack.push({ level: 'products', title: cat.name, catId: cat.id, subId: 0 });
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
                catalogStack.push({ level: 'products', title: state.title, catId: state.catId, subId: 0 });
                renderCatalog();
            };
            catalogBody.appendChild(allBtn);
            (state.subs || []).forEach(sub => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'sale-catalog__row';
                b.innerHTML = `<span>${sub.name}</span><span class="sale-catalog__chev">›</span>`;
                b.onclick = () => {
                    catalogStack.push({ level: 'products', title: sub.name, catId: state.catId, subId: sub.id });
                    renderCatalog();
                };
                catalogBody.appendChild(b);
            });
            return;
        }

        const loc = locationId.value;
        let url = @json(route('sale.api.products')) + '?contact_id=' + encodeURIComponent((contactId && contactId.value) || '') + '&location_id=' + encodeURIComponent(loc) + '&limit=80';
        if (state.subId) url += '&sub_category_id=' + state.subId;
        else if (state.catId) url += '&category_id=' + state.catId;
        renderProductRows(await fetchJson(url));
    }

    function renderProductRows(rows) {
        if (!rows.length) {
            catalogBody.innerHTML = '<div class="px-3 py-4 text-sm text-slate-400">No products</div>';
            return;
        }
        catalogBody.innerHTML = '';
        catalogBody.classList.add('pp-grid');
        rows.forEach(r => {
            const b = document.createElement('button');
            b.type = 'button';
            const oos = Number(r.enable_stock) === 1 && Number(r.stock) <= 0;
            b.className = 'pp-card' + (oos ? ' is-oos' : '');
            const thumb = r.has_image ? `<img src="${escapeHtml(r.image)}" alt="">` : '';
            b.innerHTML = `<div class="pp-img">${thumb}</div><div class="pp-body"><div class="pp-name">${escapeHtml(r.name)}</div>${r.sku ? `<div class="pp-code">Code: ${escapeHtml(r.sku)}</div>` : ''}${oos ? '<div class="pp-oos">OUT OF STOCK</div>' : `<div class="pp-price">${priceLine(r)}</div>`}</div>`;
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

    document.getElementById('catalogCloseBtn')?.addEventListener('click', closeCatalog);
    catalogBackBtn?.addEventListener('click', () => {
        if (catalogStack.length > 1) { catalogStack.pop(); renderCatalog(); }
    });
    catalogModal?.addEventListener('click', (e) => { if (e.target === catalogModal) closeCatalog(); });

    const orderViewSheet = document.getElementById('orderViewSheet');
    function hideOrderView() {
        if (typeof saleCloseSheet === 'function') saleCloseSheet('orderViewSheet');
        else if (orderViewSheet) {
            orderViewSheet.hidden = true;
            orderViewSheet.classList.remove('is-open');
        }
    }
    document.getElementById('orderViewBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (orderViewSheet) orderViewSheet.hidden = false;
    });
    document.getElementById('orderViewClose')?.addEventListener('click', hideOrderView);
    document.getElementById('orderViewCancel')?.addEventListener('click', hideOrderView);
    orderViewSheet?.addEventListener('click', (e) => {
        const opt = e.target.closest('.sale-view-opt');
        if (opt) {
            e.preventDefault();
            e.stopPropagation();
            applyProductView(opt.getAttribute('data-view'), true);
            return;
        }
        if (e.target === orderViewSheet) hideOrderView();
    });
    try {
        applyProductView(localStorage.getItem('saleProductView') || 'item', false);
    } catch (e) {
        applyProductView('item', false);
    }

    document.getElementById('orderSortSheet')?.addEventListener('click', function (e) {
        const opt = e.target.closest('[data-sort]');
        if (!opt) {
            if (e.target === e.currentTarget) saleCloseSheet('orderSortSheet');
            return;
        }
        productSort = opt.getAttribute('data-sort') || 'name_asc';
        document.querySelectorAll('#orderSortSheet [data-sort]').forEach(function (btn) {
            btn.classList.toggle('is-on', btn.getAttribute('data-sort') === productSort);
        });
        saleCloseSheet('orderSortSheet');
        renderCart();
    });

    function paintCatChips() {
        const box = document.getElementById('orderCatChips');
        if (!box) return;
        const allOn = !orderCatId;
        let html = `<button type="button" class="sale-ois-chip${allOn ? ' is-on' : ''}" data-cat="">All Items</button>`;
        orderCategories.forEach(function (c) {
            html += `<button type="button" class="sale-ois-chip${String(orderCatId) === String(c.id) ? ' is-on' : ''}" data-cat="${c.id}">${escapeHtml(c.name)}</button>`;
        });
        box.innerHTML = html;
        box.querySelectorAll('.sale-ois-chip').forEach(function (btn) {
            btn.onclick = function () {
                orderCatId = btn.getAttribute('data-cat') || '';
                orderSubId = '';
                if (filterCat) filterCat.value = orderCatId;
                fillOrderSubs();
                paintCatChips();
                loadBrowse(productSearch ? productSearch.value.trim() : '');
            };
        });
    }
    const filterCat = document.getElementById('orderFilterCat');
    const filterSub = document.getElementById('orderFilterSub');
    function fillOrderSubs() {
        if (!filterSub || !filterCat) return;
        const id = filterCat.value;
        filterSub.innerHTML = '<option value="">All</option>';
        const found = orderCategories.find(function (c) { return String(c.id) === String(id); });
        const subs = found ? (found.sub_categories || []) : [];
        filterSub.disabled = !subs.length;
        subs.forEach(function (s) {
            const o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.name;
            filterSub.appendChild(o);
        });
        filterSub.value = '';
    }
    fetch(@json(route('sale.api.categories')), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            orderCategories = Array.isArray(rows) ? rows : [];
            paintCatChips();
            if (!filterCat) return;
            orderCategories.forEach(function (c) {
                const o = document.createElement('option');
                o.value = c.id;
                o.textContent = c.name;
                filterCat.appendChild(o);
            });
        })
        .catch(function () {});
    filterCat?.addEventListener('change', fillOrderSubs);
    document.getElementById('orderFilterApply')?.addEventListener('click', function () {
        orderCatId = filterCat ? filterCat.value : '';
        orderSubId = filterSub ? filterSub.value : '';
        saleCloseSheet('orderFilterSheet');
        paintCatChips();
        loadBrowse(productSearch ? productSearch.value.trim() : '');
    });
    document.getElementById('orderFilterReset')?.addEventListener('click', function () {
        orderCatId = '';
        orderSubId = '';
        if (filterCat) filterCat.value = '';
        fillOrderSubs();
        saleCloseSheet('orderFilterSheet');
        paintCatChips();
        loadBrowse(productSearch ? productSearch.value.trim() : '');
    });

    const orderMoreSheet = document.getElementById('orderMoreSheet');
    function hideOrderMore() { if (orderMoreSheet) orderMoreSheet.hidden = true; }
    function showOrderMore(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (orderMoreSheet) orderMoreSheet.hidden = false;
    }
    document.getElementById('orderMoreBtn')?.addEventListener('click', showOrderMore);
    document.getElementById('orderMoreClose')?.addEventListener('click', hideOrderMore);
    document.getElementById('orderMoreCancel')?.addEventListener('click', hideOrderMore);
    orderMoreSheet?.addEventListener('click', (e) => { if (e.target === orderMoreSheet) hideOrderMore(); });
    document.getElementById('orderMoreScan')?.addEventListener('click', () => {
        hideOrderMore();
        startSaleScan();
    });
    document.getElementById('orderCreditAccept')?.addEventListener('click', () => {
        hideCreditWarning();
        const fn = creditAfterAccept;
        creditAfterAccept = null;
        if (typeof fn === 'function') fn();
    });
    document.getElementById('orderCreditSheet')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            hideCreditWarning();
            creditAfterAccept = null;
        }
    });
    document.getElementById('orderMoreDocs')?.addEventListener('click', () => {
        hideOrderMore();
        @if(!empty($edit_order))
        window.open(@json(route('sale.orders.invoice', $edit_order->id)), '_blank');
        @else
        showCheckoutStep();
        @endif
    });

    const checkoutMoreSheet = document.getElementById('checkoutMoreSheet');
    function hideCheckoutMore() { if (checkoutMoreSheet) checkoutMoreSheet.hidden = true; }
    document.getElementById('checkoutMoreBtn')?.addEventListener('click', () => {
        const hist = document.getElementById('coHistory');
        if (hist && contactId.value) hist.href = @json(route('sale.orders')) + '?q=' + encodeURIComponent(lastCustomer.display || '');
        if (checkoutMoreSheet) checkoutMoreSheet.hidden = false;
    });
    document.getElementById('checkoutMoreClose')?.addEventListener('click', hideCheckoutMore);
    document.getElementById('checkoutMoreCancel')?.addEventListener('click', hideCheckoutMore);
    checkoutMoreSheet?.addEventListener('click', (e) => { if (e.target === checkoutMoreSheet) hideCheckoutMore(); });
    document.getElementById('coShare')?.addEventListener('click', async () => {
        hideCheckoutMore();
        const text = (lastCustomer.display || lastCustomer.text || 'Order') + ' · ' + (document.getElementById('coTotal')?.textContent || '');
        try {
            if (navigator.share) await navigator.share({ title: 'Order', text });
            else await navigator.clipboard.writeText(text);
        } catch (e) {}
    });
    document.getElementById('coPrint')?.addEventListener('click', () => {
        hideCheckoutMore();
        window.print();
    });
    document.getElementById('coDocs')?.addEventListener('click', () => {
        hideCheckoutMore();
        @if(!empty($edit_order))
        window.open(@json(route('sale.orders.invoice', $edit_order->id)), '_blank');
        @else
        document.getElementById('sale_note')?.focus();
        @endif
    });

    form.addEventListener('submit', (e) => {
        if (!contactId.value) { e.preventDefault(); alert('Select a customer'); return; }
        if (!cart.length) { e.preventDefault(); alert('Add at least one product'); return; }
        if (!shippingAddress.value.trim()) { e.preventDefault(); alert('Enter shipping address'); return; }
        productsJson.innerHTML = '';
        cart.forEach((line, i) => {
            ['product_id', 'variation_id', 'quantity', 'unit_price', 'sub_unit_id', 'transaction_sell_lines_id'].forEach(k => {
                if (k === 'transaction_sell_lines_id' && !line[k]) return;
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = `products[${i}][${k}]`;
                inp.value = line[k];
                productsJson.appendChild(inp);
            });
        });
        allowLeave = true;
        clearDraftStorage();
        clearDraftServer();
    });

    (async function prefillFromQuery() {
        const editLines = @json($edit_lines ?? []);
        if (editLines.length) {
            editLines.forEach(r => addToCart(r, true));
            skipPersist = false;
            await loadBrowse('');
            return;
        }
        restoreCart();
        loadParked();
        skipPersist = false;
        const params = new URLSearchParams(window.location.search);
        const vid = params.get('add');
        if (!vid || !locationId.value) {
            if (isEdit || (contactId && contactId.value)) await loadBrowse('');
            else renderCart();
            return;
        }
        const rows = await fetchJson(@json(route('sale.api.products')) + '?contact_id=' + encodeURIComponent((contactId && contactId.value) || '') + '&location_id=' + encodeURIComponent(locationId.value) + '&variation_id=' + encodeURIComponent(vid));
        if (rows[0]) addToCart(rows[0]);
        await loadBrowse('');
    })();
})();
</script>
@endpush
