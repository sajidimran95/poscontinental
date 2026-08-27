<?php

use App\Models\DeliveryRoute;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Delivery Men')] class extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'view'), 403);
    }

    public function with(): array
    {
        $companyId = (int) auth()->user()->company_id;
        $today = now()->toDateString();
        $term = trim($this->search);

        $drivers = User::assignableDeliveryDrivers($companyId, ['id', 'name', 'username', 'email', 'role_id']);
        if ($term !== '') {
            $needle = mb_strtolower($term);
            $drivers = $drivers->filter(function ($d) use ($needle) {
                return str_contains(mb_strtolower((string) $d->name), $needle)
                    || str_contains(mb_strtolower((string) $d->username), $needle)
                    || str_contains(mb_strtolower((string) $d->email), $needle);
            })->values();
        }

        $routes = DeliveryRoute::query()
            ->with('stops')
            ->where('company_id', $companyId)
            ->whereDate('route_date', $today)
            ->where('status', '!=', DeliveryRoute::STATUS_CANCELLED)
            ->get()
            ->keyBy('delivery_user_id');

        $onRoute = $drivers->filter(fn ($d) => $routes->has($d->id))->count();
        $delivered = $routes->sum(fn ($r) => $r->stops->where('status', 'delivered')->count());
        $remaining = $routes->sum(fn ($r) => $r->remainingCount());
        $orders = $routes->sum(fn ($r) => (int) $r->total_orders);

        return compact('drivers', 'routes', 'today', 'onRoute', 'delivered', 'remaining', 'orders');
    }
}; ?>

<div class="desk-page dlv-page dlv-men-page">
    <div class="desk-main desk-main-rail-layout" style="height:100%;min-height:0">
        <div class="desk-toolbar orders-toolbar">
            <input
                type="search"
                class="desk-search"
                wire:model.live.debounce.200ms="search"
                placeholder="Search name, username, email…"
                aria-label="Search delivery men"
            />
            <div class="orders-toolbar-right">
                <a href="{{ route('deliveries.assign') }}" class="desk-btn" wire:navigate>Assign invoices</a>
                <a href="{{ route('deliveries.routes') }}" class="desk-btn desk-btn-primary" wire:navigate>Today's Routes</a>
            </div>
        </div>

        <div class="desk-titlebar">
            <h2 class="desk-title">Delivery Men</h2>
            <span class="desk-title-meta">{{ \Illuminate\Support\Carbon::parse($today)->format('M j, Y') }}</span>
        </div>

        <div class="dlv-summary">
            <div><strong>Drivers</strong><span>{{ $drivers->count() }}</span></div>
            <div><strong>On route today</strong><span>{{ $onRoute }}</span></div>
            <div><strong>Idle</strong><span>{{ max(0, $drivers->count() - $onRoute) }}</span></div>
            <div><strong>Orders today</strong><span>{{ $orders }}</span></div>
            <div><strong>Delivered</strong><span>{{ $delivered }}</span></div>
            <div><strong>Remaining</strong><span>{{ $remaining }}</span></div>
        </div>

        <div class="desk-main-split dlv-men-body">
            <div class="desk-main-body">
                <div class="desk-grid">
                    <table class="desk-table dlv-men-table">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Login</th>
                                <th>Today</th>
                                <th>Progress</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Delivered</th>
                                <th class="text-right">Left</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($drivers as $driver)
                                @php
                                    $route = $routes->get($driver->id);
                                    $done = $route ? $route->stops->where('status', 'delivered')->count() : 0;
                                    $left = $route ? $route->remainingCount() : 0;
                                    $total = $route ? max(1, (int) $route->total_orders) : 1;
                                    $pct = $route ? (int) round(($done / $total) * 100) : 0;
                                    $status = $route?->status ?: 'idle';
                                    $pill = match ($status) {
                                        'completed' => 'delivered',
                                        'started' => 'en_route',
                                        'planned' => 'pending',
                                        default => 'pending',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $driver->name }}</strong>
                                        <div class="dlv-muted">{{ $driver->role?->label ?: 'Delivery' }}</div>
                                    </td>
                                    <td>
                                        {{ $driver->username ?: '—' }}
                                        <div class="dlv-muted">{{ $driver->email }}</div>
                                    </td>
                                    <td>
                                        @if ($route)
                                            <span class="dlv-pill is-{{ $pill }}">{{ ucfirst($status) }}</span>
                                        @else
                                            <span class="dlv-pill is-pending">No route</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($route)
                                            <div class="dlv-progress" title="{{ $done }}/{{ $route->total_orders }}">
                                                <span style="width: {{ $pct }}%"></span>
                                            </div>
                                            <div class="dlv-muted">{{ $pct }}%</div>
                                        @else
                                            <span class="dlv-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right desk-num">{{ $route?->total_orders ?? 0 }}</td>
                                    <td class="text-right desk-num">{{ $done }}</td>
                                    <td class="text-right desk-num">{{ $left }}</td>
                                    <td class="text-right" style="white-space:nowrap">
                                        @if ($route)
                                            <a class="desk-btn desk-btn-primary" href="{{ route('deliveries.routes.show', $route) }}" wire:navigate>View Route</a>
                                        @else
                                            <a class="desk-btn" href="{{ route('deliveries.assign') }}" wire:navigate>Assign</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="is-empty">
                                    <td colspan="8">No Delivery users. Assign the Delivery role in Users &amp; Roles.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
