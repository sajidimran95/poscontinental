@extends('customer.layout')
@section('title', 'Account')
@section('header', 'Account')
@section('content')
<div class="sale-card mb-3">
    <div class="flex items-center gap-4 py-1">
        <div class="h-16 w-16 rounded-2xl bg-sale text-white flex items-center justify-center font-bold text-2xl shrink-0">
            {{ strtoupper(mb_substr($customer->displayName(), 0, 1)) }}
        </div>
        <div class="min-w-0">
            <div class="font-extrabold text-lg truncate">{{ $customer->displayName() }}</div>
            <div class="text-sm text-slate-500 mt-0.5 truncate">{{ $customer->customer_id }}</div>
            @if($customer->loginEmail())
                <div class="text-sm text-slate-500 mt-0.5 truncate">{{ $customer->loginEmail() }}</div>
            @endif
            @if($customer->mobile)
                <div class="text-sm text-slate-500 mt-0.5 truncate">{{ $customer->mobile }}</div>
            @endif
        </div>
    </div>
</div>
<div class="sale-card mb-3">
    <div class="flex justify-between text-sm py-1">
        <span class="text-slate-500">Balance</span>
        <strong class="tabular-nums">${{ number_format((float) $customer->balance, 2) }}</strong>
    </div>
    <div class="flex justify-between text-sm py-1">
        <span class="text-slate-500">Credit limit</span>
        <strong class="tabular-nums">${{ number_format((float) $customer->credit_limit, 2) }}</strong>
    </div>
</div>
<form method="POST" action="{{ route('customer.logout') }}">
    @csrf
    <button type="submit" class="sale-btn-ghost">Sign out</button>
</form>
@endsection
