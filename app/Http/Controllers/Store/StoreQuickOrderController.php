<?php

namespace App\Http\Controllers\Store;

use App\Support\Store\StoreCart;
use App\Support\Store\StoreCatalog;
use Illuminate\Http\Request;

class StoreQuickOrderController extends StoreController
{
    public function index()
    {
        return view('store.quick_order.index');
    }

    public function submit(Request $request, StoreCart $cart)
    {
        $request->validate([
            'rows' => 'nullable|array|max:200',
            'rows.*.sku' => 'nullable|string|max:60',
            'rows.*.qty' => 'nullable|numeric|min:0|max:100000',
            'bulk' => 'nullable|string|max:20000',
        ]);

        $wanted = [];
        foreach ($request->input('rows', []) as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $wanted[] = [$sku, max(1, (float) ($row['qty'] ?? 1))];
            }
        }
        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('bulk', '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(\S+?)[\s,;]+(\d+(?:\.\d+)?)$/', $line, $m)) {
                $wanted[] = [$m[1], max(1, (float) $m[2])];
            } else {
                $wanted[] = [$line, 1];
            }
        }

        if (! $wanted) {
            return back()->withErrors(['rows' => 'Enter at least one SKU.'])->withInput();
        }

        $companyId = $this->companyId();
        $added = 0;
        $missing = [];
        foreach ($wanted as [$sku, $qty]) {
            $item = StoreCatalog::query($companyId)
                ->where(fn ($q) => $q->where('items.item_code', $sku)->orWhere('items.primary_upc', $sku))
                ->first();
            if (! $item) {
                $missing[] = $sku;

                continue;
            }
            $cart->add((int) $item->id, $qty);
            $added++;
        }

        $redirect = redirect()->route($added ? 'ecommerce.cart' : 'ecommerce.quick_order');
        if ($added) {
            $redirect->with('success', $added.' line(s) added to cart.');
        }
        if ($missing) {
            $redirect->with('error', 'Not found: '.implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? '…' : ''));
            if (! $added) {
                $redirect->withInput();
            }
        }

        return $redirect;
    }
}
