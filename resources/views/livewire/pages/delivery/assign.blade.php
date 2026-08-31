<?php

use App\Livewire\Concerns\SortsDeskList;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Delivery\DeliveryRouteService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Delivery Management')] class extends Component
{
    use WithPagination;
    use SortsDeskList;

    #[Url]
    public string $search = '';

    /** Invoice date filter (from). Empty = no lower bound. */
    #[Url]
    public string $date_from = '';

    /** Invoice date filter (to). Empty = no upper bound. */
    #[Url]
    public string $date_to = '';

    public string $date = '';

    public ?int $driver_id = null;

    /** Unused; kept so stale Livewire snapshots from the old warehouse picker do not 500. */
    public ?int $location_id = null;

    public string $listFilter = 'unassigned';

    public string $customerSearch = '';

    public ?int $customer_id = null;

    /** @var array<int, bool> */
    public array $selected = [];

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'view'), 403);
        $today = now()->toDateString();
        $this->date = $this->date !== '' ? $this->date : $today;
        if ($this->date_from === '') {
            $this->date_from = $today;
        }
        if ($this->date_to === '') {
            $this->date_to = $today;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearDates(): void
    {
        $this->date_from = '';
        $this->date_to = '';
        $this->resetPage();
    }

    public function updatedListFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCustomerSearch(): void
    {
        if ($this->customer_id) {
            $this->customer_id = null;
        }
        $this->resetPage();
    }

    public function pickCustomer(int $id): void
    {
        $customer = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($id);
        if (! $customer) {
            return;
        }
        $this->customer_id = $customer->id;
        $this->customerSearch = trim($customer->customer_id.' — '.$customer->company_name);
        $this->resetPage();
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->resetPage();
    }

    public function selectVisible(array $ids): void
    {
        foreach ($ids as $id) {
            $this->selected[(int) $id] = true;
        }
    }

    public function clearSelected(): void
    {
        $this->selected = [];
    }

    public function with(\App\Services\Delivery\DeliveryAreaService $areas): array
    {
        $companyId = (int) auth()->user()->company_id;
        $term = trim($this->search);

        $invoicesQuery = Invoice::query()
            ->with([
                'customer:id,customer_id,company_name,telephone',
                'salesOrder:id,order_number,delivery_status,delivery_user_id,ship_to_city,ship_to_state,ship_to_zip,ship_to_address,bill_to_name',
                'salesOrder.deliveryUser:id,name',
            ])
            ->where('company_id', $companyId)
            ->whereNotNull('sales_order_id')
            ->whereHas('salesOrder')
            ->where(function ($q) {
                $q->whereNull('customer_id')
                    ->orWhereHas('customer', function ($c) {
                        $c->whereRaw('UPPER(customer_id) != ?', [Customer::WALK_IN_CODE]);
                    });
            })
            ->when($this->customer_id, fn ($q) => $q->where('customer_id', $this->customer_id))
            ->when(! $this->customer_id && trim($this->customerSearch) !== '', function ($q) {
                $like = '%'.trim($this->customerSearch).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereHas('customer', function ($c) use ($like) {
                        $c->where('company_name', 'like', $like)
                            ->orWhere('customer_id', 'like', $like)
                            ->orWhere('contact', 'like', $like)
                            ->orWhere('telephone', 'like', $like);
                    })->orWhereHas('salesOrder', fn ($o) => $o->where('bill_to_name', 'like', $like));
                });
            })
            ->when($this->date_from !== '', fn ($q) => $q->whereDate('invoice_date', '>=', $this->date_from))
            ->when($this->date_to !== '', fn ($q) => $q->whereDate('invoice_date', '<=', $this->date_to))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('invoice_number', 'like', $like)
                        ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', $like)->orWhere('customer_id', 'like', $like))
                        ->orWhereHas('salesOrder', function ($o) use ($like) {
                            $o->where('order_number', 'like', $like)
                                ->orWhere('ship_to_city', 'like', $like)
                                ->orWhere('ship_to_address', 'like', $like)
                                ->orWhere('ship_to_phone', 'like', $like);
                        });
                });
            })
            ->when($this->listFilter === 'unassigned', fn ($q) => $q->whereHas('salesOrder', fn ($o) => $o->whereNull('delivery_user_id')))
            ->when($this->listFilter === 'assigned', fn ($q) => $q->whereHas('salesOrder', fn ($o) => $o->whereNotNull('delivery_user_id')));

        $invoices = $this->applyDeskSort($invoicesQuery, 'invoice_date', 'desc')->paginate(40);

        $custTerm = trim($this->customerSearch);
        $customerSuggestions = (! $this->customer_id && $custTerm !== '')
            ? Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
                ->whereRaw('UPPER(customer_id) != ?', [Customer::WALK_IN_CODE])
                ->where(function ($q) use ($custTerm) {
                    $like = '%'.$custTerm.'%';
                    $q->where('customer_id', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('contact', 'like', $like)
                        ->orWhere('telephone', 'like', $like);
                })
                ->orderBy('company_name')
                ->limit(12)
                ->get(['id', 'customer_id', 'company_name', 'city'])
            : collect();

        $areaByInvoice = [];
        foreach ($invoices as $invoice) {
            $order = $invoice->salesOrder;
            if (! $order) {
                continue;
            }
            $check = $areas->evaluate($order, $companyId);
            $areaByInvoice[$invoice->id] = [
                'ok' => $check['ok'],
                'code' => $check['code'],
                'message' => $check['message'],
            ];
        }

        return [
            'invoices' => $invoices,
            'customerSuggestions' => $customerSuggestions,
            'selectedCount' => collect($this->selected)->filter()->count(),
            'visibleIds' => $invoices->pluck('id')->all(),
            'drivers' => User::assignableDeliveryDrivers($companyId),
            'areaByInvoice' => $areaByInvoice,
        ];
    }

    protected function deskSortMap(): array
    {
        return [
            'invoice_number' => 'invoice_number',
            'invoice_date' => 'invoice_date',
            'customer' => ['relation' => 'customer', 'column' => 'company_name'],
            'ship_to' => ['relation' => 'salesOrder', 'column' => 'ship_to_city'],
            'total' => 'invoice_total',
            'delivery_status' => ['relation' => 'salesOrder', 'column' => 'delivery_status'],
        ];
    }

    public function assign(DeliveryRouteService $service): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $this->errorMessage = '';
        $this->statusMessage = '';
        $ids = collect($this->selected)->filter()->keys()->map(fn ($id) => (int) $id)->all();
        $orderIds = Invoice::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereIn('id', $ids)
            ->whereNotNull('sales_order_id')
            ->pluck('sales_order_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        try {
            $count = $service->assignOrders(auth()->user(), $orderIds, (int) $this->driver_id, $this->date);
            $this->statusMessage = $count.' invoice(s) assigned.';
            $this->selected = [];
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Could not assign invoices.';
        }
    }

    public function saveAreaFromInvoice(int $invoiceId, \App\Services\Delivery\DeliveryAreaService $areas): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $this->errorMessage = '';
        $this->statusMessage = '';
        $invoice = Invoice::query()
            ->with('salesOrder')
            ->where('company_id', auth()->user()->company_id)
            ->find($invoiceId);
        $order = $invoice?->salesOrder;
        if (! $order) {
            $this->errorMessage = 'Invoice not found.';

            return;
        }
        try {
            $area = $areas->saveFromOrder($order, (int) auth()->user()->company_id);
            $this->statusMessage = 'Saved delivery area '.$area->label().'.';
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Could not save area.';
        }
    }

    public function generate(DeliveryRouteService $service)
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.manage', 'edit'), 403);
        $this->errorMessage = '';
        try {
            $route = $service->generateRoute(auth()->user(), (int) $this->driver_id, $this->date);

            return $this->redirect(route('deliveries.routes.show', $route), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Could not generate route.';
        }
    }
}; ?>

