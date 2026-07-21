<?php

use App\Models\Item;
use App\Models\Site;
use App\Models\StockCount;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Stock Count')] class extends Component
{
    public ?StockCount $stockCount = null;

    public string $activeTab = 'general';

    public string $stock_count_no = '';

    public string $date_created = '';

    public string $status = 'New';

    public ?string $last_count_date = null;

    public ?string $date_processed = null;

    public ?int $site_id = null;

    public string $description = '';

    public bool $shared_count = false;

    public string $comments = '';

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,in_stock:string,allocated:string,counted:string,count_time:?string}> */
    public array $lines = [];

    public function mount(?StockCount $stockCount = null): void
    {
        $companyId = auth()->user()->company_id;

        if ($stockCount?->exists) {
            abort_unless($stockCount->company_id === $companyId, 403);
            $this->stockCount = $stockCount->load('lines');
            $this->fill($stockCount->only([
                'stock_count_no', 'status', 'site_id', 'description', 'shared_count', 'comments',
            ]));
            $this->date_created = optional($stockCount->date_created)?->format('Y-m-d') ?? '';
            $this->last_count_date = optional($stockCount->last_count_date)?->format('Y-m-d');
            $this->date_processed = optional($stockCount->date_processed)?->format('Y-m-d');
            $this->lines = $stockCount->lines->map(fn ($l) => [
                'item_id' => $l->item_id,
                'item_code' => $l->item_code ?? '',
                'description' => $l->description ?? '',
                'uom' => $l->uom ?? '',
                'in_stock' => (string) $l->in_stock,
                'allocated' => (string) $l->allocated,
                'counted' => $l->counted !== null ? (string) $l->counted : '',
                'count_time' => optional($l->count_time)?->format('Y-m-d H:i:s'),
            ])->all();
        } else {
            $this->stock_count_no = StockCount::nextNumber($companyId);
            $this->date_created = now()->toDateString();
            $this->site_id = auth()->user()->site_id;
            $prev = StockCount::query()
                ->where('company_id', $companyId)
                ->where('status', 'Processed')
                ->orderByDesc('date_processed')
                ->value('date_processed');
            $this->last_count_date = optional($prev)?->format('Y-m-d');
        }

        if ($this->lines === []) {
            $this->lines[] = $this->emptyLine();
        }
    }

    protected function emptyLine(): array
    {
        return [
            'item_id' => null,
            'item_code' => '',
            'description' => '',
            'uom' => '',
            'in_stock' => '0',
            'allocated' => '0',
            'counted' => '',
            'count_time' => null,
        ];
    }

    public function with(): array
    {
        return [
            'sites' => Site::query()->where('company_id', auth()->user()->company_id)->orderBy('code')->get(),
            'tabs' => [
                'general' => 'General',
                'expand' => 'Expand',
                'comments' => 'Comments',
            ],
            'totalItemsCounted' => collect($this->lines)->filter(fn ($l) => filled($l['counted'] ?? null))->count(),
            'totalQtyCounted' => collect($this->lines)->sum(fn ($l) => (float) ($l['counted'] ?: 0)),
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function lookupItem(int $index): void
    {
        $code = trim($this->lines[$index]['item_code'] ?? '');
        if ($code === '') {
            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where(function ($q) use ($code) {
                $q->where('item_code', $code)->orWhere('primary_upc', $code);
            })
            ->first();

        if (! $item) {
            return;
        }

        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['item_code'] = $item->item_code;
        $this->lines[$index]['description'] = $item->description ?? '';
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $this->lines[$index]['in_stock'] = (string) $item->quantity_in_stock;
        $this->lines[$index]['allocated'] = (string) $item->allocated_qty;
    }

    public function updatedLines($value, $key): void
    {
        if (str_ends_with($key, '.counted') && filled($value)) {
            $index = (int) explode('.', $key)[0];
            $this->lines[$index]['count_time'] = now()->format('Y-m-d H:i:s');
        }
    }

    public function save(bool $redirect = true): void
    {
        $this->validate([
            'stock_count_no' => 'required|string|max:64',
            'site_id' => 'nullable|integer',
        ]);

        $data = [
            'company_id' => auth()->user()->company_id,
            'stock_count_no' => $this->stock_count_no,
            'date_created' => $this->date_created ?: null,
            'status' => $this->status,
            'last_count_date' => $this->last_count_date ?: null,
            'date_processed' => $this->date_processed ?: null,
            'site_id' => $this->site_id ?: null,
            'description' => $this->description,
            'shared_count' => $this->shared_count,
            'comments' => $this->comments,
        ];

        DB::transaction(function () use ($data) {
            if ($this->stockCount) {
                $this->stockCount->update($data);
                $count = $this->stockCount->fresh();
                $count->lines()->delete();
            } else {
                $count = StockCount::query()->create($data);
            }

            foreach (array_values($this->lines) as $i => $line) {
                if (! filled($line['item_code'] ?? null)) {
                    continue;
                }
                $count->lines()->create([
                    'item_id' => $line['item_id'] ?: null,
                    'item_code' => $line['item_code'],
                    'description' => $line['description'] ?: null,
                    'uom' => $line['uom'] ?: null,
                    'in_stock' => $line['in_stock'] ?: 0,
                    'allocated' => $line['allocated'] ?: 0,
                    'counted' => filled($line['counted'] ?? null) ? $line['counted'] : null,
                    'count_time' => $line['count_time'] ?: null,
                    'line_no' => $i + 1,
                ]);
            }

            $this->stockCount = $count->fresh('lines');
        });

        if ($redirect) {
            $this->redirect(route('inventory.stock-counts.index'), navigate: true);
        }
    }

    public function process(): void
    {
        $this->save(false);
        app(InventoryService::class)->processStockCount($this->stockCount->fresh('lines'));
        $this->redirect(route('inventory.stock-counts.index'), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="save" class="chief-panel bg-white flex flex-col min-h-[72vh]">
        <x-action-bar :title="$stockCount ? 'Stock Count '.$stock_count_no : 'New Stock Count'" variant="green" />

        <div class="flex-1 p-3 overflow-auto">
            @if ($activeTab === 'general')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 max-w-4xl">
                    <div class="space-y-1">
                        <div class="chief-field"><label>Stock Count No.</label><input wire:model="stock_count_no" class="chief-input w-48 font-mono" @disabled($stockCount) /></div>
                        <div class="chief-field"><label>Date Created</label><input type="date" wire:model="date_created" class="chief-input" /></div>
                        <div class="chief-field"><label>Count Status</label><input wire:model="status" class="chief-input w-40 bg-slate-50" readonly /></div>
                        <div class="chief-field"><label>Last Count Date</label><input type="date" wire:model="last_count_date" class="chief-input bg-slate-50" readonly /></div>
                        <div class="chief-field"><label>Date Processed</label><input type="date" wire:model="date_processed" class="chief-input bg-slate-50" readonly /></div>
                    </div>
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>Site</label>
                            <select wire:model="site_id" class="chief-input w-40">
                                <option value="">—</option>
                                @foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->code }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field chief-field-top"><label>Description</label><textarea wire:model="description" rows="3" class="chief-input w-full max-w-md"></textarea></div>
                        <label class="inline-flex items-center gap-2 text-sm ms-[9.5rem]"><input type="checkbox" wire:model="shared_count" /> Shared Count</label>
                    </div>
                </div>
            @elseif ($activeTab === 'expand')
                <div class="flex justify-between mb-2">
                    <p class="text-xs text-slate-600">Enter item code or UPC — barcode scan supported</p>
                    <button type="button" wire:click="addLine" class="chief-btn text-xs">Add Item</button>
                </div>
                <div class="chief-grid border border-slate-300 overflow-auto mb-3">
                    <table>
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>U of M</th>
                                <th class="text-right">In Stock</th>
                                <th class="text-right">Allocated</th>
                                <th class="text-right">Counted</th>
                                <th class="text-right">Variance</th>
                                <th>Count Time</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
                                <tr>
                                    <td>
                                        <div class="flex gap-1">
                                            <input wire:model.blur="lines.{{ $i }}.item_code" wire:keydown.enter.prevent="lookupItem({{ $i }})" class="chief-input w-28 font-mono" @disabled($status === 'Processed') />
                                            <button type="button" wire:click="lookupItem({{ $i }})" class="chief-btn text-xs px-1" @disabled($status === 'Processed')>…</button>
                                        </div>
                                    </td>
                                    <td>{{ $line['description'] }}</td>
                                    <td>{{ $line['uom'] }}</td>
                                    <td class="text-right">{{ number_format((float) $line['in_stock'], 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $line['allocated'], 2) }}</td>
                                    <td><input wire:model.live="lines.{{ $i }}.counted" class="chief-input w-24 text-right" @disabled($status === 'Processed') /></td>
                                    <td class="text-right @if(filled($line['counted']) && (float)$line['counted'] != (float)$line['in_stock']) text-red-700 font-semibold @endif">
                                        @if (filled($line['counted']))
                                            {{ number_format((float) $line['counted'] - (float) $line['in_stock'], 2) }}
                                        @endif
                                    </td>
                                    <td class="text-xs">{{ $line['count_time'] }}</td>
                                    <td><button type="button" wire:click="removeLine({{ $i }})" class="text-red-700 text-xs" @disabled($status === 'Processed')>−</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-sm space-x-6">
                    <span>Total Items Counted: <strong>{{ $totalItemsCounted }}</strong></span>
                    <span>Total Quantity Counted: <strong>{{ number_format($totalQtyCounted, 2) }}</strong></span>
                </div>
            @else
                <div class="max-w-2xl">
                    <label class="block text-xs font-medium mb-1">Comments & Notes</label>
                    <textarea wire:model="comments" rows="10" class="chief-input w-full"></textarea>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between border-t border-slate-300 bg-slate-100 px-1">
            <div class="flex">
                @foreach ($tabs as $key => $label)
                    <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                        @class(['px-3 py-1.5 text-sm border-r border-slate-300', 'bg-white font-semibold text-sky-800' => $activeTab === $key])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex gap-2 py-2 pe-2">
                <a href="{{ route('inventory.stock-counts.index') }}" wire:navigate class="chief-btn">Cancel</a>
                @if ($status !== 'Processed')
                    <button type="submit" class="chief-btn">Save</button>
                    <button type="button" wire:click="process" wire:confirm="Process stock count and update inventory?" class="chief-btn-primary">Process</button>
                @endif
            </div>
        </div>
    </form>
</div>
