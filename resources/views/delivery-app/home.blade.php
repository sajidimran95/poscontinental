@extends('delivery-app.layout')
@section('title', 'Overview')
@section('nav_active', 'home')
@section('content')
<div class="top">
    <div>
        <h1>Hi, {{ explode(' ', $user->name)[0] }}</h1>
        <div class="sub">{{ \Illuminate\Support\Carbon::parse($date)->format('l, M j') }}</div>
    </div>
    <form method="POST" action="{{ route('delivery.app.logout') }}">@csrf<button class="btn btn-w" type="submit">Log out</button></form>
</div>
<div class="wrap">
    <div class="stats">
        <div><strong>Today’s stops</strong><span>{{ $route?->total_orders ?? 0 }}</span></div>
        <div><strong>Left</strong><span>{{ $left }}</span></div>
        <div><strong>Delivered today</strong><span>{{ $deliveredToday }}</span></div>
        <div><strong>All delivered</strong><span>{{ $allTimeDelivered }}</span></div>
    </div>

    @if (! $route)
        <div class="card">
            <p style="margin:0 0 .35rem;font-weight:800">No route today</p>
            <p class="muted" style="margin:0">Dispatch will assign invoices and generate your route.</p>
        </div>
    @else
        <div class="card">
            <p class="muted" style="margin:0 0 .35rem">{{ ucfirst($route->status) }} · {{ $failedToday }} failed</p>
            @if ($suggested)
                <h2 style="margin:0 0 .35rem;font-size:1.1rem">Next: stop {{ $suggested->stop_no }}</h2>
                <p style="margin:0">{{ $suggested->ship_to_name ?: $suggested->order?->customer?->company_name }}</p>
                <p class="muted">Order #{{ $suggested->order?->order_number }}</p>
                <a class="btn btn-p" style="width:100%;margin-top:.75rem" href="{{ route('delivery.app.route', ['stop' => $suggested->id]) }}">Open route</a>
            @else
                <p style="margin:0;font-weight:700">All stops on this route are finished.</p>
                <a class="btn btn-w" style="width:100%;margin-top:.75rem" href="{{ route('delivery.app.history') }}">View delivered</a>
            @endif
        </div>
        @if ($route->status === 'planned')
            <form method="POST" action="{{ route('delivery.app.start') }}">
                @csrf
                <button class="btn btn-p" type="submit" style="width:100%">Start route</button>
            </form>
        @endif
    @endif
</div>
@endsection
