<?php

namespace App\Support\Store;

use App\Models\Customer;
use App\Models\EcomCart;
use App\Models\EcomCartItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Database cart: keyed by customer when logged in, otherwise by a token kept in the session
 * (the token survives session regeneration at login, so the guest cart can be merged).
 */
class StoreCart
{
    private const SESSION_KEY = 'ecom_cart_token';

    public function current(bool $create = false): ?EcomCart
    {
        $companyId = StoreContext::companyId();
        $customer = StoreContext::customer();

        if ($customer) {
            $cart = EcomCart::query()->where('company_id', $companyId)->where('customer_id', $customer->id)->first();

            return $cart ?? ($create ? EcomCart::query()->create(['company_id' => $companyId, 'customer_id' => $customer->id]) : null);
        }

        $token = session(self::SESSION_KEY);
        if (! $token && ! $create) {
            return null;
        }
        if (! $token) {
            $token = Str::random(40);
            session([self::SESSION_KEY => $token]);
        }

        $cart = EcomCart::query()->where('company_id', $companyId)->whereNull('customer_id')->where('session_id', $token)->first();

        return $cart ?? ($create ? EcomCart::query()->create(['company_id' => $companyId, 'session_id' => $token]) : null);
    }

    public function add(int $itemId, float $qty): void
    {
        $item = StoreCatalog::query(StoreContext::companyId())->whereKey($itemId)->first();
        if (! $item) {
            throw ValidationException::withMessages(['item_id' => 'This product is not available.']);
        }
        $qty = max(1, round($qty));

        $cart = $this->current(true);
        $line = $cart->items()->where('item_id', $item->id)->first();
        if ($line) {
            $line->update(['quantity' => (float) $line->quantity + $qty]);
        } else {
            $cart->items()->create(['item_id' => $item->id, 'uom' => $item->unit_of_measure ?: null, 'quantity' => $qty]);
        }
        $cart->touch();
    }

    public function update(int $lineId, float $qty): void
    {
        $line = $this->ownedLine($lineId);
        $line?->update(['quantity' => max(1, round($qty))]);
    }

    public function remove(int $lineId): void
    {
        $this->ownedLine($lineId)?->delete();
    }

    public function clear(EcomCart $cart): void
    {
        $cart->items()->delete();
    }

    /** Move the guest cart into the customer's cart after login / registration. */
    public function mergeGuestInto(Customer $customer): void
    {
        $token = session(self::SESSION_KEY);
        if (! $token) {
            return;
        }
        $companyId = StoreContext::companyId();
        $guest = EcomCart::query()->where('company_id', $companyId)->whereNull('customer_id')->where('session_id', $token)->with('items')->first();
        session()->forget(self::SESSION_KEY);
        if (! $guest) {
            return;
        }

        $target = EcomCart::query()->firstOrCreate(['company_id' => $companyId, 'customer_id' => $customer->id]);
        foreach ($guest->items as $line) {
            $existing = $target->items()->where('item_id', $line->item_id)->first();
            if ($existing) {
                $existing->update(['quantity' => (float) $existing->quantity + (float) $line->quantity]);
            } else {
                $target->items()->create($line->only(['item_id', 'uom', 'quantity']));
            }
        }
        $guest->delete();
    }

    /**
     * Priced cart lines (prices always resolved on the server for the current viewer).
     *
     * @return Collection<int, object>
     */
    public function lines(?EcomCart $cart = null): Collection
    {
        $cart ??= $this->current();
        if (! $cart) {
            return collect();
        }

        $customer = StoreContext::customer();
        $company = StoreContext::company();
        $cart->load(['items.item' => fn ($q) => $q->with(['prices', 'taxSchedule', 'category'])]);

        return $cart->items
            ->filter(fn (EcomCartItem $line) => $line->item && ! $line->item->is_inactive && $line->item->can_sell)
            ->map(function (EcomCartItem $line) use ($customer, $company) {
                $product = StoreCatalog::present($line->item, $customer, $company);
                $qty = (float) $line->quantity;
                $warning = null;
                if (! $product->can_order) {
                    $warning = 'Out of stock — remove this item to check out.';
                } elseif (! $product->in_stock) {
                    $warning = 'Currently out of stock (back-order).';
                }

                return (object) [
                    'id' => (int) $line->id,
                    'item' => $line->item,
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $product->price,
                    'line_total' => round($qty * $product->price, 2),
                    'warning' => $warning,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array{count:int,qty:float,subtotal:float,shipping:float,tax:float,total:float}
     */
    public function totals(Collection $lines): array
    {
        $subtotal = round((float) $lines->sum('line_total'), 2);
        $tax = round((float) $lines->sum(fn ($l) => $l->line_total * ($l->product->tax_rate / 100)), 2);

        return [
            'count' => (int) round((float) $lines->sum('quantity')),
            'qty' => (float) $lines->sum('quantity'),
            'subtotal' => $subtotal,
            'shipping' => 0.0,
            'tax' => $tax,
            'total' => round($subtotal + $tax, 2),
        ];
    }

    /** Header badge count without pricing every line. */
    public function badgeCount(): int
    {
        $cart = $this->current();

        return $cart ? (int) round((float) $cart->items()->sum('quantity')) : 0;
    }

    private function ownedLine(int $lineId): ?EcomCartItem
    {
        $cart = $this->current();

        return $cart ? $cart->items()->whereKey($lineId)->first() : null;
    }
}
