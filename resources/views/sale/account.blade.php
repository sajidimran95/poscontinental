@extends('sale.layout')
@section('title', 'Account')
@section('header', 'Account')
@section('content')
@php
    $displayName = trim((string) ($user->name ?? ''));
    if ($displayName === '') {
        $displayName = $user->username;
    }
    $canListCustomers = \App\Http\Controllers\Sale\SalePortalController::userCanListCustomers();
    $canCreateCustomers = \App\Http\Controllers\Sale\SalePortalController::userCanCreateCustomers();
    if ($canListCustomers) {
        $customerHint = 'View list';
    } elseif ($canCreateCustomers) {
        $customerHint = 'Add only';
    } else {
        $customerHint = 'Needs role permission';
    }
@endphp

<div class="sale-card mb-3">
    <div class="flex items-center gap-4 py-1">
        <div class="h-16 w-16 rounded-2xl bg-sale text-white flex items-center justify-center font-bold text-2xl shrink-0 shadow-md shadow-teal-900/10">
            {{ strtoupper(mb_substr($displayName, 0, 1)) }}
        </div>
        <div class="min-w-0">
            <div class="font-extrabold text-lg truncate">{{ $displayName }}</div>
            <div class="text-sm text-slate-500 mt-0.5 flex items-center gap-1.5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-sale shrink-0"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg>
                {{ $user->username }}
            </div>
            @if(!empty($user->email))
                <div class="text-sm text-slate-500 mt-0.5 truncate">{{ $user->email }}</div>
            @endif
        </div>
    </div>
</div>

<div class="sale-card mb-3">
    <div class="sale-sec-title !mb-3">
        <span class="sale-sec-title__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
        </span>
        Default location
    </div>
    <p class="text-xs text-slate-500 mb-3">Used as default when creating a new order. Only locations you can access are listed.</p>
    @if(count($locations))
        <form method="POST" action="{{ route('sale.account.location') }}" class="space-y-3">
            @csrf
            <select name="location_id" class="sale-input" required>
                @foreach($locations as $id => $name)
                    <option value="{{ $id }}" @selected((string) $current_location_id === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <button type="submit" class="sale-btn !w-full inline-flex items-center justify-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                Save location
            </button>
        </form>
    @else
        <p class="text-sm text-rose-600 font-semibold">No location assigned. Ask admin to give location access.</p>
    @endif
</div>

<div class="sale-card !py-1 mb-3">
    <a href="{{ route('sale.home') }}" class="sale-menu-row">
        <span class="sale-menu-row__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/></svg>
        </span>
        <span class="sale-menu-row__text">
            <strong>Dashboard</strong>
            <small>Sales summary</small>
        </span>
        <span class="sale-menu-row__chev">›</span>
    </a>
    <a href="{{ route('sale.orders') }}" class="sale-menu-row">
        <span class="sale-menu-row__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
        </span>
        <span class="sale-menu-row__text">
            <strong>Order list</strong>
            <small>Your sales orders</small>
        </span>
        <span class="sale-menu-row__chev">›</span>
    </a>
    <a href="{{ route('sale.orders.create') }}" class="sale-menu-row">
        <span class="sale-menu-row__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
        </span>
        <span class="sale-menu-row__text">
            <strong>Create order</strong>
            <small>New sale for customer</small>
        </span>
        <span class="sale-menu-row__chev">›</span>
    </a>
    <a href="{{ route('sale.chat') }}" class="sale-menu-row">
        <span class="sale-menu-row__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/></svg>
        </span>
        <span class="sale-menu-row__text">
            <strong>Team chat</strong>
            <small>Channels and direct messages</small>
        </span>
        <span class="sale-menu-row__chev">›</span>
    </a>
    <a href="{{ route('sale.customers') }}" class="sale-menu-row">
        <span class="sale-menu-row__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="8" r="3.5"/><path d="M2 19c1.2-3.2 6.8-3.2 8 0"/></svg>
        </span>
        <span class="sale-menu-row__text">
            <strong>Customers</strong>
            <small>{{ $customerHint }}</small>
        </span>
        <span class="sale-menu-row__chev">›</span>
    </a>
</div>

<div class="sale-card !py-1 mb-3">
    <div class="sale-menu-row cursor-default">
        <span class="sale-menu-row__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
        </span>
        <span class="sale-menu-row__text">
            <strong>About Sales App</strong>
            <small>Create & view your own orders. Admin manages sales.</small>
        </span>
    </div>
</div>

<div class="sale-card !py-1">
    <form method="POST" action="{{ route('sale.logout') }}">
        @csrf
        <button type="submit" class="sale-menu-row w-full">
            <span class="sale-menu-row__ico sale-menu-row__ico--danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="M16 16l4-4-4-4M10 12h10"/></svg>
            </span>
            <span class="sale-menu-row__text">
                <strong class="text-rose-600">Sign out</strong>
                <small>End this session</small>
            </span>
        </button>
    </form>
</div>
@endsection
