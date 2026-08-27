@extends('delivery-app.layout')
@section('title', 'Delivered')
@section('nav_active', 'delivered')
@section('content')
<div class="top">
    <div>
        <h1>Delivered</h1>
        <div class="sub">{{ $all ? 'All dates' : \Illuminate\Support\Carbon::parse($date)->format('M j, Y') }}</div>
    </div>
</div>
<div class="wrap">
    <form method="GET" action="{{ route('delivery.app.history') }}" class="filter-row">
        <input type="date" name="date" value="{{ $date }}" @disabled($all) onchange="if(!this.form.all.checked) this.form.submit()">
        <label class="muted" style="display:flex;align-items:center;gap:.3rem;white-space:nowrap">
            <input type="checkbox" name="all" value="1" style="width:auto;min-height:0" @checked($all) onchange="this.form.submit()"> All dates
        </label>
    </form>
    <div class="stats">
        <div><strong>Delivered</strong><span>{{ $deliveredCount }}</span></div>
        <div><strong>Failed</strong><span>{{ $failedCount }}</span></div>
    </div>
    @if ($stops->isEmpty())
        <div class="card"><p style="margin:0">No delivered stops for this filter.</p></div>
    @else
        <div class="card">
            @foreach ($stops as $stop)
                <div class="stop">
                    <div class="num {{ $stop->status === 'delivered' ? 'on' : '' }}">{{ $stop->stop_no }}</div>
                    <div>
                        <strong>Order #{{ $stop->order?->order_number }}</strong>
                        <div class="muted">{{ $stop->ship_to_name }} · {{ $stop->statusLabel() }}</div>
                        <div class="muted">{{ optional($stop->route?->route_date)->format('M j, Y') }} {{ optional($stop->delivered_at)->format('g:i A') }}</div>
                        @if ($stop->delivery_notes)
                            <div class="muted">Note: {{ $stop->delivery_notes }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
