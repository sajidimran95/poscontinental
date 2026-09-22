@php
    $invBtns = method_exists($this, 'invoiceReviewButtons') ? $this->invoiceReviewButtons() : ['has' => false, 'lists_ready' => false];
@endphp
@if (! empty($invBtns['has']))
    <div class="posai-inv-actions" style="display:flex;flex-wrap:wrap;gap:0.45rem;margin:0.5rem 0 0.15rem;">
        <button
            type="button"
            class="desk-btn desk-btn-primary"
            wire:click="addMissingInvoiceToLists"
            wire:loading.attr="disabled"
            wire:target="addMissingInvoiceToLists,confirmAiInvoicePurchaseOrder"
        >
            Review &amp; add to lists
        </button>
        <button
            type="button"
            class="desk-btn {{ ! empty($invBtns['lists_ready']) ? 'desk-btn-primary' : '' }}"
            wire:click="confirmAiInvoicePurchaseOrder"
            wire:loading.attr="disabled"
            wire:target="addMissingInvoiceToLists,confirmAiInvoicePurchaseOrder"
        >
            Confirm &amp; create PO
        </button>
    </div>
@endif
