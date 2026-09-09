<?php

use App\Models\User;
use App\Services\Delivery\DeliveryRouteService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title("Today's Routes")] class extends Component
{
    #[Url]
    public string $date = '';

    #[Url]
    public string $date_from = '';

    #[Url]
    public string $date_to = '';

    #[Url]
    public string $listFilter = 'today';

    public string $errorMessage = '';

    public ?int $gen_driver_id = null;

    /** Unused; kept so stale Livewire snapshots from the old warehouse picker do not 500. */
    public ?int $gen_location_id = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'view'), 403);
        if (! in_array($this->listFilter, ['today', 'pending', 'range'], true)) {
            $this->listFilter = 'today';
        }
        $today = now()->toDateString();
        $this->date = $this->date !== '' ? $this->date : $today;
        if ($this->date_from === '') {
            $this->date_from = $this->date !== '' ? $this->date : $today;
        }
        if ($this->date_to === '') {
            $this->date_to = $this->date_from;
        }
    }

    public function updatedDateFrom(): void
    {
        $this->date_from = $this->normalizeRouteDate($this->date_from) ?: now()->toDateString();
        if ($this->date_to === '' || $this->date_to < $this->date_from) {
            $this->date_to = $this->date_from;
        }
        $this->date = $this->date_from;
    }

    public function updatedDateTo(): void
    {
        $this->date_to = $this->normalizeRouteDate($this->date_to) ?: $this->date_from;
        if ($this->date_from !== '' && $this->date_to < $this->date_from) {
            $this->date_from = $this->date_to;
        }
        $this->date = $this->date_to !== '' ? $this->date_to : $this->date_from;
    }

    protected function normalizeRouteDate(?string $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public function with(DeliveryRouteService $service): array
    {
        $companyId = (int) auth()->user()->company_id;
        $routes = $service->routesForBoard($companyId, $this->listFilter, $this->date_from, $this->date_to);

        $orders = $routes->sum(fn ($r) => (int) $r->total_orders);
        $delivered = $routes->sum(fn ($r) => $r->stops->where('status', 'delivered')->count());
        $failed = $routes->sum(fn ($r) => $r->stops->where('status', 'failed')->count());
        $remaining = $routes->sum(fn ($r) => $r->remainingCount());
        $miles = round($routes->sum('total_distance') / 1609.34, 1);

        return [
            'routes' => $routes,
            'totals' => compact('orders', 'delivered', 'failed', 'remaining', 'miles'),
            'drivers' => User::assignableDeliveryDrivers($companyId),
        ];
    }

    public function generate(DeliveryRouteService $service)
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $this->errorMessage = '';
        try {
            $route = $service->generateRoute(
                auth()->user(),
                (int) $this->gen_driver_id,
                $this->date
            );

            return $this->redirect(route('deliveries.routes.show', $route), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Could not generate route.';
        }
    }
}; ?>

