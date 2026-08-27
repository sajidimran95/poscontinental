<?php

use App\Models\DeliveryRoute;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Delivery Route')] class extends Component
{
    public DeliveryRoute $deliveryRoute;

    public function mount(DeliveryRoute $deliveryRoute): void
    {
        abort_unless(auth()->user()?->can('view', $deliveryRoute), 403);
        $this->deliveryRoute = $deliveryRoute->load(['stops.order.customer', 'driver', 'location']);
    }

    public function with(): array
    {
        $this->deliveryRoute = DeliveryRoute::query()
            ->with(['stops.order.customer', 'stops.order.invoice', 'driver', 'location'])
            ->findOrFail($this->deliveryRoute->id);
        $delivered = $this->deliveryRoute->stops->where('status', 'delivered')->count();

        return [
            'route' => $this->deliveryRoute,
            'delivered' => $delivered,
            'remaining' => $this->deliveryRoute->remainingCount(),
            'miles' => round($this->deliveryRoute->total_distance / 1609.34, 1),
            'minutes' => (int) round($this->deliveryRoute->estimated_duration / 60),
            'mapStops' => $this->deliveryRoute->stops->map(fn ($s) => [
                'n' => $s->stop_no,
                'lat' => $s->latitude,
                'lng' => $s->longitude,
                'label' => '#'.$s->order?->order_number,
                'name' => $s->ship_to_name ?: $s->order?->customer?->company_name,
                'status' => $s->status,
            ])->all(),
        ];
    }
}; ?>

<div class="desk-page dlv-page" style="height:100%;min-height:calc(100vh - 7.25rem)">
    <div class="desk-main desk-main-rail-layout" style="height:100%;min-height:0;overflow:hidden">
        <div class="desk-titlebar">
            <h2 class="desk-title">{{ $route->driver?->name }} — Route</h2>
            <a href="{{ route('deliveries.routes') }}" class="desk-btn" wire:navigate>Back</a>
        </div>

        <div class="dlv-summary">
            <div><strong>Date</strong><span>{{ $route->route_date?->format('F j, Y') }}</span></div>
            <div><strong>Start</strong><span>{{ $route->start_name }}</span></div>
            <div><strong>Total orders</strong><span>{{ $route->total_orders }}</span></div>
            <div><strong>Delivered</strong><span>{{ $delivered }}</span></div>
            <div><strong>Remaining</strong><span>{{ $remaining }}</span></div>
            <div><strong>Distance</strong><span>{{ $miles }} mi</span></div>
            <div><strong>Est. time</strong><span>{{ $minutes }} min</span></div>
            <div><strong>Status</strong><span>{{ ucfirst($route->status) }}</span></div>
        </div>

        <div class="desk-main-split dlv-route-split" style="flex:1 1 auto;min-height:0;height:100%">
            <div class="desk-main-body dlv-route-stops" style="flex:0 0 28rem;max-width:28rem;overflow:auto">
                <ol class="dlv-timeline">
                    <li>
                        <div class="dlv-stop-badge is-start">S</div>
                        <div>
                            <strong>{{ $route->start_name }}</strong>
                            <p>{{ $route->start_address }}</p>
                        </div>
                    </li>
                    @foreach ($route->stops as $stop)
                        <li>
                            <div class="dlv-stop-badge">{{ $stop->stop_no }}</div>
                            <div>
                                <strong>Order #{{ $stop->order?->order_number }}</strong>
                                <p>{{ $stop->ship_to_name ?: $stop->order?->customer?->company_name }}</p>
                                <p class="dlv-muted">{{ $stop->formattedAddress() }}</p>
                                <span class="dlv-pill is-{{ $stop->status === 'delivered' ? 'delivered' : ($stop->status === 'failed' ? 'failed' : ($stop->status === 'en_route' || $stop->status === 'arrived' ? 'en_route' : 'pending')) }}">
                                    {{ $stop->statusLabel() }}
                                </span>
                                @if ($stop->delivery_notes)
                                    <p class="dlv-muted">Note: {{ $stop->delivery_notes }}</p>
                                @endif
                                <a class="desk-btn desk-btn-sm" href="{{ $stop->navigateUrl() }}" target="_blank" rel="noopener">Navigate</a>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
            <div class="dlv-route-map-wrap" style="flex:1 1 auto;min-width:0;min-height:28rem;position:relative;background:#d5dee8">
                <div
                    id="dlv-admin-map"
                    class="dlv-map"
                    wire:ignore
                    style="position:absolute;inset:0;z-index:1"
                    data-origin='@json(['lat' => $route->start_latitude, 'lng' => $route->start_longitude, 'name' => $route->start_name])'
                    data-stops='@json($mapStops)'
                ></div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    const origin = @json(['lat' => $route->start_latitude, 'lng' => $route->start_longitude, 'name' => $route->start_name]);
    const stops = @json($mapStops);

    const pin = (label, start) => window.L.divIcon({
        className: '',
        html: '<span class="dlv-map-num' + (start ? ' is-start' : '') + '">' + label + '</span>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -14],
    });

    const boot = () => {
        const el = document.getElementById('dlv-admin-map');
        if (!window.L || !el) return;
        if (window.__dlvAdminMap) {
            window.__dlvAdminMap.remove();
            window.__dlvAdminMap = null;
        }
        const map = L.map(el, { scrollWheelZoom: true });
        window.__dlvAdminMap = map;
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        const pts = [];
        if (origin.lat && origin.lng) {
            const o = [origin.lat, origin.lng];
            pts.push(o);
            L.marker(o, { icon: pin('S', true) }).addTo(map).bindPopup('Start: ' + (origin.name || 'Warehouse'));
        }
        stops.forEach((s) => {
            if (!s.lat || !s.lng) return;
            const p = [s.lat, s.lng];
            pts.push(p);
            L.marker(p, { icon: pin(String(s.n), false) })
                .addTo(map)
                .bindPopup('Order ' + s.n + ' ' + (s.label || '') + (s.name ? '<br>' + s.name : ''));
        });
        if (pts.length > 1) {
            L.polyline(pts, { color: '#1e3a5f', weight: 4 }).addTo(map);
        }
        const fit = () => {
            map.invalidateSize();
            if (pts.length) {
                map.fitBounds(pts, { padding: [36, 36] });
            } else {
                map.setView([42.3314, -83.0458], 7);
            }
        };
        requestAnimationFrame(() => {
            fit();
            setTimeout(fit, 200);
            setTimeout(fit, 800);
        });
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
    } else {
        boot();
    }
</script>
@endscript
