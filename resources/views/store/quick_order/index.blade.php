@extends('store.layouts.store')
@section('title', 'Quick Order')
@section('content')
<div class="ecom-acc max-w-[1100px] mx-auto px-4 py-10">
    @include('store.account.partials.mobile_topbar', ['title' => 'Quick Order'])
    <p class="text-[11px] uppercase tracking-[0.18em] text-black/40 font-bold mb-2 ecom-acc-desktop-only">Wholesale</p>
    <h1 class="text-3xl md:text-4xl font-extrabold mb-2 ecom-acc-desktop-only">Quick Order</h1>
    <p class="text-sm text-black/55 mb-8 max-w-2xl ecom-acc-desktop-only">Enter SKUs and quantities to add multiple items at once. Paste lines as <code class="text-xs bg-mist px-1 rounded">SKU, qty</code> or fill the table below.</p>
    <p class="text-sm text-black/55 mb-4 px-1 ecom-acc-mobile-only">Enter SKUs and quantities. Paste as <code class="text-xs bg-mist px-1 rounded">SKU, qty</code> or use the table.</p>

    <form method="post" action="{{ route('ecommerce.quick_order.submit', absolute: false) }}" class="space-y-6">
        @csrf

        <div class="rounded-xl bg-white border border-line overflow-hidden">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3">
                <h2 class="font-extrabold text-sm uppercase tracking-wide">Order lines</h2>
                <button type="button" id="addRowBtn" class="text-sm font-bold text-brand">+ Add row</button>
            </div>
            <div class="p-4 overflow-x-auto">
                <table class="w-full text-sm" id="qoTable">
                    <thead>
                        <tr class="text-left text-black/45 text-xs uppercase tracking-wide">
                            <th class="pb-2 pr-2 font-bold">SKU</th>
                            <th class="pb-2 pr-2 font-bold w-32">Qty</th>
                            <th class="pb-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody id="qoBody">
                        @for($i = 0; $i < 8; $i++)
                        <tr class="qo-row border-t border-line">
                            <td class="py-2 pr-2">
                                <input name="rows[{{ $i }}][sku]" value="{{ old('rows.'.$i.'.sku') }}" class="input-w" placeholder="Item code or UPC" autocomplete="off">
                            </td>
                            <td class="py-2 pr-2">
                                <input type="number" name="rows[{{ $i }}][qty]" value="{{ old('rows.'.$i.'.qty', 1) }}" min="1" step="1" class="input-w">
                            </td>
                            <td class="py-2">
                                <button type="button" class="qo-remove text-rose-600 text-xs font-semibold">✕</button>
                            </td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl bg-white border border-line p-4">
            <label class="font-extrabold text-sm uppercase tracking-wide block mb-2">Or paste list</label>
            <textarea name="bulk" rows="5" class="input-w font-mono text-xs" placeholder="10025, 12&#10;20431, 6&#10;028200003843 24">{{ old('bulk') }}</textarea>
            <p class="text-xs text-black/45 mt-2">One product per line. Formats: <strong>SKU, qty</strong> or <strong>SKU qty</strong>.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button class="btn-brand">Add to cart</button>
            <a href="{{ route('ecommerce.cart') }}" class="rounded-lg border border-line px-5 py-2.5 text-sm font-semibold hover:bg-mist">View cart</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const body = document.getElementById('qoBody');
    const addBtn = document.getElementById('addRowBtn');
    if (!body || !addBtn) return;
    let idx = body.querySelectorAll('.qo-row').length;

    function bindRemove(row) {
        row.querySelector('.qo-remove')?.addEventListener('click', () => {
            if (body.querySelectorAll('.qo-row').length <= 1) {
                row.querySelectorAll('input').forEach(i => { if (i.type === 'number') i.value = 1; else i.value = ''; });
                return;
            }
            row.remove();
        });
    }
    body.querySelectorAll('.qo-row').forEach(bindRemove);

    addBtn.addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.className = 'qo-row border-t border-line';
        tr.innerHTML = `
            <td class="py-2 pr-2"><input name="rows[${idx}][sku]" class="input-w" placeholder="SKU" autocomplete="off"></td>
            <td class="py-2 pr-2"><input type="number" name="rows[${idx}][qty]" value="1" min="1" step="1" class="input-w"></td>
            <td class="py-2"><button type="button" class="qo-remove text-rose-600 text-xs font-semibold">✕</button></td>`;
        body.appendChild(tr);
        bindRemove(tr);
        idx++;
    });
})();
</script>
@endpush
