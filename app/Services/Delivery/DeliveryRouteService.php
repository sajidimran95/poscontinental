<?php

namespace App\Services\Delivery;

use App\Models\Company;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteOrder;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryRouteService
{
    public function __construct(
        private RouteOptimizationService $optimizer,
        private DeliveryAreaService $areas,
    ) {}

    /**
     * @param  list<int>  $orderIds
     */
    public function assignOrders(User $actor, array $orderIds, int $driverId, string $date): int
    {
        $this->assertManage($actor);
        $companyId = (int) $actor->company_id;
        $driver = $this->deliveryDriver($companyId, $driverId);
        $ids = array_values(array_unique(array_map('intval', $orderIds)));
        if ($ids === []) {
            throw ValidationException::withMessages(['orders' => 'Select at least one order.']);
        }

        $orders = SalesOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->get();

        if ($orders->count() !== count($ids)) {
            throw ValidationException::withMessages(['orders' => 'One or more orders were not found.']);
        }

        $outside = [];
        foreach ($orders as $order) {
            if (! $this->areas->isDeliverable($order, $companyId)) {
                $outside[] = $order->order_number ?: ('#'.$order->id);
            }
        }
        if ($outside !== []) {
            throw ValidationException::withMessages([
                'orders' => 'This address is outside the current delivery area: '.implode(', ', $outside),
            ]);
        }

        return DB::transaction(function () use ($orders, $driver, $date) {
            foreach ($orders as $order) {
                $this->ensureCoordinates($order);
                $order->delivery_user_id = $driver->id;
                $order->delivery_date = $date;
                if (! in_array($order->delivery_status, ['delivered', 'failed'], true)) {
                    $order->delivery_status = 'assigned';
                }
                $order->save();
            }

            return $orders->count();
        });
    }

    public function generateRoute(User $actor, int $driverId, string $date): DeliveryRoute
    {
        $this->assertManage($actor);
        $companyId = (int) $actor->company_id;
        $driver = $this->deliveryDriver($companyId, $driverId);
        $company = Company::query()->whereKey($companyId)->firstOrFail();
        $origin = $this->originFromCompany($company);

        $orders = SalesOrder::query()
            ->with('customer')
            ->where('company_id', $companyId)
            ->where('delivery_user_id', $driver->id)
            ->whereDate('delivery_date', $date)
            ->whereHas('invoice')
            ->where(function ($q) {
                $q->whereNull('delivery_status')
                    ->orWhereNotIn('delivery_status', ['delivered', 'failed']);
            })
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages(['orders' => 'No assigned invoices for this driver and date.']);
        }

        foreach ($orders as $order) {
            $this->ensureCoordinates($order);
        }

        $withCoords = $orders->filter(fn (SalesOrder $o) => $o->shipping_latitude && $o->shipping_longitude);
        $without = $orders->reject(fn (SalesOrder $o) => $o->shipping_latitude && $o->shipping_longitude);

        $stops = [];
        foreach ($withCoords as $order) {
            $stops[$order->id] = [
                'lat' => (float) $order->shipping_latitude,
                'lng' => (float) $order->shipping_longitude,
            ];
        }

        $optimized = $stops === []
            ? ['order' => $orders->pluck('id')->all(), 'distances' => [], 'durations' => [], 'total_distance' => 0, 'total_duration' => 0]
            : $this->optimizer->optimize($origin, $stops);

        $sequence = array_values(array_unique([
            ...$optimized['order'],
            ...$without->pluck('id')->all(),
        ]));

        return DB::transaction(function () use ($companyId, $driver, $company, $date, $origin, $orders, $sequence, $optimized) {
            $existing = DeliveryRoute::query()
                ->where('company_id', $companyId)
                ->where('delivery_user_id', $driver->id)
                ->whereDate('route_date', $date)
                ->where('status', DeliveryRoute::STATUS_PLANNED)
                ->latest('id')
                ->first();

            $started = DeliveryRoute::query()
                ->where('company_id', $companyId)
                ->where('delivery_user_id', $driver->id)
                ->whereDate('route_date', $date)
                ->where('status', DeliveryRoute::STATUS_STARTED)
                ->exists();
            if ($started) {
                throw ValidationException::withMessages([
                    'orders' => 'This driver already has a started route for that date. Finish or cancel it before generating a new one.',
                ]);
            }

            $route = $existing ?? new DeliveryRoute;
            $route->fill([
                'company_id' => $companyId,
                'delivery_user_id' => $driver->id,
                'company_location_id' => null,
                'route_date' => $date,
                'status' => DeliveryRoute::STATUS_PLANNED,
                'start_name' => $company->name ?: 'Warehouse',
                'start_address' => $company->formattedAddress(),
                'start_latitude' => $origin['lat'],
                'start_longitude' => $origin['lng'],
            ]);
            $route->started_at = null;
            $route->completed_at = null;
            $route->save();

            $route->stops()->delete();

            $stopNo = 1;
            $totalDist = 0;
            $totalDur = 0;
            foreach ($sequence as $orderId) {
                /** @var SalesOrder|null $order */
                $order = $orders->firstWhere('id', (int) $orderId);
                if (! $order) {
                    continue;
                }
                $dist = (int) ($optimized['distances'][$order->id] ?? 0);
                $dur = (int) ($optimized['durations'][$order->id] ?? 0);
                $totalDist += $dist;
                $totalDur += $dur;

                DeliveryRouteOrder::query()->create([
                    'delivery_route_id' => $route->id,
                    'order_id' => $order->id,
                    'stop_no' => $stopNo,
                    'distance_from_previous' => $dist,
                    'estimated_duration_from_previous' => $dur,
                    'status' => DeliveryRouteOrder::STATUS_PENDING,
                    'ship_to_name' => $order->ship_to_name ?: $order->customer?->company_name,
                    'ship_to_phone' => $order->ship_to_phone ?: $order->customer?->telephone,
                    'ship_to_address' => $order->ship_to_address,
                    'ship_to_city' => $order->ship_to_city,
                    'ship_to_state' => $order->ship_to_state,
                    'ship_to_zip' => $order->ship_to_zip,
                    'latitude' => $order->shipping_latitude,
                    'longitude' => $order->shipping_longitude,
                ]);
                $stopNo++;
            }

            $route->total_orders = $stopNo - 1;
            $route->total_distance = $totalDist;
            $route->estimated_duration = $totalDur;
            $route->save();

            return $route->fresh(['stops.order.customer', 'driver', 'location']);
        });
    }

    public function startRoute(User $user, DeliveryRoute $route): void
    {
        $this->assertCanUpdate($user, $route);
        if ($route->status === DeliveryRoute::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['route' => 'This route was cancelled.']);
        }
        if ($route->status === DeliveryRoute::STATUS_COMPLETED) {
            return;
        }

        $this->markRouteStarted($route, markFirstHint: true);
    }

    public function markArrived(User $user, DeliveryRouteOrder $stop): void
    {
        $stop->loadMissing('route');
        $this->assertCanUpdate($user, $stop->route);
        $this->assertStopCanAct($stop);
        $this->markRouteStarted($stop->route, markFirstHint: false);
        $stop->status = DeliveryRouteOrder::STATUS_ARRIVED;
        $stop->arrived_at ??= now();
        $stop->save();
        $this->syncOrderDeliveryStatus($stop, 'arrived');
    }

    public function markDelivered(User $user, DeliveryRouteOrder $stop, ?string $notes = null): void
    {
        DB::transaction(function () use ($user, $stop, $notes) {
            $stop->loadMissing('route.stops');
            $this->assertCanUpdate($user, $stop->route);
            $this->assertStopCanAct($stop);
            $this->markRouteStarted($stop->route, markFirstHint: false);
            $stop->status = DeliveryRouteOrder::STATUS_DELIVERED;
            $stop->delivered_at = now();
            if ($notes !== null && trim($notes) !== '') {
                $stop->delivery_notes = $notes;
            }
            $stop->save();
            $this->syncOrderDeliveryStatus($stop, 'delivered');
            $this->advanceRoute($stop->route->fresh(), $stop->fresh());
        });
    }

    public function markFailed(User $user, DeliveryRouteOrder $stop, string $reason, ?string $notes = null): void
    {
        if (! isset(DeliveryRouteOrder::FAIL_REASONS[$reason])) {
            throw ValidationException::withMessages(['fail_reason' => 'Select a failure reason.']);
        }
        DB::transaction(function () use ($user, $stop, $reason, $notes) {
            $stop->loadMissing('route.stops');
            $this->assertCanUpdate($user, $stop->route);
            $this->assertStopCanAct($stop);
            $this->markRouteStarted($stop->route, markFirstHint: false);
            $stop->status = DeliveryRouteOrder::STATUS_FAILED;
            $stop->fail_reason = $reason;
            $stop->delivery_notes = $notes;
            $stop->delivered_at = now();
            $stop->save();
            $this->syncOrderDeliveryStatus($stop, 'failed');
            $this->advanceRoute($stop->route->fresh(), $stop->fresh());
        });
    }

    public function reorderStops(User $actor, DeliveryRoute $route, array $stopIdsInOrder): void
    {
        $this->assertManage($actor);
        if ((int) $route->company_id !== (int) $actor->company_id) {
            abort(403);
        }
        $ids = array_values(array_map('intval', $stopIdsInOrder));
        $stops = $route->stops()->get()->keyBy('id');
        $n = 1;
        foreach ($ids as $id) {
            if (! isset($stops[$id])) {
                continue;
            }
            $stops[$id]->stop_no = $n++;
            $stops[$id]->save();
        }
    }

    public function driverRouteForDate(User $driver, string $date): ?DeliveryRoute
    {
        return DeliveryRoute::query()
            ->with(['stops.order.customer', 'location', 'driver'])
            ->where('company_id', $driver->company_id)
            ->where('delivery_user_id', $driver->id)
            ->whereDate('route_date', $date)
            ->where('status', '!=', DeliveryRoute::STATUS_CANCELLED)
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, DeliveryRoute>
     */
    public function routesForDate(int $companyId, string $date): Collection
    {
        return DeliveryRoute::query()
            ->with(['stops', 'driver', 'location'])
            ->where('company_id', $companyId)
            ->whereDate('route_date', $date)
            ->where('status', '!=', DeliveryRoute::STATUS_CANCELLED)
            ->orderBy('delivery_user_id')
            ->get();
    }

    public function driverFinishedStops(User $driver, ?string $date = null)
    {
        return DeliveryRouteOrder::query()
            ->with(['order.customer', 'route'])
            ->whereHas('route', function ($q) use ($driver, $date) {
                $q->where('company_id', $driver->company_id)
                    ->where('delivery_user_id', $driver->id)
                    ->where('status', '!=', DeliveryRoute::STATUS_CANCELLED)
                    ->when($date, fn ($r) => $r->whereDate('route_date', $date));
            })
            ->whereIn('status', [DeliveryRouteOrder::STATUS_DELIVERED, DeliveryRouteOrder::STATUS_FAILED])
            ->orderByDesc('delivered_at')
            ->limit(250)
            ->get();
    }

    public function driverFinishedCount(User $driver, ?string $date = null): int
    {
        return DeliveryRouteOrder::query()
            ->whereHas('route', function ($q) use ($driver, $date) {
                $q->where('company_id', $driver->company_id)
                    ->where('delivery_user_id', $driver->id)
                    ->where('status', '!=', DeliveryRoute::STATUS_CANCELLED)
                    ->when($date, fn ($r) => $r->whereDate('route_date', $date));
            })
            ->where('status', DeliveryRouteOrder::STATUS_DELIVERED)
            ->count();
    }

    /**
     * @param  'open'|'all'|null  $mode
     */
    public function driverStopsInRange(User $driver, string $from, string $to, string $mode = 'all')
    {
        return DeliveryRouteOrder::query()
            ->select('delivery_route_orders.*')
            ->with(['order.customer', 'route'])
            ->join('driver_delivery_routes as ddr', 'ddr.id', '=', 'delivery_route_orders.delivery_route_id')
            ->where('ddr.company_id', $driver->company_id)
            ->where('ddr.delivery_user_id', $driver->id)
            ->where('ddr.status', '!=', DeliveryRoute::STATUS_CANCELLED)
            ->whereDate('ddr.route_date', '>=', $from)
            ->whereDate('ddr.route_date', '<=', $to)
            ->when($mode === 'open', fn ($q) => $q->whereNotIn('delivery_route_orders.status', [
                DeliveryRouteOrder::STATUS_DELIVERED,
                DeliveryRouteOrder::STATUS_FAILED,
                DeliveryRouteOrder::STATUS_SKIPPED,
            ]))
            ->orderByDesc('ddr.route_date')
            ->orderBy('delivery_route_orders.stop_no')
            ->limit(400)
            ->get();
    }

    public function saveNotes(User $user, DeliveryRouteOrder $stop, ?string $notes): void
    {
        $stop->loadMissing('route');
        $this->assertCanUpdate($user, $stop->route);
        $stop->delivery_notes = $notes;
        $stop->save();
    }

    protected function markRouteStarted(DeliveryRoute $route, bool $markFirstHint): void
    {
        if ($route->status === DeliveryRoute::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['route' => 'This route was cancelled.']);
        }
        if ($route->status === DeliveryRoute::STATUS_COMPLETED) {
            return;
        }
        if ($route->status === DeliveryRoute::STATUS_PLANNED) {
            $route->status = DeliveryRoute::STATUS_STARTED;
            $route->started_at ??= now();
            $route->save();
        }
        if (! $markFirstHint) {
            return;
        }
        $first = $route->stops()->orderBy('stop_no')->first();
        if ($first && $first->status === DeliveryRouteOrder::STATUS_PENDING) {
            $first->status = DeliveryRouteOrder::STATUS_EN_ROUTE;
            $first->save();
            $this->syncOrderDeliveryStatus($first, 'en_route');
        }
    }

    protected function assertStopCanAct(DeliveryRouteOrder $stop): void
    {
        if ($stop->isFinished()) {
            throw ValidationException::withMessages(['stop' => 'This stop is already closed.']);
        }
    }

    protected function advanceRoute(DeliveryRoute $route, DeliveryRouteOrder $justFinished): void
    {
        $route->load('stops');

        $open = $route->stops->first(fn (DeliveryRouteOrder $s) => in_array($s->status, [
            DeliveryRouteOrder::STATUS_PENDING,
            DeliveryRouteOrder::STATUS_EN_ROUTE,
            DeliveryRouteOrder::STATUS_ARRIVED,
        ], true));

        if (! $open) {
            $route->status = DeliveryRoute::STATUS_COMPLETED;
            $route->completed_at = now();
            $route->save();
        }
    }

    protected function syncOrderDeliveryStatus(DeliveryRouteOrder $stop, string $status): void
    {
        $order = $this->salesOrderForStop($stop);
        if (! $order) {
            return;
        }

        $payload = ['delivery_status' => $status];
        if ($status === 'delivered') {
            $payload['ship_date'] = $order->ship_date ?: now()->toDateString();
        }

        SalesOrder::query()->whereKey($order->id)->update($payload);

        if ($stop->order_id && (int) $stop->order_id !== (int) $order->id) {
            $stop->order_id = $order->id;
            $stop->save();
        }
    }

    protected function salesOrderForStop(DeliveryRouteOrder $stop): ?SalesOrder
    {
        $stop->loadMissing('order.invoice');
        if ($stop->order) {
            return $stop->order;
        }

        $invoice = Invoice::query()->with('salesOrder')->find($stop->order_id);

        return $invoice?->salesOrder;
    }

    protected function ensureCoordinates(SalesOrder $order): void
    {
        if ($order->shipping_latitude && $order->shipping_longitude) {
            return;
        }

        $query = trim(collect([
            $order->ship_to_address,
            $order->ship_to_city,
            $order->ship_to_state,
            $order->ship_to_zip,
            'USA',
        ])->filter()->implode(', '));

        $geo = $this->optimizer->geocode($query);
        if (! $geo) {
            return;
        }

        $order->shipping_latitude = $geo['lat'];
        $order->shipping_longitude = $geo['lng'];
        $order->save();
    }

    /**
     * @return Collection<int, DeliveryRoute>
     */
    public function routesHistory(int $companyId, ?int $driverId, string $from, string $to): Collection
    {
        return DeliveryRoute::query()
            ->with(['stops', 'driver', 'location'])
            ->where('company_id', $companyId)
            ->when($driverId, fn ($q) => $q->where('delivery_user_id', $driverId))
            ->whereDate('route_date', '>=', $from)
            ->whereDate('route_date', '<=', $to)
            ->orderByDesc('route_date')
            ->orderBy('delivery_user_id')
            ->limit(200)
            ->get();
    }

    /**
     * @return array{lat: float, lng: float}
     */
    protected function originFromCompany(Company $company): array
    {
        if (trim((string) $company->address) === '' && trim((string) $company->city) === '') {
            throw ValidationException::withMessages([
                'company' => 'Set the company address in File → Company Settings. That address is the delivery start point.',
            ]);
        }

        $geo = $this->optimizer->geocode($company->formattedAddress());
        if (! is_array($geo) || ! isset($geo['lat'], $geo['lng'])) {
            throw ValidationException::withMessages([
                'company' => 'Could not locate the company address for routing. Check File → Company Settings.',
            ]);
        }

        return [
            'lat' => (float) $geo['lat'],
            'lng' => (float) $geo['lng'],
        ];
    }

    protected function deliveryDriver(int $companyId, int $driverId): User
    {
        if ($driverId <= 0) {
            throw ValidationException::withMessages(['driver' => 'Select a delivery driver.']);
        }

        $user = User::query()
            ->with('role')
            ->where('company_id', $companyId)
            ->whereKey($driverId)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages(['driver' => 'Select a delivery driver.']);
        }

        if ($user->role?->name !== 'delivery' && ! $user->canAccessFeature('delivery.driver', 'view')) {
            throw ValidationException::withMessages(['driver' => 'Select a Delivery user.']);
        }

        return $user;
    }

    protected function assertManage(User $user): void
    {
        if (! $user->canAccessFeature('delivery.manage', 'edit')) {
            abort(403, 'You cannot assign delivery routes.');
        }
    }

    protected function assertCanUpdate(User $user, DeliveryRoute $route): void
    {
        if ((int) $route->company_id !== (int) $user->company_id) {
            abort(403);
        }
        if ($user->canAccessFeature('delivery.manage', 'edit')) {
            return;
        }
        if ((int) $route->delivery_user_id === (int) $user->id
            && ($user->isDelivery() || $user->canAccessFeature('delivery.driver', 'edit'))) {
            return;
        }
        abort(403, 'You cannot update this delivery route.');
    }
}
