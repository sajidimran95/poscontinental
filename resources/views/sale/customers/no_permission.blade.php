@extends('sale.layout')
@section('title', 'Customers')
@section('header', 'Customers')
@section('content')
@php
    $isCreate = ($action ?? '') === 'create';
@endphp
<div class="sale-card sale-empty">
    <div class="sale-empty__ico" style="background:#fff1f2;color:#e11d48;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 8v5M12 16h.01"/>
        </svg>
    </div>
    <div class="font-extrabold text-base mb-1">No permission</div>
    <p class="text-slate-500 text-sm mb-1 max-w-xs mx-auto">
        @if($isCreate)
            Your role cannot add customers.
        @else
            Your role cannot view the customer list.
        @endif
    </p>
    <p class="text-xs text-slate-400 mb-5 max-w-sm mx-auto">
        Ask admin to enable customer view / create on your Sales Rep role (Users & Roles → Sales Customers).
    </p>
    @if(!empty($canCreate))
        <a href="{{ route('sale.customers.create') }}" class="sale-btn inline-block w-auto px-6 mb-2">Add customer</a>
    @endif
    <a href="{{ route('sale.home') }}" class="sale-btn-ghost inline-block w-auto px-6">Back to dashboard</a>
</div>
@endsection