<div class="desk-page dlv-page">
    <div class="desk-main desk-main-rail-layout">
        @if ($statusMessage !== '')
            <div class="dlv-banner is-ok" wire:key="dlv-ok-{{ md5($statusMessage) }}" x-data x-init="setTimeout(() => $wire.set('statusMessage', ''), 2000)">{{ $statusMessage }}</div>
        @endif
        @if ($errorMessage !== '')
            <div class="dlv-banner is-err" wire:key="dlv-err-{{ md5($errorMessage) }}" x-data x-init="setTimeout(() => $wire.set('errorMessage', ''), 2000)">{{ $errorMessage }}</div>
        @endif

        <div class="desk-toolbar orders-toolbar">
            <div class="dlv-cust-pick">
                <div class="dlv-cust-field">
                    <input
                        id="dlv-customer"
                        type="search"
                        class="desk-search orders-search-input"
                        wire:model.live.debounce.200ms="customerSearch"
                        placeholder="Filter by customer…"
                        autocomplete="off"
                    />
                    @if ($customer_id || $customerSearch !== '')
                        <button type="button" class="dlv-cust-clear" wire:click="clearCustomer" title="Clear customer">×</button>
                    @endif
                    @if ($customerSuggestions->isNotEmpty())
                        <div class="dlv-cust-suggest" role="listbox">
                            @foreach ($customerSuggestions as $cust)
                                <button type="button" class="dlv-cust-suggest-row" wire:click="pickCustomer({{ $cust->id }})">
                                    <strong>{{ $cust->customer_id }}</strong>
                                    <span>{{ $cust->company_name }}</span>
                                    @if ($cust->city)
                                        <em>{{ $cust->city }}</em>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <input
                id="dlv-search"
                type="search"
                class="desk-search"
                wire:model.live.debounce.250ms="search"
                placeholder="Invoice # or city…"
                aria-label="Invoice search"
            />
            <label class="desk-toolbar-label" for="dlv-date-from">Invoice date</label>
            <input
                id="dlv-date-from"
                type="date"
                class="desk-input"
                wire:model.live="date_from"
                aria-label="Invoice date from"
                title="Invoice date from"
            />
            <span class="dlv-muted">to</span>
            <input
                id="dlv-date-to"
                type="date"
                class="desk-input"
                wire:model.live="date_to"
                aria-label="Invoice date to"
                title="Invoice date to"
            />
            @if ($date_from !== '' || $date_to !== '')
                <button type="button" class="desk-btn desk-btn-sm" wire:click="clearDates">All dates</button>
            @endif
            <select class="desk-select orders-status-select" wire:model.live="listFilter" aria-label="Assignment filter">
                <option value="unassigned">Unassigned</option>
                <option value="assigned">Assigned</option>
                <option value="all">All invoices</option>
            </select>
            <div class="orders-toolbar-right">
                <select class="desk-select orders-party-select" wire:model="driver_id" aria-label="Delivery man">
                    <option value="">Driver…</option>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                    @endforeach
                </select>
                <input type="date" class="desk-input" wire:model="date" aria-label="Delivery date" title="Delivery date for assign / generate route" />
                <button type="button" class="desk-btn desk-btn-primary" wire:click="assign" @disabled(! auth()->user()->canAccessFeature('delivery.manage', 'edit'))>Assign selected</button>
                <button type="button" class="desk-btn" wire:click="generate" @disabled(! auth()->user()->canAccessFeature('delivery.manage', 'edit'))>Generate route</button>
            </div>
        </div>

        <div class="desk-titlebar">
            <h2 class="desk-title">Delivery Management</h2>
            <span class="desk-title-meta">{{ number_format($invoices->total()) }} invoices{{ ($date_from !== '' || $date_to !== '') ? ' · '.($date_from !== '' ? \Illuminate\Support\Carbon::parse($date_from)->format('n/j/Y') : '…').' – '.($date_to !== '' ? \Illuminate\Support\Carbon::parse($date_to)->format('n/j/Y') : '…') : '' }} · Inactive or unmatched areas cannot be assigned</span>
            <div class="desk-footer-actions">
                <button type="button" class="desk-btn desk-btn-sm" wire:click="selectVisible({{ \Illuminate\Support\Js::from($visibleIds) }})">Select page</button>
                <button type="button" class="desk-btn desk-btn-sm" wire:click="clearSelected" @disabled($selectedCount === 0)>Clear</button>
                <span class="dlv-muted">{{ $selectedCount }} selected</span>
            </div>
        </div>

        <div class="desk-main-split">
            <div class="desk-main-body">
                <div class="desk-grid dlv-assign-grid">
                    <table class="desk-table desk-table-fit">
                        <colgroup>
                            <col style="width:2.2rem" />
                            <col style="width:11%" />
                            <col style="width:10%" />
                            <col style="width:32%" />
                            <col style="width:20%" />
                            <col style="width:11%" />
                            <col style="width:14%" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="text-center"></th>
                                <x-desk-sort-th field="invoice_number" label="Invoice #" />
                                <x-desk-sort-th field="invoice_date" label="Date" />
                                <x-desk-sort-th field="customer" label="Customer" />
                                <x-desk-sort-th field="ship_to" label="Ship to" />
                                <x-desk-sort-th field="total" label="Total" align="right" />
                                <x-desk-sort-th field="delivery_status" label="Status" />
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                @php
                                    $order = $invoice->salesOrder;
                                    $status = $order?->delivery_status ?: ($order?->delivery_user_id ? 'assigned' : 'unassigned');
                                    $statusLabel = match ($status) {
                                        'delivered' => 'Delivered',
                                        'failed' => 'Failed',
                                        'en_route' => 'En route',
                                        'arrived' => 'Arrived',
                                        'assigned' => ($order?->deliveryUser?->name ? 'Assigned · '.$order->deliveryUser->name : 'Assigned'),
                                        default => $order?->deliveryUser?->name ? 'Assigned · '.$order->deliveryUser->name : ucfirst((string) $status),
                                    };
                                    $pill = match ($status) {
                                        'delivered' => 'delivered',
                                        'failed' => 'failed',
                                        'unassigned' => 'pending',
                                        default => 'en_route',
                                    };
                                    $ship = collect([$order?->ship_to_city, $order?->ship_to_state, $order?->ship_to_zip])->filter()->implode(', ');
                                    if ($ship === '' && $order?->ship_to_address) {
                                        $ship = $order->ship_to_address;
                                    }
                                    $areaInfo = $areaByInvoice[$invoice->id] ?? ['ok' => true, 'code' => 'ok', 'message' => null];
                                @endphp
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" wire:model="selected.{{ $invoice->id }}" aria-label="Select invoice {{ $invoice->invoice_number }}" />
                                    </td>
                                    <td class="desk-num">{{ $invoice->invoice_number }}</td>
                                    <td>{{ optional($invoice->invoice_date)?->format('n/j/Y') ?: '—' }}</td>
                                    <td title="{{ $invoice->customer?->company_name }}">
                                        {{ $invoice->customer?->company_name ?: $order?->bill_to_name }}
                                        @if ($invoice->customer?->customer_id)
                                            <span class="dlv-muted"> · {{ $invoice->customer->customer_id }}</span>
                                        @endif
                                    </td>
                                    <td title="{{ $order?->ship_to_address }}">{{ $ship !== '' ? $ship : '—' }}</td>
                                    <td class="desk-money">${{ number_format((float) $invoice->invoice_total, 2) }}</td>
                                    <td>
                                        <span class="dlv-pill is-{{ $pill }}">
                                            {{ $statusLabel }}
                                        </span>
                                        @if (! ($areaInfo['ok'] ?? true))
                                            <span class="dlv-pill is-failed" title="{{ $areaInfo['message'] }}">
                                                {{ ($areaInfo['code'] ?? '') === 'inactive' ? 'Area inactive' : 'Outside area' }}
                                            </span>
                                            @if (auth()->user()->canAccessFeature('delivery.manage', 'edit'))
                                                <button
                                                    type="button"
                                                    class="desk-btn desk-btn-sm"
                                                    wire:click="saveAreaFromInvoice({{ $invoice->id }})"
                                                    title="Save this ship-to as an active delivery area"
                                                >Save area</button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="is-empty"><td colspan="7">No invoices found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="desk-footer">
                    <x-desk-pager :paginator="$invoices" />
                </div>
            </div>
        </div>
    </div>
</div>
