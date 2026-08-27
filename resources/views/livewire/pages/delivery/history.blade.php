<?php

use App\Models\Role;
use App\Models\User;
use App\Services\Delivery\DeliveryRouteService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Route History')] class extends Component
{
    public string $from = '';

    public string $to = '';

    public ?int $driver_id = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'view'), 403);
        $this->to = now()->toDateString();
        $this->from = now()->subDays(14)->toDateString();
    }

    public function with(DeliveryRouteService $service): array
    {
        $companyId = (int) auth()->user()->company_id;
        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');

        return [
            'routes' => $service->routesHistory($companyId, $this->driver_id, $this->from, $this->to),
            'drivers' => User::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->when($deliveryRoleId, fn ($q) => $q->where('role_id', $deliveryRoleId))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}; ?>

<div class="desk-page dlv-page">
    <div class="desk-main desk-main-rail-layout">
        <div class="desk-toolbar orders-toolbar">
            <input type="date" class="desk-input" wire:model.live="from" aria-label="From" />
            <input type="date" class="desk-input" wire:model.live="to" aria-label="To" />
            <select class="desk-select orders-party-select" wire:model.live="driver_id">
                <option value="">All drivers</option>
                @foreach ($drivers as $driver)
                    <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="desk-titlebar">
            <h2 class="desk-title">Route History</h2>
            <span class="desk-title-meta">{{ $routes->count() }} routes</span>
        </div>
        <div class="desk-main-body">
            <div class="desk-grid">
                <table class="desk-table desk-table-fit">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Driver</th>
                            <th>Start</th>
                            <th>Orders</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routes as $route)
                            <tr>
                                <td>{{ $route->route_date?->format('Y-m-d') }}</td>
                                <td>{{ $route->driver?->name }}</td>
                                <td>{{ $route->start_name }}</td>
                                <td>{{ $route->total_orders }}</td>
                                <td>{{ ucfirst($route->status) }}</td>
                                <td><a class="desk-btn desk-btn-sm" href="{{ route('deliveries.routes.show', $route) }}" wire:navigate>View</a></td>
                            </tr>
                        @empty
                            <tr class="is-empty"><td colspan="6">No historical routes in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
