<?php

namespace App\Services\JapsAi;

use App\Models\Customer;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\SalesOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Live POS snapshot for JapsAI Insights + Chat tools.
 */
class BusinessInsightsService
{
    public function __construct(public int $companyId) {}

    public static function forCompany(int $companyId): self
    {
        return new self($companyId);
    }

    public function timezone(): string
    {
        return (string) (config('app.timezone') ?: 'UTC');
    }

    public function asOf(): string
    {
        return now()->timezone($this->timezone())->format('Y-m-d H:i:s').' ('.$this->timezone().')';
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $sales = $this->salesSummary();
        $inventory = $this->inventorySummary();
        $invoices = $this->invoiceSummary();
        $purchases = $this->purchasesSummary(30);
        $pipeline = $this->pipeline();
        $openPos = $this->openPurchaseOrders();
        $payments = $this->paymentsToday();
        $credits = $this->creditMemosSummary(30);
        $customers = $this->customersSummary();

        return [
            'as_of' => $this->asOf(),
            'company_id' => $this->companyId,
            'sales' => $sales,
            'inventory' => $inventory,
            'invoices' => $invoices,
            'purchases_receiving_30d' => $purchases,
            'sales_pipeline' => [
                'open_orders' => $pipeline['open_orders'],
                'open_value' => $pipeline['open_value'],
            ],
            'purchase_orders_open' => [
                'count' => $openPos['count'],
                'value' => $openPos['value'],
            ],
            'payments_today' => [
                'count' => $payments['count'],
                'total' => $payments['total'],
                'by_method' => $payments['by_method'],
            ],
            'credit_memos_30d' => [
                'count' => $credits['count'],
                'total' => $credits['total'],
            ],
            'customers' => $customers,
            'actions' => $this->suggestedActions($sales, $inventory, $invoices),
            'product_map' => ProjectKnowledge::systemMapArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function salesSummary(): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $from30 = now()->subDays(30)->toDateString();

        $sumOrders = function (string $from, string $to): array {
            $q = SalesOrder::query()
                ->where('company_id', $this->companyId)
                ->whereDate('order_date', '>=', $from)
                ->whereDate('order_date', '<=', $to);
            $count = (clone $q)->count();
            $total = (float) (clone $q)->sum('total');

            return [
                'orders' => $count,
                'total' => $total,
                'avg' => $count > 0 ? round($total / $count, 2) : 0.0,
            ];
        };

        $t = $sumOrders($today, $today);
        $y = $sumOrders($yesterday, $yesterday);
        $m = $sumOrders($from30, $today);

        return [
            'today' => $t,
            'yesterday' => $y,
            'last_30_days' => $m,
            'customers_on_file' => Customer::query()
                ->where('company_id', $this->companyId)
                ->where('is_inactive', false)
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function inventorySummary(): array
    {
        $items = Item::query()
            ->where('company_id', $this->companyId)
            ->where('is_inactive', false);

        $total = (clone $items)->count();
        $out = (clone $items)->where('quantity_in_stock', '<=', 0)->count();
        $low = (clone $items)->lowStock()->count();

        $needAttention = (clone $items)
            ->where(function ($q) {
                $q->where('quantity_in_stock', '<=', 0)
                    ->orWhere(function ($q2) {
                        $q2->whereColumn('quantity_in_stock', '<=', 'reorder_point')
                            ->where('reorder_point', '>', 0);
                    });
            })
            ->count();

        $samples = (clone $items)
            ->where(function ($q) {
                $q->where('quantity_in_stock', '<=', 0)
                    ->orWhere(function ($q2) {
                        $q2->whereColumn('quantity_in_stock', '<=', 'reorder_point')
                            ->where('reorder_point', '>', 0);
                    });
            })
            ->orderBy('quantity_in_stock')
            ->limit(8)
            ->get(['item_code', 'description', 'quantity_in_stock', 'reorder_point'])
            ->map(fn (Item $i) => [
                'code' => $i->item_code,
                'name' => $i->description,
                'qty' => (float) $i->quantity_in_stock,
                'reorder' => (float) $i->reorder_point,
            ])
            ->all();

        return [
            'products' => $total,
            'out_of_stock' => $out,
            'below_reorder' => $low,
            'need_attention' => $needAttention,
            'samples' => $samples,
        ];
    }

    /** @return array<string, mixed> */
    public function invoiceSummary(): array
    {
        $open = Invoice::query()
            ->with(['payments', 'credits'])
            ->where('company_id', $this->companyId)
            ->whereNotIn('status', ['void', 'cancelled', 'paid'])
            ->limit(500)
            ->get();

        $openCount = 0;
        $overdueCount = 0;
        $outstanding = 0.0;
        $overdueAmt = 0.0;
        $today = now()->startOfDay();

        foreach ($open as $inv) {
            $bal = (float) $inv->invoice_balance;
            if ($bal <= 0.009) {
                continue;
            }
            $openCount++;
            $outstanding += $bal;
            $invDate = $inv->invoice_date?->copy()->startOfDay();
            // Past due heuristic: older than 30 days with balance
            if ($invDate && $invDate->lt($today->copy()->subDays(30))) {
                $overdueCount++;
                $overdueAmt += $bal;
            }
        }

        return [
            'open' => $openCount,
            'overdue' => $overdueCount,
            'outstanding' => round($outstanding, 2),
            'overdue_amount' => round($overdueAmt, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $sales
     * @param  array<string, mixed>  $inventory
     * @param  array<string, mixed>  $invoices
     * @return list<array{priority: string, title: string, detail: string}>
     */
    public function suggestedActions(array $sales, array $inventory, array $invoices): array
    {
        $actions = [];

        if (($inventory['need_attention'] ?? 0) > 0) {
            $actions[] = [
                'priority' => 'High',
                'title' => 'Restock attention items',
                'detail' => ($inventory['need_attention'] ?? 0).' of '.($inventory['products'] ?? 0)
                    .' products need attention (out of stock or at/below reorder).',
            ];
        }

        if (($invoices['overdue'] ?? 0) > 0) {
            $actions[] = [
                'priority' => 'High',
                'title' => 'Collections follow-up',
                'detail' => 'Follow up on '.($invoices['overdue'] ?? 0)
                    .' older open invoices (~$'.number_format((float) ($invoices['overdue_amount'] ?? 0), 2).').',
            ];
        }

        if (($sales['today']['orders'] ?? 0) === 0) {
            $actions[] = [
                'priority' => 'Medium',
                'title' => 'No sales yet today',
                'detail' => 'Yesterday was $'.number_format((float) ($sales['yesterday']['total'] ?? 0), 2)
                    .'. Check open quotes/pipeline and route activity.',
            ];
        }

        return array_slice($actions, 0, 5);
    }

    /** @return list<array{code: string, description: string, qty: float, revenue: float}> */
    public function topSellingProducts(int $days = 30, int $limit = 10): array
    {
        $from = now()->subDays($days)->toDateString();

        $rows = \App\Models\SalesOrderLine::query()
            ->selectRaw('item_code, description, SUM(qty_ordered) as qty, SUM(line_total) as revenue')
            ->whereHas('salesOrder', function ($q) use ($from) {
                $q->where('company_id', $this->companyId)
                    ->whereDate('order_date', '>=', $from);
            })
            ->groupBy('item_code', 'description')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'code' => $r->item_code,
            'description' => $r->description,
            'qty' => (float) $r->qty,
            'revenue' => (float) $r->revenue,
        ])->all();
    }

    /** @return array<string, mixed> */
    public function purchasesSummary(int $days = 30): array
    {
        $from = now()->subDays($days)->toDateString();
        $q = InventoryReceiving::query()
            ->where('company_id', $this->companyId)
            ->whereDate('receipt_date', '>=', $from);

        $count = (clone $q)->count();

        $lines = \App\Models\InventoryReceivingLine::query()
            ->whereHas('receiving', function ($q) use ($from) {
                $q->where('company_id', $this->companyId)
                    ->whereDate('receipt_date', '>=', $from);
            })
            ->get(['qty_received', 'unit_cost']);

        $value = $lines->sum(fn ($l) => (float) $l->qty_received * (float) $l->unit_cost);

        return [
            'days' => $days,
            'receipts' => $count,
            'line_value' => round((float) $value, 2),
        ];
    }

    /** @return array<string, mixed> */
    public function paymentsToday(): array
    {
        $today = now()->toDateString();
        $rows = InvoicePayment::query()
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $this->companyId))
            ->whereDate('payment_date', $today)
            ->get(['payment_method', 'amount']);

        $byMethod = $rows->groupBy(fn ($p) => $p->payment_method ?: 'Other')
            ->map(fn (Collection $g) => round((float) $g->sum('amount'), 2))
            ->all();

        return [
            'date' => $today,
            'count' => $rows->count(),
            'total' => round((float) $rows->sum('amount'), 2),
            'by_method' => $byMethod,
        ];
    }

    /**
     * ACH (and optional method filter) payment summary for a period + sample rows.
     *
     * @return array<string, mixed>
     */
    public function paymentsByMethod(string $method = 'ACH', int $days = 30): array
    {
        $method = trim($method) !== '' ? $method : 'ACH';
        $from = now()->subDays($days)->toDateString();
        $today = now()->toDateString();

        $base = InvoicePayment::query()
            ->with(['invoice.customer'])
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $this->companyId))
            ->whereRaw('LOWER(payment_method) = ?', [Str::lower($method)]);

        $period = (clone $base)->whereDate('payment_date', '>=', $from)->get();
        $todayRows = (clone $base)->whereDate('payment_date', $today)->get();

        $sample = $period->sortByDesc(fn ($p) => (string) $p->payment_date)
            ->take(12)
            ->values()
            ->map(fn (InvoicePayment $p) => [
                'date' => optional($p->payment_date)?->format('Y-m-d'),
                'invoice' => $p->invoice?->invoice_number ?? '—',
                'customer' => $p->invoice?->customer?->company_name ?? '—',
                'amount' => (float) $p->amount,
                'method' => $p->payment_method,
            ])
            ->all();

        return [
            'method' => $method,
            'days' => $days,
            'today_count' => $todayRows->count(),
            'today_total' => round((float) $todayRows->sum('amount'), 2),
            'period_count' => $period->count(),
            'period_total' => round((float) $period->sum('amount'), 2),
            'sample' => $sample,
        ];
    }

