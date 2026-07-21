<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    public function __invoke(Invoice $invoice, InvoicePayment $payment): Response
    {
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $invoice->load(['customer', 'salesOrder', 'payments', 'credits']);

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'invoice' => $invoice,
            'payment' => $payment,
            'company' => auth()->user()->company,
        ]);

        return $pdf->download('payment-receipt-'.$invoice->invoice_number.'-'.$payment->id.'.pdf');
    }
}
