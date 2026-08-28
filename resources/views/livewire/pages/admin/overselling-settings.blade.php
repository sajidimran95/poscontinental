<?php

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Overselling Settings')] class extends Component
{
    public bool $allow_negative_stock = true;

    public string $statusMessage = '';

    public function mount(): void
    {
        $company = auth()->user()?->company;
        $this->allow_negative_stock = (bool) ($company?->allow_negative_stock ?? true);
    }

    public function save(): void
    {
        $this->statusMessage = '';
        $this->validate([
            'allow_negative_stock' => ['boolean'],
        ]);

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $company->update([
            'allow_negative_stock' => $this->allow_negative_stock,
        ]);

        $this->statusMessage = 'Overselling setting saved: '
            .($this->allow_negative_stock ? 'ON (negative stock allowed)' : 'OFF (cannot sell without available stock)').'.';
    }
}; ?>

<div class="stamp-inv-page">
    <x-action-bar title="Overselling Settings" />

    @if ($statusMessage !== '')
        <div class="stamp-inv-flash stamp-inv-flash-ok" role="status">{{ $statusMessage }}</div>
    @endif

    @if ($errors->any())
        <div class="stamp-inv-flash stamp-inv-flash-err" role="alert">
            <strong>Could not save:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stamp-inv-body">
        <form wire:submit.prevent="save" class="stamp-inv-form" style="max-width: 36rem;" autocomplete="off">
            <p class="stamp-inv-hint">
                Sales module stock rules. This is separate from Company Settings (address / FEIN).
            </p>

            <h3 class="msa-section-title">Negative stock / oversell</h3>

            <label class="stamp-inv-field" style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;padding:.5rem 0;">
                <input type="checkbox" wire:model="allow_negative_stock" style="margin-top:.35rem;width:1.15rem;height:1.15rem;" />
                <span>
                    <strong>Allow overselling (negative stock)</strong>
                    <span class="block text-slate-600" style="font-size:.9em;line-height:1.4;margin-top:.35rem;">
                        When on-hand is <strong>0</strong>, you can still sell. After invoice, stock can be
                        <strong>-1</strong>, <strong>-2</strong>, etc.<br />
                        Saving a <strong>purchase order</strong> does <strong>not</strong> add on-hand (it updates <strong>On Order</strong> only).
                        <strong>Receive</strong> the PO to add qty: e.g. stock <strong>-10</strong> + receive <strong>100</strong> = on-hand <strong>90</strong>.
                    </span>
                </span>
            </label>

            <div class="msa-field-grid" style="margin-top:1rem;">
                <div class="stamp-inv-field">
                    <span>Current rule</span>
                    <div class="desk-input" style="background:#f8fafc;display:flex;align-items:center;min-height:2.25rem;">
                        @if ($allow_negative_stock)
                            <span style="color:#0f766e;font-weight:600;">Overselling ON</span>
                        @else
                            <span style="color:#b45309;font-weight:600;">Overselling OFF — stock required</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="stamp-inv-actions" style="margin-top:1.25rem;">
                <button type="submit" class="desk-btn desk-btn-primary">Save</button>
                <a href="{{ route('sales.orders.index') }}" wire:navigate class="desk-btn">Sales Orders</a>
            </div>
        </form>
    </div>
</div>
