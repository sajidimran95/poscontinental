@extends('store.layouts.auth')
@section('title', 'Wholesale Login')

@section('content')
@php
    $store = $shop['name'];
    $authLogoUrl = $shop['logo_url'];
@endphp
<div class="min-h-screen grid lg:grid-cols-[280px_1fr]">
    <aside class="bg-slatebar text-white p-6 lg:p-8">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-8">
            @if($authLogoUrl)
                <img src="{{ $authLogoUrl }}" alt="{{ $store }}" class="h-10 w-auto max-w-[180px] object-contain brightness-0 invert">
            @else
                <span class="h-9 w-9 rounded-full bg-brand text-white flex items-center justify-center font-extrabold text-sm">{{ $shop['initial'] }}</span>
                <span>
                    <span class="block font-extrabold">{{ $store }}</span>
                    <span class="block text-[10px] uppercase tracking-wider text-white/50">Wholesale</span>
                </span>
            @endif
        </a>
        <p class="text-sm text-white/65 leading-relaxed">Sign in to your wholesale account to place orders, track shipments, and reorder by SKU.</p>
    </aside>
    <div class="p-5 sm:p-8 lg:p-12 flex items-center">
        <div class="w-full max-w-md mx-auto">
            <div class="flex justify-end mb-6 text-sm">
                New customer?
                <a href="{{ route('ecommerce.register') }}" class="ml-1 font-bold text-brand">Sign Up</a>
            </div>
            <h1 class="text-3xl font-extrabold mb-8">Sign In</h1>
            <form method="post" action="{{ route('ecommerce.login.post', absolute: false) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="text-sm font-semibold mb-1.5 block">Email</label>
                    <input name="login" value="{{ old('login') }}" required class="input-w" placeholder="you@business.com">
                </div>
                <div>
                    <label class="text-sm font-semibold mb-1.5 block">Password</label>
                    <input type="password" name="password" required class="input-w" placeholder="••••••••">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-black/70">
                    <input type="checkbox" name="remember" value="1" class="rounded"> Remember me
                </label>
                <button class="btn-brand w-full">Login</button>
            </form>
            <p class="text-center text-sm mt-6 text-black/50">
                <a href="{{ url('/') }}" class="hover:text-brand">← Back to store</a>
            </p>
        </div>
    </div>
</div>
@endsection
