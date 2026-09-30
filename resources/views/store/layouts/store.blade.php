<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#b71c1c">
    <meta name="apple-mobile-web-app-title" content="{{ $shop['name'] }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('pwa/customer-icon-192.png') }}">
    <title>@yield('title', $shop['name'].' | Wholesale Store')</title>
    <meta name="description" content="@yield('meta_description', $shop['tagline'])">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/store.css') }}?v={{ @filemtime(public_path('css/store.css')) ?: 1 }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#e53935',
                        brandDark: '#b71c1c',
                        ink: '#1a1a1a',
                        slatebar: '#3b0d0d',
                        mist: '#f4f4f4',
                        line: '#eee6e6'
                    },
                    fontFamily: {
                        sans: ['Poppins', 'system-ui', 'sans-serif'],
                        serif: ['Cinzel', 'Georgia', 'serif']
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: Poppins, system-ui, sans-serif; background: #f4f4f4; color: #1a1a1a; }
        .mega.is-active > .cat-link, .cat-link:hover { color: #fff !important; }
        .btn-brand { background: #e53935; color: #fff; font-weight: 600; border-radius: 8px; padding: .7rem 1.25rem; }
        .btn-brand:hover { background: #c62828; }
        .input-w:focus { border-color: #e53935; box-shadow: 0 0 0 3px rgba(229,57,53,.15); }
        .app-tab.active, .app-tab:active { color: #c62828; }
        .app-chip.active { background: #fdecec; color: #c62828; border-color: #f5b5b3; }
        .cat-nav-wrap { position: relative; overflow: visible; }
        .gc-nav { overflow: visible; }
        .gc-menubar {
            display: flex; align-items: stretch; justify-content: space-evenly;
            width: 100%; margin: 0; padding: 0; list-style: none;
            overflow: visible;
        }
        .gc-menubar > li { flex: 1 1 0; min-width: 0; position: relative; }
        .mega { position: relative; }
        .mega > a { position: relative; z-index: 2; }
        .cat-link.gc-nav-item {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            min-height: 64px; padding: 8px 8px; color: #fff;
            font-size: 13px; font-weight: 600; line-height: 1.15; text-align: left;
            white-space: pre-line;
        }
        .cat-link.gc-nav-item:hover, .mega:hover > .cat-link, .mega.is-active > .cat-link { background: rgba(0,0,0,.12); color: #fff !important; }
        .gc-nav-item .gc-nav-text { display: block; }
        .mega-panel {
            display: none;
            position: absolute;
            left: 0;
            right: auto;
            top: 100%;
            z-index: 120;
            min-width: 220px;
            width: max-content;
            max-width: 280px;
            background: #fff;
            color: #2a1212;
            border: 1px solid #eee6e6;
            box-shadow: 0 16px 40px rgba(0,0,0,.16);
            border-radius: 0 0 10px 10px;
            padding: 8px 0;
        }
        .mega:hover > .mega-panel,
        .mega-panel.is-open { display: block; }
        .mega-panel a { display: block; padding: 8px 16px; font-size: 13px; color: #2a1212; }
        .mega-panel a:hover { background: #fdecec; color: #c62828; }
        .brand-tile {
            display: flex; align-items: center; justify-content: center;
            min-height: 64px; border: 1px solid #ececec; border-radius: 8px;
            background: #fafafa; padding: 8px; text-align: center;
            transition: border-color .15s, box-shadow .15s;
        }
        .brand-tile:hover { border-color: #e53935; box-shadow: 0 2px 10px rgba(229,57,53,.12); }
        .input-w {
            width: 100%; border: 1px solid #d4d4d4; border-radius: 8px; padding: .7rem .9rem; font-size: .875rem;
            background: #fff; outline: none;
        }
        .pc-qty-input::-webkit-outer-spin-button,
        .pc-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .pc-qty-input { -moz-appearance: textfield; }
        .product-card { transition: box-shadow .2s, transform .2s; }
        .product-card:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,0,0,.08); }

        /* App-like mobile shell */
        .app-bottom-nav {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 90;
            background: rgba(255,255,255,.94);
            backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-top: 1px solid #e8e8e8;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        .app-tab {
            flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 2px; min-height: 56px; color: #6b6b6b; font-size: 10px; font-weight: 600;
            text-decoration: none; background: transparent; border: 0; cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }
        .app-tab.active, .app-tab:active { color: #e53935; }
        .app-chip-scroll {
            display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch;
            scrollbar-width: none; padding: 0 16px 10px;
        }
        .app-chip-scroll::-webkit-scrollbar { display: none; }
        .app-chip {
            flex: 0 0 auto; padding: 8px 14px; border-radius: 999px; background: #f0f0f0;
            font-size: 13px; font-weight: 600; color: #1a1a1a; white-space: nowrap;
            border: 1px solid transparent; text-decoration: none;
        }
        .app-chip.active { background: #fdecec; color: #e53935; border-color: #f5b5b3; }
        .app-sheet {
            position: fixed; inset: 0; z-index: 90; display: none;
        }
        .app-sheet.is-open { display: block; }
        .app-sheet__backdrop {
            position: absolute; inset: 0; background: rgba(0,0,0,.45);
        }
        .app-sheet__panel {
            position: absolute; left: 0; right: 0; bottom: 0;
            max-height: min(88vh, 720px);
            background: #fff; border-radius: 18px 18px 0 0;
            box-shadow: 0 -12px 40px rgba(0,0,0,.18);
            padding-bottom: calc(12px + env(safe-area-inset-bottom, 0));
            transform: translateY(100%);
            transition: transform .28s ease;
            overflow: auto;
        }
        .app-sheet.is-open .app-sheet__panel { transform: translateY(0); }
        .app-sheet__handle {
            width: 40px; height: 4px; border-radius: 999px; background: #d4d4d4;
            margin: 10px auto 6px;
        }
        @media (max-width: 1023px) {
            body.app-mobile { overscroll-behavior-y: none; }
            body.app-mobile main { padding-bottom: calc(72px + env(safe-area-inset-bottom, 0)); }
            body.app-mobile .desktop-footer { display: none; }
            .product-card:hover { transform: none; box-shadow: none; }

            /* Customer account panel — app layout (mobile only) */
            body.ecom-account-panel .ecom-acc {
                padding: 0;
                max-width: none;
                margin: 0;
            }
            body.ecom-account-panel .ecom-acc-top {
                position: sticky;
                top: calc(3.5rem + env(safe-area-inset-top, 0px));
                z-index: 35;
                display: flex;
                align-items: center;
                gap: 8px;
                min-height: 48px;
                padding: 8px 12px;
                background: rgba(255,255,255,.96);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-bottom: 1px solid #e8e8e8;
            }
            body.ecom-account-panel .ecom-acc-top__back {
                width: 40px; height: 40px; border-radius: 999px;
                display: inline-flex; align-items: center; justify-content: center;
                background: #f0f0f0; color: #1a1a1a; text-decoration: none; flex-shrink: 0;
                font-size: 18px; line-height: 1; border: 0;
            }
            body.ecom-account-panel .ecom-acc-top__title {
                flex: 1; min-width: 0;
                font-size: 16px; font-weight: 800; letter-spacing: -0.02em;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            body.ecom-account-panel .ecom-acc-hero {
                padding: 16px 16px 12px;
                background: linear-gradient(180deg, #fff 0%, #f7f7f7 100%);
            }
            body.ecom-account-panel .ecom-acc-hero__name {
                font-size: 22px; font-weight: 800; letter-spacing: -0.03em; line-height: 1.2;
            }
            body.ecom-account-panel .ecom-acc-hero__meta {
                margin-top: 6px; font-size: 13px; color: #6b6b6b;
            }
            body.ecom-account-panel .ecom-acc-list {
                margin: 0 12px 12px;
                background: #fff;
                border: 1px solid #e8e8e8;
                border-radius: 14px;
                overflow: hidden;
            }
            body.ecom-account-panel .ecom-acc-list a,
            body.ecom-account-panel .ecom-acc-list button {
                display: flex;
                align-items: center;
                gap: 12px;
                width: 100%;
                text-align: left;
                padding: 14px 14px;
                border: 0;
                background: #fff;
                border-bottom: 1px solid #f0f0f0;
                font-size: 15px;
                font-weight: 600;
                color: #1a1a1a;
                text-decoration: none;
                cursor: pointer;
                -webkit-tap-highlight-color: transparent;
            }
            body.ecom-account-panel .ecom-acc-list a:last-child,
            body.ecom-account-panel .ecom-acc-list form:last-child button {
                border-bottom: 0;
            }
            body.ecom-account-panel .ecom-acc-list a:active,
            body.ecom-account-panel .ecom-acc-list button:active {
                background: #f5f5f5;
            }
            body.ecom-account-panel .ecom-acc-list__icon {
                width: 34px; height: 34px; border-radius: 10px;
                display: inline-flex; align-items: center; justify-content: center;
                background: #fdecec; color: #e53935; flex-shrink: 0;
                font-size: 14px;
            }
            body.ecom-account-panel .ecom-acc-list__chev {
                margin-left: auto; color: #c0c0c0; font-weight: 400; font-size: 18px;
            }
            body.ecom-account-panel .ecom-acc-list__danger .ecom-acc-list__icon {
                background: #fdecec; color: #c0392b;
            }
            body.ecom-account-panel .ecom-acc-section {
                padding: 4px 16px 8px;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .08em;
                color: #8a8a8a;
            }
            body.ecom-account-panel .ecom-acc-card {
                margin: 0 12px 12px;
                background: #fff;
                border: 1px solid #e8e8e8;
                border-radius: 14px;
                padding: 14px;
            }
            body.ecom-account-panel .ecom-acc-order {
                display: flex;
                flex-direction: column;
                gap: 4px;
                padding: 12px 14px;
                border-bottom: 1px solid #f0f0f0;
            }
            body.ecom-account-panel .ecom-acc-order:last-child { border-bottom: 0; }
            body.ecom-account-panel .ecom-acc-order__row {
                display: flex; justify-content: space-between; gap: 8px; align-items: center;
            }
            body.ecom-account-panel .ecom-acc-form .input-w,
            body.ecom-account-panel .ecom-acc-form input,
            body.ecom-account-panel .ecom-acc-form select,
            body.ecom-account-panel .ecom-acc-form textarea {
                border-radius: 12px !important;
                min-height: 46px;
                font-size: 15px;
            }
            body.ecom-account-panel .ecom-acc-form .btn-brand,
            body.ecom-account-panel .ecom-acc-form button[type=submit] {
                min-height: 48px;
                border-radius: 12px;
                width: 100%;
                font-weight: 700;
            }
            body.ecom-account-panel .ecom-acc-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            body.ecom-account-panel .ecom-acc-banner {
                margin: 0 12px 12px;
                border-radius: 12px;
                padding: 12px 14px;
                font-size: 13px;
                line-height: 1.4;
            }
        }
        @media (min-width: 1024px) {
            .app-mobile-only { display: none !important; }
            .app-bottom-nav { display: none !important; }
            .app-sheet { display: none !important; }
            body.ecom-account-panel .ecom-acc-top { display: none; }
            body.ecom-account-panel .ecom-acc-mobile-only { display: none !important; }
            body.ecom-account-panel .ecom-acc-desktop-only { display: block; }
        }
        @media (max-width: 1023px) {
            body.ecom-account-panel .ecom-acc-desktop-only { display: none !important; }
        }
    </style>
    @stack('head')
</head>
@php
    $nav_categories = $nav_categories ?? collect();
    $store = $shop['name'];
    $routeName = optional(request()->route())->getName();
    $isHome = request()->is('/') || $routeName === 'ecommerce.home';
    $isShop = in_array($routeName, ['ecommerce.shop', 'ecommerce.category', 'ecommerce.product', 'ecommerce.collection'], true);
    $isCart = $routeName === 'ecommerce.cart' || $routeName === 'ecommerce.checkout';
    $isAccount = str_starts_with((string) $routeName, 'ecommerce.account')
        || in_array($routeName, ['ecommerce.login', 'ecommerce.register', 'ecommerce.wishlist'], true);
    $isAccountPanel = str_starts_with((string) $routeName, 'ecommerce.account')
        || in_array($routeName, ['ecommerce.wishlist', 'ecommerce.quick_order'], true);

    $storeLogoUrl = $shop['logo_url'];
@endphp
<body class="antialiased app-mobile gc-store{{ ! empty($isAccountPanel) ? ' ecom-account-panel' : '' }}">

<div id="gcAgeGate" class="gc-age" role="dialog" aria-label="Age verification">
    <div class="gc-age__card">
        <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-[#e53935] flex items-center justify-center text-2xl font-extrabold">{{ $shop['initial'] }}</div>
        <h2 class="gc-serif text-2xl mb-2">Age Verification Required</h2>
        <p class="text-sm text-white/75 mb-1">You must be 21 years of age or older to enter this site.</p>
        <p class="text-xs text-white/55 mb-5">This site sells tobacco, nicotine and age-restricted products for licensed retailers only.</p>
        <p class="font-semibold mb-4">Are you 21 or older?</p>
        <div class="grid gap-2">
            <button type="button" id="gcAgeYes" class="rounded-lg bg-[#e53935] text-white font-bold py-3">Yes, I am 21 or older — enter site</button>
            <button type="button" id="gcAgeNo" class="rounded-lg border border-white/25 text-white/80 py-3">No, I am under 21 — exit site</button>
        </div>
        <p class="text-[11px] text-white/45 mt-4">By entering, you confirm you are 21+ and agree to our
            <a href="{{ route('ecommerce.page', 'terms-of-service') }}" class="underline">Terms of Service</a> and
            <a href="{{ route('ecommerce.page', 'privacy-policy') }}" class="underline">Privacy Policy</a>.</p>
    </div>
</div>

{{-- Mobile app header --}}
<header class="app-mobile-only sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-line pt-[env(safe-area-inset-top,0)]">
    <div class="px-3 h-14 flex items-center gap-2">
        <a href="{{ url('/') }}" class="shrink-0 flex items-center" aria-label="{{ $store }}">
            @if($storeLogoUrl)
                <img src="{{ $storeLogoUrl }}" alt="{{ $store }}" class="h-9 w-auto max-w-[140px] object-contain object-left">
            @else
                <span class="h-9 w-9 rounded-full bg-brandDark text-white flex items-center justify-center font-extrabold text-sm">{{ $shop['initial'] }}</span>
            @endif
        </a>
        <form action="{{ route('ecommerce.shop') }}" method="get" class="flex-1 min-w-0">
            <div class="flex items-center rounded-full bg-mist border border-line px-3 h-10">
                <svg width="16" height="16" class="text-black/40 shrink-0" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7" cy="7" r="5"/><path d="M11 11l3.5 3.5"/></svg>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search SKU, brands..."
                       class="flex-1 bg-transparent border-0 outline-none text-[13px] px-2 min-w-0">
            </div>
        </form>
    </div>
    @if($nav_categories->count())
        <div class="app-chip-scroll">
            <a href="{{ route('ecommerce.shop') }}" class="app-chip {{ $routeName === 'ecommerce.shop' && ! request('category') ? 'active' : '' }}">All</a>
            @foreach($nav_categories as $cat)
                <a href="{{ route('ecommerce.category', $cat->id) }}"
                   class="app-chip {{ (request('category') == $cat->id || (string) request()->route('slug') === (string) $cat->id) ? 'active' : '' }}">
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>
    @endif
</header>

{{-- Sticky desktop chrome matching Figma --}}
<div class="hidden lg:block sticky top-0 z-50">
<div class="gc-ann">
    <div class="max-w-[1400px] mx-auto px-4 h-[44px] flex items-center justify-center gap-3 text-sm font-semibold" id="gcAnnounce">
        <button type="button" class="opacity-70" data-ann-prev aria-label="Previous">‹</button>
        <a href="{{ $shop['announcements'][0]['href'] }}" class="text-white no-underline" data-ann-text>{{ $shop['announcements'][0]['t'] }}</a>
        <a href="{{ $shop['announcements'][0]['href'] }}" class="rounded-full bg-white text-[#b71c1c] text-xs font-bold px-3 py-1" data-ann-cta>{{ $shop['announcements'][0]['a'] }}</a>
        <button type="button" class="opacity-70" data-ann-next aria-label="Next">›</button>
    </div>
</div>
<div class="gc-util">
    <div class="max-w-[1400px] mx-auto px-4 h-[28px] flex flex-wrap items-center justify-between gap-2">
        <span>{{ $shop['tagline'] }}</span>
        <div class="flex flex-wrap items-center gap-4">
            @if($shop['phone'])
                <a href="tel:{{ $shop['phone_tel'] }}" class="text-white/85 hover:text-white">{{ $shop['phone'] }}</a>
            @endif
            @if($shop['email'])
                <a href="mailto:{{ $shop['email'] }}" class="text-white/85 hover:text-white">{{ $shop['email'] }}</a>
            @endif
            @if($shop['hours'])
                <span>{{ $shop['hours'] }}</span>
            @endif
        </div>
    </div>
</div>

{{-- Desktop header --}}
<header class="bg-white border-b border-line overflow-visible">
    <div class="max-w-[1400px] mx-auto px-4">
        <div class="h-[68px] flex items-center gap-4 lg:gap-6 relative z-[60]">
            <a href="{{ url('/') }}" class="shrink-0 flex items-center" aria-label="{{ $store }}">
                @if($storeLogoUrl)
                    <img src="{{ $storeLogoUrl }}" alt="{{ $store }}" class="h-10 w-auto max-w-[220px] object-contain object-left">
                @else
                    <span class="flex items-center gap-2">
                        <span class="h-10 w-10 rounded-full bg-[#b71c1c] text-white flex items-center justify-center text-lg font-extrabold">{{ $shop['initial'] }}</span>
                        <span class="leading-tight">
                            <span class="block font-extrabold tracking-[0.12em] text-[13px] text-[#1a1a1a]">{{ $shop['logo_line1'] }}</span>
                            <span class="block text-[12px] tracking-[0.28em] text-[#e53935]">{{ $shop['logo_line2'] }}</span>
                        </span>
                    </span>
                @endif
            </a>

            <form action="{{ route('ecommerce.shop') }}" method="get" class="flex flex-1 max-w-2xl mx-auto">
                <div class="flex w-full rounded-lg border border-line overflow-hidden bg-white focus-within:border-brand">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search products, brands, categories..."
                           class="flex-1 bg-transparent border-0 outline-none text-[13px] px-4 py-2.5">
                    <button class="text-[#b71c1c] px-4" aria-label="Search">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="5.5"/><path d="M12 12l4 4"/></svg>
                    </button>
                </div>
            </form>

            <div class="ml-auto flex items-center gap-3 sm:gap-4 text-[14px] font-semibold shrink-0">
                <a href="{{ auth('customer')->check() ? route('ecommerce.account') : route('ecommerce.login') }}" class="inline-flex items-center gap-1.5 h-9 text-[#b71c1c]">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="6" r="3"/><path d="M3 16c1.5-3 10.5-3 12 0"/></svg>
                    Account
                </a>
                <a href="{{ route('ecommerce.cart') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#e53935] text-white px-3.5 h-9 hover:bg-[#c62828]">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 4h12l-1 9H4L3 4z"/><path d="M7 7V4a2 2 0 014 0v3"/></svg>
                    Cart
                    @if(($cart_totals['count'] ?? 0) > 0)
                        <span class="bg-white text-[#b71c1c] text-[10px] rounded-full min-w-[16px] h-[16px] inline-flex items-center justify-center px-1">{{ $cart_totals['count'] }}</span>
                    @endif
                </a>
            </div>
        </div>
    </div>

    <div class="gc-nav relative z-[80] text-white overflow-visible">
        <div class="max-w-[1400px] mx-auto px-4 cat-nav-wrap" id="catNavWrap">
            <ul class="gc-menubar">
                <li>
                    <a href="{{ route('ecommerce.promotions') }}" class="cat-link gc-nav-item">
                        <span>🔥 Promotions</span>
                    </a>
                </li>
                <li class="mega">
                    <a href="{{ route('ecommerce.shop', ['new' => 1]) }}" class="cat-link gc-nav-item">
                        <span class="gc-nav-text">New
Arrivals</span>
                        <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor" class="opacity-80 shrink-0"><path d="M5 8l5 5 5-5"/></svg>
                    </a>
                    <div class="mega-panel">
                        <a href="{{ route('ecommerce.shop', ['new' => 1]) }}">All New Arrivals</a>
                        @foreach($nav_categories as $cat)
                            <a href="{{ route('ecommerce.shop', ['new' => 1, 'category' => $cat->id]) }}">{{ $cat->name }}</a>
                        @endforeach
                    </div>
                </li>
                @foreach($nav_categories as $cat)
                    @php
                        $subs = $cat->sub_categories ?? collect();
                        $href = route('ecommerce.category', $cat->id);
                    @endphp
                    <li class="mega">
                        <a href="{{ $href }}" class="cat-link gc-nav-item">
                            <span class="gc-nav-text">{{ $cat->name }}</span>
                            <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor" class="opacity-80 shrink-0"><path d="M5 8l5 5 5-5"/></svg>
                        </a>
                        <div class="mega-panel">
                            <a href="{{ $href }}">All {{ $cat->name }}</a>
                            @forelse($subs as $sub)
                                <a href="{{ route('ecommerce.category', ['slug' => $cat->id, 'sub' => $sub->id]) }}">{{ $sub->name }}</a>
                            @empty
                                <a href="{{ route('ecommerce.shop', ['category' => $cat->id, 'sort' => 'newest']) }}">New arrivals</a>
                                <a href="{{ route('ecommerce.shop', ['category' => $cat->id, 'sort' => 'price_asc']) }}">Best value</a>
                            @endforelse
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</header>
</div>

@if(session('success'))
    <div class="bg-emerald-50 text-emerald-900 text-sm text-center py-2.5 border-b border-emerald-100">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-rose-50 text-rose-900 text-sm text-center py-2.5 border-b border-rose-100">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="bg-rose-50 text-rose-900 text-sm text-center py-2.5 border-b border-rose-100">{{ $errors->first() }}</div>
@endif

<main>@yield('content')</main>

<footer class="desktop-footer mt-16 bg-[#1c0808] text-white">
    <div class="max-w-[1400px] mx-auto px-4 py-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-10 gc-footer-grid">
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="h-10 w-10 rounded-full bg-[#e53935] flex items-center justify-center font-extrabold">{{ $shop['initial'] }}</span>
                <span>
                    <span class="block font-extrabold tracking-[0.12em] text-xs">{{ $shop['logo_line1'] }}</span>
                    <span class="block text-[10px] tracking-[0.28em] text-[#ff8a80]">{{ $shop['logo_line2'] }}</span>
                </span>
            </div>
            <p class="text-white/65 text-sm leading-relaxed">{{ $shop['about_short'] }}</p>
            @if($shop['hours'])
                <p class="text-xs text-white/50 mt-3">Hours<br>{{ $shop['hours'] }}</p>
            @endif
            <p class="text-sm mt-3 break-all">
                @if($shop['phone'])<a class="text-white" href="tel:{{ $shop['phone_tel'] }}">{{ $shop['phone'] }}</a><br>@endif
                @if($shop['email'])<a class="text-white/80" href="mailto:{{ $shop['email'] }}">{{ $shop['email'] }}</a>@endif
            </p>
        </div>
        <div>
            <h3 class="font-serif text-lg mb-3">Shop</h3>
            <div class="space-y-2 text-sm text-white/80">
                <a class="block hover:text-white" href="{{ route('ecommerce.shop', ['new' => 1]) }}">New Arrivals</a>
                @foreach($nav_categories as $cat)
                    <a class="block hover:text-white" href="{{ route('ecommerce.category', $cat->id) }}">{{ $cat->name }}</a>
                @endforeach
                <a class="block hover:text-white" href="{{ route('ecommerce.brands') }}">All Brands</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.promotions') }}">Promotions &amp; Deals</a>
            </div>
        </div>
        <div>
            <h3 class="font-serif text-lg mb-3">Account</h3>
            <div class="space-y-2 text-sm text-white/80">
                <a class="block hover:text-white" href="{{ route('ecommerce.account') }}">My Account</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.account.orders') }}">Order History</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.account.addresses') }}">Saved Addresses</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.register') }}">Wholesale Application</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.cart') }}">Shopping Cart</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.checkout') }}">Checkout</a>
            </div>
        </div>
        <div>
            <h3 class="font-serif text-lg mb-3">Company</h3>
            <div class="space-y-2 text-sm text-white/80">
                <a class="block hover:text-white" href="{{ route('ecommerce.page', 'about-us') }}">About Us</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.page', 'wholesale-faq') }}">Wholesale FAQ</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.page', 'shipping-policy') }}">Shipping Policy</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.page', 'return-policy') }}">Return Policy</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.page', 'privacy-policy') }}">Privacy Policy</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.page', 'terms-of-service') }}">Terms of Service</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.page', 'age-compliance') }}">Age Compliance</a>
                <a class="block hover:text-white" href="{{ route('ecommerce.contact') }}">Contact Us</a>
            </div>
            <form class="mt-5" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Subscribed';">
                <p class="text-xs text-white/60 mb-2">Stay informed — new arrivals, deals &amp; industry news</p>
                <div class="flex flex-wrap gap-2 min-w-0">
                    <input type="email" required placeholder="Your business email" class="min-w-0 flex-1 rounded-lg px-3 py-2 text-sm text-ink">
                    <button type="submit" class="rounded-lg bg-[#e53935] px-3 py-2 text-sm font-bold shrink-0">Subscribe</button>
                </div>
                <p class="text-[11px] text-white/40 mt-2">For licensed wholesale accounts only.</p>
            </form>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="max-w-[1400px] mx-auto px-4 py-4 text-xs text-white/45">
            © {{ date('Y') }} {{ $shop['name'] }}. All rights reserved.@if($shop['address_line']) | {{ $shop['address_line'] }}@endif
        </div>
    </div>
</footer>

{{-- Mobile bottom tabs --}}
<nav class="app-bottom-nav app-mobile-only" aria-label="Primary">
    <div class="flex items-stretch">
        <a href="{{ url('/') }}" class="app-tab {{ $isHome ? 'active' : '' }}">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
            Home
        </a>
        <a href="{{ route('ecommerce.shop') }}" class="app-tab {{ $isShop ? 'active' : '' }}">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Shop
        </a>
        <button type="button" id="openCatSheet" class="app-tab">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            Menu
        </button>
        <a href="{{ route('ecommerce.cart') }}" class="app-tab {{ $isCart ? 'active' : '' }}">
            <span class="relative inline-flex">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 4h12l-1 9H4L3 4z"/><path d="M7 7V4a2 2 0 014 0v3"/></svg>
                @if(($cart_totals['count'] ?? 0) > 0)
                    <span class="absolute -top-1 -right-2 bg-brand text-white text-[9px] font-bold rounded-full min-w-[14px] h-[14px] inline-flex items-center justify-center px-0.5">{{ $cart_totals['count'] }}</span>
                @endif
            </span>
            Cart
        </a>
        <a href="{{ auth('customer')->check() ? route('ecommerce.account') : route('ecommerce.login') }}"
           class="app-tab {{ $isAccount ? 'active' : '' }}">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="8" r="3.5"/><path d="M4 19c1.8-3.5 12.2-3.5 14 0"/></svg>
            Account
        </a>
    </div>
</nav>

{{-- Mobile menu sheet --}}
<div id="catSheet" class="app-sheet app-mobile-only" aria-hidden="true">
    <div class="app-sheet__backdrop" data-close-sheet></div>
    <div class="app-sheet__panel" role="dialog" aria-label="Menu">
        <div class="app-sheet__handle"></div>
        <div class="px-4 pb-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-extrabold">Browse</h2>
                <button type="button" class="h-9 w-9 rounded-full bg-mist inline-flex items-center justify-center" data-close-sheet aria-label="Close">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-2 mb-4">
                <a href="{{ route('ecommerce.quick_order') }}" class="rounded-xl border border-line bg-mist px-3 py-3 font-semibold text-sm">Quick Order</a>
                <a href="{{ route('ecommerce.track') }}" class="rounded-xl border border-line bg-mist px-3 py-3 font-semibold text-sm">Track order</a>
                @auth('customer')
                    <a href="{{ route('ecommerce.account') }}" class="rounded-xl border border-line bg-mist px-3 py-3 font-semibold text-sm">My account</a>
                    <a href="{{ route('ecommerce.contact') }}" class="rounded-xl border border-line bg-mist px-3 py-3 font-semibold text-sm">Contact</a>
                @else
                    <a href="{{ route('ecommerce.login') }}" class="rounded-xl border border-line bg-mist px-3 py-3 font-semibold text-sm">Login</a>
                    <a href="{{ route('ecommerce.register') }}" class="rounded-xl border border-line bg-mist px-3 py-3 font-semibold text-sm text-brand">Sign Up</a>
                @endauth
            </div>
            <div class="text-xs font-bold uppercase tracking-wider text-black/40 mb-2">Departments</div>
            <div class="divide-y divide-line rounded-xl border border-line overflow-hidden bg-white">
                @foreach($nav_categories as $cat)
                    <a href="{{ route('ecommerce.category', $cat->id) }}" class="flex items-center justify-between px-4 py-3.5 font-semibold text-[15px] active:bg-mist">
                        <span>{{ $cat->name }}</span>
                        <span class="text-black/30">›</span>
                    </a>
                @endforeach
            </div>
            <p class="text-[11px] text-black/40 text-center mt-4 px-2">
                WARNING: This product contains nicotine. Nicotine is an addictive chemical.
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    const sheet = document.getElementById('catSheet');
    const openBtn = document.getElementById('openCatSheet');
    const open = () => {
        if (!sheet) return;
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };
    const close = () => {
        if (!sheet) return;
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    openBtn?.addEventListener('click', open);
    sheet?.querySelectorAll('[data-close-sheet]').forEach(el => el.addEventListener('click', close));
})();

document.querySelectorAll('.product-card-form').forEach(form => {
    const input = form.querySelector('.pc-qty-input');
    const minus = form.querySelector('.pc-qty-minus');
    const plus = form.querySelector('.pc-qty-plus');
    if (!input || !minus || !plus) return;
    const setQty = (v) => { input.value = Math.max(1, parseInt(v, 10) || 1); };
    minus.addEventListener('click', () => setQty((parseInt(input.value, 10) || 1) - 1));
    plus.addEventListener('click', () => setQty((parseInt(input.value, 10) || 1) + 1));
    input.addEventListener('change', () => setQty(input.value));
});

(function () {
    const gate = document.getElementById('gcAgeGate');
    if (gate && !localStorage.getItem('gc_age_ok')) {
        gate.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
    document.getElementById('gcAgeYes')?.addEventListener('click', () => {
        localStorage.setItem('gc_age_ok', '1');
        gate?.classList.remove('is-open');
        document.body.style.overflow = '';
    });
    document.getElementById('gcAgeNo')?.addEventListener('click', () => {
        window.location.href = 'https://www.google.com';
    });
    const msgs = @json($shop['announcements']);
    let i = 0;
    const text = document.querySelector('[data-ann-text]');
    const cta = document.querySelector('[data-ann-cta]');
    const show = () => {
        if (!text) return;
        text.textContent = msgs[i].t;
        text.href = msgs[i].href;
        if (cta) { cta.textContent = msgs[i].a; cta.href = msgs[i].href; }
    };
    document.querySelector('[data-ann-next]')?.addEventListener('click', () => { i = (i + 1) % msgs.length; show(); });
    document.querySelector('[data-ann-prev]')?.addEventListener('click', () => { i = (i + msgs.length - 1) % msgs.length; show(); });
})();
</script>
@stack('scripts')
</body>
</html>
