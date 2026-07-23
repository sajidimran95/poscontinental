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

                            return $menuUser?->canAccessFeature($feature) ?? false;
                        };
                        $menus = [
                            'File' => [
                                ['Users & Roles', 'admin.users.index'],
                                ['Email Setup', 'admin.email-setup'],
                                ['Email Send Log', 'admin.email-logs'],
                                ['Terminal', 'admin.terminal'],
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
                                ['Sales Report', 'reports.sales'],
                                ['Price List', 'reports.price-list'],
                            ],
                            'Tobacco' => [
                                ['Stamp Inventory', 'tobacco.stamp-inventory'],
                                ['XML Filing', 'tobacco.filing'],
                            ],
                        ];
                    @endphp
                    @foreach ($menus as $menu => $items)
                        @php $visible = collect($items)->filter(fn ($row) => $canRoute($row[1])); @endphp
                        @continue($visible->isEmpty())
                        <div class="relative group">
                            <button type="button" class="px-2 py-1 hover:bg-slate-200 rounded-sm" aria-haspopup="true">{{ $menu }}</button>
                            <div class="hidden group-hover:block absolute left-0 top-full z-50 min-w-52 bg-white text-slate-800 shadow-lg border border-slate-400 py-1" role="menu">
                                @foreach ($visible as [$label, $route])
                                    <a href="{{ route($route) }}" wire:navigate class="block px-3 py-1.5 hover:bg-sky-100 whitespace-nowrap" role="menuitem">{{ $label }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="ms-auto flex items-center gap-3 pe-2">
                        @if ($canRoute('lookups.index'))
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
                    'reports.sales' => 'Sales Report',
                    'reports.price-list' => 'Price List',
                    'inventory.bulk-pricing' => 'Bulk Pricing',
                    'inquiries.stock-status' => 'Stock Status',
                    'inquiries.item-velocity' => 'Item Velocity',
                    'tobacco.stamp-inventory' => 'Stamp Inventory',
                    'tobacco.filing' => 'Tobacco Filing',
                    'admin.users.index' => 'Users & Roles',
                    'admin.email-setup' => 'Email Setup',
                    'admin.email-logs' => 'Email Send Log',
                    'admin.terminal' => 'Terminal',
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
                <span>User: <strong>{{ auth()->user()?->name ?? '—' }}@if(auth()->user()?->role) — {{ auth()->user()->role->label }}@endif</strong></span>
                <span>Site: <strong>{{ session('site_code', auth()->user()?->site?->code ?? 'WS') }}</strong></span>
                <span>Company: <strong>{{ session('company_name', auth()->user()?->company?->name ?? '—') }}</strong></span>
                <span id="status-clock" class="ms-auto text-amber-200" title="Your local time" aria-live="polite">{{ now()->format('g:i A, n/j/Y') }}</span>
            </footer>
        </div>
        @livewireScripts
        <script>
            (function () {
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
        </script>
    </body>
</html>
