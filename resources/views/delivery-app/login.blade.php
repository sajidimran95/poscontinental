@extends('delivery-app.layout')
@section('title', 'Sign in')
@section('body_class', 'is-login')
@section('content')
<div class="hero-login">
    <div class="brand-mark">D</div>
    <p style="text-align:center;color:#ccfbf1;margin:0 0 1.25rem;font-weight:700">Delivery</p>
    <div class="login-card">
        <span class="chip">Driver app</span>
        <h1>Welcome back</h1>
        <p class="muted" style="margin:.2rem 0 1rem">Sign in to your route, map, and deliveries.</p>
        @if(session('status'))
            @php $st = session('status'); @endphp
            <div class="{{ !empty($st['success']) ? 'ok' : 'err' }}">{{ is_array($st) ? ($st['msg'] ?? '') : $st }}</div>
        @endif
        @if($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('delivery.app.login.post') }}" style="display:flex;flex-direction:column;gap:.8rem">
            @csrf
            <div>
                <label class="muted">Username or email</label>
                <input type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" placeholder="driver">
            </div>
            <div>
                <label class="muted">Password</label>
                <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            </div>
            <label class="muted" style="display:flex;align-items:center;gap:.45rem">
                <input type="checkbox" name="remember" value="1" style="width:auto;min-height:0"> Keep me signed in
            </label>
            <button type="submit" class="btn btn-p" style="width:100%;min-height:2.9rem">Continue</button>
        </form>
        <p class="muted" style="margin:1rem 0 0;text-align:center">Add to Home Screen after sign-in for the full app.</p>
    </div>
</div>
@endsection
