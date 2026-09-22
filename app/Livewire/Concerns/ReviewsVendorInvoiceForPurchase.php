<?php

namespace App\Livewire\Concerns;

use App\Models\Company;
use App\Services\JapsAi\VendorInvoicePurchaseOrderService;

trait ReviewsVendorInvoiceForPurchase
{
    public function addMissingInvoiceToLists(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);

        $pending = VendorInvoicePurchaseOrderService::review();
        if ($pending === null) {
            $this->posAiInvoiceNotice('No invoice is waiting for review. Upload the vendor invoice again.');

            return;
        }

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $result = app(VendorInvoicePurchaseOrderService::class)->addMissingToLists(
            $company,
            $pending['raw'],
            $pending['matched']
        );

        $this->posAiInvoiceNotice($result['reply']);
        if (property_exists($this, 'aiInvoiceLines')) {
            $this->aiInvoiceLines = $result['matched']['lines'] ?? $this->aiInvoiceLines;
            $this->aiInvoiceHeader = [
                'supplier_name' => $result['matched']['supplier_name'] ?? null,
                'supplier_id' => $result['matched']['supplier_id'] ?? null,
                'ref_no' => $result['matched']['ref_no'] ?? null,
                'invoice_date' => $result['matched']['invoice_date'] ?? null,
                'total' => $result['matched']['total'] ?? null,
            ];
            $this->aiInvoiceStatus = $result['reply'];
        }
    }

    public function confirmAiInvoicePurchaseOrder(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);

        $pending = VendorInvoicePurchaseOrderService::review();
        if ($pending === null) {
            $this->posAiInvoiceNotice('No invoice is waiting. Upload the vendor invoice again.');

            return;
        }

        $matched = $pending['matched'];
        if (! VendorInvoicePurchaseOrderService::listsAreReady($matched)) {
            $this->posAiInvoiceNotice(
                "Not all OK yet.\n\n"
                .app(VendorInvoicePurchaseOrderService::class)->missingCatalogBrief($matched)
                ."\n\nClick **Review & add to lists** first, then Confirm."
            );

            return;
        }

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $creator = app(VendorInvoicePurchaseOrderService::class);
        $created = $creator->createFromMatch($company, auth()->user(), $matched);

        if (! empty($created['success']) && ! empty($created['po'])) {
            VendorInvoicePurchaseOrderService::clearReview();
            session()->forget('pos_ai_pending_vendor_invoice');
            $reply = $creator->formatChatReply($company, $matched, $created);
            $this->posAiInvoiceNotice($reply);
            if (property_exists($this, 'showAiInvoiceModal')) {
                $this->closeAiInvoiceModal();
                session()->flash('status', $created['msg']);
                $this->redirect(route('purchasing.orders.edit', $created['po']), navigate: true);
            }

            return;
        }

        $this->posAiInvoiceNotice($created['msg'] ?? 'Could not create the purchase order.');
    }

    /**
     * @return array{has: bool, lists_ready: bool}
     */
    public function invoiceReviewButtons(): array
    {
        $pending = VendorInvoicePurchaseOrderService::review();
        if ($pending === null) {
            return ['has' => false, 'lists_ready' => false];
        }

        return [
            'has' => true,
            'lists_ready' => VendorInvoicePurchaseOrderService::listsAreReady($pending['matched']),
        ];
    }

    protected function posAiInvoiceNotice(string $text): void
    {
        if (property_exists($this, 'messages') && is_array($this->messages) && method_exists($this, 'posAiMakeMessage')) {
            $this->messages[] = $this->posAiMakeMessage('assistant', $text, 'openai');
            if (method_exists($this, 'persistChat')) {
                $this->persistChat();
            }
            if (method_exists($this, 'scrollBottom')) {
                $this->scrollBottom();
            }
            if (method_exists($this, 'dispatchScroll')) {
                $this->dispatchScroll();
            }
        }
        if (property_exists($this, 'aiInvoiceStatus')) {
            $this->aiInvoiceStatus = $text;
        }
    }
}
