@extends('sale.layout')
@section('title', 'Home')
@section('header', 'Home')
@section('content')
@php
    $roleLabel = $user->role?->label ?? 'Sales Rep';
    $locName = $site->name ?? ($company->name ?? '—');
    $locStreet = $company->address ?? '—';
    $locCity = trim(implode(', ', array_filter([$company->city ?? null, $company->state ?? null, $company->zip_code ?? null]))) ?: '—';
    $locPhone = $company->phone ?? '—';
@endphp
<div class="sale-home">
    <section class="sale-home-card">
        <ul class="sale-home-list">
            <li>
                <span class="sale-home-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/></svg></span>
                <span>{{ $locName }}</span>
            </li>
            <li>
                <span class="sale-home-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                <span>{{ $locStreet }}</span>
            </li>
            <li>
                <span class="sale-home-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="10" r="3"/><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/></svg></span>
                <span>{{ $locCity }}</span>
            </li>
            <li>
                <span class="sale-home-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.5-1.1a2 2 0 0 1 2.1-.4c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2z"/></svg></span>
                <span>{{ $locPhone }}</span>
            </li>
        </ul>
    </section>
    <section class="sale-home-card">
        <h3 class="sale-home-card__title">Employee Details:</h3>
        <ul class="sale-home-list">
            <li><span class="sale-home-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg></span><span>{{ $user->name }}</span></li>
            <li><span class="sale-home-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span><span>{{ $user->email }}</span></li>
            <li><span class="sale-home-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></span><span>{{ $roleLabel }}</span></li>
        </ul>
    </section>
    <section class="sale-home-card">
        <h3 class="sale-home-card__title">Employee Roles:</h3>
        <span class="sale-home-role-pill">{{ $roleLabel }}</span>
    </section>
    <section class="sale-home-card">
        <h3 class="sale-home-card__title">Sales</h3>
        <div class="sale-home-metric">
            <span class="sale-home-metric__label">Total Sales</span>
            <span class="sale-home-metric__value">${{ number_format((float) ($stats['month_total'] ?? 0), 0) }}</span>
        </div>
        <div class="sale-home-metric">
            <span class="sale-home-metric__label">Today</span>
            <span class="sale-home-metric__value">${{ number_format((float) ($stats['today_total'] ?? 0), 2) }}</span>
        </div>
        <div class="sale-home-metric">
            <span class="sale-home-metric__label">Orders</span>
            <span class="sale-home-metric__value">{{ (int) ($stats['total_orders'] ?? 0) }}</span>
        </div>
    </section>
</div>
@endsection
