@extends('store.layouts.store')
@section('title', 'Track order')
@section('content')
<div class="max-w-xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-semibold mb-6">Track order</h1>
    <form method="get" class="flex gap-2 mb-8">
        <input name="token" value="{{ $token }}" placeholder="Tracking token" class="flex-1 border rounded-full px-4 py-2.5 text-sm">
        <button class="rounded-full bg-ink text-white px-5 text-sm">Track</button>
    </form>
    @if($meta && $order)
        <div class="rounded-2xl border bg-white p-6 space-y-5">
            <div>
                <div class="font-semibold text-lg">{{ $order->order_number }}</div>
                <div class="text-sm text-black/50 mt-1">
                    Fulfillment:
                    <span class="inline-flex items-center rounded-full bg-mist px-2.5 py-0.5 text-xs font-bold text-ink">{{ $status_label }}</span>
                </div>
                <div class="text-sm text-black/50 mt-1">
                    Sales order status:
                    <span class="font-medium text-ink">{{ $order->status }}</span>
                </div>
            </div>

            @if($invoice)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <div class="text-xs font-bold uppercase tracking-wide text-emerald-700 mb-2">Invoice created</div>
                    <div class="font-semibold text-sm text-emerald-900">
                        #{{ $invoice->invoice_number }}
                        <span class="text-emerald-700/70 font-normal">· {{ optional($invoice->invoice_date ?? $invoice->created_at)->format('M j, Y') }}</span>
                    </div>
                </div>
            @endif

            <ol class="space-y-3 text-sm">
                @forelse($timeline as $step)
                    <li class="flex justify-between gap-3 border-b border-line pb-2">
                        <span class="@if(($step['status'] ?? '') === 'invoiced') font-semibold text-emerald-700 @endif">{{ $step['label'] }}</span>
                        <span class="text-black/40 shrink-0">{{ $step['at'] ?? '' }}</span>
                    </li>
                @empty
                    <li class="text-black/40">No updates yet.</li>
                @endforelse
            </ol>
        </div>
    @elseif($token)
        <p class="text-rose-600 text-sm">Order not found.</p>
    @endif
</div>
@endsection
