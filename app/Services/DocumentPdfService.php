<?php

namespace App\Services;

use App\Models\CreditMemo;
use App\Models\DocumentEmailLog;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class DocumentPdfService
{
    public function invoicePdf(Invoice $invoice, ?User $user = null)
    {
        $invoice->load(['customer', 'salesOrder.lines', 'salesOrder.salesRep', 'payments', 'credits']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $user?->company ?? $invoice->customer?->company ?? auth()->user()?->company,
        ]);
    }

    public function creditMemoPdf(CreditMemo $memo, ?User $user = null)
    {
        $memo->load(['customer', 'salesOrder']);

        return Pdf::loadView('pdf.credit-memo', [
            'memo' => $memo,
            'company' => $user?->company ?? auth()->user()?->company,
        ]);
    }

    public function priceListPdf(iterable $items, ?User $user = null, ?string $title = null)
    {
        return Pdf::loadView('pdf.price-list', [
            'items' => $items,
            'title' => $title ?? 'Price List',
            'company' => $user?->company ?? auth()->user()?->company,
        ]);
    }

    public function salesReportPdf(array $payload, ?User $user = null)
    {
        return Pdf::loadView('pdf.sales-report', [
            ...$payload,
            'company' => $user?->company ?? auth()->user()?->company,
        ]);
    }

    public function downloadInvoice(Invoice $invoice): Response
    {
        return $this->invoicePdf($invoice)->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function downloadCreditMemo(CreditMemo $memo): Response
    {
        return $this->creditMemoPdf($memo)->download('credit-memo-'.$memo->memo_number.'.pdf');
    }

    public function emailInvoice(Invoice $invoice, string $recipient, User $user, ?string $subject = null): void
    {
        $subject = $subject ?: 'Invoice '.$invoice->invoice_number;
        $pdf = $this->invoicePdf($invoice, $user);

        Mail::html(
            '<p>Please find attached invoice <strong>'.$invoice->invoice_number.'</strong>.</p>',
            function ($message) use ($recipient, $subject, $pdf, $invoice) {
                $message->to($recipient)
                    ->subject($subject)
                    ->attachData($pdf->output(), 'invoice-'.$invoice->invoice_number.'.pdf', [
                        'mime' => 'application/pdf',
                    ]);
            }
        );

        DocumentEmailLog::query()->create([
            'company_id' => $user->company_id,
            'document_type' => 'invoice',
            'document_id' => $invoice->id,
            'recipient' => $recipient,
            'subject' => $subject,
            'user_id' => $user->id,
        ]);
    }

    public function emailCreditMemo(CreditMemo $memo, string $recipient, User $user, ?string $subject = null): void
    {
        $subject = $subject ?: 'Credit Memo '.$memo->memo_number;
        $pdf = $this->creditMemoPdf($memo, $user);

        Mail::html(
            '<p>Please find attached credit memo <strong>'.$memo->memo_number.'</strong>.</p>',
            function ($message) use ($recipient, $subject, $pdf, $memo) {
                $message->to($recipient)
                    ->subject($subject)
                    ->attachData($pdf->output(), 'credit-memo-'.$memo->memo_number.'.pdf', [
                        'mime' => 'application/pdf',
                    ]);
            }
        );

        DocumentEmailLog::query()->create([
            'company_id' => $user->company_id,
            'document_type' => 'credit_memo',
            'document_id' => $memo->id,
            'recipient' => $recipient,
            'subject' => $subject,
            'user_id' => $user->id,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Item> */
    public function queryPriceListItems(int $companyId, ?int $departmentId, ?int $categoryId, string $search, bool $includeInactive)
    {
        return Item::query()
            ->with(['department', 'category', 'prices'])
            ->where('company_id', $companyId)
            ->when(! $includeInactive, fn ($q) => $q->where('is_inactive', false))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term);
                });
            })
            ->orderBy('item_code')
            ->limit(1000)
            ->get();
    }
}
