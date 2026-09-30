@extends('store.layouts.store')
@section('title', 'Password')
@section('content')
<div class="ecom-acc max-w-md mx-auto px-4 py-10">
    @include('store.account.partials.mobile_topbar', ['title' => 'Password'])
    <h1 class="text-2xl font-semibold mb-6 ecom-acc-desktop-only">Change password</h1>
    <div class="ecom-acc-card ecom-acc-form ecom-acc-mobile-only">
        <form method="post" class="space-y-3">
            @csrf
            <input type="password" name="current_password" placeholder="Current password" required class="input-w">
            <input type="password" name="password" placeholder="New password" required class="input-w">
            <input type="password" name="password_confirmation" placeholder="Confirm password" required class="input-w">
            <button type="submit" class="btn-brand">Update</button>
        </form>
    </div>
    <form method="post" class="rounded-2xl border bg-white p-6 space-y-3 ecom-acc-desktop-only">
        @csrf
        <input type="password" name="current_password" placeholder="Current password" required class="w-full border rounded-xl px-4 py-2.5 text-sm">
        <input type="password" name="password" placeholder="New password" required class="w-full border rounded-xl px-4 py-2.5 text-sm">
        <input type="password" name="password_confirmation" placeholder="Confirm password" required class="w-full border rounded-xl px-4 py-2.5 text-sm">
        <button class="w-full rounded-full bg-ink text-white py-2.5">Update</button>
    </form>
</div>
@endsection
