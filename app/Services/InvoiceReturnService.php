<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InventoryJournalEntry;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer returns on an invoice: the original lines stay, and each return is added
 * as a negative line ("RETURN ITEM") so the invoice shows sold and returned qty.
 */
class InvoiceReturnService
{
    public const RETURN_NOTE = 'RETURN ITEM';

    public static function isReturnLine(SalesOrderLine $line): bool
    {
        return (float) $line->qty_ordered < 0;
    }

    /**
     * Sold / returned / returnable qty per original (positive) line.
     * Returns are matched per item: earlier lines absorb returned qty first.
     *
     * @return Collection<int, array{line: SalesOrderLine, sold: float, returned: float, returnable: float}>
     */
    public function returnableLines(SalesOrder $order): Collection
    {
        $order->loadMissing('lines');
        $lines = $order->lines->sortBy([['line_no', 'asc'], ['id', 'asc']])->values();

        $returnedByItem = [];
        foreach ($lines as $line) {
            if (self::isReturnLine($line)) {
                $key = $this->itemKey($line);
                $returnedByItem[$key] = ($returnedByItem[$key] ?? 0) + abs((float) $line->qty_ordered);
            }
        }

        return $lines
            ->reject(fn (SalesOrderLine $l) => self::isReturnLine($l) || (float) $l->qty_ordered <= 0)
            ->map(function (SalesOrderLine $line) use (&$returnedByItem) {
                $sold = (float) $line->qty_ordered;
                $key = $this->itemKey($line);
                $returned = min($sold, (float) ($returnedByItem[$key] ?? 0));
                $returnedByItem[$key] = (float) ($returnedByItem[$key] ?? 0) - $returned;

                return [
                    'line' => $line,
                    'sold' => $sold,
                    'returned' => round($returned, 4),
                    'returnable' => round($sold - $returned, 4),
                ];
            })
            ->values();
    }

    /**
     * @param  array<int|string, float|int|string>  $qtyByLineId  original line id => qty to return
     * @return array{lines: int, qty: float, amount: float, balance: float}
     */
    public function addReturn(int $invoiceId, int $companyId, array $qtyByLineId, ?string $note = null, ?int $userId = null): array
    {
        return DB::transaction(function () use ($invoiceId, $companyId, $qtyByLineId, $note, $userId) {
            $invoice = Invoice::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($invoiceId);
            $order = SalesOrder::query()->lockForUpdate()->findOrFail($invoice->sales_order_id);
            $order->load('lines');

            $returnable = $this->returnableLines($order)->keyBy(fn ($r) => (int) $r['line']->id);
            $nextLineNo = (int) $order->lines->max('line_no');
            $message = trim(self::RETURN_NOTE.(filled($note) ? ' - '.trim((string) $note) : ''));
            $message = mb_substr($message, 0, 255);

            $amount = 0.0;
            $totalQty = 0.0;
            $added = 0;
            $stockByItem = [];

            foreach ($qtyByLineId as $lineId => $qtyRaw) {
                $qty = round((float) $qtyRaw, 4);
                if ($qty <= 0) {
                    continue;
                }
                $row = $returnable->get((int) $lineId);
                if (! $row) {
                    throw new \RuntimeException('Invoice line not found.');
                }
                /** @var SalesOrderLine $orig */
                $orig = $row['line'];
                if ($qty > $row['returnable'] + 0.0001) {
                    throw new \RuntimeException(sprintf(
                        'Item %s: only %s can be returned (sold %s, already returned %s).',
                        $orig->item_code,
                        $this->fmt($row['returnable']),
                        $this->fmt($row['sold']),
                        $this->fmt($row['returned'])
                    ));
                }

                $price = (float) $orig->price;
                $unitDiscount = $row['sold'] > 0 ? (float) $orig->discount / $row['sold'] : 0.0;
                $discount = round(-$qty * $unitDiscount, 4);
                $lineTotal = round((-$qty * $price) - $discount, 4);

                $order->lines()->create([
                    'item_id' => $orig->item_id,
                    'item_code' => $orig->item_code,
                    'description' => $orig->description,
                    'uom' => $orig->uom,
                    'qty_ordered' => -$qty,
                    'qty_shipped' => -$qty,
                    'price' => $price,
                    'discount' => $discount,
                    'line_message' => $message,
                    'line_total' => $lineTotal,
                    'line_no' => ++$nextLineNo,
                ]);

                $amount += $lineTotal;
                $totalQty += $qty;
                $added++;
                if ($orig->item_id) {
                    $stockByItem[(int) $orig->item_id] = ($stockByItem[(int) $orig->item_id] ?? 0) + $qty;
                }
            }

            if ($added === 0) {
                throw new \RuntimeException('Enter a return qty for at least one item.');
            }

            $amount = round($amount, 4);

            foreach ($stockByItem as $itemId => $qty) {
                $item = Item::query()->lockForUpdate()->find($itemId);
                if (! $item) {
                    continue;
                }
                $newQty = (float) $item->quantity_in_stock + $qty;
                $item->update(['quantity_in_stock' => $newQty]);
                InventoryJournalEntry::query()->create([
                    'company_id' => $order->company_id,
                    'item_id' => $item->id,
                    'site_id' => $order->ship_from_site_id,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'qty_change' => $qty,
                    'qty_after' => $newQty,
                    'unit_cost' => $item->current_cost,
                    'user_id' => $userId,
                    'notes' => 'Customer return on invoice '.$invoice->invoice_number.(filled($note) ? ' - '.trim((string) $note) : ''),
                ]);
            }
            app(InventoryService::class)->syncAllocatedQty(array_keys($stockByItem));

            $order->update([
                'subtotal' => round((float) $order->subtotal + $amount, 4),
                'total' => round((float) $order->total + $amount, 4),
            ]);

            $invoice->update([
                'subtotal' => round((float) $invoice->subtotal + $amount, 4),
                'total_discount' => round((float) $order->lines()->sum('discount'), 4),
                'invoice_total' => round((float) $invoice->invoice_total + $amount, 4),
            ]);
            $invoice->unsetRelation('payments');
            $invoice->unsetRelation('credits');
            $invoice->load(['payments', 'credits']);
            $balance = round((float) $invoice->invoice_balance, 2);
            $invoice->update(['status' => $balance <= 0.0001 ? 'PAID' : 'NOT PAID']);

            if ($invoice->customer_id) {
                $customer = Customer::query()->lockForUpdate()->find($invoice->customer_id);
                $customer?->update(['balance' => round((float) $customer->balance + $amount, 2)]);
            }

            return ['lines' => $added, 'qty' => $totalQty, 'amount' => $amount, 'balance' => $balance];
        });
    }

    protected function itemKey(SalesOrderLine $line): string
    {
        return $line->item_id ? 'id:'.$line->item_id : 'code:'.mb_strtolower((string) $line->item_code);
    }

    protected function fmt(float $q): string
    {
        return rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');
    }
}
