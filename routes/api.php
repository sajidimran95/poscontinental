<?php

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| Staff / Sales Rep API
|--------------------------------------------------------------------------
*/
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'nullable|string',
    ]);

    $user = User::query()->where('email', $request->email)->first();
    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
    }

    if (isset($user->is_active) && ! $user->is_active) {
        return response()->json(['message' => 'User account is inactive.'], 403);
    }

    $token = $user->createToken($request->string('device_name', 'mobile'))->plainTextToken;

    return ['token' => $token, 'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'company_id' => $user->company_id]];
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn (Request $request) => $request->user());

    Route::get('/items', function (Request $request) {
        $q = Item::query()
            ->where('company_id', $request->user()->company_id)
            ->where('is_inactive', false)
            ->where('can_sell', true)
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($i) => $i->where('item_code', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($request->boolean('new_only'), fn ($query) => $query->newItems())
            ->orderBy('item_code');

        return $q->paginate(50, ['id', 'item_code', 'description', 'unit_of_measure', 'list_price', 'created_at']);
    });

    Route::get('/customers', function (Request $request) {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'Staff token required.');

        $q = Customer::query()
            ->where('company_id', $user->company_id)
            ->where('is_inactive', false)
            ->when($request->boolean('assigned_only'), fn ($query) => $query->where('sales_rep_id', $user->id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($c) => $c->where('customer_id', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhere('contact', 'like', $term));
            })
            ->orderBy('company_name');

        return $q->paginate(50, ['id', 'customer_id', 'company_name', 'contact', 'telephone', 'balance', 'credit_limit', 'messages_alerts', 'price_level_id']);
    });

    Route::get('/customers/{customer}', function (Request $request, Customer $customer) {
        abort_unless($request->user() instanceof User, 403, 'Staff token required.');
        abort_unless($customer->company_id === $request->user()->company_id, 403);

        return [
            'customer' => $customer,
            'available_credit' => $customer->available_credit,
        ];
    });

    Route::post('/sales-orders', function (Request $request) {
        abort_unless($request->user() instanceof User, 403, 'Staff token required.');

        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'lines' => 'required|array|min:1',
            'lines.*.item_code' => 'required|string',
            'lines.*.qty_ordered' => 'required|numeric|min:0.0001',
            'lines.*.price' => 'nullable|numeric',
        ]);

        $user = $request->user();
        $customer = Customer::query()->findOrFail($data['customer_id']);
        abort_unless($customer->company_id === $user->company_id, 403);

        $order = DB::transaction(function () use ($user, $customer, $data) {
            $order = SalesOrder::query()->create([
                'company_id' => $user->company_id,
                'order_number' => SalesOrder::nextNumber($user->company_id),
                'order_type' => 'Sales Order',
                'status' => 'New',
                'priority' => 'Normal',
                'customer_id' => $customer->id,
                'bill_to_name' => $customer->company_name ?: $customer->contact,
                'bill_to_phone' => $customer->telephone,
                'bill_to_address' => $customer->address,
                'bill_to_city' => $customer->city,
                'bill_to_state' => $customer->state,
                'bill_to_zip' => $customer->zip_code,
                'order_date' => now()->toDateString(),
                'required_date' => now()->toDateString(),
                'sales_rep_id' => $customer->sales_rep_id ?: $user->id,
                'created_by' => $user->id,
                'subtotal' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            $neededByItem = [];
            $resolved = [];

            foreach (array_values($data['lines']) as $i => $line) {
                $item = Item::query()
                    ->where('company_id', $user->company_id)
                    ->where('item_code', $line['item_code'])
                    ->where('is_inactive', false)
                    ->where('can_sell', true)
                    ->first();

                if (! $item) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'lines' => ["Item code {$line['item_code']} was not found or cannot be sold."],
                    ]);
                }

                $qty = (float) $line['qty_ordered'];
                $neededByItem[$item->id] = ($neededByItem[$item->id] ?? 0) + $qty;
                $resolved[] = ['item' => $item, 'qty' => $qty, 'line' => $line, 'i' => $i];
            }

            foreach ($neededByItem as $itemId => $needed) {
                $item = Item::query()->lockForUpdate()->find($itemId);
                $available = (float) $item->available_quantity;
                if ($needed > $available + 0.0001) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'lines' => [
                            $item->item_code.' ordered qty ('.number_format($needed, 2).') exceeds available stock ('.number_format($available, 2).').',
                        ],
                    ]);
                }
            }

            foreach ($resolved as $row) {
                $item = $row['item'];
                $qty = $row['qty'];
                $line = $row['line'];
                $price = (float) ($line['price'] ?? $item->list_price);
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;
                $order->lines()->create([
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'uom' => $item->unit_of_measure,
                    'qty_ordered' => $qty,
                    'price' => $price,
                    'discount' => 0,
                    'line_total' => $lineTotal,
                    'line_no' => $row['i'] + 1,
                ]);
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            return $order->load('lines');
        });

        return response()->json($order, 201);
    });

    Route::get('/sales-orders/{salesOrder}', function (Request $request, SalesOrder $salesOrder) {
        abort_unless($request->user() instanceof User, 403, 'Staff token required.');
        abort_unless($salesOrder->company_id === $request->user()->company_id, 403);

        return $salesOrder->load(['customer:id,customer_id,company_name', 'lines']);
    });

    Route::get('/invoices', function (Request $request) {
        abort_unless($request->user() instanceof User, 403, 'Staff token required.');

        $rows = \App\Models\Invoice::query()
            ->with(['customer:id,customer_id,company_name', 'payments', 'credits'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('id')
            ->paginate(50);

        $rows->getCollection()->transform(function ($inv) {
            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => optional($inv->invoice_date)?->toDateString(),
                'customer_id' => $inv->customer_id,
                'customer' => $inv->customer,
                'status' => $inv->status,
                'invoice_total' => $inv->invoice_total,
                'balance' => $inv->invoice_balance,
            ];
        });

        return $rows;
    });
});
