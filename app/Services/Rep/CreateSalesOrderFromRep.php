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
        SalesRepScope::assertCompanyCustomer($rep, $customer);

        return DB::transaction(function () use ($rep, $customer, $payload) {
            $order = SalesOrder::query()->create(array_merge([
                'company_id' => $rep->company_id,
                'order_number' => SalesOrder::nextNumber((int) $rep->company_id),
                'status' => 'New',
                'priority' => 'Normal',
                'customer_id' => $customer->id,
                'order_date' => now()->toDateString(),
                'required_date' => $payload['required_date'] ?? now()->toDateString(),
                'reference_no' => $payload['reference_no'] ?? null,
                'sales_rep_id' => $rep->id,
                'created_by' => $rep->id,
                'subtotal' => 0,
                'total' => 0,
            ], $this->headerFromPayload($rep, $customer, $payload)));

            $this->attachLines($rep, $customer, $order, $payload['lines']);

            return $order->fresh(['lines', 'customer', 'salesRep']);
        });
    }

    public function rebuild(User $rep, SalesOrder $order, Customer $customer, array $payload): SalesOrder
    {
        SalesRepScope::assertOrderAccess($rep, $order);
        SalesRepScope::assertCompanyCustomer($rep, $customer);
        abort_unless($order->status === 'New' && ! $order->invoice()->exists(), 403, 'This order cannot be edited.');

        return DB::transaction(function () use ($rep, $customer, $order, $payload) {
            $order->lines()->delete();
            $order->update(array_merge([
                'customer_id' => $customer->id,
            ], $this->headerFromPayload($rep, $customer, $payload)));

            $this->attachLines($rep, $customer, $order, $payload['lines']);

            return $order->fresh(['lines', 'customer', 'salesRep', 'invoice']);
        });
    }

    private function headerFromPayload(User $rep, Customer $customer, array $payload): array
    {
        $customer->loadMissing('shippingAddresses');
        $addrId = (int) ($payload['ship_to_address_id'] ?? -1);
        $ship = null;
        if ($addrId > 0) {
            $ship = $customer->shippingAddresses->firstWhere('id', $addrId);
        } elseif ($addrId < 0) {
            $ship = $customer->shippingAddresses->firstWhere('is_primary', true)
                ?? $customer->shippingAddresses->first();
        }

        $billName = $customer->company_name ?: $customer->contact;
        $billPhone = $customer->telephone ?: $customer->mobile;

        $fromShip = $ship ? [
            'ship_to_address_id' => $ship->id,
            'ship_to_name' => $ship->name ?: $billName,
            'ship_to_phone' => $ship->telephone ?: $billPhone,
            'ship_to_address' => $ship->address,
            'ship_to_city' => $ship->city,
            'ship_to_state' => $ship->state,
            'ship_to_zip' => $ship->zip,
        ] : [
            'ship_to_address_id' => null,
            'ship_to_name' => $billName,
            'ship_to_phone' => $billPhone,
            'ship_to_address' => $customer->address,
            'ship_to_city' => $customer->city,
            'ship_to_state' => $customer->state,
            'ship_to_zip' => $customer->zip_code,
        ];

        foreach (['ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip'] as $key) {
            if (filled($payload[$key] ?? null)) {
                $fromShip[$key] = $payload[$key];
            }
        }

        return array_merge($fromShip, [
            'bill_to_name' => $billName,
            'bill_to_phone' => $billPhone,
            'bill_to_address' => $customer->address,
            'bill_to_city' => $customer->city,
            'bill_to_state' => $customer->state,
            'bill_to_zip' => $customer->zip_code,
            'comments' => $payload['comments'] ?? null,
            'ship_from_site_id' => $payload['ship_from_site_id'] ?? $rep->site_id,
            'ship_via_id' => $payload['ship_via_id'] ?? null,
            'payment_term_id' => $payload['payment_term_id'] ?? $customer->payment_term_id,
            'route_id' => $payload['route_id'] ?? $customer->delivery_route_id,
            'ship_date' => $payload['ship_date'] ?? null,
            'order_type' => $payload['order_type'] ?? 'Sales Order',
            'order_source' => $payload['order_source'] ?? SalesOrder::SOURCE_SALES,
        ]);
    }

    /**
     * @param  list<array{item_code:string,qty_ordered:float|int|string,price?:float|int|string|null}>  $lines
     */
    private function attachLines(User $rep, Customer $customer, SalesOrder $order, array $lines): void
    {
        $neededByItem = [];
        $resolved = [];

        foreach (array_values($lines) as $i => $line) {
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
    }
}
