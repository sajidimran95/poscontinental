<?php

use App\Models\DeliveryArea;
use App\Services\Delivery\DeliveryAreaService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Delivery Areas')] class extends Component
{
    use WithPagination;
    use WithFileUploads;

    public string $q = '';

    public string $state = '';

    public string $state_code = '';

    public string $city = '';

    public string $zip_code = '';

    public $csv = null;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'view'), 403);
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $term = trim($this->q);

        return [
            'areas' => DeliveryArea::query()
                ->where('company_id', auth()->user()->company_id)
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%'.$term.'%';
                    $q->where(function ($inner) use ($like) {
                        $inner->where('state', 'like', $like)
                            ->orWhere('state_code', 'like', $like)
                            ->orWhere('city', 'like', $like)
                            ->orWhere('zip_code', 'like', $like);
                    });
                })
                ->orderBy('state')
                ->orderBy('city')
                ->orderBy('zip_code')
                ->paginate(50),
        ];
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $this->validate([
            'state' => 'required|string|max:80',
            'state_code' => 'required|string|max:8',
            'city' => 'nullable|string|max:80',
            'zip_code' => 'nullable|string|max:16',
        ]);

        DeliveryArea::query()->firstOrCreate(
            [
                'company_id' => auth()->user()->company_id,
                'state_code' => strtoupper($this->state_code),
                'city' => $this->city !== '' ? $this->city : '',
                'zip_code' => $this->zip_code !== '' ? $this->zip_code : '',
            ],
            [
                'state' => $this->state,
                'is_active' => true,
                'country' => 'USA',
            ]
        );

        $this->reset('state', 'state_code', 'city', 'zip_code');
    }

    public function import(DeliveryAreaService $service): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $this->errorMessage = '';
        $this->validate(['csv' => 'required|file|mimes:csv,txt|max:51200']);
        $stats = $service->importCsv((int) auth()->user()->company_id, $this->csv);
        $this->statusMessage = 'Total '.$stats['total'].' · Imported '.$stats['imported'].' · Skipped '.$stats['skipped'].' · Duplicate '.$stats['duplicate'].' · Invalid '.$stats['invalid'];
        $this->csv = null;
    }

    public function toggle(int $id): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $area = DeliveryArea::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $area->is_active = ! $area->is_active;
        $area->save();
    }
}; ?>

<div class="desk-page dlv-page">
    <div class="desk-main desk-main-rail-layout">
        <div class="desk-titlebar">
            <h2 class="desk-title">Delivery Areas</h2>
            <span class="desk-title-meta">Import city/ZIP CSV. Do not invent locations — use your dataset.</span>
        </div>

        @if ($statusMessage !== '')
            <div class="dlv-banner is-ok">{{ $statusMessage }}</div>
        @endif
        @if ($errorMessage !== '')
            <div class="dlv-banner is-err">{{ $errorMessage }}</div>
        @endif

        <form wire:submit="save" class="dlv-form">
            <input class="desk-input" wire:model="state" placeholder="State name" required />
            <input class="desk-input" wire:model="state_code" placeholder="MI" maxlength="8" required />
            <input class="desk-input" wire:model="city" placeholder="City" />
            <input class="desk-input" wire:model="zip_code" placeholder="ZIP" />
            <button type="submit" class="desk-btn desk-btn-primary">Add area</button>
        </form>

        <form wire:submit="import" class="dlv-form">
            <input type="file" wire:model="csv" accept=".csv,text/csv" />
            <button type="submit" class="desk-btn">Import CSV</button>
            <span class="dlv-muted">Columns: state, state_code, city, zip_code (optional latitude, longitude, county)</span>
        </form>

        <div class="desk-toolbar">
            <input type="search" class="desk-search" wire:model.live.debounce.250ms="q" placeholder="Search state, city, or ZIP…" />
        </div>

        <div class="desk-main-body">
            <div class="desk-grid">
                <table class="desk-table desk-table-fit">
                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Code</th>
                            <th>City</th>
                            <th>ZIP</th>
                            <th>Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($areas as $area)
                            <tr>
                                <td>{{ $area->state }}</td>
                                <td>{{ $area->state_code }}</td>
                                <td>{{ $area->city !== '' ? $area->city : '—' }}</td>
                                <td>{{ $area->zip_code !== '' ? $area->zip_code : '—' }}</td>
                                <td>{{ $area->is_active ? 'Yes' : 'No' }}</td>
                                <td>
                                    <button type="button" class="desk-btn desk-btn-sm" wire:click="toggle({{ $area->id }})">
                                        {{ $area->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr class="is-empty"><td colspan="6">No areas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="desk-footer">
                <x-desk-pager :paginator="$areas" />
            </div>
        </div>
    </div>
</div>
