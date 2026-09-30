@extends('store.layouts.store')
@section('title', $page['title'].' | '.$shop['name'])
@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <p class="text-xs text-black/40 mb-2"><a href="{{ url('/') }}">Home</a> › {{ $page['title'] }}</p>
    <h1 class="gc-serif text-4xl mb-6">{{ $page['title'] }}</h1>
    <div class="bg-white border border-line rounded-xl p-6 md:p-8 text-sm leading-relaxed text-black/75 whitespace-pre-line">{{ $page['body'] }}</div>
</div>
@endsection
