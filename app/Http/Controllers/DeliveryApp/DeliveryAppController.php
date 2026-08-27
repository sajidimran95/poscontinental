<?php

namespace App\Http\Controllers\DeliveryApp;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteOrder;
use App\Services\Delivery\DeliveryRouteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeliveryAppController extends Controller
{
    public function home(Request $request, DeliveryRouteService $service)
    {
        $user = $request->user('delivery');
        $date = now()->toDateString();
        $route = $this->todayRoute($request, $service);
        $deliveredToday = $route ? $route->stops->where('status', 'delivered')->count() : 0;
        $failedToday = $route ? $route->stops->where('status', 'failed')->count() : 0;
        $left = $route ? $route->remainingCount() : 0;
        $suggested = $route?->currentStop();
        $allTimeDelivered = $service->driverFinishedCount($user);

        return view('delivery-app.home', compact(
            'user', 'route', 'date', 'deliveredToday', 'failedToday', 'left', 'suggested', 'allTimeDelivered'
        ));
    }

    public function route(Request $request, DeliveryRouteService $service)
    {
        $user = $request->user('delivery');
        $date = now()->toDateString();
        $route = $this->routeFromStopQuery($request) ?? $this->todayRoute($request, $service);
        $active = $this->activeStop($route, (int) $request->query('stop', 0));
        $suggested = $route?->currentStop();
        $delivered = $route ? $route->stops->where('status', 'delivered')->count() : 0;
        $mapStops = $route ? $route->stops->map(fn ($s) => [
            'id' => $s->id,
            'n' => $s->stop_no,
            'lat' => $s->latitude,
            'lng' => $s->longitude,
            'label' => '#'.$s->order?->order_number,
            'name' => $s->ship_to_name,
        ])->all() : [];

        return view('delivery-app.route', compact('user', 'route', 'active', 'suggested', 'delivered', 'mapStops', 'date'));
    }

    public function assigned(Request $request, DeliveryRouteService $service)
    {
        $user = $request->user('delivery');
        [$from, $to] = $this->dateRange($request, now()->toDateString(), now()->toDateString());
        $stops = $service->driverStopsInRange($user, $from, $to, 'open');

        return view('delivery-app.assigned', compact('user', 'stops', 'from', 'to'));
    }

    public function all(Request $request, DeliveryRouteService $service)
    {
        $user = $request->user('delivery');
        [$from, $to] = $this->dateRange($request, now()->subDays(7)->toDateString(), now()->toDateString());
        $stops = $service->driverStopsInRange($user, $from, $to, 'all');
        $openCount = $stops->filter(fn ($s) => $s->canAct())->count();
        $deliveredCount = $stops->where('status', 'delivered')->count();
        $days = $stops
            ->groupBy(fn ($s) => optional($s->route?->route_date)->toDateString() ?: 'unknown')
            ->map(fn ($group) => $group->sortBy('stop_no')->values());

        return view('delivery-app.all', compact('user', 'stops', 'days', 'from', 'to', 'openCount', 'deliveredCount'));
    }

    public function history(Request $request, DeliveryRouteService $service)
    {
        $user = $request->user('delivery');
        $all = $request->boolean('all');
        $date = $all ? null : (string) $request->query('date', now()->toDateString());
        if ($date !== null && $date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->toDateString();
        }
        $stops = $service->driverFinishedStops($user, $all ? null : $date);
        $deliveredCount = $stops->where('status', 'delivered')->count();
        $failedCount = $stops->where('status', 'failed')->count();

        return view('delivery-app.history', compact('user', 'stops', 'date', 'all', 'deliveredCount', 'failedCount'));
    }

    public function start(Request $request, DeliveryRouteService $service)
    {
        $route = $this->todayRoute($request, $service);
        abort_unless($route && $request->user('delivery')->can('update', $route), 403);
        $service->startRoute($request->user('delivery'), $route);

        return $this->backToStop($request);
    }

    public function arrived(Request $request, int $stop, DeliveryRouteService $service)
    {
        $row = $this->ownedStop($request, $stop);
        $service->markArrived($request->user('delivery'), $row);

        return $this->backToStop($request, $row->id);
    }

    public function delivered(Request $request, int $stop, DeliveryRouteService $service)
    {
        $row = $this->ownedStop($request, $stop);
        $notes = trim((string) $request->input('notes', ''));
        $service->markDelivered($request->user('delivery'), $row, $notes !== '' ? $notes : null);

        return $this->backToStop($request, $row->id);
    }

    public function failed(Request $request, int $stop, DeliveryRouteService $service)
    {
        $row = $this->ownedStop($request, $stop);
        try {
            $service->markFailed(
                $request->user('delivery'),
                $row,
                (string) $request->input('fail_reason', ''),
                $request->input('notes')
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return $this->backToStop($request, $row->id);
    }

    public function notes(Request $request, int $stop, DeliveryRouteService $service)
    {
        $row = $this->ownedStop($request, $stop);
        $service->saveNotes($request->user('delivery'), $row, $request->input('notes'));

        return $this->backToStop($request, $row->id)->with('status', ['success' => 1, 'msg' => 'Note saved.']);
    }

    protected function routeFromStopQuery(Request $request): ?DeliveryRoute
    {
        $stopId = (int) $request->query('stop', 0);
        if (! $stopId) {
            return null;
        }
        $stop = DeliveryRouteOrder::query()->with(['route.stops.order.customer', 'route.driver'])->find($stopId);
        if (! $stop?->route || $request->user('delivery')->cannot('view', $stop->route)) {
            return null;
        }

        return $stop->route;
    }

    /** @return array{0: string, 1: string} */
    protected function dateRange(Request $request, string $defaultFrom, string $defaultTo): array
    {
        $from = (string) $request->query('from', $defaultFrom);
        $to = (string) $request->query('to', $defaultTo);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = $defaultFrom;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $defaultTo;
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    protected function todayRoute(Request $request, DeliveryRouteService $service): ?DeliveryRoute
    {
        $user = $request->user('delivery');
        $route = $service->driverRouteForDate($user, now()->toDateString());
        if ($route && $user->cannot('view', $route)) {
            abort(403);
        }

        return $route;
    }

    protected function activeStop(?DeliveryRoute $route, int $openId): ?DeliveryRouteOrder
    {
        if (! $route) {
            return null;
        }
        if ($openId) {
            $picked = $route->stops->firstWhere('id', $openId);
            if ($picked) {
                return $picked;
            }
        }

        return $route->currentStop();
    }

    protected function backToStop(Request $request, ?int $stopId = null)
    {
        $id = $stopId ?: (int) $request->input('stop', $request->query('stop', 0));

        return redirect()->route('delivery.app.route', array_filter(['stop' => $id ?: null]));
    }

    protected function ownedStop(Request $request, int $stopId): DeliveryRouteOrder
    {
        $stop = DeliveryRouteOrder::query()->with('route')->findOrFail($stopId);
        abort_unless($request->user('delivery')->can('update', $stop->route), 403);

        return $stop;
    }
}
