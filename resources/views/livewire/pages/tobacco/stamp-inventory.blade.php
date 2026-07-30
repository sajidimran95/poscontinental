<?php

use App\Models\TobaccoStampInventory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Stamp Inventory')] class extends Component
{
    public string $period_start = '';

    public string $period_end = '';

    public string $notes = '';

    public string $statusMessage = '';

    /** @var array<string, string> */
    public array $beginning_unaffixed = [];

    /** @var array<string, string> */
    public array $ending_unaffixed = [];

    /** @var array<string, string> */
    public array $beginning_affixed = [];

    /** @var array<string, string> */
    public array $ending_affixed = [];

    public function mount(): void
    {
        $this->period_start = now()->startOfMonth()->toDateString();
        $this->period_end = now()->endOfMonth()->toDateString();
        $this->resetMatrix();
    }

    public function with(): array
    {
        return [
            'stampTypes' => [
                'r1' => 'R1 — Standard 20 (30,000)',
                'r2' => 'R2 — Standard 20 (1,500)',
                'r3' => 'R3 — Standard 25 (1,500)',
                'r4' => 'R4 — Tribal 20 (30,000)',
                'r5' => 'R5 — Tribal 20 (1,500)',
                'r6' => 'R6 — Tribal 25 (1,500)',
            ],
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
        $this->normalizeMatrix();

        $this->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string|max:2000',
            'beginning_unaffixed.*' => 'nullable|integer|min:0',
            'ending_unaffixed.*' => 'nullable|integer|min:0',
            'beginning_affixed.*' => 'nullable|integer|min:0',
            'ending_affixed.*' => 'nullable|integer|min:0',
        ]);

        $data = [
            'company_id' => auth()->user()->company_id,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'created_by' => auth()->id(),
            // Keep legacy totals for older reports/views.
            'r1_beginning_unaffixed' => (int) ($this->beginning_unaffixed['r1'] ?? 0),
            'r2_beginning_affixed' => (int) ($this->beginning_affixed['r1'] ?? 0),
            'r3_purchased' => 0,
            'r4_affixed' => 0,
            'r5_ending_unaffixed' => (int) ($this->ending_unaffixed['r1'] ?? 0),
            'r6_ending_affixed' => (int) ($this->ending_affixed['r1'] ?? 0),
        ];

        foreach (TobaccoStampInventory::STAMP_TYPES as $r) {
            $data["beginning_unaffixed_{$r}"] = (int) ($this->beginning_unaffixed[$r] ?? 0);
            $data["ending_unaffixed_{$r}"] = (int) ($this->ending_unaffixed[$r] ?? 0);
            $data["beginning_affixed_{$r}"] = (int) ($this->beginning_affixed[$r] ?? 0);
            $data["ending_affixed_{$r}"] = (int) ($this->ending_affixed[$r] ?? 0);
        }

        TobaccoStampInventory::query()->create($data);

        $this->resetMatrix();
        $this->notes = '';
        $this->statusMessage = 'Stamp inventory period saved.';
    }

    public function resetMatrix(): void
    {
        foreach (TobaccoStampInventory::STAMP_TYPES as $r) {
            $this->beginning_unaffixed[$r] = '0';
            $this->ending_unaffixed[$r] = '0';
            $this->beginning_affixed[$r] = '0';
            $this->ending_affixed[$r] = '0';
        }
    }

    protected function normalizeMatrix(): void
    {
        foreach (['beginning_unaffixed', 'ending_unaffixed', 'beginning_affixed', 'ending_affixed'] as $group) {
            foreach (TobaccoStampInventory::STAMP_TYPES as $r) {
                $raw = trim((string) ($this->{$group}[$r] ?? ''));
                $raw = str_replace([',', ' '], '', $raw);
                // Blank / cleared cells save as 0
                $this->{$group}[$r] = ($raw === '' || ! is_numeric($raw)) ? '0' : (string) max(0, (int) $raw);
            }
        }
    }
}; ?>

<div class="stamp-inv-page">
    <x-action-bar title="UA Cigarette — Stamp Inventory (Affixed / Unaffixed R1–R6)" />

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
        <form wire:submit.prevent="save" class="stamp-inv-form" autocomplete="off">
            <p class="stamp-inv-hint">Leave any stamp cell blank or <strong>0</strong> — both save as zero. Only period dates are required.</p>

            <div class="stamp-inv-dates">
                <label class="stamp-inv-field">
                    <span>Period Start <em>*</em></span>
                    <input id="stamp-period-start" type="date" wire:model="period_start" class="desk-input" required />
                </label>
                <label class="stamp-inv-field">
                    <span>Period End <em>*</em></span>
                    <input id="stamp-period-end" type="date" wire:model="period_end" class="desk-input" required />
                </label>
            </div>

            <div class="stamp-inv-table-wrap">
                <table class="stamp-inv-table">
                    <colgroup>
                        <col class="stamp-inv-col-type" />
                        <col class="stamp-inv-col-num" />
                        <col class="stamp-inv-col-num" />
                        <col class="stamp-inv-col-num" />
                        <col class="stamp-inv-col-num" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Stamp Type</th>
                            <th scope="col">Begin Unaffixed</th>
                            <th scope="col">End Unaffixed</th>
                            <th scope="col">Begin Affixed</th>
                            <th scope="col">End Affixed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stampTypes as $key => $label)
                            <tr>
                                <th scope="row">{{ $label }}</th>
                                <td><input type="text" inputmode="numeric" placeholder="0" wire:model="beginning_unaffixed.{{ $key }}" /></td>
                                <td><input type="text" inputmode="numeric" placeholder="0" wire:model="ending_unaffixed.{{ $key }}" /></td>
                                <td><input type="text" inputmode="numeric" placeholder="0" wire:model="beginning_affixed.{{ $key }}" /></td>
                                <td><input type="text" inputmode="numeric" placeholder="0" wire:model="ending_affixed.{{ $key }}" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <label class="stamp-inv-field stamp-inv-notes">
                <span>Notes <small>(optional)</small></span>
                <textarea id="stamp-notes" wire:model="notes" rows="2" placeholder="Optional notes (not exported)"></textarea>
            </label>

            <div class="stamp-inv-actions">
                <button type="submit" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save Period</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
                <button type="button" wire:click="resetMatrix" class="desk-btn">Reset to 0</button>
                <a href="{{ route('tobacco.filing') }}" wire:navigate class="desk-btn">Back to MSA Report</a>
            </div>
        </form>

        <section class="stamp-inv-saved" aria-label="Saved periods">
            <h3>Saved periods</h3>
            <div class="stamp-inv-saved-wrap">
                <table class="stamp-inv-saved-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Begin Unaffixed R1–R6</th>
                            <th>End Unaffixed R1–R6</th>
                            <th>Begin Affixed R1–R6</th>
                            <th>End Affixed R1–R6</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $m = $row->matrix(); @endphp
                            <tr>
                                <td>{{ optional($row->period_start)?->format('n/j/Y') }} – {{ optional($row->period_end)?->format('n/j/Y') }}</td>
                                <td>{{ implode(' / ', $m['beginning_unaffixed']) }}</td>
                                <td>{{ implode(' / ', $m['ending_unaffixed']) }}</td>
                                <td>{{ implode(' / ', $m['beginning_affixed']) }}</td>
                                <td>{{ implode(' / ', $m['ending_affixed']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="stamp-inv-empty">No stamp periods saved yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
