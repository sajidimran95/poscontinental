<?php

namespace App\Services\JapsAi;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rule-based POS intelligence. Every figure comes from this company's live data.
 * Suggestions are drafts — nothing is ordered, emailed, or posted from here.
 */
class PosAiIntelligenceService
{
    public const INTENTS = [
        'demand_forecast',
        'slow_movers',
        'collections_risk',
        'credit_risk',
        'cash_flow',
        'cross_sell',
        'reorder_due',
        'churn',
        'three_way',
        'vendor_score',
        'cheaper_suppliers',
        'supplier_prices',
        'compliance',
        'anomalies',
        'digest',
    ];

    private string $question = '';

    public function __construct(public int $companyId) {}

    public static function forCompany(int $companyId): self
    {
        return new self($companyId);
    }

    public function setQuestion(string $question): void
    {
        $this->question = $question;
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey());
        foreach (['demand', 'slow', 'collections', 'credit', 'cash', 'churn', 'match', 'vendors', 'compliance', 'anomalies', 'reorder_due', 'digest', 'cross_sell', 'cheaper', 'prices'] as $key) {
            Cache::forget($this->sectionKey($key));
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return Cache::remember($this->cacheKey(), now()->addMinutes(10), function () {
            return $this->buildSnapshot();
        });
    }

    /** One Intelligence topic only (cached). */
    public function section(string $key): array
    {
        $allowed = ['demand', 'slow', 'collections', 'credit', 'cash', 'churn', 'match', 'vendors', 'compliance', 'anomalies', 'reorder_due', 'digest', 'cross_sell', 'cheaper', 'prices'];
        if (! in_array($key, $allowed, true)) {
            return [];
        }

        $built = Cache::remember($this->sectionKey($key), now()->addMinutes(10), function () use ($key) {
            return $this->buildSection($key);
        });

        return is_array($built) ? $built : [];
    }

    public function intentForKey(string $key): string
    {
        return match ($key) {
            'demand' => 'demand_forecast',
            'slow' => 'slow_movers',
            'collections' => 'collections_risk',
            'credit' => 'credit_risk',
            'cash' => 'cash_flow',
            'cross_sell' => 'cross_sell',
            'reorder_due' => 'reorder_due',
            'churn' => 'churn',
            'match' => 'three_way',
            'vendors' => 'vendor_score',
            'cheaper' => 'cheaper_suppliers',
            'prices' => 'supplier_prices',
            'compliance' => 'compliance',
            'anomalies' => 'anomalies',
            'digest' => 'digest',
            default => '',
        };
    }

    public function keyForIntent(string $intent): string
    {
        foreach (['demand', 'slow', 'collections', 'credit', 'cash', 'cross_sell', 'reorder_due', 'churn', 'match', 'vendors', 'cheaper', 'prices', 'compliance', 'anomalies', 'digest'] as $key) {
            if ($this->intentForKey($key) === $intent) {
                return $key;
            }
        }

        return '';
    }

    /** @return array<string, mixed> */
    public function compactForAi(string $key): array
    {
        $s = [$key => $this->section($key)];
        $chunk = $key === 'digest'
            ? ['text' => (string) data_get($s, 'digest.text', data_get($s, 'digest', '')), 'as_of' => now()->toDateTimeString()]
            : (is_array($s[$key] ?? null) ? $s[$key] : []);
        $chunk['as_of'] = $s['as_of'] ?? null;
        $chunk['topic'] = $key;

        if (isset($chunk['rows']) && is_array($chunk['rows'])) {
            $chunk['rows'] = array_slice($chunk['rows'], 0, 12);
        }
        if (isset($chunk['groups']) && is_array($chunk['groups'])) {
            $chunk['groups'] = array_slice($chunk['groups'], 0, 6);
            foreach ($chunk['groups'] as $i => $group) {
                if (isset($group['lines']) && is_array($group['lines'])) {
                    $chunk['groups'][$i]['lines'] = array_slice($group['lines'], 0, 6);
                }
            }
        }
        if (isset($chunk['exceptions']) && is_array($chunk['exceptions'])) {
            $chunk['exceptions'] = array_slice($chunk['exceptions'], 0, 12);
        }
        if (isset($chunk['dropoff']) && is_array($chunk['dropoff'])) {
            $chunk['dropoff'] = array_slice($chunk['dropoff'], 0, 8);
        }
        if (isset($chunk['silent']) && is_array($chunk['silent'])) {
            $chunk['silent'] = array_slice($chunk['silent'], 0, 8);
        }
        if (isset($chunk['insufficient']) && is_array($chunk['insufficient'])) {
            $chunk['insufficient'] = array_slice($chunk['insufficient'], 0, 6);
        }

        return $chunk;
    }

