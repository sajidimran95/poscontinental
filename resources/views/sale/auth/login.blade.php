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
                    <div class="sale-pw-wrap">
                        <input id="sale-login-password" type="password" name="password" required autocomplete="current-password" class="sale-input sale-pw-input" placeholder="Password">
                        <button type="button" class="sale-pw-toggle" data-password-toggle aria-controls="sale-login-password" aria-label="Show password" aria-pressed="false">
                            <svg class="sale-pw-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="sale-pw-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.8 21.8 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.8 21.8 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                        </button>
                    </div>
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
<style>
    .sale-pw-wrap { position: relative; }
    .sale-pw-input { padding-right: 2.75rem; }
    .sale-pw-toggle {
        position: absolute; right: .35rem; top: 50%; transform: translateY(-50%);
        width: 2.25rem; height: 2.25rem; border: 0; background: transparent; color: #64748b;
        border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    }
    .sale-pw-toggle:hover { color: #0f766e; background: #f0fdfa; }
</style>
<script>
    document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('aria-controls'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var eye = btn.querySelector('.sale-pw-eye');
            var eyeOff = btn.querySelector('.sale-pw-eye-off');
            if (eye) eye.hidden = show;
            if (eyeOff) eyeOff.hidden = !show;
        });
    });
</script>
@endsection
