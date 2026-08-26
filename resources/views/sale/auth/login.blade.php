@extends('sale.layout')
@section('title', 'Sales Login')
@section('content')
<div class="min-h-[100dvh] lg:min-h-[100dvh] flex items-stretch">
    {{-- Desktop brand panel --}}
    <div class="hidden lg:flex lg:w-[44%] bg-[#0b1220] text-white flex-col justify-between p-12">
        <div class="flex items-center gap-3">
            <img src="{{ asset('pwa/sale-icon-192.png') }}" alt="" class="h-11 w-11 rounded-xl bg-sale">
            <div>
                <div class="font-extrabold text-lg">Sales App</div>
                <div class="text-sm text-white/50">Representative portal</div>
            </div>
        </div>
        <div>
            <h2 class="text-4xl font-extrabold leading-tight mb-3">Create orders<br>on the go.</h2>
            <p class="text-white/60 text-base max-w-sm">Sales representatives only. Admin and other users must use the main system login. Your dashboard shows only your own orders.</p>
        </div>
        <p class="text-xs text-white/35">© {{ date('Y') }} {{ config('app.name', 'JAPS POS') }}</p>
    </div>

    {{-- Form --}}
    <div class="flex-1 flex flex-col justify-center px-4 py-10 lg:px-16">
        <div class="w-full max-w-md mx-auto">
            <div class="text-center lg:text-left mb-8">
                <div class="mx-auto lg:mx-0 h-16 w-16 rounded-2xl bg-sale flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-teal-900/20 mb-4">S</div>
                <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight">Sales Representative</h1>
                <p class="text-sm text-slate-500 mt-1">Sales users only — not for admin or other staff</p>
            </div>

            @if(session('status'))
                @php $st = session('status'); @endphp
                <div class="mb-4 rounded-xl px-3 py-2.5 text-sm font-semibold {{ !empty($st['success']) ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : 'bg-rose-50 text-rose-900 border border-rose-200' }}">
                    {{ is_array($st) ? ($st['msg'] ?? '') : $st }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-sm p-3 font-semibold">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('sale.login.post') }}" class="sale-card space-y-4 lg:shadow-sm">
                @csrf
                <div>
                    <label class="text-xs font-bold text-slate-500 mb-1.5 block">Username</label>
                    <input type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="sale-input" placeholder="Sales user username">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 mb-1.5 block">Password</label>
                    <input type="password" name="password" required autocomplete="current-password" class="sale-input" placeholder="Password">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1"> Remember me
                </label>
                <button type="submit" class="sale-btn !w-full">Sign in</button>
            </form>

            <p class="text-center lg:text-left text-xs text-slate-400 mt-6">
                Admin must use the main login. Only sales-enabled non-admin users can sign in here. Dashboard shows your own orders only.
            </p>
        </div>
    </div>
</div>
@endsection
