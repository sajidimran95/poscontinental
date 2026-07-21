@php
    $lowStockCount = \App\Models\Item::query()
        ->where('company_id', auth()->user()->company_id)
        ->lowStock()
        ->count();

    $modules = [
        [
            'title' => 'Sales',
            'color' => '#1e4d8c',
            'text' => '#1e4d8c',
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
            'text' => '#2f6b3a',
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
            'color' => '#3a2a4a',
            'text' => '#3a2a4a',
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
            'color' => '#2a8a9a',
            'text' => '#2a8a9a',
            'icon' => 'info',
            'links' => [
                ['Stock Status', 'inquiries.stock-status', true],
                ['Item Velocity', 'inquiries.item-velocity', true],
            ],
        ],
    ];
@endphp

<x-app-layout>
    <div class="chief-home">
        <div class="chief-home-modules">
            @foreach ($modules as $module)
                <div class="chief-home-col">
                    <div class="chief-home-badge" style="background: {{ $module['color'] }}">
                        <div class="chief-home-badge-inner">
                            @if ($module['icon'] === 'register')
                                <svg viewBox="0 0 64 64" class="chief-home-svg" aria-hidden="true">
                                    <rect x="10" y="22" width="44" height="28" rx="2" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <rect x="16" y="14" width="32" height="10" rx="1" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <rect x="18" y="28" width="18" height="10" fill="currentColor" opacity="0.95"/>
                                    <circle cx="44" cy="33" r="3" fill="currentColor"/>
                                    <circle cx="44" cy="42" r="3" fill="currentColor"/>
                                    <path d="M18 46h28" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                            @elseif ($module['icon'] === 'barcode')
                                <svg viewBox="0 0 64 64" class="chief-home-svg" aria-hidden="true">
                                    <rect x="12" y="14" width="40" height="36" rx="2" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <path d="M20 22v20M24 22v20M28 22v20M34 22v20M38 22v20M42 22v20" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                    <path d="M18 48h28" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            @elseif ($module['icon'] === 'clipboard')
                                <svg viewBox="0 0 64 64" class="chief-home-svg" aria-hidden="true">
                                    <rect x="16" y="12" width="32" height="42" rx="2" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <rect x="24" y="8" width="16" height="8" rx="1" fill="none" stroke="currentColor" stroke-width="2.5"/>
                                    <circle cx="24" cy="28" r="2.5" fill="currentColor"/>
                                    <path d="M30 28h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                    <circle cx="24" cy="38" r="2.5" fill="currentColor"/>
                                    <path d="M30 38h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                    <circle cx="24" cy="48" r="2.5" fill="currentColor"/>
                                    <path d="M30 48h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                </svg>
                            @else
                                <svg viewBox="0 0 64 64" class="chief-home-svg" aria-hidden="true">
                                    <circle cx="32" cy="32" r="18" fill="none" stroke="currentColor" stroke-width="3"/>
                                    <circle cx="32" cy="22" r="2.5" fill="currentColor"/>
                                    <path d="M32 28v18" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                </svg>
                            @endif
                        </div>
                        <span class="chief-home-caret" style="border-top-color: {{ $module['color'] }}"></span>
                    </div>

                    <h2 class="chief-home-title" style="color: {{ $module['text'] }}">{{ $module['title'] }}</h2>

                    <ul class="chief-home-links">
                        @foreach ($module['links'] as [$label, $route, $enabled])
                            <li>
                                @if ($enabled && $route && Route::has($route))
                                    <a href="{{ route($route) }}" wire:navigate style="color: {{ $module['text'] }}">{{ $label }}</a>
                                @else
                                    <span class="chief-home-disabled">{{ $label }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="chief-home-alert" role="status">
            <span class="chief-home-alert-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26">
                    <path fill="#fff" d="M12 3L1.5 21h21L12 3zm0 5.2l6.6 11.3H5.4L12 8.2z"/>
                    <rect x="11" y="10" width="2.2" height="6.2" fill="#c62828"/>
                    <rect x="11" y="17.4" width="2.2" height="2.2" fill="#c62828"/>
                </svg>
            </span>
            <span>{{ $lowStockCount }} item(s) running low and should be ordered soon</span>
        </div>
    </div>
</x-app-layout>
