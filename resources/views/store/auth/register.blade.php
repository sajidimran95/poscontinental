@extends('store.layouts.auth')
@section('title', 'Wholesale Sign Up')

@section('content')
@php
    $store = $shop['name'];
    $us_states = $us_states ?? [];
    $licenseRows = [
        ['key' => 'business', 'title' => 'Business Certificate', 'number_label' => 'Business Certificate Number', 'expiry_label' => 'Business Certificate Expiry', 'placeholder' => ''],
        ['key' => 'resale', 'title' => 'Resale Certificate', 'number_label' => 'Resale Certificate Number', 'expiry_label' => 'Resale Certificate Expiry', 'placeholder' => ''],
        ['key' => 'tobacco', 'title' => 'Tobacco Certificate', 'number_label' => 'Tobacco Certificate Number', 'expiry_label' => 'Tobacco Certificate Expiry', 'placeholder' => ''],
        ['key' => 'id', 'title' => 'ID Certificate', 'number_label' => 'ID Certificate Number', 'expiry_label' => 'ID Certificate Expiry', 'placeholder' => "i.e. Passport, Driver's License"],
    ];
    $authLogoUrl = $shop['logo_url'];
@endphp
<div class="min-h-screen grid lg:grid-cols-[280px_1fr]">
    <aside class="bg-slatebar text-white p-6 lg:p-8 flex flex-col relative overflow-hidden">
        <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image:radial-gradient(circle at 20% 20%, #e53935 0, transparent 40%),radial-gradient(circle at 80% 80%, #e53935 0, transparent 35%);"></div>
        <div class="relative">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-10">
                @if($authLogoUrl)
                    <img src="{{ $authLogoUrl }}" alt="{{ $store }}" class="h-10 w-auto max-w-[180px] object-contain brightness-0 invert">
                @else
                    <span class="h-9 w-9 rounded-full bg-brand text-white flex items-center justify-center font-extrabold text-sm">{{ $shop['initial'] }}</span>
                    <span>
                        <span class="block font-extrabold">{{ $store }}</span>
                        <span class="block text-[10px] uppercase tracking-wider text-white/50">Wholesale Distribution</span>
                    </span>
                @endif
            </a>

            <nav class="space-y-0 text-sm relative" id="stepNav">
                <div class="absolute left-[15px] top-4 bottom-4 w-px border-l border-dashed border-brand/50"></div>
                @foreach([
                    1 => 'Customer Information',
                    2 => 'Shipping & Billing',
                    3 => 'Licenses',
                ] as $num => $label)
                    <div class="flex items-start gap-3 step-nav-item relative py-4" data-step="{{ $num }}">
                        <span class="step-badge relative z-10 h-8 w-8 rounded-lg bg-[#333] text-white/50 flex items-center justify-center text-xs font-bold shrink-0 {{ $num === 1 ? 'is-active' : '' }}" data-step-badge="{{ $num }}">
                            <span class="step-num">{{ $num }}</span>
                            <svg class="step-check hidden w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div class="pt-1.5">
                            <div class="step-label {{ $num === 1 ? 'font-bold text-brand' : 'font-semibold text-white/70' }}">{{ $label }}</div>
                        </div>
                    </div>
                @endforeach
            </nav>
        </div>

        <div class="relative mt-auto pt-10 space-y-2 text-xs">
            <a href="{{ route('ecommerce.page', 'terms-of-service') }}" class="block text-brand hover:underline">Terms and Conditions</a>
            <a href="{{ route('ecommerce.page', 'privacy-policy') }}" class="block text-brand hover:underline">Privacy Policy</a>
            <a href="{{ route('ecommerce.contact') }}" class="block text-brand hover:underline">Contact Us</a>
        </div>
    </aside>

    <div class="p-5 sm:p-8 lg:p-12 max-w-4xl w-full mx-auto">
        <div class="flex justify-end mb-6 text-sm">
            Already have an account?
            <a href="{{ route('ecommerce.login') }}" class="ml-1 font-bold text-brand">Sign In</a>
        </div>

        <form method="post" action="{{ route('ecommerce.register.post', absolute: false) }}" id="registerForm" enctype="multipart/form-data">
            @csrf

            {{-- Step 1 --}}
            <div class="step-panel active" data-step="1">
                <h1 class="text-3xl font-extrabold mb-8">Customer Information</h1>

                <h2 class="font-bold text-lg mb-4">Business Details</h2>
                <div class="space-y-4 mb-8">
                    <div>
                        <label class="text-sm font-semibold mb-1.5 block">Business Name <span class="text-brand">*</span></label>
                        <input name="business_name" value="{{ old('business_name') }}" required class="input-w" placeholder="Your company name">
                    </div>
                    <div>
                        <label class="text-sm font-semibold mb-1.5 block">Business Phone Number <span class="text-brand">*</span></label>
                        <div class="flex gap-2">
                            <span class="inline-flex items-center px-3 rounded-lg border border-[#d4d4d4] bg-gray-50 text-sm">+1</span>
                            <input name="mobile" value="{{ old('mobile') }}" required class="input-w" placeholder="(555) 000-0000">
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold mb-1.5 block">Business Tax ID / EIN</label>
                            <input name="tax_number" value="{{ old('tax_number') }}" class="input-w" placeholder="xx-xxxxxxx">
                        </div>
                        <div>
                            <label class="text-sm font-semibold mb-1.5 block">Tax Exempt Code</label>
                            <input name="tax_exempt_code" value="{{ old('tax_exempt_code') }}" class="input-w" placeholder="Optional">
                        </div>
                    </div>
                </div>

                <h2 class="font-bold text-lg mb-4">User Details</h2>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-semibold mb-1.5 block">Contact Name <span class="text-brand">*</span></label>
                        <input name="contact_name" value="{{ old('contact_name') }}" required class="input-w" placeholder="Full name">
                    </div>
                    <div>
                        <label class="text-sm font-semibold mb-1.5 block">Primary Email <span class="text-brand">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="input-w" placeholder="you@business.com">
                    </div>
                    <div>
                        <label class="text-sm font-semibold mb-1.5 block">Billing Email</label>
                        <input type="email" name="billing_email" value="{{ old('billing_email') }}" class="input-w" placeholder="billing@business.com">
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-black/70">
                            <input type="checkbox" name="billing_same_email" value="1" class="rounded" checked id="billingSame">
                            Same as primary email
                        </label>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold mb-1.5 block">Password <span class="text-brand">*</span></label>
                            <input type="password" name="password" required minlength="7" maxlength="16" class="input-w" placeholder="••••••••">
                            <p class="text-[11px] text-black/45 mt-1">7–16 characters, no spaces. Case sensitive.</p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold mb-1.5 block">Confirm Password <span class="text-brand">*</span></label>
                            <input type="password" name="password_confirmation" required minlength="7" maxlength="16" class="input-w" placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-10">
                    <button type="button" class="btn-brand next-step" data-next="2">Next</button>
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="step-panel" data-step="2">
                <h1 class="text-3xl font-extrabold mb-8">Shipping & Billing</h1>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-semibold mb-1.5 block">Address line 1 <span class="text-brand">*</span></label>
                        <input name="address_line_1" value="{{ old('address_line_1') }}" required class="input-w" placeholder="Street address">
                    </div>
                    <div>
                        <label class="text-sm font-semibold mb-1.5 block">Address line 2</label>
                        <input name="address_line_2" value="{{ old('address_line_2') }}" class="input-w" placeholder="Suite, unit, etc.">
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-semibold mb-1.5 block">City <span class="text-brand">*</span></label>
                            <input name="city" value="{{ old('city') }}" required class="input-w">
                        </div>
                        <div>
                            <label class="text-sm font-semibold mb-1.5 block">State <span class="text-brand">*</span></label>
                            <select name="state" required class="input-w">
                                <option value="">Select</option>
                                @foreach($us_states as $code => $name)
                                    <option value="{{ $code }}" @selected(old('state')===$code)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-semibold mb-1.5 block">ZIP <span class="text-brand">*</span></label>
                            <input name="zip_code" value="{{ old('zip_code') }}" required class="input-w">
                        </div>
                    </div>
                    <input type="hidden" name="country" value="United States">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="billing_same_address" value="1" checked class="rounded">
                        Billing address same as shipping
                    </label>
                </div>
                <div class="flex justify-between mt-10 gap-3">
                    <button type="button" class="rounded-lg border border-[#d4d4d4] px-5 py-2.5 text-sm font-semibold prev-step" data-prev="1">Back</button>
                    <button type="button" class="btn-brand next-step" data-next="3">Next</button>
                </div>
            </div>

            {{-- Step 3: Licenses --}}
            <div class="step-panel" data-step="3">
                <h1 class="text-3xl font-extrabold mb-2">Licenses</h1>
                <p class="text-brand font-semibold text-sm mb-8">Licenses</p>

                <div class="space-y-6" id="licenseRows">
                    @foreach($licenseRows as $row)
                        <div class="license-row border border-[#ebebeb] rounded-xl p-4" data-type="{{ $row['key'] }}">
                            <div class="text-sm font-bold mb-3">{{ $row['title'] }}</div>
                            <div class="grid md:grid-cols-3 gap-3">
                                <div>
                                    <label class="text-xs font-semibold mb-1.5 block text-black/70">{{ $row['number_label'] }}</label>
                                    <input name="licenses[{{ $row['key'] }}][number]" value="{{ old('licenses.'.$row['key'].'.number') }}"
                                           class="input-w" placeholder="{{ $row['placeholder'] }}">
                                    <input type="hidden" name="licenses[{{ $row['key'] }}][type]" value="{{ $row['key'] }}">
                                    <input type="hidden" name="licenses[{{ $row['key'] }}][label]" value="{{ $row['title'] }}">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold mb-1.5 block text-black/70">{{ $row['expiry_label'] }}</label>
                                    <input type="date" name="licenses[{{ $row['key'] }}][expires_at]" value="{{ old('licenses.'.$row['key'].'.expires_at') }}" class="input-w">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold mb-1.5 block text-black/70">Document</label>
                                    <label class="file-drop input-w flex items-center justify-center min-h-[46px] cursor-pointer text-sm text-black/55 hover:border-brand">
                                        <span class="file-label"><span class="text-brand font-semibold">Choose File</span> or drag & drop</span>
                                        <input type="file" name="licenses[{{ $row['key'] }}][file]" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden file-input">
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="button" id="addLicenseBtn" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#f3e8ff] text-[#6b21a8] px-4 py-2.5 text-sm font-semibold hover:bg-[#ebe0ff]">
                    + Add License
                </button>

                <label class="mt-8 inline-flex items-start gap-2 text-sm">
                    <input type="checkbox" name="agree_terms" value="1" required class="rounded mt-1">
                    <span>I accept the <a href="{{ route('ecommerce.page', 'terms-of-service') }}" target="_blank" class="text-brand font-semibold">Terms and Conditions</a> & <a href="{{ route('ecommerce.page', 'privacy-policy') }}" target="_blank" class="text-brand font-semibold">Privacy Policy</a></span>
                </label>

                <div class="flex justify-between mt-10 gap-3">
                    <button type="button" class="rounded-lg bg-gray-100 px-5 py-2.5 text-sm font-semibold prev-step" data-prev="2">Back</button>
                    <button type="submit" class="btn-brand">Submit</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('head')
<style>
.step-badge.is-active { background:#e53935; color:#fff; }
.step-badge.is-done { background:#e53935; color:#fff; }
.step-badge.is-done .step-num { display:none; }
.step-badge.is-done .step-check { display:block; }
.file-drop.has-file { border-color:#e53935; color:#1a1a1a; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const panels = [...document.querySelectorAll('.step-panel')];
    const navItems = [...document.querySelectorAll('.step-nav-item')];
    let currentStep = 1;

    function go(step) {
        currentStep = Number(step);
        panels.forEach(p => p.classList.toggle('active', p.dataset.step === String(step)));
        navItems.forEach(item => {
            const n = Number(item.dataset.step);
            const badge = item.querySelector('.step-badge');
            const label = item.querySelector('.step-label');
            badge?.classList.toggle('is-active', n === currentStep);
            badge?.classList.toggle('is-done', n < currentStep);
            if (label) {
                label.classList.toggle('text-brand', n === currentStep);
                label.classList.toggle('font-bold', n === currentStep);
                label.classList.toggle('text-white/70', n !== currentStep);
                label.classList.toggle('font-semibold', n !== currentStep);
            }
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.querySelectorAll('.next-step').forEach(btn => {
        btn.addEventListener('click', () => {
            const step = Number(btn.dataset.next);
            const current = panels.find(p => p.classList.contains('active'));
            const required = current.querySelectorAll('[required]');
            for (const el of required) {
                if (!el.checkValidity()) {
                    el.reportValidity();
                    return;
                }
            }
            go(step);
        });
    });
    document.querySelectorAll('.prev-step').forEach(btn => {
        btn.addEventListener('click', () => go(btn.dataset.prev));
    });

    const billingSame = document.getElementById('billingSame');
    const billingEmail = document.querySelector('[name=billing_email]');
    const primaryEmail = document.querySelector('[name=email]');
    function syncBilling() {
        if (billingSame?.checked) {
            billingEmail.value = primaryEmail.value;
            billingEmail.readOnly = true;
            billingEmail.classList.add('bg-gray-50');
        } else {
            billingEmail.readOnly = false;
            billingEmail.classList.remove('bg-gray-50');
        }
    }
    billingSame?.addEventListener('change', syncBilling);
    primaryEmail?.addEventListener('input', () => { if (billingSame?.checked) syncBilling(); });
    syncBilling();

    document.addEventListener('change', (e) => {
        if (!e.target.classList.contains('file-input')) return;
        const drop = e.target.closest('.file-drop');
        const label = drop?.querySelector('.file-label');
        const file = e.target.files?.[0];
        if (file && label) {
            label.textContent = file.name;
            drop.classList.add('has-file');
        }
    });

    let extra = 0;
    document.getElementById('addLicenseBtn')?.addEventListener('click', () => {
        extra += 1;
        const key = 'other_' + extra;
        const html = `
        <div class="license-row border border-[#ebebeb] rounded-xl p-4" data-type="${key}">
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm font-bold">Additional License</div>
                <button type="button" class="text-xs text-rose-600 remove-license">Remove</button>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
                <div>
                    <label class="text-xs font-semibold mb-1.5 block text-black/70">Certificate Number</label>
                    <input name="licenses[${key}][number]" class="input-w" placeholder="Certificate #">
                    <input type="hidden" name="licenses[${key}][type]" value="other">
                    <input type="hidden" name="licenses[${key}][label]" value="Additional License ${extra}">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block text-black/70">Expiry Date</label>
                    <input type="date" name="licenses[${key}][expires_at]" class="input-w">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block text-black/70">Document</label>
                    <label class="file-drop input-w flex items-center justify-center min-h-[46px] cursor-pointer text-sm text-black/55 hover:border-brand">
                        <span class="file-label"><span class="text-brand font-semibold">Choose File</span> or drag & drop</span>
                        <input type="file" name="licenses[${key}][file]" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden file-input">
                    </label>
                </div>
            </div>
        </div>`;
        document.getElementById('licenseRows').insertAdjacentHTML('beforeend', html);
    });

    document.getElementById('licenseRows')?.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-license')) {
            e.target.closest('.license-row')?.remove();
        }
    });
})();
</script>
@endpush
