<?php

namespace App\Services\JapsAi;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Intent routing + optional OpenAI free-text answers using live POS tools.
 */
class JapsAiChatService
{
    /** @var list<array{id: string, label: string, intent: string}> */
    public const QUICK_PROMPTS = [
        ['id' => 'today_sales', 'label' => "Today's sales", 'intent' => 'today_sales'],
        ['id' => 'today_invoices', 'label' => "Today's invoices", 'intent' => 'today_invoices'],
        ['id' => 'overview', 'label' => 'Business overview', 'intent' => 'overview'],
        ['id' => 'top_products', 'label' => 'Top selling products', 'intent' => 'top_products'],
        ['id' => 'top_customers', 'label' => 'Top customers', 'intent' => 'top_customers'],
        ['id' => 'purchases', 'label' => 'Purchases / receiving', 'intent' => 'purchases'],
        ['id' => 'open_pos', 'label' => 'Open purchase orders', 'intent' => 'open_pos'],
        ['id' => 'po_summary', 'label' => 'PO summary (30d)', 'intent' => 'po_summary'],
        ['id' => 'payments', 'label' => 'Payments by method today', 'intent' => 'payments_today'],
        ['id' => 'ach', 'label' => 'ACH payments', 'intent' => 'ach_payments'],
        ['id' => 'low_stock', 'label' => 'Low stock items', 'intent' => 'low_stock'],
        ['id' => 'unpaid', 'label' => 'Unpaid invoices', 'intent' => 'unpaid_invoices'],
        ['id' => 'pipeline', 'label' => 'Open sales orders', 'intent' => 'pipeline'],
        ['id' => 'credits', 'label' => 'Credit memos (30d)', 'intent' => 'credit_memos'],
        ['id' => 'customers', 'label' => 'Customers on file', 'intent' => 'customers'],
        ['id' => 'manufacturers', 'label' => 'All manufacturers', 'intent' => 'manufacturers'],
    ];

    public function __construct(
        public Company $company,
        public BusinessInsightsService $insights,
    ) {}

    public static function forCompany(Company $company): self
    {
        return new self($company, BusinessInsightsService::forCompany((int) $company->id));
    }

