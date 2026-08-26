<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Sales">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="{{ url('/sale/pwa/manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('pwa/sale-icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('pwa/sale-icon-192.png') }}">
    <title>@yield('title', 'Sales App') — {{ config('app.name', 'JAPS POS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sale: { DEFAULT: '#0f766e', dark: '#0d5f59', soft: '#ccfbf1', ink: '#0b1220', mist: '#f1f5f9', line: '#e2e8f0' }
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: #f1f5f9; color: #0b1220; margin: 0; }
        .sale-input {
            width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: .75rem .9rem;
            font-size: .9375rem; background: #fff; outline: none; box-sizing: border-box;
        }
        .sale-input:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.15); }
        .sale-btn {
            background: #0f766e; color: #fff; font-weight: 700; border: 0; border-radius: 12px;
            padding: .85rem 1.25rem; width: 100%; font-size: .95rem; cursor: pointer;
        }
        .sale-btn:disabled { opacity: .55; cursor: not-allowed; }
        .sale-btn-ghost {
            background: #fff; color: #0f766e; border: 1px solid #cbd5e1; font-weight: 700;
            border-radius: 12px; padding: .85rem 1.25rem; width: 100%; cursor: pointer;
        }
        .sale-btn-sm {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background: #0f766e; color: #fff; font-weight: 700; border: 0; border-radius: 10px;
            padding: .55rem 1rem; font-size: .875rem; cursor: pointer; text-decoration: none;
            width: auto;
        }
        .sale-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px;
            box-shadow: 0 1px 2px rgba(15,23,42,.03);
        }
        .sale-badge {
            display: inline-flex; align-items: center; border-radius: 999px;
            padding: 3px 9px; font-size: 11px; font-weight: 700;
        }
        .sale-badge--paid { background: #d1fae5; color: #065f46; }
        .sale-badge--due { background: #fee2e2; color: #991b1b; }
        .sale-badge--partial { background: #fef3c7; color: #92400e; }
        .sale-badge--draft { background: #e0f2fe; color: #075985; }
        .sale-badge--ordered { background: #e0f2fe; color: #075985; }
        .sale-badge--completed { background: #d1fae5; color: #065f46; }

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
            background: #ccfbf1; color: #0f766e;
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
            background: #f0fdfa; color: #0f766e;
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
            background: #f1f5f9; color: #0f766e;
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
            background: #f0fdfa; color: #0f766e;
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
            color: #0f766e;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-sizing: border-box;
        }
        .sale-pager__btn:hover { background: #f0fdfa; }
        .sale-pager__btn--active {
            background: #0f766e;
            border-color: #0f766e;
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
        .sale-tab.active { color: #0f766e; }
        .sale-tab.active svg { stroke-width: 2.2; }
        .sale-tab-fab {
            width: 54px; height: 54px; border-radius: 999px;
            background: #0f766e; color: #fff;
            display: flex; align-items: center; justify-content: center;
            margin-top: -22px; margin-bottom: 2px;
            box-shadow: 0 6px 16px rgba(15,118,110,.4);
            text-decoration: none;
        }
        .sale-tab-fab svg { width: 26px; height: 26px; stroke: #fff; stroke-width: 2.2; fill: none; }
        .sale-tab-fab.active,
        .sale-tab-fab:active { background: #0d5f59; }
        .sale-bottom-nav__center-label {
            font-size: 10px; font-weight: 700; color: #64748b; line-height: 1;
        }
        .sale-bottom-nav__center-label.active { color: #0f766e; }
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
            body.sale-page-chat .sale-main-app {
                padding: 0 !important;
                height: 100dvh;
                min-height: 100dvh;
                max-height: 100dvh;
                overflow: hidden;
            }
            body.sale-page-chat .sale-page {
                padding: 0 !important;
                max-width: none !important;
                height: 100%;
                overflow: hidden;
            }
            body.sale-page-chat header.sale-m-only {
                display: none !important;
            }
            body.sale-page-chat .sale-flash { display: none; }
            body.sale-page-chat,
            body.sale-page-chat .sale-desk-shell,
            body.sale-page-chat .sale-desk-main {
                overflow: hidden;
                height: 100dvh;
                max-height: 100dvh;
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
            border: 1.5px solid #0f766e;
            color: #0f766e;
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
            margin: 0; font-size: 22px; font-weight: 800; color: #0b1220;
        }
        .sale-del__sub { margin: 2px 0 0; font-size: 13px; color: #64748b; font-weight: 600; }
        .sale-exp__add {
            display: inline-flex; align-items: center; justify-content: center;
            background: #0f766e; color: #fff; border-radius: 10px;
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
            background: #ccfbf1; color: #0f766e; border-radius: 999px;
            display: inline-flex; align-items: center; justify-content: center; font-weight: 800;
        }
        .sale-exp-customer__list .sale-pick-empty { padding: 16px; text-align: center; color: #94a3b8; font-size: 13px; font-weight: 600; }
        .sale-exp-customer__chip .sale-pick-avatar {
            width: 42px; height: 42px; border-radius: 999px;
            background: #ccfbf1; color: #0f766e;
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
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
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
            background: #ccfbf1; border-color: #99f6e4;
        }
        .sale-action-tile__icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: #0f766e; color: #fff;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sale-action-tile__icon--soft {
            background: #e2e8f0; color: #0f766e;
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
        .sale-install__logo { width: 36px; height: 36px; border-radius: 9px; background: #0f766e; }
        .sale-install__btn {
            flex-shrink: 0; background: #0f766e; color: #fff; border: 0; border-radius: 8px;
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
        .sale-nav-link:hover { background: #f1f5f9; color: #0f766e; }
        .sale-nav-link.active { background: #ccfbf1; color: #0f766e; }

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
            body.sale-page-chat .sale-main-app {
                padding: 0 !important;
                min-height: calc(100dvh - 64px);
            }
            body.sale-page-chat .sale-page {
                padding: 0 !important;
                height: calc(100dvh - 64px);
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
            .sale-side-link.active { background: #0f766e; color: #fff; }
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
            body.sale-page-create #stepCart.sale-order-build #goShippingBtn,
            body.sale-page-create .sale-order-build__submit {
                width: auto !important;
                min-width: 96px;
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
        .sale-act--view { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
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
            background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4;
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
        .sale-prod-tile__price { font-size: 12px; font-weight: 800; color: #0f766e; }
        .sale-prod-tile__add {
            display: inline-flex; align-items: center; justify-content: center;
            background: #0f766e; color: #fff; border-radius: 8px;
            font-size: 11px; font-weight: 800; padding: 6px 8px; text-align: center;
        }
        .sale-prod-app__list { display: flex; flex-direction: column; gap: 10px; }
        .sale-prod-card {
            display: flex; align-items: center; gap: 12px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
            padding: 12px; box-shadow: 0 1px 2px rgba(15,23,42,.03);
        }
        .sale-prod-card__thumb {
            width: 56px; height: 56px; border-radius: 12px; flex-shrink: 0;
            overflow: hidden; background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            color: #94a3b8;
        }
        .sale-prod-card__thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
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
        .sale-prod-card__add {
            flex-shrink: 0; background: #0f766e; color: #fff; text-decoration: none;
            font-weight: 800; font-size: 12px; border-radius: 10px; padding: 8px 12px;
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
            background: #0f766e;
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
            border-color: #0f766e;
            background:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' d='M3.5 8.5l3 3 6-6'/%3E%3C/svg%3E")
                center / 12px 12px no-repeat,
                #0f766e;
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
            background: #ccfbf1; color: #0f766e;
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
            gap: 8px;
            background: #0f766e;
            color: #fff;
            padding: 10px 12px;
            padding-top: calc(10px + env(safe-area-inset-top, 0px));
            position: sticky;
            top: 0;
            z-index: 30;
            flex-shrink: 0;
        }
        .sale-order-build__iconbtn {
            border: 0; background: transparent; color: #fff;
            width: 40px; height: 40px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; flex-shrink: 0;
        }
        .sale-order-build__titles { flex: 1 1 auto; min-width: 0; }
        .sale-order-build__title { font-size: 17px; font-weight: 800; line-height: 1.2; color: #fff; }
        .sale-order-build__sub { font-size: 12px; font-weight: 600; opacity: .92; margin-top: 2px; color: #fff; }
        .sale-order-build__submit {
            border: 0 !important;
            background: #fff !important;
            color: #0f766e !important;
            font-weight: 800;
            font-size: 12px;
            letter-spacing: .04em;
            border-radius: 8px;
            padding: 10px 14px;
            cursor: pointer;
            flex: 0 0 auto;
            width: auto !important;
            min-width: 88px;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
            line-height: 1.2;
        }
        .sale-order-build__submit:hover {
            background: #f0fdfa !important;
        }
        .sale-order-build__submit:active {
            transform: translateY(1px);
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
        .sale-toggle input:checked + .sale-toggle__track { background: #0f766e; }
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
            border-color: #0f766e;
            background:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' d='M3.5 8.5l3 3 6-6'/%3E%3C/svg%3E")
                center / 12px 12px no-repeat, #0f766e;
        }
        .sale-fulfill__dot { display: none; }

        .sale-last-qty-status {
            font-size: 12px;
            font-weight: 700;
            color: #0f766e;
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
            background: #0f766e; border-color: #0f766e; color: #fff;
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
            border: 0; background: transparent; color: #0f766e;
            font-size: 13px; font-weight: 800; cursor: pointer; padding: 0;
        }
        .sale-order-build .sale-cart-scroll {
            flex: 1 1 auto;
            min-height: 180px;
            max-height: none !important;
            margin: 0;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .sale-order-build .sale-cart-lines {
            display: flex;
            flex-direction: column;
            padding: 8px;
            gap: 8px;
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
            padding: 10px 2px 8px;
            flex-shrink: 0;
            background: #fff;
            border-top: 1px solid #f1f5f9;
            margin-top: 0;
        }
        .sale-order-build__total strong { font-size: 18px; font-weight: 800; color: #0f766e; }

        /* Build step: fill space above bottom nav; cart list scrolls fully */
        body.sale-building-order {
            background: #fff;
            overflow: hidden;
        }
        body.sale-building-order.sale-page-create .sale-main-app,
        body.sale-building-order .sale-main-app {
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            box-sizing: border-box !important;
            min-height: calc(100dvh - 72px - env(safe-area-inset-bottom, 0px)) !important;
            height: calc(100dvh - 72px - env(safe-area-inset-bottom, 0px)) !important;
            max-height: calc(100dvh - 72px - env(safe-area-inset-bottom, 0px)) !important;
            background: #fff !important;
        }
        body.sale-building-order .sale-m-only.sticky { display: none !important; }
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
            background: #0f766e;
            color: #fff;
            border: 0;
            font-weight: 700;
            font-size: 13px;
            border-radius: 10px;
            padding: .55rem .9rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25);
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
        .sale-cart-total strong { color: #0f766e; font-size: 1.2rem; }
        .sale-cart-pay { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .sale-pay-chip {
            display: flex; align-items: center; gap: 8px;
            border: 2px solid #e2e8f0; border-radius: 12px; padding: 10px 12px;
            font-size: 13px; font-weight: 700; cursor: pointer; background: #fff;
        }
        .sale-pay-chip:has(:checked) { border-color: #0f766e; background: #f0fdfa; }
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
            }
            body.sale-page-products .sale-prod-app__search {
                max-width: none;
            }
            body.sale-page-products .sale-prod-card__thumb {
                width: 64px;
                height: 64px;
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
            border: 0; background: #f0fdfa; color: #0f766e; cursor: pointer;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sale-prod-app__dot {
            position: absolute; top: 8px; right: 8px; width: 8px; height: 8px;
            border-radius: 999px; background: #0f766e; border: 2px solid #f0fdfa;
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
            background: #0f766e; border-color: #0f766e; color: #fff;
            box-shadow: 0 4px 10px rgba(15,118,110,.25);
        }
        .sale-prod-app__meta {
            display: flex; align-items: center; justify-content: space-between;
            font-size: 12px; font-weight: 700; color: #64748b; min-height: 20px;
        }
        .sale-prod-app__clear {
            border: 0; background: transparent; color: #0f766e; font-weight: 800;
            font-size: 12px; cursor: pointer; padding: 0;
        }
        .sale-prod-app__clear.hidden { display: none; }
        .sale-prod-app__empty {
            text-align: center; color: #94a3b8; font-size: 14px; font-weight: 600;
            padding: 40px 12px;
        }
        .sale-prod-app__empty.hidden { display: none; }

        .sale-sheet {
            position: fixed; inset: 0; z-index: 95;
            background: rgba(15,23,42,.45);
            display: flex; align-items: flex-end; justify-content: center;
        }
        .sale-sheet[hidden] { display: none !important; }
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
        .sale-sheet__body { padding: 16px; }
        .sale-sheet__foot {
            display: flex; justify-content: flex-end; gap: 10px;
            padding: 0 16px 8px;
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
            background: #0f766e; color: #fff;
            font-size: 13px; font-weight: 800;
            padding: 10px 18px; border-radius: 999px;
            box-shadow: 0 8px 24px rgba(15,118,110,.35);
            pointer-events: none;
        }
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
        .sale-catalog__row--all { color: #0f766e; }
        .sale-catalog__chev { color: #94a3b8; font-size: 18px; }
        .sale-catalog__add {
            width: 32px; height: 32px; border-radius: 999px;
            background: #0f766e; color: #fff; display: flex; align-items: center; justify-content: center;
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
                padding-bottom: calc(180px + env(safe-area-inset-bottom, 0)) !important;
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
                background: #0f766e;
                color: #fff;
                opacity: 1;
                box-shadow: 0 6px 16px rgba(15, 118, 110, 0.35);
            }
            /* On cart step, Order button stays in card — only shipping submit is fixed */
            body.sale-page-create #stepCart .sale-btn { position: static; box-shadow: 0 6px 16px rgba(15, 118, 110, 0.25); }
        }
        @media (min-width: 1024px) {
            body.sale-page-create .sale-create-bar {
                margin-top: 16px;
            }
        }
    </style>
    @stack('head')
</head>
<body class="antialiased {{ auth('sale')->check() ? 'sale-authed' : 'sale-guest' }} {{ in_array(optional(request()->route())->getName(), ['sale.orders.create', 'sale.orders.edit'], true) ? 'sale-page-create' : '' }} {{ optional(request()->route())->getName() === 'sale.orders.create' ? 'sale-picking-customer' : '' }} {{ (optional(request()->route())->getName() === 'sale.products') ? 'sale-page-products' : '' }} {{ (optional(request()->route())->getName() === 'sale.orders.show') ? 'sale-page-order-show' : '' }} {{ optional(request()->route())->getName() === 'sale.chat' ? 'sale-page-chat'.(! request()->route('channel') ? ' sale-chat-inbox' : ' sale-chat-thread') : '' }}">
@php
    $routeName = optional(request()->route())->getName();
    $isHome = $routeName === 'sale.home';
    $isOrders = in_array($routeName, ['sale.orders', 'sale.orders.show', 'sale.orders.edit'], true);
    $isCreate = in_array($routeName, ['sale.orders.create', 'sale.orders.edit'], true);
    $isProducts = $routeName === 'sale.products';
    $isCustomers = in_array($routeName, ['sale.customers', 'sale.customers.create'], true);
    $isAccount = $routeName === 'sale.account' || $routeName === 'sale.account.location';
    $isChat = $routeName === 'sale.chat' || str_starts_with((string) $routeName, 'sale.chat');
    $isDelivery = $routeName === 'sale.delivery';
    $customerMenuUrl = route('sale.customers');
    $authUser = auth('sale')->user();
    $userInitial = 'S';
    $userName = '';
    if ($authUser) {
        $userName = (string) $authUser->name;
        $userInitial = strtoupper(mb_substr(preg_replace('/\s+/', '', $userName) ?: 'S', 0, 2));
        if (mb_strlen($userInitial) < 1) {
            $userInitial = 'S';
        }
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
                Dashboard
            </a>
            <a href="{{ route('sale.orders.create') }}" class="sale-side-link {{ $isCreate ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                </span>
                Create
            </a>
            <a href="{{ route('sale.chat') }}" class="sale-side-link {{ $isChat ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/></svg>
                </span>
                Chat
            </a>
            <a href="{{ route('sale.orders') }}" class="sale-side-link {{ $isOrders ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </span>
                Orders
            </a>
            <a href="{{ route('sale.delivery') }}" class="sale-side-link {{ $isDelivery ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                </span>
                Delivery
            </a>
            <a href="{{ route('sale.products') }}" class="sale-side-link {{ $isProducts ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg>
                </span>
                Products
            </a>
            <a href="{{ $customerMenuUrl }}" class="sale-side-link {{ $isCustomers ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2 19c1.2-3.2 6.8-3.2 8 0"/><circle cx="17" cy="8" r="2.5"/><path d="M14 19c.8-2.4 5.2-2.4 6 0"/></svg>
                </span>
                Customers
            </a>
            <a href="{{ route('sale.account') }}" class="sale-side-link {{ $isAccount ? 'active' : '' }}">
                <span class="sale-side-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg>
                </span>
                Account
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
                <div class="font-extrabold text-lg leading-tight">@yield('header', 'Orders')</div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('sale.orders.create') }}" class="sale-btn-sm sale-d-only">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.4a2 2 0 0 0 2-1.5L21 8H7"/></svg>
                    Create order
                </a>
                <div class="h-10 w-10 rounded-full bg-sale text-white flex items-center justify-center font-bold">{{ $userInitial }}</div>
            </div>
        </header>

        {{-- Mobile app header: brand + profile --}}
        <header class="sale-m-only sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-sale-line pt-[env(safe-area-inset-top,0)]">
            <div class="h-14 px-4 flex items-center justify-between gap-3">
                <a href="{{ route('sale.home') }}" class="flex items-center gap-2 min-w-0 no-underline text-inherit">
                    <img src="{{ asset('pwa/sale-icon-192.png') }}" alt="" class="h-8 w-8 rounded-full bg-sale shrink-0">
                    <span class="font-extrabold text-[15px] truncate">{{ config('app.name', 'Sales') }}</span>
                </a>
                <details class="sale-profile-menu relative shrink-0">
                    <summary class="list-none cursor-pointer h-9 w-9 rounded-full bg-sale text-white flex items-center justify-center font-bold text-sm select-none">
                        {{ $userInitial }}
                    </summary>
                    <div class="sale-profile-panel absolute right-0 mt-2 w-52 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden z-50">
                        <div class="px-3 py-2.5 text-sm font-bold text-slate-800 border-b border-slate-100 flex items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg>
                            <span class="truncate">{{ strtoupper($userName) }}</span>
                        </div>
                        <a href="{{ route('sale.account') }}" class="block px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 no-underline">Account</a>
                        <form method="POST" action="{{ route('sale.logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-3 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 flex items-center gap-2 border-0 bg-transparent cursor-pointer">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="M16 16l4-4-4-4M10 12h10"/></svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        @if(session('status'))
            @php $st = session('status'); @endphp
            <div class="sale-flash mx-3 mt-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ !empty($st['success']) ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : 'bg-rose-50 text-rose-900 border border-rose-200' }}">
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

{{-- Mobile bottom: Home · Create · Chat · Orders · Delivery --}}
<nav class="sale-bottom-nav sale-m-only" aria-label="Primary">
    <div class="sale-bottom-nav__inner">
        <a href="{{ route('sale.home') }}" class="sale-tab {{ $isHome ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/></svg>
            Home
        </a>
        <a href="{{ route('sale.orders.create') }}" class="sale-tab {{ $isCreate ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            Create
        </a>
        <a href="{{ route('sale.chat') }}" class="sale-tab {{ $isChat ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/></svg>
            Chat
        </a>
        <a href="{{ route('sale.orders') }}" class="sale-tab {{ $isOrders ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M3 7h18v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Orders
        </a>
        <a href="{{ route('sale.delivery') }}" class="sale-tab {{ $isDelivery ? 'active' : '' }}">
            <svg viewBox="0 0 24 24"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            Delivery
        </a>
    </div>
</nav>

@else
    @if(session('status'))
        @php $st = session('status'); @endphp
        <div class="mx-3 mt-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ !empty($st['success']) ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : 'bg-rose-50 text-rose-900 border border-rose-200' }}">
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

<script>
window.__SALE_PWA__ = { swUrl: @json(url('/sale/pwa/sw.js')), startUrl: @json(url('/sale/login')) };
</script>
<script src="{{ asset('js/sale-pwa.js') }}?v={{ config('app.asset_version', '1') }}" defer></script>
@stack('scripts')
</body>
</html>
