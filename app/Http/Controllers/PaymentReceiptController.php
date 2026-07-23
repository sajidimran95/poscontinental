<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentReceiptController extends Controller
{
    public function __invoke(Invoice $invoice, InvoicePayment $payment)
    {
        abort_unless(auth()->check(), 403);
        abort_unless((int) $invoice->company_id === (int) auth()->user()->company_id, 403);
        abort_unless((int) $payment->invoice_id === (int) $invoice->id, 404);

        $invoice->load(['customer', 'salesOrder', 'payments', 'credits']);

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'invoice' => $invoice,
            'payment' => $payment,
            'company' => auth()->user()->company,
        ]);

        return $pdf->stream('payment-receipt-'.$invoice->invoice_number.'-'.$payment->id.'.pdf');
    }
}
