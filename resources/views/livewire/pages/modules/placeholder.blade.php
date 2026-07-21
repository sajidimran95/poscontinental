<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Module')] class extends Component
{
    public string $moduleTitle = 'Module';

    public string $phaseNote = 'This screen is scaffolded for a later build phase.';

    public array $favorites = ['all' => 'All Records'];

    public string $favorite = 'all';

    public function mount(): void
    {
        $map = [
            'purchasing.orders.index' => ['Purchase Orders', 'Phase 5 — Purchasing', ['all' => 'All POs', 'pending' => 'Pending POs', 'month' => 'This Month']],
            'purchasing.orders.create' => ['New Purchase Order', 'Phase 5 — Purchasing', []],
            'purchasing.receivings.index' => ['Inventory Receivings', 'Phase 5 — Purchasing', ['all' => 'All Receivings']],
            'purchasing.rtv.index' => ['Return To Vendor (RTVs) List', 'Phase 5 — Purchasing', ['all' => 'All RTVs']],
            'inventory.stock-counts.index' => ['Stock Counts', 'Phase 6 — Stock Counts', ['all' => 'All Counts']],
            'inventory.bulk-pricing' => ['Bulk Pricing Update', 'Phase 10 — Pricing Tools', []],
            'sales.orders.index' => ['Orders List', 'Phase 7 — Sales', ['all' => 'All Orders', 'new' => 'New Orders', 'not_invoiced' => 'Not Invoiced']],
            'sales.orders.create' => ['New Sales Order', 'Phase 7 — Sales', []],
            'sales.invoices.index' => ['Invoices List', 'Phase 7 — Sales', ['all' => 'All Invoices', 'not_paid' => 'NOT PAID']],
            'sales.payments.index' => ['Payments & Credits', 'Phase 7 — Sales', []],
            'sales.credit-memos.index' => ['Credit Memos', 'Phase 7 — awaiting client screenshot', []],
            'inquiries.stock-status' => ['Stock Status', 'Phase 8 — Inquiries', []],
            'inquiries.item-velocity' => ['Item Velocity', 'Phase 8 — Inquiries', []],
            'reports.sales' => ['Sales Report', 'Phase 10 — Reports', []],
            'reports.price-list' => ['Price List Generator', 'Phase 10 — Reports', []],
        ];

        $name = request()->route()?->getName();
        if ($name && isset($map[$name])) {
            [$this->moduleTitle, $this->phaseNote, $this->favorites] = $map[$name];
            if ($this->favorites === []) {
                $this->favorites = ['all' => 'All'];
            }
        }
    }
}; ?>

<div class="flex gap-2 h-full">
    @if (count($favorites) > 1)
        <x-favorite-list :favorites="$favorites" :active="$favorite" />
    @endif

    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar :title="'Action'" />
        <div class="flex flex-wrap items-center gap-2 px-2 py-2 bg-slate-100 border-b border-slate-300">
            <label class="text-sm text-slate-700 whitespace-nowrap">Search {{ $moduleTitle }}:</label>
            <input type="search" class="chief-input w-64" disabled placeholder="Available in full module build" />
            <button type="button" class="chief-btn" disabled>New Search</button>
        </div>

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">{{ $moduleTitle }}</div>

        <div class="flex-1 p-6">
            <div class="rounded border border-amber-300 bg-amber-50 px-4 py-6 text-sm text-amber-950 max-w-2xl">
                <p class="font-semibold text-base">{{ $moduleTitle }}</p>
                <p class="mt-2">{{ $phaseNote }}</p>
                <p class="mt-2 text-amber-800">Chief-style chrome (menu, tabs, Favorite Lists, Action bar) is ready. Full fields and workflows ship in the phase listed above.</p>
            </div>
        </div>

        <x-record-count :count="0" note="0 records — module pending full implementation" />
    </div>
</div>
