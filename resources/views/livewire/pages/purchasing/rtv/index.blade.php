<?php

use App\Models\Item;
use App\Models\ReturnToVendor;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Return to Vendor')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $favorite = 'all';

    public ?int $selectedId = null;

    public bool $showForm = false;

    public ?ReturnToVendor $rtv = null;

    public string $rtv_number = '';

    public string $rtv_date = '';

    public string $status = 'New';

    public string $reference_no = '';

    public ?int $supplier_id = null;

    public ?int $requested_by_id = null;

    public ?int $site_id = null;

    public string $comments = '';

    public string $discount = '0';

    public string $freight = '0';

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty:string,unit_cost:string}> */
    public array $lines = [];

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'records' => ReturnToVendor::query()
                ->with('supplier')
                ->where('company_id', $companyId)
                ->when($this->search !== '', fn ($q) => $q->where('rtv_number', 'like', '%'.$this->search.'%'))
                ->orderByDesc('id')
                ->paginate(50),
            'suppliers' => Supplier::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'users' => User::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'favorites' => ['all' => 'All RTVs'],
            'subtotal' => collect($this->lines)->sum(fn ($l) => (float) $l['qty'] * (float) $l['unit_cost']),
        ];
    }

    public function startNew(): void
    {
        $companyId = auth()->user()->company_id;
        $this->showForm = true;
        $this->rtv = null;
        $this->rtv_number = ReturnToVendor::nextNumber($companyId);
        $this->rtv_date = now()->toDateString();
        $this->status = 'New';
        $this->reference_no = '';
        $this->supplier_id = null;
        $this->requested_by_id = auth()->id();
        $this->site_id = auth()->user()->site_id;
        $this->comments = '';
        $this->discount = '0';
        $this->freight = '0';
        $this->lines = [[
            'item_id' => null, 'item_code' => '', 'description' => '', 'uom' => '', 'qty' => '1', 'unit_cost' => '0',
        ]];
    }

    public function edit(int $id): void
    {
        $rtv = ReturnToVendor::query()->with('lines')->findOrFail($id);
        abort_unless($rtv->company_id === auth()->user()->company_id, 403);
        $this->rtv = $rtv;
        $this->showForm = true;
        $this->rtv_number = $rtv->rtv_number;
        $this->rtv_date = optional($rtv->rtv_date)?->format('Y-m-d') ?? '';
        $this->status = $rtv->status;
        $this->reference_no = $rtv->reference_no ?? '';
        $this->supplier_id = $rtv->supplier_id;
        $this->requested_by_id = $rtv->requested_by_id;
        $this->site_id = $rtv->site_id;
        $this->comments = $rtv->comments ?? '';
        $this->discount = (string) $rtv->discount;
        $this->freight = (string) $rtv->freight;
        $this->lines = $rtv->lines->map(fn ($l) => [
            'item_id' => $l->item_id,
            'item_code' => $l->item_code ?? '',
            'description' => $l->description ?? '',
            'uom' => $l->uom ?? '',
            'qty' => (string) $l->qty,
            'unit_cost' => (string) $l->unit_cost,
        ])->all();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id' => null, 'item_code' => '', 'description' => '', 'uom' => '', 'qty' => '1', 'unit_cost' => '0',
        ];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function lookupItem(int $index): void
    {
        $code = trim($this->lines[$index]['item_code'] ?? '');
        if ($code === '') {
            return;
        }
        $item = Item::query()->where('company_id', auth()->user()->company_id)->where('item_code', $code)->first();
        if (! $item) {
            return;
        }
        $this->lines[$index]['item_id'] = $item->id;
        $this->lines[$index]['description'] = $item->description ?? '';
        $this->lines[$index]['uom'] = $item->unit_of_measure ?? '';
        $this->lines[$index]['unit_cost'] = (string) $item->current_cost;
    }

    public function save(): void
    {
        $this->validate([
            'rtv_number' => 'required',
            'supplier_id' => 'required|integer|exists:suppliers,id',
        ]);

        $subtotal = collect($this->lines)->sum(fn ($l) => (float) $l['qty'] * (float) $l['unit_cost']);
        $total = $subtotal - (float) $this->discount + (float) $this->freight;

        $data = [
            'company_id' => auth()->user()->company_id,
            'rtv_number' => $this->rtv_number,
            'rtv_date' => $this->rtv_date ?: null,
            'status' => $this->status,
            'reference_no' => $this->reference_no,
            'supplier_id' => $this->supplier_id,
            'requested_by_id' => $this->requested_by_id,
            'site_id' => $this->site_id,
            'comments' => $this->comments,
            'subtotal' => $subtotal,
            'discount' => $this->discount,
            'freight' => $this->freight,
            'total' => $total,
        ];

        DB::transaction(function () use ($data) {
            if ($this->rtv) {
                $this->rtv->update($data);
                $rtv = $this->rtv->fresh();
                $rtv->lines()->delete();
            } else {
                $rtv = ReturnToVendor::query()->create($data);
            }

            foreach (array_values($this->lines) as $i => $line) {
                if (! filled($line['item_code'] ?? null)) {
                    continue;
                }
                $qty = (float) $line['qty'];
                $cost = (float) $line['unit_cost'];
                $rtv->lines()->create([
                    'item_id' => $line['item_id'] ?: null,
                    'item_code' => $line['item_code'],
                    'description' => $line['description'] ?: null,
                    'uom' => $line['uom'] ?: null,
                    'qty' => $qty,
                    'unit_cost' => $cost,
                    'extended_cost' => $qty * $cost,
                    'line_no' => $i + 1,
                ]);
            }

            $this->rtv = $rtv->fresh('lines');
        });

        $this->showForm = false;
        session()->flash('status', 'RTV saved.');
    }

    public function process(int $id): void
    {
        $rtv = ReturnToVendor::query()->findOrFail($id);
        abort_unless($rtv->company_id === auth()->user()->company_id, 403);
        app(InventoryService::class)->processRtv($rtv);
        session()->flash('status', 'RTV processed — stock decremented.');
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />

        @if ($showForm)
            <form wire:submit="save" class="flex-1 flex flex-col">
                <div class="p-3 space-y-3 flex-1 overflow-auto">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8">
                        <div class="space-y-1">
                            <div class="chief-field"><label>RTV Number</label><input wire:model="rtv_number" class="chief-input w-40 font-mono" @disabled($rtv) /></div>
                            <div class="chief-field"><label>RTV Date</label><input type="date" wire:model="rtv_date" class="chief-input" /></div>
                            <div class="chief-field"><label>Status</label><input wire:model="status" class="chief-input w-32 bg-slate-50" readonly /></div>
                            <div class="chief-field"><label>Reference No.</label><input wire:model="reference_no" class="chief-input w-40" /></div>
                        </div>
                        <div class="space-y-1">
                            <div class="chief-field">
                                <label>Supplier</label>
                                <select wire:model="supplier_id" class="chief-input w-64">
                                    <option value="">—</option>
                                    @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="chief-field">
                                <label>Requested By</label>
                                <select wire:model="requested_by_id" class="chief-input w-56">
                                    <option value="">—</option>
                                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="chief-field">
                                <label>Site</label>
                                <select wire:model="site_id" class="chief-input w-40">
                                    <option value="">—</option>
                                    @foreach ($sites as $site)<option value="{{ $site->id }}">{{ $site->code }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mb-1">
                        <button type="button" wire:click="addLine" class="chief-btn text-xs">Add Line</button>
                    </div>
                    <div class="chief-grid border border-slate-300">
                        <table>
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th>U of M</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Cost</th>
                                    <th class="text-right">Extended</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $i => $line)
                                    <tr>
                                        <td>
                                            <div class="flex gap-1">
                                                <input wire:model="lines.{{ $i }}.item_code" class="chief-input w-28 font-mono" />
                                                <button type="button" wire:click="lookupItem({{ $i }})" class="chief-btn text-xs px-1">…</button>
                                            </div>
                                        </td>
                                        <td><input wire:model="lines.{{ $i }}.description" class="chief-input w-full" /></td>
                                        <td><input wire:model="lines.{{ $i }}.uom" class="chief-input w-16" /></td>
                                        <td><input wire:model.live="lines.{{ $i }}.qty" class="chief-input w-20 text-right" /></td>
                                        <td><input wire:model.live="lines.{{ $i }}.unit_cost" class="chief-input w-24 text-right" /></td>
                                        <td class="text-right">${{ number_format((float)$line['qty'] * (float)$line['unit_cost'], 2) }}</td>
                                        <td><button type="button" wire:click="removeLine({{ $i }})" class="text-red-700 text-xs">−</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-6 text-sm">
                        <div>Subtotal: <strong>${{ number_format($subtotal, 2) }}</strong></div>
                        <div class="chief-field"><label>Discount</label><input wire:model.live="discount" class="chief-input w-24 text-right" /></div>
                        <div class="chief-field"><label>Freight</label><input wire:model.live="freight" class="chief-input w-24 text-right" /></div>
                        <div>Total: <strong>${{ number_format($subtotal - (float)$discount + (float)$freight, 2) }}</strong></div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-3 py-2 border-t border-slate-300 bg-slate-100">
                    <button type="button" wire:click="cancelForm" class="chief-btn">Cancel</button>
                    <button type="submit" class="chief-btn-primary">Save RTV</button>
                </div>
            </form>
        @else
            <x-list-chrome label="Search RTVs:" model="search" />
            <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Return To Vendor (RTVs) List</div>
            <div class="chief-grid flex-1 overflow-auto">
                <table>
                    <thead>
                        <tr>
                            <th>RTV Number</th>
                            <th>RTV Date</th>
                            <th>Status</th>
                            <th>Supplier</th>
                            <th class="text-right">RTV Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $rec)
                            <tr>
                                <td class="font-mono">
                                    <button type="button" wire:click="edit({{ $rec->id }})" class="hover:underline">{{ $rec->rtv_number }}</button>
                                </td>
                                <td>{{ optional($rec->rtv_date)?->format('n/j/Y') }}</td>
                                <td>{{ $rec->status }}</td>
                                <td>{{ $rec->supplier?->name }}</td>
                                <td class="text-right">${{ number_format($rec->total, 2) }}</td>
                                <td>
                                    @if ($rec->status !== 'Returned')
                                        <button type="button" wire:click="process({{ $rec->id }})" wire:confirm="Process RTV and decrement stock?" class="chief-btn text-xs">Process</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-2 py-6 text-slate-500">No RTVs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-record-count :count="$records->total()">
                <button type="button" wire:click="startNew" class="chief-btn-primary">New RTV</button>
                {{ $records->links() }}
            </x-record-count>
        @endif
    </div>
</div>
