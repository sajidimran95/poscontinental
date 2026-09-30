<?php

use App\Models\Customer;
use App\Models\EcomCustomerLicense;
use App\Support\Store\WholesaleAccount;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Wholesale Applications')] class extends Component
{
    public string $filter = 'pending';

    public string $search = '';

    public ?int $openCustomerId = null;

    public string $statusMessage = '';

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['pending', 'approved', 'rejected', 'all'], true) ? $filter : 'pending';
        $this->openCustomerId = null;
    }

    public function toggleLicenses(int $customerId): void
    {
        $this->openCustomerId = $this->openCustomerId === $customerId ? null : $customerId;
    }

    public function setStatus(int $customerId, string $status): void
    {
        abort_unless(in_array($status, [WholesaleAccount::APPROVED, WholesaleAccount::REJECTED, WholesaleAccount::PENDING], true), 422);
        $customer = Customer::query()->where('company_id', auth()->user()->company_id)->findOrFail($customerId);
        $customer->update(['web_status' => $status]);
        $this->statusMessage = ($customer->company_name ?: $customer->contact).' is now '.WholesaleAccount::label($status).'.';
    }

    public function with(): array
    {
        $companyId = (int) auth()->user()->company_id;
        $term = trim($this->search);

        $applications = Customer::query()
            ->where('company_id', $companyId)
            ->whereNotNull('web_registered_at')
            ->when($this->filter !== 'all', fn ($q) => $q->where('web_status', $this->filter))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('company_name', 'like', "%{$term}%")
                ->orWhere('contact', 'like', "%{$term}%")
                ->orWhere('portal_email', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")
                ->orWhere('customer_id', 'like', "%{$term}%")))
            ->withCount('ecomLicenses')
            ->orderByDesc('web_registered_at')
            ->limit(200)
            ->get();

        $counts = Customer::query()
            ->where('company_id', $companyId)
            ->whereNotNull('web_registered_at')
            ->selectRaw('web_status, COUNT(*) as n')
            ->groupBy('web_status')
            ->pluck('n', 'web_status');

        return [
            'applications' => $applications,
            'counts' => $counts,
            'licenses' => $this->openCustomerId
                ? EcomCustomerLicense::query()->where('customer_id', $this->openCustomerId)->orderBy('id')->get()
                : collect(),
        ];
    }
}; ?>

<div class="stamp-inv-page">
    <x-action-bar title="Wholesale Applications" />

    @if ($statusMessage !== '')
        <div class="stamp-inv-flash stamp-inv-flash-ok" role="status">{{ $statusMessage }}</div>
    @endif

    <div class="stamp-inv-body">
        <p class="stamp-inv-hint">
            Retailers who registered on the online store. They can browse and fill a cart right away; <strong>checkout unlocks after you approve</strong> them here.
        </p>

        <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-bottom:.6rem;">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <button type="button" wire:click="setFilter('{{ $key }}')" @class(['desk-btn', 'desk-btn-primary' => $filter === $key])>
                    {{ $label }}{{ $key !== 'all' ? ' ('.($counts[$key] ?? 0).')' : '' }}
                </button>
            @endforeach
            <input type="search" wire:model.live.debounce.300ms="search" class="desk-input" placeholder="Search business, contact, email, phone…" style="margin-left:auto;min-width:16rem;" />
        </div>

        <div class="chief-grid" style="max-height:calc(100vh - 16rem);overflow:auto;">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Applied</th>
                        <th>Business</th>
                        <th>Contact</th>
                        <th>Email / Phone</th>
                        <th>Address</th>
                        <th>Tax ID</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $app)
                        @php $st = WholesaleAccount::status($app); @endphp
                        <tr wire:key="app-{{ $app->id }}">
                            <td>{{ $app->web_registered_at?->format('n/j/Y g:i A') }}</td>
                            <td><a href="{{ route('sales.customers.edit', $app) }}" class="text-blue-700 underline">{{ $app->company_name }}</a> <span class="text-slate-500">{{ $app->customer_id }}</span></td>
                            <td>{{ $app->contact }}</td>
                            <td>{{ $app->portal_email }}<br><span class="text-slate-500">{{ $app->mobile }}</span></td>
                            <td>{{ $app->address }}, {{ $app->city }} {{ $app->state }} {{ $app->zip_code }}</td>
                            <td>{{ $app->fein_no ?: '—' }}</td>
                            <td><span @class(['desk-pill', 'desk-pill-new' => $st === 'pending', 'desk-pill-invoiced' => $st === 'approved', 'desk-pill-muted' => $st === 'rejected'])>{{ ucfirst($st) }}</span></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="desk-btn" wire:click="toggleLicenses({{ $app->id }})">Licenses ({{ $app->ecom_licenses_count }})</button>
                                @if ($st !== 'approved')
                                    <button type="button" class="desk-btn desk-btn-primary" wire:click="setStatus({{ $app->id }}, 'approved')">Approve</button>
                                @endif
                                @if ($st !== 'rejected')
                                    <button type="button" class="desk-btn" wire:click="setStatus({{ $app->id }}, 'rejected')" wire:confirm="Reject this wholesale application?">Reject</button>
                                @endif
                            </td>
                        </tr>
                        @if ($openCustomerId === $app->id)
                            <tr wire:key="lic-{{ $app->id }}">
                                <td colspan="8" style="background:#f8fafc;">
                                    @forelse ($licenses as $lic)
                                        <div style="padding:.2rem 0;">
                                            <strong>{{ $lic->displayLabel() }}</strong>
                                            · # {{ $lic->certificate_number ?: '—' }}
                                            · Exp {{ $lic->expires_at?->format('n/j/Y') ?: '—' }}
                                            @if ($lic->file_path)
                                                · <a href="{{ route('admin.ecommerce.license-file', $lic) }}" target="_blank" class="text-blue-700 underline">{{ $lic->original_name ?: 'View file' }}</a>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-slate-500">No licenses uploaded.</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="px-2 py-6 text-slate-500">No applications in this list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
