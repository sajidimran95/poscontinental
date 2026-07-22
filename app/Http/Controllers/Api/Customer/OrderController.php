<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $orders = SalesOrder::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 50))), [
                'id', 'order_number', 'order_type', 'status', 'priority', 'order_date',
                'required_date', 'subtotal', 'trade_discount', 'freight', 'tax', 'total', 'created_at',
            ]);

        return response()->json($orders);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $order = SalesOrder::query()
            ->with('lines')
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        return response()->json($order);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'customer_po_no' => 'nullable|string|max:64',
            'reference_no' => 'nullable|string|max:64',
            'required_date' => 'nullable|date',
            'comments' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.item_code' => 'required|string|max:64',
            'lines.*.qty_ordered' => 'required|numeric|min:0.0001',
            'lines.*.price' => 'nullable|numeric|min:0',
        ]);

        $order = DB::transaction(function () use ($customer, $data) {
            $order = SalesOrder::query()->create([
                'company_id' => $customer->company_id,
                'order_number' => SalesOrder::nextNumber($customer->company_id),
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
                'ship_to_name' => $customer->company_name ?: $customer->contact,
                'ship_to_phone' => $customer->telephone,
                'ship_to_address' => $customer->address,
                'ship_to_city' => $customer->city,
                'ship_to_state' => $customer->state,
                'ship_to_zip' => $customer->zip_code,
                'order_date' => now()->toDateString(),
                'required_date' => $data['required_date'] ?? now()->toDateString(),
                'customer_po_no' => $data['customer_po_no'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'comments' => $data['comments'] ?? null,
                'sales_rep_id' => $customer->sales_rep_id,
                'created_by' => null,
                'subtotal' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach (array_values($data['lines']) as $i => $line) {
                $item = Item::query()
                    ->where('company_id', $customer->company_id)
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
                $price = array_key_exists('price', $line) && $line['price'] !== null
                    ? (float) $line['price']
                    : (float) $item->list_price;
                $lineTotal = round($qty * $price, 4);
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
                    'line_no' => $i + 1,
                ]);
            }

            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            return $order->fresh('lines');
        });

        return response()->json($order, 201);
    }

    public function invoices(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $rows = Invoice::query()
            ->with(['payments', 'credits'])
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->when($request->boolean('unpaid_only'), fn ($q) => $q->where('status', '!=', 'Paid'))
            ->orderByDesc('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 50))));

        $rows->getCollection()->transform(function (Invoice $inv) {
            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => optional($inv->invoice_date)?->toDateString(),
                'status' => $inv->status,
                'invoice_total' => (float) $inv->invoice_total,
                'balance' => (float) $inv->invoice_balance,
            ];
        });

        return response()->json($rows);
    }
}
