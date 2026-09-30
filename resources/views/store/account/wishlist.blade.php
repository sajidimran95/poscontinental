@extends('store.layouts.store')
@section('title', 'Wishlist')
@section('content')
<div class="ecom-acc max-w-5xl mx-auto px-4 py-10">
    @include('store.account.partials.mobile_topbar', ['title' => 'Wishlist'])
    <h1 class="text-2xl font-semibold mb-6 ecom-acc-desktop-only">Wishlist</h1>
    <div class="px-1 lg:px-0">
        @include('store.partials.product_grid', ['products' => $items->pluck('product')->filter()])
    </div>
</div>
@endsection
