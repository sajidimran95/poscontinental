<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Payments')] class extends Component
{
    public ?int $customer_id = null;

    /** @var array<int, bool> */
    public array $selected = [];

    public string $pay_amount = '0';

    public string $pay_method = 'Cash';

    public string $pay_date = '';

    public string $pay_check_number = '';

    public string $checkSearch = '';

    public function mount(): void
    {
        $this->pay_date = now()->toDateString();
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;
        $invoices = collect();
        if ($this->customer_id) {
            $invoices = Invoice::query()
                ->with(['payments', 'credits', 'salesOrder'])
                ->where('company_id', $companyId)
                ->where('customer_id', $this->customer_id)
                ->get()
                ->filter(fn (Invoice $i) => $i->invoice_balance > 0.0001)
                ->values();
        }

        $checkedTotal = $invoices
            ->filter(fn ($inv) => ! empty($this->selected[$inv->id]))
            ->sum(fn ($inv) => $inv->invoice_balance);

        $checkHits = collect();
        $checkTerm = trim($this->checkSearch);
        if ($checkTerm !== '') {
            $like = '%'.$checkTerm.'%';
            $checkHits = InvoicePayment::query()
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $companyId))
                ->where(function ($q) use ($like) {
                    $q->where('check_number', 'like', $like)
                        ->orWhere(function ($inner) use ($like) {
                            $inner->whereRaw('LOWER(payment_method) = ?', ['check'])
                                ->where('comments', 'like', $like);
                        });
                })
                ->with(['invoice.customer', 'invoice.salesOrder'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        return [
            'customers' => Customer::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('company_name')->get(['id', 'customer_id', 'company_name']),
            'openInvoices' => $invoices,
            'checkedTotal' => $checkedTotal,
            'checkHits' => $checkHits,
            'isCheckMethod' => InvoicePayment::isCheckMethod($this->pay_method),
            'canEnterPayments' => auth()->user()?->canAccessFeature('sales.payments', 'edit') ?? false,
        ];
    }

    public function updatedSelected(): void
    {
        $total = 0;
        foreach ($this->selected as $id => $on) {
            if (! $on) {
                continue;
            }
            $inv = Invoice::query()->find($id);
            if ($inv) {
                $total += $inv->invoice_balance;
            }
        }
        $this->pay_amount = number_format($total, 2, '.', '');
    }

    public function updatedPayMethod(): void
    {
        if (! InvoicePayment::isCheckMethod($this->pay_method)) {
            $this->pay_check_number = '';
        }
    }

    public function openCheckHit(int $paymentId): void
    {
        $payment = InvoicePayment::query()
            ->with('invoice')
            ->find($paymentId);
        if (! $payment?->invoice || (int) $payment->invoice->company_id !== (int) auth()->user()->company_id) {
            return;
        }

        $this->customer_id = (int) $payment->invoice->customer_id;
        $this->selected = [];
    }

    public function applyPayment(): void
    {
        if (! auth()->user()?->canAccessFeature('sales.payments', 'edit')) {
            session()->flash('status', 'Your role cannot apply payments. Enable Payments & Credits permission.');

            return;
        }

        $this->validate([
            'customer_id' => 'required',
            'pay_amount' => 'required|numeric|min:0.01',
            'pay_method' => 'required',
            'pay_check_number' => InvoicePayment::isCheckMethod($this->pay_method)
                ? 'required|string|max:64'
                : 'nullable|string|max:64',
        ], [
            'pay_check_number.required' => 'Enter the check number.',
        ]);

        $checkNumber = InvoicePayment::isCheckMethod($this->pay_method)
            ? trim($this->pay_check_number)
            : null;

        $remaining = (float) $this->pay_amount;
        $ids = collect($this->selected)->filter()->keys()->all();
        $invoices = Invoice::query()
            ->whereIn('id', $ids)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }
            if ($invoice->company_id !== auth()->user()->company_id) {
                continue;
            }
            $due = $invoice->invoice_balance;
            if ($due <= 0) {
                continue;
            }
            $apply = min($remaining, $due);
            InvoicePayment::query()->create([
                'invoice_id' => $invoice->id,
                'payment_date' => $this->pay_date,
                'payment_method' => $this->pay_method,
                'check_number' => $checkNumber,
                'amount' => $apply,
                'comments' => 'Customer-first payment',
                'user_id' => auth()->id(),
            ]);
            $invoice->refresh();
            $invoice->update(['status' => $invoice->invoice_balance <= 0.0001 ? 'PAID' : 'NOT PAID']);
            $remaining -= $apply;
        }

        $this->selected = [];
        $this->pay_amount = '0';
        $this->pay_check_number = '';
        session()->flash('status', 'Payment applied to selected invoices (oldest-first allocation).');
    }
}; ?>

