@php
    $companyId = auth()->user()->company_id;

    $lowStockItems = \App\Models\Item::query()
        ->where('company_id', $companyId)
        ->lowStock()
        ->orderBy('item_code')
        ->get(['id', 'item_code', 'description', 'quantity_in_stock', 'reorder_point']);

    $lowStockCount = $lowStockItems->count();

    $modules = [
        [
            'title' => 'Sales',
            'color' => '#1e4d8c',
            'icon' => 'register',
            'links' => [
                ['New Sales Order', 'sales.orders.create', true],
                ['Sales Orders', 'sales.orders.index', true],
                ['New Customer', 'sales.customers.create', true],
                ['Customers', 'sales.customers.index', true],
                ['Invoices', 'sales.invoices.index', true],
                ['Payments', 'sales.payments.index', true],
                ['Credit Memos', 'sales.credit-memos.index', true],
            ],
        ],
        [
            'title' => 'Inventory',
            'color' => '#2f6b3a',
            'icon' => 'barcode',
            'links' => [
                ['Items', 'inventory.items.index', true],
                ['Transfers', null, false],
                ['Stock Counts', 'inventory.stock-counts.index', true],
                ['New Item', 'inventory.items.create', true],
            ],
        ],
        [
            'title' => 'Purchasing',
            'color' => '#3d2f4a',
            'icon' => 'clipboard',
            'links' => [
                ['Purchase Orders', 'purchasing.orders.index', true],
                ['Receiving', 'purchasing.receivings.index', true],
                ['Return to Vendor', 'purchasing.rtv.index', true],
                ['Suppliers', 'purchasing.suppliers.index', true],
                ['New Purchase Order', 'purchasing.orders.create', true],
            ],
        ],
        [
            'title' => 'Inquiries',
            'color' => '#1f8a9a',
            'icon' => 'info',
            'links' => [
                ['Stock Status', 'inquiries.stock-status', true],
                ['Item Velocity', 'inquiries.item-velocity', true],
            ],
        ],
    ];
@endphp

<x-app-layout>
    <div class="home-chief">
        <div class="home-chief-bg" aria-hidden="true">
            <span class="home-chief-orb home-chief-orb-a"></span>
            <span class="home-chief-orb home-chief-orb-b"></span>
            <span class="home-chief-orb home-chief-orb-c"></span>
        </div>

        <div class="home-chief-modules">
            @foreach ($modules as $i => $module)
                <section class="home-chief-col" style="--mod: {{ $module['color'] }}; --i: {{ $i }}">
                    <div class="home-chief-badge" aria-hidden="true">
                        <div class="home-chief-badge-shine"></div>
                        <div class="home-chief-badge-face">
                            @if ($module['icon'] === 'register')
                                <svg viewBox="0 0 64 64" class="home-chief-svg" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="10" y="24" width="44" height="26" rx="2.5"/>
                                    <path d="M16 24V16h32v8"/>
                                    <rect x="17" y="30" width="18" height="10" rx="1" fill="currentColor" stroke="none"/>
                                    <circle cx="44" cy="34" r="2.4" fill="currentColor" stroke="none"/>
                                    <circle cx="44" cy="42" r="2.4" fill="currentColor" stroke="none"/>
                                    <path d="M17 47h30"/>
                                </svg>
                            @elseif ($module['icon'] === 'barcode')
                                <svg viewBox="0 0 64 64" class="home-chief-svg" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round">
                                    <rect x="12" y="14" width="40" height="36" rx="2.5"/>
                                    <path d="M20 22v20M24 22v20M28 22v20M34 22v20M38 22v20M42 22v20"/>
                                    <path d="M18 48h28" stroke-width="2"/>
                                </svg>
                            @elseif ($module['icon'] === 'clipboard')
                                <svg viewBox="0 0 64 64" class="home-chief-svg" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="16" y="12" width="32" height="42" rx="2.5"/>
                                    <rect x="24" y="8" width="16" height="8" rx="1.5"/>
                                    <circle cx="24" cy="28" r="2.2" fill="currentColor" stroke="none"/>
                                    <path d="M30 28h14"/>
                                    <circle cx="24" cy="38" r="2.2" fill="currentColor" stroke="none"/>
                                    <path d="M30 38h14"/>
                                    <circle cx="24" cy="48" r="2.2" fill="currentColor" stroke="none"/>
                                    <path d="M30 48h14"/>
                                </svg>
                            @else
                                <svg viewBox="0 0 64 64" class="home-chief-svg" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round">
                                    <circle cx="32" cy="32" r="18"/>
                                    <circle cx="32" cy="22" r="2.6" fill="currentColor" stroke="none"/>
                                    <path d="M32 28v18" stroke-width="4"/>
                                </svg>
                            @endif
                        </div>
                        <span class="home-chief-caret"></span>
                    </div>

                    <h2 class="home-chief-title">{{ $module['title'] }}</h2>

                    <ul class="home-chief-links">
                        @foreach ($module['links'] as [$label, $route, $enabled])
                            <li>
                                @if ($enabled && $route && Route::has($route))
                                    <a href="{{ route($route) }}" wire:navigate>
                                        <span class="home-chief-link-dot" aria-hidden="true"></span>
                                        <span class="home-chief-link-text">{{ $label }}</span>
                                    </a>
                                @else
                                    <span class="home-chief-disabled">
                                        <span class="home-chief-link-dot" aria-hidden="true"></span>
                                        <span>{{ $label }}</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <aside class="home-chief-alert" role="status">
            <span class="home-chief-alert-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22">
                    <path fill="#fff" d="M12 3L1.8 21h20.4L12 3zm0 5.5l6.2 10.7H5.8L12 8.5z"/>
                    <rect x="11" y="10.2" width="2" height="5.6" fill="#c62828"/>
                    <rect x="11" y="17" width="2" height="2" fill="#c62828"/>
                </svg>
            </span>
            @if ($lowStockCount > 0)
                <span class="home-chief-alert-text">
                    {{ number_format($lowStockCount) }}
                    {{ $lowStockCount === 1 ? 'item' : 'items' }}
                    running low and should be ordered soon
                    @if ($lowStockItems->isNotEmpty())
                        <span class="home-chief-alert-codes">
                            ({{ $lowStockItems->take(3)->pluck('item_code')->implode(', ') }}{{ $lowStockCount > 3 ? '…' : '' }})
                        </span>
                    @endif
                </span>
                @if (Route::has('inventory.items.index'))
                    <a href="{{ route('inventory.items.index', ['favorite' => 'low_stock']) }}" wire:navigate class="home-chief-alert-go">View</a>
                @endif
            @else
                <span class="home-chief-alert-text">All stock levels look good — no items at reorder point</span>
            @endif
        </aside>
    </div>
</x-app-layout>
