<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditInvoiceBalancesCommand extends Command
{
    protected $signature = 'invoices:audit {--company= : Company ID (default: all)}';

    protected $description = 'List invoices whose status does not match total - payments - credits';

    public function handle(): int
    {
        $rows = DB::table('invoices as i')
            ->leftJoinSub(
                DB::table('invoice_payments')->selectRaw('invoice_id, SUM(amount) AS paid')->groupBy('invoice_id'),
                'p', 'p.invoice_id', '=', 'i.id'
            )
            ->leftJoinSub(
                DB::table('invoice_credits')->selectRaw('invoice_id, SUM(amount) AS credits')->groupBy('invoice_id'),
                'c', 'c.invoice_id', '=', 'i.id'
            )
            ->when($this->option('company'), fn ($q, $id) => $q->where('i.company_id', (int) $id))
            ->whereRaw("UPPER(COALESCE(i.status, '')) NOT IN ('VOID', 'CANCELLED')")
            ->selectRaw('i.invoice_number, i.invoice_date, i.status, i.invoice_total,
                COALESCE(p.paid, 0) AS paid, COALESCE(c.credits, 0) AS credits,
                ROUND(i.invoice_total - COALESCE(p.paid, 0) - COALESCE(c.credits, 0), 2) AS balance')
            ->orderBy('i.invoice_date')
            ->get();

        $problems = $rows->map(function ($r) {
            $paidStatus = strtoupper((string) $r->status) === 'PAID';
            $balance = (float) $r->balance;
            $issue = match (true) {
                $paidStatus && $balance > 0.009 => 'PAID but balance due',
                ! $paidStatus && abs($balance) <= 0.009 && (float) $r->invoice_total > 0 => 'Not paid but balance zero',
                $balance < -0.009 && (float) $r->invoice_total > 0 => 'Overpaid',
                default => null,
            };

            return $issue ? [
                $r->invoice_number,
                $r->invoice_date,
                $r->status,
                number_format((float) $r->invoice_total, 2),
                number_format((float) $r->paid, 2),
                number_format((float) $r->credits, 2),
                number_format($balance, 2),
                $issue,
            ] : null;
        })->filter()->values();

        $this->info("Checked {$rows->count()} invoices.");

        if ($problems->isEmpty()) {
            $this->info('All invoice statuses match their balances.');

            return self::SUCCESS;
        }

        $this->table(['Invoice', 'Date', 'Status', 'Total', 'Paid', 'Credits', 'Balance', 'Issue'], $problems->all());
        $this->warn($problems->count().' invoice(s) need attention.');

        return self::FAILURE;
    }
}
