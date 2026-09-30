@extends('store.layouts.store')
@section('title', 'Profile')
@section('content')
<div class="ecom-acc max-w-md mx-auto px-4 py-10">
    @include('store.account.partials.mobile_topbar', ['title' => 'Profile'])
    <h1 class="text-2xl font-semibold mb-6 ecom-acc-desktop-only">Profile</h1>
    <div class="ecom-acc-card ecom-acc-form ecom-acc-mobile-only">
        <form method="post" class="space-y-3">
            @csrf
            <input name="first_name" value="{{ old('first_name', $profile['first_name']) }}" placeholder="First name" class="input-w" required>
            <input name="last_name" value="{{ old('last_name', $profile['last_name']) }}" placeholder="Last name" class="input-w" required>
            <input name="email" type="email" value="{{ old('email', $profile['email']) }}" class="input-w" required>
            <input name="mobile" value="{{ old('mobile', $profile['mobile']) }}" class="input-w" required>
            <button type="submit" class="btn-brand">Save</button>
        </form>
    </div>
    <form method="post" class="rounded-2xl border bg-white p-6 space-y-3 ecom-acc-desktop-only">
        @csrf
        <input name="first_name" value="{{ old('first_name', $profile['first_name']) }}" placeholder="First name" class="w-full border rounded-xl px-4 py-2.5 text-sm" required>
        <input name="last_name" value="{{ old('last_name', $profile['last_name']) }}" placeholder="Last name" class="w-full border rounded-xl px-4 py-2.5 text-sm" required>
        <input name="email" type="email" value="{{ old('email', $profile['email']) }}" class="w-full border rounded-xl px-4 py-2.5 text-sm" required>
        <input name="mobile" value="{{ old('mobile', $profile['mobile']) }}" class="w-full border rounded-xl px-4 py-2.5 text-sm" required>
        <button class="w-full rounded-full bg-ink text-white py-2.5">Save</button>
    </form>
</div>
@endsection