<div class="desk-page dlv-page dlv-routes-page">
    <div class="desk-main desk-main-rail-layout" style="height:100%;min-height:0">
        <div class="desk-toolbar orders-toolbar">
            <select class="desk-select orders-status-select" wire:model.live="listFilter" aria-label="Route filter">
                <option value="today">Today</option>
                <option value="pending">Pending</option>
                <option value="range">Date range</option>
            </select>
            @if ($listFilter === 'range')
                <label class="desk-toolbar-label" for="dlv-route-from">Route date</label>
                <input
                    id="dlv-route-from"
                    type="date"
                    class="desk-input"
                    wire:model.live="date_from"
                    aria-label="Route date from"
                />
                <span class="dlv-muted">to</span>
                <input
                    type="date"
                    class="desk-input"
                    wire:model.live="date_to"
                    aria-label="Route date to"
                />
            @endif
            <select class="desk-select orders-party-select" wire:model="gen_driver_id" aria-label="Delivery man">
                <option value="">Driver…</option>
                @foreach ($drivers as $driver)
                    <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                @endforeach
            </select>
            <div class="orders-toolbar-right">
                <button type="button" class="desk-btn desk-btn-primary" wire:click="generate">Generate route</button>
                <a href="{{ route('deliveries.assign') }}" class="desk-btn" wire:navigate>Assign invoices</a>
            </div>
        </div>

        <div class="desk-titlebar">
            <h2 class="desk-title">Today's Routes</h2>
            <span class="desk-title-meta">
                @if ($listFilter === 'pending')
                    {{ $routes->count() }} pending route{{ $routes->count() === 1 ? '' : 's' }} (all dates)
                @elseif ($listFilter === 'today')
                    {{ $routes->count() }} route{{ $routes->count() === 1 ? '' : 's' }} created today
                @else
                    {{ $routes->count() }} driver{{ $routes->count() === 1 ? '' : 's' }}{{ ($date_from !== '' || $date_to !== '') ? ' · '.($date_from !== '' ? \Illuminate\Support\Carbon::parse($date_from)->format('n/j/Y') : '…').' – '.($date_to !== '' ? \Illuminate\Support\Carbon::parse($date_to)->format('n/j/Y') : '…') : '' }}
                @endif
            </span>
        </div>

        @if ($errorMessage !== '')
            <div class="dlv-banner is-err">{{ $errorMessage }}</div>
        @endif

        <div class="dlv-summary">
            <div><strong>Drivers</strong><span>{{ $routes->count() }}</span></div>
            <div><strong>Orders</strong><span>{{ $totals['orders'] }}</span></div>
            <div><strong>Delivered</strong><span>{{ $totals['delivered'] }}</span></div>
            <div><strong>Remaining</strong><span>{{ $totals['remaining'] }}</span></div>
            <div><strong>Failed</strong><span>{{ $totals['failed'] }}</span></div>
            <div><strong>Distance</strong><span>{{ $totals['miles'] }} mi</span></div>
        </div>

        <div class="desk-main-split dlv-routes-body">
            <div class="desk-main-body">
                <div class="desk-grid" style="flex:1 1 auto;min-height:0">
                    <table class="desk-table dlv-routes-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Driver</th>
                                <th>Status</th>
                                <th style="min-width:9rem">Progress</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Delivered</th>
                                <th class="text-right">Left</th>
                                <th class="text-right">Miles</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($routes as $route)
                                @php
                                    $delivered = $route->stops->where('status', 'delivered')->count();
                                    $left = $route->remainingCount();
                                    $total = max(1, (int) $route->total_orders);
                                    $pct = (int) round(($delivered / $total) * 100);
                                    $miles = round($route->total_distance / 1609.34, 1);
                                    $status = $route->status ?: 'planned';
                                    $pill = match ($status) {
                                        'completed' => 'delivered',
                                        'started' => 'en_route',
                                        'cancelled' => 'failed',
                                        default => 'pending',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $route->route_date?->format('n/j/Y') ?: '—' }}</td>
                                    <td>
                                        <strong>{{ $route->driver?->name ?: 'Driver' }}</strong>
                                        <div class="dlv-muted">{{ $route->start_name }}</div>
                                    </td>
                                    <td><span class="dlv-pill is-{{ $pill }}">{{ ucfirst($status) }}</span></td>
                                    <td>
                                        <div class="dlv-progress" title="{{ $delivered }}/{{ $route->total_orders }}">
                                            <span style="width: {{ $pct }}%"></span>
                                        </div>
                                        <div class="dlv-muted">{{ $pct }}%</div>
                                    </td>
                                    <td class="text-right desk-num">{{ $route->total_orders }}</td>
                                    <td class="text-right desk-num">{{ $delivered }}</td>
                                    <td class="text-right desk-num">{{ $left }}</td>
                                    <td class="text-right desk-num">{{ $miles }}</td>
                                    <td class="text-right" style="white-space:nowrap">
                                        <a class="desk-btn desk-btn-primary" href="{{ route('deliveries.routes.show', ['deliveryRoute' => $route, 'listFilter' => 'today']) }}" wire:navigate>View Route</a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="9">
                                        @if ($listFilter === 'pending')
                                            No pending routes.
                                        @elseif ($listFilter === 'today')
                                            No routes created today. Assign invoices, then Generate route.
                                        @else
                                            No routes in this date range. Assign invoices, select a driver, then Generate route.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
