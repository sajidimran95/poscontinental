<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ParkedSale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

        $manualCount = ParkedSale::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('is_auto', false)
            ->count();
        if ($manualCount >= self::MAX_PER_USER) {
            throw ValidationException::withMessages([
                'park' => 'Too many parked sales. Recall or discard one first.',
            ]);
        }

        if ($label === '') {
            $label = $customer->company_name ?: $customer->contact ?: ('Customer #'.$customer->id);
        }

        $row = ParkedSale::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'customer_label' => mb_substr($label, 0, 191),
            'line_count' => $lineCount,
            'total' => round($total, 4),
            'payload' => $payload,
        ]);
        $this->forgetCount($user);

        return $row;
    }

    /**
     * One auto-saved row per New Sales Order window, overwritten on every change.
     *
     * @param  array<string, mixed>  $payload
     */
    public function autoSave(User $user, string $windowId, Customer $customer, string $label, array $payload, int $lineCount, float $total): ParkedSale
    {
        abort_unless((int) $customer->company_id === (int) $user->company_id, 403);

        if ($label === '') {
            $label = $customer->company_name ?: $customer->contact ?: ('Customer #'.$customer->id);
        }

        $row = ParkedSale::query()->updateOrCreate(
            [
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'window_id' => $windowId,
            ],
            [
                'customer_id' => $customer->id,
                'is_auto' => true,
                'customer_label' => mb_substr($label, 0, 191),
                'line_count' => $lineCount,
                'total' => round($total, 4),
                'payload' => $payload,
            ]
        );
        $this->forgetCount($user);

        return $row;
    }

    /** Remove the auto-saved row of a window (order saved, parked manually, or emptied). */
    public function forgetWindow(User $user, string $windowId): void
    {
        $deleted = ParkedSale::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('window_id', $windowId)
            ->delete();
        if ($deleted) {
            $this->forgetCount($user);
        }
    }

    /** Keep the auto-saved row in the Parked list but stop the window from overwriting it. */
    public function detachWindow(User $user, string $windowId): void
    {
        ParkedSale::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('window_id', $windowId)
            ->update(['window_id' => null]);
    }

    public function discard(User $user, int $id): void
    {
        $this->findOwn($user, $id)->delete();
        $this->forgetCount($user);
    }

    protected function forgetCount(User $user): void
    {
        Cache::forget('parked.count.'.(int) $user->id);
    }
}
