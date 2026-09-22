{{-- JapsAI vendor invoice scan (japspos-style): extract → review → insert into host form --}}
@if ($showAiInvoiceModal ?? false)
    <div
        class="desk-modal-backdrop desk-modal-top"
        wire:click.self="closeAiInvoiceModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ai-inv-title"
    >
        <div
            class="desk-modal"
            style="max-width:44rem;width:min(44rem,96vw);"
            wire:keydown.escape.window="closeAiInvoiceModal"
            x-on:paste.window="
                const items = ($event.clipboardData && $event.clipboardData.items) ? [...$event.clipboardData.items] : [];
                const img = items.find(i => i.type && i.type.indexOf('image/') === 0);
                if (!img) return;
                $event.preventDefault();
                const blob = img.getAsFile();
                if (!blob) return;
                const type = blob.type === 'image/jpg' ? 'image/jpeg' : (blob.type || 'image/png');
                const ext = type === 'image/jpeg' ? 'jpg' : (type.split('/')[1] || 'png').toLowerCase();
                $wire.upload('aiInvoiceFile', new File([blob], 'invoice.' + ext, { type: type }));
            "
        >
            <div class="desk-modal-head">
                <span id="ai-inv-title">POS AI — Scan vendor invoice</span>
                <button type="button" wire:click="closeAiInvoiceModal" class="desk-modal-close" aria-label="Close">×</button>
            </div>
            <div class="desk-modal-body" style="padding:0.85rem 1rem 1rem;">
                <p style="margin:0 0 0.75rem;font-size:13px;color:#475569;line-height:1.4;">
                    Upload a supplier invoice photo or PDF, or paste a copied screenshot (Ctrl+V). When the supplier
                    and catalog SKUs match, POS AI creates a <strong>New</strong> purchase order automatically. Review qty and cost — stock still
                    updates only when you <strong>receive</strong>. Nothing is sent to the vendor.
                </p>

                <label class="so-form-lbl" for="ai_invoice_file">Invoice file (JPG, PNG, WebP, PDF) or paste a copied image</label>
                <input
                    id="ai_invoice_file"
                    type="file"
                    wire:model="aiInvoiceFile"
                    accept=".jpg,.jpeg,.png,.webp,.JPG,.JPEG,.PNG,.WEBP,.pdf,.PDF,image/*,application/pdf"
                    class="so-input"
                    style="margin-bottom:0.5rem"
                />
                <div wire:loading wire:target="aiInvoiceFile" style="font-size:12px;color:#64748b;margin-bottom:0.5rem">Uploading…</div>
                @error('aiInvoiceFile') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror

                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin:0.5rem 0 0.75rem;">
                    <button
                        type="button"
                        class="desk-btn desk-btn-primary"
                        wire:click="extractAiInvoice"
                        wire:loading.attr="disabled"
                        wire:target="extractAiInvoice"
                    >
                        <span wire:loading.remove wire:target="extractAiInvoice">Read with POS AI</span>
                        <span wire:loading wire:target="extractAiInvoice">Reading…</span>
                    </button>
                    <button type="button" class="desk-btn" wire:click="closeAiInvoiceModal">Cancel</button>
                </div>

                @if ($aiInvoiceStatus !== '')
                    <p style="margin:0 0 0.5rem;font-size:12px;color:#0369a1;white-space:pre-wrap;line-height:1.45;">{{ $aiInvoiceStatus }}</p>
                @endif
                @if ($aiInvoiceError !== '')
                    <p class="so-field-error" role="alert" style="margin-bottom:0.5rem;white-space:pre-wrap;">{{ $aiInvoiceError }}</p>
                @endif

                @if ($aiInvoiceHeader)
                    <div style="font-size:12px;margin-bottom:0.65rem;padding:0.5rem 0.65rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
                        <div><strong>Supplier:</strong> {{ $aiInvoiceHeader['supplier_name'] ?: '—' }}
                            @if (! empty($aiInvoiceHeader['supplier_id']))
                                <span style="color:#15803d">(matched)</span>
                            @else
                                <span style="color:#b45309">(not matched — pick supplier on PO)</span>
                            @endif
                        </div>
                        <div><strong>Ref:</strong> {{ $aiInvoiceHeader['ref_no'] ?: '—' }}
                            · <strong>Date:</strong> {{ $aiInvoiceHeader['invoice_date'] ?: '—' }}
                            @if ($aiInvoiceHeader['total'] !== null)
                                · <strong>Total:</strong> ${{ number_format((float) $aiInvoiceHeader['total'], 2) }}
                            @endif
                        </div>
                    </div>
                @endif

                @include('livewire.partials.ai-invoice-review-actions')

                @if ($aiInvoiceLines !== [])
                    <div class="desk-grid" style="max-height:min(40vh,18rem);overflow:auto;border:1px solid #e2e8f0;border-radius:6px;">
                        <table class="desk-table" style="font-size:12px;">
                            <thead>
                                <tr>
                                    <th style="width:2rem"></th>
                                    <th>Invoice item</th>
                                    <th>SKU</th>
                                    <th class="desk-money">Qty</th>
                                    <th class="desk-money">Cost</th>
                                    <th>Match</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($aiInvoiceLines as $i => $row)
                                    <tr>
                                        <td class="text-center">
                                            <input
                                                type="checkbox"
                                                @checked(! empty($row['selected']) && ! empty($row['item_id']))
                                                @disabled(empty($row['item_id']))
                                                wire:click.prevent="toggleAiInvoiceLine({{ $i }})"
                                            />
                                        </td>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="desk-num">{{ $row['sku'] ?: '—' }}</td>
                                        <td class="desk-money">{{ number_format((float) $row['quantity'], 2) }}</td>
                                        <td class="desk-money">${{ number_format((float) $row['unit_price'], 2) }}</td>
                                        <td>
                                            @if (! empty($row['item_id']))
                                                <span style="color:#15803d;font-weight:600">{{ $row['item_code'] }}</span>
                                            @else
                                                <span style="color:#b45309">Unmatched</span>
                                                <a
                                                    href="{{ route('inventory.items.create', array_filter(['upc' => $row['sku'] ?? null])) }}"
                                                    target="_blank"
                                                    class="desk-link"
                                                    style="margin-left:0.35rem;font-size:11px"
                                                >Create item</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
