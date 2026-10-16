<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPriceHistory;
use App\Models\SalesOrder;
use App\Support\ItemPricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * After a price change (e.g. cigarettes), move every order that is not invoiced yet to today's prices.
 * Uses the same price a new order would get: memorized customer price, then price level, then list price.
 */
class OpenOrderRepriceService
{
    /**
     * Orders whose lines would change.
     *
     * @param  list<int>  $itemIds  only these items (empty = all items)
     * @return list<array{id:int, order_number:string, customer:string, order_date:?string, lines_changed:int, old_total:float, new_total:float, locked_by:?string, changes:list<array{item_code:string, description:string, old_price:float, new_price:float}>}>
     */
    public function preview(int $companyId, array $itemIds = []): array
    {
        $out = [];
        foreach ($this->openOrders($companyId, $itemIds) as $order) {
            $plan = $this->plan($order, $itemIds);
            if ($plan['changes'] === []) {
                continue;
            }
            $holder = $order->editLockHolder();
            $out[] = [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'customer' => (string) ($order->bill_to_name ?: ($order->customer?->company_name ?: '')),
                'order_date' => optional($order->order_date)?->format('n/j/Y'),
                'lines_changed' => count($plan['changes']),
                'old_total' => round((float) $order->total, 2),
                'new_total' => $plan['total'],
                'locked_by' => $holder ? (string) $holder['name'] : null,
                'changes' => array_map(fn ($c) => [
                    'item_code' => $c['item_code'],
                    'description' => $c['description'],
                    'old_price' => $c['old_price'],
                    'new_price' => $c['new_price'],
                ], $plan['changes']),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $orderIds
     * @param  list<int>  $itemIds
     * @return array{orders:int, lines:int, skipped:list<string>}
     */
    public function apply(int $companyId, array $orderIds, array $itemIds = []): array
    {
        $orders = 0;
        $lines = 0;
        $skipped = [];

        foreach ($this->openOrders($companyId, $itemIds, $orderIds) as $order) {
            $holder = $order->editLockHolder();
            if ($holder) {
                $skipped[] = $order->order_number.' (open by '.$holder['name'].')';

                continue;
            }

            $changed = DB::transaction(function () use ($order, $itemIds) {
                $locked = SalesOrder::query()->with(['lines', 'customer'])->lockForUpdate()->find($order->id);
                if (! $locked || ! $this->isOpen($locked)) {
                    return 0;
                }
                $plan = $this->plan($locked, $itemIds);
                if ($plan['changes'] === []) {
                    return 0;
                }
                $now = now();
                foreach ($plan['changes'] as $c) {
                    DB::table('sales_order_lines')->where('id', $c['line_id'])->update([
                        'price' => $c['new_price'],
                        'line_total' => $c['line_total'],
                        'updated_at' => $now,
                    ]);
                }
                $locked->update([
                    'subtotal' => $plan['subtotal'],
                    'tax' => $plan['tax'],
                    'total' => $plan['total'],
                ]);

                return count($plan['changes']);
            });

            if ($changed > 0) {
                $orders++;
                $lines += $changed;
            }
        }

        return ['orders' => $orders, 'lines' => $lines, 'skipped' => $skipped];
    }

    /**
     * Called while invoicing a locked order: lines whose item sales price changed after the
     * line was last saved move to today's price. Lines saved after the change keep their price.
     *
     * @return list<array{item_code:string, description:string, old_price:float, new_price:float}>
     */
    public function refreshForInvoice(SalesOrder $order): array
    {
        $order->loadMissing(['lines', 'customer']);
        if ($order->isReturnSale() || in_array((string) $order->status, ['Invoiced', 'Credit', 'Void', 'Cancelled'], true)) {
            return [];
        }

        $itemIds = $order->lines->pluck('item_id')->filter()->unique()->values()->all();
        if ($itemIds === []) {
            return [];
        }

        $lastChange = DB::table('item_price_histories')
            ->where('company_id', (int) $order->company_id)
            ->whereIn('item_id', $itemIds)
            ->whereIn('change_type', [ItemPriceHistory::TYPE_SALES, ItemPriceHistory::TYPE_BOTH])
            ->groupBy('item_id')
            ->selectRaw('item_id, MAX(created_at) as changed_at')
            ->pluck('changed_at', 'item_id');

        $lineIds = $order->lines
            ->filter(function ($line) use ($lastChange) {
                $changedAt = $line->item_id ? ($lastChange[(int) $line->item_id] ?? null) : null;
                $savedAt = $line->updated_at ?? $line->created_at;

                return $changedAt && (! $savedAt || $savedAt->lt(\Illuminate\Support\Carbon::parse($changedAt)));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($lineIds === []) {
            return [];
        }

        $plan = $this->plan($order, [], $lineIds);
        if ($plan['changes'] === []) {
            return [];
        }

        $now = now();
        foreach ($plan['changes'] as $c) {
            DB::table('sales_order_lines')->where('id', $c['line_id'])->update([
                'price' => $c['new_price'],
                'line_total' => $c['line_total'],
                'updated_at' => $now,
            ]);
        }
        $order->update([
            'subtotal' => $plan['subtotal'],
            'tax' => $plan['tax'],
            'total' => $plan['total'],
        ]);
        $order->load('lines');

        return array_map(fn ($c) => [
            'item_code' => $c['item_code'],
            'description' => $c['description'],
            'old_price' => $c['old_price'],
            'new_price' => $c['new_price'],
        ], $plan['changes']);
    }

    /**
     * "Price updated: 0791 KING LIGHTER 50CT $9.49 → $10.99; …"
     *
     * @param  list<array{item_code:string, description:string, old_price:float, new_price:float}>  $changes
     */
    public static function changesMessage(array $changes): string
    {
        if ($changes === []) {
            return '';
        }

        return 'Price updated: '.collect($changes)->map(fn ($c) => trim($c['item_code'].' '.$c['description'])
            .' $'.number_format($c['old_price'], 2).' → $'.number_format($c['new_price'], 2))->implode('; ').'.';
    }

    /**
     * @param  list<int>  $itemIds
     * @param  list<int>|null  $orderIds
     * @return Collection<int, SalesOrder>
     */
    protected function openOrders(int $companyId, array $itemIds, ?array $orderIds = null): Collection
    {
        return SalesOrder::query()
            ->with(['lines', 'customer'])
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['Invoiced', 'Credit', 'Void', 'Cancelled'])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('invoices')->whereColumn('invoices.sales_order_id', 'sales_orders.id');
            })
            ->when($orderIds !== null, fn ($q) => $q->whereIn('id', $orderIds === [] ? [0] : $orderIds))
            ->when($itemIds !== [], fn ($q) => $q->whereHas('lines', fn ($l) => $l->whereIn('item_id', $itemIds)))
            ->orderBy('order_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (SalesOrder $o) => $this->isOpen($o))
            ->values();
    }

    protected function isOpen(SalesOrder $order): bool
    {
        return ! in_array((string) $order->status, ['Invoiced', 'Credit', 'Void', 'Cancelled'], true)
            && ! $order->isReturnSale()
            && ! $order->invoice()->exists();
    }

    /**
     * @param  list<int>  $itemIds
     * @param  list<int>|null  $lineIds  only these lines (null = all lines)
     * @return array{changes:list<array{line_id:int, item_code:string, description:string, old_price:float, new_price:float, line_total:float}>, subtotal:float, tax:float, total:float}
     */
    protected function plan(SalesOrder $order, array $itemIds, ?array $lineIds = null): array
    {
        $levelId = $order->customer?->price_level_id ? (int) $order->customer->price_level_id : null;
        $customerId = $order->customer_id ? (int) $order->customer_id : null;
        $items = Item::query()->with('prices')
            ->whereIn('id', $order->lines->pluck('item_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $changes = [];
        $oldSubtotal = 0.0;
        $newSubtotal = 0.0;
        foreach ($order->lines as $line) {
            $qty = (float) $line->qty_ordered;
            $discount = (float) $line->discount;
            $oldPrice = round((float) $line->price, 4);
            $oldLine = ($qty * $oldPrice) - $discount;
            $oldSubtotal += $oldLine;

            $item = $line->item_id ? $items->get((int) $line->item_id) : null;
            $eligible = $item
                && $qty > 0.0001
                && ($itemIds === [] || in_array((int) $line->item_id, $itemIds, true))
                && ($lineIds === null || in_array((int) $line->id, $lineIds, true));
            if (! $eligible) {
                $newSubtotal += $oldLine;

                continue;
            }

            $newPrice = round(ItemPricing::resolve($item, $levelId, $line->uom ?: null, $customerId), 4);
            if ($newPrice <= 0 || abs($newPrice - $oldPrice) < 0.005) {
                $newSubtotal += $oldLine;

                continue;
            }

            $lineTotal = round(($qty * $newPrice) - $discount, 4);
            $newSubtotal += $lineTotal;
            $changes[] = [
                'line_id' => (int) $line->id,
                'item_code' => (string) $line->item_code,
                'description' => (string) $line->description,
                'old_price' => round($oldPrice, 2),
                'new_price' => $newPrice,
                'line_total' => $lineTotal,
            ];
        }

        $trade = (float) $order->trade_discount;
        $oldTax = (float) $order->tax;
        $oldTaxable = $oldSubtotal - $trade;
        $newTaxable = $newSubtotal - $trade;
        // Keep the order's tax rate: scale tax with the taxable amount.
        $tax = ($oldTax != 0.0 && $oldTaxable > 0.0001)
            ? round($oldTax * max(0, $newTaxable) / $oldTaxable, 2)
            : round($oldTax, 2);

        $subtotal = round($newSubtotal, 4);
        $total = round($subtotal - $trade + (float) $order->freight + (float) $order->miscellaneous + $tax, 2);

        return ['changes' => $changes, 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $total];
    }
}
