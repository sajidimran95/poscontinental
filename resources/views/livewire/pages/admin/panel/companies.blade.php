<?php

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin'), Title('Companies')] class extends Component
{
    use WithPagination;

    public string $title = 'Companies';

    #[Url]
    public string $search = '';

    public string $status = '';

    public function with(): array
    {
        $companies = Company::query()
            ->withCount('users')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($i) => $i->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(20);

        return [
            'title' => $this->title,
            'companies' => $companies,
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleCompany(int $id): void
    {
        $company = Company::query()->findOrFail($id);
        $company->update(['is_active' => ! $company->is_active]);
        $this->status = $company->code.' company is now '.($company->is_active ? 'Active' : 'Inactive').'.';
    }

    public function toggleCustomerApi(int $id): void
    {
        $company = Company::query()->findOrFail($id);
        $company->update(['customer_app_api_active' => ! $company->customer_app_api_active]);
        $this->status = $company->code.' Customer App API is now '.($company->customer_app_api_active ? 'Active' : 'Inactive').'.';
    }
}; ?>

<div>
    @if ($status)
        <div class="admin-flash">{{ $status }}</div>
    @endif

    <div class="admin-card">
        <div class="admin-toolbar">
            <input type="search" wire:model.live.debounce.300ms="search" class="admin-search" placeholder="Search companies…" />
            <a href="{{ route('admin.panel.companies.create') }}" wire:navigate class="admin-btn admin-btn-primary">Register company</a>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Users</th>
                    <th>Company</th>
                    <th>Customer App API</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $c)
                    <tr>
                        <td class="font-mono text-sky-300">{{ $c->code }}</td>
                        <td>{{ $c->name }}</td>
                        <td>{{ number_format($c->users_count) }}</td>
                        <td>
                            <span @class(['admin-pill', $c->is_active ? 'admin-pill-on' : 'admin-pill-off'])>
                                {{ $c->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <span @class(['admin-pill', $c->customer_app_api_active ? 'admin-pill-on' : 'admin-pill-off'])>
                                {{ $c->customer_app_api_active ? 'API Active' : 'API Off' }}
                            </span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" wire:click="toggleCustomerApi({{ $c->id }})" @class([
                                'admin-btn',
                                'admin-btn-success' => ! $c->customer_app_api_active,
                                'admin-btn-danger' => $c->customer_app_api_active,
                            ])>
                                {{ $c->customer_app_api_active ? 'Deactivate API' : 'Activate API' }}
                            </button>
                            <button type="button" wire:click="toggleCompany({{ $c->id }})" class="admin-btn">
                                {{ $c->is_active ? 'Disable co.' : 'Enable co.' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-slate-400">No companies found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">{{ $companies->links() }}</div>
    </div>
</div>
