@extends('store.layouts.store')
@section('title', 'Shop by Brand | '.$shop['name'])
@section('content')
<div class="max-w-[1400px] mx-auto px-4 py-12">
    <h1 class="gc-serif text-4xl mb-2">All Brands</h1>
    <p class="text-sm text-black/50 mb-8">Shop the wholesale catalog by manufacturer.</p>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse($brands as $brand)
            @include('store.partials.name_card', ['href' => route('ecommerce.shop', ['brand' => $brand->id]), 'name' => $brand->name, 'count' => $brand->products_count])
        @empty
            <p class="col-span-full text-sm text-black/50">Brands will appear after inventory is uploaded.</p>
        @endforelse
    </div>
</div>
@endsection
