@php $block = $intel ?? []; @endphp

@if ($intelKey === 'demand')
    <p class="posai-suggest-detail"><strong>How this is built:</strong> {{ $block['method'] ?? 'Rule-based from the last 90 days of invoiced sales (not OpenAI). Create draft PO opens a purchase order for review — it is not sent to the vendor.' }}</p>
    <p class="posai-suggest-detail">{{ (int) ($block['suggested_count'] ?? 0) }} SKUs have a quantity{{ (int) ($block['suggested_count'] ?? 0) > (int) ($block['shown_count'] ?? 0) ? ', showing the '.(int) ($block['shown_count'] ?? 0).' most urgent' : '' }}. {{ (int) ($block['insufficient_count'] ?? 0) }} were skipped because history is too thin to guess.</p>
    @forelse (($block['groups'] ?? []) as $group)
        <div class="posai-group">
            <h4>{{ $group['supplier_name'] ?? 'Supplier' }}</h4>
            <button type="button" class="desk-btn desk-btn-sm desk-btn-primary" wire:click="draftReorderPo({{ (int) ($group['supplier_id'] ?? 0) }})">Create draft PO</button>
            <div class="posai-table-wrap">
                <table class="posai-table">
                    <thead><tr><th>SKU</th><th>On hand</th><th>Suggest</th><th>Need by</th><th>Confidence</th></tr></thead>
                    <tbody>
                    @foreach (($group['lines'] ?? []) as $line)
                        <tr>
                            <td>{{ $line['code'] }}<div class="posai-suggest-detail">{{ $line['name'] }}</div><div class="posai-suggest-detail">{{ $line['basis'] ?? '' }}</div></td>
                            <td>{{ $line['on_hand'] }}</td>
                            <td>{{ $line['suggested_qty'] }}</td>
                            <td>{{ $line['target_date'] }}<div class="posai-suggest-detail">{{ $line['lead_days'] }}d {{ $line['lead_source'] }}</div></td>
                            <td>{{ $line['confidence'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="posai-suggest-detail">No reorder suggestion with enough history right now.</p>
    @endforelse
    @if (! empty($block['insufficient']))
        <p class="posai-suggest-detail" style="margin-top:.6rem;">Insufficient data (not guessed):</p>
        <ul class="posai-card-list">
            @foreach ($block['insufficient'] as $line)
                <li><span class="trunc">{{ $line['code'] }} {{ $line['name'] }}</span></li>
            @endforeach
        </ul>
    @endif

@elseif ($intelKey === 'collections')
    <p class="posai-suggest-detail">{{ (int) ($block['count'] ?? 0) }} open invoices, ${{ number_format((float) ($block['amount'] ?? 0), 2) }} outstanding. Ranked by days open, past days-to-pay, and amount. The note is a draft — it is not emailed.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>Account</th><th>Invoice</th><th>Score</th><th>Balance</th><th>Draft</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['customer'] }}<div class="posai-suggest-detail">{{ $row['basis'] ?? '' }}</div></td>
                    <td>{{ $row['invoice'] }}<div class="posai-suggest-detail">{{ $row['days'] }} days</div></td>
                    <td>{{ $row['score'] }}</td>
                    <td>${{ number_format((float) $row['balance'], 2) }}</td>
                    <td><div class="posai-draft">{{ $row['draft'] }}</div></td>
                </tr>
            @empty
                <tr><td colspan="5">No open balances to rank.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'match')
    <p class="posai-suggest-detail">{{ $block['basis'] ?? '' }}</p>
    <p class="posai-suggest-detail">Checked {{ (int) ($block['checked'] ?? 0) }}. Within tolerance: {{ (int) ($block['matched'] ?? 0) }}. Exceptions: {{ (int) ($block['exception_count'] ?? 0) }}.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>PO</th><th>Receipt</th><th>Item</th><th>Why</th></tr></thead>
            <tbody>
            @forelse (($block['exceptions'] ?? []) as $row)
                <tr>
                    <td>{{ $row['po'] ?: '—' }}</td>
                    <td>{{ $row['receipt'] ?: '—' }}</td>
                    <td>{{ $row['item'] }}</td>
                    <td>{{ $row['note'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No exceptions in the last 90 days of receipts.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'slow')
    <p class="posai-suggest-detail">In stock, no sale in 90+ days ({{ (int) ($block['count'] ?? 0) }} SKUs, first {{ count($block['rows'] ?? []) }} shown). Discount is a suggestion and is not written to the item price.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>SKU</th><th>On hand</th><th>Days</th><th>Turnover</th><th>Draft discount</th><th>Cost at risk</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['code'] }}<div class="posai-suggest-detail">{{ $row['name'] }}</div></td>
                    <td>{{ $row['on_hand'] }}</td>
                    <td>{{ $row['days_since_sale'] }}</td>
                    <td>{{ $row['turnover'] }}</td>
                    <td>{{ $row['discount_pct'] }}%</td>
                    <td>${{ number_format((float) $row['cost_at_risk'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No slow movers under that rule.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'credit')
    <p class="posai-suggest-detail">Paying slower than their own prior 6 months. The sales order shows this when the account is selected. It does not block the order.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>Account</th><th>Days to pay</th><th>On time</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['customer'] }}<div class="posai-suggest-detail">{{ $row['basis'] }}</div></td>
                    <td>{{ $row['prior_dso'] }} → {{ $row['recent_dso'] }}</td>
                    <td>{{ $row['prior_ontime'] }}% → {{ $row['recent_ontime'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="3">No worsening accounts with enough payment history.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'cash')
    <p class="posai-suggest-detail">{{ $block['basis'] ?? '' }}</p>
    @php $maxCash = max(1, collect($block['weeks'] ?? [])->max('amount') ?: 1); @endphp
    <div class="posai-bars">
        @foreach (($block['weeks'] ?? []) as $week)
            <div class="posai-bar-row">
                <span>{{ $week['label'] }}</span>
                <div class="posai-bar"><span style="width: {{ min(100, round(((float) $week['amount'] / $maxCash) * 100)) }}%"></span></div>
                <strong>${{ number_format((float) $week['amount'], 0) }}</strong>
            </div>
        @endforeach
    </div>
    <p class="posai-suggest-detail" style="margin-top:.45rem;">4-week total ${{ number_format((float) ($block['total'] ?? 0), 2) }}</p>

@elseif ($intelKey === 'reorder_due')
    <p class="posai-suggest-detail">Regular items whose usual gap has elapsed. Based on the last 180 days. No order is created.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>Account</th><th>Item</th><th>Since last</th><th>Usual gap</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['customer'] }}</td>
                    <td>{{ $row['code'] }} {{ $row['name'] }}</td>
                    <td>{{ $row['days_since'] }} days</td>
                    <td>{{ $row['usual_gap'] }} days</td>
                </tr>
            @empty
                <tr><td colspan="4">No regular item is due.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'churn')
    <p class="posai-suggest-detail">Drop-off compares the last 60 days with the prior 60 for that account. Silent accounts have no invoice in {{ (int) ($block['inactive_days'] ?? 60) }} days.</p>
    <h4 style="margin:.6rem 0 .2rem;font-size:.84rem;">Ordering less</h4>
    <ul class="posai-card-list">
        @forelse (($block['dropoff'] ?? []) as $row)
            <li><span class="trunc">{{ $row['customer'] }}</span> <em>${{ number_format((float) $row['prior_revenue'], 0) }} → ${{ number_format((float) $row['recent_revenue'], 0) }}</em></li>
        @empty
            <li>None</li>
        @endforelse
    </ul>
    <h4 style="margin:.6rem 0 .2rem;font-size:.84rem;">Quiet accounts</h4>
    <ul class="posai-card-list">
        @forelse (($block['silent'] ?? []) as $row)
            <li><span class="trunc">{{ $row['customer'] }}</span> <em>{{ $row['days'] }} days</em></li>
        @empty
            <li>None</li>
        @endforelse
    </ul>

