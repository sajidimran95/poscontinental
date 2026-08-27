@extends('delivery-app.layout')
@section('title', 'Offline')
@section('content')
<div class="wrap"><div class="card"><p>You are offline. Open {{ url('/delivery') }} when you have a connection.</p></div></div>
@endsection
