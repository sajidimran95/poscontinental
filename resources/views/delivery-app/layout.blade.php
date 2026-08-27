<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>@yield('title', 'Delivery') — Delivery</title>
    <link rel="manifest" href="{{ url('/delivery/pwa/manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('pwa/sale-icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('pwa/sale-icon-192.png') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        :root {
            --dlv: #0f766e;
            --dlv-dark: #115e59;
            --dlv-ink: #0f172a;
            --dlv-muted: #64748b;
            --dlv-line: #e2e8f0;
            --dlv-bg: #f4f7f8;
            --nav-h: 4.35rem;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif;
            background: var(--dlv-bg);
            color: var(--dlv-ink);
        }
        body.is-login {
            background: linear-gradient(165deg, #0f766e 0%, #134e4a 42%, #0f172a 100%);
            min-height: 100dvh;
        }
        .top {
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            padding: .85rem 1rem calc(.85rem + env(safe-area-inset-top));
            padding-top: calc(.85rem + env(safe-area-inset-top));
            background: linear-gradient(180deg, #0f766e, #0d9488);
            color: #fff;
        }
        .top h1 { margin: 0; font-size: 1.05rem; font-weight: 800; letter-spacing: -.02em; }
        .top .sub { opacity: .85; font-size: 12px; margin-top: .1rem; }
        .wrap { max-width: 42rem; margin: 0 auto; padding: .9rem 1rem calc(var(--nav-h) + 1.25rem + env(safe-area-inset-bottom)); }
        .card {
            background: #fff; border: 1px solid var(--dlv-line); border-radius: 16px;
            padding: 1rem; margin-bottom: .75rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .35rem;
            min-height: 2.7rem; padding: .45rem 1rem; border-radius: 12px; border: 0;
            font-weight: 700; font-size: 14px; text-decoration: none; cursor: pointer;
        }
        .btn-p { background: var(--dlv); color: #fff; }
        .btn-g { background: #15803d; color: #fff; }
        .btn-w { background: #fff; color: var(--dlv-dark); border: 1px solid #cbd5e1; }
        .row { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .6rem; }
        input, select, textarea {
            width: 100%; min-height: 2.7rem; border: 1px solid #cbd5e1; border-radius: 12px;
            padding: .5rem .75rem; font: inherit; background: #fff;
        }
        .muted { color: var(--dlv-muted); font-size: 13px; }
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: .55rem; margin-bottom: .75rem; }
        .stats.four { grid-template-columns: repeat(4, 1fr); }
        .stats div, .stat {
            background: #fff; border: 1px solid var(--dlv-line); border-radius: 14px; padding: .7rem .75rem;
        }
        .stats strong, .stat strong { display: block; font-size: 11px; color: var(--dlv-muted); text-transform: uppercase; letter-spacing: .04em; }
        .stats span, .stat span { font-size: 1.25rem; font-weight: 800; }
        .map { height: 16rem; border-radius: 16px; overflow: hidden; border: 1px solid #cbd5e1; margin-bottom: .75rem; }
        .stop { display: flex; gap: .65rem; padding: .7rem 0; border-bottom: 1px solid #eef2f6; color: inherit; text-decoration: none; }
        a.stop.on-row { background: #f0fdfa; margin: 0 -1rem; padding-left: 1rem; padding-right: 1rem; }
        .num {
            flex-shrink: 0; width: 1.9rem; height: 1.9rem; border-radius: 999px; background: var(--dlv);
            color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;
        }
        .num.on { background: #15803d; }
        .err { background: #fef2f2; color: #991b1b; padding: .65rem .8rem; border-radius: 12px; margin-bottom: .75rem; font-size: 13px; }
        .ok { background: #ecfdf5; color: #065f46; padding: .65rem .8rem; border-radius: 12px; margin-bottom: .75rem; font-size: 13px; }
        .install[hidden] { display: none !important; }
        .pwa-pop {
            position: fixed; inset: 0; z-index: 80;
            background: rgba(15, 23, 42, .55);
            display: flex; align-items: flex-end; justify-content: center;
            padding: 1rem 1rem calc(1rem + env(safe-area-inset-bottom));
        }
        .pwa-pop[hidden] { display: none !important; }
        .pwa-card {
            width: min(26rem, 100%);
            background: #fff; border-radius: 1.25rem; padding: 1.2rem 1.15rem 1.1rem;
            box-shadow: 0 24px 60px rgba(0,0,0,.28);
        }
        .pwa-card h2 { margin: 0 0 .35rem; font-size: 1.2rem; }
        body.is-login .pwa-pop { align-items: center; }
        .dlv-map-num { display:flex;width:28px;height:28px;align-items:center;justify-content:center;border-radius:999px;background:#0f766e;color:#fff;font-size:12px;font-weight:800;border:2px solid #fff; }
        .dlv-map-num.is-start { background:#15803d; }
        .tabbar {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 40;
            display: grid; grid-template-columns: repeat(5, 1fr);
            background: rgba(255,255,255,.96); backdrop-filter: blur(10px);
            border-top: 1px solid var(--dlv-line);
            padding: .3rem .2rem calc(.35rem + env(safe-area-inset-bottom));
        }
        .tabbar a {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .12rem;
            min-height: 3.05rem; text-decoration: none; color: #94a3b8; font-size: 9px; font-weight: 700;
            border-radius: 10px;
        }
        .tabbar a svg { width: 22px; height: 22px; }
        .tabbar a.is-on { color: var(--dlv); background: #f0fdfa; }
        .hero-login { max-width: 26rem; margin: 0 auto; padding: 2.5rem 1.15rem 2rem; }
        .brand-mark {
            width: 4.25rem; height: 4.25rem; border-radius: 1.25rem; margin: 0 auto 1.1rem;
            display: grid; place-items: center; background: rgba(255,255,255,.14); color: #fff;
            font-size: 1.6rem; font-weight: 800; border: 1px solid rgba(255,255,255,.2);
        }
        .login-card {
            background: #fff; border-radius: 1.35rem; padding: 1.25rem 1.15rem 1.2rem;
            box-shadow: 0 24px 60px rgba(0,0,0,.25);
        }
        .login-card h1 { margin: 0 0 .25rem; font-size: 1.45rem; }
        .chip { display: inline-block; font-size: 11px; font-weight: 700; color: #0f766e; background: #ccfbf1; padding: .2rem .5rem; border-radius: 999px; }
        .filter-row { display: flex; gap: .45rem; align-items: center; margin-bottom: .75rem; }
        .filter-row input { flex: 1; }
        .pill { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; }
        .pill.ok { color: #15803d; background: transparent; padding: 0; }
    </style>
    @yield('head')
</head>
<body class="@yield('body_class')">
    @yield('content')
    @hasSection('nav_active')
    <nav class="tabbar" aria-label="Delivery app">
        <a href="{{ route('delivery.app.home') }}" class="{{ trim($__env->yieldContent('nav_active')) === 'home' ? 'is-on' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/></svg>
            Home
        </a>
        <a href="{{ route('delivery.app.route') }}" class="{{ trim($__env->yieldContent('nav_active')) === 'route' ? 'is-on' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/></svg>
            Route
        </a>
        <a href="{{ route('delivery.app.assigned') }}" class="{{ trim($__env->yieldContent('nav_active')) === 'assigned' ? 'is-on' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="4" width="14" height="16" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
            Assigned
        </a>
        <a href="{{ route('delivery.app.all') }}" class="{{ trim($__env->yieldContent('nav_active')) === 'all' ? 'is-on' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
            All
        </a>
        <a href="{{ route('delivery.app.history') }}" class="{{ trim($__env->yieldContent('nav_active')) === 'delivered' ? 'is-on' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8"/><path d="m8.5 12.2 2.3 2.3 4.7-5"/></svg>
            Delivered
        </a>
    </nav>
    @endif
    <div id="dlv_pwa_install_bar" class="pwa-pop" hidden>
        <div class="pwa-card" role="dialog" aria-labelledby="dlv-install-title">
            <h2 id="dlv-install-title">Install Delivery App</h2>
            <p class="muted" style="margin:0 0 1rem">Add Delivery to your phone home screen. Chrome: Install. iPhone: Share → Add to Home Screen.</p>
            <button type="button" id="dlv_pwa_install_btn" class="btn btn-p" style="width:100%">Install app</button>
            <button type="button" id="dlv_pwa_dismiss_btn" class="btn btn-w" style="width:100%;margin-top:.5rem">Not now</button>
        </div>
    </div>
    <script>window.__DLV_PWA__ = { swUrl: @json(url('/delivery/pwa/sw.js')), startUrl: @json(url('/delivery')) };</script>
    <script src="{{ asset('js/delivery-pwa.js') }}?v={{ config('app.asset_version', '1') }}-dlv5" defer></script>
    @yield('scripts')
</body>
</html>
