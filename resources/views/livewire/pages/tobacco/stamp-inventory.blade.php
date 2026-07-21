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

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->period_start = now()->startOfMonth()->toDateString();
        $this->period_end = now()->endOfMonth()->toDateString();
        $this->resetNumericFields();
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
        $this->statusMessage = '';
        $this->normalizeNumericFields();

        $this->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'r1_beginning_unaffixed' => 'required|numeric|min:0',
            'r2_beginning_affixed' => 'required|numeric|min:0',
            'r3_purchased' => 'required|numeric|min:0',
            'r4_affixed' => 'required|numeric|min:0',
            'r5_ending_unaffixed' => 'required|numeric|min:0',
            'r6_ending_affixed' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        TobaccoStampInventory::query()->create([
            'company_id' => auth()->user()->company_id,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'r1_beginning_unaffixed' => (float) $this->r1_beginning_unaffixed,
            'r2_beginning_affixed' => (float) $this->r2_beginning_affixed,
            'r3_purchased' => (float) $this->r3_purchased,
            'r4_affixed' => (float) $this->r4_affixed,
            'r5_ending_unaffixed' => (float) $this->r5_ending_unaffixed,
            'r6_ending_affixed' => (float) $this->r6_ending_affixed,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'created_by' => auth()->id(),
        ]);

        $this->resetNumericFields();
        $this->notes = '';
        $this->statusMessage = 'Stamp inventory period saved.';
    }

    public function resetNumericFields(): void
    {
        $this->r1_beginning_unaffixed = '0';
        $this->r2_beginning_affixed = '0';
        $this->r3_purchased = '0';
        $this->r4_affixed = '0';
        $this->r5_ending_unaffixed = '0';
        $this->r6_ending_affixed = '0';
    }

    protected function normalizeNumericFields(): void
    {
        foreach ([
            'r1_beginning_unaffixed',
            'r2_beginning_affixed',
            'r3_purchased',
            'r4_affixed',
            'r5_ending_unaffixed',
            'r6_ending_affixed',
        ] as $field) {
            $raw = trim((string) $this->{$field});
            $raw = str_replace([',', ' '], '', $raw);
            if ($raw === '') {
                $this->{$field} = '0';

                continue;
            }
            if (is_numeric($raw)) {
                $this->{$field} = (string) (0 + $raw);
            }
        }
    }
}; ?>

<div class="chief-panel bg-white flex flex-col h-full min-h-0">
    <x-action-bar title="Unclassified Acquirer — Stamp Inventory (R1–R6)" />

    @if ($statusMessage !== '')
        <div class="mx-2 mt-1 border border-emerald-500 bg-emerald-50 px-2 py-1 text-xs text-emerald-900" role="status">{{ $statusMessage }}</div>
    @endif

    @if ($errors->any())
        <div class="mx-2 mt-1 border border-red-500 bg-red-50 px-2 py-1 text-xs text-red-900" role="alert">
            <strong>Could not save:</strong>
            <ul class="list-disc ms-4 mt-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="p-3 grid grid-cols-1 lg:grid-cols-2 gap-4 flex-1 overflow-auto">
        <form wire:submit.prevent="save" class="space-y-2 border border-slate-300 p-3 bg-slate-50" autocomplete="off">
            <div class="flex flex-wrap gap-2">
                <div class="chief-field">
                    <label for="stamp-period-start">Period Start</label>
                    <input id="stamp-period-start" type="date" wire:model="period_start" class="chief-input" />
                </div>
                <div class="chief-field">
                    <label for="stamp-period-end">Period End</label>
                    <input id="stamp-period-end" type="date" wire:model="period_end" class="chief-input" />
                </div>
            </div>

            @foreach ([
                'r1_beginning_unaffixed' => 'R1 Beginning Unaffixed',
                'r2_beginning_affixed' => 'R2 Beginning Affixed',
                'r3_purchased' => 'R3 Purchased',
                'r4_affixed' => 'R4 Affixed',
                'r5_ending_unaffixed' => 'R5 Ending Unaffixed',
                'r6_ending_affixed' => 'R6 Ending Affixed',
            ] as $field => $label)
                <div class="chief-field">
                    <label for="stamp-{{ $field }}">{{ $label }}</label>
                    <input
                        id="stamp-{{ $field }}"
                        type="text"
                        inputmode="decimal"
                        wire:model="{{ $field }}"
                        class="chief-input w-40 text-right @error($field) border-red-500 @enderror"
                    />
                </div>
            @endforeach

            <div class="chief-field">
                <label for="stamp-notes">Notes</label>
                <textarea id="stamp-notes" wire:model="notes" rows="3" class="chief-input w-full" placeholder="Optional notes (text goes here, not in R1–R6)"></textarea>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="submit" class="chief-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save Period</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
                <button type="button" wire:click="resetNumericFields" class="chief-btn">Reset R1–R6</button>
            </div>
        </form>

        <div class="chief-grid border border-slate-300 overflow-auto">
            <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-slate-100">Saved periods</div>
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th class="text-right">R1</th>
                        <th class="text-right">R2</th>
                        <th class="text-right">R3</th>
                        <th class="text-right">R4</th>
                        <th class="text-right">R5</th>
                        <th class="text-right">R6</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="stamp-{{ $row->id }}">
                            <td>{{ optional($row->period_start)?->format('n/j/Y') }} – {{ optional($row->period_end)?->format('n/j/Y') }}</td>
                            <td class="text-right">{{ number_format((float) $row->r1_beginning_unaffixed, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->r2_beginning_affixed, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->r3_purchased, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->r4_affixed, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->r5_ending_unaffixed, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->r6_ending_affixed, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-slate-500 px-2 py-4">No stamp inventory periods yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