    /**
     * Spreadsheet for the open Intelligence report (same Excel export as POs / item lists).
     *
     * @return array{filename: string, title: string, headers: list<string>, rows: list<list<mixed>>}
     */
    public function exportSheet(string $key): array
    {
        $block = $this->section($key);
        $s = [$key => $block, 'digest' => (string) ($block['text'] ?? '')];
        $stamp = now()->format('Y-m-d');

        return match ($key) {
            'demand' => $this->sheetDemand($s, $stamp),
            'cheaper' => [
                'filename' => 'pos-ai-cheaper-suppliers-'.$stamp.'.xlsx',
                'title' => 'Cheaper suppliers',
                'headers' => ['SKU', 'Description', 'Current vendor', 'Current cost', 'Cheaper vendor', 'Cheaper cost', 'Save', 'Basis'],
                'rows' => collect((array) data_get($s, 'cheaper.rows', []))->map(fn ($r) => [
                    $r['code'] ?? '', $r['name'] ?? '', $r['current'] ?? '', $r['current_cost'] ?? '',
                    $r['cheaper'] ?? '', $r['cheaper_cost'] ?? '', $r['save'] ?? '', $r['basis'] ?? '',
                ])->all(),
            ],
            'prices' => [
                'filename' => 'pos-ai-supplier-prices-'.$stamp.'.xlsx',
                'title' => 'Supplier prices',
                'headers' => ['SKU', 'Description', 'Supplier', 'Vendor SKU', 'Last cost', 'Avg cost', 'Last received', 'Default'],
                'rows' => collect((array) data_get($s, 'prices.rows', []))->map(fn ($r) => [
                    $r['code'] ?? '', $r['name'] ?? '', $r['supplier'] ?? '', $r['vendor_sku'] ?? '',
                    $r['last_cost'] ?? '', $r['avg_cost'] ?? '', $r['last_received'] ?? '',
                    ! empty($r['is_default']) ? 'Yes' : 'No',
                ])->all(),
            ],
            'collections' => [
                'filename' => 'pos-ai-collections-'.$stamp.'.xlsx',
                'title' => 'Collections risk',
                'headers' => ['Customer', 'Invoice', 'Date', 'Days', 'Balance', 'Score', 'Draft', 'Basis'],
                'rows' => collect((array) data_get($s, 'collections.rows', []))->map(fn ($r) => [
                    $r['customer'] ?? '', $r['invoice'] ?? '', $r['date'] ?? '', $r['days'] ?? '',
                    $r['balance'] ?? '', $r['score'] ?? '', $r['draft'] ?? '', $r['basis'] ?? '',
                ])->all(),
            ],
            'slow' => [
                'filename' => 'pos-ai-slow-movers-'.$stamp.'.xlsx',
                'title' => 'Slow movers',
                'headers' => ['SKU', 'Description', 'On hand', 'Days since sale', 'Turnover', 'Draft discount %', 'Cost at risk'],
                'rows' => collect((array) data_get($s, 'slow.rows', []))->map(fn ($r) => [
                    $r['code'] ?? '', $r['name'] ?? '', $r['on_hand'] ?? '', $r['days_since_sale'] ?? '',
                    $r['turnover'] ?? '', $r['discount_pct'] ?? '', $r['cost_at_risk'] ?? '',
                ])->all(),
            ],
            'vendors' => [
                'filename' => 'pos-ai-vendor-score-'.$stamp.'.xlsx',
                'title' => 'Vendor score',
                'headers' => ['Vendor', 'Score', 'Fill %', 'On time %', 'Price variance %', 'POs'],
                'rows' => collect((array) data_get($s, 'vendors.rows', []))->map(fn ($r) => [
                    $r['supplier'] ?? '', $r['score'] ?? '', $r['fill_rate'] ?? '', $r['on_time'] ?? '',
                    $r['price_var'] ?? '', $r['pos'] ?? '',
                ])->all(),
            ],
            'match' => [
                'filename' => 'pos-ai-3way-match-'.$stamp.'.xlsx',
                'title' => '3-way match',
                'headers' => ['PO', 'Receipt', 'Item', 'Note'],
                'rows' => collect((array) data_get($s, 'match.exceptions', []))->map(fn ($r) => [
                    $r['po'] ?? '', $r['receipt'] ?? '', $r['item'] ?? '', $r['note'] ?? '',
                ])->all(),
            ],
            default => [
                'filename' => 'pos-ai-'.$key.'-'.$stamp.'.xlsx',
                'title' => 'POS AI',
                'headers' => ['Field', 'Value'],
                'rows' => [['report', $key], ['as_of', (string) data_get($s, 'as_of', '')]],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array{filename: string, title: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function sheetDemand(array $s, string $stamp): array
    {
        $rows = [];
        foreach ((array) data_get($s, 'demand.groups', []) as $group) {
            foreach ((array) ($group['lines'] ?? []) as $line) {
                $rows[] = [
                    $group['supplier_name'] ?? '',
                    $line['code'] ?? '',
                    $line['name'] ?? '',
                    $line['on_hand'] ?? '',
                    $line['suggested_qty'] ?? '',
                    $line['target_date'] ?? '',
                    $line['confidence'] ?? '',
                    $line['lead_days'] ?? '',
                    $line['basis'] ?? '',
                ];
            }
        }

        return [
            'filename' => 'pos-ai-forecast-draft-po-'.$stamp.'.xlsx',
            'title' => 'Forecast draft PO',
            'headers' => ['Supplier', 'SKU', 'Description', 'On hand', 'Suggest qty', 'Need by', 'Confidence', 'Lead days', 'Basis'],
            'rows' => $rows,
        ];
    }

    /**
     * Short tiles for the existing Suggested Actions list.
     *
     * @return list<array{priority: string, title: string, detail: string}>
     */
    public function actionTiles(): array
    {
        return [];
    }

    public function digestText(): string
    {
        return (string) data_get($this->section('digest'), 'text', '');
    }

    public function reply(string $intent): string
    {
        $key = $this->keyForIntent($intent);
        $block = $key !== '' ? $this->section($key) : [];
        $s = [
            $key => $block,
            'digest' => (string) ($block['text'] ?? ''),
        ];

        return match ($intent) {
            'demand_forecast' => $this->replyDemand($s),
            'slow_movers' => $this->replySlow($s),
            'collections_risk' => $this->replyCollections($s),
            'credit_risk' => $this->replyCredit($s),
            'cash_flow' => $this->replyCash($s),
            'cross_sell' => $this->replyCrossSell(),
            'reorder_due' => $this->replyReorderDue($s),
            'churn' => $this->replyChurn($s),
            'three_way' => $this->replyMatch($s),
            'vendor_score' => $this->replyVendors($s),
            'cheaper_suppliers' => $this->replyCheaper($s),
            'supplier_prices' => $this->replyPrices($s),
            'compliance' => $this->replyCompliance($s),
            'anomalies' => $this->replyAnomalies($s),
            'digest' => $this->replyDigest($s),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $matched
     */
    public function scannedInvoiceNote(array $matched, mixed $createdPo = null): string
    {
        $tolPct = $this->tolerancePercent();
        $rate = $tolPct / 100;
        $lines = array_values(array_filter(
            (array) ($matched['lines'] ?? []),
            fn ($line) => (int) ($line['item_id'] ?? 0) > 0
        ));

        if ($lines === []) {
            return "### 3-way match\nNo catalog matches yet, so this invoice was not compared to a PO or receipt. Match the SKUs, then check again. Nothing is auto-approved.";
        }

        $poId = is_object($createdPo) ? (int) ($createdPo->id ?? 0) : 0;
        $poLinesQuery = DB::table('purchase_order_lines as pol')
            ->join('purchase_orders as po', 'po.id', '=', 'pol.purchase_order_id')
            ->where('po.company_id', $this->companyId);

        if ($poId > 0) {
            $poLinesQuery->where('po.id', $poId);
        } else {
            $itemIds = collect($lines)->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
            $closed = ['Received', 'Closed', 'Cancelled', 'Void', 'Complete', 'Completed'];
            $poLinesQuery->whereIn('pol.item_id', $itemIds)->whereNotIn('po.status', $closed);
        }

        $poLines = $poLinesQuery
            ->orderBy('pol.line_no')
            ->get(['pol.id', 'pol.item_id', 'pol.qty_ordered', 'pol.qty_received', 'pol.unit_cost', 'po.po_number']);

        $queues = [];
        foreach ($poLines as $row) {
            $queues[(int) $row->item_id][] = $row;
        }

        $ready = 0;
        $bad = 0;
        $notes = [];
        foreach ($lines as $line) {
            $itemId = (int) $line['item_id'];
            $po = null;
            if (! empty($queues[$itemId])) {
                $po = array_shift($queues[$itemId]);
            }
            $code = (string) ($line['item_code'] ?: $line['sku'] ?: 'Item');
            if (! $po) {
                $bad++;
                $notes[] = "- **{$code}** — invoice line was not written to the PO. Needs a human review.";
                continue;
            }
            $qtyOk = self::withinTolerance((float) $po->qty_ordered, (float) ($line['quantity'] ?? 0), $rate);
            $costOk = self::withinTolerance((float) $po->unit_cost, (float) ($line['unit_price'] ?? 0), $rate)
                || ((float) ($line['unit_price'] ?? 0) <= 0 && (float) $po->unit_cost <= 0);
            if ($qtyOk && $costOk) {
                $ready++;
                $notes[] = "- **{$code}** — PO {$po->po_number} qty ".number_format((float) $po->qty_ordered, 2)
                    .' @ $'.number_format((float) $po->unit_cost, 2)
                    .' matches this invoice line.';
            } else {
                $bad++;
                $notes[] = "- **{$code}** — exception vs PO {$po->po_number}. Invoice qty ".number_format((float) ($line['quantity'] ?? 0), 2)
                    .' @ $'.number_format((float) ($line['unit_price'] ?? 0), 2)
                    .' vs PO '.number_format((float) $po->qty_ordered, 2).' @ $'.number_format((float) $po->unit_cost, 2).'.';
            }
        }

        return "### Invoice vs PO\n"
            ."Tolerance **{$tolPct}%**. **{$ready}** line(s) match the invoice amount on the PO, **{$bad}** exception(s). Nothing is auto-approved.\n\n"
            .implode("\n", array_slice($notes, 0, 12));
    }

    /** @return array<string, mixed>|null */
    public function draftReorderPayload(?int $supplierId): ?array
    {
        $groups = data_get($this->section('demand'), 'groups', []);
        if (! is_array($groups)) {
            return null;
        }

        $group = null;
        foreach ($groups as $candidate) {
            if ((int) ($supplierId ?? 0) === (int) ($candidate['supplier_id'] ?? 0)) {
                $group = $candidate;
                break;
            }
        }
        if (! is_array($group) || empty($group['lines'])) {
            return null;
        }

        $lines = [];
        foreach ($group['lines'] as $line) {
            if (($line['confidence'] ?? '') === 'insufficient' || (float) ($line['suggested_qty'] ?? 0) <= 0) {
                continue;
            }
            $lines[] = [
                'item_id' => $line['item_id'],
                'item_code' => $line['code'],
                'description' => $line['name'],
                'uom' => $line['uom'] ?? '',
                'qty_ordered' => $line['suggested_qty'],
                'unit_cost' => $line['unit_cost'] ?? 0,
                'list_price' => $line['list_price'] ?? 0,
            ];
        }
        if ($lines === []) {
            return null;
        }

        $dates = array_values(array_filter(array_column($group['lines'], 'target_date')));
        sort($dates);

        return [
            'supplier_id' => $group['supplier_id'] ?? null,
            'required_date' => $dates[0] ?? now()->addDays(7)->toDateString(),
            'comments' => 'POS AI draft reorder. Based on the last 90 days of invoiced sales and vendor lead time. Review quantities and cost before saving. This draft was not sent to the vendor.',
            'lines' => $lines,
        ];
    }

    /** @return array<string, mixed>|null */
    public function creditRiskForCustomer(int $customerId): ?array
    {
        if ($customerId <= 0) {
            return null;
        }

        return Cache::remember('pos-ai-credit:'.$this->companyId.':'.$customerId, now()->addMinutes(15), function () use ($customerId) {
            $customer = Customer::query()
                ->where('company_id', $this->companyId)
                ->find($customerId, ['id', 'company_name', 'contact']);
            if (! $customer) {
                return null;
            }

            $recentStart = now()->subMonths(6)->toDateString();
            $priorStart = now()->subMonths(12)->toDateString();
            $recent = $this->paymentBehavior($customerId, $recentStart, now()->toDateString());
            $prior = $this->paymentBehavior($customerId, $priorStart, now()->subMonths(6)->subDay()->toDateString());

            if ($recent['samples'] < 3 || $prior['samples'] < 3) {
                return [
                    'flagged' => false,
                    'reason' => 'insufficient',
                    'customer' => $customer->company_name ?: ($customer->contact ?: 'Customer'),
                ];
            }

            $dsoUp = $recent['avg_days'] >= $prior['avg_days'] + 7;
            $onTimeDrop = $prior['on_time_rate'] - $recent['on_time_rate'] >= 0.15;
            if (! $dsoUp && ! $onTimeDrop) {
                return [
                    'flagged' => false,
                    'reason' => 'stable',
                    'customer' => $customer->company_name ?: 'Customer',
                ];
            }

            return [
                'flagged' => true,
                'customer' => $customer->company_name ?: ($customer->contact ?: 'Customer'),
                'recent_dso' => $recent['avg_days'],
                'prior_dso' => $prior['avg_days'],
                'recent_ontime' => $recent['on_time_rate'],
                'prior_ontime' => $prior['on_time_rate'],
                'basis' => 'Based on paid invoices in the last 6 months versus the prior 6 months.',
            ];
        });
    }

    /** @return list<array{item_id: int, code: string, name: string, together: int}> */
    public function crossSellForItem(int $itemId, int $limit = 3): array
    {
        if ($itemId <= 0) {
            return [];
        }

        return Cache::remember('pos-ai-xsell:'.$this->companyId.':'.$itemId, now()->addMinutes(30), function () use ($itemId, $limit) {
            $from = now()->subDays(180)->toDateString();
            $rows = DB::table('sales_order_lines as a')
                ->join('sales_order_lines as b', function ($join) {
                    $join->on('b.sales_order_id', '=', 'a.sales_order_id')
                        ->whereColumn('b.item_id', '!=', 'a.item_id');
                })
                ->join('sales_orders as so', 'so.id', '=', 'a.sales_order_id')
                ->join('invoices as inv', 'inv.sales_order_id', '=', 'so.id')
                ->where('so.company_id', $this->companyId)
                ->where('a.item_id', $itemId)
                ->where('inv.invoice_date', '>=', $from)
                ->whereRaw("UPPER(inv.status) NOT IN ('VOID', 'CANCELLED')")
                ->whereNotNull('b.item_id')
                ->groupBy('b.item_id')
                ->orderByDesc('together')
                ->limit($limit)
                ->selectRaw('b.item_id, COUNT(DISTINCT a.sales_order_id) as together')
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $items = Item::query()
                ->where('company_id', $this->companyId)
                ->whereIn('id', $rows->pluck('item_id'))
                ->get(['id', 'item_code', 'description'])
                ->keyBy('id');

            $out = [];
            foreach ($rows as $row) {
                $item = $items->get((int) $row->item_id);
                if (! $item) {
                    continue;
                }
                $out[] = [
                    'item_id' => (int) $item->id,
                    'code' => (string) $item->item_code,
                    'name' => (string) $item->description,
                    'together' => (int) $row->together,
                ];
            }

            return $out;
        });
    }

    /** @return list<array{code: string, name: string, days_since: int, usual_gap: int}> */
    public function reorderDueForCustomer(int $customerId, int $limit = 3): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $rows = $this->customerItemCadence($customerId);
        $due = [];
        foreach ($rows as $row) {
            if ($row['times'] < 3 || $row['gap'] < 7) {
                continue;
            }
            if ($row['days_since'] >= (int) floor($row['gap'] * 0.85) && $row['days_since'] <= (int) ceil($row['gap'] * 2.5)) {
                $due[] = [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'days_since' => $row['days_since'],
                    'usual_gap' => $row['gap'],
                ];
            }
        }

        usort($due, fn ($a, $b) => $b['days_since'] <=> $a['days_since']);

        return array_slice($due, 0, $limit);
    }

    public static function collectionScore(int $daysOverdue, ?float $avgDaysToPay, float $balance, float $balanceScale): int
    {
        $age = min(1, max(0, $daysOverdue) / 90);
        $late = $avgDaysToPay === null ? 0.35 : min(1, max(0, $avgDaysToPay) / 60);
        $size = $balanceScale > 0 ? min(1, max(0, $balance) / $balanceScale) : 0;
        $score = (int) round(100 * ((0.45 * $age) + (0.35 * $late) + (0.20 * $size)));

        return max(0, min(100, $score));
    }

    public static function forecastConfidence(int $saleDays, bool $leadKnown): string
    {
        if ($saleDays < 3) {
            return 'insufficient';
        }
        if ($saleDays < 8) {
            return 'low';
        }

        return $leadKnown ? 'high' : 'medium';
    }

    public static function suggestedQty(float $daily, float $onHand, float $onOrder, int $leadDays, int $coverExtra = 14): float
    {
        $leadDays = max(1, $leadDays);
        $target = $daily * ($leadDays + $coverExtra);
        $qty = $target - $onHand - $onOrder;

        return $qty > 0.0001 ? (float) ceil($qty) : 0.0;
    }

    public static function withinTolerance(float $left, float $right, float $rate): bool
    {
        if (abs($left) < 0.0001 && abs($right) < 0.0001) {
            return true;
        }
        $base = max(abs($left), 0.0001);

        return abs($left - $right) / $base <= $rate + 0.0000001;
    }

    /** @return array<string, mixed> */
    private function buildSnapshot(): array
    {
        $demand = $this->safe(fn () => $this->demandForecast(), $this->emptyDemand());
        $slow = $this->safe(fn () => $this->slowMovers(), ['threshold_days' => 90, 'count' => 0, 'rows' => []]);
        $collections = $this->safe(fn () => $this->collectionsRisk(), ['count' => 0, 'amount' => 0, 'rows' => []]);
        $credit = $this->safe(fn () => $this->creditRiskList(), ['count' => 0, 'rows' => []]);
        $cash = $this->safe(fn () => $this->cashFlow(), ['weeks' => [], 'total' => 0, 'assumed_count' => 0, 'basis' => '']);
        $churn = $this->safe(fn () => $this->churnReport(60), $this->emptyChurn());
        $match = $this->safe(fn () => $this->threeWayMatch(), ['tolerance_pct' => $this->tolerancePercent(), 'checked' => 0, 'matched' => 0, 'exception_count' => 0, 'exceptions' => []]);
        $vendors = $this->safe(fn () => $this->vendorScorecard(), ['rows' => []]);
        $compliance = $this->safe(fn () => $this->complianceReport(), ['sku_count' => 0, 'on_hand_cost' => 0, 'sold_30d_qty' => 0, 'license_note' => '', 'samples' => []]);
        $anomalies = $this->safe(fn () => $this->anomalyReport(), ['count' => 0, 'rows' => []]);
        $reorderDue = $this->safe(fn () => $this->reorderDueCompany(), ['count' => 0, 'rows' => []]);
        $crossSell = $this->safe(fn () => $this->crossSellPairs(), ['count' => 0, 'rows' => []]);
        $cheaper = $this->safe(fn () => $this->cheaperSuppliers(), ['count' => 0, 'rows' => []]);
        $prices = $this->safe(fn () => $this->supplierPriceTable(), ['count' => 0, 'rows' => []]);

        $snapshot = [
            'as_of' => now()->timezone((string) (config('app.timezone') ?: 'UTC'))->format('Y-m-d H:i'),
            'basis' => 'Live company data. Forecasts use the last 90 days of invoiced sales unless a section says otherwise.',
            'demand' => $demand,
            'slow' => $slow,
            'collections' => $collections,
            'credit' => $credit,
            'cash' => $cash,
            'churn' => $churn,
            'match' => $match,
            'vendors' => $vendors,
            'compliance' => $compliance,
            'anomalies' => $anomalies,
            'reorder_due' => $reorderDue,
            'cross_sell' => $crossSell,
            'cheaper' => $cheaper,
            'prices' => $prices,
        ];
        $snapshot['digest'] = $this->composeDigest($snapshot);

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function demandForecast(): array
    {
        $from90 = now()->subDays(90)->toDateString();
        $from28 = now()->subDays(28)->toDateString();

        $sales = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->joinSub($this->invoiceDates($from90), 'inv', 'inv.sales_order_id', '=', 'so.id')
            ->where('so.company_id', $this->companyId)
            ->whereNotNull('sol.item_id')
            ->groupBy('sol.item_id')
            ->selectRaw(
                'sol.item_id, SUM(sol.qty_ordered) as qty_90, COUNT(DISTINCT inv.invoice_date) as sale_days, SUM(CASE WHEN inv.invoice_date >= ? THEN sol.qty_ordered ELSE 0 END) as qty_28',
                [$from28]
            )
            ->get()
            ->keyBy('item_id');

        if ($sales->isEmpty()) {
            return $this->emptyDemand();
        }

        $ids = $sales->keys()->map(fn ($id) => (int) $id)->all();
        $items = Item::query()
            ->where('company_id', $this->companyId)
            ->where('is_inactive', false)
            ->where(function ($q) {
                $q->where('can_order', true)->orWhereNull('can_order');
            })
            ->whereIn('id', $ids)
            ->get([
                'id', 'item_code', 'description', 'unit_of_measure', 'quantity_in_stock', 'on_order_qty',
                'reorder_point', 'restock_level', 'lead_time_days', 'current_cost', 'last_cost', 'list_price', 'created_at',
            ]);

        $suppliers = $this->defaultSuppliers($ids);
        $leadHistory = $this->leadTimeFromPos($ids);

        $suggestions = [];
        $insufficient = [];
        $insufficientCount = 0;
        foreach ($items as $item) {
            $stat = $sales->get($item->id);
            if (! $stat) {
                continue;
            }
            $saleDays = (int) $stat->sale_days;
            $qty90 = (float) $stat->qty_90;
            $qty28 = (float) $stat->qty_28;
            $daily90 = $qty90 / 90;
            $daily28 = $qty28 / 28;
            $daily = $qty28 > 0 ? ((0.6 * $daily28) + (0.4 * $daily90)) : $daily90;

            $supplier = $suppliers[(int) $item->id] ?? null;
            $historyLead = $leadHistory[(int) $item->id] ?? null;
            $leadKnown = false;
            $leadSource = 'default 7 days (no lead time on file)';
            $lead = 7;
            if ($historyLead) {
                $lead = max(1, (int) round($historyLead));
                $leadKnown = true;
                $leadSource = 'PO to receipt history';
            } elseif ($supplier && (int) $supplier->lead_time > 0) {
                $lead = (int) $supplier->lead_time;
                $leadKnown = true;
                $leadSource = 'supplier lead time';
            } elseif ((int) $item->lead_time_days > 0) {
                $lead = (int) $item->lead_time_days;
                $leadKnown = true;
                $leadSource = 'item lead time';
            }

            $confidence = self::forecastConfidence($saleDays, $leadKnown);
            $onHand = (float) $item->quantity_in_stock;
            $onOrder = (float) $item->on_order_qty;
            $daysCover = $daily > 0 ? ($onHand + $onOrder) / $daily : 999;
            $reorder = (float) $item->reorder_point;
            $urgent = $onHand <= 0 || ($reorder > 0 && $onHand <= $reorder) || $daysCover <= ($lead + 7);

            $row = [
                'item_id' => (int) $item->id,
                'code' => (string) $item->item_code,
                'name' => (string) $item->description,
                'uom' => (string) ($item->unit_of_measure ?? ''),
                'on_hand' => $onHand,
                'on_order' => $onOrder,
                'daily' => round($daily, 2),
                'lead_days' => $lead,
                'lead_source' => $leadSource,
                'confidence' => $confidence,
                'sale_days' => $saleDays,
                'unit_cost' => (float) ($item->current_cost ?: $item->last_cost ?: 0),
                'list_price' => (float) ($item->list_price ?: 0),
                'supplier_id' => $supplier ? (int) $supplier->supplier_id : null,
                'supplier_name' => $supplier->supplier_name ?? 'No preferred supplier',
            ];

            if ($confidence === 'insufficient') {
                $row['confidence'] = 'insufficient';
                $row['reason'] = 'Fewer than 3 sale days in the last 90 days — not enough history to suggest a quantity.';
                $insufficientCount++;
                if (count($insufficient) < 8) {
                    $insufficient[] = $row;
                }
                continue;
            }

            if (! $urgent) {
                continue;
            }

            $target = max($daily * ($lead + 14), (float) $item->restock_level);
            $qty = $target - $onHand - $onOrder;
            $suggested = $qty > 0.0001 ? (float) ceil($qty) : 0.0;
            if ($suggested <= 0) {
                continue;
            }

            $row['suggested_qty'] = $suggested;
            $row['target_date'] = now()->addDays($lead)->toDateString();
            $row['basis'] = 'Based on last 90 days of invoiced sales ('.$saleDays.' sale days, '.round($qty90, 2).' units). Lead time '.$lead.' days from '.$leadSource.'.';
            $suggestions[] = $row;
        }

        usort($suggestions, fn ($a, $b) => ($a['on_hand'] <=> $b['on_hand']) ?: ($b['daily'] <=> $a['daily']));
        $suggestedCount = count($suggestions);
        $suggestions = array_slice($suggestions, 0, 40);

        $groups = [];
        foreach ($suggestions as $row) {
            $key = (string) ($row['supplier_id'] ?? 0);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'supplier_id' => $row['supplier_id'],
                    'supplier_name' => $row['supplier_name'],
                    'lines' => [],
                ];
            }
            $groups[$key]['lines'][] = $row;
        }

        return [
            'window_days' => 90,
            'suggested_count' => $suggestedCount,
            'shown_count' => count($suggestions),
            'insufficient_count' => $insufficientCount,
            'groups' => array_values($groups),
            'insufficient' => $insufficient,
            'method' => 'Rule-based from the last 90 days of invoiced sales (not OpenAI). Create draft PO opens a purchase order for review — it is not sent to the vendor.',
        ];
    }

    /** @return array<string, mixed> */
    private function slowMovers(): array
    {
        $cutoff = now()->subDays(90)->toDateString();
        $createdBefore = now()->subDays(45);
        $base = Item::query()
            ->where('company_id', $this->companyId)
            ->where('is_inactive', false)
            ->where('quantity_in_stock', '>', 0)
            ->where(function ($q) use ($cutoff, $createdBefore) {
                $q->where('last_sold_at', '<', $cutoff)
                    ->orWhere(function ($q2) use ($createdBefore) {
                        $q2->whereNull('last_sold_at')->where('created_at', '<', $createdBefore);
                    });
            });
        $slowCount = (clone $base)->count();
        $items = (clone $base)
            ->orderBy('last_sold_at')
            ->limit(25)
            ->get(['id', 'item_code', 'description', 'quantity_in_stock', 'last_sold_at', 'current_cost', 'last_cost', 'created_at']);

        $sold = $this->qtySoldSince($items->pluck('id')->all(), now()->subDays(365)->toDateString());
        $rows = [];
        foreach ($items as $item) {
            $days = $item->last_sold_at
                ? (int) $item->last_sold_at->diffInDays(now())
                : (int) $item->created_at->diffInDays(now());
            $onHand = (float) $item->quantity_in_stock;
            $qtySold = (float) ($sold[(int) $item->id] ?? 0);
            $turnover = $onHand > 0 ? round($qtySold / $onHand, 2) : 0;
            $discount = $days >= 365 ? 25 : ($days >= 180 ? 15 : 10);
            $cost = (float) ($item->current_cost ?: $item->last_cost ?: 0);
            $rows[] = [
                'code' => (string) $item->item_code,
                'name' => (string) $item->description,
                'on_hand' => $onHand,
                'days_since_sale' => $days,
                'turnover' => $turnover,
                'discount_pct' => $discount,
                'cost_at_risk' => round($onHand * $cost, 2),
                'basis' => 'Based on on-hand quantity and last sold date. Units sold in 365 days: '.round($qtySold, 2).'. Discount is a draft only.',
            ];
        }

        return [
            'threshold_days' => 90,
            'count' => $slowCount,
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function collectionsRisk(): array
    {
        $open = DB::select(
            "
            SELECT i.id, i.invoice_number, i.invoice_date, i.customer_id, cu.company_name, cu.contact, cu.email,
                   (i.invoice_total - COALESCE(p.paid, 0) - COALESCE(c.cred, 0)) AS bal
            FROM invoices i
            LEFT JOIN customers cu ON cu.id = i.customer_id
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS paid FROM invoice_payments GROUP BY invoice_id
            ) p ON p.invoice_id = i.id
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS cred FROM invoice_credits GROUP BY invoice_id
            ) c ON c.invoice_id = i.id
            WHERE i.company_id = ?
              AND i.status = 'NOT PAID'
            ",
            [$this->companyId]
        );

        $open = array_values(array_filter($open, fn ($row) => (float) $row->bal > 0.01));
        if ($open === []) {
            return ['count' => 0, 'amount' => 0, 'rows' => []];
        }

        $balances = array_map(fn ($row) => (float) $row->bal, $open);
        sort($balances);
        $scale = $balances[(int) floor((count($balances) - 1) * 0.9)] ?? 1;
        if ($scale <= 0) {
            $scale = 1;
        }

        $customerIds = collect($open)->pluck('customer_id')->filter()->unique()->map(fn ($id) => (int) $id)->all();
        $behavior = $this->paymentBehaviorForCustomers($customerIds, now()->subYear()->toDateString(), now()->toDateString());

        $rows = [];
        foreach ($open as $row) {
            $days = $row->invoice_date
                ? (int) Carbon::parse($row->invoice_date)->startOfDay()->diffInDays(now()->startOfDay())
                : 0;
            $avg = $behavior[(int) $row->customer_id]['avg_days'] ?? null;
            $samples = $behavior[(int) $row->customer_id]['samples'] ?? 0;
            $score = self::collectionScore($days, $samples >= 2 ? $avg : null, (float) $row->bal, (float) $scale);
            $name = trim((string) ($row->company_name ?: $row->contact ?: 'Customer'));
            $rows[] = [
                'customer_id' => (int) ($row->customer_id ?? 0),
                'customer' => $name,
                'email' => trim((string) ($row->email ?? '')),
                'invoice' => (string) $row->invoice_number,
                'date' => $row->invoice_date ? substr((string) $row->invoice_date, 0, 10) : null,
                'days' => $days,
                'balance' => round((float) $row->bal, 2),
                'score' => $score,
                'avg_days_to_pay' => $samples >= 2 ? (int) round((float) $avg) : null,
                'draft' => $this->collectionDraft($name, (string) $row->invoice_number, (float) $row->bal, $days),
                'basis' => $samples >= 2
                    ? 'Based on '.$days.' days open and this account\'s average '.$this->moneyDays($avg).' days to pay over the last year.'
                    : 'Based on '.$days.' days open. Not enough payment history to score lateness, so age and amount carry the rank.',
            ];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $b['balance'] <=> $a['balance']);
        $top = array_slice($rows, 0, 15);

        return [
            'count' => count($rows),
            'amount' => round(array_sum(array_column($rows, 'balance')), 2),
            'rows' => $top,
        ];
    }

    /** @return array<string, mixed> */
    private function creditRiskList(): array
    {
        $recentStart = now()->subMonths(6)->toDateString();
        $priorStart = now()->subMonths(12)->toDateString();
        $recent = $this->paymentBehaviorForCustomers([], $recentStart, now()->toDateString());
        $prior = $this->paymentBehaviorForCustomers([], $priorStart, now()->subMonths(6)->subDay()->toDateString());

        $ids = array_values(array_unique(array_merge(array_keys($recent), array_keys($prior))));
        $names = Customer::query()
            ->where('company_id', $this->companyId)
            ->whereIn('id', $ids)
            ->pluck('company_name', 'id');

        $rows = [];
        foreach ($ids as $id) {
            $r = $recent[$id] ?? null;
            $p = $prior[$id] ?? null;
            if (! $r || ! $p || $r['samples'] < 3 || $p['samples'] < 3) {
                continue;
            }
            $dsoUp = $r['avg_days'] >= $p['avg_days'] + 7;
            $onTimeDrop = $p['on_time_rate'] - $r['on_time_rate'] >= 0.15;
            if (! $dsoUp && ! $onTimeDrop) {
                continue;
            }
            $rows[] = [
                'customer_id' => $id,
                'customer' => $names[$id] ?? ('Customer #'.$id),
                'recent_dso' => (int) round($r['avg_days']),
                'prior_dso' => (int) round($p['avg_days']),
                'recent_ontime' => round($r['on_time_rate'] * 100),
                'prior_ontime' => round($p['on_time_rate'] * 100),
                'basis' => 'Based on paid invoices in the last 6 months versus the prior 6 months.',
            ];
        }

        usort($rows, fn ($a, $b) => ($b['recent_dso'] - $b['prior_dso']) <=> ($a['recent_dso'] - $a['prior_dso']));

        return ['count' => count($rows), 'rows' => array_slice($rows, 0, 15)];
    }

    /** @return array<string, mixed> */
    private function cashFlow(): array
    {
        $open = DB::select(
            "
            SELECT i.customer_id, i.invoice_date,
                   (i.invoice_total - COALESCE(p.paid, 0) - COALESCE(c.cred, 0)) AS bal
            FROM invoices i
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS paid FROM invoice_payments GROUP BY invoice_id
            ) p ON p.invoice_id = i.id
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS cred FROM invoice_credits GROUP BY invoice_id
            ) c ON c.invoice_id = i.id
            WHERE i.company_id = ?
              AND i.status = 'NOT PAID'
            ",
            [$this->companyId]
        );
        $open = array_values(array_filter($open, fn ($row) => (float) $row->bal > 0.01));
        $behavior = $this->paymentBehaviorForCustomers(
            collect($open)->pluck('customer_id')->filter()->unique()->map(fn ($id) => (int) $id)->all(),
            now()->subYear()->toDateString(),
            now()->toDateString()
        );

        $weeks = [];
        for ($w = 0; $w < 4; $w++) {
            $start = now()->startOfDay()->addDays($w * 7);
            $label = $w === 3
                ? $start->format('M j').' and later'
                : $start->format('M j').' – '.$start->copy()->addDays(6)->format('M j');
            $weeks[] = [
                'label' => $label,
                'amount' => 0.0,
                'invoices' => 0,
            ];
        }

        $assumed = 0;
        $customersAssumed = [];
        foreach ($open as $row) {
            $cid = (int) ($row->customer_id ?? 0);
            $stats = $behavior[$cid] ?? null;
            $useDefault = ! $stats || $stats['samples'] < 3;
            $typical = $useDefault ? 30 : (int) round($stats['avg_days']);
            if ($useDefault) {
                $customersAssumed[$cid] = true;
            }
            $expected = $row->invoice_date
                ? Carbon::parse($row->invoice_date)->startOfDay()->addDays(max(0, $typical))
                : now()->addDays($typical);
            if ($expected->lt(now()->startOfDay())) {
                $expected = now()->startOfDay();
            }
            $dayOffset = (int) now()->startOfDay()->diffInDays($expected);
            $bucket = (int) min(3, floor($dayOffset / 7));
            $weeks[$bucket]['amount'] += (float) $row->bal;
            $weeks[$bucket]['invoices']++;
        }
        $assumed = count($customersAssumed);
        foreach ($weeks as &$week) {
            $week['amount'] = round($week['amount'], 2);
        }
        unset($week);

        return [
            'weeks' => $weeks,
            'total' => round(array_sum(array_column($weeks, 'amount')), 2),
            'assumed_count' => $assumed,
            'basis' => 'Based on open NOT PAID balances and each customer\'s typical days to pay over the last year. Accounts with fewer than 3 paid invoices use a 30-day assumption ('.$assumed.' accounts).',
        ];
    }

    /** @return array<string, mixed> */
    private function churnReport(int $inactiveDays): array
    {
        $inactiveDays = max(7, min(365, $inactiveDays));
        $recentStart = now()->subDays(60)->toDateString();
        $priorStart = now()->subDays(120)->toDateString();
        $rows = DB::table('invoices')
            ->where('company_id', $this->companyId)
            ->whereDate('invoice_date', '>=', $priorStart)
            ->whereRaw("UPPER(status) NOT IN ('VOID', 'CANCELLED')")
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw(
                'customer_id,
                SUM(CASE WHEN invoice_date >= ? THEN 1 ELSE 0 END) as orders_recent,
                SUM(CASE WHEN invoice_date >= ? THEN invoice_total ELSE 0 END) as rev_recent,
                SUM(CASE WHEN invoice_date < ? THEN 1 ELSE 0 END) as orders_prior,
                SUM(CASE WHEN invoice_date < ? THEN invoice_total ELSE 0 END) as rev_prior,
                MAX(invoice_date) as last_date',
                [$recentStart, $recentStart, $recentStart, $recentStart]
            )
            ->get();

        $silentCutoff = now()->subDays($inactiveDays)->toDateString();
        $silentLookback = now()->subDays($inactiveDays + 180)->toDateString();
        $silentRows = DB::table('invoices')
            ->where('company_id', $this->companyId)
            ->whereDate('invoice_date', '>=', $silentLookback)
            ->whereRaw("UPPER(status) NOT IN ('VOID', 'CANCELLED')")
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('MAX(invoice_date) < ?', [$silentCutoff])
            ->havingRaw('COUNT(*) >= 2')
            ->selectRaw('customer_id, COUNT(*) as orders, MAX(invoice_date) as last_date, SUM(invoice_total) as revenue')
            ->get();

        $ids = $rows->pluck('customer_id')
            ->merge($silentRows->pluck('customer_id'))
            ->unique()
            ->all();
        $names = Customer::query()->whereIn('id', $ids)->pluck('company_name', 'id');

        $dropoff = [];
        foreach ($rows as $row) {
            $priorOrders = (int) $row->orders_prior;
            $priorRev = (float) $row->rev_prior;
            if ($priorOrders < 2 || $priorRev <= 0) {
                continue;
            }
            $recentRev = (float) $row->rev_recent;
            $recentOrders = (int) $row->orders_recent;
            if ($recentRev >= $priorRev * 0.6 && $recentOrders > 0) {
                continue;
            }
            $dropoff[] = [
                'customer' => $names[$row->customer_id] ?? ('Customer #'.$row->customer_id),
                'recent_orders' => $recentOrders,
                'prior_orders' => $priorOrders,
                'recent_revenue' => round($recentRev, 2),
                'prior_revenue' => round($priorRev, 2),
                'last_date' => $row->last_date ? substr((string) $row->last_date, 0, 10) : null,
                'basis' => 'Based on the last 60 days versus the prior 60 days for this account.',
            ];
        }
        usort($dropoff, fn ($a, $b) => $b['prior_revenue'] <=> $a['prior_revenue']);

        $silent = [];
        foreach ($silentRows as $row) {
            $last = $row->last_date ? Carbon::parse($row->last_date) : null;
            $silent[] = [
                'customer' => $names[$row->customer_id] ?? ('Customer #'.$row->customer_id),
                'last_order' => $last?->toDateString(),
                'days' => $last ? (int) $last->diffInDays(now()) : $inactiveDays,
                'prior_orders' => (int) $row->orders,
                'basis' => 'Based on invoices since '.Carbon::parse($silentLookback)->toDateString().'. No invoice in the last '.$inactiveDays.' days.',
            ];
        }
        usort($silent, fn ($a, $b) => $b['days'] <=> $a['days']);

        return [
            'inactive_days' => $inactiveDays,
            'dropoff_count' => count($dropoff),
            'silent_count' => count($silent),
            'dropoff' => array_slice($dropoff, 0, 12),
            'silent' => array_slice($silent, 0, 12),
        ];
    }

    /** @return array<string, mixed> */
    private function threeWayMatch(): array
    {
        $from = now()->subDays(90)->toDateString();
        $rate = $this->tolerancePercent() / 100;
        $rows = DB::table('inventory_receiving_lines as rl')
            ->join('inventory_receivings as r', 'r.id', '=', 'rl.inventory_receiving_id')
            ->leftJoin('purchase_order_lines as pol', 'pol.id', '=', 'rl.purchase_order_line_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
            ->where('r.company_id', $this->companyId)
            ->whereDate('r.receipt_date', '>=', $from)
            ->orderByDesc('r.receipt_date')
            ->limit(400)
            ->get([
                'r.receipt_number', 'po.po_number', 'rl.item_code', 'rl.description',
                'rl.qty_received', 'rl.unit_cost as recv_cost', 'rl.purchase_order_line_id',
                'pol.qty_ordered', 'pol.unit_cost as po_cost',
            ]);

        $exceptions = [];
        $matched = 0;
        foreach ($rows as $row) {
            if (! $row->purchase_order_line_id || $row->qty_ordered === null) {
                $exceptions[] = $this->matchRow($row, 'Receipt line is not linked to a PO line.');
                continue;
            }
            $qtyOk = self::withinTolerance((float) $row->qty_ordered, (float) $row->qty_received, $rate);
            $bothCosts = (float) $row->po_cost > 0 && (float) $row->recv_cost > 0;
            $costOk = ! $bothCosts || self::withinTolerance((float) $row->po_cost, (float) $row->recv_cost, $rate);
            if ($qtyOk && $costOk) {
                $matched++;
                continue;
            }
            $why = [];
            if (! $qtyOk) {
                $why[] = 'qty ordered '.(float) $row->qty_ordered.' vs received '.(float) $row->qty_received;
            }
            if (! $costOk) {
                $why[] = 'PO cost $'.number_format((float) $row->po_cost, 2).' vs receipt $'.number_format((float) $row->recv_cost, 2);
            }
            $exceptions[] = $this->matchRow($row, implode('; ', $why));
        }

        return [
            'tolerance_pct' => $this->tolerancePercent(),
            'checked' => $rows->count(),
            'matched' => $matched,
            'exception_count' => count($exceptions),
            'exceptions' => array_slice($exceptions, 0, 20),
            'basis' => 'Based on warehouse receipts in the last 90 days compared with the linked PO line. Vendor invoice comparison runs when a invoice is scanned. Tolerance '.$this->tolerancePercent().'%. Lines inside tolerance are not listed. Nothing is auto-posted.',
        ];
    }

    /** @return array<string, mixed> */
    private function vendorScorecard(): array
    {
        $from = now()->subDays(180)->toDateString();
        $rows = DB::table('purchase_order_lines as pol')
            ->join('purchase_orders as po', 'po.id', '=', 'pol.purchase_order_id')
            ->leftJoin('suppliers as v', 'v.id', '=', 'po.supplier_id')
            ->leftJoin('inventory_receivings as r', 'r.purchase_order_id', '=', 'po.id')
            ->where('po.company_id', $this->companyId)
            ->whereDate('po.requisition_date', '>=', $from)
            ->whereNotNull('po.supplier_id')
            ->groupBy('po.supplier_id', 'v.name')
            ->selectRaw('po.supplier_id, v.name as supplier_name, SUM(pol.qty_ordered) as ordered_qty, SUM(pol.qty_received) as received_qty, COUNT(DISTINCT po.id) as pos')
            ->get();

        $onTime = DB::table('inventory_receivings as r')
            ->join('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
            ->where('r.company_id', $this->companyId)
            ->whereDate('r.receipt_date', '>=', $from)
            ->whereNotNull('po.required_date')
            ->groupBy('po.supplier_id')
            ->selectRaw('po.supplier_id, COUNT(*) as receipts, SUM(CASE WHEN r.receipt_date <= po.required_date THEN 1 ELSE 0 END) as on_time')
            ->get()
            ->keyBy('supplier_id');

        $price = DB::table('inventory_receiving_lines as rl')
            ->join('inventory_receivings as r', 'r.id', '=', 'rl.inventory_receiving_id')
            ->join('purchase_order_lines as pol', 'pol.id', '=', 'rl.purchase_order_line_id')
            ->join('purchase_orders as po', 'po.id', '=', 'pol.purchase_order_id')
            ->where('r.company_id', $this->companyId)
            ->whereDate('r.receipt_date', '>=', $from)
            ->where('pol.unit_cost', '>', 0)
            ->where('rl.unit_cost', '>', 0)
            ->groupBy('po.supplier_id')
            ->selectRaw('po.supplier_id, AVG(ABS(rl.unit_cost - pol.unit_cost) / pol.unit_cost) as price_var')
            ->get()
            ->keyBy('supplier_id');

        $out = [];
        foreach ($rows as $row) {
            $ordered = (float) $row->ordered_qty;
            $fill = $ordered > 0 ? min(1, (float) $row->received_qty / $ordered) : 0;
            $ot = $onTime->get($row->supplier_id);
            $onTimeRate = ($ot && (int) $ot->receipts > 0) ? ((float) $ot->on_time / (float) $ot->receipts) : null;
            $var = $price->get($row->supplier_id);
            $priceVar = $var ? (float) $var->price_var : null;
            $onTimePart = $onTimeRate ?? 0.5;
            $pricePart = $priceVar === null ? 0.5 : max(0, 1 - min(1, $priceVar));
            $score = (int) round(100 * ((0.5 * $fill) + (0.3 * $onTimePart) + (0.2 * $pricePart)));
            $out[] = [
                'supplier' => $row->supplier_name ?: ('Supplier #'.$row->supplier_id),
                'fill_rate' => round($fill * 100),
                'on_time' => $onTimeRate === null ? null : round($onTimeRate * 100),
                'price_var' => $priceVar === null ? null : round($priceVar * 100, 1),
                'score' => $score,
                'pos' => (int) $row->pos,
                'basis' => 'Based on POs and receipts in the last 180 days. On-time uses the PO required date. Price variance is receipt cost versus PO cost.',
            ];
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['rows' => array_slice($out, 0, 15)];
    }

    /** @return array<string, mixed> */
    private function cheaperSuppliers(): array
    {
        $rows = DB::table('item_suppliers as s')
            ->join('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('suppliers as v', 'v.id', '=', 's.supplier_id')
            ->where('i.company_id', $this->companyId)
            ->where('i.is_inactive', false)
            ->where('s.last_cost', '>', 0)
            ->orderBy('s.item_id')
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->get([
                's.item_id', 'i.item_code', 'i.description',
                's.supplier_id', 'v.name as supplier_name', 's.last_cost', 's.is_default', 's.sort_order',
            ]);

        $byItem = [];
        foreach ($rows as $row) {
            $byItem[(int) $row->item_id][] = $row;
        }

        $out = [];
        foreach ($byItem as $list) {
            if (count($list) < 2) {
                continue;
            }
            $primary = $list[0];
            foreach ($list as $candidate) {
                if ((int) $candidate->is_default === 1) {
                    $primary = $candidate;
                    break;
                }
            }
            $cheapest = $primary;
            foreach ($list as $candidate) {
                if ((float) $candidate->last_cost + 0.0001 < (float) $cheapest->last_cost) {
                    $cheapest = $candidate;
                }
            }
            if ((int) $cheapest->supplier_id === (int) $primary->supplier_id) {
                continue;
            }
            $save = (float) $primary->last_cost - (float) $cheapest->last_cost;
            if ($save < 0.01) {
                continue;
            }
            $out[] = [
                'code' => (string) $primary->item_code,
                'name' => (string) $primary->description,
                'current' => (string) ($primary->supplier_name ?: 'Vendor #'.$primary->supplier_id),
                'current_cost' => round((float) $primary->last_cost, 2),
                'cheaper' => (string) ($cheapest->supplier_name ?: 'Vendor #'.$cheapest->supplier_id),
                'cheaper_cost' => round((float) $cheapest->last_cost, 2),
                'save' => round($save, 2),
                'basis' => 'From item supplier last cost. Preferred/first vendor vs the lowest last cost on the same SKU. This does not change the PO.',
            ];
            if (count($out) >= 50) {
                break;
            }
        }

        usort($out, fn ($a, $b) => $b['save'] <=> $a['save']);

        return ['count' => count($out), 'rows' => $out];
    }

    /** @return array<string, mixed> */
    private function supplierPriceTable(): array
    {
        $rows = DB::table('item_suppliers as s')
            ->join('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('suppliers as v', 'v.id', '=', 's.supplier_id')
            ->where('i.company_id', $this->companyId)
            ->where('i.is_inactive', false)
            ->where(function ($q) {
                $q->where('s.last_cost', '>', 0)->orWhere('s.avg_cost', '>', 0);
            })
            ->orderByDesc('s.last_received_at')
            ->orderBy('i.item_code')
            ->limit(80)
            ->get([
                'i.item_code', 'i.description', 'v.name as supplier',
                's.supplier_item_code', 's.last_cost', 's.avg_cost', 's.last_received_at', 's.is_default',
            ]);

        if ($rows->isEmpty()) {
            $rows = DB::table('inventory_receiving_lines as rl')
                ->join('inventory_receivings as r', 'r.id', '=', 'rl.inventory_receiving_id')
                ->leftJoin('suppliers as v', 'v.id', '=', 'r.supplier_id')
                ->where('r.company_id', $this->companyId)
                ->where('rl.unit_cost', '>', 0)
                ->orderByDesc('r.receipt_date')
                ->limit(80)
                ->get([
                    'rl.item_code', 'rl.description', 'v.name as supplier',
                    DB::raw('NULL as supplier_item_code'),
                    'rl.unit_cost as last_cost',
                    'rl.unit_cost as avg_cost',
                    'r.receipt_date as last_received_at',
                    DB::raw('0 as is_default'),
                ]);
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'code' => (string) ($row->item_code ?? ''),
                'name' => (string) ($row->description ?? ''),
                'supplier' => (string) ($row->supplier ?: '—'),
                'vendor_sku' => (string) ($row->supplier_item_code ?? ''),
                'last_cost' => round((float) $row->last_cost, 2),
                'avg_cost' => round((float) $row->avg_cost, 2),
                'last_received' => $row->last_received_at ? substr((string) $row->last_received_at, 0, 10) : null,
                'is_default' => (bool) $row->is_default,
                'basis' => 'From the item supplier list (last cost / average cost). Not a live vendor quote.',
            ];
        }

        return ['count' => count($out), 'rows' => $out];
    }

    /** @return array<string, mixed> */
    private function crossSellPairs(): array
    {
        $from = now()->subDays(180)->toDateString();
        $rows = DB::table('sales_order_lines as a')
            ->join('sales_order_lines as b', function ($join) {
                $join->on('b.sales_order_id', '=', 'a.sales_order_id')
                    ->whereColumn('b.item_id', '>', 'a.item_id');
            })
            ->join('sales_orders as so', 'so.id', '=', 'a.sales_order_id')
            ->join('invoices as inv', 'inv.sales_order_id', '=', 'so.id')
            ->join('items as ia', 'ia.id', '=', 'a.item_id')
            ->join('items as ib', 'ib.id', '=', 'b.item_id')
            ->where('so.company_id', $this->companyId)
            ->where('inv.invoice_date', '>=', $from)
            ->whereRaw("UPPER(inv.status) NOT IN ('VOID', 'CANCELLED')")
            ->whereNotNull('a.item_id')
            ->whereNotNull('b.item_id')
            ->groupBy('a.item_id', 'b.item_id', 'ia.item_code', 'ia.description', 'ib.item_code', 'ib.description')
            ->havingRaw('COUNT(DISTINCT a.sales_order_id) >= 4')
            ->orderByDesc('together')
            ->limit(25)
            ->selectRaw('ia.item_code as code_a, ia.description as name_a, ib.item_code as code_b, ib.description as name_b, COUNT(DISTINCT a.sales_order_id) as together')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'code_a' => (string) $row->code_a,
                'name_a' => (string) $row->name_a,
                'code_b' => (string) $row->code_b,
                'name_b' => (string) $row->name_b,
                'together' => (int) $row->together,
                'basis' => 'Based on invoiced orders in the last 180 days. On a sales order this also appears as add-on hints. Nothing is added automatically.',
            ];
        }

        return ['count' => count($out), 'rows' => $out];
    }

    /** @return array<string, mixed> */
    private function complianceReport(): array
    {
        $items = Item::query()
            ->where('company_id', $this->companyId)
            ->where('is_inactive', false)
            ->where(function ($q) {
                $q->where('msa_reporting', true)
                    ->orWhere('state_reporting', true)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('tobacco_product_type')->where('tobacco_product_type', '!=', '');
                    });
            })
            ->orderBy('item_code')
            ->get(['id', 'item_code', 'description', 'tobacco_product_type', 'quantity_in_stock', 'current_cost', 'msa_reporting', 'state_reporting']);

        $sold = $this->qtySoldSince($items->pluck('id')->all(), now()->subDays(30)->toDateString());
        $samples = [];
        $onHandCost = 0.0;
        foreach ($items as $item) {
            $onHandCost += (float) $item->quantity_in_stock * (float) ($item->current_cost ?: 0);
            if (count($samples) < 8) {
                $type = trim((string) $item->tobacco_product_type);
                if ($type === '') {
                    $type = $item->msa_reporting ? 'MSA' : 'State reporting';
                }
                $samples[] = [
                    'code' => (string) $item->item_code,
                    'name' => (string) $item->description,
                    'type' => $type,
                    'on_hand' => (float) $item->quantity_in_stock,
                ];
            }
        }

        return [
            'sku_count' => $items->count(),
            'on_hand_cost' => round($onHandCost, 2),
            'sold_30d_qty' => round(array_sum($sold), 2),
            'license_note' => 'Customer resale and tobacco license numbers and expiration dates are not stored on the customer record, so expiration alerts cannot be raised yet. Regulated SKUs are tagged from tobacco product type and the MSA / state reporting flags.',
            'samples' => $samples,
        ];
    }

    /** @return array<string, mixed> */
    private function anomalyReport(): array
    {
        $from = now()->subDays(21)->toDateString();
        $lines = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->join('invoices as inv', 'inv.sales_order_id', '=', 'so.id')
            ->leftJoin('customers as cu', 'cu.id', '=', 'inv.customer_id')
            ->leftJoin('items as it', 'it.id', '=', 'sol.item_id')
            ->where('so.company_id', $this->companyId)
            ->whereDate('inv.invoice_date', '>=', $from)
            ->whereRaw("UPPER(inv.status) NOT IN ('VOID', 'CANCELLED')")
            ->orderByDesc('inv.invoice_date')
            ->limit(800)
            ->get([
                'inv.invoice_date', 'inv.invoice_number', 'inv.invoice_total', 'inv.customer_id',
                'cu.company_name', 'sol.item_code', 'sol.description', 'sol.qty_ordered', 'sol.price',
                'sol.discount', 'sol.line_total', 'it.list_price',
            ]);

        $flags = [];
        foreach ($lines as $line) {
            $gross = (float) $line->qty_ordered * (float) $line->price;
            $discountPct = $gross > 0 ? ((float) $line->discount / $gross) : 0;
            $list = (float) ($line->list_price ?? 0);
            $reasons = [];
            if ($discountPct >= 0.25) {
                $reasons[] = 'line discount '.round($discountPct * 100).'%';
            }
            if ($list > 0 && (float) $line->price > 0 && (float) $line->price <= $list * 0.6) {
                $reasons[] = 'price $'.number_format((float) $line->price, 2).' is under 60% of list $'.number_format($list, 2);
            }
            if ($reasons === []) {
                continue;
            }
            $flags[] = [
                'date' => $line->invoice_date ? substr((string) $line->invoice_date, 0, 10) : null,
                'customer' => $line->company_name ?: '—',
                'invoice' => (string) $line->invoice_number,
                'item' => trim((string) ($line->item_code.' '.$line->description)),
                'reason' => implode('; ', $reasons),
                'amount' => round((float) $line->line_total, 2),
                'basis' => 'Based on the last 21 days. Flagged for review only — the sale was not blocked.',
            ];
            if (count($flags) >= 15) {
                break;
            }
        }

        return ['count' => count($flags), 'rows' => $flags];
    }

    /** @return array<string, mixed> */
    private function reorderDueCompany(): array
    {
        $from = now()->subDays(180)->toDateString();
        $rows = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->joinSub($this->invoiceDates($from), 'inv', 'inv.sales_order_id', '=', 'so.id')
            ->where('so.company_id', $this->companyId)
            ->whereNotNull('so.customer_id')
            ->whereNotNull('sol.item_id')
            ->groupBy('so.customer_id', 'sol.item_id', 'sol.item_code')
                ->havingRaw('COUNT(DISTINCT inv.invoice_date) >= 4')
            ->selectRaw('so.customer_id, sol.item_id, sol.item_code, MAX(sol.description) as description, COUNT(DISTINCT inv.invoice_date) as times, MIN(inv.invoice_date) as first_date, MAX(inv.invoice_date) as last_date')
            ->get();

        $names = Customer::query()
            ->whereIn('id', $rows->pluck('customer_id')->unique()->all())
            ->pluck('company_name', 'id');

        $due = [];
        foreach ($rows as $row) {
            $times = (int) $row->times;
            if ($times < 3 || ! $row->first_date || ! $row->last_date) {
                continue;
            }
            $span = (int) Carbon::parse($row->first_date)->diffInDays(Carbon::parse($row->last_date));
            $gap = (int) max(1, round($span / max(1, $times - 1)));
            if ($gap < 7 || $gap > 45) {
                continue;
            }
            $daysSince = (int) Carbon::parse($row->last_date)->diffInDays(now());
            if ($daysSince < (int) floor($gap * 0.9) || $daysSince > (int) ceil($gap * 1.4)) {
                continue;
            }
            $due[] = [
                'customer' => $names[$row->customer_id] ?? ('Customer #'.$row->customer_id),
                'code' => (string) $row->item_code,
                'name' => (string) $row->description,
                'days_since' => $daysSince,
                'usual_gap' => $gap,
                'basis' => 'Based on '.$times.' invoice dates over the last 180 days. Usual gap about '.$gap.' days.',
            ];
        }
        usort($due, fn ($a, $b) => $b['days_since'] <=> $a['days_since']);

        return ['count' => count($due), 'rows' => array_slice($due, 0, 15)];
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, object>
     */
    private function defaultSuppliers(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }
        $rows = DB::table('item_suppliers as s')
            ->join('suppliers as v', 'v.id', '=', 's.supplier_id')
            ->whereIn('s.item_id', $itemIds)
            ->orderByDesc('s.is_default')
            ->orderBy('s.sort_order')
            ->get(['s.item_id', 's.supplier_id', 's.lead_time', 'v.name as supplier_name']);

        $byItem = [];
        foreach ($rows as $row) {
            $byItem[(int) $row->item_id] ??= $row;
        }

        return $byItem;
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    private function leadTimeFromPos(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }
        $pairs = DB::table('purchase_order_lines as pol')
            ->join('purchase_orders as po', 'po.id', '=', 'pol.purchase_order_id')
            ->join('inventory_receivings as r', 'r.purchase_order_id', '=', 'po.id')
            ->where('po.company_id', $this->companyId)
            ->whereDate('r.receipt_date', '>=', now()->subDays(365)->toDateString())
            ->whereIn('pol.item_id', array_slice($itemIds, 0, 1500))
            ->get(['pol.item_id', 'po.requisition_date', 'r.receipt_date']);

        $buckets = [];
        foreach ($pairs as $pair) {
            if (! $pair->requisition_date || ! $pair->receipt_date) {
                continue;
            }
            $days = (int) Carbon::parse($pair->requisition_date)->diffInDays(Carbon::parse($pair->receipt_date), false);
            if ($days < 0 || $days > 180) {
                continue;
            }
            $buckets[(int) $pair->item_id][] = $days;
        }

        $avg = [];
        foreach ($buckets as $itemId => $days) {
            if (count($days) < 2) {
                continue;
            }
            $avg[$itemId] = array_sum($days) / count($days);
        }

        return $avg;
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    private function qtySoldSince(array $itemIds, string $from): array
    {
        $itemIds = array_values(array_filter($itemIds));
        if ($itemIds === []) {
            return [];
        }
        $rows = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->joinSub($this->invoiceDates($from), 'inv', 'inv.sales_order_id', '=', 'so.id')
            ->where('so.company_id', $this->companyId)
            ->whereIn('sol.item_id', $itemIds)
            ->groupBy('sol.item_id')
            ->selectRaw('sol.item_id, SUM(sol.qty_ordered) as qty')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->item_id] = (float) $row->qty;
        }

        return $out;
    }

    private function invoiceDates(string $from): \Illuminate\Database\Query\Builder
    {
        return DB::table('invoices')
            ->selectRaw('sales_order_id, MIN(invoice_date) as invoice_date')
            ->where('company_id', $this->companyId)
            ->whereNotNull('sales_order_id')
            ->whereDate('invoice_date', '>=', $from)
            ->whereRaw("UPPER(status) NOT IN ('VOID', 'CANCELLED')")
            ->groupBy('sales_order_id');
    }

    /**
     * @param  list<int>  $customerIds  Empty means every customer in the window.
     * @return array<int, array{avg_days: float, on_time_rate: float, samples: int}>
     */
    private function paymentBehaviorForCustomers(array $customerIds, string $from, string $to): array
    {
        $query = DB::table('invoice_payments as pay')
            ->join('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->where('i.company_id', $this->companyId)
            ->whereNotNull('i.customer_id')
            ->whereDate('pay.payment_date', '>=', $from)
            ->whereDate('pay.payment_date', '<=', $to)
            ->groupBy('i.customer_id', 'i.id', 'i.invoice_date')
            ->selectRaw('i.customer_id, i.id as invoice_id, i.invoice_date, MAX(pay.payment_date) as paid_on');

        if ($customerIds !== []) {
            $query->whereIn('i.customer_id', $customerIds);
        }

        $grouped = [];
        foreach ($query->get() as $row) {
            if (! $row->invoice_date || ! $row->paid_on) {
                continue;
            }
            $days = (int) Carbon::parse($row->invoice_date)->startOfDay()->diffInDays(Carbon::parse($row->paid_on)->startOfDay());
            $cid = (int) $row->customer_id;
            $grouped[$cid]['days'][] = max(0, $days);
            $grouped[$cid]['on_time'] = ($grouped[$cid]['on_time'] ?? 0) + ($days <= 30 ? 1 : 0);
        }

        $out = [];
        foreach ($grouped as $cid => $bag) {
            $n = count($bag['days']);
            $out[$cid] = [
                'avg_days' => $n > 0 ? array_sum($bag['days']) / $n : 0,
                'on_time_rate' => $n > 0 ? $bag['on_time'] / $n : 0,
                'samples' => $n,
            ];
        }

        return $out;
    }

    /** @return array{avg_days: float, on_time_rate: float, samples: int} */
    private function paymentBehavior(int $customerId, string $from, string $to): array
    {
        $all = $this->paymentBehaviorForCustomers([$customerId], $from, $to);

        return $all[$customerId] ?? ['avg_days' => 0, 'on_time_rate' => 0, 'samples' => 0];
    }

    /** @return list<array{code: string, name: string, times: int, gap: int, days_since: int}> */
    private function customerItemCadence(int $customerId): array
    {
        $from = now()->subDays(180)->toDateString();
        $rows = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->joinSub($this->invoiceDates($from), 'inv', 'inv.sales_order_id', '=', 'so.id')
            ->where('so.company_id', $this->companyId)
            ->where('so.customer_id', $customerId)
            ->whereNotNull('sol.item_id')
            ->groupBy('sol.item_id', 'sol.item_code')
            ->selectRaw('sol.item_id, sol.item_code, MAX(sol.description) as description, COUNT(DISTINCT inv.invoice_date) as times, MIN(inv.invoice_date) as first_date, MAX(inv.invoice_date) as last_date')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $times = (int) $row->times;
            $span = ($row->first_date && $row->last_date)
                ? (int) Carbon::parse($row->first_date)->diffInDays(Carbon::parse($row->last_date))
                : 0;
            $out[] = [
                'code' => (string) $row->item_code,
                'name' => (string) $row->description,
                'times' => $times,
                'gap' => $times > 1 ? (int) max(1, round($span / ($times - 1))) : 0,
                'days_since' => $row->last_date ? (int) Carbon::parse($row->last_date)->diffInDays(now()) : 0,
            ];
        }

        return $out;
    }

    private function collectionDraft(string $name, string $invoice, float $balance, int $days): string
    {
        $who = $name !== '' ? $name : 'there';

        return 'Hi '.$who.', just checking in on invoice '.$invoice.' for $'.number_format($balance, 2)
            .' from about '.$days.' days ago. Let me know when you can send payment, or if you need another copy. Thanks.';
    }

    /** @param  array<string, mixed>  $snapshot */
    private function composeDigest(array $snapshot): string
    {
        $sales = 'Open the Insights tab for today\'s live sales total.';
        $sentences = [];
        $sentences[] = 'Morning notes for '.now()->format('M j').', from live POS data. These are drafts for staff — nothing was ordered, emailed, or posted.';
        $suggested = (int) data_get($snapshot, 'demand.suggested_count', 0);
        $sentences[] = $suggested > 0
            ? $suggested.' SKUs have a suggested reorder quantity based on the last 90 days of sales. Open Suggested reorders and create a draft PO to review.'
            : 'No SKU has both enough sales history and a stock position that needs a reorder suggestion today.';
        $collections = (int) data_get($snapshot, 'collections.count', 0);
        $amount = (float) data_get($snapshot, 'collections.amount', 0);
        $top = data_get($snapshot, 'collections.rows.0.customer');
        $sentences[] = $collections > 0
            ? 'Collections: '.$collections.' open invoices, about $'.number_format($amount, 2).' outstanding, ranked by risk'.($top ? '. Start with '.$top.'.' : '.')
            : 'No open NOT PAID balances to rank for collections.';
        $exceptions = (int) data_get($snapshot, 'match.exception_count', 0);
        $sentences[] = $exceptions > 0
            ? $exceptions.' receipt lines are outside the match tolerance and should be reviewed before they are treated as matched.'
            : 'No receipt exceptions are sitting outside the match tolerance.';
        $slow = (int) data_get($snapshot, 'slow.count', 0);
        $churn = (int) data_get($snapshot, 'churn.dropoff_count', 0);
        $sentences[] = 'Also on the list: '.$slow.' slow-moving SKUs and '.$churn.' accounts ordering below their own recent baseline.';
        $cash = (float) data_get($snapshot, 'cash.total', 0);
        $sentences[] = 'Expected cash-in from open invoices over the next 4 weeks is about $'.number_format($cash, 2).', using each account\'s usual days to pay. '.$sales;

        return implode(' ', $sentences);
    }

    /** @param  array<string, mixed>  $s */
    private function replyDemand(array $s): string
    {
        $lines = [
            '### Suggested reorders',
            'Based on the **last 90 days** of invoiced sales, blended with the last 28 days, plus lead time from PO history, the supplier, or the item.',
            '**'.(int) data_get($s, 'demand.suggested_count', 0).'** SKUs have a draft quantity. **'.(int) data_get($s, 'demand.insufficient_count', 0).'** sampled SKUs were skipped for insufficient history (not guessed).',
            'Create the draft from **Insights → Suggested reorders**. Saving the PO is still a manual step.',
            '',
        ];
        foreach ((array) data_get($s, 'demand.groups', []) as $group) {
            $lines[] = '**'.($group['supplier_name'] ?? 'Supplier').'**';
            foreach (array_slice((array) ($group['lines'] ?? []), 0, 6) as $line) {
                $lines[] = '- '.$line['code'].' — '.$line['name'].': order **'.$line['suggested_qty'].'** by '.$line['target_date']
                    .' ('.$line['confidence'].' confidence, '.$line['lead_days'].' day lead). '.$line['basis'];
            }
        }
        if ((int) data_get($s, 'demand.suggested_count', 0) === 0) {
            $lines[] = 'Nothing to reorder from the current rules.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replySlow(array $s): string
    {
        $lines = ['### Slow movers', 'SKUs still in stock with no sale in **90+ days**. Clearance % is a suggestion only.', ''];
        foreach ((array) data_get($s, 'slow.rows', []) as $row) {
            $lines[] = '- **'.$row['code'].'** '.$row['name'].' — '.$row['days_since_sale'].' days, on hand '.$row['on_hand']
                .', turnover '.$row['turnover'].', draft discount '.$row['discount_pct'].'%, cost at risk $'.number_format((float) $row['cost_at_risk'], 2).'.';
        }
        if ($lines === ['### Slow movers', 'SKUs still in stock with no sale in **90+ days**. Clearance % is a suggestion only.', '']) {
            $lines[] = 'No slow movers under that rule.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyCollections(array $s): string
    {
        $lines = [
            '### Collections by risk',
            (int) data_get($s, 'collections.count', 0).' open invoices, $'.number_format((float) data_get($s, 'collections.amount', 0), 2).' outstanding. Ranked by days open, historical days to pay, and amount. Drafts are not sent.',
            '',
        ];
        foreach (array_slice((array) data_get($s, 'collections.rows', []), 0, 8) as $row) {
            $lines[] = '- **'.$row['customer'].'** invoice '.$row['invoice'].' — score '.$row['score'].', $'.number_format((float) $row['balance'], 2).', '.$row['days'].' days. '.$row['basis'];
            $lines[] = '  Draft: '.$row['draft'];
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyCredit(array $s): string
    {
        $lines = ['### Credit risk', 'Accounts whose days-to-pay or on-time rate worsened versus their own prior 6 months. The sales order shows this when the account is selected.', ''];
        foreach ((array) data_get($s, 'credit.rows', []) as $row) {
            $lines[] = '- **'.$row['customer'].'** — days to pay '.$row['prior_dso'].' → '.$row['recent_dso']
                .', on time '.$row['prior_ontime'].'% → '.$row['recent_ontime'].'%. '.$row['basis'];
        }
        if ((int) data_get($s, 'credit.count', 0) === 0) {
            $lines[] = 'No account has both enough history and a worsening trend.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyCash(array $s): string
    {
        $lines = ['### Cash-in forecast', (string) data_get($s, 'cash.basis', ''), ''];
        foreach ((array) data_get($s, 'cash.weeks', []) as $week) {
            $lines[] = '- **'.$week['label'].'** — $'.number_format((float) $week['amount'], 2).' ('.$week['invoices'].' invoices)';
        }
        $lines[] = '';
        $lines[] = '**4-week total:** $'.number_format((float) data_get($s, 'cash.total', 0), 2);

        return implode("\n", $lines);
    }

    private function replyCrossSell(): string
    {
        $item = $this->itemFromQuestion();
        if ($item) {
            $rows = $this->crossSellForItem((int) $item->id);
            $lines = ['### Often bought with '.$item->item_code, 'Based on the last **180 days** of invoiced orders that included this SKU.', ''];
            if ($rows === []) {
                $lines[] = 'Not enough shared orders to suggest add-ons.';
            }
            foreach ($rows as $row) {
                $lines[] = '- **'.$row['code'].'** '.$row['name'].' — together on '.$row['together'].' orders';
            }

            return implode("\n", $lines);
        }

        $pairs = (array) data_get($this->section('cross_sell'), 'rows', []);
        $lines = ['### Cross-sell', 'Pairs that show up together on invoiced orders in the last 180 days. On a sales order, add-ons also appear when you scan an item. Nothing is added automatically.', ''];
        foreach ($pairs as $row) {
            $lines[] = '- **'.$row['code_a'].'** + **'.$row['code_b'].'** — together on '.$row['together'].' orders';
        }
        if ($pairs === []) {
            $lines[] = 'Not enough shared orders yet. Add-ons still appear on the sales order when a SKU has history.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyReorderDue(array $s): string
    {
        $lines = ['### Reorder due', 'Accounts whose usual gap for a regular item has elapsed. Based on the last 180 days. Prompt only — no order is created.', ''];
        foreach ((array) data_get($s, 'reorder_due.rows', []) as $row) {
            $lines[] = '- **'.$row['customer'].'** — '.$row['code'].' '.$row['name'].' last bought '.$row['days_since'].' days ago (usual gap '.$row['usual_gap'].' days).';
        }
        if ((int) data_get($s, 'reorder_due.count', 0) === 0) {
            $lines[] = 'No regular item is due under that rule.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyChurn(array $s): string
    {
        $days = $this->askedInactiveDays();
        if ($days !== 60) {
            $s['churn'] = $this->churnReport($days);
        }
        $churn = $s['churn'];
        $lines = [
            '### Account drop-off',
            'Compared with each account\'s own prior 60 days. Silent list: no invoice in the last **'.(int) ($churn['inactive_days'] ?? $days).'** days, but they ordered in the 180 days before that.',
            '',
            '**Ordering less**',
        ];
        foreach ((array) ($churn['dropoff'] ?? []) as $row) {
            $lines[] = '- **'.$row['customer'].'** — $'.number_format((float) $row['prior_revenue'], 2).' → $'.number_format((float) $row['recent_revenue'], 2).' ('.$row['basis'].')';
        }
        if (($churn['dropoff'] ?? []) === []) {
            $lines[] = '- None';
        }
        $lines[] = '';
        $lines[] = '**No order in '.(int) ($churn['inactive_days'] ?? $days).' days**';
        foreach ((array) ($churn['silent'] ?? []) as $row) {
            $lines[] = '- **'.$row['customer'].'** — last invoice '.$row['last_order'].' ('.$row['days'].' days). '.$row['basis'];
        }
        if (($churn['silent'] ?? []) === []) {
            $lines[] = '- None';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyMatch(array $s): string
    {
        $match = $s['match'] ?? [];
        $lines = [
            '### 3-way match',
            (string) ($match['basis'] ?? ''),
            'Checked **'.(int) ($match['checked'] ?? 0).'** receipt lines. **'.(int) ($match['matched'] ?? 0).'** within tolerance. **'.(int) ($match['exception_count'] ?? 0).'** exceptions.',
            '',
        ];
        foreach ((array) ($match['exceptions'] ?? []) as $row) {
            $lines[] = '- PO '.($row['po'] ?: '—').' / receipt '.($row['receipt'] ?: '—').' — '.$row['item'].'. '.$row['note'];
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyVendors(array $s): string
    {
        $lines = ['### Vendor scorecard', 'Fill rate, on-time delivery (vs PO required date), and price variance. Last **180 days**. Use this when choosing who to reorder from — it does not place an order.', ''];
        foreach ((array) data_get($s, 'vendors.rows', []) as $row) {
            $onTime = $row['on_time'] === null ? 'n/a' : $row['on_time'].'%';
            $price = $row['price_var'] === null ? 'n/a' : $row['price_var'].'%';
            $lines[] = '- **'.$row['supplier'].'** — score '.$row['score'].', fill '.$row['fill_rate'].'%, on time '.$onTime.', price variance '.$price.' ('.$row['pos'].' POs).';
        }
        if (data_get($s, 'vendors.rows', []) === []) {
            $lines[] = 'No vendor receipts in the last 180 days.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyCheaper(array $s): string
    {
        $lines = ['### Cheaper suppliers', 'Another vendor on the same SKU has a lower last cost than the default. This does not change who you buy from.', ''];
        foreach ((array) data_get($s, 'cheaper.rows', []) as $row) {
            $lines[] = '- **'.$row['code'].'** '.$row['name'].' — '.$row['current'].' $'.number_format((float) $row['current_cost'], 2)
                .' vs '.$row['cheaper'].' $'.number_format((float) $row['cheaper_cost'], 2).' (save $'.number_format((float) $row['save'], 2).').';
        }
        if ((int) data_get($s, 'cheaper.count', 0) === 0) {
            $lines[] = 'No SKU has a cheaper alternate vendor on file.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyPrices(array $s): string
    {
        $lines = ['### Supplier price table', 'Last cost from the item supplier list.', ''];
        foreach (array_slice((array) data_get($s, 'prices.rows', []), 0, 20) as $row) {
            $lines[] = '- **'.$row['code'].'** '.$row['supplier'].' — last $'.number_format((float) $row['last_cost'], 2)
                .($row['is_default'] ? ' (default)' : '');
        }
        if ((int) data_get($s, 'prices.count', 0) === 0) {
            $lines[] = 'No supplier costs on file.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyCompliance(array $s): string
    {
        $c = $s['compliance'] ?? [];
        $lines = [
            '### Regulated products',
            '**'.(int) ($c['sku_count'] ?? 0).'** active SKUs tagged tobacco / MSA / state reporting. On-hand cost about $'.number_format((float) ($c['on_hand_cost'] ?? 0), 2).'. Sold qty in 30 days: '.($c['sold_30d_qty'] ?? 0).'.',
            (string) ($c['license_note'] ?? ''),
            '',
        ];
        foreach ((array) ($c['samples'] ?? []) as $row) {
            $lines[] = '- **'.$row['code'].'** '.$row['name'].' ('.$row['type'].') on hand '.$row['on_hand'];
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyAnomalies(array $s): string
    {
        $lines = ['### Unusual transactions', 'Last **21 days**. High line discount (25%+) or unit price at or under 60% of list. Review only — sales were not blocked.', ''];
        foreach ((array) data_get($s, 'anomalies.rows', []) as $row) {
            $lines[] = '- '.$row['date'].' **'.$row['customer'].'** '.$row['invoice'].' — '.$row['item'].'. '.$row['reason'].' ($'.number_format((float) $row['amount'], 2).').';
        }
        if ((int) data_get($s, 'anomalies.count', 0) === 0) {
            $lines[] = 'Nothing outside those ranges.';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $s */
    private function replyDigest(array $s): string
    {
        return "### Morning digest\n".(string) ($s['digest'] ?? '')."\n\nEmail delivery is off unless **Email morning digest** is enabled in POS AI settings. The scheduled send goes to the company email at 7:00am and does not send POs or collection notes.";
    }

    private function itemFromQuestion(): ?Item
    {
        $words = preg_split('/[^A-Za-z0-9\-]+/', $this->question) ?: [];
        $words = array_values(array_filter($words, fn ($w) => strlen($w) >= 2));
        $words = array_slice($words, 0, 12);
        if ($words === []) {
            return null;
        }

        return Item::query()
            ->where('company_id', $this->companyId)
            ->where('is_inactive', false)
            ->whereIn('item_code', $words)
            ->first(['id', 'item_code', 'description']);
    }

    private function askedInactiveDays(): int
    {
        if (preg_match('/(\d{1,3})\s*days/', strtolower($this->question), $m)) {
            return max(7, min(365, (int) $m[1]));
        }

        return 60;
    }

    private function tolerancePercent(): float
    {
        $company = Company::query()->find($this->companyId, ['id', 'japs_ai_match_tolerance']);
        $pct = (float) ($company->japs_ai_match_tolerance ?? 2);
        if ($pct <= 0) {
            $pct = 2;
        }

        return min(25, $pct);
    }

    /** @return array<string, mixed> */
    private function matchRow(object $row, string $note): array
    {
        return [
            'po' => (string) ($row->po_number ?? ''),
            'receipt' => (string) ($row->receipt_number ?? ''),
            'item' => trim((string) (($row->item_code ?? '').' '.($row->description ?? ''))),
            'ordered' => $row->qty_ordered !== null ? (float) $row->qty_ordered : null,
            'received' => (float) ($row->qty_received ?? 0),
            'po_cost' => $row->po_cost !== null ? (float) $row->po_cost : null,
            'recv_cost' => (float) ($row->recv_cost ?? 0),
            'note' => $note,
        ];
    }

    private function moneyDays(mixed $days): string
    {
        return (string) (int) round((float) $days);
    }

    /** @return array<string, mixed> */
    private function emptyDemand(): array
    {
        return [
            'window_days' => 90,
            'suggested_count' => 0,
            'insufficient_count' => 0,
            'groups' => [],
            'insufficient' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyChurn(): array
    {
        return [
            'inactive_days' => 60,
            'dropoff_count' => 0,
            'silent_count' => 0,
            'dropoff' => [],
            'silent' => [],
        ];
    }

    private function cacheKey(): string
    {
        return 'pos-ai-intel:v2:'.$this->companyId;
    }

    private function sectionKey(string $key): string
    {
        return 'pos-ai-sec:v1:'.$this->companyId.':'.$key;
    }

    /** @return array<string, mixed> */
    private function buildSection(string $key): array
    {
        return match ($key) {
            'demand' => $this->demandForecast(),
            'slow' => $this->slowMovers(),
            'collections' => $this->collectionsRisk(),
            'credit' => $this->creditRiskList(),
            'cash' => $this->cashFlow(),
            'churn' => $this->churnReport(60),
            'match' => $this->threeWayMatch(),
            'vendors' => $this->vendorScorecard(),
            'cheaper' => $this->cheaperSuppliers(),
            'prices' => $this->supplierPriceTable(),
            'compliance' => $this->complianceReport(),
            'anomalies' => $this->anomalyReport(),
            'reorder_due' => $this->reorderDueCompany(),
            'cross_sell' => $this->crossSellPairs(),
            'digest' => ['text' => $this->quickDigest()],
            default => [],
        };
    }

    private function quickDigest(): string
    {
        $collections = $this->section('collections');
        $slow = $this->section('slow');

        return 'Live POS notes for '.now()->format('M j').'. '
            .(int) data_get($collections, 'count', 0).' open invoices ranked for collections (~$'
            .number_format((float) data_get($collections, 'amount', 0), 2).'). '
            .(int) data_get($slow, 'count', 0).' slow-moving SKUs. '
            .'Open a topic on the Intelligence tab for the full answer. Drafts only — nothing was sent.';
    }

    /**
     * @template T
     * @param  callable(): T  $fn
     * @param  T  $fallback
     * @return T
     */
    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);

            return $fallback;
        }
    }
}
