<?php

use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Credit Memos')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $favorite = 'all';

    public bool $showForm = false;

    public string $memo_number = '';

    public string $memo_date = '';

    public ?int $customer_id = null;

    public string $comments = '';

    /** @var array<int, array{item_code:string,description:string,uom:string,qty:string,price:string}> */
    public array $lines = [];

    public ?int $emailMemoId = null;

    public string $emailTo = '';

    public string $emailSubject = '';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'memos' => CreditMemo::query()
                ->with('customer')
                ->where('company_id', $companyId)
                ->when($this->search !== '', fn ($q) => $q->where('memo_number', 'like', '%'.$this->search.'%'))
                ->orderByDesc('id')
                ->paginate(50),
            'customers' => Customer::query()->where('company_id', $companyId)->orderBy('company_name')->get(),
            'favorites' => ['all' => 'All Credit Memos'],
            'emailMemo' => $this->emailMemoId
                ? CreditMemo::query()->with('customer')->find($this->emailMemoId)
                : null,
            'lineTotal' => collect($this->lines)->sum(function ($l) {
                return ((float) ($l['qty'] ?? 0)) * ((float) ($l['price'] ?? 0));
            }),
        ];
    }

    public function startNew(): void
    {
        $this->showForm = true;
        $this->memo_number = CreditMemo::nextNumber(auth()->user()->company_id);
        $this->memo_date = now()->toDateString();
        $this->customer_id = null;
        $this->comments = '';
        $this->lines = [
            ['item_code' => '', 'description' => '', 'uom' => '', 'qty' => '1', 'price' => '0'],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_code' => '', 'description' => '', 'uom' => '', 'qty' => '1', 'price' => '0'];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines) ?: [
            ['item_code' => '', 'description' => '', 'uom' => '', 'qty' => '1', 'price' => '0'],
        ];
    }

    public function lookupLineItem(int $index): void
    {
        $code = trim($this->lines[$index]['item_code'] ?? '');
        if ($code === '') {
            return;
        }
        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('item_code', $code)
            ->first();
        if (! $item) {
            return;
        }
        $this->lines[$index]['description'] = (string) $item->description;
        $this->lines[$index]['uom'] = (string) ($item->unit_of_measure ?? '');
        $this->lines[$index]['price'] = (string) $item->list_price;
    }

    public function openEmail(int $id): void
    {
        $memo = CreditMemo::query()->with('customer')->findOrFail($id);
        abort_unless($memo->company_id === auth()->user()->company_id, 403);
        $this->emailMemoId = $memo->id;
        $this->emailTo = $memo->customer?->email ?? '';
        $this->emailSubject = 'Credit Memo '.$memo->memo_number;
    }

    public function closeEmail(): void
    {
        $this->emailMemoId = null;
    }

    public function save(): void
    {
        $this->validate([
            'memo_number' => 'required',
            'customer_id' => 'required|exists:customers,id',
            'lines' => 'required|array|min:1',
            'lines.*.item_code' => 'required|string',
            'lines.*.qty' => 'required|numeric|min:0.0001',
            'lines.*.price' => 'required|numeric|min:0',
        ]);

        $amount = collect($this->lines)->sum(fn ($l) => ((float) $l['qty']) * ((float) $l['price']));
        if ($amount < 0.01) {
            $this->addError('lines', 'Credit memo total must be greater than zero.');

            return;
        }

        DB::transaction(function () use ($amount) {
            $memo = CreditMemo::query()->create([
                'company_id' => auth()->user()->company_id,
                'memo_number' => $this->memo_number,
                'memo_date' => $this->memo_date,
                'customer_id' => $this->customer_id,
                'amount' => $amount,
                'status' => 'Open',
                'comments' => $this->comments,
            ]);

            foreach (array_values($this->lines) as $i => $line) {
                $item = Item::query()
                    ->where('company_id', auth()->user()->company_id)
                    ->where('item_code', $line['item_code'])
                    ->first();
                $qty = (float) $line['qty'];
                $price = (float) $line['price'];
                $memo->lines()->create([
                    'item_id' => $item?->id,
                    'item_code' => $line['item_code'],
                    'description' => $line['description'] ?: $item?->description,
                    'uom' => $line['uom'] ?: $item?->unit_of_measure,
                    'qty' => $qty,
                    'price' => $price,
                    'line_total' => $qty * $price,
                    'line_no' => $i + 1,
                ]);
            }
        });

        $this->showForm = false;
        session()->flash('status', 'Credit memo created.');
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        @if (session('status'))
            <div class="mx-2 mt-1 border border-sky-400 bg-sky-50 px-2 py-1 text-xs" role="status">{{ session('status') }}</div>
        @endif
        @if ($showForm)
            <form wire:submit="save" class="p-3 space-y-3 overflow-auto">
                <x-desktop-form>
                    <table class="desktop-form-table">
                        <x-desktop-field-row label="Memo No.">
                            <input wire:model="memo_number" class="chief-input w-40 font-mono" aria-label="Memo number" />
                        </x-desktop-field-row>
                        <x-desktop-field-row label="Memo Date">
                            <input type="date" wire:model="memo_date" class="chief-input" aria-label="Memo date" />
                        </x-desktop-field-row>
                        <x-desktop-field-row label="Customer">
                            <select wire:model="customer_id" class="chief-input w-64" aria-label="Customer">
                                <option value="">—</option>
                                @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->company_name }}</option>@endforeach
                            </select>
                        </x-desktop-field-row>
                        <x-desktop-field-row label="Comments">
                            <textarea wire:model="comments" rows="2" class="chief-input w-80" aria-label="Comments"></textarea>
                        </x-desktop-field-row>
                    </table>
                </x-desktop-form>

                <div class="chief-grid border border-slate-400">
                    <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-slate-100 flex justify-between items-center">
                        <span>Credit Lines</span>
                        <button type="button" wire:click="addLine" class="chief-btn text-xs">Add Line</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>UOM</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
                                <tr>
                                    <td>
                                        <input
                                            wire:model.blur="lines.{{ $i }}.item_code"
                                            wire:keydown.enter.prevent="lookupLineItem({{ $i }})"
                                            class="chief-input font-mono w-28"
                                            aria-label="Item code line {{ $i + 1 }}"
                                        />
                                    </td>
                                    <td><input wire:model="lines.{{ $i }}.description" class="chief-input w-full min-w-[10rem]" aria-label="Description line {{ $i + 1 }}" /></td>
                                    <td><input wire:model="lines.{{ $i }}.uom" class="chief-input w-16" aria-label="UOM line {{ $i + 1 }}" /></td>
                                    <td><input wire:model.live="lines.{{ $i }}.qty" class="chief-input w-20 text-right" aria-label="Qty line {{ $i + 1 }}" /></td>
                                    <td><input wire:model.live="lines.{{ $i }}.price" class="chief-input w-24 text-right" aria-label="Price line {{ $i + 1 }}" /></td>
                                    <td class="text-right pe-2">${{ number_format(((float) $line['qty'] * (float) $line['price']), 2) }}</td>
                                    <td><button type="button" wire:click="removeLine({{ $i }})" class="text-red-600 text-xs" aria-label="Remove line">×</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @error('lines') <p class="px-2 py-1 text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    <div class="px-2 py-1 text-right font-semibold border-t border-slate-300">Amount: ${{ number_format($lineTotal, 2) }}</div>
                </div>

                <div class="flex gap-2">
                    <button type="button" wire:click="$set('showForm', false)" class="chief-btn">Cancel</button>
                    <button type="submit" class="chief-btn-primary">Save</button>
                </div>
            </form>
        @else
            <x-list-chrome label="Search Credit Memos:" model="search" />
            <div class="px-2 py-1 font-semibold border-b border-slate-300">Credit Memos</div>
            <div class="chief-grid flex-1 overflow-auto">
                <table>
                    <thead><tr><th>Memo No.</th><th>Date</th><th>Customer</th><th class="text-right">Amount</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse ($memos as $m)
                            <tr>
                                <td class="font-mono">{{ $m->memo_number }}</td>
                                <td>{{ optional($m->memo_date)?->format('n/j/Y') }}</td>
                                <td>{{ $m->customer?->company_name }}</td>
                                <td class="text-right">${{ number_format($m->amount, 2) }}</td>
                                <td>{{ $m->status }}</td>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('sales.credit-memos.pdf', $m) }}" class="text-sky-700 underline text-xs me-2" target="_blank">PDF</a>
                                    <button type="button" wire:click="openEmail({{ $m->id }})" class="text-sky-700 underline text-xs">Email</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-2 py-6 text-slate-500">No credit memos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-record-count :count="$memos->total()">
                <button type="button" wire:click="startNew" class="chief-btn-primary">New Credit Memo</button>
                {{ $memos->links() }}
            </x-record-count>
        @endif
    </div>

    @if ($emailMemo)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeEmail" role="dialog" aria-modal="true" aria-label="Email credit memo">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-md">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between">
                    <span>Email Credit Memo {{ $emailMemo->memo_number }}</span>
                    <button type="button" wire:click="closeEmail" class="text-white hover:text-red-200" aria-label="Close">×</button>
                </div>
                <form method="POST" action="{{ route('sales.credit-memos.email', $emailMemo) }}" class="p-3 space-y-2 text-sm">
                    @csrf
                    <div>
                        <label class="block text-xs mb-1" for="cm-email">Recipient</label>
                        <input id="cm-email" name="email" type="email" value="{{ $emailTo }}" required class="chief-input w-full" />
                    </div>
                    <div>
                        <label class="block text-xs mb-1" for="cm-subject">Subject</label>
                        <input id="cm-subject" name="subject" value="{{ $emailSubject }}" class="chief-input w-full" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeEmail" class="chief-btn">Cancel</button>
                        <button type="submit" class="chief-btn-primary">Send</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
