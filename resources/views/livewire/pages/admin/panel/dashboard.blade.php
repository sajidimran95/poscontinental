<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.admin'), Title('Dashboard')] class extends Component
{
    public string $title = 'Dashboard';

    public function with(): array
    {
        return [
            'title' => $this->title,
            'companyCount' => Company::query()->count(),
            'activeCompanies' => Company::query()->where('is_active', true)->count(),
            'apiActive' => Company::query()->where('customer_app_api_active', true)->count(),
            'customerPortals' => Customer::query()->whereNotNull('portal_email')->count(),
            'recentCompanies' => Company::query()->orderByDesc('id')->limit(5)->get(),
        ];
    }
}; ?>

<div>
    <div class="admin-grid">
        <div class="admin-card">
            <div class="admin-stat-lbl">Companies</div>
            <div class="admin-stat-val">{{ number_format($companyCount) }}</div>
        </div>
        <div class="admin-card">
            <div class="admin-stat-lbl">Active companies</div>
            <div class="admin-stat-val">{{ number_format($activeCompanies) }}</div>
        </div>
        <div class="admin-card">
            <div class="admin-stat-lbl">Customer API on</div>
            <div class="admin-stat-val">{{ number_format($apiActive) }}</div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-toolbar">
            <div>
                <div class="admin-stat-lbl">Recent companies</div>
                <p class="text-sm text-slate-400 mt-1 mb-0">{{ number_format($customerPortals) }} customer portal logins configured</p>
            </div>
            <a href="{{ route('admin.panel.companies.create') }}" wire:navigate class="admin-btn admin-btn-primary">Register company</a>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Customer App API</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentCompanies as $c)
                    <tr>
                        <td class="font-mono text-sky-300">{{ $c->code }}</td>
                        <td>{{ $c->name }}</td>
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
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-slate-400">No companies yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
