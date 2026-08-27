<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRoute extends Model
{
    protected $table = 'driver_delivery_routes';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id', 'delivery_user_id', 'company_location_id', 'route_date', 'status',
        'total_orders', 'total_distance', 'estimated_duration', 'started_at', 'completed_at',
        'start_name', 'start_address', 'start_latitude', 'start_longitude',
    ];

    protected function casts(): array
    {
        return [
            'route_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'start_latitude' => 'float',
            'start_longitude' => 'float',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(CompanyLocation::class, 'company_location_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryRouteOrder::class, 'delivery_route_id')->orderBy('stop_no');
    }

    public function deliveryUser(): BelongsTo
    {
        return $this->driver();
    }

    public function companyLocation(): BelongsTo
    {
        return $this->location();
    }

    public function routeOrders(): HasMany
    {
        return $this->stops();
    }

    public function deliveredCount(): int
    {
        return $this->stops->where('status', DeliveryRouteOrder::STATUS_DELIVERED)->count();
    }

    public function remainingCount(): int
    {
        return $this->stops->whereNotIn('status', [
            DeliveryRouteOrder::STATUS_DELIVERED,
            DeliveryRouteOrder::STATUS_FAILED,
            DeliveryRouteOrder::STATUS_SKIPPED,
        ])->count();
    }

    public function currentStop(): ?DeliveryRouteOrder
    {
        return $this->stops->first(function (DeliveryRouteOrder $stop) {
            return in_array($stop->status, [
                DeliveryRouteOrder::STATUS_EN_ROUTE,
                DeliveryRouteOrder::STATUS_ARRIVED,
            ], true);
        }) ?? $this->stops->first(fn (DeliveryRouteOrder $stop) => $stop->status === DeliveryRouteOrder::STATUS_PENDING);
    }

    public function nextPendingAfter(DeliveryRouteOrder $stop): ?DeliveryRouteOrder
    {
        return $this->stops->first(
            fn (DeliveryRouteOrder $row) => $row->stop_no > $stop->stop_no && $row->status === DeliveryRouteOrder::STATUS_PENDING
        );
    }
}
