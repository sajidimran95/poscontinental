@extends('store.layouts.store')
@section('title', 'My Account')
@section('content')
@php
    $badge = match($approval) {
        'pending' => 'bg-amber-100 text-amber-900',
        'rejected' => 'bg-rose-100 text-rose-900',
        default => 'bg-emerald-100 text-emerald-900',
    };
    $first = $first_name;
@endphp

<div class="ecom-acc max-w-5xl mx-auto px-4 py-10 lg:py-10">
    {{-- Mobile app home --}}
    <div class="ecom-acc-mobile-only">
        <div class="ecom-acc-hero">
            <div class="ecom-acc-hero__name">Hello, {{ $first }}</div>
            <div class="ecom-acc-hero__meta flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge }}">
                    {{ \App\Support\Store\WholesaleAccount::label($approval) }}
                </span>
                @if($contact->email)
                    <span class="truncate">{{ $contact->email }}</span>
                @endif
            </div>
        </div>

        @if($approval === 'pending')
            <div class="ecom-acc-banner border border-amber-200 bg-amber-50 text-amber-950">
                Approval pending.
                <a href="{{ route('ecommerce.account.licenses') }}" class="font-bold underline">Upload licenses</a>
            </div>
        @endif

        <div class="ecom-acc-section">Account</div>
        <div class="ecom-acc-list">
            <a href="{{ route('ecommerce.account.orders') }}">
                <span class="ecom-acc-list__icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h12l-1 10H5L4 6z"/><path d="M8 6V4a2 2 0 014 0v2"/></svg>
                </span>
                <span>Orders</span>
                <span class="ecom-acc-list__chev">›</span>
            </a>
            <a href="{{ route('ecommerce.account.licenses') }}">
                <span class="ecom-acc-list__icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h7l3 3v10a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M13 3v3h3"/></svg>
                </span>
                <span>Licenses</span>
                <span class="ecom-acc-list__chev">›</span>
            </a>
            <a href="{{ route('ecommerce.account.profile') }}">
                <span class="ecom-acc-list__icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="6" r="3"/><path d="M3 14c1.5-2.5 8.5-2.5 10 0"/></svg>
                </span>
                <span>Profile</span>
                <span class="ecom-acc-list__chev">›</span>
            </a>
            <a href="{{ route('ecommerce.account.addresses') }}">
                <span class="ecom-acc-list__icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 14s5-4.2 5-7.2A5 5 0 003 6.8C3 9.8 8 14 8 14z"/><circle cx="8" cy="7" r="1.6"/></svg>
                </span>
                <span>Addresses</span>
                <span class="ecom-acc-list__chev">›</span>
            </a>
            <a href="{{ route('ecommerce.wishlist') }}">
                <span class="ecom-acc-list__icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 13.5S3 10.2 3 6.8A2.8 2.8 0 018 4.6 2.8 2.8 0 0113 6.8C13 10.2 8 13.5 8 13.5z"/></svg>
                </span>
                <span>Wishlist</span>
                <span class="ecom-acc-list__chev">›</span>
            </a>
            <a href="{{ route('ecommerce.quick_order') }}">
                <span class="ecom-acc-list__icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2v7M5 6l3 3 3-3"/><path d="M3 12h10"/></svg>
                </span>
                <span>Quick Order</span>
                <span class="ecom-acc-list__chev">›</span>
            </a>
            <a href="{{ route('ecommerce.account.password') }}">
                <span class="ecom-acc-list__icon" aria-hidden="true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 015 0v2"/></svg>
                </span>
                <span>Password</span>
                <span class="ecom-acc-list__chev">›</span>
            </a>
            <form action="{{ route('ecommerce.logout', absolute: false) }}" method="post" class="ecom-acc-list__danger">
                @csrf
                <button type="submit">
                    <span class="ecom-acc-list__icon" aria-hidden="true">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4H4v8h2M9 8h5M12 5l3 3-3 3"/></svg>
                    </span>
                    <span>Logout</span>
                    <span class="ecom-acc-list__chev">›</span>
                </button>
            </form>
        </div>

        <div class="ecom-acc-section">Recent orders</div>
        <div class="ecom-acc-list">
            @forelse($orders as $order)
                <div class="ecom-acc-order">
                    <div class="ecom-acc-order__row">
                        <span class="font-bold text-[15px]">{{ $order->order_number }}</span>
                        <span class="font-bold tabular-nums text-[15px]">${{ number_format($order->total, 2) }}</span>
                    </div>
                    <div class="text-[12px] text-black/45">
                        {{ optional($order->order_date)->toFormattedDateString() }}
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-black/45 text-center">No orders yet.</div>
            @endforelse
        </div>
        @if($orders->count())
            <div class="px-4 pb-4">
                <a href="{{ route('ecommerce.account.orders') }}" class="block text-center text-sm font-bold text-brand py-2">View all orders</a>
            </div>
        @endif
    </div>

    {{-- Desktop original layout --}}
    <div class="ecom-acc-desktop-only">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <h1 class="text-3xl font-extrabold">Hello, {{ $first }}</h1>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">
                {{ \App\Support\Store\WholesaleAccount::label($approval) }}
            </span>
        </div>

        @if($approval === 'pending')
            <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-950 text-sm p-4 mb-6">
                Wholesale approval pending. <a href="{{ route('ecommerce.account.licenses') }}" class="font-bold underline">Review / upload licenses</a> — checkout unlocks after admin approval.
            </div>
        @endif

        <div class="grid md:grid-cols-3 gap-4 mb-10 text-sm">
            <a href="{{ route('ecommerce.account.orders') }}" class="rounded-xl border border-line bg-white p-4 font-semibold hover:border-brand">Orders</a>
            <a href="{{ route('ecommerce.account.licenses') }}" class="rounded-xl border border-line bg-white p-4 font-semibold hover:border-brand">Licenses</a>
            <a href="{{ route('ecommerce.account.profile') }}" class="rounded-xl border border-line bg-white p-4 font-semibold hover:border-brand">Profile</a>
            <a href="{{ route('ecommerce.account.addresses') }}" class="rounded-xl border border-line bg-white p-4 font-semibold hover:border-brand">Addresses</a>
            <a href="{{ route('ecommerce.wishlist') }}" class="rounded-xl border border-line bg-white p-4 font-semibold hover:border-brand">Wishlist</a>
            <a href="{{ route('ecommerce.quick_order') }}" class="rounded-xl border border-line bg-white p-4 font-semibold hover:border-brand">Quick Order</a>
            <a href="{{ route('ecommerce.account.password') }}" class="rounded-xl border border-line bg-white p-4 font-semibold hover:border-brand">Password</a>
            <form action="{{ route('ecommerce.logout', absolute: false) }}" method="post" class="md:col-span-1">
                @csrf
                <button class="rounded-xl border border-line bg-white p-4 w-full text-left font-semibold hover:border-brand">Logout</button>
            </form>
        </div>

        <h2 class="font-extrabold mb-3">Recent orders</h2>
        <div class="space-y-2">
            @forelse($orders as $order)
                <div class="rounded-xl border border-line bg-white p-4 flex justify-between text-sm gap-3">
                    <span class="font-semibold">{{ $order->order_number }}</span>
                    <span class="tabular-nums">${{ number_format($order->total, 2) }}</span>
                    <span class="text-black/45">{{ optional($order->order_date)->toFormattedDateString() }}</span>
                </div>
            @empty
                <p class="text-black/45 text-sm">No orders yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
