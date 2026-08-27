<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRouteOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_EN_ROUTE = 'en_route';

    public const STATUS_ARRIVED = 'arrived';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const FAIL_REASONS = [
        'customer_unavailable' => 'Customer unavailable',
        'wrong_address' => 'Wrong address',
        'customer_refused' => 'Customer refused',
        'unable_to_access' => 'Unable to access location',
        'other' => 'Other',
    ];

    protected $fillable = [
        'delivery_route_id', 'order_id', 'stop_no', 'distance_from_previous',
        'estimated_duration_from_previous', 'status', 'arrived_at', 'delivered_at',
        'fail_reason', 'delivery_notes', 'ship_to_name', 'ship_to_phone',
        'ship_to_address', 'ship_to_city', 'ship_to_state', 'ship_to_zip',
        'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'delivered_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function formattedAddress(): string
    {
        $line2 = collect([$this->ship_to_city, $this->ship_to_state, $this->ship_to_zip])->filter()->implode(', ');

        return collect([$this->ship_to_address, $line2])->filter()->implode("\n");
    }

    public function navigateUrl(): string
    {
        if ($this->latitude && $this->longitude) {
            return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($this->latitude.','.$this->longitude);
        }

        $addr = trim(preg_replace('/\s+/', ' ', str_replace("\n", ', ', $this->formattedAddress())));

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($addr !== '' ? $addr : 'United States');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_FAILED, self::STATUS_SKIPPED], true);
    }

    public function canAct(): bool
    {
        return ! $this->isFinished();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_EN_ROUTE => 'En Route',
            self::STATUS_ARRIVED => 'Arrived',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_SKIPPED => 'Skipped',
            default => 'Pending',
        };
    }
}
