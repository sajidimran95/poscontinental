{{-- Company letterhead under brand name (invoices, lists, purchasing PDFs) --}}
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
    $companyAddress = $companyAddress ?? ($company?->letterheadAddress() ?? config('company.address', '3802 TRADE CENTER DR'));
    $companyCityLine = $companyCityLine ?? ($company?->letterheadCityLine() ?? config('company.city_line', 'ANN ARBOR, MI 48108'));
    $companyTel = $companyTel ?? ($company?->letterheadTel() ?? config('company.tel', 'Tel:7346773510'));
    $companyFax = $companyFax ?? ($company?->letterheadFax() ?? config('company.fax', 'Fax:7346773567'));
    $companyEmail = $companyEmail ?? trim((string) ($company?->email ?? ''));
    $companyContact = $companyContact ?? trim((string) ($company?->contact_name ?? ''));
@endphp
<div class="brand-name">{{ $companyName }}</div>
@if ($companyContact !== '')
    <div class="brand-sub">{{ $companyContact }}</div>
@endif
<div class="brand-sub">{{ $companyAddress }}</div>
<div class="brand-sub">{{ $companyCityLine }}</div>
<div class="brand-sub">{{ $companyTel }}@if ($companyFax) &nbsp; {{ $companyFax }}@endif</div>
@if ($companyEmail !== '')
    <div class="brand-sub">{{ $companyEmail }}</div>
@endif
