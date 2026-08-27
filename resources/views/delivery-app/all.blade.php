@extends('delivery-app.layout')
@section('title', 'All')
@section('nav_active', 'all')
@section('content')
<div class="top">
    <div>
        <h1>All invoices</h1>
        <div class="sub">Each day starts at stop 1 — grouped by date</div>
    </div>
</div>
<div class="wrap">
    <form method="GET" action="{{ route('delivery.app.all') }}" class="card" style="display:grid;grid-template-columns:1fr 1fr;gap:.55rem">
        <div>
            <label class="muted">From</label>
            <input type="date" name="from" value="{{ $from }}" onchange="this.form.submit()">
        </div>
        <div>
            <label class="muted">To</label>
            <input type="date" name="to" value="{{ $to }}" onchange="this.form.submit()">
        </div>
    </form>
    <div class="stats">
        <div><strong>Days</strong><span>{{ $days->count() }}</span></div>
        <div><strong>Invoices</strong><span>{{ $stops->count() }}</span></div>
        <div><strong>Open</strong><span>{{ $openCount }}</span></div>
        <div><strong>Delivered</strong><span>{{ $deliveredCount }}</span></div>
    </div>
    @forelse ($days as $day => $dayStops)
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;margin-bottom:.35rem">
                <strong>{{ $day === 'unknown' ? 'No date' : \Illuminate\Support\Carbon::parse($day)->format('D, M j, Y') }}</strong>
                <span class="muted">{{ $dayStops->count() }} stops · 1–{{ $dayStops->max('stop_no') }}</span>
            </div>
            @foreach ($dayStops as $stop)
                <a class="stop" href="{{ route('delivery.app.route', ['stop' => $stop->id]) }}#dlv-stop-card">
                    <div class="num {{ $stop->status === 'delivered' ? 'on' : '' }}">{{ $stop->stop_no }}</div>
                    <div>
                        <strong>Order #{{ $stop->order?->order_number }}</strong>
                        <div class="muted">{{ $stop->ship_to_name }} · {{ $stop->statusLabel() }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    @empty
        <div class="card"><p style="margin:0">No invoices from {{ $from }} to {{ $to }}.</p></div>
    @endforelse
</div>
@endsection
