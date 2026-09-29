<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PaymentTerm;
use App\Models\RouteLookup;
use App\Models\SalesOrder;
use App\Models\ShipVia;
use App\Models\Site;
use App\Models\User;
use App\Services\DocumentPdfService;
use App\Services\ItemPriceHistoryService;
use App\Services\Rep\CreateSalesOrderFromRep;
use App\Services\Rep\SalesRepScope;
use App\Support\ItemPricing;
use App\Support\ItemSearch;
use App\Support\StockPolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

    protected function canEditPrice(User $user): bool
    {
        return $user->canAccessFeature('sales.price_override', 'view');
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

    /** One-line ship-to text used as the editable "Shipping address" on checkout. */
    protected static function shipText(?string $address, ?string $city, ?string $state, ?string $zip): string
    {
        return trim(implode(', ', array_filter(array_map(
            fn ($v) => trim((string) $v),
            [$address, $city, $state, $zip]
        ))));
    }

    protected static function normalizeText(?string $text): string
    {
        return strtolower(preg_replace('/[\s,]+/', ' ', trim((string) $text)));
    }

    protected static function orderDisplayTotal(SalesOrder $order): float
    {
        $inv = $order->relationLoaded('invoice') ? $order->invoice : null;

        return $inv ? (float) $inv->invoice_total : (float) $order->total;
    }

    protected function presentOrder(SalesOrder $order, User $user, bool $withLines = true): SalesOrder
    {
        $order->loadMissing(['customer', 'invoice.payments', 'invoice.credits']);
        if ($withLines) {
            $order->loadMissing('lines.item');
        }
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
        $order->can_delete = $this->canDeleteOrders($user) && $order->status === 'New' && ! $invoiced;

        if ($withLines) {
            foreach ($order->lines as $line) {
                $line->variation_id = (int) $line->item_id;
                $line->product_id = (int) $line->item_id;
                $line->quantity = (float) $line->qty_ordered;
                $line->unit_price_inc_tax = (float) $line->price;
                $line->unit_price_before_discount = (float) $line->price;
                $line->item_tax = 0;
                $line->line_discount_amount = (float) $line->discount;
                $line->sub_unit_id = null;
                $line->product = (object) [
                    'name' => $line->description ?: $line->item_code,
                    'unit' => (object) ['actual_name' => $line->uom ?: 'Pc'],
                ];
            }
            $order->setRelation('sell_lines', $order->lines);
        }

        return $order;
    }

    protected function orderAmounts(SalesOrder $order): array
    {
        return $order->portalAmounts();
    }

    protected function priceFor(Item $item, ?Customer $customer): float
    {
        return (float) ItemPricing::resolve(
            $item,
            $customer?->price_level_id ? (int) $customer->price_level_id : null,
            $item->unit_of_measure,
            $customer?->id
        );
    }

    protected function linesFromRequest(Request $request, User $user, Customer $customer): array
    {
        $canPrice = $this->canEditPrice($user);
        $rows = collect($request->input('products', []))->filter(fn ($r) => (float) ($r['quantity'] ?? 0) > 0);
        $items = Item::query()
            ->with('prices')
            ->where('company_id', $user->company_id)
            ->whereIn('id', $rows->map(fn ($r) => (int) ($r['variation_id'] ?? 0))->all())
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($rows as $row) {
            $item = $items->get((int) ($row['variation_id'] ?? 0));
            if (! $item) {
                continue;
            }
            $posted = $row['unit_price'] ?? null;
            $lines[] = [
                'item_code' => $item->item_code,
                'qty_ordered' => (float) $row['quantity'],
                'price' => $canPrice && $posted !== null && $posted !== ''
                    ? round((float) $posted, 4)
                    : $this->priceFor($item, $customer),
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['products' => ['Add at least one product.']]);
        }

        return $lines;
    }

    protected function salePayload(Request $request, User $user, Customer $customer, array $lines, ?SalesOrder $existing = null): array
    {
        $postedAddr = (string) $request->input('shipping_address', '');
        $payload = [
            'lines' => $lines,
            'comments' => $request->input('sale_note'),
            'ship_from_site_id' => $request->integer('location_id') ?: $user->site_id,
            'order_type' => 'Sales Order',
            'order_source' => SalesOrder::SOURCE_SALES,
        ];

        if ($header = $this->checkoutHeader($request, $user, $customer)) {
            return array_merge($payload, $header);
        }

        if ($existing) {
            $payload['ship_via_id'] = $existing->ship_via_id;
            $payload['payment_term_id'] = $existing->payment_term_id;
            $payload['route_id'] = $existing->route_id;
            $payload['ship_date'] = optional($existing->ship_date)->toDateString();
            $default = static::shipText($existing->ship_to_address, $existing->ship_to_city, $existing->ship_to_state, $existing->ship_to_zip);
            if (static::normalizeText($postedAddr) === static::normalizeText($default) || trim($postedAddr) === '') {
                $payload['ship_to_address_id'] = $existing->ship_to_address_id ?: 0;
                foreach (['ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip'] as $k) {
                    $payload[$k] = $existing->{$k};
                }

                return $payload;
            }
        } else {
            $default = $this->mapCustomerForSale($customer)['shipping_address'];
            if (static::normalizeText($postedAddr) === static::normalizeText($default) || trim($postedAddr) === '') {
                $payload['ship_to_address_id'] = -1;

                return $payload;
            }
        }

        $payload['ship_to_address_id'] = 0;
        $payload['ship_to_address'] = trim($postedAddr);

        return $payload;
    }

    protected function saleOrderRules(): array
    {
        return [
            'contact_id' => 'required|integer|exists:customers,id',
            'location_id' => 'required|integer',
            'products' => 'required|array|min:1',
            'products.*.variation_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.unit_price' => 'nullable|numeric|min:0',
            'sale_note' => 'nullable|string|max:2000',
            'shipping_address' => 'nullable|string|max:1000',
            'order_mode' => 'nullable|in:new_order,estimate,back_order',
            'ship_to_address_id' => 'nullable|integer|min:-2',
            'ship_to_name' => 'nullable|string|max:191',
            'ship_to_phone' => 'nullable|string|max:50',
            'ship_to_address' => 'nullable|string|max:255',
            'ship_to_city' => 'nullable|string|max:100',
            'ship_to_state' => 'nullable|string|max:50',
            'ship_to_zip' => 'nullable|string|max:20',
            'ship_via_id' => 'nullable|integer',
            'payment_term_id' => 'nullable|integer',
            'route_id' => 'nullable|integer',
            'ship_date' => 'nullable|date',
        ];
    }

    /** Continental order header fields (Ship Via, Terms, Route, Ship Date, Ship-To) posted from checkout. */
    protected function checkoutHeader(Request $request, User $user, Customer $customer): ?array
    {
        if (! $request->has('ship_to_address_id')) {
            return null;
        }

        $companyId = (int) $user->company_id;
        $pick = fn (string $model, string $key) => $request->filled($key)
            && $model::query()->where('company_id', $companyId)->whereKey($request->integer($key))->exists()
            ? $request->integer($key) : null;

        $header = [
            'ship_via_id' => $pick(ShipVia::class, 'ship_via_id'),
            'payment_term_id' => $pick(PaymentTerm::class, 'payment_term_id'),
            'route_id' => $pick(RouteLookup::class, 'route_id'),
            'ship_date' => $request->input('ship_date') ?: null,
        ];

        $shipTo = $request->integer('ship_to_address_id');
        if ($shipTo === -2) {
            $header['ship_to_address_id'] = 0;
            foreach (['ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip'] as $k) {
                $header[$k] = $request->input($k) ?: null;
            }
        } elseif ($shipTo > 0 && $customer->shippingAddresses()->whereKey($shipTo)->exists()) {
            $header['ship_to_address_id'] = $shipTo;
        } else {
            $header['ship_to_address_id'] = 0;
        }

        return $header;
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
            'shipping_address' => static::shipText($defaultShip['address'], $defaultShip['city'], $defaultShip['state'], $defaultShip['zip']),
            'shipping_addresses' => $mapped,
            'default_ship' => $defaultShip,
            'bill_to' => $bill,
            'credit_limit' => $c->credit_limit !== null ? (float) $c->credit_limit : null,
            'acct' => $c->customer_id,
            'payment_term_id' => $c->payment_term_id ? (int) $c->payment_term_id : null,
            'route_id' => $c->delivery_route_id ? (int) $c->delivery_route_id : null,
        ];
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    protected function mapProducts(Collection $items, ?Customer $customer, User $user): array
    {
        $alerts = app(ItemPriceHistoryService::class)->salesAlertsForItemIds($items->pluck('id')->all());
        $company = StockPolicy::company($user->company);

        return $items->map(function (Item $item) use ($customer, $alerts, $company) {
            $payload = $this->mapProduct($item, $customer, $company);
            $alert = $alerts[(int) $item->id] ?? null;

            return $alert ? array_merge($payload, $alert) : array_merge($payload, ['price_updated' => false, 'alert_type' => null]);
        })->values()->all();
    }

    protected function mapProduct(Item $item, ?Customer $customer = null, $company = null): array
    {
        $item->loadMissing('prices');
        $img = filled($item->image_path) ? url('/media/'.$item->image_path) : null;
        $price = $this->priceFor($item, $customer);

        return [
            'product_id' => (int) $item->id,
            'variation_id' => (int) $item->id,
            'name' => trim($item->description.' ('.$item->item_code.')'),
            'sku' => $item->item_code,
            'price' => $price,
            'base_price' => $price,
            'catalog_base_price' => (float) ($item->list_price ?: $price),
            'stock' => (float) $item->available_quantity,
            'enable_stock' => StockPolicy::allowsOversell($company, $item) ? 0 : 1,
            'product_type' => 'single',
            'unit_id' => 0,
            'unit_name' => $item->unit_of_measure ?: 'Pc',
            'units' => [],
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

    /** Rep sales orders (not returns). */
    protected function repOrders(User $user): Builder
    {
        return SalesRepScope::salesOrdersQuery($user)
            ->where(fn ($q) => $q->whereNull('order_type')->orWhere('order_type', '!=', 'Return'));
    }

    protected function dashboardProduct(Item $item, $company): array
    {
        $img = filled($item->image_path) ? url('/media/'.$item->image_path) : null;

        return [
            'id' => $item->id,
            'variation_id' => $item->id,
            'name' => $item->description ?: $item->item_code,
            'sku' => $item->item_code,
            'price' => (float) ItemPricing::resolve($item, null, $item->unit_of_measure),
            'image' => $img,
            'image_url' => $img,
            'has_image' => (bool) $img,
            'in_stock' => StockPolicy::allowsOversell($company, $item) || (float) $item->available_quantity > 0,
        ];
    }

    public function home(Request $request)
    {
        $user = $this->user()->loadMissing(['company']);
        $company = StockPolicy::company($user->company);

        $monthRows = $this->repOrders($user)
            ->with('invoice:id,sales_order_id,invoice_total')
            ->where('created_at', '>=', now()->startOfMonth())
            ->get(['id', 'total', 'created_at']);
        $todayStart = now()->startOfDay();
        $todayRows = $monthRows->filter(fn ($o) => $o->created_at && $o->created_at->gte($todayStart));
        $sum = fn ($rows) => (float) $rows->sum(fn ($o) => static::orderDisplayTotal($o));

        $stats = [
            'today_orders' => $todayRows->count(),
            'today_total' => $sum($todayRows),
            'month_total' => $sum($monthRows),
            'month_orders' => $monthRows->count(),
            'due_orders' => $this->repOrders($user)->where('status', 'New')->whereDoesntHave('invoice')->count(),
        ];

        $newProducts = Item::query()
            ->with('prices')
            ->where('company_id', $user->company_id)
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Item $item) => $this->dashboardProduct($item, $company))
            ->all();

        // Aggregates hundreds of thousands of lines company-wide; never run it per page view.
        $topIds = Cache::remember('sale.home.top_sellers.'.$user->company_id, now()->addHours(6), function () use ($user) {
            return DB::table('sales_order_lines as l')
                ->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')
                ->where('o.company_id', $user->company_id)
                ->where('o.created_at', '>=', now()->subDays(30))
                ->whereNotNull('l.item_id')
                ->groupBy('l.item_id')
                ->orderByRaw('SUM(l.qty_ordered) DESC')
                ->limit(8)
                ->pluck('l.item_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        });

        $topItems = Item::query()
            ->with('prices')
            ->whereIn('id', $topIds ?: [0])
            ->where('is_inactive', false)
            ->get()
            ->keyBy('id');
        $topProducts = collect($topIds)
            ->map(fn ($id) => $topItems->get($id))
            ->filter()
            ->map(fn (Item $item) => (object) $this->dashboardProduct($item, $company))
            ->values();

        return view('sale.dashboard', [
            'stats' => $stats,
            'userName' => $user->name ?: $user->username,
            'newProducts' => $newProducts,
            'topProducts' => $topProducts,
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
                        ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', $term))
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

        $orders->getCollection()->transform(fn (SalesOrder $o) => $this->presentOrder($o, $user, false));

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

    protected function openBalance(User $user, Customer $customer): float
    {
        return (float) Invoice::previousOpenBalance((int) $user->company_id, (int) $customer->id);
    }

    protected function orderFormData(User $user): array
    {
        $user->loadMissing('company');
        $companyId = (int) $user->company_id;

        return [
            'locations' => $this->locationsFor($user),
            'companyName' => $user->company?->name ?? '',
            'userName' => $user->name ?: $user->username,
            'canEditPrice' => $this->canEditPrice($user),
            'ship_vias' => ShipVia::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'payment_terms' => PaymentTerm::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'days_due']),
            'routes' => RouteLookup::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    protected function lastOrderSummary(User $user, Customer $customer, ?int $exceptOrderId = null): array
    {
        $last = SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->where('customer_id', $customer->id)
            ->where('order_type', '!=', 'Return')
            ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId))
            ->with('invoice:id,sales_order_id,invoice_total')
            ->latest('order_date')
            ->latest('id')
            ->first(['id', 'order_number', 'order_date', 'total']);

        return [
            'last_order_total' => $last ? (float) ($last->invoice?->invoice_total ?? $last->total) : 0.0,
            'last_order_date' => $last?->order_date?->format('m/d/Y'),
            'last_order_number' => $last?->order_number,
        ];
    }

    public function create(Request $request)
    {
        $user = $this->user();
        abort_unless($this->canEditOrders($user), 403);

        $contactId = (int) old('contact_id', $request->get('contact_id'));
        $customer = $contactId > 0
            ? SalesRepScope::companyCustomersQuery($user)->with('shippingAddresses')->find($contactId)
            : null;

        if (! $customer) {
            return redirect()->route('sale.customers', $request->only('add'));
        }

        $mapped = $this->mapCustomerForSale($customer);
        $default_customer = array_merge($mapped, $this->lastOrderSummary($user, $customer), [
            'address' => $mapped['shipping_address'] ?: $mapped['address'],
            'open_balance' => $this->openBalance($user, $customer),
        ]);

        return view('sale.orders.create', array_merge($this->orderFormData($user), [
            'default_location' => $this->defaultLocationId($request, $user),
            'default_customer' => $default_customer,
            'edit_order' => null,
            'edit_lines' => [],
            'checkout' => [
                'order_number' => SalesOrder::nextNumber((int) $user->company_id),
                'ship_to_address_id' => (int) $mapped['default_ship']['id'],
                'ship_via_id' => null,
                'payment_term_id' => $customer->payment_term_id ? (int) $customer->payment_term_id : null,
                'route_id' => $customer->delivery_route_id ? (int) $customer->delivery_route_id : null,
                'ship_date' => null,
                'ship_to' => [],
            ],
        ]));
    }

    public function edit(Request $request, SalesOrder $salesOrder)
    {
        $user = $this->user();
        $this->presentOrder($salesOrder, $user);
        SalesRepScope::assertOrderAccess($user, $salesOrder);
        if (! $salesOrder->can_edit) {
            return redirect()->route('sale.orders.show', $salesOrder)
                ->with('status', ['success' => 0, 'msg' => 'This order is invoiced or closed and cannot be edited.']);
        }

        $customer = $salesOrder->customer;
        $items = Item::query()->with('prices')->whereIn('id', $salesOrder->lines->pluck('item_id')->filter()->all() ?: [0])->get()->keyBy('id');
        $company = StockPolicy::company($user->company);

        $edit_lines = $salesOrder->lines->map(function ($line) use ($items, $customer, $company) {
            $item = $items->get($line->item_id);
            $base = $item ? $this->mapProduct($item, $customer, $company) : [
                'product_id' => (int) $line->item_id,
                'variation_id' => (int) $line->item_id,
                'name' => $line->description,
                'sku' => $line->item_code,
                'stock' => 0,
                'enable_stock' => 0,
                'unit_id' => 0,
                'unit_name' => $line->uom ?: 'Pc',
                'units' => [],
                'allow_decimal' => 1,
            ];

            return array_merge($base, [
                'price' => (float) $line->price,
                'base_price' => (float) $line->price,
                'catalog_base_price' => (float) ($base['catalog_base_price'] ?? $line->price),
                'quantity' => (float) $line->qty_ordered,
                'sub_unit_id' => 0,
            ]);
        })->values()->all();

        $shipText = static::shipText($salesOrder->ship_to_address, $salesOrder->ship_to_city, $salesOrder->ship_to_state, $salesOrder->ship_to_zip);
        $mapped = $customer ? $this->mapCustomerForSale($customer) : [];
        $default_customer = $customer ? array_merge($mapped, $this->lastOrderSummary($user, $customer, (int) $salesOrder->id), [
            'shipping_address' => $shipText,
            'address' => $shipText,
            'open_balance' => $this->openBalance($user, $customer),
        ]) : null;

        $salesOrder->shipping_address = $shipText;

        $knownAddress = $salesOrder->ship_to_address_id
            && $customer?->shippingAddresses->contains('id', (int) $salesOrder->ship_to_address_id);
        $sameAsBilling = $customer
            && static::normalizeText($shipText) === static::normalizeText(static::shipText($customer->address, $customer->city, $customer->state, $customer->zip_code));
        $shipToId = $knownAddress ? (int) $salesOrder->ship_to_address_id : ($sameAsBilling ? 0 : -2);

        return view('sale.orders.create', array_merge($this->orderFormData($user), [
            'default_location' => $salesOrder->ship_from_site_id ?: $this->defaultLocationId($request, $user),
            'default_customer' => $default_customer,
            'edit_order' => $salesOrder,
            'edit_lines' => $edit_lines,
            'checkout' => [
                'order_number' => $salesOrder->order_number,
                'ship_to_address_id' => $shipToId,
                'ship_via_id' => $salesOrder->ship_via_id ? (int) $salesOrder->ship_via_id : null,
                'payment_term_id' => $salesOrder->payment_term_id ? (int) $salesOrder->payment_term_id : null,
                'route_id' => $salesOrder->route_id ? (int) $salesOrder->route_id : null,
                'ship_date' => $salesOrder->ship_date?->format('Y-m-d'),
                'ship_to' => [
                    'name' => $salesOrder->ship_to_name,
                    'phone' => $salesOrder->ship_to_phone,
                    'address' => $salesOrder->ship_to_address,
                    'city' => $salesOrder->ship_to_city,
                    'state' => $salesOrder->ship_to_state,
                    'zip' => $salesOrder->ship_to_zip,
                ],
            ],
        ]));
    }

    public function store(Request $request, CreateSalesOrderFromRep $creator)
    {
        $user = $this->user();
        abort_unless($this->canEditOrders($user), 403);

        $request->validate($this->saleOrderRules());
        $customer = SalesRepScope::companyCustomersQuery($user)->findOrFail($request->integer('contact_id'));

        try {
            $order = $creator->handle($user, $customer, $this->salePayload($request, $user, $customer, $this->linesFromRequest($request, $user, $customer)));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors())
                ->with('status', ['success' => 0, 'msg' => collect($e->errors())->flatten()->first() ?: 'Could not create order.']);
        }

        return redirect()->route('sale.orders.show', $order)->with('status', ['success' => 1, 'msg' => 'Sales order created: '.$order->order_number]);
    }

    public function update(Request $request, SalesOrder $salesOrder, CreateSalesOrderFromRep $creator)
    {
        $user = $this->user();
        abort_unless($this->canEditOrders($user), 403);
        SalesRepScope::assertOrderAccess($user, $salesOrder);

        $request->validate($this->saleOrderRules());
        $customer = SalesRepScope::companyCustomersQuery($user)->findOrFail($request->integer('contact_id'));

        try {
            $order = $creator->rebuild($user, $salesOrder, $customer, $this->salePayload($request, $user, $customer, $this->linesFromRequest($request, $user, $customer), $salesOrder));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors())
                ->with('status', ['success' => 0, 'msg' => collect($e->errors())->flatten()->first() ?: 'Could not update order.']);
        }

        return redirect()->route('sale.orders.show', $order)->with('status', ['success' => 1, 'msg' => 'Sales order updated: '.$order->order_number]);
    }

    public function searchCustomers(Request $request)
    {
        $user = $this->user();
        $term = trim((string) $request->get('q', ''));

        $query = SalesRepScope::companyCustomersQuery($user)
            ->where('is_inactive', false)
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('company_name', 'like', "%{$term}%")
                        ->orWhere('contact', 'like', "%{$term}%")
                        ->orWhere('mobile', 'like', "%{$term}%")
                        ->orWhere('telephone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('customer_id', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%");
                });
            })
            ->with('shippingAddresses');
        Customer::orderForNameSearch($query, $term);
        $rows = Customer::sortedForNameSearch($query->limit(80)->get(), $term);

        $mapped = Customer::sortedForNameSearch(
            $rows->map(fn (Customer $c) => $this->mapCustomerForSale($c))->all(),
            $term
        );

        return response()->json($mapped);
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

        $customer = $contactId > 0
            ? Customer::query()->where('company_id', $user->company_id)->find($contactId)
            : null;

        if ($request->boolean('scan') && $term !== '') {
            $scanned = Item::findByScanCode((int) $user->company_id, $term, 'sell');
            if (! $scanned) {
                return response()->json([]);
            }

            return response()->json($this->mapProducts(collect([$scanned]), $customer, $user));
        }

        $query = Item::query()
            ->with('prices')
            ->where('company_id', $user->company_id)
            ->where('is_inactive', false)
            ->where('can_sell', true);

        if ($variationId > 0) {
            $query->where('id', $variationId);
        }
        if ($term !== '') {
            ItemSearch::constrain($query, $term);
        }
        if ($categoryId === -1) {
            $query->whereNull('category_id');
        } elseif ($subId > 0) {
            $query->where($viaDepartment ? 'category_id' : 'subcategory_id', $subId);
        } elseif ($categoryId > 0) {
            $query->where($viaDepartment ? 'department_id' : 'category_id', $categoryId);
        }

        $items = $query->orderBy('description')->limit($limit)->get();

        return response()->json($this->mapProducts($items, $customer, $user));
    }

    public function lastPurchases(Request $request)
    {
        $user = $this->user();
        $contactId = (int) $request->get('contact_id');
        if ($contactId < 1) {
            return response()->json([]);
        }

        $customer = Customer::query()->where('company_id', $user->company_id)->findOrFail($contactId);

        $orderIds = SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->where('customer_id', $customer->id)
            ->where(fn ($q) => $q->whereNull('order_type')->orWhere('order_type', '!=', 'Return'))
            ->orderByDesc('id')
            ->limit(40)
            ->pluck('id');
        if ($orderIds->isEmpty()) {
            return response()->json([]);
        }

        $lines = DB::table('sales_order_lines as l')
            ->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')
            ->whereIn('l.sales_order_id', $orderIds)
            ->whereNotNull('l.item_id')
            ->orderByDesc('o.id')
            ->orderBy('l.line_no')
            ->get(['l.item_id', 'l.qty_ordered', 'l.price', 'o.created_at', 'o.order_date']);

        $seen = [];
        $firstByItem = [];
        foreach ($lines as $l) {
            $iid = (int) $l->item_id;
            if (! isset($seen[$iid])) {
                $seen[$iid] = true;
                $firstByItem[$iid] = $l;
            }
        }

        $items = Item::query()
            ->with('prices')
            ->where('company_id', $user->company_id)
            ->whereIn('id', array_keys($firstByItem) ?: [0])
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->get()
            ->keyBy('id');
        $company = StockPolicy::company($user->company);

        $out = [];
        foreach ($firstByItem as $iid => $l) {
            $item = $items->get($iid);
            if (! $item) {
                continue;
            }
            $base = $this->mapProduct($item, $customer, $company);
            $date = $l->created_at ? Carbon::parse($l->created_at) : ($l->order_date ? Carbon::parse($l->order_date) : null);
            $out[] = array_merge($base, [
                'quantity' => (float) $l->qty_ordered,
                'last_qty' => (float) $l->qty_ordered,
                'last_price' => (float) $l->price,
                'last_date' => $date?->format('m/d/Y'),
                'sub_unit_id' => 0,
            ]);
        }

        return response()->json($out);
    }

    /**
     * Past order lines for one product (last-ordered popup).
     */
    public function productOrderHistory(Request $request)
    {
        $user = $this->user();
        $contactId = (int) $request->get('contact_id');
        $itemId = (int) $request->get('variation_id');
        if ($contactId < 1 || $itemId < 1) {
            return response()->json([]);
        }

        $customer = Customer::query()->where('company_id', $user->company_id)->findOrFail($contactId);

        $rows = DB::table('sales_order_lines as l')
            ->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')
            ->where('o.company_id', $user->company_id)
            ->where('o.customer_id', $customer->id)
            ->where(fn ($q) => $q->whereNull('o.order_type')->orWhere('o.order_type', '!=', 'Return'))
            ->where('l.item_id', $itemId)
            ->orderByDesc('o.id')
            ->orderBy('l.line_no')
            ->limit(20)
            ->get(['o.order_number', 'o.created_at', 'o.order_date', 'l.qty_ordered', 'l.price', 'l.line_total', 'l.uom']);

        return response()->json($rows->map(function ($r) {
            $date = $r->created_at ? Carbon::parse($r->created_at) : ($r->order_date ? Carbon::parse($r->order_date) : null);
            $qty = (float) $r->qty_ordered;
            $price = (float) $r->price;
            $total = $r->line_total !== null ? (float) $r->line_total : $qty * $price;

            return [
                'order_number' => $r->order_number,
                'date' => $date?->format('m/d/Y') ?? '',
                'date_short' => $date?->format('M j, Y') ?? '',
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => round($total, 2),
                'unit_name' => $r->uom ?: 'Pc',
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
            'locations' => $this->locationsFor($user),
            'default_location' => $this->defaultLocationId($request, $user),
            'categories' => $tree,
            'categoriesJson' => $tree,
        ]);
    }

    public function account(Request $request)
    {
        $user = $this->user();

        return view('sale.account', [
            'user' => $user,
            'locations' => $this->locationsFor($user),
            'current_location_id' => $this->defaultLocationId($request, $user),
        ]);
    }

    public function updateLocation(Request $request)
    {
        $user = $this->user();
        $locations = $this->locationsFor($user);
        $id = (int) $request->validate(['location_id' => 'required|integer'])['location_id'];
        if (! array_key_exists($id, $locations)) {
            return back()->with('status', ['success' => 0, 'msg' => 'Invalid location.']);
        }
        $request->session()->put('user.default_location_id', $id);

        return back()->with('status', ['success' => 1, 'msg' => 'Location updated: '.$locations[$id]]);
    }

    public function updatePassword(Request $request)
    {
        $user = $this->user();
        $data = $request->validate([
            'current_password' => 'required|string|max:191',
            'password' => ['required', 'string', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($data['current_password'], (string) $user->getAuthPassword())) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        if (Hash::check($data['password'], (string) $user->getAuthPassword())) {
            return back()->withErrors(['password' => 'New password must be different from the current password.']);
        }

        $user->password = $data['password'];
        $user->save();

        return back()->with('status', ['success' => 1, 'msg' => 'Password updated. Use it the next time you sign in.']);
    }

    public function delivery(Request $request)
    {
        $user = $this->user()->loadMissing('company');
        abort_unless($user->canAccessFeature('sales.orders', 'view') || $user->isSalesRep(), 403);

        $q = trim((string) $request->get('q', ''));
        $start = (string) $request->get('start_date', now()->subDays(30)->toDateString());
        $end = (string) $request->get('end_date', now()->toDateString());
        try {
            $startAt = Carbon::parse($start)->startOfDay();
            $endAt = Carbon::parse($end)->endOfDay();
        } catch (\Throwable) {
            $startAt = now()->subDays(30)->startOfDay();
            $endAt = now()->endOfDay();
            $start = $startAt->toDateString();
            $end = $endAt->toDateString();
        }

        $company = $user->company;
        $locationId = $this->defaultLocationId($request, $user);
        $originName = trim((string) ($locationId ? ($this->locationsFor($user)[$locationId] ?? '') : '')) ?: trim((string) ($company?->name ?? ''));
        $originCity = trim((string) ($company?->city ?? ''));
        $originZip = (int) preg_replace('/\D+/', '', (string) ($company?->zip_code ?? ''));

        $rows = $this->repOrders($user)
            ->with('customer')
            ->whereBetween('created_at', [$startAt, $endAt])
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.$q.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', $term)
                        ->orWhere('ship_to_address', 'like', $term)
                        ->orWhere('ship_to_city', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', $term)
                            ->orWhere('contact', 'like', $term)
                            ->orWhere('mobile', 'like', $term)
                            ->orWhere('city', 'like', $term));
                });
            })
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        $rows->each(function (SalesOrder $o) {
            $this->presentContact($o->customer);
            $o->setRelation('contact', $o->customer);
            $o->invoice_no = $o->order_number;
            $o->final_total = (float) $o->total;
            $o->shipping_address = static::shipText($o->ship_to_address, $o->ship_to_city, $o->ship_to_state, $o->ship_to_zip);
            $o->shipping_status = $o->delivery_status ? strtolower((string) $o->delivery_status) : null;
        });

        $sorted = $rows->sortBy(function (SalesOrder $o) use ($originZip, $originCity) {
            $zip = (int) preg_replace('/\D+/', '', (string) ($o->ship_to_zip ?: $o->customer?->zip_code));
            $zipDist = $originZip > 0 && $zip > 0 ? abs($zip - $originZip) : ($zip ?: 999999);
            $city = trim((string) ($o->ship_to_city ?: $o->customer?->city));
            $sameCity = ($originCity !== '' && strcasecmp($city, $originCity) === 0) ? 0 : 1;

            return sprintf('%d-%08d-%s', $sameCity, $zipDist, strtolower((string) $o->shipping_address));
        })->values();

        $label = $originName !== '' ? $originName : ($originCity !== '' ? $originCity : 'POS');
        $routes = $sorted->isEmpty() ? collect() : collect([$label => $sorted]);

        return view('sale.delivery', compact('routes', 'start', 'end', 'q', 'originCity', 'originName'));
    }

    public function customers(Request $request)
    {
        $user = $this->user();
        $canList = static::userCanListCustomers();
        $canCreate = static::userCanCreateCustomers();

        if (! $canList && ! $canCreate) {
            return view('sale.customers.no_permission', ['canCreate' => false]);
        }
        if (! $canList) {
            return redirect()->route('sale.customers.create');
        }

        $term = trim((string) $request->get('q', ''));
        $customers = SalesRepScope::companyCustomersQuery($user)
            ->where('is_inactive', false)
            ->with('shippingAddresses')
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($c) use ($term) {
                    $c->where('company_name', 'like', "%{$term}%")
                        ->orWhere('contact', 'like', "%{$term}%")
                        ->orWhere('customer_id', 'like', "%{$term}%")
                        ->orWhere('mobile', 'like', "%{$term}%")
                        ->orWhere('telephone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('company_name')
            ->paginate(20)
            ->appends(['q' => $term]);

        $pageIds = $customers->getCollection()->pluck('id')->all();
        $lastTotals = [];
        if ($pageIds !== []) {
            $lastIds = SalesOrder::query()
                ->where('company_id', $user->company_id)
                ->whereIn('customer_id', $pageIds)
                ->groupBy('customer_id')
                ->selectRaw('customer_id, MAX(id) as last_id')
                ->pluck('last_id', 'customer_id');
            if ($lastIds->isNotEmpty()) {
                $lastTotals = SalesOrder::query()
                    ->whereIn('id', $lastIds->values()->all())
                    ->pluck('total', 'customer_id')
                    ->map(fn ($v) => (float) $v)
                    ->all();
            }
        }

        $customers->getCollection()->transform(function (Customer $c) use ($lastTotals) {
            $this->presentContact($c);
            $c->shipping_address = $this->mapCustomerForSale($c)['shipping_address'];
            $c->last_order_total = $lastTotals[$c->id] ?? 0;

            return $c;
        });

        return view('sale.customers.index', [
            'customers' => $customers,
            'term' => $term,
            'canCreate' => $canCreate,
            'canList' => true,
        ]);
    }

    public function createCustomer()
    {
        if (! static::userCanCreateCustomers()) {
            return view('sale.customers.no_permission', ['action' => 'create']);
        }

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

        return redirect()->route(static::userCanListCustomers() ? 'sale.customers' : 'sale.customers.create')
            ->with('status', ['success' => 1, 'msg' => 'Customer '.$customer->customer_id.' created.']);
    }

    public function searchItems(Request $request)
    {
        return $this->searchProducts($request);
    }
}
