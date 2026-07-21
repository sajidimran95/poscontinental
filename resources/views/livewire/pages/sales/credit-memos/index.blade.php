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

        $query = CreditMemo::query()
            ->with('customer')
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('memo_number', 'like', $term)
                        ->orWhere('comments', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', $term)
                            ->orWhere('customer_id', 'like', $term));
                });
            })
            ->when($this->favorite === 'open', fn ($q) => $q->where('status', 'Open'))
            ->when($this->favorite === 'applied', fn ($q) => $q->where('status', 'Applied'))
            ->orderByDesc('id');

        return [
            'memos' => $query->paginate(50),
            'customers' => Customer::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('company_name')->get(),
            'favorites' => [
                'all' => 'All Credit Memos',
                'open' => 'Open',
                'applied' => 'Applied',
            ],
            'emailMemo' => $this->emailMemoId
                ? CreditMemo::query()->with('customer')->find($this->emailMemoId)
                : null,
            'lineTotal' => collect($this->lines)->sum(function ($l) {
                return ((float) ($l['qty'] ?? 0)) * ((float) ($l['price'] ?? 0));
            }),
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
    }

    public function startNew(): void
    {
        $this->showForm = true;
        $this->resetErrorBag();
        $this->memo_number = CreditMemo::nextNumber(auth()->user()->company_id);
        $this->memo_date = now()->toDateString();
        $this->customer_id = null;
        $this->comments = '';
        $this->lines = [
            ['item_code' => '', 'description' => '', 'uom' => '', 'qty' => '1', 'price' => '0'],
        ];
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetErrorBag();
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

        $companyId = (int) auth()->user()->company_id;

        DB::transaction(function () use ($amount, $companyId) {
            $candidate = filled($this->memo_number) ? (string) $this->memo_number : CreditMemo::nextNumber($companyId);
            if (
                CreditMemo::query()
                    ->where('company_id', $companyId)
                    ->where('memo_number', $candidate)
                    ->exists()
            ) {
                $candidate = CreditMemo::nextNumber($companyId);
            }
            $this->memo_number = $candidate;

            $memo = CreditMemo::query()->create([
                'company_id' => $companyId,
                'memo_number' => $candidate,
                'memo_date' => $this->memo_date,
                'customer_id' => $this->customer_id,
                'amount' => $amount,
                'status' => 'Open',
                'comments' => $this->comments,
            ]);

            foreach (array_values($this->lines) as $i => $line) {
                $item = Item::query()
                    ->where('company_id', $companyId)
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
        session()->flash('status', 'Credit memo '.$this->memo_number.' created. Apply it from an unpaid invoice.');
    }
}; ?>

<div class="desk-page relative">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar :title="$showForm ? 'New Credit Memo' : 'Action'" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        @if ($showForm)
            <form wire:submit="save" class="entity-body">
                <div class="cm-help">
                    <strong>What is a Credit Memo?</strong>
                    A credit memo reduces what a customer owes (returns, price adjustments, allowances).
                    Create it here as <em>Open</em>, then apply it on an invoice under <strong>Payments &amp; Credits</strong>.
                </div>

                <div class="inv-top-grid" style="margin-bottom:1rem">
                    <div class="inv-card">
                        <div class="inv-card-title">Memo header</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="memo_number">Memo No.</label>
                            <input id="memo_number" wire:model="memo_number" class="so-input font-mono" readonly title="Auto-generated" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="memo_date">Memo Date</label>
                            <input id="memo_date" type="date" wire:model="memo_date" class="so-input" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="cm_customer_id">Customer</label>
                            <select id="cm_customer_id" wire:model="customer_id" class="so-input">
                                <option value="">— Select customer —</option>
                                @foreach ($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('customer_id') <p class="text-xs text-red-700 mt-1" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="inv-card" style="grid-column: span 2">
                        <div class="inv-card-title">Comments</div>
                        <textarea wire:model="comments" rows="4" class="so-input so-input-area" placeholder="Reason for credit (return, price adjustment, etc.)"></textarea>
                    </div>
                </div>

                <div class="entity-section">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Credit Lines</h3>
                        <button type="button" wire:click="addLine" class="desk-btn desk-btn-sm">Add Line</button>
                    </div>
                    <div class="desk-grid">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th>UOM</th>
                                    <th class="desk-money">Qty</th>
                                    <th class="desk-money">Price</th>
                                    <th class="desk-money">Total</th>
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
                                                class="so-input font-mono"
                                                style="width:7.5rem"
                                                placeholder="Code + Enter"
                                                aria-label="Item code line {{ $i + 1 }}"
                                            />
                                        </td>
                                        <td><input wire:model="lines.{{ $i }}.description" class="so-input" style="min-width:12rem" aria-label="Description line {{ $i + 1 }}" /></td>
                                        <td><input wire:model="lines.{{ $i }}.uom" class="so-input" style="width:4rem" aria-label="UOM line {{ $i + 1 }}" /></td>
                                        <td><input wire:model.live="lines.{{ $i }}.qty" class="so-input text-right" style="width:5rem" aria-label="Qty line {{ $i + 1 }}" /></td>
                                        <td><input wire:model.live="lines.{{ $i }}.price" class="so-input text-right" style="width:6rem" aria-label="Price line {{ $i + 1 }}" /></td>
                                        <td class="desk-money">${{ number_format(((float) $line['qty'] * (float) $line['price']), 2) }}</td>
                                        <td><button type="button" wire:click="removeLine({{ $i }})" class="desk-btn desk-btn-sm" aria-label="Remove line">×</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('lines') <p class="px-3 py-2 text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    @error('lines.0.item_code') <p class="px-3 py-2 text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    <div class="entity-section-head" style="border-top:1px solid #e2e8f0;border-bottom:none;justify-content:flex-end">
                        <span class="entity-value">Credit Amount: ${{ number_format($lineTotal, 2) }}</span>
                    </div>
                </div>

                <div class="entity-footer-actions" style="margin-top:1rem;justify-content:flex-end">
                    <button type="button" wire:click="cancelForm" class="desk-btn">Cancel</button>
                    <button type="submit" class="desk-btn desk-btn-primary">Save Credit Memo</button>
                </div>
            </form>
        @else
            <x-list-chrome label="Search Credit Memos:" model="search" placeholder="Memo #, customer, comments…">
                <button type="button" wire:click="startNew" class="desk-btn desk-btn-primary ms-auto">New Credit Memo</button>
            </x-list-chrome>

            <div class="desk-titlebar">
                <h2 class="desk-title">Credit Memos</h2>
                <span class="desk-title-meta">{{ number_format($memos->total()) }} records</span>
            </div>

            <div class="cm-help" style="margin:0.65rem 0.85rem 0">
                Credit memos lower a customer’s balance. Create one, then open an unpaid invoice → <strong>Apply Credit</strong>.
            </div>

            <div class="desk-grid">
                <table class="desk-table">
                    <thead>
                        <tr>
                            <th>Memo No.</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th class="desk-money">Amount</th>
                            <th class="text-center">Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($memos as $m)
                            <tr>
                                <td class="desk-num">{{ $m->memo_number }}</td>
                                <td>{{ optional($m->memo_date)?->format('n/j/Y') }}</td>
                                <td>{{ $m->customer?->company_name }}</td>
                                <td class="desk-money">${{ number_format($m->amount, 2) }}</td>
                                <td class="text-center">
                                    <span @class([
                                        'desk-pill',
                                        'desk-pill-new' => $m->status === 'Open',
                                        'desk-pill-invoiced' => $m->status === 'Applied',
                                        'desk-pill-muted' => ! in_array($m->status, ['Open', 'Applied'], true),
                                    ])>{{ $m->status }}</span>
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <a href="{{ route('sales.credit-memos.pdf', $m) }}" class="desk-btn desk-btn-sm" target="_blank">PDF</a>
                                        <button type="button" wire:click="openEmail({{ $m->id }})" class="desk-btn desk-btn-sm">Email</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="is-empty">
                                <td colspan="6">No credit memos yet. Click <strong>New Credit Memo</strong> to create one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-record-count :count="$memos->total()">
                <button type="button" wire:click="startNew" class="desk-btn desk-btn-primary">New Credit Memo</button>
                {{ $memos->links() }}
            </x-record-count>
        @endif
    </div>

    @if ($emailMemo)
        <div class="desk-modal-backdrop" wire:click.self="closeEmail" role="dialog" aria-modal="true" aria-label="Email credit memo">
            <div class="desk-modal desk-modal-sm">
                <div class="desk-modal-head">
                    <span>Email Credit Memo {{ $emailMemo->memo_number }}</span>
                    <button type="button" wire:click="closeEmail" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <form method="POST" action="{{ route('sales.credit-memos.email', $emailMemo) }}" class="desk-modal-body space-y-3">
                    @csrf
                    <p class="inv-email-note">Sends the credit memo PDF to the customer.</p>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="cm-email">To</label>
                        <input id="cm-email" name="email" type="email" value="{{ $emailTo }}" required class="so-input" placeholder="customer@email.com" />
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="cm-subject">Subject</label>
                        <input id="cm-subject" name="subject" value="{{ $emailSubject }}" class="so-input" />
                    </div>
                    <div class="entity-footer-actions" style="justify-content:flex-end">
                        <button type="button" wire:click="closeEmail" class="desk-btn">Cancel</button>
                        <button type="submit" class="desk-btn desk-btn-primary">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
