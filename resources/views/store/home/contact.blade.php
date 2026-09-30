@extends('store.layouts.store')
@section('title', 'Contact Sales | '.$shop['name'])
@section('content')
<div class="max-w-lg mx-auto px-4 py-12">
    <h1 class="gc-serif text-4xl mb-2">Contact Us</h1>
    <p class="text-sm text-black/55 mb-6">
        Sales desk:
        {{ implode(' · ', array_filter([$shop['phone'], $shop['email'], $shop['address_line']])) }}
    </p>
    <form method="post" action="{{ route('ecommerce.contact.send') }}" class="rounded-2xl border bg-white p-6 space-y-3">
        @csrf
        <input name="name" required value="{{ old('name') }}" placeholder="Name" class="w-full border rounded-xl px-4 py-2.5 text-sm">
        <input type="email" name="email" required value="{{ old('email') }}" placeholder="Email" class="w-full border rounded-xl px-4 py-2.5 text-sm">
        <input name="phone" value="{{ old('phone') }}" placeholder="Phone (optional)" class="w-full border rounded-xl px-4 py-2.5 text-sm">
        <textarea name="message" required rows="4" placeholder="Message" class="w-full border rounded-xl px-4 py-2.5 text-sm">{{ old('message') }}</textarea>
        <button class="w-full rounded-lg bg-[#e53935] text-white py-2.5 font-semibold">Send</button>
    </form>
</div>
@endsection
