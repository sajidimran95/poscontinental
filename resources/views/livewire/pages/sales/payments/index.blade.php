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
        $ids = collect($this->selected)->filter()->keys()->sort()->all();

        foreach ($ids as $id) {
            if ($remaining <= 0) {
                break;
            }
            $invoice = Invoice::query()->find($id);
            if (! $invoice || $invoice->company_id !== auth()->user()->company_id) {
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

<div class="chief-panel bg-white min-h-[70vh] flex flex-col">
    <x-action-bar title="Payments — Customer First" />
    <div class="p-3 space-y-3 flex-1">
        <div class="chief-field max-w-xl">
            <label>Customer</label>
            <select wire:model.live="customer_id" class="chief-input w-80">
                <option value="">— Select customer —</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                @endforeach
            </select>
        </div>

        @if ($customer_id)
            <div class="chief-grid border border-slate-300 overflow-auto">
                <table>
                    <thead>
                        <tr>
                            <th class="w-10"></th>
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
                                <td class="text-center"><input type="checkbox" wire:model.live="selected.{{ $inv->id }}" /></td>
                                <td class="font-mono">{{ $inv->invoice_number }}</td>
                                <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
                                <td class="font-mono">{{ $inv->salesOrder?->order_number }}</td>
                                <td class="text-right">${{ number_format($inv->invoice_total, 2) }}</td>
                                <td class="text-right font-semibold">${{ number_format($inv->invoice_balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-2 py-4 text-slate-500">No unpaid invoices for this customer.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-end gap-3 border border-slate-300 p-3 bg-slate-50 max-w-3xl">
                <div><label class="block text-xs">Payment Date</label><input type="date" wire:model="pay_date" class="chief-input" /></div>
                <div>
                    <label class="block text-xs">Payment Method</label>
                    <select wire:model="pay_method" class="chief-input">
                        <option>Cash</option><option>Credit Card</option><option>Check</option>
                    </select>
                </div>
                <div><label class="block text-xs">Payment Amount</label><input wire:model="pay_amount" class="chief-input w-32 text-right" /></div>
                <div class="text-sm pb-1">Checked total: <strong>${{ number_format($checkedTotal, 2) }}</strong></div>
                <button type="button" wire:click="applyPayment" class="chief-btn-primary">Apply Payment</button>
            </div>
        @endif
    </div>
</div>
