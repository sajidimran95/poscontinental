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
                ->with('payments', 'credits')
                ->where('company_id', $companyId)
                ->where('customer_id', $this->customer_id)
                ->get()
                ->filter(fn (Invoice $i) => $i->invoice_balance > 0.0001)
                ->values();
        }

        $checkedTotal = $invoices
            ->filter(fn ($inv, $k) => ! empty($this->selected[$inv->id]))
            ->sum(fn ($inv) => $inv->invoice_balance);

        return [
            'customers' => Customer::query()->where('company_id', $companyId)->where('is_inactive', false)->orderBy('company_name')->get(),
            'openInvoices' => $invoices,
            'checkedTotal' => $checkedTotal,
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

    public function applyPayment(): void
    {
        $this->validate([
            'customer_id' => 'required',
            'pay_amount' => 'required|numeric|min:0.01',
            'pay_method' => 'required',
        ]);

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

            <div class="so-form-row" style="max-width:36rem;margin-bottom:1rem">
                <label class="so-form-lbl" for="payment_customer_id">Customer</label>
                <select id="payment_customer_id" wire:model.live="customer_id" class="so-input">
                    <option value="">— Select customer —</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                    @endforeach
                </select>
            </div>

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

                <div class="entity-fieldset" style="margin-top:1rem;max-width:48rem">
                    <legend>Apply Payment</legend>
                    <div class="entity-grid-2" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:0.75rem">
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_date_cf">Date</label>
                            <input id="pay_date_cf" type="date" wire:model="pay_date" class="so-input" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_method_cf">Method</label>
                            <select id="pay_method_cf" wire:model="pay_method" class="so-input">
                                <option>Cash</option>
                                <option>Credit Card</option>
                                <option>Check</option>
                            </select>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="pay_amount_cf">Amount</label>
                            <input id="pay_amount_cf" wire:model="pay_amount" class="so-input text-right" />
                        </div>
                    </div>
                    <div class="entity-footer-actions" style="margin-top:0.85rem;justify-content:space-between">
                        <div class="entity-value">Checked total: ${{ number_format($checkedTotal, 2) }}</div>
                        <button type="button" wire:click="applyPayment" class="desk-btn desk-btn-primary">Apply Payment</button>
                    </div>
                </div>
            @else
                <div class="desk-empty-hint">Select a customer to view unpaid invoices and apply payments.</div>
            @endif
        </div>
    </div>
</div>
