<?php

namespace App\Services\Rep;

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\ItemPricing;
use App\Support\StockPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSalesOrderFromRep
{
    /**
     * Create a Sales Order in New status (same as desk order entry).
     *
     * @param  array{
     *     lines: list<array{item_code:string,qty_ordered:float|int|string,price?:float|int|string|null,uom?:string|null,line_message?:string|null}>,
     *     required_date?:string|null,
     *     customer_po_no?:string|null,
     *     reference_no?:string|null,
     *     comments?:string|null,
     * }  $payload
     */
    public function handle(User $rep, Customer $customer, array $payload): SalesOrder
    {
        SalesRepScope::assertCustomerAccess($rep, $customer);

        return DB::transaction(function () use ($rep, $customer, $payload) {
            $order = SalesOrder::query()->create([
                'company_id' => $rep->company_id,
                'order_number' => SalesOrder::nextNumber((int) $rep->company_id),
                'order_type' => 'Sales Order',
                'status' => 'New',
                'priority' => 'Normal',
                'customer_id' => $customer->id,
                'bill_to_name' => $customer->company_name ?: $customer->contact,
                'bill_to_phone' => $customer->telephone ?: $customer->mobile,
                'bill_to_address' => $customer->address,
                'bill_to_city' => $customer->city,
                'bill_to_state' => $customer->state,
                'bill_to_zip' => $customer->zip_code,
                'ship_to_name' => $customer->company_name ?: $customer->contact,
                'ship_to_phone' => $customer->telephone ?: $customer->mobile,
                'ship_to_address' => $customer->address,
                'ship_to_city' => $customer->city,
                'ship_to_state' => $customer->state,
                'ship_to_zip' => $customer->zip_code,
                'order_date' => now()->toDateString(),
                'required_date' => $payload['required_date'] ?? now()->toDateString(),
                'customer_po_no' => $payload['customer_po_no'] ?? null,
                'reference_no' => $payload['reference_no'] ?? null,
                'comments' => $payload['comments'] ?? null,
                'sales_rep_id' => $rep->id,
                'ship_from_site_id' => $rep->site_id,
                'created_by' => $rep->id,
                'subtotal' => 0,
                'total' => 0,
            ]);

            $neededByItem = [];
            $resolved = [];

            foreach (array_values($payload['lines']) as $i => $line) {
                $item = Item::query()
                    ->with('prices')
                    ->where('company_id', $rep->company_id)
                    ->where('item_code', $line['item_code'])
                    ->where('is_inactive', false)
                    ->where('can_sell', true)
                    ->first();

                if (! $item) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item code {$line['item_code']} was not found or cannot be sold."],
                    ]);
                }

                $qty = (float) $line['qty_ordered'];
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => ['Quantity must be greater than zero for '.$item->item_code.'.'],
                    ]);
                }

                $neededByItem[$item->id] = ($neededByItem[$item->id] ?? 0) + $qty;
                $resolved[] = ['item' => $item, 'qty' => $qty, 'line' => $line, 'i' => $i];
            }

            foreach ($neededByItem as $itemId => $needed) {
                /** @var Item $locked */
                $locked = Item::query()->lockForUpdate()->findOrFail($itemId);
                $available = (float) $locked->available_quantity;
                $err = StockPolicy::orderQtyError($locked, $needed, $available, $rep->company);
                if ($err) {
                    throw ValidationException::withMessages([
                        'lines' => [$err],
                    ]);
                }
            }

            $subtotal = 0.0;
            foreach ($resolved as $row) {
                /** @var Item $item */
                $item = $row['item'];
                $qty = $row['qty'];
                $line = $row['line'];
                $uom = filled($line['uom'] ?? null) ? (string) $line['uom'] : ($item->unit_of_measure ?: null);
                $price = array_key_exists('price', $line) && $line['price'] !== null && $line['price'] !== ''
                    ? (float) $line['price']
                    : ItemPricing::resolve($item, $customer->price_level_id ? (int) $customer->price_level_id : null, $uom);

                $lineTotal = round($qty * $price, 4);
                $subtotal += $lineTotal;

                $order->lines()->create([
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'uom' => $uom,
                    'qty_ordered' => $qty,
                    'price' => $price,
                    'discount' => 0,
                    'line_message' => $line['line_message'] ?? null,
                    'line_total' => $lineTotal,
                    'line_no' => $row['i'] + 1,
                ]);
            }

            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            return $order->fresh(['lines', 'customer', 'salesRep']);
        });
    }
}
