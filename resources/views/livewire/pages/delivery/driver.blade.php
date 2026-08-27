<?php

use App\Models\DeliveryRouteOrder;
use App\Services\Delivery\DeliveryRouteService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title("Today's Deliveries")] class extends Component
{
    public string $fail_reason = '';

    public string $fail_notes = '';

    public ?int $failStopId = null;

    public string $errorMessage = '';

    public ?int $openStopId = null;

    public string $delivery_notes = '';

    public function mount(DeliveryRouteService $service): void
    {
        abort_unless(auth()->user()?->canAccessFeature('delivery.driver', 'view'), 403);
        $route = $service->driverRouteForDate(auth()->user(), now()->toDateString());
        $suggested = $route?->currentStop();
        if ($suggested) {
            $this->openStopId = $suggested->id;
            $this->delivery_notes = (string) ($suggested->delivery_notes ?? '');
        }
    }

    public function with(DeliveryRouteService $service): array
    {
        $user = auth()->user();
        $route = $service->driverRouteForDate($user, now()->toDateString());
        if ($route && $user->cannot('view', $route)) {
            abort(403);
        }

        $suggested = $route?->currentStop();
        $active = $route?->stops->firstWhere('id', $this->openStopId) ?? $suggested;
        $delivered = $route ? $route->stops->where('status', 'delivered')->count() : 0;
        $mapStops = $route ? $route->stops->map(fn ($s) => [
            'id' => $s->id,
            'n' => $s->stop_no,
            'lat' => $s->latitude,
            'lng' => $s->longitude,
            'label' => '#'.$s->order?->order_number,
        ])->all() : [];

        return compact('route', 'suggested', 'active', 'delivered', 'mapStops');
    }

    public function openStop(int $stopId): void
    {
        $stop = $this->ownedStop($stopId);
        $this->openStopId = $stop->id;
        $this->delivery_notes = (string) ($stop->delivery_notes ?? '');
        $this->errorMessage = '';
    }

    public function start(DeliveryRouteService $service): void
    {
        $route = $service->driverRouteForDate(auth()->user(), now()->toDateString());
        abort_unless($route && auth()->user()->can('update', $route), 403);
        $service->startRoute(auth()->user(), $route);
    }

    public function arrived(int $stopId, DeliveryRouteService $service): void
    {
        $stop = $this->ownedStop($stopId);
        $this->openStopId = $stop->id;
        $service->markArrived(auth()->user(), $stop);
    }

    public function delivered(int $stopId, DeliveryRouteService $service): void
    {
        $stop = $this->ownedStop($stopId);
        $this->openStopId = $stop->id;
        try {
            $service->markDelivered(auth()->user(), $stop, $this->delivery_notes !== '' ? $this->delivery_notes : null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Could not mark delivered.';
        }
    }

    public function saveNotes(int $stopId, DeliveryRouteService $service): void
    {
        $stop = $this->ownedStop($stopId);
        $this->openStopId = $stop->id;
        $service->saveNotes(auth()->user(), $stop, $this->delivery_notes);
    }

    public function openFail(int $stopId): void
    {
        $this->ownedStop($stopId);
        $this->openStopId = $stopId;
        $this->failStopId = $stopId;
        $this->fail_reason = '';
        $this->fail_notes = '';
    }

    public function closeFail(): void
    {
        $this->failStopId = null;
        $this->fail_reason = '';
        $this->fail_notes = '';
    }

    public function confirmFail(DeliveryRouteService $service): void
    {
        if (! $this->failStopId) {
            return;
        }
        $stop = $this->ownedStop($this->failStopId);
        try {
            $service->markFailed(auth()->user(), $stop, $this->fail_reason, $this->fail_notes);
            $this->failStopId = null;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?: 'Could not mark failed.';
        }
    }

    protected function ownedStop(int $stopId): DeliveryRouteOrder
    {
        $stop = DeliveryRouteOrder::query()->with('route')->findOrFail($stopId);
        abort_unless(auth()->user()->can('update', $stop->route), 403);

        return $stop;
    }
}; ?>

<div class="desk-page dlv-page">
    <div class="desk-main desk-main-rail-layout dlv-driver">
    <div class="desk-titlebar">
        <h2 class="desk-title">Today's Deliveries</h2>
    </div>

    @if ($errorMessage !== '')
        <div class="dlv-banner is-err">{{ $errorMessage }}</div>
    @endif

    @if (! $route)
        <p class="dlv-muted" style="padding:0.85rem">No route assigned for today.</p>
    @else
        <div class="dlv-summary">
            <div><strong>Driver</strong><span>{{ $route->driver?->name }}</span></div>
            <div><strong>Total</strong><span>{{ $route->total_orders }}</span></div>
            <div><strong>Delivered</strong><span>{{ $delivered }}</span></div>
            <div><strong>Remaining</strong><span>{{ $route->remainingCount() }}</span></div>
            @if ($active)
                <div><strong>Open</strong><span>STOP {{ $active->stop_no }}</span></div>
            @endif
            @if ($suggested && (! $active || $suggested->id !== $active->id))
                <div><strong>Suggested</strong><span>STOP {{ $suggested->stop_no }}</span></div>
            @endif
        </div>

        <p class="dlv-muted" style="padding:0 0.85rem">Suggested order is serial (1, 2, 3…). Click any stop to deliver out of order.</p>

        <div class="dlv-route-split">
            <div class="dlv-route-stops">
        @if ($route->status === 'planned')
            <div style="padding:0.75rem"><button type="button" class="desk-btn desk-btn-primary" wire:click="start">Start route</button></div>
        @endif

        @if ($active)
            <article class="dlv-order-card" style="margin:0.75rem">
                <div class="dlv-stop-badge">{{ $active->stop_no }}</div>
                <h3>Order #{{ $active->order?->order_number }}</h3>
                <p><strong>Customer:</strong> {{ $active->ship_to_name ?: $active->order?->customer?->company_name }}</p>
                <p><strong>Address:</strong><br>{!! nl2br(e($active->formattedAddress())) !!}</p>
                <p><strong>Phone:</strong> {{ $active->ship_to_phone ?: $active->order?->customer?->telephone ?: '—' }}</p>
                <p><strong>Order Total:</strong> ${{ number_format((float) ($active->order?->total ?? 0), 2) }}</p>
                <p><strong>Status:</strong> {{ $active->statusLabel() }}</p>
                <label class="dlv-muted">Delivery note
                    <textarea class="so-input" rows="2" wire:model="delivery_notes" placeholder="Who signed, gate code, shortage…"></textarea>
                </label>
                <div class="dlv-card-actions">
                    <button type="button" class="desk-btn" wire:click="saveNotes({{ $active->id }})">Save note</button>
                    <a class="desk-btn desk-btn-primary" href="{{ $active->navigateUrl() }}" target="_blank" rel="noopener">Navigate</a>
                    @if ($active->canAct() && $active->status !== 'arrived')
                        <button type="button" class="desk-btn" wire:click="arrived({{ $active->id }})">Mark Arrived</button>
                    @endif
                    @if ($active->canAct())
                        <button type="button" class="desk-btn desk-btn-primary" wire:click="delivered({{ $active->id }})">Mark Delivered</button>
                        <button type="button" class="desk-btn" wire:click="openFail({{ $active->id }})">Delivery Failed</button>
                    @endif
                </div>
            </article>
        @endif

        <ol class="dlv-mini-list">
            @foreach ($route->stops as $stop)
                <li @class(['is-current' => $active && $active->id === $stop->id])>
                    <button type="button" class="dlv-stop-pick" wire:click="openStop({{ $stop->id }})">
                        {{ $stop->stop_no }} · #{{ $stop->order?->order_number }} · {{ $stop->statusLabel() }}
                    </button>
                </li>
            @endforeach
        </ol>
            </div>
            <div class="dlv-route-map-wrap">
                <div id="dlv-driver-map" class="dlv-map" wire:ignore></div>
            </div>
        </div>
    @endif

    @if ($failStopId)
        <div class="dlv-modal-backdrop" wire:click.self="closeFail" role="dialog" aria-modal="true">
            <div class="dlv-modal">
                <div class="dlv-modal-head">
                    <h3>Delivery Failed</h3>
                    <button type="button" class="desk-modal-close" wire:click="closeFail" aria-label="Close">×</button>
                </div>
                <label>Reason
                    <select class="so-input" wire:model="fail_reason">
                        <option value="">— Select —</option>
                        @foreach (\App\Models\DeliveryRouteOrder::FAIL_REASONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Notes
                    <textarea class="so-input" rows="3" wire:model="fail_notes"></textarea>
                </label>
                <div class="dlv-card-actions">
                    <button type="button" class="desk-btn desk-btn-primary" wire:click="confirmFail">Save</button>
                    <button type="button" class="desk-btn" wire:click="closeFail">Cancel</button>
                </div>
            </div>
        </div>
    @endif
    </div>
</div>

@script
<script>
    const origin = @json($route ? ['lat' => $route->start_latitude, 'lng' => $route->start_longitude, 'name' => $route->start_name] : ['lat' => null, 'lng' => null, 'name' => '']);
    const stops = @json($mapStops);
    const pin = (label, start) => window.L.divIcon({
        className: '',
        html: '<span class="dlv-map-num' + (start ? ' is-start' : '') + '">' + label + '</span>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });
    const boot = () => {
        const el = document.getElementById('dlv-driver-map');
        if (!window.L || !el) return;
        if (window.__dlvDriverMap) {
            window.__dlvDriverMap.remove();
            window.__dlvDriverMap = null;
        }
        const map = L.map(el);
        window.__dlvDriverMap = map;
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
        const pts = [];
        if (origin.lat && origin.lng) {
            pts.push([origin.lat, origin.lng]);
            L.marker([origin.lat, origin.lng], { icon: pin('S', true) }).addTo(map).bindPopup('Start');
        }
        stops.forEach((s) => {
            if (!s.lat || !s.lng) return;
            pts.push([s.lat, s.lng]);
            const m = L.marker([s.lat, s.lng], { icon: pin(String(s.n), false) }).addTo(map)
                .bindPopup('Stop ' + s.n + ' ' + (s.label || ''));
            m.on('click', function () {
                @this.call('openStop', s.id);
            });
        });
        if (pts.length > 1) L.polyline(pts, { color: '#1e3a5f', weight: 4 }).addTo(map);
        const fit = () => {
            map.invalidateSize();
            if (pts.length) map.fitBounds(pts, { padding: [28, 28] });
            else map.setView([42.3314, -83.0458], 7);
        };
        requestAnimationFrame(() => { fit(); setTimeout(fit, 200); });
    };
    if (!document.getElementById('dlv-leaflet-css')) {
        const css = document.createElement('link');
        css.id = 'dlv-leaflet-css';
        css.rel = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(css);
    }
    if (!window.L) {
        const s = document.createElement('script');
        s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        s.onload = boot;
        document.body.appendChild(s);
    } else boot();
</script>
@endscript

