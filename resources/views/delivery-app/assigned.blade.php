@extends('delivery-app.layout')
@section('title', 'Assigned')
@section('nav_active', 'assigned')
@section('content')
<div class="top">
    <div>
        <h1>Assigned invoices</h1>
        <div class="sub">Open stops in this date range — tap any order</div>
    </div>
</div>
<div class="wrap">
    <form method="GET" action="{{ route('delivery.app.assigned') }}" class="card" style="display:grid;grid-template-columns:1fr 1fr;gap:.55rem">
        <div>
            <label class="muted">From</label>
            <input type="date" name="from" value="{{ $from }}" onchange="this.form.submit()">
        </div>
        <div>
            <label class="muted">To</label>
            <input type="date" name="to" value="{{ $to }}" onchange="this.form.submit()">
        </div>
    </form>
    @if ($stops->isEmpty())
        <div class="card">
            <p style="margin:0">No assigned invoices from {{ $from }} to {{ $to }}.</p>
        </div>
    @else
        <div class="card">
            @foreach ($stops as $stop)
                <a class="stop" href="{{ route('delivery.app.route', ['stop' => $stop->id]) }}#dlv-stop-card">
                    <div class="num">{{ $stop->stop_no }}</div>
                    <div>
                        <strong>Order #{{ $stop->order?->order_number }}</strong>
                        <div class="muted">{{ $stop->ship_to_name ?: $stop->order?->customer?->company_name }}</div>
                        <div class="muted">{{ optional($stop->route?->route_date)->format('M j, Y') }} · {{ $stop->statusLabel() }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
