<?php

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Customer App API')] class extends Component
{
    public bool $customer_app_api_active = true;

    public string $status = '';

    public function mount(): void
    {
        $company = Company::query()->find(auth()->user()->company_id);
        $this->customer_app_api_active = (bool) ($company?->customer_app_api_active ?? true);
    }

    public function save(): void
    {
        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $company->update([
            'customer_app_api_active' => $this->customer_app_api_active,
        ]);

        $this->status = $this->customer_app_api_active
            ? 'Customer App API is Active — Flutter apps can login.'
            : 'Customer App API is Inactive — all customer app logins are blocked.';
    }

    public function activate(): void
    {
        $this->customer_app_api_active = true;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->customer_app_api_active = false;
        $this->save();
    }
}; ?>

<div class="desk-page">
    <div class="desk-main" style="max-width:40rem">
        <x-action-bar title="Customer App API" />

        @if ($status)
            <div class="desk-flash">{{ $status }}</div>
        @endif

        <div class="inv-card" style="margin:1rem 0.85rem">
            <div class="inv-card-title">One Customer App — one API</div>
            <p style="margin:0 0 1rem;font-size:13px;color:#475569;line-height:1.5">
                This switch turns the shared Customer Flutter API
                (<code>/api/customer/*</code>) <strong>on</strong> or <strong>off</strong> for the whole company.
                It is not per-customer. Set each customer’s app login email/password on the Customer form (Account tab).
            </p>

            <div class="so-form-row so-form-row-side sc-field" style="align-items:center">
                <span class="so-form-lbl">API Status</span>
                <span @class([
                    'desk-pill',
                    'desk-pill-invoiced' => $customer_app_api_active,
                    'desk-pill-muted' => ! $customer_app_api_active,
                ])>{{ $customer_app_api_active ? 'Active' : 'Inactive' }}</span>
            </div>

            <div class="rpt-actions" style="margin-top:1rem;display:flex;gap:0.5rem">
                <button type="button" wire:click="activate" class="desk-btn desk-btn-primary" @disabled($customer_app_api_active)>
                    Activate API
                </button>
                <button type="button" wire:click="deactivate" class="desk-btn" @disabled(! $customer_app_api_active)>
                    Deactivate API
                </button>
            </div>

            <p class="item-hint" style="margin-top:1rem;border:0;padding:0">
                Docs: <code>docs/CUSTOMER_APP_API.md</code>
            </p>
        </div>
    </div>
</div>
