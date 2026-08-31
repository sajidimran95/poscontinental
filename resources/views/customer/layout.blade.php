<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#e11d48">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Customer">
    <link rel="manifest" href="{{ url('/customer/pwa/manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('pwa/customer-icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('pwa/customer-icon-192.png') }}">
    <title>@yield('title', 'Customer') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/pwa.css'])
    <style>
        :root {
            --ca-brand: #e11d48;
            --ca-brand-soft: #fff1f2;
            --ca-ink: #0f172a;
            --ca-muted: #64748b;
            --ca-line: rgba(15, 23, 42, .08);
            --ca-surface: rgba(255, 255, 255, .82);
            --ca-radius: 20px;
            --ca-shadow: 0 10px 30px rgba(15, 23, 42, .06);
            --ca-shadow-lg: 0 20px 50px rgba(225, 29, 72, .12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ca-ink);
            font-family: 'DM Sans', system-ui, sans-serif;
            background:
                radial-gradient(1200px 600px at 10% -10%, rgba(225, 29, 72, .12), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(251, 113, 133, .14), transparent 50%),
                linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100dvh;
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3, .ca-display { font-family: Outfit, system-ui, sans-serif; letter-spacing: -0.02em; }

        .ca-shell { min-height: 100dvh; display: flex; flex-direction: column; }
        .ca-main {
            flex: 1; width: 100%; max-width: 1080px; margin: 0 auto;
            padding: 16px 16px calc(96px + env(safe-area-inset-bottom, 0));
            animation: caFade .35s ease both;
        }
        @keyframes caFade {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: none; }
        }

        .ca-side { display: none; }
        .ca-top {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 12px 16px; padding-top: max(12px, env(safe-area-inset-top, 0));
            position: sticky; top: 0; z-index: 40;
            background: rgba(255,255,255,.72);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--ca-line);
        }
        .ca-desk-bar { display: none; }

        .ca-bottom {
            position: fixed; left: 12px; right: 12px; bottom: calc(10px + env(safe-area-inset-bottom, 0));
            z-index: 90; display: flex; justify-content: space-around; gap: 2px;
            padding: 8px;
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            border: 1px solid var(--ca-line);
            border-radius: 22px;
            box-shadow: 0 12px 40px rgba(15,23,42,.12);
        }
        .ca-tab {
            flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px;
            color: #94a3b8; text-decoration: none; font-size: 10px; font-weight: 700;
            padding: 8px 4px; border-radius: 16px; transition: .2s ease;
        }
        .ca-tab svg { width: 22px; height: 22px; }
        .ca-tab.active {
            color: var(--ca-brand);
            background: var(--ca-brand-soft);
        }

        .ca-card {
            background: var(--ca-surface);
            backdrop-filter: blur(10px);
            border: 1px solid var(--ca-line);
            border-radius: var(--ca-radius);
            padding: 16px;
            box-shadow: var(--ca-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .ca-card:active { transform: scale(.995); }

        .ca-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
            color: #fff; font-weight: 800; border: 0; border-radius: 14px;
            padding: .9rem 1.15rem; width: 100%; cursor: pointer; text-decoration: none;
            font-size: .95rem; font-family: Outfit, system-ui, sans-serif;
            box-shadow: 0 10px 24px rgba(225, 29, 72, .28);
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        }
        .ca-btn:hover { filter: brightness(1.05); box-shadow: var(--ca-shadow-lg); }
        .ca-btn:active { transform: translateY(1px); }
        .ca-btn:disabled { opacity: .45; cursor: not-allowed; box-shadow: none; }

        .ca-input {
            width: 100%; border: 1px solid var(--ca-line); border-radius: 14px;
            padding: .85rem 1rem .85rem 2.6rem; background: #fff; outline: none;
            font-size: .95rem; font-weight: 600; color: var(--ca-ink, #0f172a);
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .ca-input:focus {
            border-color: var(--ca-brand);
            box-shadow: 0 0 0 4px rgba(225, 29, 72, .12);
        }

        .ca-badge {
            display: inline-flex; border-radius: 999px; padding: 4px 10px;
            font-size: 11px; font-weight: 800; border: 1px solid currentColor;
        }
        .ca-badge--paid { color: #16a34a; background: #f0fdf4; }
        .ca-badge--due { color: #e11d48; background: #fff1f2; }
        .ca-badge--partial { color: #d97706; background: #fffbeb; }

        .ca-side-link {
            display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-radius: 14px;
            color: var(--ca-muted); font-weight: 700; font-size: 14px; text-decoration: none;
            border: 0; background: transparent; cursor: pointer; width: 100%; transition: .15s ease;
        }
        .ca-side-link.active, .ca-side-link:hover {
            background: var(--ca-brand-soft); color: var(--ca-brand);
        }

        .ca-logo {
            width: 42px; height: 42px; border-radius: 14px; object-fit: contain; background: #fff;
            border: 1px solid var(--ca-line); flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(15,23,42,.06);
        }
        .ca-logo-fb {
            width: 42px; height: 42px; border-radius: 14px;
            background: linear-gradient(135deg, #fb7185, #e11d48);
            color: #fff; font-weight: 800; font-family: Outfit, sans-serif;
            display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .ca-avatar {
            width: 42px; height: 42px; border-radius: 999px;
            background: linear-gradient(135deg, #ffe4e6, #fecdd3);
            color: var(--ca-brand); font-weight: 800; font-family: Outfit, sans-serif;
            display: inline-flex; align-items: center; justify-content: center;
            border: 2px solid #fff; cursor: pointer; flex-shrink: 0; padding: 0; font-size: 15px;
            box-shadow: 0 4px 14px rgba(225, 29, 72, .18);
            transition: transform .15s ease;
        }
        .ca-avatar:active { transform: scale(.96); }

        .ca-menu { position: relative; }
        .ca-drop {
            display: none; position: absolute; right: 0; top: calc(100% + 10px); min-width: 220px;
            background: rgba(255,255,255,.96); border: 1px solid var(--ca-line); border-radius: 18px;
            box-shadow: 0 18px 40px rgba(15,23,42,.14); padding: 8px; z-index: 70;
            backdrop-filter: blur(12px); animation: caFade .18s ease both;
        }
        .ca-drop.open { display: block; }
        .ca-drop a, .ca-drop button {
            display: flex; width: 100%; align-items: center; gap: 8px; text-align: left;
            padding: 11px 12px; border-radius: 12px; border: 0; background: transparent;
            color: #334155; font-weight: 700; font-size: 13px; text-decoration: none; cursor: pointer;
        }
        .ca-drop a:hover, .ca-drop button:hover { background: #f8fafc; }
        .ca-drop .danger { color: #e11d48; }

        .text-brand { color: var(--ca-brand) !important; }
        .fulfill-opt, .fulfill-opt {
            border: 1px solid var(--ca-line); border-radius: 16px; padding: 12px 6px;
            font-size: 11px; font-weight: 800; color: var(--ca-muted); cursor: pointer;
            background: rgba(255,255,255,.9); display: flex; flex-direction: column;
            align-items: center; gap: 4px; transition: .18s ease; font-family: Outfit, sans-serif;
        }
        .fulfill-opt.is-on, .fulfill-opt.is-on {
            background: linear-gradient(135deg, #e11d48, #be123c);
            border-color: transparent; color: #fff;
            box-shadow: 0 10px 22px rgba(225, 29, 72, .25);
        }

        .ca-flash {
            border-radius: 14px; padding: 12px 14px; font-size: 13px; font-weight: 700; margin-bottom: 12px;
        }
        .ca-flash--ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .ca-flash--err { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }

        .ca-added-msg {
            position: fixed; left: 50%; bottom: calc(88px + env(safe-area-inset-bottom, 0px));
            transform: translateX(-50%);
            z-index: 110; padding: 10px 18px; border-radius: 999px;
            background: #0f172a; color: #fff; font-weight: 800; font-size: 13px;
            font-family: Outfit, system-ui, sans-serif;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .28);
            pointer-events: none; white-space: nowrap;
        }
        .ca-added-msg[hidden] { display: none !important; }
        @media (min-width: 1024px) {
            .ca-added-msg { bottom: 32px; }
        }

        .ca-scan-overlay {
            position: fixed; inset: 0; z-index: 100; background: rgba(15,23,42,.94);
            display: none; flex-direction: column; color: #fff;
        }
        .ca-scan-overlay.open { display: flex; }
        .ca-scan-overlay__head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px; padding-top: max(16px, env(safe-area-inset-top, 0));
        }
        .ca-scan-overlay__title { font-family: Outfit, sans-serif; font-weight: 800; font-size: 1.1rem; }
        .ca-scan-overlay__close {
            width: 42px; height: 42px; border: 0; border-radius: 999px; background: rgba(255,255,255,.12);
            color: #fff; font-size: 22px; cursor: pointer; line-height: 1;
        }
        .ca-scan-overlay__body { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 12px 16px 24px; }
        #caScanReader { width: min(100%, 420px); border-radius: 20px; overflow: hidden; background: #000; }
        #caScanReader video { border-radius: 20px; }
        .ca-scan-overlay__hint { margin-top: 14px; font-size: 13px; color: #cbd5e1; text-align: center; font-weight: 600; }
        .ca-scan-overlay__error { margin-top: 10px; font-size: 13px; color: #fda4af; text-align: center; font-weight: 700; display: none; }

        .ca-page-title { font-size: 1.65rem; font-weight: 800; line-height: 1.15; margin: 0; font-family: Outfit, system-ui, sans-serif; letter-spacing: -0.02em; }
        .ca-page-sub { color: var(--ca-muted); font-size: .875rem; font-weight: 600; margin-top: 4px; }
        .ca-section-title { font-size: 1.05rem; font-weight: 800; margin: 0; font-family: Outfit, system-ui, sans-serif; }
        .ca-link { color: var(--ca-brand); font-weight: 800; text-decoration: none; font-size: .875rem; }
        .ca-display { font-family: Outfit, system-ui, sans-serif; }
        .text-brand { color: var(--ca-brand) !important; }

        .fulfill-opt {
            border: 1px solid var(--ca-line); border-radius: 16px; padding: 12px 6px;
            font-size: 11px; font-weight: 800; color: var(--ca-muted); cursor: pointer;
            background: rgba(255,255,255,.95); transition: .18s ease; font-family: Outfit, sans-serif;
        }
        .fulfill-opt.is-on {
            background: linear-gradient(135deg, #e11d48, #be123c);
            border-color: transparent; color: #fff;
            box-shadow: 0 10px 22px rgba(225, 29, 72, .25);
        }

        .ca-catalog {
            position: fixed; inset: 0; z-index: 90;
            background: rgba(15, 23, 42, .45);
            display: flex; align-items: flex-end; justify-content: center;
        }
        .ca-catalog[hidden] { display: none !important; }
        .ca-catalog__panel {
            width: 100%; max-width: 560px; max-height: 88dvh;
            background: #fff; border-radius: 24px 24px 0 0;
            display: flex; flex-direction: column;
            box-shadow: 0 -12px 40px rgba(15, 23, 42, .18);
        }
        .ca-catalog__head {
            display: flex; align-items: center; gap: 8px;
            padding: 14px 16px; border-bottom: 1px solid var(--ca-line);
            font-family: Outfit, system-ui, sans-serif;
        }
        .ca-catalog__back, .ca-catalog__close {
            width: 36px; height: 36px; border-radius: 12px; border: 0;
            background: #f1f5f9; color: #0f172a; font-size: 20px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
        }
        .ca-catalog__back.hidden { visibility: hidden; }
        .ca-catalog__body {
            overflow: auto; padding: 4px 0 calc(24px + env(safe-area-inset-bottom, 0));
            -webkit-overflow-scrolling: touch; flex: 1;
        }
        .ca-catalog__row {
            width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 16px; border: 0; background: transparent; text-align: left;
            cursor: pointer; font-family: Outfit, system-ui, sans-serif; color: #0f172a;
            border-bottom: 1px solid #f1f5f9;
        }
        .ca-catalog__row:active { background: #f8fafc; }
        .ca-catalog__row--all { color: var(--ca-brand); font-weight: 800; }
        .ca-catalog__chev { color: #94a3b8; font-size: 18px; }
        .ca-catalog__add {
            min-width: 36px; height: 36px; border-radius: 12px;
            background: linear-gradient(135deg, #e11d48, #be123c); color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 14px; flex-shrink: 0;
            box-shadow: 0 8px 16px rgba(225, 29, 72, .25);
        }
        .ca-catalog__prod img {
            width: 48px; height: 48px; border-radius: 12px; object-fit: cover;
            background: #f1f5f9; flex-shrink: 0;
        }
        @media (min-width: 768px) {
            .ca-catalog { align-items: center; padding: 24px; }
            .ca-catalog__panel { border-radius: 20px; max-height: 80vh; width: min(560px, 92vw); }
        }

        @media (min-width: 1024px) {
            .ca-top { display: none; }
            .ca-desk-bar { display: flex; align-items: center; justify-content: flex-end; margin-bottom: 16px; }
            .ca-shell { flex-direction: row; }
            .ca-side {
                display: flex; width: 260px; flex-shrink: 0;
                background: rgba(255,255,255,.78);
                backdrop-filter: blur(18px);
                border-right: 1px solid var(--ca-line);
                min-height: 100dvh; position: sticky; top: 0;
                padding: 24px 16px; flex-direction: column; gap: 4px;
            }
            .ca-main { padding: 28px 36px 48px; max-width: none; flex: 1; }
            .ca-bottom { display: none !important; }
        }
        .ca-main.ca-main--create {
            padding: 0 0 calc(96px + env(safe-area-inset-bottom, 0px));
            max-width: none;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .ca-main.ca-main--create .ca-desk-bar { display: none; }
        .ca-main.ca-main--create .sale-create-form {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .ca-main.ca-main--create .sale-order-build {
            flex: 1 1 auto;
            height: calc(100dvh - 88px - env(safe-area-inset-bottom, 0px)) !important;
            max-height: calc(100dvh - 88px - env(safe-area-inset-bottom, 0px)) !important;
            min-height: 0 !important;
            overflow: hidden !important;
        }
        .ca-main.ca-main--create .sale-order-build__body {
            overflow: hidden !important;
            min-height: 0 !important;
            flex: 1 1 auto !important;
        }
        .ca-main.ca-main--create .sale-order-build .sale-cart-scroll {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        .ca-main.ca-main--create #stepShipping {
            padding: 12px 14px calc(88px + env(safe-area-inset-bottom, 0px));
            max-width: none;
                width: 100%;
            box-sizing: border-box;
        }
        .ca-main.ca-main--create #stepShipping .sale-create-bar {
            position: sticky !important;
            bottom: 0;
            z-index: 20;
            margin: 12px 0 0;
            padding: 10px 0 8px;
            background: #fff;
            border: 0;
            box-shadow: none;
        }
        .ca-main.ca-main--create #stepShipping .sale-create-bar .sale-btn {
            background: linear-gradient(135deg, #e11d48, #be123c) !important;
            color: #fff !important;
            border-radius: 14px;
            font-weight: 800;
            box-shadow: 0 10px 24px rgba(225, 29, 72, .28);
        }
        body.ca-on-shipping { overflow: auto !important; }
        body.ca-on-shipping .ca-main.ca-main--create {
            overflow: visible !important;
            height: auto !important;
            min-height: 0 !important;
        }
        @media (min-width: 1024px) {
            .ca-main.ca-main--create { padding: 0; }
            .ca-main.ca-main--create .sale-order-build {
                height: calc(100dvh - 24px) !important;
                max-height: calc(100dvh - 24px) !important;
            }
            .ca-main.ca-main--create #stepShipping .sale-create-bar {
                position: static !important;
                margin: 16px 0 32px;
            padding: 0;
            }
        }
    </style>
    @stack('styles')
</head>
@php
    $routeName = request()->route()?->getName() ?? '';
    $isHome = $routeName === 'customer.home';
    $isCreate = in_array($routeName, ['customer.orders.create', 'customer.orders.store'], true);
    $isOrders = in_array($routeName, ['customer.orders', 'customer.orders.show'], true);
    $isPrice = $routeName === 'customer.price';
@endphp
<body class="{{ $isCreate ? 'sale-building-order sale-page-create' : '' }}">
@php
    $contact = $contact ?? auth('customer')->user();
    $custName = trim((string) ($contact?->displayName() ?: $contact?->company_name ?: 'C'));
    $letter = strtoupper(substr($custName, 0, 1));
    $company = $contact?->company ?? $contact?->loadMissing('company')->company;
    $business = $business ?? $company ?? (object) ['name' => config('app.name')];
    $logoUrl = null;
    $bizLetter = strtoupper(substr((string) ($business->name ?? 'C'), 0, 1));
    $caLocations = \App\Models\Site::query()
        ->where('company_id', $contact->company_id ?? 0)
        ->where('is_active', true)
        ->orderBy('name')
        ->pluck('name', 'id');
    $caLocationId = session('customer.default_location_id');
    $caLocOk = $caLocations->keys()->contains(fn ($id) => (int) $id === (int) $caLocationId);
    if (empty($caLocationId) || ! $caLocOk) {
        $caLocationId = $caLocations->keys()->first();
        if ($caLocationId) {
            session(['customer.default_location_id' => (int) $caLocationId]);
        }
    }
    $caLocationName = 'No location';
    if ($caLocationId) {
        foreach ($caLocations as $id => $name) {
            if ((int) $id === (int) $caLocationId) {
                $caLocationName = $name;
                break;
            }
        }
    }
@endphp
<div class="ca-shell">
    @unless($isCreate)
    <header class="ca-top">
        <div class="flex items-center gap-3 min-w-0">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo" class="ca-logo">
            @else
                <div class="ca-logo-fb">{{ $bizLetter }}</div>
            @endif
            <div class="min-w-0">
                <div class="ca-display text-[15px] font-bold truncate">{{ $business->name ?? config('app.name') }}</div>
                <div class="text-[11px] text-slate-500 font-semibold truncate">{{ $custName }}</div>
            </div>
        </div>
        <div class="ca-menu">
            <button type="button" class="ca-avatar js-menu-btn" data-menu="menuMobile" aria-label="Account">{{ $letter }}</button>
            <div class="ca-drop" id="menuMobile">
                <div class="px-3 py-2 text-xs text-slate-500 font-semibold truncate border-b border-slate-100 mb-1">{{ $custName }}</div>
                <div class="px-3 py-2 text-xs text-slate-600 font-semibold flex items-center gap-1.5 border-b border-slate-100 mb-1">
                    <span class="text-brand">📍</span>
                    <span class="truncate">{{ $caLocationName }}</span>
                </div>
                <a href="{{ route('customer.profile') }}#location">Update Profile</a>
                <a href="{{ route('customer.profile') }}#location">Location</a>
                <form method="POST" action="{{ route('customer.logout') }}">@csrf<button type="submit" class="danger">Logout</button></form>
            </div>
        </div>
    </header>
    @endunless

    @unless($isCreate)
    <aside class="ca-side">
        <div class="px-2 mb-6 flex items-center gap-3">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo" class="ca-logo">
            @else
                <div class="ca-logo-fb">{{ $bizLetter }}</div>
            @endif
            <div class="min-w-0">
                <div class="ca-display text-base font-bold text-brand truncate">{{ $business->name ?? 'Customer App' }}</div>
                <div class="text-xs text-slate-500 mt-0.5 truncate">{{ $custName }}</div>
                <div class="text-[11px] text-slate-400 mt-0.5 truncate">📍 {{ $caLocationName }}</div>
            </div>
        </div>
        <a href="{{ route('customer.home') }}" class="ca-side-link {{ $isHome ? 'active' : '' }}">Home</a>
        <a href="{{ route('customer.orders.create') }}" class="ca-side-link {{ $isCreate ? 'active' : '' }}">Create Order</a>
        <a href="{{ route('customer.price') }}" class="ca-side-link {{ $isPrice ? 'active' : '' }}">Price Check</a>
        <a href="{{ route('customer.orders') }}" class="ca-side-link {{ $isOrders ? 'active' : '' }}">My Orders</a>
        <div class="mt-auto pt-4 border-t border-slate-100/80">
            <div class="ca-menu">
                <button type="button" class="ca-side-link js-menu-btn" data-menu="menuSide">
                    <span class="ca-avatar" style="width:28px;height:28px;font-size:12px;pointer-events:none;">{{ $letter }}</span>
                Account
                </button>
                <div class="ca-drop" id="menuSide" style="left:0;right:auto;width:100%;">
                    <div class="px-3 py-2 text-xs text-slate-600 font-semibold flex items-center gap-1.5 border-b border-slate-100 mb-1">
                        <span class="text-brand">📍</span>
                        <span class="truncate">{{ $caLocationName }}</span>
        </div>
                    <a href="{{ route('customer.profile') }}#location">Update Profile</a>
                    <a href="{{ route('customer.profile') }}#location">Location</a>
                    <form method="POST" action="{{ route('customer.logout') }}">@csrf<button type="submit" class="danger">Logout</button></form>
            </div>
            </div>
        </div>
    </aside>
    @endunless

    <div class="ca-main {{ $isCreate ? 'ca-main--create' : '' }}">
        @unless($isCreate)
        <div class="ca-desk-bar">
            <div class="ca-menu">
                <button type="button" class="ca-avatar js-menu-btn" data-menu="menuDesk" aria-label="Account">{{ $letter }}</button>
                <div class="ca-drop" id="menuDesk">
                    <div class="px-3 py-2 text-xs text-slate-500 font-semibold truncate border-b border-slate-100 mb-1">{{ $custName }}</div>
                    <div class="px-3 py-2 text-xs text-slate-600 font-semibold flex items-center gap-1.5 border-b border-slate-100 mb-1">
                        <span class="text-brand">📍</span>
                        <span class="truncate">{{ $caLocationName }}</span>
                        </div>
                    <a href="{{ route('customer.profile') }}#location">Update Profile</a>
                    <a href="{{ route('customer.profile') }}#location">Location</a>
                    <form method="POST" action="{{ route('customer.logout') }}">@csrf<button type="submit" class="danger">Logout</button></form>
                    </div>
            </div>
        </div>
        @endunless

        @if(session('success'))
            <div class="ca-flash ca-flash--ok">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="ca-flash ca-flash--err">{{ session('error') }}</div>
        @endif
        @if(session('status'))
            @php $st = session('status'); @endphp
            <div class="ca-flash {{ !empty($st['success']) ? 'ca-flash--ok' : 'ca-flash--err' }}">{{ is_array($st) ? ($st['msg'] ?? '') : $st }}</div>
        @endif
                @yield('content')
</div>

    <nav class="ca-bottom" aria-label="Customer navigation">
        <a href="{{ route('customer.home') }}" class="ca-tab {{ $isHome ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z"/></svg>
            Home
        </a>
        <a href="{{ route('customer.orders.create') }}" class="ca-tab {{ $isCreate ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.2 11h10.4l2-8H7"/></svg>
            Order
        </a>
        <a href="{{ route('customer.price') }}" class="ca-tab {{ $isPrice ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 14h2"/></svg>
            Price
        </a>
        <a href="{{ route('customer.orders') }}" class="ca-tab {{ $isOrders ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
            Orders
        </a>
</nav>
        </div>

<div id="caScanOverlay" class="ca-scan-overlay" hidden aria-hidden="true">
    <div class="ca-scan-overlay__head">
        <div class="ca-scan-overlay__title">Scan barcode</div>
        <button type="button" id="caScanCloseBtn" class="ca-scan-overlay__close" aria-label="Close">×</button>
        </div>
    <div class="ca-scan-overlay__body">
        <div id="caScanReader"></div>
        <div class="ca-scan-overlay__hint">Point your camera at the product barcode</div>
        <div id="caScanError" class="ca-scan-overlay__error"></div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    document.querySelectorAll('.js-menu-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const id = btn.getAttribute('data-menu');
            const menu = document.getElementById(id);
            document.querySelectorAll('.ca-drop.open').forEach((el) => {
                if (el !== menu) el.classList.remove('open');
            });
            menu?.classList.toggle('open');
        });
    });
    document.querySelectorAll('.ca-drop').forEach((menu) => {
        menu.addEventListener('click', (e) => e.stopPropagation());
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('.ca-drop.open').forEach((el) => el.classList.remove('open'));
    });
})();

window.CustomerCameraScan = (function () {
    let scanner = null, busy = false, onCode = null;
    const overlay = document.getElementById('caScanOverlay');
    const errEl = document.getElementById('caScanError');
    function showError(msg) {
        if (!errEl) return;
        errEl.textContent = msg || '';
        errEl.style.display = msg ? 'block' : 'none';
    }
    async function stop() {
        try {
            if (scanner) {
                const s = scanner; scanner = null;
                if (s.isScanning) await s.stop();
                await s.clear();
            }
        } catch (e) {}
        if (overlay) { overlay.classList.remove('open'); overlay.hidden = true; overlay.setAttribute('aria-hidden', 'true'); }
        busy = false; onCode = null; showError('');
    }
    async function start(callback) {
        if (typeof Html5Qrcode === 'undefined') { alert('Camera scanner failed to load.'); return; }
        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            alert('Camera scan needs HTTPS (or localhost).'); return;
        }
        if (busy) return;
        busy = true; onCode = typeof callback === 'function' ? callback : null; showError('');
        if (overlay) { overlay.hidden = false; overlay.classList.add('open'); overlay.setAttribute('aria-hidden', 'false'); }
        try {
            scanner = new Html5Qrcode('caScanReader');
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 280, height: 160 }, aspectRatio: 1.777 },
                async (decoded) => {
                    const code = String(decoded || '').trim();
                    if (!code) return;
                    const cb = onCode; await stop(); if (cb) cb(code);
                },
                () => {}
            );
        } catch (e) {
            showError('Camera permission denied or not available.');
            busy = false;
        }
    }
    document.getElementById('caScanCloseBtn')?.addEventListener('click', () => stop());
    return { start, stop };
})();
</script>
@stack('scripts')
@include('customer.partials.pwa')
</body>
</html>
