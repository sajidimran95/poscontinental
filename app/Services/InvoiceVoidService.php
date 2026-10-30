<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceVoidLog;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceVoidService
{
    /**
     * Mark VOID, reverse stock/AR, reopen SO, and detach from the sales order
     * so the voided invoice lives only in the VOID list (not on the SO).
     */
    public function void(Invoice $invoice, ?int $userId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($invoice, $userId, $note) {
            $invoice = Invoice::query()
                ->with(['salesOrder.lines', 'salesOrder.customer', 'payments', 'credits'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $status = strtoupper(trim((string) $invoice->status));
            if ($status === 'VOID') {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice #'.$invoice->invoice_number.' is already voided.',
                ]);
            }

            $payTotal = round((float) $invoice->payments->sum('amount'), 2);
            $creditTotal = round((float) $invoice->credits->sum('amount'), 2);
            if (abs($payTotal) > 0.004 || abs($creditTotal) > 0.004) {
                throw ValidationException::withMessages([
                    'invoice' => 'Reverse all payments and credits on invoice #'.$invoice->invoice_number.' before voiding. Then you can void and later unvoid it.',
                ]);
            }

            $order = $invoice->salesOrder;
            $fromStatus = (string) $invoice->status;
            $orderId = $order ? (int) $order->id : (int) ($invoice->sales_order_id ?: 0);
            $orderNumber = $order?->order_number;

            if ($order) {
                app(InventoryService::class)->reverseInvoiceStock($order, $invoice);

                $customer = $order->customer;
                if ($customer instanceof Customer) {
                    $customer->refresh();
                    $customer->update([
                        'number_of_orders' => max(0, (int) $customer->number_of_orders - 1),
                        'total_sales' => max(0, round((float) $customer->total_sales - (float) $invoice->invoice_total, 2)),
                        'balance' => round((float) $customer->balance - (float) $invoice->invoice_total, 2),
                    ]);
                }

                $order->update(['status' => 'New']);
            }

            // Detach from SO — void invoice stays only in VOID list.
            $invoice->update([
                'status' => 'VOID',
                'sales_order_id' => null,
                'voided_sales_order_id' => $orderId > 0 ? $orderId : null,
                'voided_order_number' => $orderNumber,
            ]);

            $this->log(
                invoice: $invoice->fresh(),
                action: 'void',
                fromStatus: $fromStatus,
                toStatus: 'VOID',
                userId: $userId,
                salesOrderId: $orderId > 0 ? $orderId : null,
                orderNumber: $orderNumber,
                note: $note
            );
        });
    }

    /**
     * Re-attach to the original SO, restore NOT PAID, stock out, AR, SO Invoiced.
     */
    public function unvoid(Invoice $invoice, ?int $userId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($invoice, $userId, $note) {
            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $status = strtoupper(trim((string) $invoice->status));
            if ($status !== 'VOID') {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice #'.$invoice->invoice_number.' is not voided.',
                ]);
            }

            $orderId = (int) ($invoice->voided_sales_order_id
                ?: $invoice->sales_order_id
                ?: 0);

            if ($orderId < 1) {
                $orderId = (int) (InvoiceVoidLog::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('action', 'void')
                    ->whereNotNull('sales_order_id')
                    ->orderByDesc('id')
                    ->value('sales_order_id') ?: 0);
            }

            if ($orderId < 1) {
                throw ValidationException::withMessages([
                    'invoice' => 'Cannot unvoid invoice #'.$invoice->invoice_number.' — original sales order is unknown.',
                ]);
            }

            $order = SalesOrder::query()
                ->with(['lines', 'customer', 'invoice'])
                ->lockForUpdate()
                ->find($orderId);

            if (! $order) {
                throw ValidationException::withMessages([
                    'invoice' => 'Cannot unvoid invoice #'.$invoice->invoice_number.' — sales order was deleted.',
                ]);
            }

            $otherActive = Invoice::query()
                ->where('sales_order_id', $order->id)
                ->where('id', '!=', $invoice->id)
                ->whereRaw("UPPER(COALESCE(status,'')) NOT IN ('VOID','CANCELLED','CANCELED')")
                ->exists();
            if ($otherActive) {
                throw ValidationException::withMessages([
                    'invoice' => 'Cannot unvoid #'.$invoice->invoice_number.' — order '.$order->order_number.' already has another active invoice. Void or remove that invoice first.',
                ]);
            }

            $fromStatus = (string) $invoice->status;

            // Re-attach to SO before stock/AR so journals and relations resolve.
            $invoice->update([
                'sales_order_id' => (int) $order->id,
                'status' => 'NOT PAID',
                'voided_sales_order_id' => null,
                'voided_order_number' => null,
            ]);
            $invoice = $invoice->fresh();

            app(InventoryService::class)->applyInvoiceStock($order, $invoice);

            $customer = $order->customer;
            if ($customer instanceof Customer) {
                $customer->refresh();
                $customer->update([
                    'last_order_on' => $order->order_date ?? now()->toDateString(),
                    'number_of_orders' => (int) $customer->number_of_orders + 1,
                    'total_sales' => round((float) $customer->total_sales + (float) $invoice->invoice_total, 2),
                    'balance' => round((float) $customer->balance + (float) $invoice->invoice_total, 2),
                ]);
            }

            $order->update(['status' => 'Invoiced']);

            $this->log(
                invoice: $invoice->fresh(),
                action: 'unvoid',
                fromStatus: $fromStatus,
                toStatus: 'NOT PAID',
                userId: $userId,
                salesOrderId: (int) $order->id,
                orderNumber: $order->order_number,
                note: $note
            );
        });
    }

    protected function log(
        Invoice $invoice,
        string $action,
        string $fromStatus,
        string $toStatus,
        ?int $userId,
        ?int $salesOrderId,
        ?string $orderNumber,
        ?string $note
    ): void {
        InvoiceVoidLog::query()->create([
            'company_id' => (int) $invoice->company_id,
            'invoice_id' => (int) $invoice->id,
            'sales_order_id' => $salesOrderId,
            'user_id' => $userId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'invoice_total' => (float) $invoice->invoice_total,
            'invoice_number' => (string) $invoice->invoice_number,
            'order_number' => $orderNumber,
            'note' => $note,
        ]);
    }
}