@elseif ($intelKey === 'vendors')
    <p class="posai-suggest-detail">Fill rate, on-time vs the PO required date, and price variance. Last 180 days. Does not place an order.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>Vendor</th><th>Score</th><th>Fill</th><th>On time</th><th>Price var.</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['supplier'] }}<div class="posai-suggest-detail">{{ $row['pos'] }} POs</div></td>
                    <td>{{ $row['score'] }}</td>
                    <td>{{ $row['fill_rate'] }}%</td>
                    <td>{{ $row['on_time'] === null ? '—' : $row['on_time'].'%' }}</td>
                    <td>{{ $row['price_var'] === null ? '—' : $row['price_var'].'%' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No vendor receipts in the last 180 days.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'compliance')
    <p class="posai-suggest-detail">{{ (int) ($block['sku_count'] ?? 0) }} regulated SKUs. On-hand cost ${{ number_format((float) ($block['on_hand_cost'] ?? 0), 2) }}. Sold in 30 days: {{ $block['sold_30d_qty'] ?? 0 }}.</p>
    <p class="posai-suggest-detail">{{ $block['license_note'] ?? '' }}</p>
    <ul class="posai-card-list">
        @foreach (($block['samples'] ?? []) as $row)
            <li><span class="trunc">{{ $row['code'] }} {{ $row['name'] }} ({{ $row['type'] }})</span> <em>{{ $row['on_hand'] }}</em></li>
        @endforeach
    </ul>

@elseif ($intelKey === 'anomalies')
    <p class="posai-suggest-detail">Last 21 days. High discount or a price well under list. Flagged for review — the sale was not blocked.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>Date</th><th>Account</th><th>Why</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['customer'] }}<div class="posai-suggest-detail">{{ $row['invoice'] }} {{ $row['item'] }}</div></td>
                    <td>{{ $row['reason'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Nothing outside those ranges.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'digest')
    <p class="posai-draft">{{ $block['text'] ?? '' }}</p>
    <p class="posai-suggest-detail">Turn on <strong>Email morning digest</strong> in Settings to send this to the company email at 7:00am. It stays off until then.</p>

@elseif ($intelKey === 'cross_sell')
    <p class="posai-suggest-detail">Pairs that show up together on invoiced orders in the last 180 days. On a sales order, add-ons also appear when you scan an item. Nothing is added automatically.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>Item</th><th>Often with</th><th>Orders together</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['code_a'] }}<div class="posai-suggest-detail">{{ $row['name_a'] }}</div></td>
                    <td>{{ $row['code_b'] }}<div class="posai-suggest-detail">{{ $row['name_b'] }}</div></td>
                    <td>{{ $row['together'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Not enough shared orders yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'cheaper')
    <p class="posai-suggest-detail">Another vendor on the same SKU has a lower last cost than the default. This does not change who you buy from.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>SKU</th><th>Default</th><th>Cheaper</th><th>Save</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['code'] }}<div class="posai-suggest-detail">{{ $row['name'] }}</div></td>
                    <td>{{ $row['current'] }} ${{ number_format((float) $row['current_cost'], 2) }}</td>
                    <td>{{ $row['cheaper'] }} ${{ number_format((float) $row['cheaper_cost'], 2) }}</td>
                    <td>${{ number_format((float) $row['save'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No SKU has a cheaper alternate vendor on file.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

@elseif ($intelKey === 'prices')
    <p class="posai-suggest-detail">Last cost from the item supplier list. Not a live vendor quote.</p>
    <div class="posai-table-wrap">
        <table class="posai-table">
            <thead><tr><th>SKU</th><th>Supplier</th><th>Last cost</th><th>Avg</th><th>Last received</th></tr></thead>
            <tbody>
            @forelse (($block['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['code'] }}{{ ! empty($row['is_default']) ? ' •' : '' }}<div class="posai-suggest-detail">{{ $row['name'] }}</div></td>
                    <td>{{ $row['supplier'] }}</td>
                    <td>${{ number_format((float) $row['last_cost'], 2) }}</td>
                    <td>${{ number_format((float) $row['avg_cost'], 2) }}</td>
                    <td>{{ $row['last_received'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No supplier costs on file.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif
