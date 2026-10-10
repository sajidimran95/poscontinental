    @if ($modalInvoice)
        <div class="desk-modal-backdrop" role="dialog" aria-modal="true" aria-label="Payments and Credits">
            <div class="desk-modal desk-modal-xl pc-modal">
                <div class="desk-modal-head">
                    <div class="inv-modal-title">
                        <span>Payments &amp; Credits</span>
                        <span @class([
                            'desk-pill',
                            'desk-pill-new' => $modalInvoice->status === 'NOT PAID',
                            'desk-pill-invoiced' => $modalInvoice->status === 'PAID',
                        ])>{{ $modalInvoice->status }}</span>
                    </div>
                    <div class="desk-modal-head-actions">
                        <a href="{{ route('sales.invoices.pdf', $modalInvoice) }}" class="desk-btn desk-btn-sm" target="_blank">Print PDF</a>
                        <button type="button" wire:click="$set('showEmailForm', true)" class="desk-btn desk-btn-sm">Email</button>
                        <button type="button" wire:click="closeModal" class="desk-modal-close" aria-label="Close">×</button>
                    </div>
                </div>

                <div class="desk-modal-body pc-modal-body">
                    <div class="pc-top">
                        <div class="pc-top-left">
                            <div class="pc-kv"><label>Order No.</label><div class="pc-val desk-num">{{ $modalInvoice->salesOrder?->order_number ?: '—' }}</div></div>
                            <div class="pc-kv"><label>Order Date</label><div class="pc-val">{{ optional($modalInvoice->salesOrder?->order_date)?->format('n/j/Y') ?: '—' }}</div></div>
                            <div class="pc-kv"><label>Sales Rep.</label><div class="pc-val">{{ $modalInvoice->salesOrder?->salesRep?->name ?: '' }}</div></div>
                            <div class="pc-kv"><label>Status</label><div class="pc-val">{{ $modalInvoice->salesOrder?->status ?: 'Invoiced' }}{{ $modalInvoice->salesOrder?->delivery_status ? ' · '.ucfirst(str_replace('_', ' ', $modalInvoice->salesOrder->delivery_status)) : '' }}</div></div>
                            <div class="pc-kv"><label>Invoice No.</label><div class="pc-val desk-num">{{ $modalInvoice->invoice_number }}</div></div>
                            <div class="pc-kv"><label>Invoice Date</label><div class="pc-val">{{ optional($modalInvoice->invoice_date)?->format('n/j/Y') }}</div></div>
                        </div>

                        <div class="pc-top-mid">
                            <div class="pc-kv pc-kv-block">
                                <label>Bill to</label>
                                <div class="pc-billto">
                                    <strong>{{ $modalInvoice->salesOrder?->bill_to_name ?: $modalInvoice->customer?->company_name ?: '—' }}</strong>
                                    <div>{{ $modalInvoice->salesOrder?->bill_to_address }}</div>
                                    @if ($modalInvoice->salesOrder?->bill_to_city || $modalInvoice->salesOrder?->bill_to_state || $modalInvoice->salesOrder?->bill_to_zip)
                                        <div>{{ collect([$modalInvoice->salesOrder?->bill_to_city, $modalInvoice->salesOrder?->bill_to_state, $modalInvoice->salesOrder?->bill_to_zip])->filter()->implode(', ') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="pc-kv"><label>Terms</label><div class="pc-val">{{ $modalInvoice->salesOrder?->paymentTerm?->name ?: '' }}</div></div>
                            <div class="pc-kv">
                                <label for="invoice-driver">Driver</label>
                                <input id="invoice-driver" wire:model.live.debounce.400ms="driver" wire:blur="saveDriver" class="so-input pc-input" placeholder="Driver name" autocomplete="off" />
                            </div>
                        </div>

                        <div class="pc-top-right">
                            <div class="pc-sum-row"><span>Subtotal</span><strong>${{ number_format((float) $modalInvoice->subtotal, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Total Qty</span><strong>{{ number_format((float) ($modalInvoice->salesOrder?->lines?->sum('qty_ordered') ?? 0), 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Trade Discount</span><strong>${{ number_format((float) $modalInvoice->trade_discount, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Freight</span><strong>${{ number_format((float) $modalInvoice->freight, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Miscellaneous</span><strong>${{ number_format((float) $modalInvoice->miscellaneous, 2) }}</strong></div>
                            <div class="pc-sum-row pc-sum-total"><span>Total</span><strong>${{ number_format((float) $modalInvoice->invoice_total, 2) }}</strong></div>
                        </div>
                    </div>

                    <div class="pc-section">
                        <div class="pc-section-head">
                            <h3>Collected Payments</h3>
                            <div class="pc-row-tools">
                                <button type="button" class="pc-tool-btn" wire:click="addPaymentRow" title="Add payment">+</button>
                                <button type="button" class="pc-tool-btn" wire:click="removePaymentRow" title="Remove selected">−</button>
                            </div>
                        </div>
                        <div class="pc-pay-meta">
                            <div class="pc-pay-meta-row">
                                <span>Invoice Amount Due</span>
                                <strong>${{ number_format((float) max(0, $savedBalance + $pcDelta), 2) }}</strong>
                            </div>
                            @if ($draftPayTotal > 0.0001 || $draftCreditTotal > 0.0001)
                                <div class="pc-pay-meta-row">
                                    <span>Entered now</span>
                                    <strong>${{ number_format((float) $draftPayTotal + $draftCreditTotal, 2) }}</strong>
                                </div>
                            @endif
                        </div>
                        <div class="pc-grid-wrap">
                            <table class="desk-table pc-table">
                                <thead>
                                    <tr>
                                        <th style="width:8.5rem">Payment Date</th>
                                        <th style="width:9rem">Payment Method</th>
                                        <th style="width:7.5rem">Check #</th>
                                        <th style="width:7rem" class="text-right">Amount</th>
                                        <th>Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $returnedCheckIds = $modalInvoice->payments
                                            ->filter(fn ($rp) => \App\Models\InvoicePayment::isReturnedCheckMethod($rp->payment_method))
                                            ->map(fn ($rp) => preg_match('/#(\d+)/', (string) $rp->comments, $m) ? (int) $m[1] : 0)
                                            ->filter()
                                            ->values()
                                            ->all();
                                    @endphp
                                    @foreach ($modalInvoice->payments as $p)
                                        @php
                                            $pIsCheck = \App\Models\InvoicePayment::isCheckMethod($p->payment_method);
                                            $pIsReturnLine = \App\Models\InvoicePayment::isReturnedCheckMethod($p->payment_method);
                                            $pWasReturned = $pIsCheck && in_array((int) $p->id, $returnedCheckIds, true);
                                        @endphp
                                        <tr class="pc-row-saved">
                                            <td>{{ optional($p->payment_date)?->format('n/j/Y') }}</td>
                                            <td>{{ $p->payment_method }}</td>
                                            <td class="desk-num">{{ $p->check_number ?: '—' }}</td>
                                            <td class="desk-money">${{ number_format((float) $p->amount, 2) }}</td>
                                            <td>
                                                @if ($pIsReturnLine)
                                                    <span class="pc-returned-tag">Returned</span>
                                                @elseif ($pWasReturned)
                                                    <span class="pc-returned-tag">Bounced</span>
                                                @else
                                                    <span class="pc-saved-tag">Saved</span>
                                                @endif
                                                {{ $p->comments }}
                                                @if ($pIsCheck && ! $pWasReturned)
                                                    <button
                                                        type="button"
                                                        class="pc-row-return"
                                                        wire:click="returnSavedCheck({{ $p->id }})"
                                                        wire:confirm="Return check #{{ $p->check_number ?: $p->id }} (${{ number_format((float) $p->amount, 2) }})? The invoice will owe this amount again plus a ${{ number_format(\App\Models\InvoicePayment::RETURNED_CHECK_FEE, 2) }} returned-check fee (Miscellaneous)."
                                                        title="Return check (bounced)"
                                                    >Return Check</button>
                                                @endif
                                                <button
                                                    type="button"
                                                    class="pc-row-remove"
                                                    wire:click="removeSavedPayment({{ $p->id }})"
                                                    wire:confirm="{{ $pIsReturnLine
                                                        ? 'Undo this returned check? The check counts as paid again and the returned-check fee is removed.'
                                                        : 'Void this payment of $'.number_format((float) $p->amount, 2).'? The invoice will owe this amount again.' }}"
                                                    title="{{ $pIsReturnLine ? 'Undo returned check' : 'Void payment' }}"
                                                >{{ $pIsReturnLine ? 'Undo' : 'Void' }}</button>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @forelse ($draftPayments as $i => $row)
                                        @php $rowIsCheck = \App\Models\InvoicePayment::isCheckMethod($row['payment_method'] ?? ''); @endphp
                                        <tr
                                            wire:key="draft-pay-{{ $row['key'] }}"
                                            wire:click="selectPaymentRow({{ $i }})"
                                            @class(['is-selected' => $selectedPaymentIndex === $i])
                                        >
                                            <td>
                                                <input type="date" class="so-input pc-cell-input" wire:model.live="draftPayments.{{ $i }}.payment_date" />
                                            </td>
                                            <td>
                                                <select class="so-input pc-cell-input" wire:model.live="draftPayments.{{ $i }}.payment_method">
                                                    <option>Cash</option>
                                                    <option>Credit Card</option>
                                                    <option>Check</option>
                                                    <option>ACH</option>
                                                    <option>Other</option>
                                                </select>
                                            </td>
                                            <td>
                                                @if ($rowIsCheck)
                                                    <input
                                                        type="text"
                                                        class="so-input pc-cell-input"
                                                        wire:model.live="draftPayments.{{ $i }}.check_number"
                                                        placeholder="Check number"
                                                        autocomplete="off"
                                                    />
                                                @else
                                                    <span class="text-slate-400">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="text" inputmode="decimal" class="so-input pc-cell-input text-right" wire:model.live="draftPayments.{{ $i }}.amount" placeholder="0" />
                                            </td>
                                            <td>
                                                <input type="text" class="so-input pc-cell-input" wire:model.live="draftPayments.{{ $i }}.comments" placeholder="Optional" />
                                            </td>
                                        </tr>
                                    @empty
                                        @if ($modalInvoice->payments->isEmpty())
                                            <tr class="is-empty"><td colspan="5">Use + or Add Payment to enter amount. Split any amount, then Add 2nd Due for the rest.</td></tr>
                                        @endif
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pc-bottom">
                        <div class="pc-section pc-credits">
                            <div class="pc-section-head">
                                <h3>Applied Credits</h3>
                                <div class="pc-row-tools">
                                    <button type="button" class="pc-tool-btn" wire:click="addCreditRow" title="{{ $pcOpenCredits->isEmpty() ? 'No credit memo — go create one' : 'Add credit' }}">+</button>
                                    <button type="button" class="pc-tool-btn" wire:click="removeCreditRow" title="Remove selected">−</button>
                                </div>
                            </div>
                            @if ($pcOpenCredits->isEmpty())
                                <div class="pc-credit-empty">
                                    This customer has no open credit memo.
                                    <button type="button" class="pc-link-btn" wire:click="addCreditRow">Go to Credit Memo</button>
                                </div>
                            @endif
                            <div class="pc-grid-wrap">
                                <table class="desk-table pc-table">
                                    <thead>
                                        <tr>
                                            <th>Memo No.</th>
                                            <th style="width:7.5rem">Memo Date</th>
                                            @if ($hasCreditSalesOrder)
                                                <th style="width:6.5rem">Order No.</th>
                                            @endif
                                            <th style="width:7rem" class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($modalInvoice->credits as $c)
                                            <tr class="pc-row-saved">
                                                <td class="desk-num">{{ $c->creditMemo?->memo_number }}</td>
                                                <td>{{ optional($c->creditMemo?->memo_date)?->format('n/j/Y') }}</td>
                                                @if ($hasCreditSalesOrder)
                                                    <td class="desk-num">{{ $c->creditMemo?->salesOrder?->order_number ?: '—' }}</td>
                                                @endif
                                                <td class="desk-money">
                                                    ${{ number_format((float) $c->amount, 2) }}
                                                    <button
                                                        type="button"
                                                        class="pc-row-remove"
                                                        wire:click="removeSavedCredit({{ $c->id }})"
                                                        wire:confirm="Void this applied credit of ${{ number_format((float) $c->amount, 2) }}? The credit memo goes back to open."
                                                        title="Void credit"
                                                    >Void</button>
                                                </td>
                                            </tr>
                                        @endforeach

                                        @forelse ($draftCredits as $i => $row)
                                            @php
                                                $selectedMemo = $pcOpenCredits->firstWhere('id', (int) ($row['credit_memo_id'] ?? 0));
                                            @endphp
                                            <tr
                                                wire:key="draft-cr-{{ $row['key'] }}"
                                                wire:click="selectCreditRow({{ $i }})"
                                                @class(['is-selected' => $selectedCreditIndex === $i])
                                            >
                                                <td>
                                                    <select class="so-input pc-cell-input" wire:model.live="draftCredits.{{ $i }}.credit_memo_id">
                                                        <option value="">— Select credit memo —</option>
                                                        @foreach ($pcOpenCredits as $cm)
                                                            <option value="{{ $cm->id }}">{{ $cm->memo_number }} (${{ number_format($cm->remaining_amount, 2) }} left)</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>{{ optional($selectedMemo?->memo_date)?->format('n/j/Y') ?: '—' }}</td>
                                                @if ($hasCreditSalesOrder)
                                                    <td class="desk-num">{{ $selectedMemo?->salesOrder?->order_number ?: '—' }}</td>
                                                @endif
                                                <td>
                                                    <input type="text" inputmode="decimal" class="so-input pc-cell-input text-right" wire:model.live="draftCredits.{{ $i }}.amount" placeholder="0" />
                                                </td>
                                            </tr>
                                        @empty
                                            @if ($modalInvoice->credits->isEmpty())
                                                <tr class="is-empty"><td colspan="{{ $hasCreditSalesOrder ? 4 : 3 }}">Use + to select from outstanding credit memos.</td></tr>
                                            @endif
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="pc-totals">
                            <div class="pc-sum-row pc-adj-row" title="Subtracted from the invoice total">
                                <label for="pc_trade_discount">Trade Discount</label>
                                <span class="pc-adj-ctl">$<input id="pc_trade_discount" type="text" inputmode="decimal" wire:model.live.debounce.500ms="pc_trade_discount" class="so-input pc-adj-input" placeholder="0.00" autocomplete="off" /></span>
                            </div>
                            <div class="pc-sum-row pc-adj-row" title="Added to the invoice total">
                                <label for="pc_freight">Freight</label>
                                <span class="pc-adj-ctl">$<input id="pc_freight" type="text" inputmode="decimal" wire:model.live.debounce.500ms="pc_freight" class="so-input pc-adj-input" placeholder="0.00" autocomplete="off" /></span>
                            </div>
                            <div class="pc-sum-row pc-adj-row" title="Added to the invoice total">
                                <label for="pc_miscellaneous">Miscellaneous</label>
                                <span class="pc-adj-ctl">$<input id="pc_miscellaneous" type="text" inputmode="decimal" wire:model.live.debounce.500ms="pc_miscellaneous" class="so-input pc-adj-input" placeholder="0.00" autocomplete="off" /></span>
                            </div>
                            <div @class(['pc-sum-row', 'pc-sum-changed' => abs($pcDelta) >= 0.005])><span>New Total</span><strong>${{ number_format((float) $pcNewTotal, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Total Credits</span><strong>${{ number_format((float) $previewCredits, 2) }}</strong></div>
                            <div class="pc-sum-row"><span>Total Payments</span><strong>${{ number_format((float) $previewPayments, 2) }}</strong></div>
                            <div class="pc-sum-row pc-sum-balance"><span>Invoice Balance</span><strong>${{ number_format((float) max(0, $savedBalance + $pcDelta), 2) }}</strong></div>
                            @if (abs($pcDelta) >= 0.005)
                                <div class="pc-sum-hint">Total {{ $pcDelta > 0 ? 'goes up' : 'goes down' }} ${{ number_format(abs($pcDelta), 2) }}. Click Save to apply.</div>
                            @endif
                            @if ($draftPayTotal > 0.0001 || $draftCreditTotal > 0.0001)
                                <div class="pc-sum-row"><span>After Save</span><strong>${{ number_format((float) $previewBalance, 2) }}</strong></div>
                                <div class="pc-sum-hint">Click Save to apply. Balance above is current unpaid amount.</div>
                            @endif
                        </div>
                    </div>

                    <div class="pc-footer">
                        @if (session('status'))
                            <div class="pc-footer-msg" role="status">{{ session('status') }}</div>
                        @endif
                        <div class="pc-footer-actions">
                            <button type="button" wire:click="closeModal" class="desk-btn">Cancel</button>
                            <button type="button" wire:click="saveAndPrint" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">Save &amp; Print</button>
                            <button type="button" wire:click="savePayments" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showEmailForm && $modalInvoice)
        <div class="desk-modal-backdrop desk-modal-top" wire:click.self="$set('showEmailForm', false)" role="dialog" aria-modal="true" aria-label="Email invoice">
            <div class="desk-modal desk-modal-sm">
                <div class="desk-modal-head">
                    <span>Email Invoice {{ $modalInvoice->invoice_number }}</span>
                    <button type="button" wire:click="$set('showEmailForm', false)" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <form method="POST" action="{{ route('sales.invoices.email', $modalInvoice) }}" class="desk-modal-body space-y-3">
                    @csrf
                    <p class="inv-email-note">Sends the invoice PDF to the customer email address.</p>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="inv-email">To</label>
                        <input id="inv-email" name="email" type="email" value="{{ $emailTo }}" required class="so-input" placeholder="customer@email.com" />
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="inv-subject">Subject</label>
                        <input id="inv-subject" name="subject" value="{{ $emailSubject }}" class="so-input" />
                    </div>
                    <div class="entity-footer-actions" style="justify-content:flex-end">
                        <button type="button" wire:click="$set('showEmailForm', false)" class="desk-btn">Cancel</button>
                        <button type="submit" class="desk-btn desk-btn-primary">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
