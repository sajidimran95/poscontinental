<?php

namespace App\Http\Controllers\CustomerApp;

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
use App\Services\Rep\CreateSalesOrderFromRep;
use App\Support\ItemPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CustomerPortalController extends Controller
{
    protected function customer(): Customer
    {
        return auth('customer')->user();
    }

    protected function actingRep(Customer $customer): User
    {
        if ($customer->sales_rep_id) {
            $rep = User::query()->find($customer->sales_rep_id);
            if ($rep && (int) $rep->company_id === (int) $customer->company_id) {
                return $rep;
            }
        }

        $rep = User::query()
            ->where('company_id', $customer->company_id)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();

        abort_unless($rep, 422, 'No staff user is available to own this order. Ask the office.');

        return $rep;
    }

    public function home()
    {
        $customer = $this->customer()->load('company');
        $this->presentContact($customer);
        $due = (float) $customer->balance;
        $business = $customer->company;
        $contact = $customer;
        $locations = $this->customerLocations($customer);
        $defaultId = $this->defaultLocationId($customer, $locations);
        $location = Site::query()->find($defaultId);
        $newArrivals = $this->productCards($customer, 8, 'newest');
        $topProducts = $this->productCards($customer, 8, 'top');
        $initials = strtoupper(substr($customer->displayName(), 0, 1));

        return view('customer.home', compact(
            'contact', 'due', 'locations', 'location', 'business', 'newArrivals', 'topProducts', 'initials'
        ));
    }

    public function account()
    {
        return redirect()->route('customer.profile');
    }

    public function profile()
    {
        $contact = $this->presentContact($this->customer());
        $initials = strtoupper(substr($contact->displayName(), 0, 1));
        $locations = $this->customerLocations($contact);
        $current_location_id = $this->defaultLocationId($contact, $locations);

        return view('customer.profile', compact('contact', 'initials', 'locations', 'current_location_id'));
    }

    public function updateLocation(Request $request)
    {
        $customer = $this->customer();
        $data = $request->validate(['location_id' => 'required|integer']);
        $locations = $this->customerLocations($customer);
        $locationId = (int) $data['location_id'];
        if (! $locations->keys()->contains(fn ($id) => (int) $id === $locationId)) {
            return back()->with('error', 'Invalid location.');
        }
        session(['customer.default_location_id' => $locationId]);

        return back()->with('success', 'Location updated: '.($locations[$locationId] ?? ''));
    }

    public function updatePassword(Request $request)
    {
        $customer = $this->customer();
        $data = $request->validate([
            'current_password' => 'required|string|max:191',
            'password' => ['required', 'string', 'confirmed', Password::min(4)],
        ]);
        if (! Hash::check($data['current_password'], (string) $customer->portal_password)) {
            return back()->with('error', 'Current password is incorrect.');
        }
        $customer->portal_password = Hash::make($data['password']);
        $customer->save();

        return back()->with('success', 'Password updated successfully.');
    }

    public function documents(Request $request)
    {
        $customer = $this->presentContact($this->customer());
        $start = trim((string) $request->get('start_date', ''));
        $end = trim((string) $request->get('end_date', ''));
        $q = trim((string) $request->get('q', ''));
        $hasDateFilter = ($start !== '' || $end !== '');

        $query = Invoice::query()
            ->with('payments')
            ->where('customer_id', $customer->id);

        if ($start !== '') {
            $query->whereDate('invoice_date', '>=', $start);
        }
        if ($end !== '') {
            $query->whereDate('invoice_date', '<=', $end);
        }
        if ($q !== '') {
            $query->where('invoice_number', 'like', '%'.$q.'%');
        }

        $invoices = $query->orderByDesc('invoice_date')->orderByDesc('id')->get()->map(function (Invoice $inv) {
            $inv->invoice_no = $inv->invoice_number;
            $inv->transaction_date = $inv->invoice_date;
            $inv->final_total = (float) $inv->invoice_total;
            $inv->tax_amount = (float) $inv->tax;
            $inv->total_paid = (float) $inv->total_payments;

            return $inv;
        });

        $grouped = $invoices->groupBy(function ($t) {
            return optional($t->transaction_date)->format('Y-m-d') ?: 'undated';
        });

        $taxSummary = [
            'invoice_count' => $invoices->count(),
            'sales_total' => (float) $invoices->sum('final_total'),
            'tax_total' => (float) $invoices->sum('tax_amount'),
            'paid_total' => (float) $invoices->sum('total_paid'),
        ];
        $contact = $customer;
        $initials = strtoupper(substr($customer->displayName(), 0, 1));

        return view('customer.documents', compact(
            'contact', 'invoices', 'grouped', 'initials', 'start', 'end', 'hasDateFilter', 'q', 'taxSummary'
        ));
    }

    public function showInvoice(Invoice $invoice, DocumentPdfService $pdfs)
    {
        abort_unless((int) $invoice->customer_id === (int) $this->customer()->id, 403);

        return $pdfs->streamInvoice($invoice);
    }

    public function priceCheck()
    {
        $contact = $this->presentContact($this->customer());
        $initials = strtoupper(substr($contact->displayName(), 0, 1));
        $locations = $this->customerLocations($contact);
        $default_location = $this->defaultLocationId($contact, $locations);

        return view('customer.price_check', compact('contact', 'initials', 'locations', 'default_location'));
    }

    public function orders(Request $request)
    {
        $customer = $this->customer();
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');

        $orders = SalesOrder::query()
            ->with(['customer', 'invoice'])
            ->where('customer_id', $customer->id)
            ->when($q !== '', fn ($query) => $query->where('order_number', 'like', '%'.$q.'%'))
            ->when($status === 'return', fn ($query) => $query->where('order_type', 'Return'))
            ->when($status === 'sale', fn ($query) => $query->where('order_type', 'Sales Order')->whereDoesntHave('invoice'))
            ->when($status === 'invoiced', fn ($query) => $query->whereHas('invoice'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $orders->getCollection()->transform(fn (SalesOrder $o) => $this->presentOrder($o));

        return view('customer.orders.index', [
            'orders' => $orders,
            'q' => $q,
            'status' => $status,
            'contact' => $this->presentContact($customer),
        ]);
    }

    public function show(SalesOrder $salesOrder)
    {
        abort_unless((int) $salesOrder->customer_id === (int) $this->customer()->id, 403);
        $this->presentOrder($salesOrder);

        return view('customer.orders.show', [
            'order' => $salesOrder,
            'amounts' => [
                'subtotal' => (float) $salesOrder->subtotal,
                'discount' => (float) $salesOrder->trade_discount,
                'discount_label' => 'Discount',
                'tax' => (float) $salesOrder->tax,
                'shipping' => (float) $salesOrder->freight,
                'packing' => (float) $salesOrder->miscellaneous,
                'packing_label' => 'Misc',
                'extras' => [],
                'total' => (float) $salesOrder->total,
                'show_paid' => false,
                'paid' => 0,
                'due' => (float) $salesOrder->total,
            ],
        ]);
    }

    public function downloadInvoice(SalesOrder $salesOrder, DocumentPdfService $pdfs)
    {
        abort_unless((int) $salesOrder->customer_id === (int) $this->customer()->id, 403);

        return $pdfs->streamSalesOrderInvoiceStyle($salesOrder, $this->actingRep($this->customer()));
    }

    public function create(Request $request)
    {
        $customer = $this->customer();
        $this->presentContact($customer);
        $companyId = (int) $customer->company_id;
        $locations = Site::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->pluck('name', 'id');
        $defaultLocation = $this->defaultLocationId($customer, $locations);

        return view('customer.orders.create', [
            'locations' => $locations,
            'default_location' => $defaultLocation,
            'ship_vias' => ShipVia::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'payment_terms' => PaymentTerm::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'routes' => RouteLookup::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'default_customer' => [
                'id' => $customer->id,
                'text' => $customer->displayName(),
                'shipping_address' => trim(implode(', ', array_filter([$customer->address, $customer->city]))),
            ],
            'edit_order' => null,
            'edit_lines' => [],
            'lock_customer' => true,
        ]);
    }

    public function store(Request $request, CreateSalesOrderFromRep $creator)
    {
        $customer = $this->customer();
        $request->merge(['contact_id' => $customer->id]);
        $request->validate([
            'location_id' => 'required|integer',
            'products' => 'required|array|min:1',
            'sale_note' => 'nullable|string|max:2000',
        ]);

        $rep = $this->actingRep($customer);
        $lines = [];
        foreach ($request->input('products', []) as $row) {
            $item = Item::query()->where('company_id', $customer->company_id)->where('id', (int) ($row['variation_id'] ?? 0))->first();
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
            return back()->withInput()->with('error', 'Add at least one product.');
        }

        $note = trim((string) $request->input('sale_note', ''));
        $comments = $note !== '' ? "Customer App\n".$note : 'Customer App';

        try {
            $order = $creator->handle($rep, $customer, [
                'lines' => $lines,
                'comments' => $comments,
                'reference_no' => 'CUSTAPP',
                'ship_to_address_id' => $request->filled('ship_to_address_id') ? $request->integer('ship_to_address_id') : 0,
                'ship_to_name' => $request->input('ship_to_name'),
                'ship_to_phone' => $request->input('ship_to_phone'),
                'ship_to_address' => $request->input('ship_to_address'),
                'ship_to_city' => $request->input('ship_to_city'),
                'ship_to_state' => $request->input('ship_to_state'),
                'ship_to_zip' => $request->input('ship_to_zip'),
                'ship_via_id' => $request->integer('ship_via_id') ?: null,
                'payment_term_id' => $request->integer('payment_term_id') ?: $customer->payment_term_id,
                'route_id' => $request->integer('route_id') ?: $customer->delivery_route_id,
                'ship_date' => $request->input('ship_date') ?: null,
                'ship_from_site_id' => $request->integer('location_id') ?: $rep->site_id,
                'order_type' => 'Sales Order',
                'order_source' => SalesOrder::SOURCE_CUSTOMER,
            ]);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Could not create the sales order.';

            return back()->withInput()->with('error', $msg)->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Could not create the sales order. '.$e->getMessage());
        }

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'Sales order '.$order->order_number.' created.');
    }

    public function searchProducts(Request $request)
    {
        $customer = $this->customer();
        $term = trim((string) $request->get('q', ''));
        $limit = min(100, max(1, (int) $request->get('limit', 30)));
        $query = Item::query()->with('prices')->where('company_id', $customer->company_id)->where('is_inactive', false)->where('can_sell', true);
        if ($request->boolean('scan') && $term !== '') {
            $scanned = Item::findByScanCode((int) $customer->company_id, $term, 'sell');

            return response()->json($scanned ? [$this->mapProduct($scanned, $customer)] : []);
        }
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('item_code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('primary_upc', 'like', "%{$term}%");
            });
        }
        $categoryId = (int) $request->get('category_id', 0);
        $subId = (int) $request->get('sub_category_id', 0);
        if ($subId > 0) {
            $query->where('subcategory_id', $subId);
        } elseif ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        return response()->json($query->orderBy('description')->limit($limit)->get()->map(fn (Item $item) => $this->mapProduct($item, $customer))->values());
    }

    public function searchCustomers()
    {
        return response()->json([]);
    }

    public function parkedSales()
    {
        return response()->json([]);
    }

    public function categoriesTree()
    {
        $customer = $this->customer();

        return response()->json(
            Category::query()
                ->where('company_id', $customer->company_id)
                ->where('is_active', true)
                ->with(['subcategories' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (Category $cat) => [
                    'id' => (int) $cat->id,
                    'name' => $cat->name,
                    'via_department' => 0,
                    'sub_categories' => $cat->subcategories->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])->values(),
                ])
                ->values()
        );
    }

    public function customerShipping(Customer $customer)
    {
        abort_unless((int) $customer->id === (int) $this->customer()->id, 403);
        $c = $this->customer()->load('shippingAddresses');
        $addresses = $c->shippingAddresses->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])->values();
        $mapped = $addresses->map(fn ($a) => [
            'id' => (int) $a->id,
            'name' => $a->name ?: ($a->address ?: 'Ship-To #'.$a->id),
            'address' => (string) ($a->address ?? ''),
            'city' => (string) ($a->city ?? ''),
            'state' => (string) ($a->state ?? ''),
            'zip' => (string) ($a->zip ?? ''),
            'telephone' => (string) ($a->telephone ?? ''),
            'is_primary' => (bool) $a->is_primary,
        ]);
        $primary = $addresses->firstWhere('is_primary', true) ?? $addresses->first();
        $defaultShip = $primary ? [
            'id' => (int) $primary->id,
            'name' => $primary->name ?: $c->company_name,
            'address' => (string) ($primary->address ?? $c->address),
            'city' => (string) ($primary->city ?? $c->city),
            'state' => (string) ($primary->state ?? $c->state),
            'zip' => (string) ($primary->zip ?? $c->zip_code),
            'telephone' => (string) ($primary->telephone ?? $c->telephone ?? $c->mobile),
        ] : [
            'id' => null,
            'name' => $c->company_name,
            'address' => (string) $c->address,
            'city' => (string) $c->city,
            'state' => (string) $c->state,
            'zip' => (string) $c->zip_code,
            'telephone' => (string) ($c->telephone ?: $c->mobile),
        ];

        return response()->json([
            'shipping_addresses' => $mapped,
            'default_ship' => $defaultShip,
            'payment_term_id' => $c->payment_term_id,
            'route_id' => $c->delivery_route_id,
        ]);
    }

    protected function presentContact(Customer $customer): Customer
    {
        $customer->supplier_business_name = $customer->company_name;
        $customer->name = $customer->contact ?: $customer->company_name;
        $customer->contact_id = $customer->customer_id;

        return $customer;
    }

    protected function presentOrder(SalesOrder $order): SalesOrder
    {
        $order->loadMissing(['customer', 'lines.item', 'invoice']);
        $this->presentContact($order->customer ?? $this->customer());
        $order->setRelation('contact', $order->customer);
        $order->invoice_no = $order->order_number;
        $order->transaction_date = $order->created_at ?? $order->order_date;
        $order->final_total = (float) $order->total;
        $order->sale_display_total = (float) $order->total;
        $order->can_edit = false;
        $order->can_show_edit = false;
        $invoiced = (bool) $order->invoice;
        $order->sale_status = $invoiced ? 'invoiced' : ((string) $order->order_type === 'Return' ? 'return' : 'sale');
        if ($order->invoice) {
            $order->converted_invoice_no = $order->invoice->invoice_number;
        }
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

    protected function customerLocations(Customer $customer)
    {
        return Site::query()
            ->where('company_id', $customer->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    protected function defaultLocationId(Customer $customer, $locations): ?int
    {
        $sessionId = session('customer.default_location_id');
        if ($sessionId && $locations->keys()->contains(fn ($id) => (int) $id === (int) $sessionId)) {
            return (int) $sessionId;
        }
        $first = $locations->keys()->first();

        return $first ? (int) $first : null;
    }

    protected function productCards(Customer $customer, int $limit, string $mode): array
    {
        $query = Item::query()
            ->with('prices')
            ->where('company_id', $customer->company_id)
            ->where('is_inactive', false)
            ->where('can_sell', true);

        if ($mode === 'newest') {
            $query->orderByDesc('id');
        } else {
            $query->orderByDesc('quantity_in_stock')->orderBy('description');
        }

        return $query->limit($limit)->get()->map(function (Item $item) use ($customer) {
            $row = $this->mapProduct($item, $customer);
            $row['image'] = $row['image'] ?: asset('pwa/sale-icon-192.png');

            return $row;
        })->all();
    }

    protected function mapProduct(Item $item, Customer $customer): array
    {
        $price = ItemPricing::resolve($item, $customer->price_level_id ? (int) $customer->price_level_id : null, $item->unit_of_measure, $customer->id);

        return [
            'product_id' => (int) $item->id,
            'variation_id' => (int) $item->id,
            'name' => trim($item->description.' ('.$item->item_code.')'),
            'sku' => $item->item_code,
            'price' => $price,
            'image' => filled($item->image_path) ? url('/media/'.$item->image_path) : '',
            'stock' => (float) $item->available_quantity,
            'enable_stock' => 1,
            'product_type' => 'single',
            'allow_decimal' => 1,
        ];
    }
}
