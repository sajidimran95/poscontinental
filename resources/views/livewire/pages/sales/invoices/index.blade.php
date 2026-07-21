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

    public string $driverSavedAt = '';

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
        $this->driverSavedAt = '';
    }

    public function updatedDriver(): void
    {
        $this->persistDriver();
    }

    public function saveDriver(): void
    {
        $this->persistDriver();
    }

    protected function persistDriver(): void
    {
        if (! $this->modalInvoiceId) {
            return;
        }
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice || $invoice->company_id !== auth()->user()->company_id) {
            return;
        }
        $invoice->update(['driver' => $this->driver !== '' ? trim($this->driver) : null]);
        $this->driverSavedAt = now()->format('g:i:s A');
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

<div class="desk-page relative">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main">
        <x-action-bar title="Action" />

        <x-list-chrome label="Search Invoices:" model="search" placeholder="Invoice #, order #, customer…">
            <label class="desk-toolbar-label" for="invoice-status-filter">Status</label>
            <select id="invoice-status-filter" wire:model.live="statusFilter" class="desk-select" aria-label="Filter by status">
                <option value="">All</option>
                <option value="NOT PAID">NOT PAID</option>
                <option value="PAID">PAID</option>
            </select>
        </x-list-chrome>

        <div class="desk-titlebar">
            <h2 class="desk-title">Invoices List</h2>
            <span class="desk-title-meta">{{ number_format($invoices->total()) }} records</span>
        </div>

        <div class="desk-grid">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Invoice Date</th>
                        <th>Order No</th>
                        <th>Customer ID</th>
                        <th>Bill to</th>
                        <th class="desk-money">Subtotal</th>
                        <th class="desk-money">Total Discount</th>
                        <th class="desk-money">Trade Discount</th>
                        <th class="desk-money">Freight</th>
                        <th class="desk-money">Misc</th>
                        <th class="desk-money">Invoice Total</th>
                        <th class="desk-money">Payments</th>
                        <th class="desk-money">Credits</th>
                        <th class="desk-money">Balance</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        <tr class="cursor-pointer" wire:click="openPayments({{ $inv->id }})" @class(['is-selected' => $modalInvoiceId === $inv->id])>
                            <td class="desk-num">{{ $inv->invoice_number }}</td>
                            <td>{{ optional($inv->invoice_date)?->format('n/j/Y') }}</td>
                            <td class="desk-num">{{ $inv->salesOrder?->order_number }}</td>
                            <td class="desk-num">{{ $inv->customer?->customer_id }}</td>
                            <td>{{ $inv->customer?->company_name ?: $inv->salesOrder?->bill_to_name }}</td>
                            <td class="desk-money">${{ number_format($inv->subtotal, 2) }}</td>
                            <td class="desk-money">${{ number_format($inv->total_discount, 2) }}</td>
                            <td class="desk-money">${{ number_format($inv->trade_discount, 2) }}</td>
                            <td class="desk-money">${{ number_format($inv->freight, 2) }}</td>
                            <td class="desk-money">${{ number_format($inv->miscellaneous, 2) }}</td>
                            <td class="desk-money">${{ number_format($inv->invoice_total, 2) }}</td>
                            <td class="desk-money">${{ number_format($inv->total_payments, 2) }}</td>
                            <td class="desk-money">${{ number_format($inv->total_credits, 2) }}</td>
                            <td class="desk-money">${{ number_format($inv->invoice_balance, 2) }}</td>
                            <td class="text-center">
                                <span @class([
                                    'desk-pill',
                                    'desk-pill-new' => $inv->status === 'NOT PAID',
                                    'desk-pill-invoiced' => $inv->status === 'PAID',
                                    'desk-pill-muted' => ! in_array($inv->status, ['NOT PAID', 'PAID'], true),
                                ])>{{ $inv->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr class="is-empty">
                            <td colspan="15">No invoices. Invoice a sales order from the Orders list.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-record-count :count="$invoices->total()">{{ $invoices->links() }}</x-record-count>
    </div>

    @if ($modalInvoice)
        <div class="desk-modal-backdrop" wire:click.self="closeModal" role="dialog" aria-modal="true" aria-label="Invoice payments">
            <div class="desk-modal desk-modal-lg inv-modal">
                <div class="desk-modal-head">
                    <div class="inv-modal-title">
                        <span>Invoice {{ $modalInvoice->invoice_number }}</span>
                        <span @class([
                            'desk-pill',
                            'desk-pill-new' => $modalInvoice->status === 'NOT PAID',
                            'desk-pill-invoiced' => $modalInvoice->status === 'PAID',
                        ])>{{ $modalInvoice->status }}</span>
                    </div>
                    <div class="desk-modal-head-actions">
                        <a href="{{ route('sales.invoices.pdf', $modalInvoice) }}" class="desk-btn desk-btn-sm" target="_blank">Print PDF</a>
                        <button type="button" wire:click="$set('showEmailForm', true)" class="desk-btn desk-btn-sm">Email Invoice</button>
                        <button type="button" wire:click="closeModal" class="desk-modal-close" aria-label="Close">×</button>
                    </div>
                </div>
                <div class="desk-modal-body inv-modal-body">
                    <div class="inv-top-grid">
                        <div class="inv-card">
                            <div class="inv-card-title">Document</div>
                            <div class="inv-kv"><span>Order No</span><strong class="desk-num">{{ $modalInvoice->salesOrder?->order_number ?: '—' }}</strong></div>
                            <div class="inv-kv"><span>Order Date</span><strong>{{ optional($modalInvoice->salesOrder?->order_date)?->format('n/j/Y') ?: '—' }}</strong></div>
                            <div class="inv-kv"><span>Invoice No</span><strong class="desk-num">{{ $modalInvoice->invoice_number }}</strong></div>
                            <div class="inv-kv"><span>Invoice Date</span><strong>{{ optional($modalInvoice->invoice_date)?->format('n/j/Y') }}</strong></div>
                        </div>
                        <div class="inv-card">
                            <div class="inv-card-title">Bill To</div>
                            <div class="inv-billto">
                                <strong>{{ $modalInvoice->salesOrder?->bill_to_name ?: $modalInvoice->customer?->company_name ?: '—' }}</strong>
                                <div>{{ $modalInvoice->salesOrder?->bill_to_address }}</div>
                                @if ($modalInvoice->salesOrder?->bill_to_city || $modalInvoice->salesOrder?->bill_to_state || $modalInvoice->salesOrder?->bill_to_zip)
                                    <div>{{ collect([$modalInvoice->salesOrder?->bill_to_city, $modalInvoice->salesOrder?->bill_to_state, $modalInvoice->salesOrder?->bill_to_zip])->filter()->implode(', ') }}</div>
                                @endif
                                <div>{{ $modalInvoice->salesOrder?->bill_to_phone }}</div>
                            </div>
                        </div>
                        <div class="inv-card">
                            <div class="inv-card-title">Details</div>
                            <div class="inv-kv"><span>Sales Rep</span><strong>{{ $modalInvoice->salesOrder?->salesRep?->name ?: '—' }}</strong></div>
                            <div class="inv-kv"><span>Terms</span><strong>{{ $modalInvoice->salesOrder?->paymentTerm?->name ?: '—' }}</strong></div>
                            <div class="inv-driver">
                                <label for="invoice-driver">Delivery Driver</label>
                                <p class="inv-driver-hint">Who delivers this order / invoice. Saved when you leave the field or click Save.</p>
                                <div class="inv-driver-row">
                                    <input id="invoice-driver" wire:model.live="driver" wire:blur="saveDriver" class="so-input" placeholder="Driver name" autocomplete="off" />
                                    <button type="button" wire:click="saveDriver" class="desk-btn desk-btn-sm">Save</button>
                                </div>
                                @if ($driverSavedAt !== '')
                                    <div class="inv-driver-saved">Saved at {{ $driverSavedAt }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="inv-balance-row">
                        <div class="inv-metric"><span>Subtotal</span><strong>${{ number_format($modalInvoice->subtotal, 2) }}</strong></div>
                        <div class="inv-metric"><span>Discounts</span><strong>${{ number_format((float) $modalInvoice->trade_discount + (float) $modalInvoice->total_discount, 2) }}</strong></div>
                        <div class="inv-metric"><span>Freight / Misc</span><strong>${{ number_format((float) $modalInvoice->freight + (float) $modalInvoice->miscellaneous, 2) }}</strong></div>
                        <div class="inv-metric"><span>Invoice Total</span><strong>${{ number_format($modalInvoice->invoice_total, 2) }}</strong></div>
                        <div class="inv-metric"><span>Payments</span><strong>${{ number_format($modalInvoice->total_payments, 2) }}</strong></div>
                        <div class="inv-metric"><span>Credits</span><strong>${{ number_format($modalInvoice->total_credits, 2) }}</strong></div>
                        <div class="inv-metric inv-metric-balance"><span>Balance Due</span><strong>${{ number_format($modalInvoice->invoice_balance, 2) }}</strong></div>
                    </div>

                    <div class="entity-section">
                        <div class="entity-section-head">
                            <h3 class="entity-section-title">Collected Payments</h3>
                            <button type="button" wire:click="openPayForm" class="desk-btn desk-btn-primary desk-btn-sm">Enter Payment</button>
                        </div>
                        <div class="desk-grid" style="max-height:12rem">
                            <table class="desk-table">
                                <thead><tr><th>Date</th><th>Method</th><th class="text-right">Amount</th><th>Comments</th></tr></thead>
                                <tbody>
                                    @forelse ($modalInvoice->payments as $p)
                                        <tr>
                                            <td>{{ optional($p->payment_date)?->format('n/j/Y') }}</td>
                                            <td>{{ $p->payment_method }}</td>
                                            <td class="desk-money">${{ number_format($p->amount, 2) }}</td>
                                            <td>{{ $p->comments }}</td>
                                        </tr>
                                    @empty
                                        <tr class="is-empty"><td colspan="4">No payments yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="entity-section" style="margin-top:0.75rem">
                        <div class="entity-section-head">
                            <h3 class="entity-section-title">Applied Credits</h3>
                        </div>
                        <div class="desk-grid" style="max-height:10rem">
                            <table class="desk-table">
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
                                            <td class="desk-num">{{ $c->creditMemo?->memo_number }}</td>
                                            <td>{{ optional($c->creditMemo?->memo_date)?->format('n/j/Y') }}</td>
                                            @if ($hasCreditSalesOrder)
                                                <td class="desk-num">{{ $c->creditMemo?->salesOrder?->order_number ?: '—' }}</td>
                                            @endif
                                            <td class="desk-money">${{ number_format($c->amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr class="is-empty"><td colspan="{{ $hasCreditSalesOrder ? 4 : 3 }}">No credits applied.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($openCredits->count())
                            <div class="desk-modal-form-row">
                                <div class="so-form-row so-form-row-side">
                                    <label class="so-form-lbl" for="applyCreditId">Open Credit</label>
                                    <select id="applyCreditId" wire:model="applyCreditId" class="so-input">
                                        <option value="">—</option>
                                        @foreach ($openCredits as $cm)
                                            <option value="{{ $cm->id }}">{{ $cm->memo_number }} — ${{ number_format($cm->amount, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="so-form-row so-form-row-side">
                                    <label class="so-form-lbl" for="applyCreditAmount">Amount</label>
                                    <input id="applyCreditAmount" wire:model="applyCreditAmount" class="so-input text-right" />
                                </div>
                                <button type="button" wire:click="applyCredit" class="desk-btn">Apply Credit</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showEmailForm && $modalInvoice)
        <div class="desk-modal-backdrop desk-modal-top" wire:click.self="$set('showEmailForm', false)" role="dialog" aria-modal="true" aria-label="Email invoice">
            <div class="desk-modal desk-modal-sm">
                <div class="desk-modal-head">
                    <span>Email Invoice {{ $modalInvoice->invoice_number }}</span>
                    <button type="button" wire:click="$set('showEmailForm', false)" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <form method="POST" action="{{ route('sales.invoices.email', $modalInvoice) }}" class="desk-modal-body space-y-3">
                    @csrf
                    <p class="inv-email-note">Sends the invoice PDF to the customer email address.</p>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="inv-email">To</label>
                        <input id="inv-email" name="email" type="email" value="{{ $emailTo }}" required class="so-input" placeholder="customer@email.com" />
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="inv-subject">Subject</label>
                        <input id="inv-subject" name="subject" value="{{ $emailSubject }}" class="so-input" />
                    </div>
                    <div class="entity-footer-actions" style="justify-content:flex-end">
                        <button type="button" wire:click="$set('showEmailForm', false)" class="desk-btn">Cancel</button>
                        <button type="submit" class="desk-btn desk-btn-primary">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showPayForm && $modalInvoice)
        <div class="desk-modal-backdrop desk-modal-top" wire:click.self="$set('showPayForm', false)" role="dialog" aria-modal="true" aria-label="Enter payment">
            <div class="desk-modal desk-modal-sm">
                <div class="desk-modal-head">
                    <span>Enter Payment</span>
                    <button type="button" wire:click="$set('showPayForm', false)" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="desk-modal-body space-y-3">
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="pay_date">Payment Date</label>
                        <input id="pay_date" type="date" wire:model="pay_date" class="so-input" />
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="pay_method">Method</label>
                        <select id="pay_method" wire:model="pay_method" class="so-input">
                            <option>Cash</option>
                            <option>Credit Card</option>
                            <option>Check</option>
                            <option>ACH</option>
                        </select>
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="pay_amount">Amount</label>
                        <input id="pay_amount" wire:model="pay_amount" class="so-input text-right" />
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="pay_comments">Comments</label>
                        <input id="pay_comments" wire:model="pay_comments" class="so-input" />
                    </div>
                    <div class="entity-footer-actions" style="justify-content:flex-end">
                        <button type="button" wire:click="$set('showPayForm', false)" class="desk-btn">Cancel</button>
                        <button type="button" wire:click="savePayment" class="desk-btn desk-btn-primary">Save &amp; Print</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
