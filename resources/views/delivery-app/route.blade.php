@extends('delivery-app.layout')
@section('title', 'Route')
@section('nav_active', 'route')
@section('content')
<div class="top">
    <div>
        <h1>Today’s route</h1>
        <div class="sub">Tap any stop or pin — order 1, 2, 3 is only a suggestion</div>
    </div>
</div>
<div class="wrap">
    @if ($errors->any())
        <div class="err">{{ $errors->first() }}</div>
    @endif
    @if(session('status'))
        @php $st = session('status'); @endphp
        <div class="ok">{{ is_array($st) ? ($st['msg'] ?? '') : $st }}</div>
    @endif

    @if (! $route)
        <div class="card">
            <p>No route assigned for {{ $date }}.</p>
        </div>
    @else
        <div class="stats four">
            <div><strong>Total</strong><span>{{ $route->total_orders }}</span></div>
            <div><strong>Done</strong><span>{{ $delivered }}</span></div>
            <div><strong>Left</strong><span>{{ $route->remainingCount() }}</span></div>
            <div><strong>Open</strong><span>{{ $active?->stop_no ?: '—' }}</span></div>
        </div>

        @if ($route->status === 'planned')
            <form method="POST" action="{{ route('delivery.app.start') }}" style="margin-bottom:.75rem">
                @csrf
                <button class="btn btn-p" type="submit" style="width:100%">Start route</button>
            </form>
        @endif

        <div id="dlv-app-map" class="map"></div>

        @if ($active)
            @include('delivery-app.partials.stop-card', ['active' => $active, 'suggested' => $suggested])
        @endif

        <div class="card">
            <div class="muted">Stops</div>
            @foreach ($route->stops as $stop)
                <a class="stop {{ $active && $active->id === $stop->id ? 'on-row' : '' }}" href="{{ route('delivery.app.route', ['stop' => $stop->id]) }}#dlv-stop-card">
                    <div class="num {{ $active && $active->id === $stop->id ? 'on' : '' }}">{{ $stop->stop_no }}</div>
                    <div>
                        <strong>Order #{{ $stop->order?->order_number }}</strong>
                        <div class="muted">{{ $stop->ship_to_name }} · {{ $stop->statusLabel() }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection

@section('scripts')
@if ($route)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  const origin = @json(['lat' => $route->start_latitude, 'lng' => $route->start_longitude, 'name' => $route->start_name]);
  const stops = @json($mapStops);
  const home = @json(route('delivery.app.route'));
  const el = document.getElementById('dlv-app-map');
  if (!el || !window.L) return;
  const pin = (label, start) => L.divIcon({
    className: '',
    html: '<span class="dlv-map-num'+(start?' is-start':'')+'">'+label+'</span>',
    iconSize: [28, 28], iconAnchor: [14, 14]
  });
  const map = L.map(el);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM' }).addTo(map);
  const pts = [];
  if (origin.lat && origin.lng) {
    pts.push([origin.lat, origin.lng]);
    L.marker([origin.lat, origin.lng], { icon: pin('S', true) }).addTo(map).bindPopup('Start');
  }
  stops.forEach((s) => {
    if (!s.lat || !s.lng) return;
    pts.push([s.lat, s.lng]);
    const m = L.marker([s.lat, s.lng], { icon: pin(String(s.n), false) }).addTo(map)
      .bindPopup('Stop '+s.n+' '+(s.label||''));
    m.on('click', function () {
      window.location.href = home + '?stop=' + s.id + '#dlv-stop-card';
    });
  });
  if (pts.length > 1) L.polyline(pts, { color: '#0f766e', weight: 4 }).addTo(map);
  setTimeout(() => {
    map.invalidateSize();
    if (pts.length) map.fitBounds(pts, { padding: [28, 28] });
    else map.setView([42.3314, -83.0458], 7);
  }, 250);
})();
</script>
@endif
@endsection
