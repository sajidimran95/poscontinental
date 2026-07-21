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

    public string $favorite = 'all';

    public ?int $modalInvoiceId = null;

    public string $pay_date = '';

    public string $pay_method = 'Cash';

    public string $pay_amount = '';

    public string $pay_comments = '';

    public ?int $applyCreditId = null;

    public string $applyCreditAmount = '';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = Invoice::query()
            ->with(['customer', 'salesOrder', 'payments', 'credits.creditMemo'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('invoice_number', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', $term));
                });
            })
            ->when($this->favorite === 'not_paid', fn ($q) => $q->where('status', 'NOT PAID'))
            ->orderByDesc('id');

        $modalInvoice = $this->modalInvoiceId
            ? Invoice::query()->with(['customer', 'salesOrder', 'payments', 'credits.creditMemo'])->find($this->modalInvoiceId)
            : null;

        return [
            'invoices' => $query->paginate(50),
            'favorites' => [
                'all' => 'All Invoices',
                'not_paid' => 'NOT PAID',
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
        ];
    }

    public function openPayments(int $id): void
    {
        $this->modalInvoiceId = $id;
        $invoice = Invoice::query()->find($id);
        $this->pay_date = now()->toDateString();
        $this->pay_method = 'Cash';
        $this->pay_amount = $invoice ? number_format($invoice->invoice_balance, 2, '.', '') : '0';
        $this->pay_comments = '';
    }

    public function closeModal(): void
    {
        $this->modalInvoiceId = null;
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

        InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_date' => $this->pay_date,
            'payment_method' => $this->pay_method,
            'amount' => $this->pay_amount,
            'comments' => $this->pay_comments,
            'user_id' => auth()->id(),
        ]);

        $invoice->refresh();
        $balance = $invoice->invoice_balance;
        $invoice->update(['status' => $balance <= 0.0001 ? 'PAID' : 'NOT PAID']);

        $this->pay_amount = number_format(max(0, $balance), 2, '.', '');
        session()->flash('status', 'Payment recorded.');
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
        <x-list-chrome label="Search Invoices:" model="search" />
        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Invoices List</div>
        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Invoice No.</th>
                        <th>Invoice Date</th>
                        <th>Order No.</th>
                        <th>Customer</th>
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
                            <td>{{ $inv->customer?->company_name }}</td>
                            <td class="text-right">${{ number_format($inv->invoice_total, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->total_payments, 2) }}</td>
                            <td class="text-right">${{ number_format($inv->total_credits, 2) }}</td>
                            <td class="text-right font-semibold">${{ number_format($inv->invoice_balance, 2) }}</td>
                            <td>{{ $inv->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-2 py-6 text-slate-500">No invoices. Invoice a sales order from the Orders list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-count :count="$invoices->total()">{{ $invoices->links() }}</x-record-count>
    </div>

    @if ($modalInvoice)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeModal">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-3xl max-h-[90vh] overflow-auto">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between">
                    <span>Payments & Credits — Invoice {{ $modalInvoice->invoice_number }}</span>
                    <button type="button" wire:click="closeModal" class="text-white hover:text-red-200">×</button>
                </div>
                <div class="p-3 space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-3 border border-slate-300 p-2 bg-slate-50">
                        <div>
                            <div>Order No.: <strong class="font-mono">{{ $modalInvoice->salesOrder?->order_number }}</strong></div>
                            <div>Invoice Date: {{ optional($modalInvoice->invoice_date)?->format('n/j/Y') }}</div>
                            <div>Status: <strong>{{ $modalInvoice->status }}</strong></div>
                        </div>
                        <div>
                            <div class="font-semibold">{{ $modalInvoice->customer?->company_name }}</div>
                            <div>{{ $modalInvoice->salesOrder?->bill_to_address }}</div>
                            <div>{{ $modalInvoice->salesOrder?->bill_to_phone }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2 text-right border border-slate-300 p-2">
                        <div>Subtotal<br><strong>${{ number_format($modalInvoice->subtotal, 2) }}</strong></div>
                        <div>Freight<br><strong>${{ number_format($modalInvoice->freight, 2) }}</strong></div>
                        <div>Total<br><strong>${{ number_format($modalInvoice->invoice_total, 2) }}</strong></div>
                        <div>Balance<br><strong class="text-red-700">${{ number_format($modalInvoice->invoice_balance, 2) }}</strong></div>
                    </div>

                    <div>
                        <h3 class="font-semibold mb-1">Collected Payments</h3>
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
                        <div class="flex flex-wrap items-end gap-2 border border-slate-300 p-2 bg-slate-50">
                            <div><label class="block text-xs">Payment Date</label><input type="date" wire:model="pay_date" class="chief-input" /></div>
                            <div>
                                <label class="block text-xs">Method</label>
                                <select wire:model="pay_method" class="chief-input">
                                    <option>Cash</option><option>Credit Card</option><option>Check</option>
                                </select>
                            </div>
                            <div><label class="block text-xs">Amount</label><input wire:model="pay_amount" class="chief-input w-28 text-right" /></div>
                            <div><label class="block text-xs">Comments</label><input wire:model="pay_comments" class="chief-input w-40" /></div>
                            <button type="button" wire:click="savePayment" class="chief-btn-primary">Save & Print</button>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold mb-1">Applied Credits</h3>
                        <div class="chief-grid border border-slate-300 mb-2">
                            <table>
                                <thead><tr><th>Memo No.</th><th>Memo Date</th><th class="text-right">Amount</th></tr></thead>
                                <tbody>
                                    @forelse ($modalInvoice->credits as $c)
                                        <tr>
                                            <td class="font-mono">{{ $c->creditMemo?->memo_number }}</td>
                                            <td>{{ optional($c->creditMemo?->memo_date)?->format('n/j/Y') }}</td>
                                            <td class="text-right">${{ number_format($c->amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-slate-500">No credits applied.</td></tr>
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
</div>
