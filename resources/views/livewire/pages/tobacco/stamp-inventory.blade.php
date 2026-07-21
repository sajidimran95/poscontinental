<?php

use App\Models\TobaccoStampInventory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Stamp Inventory')] class extends Component
{
    public string $period_start = '';

    public string $period_end = '';

    public string $r1_beginning_unaffixed = '0';

    public string $r2_beginning_affixed = '0';

    public string $r3_purchased = '0';

    public string $r4_affixed = '0';

    public string $r5_ending_unaffixed = '0';

    public string $r6_ending_affixed = '0';

    public string $notes = '';

    public function mount(): void
    {
        $this->period_start = now()->startOfMonth()->toDateString();
        $this->period_end = now()->endOfMonth()->toDateString();
    }

    public function with(): array
    {
        return [
            'rows' => TobaccoStampInventory::query()
                ->where('company_id', auth()->user()->company_id)
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        TobaccoStampInventory::query()->create([
            'company_id' => auth()->user()->company_id,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'r1_beginning_unaffixed' => $this->r1_beginning_unaffixed,
            'r2_beginning_affixed' => $this->r2_beginning_affixed,
            'r3_purchased' => $this->r3_purchased,
            'r4_affixed' => $this->r4_affixed,
            'r5_ending_unaffixed' => $this->r5_ending_unaffixed,
            'r6_ending_affixed' => $this->r6_ending_affixed,
            'notes' => $this->notes,
            'created_by' => auth()->id(),
        ]);

        session()->flash('status', 'Stamp inventory period saved.');
    }
}; ?>

<div class="chief-panel bg-white flex flex-col h-full min-h-0">
    <x-action-bar title="Unclassified Acquirer — Stamp Inventory (R1–R6)" />
    @if (session('status'))
        <div class="mx-2 mt-1 border border-sky-400 bg-sky-50 px-2 py-1 text-xs">{{ session('status') }}</div>
    @endif
    <div class="p-3 grid grid-cols-1 lg:grid-cols-2 gap-4 flex-1 overflow-auto">
        <form wire:submit="save" class="space-y-2 border border-slate-300 p-3 bg-slate-50">
            <div class="flex gap-2">
                <div class="chief-field"><label>Period Start</label><input type="date" wire:model="period_start" class="chief-input" /></div>
                <div class="chief-field"><label>Period End</label><input type="date" wire:model="period_end" class="chief-input" /></div>
            </div>
            <div class="chief-field"><label>R1 Beginning Unaffixed</label><input wire:model="r1_beginning_unaffixed" class="chief-input w-40 text-right" /></div>
            <div class="chief-field"><label>R2 Beginning Affixed</label><input wire:model="r2_beginning_affixed" class="chief-input w-40 text-right" /></div>
            <div class="chief-field"><label>R3 Purchased</label><input wire:model="r3_purchased" class="chief-input w-40 text-right" /></div>
            <div class="chief-field"><label>R4 Affixed</label><input wire:model="r4_affixed" class="chief-input w-40 text-right" /></div>
            <div class="chief-field"><label>R5 Ending Unaffixed</label><input wire:model="r5_ending_unaffixed" class="chief-input w-40 text-right" /></div>
            <div class="chief-field"><label>R6 Ending Affixed</label><input wire:model="r6_ending_affixed" class="chief-input w-40 text-right" /></div>
            <div class="chief-field"><label>Notes</label><textarea wire:model="notes" rows="3" class="chief-input w-full"></textarea></div>
            <button type="submit" class="chief-btn-primary">Save Period</button>
        </form>
        <div class="chief-grid border border-slate-300 overflow-auto">
            <table>
                <thead>
                    <tr><th>Period</th><th class="text-right">R1</th><th class="text-right">R2</th><th class="text-right">R5</th><th class="text-right">R6</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ optional($row->period_start)?->format('n/j/Y') }} – {{ optional($row->period_end)?->format('n/j/Y') }}</td>
                            <td class="text-right">{{ number_format($row->r1_beginning_unaffixed, 2) }}</td>
                            <td class="text-right">{{ number_format($row->r2_beginning_affixed, 2) }}</td>
                            <td class="text-right">{{ number_format($row->r5_ending_unaffixed, 2) }}</td>
                            <td class="text-right">{{ number_format($row->r6_ending_affixed, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-500 px-2 py-4">No stamp inventory periods yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
