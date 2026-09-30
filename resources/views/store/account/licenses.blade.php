@extends('store.layouts.store')
@section('title', 'Licenses')
@section('content')
@php
    $badge = match($approval) {
        'pending' => 'bg-amber-100 text-amber-900',
        'rejected' => 'bg-rose-100 text-rose-900',
        default => 'bg-emerald-100 text-emerald-900',
    };
@endphp
<div class="ecom-acc max-w-5xl mx-auto px-4 py-10">
    @include('store.account.partials.mobile_topbar', ['title' => 'Licenses'])

    <div class="ecom-acc-mobile-only">
        <div class="px-3 pt-2 pb-1">
            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge }}">
                {{ \App\Support\Store\WholesaleAccount::label($approval) }}
            </span>
        </div>
        @if($approval === 'pending')
            <div class="ecom-acc-banner border border-amber-200 bg-amber-50 text-amber-950">
                Pending admin review. Checkout unlocks after approval.
            </div>
        @elseif($approval === 'rejected')
            <div class="ecom-acc-banner border border-rose-200 bg-rose-50 text-rose-950">
                Rejected. Upload updated licenses to resubmit.
            </div>
        @endif

        <div class="ecom-acc-section">Uploaded</div>
        <div class="ecom-acc-list">
            @forelse($licenses as $lic)
                <div class="ecom-acc-order">
                    <div class="font-bold text-[15px]">{{ $lic->label ?: $lic->license_type }}</div>
                    <div class="text-[12px] text-black/50">
                        {{ $lic->certificate_number ?: '—' }}
                        @if($lic->expires_at) · Exp {{ $lic->expires_at->format('M j, Y') }}@endif
                    </div>
                    @if($lic->file_path)
                        <a class="text-brand font-bold text-[13px] mt-1" href="{{ route('ecommerce.account.licenses.file', $lic->id) }}" target="_blank">
                            {{ $lic->original_name ?: 'View file' }}
                        </a>
                    @endif
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-center text-black/45">No licenses uploaded yet.</div>
            @endforelse
        </div>

        <div class="ecom-acc-section">Upload another</div>
        <div class="ecom-acc-card ecom-acc-form">
            <form method="post" action="{{ route('ecommerce.account.licenses', absolute: false) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">Type</label>
                    <select name="license_type" class="input-w" required>
                        <option value="business">Business Certificate</option>
                        <option value="resale">Resale Certificate</option>
                        <option value="tobacco">Tobacco Certificate</option>
                        <option value="id">ID Certificate</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">Label (optional)</label>
                    <input name="label" class="input-w" placeholder="Custom label">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">Certificate number</label>
                    <input name="certificate_number" class="input-w">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">Expiry</label>
                    <input type="date" name="expires_at" class="input-w">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">File (PDF/JPG/PNG)</label>
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="input-w">
                </div>
                <button type="submit" class="btn-brand">Submit license</button>
            </form>
        </div>
    </div>

    <div class="ecom-acc-desktop-only">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
            <div>
                <p class="text-[11px] uppercase tracking-[0.18em] text-black/40 font-bold mb-2">Account</p>
                <h1 class="text-3xl font-extrabold">Licenses</h1>
                <p class="text-sm text-black/55 mt-2">Upload business, resale, tobacco, or ID certificates for wholesale approval.</p>
            </div>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">
                {{ \App\Support\Store\WholesaleAccount::label($approval) }}
            </span>
        </div>

        @if($approval === 'pending')
            <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-950 text-sm p-4 mb-6">
                Your account is pending admin review. You can browse and build a cart, but checkout unlocks after approval.
            </div>
        @elseif($approval === 'rejected')
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-950 text-sm p-4 mb-6">
                Your account was rejected. Upload updated licenses below to resubmit for review.
            </div>
        @endif

        <div class="rounded-xl bg-white border border-line overflow-hidden mb-8">
            <div class="px-4 py-3 border-b border-line font-extrabold text-sm uppercase tracking-wide">Uploaded certificates</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-black/45 border-b border-line">
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Number</th>
                            <th class="px-4 py-3">Expiry</th>
                            <th class="px-4 py-3">File</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($licenses as $lic)
                        <tr class="border-b border-line">
                            <td class="px-4 py-3 font-semibold">{{ $lic->label ?: $lic->license_type }}</td>
                            <td class="px-4 py-3">{{ $lic->certificate_number ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $lic->expires_at ? $lic->expires_at->format('M j, Y') : '—' }}</td>
                            <td class="px-4 py-3">
                                @if($lic->file_path)
                                    <a class="text-brand font-semibold" href="{{ route('ecommerce.account.licenses.file', $lic->id) }}" target="_blank">{{ $lic->original_name ?: 'View' }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-black/45">No licenses uploaded yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl bg-white border border-line p-5">
            <h2 class="font-extrabold mb-4">Upload another license</h2>
            <form method="post" action="{{ route('ecommerce.account.licenses', absolute: false) }}" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
                @csrf
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">Type</label>
                    <select name="license_type" class="input-w" required>
                        <option value="business">Business Certificate</option>
                        <option value="resale">Resale Certificate</option>
                        <option value="tobacco">Tobacco Certificate</option>
                        <option value="id">ID Certificate</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">Label (optional)</label>
                    <input name="label" class="input-w" placeholder="Custom label">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">Certificate number</label>
                    <input name="certificate_number" class="input-w">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1.5 block">Expiry</label>
                    <input type="date" name="expires_at" class="input-w">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs font-semibold mb-1.5 block">File (PDF/JPG/PNG)</label>
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="input-w">
                </div>
                <div class="md:col-span-2">
                    <button class="btn-brand">Submit license</button>
                    <a href="{{ route('ecommerce.account') }}" class="ml-3 text-sm font-semibold text-black/50 hover:text-brand">Back to account</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