<div class="desk-page entity-page">
    <div class="desk-main entity-form" style="width:min(100%,70rem)">
        <x-action-bar title="Payments — Customer First" />

        <div class="entity-body">
            @if (session('status'))
                <div class="desk-flash" role="status">{{ session('status') }}</div>
            @endif

            <div class="entity-grid-2" style="grid-template-columns:minmax(14rem,18rem) minmax(20rem,1fr);gap:0.75rem 1.25rem;max-width:48rem;margin-bottom:1rem;align-items:end">
                <div class="so-form-row">
                    <label class="so-form-lbl" for="payment_check_search">Search by check #</label>
                    <input
                        id="payment_check_search"
                        type="search"
                        wire:model.live.debounce.300ms="checkSearch"
                        class="so-input"
                        placeholder="Enter check number…"
                        autocomplete="off"
                    />
                </div>
                <div class="so-form-row">
                    <label class="so-form-lbl" for="payment_customer_id">Customer</label>
                    <select id="payment_customer_id" wire:model.live="customer_id" class="so-input">
                        <option value="">— Select customer —</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if (trim($checkSearch) !== '')
                <div class="entity-section" style="margin-bottom:1rem">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Check Number Results</h3>
                        <span class="desk-title-meta">{{ $checkHits->count() }} match{{ $checkHits->count() === 1 ? '' : 'es' }}</span>
                    </div>
                    <div class="desk-grid" style="max-height:16rem">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th>Check #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Invoice</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($checkHits as $hit)
                                    <tr
                                        wire:key="check-hit-{{ $hit->id }}"
                                        wire:click="openCheckHit({{ $hit->id }})"
                                        class="cursor-pointer"
                                        title="Open this customer’s invoices"
                                    >
                                        <td class="desk-num">{{ $hit->check_number ?: '—' }}</td>
                                        <td>{{ optional($hit->payment_date)?->format('n/j/Y') }}</td>
                                        <td>{{ $hit->invoice?->customer?->customer_id }} — {{ $hit->invoice?->customer?->company_name }}</td>
                                        <td class="desk-num">{{ $hit->invoice?->invoice_number }}</td>
                                        <td class="desk-money">${{ number_format((float) $hit->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="5">No payments found for that check number.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($customer_id)
                <div class="entity-section">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Open Invoices</h3>
                        <span class="desk-title-meta">Select invoices to pay (oldest first)</span>
                    </div>
                    <div class="desk-grid" style="max-height:22rem">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:2.5rem"></th>
                                    <th>Invoice No.</th>
                                    <th>Invoice Date</th>
                                    <th>Order No.</th>
                                    <th class="text-right">Invoice Total</th>
                                    <th class="text-right">Balance Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($openInvoices as $inv)
                                    <tr>
                                        <td class="text-center"><input type="checkbox" wire:model.live="selected.{{ $inv->id }}" aria-label="Select invoice {{ $inv->invoice_number }}" /></td>
                                        <td class="desk-num">{{ $inv->invoice_number }}</td>
                                        <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
                                        <td class="desk-num">{{ $inv->salesOrder?->order_number }}</td>
                                        <td class="desk-money">${{ number_format($inv->invoice_total, 2) }}</td>
                                        <td class="desk-money">${{ number_format($inv->invoice_balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="6">No unpaid invoices for this customer.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="entity-fieldset" style="margin-top:1rem;max-width:48rem" @class(['opacity-60' => ! $canEnterPayments])>
                    <legend>Apply Payment</legend>
                    @unless ($canEnterPayments)
                        <p class="item-hint" style="padding:0 0 0.5rem">Payments are disabled for your role. Enable <strong>Payments &amp; Credits → Edit</strong> to apply.</p>
                    @endunless
                    <div class="entity-grid-2" style="grid-template-columns:repeat({{ $isCheckMethod ? 4 : 3 }},minmax(0,1fr));gap:0.75rem">
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_date_cf">Date</label>
                            <input id="pay_date_cf" type="date" wire:model="pay_date" class="so-input" @disabled(! $canEnterPayments) />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_method_cf">Method</label>
                            <select id="pay_method_cf" wire:model.live="pay_method" class="so-input" @disabled(! $canEnterPayments)>
                                <option>Cash</option>
                                <option>Credit Card</option>
                                <option>Check</option>
                            </select>
                        </div>
                        @if ($isCheckMethod)
                            <div class="so-form-row so-form-row-side">
                                <label class="so-form-lbl so-field-req" for="pay_check_number_cf">Check #</label>
                                <input
                                    id="pay_check_number_cf"
                                    type="text"
                                    wire:model="pay_check_number"
                                    class="so-input @error('pay_check_number') is-invalid @enderror"
                                    placeholder="Check number"
                                    autocomplete="off"
                                    @disabled(! $canEnterPayments)
                                />
                                @error('pay_check_number')
                                    <span class="so-field-error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_amount_cf">Amount</label>
                            <input id="pay_amount_cf" wire:model="pay_amount" class="so-input text-right" @disabled(! $canEnterPayments) />
                        </div>
                    </div>
                    <div class="entity-footer-actions" style="margin-top:0.85rem;justify-content:space-between">
                        <div class="entity-value">Checked total: ${{ number_format($checkedTotal, 2) }}</div>
                        <button type="button" wire:click="applyPayment" class="desk-btn desk-btn-primary" @disabled(! $canEnterPayments) title="{{ $canEnterPayments ? 'Apply payment' : 'No payment permission' }}">Apply Payment</button>
                    </div>
                </div>
            @else
                @if (trim($checkSearch) === '')
                    <div class="desk-empty-hint">Select a customer to view unpaid invoices and apply payments, or search by check number.</div>
                @endif
            @endif
        </div>
    </div>
</div>
