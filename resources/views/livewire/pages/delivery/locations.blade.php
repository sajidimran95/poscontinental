<?php

use App\Models\CompanyLocation;
use App\Services\Delivery\RouteOptimizationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Company Locations')] class extends Component
{
    public string $name = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $state_code = '';

    public string $zip_code = '';

    public string $country = 'USA';

    public string $latitude = '';

    public string $longitude = '';

    public bool $is_default = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'view'), 403);
    }

    public function with(): array
    {
        return [
            'locations' => CompanyLocation::query()
                ->where('company_id', auth()->user()->company_id)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ];
    }

    public function save(RouteOptimizationService $geo): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $this->validate([
            'name' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:80',
            'state' => 'nullable|string|max:80',
            'state_code' => 'nullable|string|max:8',
            'zip_code' => 'nullable|string|max:16',
            'country' => 'nullable|string|max:80',
        ]);

        $companyId = (int) auth()->user()->company_id;
        $row = $this->editingId
            ? CompanyLocation::query()->where('company_id', $companyId)->findOrFail($this->editingId)
            : new CompanyLocation(['company_id' => $companyId]);

        $row->fill([
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'state_code' => strtoupper($this->state_code),
            'zip_code' => $this->zip_code,
            'country' => $this->country ?: 'USA',
            'is_default' => $this->is_default,
            'is_active' => true,
        ]);

        $lat = is_numeric($this->latitude) ? (float) $this->latitude : null;
        $lng = is_numeric($this->longitude) ? (float) $this->longitude : null;
        if ($lat === null || $lng === null) {
            $found = $geo->geocode($row->formattedAddress());
            $lat = $found['lat'] ?? $lat;
            $lng = $found['lng'] ?? $lng;
        }
        $row->latitude = $lat;
        $row->longitude = $lng;
        $row->save();

        if ($row->is_default) {
            CompanyLocation::query()
                ->where('company_id', $companyId)
                ->where('id', '!=', $row->id)
                ->update(['is_default' => false]);
        }

        $this->reset('name', 'address', 'city', 'state', 'state_code', 'zip_code', 'latitude', 'longitude', 'is_default', 'editingId');
        $this->country = 'USA';
    }

    public function edit(int $id): void
    {
        $row = CompanyLocation::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->editingId = $row->id;
        $this->name = $row->name;
        $this->address = (string) $row->address;
        $this->city = (string) $row->city;
        $this->state = (string) $row->state;
        $this->state_code = (string) $row->state_code;
        $this->zip_code = (string) $row->zip_code;
        $this->country = (string) ($row->country ?: 'USA');
        $this->latitude = $row->latitude !== null ? (string) $row->latitude : '';
        $this->longitude = $row->longitude !== null ? (string) $row->longitude : '';
        $this->is_default = (bool) $row->is_default;
    }

    public function toggle(int $id): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $row = CompanyLocation::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $row->is_active = ! $row->is_active;
        $row->save();
    }
}; ?>

<div class="desk-page dlv-page">
    <div class="desk-main desk-main-rail-layout">
    <div class="desk-titlebar">
        <h2 class="desk-title">Company / Warehouse Locations</h2>
        <span class="desk-title-meta">Starting point for delivery routes</span>
    </div>

    <form wire:submit="save" class="dlv-form">
        <input class="so-input" wire:model="name" placeholder="Name (Main Warehouse)" required />
        <input class="so-input" wire:model="address" placeholder="Address" />
        <input class="so-input" wire:model="city" placeholder="City" />
        <input class="so-input" wire:model="state" placeholder="State" />
        <input class="so-input" wire:model="state_code" placeholder="MI" maxlength="8" />
        <input class="so-input" wire:model="zip_code" placeholder="ZIP" />
        <input class="so-input" wire:model="latitude" placeholder="Latitude (optional)" />
        <input class="so-input" wire:model="longitude" placeholder="Longitude (optional)" />
        <label class="dlv-check"><input type="checkbox" wire:model="is_default" /> Default</label>
        <button type="submit" class="desk-btn desk-btn-primary">{{ $editingId ? 'Update' : 'Save location' }}</button>
    </form>

    <table class="desk-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Address</th>
                <th>Active</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($locations as $loc)
                <tr>
                    <td>{{ $loc->name }}{{ $loc->is_default ? ' (default)' : '' }}</td>
                    <td>{{ $loc->formattedAddress() }}</td>
                    <td>{{ $loc->is_active ? 'Yes' : 'No' }}</td>
                    <td>
                        <button type="button" class="desk-btn desk-btn-sm" wire:click="edit({{ $loc->id }})">Edit</button>
                        <button type="button" class="desk-btn desk-btn-sm" wire:click="toggle({{ $loc->id }})">{{ $loc->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </td>
                </tr>
            @empty
                <tr class="is-empty"><td colspan="4">No warehouses yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
