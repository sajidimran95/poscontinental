<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesOrder extends Model
{
    protected $fillable = [
        'company_id', 'order_number', 'order_type', 'order_source', 'status', 'priority', 'customer_id', 'ship_to_address_id',
        'bill_to_name', 'bill_to_phone', 'bill_to_address', 'bill_to_city', 'bill_to_state', 'bill_to_zip',
        'ship_to_name', 'ship_to_phone', 'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip',
        'order_date', 'required_date', 'customer_po_no', 'reference_no', 'sales_rep_id',
        'payment_term_id', 'route_id', 'ship_via_id', 'ship_from_site_id', 'ship_date',
        'no_of_boxes', 'no_of_pallets', 'custom_field_1', 'custom_field_2', 'custom_field_3',
        'custom_field_4', 'custom_field_5', 'comments',
        'subtotal', 'trade_discount', 'freight', 'miscellaneous', 'tax', 'total', 'created_by',
        'delivery_user_id', 'delivery_date', 'delivery_status', 'shipping_latitude', 'shipping_longitude',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'required_date' => 'date',
            'ship_date' => 'date',
            'delivery_date' => 'date',
            'shipping_latitude' => 'float',
            'shipping_longitude' => 'float',
            'subtotal' => 'decimal:4',
            'trade_discount' => 'decimal:4',
            'freight' => 'decimal:4',
            'miscellaneous' => 'decimal:4',
            'tax' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('line_no');
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(SalesOrderBox::class)->orderBy('sort_order');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RouteLookup::class, 'route_id');
    }

    public function shipVia(): BelongsTo
    {
        return $this->belongsTo(ShipVia::class);
    }

    public function shipFromSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'ship_from_site_id');
    }

    public function shipToAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerShippingAddress::class, 'ship_to_address_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function isReturnSale(): bool
    {
        $type = strtolower(preg_replace('/\s+/', '', (string) $this->order_type));

        return $type !== '' && str_contains($type, 'return');
    }

    public static function nextNumber(int $companyId): string
    {
        $query = static::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id');

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $last = $query->value('order_number');

        $n = $last ? ((int) preg_replace('/\D/', '', (string) $last)) + 1 : 243074;
        if ($n < 1) {
            $n = 243074;
        }

        while (
            static::query()
                ->where('company_id', $companyId)
                ->where('order_number', (string) $n)
                ->exists()
        ) {
            $n++;
        }

        return (string) $n;
    }

    public const SOURCE_POS = 'pos';

    public const SOURCE_SALES = 'sales';

    public const SOURCE_CUSTOMER = 'customer';

    public function sourceLabel(): string
    {
        return match ((string) ($this->order_source ?? 'pos')) {
            self::SOURCE_SALES => 'Sales',
            self::SOURCE_CUSTOMER => 'Customer App',
            default => 'POS Sale',
        };
    }

    /**
     * Overlay admin invoice totals/status so customer and sales apps show the latest invoice.
     */
    public function applyInvoiceForPortal(): self
    {
        $this->loadMissing(['invoice.payments', 'invoice.credits']);
        $inv = $this->invoice;
        if (! $inv) {
            $this->invoice_pay_status = null;
            $this->portal_paid = 0.0;
            $this->portal_due = (float) $this->total;

            return $this;
        }

        $paid = round((float) $inv->total_payments + (float) $inv->total_credits, 2);
        $total = round((float) $inv->invoice_total, 2);
        $due = round(max(0, $total - $paid), 2);

        $this->converted_invoice_no = $inv->invoice_number;
        $this->final_total = $total;
        $this->sale_display_total = $total;
        $this->sale_status = 'invoiced';
        $this->portal_paid = $paid;
        $this->portal_due = $due;

        if ($due <= 0.0001) {
            $this->invoice_pay_status = 'PAID';
        } elseif ($paid > 0.0001) {
            $this->invoice_pay_status = 'PARTIAL';
        } else {
            $this->invoice_pay_status = 'UNPAID';
        }

        return $this;
    }

    public function ownerUserId(): int
    {
        return (int) ($this->created_by ?: $this->sales_rep_id ?: 0);
    }

    public function canBeEditedBy(?User $user): bool
    {
        if (! $user || (int) $user->company_id !== (int) $this->company_id) {
            return false;
        }

        if (! $user->canAccessFeature('sales.orders', 'edit')) {
            return false;
        }

        $ownerId = (int) ($this->created_by ?: 0);
        if ($ownerId < 1) {
            $ownerId = $this->ownerUserId();
        }
        if ($ownerId < 1) {
            return false;
        }

        return $ownerId === (int) $user->id;
    }

    public static function editLockCacheKey(int $orderId): string
    {
        return 'so.edit.lock.'.$orderId;
    }

    /** @return array{user_id:int,name:string}|null */
    public function editLockHolder(): ?array
    {
        $held = Cache::get(static::editLockCacheKey((int) $this->id));
        if (! is_array($held) || (int) ($held['user_id'] ?? 0) < 1) {
            return null;
        }

        return $held;
    }

    public function claimEditLock(User $user): bool
    {
        $key = static::editLockCacheKey((int) $this->id);
        $held = Cache::get($key);
        $heldId = is_array($held) ? (int) ($held['user_id'] ?? 0) : 0;
        if ($heldId > 0 && $heldId !== (int) $user->id) {
            return false;
        }

        Cache::put($key, [
            'user_id' => (int) $user->id,
            'name' => (string) $user->name,
        ], now()->addMinutes(20));

        return true;
    }

    public function releaseEditLock(?User $user = null): void
    {
        $held = $this->editLockHolder();
        if ($user && $held && (int) $held['user_id'] !== (int) $user->id) {
            return;
        }
        Cache::forget(static::editLockCacheKey((int) $this->id));
    }

    public function portalAmounts(): array
    {
        $inv = $this->relationLoaded('invoice') ? $this->invoice : $this->invoice()->with(['payments', 'credits'])->first();

        if ($inv) {
            $paid = (float) ($this->portal_paid ?? ((float) $inv->total_payments + (float) $inv->total_credits));
            $total = (float) $inv->invoice_total;

            return [
                'subtotal' => (float) $inv->subtotal,
                'discount' => (float) $inv->trade_discount,
                'discount_label' => 'Discount',
                'tax' => (float) $inv->tax,
                'shipping' => (float) $inv->freight,
                'packing' => (float) $inv->miscellaneous,
                'packing_label' => 'Misc',
                'extras' => [],
                'total' => $total,
                'show_paid' => true,
                'paid' => $paid,
                'due' => (float) ($this->portal_due ?? max(0, round($total - $paid, 2))),
            ];
        }

        $total = (float) $this->total;

        return [
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->trade_discount,
            'discount_label' => 'Discount',
            'tax' => (float) $this->tax,
            'shipping' => (float) $this->freight,
            'packing' => (float) $this->miscellaneous,
            'packing_label' => 'Misc',
            'extras' => [],
            'total' => $total,
            'show_paid' => false,
            'paid' => 0,
            'due' => $total,
        ];
    }
}
