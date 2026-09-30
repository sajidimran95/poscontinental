@php
    $title = $title ?? 'Account';
    $backUrl = $backUrl ?? route('ecommerce.account');
    $showBack = $showBack ?? true;
@endphp
<div class="ecom-acc-top ecom-acc-mobile-only">
    @if($showBack)
        <a href="{{ $backUrl }}" class="ecom-acc-top__back" aria-label="Back">‹</a>
    @endif
    <div class="ecom-acc-top__title">{{ $title }}</div>
</div>
