<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('layouts.partials.user-timezone')
        <title>{{ $title ?? ($pageTitle ?? config('app.name', 'Continental Wholesale')) }} — JAPS POS</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700|ibm-plex-mono:400,500&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>
            .chief-tabs .chief-tab-add {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                align-self: stretch !important;
                box-sizing: border-box !important;
                height: 100% !important;
                min-width: 3.5rem !important;
                padding: 0 1.35rem !important;
                margin: 0 !important;
                border: none !important;
                border-right: 1px solid #15803d !important;
                border-radius: 0 !important;
                background: #22c55e !important;
                color: #fff !important;
                font-size: 1.4rem !important;
                font-weight: 700 !important;
                line-height: 1 !important;
                cursor: pointer !important;
                flex: 0 0 auto !important;
            }
            .chief-tabs .chief-tab-add:hover:not(:disabled) {
                background: #16a34a !important;
                color: #fff !important;
            }
            .chief-tabs .chief-tab-add:disabled {
                opacity: 0.45 !important;
                cursor: not-allowed !important;
            }
            .dlv-route-split {
                display: flex !important;
                flex: 1 1 auto !important;
                min-height: 0 !important;
                align-items: stretch !important;
            }
            .dlv-route-stops {
                flex: 0 0 28rem !important;
                width: 28rem !important;
                max-width: 40% !important;
                overflow: auto !important;
                border-right: 1px solid #e2e8f0 !important;
                background: #fff !important;
            }
            .dlv-route-map-wrap {
                flex: 1 1 auto !important;
                min-width: 0 !important;
                min-height: 28rem !important;
                position: relative !important;
                background: #d5dee8 !important;
            }
            .dlv-route-map-wrap .leaflet-container,
            #dlv-admin-map,
            #dlv-driver-map {
                position: absolute !important;
                inset: 0 !important;
                height: 100% !important;
                width: 100% !important;
            }
            .dlv-summary {
                display: grid !important;
                grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)) !important;
                gap: 0.5rem !important;
                padding: 0.65rem 0.85rem !important;
                background: #f7f9fb !important;
                border-bottom: 1px solid #e2e8f0 !important;
                flex-shrink: 0 !important;
            }
            .dlv-summary div {
                background: #fff !important;
                border: 1px solid #e2e8f0 !important;
                padding: 0.4rem 0.55rem !important;
                border-radius: 4px !important;
            }
            .dlv-summary strong {
                display: block !important;
                font-size: 11px !important;
                color: #64748b !important;
                text-transform: uppercase !important;
            }
            .dlv-map-num {
                display: flex !important;
                width: 28px !important;
                height: 28px !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 999px !important;
                background: #1e3a5f !important;
                color: #fff !important;
                font-size: 12px !important;
                font-weight: 800 !important;
                border: 2px solid #fff !important;
            }
            .dlv-map-num.is-start { background: #0f766e !important; }
            .dlv-timeline { list-style: none !important; margin: 0 !important; padding: 0.5rem 0.75rem 1rem !important; }
            .dlv-timeline li { display: flex !important; gap: 0.65rem !important; padding: 0.7rem 0.4rem !important; border-bottom: 1px solid #eef2f6 !important; }
            .dlv-stop-badge {
                flex-shrink: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-width: 2rem !important;
                height: 2rem !important;
                border-radius: 999px !important;
                background: #1e3a5f !important;
                color: #fff !important;
                font-size: 11px !important;
                font-weight: 800 !important;
            }
            .dlv-stop-badge.is-start { background: #0f766e !important; font-size: 9px !important; }
            .dlv-page {
                padding: 0.7rem 1rem 0.7rem 1.15rem !important;
                box-sizing: border-box !important;
            }
            .dlv-page .desk-toolbar,
            .dlv-page .desk-titlebar,
            .dlv-page .dlv-banner,
            .dlv-page .dlv-summary,
            .dlv-page .dlv-form {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            .dlv-page .desk-main-body,
            .dlv-page .dlv-driver-scroll {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            .dlv-stop-pick { background: none !important; border: 0 !important; padding: 0.2rem 0 !important; font: inherit !important; color: inherit !important; cursor: pointer !important; text-align: left !important; width: 100% !important; }
            .dlv-modal-backdrop { z-index: 400 !important; }
            .dlv-modal-head { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 0.75rem !important; margin-bottom: 0.75rem !important; }
            .dlv-modal-head h3 { margin: 0 !important; }
            .dlv-routes-page { height: 100% !important; min-height: 0 !important; }
            .dlv-routes-page .desk-toolbar,
            .dlv-routes-page .desk-titlebar,
            .dlv-routes-page .dlv-banner,
            .dlv-routes-page .dlv-summary {
                padding-left: 1.15rem !important;
                padding-right: 1.15rem !important;
            }
            .dlv-routes-page .desk-main-split.dlv-routes-body {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                padding: 0 1.15rem 1.15rem !important;
                min-width: 0 !important;
            }
            .dlv-routes-page .desk-grid {
                overflow-x: auto !important;
                overflow-y: auto !important;
                width: 100% !important;
                max-width: 100% !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 6px !important;
            }
            .dlv-routes-table { width: 100% !important; min-width: 58rem !important; table-layout: auto !important; }
            .dlv-routes-table tbody tr { height: 3.4rem !important; }
            .dlv-routes-table tbody td { overflow: visible !important; }
            .dlv-routes-table tbody td a.desk-btn,
            .dlv-routes-page .desk-grid > .desk-table tbody td a.desk-btn {
                display: inline-flex !important;
                overflow: visible !important;
                max-width: none !important;
                min-width: 7.25rem !important;
                color: #fff !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                line-height: 1.2 !important;
                padding: 0.4rem 0.85rem !important;
                height: auto !important;
                white-space: nowrap !important;
                text-overflow: clip !important;
                text-decoration: none !important;
            }
            .dlv-progress {
                height: 8px !important;
                background: #e2e8f0 !important;
                border-radius: 999px !important;
                overflow: hidden !important;
                min-width: 6rem !important;
            }
            .dlv-progress span {
                display: block !important;
                height: 100% !important;
                background: #0f766e !important;
                border-radius: 999px !important;
            }
            .dlv-pill { display: inline-block !important; font-size: 12px !important; font-weight: 700 !important; padding: 0.12rem 0.5rem !important; border-radius: 999px !important; }
            .dlv-pill.is-delivered { background: #d1fae5 !important; color: #065f46 !important; }
            .dlv-pill.is-en_route { background: #fef3c7 !important; color: #92400e !important; }
            .dlv-pill.is-failed { background: #fee2e2 !important; color: #991b1b !important; }
            .dlv-pill.is-pending { background: #e2e8f0 !important; color: #475569 !important; }
            .dlv-men-page { height: 100% !important; min-height: 0 !important; }
            .dlv-men-page .desk-main-split.dlv-men-body {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                padding: 0 1rem 1rem !important;
                min-width: 0 !important;
            }
            .dlv-men-page .desk-grid {
                overflow-x: auto !important;
                overflow-y: auto !important;
                width: 100% !important;
                max-width: 100% !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 6px !important;
            }
            .dlv-men-table { width: 100% !important; min-width: 56rem !important; table-layout: auto !important; }
            .dlv-men-table tbody tr { height: 3.4rem !important; }
            .dlv-men-table tbody td { overflow: visible !important; }
            .dlv-men-page .desk-grid > .desk-table tbody td a.desk-btn {
                display: inline-flex !important;
                overflow: visible !important;
                max-width: none !important;
                min-width: 6.5rem !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                padding: 0.4rem 0.85rem !important;
                height: auto !important;
                white-space: nowrap !important;
                text-overflow: clip !important;
                text-decoration: none !important;
            }
            .dlv-men-page .desk-grid > .desk-table tbody td a.desk-btn-primary { color: #fff !important; }
            .dlv-men-page .desk-grid > .desk-table tbody td a.desk-btn:not(.desk-btn-primary) { color: #1e3a5f !important; }
        </style>
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
                            'Delivery' => [
                                ['Delivery Management', 'deliveries.assign'],
                                ["Today's Routes", 'deliveries.routes'],
                                ['Delivery Men', 'deliveries.men'],
                                ['Route History', 'deliveries.history'],
                                ['Delivery Areas', 'deliveries.areas'],
                                ["My Deliveries", 'deliveries.driver'],
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
                                if ($row[1] === 'deliveries.driver' && $menuUser?->canAccessFeature('delivery.manage', 'view')) {
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
                                        <a
                                            href="{{ route('pos.tabs.open', ['route' => $route, 'label' => $label]) }}"
                                            class="block px-3 py-1.5 hover:bg-sky-100 whitespace-nowrap"
                                            role="menuitem"
                                        >{{ $label }}</a>
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

                    {{-- Window: open list + Close All (max 9) --}}
                    @php
                        $windowMenuSo = app(\App\Services\SalesOrderWindowManager::class);
                        $windowMenuDocs = app(\App\Services\DocumentTabManager::class);
                        $windowMenuItems = [];
                        foreach ($windowMenuSo->list() as $win) {
                            $windowMenuItems[] = [
                                'label' => $win['label'],
                                'url' => $win['url'],
                                'active' => (request()->route()?->getName() === 'sales.orders.create')
                                    && (request()->query('w') ?: $windowMenuSo->activeId()) === $win['id'],
                                'kind' => 'so',
                            ];
                        }
                        foreach ($windowMenuDocs->list() as $tab) {
                            $windowMenuItems[] = [
                                'label' => $tab['label'],
                                'url' => $tab['url'],
                                'active' => (request()->route()?->getName() === ($tab['route'] ?? '')),
                                'kind' => 'doc',
                            ];
                        }
                        $windowOpenCount = count($windowMenuItems);
                        $windowMax = \App\Services\DocumentTabManager::MAX_OPEN_WINDOWS;
                        $windowHomeActive = (request()->route()?->getName() ?? 'home') === 'home';
                    @endphp
                    <div class="relative group">
                        <button
                            type="button"
                            class="px-2 py-1 rounded-sm hover:bg-slate-200"
                            aria-haspopup="true"
                            aria-label="Window menu"
                        >Window</button>
                        <div class="hidden group-hover:block absolute left-0 top-full z-50 min-w-60 bg-white text-slate-800 shadow-lg border border-slate-400 py-1" role="menu">
                            <a
                                href="{{ route('home') }}"
                                wire:navigate
                                @class([
                                    'block px-3 py-1.5 hover:bg-sky-100 whitespace-nowrap',
                                    'font-semibold bg-sky-50' => $windowHomeActive,
                                ])
                                role="menuitem"
                            >{{ $windowHomeActive ? '✓ ' : '' }}Home</a>

                            @if ($windowMenuItems !== [])
                                <div class="my-1 border-t border-slate-200" role="separator"></div>
                                @foreach ($windowMenuItems as $wi => $wItem)
                                    <a
                                        href="{{ $wItem['url'] }}"
                                        @class([
                                            'block px-3 py-1.5 hover:bg-sky-100 whitespace-nowrap',
                                            'font-semibold bg-sky-50' => $wItem['active'],
                                        ])
                                        role="menuitem"
                                    >{{ $wItem['active'] ? '✓ ' : '' }}{{ $wi + 1 }}. {{ $wItem['label'] }}</a>
                                @endforeach
                            @endif

                            <div class="my-1 border-t border-slate-200" role="separator"></div>
                            @if ($windowOpenCount > 0)
                                <form method="POST" action="{{ route('pos.tabs.close-all') }}" class="m-0" onsubmit="return confirm('Close all open windows and return to Home?');">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="block w-full text-left px-3 py-1.5 hover:bg-sky-100 whitespace-nowrap"
                                        role="menuitem"
                                    >Close All</button>
                                </form>
                            @else
                                <button
                                    type="button"
                                    class="chief-menu-item-disabled block w-full text-left"
                                    role="menuitem"
                                    disabled
                                >Close All</button>
                            @endif
                            <div class="px-3 py-1 text-[11px] text-slate-500 select-none" aria-hidden="true">
                                {{ $windowOpenCount }}/{{ $windowMax }} windows
                            </div>
                        </div>
                    </div>

                    <div class="ms-auto flex items-center gap-3 pe-2">
                        @if ($routeExists('team-chat.index') && ($menuUser?->canAccessFeature('team.chat', 'view') ?? false))
                            <a
                                href="{{ route('pos.tabs.open', ['route' => 'team-chat.index', 'label' => 'Team chat']) }}"
                                class="text-sm font-medium text-slate-700 hover:text-slate-900"
                            >Team chat</a>
                        @endif
                        @if ($routeExists('lookups.index') && ($menuUser?->canAccessFeature('lookups', 'view') ?? false))
                            <a
                                href="{{ route('pos.tabs.open', ['route' => 'lookups.index', 'label' => 'Lookups']) }}"
                                class="text-sm font-medium text-slate-700 hover:text-slate-900"
                            >Lookups</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-slate-700 hover:text-slate-900">Logout</button>
                        </form>
                    </div>
                </div>
            </nav>

            {{-- Document tabs: Home | + (SO) | open docs — menu clicks stay open until × --}}
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
                    'team-chat.index' => 'Team chat',
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
                    'admin.users.index' => 'Users & Roles',
                    'admin.email-setup' => 'Email Setup',
                    'admin.email-logs' => 'Email Send Log',
                    'deliveries.assign' => 'Delivery Management',
                    'deliveries.routes' => "Today's Routes",
                    'deliveries.routes.show' => 'Delivery Route',
                    'deliveries.history' => 'Route History',
                    'deliveries.men' => 'Delivery Men',
                    'deliveries.driver' => "My Deliveries",
                    'deliveries.areas' => 'Delivery Areas',
                ];

                $soWindows = app(\App\Services\SalesOrderWindowManager::class);
                $docTabs = app(\App\Services\DocumentTabManager::class);
                $activeWindowId = null;
                $activeDocTabId = null;

                if ($routeName === 'sales.orders.create') {
                    $soWindows->ensureOne();
                    $activeWindowId = request()->query('w') ?: $soWindows->activeId();
                    if (is_string($activeWindowId) && $soWindows->has($activeWindowId)) {
                        $soWindows->setActive($activeWindowId);
                    } else {
                        $activeWindowId = $soWindows->ensureOne();
                    }
                } elseif ($routeName !== 'home' && $routeName !== 'pos.tabs.open') {
                    $label = $docLabelMap[$routeName]
                        ?? str($routeName)->afterLast('.')->headline()->toString();
                    if (in_array($routeName, ['sales.orders.edit', 'sales.orders.show'], true)) {
                        $label = 'Order';
                    }
                    $docTabs->syncCurrent($routeName, $label, url()->current());
                    $activeDocTabId = $docTabs->activeId();
                }

                $builtTabs = [];
                foreach ($soWindows->list() as $win) {
                    $builtTabs[] = [
                        'kind' => 'so',
                        'label' => $win['label'],
                        'route' => 'sales.orders.create',
                        'url' => $win['url'],
                        'window_id' => $win['id'],
                        'tab_id' => null,
                        'close_url' => route('sales.orders.windows.close', $win['id']),
                    ];
                }
                foreach ($docTabs->list() as $tab) {
                    $builtTabs[] = [
                        'kind' => 'doc',
                        'label' => $tab['label'],
                        'route' => $tab['route'],
                        'url' => $tab['url'],
                        'window_id' => null,
                        'tab_id' => $tab['id'],
                        'close_url' => route('pos.tabs.close', $tab['id']),
                    ];
                }

                $soWindowAdd = $soWindows->count() > 0 || $routeName === 'sales.orders.create';
                $homeIsActive = $routeName === 'home';
                $openWindowCount = count($builtTabs);
                $windowsAtMax = $openWindowCount >= \App\Services\DocumentTabManager::MAX_OPEN_WINDOWS
                    || $soWindows->count() >= \App\Services\SalesOrderWindowManager::MAX_WINDOWS;
            @endphp
            <div class="chief-tabs">
                <div @class(['chief-tab', 'chief-tab-active' => $homeIsActive])>
                    <a href="{{ route('home') }}" wire:navigate class="chief-tab-link">Home</a>
                </div>

                <button
                    type="button"
                    class="chief-tab-add"
                    title="{{ $windowsAtMax ? \App\Services\DocumentTabManager::tabLimitMessage() : 'New Sales Order' }}"
                    aria-label="Open another New Sales Order"
                    style="display:inline-flex;align-items:center;justify-content:center;align-self:stretch;box-sizing:border-box;height:100%;min-width:3.5rem;padding:0 1.35rem;margin:0;border:none;border-right:1px solid #15803d;border-radius:0;background:#22c55e;color:#fff;font-size:1.4rem;font-weight:700;line-height:1;cursor:pointer;flex:0 0 auto;"
                    onclick="if ({{ $windowsAtMax ? 'true' : 'false' }}) { window.showPosTabLimit && window.showPosTabLimit(); return false; } if (window.Livewire && {{ $routeName === 'sales.orders.create' ? 'true' : 'false' }}) { Livewire.dispatch('so-windows-open'); } else { window.location.href = {{ json_encode(route('pos.tabs.open', ['route' => 'sales.orders.create', 'label' => 'New Sales Order'])) }}; }"
                >+</button>

                @foreach ($builtTabs as $tab)
                    @php
                        $isSo = ($tab['kind'] ?? '') === 'so';
                        $isActive = $isSo
                            ? ($routeName === 'sales.orders.create' && ($tab['window_id'] ?? null) === $activeWindowId)
                            : ($routeName === ($tab['route'] ?? '') && (
                                $activeDocTabId === null || ($tab['tab_id'] ?? null) === $activeDocTabId || $routeName !== 'home'
                            ) && $routeName === ($tab['route'] ?? ''));
                        // Active when current route matches this tab's route (singleton docs)
                        if (! $isSo) {
                            $isActive = $routeName === ($tab['route'] ?? '');
                        }
                    @endphp
                    <div @class(['chief-tab', 'chief-tab-active' => $isActive])>
                        <a
                            href="{{ $tab['url'] }}"
                            @if ($isSo)
                                onclick="event.preventDefault(); if (window.Livewire && {{ $routeName === 'sales.orders.create' ? 'true' : 'false' }}) { Livewire.dispatch('so-windows-switch', { id: {{ json_encode($tab['window_id']) }} }); } else { window.location.href = {{ json_encode($tab['url']) }}; }"
                            @endif
                            class="chief-tab-link"
                        >{{ $tab['label'] }}</a>
                        @if ($isSo)
                            <button
                                type="button"
                                class="chief-tab-close"
                                title="Close"
                                aria-label="Close {{ $tab['label'] }}"
                                onclick="if (window.Livewire && {{ $routeName === 'sales.orders.create' ? 'true' : 'false' }}) { Livewire.dispatch('so-windows-close', { id: {{ json_encode($tab['window_id']) }} }); } else { const f=document.createElement('form'); f.method='POST'; f.action={{ json_encode($tab['close_url']) }}; const t=document.createElement('input'); t.type='hidden'; t.name='_token'; t.value={{ json_encode(csrf_token()) }}; f.appendChild(t); document.body.appendChild(f); f.submit(); }"
                            >×</button>
                        @else
                            <form method="POST" action="{{ $tab['close_url'] }}" class="chief-tab-close-form">
                                @csrf
                                <button type="submit" class="chief-tab-close" title="Close" aria-label="Close {{ $tab['label'] }}">×</button>
                            </form>
                        @endif
                    </div>
                @endforeach

                @if ($openWindowCount > 0)
                    <form
                        method="POST"
                        action="{{ route('pos.tabs.close-all') }}"
                        class="chief-tab-close-all-form"
                        style="display:inline-flex;align-self:stretch;margin:0;margin-left:auto;height:100%;"
                        onsubmit="return confirm('Close all open windows and return to Home?');"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="chief-tab-close-all"
                            title="Close all windows"
                            aria-label="Close all windows"
                            style="display:inline-flex;align-items:center;justify-content:center;align-self:stretch;box-sizing:border-box;height:100%;min-width:auto;padding:0 0.85rem;margin:0;border:none;border-left:1px solid #94a3b8;border-radius:0;background:#f1f5f9;color:#334155;font-size:12px;font-weight:700;line-height:1;cursor:pointer;flex:0 0 auto;white-space:nowrap;"
                        >Close all</button>
                    </form>
                @endif
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

                window.showPosTabLimit = function () {
                    showPermissionToast(@json(\App\Services\DocumentTabManager::tabLimitMessage()));
                    window.playPosAlert && window.playPosAlert('error');
                };

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

            (function () {
                const routes = {
                    newOrder: @json(route('pos.tabs.open', ['route' => 'sales.orders.create', 'label' => 'New Sales Order'])),
                    newPo: @json(Route::has('purchasing.orders.create') ? route('pos.tabs.open', ['route' => 'purchasing.orders.create', 'label' => 'New Purchase Order']) : null),
                    newItem: @json(Route::has('inventory.items.create') ? route('pos.tabs.open', ['route' => 'inventory.items.create', 'label' => 'New Item']) : null),
                    reports: @json(Route::has('reports.sales-by-customer') ? route('pos.tabs.open', ['route' => 'reports.sales-by-customer', 'label' => 'Sales Report By Customer']) : null),
                };

                function typingInField(el) {
                    if (! el || el === document.body) return false;
                    const tag = (el.tagName || '').toLowerCase();
                    if (tag === 'textarea' || tag === 'select' || el.isContentEditable) return true;
                    if (tag === 'input') {
                        const type = (el.getAttribute('type') || 'text').toLowerCase();
                        return ! ['button', 'submit', 'checkbox', 'radio', 'file', 'reset', 'hidden'].includes(type);
                    }
                    return false;
                }

                function go(url) {
                    if (! url) return;
                    if (window.Livewire && typeof Livewire.navigate === 'function') {
                        Livewire.navigate(url);
                    } else {
                        window.location.href = url;
                    }
                }

                function dispatchShortcut(name, detail) {
                    if (window.Livewire && typeof Livewire.dispatch === 'function') {
                        Livewire.dispatch(name, detail || {});
                        return true;
                    }
                    return false;
                }

                function isVisible(el) {
                    if (! el || el.disabled) return false;
                    const style = window.getComputedStyle(el);
                    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return false;
                    const r = el.getBoundingClientRect();
                    return r.width > 0 && r.height > 0;
                }

                function clickFirst(selector) {
                    const nodes = document.querySelectorAll(selector);
                    for (const el of nodes) {
                        if (! isVisible(el) || el.disabled) continue;
                        el.click();
                        return true;
                    }
                    return false;
                }

                function focusFirst(selectors) {
                    for (const sel of selectors) {
                        const nodes = document.querySelectorAll(sel);
                        for (const el of nodes) {
                            if (! isVisible(el)) continue;
                            el.focus();
                            if (typeof el.select === 'function') el.select();
                            return true;
                        }
                    }
                    return false;
                }

                function onNewOrder() {
                    const onCreate = window.location.pathname.indexOf('/sales/orders/create') !== -1;
                    if (onCreate && dispatchShortcut('so-windows-open')) {
                        return;
                    }
                    go(routes.newOrder);
                }

                function onSave() {
                    if (clickFirst('[data-pos-save]')) return;
                    dispatchShortcut('pos-shortcut-save');
                    const form = document.querySelector('form#so-form');
                    if (form) {
                        if (typeof form.requestSubmit === 'function') form.requestSubmit();
                        else form.submit();
                    }
                }

                function onPrint() {
                    // Prefer the on-page Print Invoice control (SO / invoices / rails).
                    if (clickFirst('[data-pos-print]:not([disabled])')) return;
                    if (clickFirst('button.so-btn-save[wire\\:click="printInvoiceStyle"], button[title*="Print invoice" i]:not([disabled])')) return;
                    dispatchShortcut('pos-shortcut-print');
                }

                function onF2() {
                    if (focusFirst(['#so-item-entry', '[data-pos-item-entry]', 'input.so-entry-input'])) {
                        dispatchShortcut('pos-shortcut-f2');
                        return;
                    }
                    dispatchShortcut('pos-shortcut-f2');
                    setTimeout(function () {
                        focusFirst(['#so-item-entry', '[data-pos-item-entry]', 'input.so-entry-input']);
                    }, 80);
                    focusFirst(['input[placeholder*="item code" i]', 'input[placeholder*="barcode" i]', 'input[placeholder*="Scan" i]']);
                }

                function onF3() {
                    if (clickFirst('[data-pos-browse], button.so-browse-btn, button[title*="Browse" i]')) return;
                    dispatchShortcut('pos-shortcut-f3');
                }

                function onF4() {
                    // List / browse search anywhere in the program
                    if (focusFirst([
                        '[data-pos-search]',
                        '#so-browse-search',
                        '#orders-search',
                        '#invoices-search',
                        '#customers-search',
                        '#po-search',
                        '#suppliers-search',
                        '#rcv-search',
                        '#rtv-search',
                        '#cm-search',
                        '#stock-counts-search',
                        '#users-search',
                        'input[type="search"]',
                        'input[placeholder*="Search" i]',
                        'input[placeholder*="search" i]',
                        '.desk-toolbar input[type="text"]',
                    ])) return;

                    // Sales order: open browse then focus its search
                    dispatchShortcut('pos-shortcut-f4');
                    setTimeout(function () {
                        focusFirst(['#so-browse-search', '[data-pos-search]']);
                    }, 120);
                }

                document.addEventListener('keydown', function (e) {
                    if (e.defaultPrevented) return;
                    if (e.altKey) return;

                    const key = e.key;
                    const ctrl = e.ctrlKey || e.metaKey;
                    const typing = typingInField(e.target);

                    // Function keys always (except when a native browser dialog owns focus)
                    if (key === 'F2') {
                        e.preventDefault();
                        onF2();
                        return;
                    }
                    if (key === 'F3') {
                        e.preventDefault();
                        onF3();
                        return;
                    }
                    if (key === 'F4') {
                        e.preventDefault();
                        onF4();
                        return;
                    }
                    if (key === 'F10') {
                        e.preventDefault();
                        onPrint();
                        return;
                    }

                    if (! ctrl) return;

                    const k = key.toLowerCase();
                    // Allow Ctrl+S / Ctrl+O etc. even while typing
                    if (k === 'o') {
                        e.preventDefault();
                        onNewOrder();
                        return;
                    }
                    if (k === 's') {
                        e.preventDefault();
                        onSave();
                        return;
                    }
                    if (k === 'p') {
                        e.preventDefault();
                        go(routes.newPo);
                        return;
                    }
                    if (k === 'i') {
                        e.preventDefault();
                        go(routes.newItem);
                        return;
                    }
                    if (k === 'r') {
                        e.preventDefault();
                        go(routes.reports);
                        return;
                    }
                }, true);
            })();
        </script>
    </body>
</html>
