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

    public string $errorMessage = '';

    public ?int $gen_driver_id = null;

    /** Unused; kept so stale Livewire snapshots from the old warehouse picker do not 500. */
    public ?int $gen_location_id = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'view'), 403);
        $this->date = $this->date !== '' ? $this->date : now()->toDateString();
    }

    public function with(DeliveryRouteService $service): array
    {
        $companyId = (int) auth()->user()->company_id;
        $routes = $service->routesForDate($companyId, $this->date);

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
            <input type="date" class="desk-input" wire:model.live="date" aria-label="Route date" />
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
            <span class="desk-title-meta">{{ $routes->count() }} driver{{ $routes->count() === 1 ? '' : 's' }}</span>
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
                                        <a class="desk-btn desk-btn-primary" href="{{ route('deliveries.routes.show', $route) }}" wire:navigate>View Route</a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="8">No routes for this date. Assign invoices, select a driver, then Generate route.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
