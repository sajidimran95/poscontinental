@extends('customer.layout')
@section('title', 'Profile')
@section('content')
<div class="mb-5">
    <h1 class="ca-page-title">Profile</h1>
    <div class="ca-page-sub">{{ $initials }}</div>
</div>

<div class="ca-card mb-4">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-14 h-14 rounded-2xl text-white font-extrabold flex items-center justify-center text-xl shadow-lg" style="background:linear-gradient(135deg,#fb7185,#e11d48)">{{ $initials }}</div>
        <div class="min-w-0">
            <div class="font-extrabold truncate">{{ $contact->displayName() }}</div>
            <div class="text-xs text-slate-500 font-semibold">{{ $contact->customer_id }}</div>
        </div>
    </div>
    <div class="space-y-2 text-sm">
        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
            <span class="text-slate-500 font-semibold">Email</span>
            <span class="font-bold text-right">{{ $contact->loginEmail() ?: ($contact->email ?: '—') }}</span>
        </div>
        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
            <span class="text-slate-500 font-semibold">Mobile</span>
            <span class="font-bold text-right">{{ $contact->mobile ?: '—' }}</span>
        </div>
        <div class="flex justify-between gap-3">
            <span class="text-slate-500 font-semibold">Login ID</span>
            <span class="font-bold text-right text-xs">Email or mobile</span>
        </div>
        <div class="flex justify-between gap-3 border-t border-slate-100 pt-2">
            <span class="text-slate-500 font-semibold">Balance</span>
            <span class="font-extrabold tabular-nums">${{ number_format((float) $contact->balance, 2) }}</span>
        </div>
    </div>
</div>

<div class="ca-card mb-4" id="location">
    <h2 class="font-extrabold mb-1 flex items-center gap-2">
        <span class="text-red-500">📍</span> Default Location
    </h2>
    <p class="text-xs text-slate-500 mb-4">Used for Create Order and Price Check.</p>

    @if($locations->count())
        <form method="POST" action="{{ route('customer.profile.location') }}" class="space-y-3">
            @csrf
            <select name="location_id" required
                    class="w-full border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold bg-white outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100">
                @foreach($locations as $id => $name)
                    <option value="{{ $id }}" @selected((string) $current_location_id === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <button type="submit" class="ca-btn">Save Location</button>
        </form>
    @else
        <div class="text-sm text-rose-600 font-semibold">No active location found.</div>
    @endif
</div>

<div class="ca-card">
    <h2 class="font-extrabold mb-1">Change Password</h2>
    <p class="text-xs text-slate-500 mb-4">Enter current password, then choose a new one.</p>

    @if($errors->any())
        <div class="mb-3 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold px-3 py-2">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('customer.profile.password') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">Current Password</label>
            <input type="password" name="current_password" required autocomplete="current-password"
                   class="w-full border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                   placeholder="Current password">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">New Password</label>
            <input type="password" name="password" required autocomplete="new-password" minlength="4"
                   class="w-full border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                   placeholder="New password (min 4)">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">Confirm New Password</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password" minlength="4"
                   class="w-full border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                   placeholder="Confirm new password">
        </div>
        <button type="submit" class="ca-btn mt-2">Update Password</button>
    </form>
</div>
@endsection
