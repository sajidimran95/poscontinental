<?php

use App\Livewire\Concerns\SortsDeskList;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
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
        $this->date = now()->toDateString();
    }

    public function updatingSearch(): void
    {
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

    public function with(): array
    {
        $companyId = (int) auth()->user()->company_id;
        $term = trim($this->search);

        $invoicesQuery = Invoice::query()
            ->with([
                'customer:id,customer_id,company_name,telephone',
                'salesOrder:id,delivery_status,delivery_user_id,ship_to_city,ship_to_state,ship_to_zip,ship_to_address,bill_to_name',
                'salesOrder.deliveryUser:id,name',
            ])
            ->where('company_id', $companyId)
            ->whereNotNull('sales_order_id')
            ->whereHas('salesOrder')
            ->when($this->customer_id, fn ($q) => $q->where('customer_id', $this->customer_id))
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

        $invoices = $this->applyDeskSort($invoicesQuery)->paginate(40);

        $custTerm = trim($this->customerSearch);
        $customerSuggestions = (! $this->customer_id && $custTerm !== '')
            ? Customer::query()
                ->where('company_id', $companyId)
                ->where('is_inactive', false)
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

        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');

        return [
            'invoices' => $invoices,
            'customerSuggestions' => $customerSuggestions,
            'selectedCount' => collect($this->selected)->filter()->count(),
            'visibleIds' => $invoices->pluck('id')->all(),
            'drivers' => User::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->when($deliveryRoleId, fn ($q) => $q->where('role_id', $deliveryRoleId))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    protected function deskSortMap(): array
    {
        return [
            'invoice_number' => 'invoice_number',
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
            <div class="dlv-banner is-ok">{{ $statusMessage }}</div>
        @endif
        @if ($errorMessage !== '')
            <div class="dlv-banner is-err">{{ $errorMessage }}</div>
        @endif

        <div class="desk-toolbar orders-toolbar">
            <div class="dlv-cust-pick">
                <div class="dlv-cust-field">
                    <input
                        id="dlv-customer"
                        type="search"
                        class="desk-search orders-search-input"
                        wire:model.live.debounce.200ms="customerSearch"
                        placeholder="Customer ID or name…"
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
                <input type="date" class="desk-input" wire:model="date" aria-label="Delivery date" />
                <button type="button" class="desk-btn desk-btn-primary" wire:click="assign" @disabled(! auth()->user()->canAccessFeature('delivery.manage', 'edit'))>Assign selected</button>
                <button type="button" class="desk-btn" wire:click="generate" @disabled(! auth()->user()->canAccessFeature('delivery.manage', 'edit'))>Generate route</button>
            </div>
        </div>

        <div class="desk-titlebar">
            <h2 class="desk-title">Delivery Management</h2>
            <span class="desk-title-meta">{{ number_format($invoices->total()) }} invoices</span>
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
                            <col style="width:12%" />
                            <col style="width:38%" />
                            <col style="width:22%" />
                            <col style="width:12%" />
                            <col style="width:14%" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="text-center"></th>
                                <x-desk-sort-th field="invoice_number" label="Invoice #" />
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
                                @endphp
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" wire:model="selected.{{ $invoice->id }}" aria-label="Select invoice {{ $invoice->invoice_number }}" />
                                    </td>
                                    <td class="desk-num">{{ $invoice->invoice_number }}</td>
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
                                    </td>
                                </tr>
                            @empty
                                <tr class="is-empty"><td colspan="6">No invoices found.</td></tr>
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
