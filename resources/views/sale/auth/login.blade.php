@extends('sale.layout')
@section('title', 'Sales Login')
@section('content')
<div class="min-h-[100dvh] lg:min-h-[100dvh] flex items-stretch">
    {{-- Desktop brand panel --}}
    <div class="hidden lg:flex lg:w-[44%] bg-[#0b1220] text-white flex-col justify-between p-12">
        <div class="flex items-center gap-3">
            <div class="h-11 w-11 rounded-xl bg-sale flex items-center justify-center text-white font-black text-xl">S</div>
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
                <div class="mx-auto lg:mx-0 h-16 w-16 rounded-2xl bg-sale flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-red-900/20 mb-4">S</div>
                <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight">Sales Representative</h1>
                <p class="text-sm text-slate-500 mt-1">Sales users only — not for admin or other staff</p>
            </div>

            @if(session('status'))
                @php $st = session('status'); @endphp
                <div class="mb-4 rounded-xl px-3 py-2.5 text-sm font-semibold {{ !empty($st['success']) ? 'bg-sale-soft text-sale-dark border border-rose-200' : 'bg-rose-50 text-rose-900 border border-rose-200' }}">
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
                    <div class="relative">
                        <input type="password" name="password" id="sale_password" required autocomplete="current-password" class="sale-input pr-12" placeholder="Password">
                        <button type="button" id="sale_toggle_password" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 p-1" aria-label="Show password" tabindex="-1">
                            <svg id="sale_eye_show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="sale_eye_hide" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.585 10.587a2 2 0 002.828 2.828M9.88 9.88A3 3 0 0114.12 14.12M6.228 6.228C4.64 7.41 3.366 9.05 2.458 12c1.274 4.057 5.064 7 9.542 7 1.605 0 3.13-.37 4.48-1.03M17.77 17.77A10.45 10.45 0 0021.542 12c-1.274-4.057-5.064-7-9.542-7-.86 0-1.69.12-2.48.35"/></svg>
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
@endsection
@push('scripts')
<script>
(function () {
    var btn = document.getElementById('sale_toggle_password');
    var input = document.getElementById('sale_password');
    var eyeShow = document.getElementById('sale_eye_show');
    var eyeHide = document.getElementById('sale_eye_hide');
    if (!btn || !input) return;
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        var show = input.getAttribute('type') === 'password';
        input.setAttribute('type', show ? 'text' : 'password');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        if (eyeShow) eyeShow.classList.toggle('hidden', show);
        if (eyeHide) eyeHide.classList.toggle('hidden', !show);
    });
})();
</script>
@endpush