    /** Purchase order status rollup + recent list (not only "open"). */
    public function purchaseOrderSummary(int $days = 30): array
    {
        $from = now()->subDays($days)->toDateString();

        $recent = \App\Models\PurchaseOrder::query()
            ->with('supplier')
            ->where('company_id', $this->companyId)
            ->whereDate('requisition_date', '>=', $from)
            ->orderByDesc('requisition_date')
            ->limit(200)
            ->get();

        $byStatus = $recent->groupBy(fn ($po) => $po->status ?: 'unknown')
            ->map(fn (Collection $g) => [
                'count' => $g->count(),
                'value' => round((float) $g->sum('total'), 2),
            ])
            ->all();

        $open = $this->openPurchaseOrders();

        return [
            'days' => $days,
            'recent_count' => $recent->count(),
            'recent_value' => round((float) $recent->sum('total'), 2),
            'by_status' => $byStatus,
            'open' => $open,
            'sample' => $recent->take(10)->map(fn ($po) => [
                'po' => $po->po_number,
                'supplier' => $po->supplier?->name ?? '—',
                'status' => $po->status,
                'total' => (float) ($po->total ?? 0),
                'date' => optional($po->requisition_date)?->format('Y-m-d'),
            ])->all(),
        ];
    }

    /** @return list<array{invoice: string, customer: string, balance: float, date: string|null}> */
    public function unpaidInvoices(int $limit = 15): array
    {
        return Invoice::query()
            ->with(['customer', 'payments', 'credits'])
            ->where('company_id', $this->companyId)
            ->whereNotIn('status', ['void', 'cancelled', 'paid'])
            ->orderByDesc('invoice_date')
            ->limit(80)
            ->get()
            ->filter(fn (Invoice $i) => $i->invoice_balance > 0.009)
            ->take($limit)
            ->map(fn (Invoice $i) => [
                'invoice' => $i->invoice_number,
                'customer' => $i->customer?->company_name ?? '—',
                'balance' => round((float) $i->invoice_balance, 2),
                'date' => optional($i->invoice_date)?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    /** Open sales orders not fully invoiced / open status. */
    public function pipeline(): array
    {
        $open = SalesOrder::query()
            ->with('customer')
            ->where('company_id', $this->companyId)
            ->whereNotIn('status', ['invoiced', 'cancelled', 'void', 'closed'])
            ->orderByDesc('order_date')
            ->limit(20)
            ->get();

        return [
            'open_orders' => $open->count(),
            'open_value' => round((float) $open->sum('total'), 2),
            'sample' => $open->take(8)->map(fn (SalesOrder $o) => [
                'order' => $o->order_number,
                'customer' => $o->customer?->company_name,
                'status' => $o->status,
                'total' => (float) $o->total,
                'date' => optional($o->order_date)?->format('Y-m-d'),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function invoicesToday(): array
    {
        $today = now()->toDateString();
        $q = Invoice::query()
            ->where('company_id', $this->companyId)
            ->whereDate('invoice_date', $today)
            ->whereNotIn('status', ['void', 'cancelled']);

        $count = (clone $q)->count();
        $total = (float) (clone $q)->sum('invoice_total');

        $rows = (clone $q)
            ->with('customer')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Invoice $i) => [
                'invoice' => $i->invoice_number,
                'customer' => $i->customer?->company_name ?? '—',
                'total' => (float) $i->invoice_total,
                'status' => $i->status,
            ])
            ->all();

        return [
            'date' => $today,
            'count' => $count,
            'total' => round($total, 2),
            'sample' => $rows,
        ];
    }

    /** Open purchase orders (not received / closed). */
    public function openPurchaseOrders(): array
    {
        $open = \App\Models\PurchaseOrder::query()
            ->with('supplier')
            ->where('company_id', $this->companyId)
            ->whereNotIn('status', ['received', 'closed', 'cancelled', 'void', 'complete', 'completed'])
            ->orderByDesc('requisition_date')
            ->limit(25)
            ->get();

        return [
            'count' => $open->count(),
            'value' => round((float) $open->sum('total'), 2),
            'sample' => $open->take(10)->map(fn ($po) => [
                'po' => $po->po_number,
                'supplier' => $po->supplier?->name ?? '—',
                'status' => $po->status,
                'total' => (float) ($po->total ?? 0),
                'date' => optional($po->requisition_date)?->format('Y-m-d'),
            ])->all(),
        ];
    }

    /** @return list<array{name: string, orders: int, revenue: float}> */
    public function topCustomers(int $days = 30, int $limit = 10): array
    {
        $from = now()->subDays($days)->toDateString();

        $rows = SalesOrder::query()
            ->selectRaw('customer_id, COUNT(*) as orders, SUM(total) as revenue')
            ->where('company_id', $this->companyId)
            ->whereDate('order_date', '>=', $from)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $names = Customer::query()
            ->whereIn('id', $rows->pluck('customer_id'))
            ->pluck('company_name', 'id');

        return $rows->map(fn ($r) => [
            'name' => $names[$r->customer_id] ?? 'Customer #'.$r->customer_id,
            'orders' => (int) $r->orders,
            'revenue' => (float) $r->revenue,
        ])->all();
    }

    /** @return array<string, mixed> */
    public function creditMemosSummary(int $days = 30): array
    {
        $from = now()->subDays($days)->toDateString();
        $q = \App\Models\CreditMemo::query()
            ->where('company_id', $this->companyId)
            ->whereDate('memo_date', '>=', $from)
            ->whereNotIn('status', ['void', 'cancelled']);

        $count = (clone $q)->count();
        $total = (float) (clone $q)->sum('amount');

        $sample = (clone $q)
            ->with('customer')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($m) => [
                'memo' => $m->memo_number ?: ('#'.$m->id),
                'customer' => $m->customer?->company_name ?? '—',
                'amount' => (float) $m->amount,
                'status' => $m->status,
            ])
            ->all();

        return [
            'days' => $days,
            'count' => $count,
            'total' => round($total, 2),
            'sample' => $sample,
        ];
    }

    /** Active customers on file. */
    public function customersSummary(): array
    {
        $active = Customer::query()
            ->where('company_id', $this->companyId)
            ->where('is_inactive', false)
            ->count();
        $inactive = Customer::query()
            ->where('company_id', $this->companyId)
            ->where('is_inactive', true)
            ->count();

        return [
            'active' => $active,
            'inactive' => $inactive,
            'total' => $active + $inactive,
        ];
    }

    /**
     * Distinct manufacturers from items (free-text on item master).
     *
     * @return list<array{name: string, items: int, active: int}>
     */
    public function manufacturersList(): array
    {
        $rows = Item::query()
            ->where('company_id', $this->companyId)
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->selectRaw('manufacturer, COUNT(*) as items, SUM(CASE WHEN is_inactive = 0 THEN 1 ELSE 0 END) as active')
            ->groupBy('manufacturer')
            ->orderBy('manufacturer')
            ->get();

        return $rows->map(fn ($r) => [
            'name' => trim((string) $r->manufacturer),
            'items' => (int) $r->items,
            'active' => (int) $r->active,
        ])->filter(fn ($r) => $r['name'] !== '')->values()->all();
    }

    public function money(float $n): string
    {
        return '$'.number_format($n, 2);
    }
}
