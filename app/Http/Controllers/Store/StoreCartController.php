<?php

namespace App\Http\Controllers\Store;

use App\Support\Store\StoreCart;
use App\Support\Store\WholesaleAccount;
use Illuminate\Http\Request;

class StoreCartController extends StoreController
{
    public function __construct(private StoreCart $cart) {}

    public function index()
    {
        $lines = $this->cart->lines();
        $customer = $this->customer();

        return view('store.cart.index', [
            'lines' => $lines,
            'totals' => $this->cart->totals($lines),
            'customer' => $customer,
            'approval' => $customer ? WholesaleAccount::status($customer) : null,
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|integer',
            'quantity' => 'nullable|numeric|min:1|max:100000',
        ]);
        $this->cart->add((int) $data['item_id'], (float) ($data['quantity'] ?? 1));

        if ($request->boolean('buy_now')) {
            return redirect()->route('ecommerce.checkout');
        }

        return back()->with('success', 'Added to cart.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|integer',
            'quantity' => 'required|numeric|min:1|max:100000',
        ]);
        $this->cart->update((int) $data['item_id'], (float) $data['quantity']);

        return redirect()->route('ecommerce.cart');
    }

    public function remove(Request $request)
    {
        $this->cart->remove((int) $request->input('item_id'));

        return redirect()->route('ecommerce.cart')->with('success', 'Item removed.');
    }
}