    /**
     * @return array{ok: bool, tool?: string, reply: string, error?: string}
     */
    public function handle(string $message, ?string $forcedIntent = null): array
    {
        $message = trim($message);
        if ($message === '' && ! $forcedIntent) {
            return ['ok' => false, 'reply' => 'Ask me about sales, inventory, invoices, or payments.', 'error' => 'empty'];
        }

        $intent = $forcedIntent ?: $this->detectIntent($message);

        if ($intent) {
            return [
                'ok' => true,
                'tool' => $intent,
                'reply' => $this->formatToolReply($intent),
            ];
        }

        // Free-text → OpenAI with live snapshot, if configured
        if ($this->company->japs_ai_enabled && $this->resolveApiKey() !== '') {
            try {
                $reply = $this->askOpenAi($message);

                return ['ok' => true, 'tool' => 'openai', 'reply' => $reply];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'tool' => 'openai',
                    'reply' => 'OpenAI error: '.$e->getMessage()."\n\nYou can still use the quick-action buttons for live POS answers.",
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'ok' => true,
            'tool' => 'help',
            'reply' => $this->helpReply(),
        ];
    }

    public function detectIntent(string $message): ?string
    {
        $m = Str::lower($message);

        // Longer / more specific phrases first (order matters).
        $map = [
            'ach_payments' => ['ach payments', 'ach payment', 'ach '],
            'today_sales' => ["today's sales", 'todays sales', 'sales today', 'today sales'],
            'today_invoices' => ["today's invoices", 'todays invoices', 'invoices today', 'invoice today'],
            'po_summary' => ['po summary', 'pos (30', 'all purchase order', 'purchase orders'],
            'open_pos' => ['open purchase order', 'open pos', 'open po', 'pending po', 'open purchase'],
            'top_products' => ['top selling', 'top products', 'top sell', 'best sell', 'top items'],
            'top_customers' => ['top customers', 'top customer', 'best customer', 'customer sales'],
            'credit_memos' => ['credit memos', 'credit memo', 'credit note', 'returns credit'],
            'unpaid_invoices' => ['unpaid invoices', 'unpaid', 'open invoice', 'outstanding invoice', 'overdue invoice'],
            'payments_today' => ['payments by method', 'payment method', 'paid today', 'payments today'],
            'low_stock' => ['low stock', 'out of stock', 'reorder', 'need attention'],
            'pipeline' => ['open sales order', 'pipeline', 'open sales', 'pending order'],
            'purchases' => ['purchases / receiving', 'purchase report', 'receiving', 'vendor receive'],
            'manufacturers' => ['all manufacturers', 'manufacturers', 'manufacturer', 'all brand', 'brands'],
            'customers' => ['customers on file', 'how many customer', 'customers'],
            'overview' => ['business overview', 'business report', 'dashboard', 'overview report'],
        ];

        // Prefer longest matching phrase (more specific).
        $bestIntent = null;
        $bestLen = 0;
        foreach ($map as $intent => $keys) {
            foreach ($keys as $k) {
                $k = trim($k);
                if ($k === '') {
                    continue;
                }
                if (str_contains($m, $k) && strlen($k) > $bestLen) {
                    $bestIntent = $intent;
                    $bestLen = strlen($k);
                }
            }
        }

        // Bare "ach" only if no longer match
        if ($bestIntent === null && preg_match('/\bach\b/', $m)) {
            return 'ach_payments';
        }

        return $bestIntent;
    }

    public function formatToolReply(string $intent): string
    {
        $i = $this->insights;
        $asOf = $i->asOf();

        return match ($intent) {
            'today_sales' => $this->replyTodaySales($i, $asOf),
            'today_invoices' => $this->replyTodayInvoices($i, $asOf),
            'overview' => $this->replyOverview($i, $asOf),
            'top_products' => $this->replyTopProducts($i, $asOf),
            'top_customers' => $this->replyTopCustomers($i, $asOf),
            'purchases' => $this->replyPurchases($i, $asOf),
            'open_pos' => $this->replyOpenPos($i, $asOf),
            'po_summary' => $this->replyPoSummary($i, $asOf),
            'payments_today' => $this->replyPayments($i, $asOf),
            'ach_payments' => $this->replyAchPayments($i, $asOf),
            'low_stock' => $this->replyLowStock($i, $asOf),
            'unpaid_invoices' => $this->replyUnpaid($i, $asOf),
            'pipeline' => $this->replyPipeline($i, $asOf),
            'credit_memos' => $this->replyCreditMemos($i, $asOf),
            'customers' => $this->replyCustomers($i, $asOf),
            'manufacturers' => $this->replyManufacturers($i, $asOf),
            default => $this->helpReply(),
        };
    }

    private function replyTodaySales(BusinessInsightsService $i, string $asOf): string
    {
        $s = $i->salesSummary();
        $t = $s['today'];
        $y = $s['yesterday'];
        $m = $s['last_30_days'];

        return "As of **{$asOf}** — live POS database numbers.\n\n"
            ."### Today's sales\n"
            .'- **Total:** '.$i->money((float) $t['total'])." ({$t['orders']} orders)\n"
            .'- **Average order:** '.$i->money((float) $t['avg'])."\n\n"
            ."### Yesterday\n"
            .'- **Total:** '.$i->money((float) $y['total'])." ({$y['orders']} orders)\n\n"
            ."### Last 30 days\n"
            .'- **Total:** '.$i->money((float) $m['total'])." ({$m['orders']} orders)\n"
            .'- **Average order:** '.$i->money((float) $m['avg']);
    }

    private function replyOverview(BusinessInsightsService $i, string $asOf): string
    {
        $o = $i->overview();
        $s = $o['sales'];
        $inv = $o['inventory'];
        $ar = $o['invoices'];

        $lines = [
            "As of **{$asOf}** — business overview.",
            '',
            '### Sales',
            '- Today: '.$i->money((float) $s['today']['total']).' / '.$s['today']['orders'].' orders',
            '- Last 30 days: '.$i->money((float) $s['last_30_days']['total']).' / '.$s['last_30_days']['orders'].' orders',
            '- Customers on file: '.$s['customers_on_file'],
            '',
            '### Inventory',
            "- {$inv['need_attention']} of {$inv['products']} products need attention",
            "- Out of stock: {$inv['out_of_stock']} · Below reorder: {$inv['below_reorder']}",
            '',
            '### Invoices',
            "- Open with balance: {$ar['open']} · Outstanding: ".$i->money((float) $ar['outstanding']),
            "- Older open (30+ days): {$ar['overdue']} · ".$i->money((float) $ar['overdue_amount']),
        ];

        if (! empty($o['actions'])) {
            $lines[] = '';
            $lines[] = '### Suggested actions';
            foreach ($o['actions'] as $a) {
                $lines[] = '- **['.$a['priority'].']** '.$a['title'].' — '.$a['detail'];
            }
        }

        return implode("\n", $lines);
    }

    private function replyTopProducts(BusinessInsightsService $i, string $asOf): string
    {
        $rows = $i->topSellingProducts(30, 10);
        if ($rows === []) {
            return "As of **{$asOf}**\n\nNo sold lines in the last 30 days.";
        }
        $lines = ["As of **{$asOf}** — top products by revenue (30 days).", ''];
        $n = 1;
        foreach ($rows as $r) {
            $lines[] = "{$n}. **{$r['code']}** — {$r['description']}";
            $lines[] = '   Qty '.number_format($r['qty'], 2).' · '.$i->money($r['revenue']);
            $n++;
        }

        return implode("\n", $lines);
    }

    private function replyPurchases(BusinessInsightsService $i, string $asOf): string
    {
        $p = $i->purchasesSummary(30);

        return "As of **{$asOf}** — purchases / receiving (last {$p['days']} days).\n\n"
            ."- Receipts: **{$p['receipts']}**\n"
            .'- Line value (qty × cost): **'.$i->money((float) $p['line_value']).'**';
    }

    private function replyPayments(BusinessInsightsService $i, string $asOf): string
    {
        $p = $i->paymentsToday();
        $lines = [
            "As of **{$asOf}** — payments on {$p['date']}.",
            '',
            '- Count: **'.$p['count'].'**',
            '- Total: **'.$i->money((float) $p['total']).'**',
            '',
            '### By method',
        ];
        if ($p['by_method'] === []) {
            $lines[] = '- No payments recorded today.';
        } else {
            foreach ($p['by_method'] as $method => $amt) {
                $lines[] = '- **'.$method.':** '.$i->money((float) $amt);
            }
        }

        return implode("\n", $lines);
    }

    private function replyLowStock(BusinessInsightsService $i, string $asOf): string
    {
        $inv = $i->inventorySummary();
        $lines = [
            "As of **{$asOf}** — inventory attention.",
            '',
            "- Products: **{$inv['products']}**",
            "- Need attention: **{$inv['need_attention']}** (out of stock: {$inv['out_of_stock']}, at/below reorder: {$inv['below_reorder']})",
            '',
        ];
        if ($inv['samples'] === []) {
            $lines[] = 'No low-stock / out-of-stock items flagged.';
        } else {
            $lines[] = '### Sample items';
            foreach ($inv['samples'] as $s) {
                $lines[] = '- **'.$s['code'].'** '.$s['name'].' — on hand '.$s['qty'].' / reorder '.$s['reorder'];
            }
        }

        return implode("\n", $lines);
    }

    private function replyUnpaid(BusinessInsightsService $i, string $asOf): string
    {
        $rows = $i->unpaidInvoices(12);
        $sum = $i->invoiceSummary();
        $lines = [
            "As of **{$asOf}** — unpaid / open invoices.",
            '',
            '- Open with balance: **'.$sum['open'].'**',
            '- Outstanding: **'.$i->money((float) $sum['outstanding']).'**',
            '',
        ];
        if ($rows === []) {
            $lines[] = 'No open balances found.';
        } else {
            foreach ($rows as $r) {
                $lines[] = '- **'.$r['invoice'].'** · '.$r['customer'].' · '.$i->money($r['balance']).' · '.$r['date'];
            }
        }

        return implode("\n", $lines);
    }

    private function replyPipeline(BusinessInsightsService $i, string $asOf): string
    {
        $p = $i->pipeline();
        $lines = [
            "As of **{$asOf}** — open sales order pipeline.",
            '',
            '- Open orders: **'.$p['open_orders'].'**',
            '- Open value: **'.$i->money((float) $p['open_value']).'**',
            '',
        ];
        foreach ($p['sample'] as $row) {
            $lines[] = '- **'.$row['order'].'** · '.$row['customer'].' · '.$row['status']
                .' · '.$i->money((float) $row['total']).' · '.$row['date'];
        }
        if ($p['sample'] === []) {
            $lines[] = 'No open orders in non-closed statuses.';
        }

        return implode("\n", $lines);
    }

    private function replyTodayInvoices(BusinessInsightsService $i, string $asOf): string
    {
        $p = $i->invoicesToday();
        $lines = [
            "As of **{$asOf}** — invoices on {$p['date']}.",
            '',
            '- Count: **'.$p['count'].'**',
            '- Total: **'.$i->money((float) $p['total']).'**',
            '',
        ];
        if ($p['sample'] === []) {
            $lines[] = 'No invoices dated today.';
        } else {
            $lines[] = '### Sample';
            foreach ($p['sample'] as $r) {
                $lines[] = '- **'.$r['invoice'].'** · '.$r['customer'].' · '.$i->money((float) $r['total']).' · '.$r['status'];
            }
        }

        return implode("\n", $lines);
    }

    private function replyTopCustomers(BusinessInsightsService $i, string $asOf): string
    {
        $rows = $i->topCustomers(30, 10);
        if ($rows === []) {
            return "As of **{$asOf}**\n\nNo customer sales in the last 30 days.";
        }
        $lines = ["As of **{$asOf}** — top customers by sales (30 days).", ''];
        $n = 1;
        foreach ($rows as $r) {
            $lines[] = "{$n}. **{$r['name']}** — ".$i->money($r['revenue'])." ({$r['orders']} orders)";
            $n++;
        }

        return implode("\n", $lines);
    }

    private function replyOpenPos(BusinessInsightsService $i, string $asOf): string
    {
        $p = $i->openPurchaseOrders();
        $lines = [
            "As of **{$asOf}** — open purchase orders.",
            '',
            '- Open POs: **'.$p['count'].'**',
            '- Open value: **'.$i->money((float) $p['value']).'**',
            '',
        ];
        if ($p['sample'] === []) {
            $lines[] = 'No open purchase orders found.';
        } else {
            foreach ($p['sample'] as $row) {
                $lines[] = '- **'.$row['po'].'** · '.$row['supplier'].' · '.$row['status']
                    .' · '.$i->money((float) $row['total']).' · '.$row['date'];
            }
        }

        return implode("\n", $lines);
    }

    private function replyPoSummary(BusinessInsightsService $i, string $asOf): string
    {
        $p = $i->purchaseOrderSummary(30);
        $lines = [
            "As of **{$asOf}** — purchase orders (last {$p['days']} days).",
            '',
            '- Recent POs: **'.$p['recent_count'].'**',
            '- Recent value: **'.$i->money((float) $p['recent_value']).'**',
            '- Still open: **'.$p['open']['count'].'** · '.$i->money((float) $p['open']['value']),
            '',
            '### By status',
        ];
        if ($p['by_status'] === []) {
            $lines[] = '- No POs in this period.';
        } else {
            foreach ($p['by_status'] as $status => $row) {
                $lines[] = '- **'.$status.':** '.$row['count'].' · '.$i->money((float) $row['value']);
            }
        }
        if ($p['sample'] !== []) {
            $lines[] = '';
            $lines[] = '### Recent POs';
            foreach ($p['sample'] as $row) {
                $lines[] = '- **'.$row['po'].'** · '.$row['supplier'].' · '.$row['status']
                    .' · '.$i->money((float) $row['total']).' · '.$row['date'];
            }
        }

        return implode("\n", $lines);
    }

    private function replyAchPayments(BusinessInsightsService $i, string $asOf): string
    {
        $p = $i->paymentsByMethod('ACH', 30);
        $lines = [
            "As of **{$asOf}** — **{$p['method']}** payments (invoice AR payments).",
            '',
            "### Today",
            '- Count: **'.$p['today_count'].'**',
            '- Total: **'.$i->money((float) $p['today_total']).'**',
            '',
            "### Last {$p['days']} days",
            '- Count: **'.$p['period_count'].'**',
            '- Total: **'.$i->money((float) $p['period_total']).'**',
            '',
        ];
        if ($p['sample'] === []) {
            $lines[] = 'No ACH payments found in this period. (ACH must be saved as payment method on invoice payments.)';
        } else {
            $lines[] = '### Recent ACH lines';
            foreach ($p['sample'] as $r) {
                $lines[] = '- **'.$r['date'].'** · inv '.$r['invoice'].' · '.$r['customer']
                    .' · '.$i->money((float) $r['amount']);
            }
        }

        return implode("\n", $lines);
    }

    private function replyCreditMemos(BusinessInsightsService $i, string $asOf): string
    {
        $p = $i->creditMemosSummary(30);
        $lines = [
            "As of **{$asOf}** — credit memos (last {$p['days']} days).",
            '',
            '- Count: **'.$p['count'].'**',
            '- Total amount: **'.$i->money((float) $p['total']).'**',
            '',
        ];
        if ($p['sample'] === []) {
            $lines[] = 'No credit memos in this period.';
        } else {
            foreach ($p['sample'] as $r) {
                $lines[] = '- **'.$r['memo'].'** · '.$r['customer'].' · '.$i->money((float) $r['amount']).' · '.$r['status'];
            }
        }

        return implode("\n", $lines);
    }

    private function replyCustomers(BusinessInsightsService $i, string $asOf): string
    {
        $c = $i->customersSummary();

        return "As of **{$asOf}** — customers on file.\n\n"
            ."- Active: **{$c['active']}**\n"
            ."- Inactive: **{$c['inactive']}**\n"
            ."- Total: **{$c['total']}**";
    }

    private function replyManufacturers(BusinessInsightsService $i, string $asOf): string
    {
        $rows = $i->manufacturersList();
        if ($rows === []) {
            return "As of **{$asOf}**\n\nNo manufacturers found on items. Set Manufacturer on inventory items to list them here.";
        }

        $totalItems = array_sum(array_column($rows, 'items'));
        $lines = [
            "As of **{$asOf}** — all manufacturers from inventory items.",
            '',
            '- Manufacturers: **'.count($rows).'**',
            '- Items with manufacturer: **'.$totalItems.'**',
            '',
            '### Manufacturers',
        ];
        foreach ($rows as $r) {
            $lines[] = '- **'.$r['name'].'** — '.$r['items'].' item'.($r['items'] === 1 ? '' : 's')
                .($r['active'] !== $r['items']
                    ? ' ('.$r['active'].' active)'
                    : '');
        }

        return implode("\n", $lines);
    }

    private function helpReply(): string
    {
        $enabled = $this->company->japs_ai_enabled && $this->resolveApiKey() !== '';
        $extra = $enabled
            ? 'You can also type any free-form question — POS AI will use OpenAI with live POS context.'
            : 'Enable POS AI and save an OpenAI API key in Settings for free-form questions. Quick actions work without OpenAI.';

        return "Hi! I'm **POS AI**. I read your live POS data.\n\n"
            ."Click a quick action below, or ask about sales, invoices, stock, purchases, customers, payments, or pipeline.\n\n"
            .$extra;
    }

    public function resolveApiKey(): string
    {
        $fromCompany = trim((string) ($this->company->japs_ai_api_key ?? ''));
        if ($fromCompany !== '') {
            return $fromCompany;
        }

        return trim((string) env('OPENAI_API_KEY', ''));
    }

    private function askOpenAi(string $message): string
    {
        $snapshot = $this->insights->overview();
        $model = trim((string) ($this->company->japs_ai_model ?: 'gpt-4o-mini')) ?: 'gpt-4o-mini';
        $key = $this->resolveApiKey();

        $system = 'You are POS AI for a wholesale POS (Continental / JAPS). '
            .'Answer using ONLY the provided live data snapshot when numbers are needed. '
            .'Be concise, use bullet points, and suggest concrete next actions. '
            .'Currency is USD. If data is missing, say so.';

        $user = "Live snapshot JSON:\n".json_encode($snapshot, JSON_PRETTY_PRINT)
            ."\n\nUser question: {$message}";

        $response = Http::withToken($key)
            ->timeout(45)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($text === '') {
            throw new \RuntimeException('Empty response from OpenAI.');
        }

        return $text;
    }
}
