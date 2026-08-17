<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? ($pageTitle ?? config('app.name', 'Continental Wholesale')) }} — JAPS POS</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700|ibm-plex-mono:400,500&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-[#ececec] text-slate-900 text-sm h-screen overflow-hidden">
        <a href="#main-content" class="skip-link">Skip to main content</a>
        <div class="h-screen flex flex-col overflow-hidden">
            <nav class="chief-menu select-none" role="navigation" aria-label="Main menu">
                <div class="flex items-center gap-0.5 px-2 py-0.5">
                    <span class="px-2 py-1 font-semibold text-slate-800">JAPS POS</span>
                    @php
                        $menuUser = auth()->user();
                        $canRoute = function (?string $route) use ($menuUser): bool {
                            if (! $route || ! Route::has($route)) {
                                return false;
                            }
                            $feature = \App\Support\AppFeatures::featureForRoute($route);
                            if (! $feature) {
                                return true;
                            }
                            $action = \App\Support\AppFeatures::actionForRoute($route);

                            return $menuUser?->canAccessFeature($feature, $action) ?? false;
                        };
                        $routeExists = fn (?string $route) => $route && Route::has($route);
                        $menus = [
                            'File' => [
                                ['My Profile', 'profile'],
                                ['Company Settings', 'admin.company-settings'],
                                ['Overselling Settings', 'admin.overselling-settings'],
                                ['POS AI Settings', 'admin.japsai'],
                                ['Users & Roles', 'admin.users.index'],
                                ['Email Setup', 'admin.email-setup'],
                                ['Email Send Log', 'admin.email-logs'],
                            ],
                            'Inquiry' => [
                                ['Stock Status', 'inquiries.stock-status'],
                                ['Item Velocity', 'inquiries.item-velocity'],
                            ],
                            'Inventory' => [
                                ['Items', 'inventory.items.index'],
                                ['New Item', 'inventory.items.create'],
                                ['Stock Counts', 'inventory.stock-counts.index'],
                                ['Bulk Pricing', 'inventory.bulk-pricing'],
                                ['Stamp Inventory', 'inventory.stamp-inventory'],
                            ],
                            'Sales' => [
                                ['New Sales Order', 'sales.orders.create'],
                                ['Sales Orders', 'sales.orders.index'],
                                ['Customers', 'sales.customers.index'],
                                ['New Customer', 'sales.customers.create'],
                                ['Invoices', 'sales.invoices.index'],
                                ['Payments & Credits', 'sales.payments.index'],
                                ['Credit Memos', 'sales.credit-memos.index'],
                            ],
                            'Purchasing' => [
                                ['Purchase Orders', 'purchasing.orders.index'],
                                ['New Purchase Order', 'purchasing.orders.create'],
                                ['Inventory Receivings', 'purchasing.receivings.index'],
                                ['Return to Vendor', 'purchasing.rtv.index'],
                                ['Suppliers', 'purchasing.suppliers.index'],
                                ['New Supplier', 'purchasing.suppliers.create'],
                            ],
                            'Reports' => [
                                ['Sales Report By Customer', 'reports.sales-by-customer'],
                                ['Sales Report By Item', 'reports.sales-by-item'],
                                ['Sales Report By Categories', 'reports.sales-by-categories'],
                                ['Sales Report By Totals', 'reports.sales-by-totals'],
                                ['Sales Report By Stick Count', 'reports.sales-by-stick-count'],
                                ['Sales Report By Manufacturer', 'reports.sales-by-manufacturer'],
                                ['Purchases Report by Stick Count', 'reports.purchases-by-stick-count'],
                                ['Purchases Report by Item', 'reports.purchases-by-item'],
                                ['Price List', 'reports.price-list'],
                                ['MSA Report', 'reports.msa'],
                            ],
                        ];
                    @endphp
                    @foreach ($menus as $menu => $items)
                        @php
                            $menuItems = [];
                            foreach ($items as $row) {
                                if (! $routeExists($row[1])) {
                                    continue;
                                }
                                $menuItems[] = [$row[0], $row[1], $canRoute($row[1])];
                            }
                        @endphp
                        @continue($menuItems === [])
                        @php $menuHasAccess = collect($menuItems)->contains(fn ($row) => $row[2]); @endphp
                        <div class="relative group">
                            <button
                                type="button"
                                @class(['px-2 py-1 rounded-sm', 'hover:bg-slate-200' => $menuHasAccess, 'chief-menu-inactive' => ! $menuHasAccess])
                                aria-haspopup="true"
                            >{{ $menu }}</button>
                            <div class="hidden group-hover:block absolute left-0 top-full z-50 min-w-52 bg-white text-slate-800 shadow-lg border border-slate-400 py-1" role="menu">
                                @foreach ($menuItems as [$label, $route, $allowed])
                                    @if ($allowed)
                                        <a href="{{ route($route) }}" wire:navigate class="block px-3 py-1.5 hover:bg-sky-100 whitespace-nowrap" role="menuitem">{{ $label }}</a>
                                    @else
                                        <button
                                            type="button"
                                            class="chief-menu-item-disabled"
                                            role="menuitem"
                                            aria-disabled="true"
                                            title="No permission"
                                            onclick="window.posPermissionDenied && window.posPermissionDenied({{ json_encode($label) }})"
                                        >{{ $label }}</button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="ms-auto flex items-center gap-3 pe-2">
                        @if ($routeExists('lookups.index'))
                            <a href="{{ route('lookups.index') }}" wire:navigate class="text-sm font-medium text-slate-700 hover:text-slate-900">Lookups</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-slate-700 hover:text-slate-900">Logout</button>
                        </form>
                    </div>
                </div>
            </nav>

            {{-- Document tabs: active doc first (yellow), then Home — matches Chief --}}
            @php
                $routeName = request()->route()?->getName() ?? 'home';
                $docLabelMap = [
                    'sales.orders.create' => 'New Sales Order',
                    'sales.orders.edit' => 'Order',
                    'sales.orders.show' => 'Order',
                    'sales.customers.create' => 'New Customer',
                    'sales.customers.edit' => 'Customer',
                    'sales.customers.show' => 'Customer',
                    'sales.customers.index' => 'Customers',
                    'sales.orders.index' => 'Orders',
                    'sales.invoices.index' => 'Invoices',
                    'sales.payments.index' => 'Payments',
                    'sales.credit-memos.index' => 'Credit Memos',
                    'inventory.items.create' => 'New Item',
                    'inventory.items.edit' => 'Item',
                    'inventory.items.show' => 'Item',
                    'inventory.items.index' => 'Items',
                    'inventory.stock-counts.create' => 'New Stock Count',
                    'inventory.stock-counts.edit' => 'Stock Count',
                    'inventory.stock-counts.index' => 'Stock Counts',
                    'purchasing.orders.create' => 'New Purchase Order',
                    'purchasing.orders.edit' => 'Purchase Order',
                    'purchasing.orders.show' => 'Purchase Order',
                    'purchasing.orders.index' => 'Purchase Orders',
                    'purchasing.suppliers.create' => 'New Supplier',
                    'purchasing.suppliers.edit' => 'Supplier',
                    'purchasing.suppliers.index' => 'Suppliers',
                    'purchasing.receivings.index' => 'Receivings',
                    'purchasing.receivings.edit' => 'Receiving',
                    'purchasing.rtv.index' => 'RTV',
                    'lookups.index' => 'Lookups',
                    'reports.sales' => 'Sales Report By Customer',
                    'reports.sales-by-customer' => 'Sales Report By Customer',
                    'reports.sales-by-item' => 'Sales Report By Item',
                    'reports.sales-by-categories' => 'Sales Report By Categories',
                    'reports.sales-by-totals' => 'Sales Report By Totals',
                    'reports.sales-by-stick-count' => 'Sales Report By Stick Count',
                    'reports.sales-by-manufacturer' => 'Sales Report By Manufacturer',
                    'reports.purchases-by-stick-count' => 'Purchases Report by Stick Count',
                    'reports.purchases-by-item' => 'Purchases Report by Item',
                    'reports.price-list' => 'Price List',
                    'reports.msa' => 'MSA Report',
                    'inventory.bulk-pricing' => 'Bulk Pricing',
                    'inventory.stamp-inventory' => 'Stamp Inventory',
                    'inquiries.stock-status' => 'Stock Status',
                    'inquiries.item-velocity' => 'Item Velocity',
                    'profile' => 'My Profile',
                    'admin.company-settings' => 'Company Settings',
                    'admin.overselling-settings' => 'Overselling Settings',
                    'admin.japsai' => 'POS AI Settings',
                ];
                $homeTab = ['label' => 'Home', 'route' => 'home', 'url' => route('home')];
                if (isset($documentTabs)) {
                    $builtTabs = $documentTabs;
                    $activeRoute = $activeTabRoute ?? $routeName;
                } elseif ($routeName === 'home' || ! isset($docLabelMap[$routeName])) {
                    $builtTabs = [$homeTab];
                    $activeRoute = 'home';
                } else {
                    $label = $docLabelMap[$routeName];
                    if ($routeName === 'sales.orders.edit') {
                        $label = 'Order';
                    }
                    $builtTabs = [
                        ['label' => $label, 'route' => $routeName, 'url' => url()->current()],
                        $homeTab,
                    ];
                    $activeRoute = $routeName;
                }
            @endphp
            <div class="chief-tabs">
                @foreach ($builtTabs as $tab)
                    <a
                        href="{{ $tab['url'] }}"
                        wire:navigate
                        @class([
                            'chief-tab',
                            'chief-tab-active' => ($activeRoute ?? 'home') === $tab['route'],
                        ])
                    >
                        {{ $tab['label'] }}
                        @if ($tab['route'] !== 'home')
                            <span class="chief-tab-close" aria-hidden="true">×</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <main class="chief-main flex-1 min-h-0 overflow-x-hidden overflow-y-auto bg-[#ececec]" role="main" id="main-content" aria-label="Document content">
                {{ $slot }}
            </main>

            <footer class="chief-status-bar" role="contentinfo" aria-label="Status bar">
                <a href="{{ route('profile') }}" wire:navigate class="chief-status-user" title="My Profile">
                    @php $statusUser = auth()->user(); $statusAvatar = $statusUser?->avatarUrl(); @endphp
                    @if ($statusAvatar)
                        <img
                            src="{{ $statusAvatar }}"
                            alt=""
                            class="chief-status-avatar"
                            width="22"
                            height="22"
                            style="width:22px;height:22px;border-radius:999px;object-fit:cover;flex-shrink:0;display:inline-block;vertical-align:middle;"
                        />
                    @endif
                    <span>User: <strong>{{ $statusUser?->name ?? '—' }}@if($statusUser?->role) — {{ $statusUser->role->label }}@endif</strong></span>
                </a>
                <span>Site: <strong>{{ session('site_code', auth()->user()?->site?->code ?? 'WS') }}</strong></span>
                <span>Company: <strong>{{ session('company_name', auth()->user()?->company?->name ?? '—') }}</strong></span>
                <span id="status-clock" class="ms-auto text-amber-200" title="Your local time" aria-live="polite">{{ now()->format('g:i A, n/j/Y') }}</span>
            </footer>
        </div>
        @auth
            @php
                $posAiCompany = auth()->user()?->company;
                $showPosAiWidget = (bool) ($posAiCompany?->japs_ai_widget_enabled ?? false)
                    && (auth()->user()?->canUsePosAiChat() ?? false)
                    && request()->routeIs('admin.japsai') === false;
            @endphp
            @if ($showPosAiWidget)
                @persist('pos-ai-widget')
                    <livewire:pos-ai-widget />
                @endpersist
            @endif
        @endauth
        <style>
            .pos-permission-toast {
                position: fixed;
                top: 3.6rem;
                left: 50%;
                transform: translateX(-50%);
                z-index: 220;
                max-width: min(36rem, calc(100vw - 2rem));
                padding: 0.7rem 1.15rem;
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
                border-left: 5px solid #dc2626;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 700;
                line-height: 1.4;
                box-shadow: 0 10px 28px rgba(15, 23, 42, 0.2);
            }
        </style>
        <div id="pos-permission-toast" class="pos-permission-toast" role="alert" @if (! session('pos_permission')) hidden @endif>{{ session('pos_permission') }}</div>
        @livewireScripts
        <script>
            (function () {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
                const cookie = document.cookie.split('; ').find(function (r) { return r.indexOf('pos_tz=') === 0; });
                const current = cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';
                if (current !== tz) {
                    document.cookie = 'pos_tz=' + encodeURIComponent(tz) + ';path=/;max-age=31536000;SameSite=Lax';
                }

                const el = document.getElementById('status-clock');
                if (! el) return;

                const fmt = new Intl.DateTimeFormat(undefined, {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true,
                    month: 'numeric',
                    day: 'numeric',
                    year: 'numeric',
                });

                function tick() {
                    // Match prior style: "2:44 AM, 7/24/2026"
                    const parts = fmt.formatToParts(new Date());
                    const get = (type) => parts.find((p) => p.type === type)?.value ?? '';
                    const hour = get('hour');
                    const minute = get('minute');
                    const dayPeriod = get('dayPeriod');
                    const month = get('month');
                    const day = get('day');
                    const year = get('year');
                    el.textContent = `${hour}:${minute} ${dayPeriod}, ${month}/${day}/${year}`;
                }

                tick();
                setInterval(tick, 30000);
            })();

            (function () {
                let audioCtx = null;
                let lastKey = '';
                let lastAt = 0;
                let toastTimer = 0;

                function audio() {
                    const AC = window.AudioContext || window.webkitAudioContext;
                    if (! AC) return null;
                    if (! audioCtx) audioCtx = new AC();
                    if (audioCtx.state === 'suspended') {
                        audioCtx.resume();
                    }

                    return audioCtx;
                }

                document.addEventListener('pointerdown', function () { audio(); });
                document.addEventListener('keydown', function () { audio(); });

                function kindFromText(text) {
                    const t = String(text || '').toLowerCase();
                    if (
                        t.indexOf('permission') !== -1
                        || t.indexOf('your role cannot') !== -1
                        || t.indexOf('your role does not') !== -1
                        || t.indexOf('no permission') !== -1
                        || t.indexOf('not allowed') !== -1
                        || t.indexOf('access denied') !== -1
                        || t.indexOf('cannot delete') !== -1
                        || t.indexOf('cannot enter') !== -1
                        || t.indexOf('cannot apply') !== -1
                        || t.indexOf('cannot save') !== -1
                        || t.indexOf('cannot create') !== -1
                        || t.indexOf('cannot edit') !== -1
                        || t.indexOf('cannot assign') !== -1
                        || t.indexOf('cannot adjust') !== -1
                    ) {
                        return 'error';
                    }
                    if (t.indexOf('not found') !== -1 || t.indexOf('not available') !== -1 || t.indexOf('could not') !== -1) return 'error';
                    if (t.indexOf('memorize') !== -1 || t.indexOf('are you sure') !== -1 || t.indexOf('below allowed') !== -1) {
                        return 'warning';
                    }
                    if (t.indexOf('saved') !== -1 || t.indexOf('created') !== -1 || t.indexOf('deleted') !== -1 || t.indexOf('added') !== -1) return 'success';
                    return null;
                }

                window.playPosAlert = function (kind) {
                    const nowMs = Date.now();
                    if (nowMs - lastAt < 280) return;
                    lastAt = nowMs;
                    const ac = audio();
                    if (! ac) return;
                    const now = ac.currentTime;
                    const beep = function (freq, start, dur, vol) {
                        const osc = ac.createOscillator();
                        const gain = ac.createGain();
                        osc.type = 'square';
                        osc.frequency.setValueAtTime(freq, now + start);
                        gain.gain.setValueAtTime(0.0001, now + start);
                        gain.gain.exponentialRampToValueAtTime(vol || 0.22, now + start + 0.012);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + start + dur);
                        osc.connect(gain);
                        gain.connect(ac.destination);
                        osc.start(now + start);
                        osc.stop(now + start + dur + 0.02);
                    };
                    kind = kind || 'error';
                    if (kind === 'error' || kind === 'danger') {
                        beep(980, 0, 0.16, 0.28);
                        beep(420, 0.18, 0.28, 0.28);
                    } else if (kind === 'warning' || kind === 'alert' || kind === 'credit') {
                        beep(760, 0, 0.22, 0.22);
                    } else if (kind === 'success' || kind === 'info') {
                        beep(1320, 0, 0.08, 0.16);
                        beep(1760, 0.09, 0.11, 0.16);
                    } else {
                        beep(1080, 0, 0.1, 0.16);
                    }
                };

                function showPermissionToast(message) {
                    const el = document.getElementById('pos-permission-toast');
                    if (! el) return;
                    el.textContent = message;
                    el.hidden = false;
                    window.clearTimeout(toastTimer);
                    toastTimer = window.setTimeout(function () {
                        el.hidden = true;
                    }, 4500);
                }

                window.posPermissionDenied = function (label) {
                    const name = String(label || '').trim();
                    const msg = name
                        ? 'No permission for "' + name + '". Your role cannot open this.'
                        : 'Your role does not have permission for this action.';
                    showPermissionToast(msg);
                    window.playPosAlert('error');
                };

                const alertSel = '[role="alert"], [role="alertdialog"], [role="status"], .so-msg, .desk-flash, .stamp-inv-flash, .so-field-error, .so-browse-alert, .isa-err, .pos-permission-toast, .desk-chief-prompt';
                const flashSel = '.desk-flash, .so-msg, .so-browse-alert, .stamp-inv-flash, .bp-flash, .bp-flash-error, .pc-footer-msg';
                const flashDismissed = new Set();
                const FLASH_MS = 2500;

                function isStickyFlash(el) {
                    if (! el || ! el.matches) return true;
                    if (el.closest && el.closest('.desk-chief-prompt, [role="alertdialog"], dialog')) return true;
                    if (el.classList && el.classList.contains('so-msg-credit')) return true;
                    const t = String(el.textContent || '').toLowerCase();
                    if (t.indexOf('locked:') !== -1) return true;

                    return false;
                }

                function hideFlash(el) {
                    const text = String(el.textContent || '').replace(/\s+/g, ' ').trim();
                    if (text) flashDismissed.add(text);
                    el.style.display = 'none';
                }

                function scheduleFlashHide(el) {
                    if (! el || el.nodeType !== 1 || ! el.matches) return;
                    if (! el.matches(flashSel)) {
                        if (el.querySelectorAll) el.querySelectorAll(flashSel).forEach(scheduleFlashHide);
                        return;
                    }
                    if (isStickyFlash(el)) return;
                    const text = String(el.textContent || '').replace(/\s+/g, ' ').trim();
                    if (text.length < 2) return;
                    if (flashDismissed.has(text)) {
                        el.style.display = 'none';
                        return;
                    }
                    if (el.dataset.flashTimer === '1') return;
                    el.dataset.flashTimer = '1';
                    window.setTimeout(function () { hideFlash(el); }, FLASH_MS);
                }

                function kindFromEl(el) {
                    const fromText = kindFromText(el.textContent || '');
                    if (fromText) return fromText;
                    const cls = String(el.className || '');
                    if (cls.indexOf('danger') !== -1 || cls.indexOf('err') !== -1 || cls.indexOf('alert-error') !== -1) return 'error';
                    if (cls.indexOf('credit') !== -1 || cls.indexOf('alert-warn') !== -1 || cls.indexOf('so-msg-alert') !== -1 || cls.indexOf('chief-prompt') !== -1) return 'warning';
                    if (el.getAttribute && el.getAttribute('role') === 'alertdialog') return 'warning';
                    if (cls.indexOf('info') !== -1 || cls.indexOf('alert-ok') !== -1) return 'success';
                    return 'info';
                }

                function maybePlay(el) {
                    if (! el || el.nodeType !== 1 || ! el.matches) return;
                    if (el.closest && el.closest('.home-chief-alert')) return;
                    if (el.id === 'pos-permission-toast' && el.hidden) return;
                    if (! el.matches(alertSel)) return;
                    scheduleFlashHide(el);
                    const text = (el.textContent || '').replace(/\s+/g, ' ').trim();
                    if (text.length < 3) return;
                    const now = Date.now();
                    const key = kindFromEl(el) + '|' + text;
                    if (key === lastKey && now - lastAt < 1600) return;
                    lastKey = key;
                    window.playPosAlert(kindFromEl(el));
                }

                function maybePlayFromNode(n) {
                    if (! n) return;
                    const el = n.nodeType === 1 ? n : n.parentElement;
                    if (! el || ! el.closest) return;
                    maybePlay(el.closest(alertSel));
                }

                function scanAlerts() {
                    document.querySelectorAll(alertSel).forEach(maybePlay);
                    document.querySelectorAll(flashSel).forEach(scheduleFlashHide);
                }

                const obs = new MutationObserver(function (muts) {
                    muts.forEach(function (m) {
                        if (m.type === 'characterData') {
                            maybePlayFromNode(m.target);
                            return;
                        }
                        m.addedNodes.forEach(function (n) {
                            if (n.nodeType !== 1) return;
                            maybePlay(n);
                            scheduleFlashHide(n);
                            if (n.querySelectorAll) {
                                n.querySelectorAll(alertSel).forEach(maybePlay);
                                n.querySelectorAll(flashSel).forEach(scheduleFlashHide);
                            }
                        });
                    });
                });

                function watch() {
                    const root = document.getElementById('main-content') || document.body;
                    obs.observe(root, { childList: true, subtree: true, characterData: true });
                    scanAlerts();
                    const toast = document.getElementById('pos-permission-toast');
                    if (toast && ! toast.hidden && (toast.textContent || '').trim()) {
                        window.clearTimeout(toastTimer);
                        toastTimer = window.setTimeout(function () { toast.hidden = true; }, 4500);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', watch);
                } else {
                    watch();
                }
                document.addEventListener('livewire:navigated', watch);

                let livewireBound = false;
                const bindLivewire = function () {
                    if (livewireBound || ! window.Livewire || ! Livewire.on) return;
                    livewireBound = true;
                    Livewire.on('pos-alert', function (e) {
                        const kind = (e && e.kind) || (Array.isArray(e) && e[0] && e[0].kind) || 'error';
                        window.playPosAlert(kind);
                    });
                    if (Livewire.hook) {
                        Livewire.hook('morph.updated', function ({ el }) {
                            if (! el || ! el.matches) return;
                            scheduleFlashHide(el);
                            if (! el.matches('.desk-flash, [role="alert"], [role="alertdialog"], [role="status"], .isa-err, .so-msg, .so-field-error, .desk-chief-prompt')) return;
                            const t = String(el.textContent || '').toLowerCase();
                            if (t.indexOf('permission') === -1 && t.indexOf('your role') === -1) return;
                            maybePlay(el);
                        });
                    }
                };
                document.addEventListener('livewire:init', bindLivewire);
                queueMicrotask(bindLivewire);
                setTimeout(bindLivewire, 400);
            })();
        </script>
    </body>
</html>
