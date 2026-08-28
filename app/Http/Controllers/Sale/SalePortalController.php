<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PaymentTerm;
use App\Models\RouteLookup;
use App\Models\SalesOrder;
use App\Models\ShipVia;
use App\Models\Site;
use App\Models\User;
use App\Services\DocumentPdfService;
use App\Services\Rep\CreateSalesOrderFromRep;
use App\Services\Rep\SalesRepScope;
use App\Support\ItemPricing;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalePortalController extends Controller
{
    protected function user(): User
    {
        return auth('sale')->user();
    }

    public static function userCanListCustomers(): bool
    {
        $user = auth('sale')->user();

        return $user && ($user->isSalesRep() || $user->canAccessFeature('sales.customers', 'view'));
    }

    public static function userCanCreateCustomers(): bool
    {
        $user = auth('sale')->user();

        return $user && ($user->isSalesRep() || $user->canAccessFeature('sales.customers', 'edit'));
    }

    public static function userCanAccessCustomers(): bool
    {
        return static::userCanListCustomers() || static::userCanCreateCustomers();
    }

    protected function canEditOrders(User $user): bool
    {
        return $user->isSalesRep() || $user->canAccessFeature('sales.orders', 'edit');
    }

    protected function canDeleteOrders(User $user): bool
    {
        return $user->isSalesRep() || $user->canAccessFeature('sales.orders', 'delete');
    }

    protected function locationsFor(User $user): array
    {
        return Site::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function defaultLocationId(Request $request, User $user): ?int
    {
        $locations = $this->locationsFor($user);
        $ids = array_map('strval', array_keys($locations));
        $current = $request->session()->get('user.default_location_id') ?: $user->site_id;
        if (empty($current) || ! in_array((string) $current, $ids, true)) {
            $current = count($locations) ? (int) array_key_first($locations) : null;
            if ($current) {
                $request->session()->put('user.default_location_id', $current);
            }
        }

        return $current ? (int) $current : null;
    }

    protected function presentContact(?Customer $customer): ?Customer
    {
        if (! $customer) {
            return null;
        }

        $customer->supplier_business_name = $customer->company_name;
        $customer->name = $customer->contact ?: $customer->company_name;
        $customer->contact_id = $customer->customer_id;
        $customer->address_line_1 = $customer->address;
        if (! filled($customer->mobile)) {
            $customer->mobile = $customer->telephone;
        }

        return $customer;
    }

    protected function presentOrder(SalesOrder $order, User $user): SalesOrder
    {
        $order->loadMissing(['customer', 'lines.item', 'invoice.payments', 'invoice.credits']);
        $this->presentContact($order->customer);
        $order->setRelation('contact', $order->customer);
        $order->invoice_no = $order->order_number;
        $order->transaction_date = $order->created_at ?? $order->order_date;
        $order->final_total = (float) $order->total;
        $order->sale_display_total = (float) $order->total;
        $order->loadMissing(['shipVia', 'route', 'paymentTerm', 'shipFromSite']);
        $order->additional_notes = $order->comments;
        $order->shipping_address = trim(implode("\n", array_filter([
            $order->ship_to_name,
            $order->ship_to_address,
            trim(implode(', ', array_filter([$order->ship_to_city, $order->ship_to_state, $order->ship_to_zip]))),
            $order->ship_to_phone,
        ])));
        $order->shipping_details = collect([
            $order->shipVia?->name ? 'Ship Via: '.$order->shipVia->name : null,
            $order->route?->name ? 'Route: '.$order->route->name : null,
            $order->paymentTerm?->name ? 'Terms: '.$order->paymentTerm->name : null,
            $order->shipFromSite?->name ? 'Ship From: '.$order->shipFromSite->name : null,
            $order->ship_date ? 'Ship Date: '.$order->ship_date->format('M j, Y') : null,
        ])->filter()->implode("\n");
        $order->shipping_method = $order->shipVia?->name;
        $order->shipping_status = null;
        $invoiced = (bool) $order->invoice;
        $order->applyInvoiceForPortal();
        $type = (string) $order->order_type;
        if ($invoiced) {
            $order->sale_status = 'invoiced';
        } elseif ($type === 'Return') {
            $order->sale_status = 'return';
        } else {
            $order->sale_status = 'sale';
        }

        $order->can_show_edit = $this->canEditOrders($user);
        $order->can_edit = $order->can_show_edit && $order->status === 'New' && ! $invoiced;

        foreach ($order->lines as $line) {
            $line->quantity = (float) $line->qty_ordered;
            $line->unit_price_inc_tax = (float) $line->price;
            $line->item_tax = 0;
            $line->line_discount_amount = (float) $line->discount;
            $line->product = (object) ['name' => $line->description ?: $line->item_code];
        }
        $order->setRelation('sell_lines', $order->lines);

        return $order;
    }

    protected function orderAmounts(SalesOrder $order): array
    {
        return $order->portalAmounts();
    }

    protected function saleOrderType(?string $mode): string
    {
        return match ($mode) {
            'return' => 'Return',
            default => 'Sales Order',
        };
    }

    protected function linesFromRequest(Request $request, User $user): array
    {
        $lines = [];
        foreach ($request->input('products', []) as $row) {
            $item = Item::query()
                ->where('company_id', $user->company_id)
                ->where('id', (int) ($row['variation_id'] ?? 0))
                ->first();
            if (! $item) {
                continue;
            }
            $lines[] = [
                'item_code' => $item->item_code,
                'qty_ordered' => $row['quantity'],
                'price' => $row['unit_price'] ?? null,
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['products' => ['Add at least one product.']]);
        }

        return $lines;
    }

    protected function salePayload(Request $request, User $user, array $lines): array
    {
        return [
            'lines' => $lines,
            'comments' => $request->input('sale_note'),
            'ship_to_address_id' => $request->filled('ship_to_address_id') ? $request->integer('ship_to_address_id') : 0,
            'ship_to_name' => $request->input('ship_to_name'),
            'ship_to_phone' => $request->input('ship_to_phone'),
            'ship_to_address' => $request->input('ship_to_address'),
            'ship_to_city' => $request->input('ship_to_city'),
            'ship_to_state' => $request->input('ship_to_state'),
            'ship_to_zip' => $request->input('ship_to_zip'),
            'ship_via_id' => $request->integer('ship_via_id') ?: null,
            'payment_term_id' => $request->integer('payment_term_id') ?: null,
            'route_id' => $request->integer('route_id') ?: null,
            'ship_date' => $request->input('ship_date') ?: null,
            'ship_from_site_id' => $request->integer('location_id') ?: $user->site_id,
            'order_type' => $this->saleOrderType($request->input('order_mode')),
            'order_source' => SalesOrder::SOURCE_SALES,
        ];
    }

    protected function saleOrderRules(): array
    {
        return [
            'contact_id' => 'required|integer|exists:customers,id',
            'location_id' => 'required|integer',
            'products' => 'required|array|min:1',
            'ship_to_address_id' => 'nullable|integer',
            'ship_to_name' => 'nullable|string|max:255',
            'ship_to_phone' => 'nullable|string|max:50',
            'ship_to_address' => 'nullable|string|max:1000',
            'ship_to_city' => 'nullable|string|max:100',
            'ship_to_state' => 'nullable|string|max:50',
            'ship_to_zip' => 'nullable|string|max:20',
            'ship_via_id' => 'nullable|integer',
            'payment_term_id' => 'nullable|integer',
            'route_id' => 'nullable|integer',
            'ship_date' => 'nullable|date',
            'sale_note' => 'nullable|string|max:2000',
            'order_mode' => 'nullable|in:new_order,return',
        ];
    }

    protected function saleFormLookups(User $user): array
    {
        $companyId = $user->company_id;

        return [
            'locations' => $this->locationsFor($user),
            'ship_vias' => ShipVia::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'payment_terms' => PaymentTerm::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'routes' => RouteLookup::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function mapCustomerForSale(Customer $c): array
    {
        $c->loadMissing('shippingAddresses');
        $addresses = $c->shippingAddresses
            ->sortBy([
                ['is_primary', 'desc'],
                ['sort_order', 'asc'],
            ])
            ->values();

        $mapped = $addresses->map(fn ($a) => [
            'id' => (int) $a->id,
            'name' => $a->name ?: ($a->address ?: 'Ship-To #'.$a->id),
            'address' => (string) ($a->address ?? ''),
            'city' => (string) ($a->city ?? ''),
            'state' => (string) ($a->state ?? ''),
            'zip' => (string) ($a->zip ?? ''),
            'telephone' => (string) ($a->telephone ?? ''),
            'is_primary' => (bool) $a->is_primary,
        ])->all();

        $bill = [
            'id' => 0,
            'name' => trim((string) ($c->company_name ?: $c->contact)) ?: 'Billing address',
            'address' => (string) ($c->address ?? ''),
            'city' => (string) ($c->city ?? ''),
            'state' => (string) ($c->state ?? ''),
            'zip' => (string) ($c->zip_code ?? ''),
            'telephone' => (string) ($c->telephone ?: $c->mobile ?: ''),
            'is_primary' => $mapped === [],
        ];

        $default = $addresses->firstWhere('is_primary', true) ?? $addresses->first();
        $defaultShip = $default ? [
            'id' => (int) $default->id,
            'name' => $default->name ?: $bill['name'],
            'address' => (string) ($default->address ?? ''),
            'city' => (string) ($default->city ?? ''),
            'state' => (string) ($default->state ?? ''),
            'zip' => (string) ($default->zip ?? ''),
            'telephone' => (string) ($default->telephone ?: $bill['telephone']),
        ] : $bill;

        $display = trim((string) ($c->company_name ?: $c->contact));
        $address = trim(implode(', ', array_filter([$c->address, $c->city])));
        $parts = preg_split('/\s+/', preg_replace('/[^A-Za-z0-9\s]/', '', $display) ?: 'C') ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $initials .= strtoupper(mb_substr($p, 0, 1));
        }

        return [
            'id' => $c->id,
            'text' => trim(($c->company_name ? $c->company_name.' — ' : '').($c->contact ?: '').(($c->mobile ?: $c->telephone) ? ' ('.($c->mobile ?: $c->telephone).')' : '')),
            'display_name' => $display,
            'name' => $c->contact ?: $c->company_name,
            'mobile' => $c->mobile ?: $c->telephone,
            'address' => $address,
            'initials' => $initials !== '' ? $initials : 'C',
            'shipping_address' => trim(implode(', ', array_filter([
                $defaultShip['address'],
                $defaultShip['city'],
                $defaultShip['state'],
                $defaultShip['zip'],
            ]))),
            'shipping_addresses' => $mapped,
            'default_ship' => $defaultShip,
            'payment_term_id' => $c->payment_term_id ? (int) $c->payment_term_id : null,
            'route_id' => $c->delivery_route_id ? (int) $c->delivery_route_id : null,
        ];
    }

    protected function mapProduct(Item $item, ?Customer $customer = null): array
    {
        $item->loadMissing('prices');
        $img = filled($item->image_path) ? url('/media/'.$item->image_path) : null;
        $price = ItemPricing::resolve(
            $item,
            $customer?->price_level_id ? (int) $customer->price_level_id : null,
            $item->unit_of_measure,
            $customer?->id
        );

        return [
            'product_id' => (int) $item->id,
            'variation_id' => (int) $item->id,
            'name' => trim($item->description.' ('.$item->item_code.')'),
            'sku' => $item->item_code,
            'price' => $price,
            'stock' => (float) $item->available_quantity,
            'enable_stock' => 1,
            'product_type' => 'single',
            'allow_decimal' => 1,
            'tax_id' => null,
            'category_id' => (int) ($item->category_id ?: 0),
            'sub_category_id' => (int) ($item->subcategory_id ?: 0),
            'image' => $img,
            'has_image' => (bool) $img,
        ];
    }

    protected function categoryTree(User $user): array
    {
        $tree = Category::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->with(['subcategories' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $cat) => [
                'id' => (int) $cat->id,
                'name' => $cat->name,
                'sub_categories' => $cat->subcategories->map(fn ($s) => [
                    'id' => (int) $s->id,
                    'name' => $s->name,
                ])->values(),
            ])
            ->values()
            ->all();

        if ($tree === []) {
            $tree = \App\Models\Department::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->with(['categories' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn ($dept) => [
                    'id' => (int) $dept->id,
                    'name' => $dept->name,
                    'via_department' => true,
                    'sub_categories' => $dept->categories->map(fn ($c) => [
                        'id' => (int) $c->id,
                        'name' => $c->name,
                    ])->values(),
                ])
                ->values()
                ->all();
        }

        $hasUncategorized = Item::query()
            ->where('company_id', $user->company_id)
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->whereNull('category_id')
            ->exists();

        if ($hasUncategorized) {
            array_unshift($tree, [
                'id' => -1,
                'name' => 'Uncategorized',
                'sub_categories' => [],
            ]);
        }

        return $tree;
    }

    public function home(Request $request)
    {
        $user = $this->user()->loadMissing(['role', 'site', 'company']);
        $orders = SalesRepScope::salesOrdersQuery($user);
        $site = $user->site;
        $company = $user->company;

        $stats = [
            'today_total' => (clone $orders)->whereDate('order_date', now()->toDateString())->sum('total'),
            'month_total' => (clone $orders)->where('order_date', '>=', now()->startOfMonth()->toDateString())->sum('total'),
            'total_orders' => (clone $orders)->count(),
        ];

        $location = (object) [
            'name' => $site->name ?? ($company->name ?? '—'),
            'landmark' => $company->address ?? '—',
            'city' => $company->city ?? null,
            'state' => $company->state ?? null,
            'zip_code' => $company->zip_code ?? null,
            'mobile' => $company->phone ?? null,
            'alternate_number' => null,
        ];

        return view('sale.dashboard', [
            'stats' => $stats,
            'location' => $location,
            'role_name' => $user->role?->label ?? 'Sales Rep',
            'current_location_name' => $location->name,
        ]);
    }

    public function orders(Request $request)
    {
        $user = $this->user();
        abort_unless($user->canAccessFeature('sales.orders', 'view') || $user->isSalesRep(), 403);

        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');

        $orders = SalesRepScope::salesOrdersQuery($user)
            ->with(['customer', 'invoice.payments', 'invoice.credits'])
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.$q.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', $term)
                        ->orWhereHas('customer', function ($c) use ($term) {
                            $c->where('company_name', 'like', $term)
                                ->orWhere('contact', 'like', $term)
                                ->orWhere('customer_id', 'like', $term)
                                ->orWhere('mobile', 'like', $term);
                        });
                });
            })
            ->when($status === 'return', fn ($query) => $query->where('order_type', 'Return'))
            ->when($status === 'sale', fn ($query) => $query->where('order_type', 'Sales Order')->whereDoesntHave('invoice'))
            ->when($status === 'invoiced', fn ($query) => $query->whereHas('invoice'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $orders->getCollection()->transform(fn (SalesOrder $o) => $this->presentOrder($o, $user));

        return view('sale.orders.index', ['orders' => $orders, 'q' => $q, 'status' => $status]);
    }

    public function show(SalesOrder $salesOrder)
    {
        $user = $this->user();
        $this->presentOrder($salesOrder, $user);
        SalesRepScope::assertOrderAccess($user, $salesOrder);

        return view('sale.orders.show', [
            'order' => $salesOrder,
            'amounts' => $this->orderAmounts($salesOrder),
        ]);
    }

    public function downloadInvoice(SalesOrder $salesOrder, DocumentPdfService $pdfs)
    {
        $user = $this->user();
        SalesRepScope::assertOrderAccess($user, $salesOrder);
        $salesOrder->loadMissing('invoice');
        if ($salesOrder->invoice) {
            return $pdfs->streamInvoice($salesOrder->invoice);
        }

        return $pdfs->streamSalesOrderInvoiceStyle($salesOrder, $user);
    }

    public function destroy(SalesOrder $salesOrder)
    {
        $user = $this->user();
        abort_unless($this->canDeleteOrders($user), 403);
        SalesRepScope::assertOrderAccess($user, $salesOrder);
        abort_unless($salesOrder->status === 'New' && ! $salesOrder->invoice()->exists(), 403, 'Only new orders can be deleted.');

        $no = $salesOrder->order_number;
        $salesOrder->lines()->delete();
        $salesOrder->delete();

        return redirect()->route('sale.orders')->with('status', ['success' => 1, 'msg' => 'Order '.$no.' deleted.']);
    }

    public function create(Request $request)
    {
        $user = $this->user();
        abort_unless($this->canEditOrders($user), 403);

        $customers = SalesRepScope::companyCustomersQuery($user)
            ->where('is_inactive', false)
            ->orderBy('company_name')
            ->get(['id', 'customer_id', 'company_name', 'contact', 'mobile', 'telephone', 'address', 'city']);

        $default_customer = null;
        $oldContactId = old('contact_id');
        if (! empty($oldContactId)) {
            $c = $customers->firstWhere('id', (int) $oldContactId)
                ?? Customer::query()->find($oldContactId);
            if ($c && (int) $c->company_id === (int) $user->company_id) {
                $this->presentContact($c);
                $default_customer = [
                    'id' => $c->id,
                    'text' => trim(($c->company_name ?: $c->contact).(($c->mobile ?: $c->telephone) ? ' ('.($c->mobile ?: $c->telephone).')' : '')),
                    'shipping_address' => trim(implode(', ', array_filter([$c->address, $c->city]))),
                ];
            }
        }

        return view('sale.orders.create', array_merge($this->saleFormLookups($user), [
            'customers' => $customers,
            'default_location' => $this->defaultLocationId($request, $user),
            'default_customer' => $default_customer,
            'edit_order' => null,
            'edit_lines' => [],
        ]));
    }

    public function edit(Request $request, SalesOrder $salesOrder)
    {
        $user = $this->user();
        $this->presentOrder($salesOrder, $user);
        SalesRepScope::assertOrderAccess($user, $salesOrder);
        abort_unless($salesOrder->can_edit, 403, 'This order cannot be edited.');

        $edit_lines = $salesOrder->lines->map(fn ($line) => [
            'product_id' => (int) $line->item_id,
            'variation_id' => (int) $line->item_id,
            'name' => $line->description,
            'price' => (float) $line->price,
            'quantity' => (float) $line->qty_ordered,
            'enable_stock' => 1,
            'product_type' => 'single',
            'allow_decimal' => 1,
        ])->values()->all();

        $cust = $salesOrder->customer;
        $this->presentContact($cust);

        return view('sale.orders.create', array_merge($this->saleFormLookups($user), [
            'customers' => SalesRepScope::companyCustomersQuery($user)->where('is_inactive', false)->orderBy('company_name')->get(['id', 'customer_id', 'company_name', 'contact', 'mobile']),
            'default_location' => $salesOrder->ship_from_site_id ?: $this->defaultLocationId($request, $user),
            'default_customer' => $cust ? [
                'id' => $cust->id,
                'text' => trim(($cust->company_name ?: $cust->contact).($cust->mobile ? ' ('.$cust->mobile.')' : '')),
            ] : null,
            'edit_order' => $salesOrder,
            'edit_lines' => $edit_lines,
        ]));
    }

    public function store(Request $request, CreateSalesOrderFromRep $creator)
    {
        $user = $this->user();
        abort_unless($this->canEditOrders($user), 403);

        $request->validate($this->saleOrderRules());

        $customer = Customer::query()->findOrFail($request->integer('contact_id'));

        try {
            $order = $creator->handle($user, $customer, $this->salePayload($request, $user, $this->linesFromRequest($request, $user)));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('sale.orders.show', $order)->with('status', ['success' => 1, 'msg' => 'Order '.$order->order_number.' created.']);
    }

    public function update(Request $request, SalesOrder $salesOrder, CreateSalesOrderFromRep $creator)
    {
        $user = $this->user();
        abort_unless($this->canEditOrders($user), 403);
        SalesRepScope::assertOrderAccess($user, $salesOrder);

        $request->validate($this->saleOrderRules());

        $customer = Customer::query()->findOrFail($request->integer('contact_id'));

        try {
            $order = $creator->rebuild($user, $salesOrder, $customer, $this->salePayload($request, $user, $this->linesFromRequest($request, $user)));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('sale.orders.show', $order)->with('status', ['success' => 1, 'msg' => 'Order '.$order->order_number.' updated.']);
    }

    public function searchCustomers(Request $request)
    {
        $user = $this->user();
        $term = trim((string) $request->get('q', ''));

        $rows = SalesRepScope::companyCustomersQuery($user)
            ->where('is_inactive', false)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('company_name', 'like', "%{$term}%")
                        ->orWhere('contact', 'like', "%{$term}%")
                        ->orWhere('mobile', 'like', "%{$term}%")
                        ->orWhere('telephone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('customer_id', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%");
                });
            })
            ->with('shippingAddresses')
            ->orderBy('company_name')
            ->limit(40)
            ->get();

        return response()->json($rows->map(fn (Customer $c) => $this->mapCustomerForSale($c))->values());
    }

    public function customerShipping(Customer $customer)
    {
        $user = $this->user();
        abort_unless((int) $customer->company_id === (int) $user->company_id, 404);

        return response()->json($this->mapCustomerForSale($customer));
    }

    public function searchProducts(Request $request)
    {
        $user = $this->user();
        $term = trim((string) $request->get('q', ''));
        $categoryId = (int) $request->get('category_id', 0);
        $subId = (int) $request->get('sub_category_id', 0);
        $variationId = (int) $request->get('variation_id', 0);
        $contactId = (int) $request->get('contact_id', 0);
        $limit = min(100, max(1, (int) $request->get('limit', 30)));
        $viaDepartment = (int) $request->get('via_department', 0) === 1
            || ($categoryId > 0 && ! Category::query()->where('company_id', $user->company_id)->whereKey($categoryId)->exists());

        $customer = null;
        if ($contactId > 0) {
            $customer = Customer::query()
                ->where('company_id', $user->company_id)
                ->find($contactId);
        }

        $query = Item::query()
            ->with('prices')
            ->where('company_id', $user->company_id)
            ->where('is_inactive', false)
            ->where('can_sell', true);

        if ($variationId > 0) {
            $query->where('id', $variationId);
        }
        if ($request->boolean('scan') && $term !== '') {
            $scanned = Item::findByScanCode((int) $user->company_id, $term, 'sell');
            if (! $scanned) {
                return response()->json([]);
            }

            return response()->json([$this->mapProduct($scanned, $customer)]);
        }
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('item_code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('primary_upc', 'like', "%{$term}%");
            });
        }
        if ($categoryId === -1) {
            $query->whereNull('category_id');
        } elseif ($subId > 0) {
            if ($viaDepartment) {
                $query->where('category_id', $subId);
            } else {
                $query->where('subcategory_id', $subId);
            }
        } elseif ($categoryId > 0) {
            if ($viaDepartment) {
                $query->where('department_id', $categoryId);
            } else {
                $query->where('category_id', $categoryId);
            }
        }

        $items = $query->orderBy('description')->limit($limit)->get();

        return response()->json($items->map(fn (Item $item) => $this->mapProduct($item, $customer))->values());
    }

    public function lastPurchases(Request $request)
    {
        $user = $this->user();
        $contactId = (int) $request->get('contact_id');
        if ($contactId < 1) {
            return response()->json([]);
        }

        $customer = Customer::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($contactId);

        $last = SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->first();

        if (! $last) {
            return response()->json([]);
        }

        $last->load('lines.item');

        return response()->json($last->lines->map(function ($line) {
            $item = $line->item;

            return [
                'product_id' => (int) $line->item_id,
                'variation_id' => (int) $line->item_id,
                'name' => $line->description.($line->item_code ? ' ('.$line->item_code.')' : ''),
                'sku' => $line->item_code,
                'price' => (float) $line->price,
                'quantity' => (float) $line->qty_ordered,
                'stock' => $item ? (float) $item->available_quantity : 0,
                'enable_stock' => 1,
                'product_type' => 'single',
                'allow_decimal' => 1,
            ];
        })->values());
    }

    public function categoriesTree()
    {
        return response()->json($this->categoryTree($this->user()));
    }

    public function products(Request $request)
    {
        $user = $this->user();
        $tree = $this->categoryTree($user);

        return view('sale.products.index', [
            'default_location' => $this->defaultLocationId($request, $user),
            'categories' => $tree,
            'categoriesJson' => $tree,
        ]);
    }

    public function account(Request $request)
    {
        $user = $this->user();
        $locations = $this->locationsFor($user);

        return view('sale.account', [
            'user' => $user,
            'locations' => $locations,
            'current_location_id' => $this->defaultLocationId($request, $user),
        ]);
    }

    public function updateLocation(Request $request)
    {
        $user = $this->user();
        $locations = $this->locationsFor($user);
        $id = (int) $request->validate(['location_id' => 'required|integer'])['location_id'];
        abort_unless(array_key_exists($id, $locations), 422, 'Invalid location.');
        $request->session()->put('user.default_location_id', $id);

        return back()->with('status', ['success' => 1, 'msg' => 'Default location saved.']);
    }

    public function delivery(Request $request)
    {
        $user = $this->user();
        abort_unless($user->canAccessFeature('sales.orders', 'view') || $user->isSalesRep(), 403);

        $q = trim((string) $request->get('q', ''));
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        $orders = SalesRepScope::salesOrdersQuery($user)
            ->with('customer')
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.$q.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', $term)->orWhere('contact', 'like', $term));
                });
            })
            ->when($start, fn ($query) => $query->whereDate('order_date', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('order_date', '<=', $end))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $orders->getCollection()->transform(fn (SalesOrder $o) => $this->presentOrder($o, $user));

        return view('sale.delivery', compact('orders', 'q', 'start', 'end'));
    }

    public function customers(Request $request)
    {
        $user = $this->user();
        $canList = static::userCanListCustomers();
        $canCreate = static::userCanCreateCustomers();
        abort_unless($canList || $canCreate, 403);

        if (! $canList) {
            return view('sale.customers.no_permission', ['canCreate' => $canCreate]);
        }

        $term = trim((string) $request->get('q', ''));
        $customers = SalesRepScope::customersQuery($user)
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($c) use ($term) {
                    $c->where('company_name', 'like', "%{$term}%")
                        ->orWhere('contact', 'like', "%{$term}%")
                        ->orWhere('customer_id', 'like', "%{$term}%")
                        ->orWhere('mobile', 'like', "%{$term}%");
                });
            })
            ->orderBy('company_name')
            ->paginate(30)
            ->withQueryString();

        $customers->getCollection()->transform(fn (Customer $c) => $this->presentContact($c) ?? $c);

        return view('sale.customers.index', [
            'customers' => $customers,
            'term' => $term,
            'canCreate' => $canCreate,
            'canList' => true,
        ]);
    }

    public function createCustomer()
    {
        abort_unless(static::userCanCreateCustomers(), 403);

        return view('sale.customers.create', [
            'canList' => static::userCanListCustomers(),
        ]);
    }

    public function storeCustomer(Request $request)
    {
        $user = $this->user();
        abort_unless(static::userCanCreateCustomers(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:191',
            'supplier_business_name' => 'nullable|string|max:191',
            'mobile' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:191',
            'address_line_1' => 'nullable|string|max:255',
        ]);

        $code = 'R'.now()->format('ymdHis');
        while (Customer::query()->where('company_id', $user->company_id)->where('customer_id', $code)->exists()) {
            $code = 'R'.now()->format('ymdHis').random_int(10, 99);
        }

        $customer = Customer::query()->create([
            'company_id' => $user->company_id,
            'customer_id' => $code,
            'contact' => $data['name'],
            'company_name' => $data['supplier_business_name'] ?: $data['name'],
            'mobile' => $data['mobile'] ?? null,
            'telephone' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address_line_1'] ?? null,
            'sales_rep_id' => $user->id,
            'is_inactive' => false,
            'customer_since' => now()->toDateString(),
        ]);

        return redirect()->route('sale.customers')->with('status', [
            'success' => 1,
            'msg' => 'Customer '.$customer->customer_id.' created.',
        ]);
    }

    public function searchItems(Request $request)
    {
        return $this->searchProducts($request);
    }
}
