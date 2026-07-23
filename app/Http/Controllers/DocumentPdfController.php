<?php

namespace App\Http\Controllers;

use App\Models\CreditMemo;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\ReturnToVendor;
use App\Models\SalesOrder;
use App\Services\DocumentPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentPdfController extends Controller
{
    public function invoice(Invoice $invoice, DocumentPdfService $pdfs): Response
    {
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

        return $pdfs->invoicePdf($invoice, auth()->user())->stream('invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function salesOrder(SalesOrder $salesOrder, DocumentPdfService $pdfs): Response
    {
        abort_unless($salesOrder->company_id === auth()->user()->company_id, 403);

        return $pdfs->streamSalesOrderInvoiceStyle($salesOrder, auth()->user());
    }

    public function purchaseOrder(PurchaseOrder $purchaseOrder, DocumentPdfService $pdfs): Response
    {
        abort_unless($purchaseOrder->company_id === auth()->user()->company_id, 403);

        return $pdfs->streamPurchaseOrder($purchaseOrder, auth()->user());
    }

    public function receiving(InventoryReceiving $receiving, DocumentPdfService $pdfs): Response
    {
        abort_unless($receiving->company_id === auth()->user()->company_id, 403);

        return $pdfs->streamReceiving($receiving, auth()->user());
    }

    public function rtv(ReturnToVendor $rtv, DocumentPdfService $pdfs): Response
    {
        abort_unless($rtv->company_id === auth()->user()->company_id, 403);

        return $pdfs->streamRtv($rtv, auth()->user());
    }

    public function creditMemo(CreditMemo $memo, DocumentPdfService $pdfs): Response
    {
        abort_unless($memo->company_id === auth()->user()->company_id, 403);

        return $pdfs->downloadCreditMemo($memo);
    }

    public function emailInvoice(Request $request, Invoice $invoice, DocumentPdfService $pdfs): RedirectResponse
    {
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

        $data = $request->validate([
            'email' => 'required|email',
            'subject' => 'nullable|string|max:255',
        ]);

        $pdfs->emailInvoice($invoice, $data['email'], auth()->user(), $data['subject'] ?? null);

        return back()->with('status', 'Invoice emailed to '.$data['email']);
    }

    public function emailCreditMemo(Request $request, CreditMemo $memo, DocumentPdfService $pdfs): RedirectResponse
    {
        abort_unless($memo->company_id === auth()->user()->company_id, 403);

        $data = $request->validate([
            'email' => 'required|email',
            'subject' => 'nullable|string|max:255',
        ]);

        $pdfs->emailCreditMemo($memo, $data['email'], auth()->user(), $data['subject'] ?? null);

        return back()->with('status', 'Credit memo emailed to '.$data['email']);
    }
}
