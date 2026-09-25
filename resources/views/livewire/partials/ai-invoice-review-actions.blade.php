@php
    $invBtns = method_exists($this, 'invoiceReviewButtons')
        ? $this->invoiceReviewButtons()
        : ['has' => false, 'lists_ready' => false, 'amounts_match' => false, 'can_confirm' => false];
@endphp
@if (! empty($invBtns['has']))
    <div class="posai-inv-actions" style="display:flex;flex-wrap:wrap;align-items:center;gap:0.45rem;margin:0.5rem 0 0.15rem;">
        @if (empty($invBtns['lists_ready']))
            <button
                type="button"
                class="desk-btn desk-btn-primary"
                wire:click="addMissingInvoiceToLists"
                wire:loading.attr="disabled"
                wire:target="addMissingInvoiceToLists,confirmAiInvoicePurchaseOrder"
            >
                Review &amp; add to lists
            </button>
        @endif
        <button
            type="button"
            class="desk-btn {{ ! empty($invBtns['can_confirm']) ? 'desk-btn-primary' : '' }}"
            wire:click="confirmAiInvoicePurchaseOrder"
            wire:loading.attr="disabled"
            wire:target="addMissingInvoiceToLists,confirmAiInvoicePurchaseOrder"
            @disabled(empty($invBtns['can_confirm']))
            @if (empty($invBtns['can_confirm'])) title="Invoice amount does not match the lines read — upload the invoice again" @endif
        >
            Confirm &amp; create PO
        </button>
        @if (empty($invBtns['amounts_match']))
            <span style="font-size:0.78rem;color:#b45309;">Invoice amount not matched — upload again.</span>
        @elseif (empty($invBtns['lists_ready']))
            <span style="font-size:0.78rem;color:#1d4ed8;">Amounts match — add missing items, then Confirm.</span>
        @else
            <span style="font-size:0.78rem;color:#15803d;">All items matched — click Confirm &amp; create PO.</span>
        @endif
    </div>
@endif
