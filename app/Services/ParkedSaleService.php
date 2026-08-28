<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ParkedSale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ParkedSaleService
{
    public const MAX_PER_USER = 40;

    public function listFor(User $user): Collection
    {
        return ParkedSale::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(self::MAX_PER_USER)
            ->get();
    }

    public function findOwn(User $user, int $id): ParkedSale
    {
        $row = ParkedSale::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->first();

        abort_unless($row instanceof ParkedSale, 404);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function park(User $user, Customer $customer, string $label, array $payload, int $lineCount, float $total): ParkedSale
    {
        abort_unless((int) $customer->company_id === (int) $user->company_id, 403);

        if ($this->listFor($user)->count() >= self::MAX_PER_USER) {
            throw ValidationException::withMessages([
                'park' => 'Too many parked sales. Recall or discard one first.',
            ]);
        }

        if ($label === '') {
            $label = $customer->company_name ?: $customer->contact ?: ('Customer #'.$customer->id);
        }

        return ParkedSale::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'customer_label' => mb_substr($label, 0, 191),
            'line_count' => $lineCount,
            'total' => round($total, 4),
            'payload' => $payload,
        ]);
    }

    public function discard(User $user, int $id): void
    {
        $this->findOwn($user, $id)->delete();
    }
}
