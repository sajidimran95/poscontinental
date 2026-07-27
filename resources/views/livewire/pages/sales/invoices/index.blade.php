<?php

use App\Models\CreditMemo;
use App\Models\Invoice;
use App\Models\InvoiceCredit;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;
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

    public ?int $selectedId = null;

    public ?int $modalInvoiceId = null;

    public string $driver = '';

    public string $driverSavedAt = '';

    /** @var list<array{key: string, payment_date: string, payment_method: string, amount: string, comments: string}> */
    public array $draftPayments = [];

    /** @var list<array{key: string, credit_memo_id: string, amount: string}> */
    public array $draftCredits = [];

    public int $selectedPaymentIndex = -1;

    public int $selectedCreditIndex = -1;

    public ?int $lastPaymentId = null;

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

        $draftPayTotal = collect($this->draftPayments)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $draftCreditTotal = collect($this->draftCredits)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $savedBalance = $modalInvoice ? round((float) $modalInvoice->invoice_balance, 2) : 0;
        $previewBalance = $modalInvoice
            ? max(0, round($savedBalance - $draftPayTotal - $draftCreditTotal, 2))
            : 0;

        return [
            'invoices' => $query->orderByDesc('id')->paginate(50),
            'favorites' => [
                'all' => 'All Invoices',
                'not_paid' => 'NOT PAID',
                'paid' => 'PAID',
            ],
            'listTitle' => match (true) {
                $this->statusFilter === 'NOT PAID', $this->favorite === 'not_paid' => 'Invoices List (NOT PAID)',
                $this->statusFilter === 'PAID', $this->favorite === 'paid' => 'Invoices List (PAID)',
                default => 'Invoices List',
            },
            'modalInvoice' => $modalInvoice,
            'openCredits' => $modalInvoice
                ? CreditMemo::query()
                    ->with(['salesOrder'])
                    ->where('company_id', $companyId)
                    ->where('customer_id', $modalInvoice->customer_id)
                    ->where('status', 'Open')
                    ->orderByDesc('id')
                    ->get()
                    ->filter(fn (CreditMemo $m) => $m->remaining_amount > 0.0001)
                    ->values()
                : collect(),
            'hasCreditSalesOrder' => \Illuminate\Support\Facades\Schema::hasColumn('credit_memos', 'sales_order_id'),
            'draftPayTotal' => $draftPayTotal,
            'draftCreditTotal' => $draftCreditTotal,
            'previewBalance' => $previewBalance,
            'savedBalance' => $savedBalance,
            'previewPayments' => $modalInvoice ? round((float) $modalInvoice->total_payments + $draftPayTotal, 2) : 0,
            'previewCredits' => $modalInvoice ? round((float) $modalInvoice->total_credits + $draftCreditTotal, 2) : 0,
        ];
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
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
        $this->selectedId = null;
        $this->favorite = match ($this->statusFilter) {
            'NOT PAID' => 'not_paid',
            'PAID' => 'paid',
            default => 'all',
        };
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->favorite = 'all';
        $this->selectedId = null;
        $this->resetPage();
    }

    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function viewSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $this->openInvoicePdf($this->selectedId);
    }

    public function printSelected(): void
    {
        $this->viewSelected();
    }

    public function printPickListSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $invoice = Invoice::query()
            ->with('salesOrder')
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $invoice) {
            session()->flash('status', 'Invoice not found.');

            return;
        }

        if (! $invoice->salesOrder) {
            session()->flash('status', 'No sales order linked to this invoice for pick list.');

            return;
        }

        $url = route('sales.invoices.pick-list', $invoice);
        $this->dispatch('open-invoice-pdf', url: $url);
        $this->js('window.open('.json_encode($url).', "_blank", "noopener")');
    }

    public function viewInvoice(int $id): void
    {
        $this->selectedId = $id;
        $this->openInvoicePdf($id);
    }

    protected function openInvoicePdf(int $id): void
    {
        $invoice = Invoice::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);

        if (! $invoice) {
            session()->flash('status', 'Invoice not found.');

            return;
        }

        $this->dispatch('open-invoice-pdf', url: route('sales.invoices.pdf', $invoice));
        $this->js('window.open('.json_encode(route('sales.invoices.pdf', $invoice)).', "_blank", "noopener")');
    }

    public function editSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $this->openPayments($this->selectedId);
    }

    public function markSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select an invoice first.');

            return;
        }

        $this->openPayments($this->selectedId);
    }

    public function openPayments(int $id): void
    {
        $this->selectedId = $id;
        $this->modalInvoiceId = $id;
        $invoice = Invoice::query()->find($id);
        $this->driver = $invoice?->driver ?? '';
        $this->driverSavedAt = '';
        $this->draftPayments = [];
        $this->draftCredits = [];
        $this->selectedPaymentIndex = -1;
        $this->selectedCreditIndex = -1;
        $this->showEmailForm = false;
        $this->emailTo = $invoice?->customer?->email ?? '';
        $this->emailSubject = $invoice ? 'Invoice '.$invoice->invoice_number : '';

        if ($invoice && $invoice->invoice_balance > 0.0001) {
            $this->addPaymentRow();
        }
    }

    public function closeModal(): void
    {
        $this->modalInvoiceId = null;
        $this->showEmailForm = false;
        $this->driverSavedAt = '';
        $this->draftPayments = [];
        $this->draftCredits = [];
        $this->selectedPaymentIndex = -1;
        $this->selectedCreditIndex = -1;
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

    public function addPaymentRow(): void
    {
        $this->pushPaymentRow(true);
    }

    public function addRemainingDuePayment(): void
    {
        $this->pushPaymentRow(true);
    }

    protected function pushPaymentRow(bool $fillRemaining = true): void
    {
        $due = round($this->remainingDraftDue(), 2);

        // Do not open extra blank $0.00 rows when nothing is left to pay.
        if ($due <= 0.0001) {
            session()->flash('status', 'No remaining balance. Remove or lower an amount first to add another payment.');

            return;
        }

        $this->draftPayments[] = [
            'key' => uniqid('pay_', true),
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'amount' => $fillRemaining ? number_format($due, 2, '.', '') : '',
            'comments' => '',
        ];
        $this->selectedPaymentIndex = count($this->draftPayments) - 1;
    }

    protected function remainingDraftDue(): float
    {
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice) {
            return 0;
        }

        $draftPay = collect($this->draftPayments)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );
        $draftCredit = collect($this->draftCredits)->sum(
            fn ($r) => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2)
        );

        return max(0, round((float) $invoice->invoice_balance - $draftPay - $draftCredit, 2));
    }

    public function removePaymentRow(): void
    {
        if ($this->selectedPaymentIndex < 0 || ! isset($this->draftPayments[$this->selectedPaymentIndex])) {
            if (count($this->draftPayments) === 0) {
                return;
            }
            $this->selectedPaymentIndex = count($this->draftPayments) - 1;
        }

        array_splice($this->draftPayments, $this->selectedPaymentIndex, 1);
        $this->draftPayments = array_values($this->draftPayments);
        $this->selectedPaymentIndex = count($this->draftPayments) > 0
            ? min($this->selectedPaymentIndex, count($this->draftPayments) - 1)
            : -1;
    }

    public function selectPaymentRow(int $index): void
    {
        $this->selectedPaymentIndex = $index;
    }

    public function addCreditRow(): void
    {
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $invoice) {
            return;
        }

        $hasOpen = CreditMemo::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('customer_id', $invoice->customer_id)
            ->where('status', 'Open')
            ->get()
            ->contains(fn (CreditMemo $m) => $m->remaining_amount > 0.0001);

        if (! $hasOpen) {
            $this->redirect(route('sales.credit-memos.index', [
                'new' => 1,
                'customer_id' => $invoice->customer_id,
            ]), navigate: true);

            return;
        }

        $this->draftCredits[] = [
            'key' => uniqid('cr_', true),
            'credit_memo_id' => '',
            'amount' => '',
        ];
        $this->selectedCreditIndex = count($this->draftCredits) - 1;
    }

    public function removeCreditRow(): void
    {
        if ($this->selectedCreditIndex < 0 || ! isset($this->draftCredits[$this->selectedCreditIndex])) {
            if (count($this->draftCredits) === 0) {
                return;
            }
            $this->selectedCreditIndex = count($this->draftCredits) - 1;
        }

        array_splice($this->draftCredits, $this->selectedCreditIndex, 1);
        $this->draftCredits = array_values($this->draftCredits);
        $this->selectedCreditIndex = count($this->draftCredits) > 0
            ? min($this->selectedCreditIndex, count($this->draftCredits) - 1)
            : -1;
    }

    public function selectCreditRow(int $index): void
    {
        $this->selectedCreditIndex = $index;
    }

    public function updatedDraftCredits($value, string $key): void
    {
        // When a credit memo is selected, default amount to min(remaining, balance).
        if (! str_ends_with($key, '.credit_memo_id')) {
            return;
        }

        $parts = explode('.', $key);
        $index = (int) ($parts[0] ?? -1);
        if ($index < 0 || ! isset($this->draftCredits[$index])) {
            return;
        }

        $memoId = (int) ($this->draftCredits[$index]['credit_memo_id'] ?? 0);
        if ($memoId <= 0) {
            return;
        }

        $memo = CreditMemo::query()->find($memoId);
        $invoice = Invoice::query()->find($this->modalInvoiceId);
        if (! $memo || ! $invoice) {
            return;
        }

        $usedElsewhere = collect($this->draftCredits)
            ->filter(fn ($r, $i) => $i !== $index && (int) ($r['credit_memo_id'] ?? 0) === $memoId)
            ->sum(fn ($r) => (float) ($r['amount'] ?? 0));

        $remaining = max(0, (float) $memo->remaining_amount - $usedElsewhere);
        $balance = max(0, (float) $invoice->invoice_balance
            - collect($this->draftPayments)->sum(fn ($r) => (float) ($r['amount'] ?? 0))
            - collect($this->draftCredits)->filter(fn ($r, $i) => $i !== $index)->sum(fn ($r) => (float) ($r['amount'] ?? 0)));

        $this->draftCredits[$index]['amount'] = number_format(min($remaining, $balance), 2, '.', '');
    }

    public function saveAll(bool $print = false): void
    {
        $invoice = Invoice::query()->with('customer')->findOrFail($this->modalInvoiceId);
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

        if ($this->driver !== ($invoice->driver ?? '')) {
            $invoice->update(['driver' => $this->driver !== '' ? trim($this->driver) : null]);
        }

        $payments = collect($this->draftPayments)
            ->map(fn ($r) => [
                'payment_date' => trim((string) ($r['payment_date'] ?? '')),
                'payment_method' => trim((string) ($r['payment_method'] ?? '')),
                'amount' => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2),
                'comments' => trim((string) ($r['comments'] ?? '')),
            ])
            ->filter(fn ($r) => $r['amount'] > 0.0001)
            ->values();

        $credits = collect($this->draftCredits)
            ->map(fn ($r) => [
                'credit_memo_id' => (int) ($r['credit_memo_id'] ?? 0),
                'amount' => round((float) str_replace(',', '', (string) ($r['amount'] ?? 0)), 2),
            ])
            ->filter(fn ($r) => $r['credit_memo_id'] > 0 && $r['amount'] > 0.0001)
            ->values();

        if ($payments->isEmpty() && $credits->isEmpty()) {
            $invoice->refresh();
            if ($invoice->payments()->exists() || $invoice->credits()->exists()) {
                $due = round((float) $invoice->invoice_balance, 2);
                if ($due > 0.0001) {
                    session()->flash('status', 'Previous payment is already saved. Remaining due $'.number_format($due, 2).' — enter amount in the new row, then Save.');
                    if (count($this->draftPayments) === 0) {
                        $this->addPaymentRow();
                    }
                } else {
                    session()->flash('status', 'Invoice is already paid. Nothing new to save.');
                    if ($print) {
                        $last = $invoice->payments()->latest('id')->first();
                        $this->openPdfInBrowser(
                            $last
                                ? route('sales.invoices.receipt', [$invoice, $last])
                                : route('sales.invoices.pdf', $invoice)
                        );
                    }
                }
            } else {
                session()->flash('status', 'Add at least one payment or credit before saving.');
            }

            return;
        }

        foreach ($payments as $i => $row) {
            if ($row['payment_date'] === '' || $row['payment_method'] === '') {
                session()->flash('status', 'Payment row '.($i + 1).' needs a date and method.');

                return;
            }
        }

        $balance = round((float) $invoice->invoice_balance, 2);
        $payTotal = round((float) $payments->sum('amount'), 2);
        $creditTotal = round((float) $credits->sum('amount'), 2);
        $combined = round($payTotal + $creditTotal, 2);

        // Allow full payoff when float/rounding is slightly over.
        if ($combined > $balance && $combined <= round($balance + 0.02, 2) && $payments->isNotEmpty()) {
            $over = round($combined - $balance, 2);
            $payments = $payments->values();
            $lastIdx = $payments->count() - 1;
            $adjusted = round((float) $payments[$lastIdx]['amount'] - $over, 2);
            if ($adjusted <= 0.0001) {
                $payments->forget($lastIdx);
                $payments = $payments->values();
            } else {
                $row = $payments[$lastIdx];
                $row['amount'] = $adjusted;
                $payments->put($lastIdx, $row);
                $payments = $payments->values();
            }
            $payTotal = round((float) $payments->sum('amount'), 2);
            $combined = round($payTotal + $creditTotal, 2);
        }

        if ($combined > $balance + 0.0001) {
            session()->flash('status', 'Total payments and credits cannot exceed the invoice balance of $'.number_format($balance, 2).'.');

            return;
        }

        $lastPayment = null;
        $savedPayTotal = $payTotal;
        $savedCreditTotal = $creditTotal;

        try {
            DB::transaction(function () use ($invoice, $payments, $credits, &$lastPayment) {
                $customerDebit = 0.0;

                foreach ($payments as $row) {
                    $lastPayment = InvoicePayment::query()->create([
                        'invoice_id' => $invoice->id,
                        'payment_date' => $row['payment_date'],
                        'payment_method' => $row['payment_method'],
                        'amount' => $row['amount'],
                        'comments' => $row['comments'] !== '' ? $row['comments'] : null,
                        'user_id' => auth()->id(),
                    ]);
                    $customerDebit += $row['amount'];
                }

                foreach ($credits as $row) {
                    $memo = CreditMemo::query()->lockForUpdate()->findOrFail($row['credit_memo_id']);
                    abort_unless(
                        $memo->company_id === $invoice->company_id
                        && (int) $memo->customer_id === (int) $invoice->customer_id
                        && $memo->status === 'Open',
                        403
                    );

                    $remaining = (float) $memo->remaining_amount;
                    $amount = min($row['amount'], $remaining);
                    if ($amount <= 0.0001) {
                        throw new \RuntimeException('Credit memo '.$memo->memo_number.' has no remaining balance.');
                    }

                    InvoiceCredit::query()->create([
                        'invoice_id' => $invoice->id,
                        'credit_memo_id' => $memo->id,
                        'amount' => $amount,
                    ]);

                    $memo->refresh();
                    $memo->update([
                        'status' => $memo->remaining_amount <= 0.0001 ? 'Applied' : 'Open',
                    ]);

                    $customerDebit += $amount;
                }

                $invoice->unsetRelation('payments');
                $invoice->unsetRelation('credits');
                $invoice->refresh();
                $invoice->load(['payments', 'credits']);
                $invoice->update([
                    'status' => round((float) $invoice->invoice_balance, 2) <= 0.0001 ? 'PAID' : 'NOT PAID',
                ]);

                if ($invoice->customer && $customerDebit > 0) {
                    $invoice->customer->update([
                        'balance' => max(0, round((float) $invoice->customer->balance - $customerDebit, 2)),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            session()->flash('status', $e->getMessage());

            return;
        }

        $this->lastPaymentId = $lastPayment?->id;
        $this->draftPayments = [];
        $this->draftCredits = [];
        $this->selectedPaymentIndex = -1;
        $this->selectedCreditIndex = -1;
        $this->modalInvoiceId = $invoice->id;

        $invoice->unsetRelation('payments');
        $invoice->unsetRelation('credits');
        $invoice->refresh();
        $invoice->load(['payments', 'credits']);

        $parts = [];
        if ($savedPayTotal > 0) {
            $parts[] = 'Payment $'.number_format($savedPayTotal, 2).' saved';
        }
        if ($savedCreditTotal > 0) {
            $parts[] = 'Credit $'.number_format($savedCreditTotal, 2).' applied';
        }
        $msg = implode('. ', $parts).'. Status: '.$invoice->status.'.';
        $remainingDue = round((float) $invoice->invoice_balance, 2);
        if ($remainingDue > 0.0001) {
            $msg .= ' Remaining due $'.number_format($remainingDue, 2).'.';
        } else {
            $msg .= ' Invoice is fully paid.';
        }
        session()->flash('status', $msg);

        if ($print) {
            if ($lastPayment) {
                $this->openPdfInBrowser(route('sales.invoices.receipt', [$invoice, $lastPayment]));
            } else {
                $this->openPdfInBrowser(route('sales.invoices.pdf', $invoice));
            }
        }

        $this->closeModal();
    }

    protected function openPdfInBrowser(string $url): void
    {
        $this->dispatch('open-invoice-pdf', url: $url);
        $this->js('window.open('.json_encode($url).', "_blank", "noopener")');
    }

    public function savePayments(): void
    {
        $this->saveAll(false);
    }

    public function saveAndPrint(): void
    {
        $this->saveAll(true);
    }
}; ?>

<div class="desk-page relative">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div class="desk-main desk-main-rail-layout">
        <x-action-bar title="Action" />

        <div class="desk-main-split">
            <div class="desk-main-body">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="desk-toolbar orders-toolbar">
                    <label class="desk-toolbar-label" for="invoices-search">Search Invoices:</label>
                    <input
                        id="invoices-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Invoice #, order #, customer…"
                        class="desk-search orders-search-input"
                        aria-label="Search Invoices"
                    />

                    <div class="orders-toolbar-right">
                        <select
                            id="invoice-status-filter"
                            wire:model.live="statusFilter"
                            class="desk-select orders-status-select"
                            aria-label="Filter by status"
                        >
                            <option value="">All</option>
                            <option value="NOT PAID">NOT PAID</option>
                            <option value="PAID">PAID</option>
                        </select>
                    </div>
                </div>

                <div class="desk-titlebar">
                    <h2 class="desk-title">{{ $listTitle }}</h2>
                    <span class="desk-title-meta">{{ number_format($invoices->total()) }} records</span>
                </div>

                <div class="desk-grid">
                    <table class="desk-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:2rem"></th>
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
                                <tr
                                    wire:click="selectRow({{ $inv->id }})"
                                    wire:dblclick="viewInvoice({{ $inv->id }})"
                                    class="cursor-pointer"
                                    @class(['is-selected' => $selectedId === $inv->id || $modalInvoiceId === $inv->id])
                                >
                                    <td class="text-center" wire:click.stop>
                                        <input
                                            type="radio"
                                            name="invoice_select"
                                            value="{{ $inv->id }}"
                                            @checked($selectedId === $inv->id)
                                            wire:click="selectRow({{ $inv->id }})"
                                            aria-label="Select invoice {{ $inv->invoice_number }}"
                                        />
                                    </td>
                                    <td class="desk-num">
                                        <a
                                            href="{{ route('sales.invoices.pdf', $inv) }}"
                                            target="_blank"
                                            rel="noopener"
                                            wire:click.stop
                                        >{{ $inv->invoice_number }}</a>
                                    </td>
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
                                    <td colspan="16">No invoices. Invoice a sales order from the Orders list.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-record-count :count="$invoices->total()">{{ $invoices->links() }}</x-record-count>
            </div>

            {{-- Right icons: view, print, open/pay, payment, refresh --}}
            <aside class="desk-rail" aria-label="Invoice actions">
                <button type="button" wire:click="viewSelected" class="desk-rail-btn" title="View invoice" aria-label="View invoice" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                        <circle cx="8" cy="8" r="2"/>
                    </svg>
                </button>
                <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print invoice" aria-label="Print invoice" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                        <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                    </svg>
                </button>
                <button type="button" wire:click="printPickListSelected" class="desk-rail-btn" title="Print pick list" aria-label="Print pick list" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <rect x="3" y="2" width="10" height="12" rx="1"/>
                        <path d="M5.5 5h5M5.5 7.5h5M5.5 10h3"/>
                    </svg>
                </button>
                <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Open invoice / payments" aria-label="Open invoice" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                    </svg>
                </button>
                <button type="button" wire:click="markSelected" class="desk-rail-btn" title="Enter payment" aria-label="Enter payment" @disabled(! $selectedId)>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/>
                        <path d="M5 8.2l2.1 2.1L11.2 6" stroke-width="1.7"/>
                    </svg>
                </button>
                <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M13 8a5 5 0 11-1.2-3.3"/>
                        <path d="M13 3v3h-3"/>
                    </svg>
                </button>
            </aside>
        </div>
    </div>

    @if ($modalInvoice)
        <div class="desk-modal-backdrop" wire:click.self="closeModal" role="dialog" aria-modal="true" aria-label="Payments and Credits">
            <div class="desk-modal desk-modal-xl pc-modal">
                <div class="desk-modal-head">
                    <div class="inv-modal-title">
                        <span>Payments &amp; Credits</span>
                        <span @class([
                            'desk-pill',
                            'desk-pill-new' => $modalInvoice->status === 'NOT PAID',
                            'desk-pill-invoiced' => $modalInvoice->status === 'PAID',
                        ])>{{ $modalInvoice->status }}</span>
                    </div>
                    <div class="desk-modal-head-actions">
                        <a href="{{ route('sales.invoices.pdf', $modalInvoice) }}" class="desk-btn desk-btn-sm" target="_blank">Print PDF</a>
                        <button type="button" wire:click="$set('showEmailForm', true)" class="desk-btn desk-btn-sm">Email</button>
                        <button type="button" wire:click="closeModal" class="desk-modal-close" aria-label="Close">×</button>
                    </div>
                </div>

                <div class="desk-modal-body pc-modal-body">
                    <div class="pc-top">
                        <div class="pc-top-left">
                            <div class="pc-kv"><label>Order No.</label><div class="pc-val desk-num">{{ $modalInvoice->salesOrder?->order_number ?: '—' }}</div></div>
                            <div class="pc-kv"><label>Order Date</label><div class="pc-val">{{ optional($modalInvoice->salesOrder?->order_date)?->format('n/j/Y') ?: '—' }}</div></div>
                            <div class="pc-kv"><label>Sales Rep.</label><div class="pc-val">{{ $modalInvoice->salesOrder?->salesRep?->name ?: '' }}</div></div>
                            <div class="pc-kv"><label>Status</label><div class="pc-val">{{ $modalInvoice->salesOrder?->status ?: 'Invoiced' }}</div></div>
                            <div class="pc-kv"><label>Invoice No.</label><div class="pc-val desk-num">{{ $modalInvoice->invoice_number }}</div></div>
                            <div class="pc-kv"><label>Invoice Date</label><div class="pc-val">{{ optional($modalInvoice->invoice_date)?->format('n/j/Y') }}</div></div>
                        </div>

                        <div class="pc-top-mid">
                            <div class="pc-kv pc-kv-block">
                                <label>Bill to</label>
                                <div class="pc-billto">
                                    <strong>{{ $modalInvoice->salesOrder?->bill_to_name ?: $modalInvoice->customer?->company_name ?: '—' }}</strong>
                                    <div>{{ $modalInvoice->salesOrder?->bill_to_address }}</div>
                                    @if ($modalInvoice->salesOrder?->bill_to_city || $modalInvoice->salesOrder?->bill_to_state || $modalInvoice->salesOrder?->bill_to_zip)
                                        <div>{{ collect([$modalInvoice->salesOrder?->bill_to_city, $modalInvoice->salesOrder?->bill_to_state, $modalInvoice->salesOrder?->bill_to_zip])->filter()->implode(', ') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="pc-kv"><label>Terms</label><div class="pc-val">{{ $modalInvoice->salesOrder?->paymentTerm?->name ?: '' }}</div></div>
                            <div class="pc-kv">
                                <label for="invoice-driver">Driver</label>
                                <input id="invoice-driver" wire:model.live.debounce.400ms="driver" wire:blur="saveDriver" class="so-input pc-input" placeholder="Driver name" autocomplete="off" />
                            </div>
                        </div>

                        <div class="pc-top-right">
                            <div class="pc-sum-row"><span>Subtotal</span><strong>${{ number_format((float) $modalInvoice->subtotal, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Trade Discount</span><strong>${{ number_format((float) $modalInvoice->trade_discount, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Freight</span><strong>${{ number_format((float) $modalInvoice->freight, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Miscellaneous</span><strong>${{ number_format((float) $modalInvoice->miscellaneous, 2) }}</strong></div>
                            <div class="pc-sum-row pc-sum-total"><span>Total</span><strong>${{ number_format((float) $modalInvoice->invoice_total, 2) }}</strong></div>
                        </div>
                    </div>

                    <div class="pc-section">
                        <div class="pc-section-head">
                            <h3>Collected Payments</h3>
                            <div class="pc-row-tools">
                                <button type="button" class="pc-tool-btn" wire:click="addPaymentRow" title="Add payment">+</button>
                                <button type="button" class="pc-tool-btn" wire:click="removePaymentRow" title="Remove selected">−</button>
                            </div>
                        </div>
                        <div class="pc-pay-meta">
                            <div class="pc-pay-meta-row">
                                <span>Invoice Amount Due</span>
                                <strong>${{ number_format((float) $savedBalance, 2) }}</strong>
                            </div>
                            @if ($draftPayTotal > 0.0001 || $draftCreditTotal > 0.0001)
                                <div class="pc-pay-meta-row">
                                    <span>Entered now</span>
                                    <strong>${{ number_format((float) $draftPayTotal + $draftCreditTotal, 2) }}</strong>
                                </div>
                            @endif
                        </div>
                        <div class="pc-grid-wrap">
                            <table class="desk-table pc-table">
                                <thead>
                                    <tr>
                                        <th style="width:8.5rem">Payment Date</th>
                                        <th style="width:9rem">Payment Method</th>
                                        <th style="width:7rem" class="text-right">Amount</th>
                                        <th>Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($modalInvoice->payments as $p)
                                        <tr class="pc-row-saved">
                                            <td>{{ optional($p->payment_date)?->format('n/j/Y') }}</td>
                                            <td>{{ $p->payment_method }}</td>
                                            <td class="desk-money">${{ number_format((float) $p->amount, 2) }}</td>
                                            <td>
                                                <span class="pc-saved-tag">Saved</span>
                                                {{ $p->comments }}
                                            </td>
                                        </tr>
                                    @endforeach

                                    @forelse ($draftPayments as $i => $row)
                                        <tr
                                            wire:key="draft-pay-{{ $row['key'] }}"
                                            wire:click="selectPaymentRow({{ $i }})"
                                            @class(['is-selected' => $selectedPaymentIndex === $i])
                                        >
                                            <td>
                                                <input type="date" class="so-input pc-cell-input" wire:model.live="draftPayments.{{ $i }}.payment_date" />
                                            </td>
                                            <td>
                                                <select class="so-input pc-cell-input" wire:model.live="draftPayments.{{ $i }}.payment_method">
                                                    <option>Cash</option>
                                                    <option>Credit Card</option>
                                                    <option>Check</option>
                                                    <option>ACH</option>
                                                    <option>Other</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" inputmode="decimal" class="so-input pc-cell-input text-right" wire:model.live="draftPayments.{{ $i }}.amount" placeholder="0.00" />
                                            </td>
                                            <td>
                                                <input type="text" class="so-input pc-cell-input" wire:model.live="draftPayments.{{ $i }}.comments" placeholder="Optional" />
                                            </td>
                                        </tr>
                                    @empty
                                        @if ($modalInvoice->payments->isEmpty())
                                            <tr class="is-empty"><td colspan="4">Use + or Add Payment to enter amount. Split any amount, then Add 2nd Due for the rest.</td></tr>
                                        @endif
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pc-bottom">
                        <div class="pc-section pc-credits">
                            <div class="pc-section-head">
                                <h3>Applied Credits</h3>
                                <div class="pc-row-tools">
                                    <button type="button" class="pc-tool-btn" wire:click="addCreditRow" title="{{ $openCredits->isEmpty() ? 'No credit memo — go create one' : 'Add credit' }}">+</button>
                                    <button type="button" class="pc-tool-btn" wire:click="removeCreditRow" title="Remove selected">−</button>
                                </div>
                            </div>
                            @if ($openCredits->isEmpty())
                                <div class="pc-credit-empty">
                                    This customer has no open credit memo.
                                    <button type="button" class="pc-link-btn" wire:click="addCreditRow">Go to Credit Memo</button>
                                </div>
                            @endif
                            <div class="pc-grid-wrap">
                                <table class="desk-table pc-table">
                                    <thead>
                                        <tr>
                                            <th>Memo No.</th>
                                            <th style="width:7.5rem">Memo Date</th>
                                            @if ($hasCreditSalesOrder)
                                                <th style="width:6.5rem">Order No.</th>
                                            @endif
                                            <th style="width:7rem" class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($modalInvoice->credits as $c)
                                            <tr class="pc-row-saved">
                                                <td class="desk-num">{{ $c->creditMemo?->memo_number }}</td>
                                                <td>{{ optional($c->creditMemo?->memo_date)?->format('n/j/Y') }}</td>
                                                @if ($hasCreditSalesOrder)
                                                    <td class="desk-num">{{ $c->creditMemo?->salesOrder?->order_number ?: '—' }}</td>
                                                @endif
                                                <td class="desk-money">${{ number_format((float) $c->amount, 2) }}</td>
                                            </tr>
                                        @endforeach

                                        @forelse ($draftCredits as $i => $row)
                                            @php
                                                $selectedMemo = $openCredits->firstWhere('id', (int) ($row['credit_memo_id'] ?? 0));
                                            @endphp
                                            <tr
                                                wire:key="draft-cr-{{ $row['key'] }}"
                                                wire:click="selectCreditRow({{ $i }})"
                                                @class(['is-selected' => $selectedCreditIndex === $i])
                                            >
                                                <td>
                                                    <select class="so-input pc-cell-input" wire:model.live="draftCredits.{{ $i }}.credit_memo_id">
                                                        <option value="">— Select credit memo —</option>
                                                        @foreach ($openCredits as $cm)
                                                            <option value="{{ $cm->id }}">{{ $cm->memo_number }} (${{ number_format($cm->remaining_amount, 2) }} left)</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>{{ optional($selectedMemo?->memo_date)?->format('n/j/Y') ?: '—' }}</td>
                                                @if ($hasCreditSalesOrder)
                                                    <td class="desk-num">{{ $selectedMemo?->salesOrder?->order_number ?: '—' }}</td>
                                                @endif
                                                <td>
                                                    <input type="text" inputmode="decimal" class="so-input pc-cell-input text-right" wire:model.live="draftCredits.{{ $i }}.amount" />
                                                </td>
                                            </tr>
                                        @empty
                                            @if ($modalInvoice->credits->isEmpty())
                                                <tr class="is-empty"><td colspan="{{ $hasCreditSalesOrder ? 4 : 3 }}">Use + to select from outstanding credit memos.</td></tr>
                                            @endif
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="pc-totals">
                            <div class="pc-sum-row"><span>New Total</span><strong>${{ number_format((float) $modalInvoice->invoice_total, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Total Credits</span><strong>${{ number_format((float) $previewCredits, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Total Payments</span><strong>${{ number_format((float) $previewPayments, 2) }}</strong></div>
                            <div class="pc-sum-row pc-sum-balance"><span>Invoice Balance</span><strong>${{ number_format((float) $savedBalance, 2) }}</strong></div>
                            @if ($draftPayTotal > 0.0001 || $draftCreditTotal > 0.0001)
                                <div class="pc-sum-row"><span>After Save</span><strong>${{ number_format((float) $previewBalance, 2) }}</strong></div>
                                <div class="pc-sum-hint">Click Save to apply. Balance above is current unpaid amount.</div>
                            @endif
                        </div>
                    </div>

                    <div class="pc-footer">
                        @if (session('status'))
                            <div class="pc-footer-msg" role="status">{{ session('status') }}</div>
                        @endif
                        <div class="pc-footer-actions">
                            <button type="button" wire:click="closeModal" class="desk-btn">Cancel</button>
                            <button type="button" wire:click="saveAndPrint" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">Save &amp; Print</button>
                            <button type="button" wire:click="savePayments" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">Save</button>
                        </div>
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
</div>

@script
<script>
    $wire.on('open-invoice-pdf', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });
</script>
@endscript
