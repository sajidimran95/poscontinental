@extends('sale.layout')
@section('title', 'Add customer')
@section('header', 'Add customer')
@section('content')
@if(!empty($canList))
<a href="{{ route('sale.customers') }}" class="inline-flex items-center gap-1.5 text-sm font-bold mb-3" style="color:#e11d48">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
    Back
</a>
@endif

<form method="POST" action="{{ route('sale.customers.store') }}" class="sale-card space-y-3" id="saleAddCustomerForm">
    @csrf
    <div class="sale-sec-title">New customer</div>
    @if(empty($canList))
        <p class="text-xs text-slate-500 -mt-1">Your role can add customers. List view is not enabled on this role.</p>
    @endif

    <div>
        <label class="text-xs font-bold text-slate-500 mb-1.5 block">Name *</label>
        <input type="text" name="name" value="{{ old('name') }}" required class="sale-input" placeholder="Customer name" autofocus>
    </div>
    <div>
        <label class="text-xs font-bold text-slate-500 mb-1.5 block">Business name</label>
        <input type="text" name="supplier_business_name" value="{{ old('supplier_business_name') }}" class="sale-input" placeholder="Optional business name">
    </div>
    <div>
        <label class="text-xs font-bold text-slate-500 mb-1.5 block">Mobile</label>
        <input type="text" name="mobile" value="{{ old('mobile') }}" class="sale-input" placeholder="Mobile number">
    </div>
    <div>
        <label class="text-xs font-bold text-slate-500 mb-1.5 block">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" class="sale-input" placeholder="Email">
    </div>
    <div>
        <label class="text-xs font-bold text-slate-500 mb-1.5 block">Address</label>
        <input type="text" name="address_line_1" value="{{ old('address_line_1') }}" class="sale-input" placeholder="Address">
    </div>

    <button type="submit" class="sale-order-build__checkout !w-full">Save customer</button>
</form>
@endsection
