<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#e11d48">
    <link rel="manifest" href="{{ url('/customer/pwa/manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('pwa/customer-icon-192.png') }}">
    <title>Customer Login — {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/pwa.css'])
    <style>
        body {
            margin: 0; min-height: 100dvh; font-family: 'DM Sans', system-ui, sans-serif;
            background:
                radial-gradient(1200px 600px at 10% -10%, rgba(225, 29, 72, .12), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(251, 113, 133, .14), transparent 50%),
                linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .ca-display { font-family: Outfit, system-ui, sans-serif; }
        .ca-btn {
            display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
            color: #fff; font-weight: 800; border: 0; border-radius: 14px;
            padding: .9rem 1.15rem; width: 100%; cursor: pointer;
            font-family: Outfit, system-ui, sans-serif;
            box-shadow: 0 10px 24px rgba(225, 29, 72, .28);
        }
        .ca-input {
            width: 100%; border: 1px solid rgba(15,23,42,.08); border-radius: 14px;
            padding: .85rem 1rem; background: #fff; outline: none; font-weight: 600; box-sizing: border-box;
        }
        .ca-input:focus { border-color: #e11d48; box-shadow: 0 0 0 4px rgba(225,29,72,.12); }
        .ca-card {
            background: rgba(255,255,255,.82); border: 1px solid rgba(15,23,42,.08);
            border-radius: 20px; padding: 24px; box-shadow: 0 10px 30px rgba(15,23,42,.06);
        }
    </style>
</head>
<body class="japs-auth-body">
<div class="min-h-[100dvh] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="mx-auto h-16 w-16 rounded-2xl text-white text-2xl font-black flex items-center justify-center mb-4 ca-display"
                 style="background:linear-gradient(135deg,#fb7185,#e11d48)">C</div>
            <h1 class="ca-display text-3xl font-extrabold tracking-tight">Welcome back</h1>
            <p class="text-sm text-slate-500 mt-1 font-semibold">Sign in to the Customer App</p>
        </div>
        @if(session('status'))
            @php $st = session('status'); @endphp
            <div class="mb-4 rounded-xl px-3 py-2.5 text-sm font-semibold {{ !empty($st['success']) ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : 'bg-rose-50 text-rose-900 border border-rose-200' }}">
                {{ is_array($st) ? ($st['msg'] ?? '') : $st }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-sm p-3 font-semibold">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('customer.login.post') }}" class="ca-card space-y-4">
            @csrf
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block">Email or Mobile</label>
                <input type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" class="ca-input" placeholder="email@example.com or mobile">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 mb-1.5 block">Password</label>
                <input type="password" name="password" required autocomplete="current-password" class="ca-input" placeholder="Password">
            </div>
            <button type="submit" class="ca-btn">Login</button>
        </form>
    </div>
</div>
@include('customer.partials.pwa')
</body>
</html>
