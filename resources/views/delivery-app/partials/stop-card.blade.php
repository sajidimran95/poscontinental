@php
    $active = $active ?? null;
    $suggested = $suggested ?? null;
@endphp
<div class="card" id="dlv-stop-card">
    <div class="muted">STOP {{ $active->stop_no }} · {{ $active->statusLabel() }}
        @if ($suggested && $suggested->id === $active->id)
            · SUGGESTED
        @endif
    </div>
    <h2 style="margin:.25rem 0;font-size:1.15rem">Order #{{ $active->order?->order_number }}</h2>
    <p style="margin:.2rem 0">{{ $active->ship_to_name ?: $active->order?->customer?->company_name }}</p>
    <p class="muted">{!! nl2br(e($active->formattedAddress())) !!}</p>
    <p class="muted">{{ $active->ship_to_phone ?: $active->order?->customer?->telephone }}</p>
    <div class="row">
        <a class="btn btn-p" href="{{ $active->navigateUrl() }}" target="_blank" rel="noopener">Navigate</a>
        @if ($active->canAct() && $active->status !== 'arrived')
            <form method="POST" action="{{ route('delivery.app.arrived', $active) }}">@csrf<button class="btn btn-w" type="submit">Arrived</button></form>
        @endif
    </div>
    <form method="POST" action="{{ route('delivery.app.notes', $active) }}" style="margin-top:.75rem">
        @csrf
        <label class="muted">Delivery note</label>
        <textarea name="notes" rows="2" placeholder="Gate code, who signed, short…">{{ old('notes', $active->delivery_notes) }}</textarea>
        <button class="btn btn-w" type="submit" style="width:100%;margin-top:.5rem">Save note</button>
    </form>
    @if ($active->canAct())
        <form method="POST" action="{{ route('delivery.app.delivered', $active) }}" style="margin-top:.75rem">
            @csrf
            <label class="muted">Note when marking delivered</label>
            <textarea name="notes" rows="2" placeholder="Optional">{{ old('notes', $active->delivery_notes) }}</textarea>
            <button class="btn btn-g" type="submit" style="width:100%;margin-top:.5rem">Mark delivered</button>
        </form>
        <form method="POST" action="{{ route('delivery.app.failed', $active) }}" style="margin-top:.75rem">
            @csrf
            <label class="muted">Could not deliver</label>
            <select name="fail_reason" required>
                <option value="">Reason…</option>
                @foreach (\App\Models\DeliveryRouteOrder::FAIL_REASONS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <textarea name="notes" rows="2" placeholder="Note" style="margin-top:.4rem">{{ old('notes') }}</textarea>
            <button class="btn btn-w" type="submit" style="width:100%;margin-top:.5rem">Save failed</button>
        </form>
    @endif
</div>
