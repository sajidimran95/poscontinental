<?php

use App\Models\CreditMemo;
use App\Models\Invoice;
use App\Models\InvoiceCredit;
use App\Models\InvoicePayment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Invoices')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public string $favorite = 'all';

    public ?int $modalInvoiceId = null;

    public bool $showPayForm = false;

    public string $driver = '';

    public string $pay_date = '';

    public string $pay_method = 'Cash';

    public string $pay_amount = '';

    public string $pay_comments = '';

    public ?int $lastPaymentId = null;

    public ?int $applyCreditId = null;

    public string $applyCreditAmount = '';

    public string $emailTo = '';

    public string $emailSubject = '';

    public bool $showEmailForm = false;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = Invoice::query()
            ->with(['customer', 'salesOrder', 'payments', 'credits.creditMemo.salesOrder'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('invoice_number', 'like', $term)
                        ->orWhereHas('customer', function ($c) use ($term) {
                            $c->where('company_name', 'like', $term)
                                ->orWhere('customer_id', 'like', $term);
                        })
                        ->orWhereHas('salesOrder', fn ($o) => $o->where('order_number', 'like', $term));
                });
            });

        if (in_array($this->statusFilter, ['NOT PAID', 'PAID'], true)) {
            $query->where('status', $this->statusFilter);
        } elseif ($this->favorite === 'not_paid') {
            $query->where('status', 'NOT PAID');
        } elseif ($this->favorite === 'paid') {
            $query->where('status', 'PAID');
        }

        $modalInvoice = $this->modalInvoiceId
            ? Invoice::query()
                ->with([
                    'customer',
                    'salesOrder.salesRep',
                    'salesOrder.paymentTerm',
                    'payments',
                    'credits.creditMemo.salesOrder',
                ])
                ->find($this->modalInvoiceId)
            : null;

        return [
            'invoices' => $query->orderByDesc('id')->paginate(50),
            'favorites' => [
                'all' => 'All Invoices',
                'not_paid' => 'NOT PAID',
                'paid' => 'PAID',
            ],
            'modalInvoice' => $modalInvoice,
            'openCredits' => $modalInvoice
                ? CreditMemo::query()
                    ->where('company_id', $companyId)
                    ->where('customer_id', $modalInvoice->customer_id)
                    ->where('status', 'Open')
                    ->orderByDesc('id')
                    ->get()
                : collect(),
            'hasCreditSalesOrder' => \Illuminate\Support\Facades\Schema::hasColumn('credit_memos', 'sales_order_id'),
        ];
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        if ($this->favorite === 'not_paid') {
            $this->statusFilter = 'NOT PAID';
        } elseif ($this->favorite === 'paid') {
            $this->statusFilter = 'PAID';
        } elseif ($this->favorite === 'all') {
            $this->statusFilter = '';
        }
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->favorite = match ($this->statusFilter) {
            'NOT PAID' => 'not_paid',
            'PAID' => 'paid',
            default => 'all',
        };
    }

    public function openPayments(int $id): void
    {
        $this->modalInvoiceId = $id;
        $this->showPayForm = false;
        $invoice = Invoice::query()->find($id);
        $this->driver = $invoice?->driver ?? '';
        $this->pay_date = now()->toDateString();
        $this->pay_method = 'Cash';
        $this->pay_amount = $invoice ? number_format($invoice->invoice_balance, 2, '.', '') : '0';
        $this->pay_comments = '';
        $this->applyCreditId = null;
        $this->applyCreditAmount = '';
        $this->showEmailForm = false;
        $this->emailTo = $invoice?->customer?->email ?? '';
        $this->emailSubject = $invoice ? 'Invoice '.$invoice->invoice_number : '';
    }

    public function closeModal(): void
    {
        $this->modalInvoiceId = null;
        $this->showPayForm = false;
        $this->showEmailForm = false;
    }

    public function updatedDriver(): void
    {
        if (! $this->modalInvoiceId) {
            return;
        }
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice || $invoice->company_id !== auth()->user()->company_id) {
            return;
        }
        $invoice->update(['driver' => $this->driver !== '' ? $this->driver : null]);
    }

    public function openPayForm(): void
    {
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        $this->pay_date = now()->toDateString();
        $this->pay_method = 'Cash';
        $this->pay_amount = $invoice ? number_format(max(0, $invoice->invoice_balance), 2, '.', '') : '0';
        $this->pay_comments = '';
        $this->showPayForm = true;
    }

    public function savePayment(): void
    {
        $invoice = Invoice::query()->findOrFail($this->modalInvoiceId);
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

        $this->validate([
            'pay_date' => 'required|date',
            'pay_method' => 'required|string',
            'pay_amount' => 'required|numeric|min:0.01',
        ]);

        if ($this->driver !== ($invoice->driver ?? '')) {
            $invoice->update(['driver' => $this->driver !== '' ? $this->driver : null]);
        }

        $payment = InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_date' => $this->pay_date,
            'payment_method' => $this->pay_method,
            'amount' => $this->pay_amount,
            'comments' => $this->pay_comments,
            'user_id' => auth()->id(),
        ]);

        $this->lastPaymentId = $payment->id;

        $invoice->refresh();
        $balance = $invoice->invoice_balance;
        $invoice->update(['status' => $balance <= 0.0001 ? 'PAID' : 'NOT PAID']);

        $this->showPayForm = false;
        $this->redirect(route('sales.invoices.receipt', [$invoice, $payment]));
    }

    public function applyCredit(): void
    {
        $invoice = Invoice::query()->findOrFail($this->modalInvoiceId);
        $memo = CreditMemo::query()->findOrFail($this->applyCreditId);
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

        $amount = min((float) $this->applyCreditAmount, (float) $memo->amount, $invoice->invoice_balance);
        if ($amount <= 0) {
            return;
        }

        InvoiceCredit::query()->create([
            'invoice_id' => $invoice->id,
            'credit_memo_id' => $memo->id,
            'amount' => $amount,
        ]);

        $memo->update(['status' => 'Applied']);
        $invoice->refresh();
        $invoice->update(['status' => $invoice->invoice_balance <= 0.0001 ? 'PAID' : 'NOT PAID']);
        $this->applyCreditId = null;
        $this->applyCreditAmount = '';
    }
}; ?>

