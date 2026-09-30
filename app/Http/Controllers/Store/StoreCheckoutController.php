<?php

namespace App\Http\Controllers\Store;

use App\Models\Customer;
use App\Models\EcomOrderMeta;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Rep\CreateSalesOrderFromRep;
use App\Support\Store\StoreCart;
use App\Support\Store\WholesaleAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreCheckoutController extends StoreController
{
    public function __construct(private StoreCart $cart) {}

    public function index()
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $customer = $this->customer()->load('shippingAddresses', 'paymentTerm');
        $lines = $this->cart->lines();

        $nameParts = explode(' ', trim((string) ($customer->contact ?: $customer->company_name)), 2) + ['', ''];
        $primary = $customer->shippingAddresses->firstWhere('is_primary', true);

        return view('store.checkout.index', [
            'customer' => $customer,
            'lines' => $lines,
            'totals' => $this->cart->totals($lines),
            'addresses' => $customer->shippingAddresses,
            'default_address_id' => $primary?->id ?? 0,
            'name_parts' => $nameParts,
            'us_states' => $this->usStates(),
            'payment_label' => $customer->paymentTerm?->name
                ? 'Pay on delivery / account terms ('.$customer->paymentTerm->name.')'
                : 'Pay on delivery (cash / check)',
        ]);
    }

    public function place(Request $request, CreateSalesOrderFromRep $creator)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $customer = $this->customer();

        $data = $request->validate([
            'email' => 'required|email|max:191',
            'phone' => 'nullable|string|max:40',
            'ship_to_address_id' => 'nullable|string|max:20',
            'shipping.first_name' => 'nullable|string|max:100',
            'shipping.last_name' => 'nullable|string|max:100',
            'shipping.company' => 'nullable|string|max:191',
            'shipping.address_line_1' => 'required_if:ship_to_address_id,new|nullable|string|max:255',
            'shipping.address_line_2' => 'nullable|string|max:255',
            'shipping.city' => 'required_if:ship_to_address_id,new|nullable|string|max:100',
            'shipping.state' => 'required_if:ship_to_address_id,new|nullable|string|max:40',
            'shipping.zip_code' => 'required_if:ship_to_address_id,new|nullable|string|max:20',
            'note' => 'nullable|string|max:2000',
        ]);

        $lines = $this->cart->lines();
        $blocked = $lines->first(fn ($l) => ! $l->product->can_order);
        if ($blocked) {
            return redirect()->route('ecommerce.cart')->with('error', $blocked->product->name.' is out of stock. Remove it to check out.');
        }

        $payload = [
            'lines' => $lines->map(fn ($l) => [
                'item_code' => $l->product->sku,
                'qty_ordered' => $l->quantity,
                'price' => $l->unit_price,
                'uom' => $l->product->uom,
            ])->all(),
            'order_source' => SalesOrder::SOURCE_ECOMMERCE,
            'reference_no' => 'ECOMMERCE',
            'comments' => trim("Ecommerce order\n".($data['note'] ?? '')),
        ];

        $shipChoice = (string) ($data['ship_to_address_id'] ?? '0');
        if ($shipChoice === 'new') {
            $s = $data['shipping'] ?? [];
            $payload['ship_to_address_id'] = 0;
            $payload['ship_to_name'] = trim(($s['company'] ?? '') ?: trim(($s['first_name'] ?? '').' '.($s['last_name'] ?? '')));
            $payload['ship_to_phone'] = $data['phone'] ?? null;
            $payload['ship_to_address'] = trim(($s['address_line_1'] ?? '').' '.($s['address_line_2'] ?? ''));
            $payload['ship_to_city'] = $s['city'] ?? null;
            $payload['ship_to_state'] = $s['state'] ?? null;
            $payload['ship_to_zip'] = $s['zip_code'] ?? null;
        } else {
            $addrId = (int) $shipChoice;
            abort_if($addrId > 0 && ! $customer->shippingAddresses()->whereKey($addrId)->exists(), 422, 'Invalid address.');
            $payload['ship_to_address_id'] = $addrId;
        }

        $cartModel = $this->cart->current();

        try {
            [$order, $meta] = DB::transaction(function () use ($creator, $customer, $payload, $data, $cartModel) {
                $order = $creator->handle($this->actingRep($customer), $customer, $payload);
                $meta = EcomOrderMeta::query()->create([
                    'company_id' => $order->company_id,
                    'sales_order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'tracking_token' => Str::lower(Str::random(24)),
                    'payment_method' => 'cod',
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'timeline' => [['status' => 'placed', 'label' => 'Order placed online', 'at' => now()->format('M j, Y g:i A')]],
                ]);
                if ($cartModel) {
                    $this->cart->clear($cartModel);
                }

                return [$order, $meta];
            });
        } catch (ValidationException $e) {
            return redirect()->route('ecommerce.cart')->with('error', collect($e->errors())->flatten()->first() ?: 'Could not place the order.');
        }

        return redirect()->route('ecommerce.thanks', $meta->tracking_token);
    }

    public function thanks(string $token)
    {
        $meta = EcomOrderMeta::query()->where('tracking_token', $token)->where('company_id', $this->companyId())->firstOrFail();

        return view('store.checkout.thanks', ['meta' => $meta, 'order' => $meta->salesOrder]);
    }

    public function track(Request $request)
    {
        $token = trim((string) $request->query('token', ''));
        $meta = $token !== ''
            ? EcomOrderMeta::query()->where('tracking_token', $token)->where('company_id', $this->companyId())->with('salesOrder.invoice')->first()
            : null;
        $order = $meta?->salesOrder;
        $invoice = $order?->invoice;

        $timeline = collect($meta?->timeline ?? []);
        if ($order && $order->status !== 'New') {
            $timeline->push(['status' => 'processing', 'label' => 'Order status: '.$order->status, 'at' => optional($order->updated_at)->format('M j, Y g:i A')]);
        }
        if ($invoice) {
            $timeline->push(['status' => 'invoiced', 'label' => 'Invoiced — #'.$invoice->invoice_number, 'at' => optional($invoice->invoice_date ?? $invoice->created_at)->format('M j, Y')]);
        }

        return view('store.account.track', [
            'token' => $token,
            'meta' => $meta,
            'order' => $order,
            'invoice' => $invoice,
            'timeline' => $timeline->all(),
            'status_label' => $invoice ? 'Invoiced' : ($order ? ($order->status === 'New' ? 'Received' : $order->status) : ''),
        ]);
    }

    /** Checkout needs an approved, logged-in customer and a non-empty cart. */
    private function guard()
    {
        $customer = $this->customer();
        if (! $customer) {
            return redirect()->guest(route('ecommerce.login'))->with('error', 'Please log in to check out.');
        }
        if (! WholesaleAccount::isApproved($customer)) {
            return redirect()->route('ecommerce.cart')->with('error', 'Checkout unlocks after your wholesale account is approved.');
        }
        if (! $this->cart->current()?->items()->exists()) {
            return redirect()->route('ecommerce.cart')->with('error', 'Your cart is empty.');
        }

        return null;
    }

    private function actingRep(Customer $customer): User
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

        abort_unless($rep, 422, 'No staff user is available to own this order. Please contact us.');

        return $rep;
    }
}
