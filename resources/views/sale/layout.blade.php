<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#e53935">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Sales">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="{{ url('/sale/pwa/manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('pwa/sale-icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('pwa/sale-icon-192.png') }}">
    <title>@yield('title', 'Sales App') — {{ config('app.name', 'JAPS POS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/pwa.css'])
    <script>
        window.saleOpenSheet = function (id, e) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            var sheet = document.getElementById(id);
            if (!sheet) return false;
            document.body.appendChild(sheet);
            sheet.hidden = false;
            sheet.classList.add('is-open');
            return false;
        };
        window.saleCloseSheet = function (id) {
            var sheet = document.getElementById(id);
            if (!sheet) return false;
            sheet.hidden = true;
            sheet.classList.remove('is-open');
            return false;
        };
        window.saleOpenOrderView = function (e) { return window.saleOpenSheet('orderViewSheet', e); };
    </script>
    <style>
        body { font-family: Inter, system-ui, sans-serif; }
        .bg-sale { background-color: #e53935; }
        .text-sale { color: #e53935; }
        .bg-sale-soft { background-color: #ffebee; }
        .text-sale-dark { color: #c62828; }
        .border-sale { border-color: #e53935; }
        .hover\:bg-sale-dark:hover, .active\:bg-sale-dark:active { background-color: #c62828; }
        .focus\:ring-sale:focus, .focus-visible\:ring-sale:focus-visible { --tw-ring-color: #e53935; }
        .text-sale-ink { color: #0b1220; }
        .border-sale { border-color: #e53935; }
        .hover\:bg-sale-dark:hover { background-color: #c62828; }
        .focus\:ring-sale:focus { --tw-ring-color: #e53935; }
        .sale-pw-wrap { position: relative; }
        .sale-pw-input { padding-right: 2.75rem; }
        .sale-pw-toggle {
            position: absolute; right: .35rem; top: 50%; transform: translateY(-50%);
            width: 2.25rem; height: 2.25rem; border: 0; background: transparent; color: #64748b;
            border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
        }
        .sale-pw-toggle:hover { color: #e53935; background: #ffebee; }
        @media (max-width: 1023px) {
            body.sale-page-chat .sale-main-app {
                padding: 0 !important; height: 100dvh; min-height: 100dvh; max-height: 100dvh; overflow: hidden;
            }
            body.sale-page-chat .sale-page { padding: 0 !important; max-width: none !important; height: 100%; overflow: hidden; }
            body.sale-page-chat header.sale-m-only { display: none !important; }
            body.sale-page-chat .sale-flash { display: none; }
            body.sale-page-chat,
            body.sale-page-chat .sale-desk-shell,
            body.sale-page-chat .sale-desk-main { overflow: hidden; height: 100dvh; max-height: 100dvh; }
        }
        @media (min-width: 1024px) {
            body.sale-page-chat .sale-main-app { padding: 0 !important; min-height: calc(100dvh - 64px); }
            body.sale-page-chat .sale-page { padding: 0 !important; height: calc(100dvh - 64px); }
        }
        .text-sale-dark { color: #c62828; }
        .bg-sale-soft { background-color: #ffebee; }
        .border-sale-line { border-color: #e2e8f0; }
        .min-h-\[100dvh\] { min-height: 100dvh; }
        .text-\[10px\] { font-size: 10px; }
        .text-\[11px\] { font-size: 11px; }
        .text-\[12px\] { font-size: 12px; }
        .text-\[15px\] { font-size: 15px; }
        .text-\[16px\] { font-size: 16px; }
        .text-white\/40 { color: rgba(255,255,255,.4); }
        .text-white\/50 { color: rgba(255,255,255,.5); }
        .text-white\/70 { color: rgba(255,255,255,.7); }
        .bg-white\/95 { background-color: rgba(255,255,255,.95); }
        .bg-\[\#0b1220\] { background-color: #0b1220; }
        .pt-\[env\(safe-area-inset-top\,0\)\] { padding-top: env(safe-area-inset-top, 0); }
        @media (min-width: 1024px) {
            .lg\:min-h-\[100dvh\] { min-height: 100dvh; }
            .lg\:w-\[44\%\] { width: 44%; }
            .lg\:flex { display: flex; }
        }
        .sale-page-title {
            font-size: 14px; font-weight: 700; color: #0b1220; line-height: 1.2;
        }
        .sale-goals h2, .sale-tx h2, .sale-rpt-list__title {
            font-size: 15px !important;
        }
        .sale-cust-card { font-size: 14px; }
        .sale-cust-card .font-extrabold { font-size: 15px; font-weight: 700; }
        .sale-input {
            width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: .75rem .9rem;
            font-size: .9375rem; background: #fff; outline: none; box-sizing: border-box;
        }
        .sale-input:focus { border-color: #e53935; box-shadow: 0 0 0 3px rgba(229,57,53,.15); }
        .sale-btn {
            background: #e53935; color: #fff; font-weight: 700; border: 0; border-radius: 12px;
            padding: .85rem 1.25rem; width: 100%; font-size: .95rem; cursor: pointer;
        }
        .sale-btn:disabled { opacity: .55; cursor: not-allowed; }
        .sale-btn-ghost {
            background: #fff; color: #e53935; border: 1px solid #cbd5e1; font-weight: 700;
            border-radius: 12px; padding: .85rem 1.25rem; width: 100%; cursor: pointer;
        }
        .sale-btn-sm {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background: #e53935; color: #fff; font-weight: 700; border: 0; border-radius: 10px;
            padding: .55rem 1rem; font-size: .875rem; cursor: pointer; text-decoration: none;
            width: auto;
        }
        .sale-card {
            background: #fff; border: 1px solid #e8edf5; border-radius: 18px; padding: 14px;
            box-shadow: 0 8px 24px rgba(15,23,42,.05);
        }
        .sale-badge {
            display: inline-flex; align-items: center; border-radius: 999px;
            padding: 3px 9px; font-size: 11px; font-weight: 700;
        }
        .sale-badge--paid { background: #ffebee; color: #c62828; }
        .sale-badge--due { background: #fee2e2; color: #991b1b; }
        .sale-badge--partial { background: #fef3c7; color: #92400e; }
        .sale-badge--draft { background: #e0f2fe; color: #075985; }
        .sale-badge--ordered { background: #e0f2fe; color: #075985; }
        .sale-badge--completed { background: #ffebee; color: #c62828; }

        /* App UI primitives */
        .sale-ico {
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0; width: 1em; height: 1em;
        }
        .sale-ico svg { width: 100%; height: 100%; display: block; }
        .sale-sec-title {
            display: flex; align-items: center; gap: 10px;
            font-size: 14px; font-weight: 800; color: #0b1220; margin-bottom: 12px;
        }
        .sale-sec-title > a.ml-auto {
            margin-left: auto;
            flex-shrink: 0;
        }
        .sale-sec-title__ico {
            width: 34px; height: 34px; border-radius: 10px;
            background: #ffebee; color: #e53935;
            display: flex; align-items: center; justify-content: center;
        }
        .sale-sec-title__ico svg { width: 18px; height: 18px; }
        .sale-order-row {
            display: flex; align-items: center; flex-wrap: wrap; gap: 12px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
            padding: 12px 14px; text-decoration: none; color: inherit;
            box-shadow: 0 1px 2px rgba(15,23,42,.03);
            transition: background .12s ease, border-color .12s ease;
        }
        .sale-order-row:active, .sale-order-row:hover { background: #f8fafc; border-color: #cbd5e1; }
        .sale-order-row__ico {
            width: 44px; height: 44px; border-radius: 12px;
            background: #fff5f5; color: #e53935;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sale-order-row__ico svg { width: 22px; height: 22px; }
        .sale-order-row__body { min-width: 0; flex: 1; }
        .sale-order-row__meta { min-width: 0; text-align: right; flex-shrink: 0; }
        .sale-menu-row {
            display: flex; align-items: center; gap: 12px;
            width: 100%; padding: 14px 4px; text-decoration: none; color: inherit;
            border: 0; background: transparent; border-bottom: 1px solid #f1f5f9;
            font: inherit; text-align: left; cursor: pointer;
        }
        .sale-menu-row:last-child { border-bottom: 0; }
        .sale-menu-row__ico {
            width: 40px; height: 40px; border-radius: 12px;
            background: #f1f5f9; color: #e53935;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sale-menu-row__ico svg { width: 20px; height: 20px; }
        .sale-menu-row__ico--danger { background: #fff1f2; color: #e11d48; }
        .sale-menu-row__text { flex: 1; min-width: 0; }
        .sale-menu-row__text strong { display: block; font-size: 14px; font-weight: 700; }
        .sale-menu-row__text small { display: block; font-size: 12px; color: #64748b; margin-top: 1px; }
        .sale-menu-row__chev { color: #94a3b8; font-size: 18px; font-weight: 700; }
        .sale-empty {
            text-align: center; padding: 36px 16px;
        }
        .sale-empty__ico {
            width: 64px; height: 64px; margin: 0 auto 12px; border-radius: 20px;
            background: #fff5f5; color: #e53935;
            display: flex; align-items: center; justify-content: center;
        }
        .sale-empty__ico svg { width: 30px; height: 30px; }
        .sale-page-tool {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            margin-bottom: 12px;
        }
        .sale-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 999px;
            padding: 6px 12px; font-size: 12px; font-weight: 700; color: #475569;
        }

        /* App pagination (no Bootstrap) */
        .sale-pager {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 10px 12px;
        }
        .sale-pager__meta {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }
        .sale-pager__btns {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        .sale-pager__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #e53935;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-sizing: border-box;
        }
        .sale-pager__btn:hover { background: #fff5f5; }
        .sale-pager__btn--active {
            background: #e53935;
            border-color: #e53935;
            color: #fff;
        }
        .sale-pager__btn--disabled {
            color: #94a3b8;
            border-color: #f1f5f9;
            background: #f8fafc;
            pointer-events: none;
        }

        /* —— Mobile app chrome —— */
        .sale-bottom-nav {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 80;
            background: rgba(255,255,255,.98); backdrop-filter: blur(12px);
            border-top: 1px solid #e2e8f0;
            padding-bottom: env(safe-area-inset-bottom, 0);
            box-shadow: 0 -4px 20px rgba(15,23,42,.06);
            display: block;
        }
        @media (min-width: 1024px) {
            .sale-bottom-nav { display: none !important; }
        }
        .sale-bottom-nav__inner {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            align-items: stretch;
            max-width: 560px;
            margin: 0 auto;
            padding: 4px 2px 6px;
            gap: 0;
        }
        .sale-tab {
            flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 3px; min-height: 58px; color: #64748b; font-size: 10px; font-weight: 700;
            text-decoration: none; background: transparent; border: 0;
            padding: 6px 2px;
            -webkit-tap-highlight-color: transparent;
            max-width: none;
        }
        .sale-tab svg {
            width: 22px; height: 22px; display: block; stroke: currentColor; fill: none;
            stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
        }
        .sale-tab.active { color: #e53935; }
        .sale-tab.active svg { stroke-width: 2.2; }
        .sale-tab.active span, .sale-tab.active { font-weight: 800; }
        .sale-tab-fab {
            width: 54px; height: 54px; border-radius: 999px;
            background: #e53935; color: #fff;
            display: flex; align-items: center; justify-content: center;
            margin-top: -22px; margin-bottom: 2px;
            box-shadow: 0 6px 16px rgba(229,57,53,.4);
            text-decoration: none;
        }
        .sale-tab-fab svg { width: 26px; height: 26px; stroke: #fff; stroke-width: 2.2; fill: none; }
        .sale-tab-fab.active,
        .sale-tab-fab:active { background: #0d5f59; }
        .sale-bottom-nav__center-label {
            font-size: 10px; font-weight: 700; color: #64748b; line-height: 1;
        }
        .sale-bottom-nav__center-label.active { color: #e11d48; }
        .sale-main-app {
            padding-bottom: calc(130px + env(safe-area-inset-bottom, 0)) !important;
            min-height: 100dvh;
            box-sizing: border-box;
        }
        @media (max-width: 1023px) {
            body.sale-authed .sale-main-app {
                padding-bottom: calc(130px + env(safe-area-inset-bottom, 0)) !important;
            }
            body.sale-page-order-show .sale-main-app,
            body.sale-page-create .sale-main-app {
                padding-bottom: calc(150px + env(safe-area-inset-bottom, 0)) !important;
            }
            body.sale-page-order-show .sale-order-actions {
                padding-bottom: 12px;
            }
            body.sale-page-order-show .sale-layout-2 {
                padding-bottom: 24px;
            }
            .sale-page {
                padding-bottom: 24px;
            }
            .sale-dash,
            .sale-prod-app,
            .sale-cart-flow,
            .sale-ship-flow {
                padding-bottom: 16px;
            }
        }

        /* Dashboard home — card stack (layout like mobile reference) */
        .sale-home {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 560px;
            margin: 0 auto;
            padding-bottom: 8px;
        }
        .sale-home-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
        }
        .sale-home-card__title {
            margin: 0 0 12px;
            font-size: 15px;
            font-weight: 800;
            color: #0b1220;
        }
        .sale-home-section-title {
            margin: 4px 2px 0;
            font-size: 15px;
            font-weight: 800;
            color: #0b1220;
        }
        .sale-home-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .sale-home-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .sale-home-ico {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sale-home-ico svg { width: 16px; height: 16px; display: block; }
        .sale-home-text {
            min-width: 0;
            flex: 1;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sale-home-roles { display: flex; flex-wrap: wrap; gap: 8px; }
        .sale-home-role-pill {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid #e53935;
            color: #e53935;
            background: #fff;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 700;
        }
        .sale-home-metric {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 36px;
        }
        .sale-home-metric--sub {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
        }
        .sale-home-metric--sub .sale-home-metric__label { margin-left: 0; }
        .sale-home-metric__label {
            flex: 1;
            min-width: 0;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }
        .sale-home-metric__value {
            font-size: 15px;
            font-weight: 800;
            color: #0b1220;
            font-variant-numeric: tabular-nums;
        }
        .sale-profile-menu > summary { list-style: none; }
        .sale-profile-menu > summary::-webkit-details-marker { display: none; }
        .sale-profile-panel { min-width: 200px; }

        /* Expense + Delivery screens (keep bottom nav) */
        .sale-exp__head, .sale-del__head {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            margin-bottom: 4px;
        }
        .sale-exp__title, .sale-del__title {
            margin: 0; font-size: 16px; font-weight: 700; color: #0b1220;
        }
        .sale-del__sub { margin: 2px 0 0; font-size: 13px; color: #64748b; font-weight: 600; }
        .sale-exp__add {
            display: inline-flex; align-items: center; justify-content: center;
            background: #e53935; color: #fff; border-radius: 10px;
            padding: 8px 12px; font-size: 11px; font-weight: 800; letter-spacing: .02em;
            text-decoration: none; white-space: nowrap;
        }
        .sale-exp__empty, .sale-del__empty {
            text-align: center; padding: 56px 16px 24px;
        }
        .sale-exp__empty-ico, .sale-del__empty-ico {
            width: 72px; height: 72px; margin: 0 auto 14px; border-radius: 20px;
            background: #f1f5f9; color: #94a3b8;
            display: flex; align-items: center; justify-content: center;
        }
        .sale-exp__empty-ico svg, .sale-del__empty-ico svg { width: 34px; height: 34px; }
        .sale-exp-upload { position: relative; display: block; cursor: pointer; }
        .sale-exp-upload input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .sale-exp-upload__box {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            border: 1.5px dashed #cbd5e1; border-radius: 14px; padding: 22px 12px;
            color: #64748b; background: #f8fafc; text-align: center; cursor: pointer;
        }
        .sale-exp-upload__box strong { font-size: 13px; color: #334155; }
        .sale-exp-upload__box small { font-size: 12px; color: #94a3b8; }

        /* Expense — customer picker (Create Order style list) */
        .sale-exp-customer {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
        }
        .sale-exp-customer__head {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 10px 12px 6px;
        }
        .sale-exp-customer__clear {
            border: 0; background: transparent; color: #e11d48;
            font-size: 12px; font-weight: 800; cursor: pointer; padding: 0;
        }
        .sale-exp-customer__clear.hidden { display: none !important; }
        .sale-exp-customer__chip {
            width: 100%; display: flex; align-items: center; gap: 12px;
            padding: 10px 12px 14px; border: 0; background: #fff;
            cursor: pointer; text-align: left; font-family: inherit;
        }
        .sale-exp-customer__chip.hidden { display: none !important; }
        .sale-exp-customer__chip-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
        .sale-exp-customer__chip-name { font-size: 14px; font-weight: 800; color: #0b1220; }
        .sale-exp-customer__chip-hint { font-size: 11px; font-weight: 600; color: #94a3b8; }
        .sale-exp-customer__search { padding: 0 10px 10px; }
        .sale-exp-customer__search.hidden { display: none !important; }
        .sale-exp-customer__searchbox {
            margin-bottom: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            padding: 0 12px;
            height: 42px;
            display: flex; align-items: center; gap: 8px;
        }
        .sale-exp-customer__searchbox svg { width: 18px; height: 18px; color: #94a3b8; flex-shrink: 0; }
        .sale-exp-customer__searchbox .sale-pick-search__input {
            flex: 1; min-width: 0; border: 0 !important; background: transparent !important;
            outline: none !important; box-shadow: none !important; padding: 0 !important;
            font-size: 14px; font-weight: 600; color: #0b1220; height: auto !important;
        }
        .sale-exp-customer__list {
            max-height: 260px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            background: #fff;
        }
        .sale-exp-customer__list .sale-pick-row { padding: 12px; }
        .sale-exp-customer__list .sale-pick-avatar {
            width: 40px; height: 40px; font-size: 12px;
            background: #ffebee; color: #e53935; border-radius: 999px;
            display: inline-flex; align-items: center; justify-content: center; font-weight: 800;
        }
        .sale-exp-customer__list .sale-pick-empty { padding: 16px; text-align: center; color: #94a3b8; font-size: 13px; font-weight: 600; }
        .sale-exp-customer__chip .sale-pick-avatar {
            width: 42px; height: 42px; border-radius: 999px;
            background: #ffebee; color: #e53935;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 13px; flex-shrink: 0;
        }
        .sale-del__dates {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
            padding: 12px; margin-top: 10px;
        }
        .sale-del__label {
            display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;
        }

        /* Dashboard */
        .sale-dash-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }
        .sale-stat {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
        }
        .sale-stat--primary {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
            border: 0;
            color: #fff;
        }
        .sale-stat--primary .sale-stat__label,
        .sale-stat--primary .sale-stat__sub { color: rgba(255,255,255,.8); }
        .sale-stat__label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
        .sale-stat__value { font-size: 1.35rem; font-weight: 800; margin-top: 4px; line-height: 1.2; }
        .sale-stat__sub { font-size: 12px; color: #94a3b8; margin-top: 4px; font-weight: 600; }
        .sale-dash-actions {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 10px;
        }
        .sale-action-tile {
            display: flex; align-items: center; gap: 10px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
            padding: 12px; text-decoration: none; color: inherit;
        }
        .sale-action-tile--main {
            background: #ffebee; border-color: #ffcdd2;
        }
        .sale-action-tile__icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: #e53935; color: #fff;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sale-action-tile__icon--soft {
            background: #e2e8f0; color: #e53935;
        }
        .sale-action-tile__text { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
        .sale-action-tile__text strong { font-size: 13px; font-weight: 800; }
        .sale-action-tile__text small { font-size: 11px; color: #64748b; font-weight: 600; }
        .sale-install {
            position: fixed;
            left: 0;
            right: 0;
            bottom: calc(72px + env(safe-area-inset-bottom, 0));
            top: auto;
            z-index: 100;
            padding: 0 16px 8px;
            pointer-events: none;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            box-sizing: border-box;
        }
        .sale-install[hidden] { display: none !important; }
        .sale-install__inner {
            pointer-events: auto;
            width: 100%;
            max-width: 420px;
            display: flex; align-items: center; gap: 10px;
            background: #0b1220; color: #fff; border-radius: 14px; padding: 12px 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,.35);
        }
        .sale-install__logo { width: 36px; height: 36px; border-radius: 9px; background: #e53935; }
        .sale-install__btn {
            flex-shrink: 0; background: #e53935; color: #fff; border: 0; border-radius: 8px;
            padding: 8px 12px; font-weight: 700; font-size: 12px; cursor: pointer;
        }
        .sale-install__close {
            width: 28px; height: 28px; border: 0; border-radius: 999px;
            background: rgba(255,255,255,.12); color: #fff; font-size: 16px; cursor: pointer;
        }
        body.sale-pwa-standalone .sale-install { display: none !important; }

        /* Mobile only / desktop only */
        .sale-m-only { display: block; }
        .sale-d-only { display: none !important; }
        .sale-nav-link {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 14px; border-radius: 10px; font-size: 14px; font-weight: 700;
            color: #475569; text-decoration: none;
        }
        .sale-nav-link:hover { background: #f1f5f9; color: #e53935; }
        .sale-nav-link.active { background: #ffebee; color: #e53935; }

        @media (min-width: 1024px) {
            body { background: #eef2f6; overflow-x: hidden; }
            .sale-m-only { display: none !important; }
            .sale-d-only { display: block !important; }
            .sale-d-flex { display: flex !important; }
            .sale-d-only.sale-desk-side {
                display: flex !important;
                flex-direction: column;
                height: 100dvh;
                min-height: 100dvh;
            }
            .sale-desk-side .sale-side-footer {
                margin-top: auto;
                padding-top: 16px;
                flex-shrink: 0;
            }
            .sale-bottom-nav { display: none !important; }
            .sale-main-app {
                padding: 0 !important;
                padding-bottom: 48px !important;
                min-height: calc(100dvh - 64px);
                width: 100%;
                max-width: none;
                box-sizing: border-box;
            }
            /* Install: bottom center — login full width; logged-in desktop offset for sidebar */
            .sale-install {
                left: 0;
                right: 0;
                top: auto;
                bottom: 24px;
                padding: 0 24px;
                align-items: flex-end;
                justify-content: center;
            }
            body.sale-authed .sale-install {
                left: 240px;
            }
            .sale-install__inner {
                width: 100%;
                max-width: 400px;
                min-width: 0;
            }
            .sale-btn { width: auto; min-width: 160px; }
            /* Shared desktop page frame — same left/right edge on every screen */
            .sale-page {
                display: block;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 24px 28px 40px !important;
                box-sizing: border-box;
            }
            body.sale-page-create .sale-page,
            body.sale-page-products .sale-page,
            body.sale-page-order-show .sale-page {
                padding: 24px 28px 40px !important;
                max-width: none !important;
                margin: 0 !important;
                width: 100% !important;
            }
            /* Kill mobile-centered column widths so content aligns with page padding */
            .sale-home,
            .sale-prod-app,
            .sale-cart-flow,
            .sale-ship-flow,
            .sale-pick-customer,
            .sale-pick-customer--embedded,
            .sale-create-form,
            .sale-exp-form,
            .sale-page > .max-w-xl,
            .sale-page > form.max-w-xl {
                max-width: none !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
            }
            .sale-home {
                gap: 16px;
            }
            .sale-home-card {
                border-radius: 16px;
            }
            .sale-card {
                display: block;
                width: 100% !important;
                max-width: none !important;
                box-sizing: border-box;
                padding: 20px;
                border-radius: 16px;
                box-shadow: 0 1px 2px rgba(15,23,42,.04);
            }
            .sale-desk-shell {
                display: flex;
                width: 100%;
                max-width: none;
                min-height: 100dvh;
                box-sizing: border-box;
            }
            .sale-desk-side {
                width: 240px;
                flex-shrink: 0;
                background: #0b1220;
                color: #fff;
                padding: 24px 16px;
                position: sticky;
                top: 0;
                height: 100dvh;
                display: flex;
                flex-direction: column;
            }
            .sale-desk-main {
                flex: 1 1 auto;
                min-width: 0;
                width: auto;
                max-width: none;
                box-sizing: border-box;
            }
            .sale-desk-top {
                height: 64px;
                background: #fff;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 28px;
                position: sticky;
                top: 0;
                z-index: 40;
            }
            .sale-side-link {
                display: flex; align-items: center; gap: 12px;
                padding: 10px 10px; border-radius: 12px;
                color: rgba(255,255,255,.72); text-decoration: none;
                font-weight: 600; font-size: 14px; margin-bottom: 6px;
            }
            .sale-side-link:hover { background: rgba(255,255,255,.08); color: #fff; }
            .sale-side-link.active { background: #e53935; color: #fff; }
            .sale-side-ico {
                width: 36px; height: 36px; border-radius: 10px;
                background: rgba(255,255,255,.1);
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0;
            }
            .sale-side-link.active .sale-side-ico { background: rgba(255,255,255,.18); }
            .sale-side-ico svg {
                width: 18px; height: 18px; display: block;
                stroke: currentColor; fill: none; stroke-width: 1.8;
                stroke-linecap: round; stroke-linejoin: round;
            }
            .sale-side-logout {
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
                padding: 11px 12px;
                border-radius: 10px;
                border: 1px solid rgba(255,255,255,.12);
                background: transparent;
                color: #fda4af;
                font-weight: 600;
                font-size: 14px;
                cursor: pointer;
                text-align: left;
                font-family: inherit;
            }
            .sale-side-logout:hover {
                background: rgba(244, 63, 94, .15);
                border-color: rgba(244, 63, 94, .35);
                color: #fecdd3;
            }
            .sale-table { width: 100%; border-collapse: collapse; font-size: 14px; }
            .sale-table th {
                text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .06em;
                color: #64748b; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-weight: 700;
            }
            .sale-table td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
            .sale-table tr:hover td { background: #f8fafc; }
            .sale-flash { margin: 12px 28px 0; max-width: none; }

            /* Desktop page grids — full main width */
            .sale-layout-2 {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(300px, 360px);
                gap: 24px;
                align-items: start;
                width: 100%;
            }
            .sale-layout-2 > * { min-width: 0; width: 100%; }
            .sale-create-form {
                display: block;
                width: 100%;
                max-width: none;
                margin: 0;
                min-height: calc(100dvh - 64px - 48px);
            }
            .sale-layout-create {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) 380px;
                gap: 24px;
                align-items: stretch;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                min-height: calc(100dvh - 64px - 72px);
                box-sizing: border-box;
            }
            .sale-layout-create > * { min-width: 0; width: 100%; }
            .sale-create-main {
                display: flex;
                flex-direction: column;
                gap: 16px;
                width: 100%;
                min-height: 100%;
            }
            .sale-create-products {
                flex: 1 1 auto;
                min-height: 420px;
                display: flex;
                flex-direction: column;
            }
            .sale-create-side {
                width: 100%;
            }
            .sale-layout-account {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
                gap: 24px;
                align-items: start;
                width: 100%;
            }
            .sale-layout-fields {
                display: grid;
                grid-template-columns: 220px minmax(0, 1fr);
                gap: 16px;
                align-items: start;
            }
            .sale-layout-fields .sale-field-full { grid-column: auto; }
            .sale-stack { display: flex; flex-direction: column; gap: 16px; width: 100%; }
            .sale-sticky-panel { position: sticky; top: 88px; }
            /* Create page: full workstation layout */
            body.sale-page-create .sale-main-app {
                padding-bottom: 0 !important;
                min-height: calc(100dvh - 64px);
                height: calc(100dvh - 64px);
                overflow: hidden;
            }
            body.sale-page-create .sale-page {
                padding: 24px 28px 24px !important;
                width: 100%;
                max-width: none !important;
                height: 100%;
                box-sizing: border-box;
                overflow: hidden;
            }
            body.sale-page-create .sale-create-form {
                height: 100%;
                min-height: 0;
                display: flex;
                flex-direction: column;
            }
            body.sale-page-create #stepCart.sale-cart-flow {
                max-width: none;
                margin: 0;
                width: 100%;
                height: 100%;
                min-height: 0;
                display: grid;
                grid-template-columns: minmax(0, 1fr) 360px;
                grid-template-rows: auto minmax(0, 1fr);
                gap: 16px;
                align-items: stretch;
            }
            body.sale-page-create #stepCart .sale-cart-customer {
                grid-column: 1 / -1;
                margin: 0;
            }
            body.sale-page-create #stepCart .sale-cart-panel {
                grid-column: 1;
                grid-row: 2;
                height: 100%;
                min-height: 0;
                display: flex;
                flex-direction: column;
                margin: 0;
            }
            body.sale-page-create #stepCart .sale-cart-scroll {
                flex: 1 1 auto;
                max-height: none;
                min-height: 0;
                overflow-y: auto;
            }
            body.sale-page-create #stepCart .sale-cart-footer {
                grid-column: 2;
                grid-row: 2;
                height: 100%;
                min-height: 0;
                margin: 0;
                padding: 20px !important;
                display: flex;
                flex-direction: column;
                gap: 14px;
                position: sticky;
                top: 0;
                align-self: stretch;
            }
            /* Old cart-footer layout only — do not stretch header SUBMIT */
            body.sale-page-create #stepCart .sale-cart-footer #goShippingBtn {
                margin-top: auto;
                margin-bottom: 0;
                width: 100% !important;
            }
            body.sale-page-create #stepCart.sale-order-build #goShippingBtn.pp-cart {
                width: 100% !important;
                min-width: 0 !important;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                flex: 0 0 auto;
            }
            body.sale-page-create #stepCart.sale-order-build #goShippingBtn:not(.pp-cart) {
                width: auto !important;
                min-width: 140px;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                flex: 0 0 auto;
            }
            body.sale-page-create #stepShipping.sale-ship-flow {
                max-width: none;
                width: 100%;
                flex: 1;
                min-height: 0;
                height: auto;
                overflow: auto;
                display: grid;
                grid-template-columns: minmax(0, 1fr) 360px;
                grid-template-rows: auto auto;
                gap: 12px 16px;
                align-items: start;
                align-content: start;
                justify-items: stretch;
            }
            body.sale-page-create #stepShipping.sale-ship-flow[hidden] {
                display: none !important;
            }
            body.sale-page-create #stepCart.sale-cart-flow[hidden] {
                display: none !important;
            }
            body.sale-page-create #stepCustomer.sale-pick-customer[hidden] {
                display: none !important;
            }
            body.sale-page-create #stepShipping > button#backToCartBtn {
                grid-column: 1 / -1;
                grid-row: 1;
                margin: 0 0 4px;
                align-self: start;
            }
            body.sale-page-create #stepShipping > .sale-card {
                grid-column: 1;
                grid-row: 2;
                margin: 0 !important;
                align-self: start;
            }
            body.sale-page-create #stepShipping .sale-create-bar {
                grid-column: 2;
                grid-row: 2;
                position: sticky;
                top: 16px;
                margin: 0;
                padding: 20px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                align-self: start;
            }
            body.sale-page-create #stepShipping .sale-create-bar .sale-btn {
                width: 100% !important;
            }
            .sale-dash-stats {
                grid-template-columns: repeat(4, 1fr);
            }
            .sale-stat--primary { grid-column: auto; }
            .sale-dash-actions {
                grid-template-columns: 320px 220px;
            }
        }

        @media (max-width: 1023px) {
            .sale-d-flex { display: none !important; }
            .sale-desk-shell { display: block; }
            .sale-desk-side, .sale-desk-top { display: none !important; }
        }

        .product-pick:active, .cust-pick:active { background: #f8fafc; }

        /* Mobile create stack (overridden by desktop grid) */
        .sale-create-form { width: 100%; }
        .sale-layout-create {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }
        .sale-create-main { display: flex; flex-direction: column; gap: 12px; width: 100%; }
        .sale-create-side { width: 100%; }
        .sale-layout-fields { display: block; width: 100%; }

        .sale-order-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            width: 100%;
            margin-top: 2px;
        }
        .sale-order-actions form { display: block; margin: 0; min-width: 0; }
        .sale-act {
            display: inline-flex; align-items: center; justify-content: center;
            width: 100%; box-sizing: border-box;
            font-size: 12px; font-weight: 800; border-radius: 8px;
            padding: 8px 8px; text-decoration: none; border: 1px solid transparent;
            cursor: pointer; background: #f1f5f9; color: #334155; line-height: 1.2;
        }
        .sale-act--view { background: #fff5f5; color: #e53935; border-color: #ffcdd2; }
        .sale-act--edit { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
        .sale-act--dl { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .sale-act--del { background: #fff1f2; color: #e11d48; border-color: #fecdd3; }
        .sale-act.is-disabled,
        .sale-btn.is-disabled {
            opacity: .42;
            cursor: not-allowed;
            pointer-events: none;
            filter: grayscale(.25);
            box-shadow: none;
        }
        button.sale-act { font-family: inherit; }
        @media (min-width: 1024px) {
            .sale-order-row {
                display: grid;
                grid-template-columns: auto minmax(0, 1fr) auto;
                grid-template-rows: auto auto;
                align-items: center;
                flex-wrap: nowrap;
            }
            .sale-order-row__ico { grid-column: 1; grid-row: 1 / span 2; }
            .sale-order-row__body { grid-column: 2; grid-row: 1 / span 2; }
            .sale-order-row__meta { grid-column: 3; grid-row: 1; }
            .sale-order-actions {
                grid-column: 3;
                grid-row: 2;
                width: auto;
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-end;
                margin-top: 8px;
            }
            .sale-order-actions form { display: inline; }
            .sale-act {
                width: auto;
                font-size: 11px;
                padding: 5px 8px;
            }
        }

        /* Dashboard product grids */
        .sale-loc-pill {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff5f5; color: #e53935; border: 1px solid #ffcdd2;
            border-radius: 999px; padding: 8px 12px; font-size: 12px; font-weight: 800;
            text-decoration: none; max-width: 100%;
        }
        .sale-prod-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        /* Dashboard: one line only */
        .sale-prod-grid--row > .sale-prod-tile:nth-child(n+4) {
            display: none;
        }
        .sale-prod-tile {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
            padding: 8px; text-decoration: none; color: inherit;
            display: flex; flex-direction: column; gap: 6px;
            box-shadow: 0 1px 2px rgba(15,23,42,.03);
            min-height: 0;
            position: relative;
        }
        .sale-prod-tile__media {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 10px;
            overflow: hidden;
            background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
        }
        .sale-prod-tile__media img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .sale-prod-tile__media.is-placeholder {
            background:
                linear-gradient(45deg, #e2e8f0 25%, transparent 25%),
                linear-gradient(-45deg, #e2e8f0 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #e2e8f0 75%),
                linear-gradient(-45deg, transparent 75%, #e2e8f0 75%);
            background-size: 16px 16px;
            background-position: 0 0, 0 8px, 8px -8px, -8px 0;
            background-color: #f8fafc;
            color: #94a3b8;
        }
        .sale-prod-tile__media.is-placeholder svg { width: 28px; height: 28px; opacity: .85; }
        .sale-prod-tile__name {
            font-size: 11px; font-weight: 800; line-height: 1.25;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            min-height: 2.4em;
        }
        .sale-prod-tile__meta { font-size: 10px; font-weight: 700; color: #64748b; margin-top: -2px; }
        .sale-prod-tile__price { font-size: 12px; font-weight: 800; color: #111; }
        .sale-prod-tile__add {
            display: inline-flex; align-items: center; justify-content: center;
            background: #e53935; color: #fff; border-radius: 8px;
            font-size: 11px; font-weight: 800; padding: 6px 8px; text-align: center;
        }
        .sale-prod-app__list { display: flex; flex-direction: column; gap: 10px; }
        .sale-prod-card {
            display: grid;
            grid-template-columns: 76px minmax(0, 1fr) auto;
            gap: 10px 12px;
            align-items: start;
            background: #fff; border: 1px solid #e8e8e8; border-radius: 12px;
            padding: 12px; box-shadow: 0 1px 2px rgba(15,23,42,.03);
        }
        .sale-prod-card__media {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
        }
        .sale-prod-card__thumb {
            width: 72px; height: 72px; border-radius: 10px; flex-shrink: 0;
            overflow: hidden; background: #f8fafc;
            display: flex; align-items: center; justify-content: center;
            color: #94a3b8;
        }
        .sale-prod-card__thumb img { width: 100%; height: 100%; object-fit: contain; display: block; background: #fff; }
        .sale-prod-card__thumb.is-placeholder {
            background:
                linear-gradient(45deg, #e2e8f0 25%, transparent 25%),
                linear-gradient(-45deg, #e2e8f0 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #e2e8f0 75%),
                linear-gradient(-45deg, transparent 75%, #e2e8f0 75%);
            background-size: 12px 12px;
            background-position: 0 0, 0 6px, 6px -6px, -6px 0;
            background-color: #f8fafc;
        }
        .sale-prod-card__thumb svg { width: 22px; height: 22px; }
        .sale-prod-card__stock {
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 999px; padding: 3px 8px; font-size: 10px; font-weight: 800;
            color: #fff; white-space: nowrap; line-height: 1.2;
        }
        .sale-prod-card__stock.in { background: #43a047; }
        .sale-prod-card__stock.out { background: #e53935; }
        .sale-prod-card__info { min-width: 0; padding-top: 2px; }
        .sale-prod-card__name {
            font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.35;
        }
        .sale-prod-card__price {
            font-size: 13px; font-weight: 800; color: #111; margin-top: 6px;
        }
        .sale-prod-card__units {
            font-size: 12px; font-weight: 600; color: #64748b; margin-top: 4px; line-height: 1.4;
        }
        .sale-prod-card__code {
            font-size: 12px; font-weight: 700; color: #334155; margin-top: 4px;
        }
        .sale-prod-card__add {
            align-self: center;
            flex-shrink: 0; background: #e53935; color: #fff; text-decoration: none;
            font-weight: 800; font-size: 13px; border-radius: 10px; padding: 8px 14px;
            line-height: 1;
        }
        @media (min-width: 1024px) {
            .sale-prod-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 14px;
            }
            .sale-prod-grid--row > .sale-prod-tile:nth-child(n+4) {
                display: flex;
            }
            .sale-prod-grid--row > .sale-prod-tile:nth-child(n+7) {
                display: none;
            }
            .sale-prod-tile__name { font-size: 13px; min-height: 2.5em; }
            .sale-prod-tile__meta { font-size: 11px; }
            .sale-prod-tile__price { font-size: 14px; }
            .sale-prod-tile__media.is-placeholder svg { width: 36px; height: 36px; }
        }

        /* Create order: Select Customer */
        .sale-pick-customer {
            display: flex;
            flex-direction: column;
            width: 100%;
            margin: 0;
            background: #fff;
            min-height: calc(100dvh - 72px - env(safe-area-inset-bottom, 0px));
            box-sizing: border-box;
        }
        .sale-pick-customer[hidden] { display: none !important; }
        .sale-pick-customer__head {
            background: #e53935;
            color: #fff;
            padding: 16px 16px 18px;
        }
        .sale-pick-customer__title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.2;
        }
        .sale-pick-customer__sub {
            margin: 4px 0 0;
            font-size: .875rem;
            font-weight: 500;
            opacity: .92;
        }
        .sale-pick-modes {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            flex-wrap: nowrap;
            padding: 12px 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
        }
        .sale-pick-mode {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
            flex: 1 1 auto;
            justify-content: center;
            min-width: 0;
        }
        .sale-pick-mode input[type="radio"] {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            margin: 0;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            background: #fff;
            flex-shrink: 0;
            box-sizing: border-box;
            vertical-align: middle;
        }
        .sale-pick-mode input[type="radio"]:checked {
            border-color: #e53935;
            background:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' d='M3.5 8.5l3 3 6-6'/%3E%3C/svg%3E")
                center / 12px 12px no-repeat,
                #e53935;
        }
        .sale-pick-mode__dot { display: none !important; }
        .sale-pick-search {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 12px 12px 8px;
            padding: 0 12px;
            height: 44px;
            border-radius: 12px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            box-sizing: border-box;
        }
        .sale-pick-search svg {
            width: 18px; height: 18px; color: #94a3b8; flex-shrink: 0;
        }
        .sale-pick-search__input {
            flex: 1;
            border: 0 !important;
            background: transparent !important;
            outline: none !important;
            box-shadow: none !important;
            font-size: 15px;
            font-weight: 600;
            color: #0b1220;
            min-width: 0;
            padding: 0 !important;
            height: auto !important;
            border-radius: 0 !important;
        }
        .sale-pick-search__input::placeholder { color: #94a3b8; font-weight: 500; }
        .sale-pick-list {
            flex: 1 1 auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px));
            background: #fff;
            min-height: 0;
        }
        .sale-pick-row {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border: 0;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            box-sizing: border-box;
        }
        .sale-pick-row:active { background: #f8fafc; }
        .sale-pick-avatar {
            width: 44px; height: 44px; border-radius: 999px;
            background: #ffebee; color: #e53935;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 13px; flex-shrink: 0;
            letter-spacing: .02em;
        }
        .sale-pick-meta { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
        .sale-pick-name {
            font-weight: 800; font-size: 14px; color: #0b1220;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sale-pick-addr {
            font-size: 12px; color: #64748b; font-weight: 500;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sale-pick-chev { color: #94a3b8; font-size: 22px; line-height: 1; flex-shrink: 0; font-weight: 400; }
        .sale-pick-empty { padding: 28px 16px; text-align: center; color: #94a3b8; font-size: 14px; font-weight: 600; }

        /* Create Order — build step (after customer): system Pickup/Delivery/Shipping + SKU/catalog */
        .sale-order-build {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
            max-height: 100%;
            min-height: 0;
            margin: 0;
            background: #fff;
            overflow: hidden;
        }
        .sale-order-build[hidden] { display: none !important; }
        .sale-order-build__bar {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #fff;
            color: #111;
            border-bottom: 1px solid #eee;
            padding: 8px 8px 8px 4px;
            padding-top: calc(8px + env(safe-area-inset-top, 0px));
            position: sticky;
            top: 0;
            z-index: 30;
            flex-shrink: 0;
        }
        .sale-order-build__titles {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            pointer-events: none;
        }
        .sale-order-cart-ico { margin-left: auto; }
        .sale-order-build__iconbtn {
            border: 0; background: transparent; color: #334155;
            width: 40px; height: 40px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; flex-shrink: 0;
        }
        .sale-order-build__titles { flex: 1 1 auto; min-width: 0; }
        .sale-order-build__title { font-size: 15px; font-weight: 700; line-height: 1.2; color: #e11d48; }
        .sale-order-build__sub { font-size: 12px; font-weight: 600; margin-top: 2px; color: #64748b; }
        .sale-order-cart-badge {
            min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
            background: #e53935; color: #fff; font-size: 10px; font-weight: 800;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .sale-order-build__checkout {
            border: 0;
            background: #e53935;
            color: #fff;
            font-weight: 800;
            font-size: 16px;
            border-radius: 999px;
            padding: 12px 28px;
            cursor: pointer;
            min-width: 148px;
        }
        .sale-order-build__total-label {
            font-size: 12px; font-weight: 800; color: #e53935; letter-spacing: .04em;
        }
        .sale-ois-search {
            display: flex; align-items: center; gap: 8px;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 12px; flex: 1;
        }
        .sale-ois-search svg { width: 18px; height: 18px; flex-shrink: 0; }
        .sale-ois-search input {
            flex: 1; border: 0; background: transparent; outline: none; font-size: 15px; color: #222;
        }
        .sale-ois-toolbar {
            display: flex; align-items: center; gap: 8px; flex-shrink: 0;
        }
        .sale-ois-toolbar__ico {
            width: 36px; height: 36px; border: 0; background: transparent; color: #444;
            display: inline-flex; align-items: center; justify-content: center; padding: 0; cursor: pointer;
        }
        .sale-scan {
            position: fixed; inset: 0; z-index: 12000;
            background: #fff;
            display: flex; flex-direction: column;
        }
        .sale-scan[hidden] { display: none !important; }
        .sale-scan__bar {
            display: flex; align-items: center; justify-content: center;
            position: relative;
            padding: 12px 48px 10px;
            padding-top: calc(12px + env(safe-area-inset-top, 0px));
            border-bottom: 1px solid #f3f4f6;
            flex-shrink: 0;
        }
        .sale-scan__back {
            position: absolute; left: 4px; top: 50%; transform: translateY(-50%);
            margin-top: calc(env(safe-area-inset-top, 0px) / 2);
            width: 44px; height: 44px; border: 0; background: transparent;
            font-size: 26px; color: #111; cursor: pointer;
        }
        .sale-scan__title { font-size: 17px; font-weight: 600; color: #111; }
        .sale-scan__cam {
            position: relative; height: 168px; flex-shrink: 0;
            background: #111; overflow: hidden;
        }
        .sale-scan__cam #saleScanReader,
        .sale-scan__cam #saleScanReader video {
            width: 100% !important; height: 168px !important; object-fit: cover;
        }
        .sale-scan__cam #saleScanReader img { display: none !important; }
        .sale-scan__laser {
            position: absolute; left: 0; right: 0; top: 50%; height: 2px;
            background: #e53935; box-shadow: 0 0 10px #e53935; pointer-events: none; z-index: 2;
        }
        .sale-scan__guide {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 28px 16px 10px; color: #9ca3af;
        }
        .sale-scan__guide-ico {
            width: 56px; height: 56px; border: 1.5px dashed #d1d5db; border-radius: 999px;
            display: flex; align-items: center; justify-content: center;
        }
        .sale-scan__guide-ico.is-box { border-radius: 10px; }
        .sale-scan__guide svg { width: 28px; height: 28px; }
        .sale-scan__chev { color: #e53935; font-size: 18px; font-weight: 800; }
        .sale-scan__empty {
            text-align: center; color: #c4c4c4; font-size: 15px; padding: 8px 24px 16px;
        }
        .sale-scan__empty[hidden], .sale-scan__list[hidden] { display: none !important; }
        .sale-scan__list {
            flex: 1; overflow-y: auto; padding: 0 12px 8px;
        }
        .sale-scan__row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; padding: 10px 0; border-bottom: 1px solid #f3f4f6;
        }
        .sale-scan__row-name { font-size: 14px; font-weight: 700; color: #111; }
        .sale-scan__row-meta { font-size: 12px; color: #888; }
        .sale-scan__torch {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            margin-top: calc(env(safe-area-inset-top, 0px) / 2);
            border: 1px solid #e5e7eb; border-radius: 999px; background: #fff;
            padding: 6px 12px; font-size: 13px; font-weight: 700; color: #e53935; cursor: pointer;
        }
        .sale-scan__torch[hidden] { display: none !important; }
        .sale-scan__status {
            min-height: 20px; padding: 8px 16px 0; text-align: center;
            font-size: 13px; font-weight: 700; color: #e53935;
        }
        .sale-scan-miss {
            position: absolute; inset: 0; z-index: 5;
            display: flex; align-items: center; justify-content: center;
            padding: 20px; background: rgba(17, 17, 17, .6);
        }
        .sale-scan-miss[hidden] { display: none !important; }
        .sale-price-alert { position: fixed; z-index: 13000; }
        .sale-scan-miss__card {
            width: 100%; max-width: 320px; background: #fff; color: #111;
            border-radius: 16px; padding: 18px 16px 14px; text-align: center;
        }
        .sale-scan-miss__title { font-size: 17px; font-weight: 800; margin: 0 0 8px; }
        .sale-scan-miss__text { font-size: 14px; color: #555; margin: 0 0 16px; line-height: 1.4; }
        .sale-scan-miss__ok {
            width: 100%; border: 0; border-radius: 12px; padding: 12px;
            background: #e53935; color: #fff; font-weight: 800; font-size: 15px; cursor: pointer;
        }
        .sale-scan .sale-order-build__checkout.is-off { background: #bdbdbd; }
        .sale-scan .sale-order-build__total { margin-top: auto; }
        .sale-act-row__ico { position: relative; display: inline-flex; }
        .sale-credit-on {
            position: absolute; top: -6px; right: -10px;
            font-size: 9px; font-weight: 800; color: #e53935; letter-spacing: .04em;
        }
        .sale-credit-on[hidden] { display: none !important; }
        .sale-sheet--dark { background: rgba(0,0,0,.55); }
        .sale-sheet--dark .sale-sheet__panel { background: #1c1c1c; }
        .sale-sheet--dark .sale-sheet__title,
        .sale-sheet--dark .sale-act-row,
        .sale-sheet--dark .sale-sheet__close { color: #fff; }
        .sale-sheet--dark .sale-sheet__close { background: transparent; }
        .sale-sheet--dark .sale-more-cancel { background: transparent; color: #fff; }
        .sale-checkout {
            display: flex; flex-direction: column; flex: 1; height: 100%; min-height: 0; background: #f4f5f7;
        }
        .sale-checkout[hidden] { display: none !important; }
        .sale-checkout__bar {
            display: flex; align-items: center; gap: 4px;
            padding: 8px 8px 8px 4px;
            background: #fff;
            border-bottom: 1px solid #eceff3; flex-shrink: 0;
        }
        .sale-checkout__title { flex: 1; text-align: center; font-size: 17px; font-weight: 800; color: #111; }
        .sale-checkout__body {
            flex: 1; overflow-y: auto; padding: 12px 12px 16px;
            padding-bottom: 8px;
        }
        .sale-co-card {
            background: #fff; border-radius: 16px; padding: 14px 14px 12px;
            box-shadow: 0 8px 24px rgba(15,23,42,.04); margin-bottom: 12px;
        }
        .sale-checkout__fields { display: flex; flex-direction: column; gap: 12px; padding: 2px 0 2px; }
        .sale-checkout__fields label { font-size: 12px; font-weight: 700; color: #64748b; display: flex; flex-direction: column; gap: 6px; }
        .sale-checkout__fields .sale-input { border-radius: 12px; background: #f8fafc; border-color: #e8edf3; }
        .sale-checkout__lines-title { font-size: 14px; font-weight: 800; margin: 0 0 10px; }
        .sale-checkout__name { font-size: 20px; font-weight: 800; color: #0b1220; letter-spacing: -.02em; }
        .sale-checkout__acct { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .sale-checkout__addr { font-size: 13px; color: #64748b; margin-top: 6px; }
        .sale-checkout__stats {
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;
            margin-bottom: 12px;
        }
        .sale-co-stat {
            background: #fff; border-radius: 14px; padding: 12px 8px; text-align: center;
            box-shadow: 0 8px 24px rgba(15,23,42,.04);
        }
        .sale-checkout__stats strong { display: block; font-size: 14px; font-weight: 800; color: #111; }
        .sale-checkout__stats span { font-size: 11px; color: #94a3b8; font-weight: 600; }
        .sale-checkout__meta {
            display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;
        }
        .sale-checkout__meta > div {
            background: #fff; border-radius: 999px; padding: 8px 12px;
            font-size: 12px; color: #64748b; font-weight: 600;
            box-shadow: 0 4px 12px rgba(15,23,42,.04);
        }
        .sale-checkout__meta b { color: #0b1220; font-weight: 800; }
        #coTerms { color: #e53935; }
        .sale-checkout__lines { display: flex; flex-direction: column; gap: 10px; }
        .sale-checkout__line { display: flex; justify-content: space-between; gap: 10px; font-size: 14px; font-weight: 700; }
        .sale-checkout__line span { color: #94a3b8; font-size: 12px; font-weight: 600; display: block; }
        .sale-checkout__line em { font-style: normal; color: #e53935; font-weight: 700; display: block; font-size: 11px; margin-top: 2px; }
        .sale-checkout__price { text-align: right; }
        .sale-checkout__list { display: block; font-size: 11px; color: #94a3b8; text-decoration: line-through; font-weight: 600; }
        .sale-checkout__totals { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 6px; }
        .sale-checkout__tot { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; font-weight: 700; color: #64748b; }
        .sale-checkout__tot strong { color: #0f172a; font-variant-numeric: tabular-nums; }
        .sale-checkout__tot.is-disc strong { color: #e53935; }
        .sale-checkout__tot.is-total { font-size: 15px; color: #0f172a; padding-top: 4px; }
        .sale-checkout__tot.is-total strong { color: #0E3F34; }
        .sale-checkout .sale-order-build__total {
            background: #fff;
            box-shadow: 0 -8px 24px rgba(15,23,42,.06);
            padding: 10px 12px 12px;
        }
        body.sale-checking-out .sale-m-only.sticky { display: none !important; }
        body.sale-checking-out .sale-bottom-nav { display: block !important; }
        @media (min-width: 1024px) {
            body.sale-checking-out .sale-bottom-nav { display: none !important; }
        }
        body.sale-checking-out .sale-main-app {
            padding: 0 !important;
            padding-bottom: calc(68px + env(safe-area-inset-bottom, 0px)) !important;
            margin: 0 !important; overflow: hidden !important;
            min-height: 100dvh !important; height: 100dvh !important; background: #f4f5f7 !important;
            display: flex; flex-direction: column;
        }
        body.sale-checking-out .sale-page,
        body.sale-checking-out .sale-create-form {
            flex: 1; min-height: 0; display: flex; flex-direction: column; overflow: hidden; margin: 0; padding: 0;
        }
        .sale-ois-h1 { font-size: 13px; font-weight: 700; color: #e53935; line-height: 1.1; }
        .sale-ois-h2 { font-size: 20px; font-weight: 800; color: #222; line-height: 1.15; }
        .sale-ois-idbar {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 8px;
            padding: 4px 12px 8px;
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            flex-shrink: 0;
        }
        .sale-ois-idbar__biz {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #334155;
            font-weight: 700;
        }
        .sale-ois-idbar__dot {
            width: 7px; height: 7px; border-radius: 999px; background: #16a34a; flex-shrink: 0;
        }
        .sale-ois-idbar__sync { color: #16a34a; flex-shrink: 0; }
        .sale-ois-idbar__av {
            margin-left: auto;
            width: 22px; height: 22px; border-radius: 999px;
            background: #e53935; color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 800; flex-shrink: 0;
        }
        .sale-ois-idbar__rep { color: #334155; flex-shrink: 0; }
        .sale-ois-chips {
            display: flex; gap: 8px; overflow-x: auto; padding: 8px 0 4px;
            -webkit-overflow-scrolling: touch; flex-shrink: 0;
        }
        .sale-ois-chip {
            flex: 0 0 auto; border: 1px solid #e2e8f0; background: #fff; color: #334155;
            border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 700;
        }
        .sale-ois-chip.is-on { background: #e53935; border-color: #e53935; color: #fff; }
        .sale-ois-units {
            display: block;
            width: auto;
            max-width: 100%;
            margin: 6px 0 4px;
            grid-column: auto;
        }
        .sale-ois-unitbtns {
            display: inline-flex;
            align-items: stretch;
            width: auto;
            max-width: 100%;
            background: #eceff1;
            border-radius: 999px;
            padding: 2px;
            gap: 0;
            box-sizing: border-box;
            overflow: hidden;
            flex-wrap: wrap;
            row-gap: 2px;
        }
        .sale-ois-unitbtn {
            flex: 0 0 auto;
            border: 0;
            background: transparent;
            color: #546e7a;
            position: relative;
            z-index: 1;
            min-height: 24px;
            min-width: 52px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1.05;
            padding: 3px 10px;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .sale-ois-unitbtn.is-on { z-index: 0; }
        .sale-ois-unitbtn__price {
            font-size: 10px;
            font-weight: 700;
            margin-top: 0;
            opacity: .9;
        }
        .sale-ois-unitbtn--piece,
        .sale-ois-unitbtn--pack,
        .sale-ois-unitbtn--case {
            border: 0;
            background: transparent;
            color: #546e7a;
        }
        .sale-ois-unitbtn--piece.is-on,
        .sale-ois-unitbtn--pack.is-on,
        .sale-ois-unitbtn--case.is-on {
            background: #f59e0b;
            color: #fff;
            box-shadow: none;
        }
        @media (max-width: 767px) {
            .sale-ois-unitbtns { width: auto; max-width: 100%; }
            .sale-ois-unitbtn { min-height: 26px; font-size: 10px; padding: 2px 7px; }
            .sale-ois-unitbtn__price { display: block; font-size: 9px; }
        }
        .sale-ois-card.is-oos { opacity: .55; }
        .sale-ois-card.is-oos .sale-ois-qty button,
        .sale-ois-card.is-oos .sale-qty-input { pointer-events: none; opacity: .5; }
        .sale-ois-card__hist {
            display: block; width: 100%; text-align: left; border: 0; background: transparent;
            padding: 4px 0 0; font-size: 12px; font-weight: 700; color: #2563eb; cursor: pointer;
        }
        .sale-ois-hist { padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
        .sale-ois-card__stock.out { background: #b91c1c; }
        .sale-ois-card {
            display: block;
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 10px;
            padding: 10px 12px 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .sale-ois-card.is-active { border-color: #e53935; }
        .sale-ois-card__head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 8px;
        }
        .sale-ois-card__name { font-size: 14px; font-weight: 800; color: #222; line-height: 1.25; flex: 1; }
        .sale-ois-card__chev { color: #e53935; font-size: 18px; font-weight: 700; line-height: 1; }
        .sale-ois-card__body {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: start;
        }
        .sale-ois-card__photo {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 150px;
            margin: 4px 0 10px;
            background: #fff;
        }
        .sale-ois-card__photo img { max-height: 150px; max-width: 70%; object-fit: contain; }
        .sale-ois-card__photo svg { width: 48px; height: 48px; }
        .sale-ois-card__photo .sale-ois-card__stock { display: none; }
        .sale-ois-card__right {
            display: flex; flex-direction: column; align-items: flex-end; gap: 6px; min-width: 108px;
        }
        .sale-ois-card__left { min-width: 0; }
        .sale-order-build__checkout.is-off { background: #9e9e9e !important; }
        .sale-ois-card__stock {
            display: inline-flex; border-radius: 999px;
            padding: 3px 8px; font-size: 10px; font-weight: 800; color: #fff;
        }
        .sale-ois-card__stock.in { background: #43a047; }
        .sale-ois-card__stock.out { background: #e53935; }
        .sale-ois-card__meta { font-size: 12px; color: #888; margin-top: 2px; }
        .sale-ois-card__code { color: #222; font-weight: 700; }
        .sale-ois-card__cat { font-size: 11px; color: #9ca3af; letter-spacing: .02em; margin-top: 4px; }
        .sale-ois-hit { background: #fff59d; color: inherit; padding: 0; }
        .sale-ois-results { text-align: right; font-size: 12px; color: #9ca3af; padding: 2px 2px 0; flex-shrink: 0; }
        .sale-ois-card__price {
            font-size: 14px; font-weight: 700; color: #222; background: #f3f4f6;
            border-radius: 8px; padding: 6px 8px; display: inline-block; margin-bottom: 4px;
        }
        .sale-ois-card__unit { font-size: 12px; color: #666; }
        .sale-ois-card__pack { font-size: 12px; color: #888; }
        .sale-ois-card__total { font-size: 13px; font-weight: 800; color: #222; }
        .sale-view-sheet { padding: 4px 0 8px; }
        .sale-view-opt {
            display: block; width: 100%; border: 0; background: transparent;
            padding: 16px 12px; font-size: 20px; font-weight: 500; color: #111;
            text-align: center; cursor: pointer; position: relative; z-index: 2;
        }
        .sale-view-opt.is-on { font-weight: 800; color: #e53935; }
        .sale-cart-lines.is-view-item .sale-ois-card,
        #stepCart[data-product-view="item"] .sale-ois-card {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 8px 10px;
            align-items: start;
        }
        .sale-cart-lines.is-view-item .sale-ois-card__head,
        #stepCart[data-product-view="item"] .sale-ois-card__head { grid-column: 1 / -1; margin: 0; }
        .sale-cart-lines.is-view-item .sale-ois-card__photo,
        #stepCart[data-product-view="item"] .sale-ois-card__photo {
            display: flex !important;
            grid-column: 1;
            height: auto !important;
            margin: 0;
            min-height: 80px;
            visibility: visible !important;
        }
        .sale-cart-lines.is-view-item .sale-ois-card__photo img,
        #stepCart[data-product-view="item"] .sale-ois-card__photo img {
            width: 80px; height: 80px; max-width: 80px; max-height: 80px; object-fit: contain;
        }
        .sale-cart-lines.is-view-item .sale-ois-card__photo svg,
        #stepCart[data-product-view="item"] .sale-ois-card__photo svg { width: 36px; height: 36px; }
        .sale-cart-lines.is-view-item .sale-ois-card__photo .sale-ois-card__stock,
        #stepCart[data-product-view="item"] .sale-ois-card__photo .sale-ois-card__stock { display: inline-flex !important; }
        .sale-cart-lines.is-view-item .sale-ois-card__body,
        #stepCart[data-product-view="item"] .sale-ois-card__body { grid-column: 2; min-width: 0; }
        .sale-cart-lines.is-view-item .sale-ois-card__right > .sale-ois-card__stock,
        #stepCart[data-product-view="item"] .sale-ois-card__right > .sale-ois-card__stock { display: none; }
        .sale-cart-lines.is-view-item .sale-ois-units,
        #stepCart[data-product-view="item"] .sale-ois-units {
            grid-column: auto;
            width: auto;
            margin: 6px 0 4px;
        }
        .sale-cart-lines.is-view-details .sale-ois-card__photo { display: none !important; }
        .sale-cart-lines.is-view-large .sale-ois-card__photo,
        .sale-cart-lines.is-view-medium .sale-ois-card__photo { display: flex; }
        .sale-cart-lines.is-view-large .sale-ois-card__photo { height: 170px; }
        .sale-cart-lines.is-view-large .sale-ois-card__photo img { max-height: 170px; }
        .sale-cart-lines.is-view-medium .sale-ois-card__photo { height: 110px; }
        .sale-cart-lines.is-view-medium .sale-ois-card__photo img { max-height: 110px; }
        .sale-cart-lines.is-view-large .sale-ois-card {
            padding: 14px 14px 12px;
        }
        .sale-cart-lines.is-view-large .sale-ois-card__name { font-size: 16px; }
        .sale-cart-lines.is-view-large .sale-ois-card__price { font-size: 16px; padding: 8px 10px; }
        .sale-cart-lines.is-view-large .sale-ois-card__total { font-size: 15px; }
        .sale-cart-lines.is-view-large .sale-ois-qty button { width: 34px; height: 34px; font-size: 20px; }
        .sale-cart-lines.is-view-large .sale-ois-qty .sale-qty-input { width: 36px; font-size: 20px; }
        .sale-cart-lines.is-view-medium .sale-ois-card {
            padding: 8px 10px 6px;
        }
        .sale-cart-lines.is-view-medium .sale-ois-card__name { font-size: 13px; }
        .sale-cart-lines.is-view-medium .sale-ois-card__price { font-size: 12px; padding: 4px 6px; }
        .sale-cart-lines.is-view-medium .sale-ois-card__total { font-size: 12px; }
        .sale-cart-lines.is-view-medium .sale-ois-card__unit,
        .sale-cart-lines.is-view-medium .sale-ois-card__pack,
        .sale-cart-lines.is-view-medium .sale-ois-card__meta { font-size: 11px; }
        .sale-cart-lines.is-view-medium .sale-ois-qty button { width: 24px; height: 24px; font-size: 14px; }
        .sale-cart-lines.is-view-medium .sale-ois-qty .sale-qty-input { width: 22px; font-size: 14px; }
        .sale-cart-lines.is-view-list .sale-ois-card__photo,
        .sale-cart-lines.is-view-list .sale-ois-card__left,
        .sale-cart-lines.is-view-list .sale-ois-card__stock,
        .sale-cart-lines.is-view-list .sale-ois-card__cat,
        .sale-cart-lines.is-view-list .sale-ois-card__head .sale-ois-card__chev { display: none; }
        .sale-cart-lines.is-view-list .sale-ois-card {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px 10px 10px;
        }
        .sale-cart-lines.is-view-list .sale-ois-card__head { margin: 0; flex: 1; min-width: 0; }
        .sale-cart-lines.is-view-list .sale-ois-card__body { display: contents; }
        .sale-cart-lines.is-view-list .sale-ois-card__right { flex-direction: row; min-width: 0; align-items: center; }
        .sale-cart-lines.is-view-list .sale-ois-card__name { font-size: 13px; font-weight: 700; }
        .sale-cart-lines.is-view-list .sale-ois-units {
            display: block; width: auto; order: 9; margin-top: 2px;
        }
        .sale-ois-qty { display: inline-flex; align-items: center; gap: 10px; }
        .sale-ois-qty button {
            width: 28px; height: 28px; border-radius: 999px; border: 1.6px solid #9e9e9e;
            background: #fff; font-weight: 800; cursor: pointer; color: #9e9e9e; font-size: 16px; line-height: 1;
        }
        .sale-ois-card.is-active .sale-ois-qty button { border-color: #e53935; color: #e53935; }
        .sale-ois-qty button:disabled { border-color: #d1d5db; color: #d1d5db; background: #fff; }
        .sale-ois-qty .sale-qty-input {
            width: 28px; text-align: center; border: 0; background: transparent;
            font-weight: 800; font-size: 16px; color: #111; padding: 0;
        }
        .sale-ois-units { margin-top: 6px; width: auto; }
        .sale-ois-radios {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: 1fr;
            width: 100%;
            border: 1.5px solid #e53935;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .sale-ois-radio {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            margin: 0; border: 0; border-radius: 0; border-right: 1px solid #ffcdd2;
            padding: 9px 8px; font-size: 13px; font-weight: 800; color: #64748b;
            background: #fff; cursor: pointer;
        }
        .sale-ois-radio:last-child { border-right: 0; }
        .sale-ois-radio input {
            width: 16px; height: 16px; margin: 0; accent-color: #e53935; flex-shrink: 0;
        }
        .sale-ois-radio.is-on,
        .sale-ois-radio:has(input:checked) {
            background: #e53935; color: #fff; border-color: #e53935;
        }
        .sale-ois-radio:has(input:checked) input { accent-color: #fff; }
        .sale-ois-units .sale-ois-card__pack { margin-top: 4px; font-size: 11px; }
        .sale-tab.active { color: #e53935; }
        .sale-more-cancel { color: #e53935 !important; border-color: #e53935 !important; }
        .sale-order-build__submit {
            display: none !important;
        }
        .sale-order-build__body {
            padding: 12px 14px 16px;
            display: flex; flex-direction: column; gap: 12px;
            flex: 1 1 auto; min-height: 0; overflow: hidden;
        }
        .sale-order-build__row {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            flex-shrink: 0;
        }
        .sale-order-build__label { font-size: 13px; font-weight: 700; color: #334155; min-width: 0; }
        .sale-toggle {
            position: relative; display: inline-flex; width: 46px; height: 26px;
            flex-shrink: 0; cursor: pointer;
        }
        .sale-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .sale-toggle__track {
            width: 100%; height: 100%; border-radius: 999px; background: #cbd5e1;
            transition: background .15s ease; position: relative;
        }
        .sale-toggle__track::after {
            content: ''; position: absolute; top: 3px; left: 3px;
            width: 20px; height: 20px; border-radius: 50%; background: #fff;
            box-shadow: 0 1px 3px rgba(15,23,42,.2); transition: transform .15s ease;
        }
        .sale-toggle input:checked + .sale-toggle__track { background: #e53935; }
        .sale-toggle input:checked + .sale-toggle__track::after { transform: translateX(20px); }

        .sale-fulfill {
            display: flex; align-items: center; justify-content: flex-start; flex-wrap: wrap; gap: 16px 28px;
            padding: 4px 0 2px;
            flex-shrink: 0;
        }
        .sale-fulfill__opt {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 13px; font-weight: 700; color: #334155; cursor: pointer; user-select: none;
        }
        .sale-fulfill__opt input {
            -webkit-appearance: none; appearance: none;
            width: 18px; height: 18px; margin: 0; border: 2px solid #cbd5e1; border-radius: 50%;
            background: #fff; flex-shrink: 0; box-sizing: border-box;
        }
        .sale-fulfill__opt input:checked {
            border-color: #e53935;
            background:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' d='M3.5 8.5l3 3 6-6'/%3E%3C/svg%3E")
                center / 12px 12px no-repeat, #e53935;
        }
        .sale-fulfill__dot { display: none; }

        .sale-last-qty-status {
            font-size: 12px;
            font-weight: 700;
            color: #e53935;
            margin-top: -4px;
            flex-shrink: 0;
        }
        .sale-last-qty-status.is-error { color: #e11d48; }
        .sale-last-qty-status[hidden] { display: none !important; }
        .sale-sku-block { position: relative; flex-shrink: 0; }
        .sale-sku-block .sale-prod-results {
            position: absolute;
            left: 0; right: 0; top: calc(100% + 4px);
            z-index: 25;
        }
        .sale-sku-row { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .sale-sku-modes { display: inline-flex; gap: 6px; flex-shrink: 0; }
        .sale-sku-mode {
            width: 42px; height: 42px; border-radius: 10px; border: 1px solid #e2e8f0;
            background: #f1f5f9; color: #64748b; display: inline-flex;
            align-items: center; justify-content: center; cursor: pointer; padding: 0;
        }
        .sale-sku-mode.is-active {
            background: #e53935; border-color: #e53935; color: #fff;
        }
        .sale-sku-search {
            flex: 1; min-width: 0; height: 42px; border-radius: 10px;
            border: 1px solid #e2e8f0; background: #f8fafc;
            display: flex; align-items: center; gap: 8px; padding: 0 12px;
        }
        .sale-sku-search svg { width: 18px; height: 18px; color: #94a3b8; flex-shrink: 0; }
        .sale-sku-search__input {
            flex: 1; min-width: 0; border: 0 !important; background: transparent !important;
            outline: none !important; box-shadow: none !important; padding: 0 !important;
            font-size: 15px; font-weight: 600; color: #0b1220; height: auto !important;
        }
        .sale-order-list-head {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding-top: 4px;
            flex-shrink: 0;
        }
        .sale-order-list-head strong { font-size: 15px; font-weight: 800; color: #0b1220; }
        .sale-order-list-head__link {
            border: 0; background: transparent; color: #e53935;
            font-size: 13px; font-weight: 800; cursor: pointer; padding: 0;
        }
        .sale-order-build .sale-cart-scroll {
            flex: 1 1 auto;
            min-height: 180px;
            max-height: none !important;
            margin: 0;
            padding: 0 2px 8px;
            border: 0;
            border-radius: 0;
            background: #fff;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .sale-order-build .sale-cart-lines {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 4px 0 8px;
        }
        .sale-order-build .sale-cart-item {
            word-break: break-word;
            overflow-wrap: anywhere;
            flex-shrink: 0;
        }
        .sale-order-build .sale-cart-item__qty {
            flex-wrap: nowrap;
            align-items: center;
        }
        .sale-order-build__total {
            display: flex; align-items: center; justify-content: space-between;
            font-size: 15px; font-weight: 700; color: #334155;
            padding: 10px 12px 12px;
            flex-shrink: 0;
            background: #fff;
            border-top: 1px solid #f1f5f9;
            margin-top: 0;
            gap: 12px;
        }
        .sale-order-build__total strong { font-size: 22px; font-weight: 800; color: #0f172a; display: block; }

        /* Build step: fill space above bottom nav; cart list scrolls fully */
        body.sale-building-order {
            background: #fff;
            overflow-x: hidden;
        }
        body.sale-building-order .sale-m-only.sticky { display: none !important; }
        body.sale-building-order .sale-bottom-nav { display: block !important; }
        @media (min-width: 1024px) {
            body.sale-building-order .sale-bottom-nav { display: none !important; }
        }
        body.sale-building-order.sale-page-create .sale-main-app,
        body.sale-building-order .sale-main-app {
            padding: 0 !important;
            padding-bottom: calc(68px + env(safe-area-inset-bottom, 0px)) !important;
            margin: 0 !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            box-sizing: border-box !important;
            min-height: 100dvh !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            background: #fff !important;
        }
        body.sale-building-order #customerSelected { display: none !important; }
        body.sale-building-order .sale-page {
            padding: 0 !important;
            margin: 0 !important;
            flex: 1 1 auto !important;
            min-height: 0 !important;
            height: auto !important;
            max-height: none !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            background: #fff !important;
        }
        body.sale-building-order .sale-create-form,
        body.sale-building-order #saleOrderForm,
        body.sale-building-order form.sale-create-form {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            height: 100% !important;
            max-height: 100% !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            margin: 0 !important;
            background: #fff !important;
        }
        body.sale-building-order .sale-order-build {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            height: 100% !important;
            max-height: 100% !important;
            margin: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            background: #fff !important;
        }
        body.sale-building-order .sale-order-build__body {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            padding-bottom: 8px !important;
        }
        body.sale-building-order .sale-order-build .sale-cart-scroll {
            flex: 1 1 auto !important;
            min-height: 160px !important;
            max-height: none !important;
            overflow-y: auto !important;
        }
        @media (min-width: 1024px) {
            .sale-order-build {
                margin: 0; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;
                height: calc(100dvh - 64px - 48px);
                max-height: calc(100dvh - 64px - 48px);
            }
            body.sale-building-order.sale-page-create .sale-main-app,
            body.sale-building-order .sale-main-app {
                padding: 24px 28px !important;
                height: calc(100dvh - 64px) !important;
                min-height: calc(100dvh - 64px) !important;
                max-height: calc(100dvh - 64px) !important;
                overflow: hidden !important;
            }
            body.sale-building-order .sale-page { padding: 0 !important; overflow: hidden !important; }
            body.sale-building-order .sale-desk-top { display: none !important; }
            body.sale-building-order .sale-order-build {
                height: calc(100dvh - 64px - 48px) !important;
                max-height: calc(100dvh - 64px - 48px) !important;
            }
            body.sale-building-order .sale-order-build__bar {
                padding-left: 16px;
                padding-right: 16px;
            }
            body.sale-building-order .sale-order-build__submit {
                width: auto !important;
                flex: 0 0 auto !important;
                margin: 0 !important;
            }
        }

        /* Embedded pick (Delivery) — keep app header + bottom nav */
        .sale-pick-customer--embedded {
            margin: -12px -12px 0;
            border-radius: 0;
            min-height: calc(100dvh - 56px - 72px - env(safe-area-inset-bottom, 0px));
        }
        @media (min-width: 1024px) {
            .sale-pick-customer--embedded {
                margin: 0;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                overflow: hidden;
                max-width: 720px;
            }
        }

        /* Pick step: full-bleed, no double header / no create-page clip */
        body.sale-picking-customer .sale-m-only.sticky { display: none !important; }
        body.sale-picking-customer .sale-desk-top { display: none !important; }
        body.sale-picking-customer.sale-page-create .sale-main-app {
            padding: 0 !important;
            height: auto !important;
            min-height: 100dvh !important;
            overflow: visible !important;
        }
        body.sale-picking-customer.sale-page-create .sale-page {
            padding: 0 !important;
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
            max-width: none !important;
        }
        body.sale-picking-customer.sale-page-create .sale-create-form {
            display: block !important;
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
        }
        body.sale-picking-customer .sale-flash {
            margin: 12px 12px 0;
        }
        @media (min-width: 1024px) {
            body.sale-picking-customer.sale-page-create .sale-main-app {
                padding: 0 !important;
            }
            body.sale-picking-customer.sale-page-create .sale-page {
                padding: 24px 28px 40px !important;
            }
            .sale-pick-customer {
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                overflow: hidden;
                max-width: none !important;
                margin: 0 !important;
                width: 100%;
                min-height: calc(100dvh - 64px - 88px);
            }
            .sale-pick-mode { font-size: 13px; justify-content: flex-start; flex: 0 1 auto; }
            .sale-pick-modes { justify-content: flex-start; gap: 18px; padding-left: 16px; }
        }

        /* Create order: cart layout */
        .sale-cart-flow {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
        }
        .sale-cart-panel {
            display: flex;
            flex-direction: column;
            padding-bottom: 16px !important;
            min-height: 0;
            flex: 1;
        }
        .sale-cart-footer {
            padding-top: 4px;
            padding-bottom: 8px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        @media (max-width: 1023px) {
            body.sale-page-create #stepCart .sale-cart-footer {
                padding-bottom: calc(24px + env(safe-area-inset-bottom, 0));
            }
            body.sale-page-create #stepCart #goShippingBtn {
                margin-bottom: 16px;
            }
        }
        .sale-prod-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .sale-prod-head .sale-sec-title { flex: 1; min-width: 0; }
        .sale-prod-search-wrap {
            position: relative;
            margin-bottom: 10px;
        }
        .sale-prod-results {
            position: absolute;
            left: 0; right: 0; top: calc(100% + 4px);
            z-index: 20;
            max-height: 220px;
            overflow: auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
        }
        .sale-prod-results.hidden { display: none; }
        .sale-catalog-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            background: #e53935;
            color: #fff;
            border: 0;
            font-weight: 700;
            font-size: 13px;
            border-radius: 10px;
            padding: .55rem .9rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(229, 57, 53, 0.25);
        }
        .sale-cart-scroll {
            max-height: min(48dvh, 420px);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
            padding: 0 4px 8px;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            min-height: 140px;
        }
        .sale-cart-empty { text-align: center; padding: 28px 12px; }
        .sale-cart-empty.hidden { display: none; }
        .sale-cart-lines { display: flex; flex-direction: column; gap: 8px; padding: 10px 0; }
        .sale-cart-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
        }
        .sale-cart-item__top { display: flex; justify-content: space-between; gap: 8px; }
        .sale-cart-item__rm {
            width: 28px; height: 28px; border-radius: 8px; border: 0;
            background: #fee2e2; color: #e11d48; font-size: 18px; font-weight: 700;
            line-height: 1; cursor: pointer; flex-shrink: 0;
        }
        .sale-cart-item__qty { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
        .sale-qty-btn {
            width: 34px; height: 34px; border-radius: 8px;
            border: 1px solid #e2e8f0; background: #fff; font-weight: 800; cursor: pointer;
        }
        .sale-qty-btn:disabled {
            opacity: .35;
            cursor: not-allowed;
        }
        .sale-qty-input {
            width: 64px; text-align: center; font-weight: 800;
            border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; background: #fff;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .sale-qty-input::-webkit-outer-spin-button,
        .sale-qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .sale-cart-total {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 1.05rem; font-weight: 800;
        }
        .sale-cart-total strong { color: #e53935; font-size: 1.2rem; }
        .sale-cart-pay { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .sale-pay-chip {
            display: flex; align-items: center; gap: 8px;
            border: 2px solid #e2e8f0; border-radius: 12px; padding: 10px 12px;
            font-size: 13px; font-weight: 700; cursor: pointer; background: #fff;
        }
        .sale-pay-chip:has(:checked) { border-color: #e53935; background: #fff5f5; }
        .sale-ship-flow {
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
        }
        .sale-ship-flow[hidden], .sale-cart-flow[hidden] { display: none !important; }

        /* Products page — mobile app filters */
        .sale-prod-app { width: 100%; max-width: 780px; margin: 0 auto; }
        @media (min-width: 1024px) {
            body.sale-page-products .sale-main-app {
                padding-bottom: 24px !important;
                min-height: calc(100dvh - 64px);
            }
            body.sale-page-products .sale-page {
                padding: 24px 28px 40px !important;
                width: 100%;
                max-width: none !important;
                margin: 0 !important;
            }
            body.sale-page-products .sale-prod-app {
                max-width: none;
                margin: 0;
                width: 100%;
                display: flex;
                flex-direction: column;
                min-height: calc(100dvh - 64px - 40px);
            }
            body.sale-page-products .sale-prod-app__toolbar {
                position: sticky;
                top: 0;
                z-index: 40;
                margin-bottom: 14px;
                padding: 4px 0 12px;
            }
            body.sale-page-products .sale-prod-app__list {
                display: flex;
                flex-direction: column;
                gap: 10px;
                width: 100%;
            }
            body.sale-page-products .sale-prod-card {
                width: 100%;
                grid-template-columns: 88px minmax(0, 1fr) auto;
            }
            body.sale-page-products .sale-prod-app__search {
                max-width: none;
            }
            body.sale-page-products .sale-prod-card__thumb {
                width: 80px;
                height: 80px;
            }
        }
        .sale-prod-app__toolbar {
            position: sticky; top: 0; z-index: 30;
            background: rgba(248,250,252,.96); backdrop-filter: blur(10px);
            padding: 2px 0 10px; margin: 0 -2px 10px; padding-left: 2px; padding-right: 2px;
        }
        @media (max-width: 1023px) {
            .sale-prod-app__toolbar {
                top: calc(56px + env(safe-area-inset-top, 0));
                margin-left: -2px; margin-right: -2px;
            }
        }
        .sale-prod-app__search {
            display: flex; align-items: center; gap: 8px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
            padding: 0 6px 0 12px; margin-bottom: 10px;
            box-shadow: 0 1px 2px rgba(15,23,42,.04);
        }
        .sale-prod-app__search svg { color: #94a3b8; flex-shrink: 0; }
        .sale-prod-app__search input {
            flex: 1; min-width: 0; border: 0; outline: 0; background: transparent;
            padding: 12px 4px; font-size: 14px; font-weight: 600; color: #0f172a;
        }
        .sale-prod-app__filter-btn {
            position: relative; width: 40px; height: 40px; border-radius: 12px;
            border: 0; background: #fff5f5; color: #e53935; cursor: pointer;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sale-prod-app__dot {
            position: absolute; top: 8px; right: 8px; width: 8px; height: 8px;
            border-radius: 999px; background: #e53935; border: 2px solid #fff5f5;
        }
        .sale-prod-app__dot.hidden { display: none; }
        .sale-prod-app__chips {
            display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px;
            -webkit-overflow-scrolling: touch; scrollbar-width: none;
            margin-bottom: 8px;
        }
        .sale-prod-app__chips::-webkit-scrollbar { display: none; }
        .sale-prod-app__chips--sub { margin-top: -2px; }
        .sale-prod-app__chips--sub.hidden { display: none; }
        .sale-chip {
            flex-shrink: 0; border: 1px solid #e2e8f0; background: #fff; color: #475569;
            border-radius: 999px; padding: 7px 14px; font-size: 12px; font-weight: 700;
            cursor: pointer; white-space: nowrap; text-decoration: none;
        }
        .sale-chip.active {
            background: #e53935; border-color: #e53935; color: #fff;
            box-shadow: 0 4px 10px rgba(229,57,53,.25);
        }
        .sale-prod-app__meta {
            display: flex; align-items: center; justify-content: space-between;
            font-size: 12px; font-weight: 700; color: #64748b; min-height: 20px;
        }
        .sale-prod-app__clear {
            border: 0; background: transparent; color: #e53935; font-weight: 800;
            font-size: 12px; cursor: pointer; padding: 0;
        }
        .sale-prod-app__clear.hidden { display: none; }
        .sale-prod-app__empty {
            text-align: center; color: #94a3b8; font-size: 14px; font-weight: 600;
            padding: 40px 12px;
        }
        .sale-prod-app__empty.hidden { display: none; }

        .sale-sheet {
            position: fixed; inset: 0; z-index: 400;
            background: rgba(15,23,42,.45);
            display: flex; align-items: flex-end; justify-content: center;
        }
        .sale-credit-modal {
            align-items: center;
            padding: 24px;
        }
        .sale-credit-modal__panel {
            width: 100%; max-width: 320px; background: #fff;
            border-radius: 16px; padding: 28px 22px 20px;
            text-align: center; box-shadow: 0 12px 40px rgba(15,23,42,.2);
        }
        .sale-credit-modal__icon {
            width: 56px; height: 56px; margin: 0 auto 12px; border-radius: 999px;
            border: 3px solid #e53935; color: #e53935;
            font-size: 28px; font-weight: 800; line-height: 50px;
        }
        .sale-credit-modal__title { font-size: 20px; font-weight: 800; color: #111; margin-bottom: 8px; }
        .sale-credit-modal__msg { font-size: 15px; color: #64748b; margin: 0 0 18px; }
        .sale-credit-modal__accept {
            width: 100%; border: 0; background: #e53935; color: #fff;
            font-weight: 800; font-size: 16px; border-radius: 10px; padding: 12px;
            cursor: pointer;
        }
        .sale-attn-modal {
            background: rgba(0, 0, 0, .55);
        }
        .sale-attn-modal .sale-attn-panel {
            width: 100%; max-width: 300px; background: #2b2b2b; color: #fff;
            border-radius: 18px; padding: 22px 18px 16px; text-align: center;
        }
        .sale-attn-modal .sale-attn-ico {
            width: 52px; height: 52px; margin: 0 auto 10px; border-radius: 999px;
            background: #e53935; color: #fff; font-size: 26px; font-weight: 800; line-height: 52px;
        }
        .sale-attn-modal .sale-attn-title { font-size: 20px; font-weight: 800; margin-bottom: 8px; }
        .sale-attn-modal .sale-attn-msg { font-size: 14px; color: #e5e7eb; margin: 0 0 16px; }
        .sale-attn-modal .sale-attn-btns { display: flex; gap: 10px; }
        .sale-attn-modal .sale-attn-btns button {
            flex: 1; border-radius: 999px; padding: 10px; font-weight: 800; cursor: pointer; font-size: 14px;
        }
        .sale-attn-no { background: transparent; border: 1.5px solid #e53935; color: #e53935; }
        .sale-attn-yes { background: #e53935; border: 0; color: #fff; }
        body.sale-page-reports { background: #f4f6fb; }
        body.sale-page-reports .sale-m-only.sticky { background: #fff; }
        body.sale-page-reports .sale-main-app { background: #f4f6fb !important; }
        body.sale-authed { background: #f4f6fb; }
        body.sale-authed .sale-main-app { background: #f4f6fb; }
        .sale-rpt { color: #0b1220; padding-bottom: 12px; }
        .sale-rpt__row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .sale-rpt__dd { background: none; border: 0; color: #0b1220; font-weight: 800; font-size: 15px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .sale-rpt__caret {
            width: 0; height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 6px solid #e53935;
        }
        .sale-goals {
            background: #fff; border-radius: 20px; padding: 16px 12px 18px;
            box-shadow: 0 10px 28px rgba(15,23,42,.06);
            border: 1px solid #eef2f7;
        }
        .sale-goals h2 { text-align: center; font-size: 16px; font-weight: 800; margin: 0 0 12px; }
        .sale-goals__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        a.sale-goal { text-align: center; padding: 10px 8px; text-decoration: none; color: inherit; display: block; border-radius: 16px; border: 1px solid transparent; }
        a.sale-goal.is-on { border-color: #fecaca; background: #fff7f7; }
        .sale-goal__arc {
            width: 118px; height: 72px; margin: 0 auto 4px; position: relative;
        }
        .sale-goal__arc svg { width: 118px; height: 72px; display: block; overflow: visible; }
        .sale-goal__track, .sale-goal__fill {
            fill: none; stroke-width: 10; stroke-linecap: round;
        }
        .sale-goal__track { stroke: #e8edf5; }
        .sale-goal__fill { stroke: #e53935; }
        .sale-goal__arc b {
            position: absolute; left: 0; right: 0; bottom: 2px; z-index: 1;
            font-size: 15px; font-weight: 800; color: #0b1220;
        }
        .sale-goal__name { font-weight: 800; font-size: 14px; margin-bottom: 4px; }
        .sale-goal__cur { font-size: 12px; font-weight: 700; }
        .sale-goal__tgt { font-size: 11px; color: #94a3b8; }
        .sale-tx { margin-top: 16px; }
        .sale-tx h2 { font-size: 16px; font-weight: 800; margin: 0 0 10px; }
        .sale-tx__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .sale-tx__tile {
            display: block; text-decoration: none; color: inherit;
            background: #fff; border: 1px solid #eef2f7; border-radius: 16px;
            padding: 12px 12px 10px; min-height: 72px;
            box-shadow: 0 6px 16px rgba(15,23,42,.04);
        }
        .sale-tx__tile--wide { grid-column: 1 / -1; max-width: 50%; }
        .sale-tx__top { display: flex; justify-content: space-between; align-items: center; gap: 6px; }
        .sale-tx__top b { font-size: 14px; font-weight: 800; }
        .sale-tx__amt { font-size: 13px; font-weight: 700; color: #64748b; margin-top: 10px; }
        .sale-tx__tile.is-quotes .sale-tx__top b { color: #e53935; }
        .sale-tx__tile.is-orders .sale-tx__top b { color: #e53935; }
        .sale-tx__tile.is-invoices .sale-tx__top b { color: #e53935; }
        .sale-tx__tile.is-credit .sale-tx__top b { color: #e53935; }
        .sale-tx__tile.is-pay .sale-tx__top b { color: #e53935; }
        .sale-rpt-more-btn { background: none; border: 0; color: #0b1220; width: 36px; height: 36px; cursor: pointer; }
        .sale-rpt-list { margin-top: 14px; }
        .sale-rpt-list__title { font-size: 16px; font-weight: 800; margin: 0 0 10px; }
        .sale-rpt-row {
            display: flex; gap: 10px; align-items: center; background: #fff;
            border-radius: 14px; padding: 12px; margin-bottom: 8px; text-decoration: none; color: inherit;
            box-shadow: 0 4px 14px rgba(15,23,42,.05);
        }
        .sale-rpt-row strong { display: block; font-size: 14px; }
        .sale-rpt-row span { display: block; font-size: 12px; color: #64748b; }
        .sale-rpt-row em { font-style: normal; font-weight: 800; margin-left: auto; white-space: nowrap; }
        .sale-sheet--rpt .sale-sheet__panel { background: #fff; color: #0b1220; }
        .sale-sheet--rpt .sale-sheet__title { color: #0b1220; }
        .sale-sheet--rpt .sale-sheet__close { color: #0b1220; background: transparent; }
        .sale-sheet--rpt .sale-act-row { color: #0b1220; }
        .sale-sheet--rpt .sale-act-row svg { stroke: #e53935; }
        .sale-sheet--rpt .sale-more-cancel { border: 1px solid #e53935; color: #e53935; background: transparent; border-radius: 999px; }
        .sale-period-opt { display: block; width: 100%; background: none; border: 0; color: #0b1220; font-size: 18px; font-weight: 700; padding: 14px; cursor: pointer; text-decoration: none; }
        .sale-period-opt.is-on { color: #e53935; }
        .sale-month-done { background: #e53935; color: #fff; border: 0; border-radius: 999px; padding: 8px 22px; font-weight: 800; float: right; margin: 8px 12px; cursor: pointer; }
        .sale-wheel {
            position: relative; display: flex; height: 200px; margin: 8px 0 20px;
            padding: 0 8px;
        }
        .sale-wheel__hl {
            position: absolute; left: 12px; right: 12px; top: 50%; height: 40px;
            margin-top: -20px; border-radius: 10px; background: rgba(229,57,53,.08);
            pointer-events: none; z-index: 0;
        }
        .sale-wheel__col {
            flex: 1; overflow-y: auto; scroll-snap-type: y mandatory;
            -webkit-overflow-scrolling: touch; z-index: 1;
            padding: 80px 0; scrollbar-width: none;
        }
        .sale-wheel__col::-webkit-scrollbar { display: none; }
        .sale-wheel__item {
            height: 40px; line-height: 40px; text-align: center;
            font-size: 18px; font-weight: 700; scroll-snap-align: center;
            color: #334155;
        }
        .sale-sheet[hidden],
        .sale-sheet[hidden].is-open { display: none !important; }
        .sale-sheet.is-open {
            display: flex !important;
            z-index: 9999;
        }
        .sale-sheet__panel {
            width: 100%; max-width: 560px; background: #fff;
            border-radius: 18px 18px 0 0;
            padding-bottom: calc(16px + env(safe-area-inset-bottom, 0));
            box-shadow: 0 -8px 32px rgba(15,23,42,.18);
        }
        .sale-sheet__handle {
            width: 40px; height: 4px; border-radius: 999px; background: #cbd5e1;
            margin: 10px auto 6px;
        }
        .sale-sheet__head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 16px 12px; border-bottom: 1px solid #f1f5f9;
        }
        .sale-sheet__close {
            width: 36px; height: 36px; border-radius: 10px; border: 0;
            background: #f1f5f9; font-size: 22px; font-weight: 700; cursor: pointer; color: #334155;
        }
        .sale-more-link {
            display: block; padding: 14px 4px; font-size: 16px; font-weight: 600; color: #111;
            text-decoration: none; border-bottom: 1px solid #f1f5f9;
        }
        .sale-sheet__head.is-center {
            position: relative;
            justify-content: center;
            border-bottom: 0;
            padding-top: 14px;
        }
        .sale-sheet__head.is-center .sale-sheet__title {
            font-size: 16px; font-weight: 700; color: #111;
        }
        #orderHistorySheet .sale-ohist__panel {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
            max-width: 420px;
            margin: 0 auto;
            padding-bottom: 8px;
        }
        #orderHistorySheet.sale-sheet {
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        #orderHistorySheet .sale-ohist__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 18px 14px;
            border-bottom: 1px solid #eceff3;
        }
        #orderHistorySheet .sale-ohist__head-text { min-width: 0; flex: 1; }
        #orderHistorySheet .sale-ohist__name {
            font-size: 16px;
            font-weight: 800;
            color: #0b1220;
            line-height: 1.3;
            word-break: break-word;
        }
        #orderHistorySheet .sale-ohist__sub {
            margin-top: 4px;
            font-size: 13px;
            font-weight: 500;
            color: #94a3b8;
        }
        #orderHistorySheet .sale-ohist__close {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            border: 0;
            background: #f1f5f9;
            color: #64748b;
            font-size: 20px;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
        }
        #orderHistorySheet .sale-ohist__body {
            padding: 0 18px;
            max-height: min(60vh, 420px);
            overflow-y: auto;
        }
        #orderHistorySheet .sale-ohist__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid #eceff3;
        }
        #orderHistorySheet .sale-ohist__row:last-child { border-bottom: 0; }
        #orderHistorySheet .sale-ohist__date {
            font-size: 15px;
            font-weight: 800;
            color: #0b1220;
            white-space: nowrap;
        }
        #orderHistorySheet .sale-ohist__meta {
            font-size: 14px;
            font-weight: 600;
            color: #0b1220;
            text-align: right;
            white-space: nowrap;
        }
        #orderHistorySheet .sale-ohist__qty,
        #orderHistorySheet .sale-ohist__amt {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
        }
        #orderHistorySheet .sale-ohist__empty {
            padding: 24px 4px;
            font-size: 13px;
            color: #94a3b8;
            text-align: center;
        }
        .sale-sheet__head.is-center .sale-sheet__close {
            position: absolute; right: 8px; top: 8px;
            background: transparent; font-size: 22px;
        }
        .sale-act-row {
            display: flex; align-items: center; gap: 16px;
            width: 100%; padding: 16px 12px; border: 0; background: transparent;
            font-size: 15px; font-weight: 500; color: #111; cursor: pointer; text-align: left;
        }
        .sale-act-row svg {
            width: 28px; height: 28px; stroke: #e53935; fill: none;
            stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0;
        }
        .sale-more-cancel {
            width: 100%; border: 1.5px solid #e53935; background: #fff; color: #e53935;
            font-weight: 800; border-radius: 999px; padding: 12px; cursor: pointer;
        }
        .sale-order-build__title span.ois-red { color: #e11d48; }
        .sale-order-build__title span.ois-ink { color: #334155; font-weight: 700; }
        .sale-order-cart-ico {
            position: relative; width: 40px; height: 40px;
            display: inline-flex; align-items: center; justify-content: center; color: #334155;
        }
        .sale-order-cart-badge {
            position: absolute; top: 2px; right: 0;
        }
        .sale-cust-card {
            display: block; background: #fff; border: 1px solid #eee; border-radius: 12px;
            padding: 12px; text-decoration: none; color: inherit;
        }
        .sale-cust-card__addr {
            display: flex; align-items: flex-start; gap: 6px;
            font-size: 12px; color: #64748b; margin-top: 6px; line-height: 1.35;
        }
        .sale-cust-card__addr svg { width: 14px; height: 14px; stroke: #e53935; flex-shrink: 0; margin-top: 1px; }
        a.sale-act-row { text-decoration: none; box-sizing: border-box; }
        .sale-cust-card__top { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; }
        .sale-cust-card__chev { color: #e11d48; font-weight: 800; }
        .sale-route-area { margin-bottom: 18px; }
        .sale-route-area__head { margin: 4px 2px 10px; }
        .sale-route-area__name { font-size: 16px; font-weight: 800; }
        .sale-route-area__sub { font-size: 12px; color: #94a3b8; font-weight: 700; margin-top: 2px; }
        .sale-route-line { position: relative; padding-left: 4px; }
        .sale-route-stop {
            display: flex; gap: 10px; align-items: stretch;
            text-decoration: none; color: inherit; margin-bottom: 2px;
        }
        .sale-route-stop__rail {
            width: 28px; flex-shrink: 0; position: relative;
            display: flex; justify-content: center;
        }
        .sale-route-stop__rail::before {
            content: ''; position: absolute; top: 0; bottom: 0; left: 50%;
            width: 3px; margin-left: -1.5px; background: #e53935;
        }
        .sale-route-stop:first-child .sale-route-stop__rail::before { top: 14px; }
        .sale-route-stop:last-child .sale-route-stop__rail::before { bottom: 18px; }
        .sale-route-stop__dot {
            position: relative; z-index: 1; align-self: flex-start; margin-top: 10px;
            width: 22px; height: 22px; border-radius: 999px;
            background: #e53935; color: #fff;
            font-size: 11px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 0 3px #fff;
        }
        .sale-route-stop__card {
            flex: 1; min-width: 0; background: #fff; border: 1px solid #eee;
            border-radius: 14px; padding: 10px 12px 10px; margin-bottom: 10px;
        }
        .sale-route-stop__tag {
            display: inline-block; font-size: 10px; font-weight: 800; color: #e53935;
            background: #ffebee; border-radius: 999px; padding: 2px 8px; margin-bottom: 6px;
        }
        .sale-route-stop__addr { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.35; }
        .sale-route-stop__foot {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 8px; font-size: 13px; font-weight: 800;
        }
        .sale-route-stop__foot em { font-style: normal; font-size: 11px; color: #64748b; font-weight: 700; }
        .sale-gauge {
            width: 72px; height: 40px; margin: 0 auto;
            background: conic-gradient(#93c5fd calc(var(--p, 0) * 1%), #e5e7eb 0);
            border-radius: 80px 80px 0 0; mask: radial-gradient(circle at 50% 100%, transparent 22px, #000 23px);
            -webkit-mask: radial-gradient(circle at 50% 100%, transparent 22px, #000 23px);
        }
        .sale-home-welcome { text-align: center; padding: 8px 0 16px; }
        .sale-home-welcome h1 { font-size: 22px; font-weight: 700; }
        .sale-home-logo { margin: 12px auto; max-width: 180px; }
        .sale-home-today { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .sale-prod-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .sale-viewmore { color: #9ca3af; font-size: 13px; font-weight: 600; text-decoration: none; }
        .sale-prod-tile { background: #fff; border: 1px solid #eee; border-radius: 20px; overflow: hidden; text-decoration: none; color: inherit; text-align: center; }
        .sale-prod-tile img, .sale-prod-tile__ph { width: 100%; height: 110px; object-fit: contain; background: #fff; display: block; }
        .sale-prod-tile__ph { display: flex; align-items: center; justify-content: center; color: #94a3b8; }
        .sale-prod-tile__name { font-size: 12px; font-weight: 700; padding: 8px 8px 2px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .sale-prod-tile__price { font-size: 13px; font-weight: 800; padding: 0 8px 4px; color: #111; }
        .sale-prod-tile__stock { font-size: 12px; font-weight: 700; padding: 0 8px 10px; color: #e53935; }
        .sale-sheet__foot {
            display: flex; justify-content: flex-end; gap: 10px;
            padding: 0 16px 8px;
        }
        .sale-filter-body { padding: 4px 20px 12px; }
        .sale-filter-label {
            display: block; font-size: 11px; font-weight: 800; letter-spacing: .06em;
            text-transform: uppercase; color: #94a3b8; margin: 0 0 8px;
        }
        .sale-filter-select { margin-bottom: 16px; appearance: auto; }
        .sale-filter-select:disabled { background: #f8fafc; color: #94a3b8; }
        .sale-filter-foot {
            display: flex; flex-direction: column; gap: 10px;
            padding: 8px 20px calc(12px + env(safe-area-inset-bottom, 0));
        }
        .sale-filter-foot .sale-more-cancel {
            width: 100%; margin: 0;
        }
        .sale-filter-apply {
            width: 100%; border-radius: 999px; padding: 12px;
        }
        @media (min-width: 1024px) {
            .sale-sheet { align-items: center; padding: 24px; }
            .sale-sheet__panel { border-radius: 16px; padding-bottom: 16px; }
            .sale-prod-app__toolbar { top: 0; }
        }

        .sale-added-msg {
            position: fixed; left: 50%; bottom: calc(88px + env(safe-area-inset-bottom, 0));
            transform: translateX(-50%);
            z-index: 140;
            background: #e53935; color: #fff;
            font-size: 13px; font-weight: 800;
            padding: 10px 18px; border-radius: 999px;
            box-shadow: 0 8px 24px rgba(229,57,53,.35);
            pointer-events: none;
        }
        #saleSyncBtn.is-syncing svg {
            animation: sale-sync-spin .7s linear infinite;
        }
        @keyframes sale-sync-spin { to { transform: rotate(360deg); } }
        .sale-added-msg[hidden] { display: none !important; }
        @media (min-width: 1024px) {
            .sale-added-msg { bottom: 32px; }
        }
        .sale-catalog {
            position: fixed; inset: 0; z-index: 120;
            background: rgba(15, 23, 42, .45);
            display: flex; align-items: flex-end; justify-content: center;
            padding: 0;
        }
        .sale-catalog[hidden] { display: none !important; }
        .sale-catalog__panel {
            width: 100%; max-width: 560px;
            max-height: min(88dvh, 760px);
            background: #fff;
            border-radius: 18px 18px 0 0;
            display: flex; flex-direction: column;
            box-shadow: 0 -8px 32px rgba(15,23,42,.18);
        }
        .sale-catalog__head {
            display: flex; align-items: center; gap: 8px;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .sale-catalog__back, .sale-catalog__close {
            width: 36px; height: 36px; border-radius: 10px;
            border: 0; background: #f1f5f9; font-size: 22px; font-weight: 700;
            color: #334155; cursor: pointer; line-height: 1; flex-shrink: 0;
        }
        .sale-catalog__back.hidden { visibility: hidden; }
        .sale-catalog__body { overflow: auto; padding: 4px 0 calc(24px + env(safe-area-inset-bottom, 0)); -webkit-overflow-scrolling: touch; flex: 1; }
        .sale-catalog__row {
            width: 100%; display: flex; align-items: center; justify-content: space-between;
            gap: 10px; text-align: left; padding: 14px 16px;
            border: 0; background: transparent; border-bottom: 1px solid #f1f5f9;
            font-weight: 700; font-size: 14px; color: #0f172a; cursor: pointer;
        }
        .sale-catalog__row:active { background: #f8fafc; }
        .sale-catalog__row--all { color: #e53935; }
        .sale-catalog__chev { color: #94a3b8; font-size: 18px; }
        .sale-catalog__add {
            width: 32px; height: 32px; border-radius: 999px;
            background: #e53935; color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 700; flex-shrink: 0;
        }
        @media (min-width: 1024px) {
            .sale-cart-flow { max-width: none !important; margin: 0 !important; width: 100%; }
            .sale-catalog { align-items: center; padding: 24px; }
            .sale-catalog__panel { border-radius: 16px; max-height: 80vh; width: min(640px, 92vw); }
            body.sale-page-create .sale-cart-flow { max-width: none; }
            body.sale-page-create .sale-create-bar { position: static; box-shadow: none; border: 0; padding: 0; background: transparent; }
            body.sale-page-create #stepShipping .sale-create-bar {
                box-shadow: 0 1px 2px rgba(15,23,42,.04);
                border: 1px solid #e2e8f0;
                background: #fff;
                padding: 20px;
            }
        }

        /* Keep Create order button visible above bottom nav on mobile */
        .sale-create-bar {
            margin-top: 4px;
        }
        @media (max-width: 1023px) {
            body.sale-page-create .sale-main-app {
                padding-bottom: calc(68px + env(safe-area-inset-bottom, 0)) !important;
            }
            body.sale-checking-out.sale-page-create .sale-main-app,
            body.sale-checking-out .sale-main-app {
                padding: 0 !important;
                padding-bottom: calc(68px + env(safe-area-inset-bottom, 0px)) !important;
            }
            body.sale-page-create .sale-create-bar {
                position: fixed;
                left: 0;
                right: 0;
                bottom: calc(68px + env(safe-area-inset-bottom, 0));
                z-index: 75;
                margin: 0;
                padding: 10px 12px;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                box-shadow: 0 -6px 16px rgba(15, 23, 42, 0.06);
            }
            body.sale-page-create .sale-create-bar .sale-btn {
                background: #e53935;
                color: #fff;
                opacity: 1;
                box-shadow: 0 6px 16px rgba(229, 57, 53, 0.35);
            }
            /* On cart step, Order button stays in card — only shipping submit is fixed */
            body.sale-page-create #stepCart .sale-btn { position: static; box-shadow: 0 6px 16px rgba(229, 57, 53, 0.25); }
        }
        .sale-cust-head { padding: 4px 4px 10px; }
        .sale-cust-head h1 { font-size: 18px; font-weight: 700; line-height: 1.2; margin: 0 0 6px; }
        .sale-acct-pill { display: inline-block; background: #e8e8e8; color: #444; font-size: 12px; border-radius: 999px; padding: 3px 10px; }
        .sale-cust-head__addr { color: #9aa0a6; font-size: 12px; margin-top: 6px; }
        .sale-cust-head__addr-label { color: #e53935; font-weight: 700; font-size: 11px; }
        .sale-cust-tabs { display: flex; justify-content: space-around; border-bottom: 1px solid #eee; margin: 8px -12px 0; padding: 0 8px; background: #fff; border-radius: 18px 18px 0 0; }
        .sale-cust-tabs a { flex: 1; text-align: center; padding: 12px 4px 8px; font-weight: 600; color: #111; text-decoration: none; font-size: 13px; border-bottom: 2px solid transparent; }
        .sale-cust-tabs a.is-on { border-bottom-color: #111; }
        .sale-cust-panel { background: #fff; margin: 0 -12px; padding: 16px 16px 24px; min-height: 50vh; }
        .sale-open-bal { text-align: center; }
        .sale-open-bal span { color: #64748b; font-size: 14px; }
        .sale-open-bal b { display: block; font-size: 20px; font-weight: 700; margin-top: 2px; }
        .sale-aging { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin: 18px 0 8px; min-height: 210px; align-items: end; }
        .sale-aging__col { display: flex; flex-direction: column; align-items: center; height: 210px; position: relative; }
        .sale-aging__val { font-size: 11px; font-weight: 700; margin-bottom: 4px; }
        .sale-aging__track { flex: 1; width: 10px; background: #f3f4f6; border-radius: 8px; position: relative; overflow: hidden; }
        .sale-aging__bar { position: absolute; left: 0; right: 0; border-radius: 8px; }
        .sale-aging__dot { width: 12px; height: 12px; border-radius: 999px; position: absolute; left: 50%; margin-left: -6px; }
        .sale-aging__lbl { font-size: 11px; color: #64748b; margin-top: 8px; }
        .sale-aging-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .sale-aging-card { border: 1px solid #eee; border-radius: 12px; padding: 12px; }
        .sale-aging-card em { font-style: normal; font-weight: 700; font-size: 13px; }
        .sale-aging-card b { display: block; font-size: 15px; margin-top: 6px; }
        .sale-inv-card { display: block; background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 12px; margin-bottom: 8px; text-decoration: none; color: inherit; }
        .sale-inv-card__row { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; }
        .sale-inv-card h3 { margin: 0; font-size: 15px; font-weight: 700; }
        .sale-inv-card .meta { color: #64748b; font-size: 13px; margin-top: 4px; }
        .sale-status-sent { background: #22c55e; color: #fff; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
        .sale-status-paid { background: #94a3b8; color: #fff; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
        .sale-inv-cols { display: flex; justify-content: space-between; color: #94a3b8; font-size: 11px; margin: 8px 2px 10px; }
        .sale-inv-total { display: flex; justify-content: space-between; font-weight: 700; font-size: 14px; padding: 12px 4px 8px; }
        .sale-seg { display: flex; background: #ececec; border-radius: 8px; overflow: hidden; margin: 10px 0; }
        .sale-seg a, .sale-seg button { flex: 1; border: 0; background: transparent; padding: 8px; font-weight: 700; font-size: 13px; color: #888; text-decoration: none; text-align: center; cursor: pointer; }
        .sale-seg .is-on { background: #4b5563; color: #fff; }
        .sale-pay-total { background: #f3f4f6; border-radius: 8px; padding: 12px; display: flex; justify-content: space-between; font-weight: 700; margin-bottom: 12px; }
        .sale-pay-check {
            display: flex; gap: 12px; align-items: flex-start;
            background: #fff; border: 1px solid #ececec; border-radius: 14px;
            padding: 14px 14px 12px; margin-bottom: 10px;
        }
        .sale-pay-check input[type="checkbox"] {
            appearance: none; -webkit-appearance: none;
            width: 22px; height: 22px; margin-top: 2px; flex-shrink: 0;
            border: 2px solid #cbd5e1; border-radius: 4px; background: #fff;
            position: relative; cursor: pointer;
        }
        .sale-pay-check input[type="checkbox"]:checked {
            background: #2563eb; border-color: #2563eb;
        }
        .sale-pay-check input[type="checkbox"]:checked::after {
            content: ''; position: absolute; left: 6px; top: 2px;
            width: 6px; height: 11px; border: solid #fff; border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .sale-pay-dl {
            display: grid; grid-template-columns: 1fr auto; gap: 8px 16px;
            font-size: 14px; width: 100%; margin: 0;
        }
        .sale-pay-dl dt { color: #9ca3af; font-weight: 500; }
        .sale-pay-dl dd { margin: 0; text-align: right; color: #6b7280; font-weight: 500; }
        .sale-pay-btn {
            width: 100%; border: 0; background: #e53935; color: #fff;
            font-weight: 700; font-size: 15px; letter-spacing: .04em;
            border-radius: 10px; padding: 12px; cursor: pointer; margin: 8px 0 6px;
        }
        .sale-pay-tools {
            display: flex; justify-content: space-around; padding: 16px 0 8px;
            color: #0b1220; font-size: 12px; font-weight: 600; text-align: center;
        }
        .sale-pay-tools button { background: none; border: 0; color: inherit; cursor: pointer; font: inherit; }
        .sale-pay-tools svg { display: block; margin: 0 auto 6px; }
        .sale-sig-pad { width: 100%; height: 180px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; touch-action: none; }
        .sale-pay-sum { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 12px; margin-bottom: 14px; font-size: 14px; }
        .sale-pay-sum .red { color: #e53935; font-weight: 700; }
        .sale-pay-sum-row { display: flex; justify-content: space-between; padding: 6px 0; }
        .sale-pay-modes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
        .sale-pay-chip { border-radius: 999px; padding: 8px; font-weight: 700; font-size: 13px; border: 1.5px solid #e53935; background: #fff; color: #e53935; cursor: pointer; }
        .sale-pay-chip.is-on { background: #9ca3af; color: #fff; border-color: #9ca3af; }
        @media (min-width: 1024px) {
            body.sale-page-create .sale-create-bar {
                margin-top: 16px;
            }
        }
        .pp-grid,
        .sale-cart-lines.is-view-item,
        .sale-cart-lines.is-view-details,
        .sale-cart-lines.is-view-medium,
        .sale-cart-lines.is-view-large,
        #catalogBody.pp-grid,
        .sale-prod-grid.pp-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 12px !important;
        }
        .pp-card {
            background: #fff; border: 1px solid #E3E1DA; border-radius: 14px;
            padding: 13px; display: flex !important; flex-direction: row-reverse; gap: 12px;
            text-decoration: none; color: inherit; text-align: left; min-width: 0;
        }
        .pp-card.is-oos { opacity: .55; }
        .pp-img {
            width: 118px; height: 118px; border-radius: 10px; background: #FBEBD6;
            flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .pp-img img { width: 100%; height: 100%; object-fit: cover; }
        .pp-body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .pp-name { font-size: 13.5px; font-weight: 700; color: #17221E; line-height: 1.25; }
        .pp-pack, .pp-code { font-size: 11px; color: #56645F; margin-top: 2px; }
        .pp-code { font-family: ui-monospace, monospace; font-size: 10px; margin-top: 5px; }
        .pp-price-row { margin-top: 7px; display: flex; align-items: baseline; gap: 7px; flex-wrap: wrap; }
        .pp-price { font-size: 15px; font-weight: 800; color: #0E3F34; }
        .pp-unit-price { font-size: 10.5px; color: #56645F; font-family: ui-monospace, monospace; }
        .pp-last, .sale-ois-card__hist.pp-last {
            font-size: 10.5px; color: #8C9690; margin-top: 4px; background: none; border: 0;
            padding: 0; text-align: left; font-family: inherit; cursor: pointer;
        }
        .pp-oos { font-size: 10px; font-weight: 700; color: #B4432C; background: #FBEAE5; padding: 2px 7px; border-radius: 5px; align-self: flex-start; margin-top: 6px; }
        .pp-bottom { margin-top: auto; display: flex; align-items: center; justify-content: space-between; padding-top: 9px; gap: 8px; }
        .pp-card .sale-ois-qty, .pp-step {
            display: flex; align-items: center; border: 1px solid #E3E1DA; border-radius: 8px; overflow: hidden;
        }
        .pp-card .sale-ois-qty button, .pp-step button {
            width: 27px; height: 27px; border: 0; background: #F6F5F1; font-size: 14px; font-weight: 700; color: #0E3F34;
        }
        .pp-card .sale-qty-input, .pp-qty {
            width: 30px; text-align: center; border: 0; background: #fff; font-weight: 700; font-size: 12.5px;
        }
        .pp-total { font-size: 13px; font-weight: 800; font-family: ui-monospace, monospace; }
        .pp-total.zero { color: #B7BEB9; }
        .pp-card .sale-ois-unitbtns { background: transparent; border: 1px solid #E3E1DA; border-radius: 8px; padding: 0; }
        .pp-card .sale-ois-unitbtn { background: #F6F5F1; color: #56645F; border-radius: 0; min-height: 0; padding: 5px 10px; }
        .pp-card .sale-ois-unitbtn.is-on { background: #175C4C; color: #fff; }
        .pp-card .sale-ois-unitbtn__price { display: none; }
        .sale-ois-chips { scrollbar-width: thin; scrollbar-color: #175C4C #E3E1DA; }
        .sale-ois-chip.is-on { background: #0E3F34; border-color: #0E3F34; color: #fff; }
        .sale-prod-results.pp-grid { position: static; box-shadow: none; max-height: none; }
        @media (max-width: 720px) {
            .pp-card { flex-direction: column; padding: 9px; gap: 8px; }
            .pp-img { width: 100%; height: 64px; }
            .pp-name { font-size: 11.5px; }
            .pp-price { font-size: 12.5px; }
        }
    </style>
    @stack('head')
</head>
<body class="antialiased {{ auth('sale')->check() ? 'sale-authed' : 'sale-guest' }} {{ in_array(optional(request()->route())->getName(), ['sale.orders.create', 'sale.orders.edit'], true) ? 'sale-page-create sale-building-order' : '' }} {{ optional(request()->route())->getName() === 'sale.products' ? 'sale-page-products' : '' }} {{ (optional(request()->route())->getName() === 'sale.orders.show') ? 'sale-page-order-show' : '' }} {{ optional(request()->route())->getName() === 'sale.orders' ? 'sale-page-reports' : '' }} {{ optional(request()->route())->getName() === 'sale.chat' ? 'sale-page-chat'.(! request()->route('channel') ? ' sale-chat-inbox' : ' sale-chat-thread') : '' }}">
@php
    $routeName = optional(request()->route())->getName();
    $isHome = $routeName === 'sale.home';
    $isOrders = in_array($routeName, ['sale.orders', 'sale.orders.show'], true);
    $isCreate = in_array($routeName, ['sale.orders.create', 'sale.orders.edit'], true);
    $isProducts = $routeName === 'sale.products';
        $isCustomers = str_starts_with((string) $routeName, 'sale.customers');
    $isAccount = $routeName === 'sale.account' || $routeName === 'sale.account.location';
    $isDelivery = $routeName === 'sale.delivery';
    $isReports = in_array($routeName, ['sale.orders', 'sale.orders.show'], true);
    $isChat = str_starts_with((string) $routeName, 'sale.chat');
    $isMore = $isAccount || $isProducts || $isDelivery;
    $customerMenuUrl = route('sale.customers');
    $authUser = auth('sale')->user();
    $userInitial = 'S';
    $userName = '';
    if ($authUser) {
        $userName = trim((string) ($authUser->name ?: $authUser->username));
        $nameParts = preg_split('/\s+/', $userName) ?: [];
        $userInitial = strtoupper(mb_substr($nameParts[0] ?? 'S', 0, 1).(count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : '')) ?: 'S';
    }
@endphp

@auth('sale')
<div class="sale-desk-shell">
    {{-- Desktop sidebar --}}
    <aside class="sale-desk-side sale-d-only">
        <div class="flex items-center gap-3 px-2 mb-8">
            <img src="{{ asset('pwa/sale-icon-192.png') }}" alt="" class="h-10 w-10 rounded-xl bg-sale">
            <div>
                <div class="font-extrabold text-[15px] leading-tight">Sales App</div>
                <div class="text-[11px] text-white/50 font-semibold">Representative</div>
            </div>
        </div>
        <nav>
            <a href="{{ route('sale.home') }}" class="sale-side-link {{ $isHome ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/></svg>
                </span>
                Main
            </a>
            <a href="{{ $customerMenuUrl }}" class="sale-side-link {{ $isCustomers ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2 19c1.2-3.2 6.8-3.2 8 0"/><circle cx="17" cy="8" r="2.5"/><path d="M14 19c.8-2.4 5.2-2.4 6 0"/></svg>
                </span>
                Customers
            </a>
            <a href="{{ route('sale.delivery') }}" class="sale-side-link {{ $isDelivery ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/><path d="M5 12h14" stroke-dasharray="2 2"/></svg>
                </span>
                Routes
            </a>
            <a href="{{ route('sale.orders') }}" class="sale-side-link {{ $isReports ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </span>
                Orders
            </a>
            <a href="{{ route('sale.customers') }}" class="sale-side-link {{ $isCreate ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                </span>
                Create order
            </a>
            <a href="{{ route('sale.products') }}" class="sale-side-link {{ $isProducts ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg>
                </span>
                Products
            </a>
            <a href="{{ route('sale.chat') }}" class="sale-side-link {{ $isChat ? 'active' : '' }}" style="position:relative">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>
                </span>
                Chat
                <span data-chat-unread class="sale-chat-badge" hidden>0</span>
            </a>
            <a href="{{ route('sale.account') }}" class="sale-side-link {{ $isAccount ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg>
                </span>
                Settings
            </a>
        </nav>
        <div class="sale-side-footer px-1">
            <div class="px-2 mb-3">
                <div class="text-[11px] text-white/40 mb-1">Signed in</div>
                <div class="text-sm font-semibold truncate">{{ $userName }}</div>
            </div>
            <form method="POST" action="{{ route('sale.logout') }}">
                @csrf
                <button type="submit" class="sale-side-logout">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 18 18" aria-hidden="true">
                        <path d="M7 3H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>
                        <path d="M12 12l3-3-3-3M7 9h8"/>
                    </svg>
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    <div class="sale-desk-main">
        {{-- Desktop top bar --}}
        <header class="sale-desk-top sale-d-flex">
            <div>
                <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Sales workstation</div>
                <div class="font-extrabold text-base leading-tight">@yield('header', 'Orders')</div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('sale.customers') }}" class="sale-btn-sm sale-d-only">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.4a2 2 0 0 0 2-1.5L21 8H7"/></svg>
                    Create order
                </a>
                <div class="h-10 w-10 rounded-full bg-sale text-white flex items-center justify-center font-bold">{{ $userInitial }}</div>
            </div>
        </header>

        {{-- Mobile app header: brand + profile --}}
        <header class="sale-m-only sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-sale-line pt-[env(safe-area-inset-top,0)]">
            <div class="h-14 px-4 flex items-center justify-between gap-3">
                @hasSection('header_left')
                    @yield('header_left')
                @else
                <a href="{{ route('sale.home') }}" class="flex items-center gap-2 min-w-0 no-underline text-inherit">
                    <img src="{{ asset('pwa/sale-icon-192.png') }}" alt="" class="h-8 w-8 rounded-full bg-sale shrink-0">
                </a>
                @endif
                <div class="sale-page-title truncate">@yield('header', 'Sales')</div>
                @hasSection('header_right')
                    @yield('header_right')
                @else
                <button type="button" class="h-9 w-9 rounded-full text-sale flex items-center justify-center border-0 bg-transparent" id="saleSyncBtn" aria-label="Sync" title="Sync">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 12a9 9 0 1 1-2.6-6.3"/><path d="M21 3v6h-6"/></svg>
                </button>
                @endif
            </div>
        </header>

        @if(session('status'))
            @php $st = session('status'); @endphp
            <div class="sale-flash mx-3 mt-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ !empty($st['success']) ? 'bg-sale-soft text-sale-dark border border-rose-200' : 'bg-rose-50 text-rose-900 border border-rose-200' }}">
                {{ is_array($st) ? ($st['msg'] ?? '') : $st }}
            </div>
        @endif

        <main class="sale-main-app px-3 pt-3 w-full">
            <div class="sale-page w-full">
                @yield('content')
            </div>
        </main>
    </div>
</div>

{{-- Mobile bottom: Main · Customers · Chat · Orders · More --}}
<nav class="sale-bottom-nav sale-m-only" aria-label="Primary">
    <div class="sale-bottom-nav__inner">
        <a href="{{ route('sale.home') }}" class="sale-tab {{ $isHome ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/></svg>
            Main
        </a>
        <a href="{{ $customerMenuUrl }}" class="sale-tab {{ $isCustomers ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M4 20c1.6-3.6 14.4-3.6 16 0"/></svg>
            Customers
        </a>
        <a href="{{ route('sale.chat') }}" class="sale-tab {{ $isChat ? 'active' : '' }}" style="position:relative">
            <svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>
            Chat
            <span data-chat-unread class="sale-chat-badge" hidden>0</span>
        </a>
        <a href="{{ route('sale.orders') }}" class="sale-tab {{ $isReports ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            Orders
        </a>
        <button type="button" class="sale-tab {{ $isMore ? 'active' : '' }}" id="saleMoreOpen">
            <svg viewBox="0 0 24 24"><circle cx="6" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="18" cy="12" r="1.6" fill="currentColor" stroke="none"/></svg>
            More
        </button>
    </div>
</nav>

<div id="saleMoreSheet" class="sale-sheet" hidden>
    <div class="sale-sheet__panel">
        <div class="sale-sheet__head is-center">
            <div class="sale-sheet__title">More</div>
            <button type="button" id="saleMoreClose" class="sale-sheet__close" aria-label="Close">×</button>
        </div>
        <div class="sale-sheet__body !pt-1">
            <a href="{{ route('sale.customers') }}" class="sale-act-row">
                <svg viewBox="0 0 24 24"><path d="M8 4h8v3H8z"/><path d="M6 7h12v13H6z"/><path d="M9 11h6M9 15h4"/></svg>
                Create Order
            </a>
            <a href="{{ route('sale.products') }}" class="sale-act-row">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Products
            </a>
            <a href="{{ route('sale.delivery') }}" class="sale-act-row">
                <svg viewBox="0 0 24 24"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                Routes
            </a>
            <a href="{{ route('sale.account') }}" class="sale-act-row">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H8a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V8c.3.6.9 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
                Settings
            </a>
            <form method="POST" action="{{ route('sale.logout') }}">
                @csrf
                <button type="submit" class="sale-act-row" style="color:#e53935">
                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
                    Sign Out
                </button>
            </form>
        </div>
        <div class="sale-sheet__foot">
            <button type="button" class="sale-more-cancel" id="saleMoreCancel">CANCEL</button>
        </div>
    </div>
</div>

@else
    @if(session('status'))
        @php $st = session('status'); @endphp
        <div class="mx-3 mt-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ !empty($st['success']) ? 'bg-sale-soft text-sale-dark border border-rose-200' : 'bg-rose-50 text-rose-900 border border-rose-200' }}">
            {{ is_array($st) ? ($st['msg'] ?? '') : $st }}
        </div>
    @endif
    <main>
        @yield('content')
    </main>
@endauth

{{-- PWA install: always available (login + logged-in) --}}
<div id="sale_pwa_install_bar" class="sale-install" hidden>
    <div class="sale-install__inner">
        <img src="{{ asset('pwa/sale-icon-192.png') }}" alt="" class="sale-install__logo" width="36" height="36">
        <div class="flex-1 min-w-0">
            <div class="text-[12px] font-bold">Install Sales app</div>
            <div class="text-[10px] text-white/70">Faster access — open from home screen</div>
        </div>
        <button type="button" id="sale_pwa_install_btn" class="sale-install__btn">Install</button>
        <button type="button" id="sale_pwa_dismiss_btn" class="sale-install__close" aria-label="Dismiss">×</button>
    </div>
</div>

<div id="saleSyncToast" class="sale-added-msg" hidden>Synced</div>
<script>
window.__SALE_PWA__ = { swUrl: @json(url('/sale/pwa/sw.js')), startUrl: @json(url('/sale/login')) };
window.saleAppSync = async function () {
    const btn = document.getElementById('saleSyncBtn');
    if (btn) btn.classList.add('is-syncing');
    try {
        if ('serviceWorker' in navigator) {
            const regs = await navigator.serviceWorker.getRegistrations();
            await Promise.all(regs.map((r) => r.update()));
        }
        if ('caches' in window) {
            const keys = await caches.keys();
            await Promise.all(keys.filter((k) => k.indexOf('japspos-sale-pwa') === 0).map((k) => caches.delete(k)));
        }
        try { sessionStorage.setItem('sale_just_synced', '1'); } catch (e) {}
    } catch (e) {
        try { sessionStorage.setItem('sale_just_synced', '1'); } catch (err) {}
    }
    const url = new URL(window.location.href);
    url.searchParams.set('_sync', String(Date.now()));
    window.location.replace(url.toString());
};
(function () {
    function closeOrderView() {
        const sheet = document.getElementById('orderViewSheet');
        if (!sheet) return;
        sheet.hidden = true;
        sheet.classList.remove('is-open');
    }
    window.saleOpenOrderView = function (e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const sheet = document.getElementById('orderViewSheet');
        if (!sheet) return;
        document.body.appendChild(sheet);
        sheet.hidden = false;
        sheet.classList.add('is-open');
    };
    document.addEventListener('click', function (e) {
        const t = e.target.closest && e.target.closest('button, .sale-view-opt, a, label');
        const openMap = {
            orderViewBtn: 'orderViewSheet',
            orderSortBtn: 'orderSortSheet',
            orderFilterBtn: 'orderFilterSheet',
            prodFilterBtn: 'prodFilterSheet',
        };
        const openId = t && t.id ? openMap[t.id] : null;
        if (openId) {
            window.saleOpenSheet(openId, e);
            return;
        }
        if (e.target && e.target.id === 'orderViewBtn') {
            window.saleOpenSheet('orderViewSheet', e);
        }
    }, true);
    const sheet = document.getElementById('saleMoreSheet');
    const open = document.getElementById('saleMoreOpen');
    const close = document.getElementById('saleMoreClose');
    const cancel = document.getElementById('saleMoreCancel');
    function hide() { if (sheet) sheet.hidden = true; }
    function show() { if (sheet) sheet.hidden = false; }
    if (open) open.addEventListener('click', show);
    if (close) close.addEventListener('click', hide);
    if (cancel) cancel.addEventListener('click', hide);
    if (sheet) sheet.addEventListener('click', (e) => { if (e.target === sheet) hide(); });
    document.getElementById('saleSyncBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        if (typeof window.saleAppSync === 'function') window.saleAppSync();
    });
    try {
        if (sessionStorage.getItem('sale_just_synced') === '1') {
            sessionStorage.removeItem('sale_just_synced');
            const toast = document.getElementById('saleSyncToast');
            if (toast) {
                toast.hidden = false;
                setTimeout(() => { toast.hidden = true; }, 1800);
            }
            const url = new URL(window.location.href);
            if (url.searchParams.has('_sync')) {
                url.searchParams.delete('_sync');
                history.replaceState({}, '', url.pathname + url.search + url.hash);
            }
        }
    } catch (e) {}
})();
</script>
<script src="{{ asset('js/sale-pwa.js') }}?v={{ config('app.asset_version', '1') }}" defer></script>
@auth('sale')
    @include('layouts.partials.team-chat-nav-poller', ['teamChatUnreadUrl' => route('sale.chat.unread')])
@endauth
@include('partials.app-scan-miss-sound')
@stack('scripts')
</body>
</html>