<div class="flex gap-2 h-full relative">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        <x-list-chrome label="Search Invoices:" model="search">
            <label class="text-sm text-slate-700 whitespace-nowrap ms-2">Status:</label>
            <select wire:model.live="statusFilter" class="chief-input w-36">
                <option value="">All</option>
                <option value="NOT PAID">NOT PAID</option>
                <option value="PAID">PAID</option>
            </select>
        </x-list-chrome>
        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Invoices List</div>
        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Invoice Date</th>
                        <th>Order No</th>
                        <th>Customer ID</th>
                        <th>Bill to</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Total Discount</th>
                        <th class="text-right">Trade Discount</th>
                        <th class="text-right">Freight</th>
                        <th class="text-right">Misc</th>
                        <th class="text-right">Invoice Total</th>
                        <th class="text-right">Payments</th>
                        <th class="text-right">Credits</th>
                        <th class="text-right">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        <tr class="cursor-pointer" wire:click="openPayments({{ $inv->id }})" @class(['chief-selected-row' => $modalInvoiceId === $inv->id])>
                            <td class="font-mono">{{ $inv->invoice_number }}</td>
                            <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
                            <td class="font-mono">{{ $inv->salesOrder?->order_number }}</td>
                            <td class="font-mono">{{ $inv->customer?->customer_id }}</td>
                            <td>{{ $inv->customer?->company_name ?: $inv->salesOrder?->bill_to_name }}</td>
                            <td class="text-right">${{ number_format($inv->subtotal, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->total_discount, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->trade_discount, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->freight, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->miscellaneous, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->invoice_total, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->total_payments, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->total_credits, 2) }}</td>
                            <td class="text-right font-semibold">${{ number_format($inv->invoice_balance, 2) }}</td>
                            <td>{{ $inv->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="15" class="px-2 py-6 text-slate-500">No invoices. Invoice a sales order from the Orders list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-count :count="$invoices->total()">{{ $invoices->links() }}</x-record-count>
    </div>

    @if ($modalInvoice)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeModal">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-4xl max-h-[92vh] overflow-auto">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between items-center gap-2">
                    <span>Payments & Credits — Invoice {{ $modalInvoice->invoice_number }}</span>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('sales.invoices.pdf', $modalInvoice) }}" class="chief-btn text-xs !text-slate-800 bg-white border border-slate-400 px-2 py-0.5 rounded-sm" target="_blank">Print PDF</a>
                        <button type="button" wire:click="$set('showEmailForm', true)" class="chief-btn text-xs !text-slate-800 bg-white border border-slate-400 px-2 py-0.5 rounded-sm">Email</button>
                        <button type="button" wire:click="closeModal" class="text-white hover:text-red-200" aria-label="Close">×</button>
                    </div>
                </div>
                <div class="p-3 space-y-3 text-sm">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 border border-slate-300 p-2 bg-slate-50">
                        <div class="space-y-0.5">
                            <div>Order No: <strong class="font-mono">{{ $modalInvoice->salesOrder?->order_number }}</strong></div>
                            <div>Order Date: {{ optional($modalInvoice->salesOrder?->order_date)?->format('n/j/Y') }}</div>
                            <div>Invoice No: <strong class="font-mono">{{ $modalInvoice->invoice_number }}</strong></div>
                            <div>Invoice Date: {{ optional($modalInvoice->invoice_date)?->format('n/j/Y') }}</div>
                        </div>
                        <div class="space-y-0.5">
                            <div class="font-semibold">Bill To</div>
                            <div>{{ $modalInvoice->salesOrder?->bill_to_name ?: $modalInvoice->customer?->company_name }}</div>
                            <div>{{ $modalInvoice->salesOrder?->bill_to_address }}</div>
                            @if ($modalInvoice->salesOrder?->bill_to_city || $modalInvoice->salesOrder?->bill_to_state || $modalInvoice->salesOrder?->bill_to_zip)
                                <div>
                                    {{ collect([$modalInvoice->salesOrder?->bill_to_city, $modalInvoice->salesOrder?->bill_to_state, $modalInvoice->salesOrder?->bill_to_zip])->filter()->implode(', ') }}
                                </div>
                            @endif
                            <div>{{ $modalInvoice->salesOrder?->bill_to_phone }}</div>
                        </div>
                        <div class="space-y-1">
                            <div>Sales Rep: <strong>{{ $modalInvoice->salesOrder?->salesRep?->name ?: '—' }}</strong></div>
                            <div>Status: <strong>{{ $modalInvoice->status }}</strong></div>
                            <div>Terms: <strong>{{ $modalInvoice->salesOrder?->paymentTerm?->name ?: '—' }}</strong></div>
                            <div class="chief-field !ms-0">
                                <label class="!w-auto !min-w-0 me-2">Driver</label>
                                <input wire:model.blur="driver" class="chief-input w-40" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-2 text-right border border-slate-300 p-2 text-xs">
                        <div>Subtotal<br><strong class="text-sm">${{ number_format($modalInvoice->subtotal, 2) }}</strong></div>
                        <div>Trade Discount<br><strong class="text-sm">${{ number_format($modalInvoice->trade_discount, 2) }}</strong></div>
                        <div>Freight<br><strong class="text-sm">${{ number_format($modalInvoice->freight, 2) }}</strong></div>
                        <div>Misc<br><strong class="text-sm">${{ number_format($modalInvoice->miscellaneous, 2) }}</strong></div>
                        <div>Total<br><strong class="text-sm">${{ number_format($modalInvoice->invoice_total, 2) }}</strong></div>
                        <div>New Total<br><strong class="text-sm">${{ number_format($modalInvoice->invoice_total, 2) }}</strong></div>
                        <div>Total Credits<br><strong class="text-sm">${{ number_format($modalInvoice->total_credits, 2) }}</strong></div>
                        <div>Total Payments<br><strong class="text-sm">${{ number_format($modalInvoice->total_payments, 2) }}</strong></div>
                        <div>Invoice Balance<br><strong class="text-sm text-red-700">${{ number_format($modalInvoice->invoice_balance, 2) }}</strong></div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="font-semibold">Collected Payments</h3>
                            <button type="button" wire:click="openPayForm" class="chief-btn-primary text-xs">Enter Payment</button>
                        </div>
                        <div class="chief-grid border border-slate-300 mb-2">
                            <table>
                                <thead><tr><th>Date</th><th>Method</th><th class="text-right">Amount</th><th>Comments</th></tr></thead>
                                <tbody>
                                    @forelse ($modalInvoice->payments as $p)
                                        <tr>
                                            <td>{{ optional($p->payment_date)?->format('n/j/Y') }}</td>
                                            <td>{{ $p->payment_method }}</td>
                                            <td class="text-right">${{ number_format($p->amount, 2) }}</td>
                                            <td>{{ $p->comments }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-slate-500">No payments yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold mb-1">Applied Credits</h3>
                        <div class="chief-grid border border-slate-300 mb-2">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Memo No</th>
                                        <th>Memo Date</th>
                                        @if ($hasCreditSalesOrder)
                                            <th>Order No</th>
                                        @endif
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($modalInvoice->credits as $c)
                                        <tr>
                                            <td class="font-mono">{{ $c->creditMemo?->memo_number }}</td>
                                            <td>{{ optional($c->creditMemo?->memo_date)?->format('n/j/Y') }}</td>
                                            @if ($hasCreditSalesOrder)
                                                <td class="font-mono">{{ $c->creditMemo?->salesOrder?->order_number ?: '—' }}</td>
                                            @endif
                                            <td class="text-right">${{ number_format($c->amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="{{ $hasCreditSalesOrder ? 4 : 3 }}" class="text-slate-500">No credits applied.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($openCredits->count())
                            <div class="flex flex-wrap items-end gap-2">
                                <div>
                                    <label class="block text-xs">Open Credit Memo</label>
                                    <select wire:model="applyCreditId" class="chief-input w-48">
                                        <option value="">—</option>
                                        @foreach ($openCredits as $cm)
                                            <option value="{{ $cm->id }}">{{ $cm->memo_number }} — ${{ number_format($cm->amount, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div><label class="block text-xs">Amount</label><input wire:model="applyCreditAmount" class="chief-input w-28 text-right" /></div>
                                <button type="button" wire:click="applyCredit" class="chief-btn">Apply Credit</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showEmailForm && $modalInvoice)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" wire:click.self="$set('showEmailForm', false)">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-md">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between">
                    <span>Email Invoice</span>
                    <button type="button" wire:click="$set('showEmailForm', false)" class="text-white hover:text-red-200" aria-label="Close">×</button>
                </div>
                <form method="POST" action="{{ route('sales.invoices.email', $modalInvoice) }}" class="p-3 space-y-2 text-sm">
                    @csrf
                    <div>
                        <label class="block text-xs mb-1" for="inv-email">Recipient</label>
                        <input id="inv-email" name="email" type="email" value="{{ $emailTo }}" required class="chief-input w-full" />
                    </div>
                    <div>
                        <label class="block text-xs mb-1" for="inv-subject">Subject</label>
                        <input id="inv-subject" name="subject" value="{{ $emailSubject }}" class="chief-input w-full" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showEmailForm', false)" class="chief-btn">Cancel</button>
                        <button type="submit" class="chief-btn-primary">Send</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showPayForm && $modalInvoice)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" wire:click.self="$set('showPayForm', false)">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-md">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between">
                    <span>Enter Payment</span>
                    <button type="button" wire:click="$set('showPayForm', false)" class="text-white hover:text-red-200">×</button>
                </div>
                <div class="p-3 space-y-2 text-sm">
                    <div class="chief-field !ms-0 flex-col items-stretch gap-1">
                        <label>Payment Date</label>
                        <input type="date" wire:model="pay_date" class="chief-input w-full" />
                    </div>
                    <div class="chief-field !ms-0 flex-col items-stretch gap-1">
                        <label>Method</label>
                        <select wire:model="pay_method" class="chief-input w-full">
                            <option>Cash</option>
                            <option>Credit Card</option>
                            <option>Check</option>
                            <option>ACH</option>
                        </select>
                    </div>
                    <div class="chief-field !ms-0 flex-col items-stretch gap-1">
                        <label>Amount</label>
                        <input wire:model="pay_amount" class="chief-input w-full text-right" />
                    </div>
                    <div class="chief-field !ms-0 flex-col items-stretch gap-1">
                        <label>Comments</label>
                        <input wire:model="pay_comments" class="chief-input w-full" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showPayForm', false)" class="chief-btn">Cancel</button>
                        <button type="button" wire:click="savePayment" class="chief-btn-primary">Save &amp; Print</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
