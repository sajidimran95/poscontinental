<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const WALK_IN_CODE = 'WALKIN';

    public const WALK_IN_NAME = 'Walk-in Customer';

    protected $fillable = [
        'company_id',
        'customer_id',
        'is_inactive',
        'is_favorite',
        'contact',
        'company_name',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'telephone',
        'telephone2',
        'mobile',
        'fax',
        'email',
        'web_page',
        'price_level_id',
        'cigarette_tax_class_id',
        'discount_schedule_id',
        'purchase_limit_schedule_id',
        'payment_term_id',
        'sales_rep_id',
        'delivery_route_id',
        'lead_source',
        'customer_category',
        'opt_out_catalog',
        'opt_out_email',
        'opt_out_telemarketing',
        'opt_out_mobile',
        'opt_out_all',
        'fein_no',
        'account_type',
        'credit_limit',
        'balance',
        'customer_since',
        'last_order_on',
        'number_of_orders',
        'total_sales',
        'bad_checks_count',
        'replacements_count',
        'returns_count',
        'messages_alerts',
        'comments',
        'is_tax_exempt',
        'tax_certificate_no',
        'tax_certificate_exp',
        'certificate_on_file',
        'order_day',
        'location_no',
        'drivers_accept_returns',
        'is_employee',
        'owner_name',
        'owner_ssn',
        'owner_address',
        'owner_city',
        'owner_state',
        'owner_zip',
        'owner_country',
        'owner_telephone',
        'owner_fax',
        'owner_email',
    ];

    protected $hidden = [
        'owner_ssn',
    ];

    protected function casts(): array
    {
        return [
            'is_inactive' => 'boolean',
            'is_favorite' => 'boolean',
            'is_tax_exempt' => 'boolean',
            'certificate_on_file' => 'boolean',
            'drivers_accept_returns' => 'boolean',
            'is_employee' => 'boolean',
            'opt_out_catalog' => 'boolean',
            'opt_out_email' => 'boolean',
            'opt_out_telemarketing' => 'boolean',
            'opt_out_mobile' => 'boolean',
            'opt_out_all' => 'boolean',
            'credit_limit' => 'decimal:2',
            'balance' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'tax_certificate_exp' => 'date',
            'customer_since' => 'date',
            'last_order_on' => 'date',
            'owner_ssn' => 'encrypted',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function deliveryRoute(): BelongsTo
    {
        return $this->belongsTo(RouteLookup::class, 'delivery_route_id');
    }

    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(CustomerShippingAddress::class)->orderBy('sort_order');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * Default cash / counter customer for POS desk (one per company).
     *
     * Creates the WALKIN account if it does not exist yet.
     */
    public static function ensureWalkIn(int $companyId): self
    {
        $code = self::WALK_IN_CODE;

        $existing = static::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($code) {
                $q->where('customer_id', $code)
                    ->orWhereRaw('UPPER(customer_id) = ?', [strtoupper($code)]);
            })
            ->first();

        if ($existing) {
            if ($existing->is_inactive) {
                $existing->update(['is_inactive' => false]);
            }

            // Drop legacy system alert so New SO does not show "Alert: Default walk-in..."
            if (filled($existing->messages_alerts) && str_contains((string) $existing->messages_alerts, 'Default walk-in')) {
                $existing->update(['messages_alerts' => null]);
            }

            return $existing->refresh();
        }

        return static::query()->create([
            'company_id' => $companyId,
            'customer_id' => $code,
            'company_name' => self::WALK_IN_NAME,
            'contact' => self::WALK_IN_NAME,
            'lead_source' => 'Walk-in',
            'customer_category' => 'Walk-in',
            'account_type' => 'Cash',
            'is_inactive' => false,
            'is_favorite' => true,
            'credit_limit' => 0,
            'balance' => 0,
            'customer_since' => now()->toDateString(),
            'messages_alerts' => null,
            'comments' => 'System default — use for walk-in sales without a named account.',
        ]);
    }

    public function isWalkIn(): bool
    {
        return strtoupper((string) $this->customer_id) === self::WALK_IN_CODE;
    }

    public function getAvailableCreditAttribute(): float
    {
        return (float) $this->credit_limit - (float) $this->balance;
    }

    public function getOwnerSsnMaskedAttribute(): string
    {
        $ssn = $this->owner_ssn;
        if (! filled($ssn)) {
            return '';
        }
        $digits = preg_replace('/\D/', '', $ssn) ?? '';
        if (strlen($digits) < 4) {
            return '***';
        }

        return '***-**-'.substr($digits, -4);
    }
}
