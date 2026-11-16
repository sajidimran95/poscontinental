<?php

namespace App\Livewire\Concerns;

use App\Models\Company;
use App\Services\JapsAi\VendorInvoicePurchaseOrderService;
use App\Services\JapsAi\VendorInvoiceRtvService;

trait ReviewsVendorInvoiceForPurchase
{
    public function addMissingInvoiceToLists(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);

        $pending = VendorInvoicePurchaseOrderService::review();
        if ($pending === null) {
            $this->posAiInvoiceNotice('No document is waiting for review. Upload the vendor PDF again.');

            return;
        }

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $result = app(VendorInvoicePurchaseOrderService::class)->addMissingToLists(
            $company,
            $pending['raw'],
            $pending['matched']
        );

        $kind = (string) ($result['matched']['document_kind'] ?? 'po');
        $reply = $result['reply'];
        if ($kind === 'rtv') {
            $reply = app(VendorInvoiceRtvService::class)->reviewReply($company, $result['matched']);
            if (! empty($result['added_supplier']) || ! empty($result['added_items'])) {
                $prefix = trim((string) preg_replace('/\n\n\*\*All are OK\*\*.*$/s', '', $result['reply']));
                if ($prefix !== '') {
                    $reply = $prefix."\n\n".$reply;
                }
            }
        }

        $this->posAiInvoiceNotice($reply);
        if (property_exists($this, 'aiInvoiceLines')) {
            $this->aiInvoiceLines = $result['matched']['lines'] ?? $this->aiInvoiceLines;
            $this->aiInvoiceHeader = [
                'supplier_name' => $result['matched']['supplier_name'] ?? null,
                'supplier_id' => $result['matched']['supplier_id'] ?? null,
                'ref_no' => $result['matched']['ref_no'] ?? null,
                'invoice_date' => $result['matched']['invoice_date'] ?? null,
                'total' => $result['matched']['total'] ?? null,
                'document_kind' => $result['matched']['document_kind'] ?? 'po',
            ];
            $this->aiInvoiceStatus = $reply;
        }
    }

    public function confirmAiInvoicePurchaseOrder(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);

        $pending = VendorInvoicePurchaseOrderService::review();
        if ($pending === null) {
            $this->posAiInvoiceNotice('No document is waiting. Upload the vendor PDF again.');

            return;
        }

        $matched = $pending['matched'];
        $kind = (string) ($matched['document_kind'] ?? 'po');

        if ($kind === 'rtv') {
            $this->confirmAiInvoiceReturnToVendor($pending);

            return;
        }

        $amountsOk = VendorInvoicePurchaseOrderService::amountsMatch($matched);
        $listsReady = VendorInvoicePurchaseOrderService::listsAreReady($matched);
        // Long multi-page invoices can miss a few OCR rows. If every scanned line is in
        // the catalog, allow Confirm and create the PO from those lines.
        if (! $amountsOk && ! $listsReady) {
            $this->posAiInvoiceNotice(VendorInvoicePurchaseOrderService::amountMismatchText($matched));

            return;
        }

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $creator = app(VendorInvoicePurchaseOrderService::class);

        if (! $listsReady) {
            $added = $creator->addMissingToLists($company, $pending['raw'], $matched);
            $matched = $added['matched'];
            $amountsOk = VendorInvoicePurchaseOrderService::amountsMatch($matched);
            $listsReady = VendorInvoicePurchaseOrderService::listsAreReady($matched);
            if (! $listsReady) {
                $this->posAiInvoiceNotice($added['reply']);

                return;
            }
        }
        if (! $amountsOk) {
            $this->posAiInvoiceNotice(
                VendorInvoicePurchaseOrderService::amountMismatchText($matched)
                ."\n\nCreating the PO from the **matched lines** — review and add any missing items on the PO."
            );
            $matched['allow_partial_amount'] = true;
        }
        $created = $creator->createFromMatch($company, auth()->user(), $matched);

        if (! empty($created['success']) && ! empty($created['po'])) {
            VendorInvoicePurchaseOrderService::clearReview();
            session()->forget('pos_ai_pending_vendor_invoice');
            $reply = $creator->formatChatReply($company, $matched, $created);
            if (! empty($matched['credits'])) {
                $reply .= "\n\nInvoice credit / return rows were noted on the PO comments only — **no RTV** (stock was not received for those lines).";
            }
            $this->posAiInvoiceNotice($reply);
            $this->speakPosWorkAlert();
            if (property_exists($this, 'showAiInvoiceModal')) {
                $this->closeAiInvoiceModal();
            }
            session()->flash('status', $created['msg']);
            $this->redirect(route('purchasing.orders.edit', $created['po']), navigate: true);

            return;
        }

        $this->posAiInvoiceNotice($created['msg'] ?? 'Could not create the purchase order.');
    }

    /**
     * @param  array{raw: array<string, mixed>, matched: array<string, mixed>, lists_ready: bool}  $pending
     */
    protected function confirmAiInvoiceReturnToVendor(array $pending): void
    {
        $user = auth()->user();
        abort_unless($user?->canAccessFeature('purchasing.rtv', 'edit') ?? false, 403, 'Your role cannot create Return to Vendor documents.');

        $matched = $pending['matched'];
        if (! VendorInvoicePurchaseOrderService::amountsMatch($matched)) {
            $this->posAiInvoiceNotice(VendorInvoicePurchaseOrderService::amountMismatchText($matched));

            return;
        }

        $company = Company::query()->findOrFail($user->company_id);
        $poSvc = app(VendorInvoicePurchaseOrderService::class);
        $rtvSvc = app(VendorInvoiceRtvService::class);

        if (! VendorInvoicePurchaseOrderService::listsAreReady($matched)) {
            $added = $poSvc->addMissingToLists($company, $pending['raw'], $matched);
            $matched = $added['matched'];
            $matched['document_kind'] = 'rtv';
            if (! VendorInvoicePurchaseOrderService::listsAreReady($matched) || ! VendorInvoicePurchaseOrderService::amountsMatch($matched)) {
                $this->posAiInvoiceNotice($rtvSvc->reviewReply($company, $matched));

                return;
            }
        }

        $created = $rtvSvc->createFromMatch($company, $user, $matched);

        if (! empty($created['success']) && ! empty($created['rtv'])) {
            VendorInvoicePurchaseOrderService::clearReview();
            session()->forget('pos_ai_pending_vendor_invoice');
            $reply = $rtvSvc->formatChatReply($company, $matched, $created);
            $this->posAiInvoiceNotice($reply);
            if (property_exists($this, 'showAiInvoiceModal')) {
                $this->closeAiInvoiceModal();
            }
            session()->flash('status', $created['msg']);
            $this->redirect(route('purchasing.rtv.index'), navigate: true);

            return;
        }

        $this->posAiInvoiceNotice($created['msg'] ?? 'Could not create the RTV.');
    }

    /**
     * Read the new alert on this user's POS only.
     */
    protected function speakPosWorkAlert(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $companyId = (int) $user->company_id;
        $alerts = VendorInvoicePurchaseOrderService::adminAlerts($companyId);
        $script = VendorInvoicePurchaseOrderService::speakTextForUser($companyId, (int) $user->id, $alerts);
        $fp = VendorInvoicePurchaseOrderService::adminAlertFingerprint($alerts);
        if ($script === '' || $fp === '') {
            return;
        }
        if (property_exists($this, 'adminAlerts')) {
            $this->adminAlerts = $alerts;
        }
        if (property_exists($this, 'adminAlertFingerprint') && $fp === $this->adminAlertFingerprint) {
            return;
        }
        if (property_exists($this, 'adminAlertFingerprint')) {
            $this->adminAlertFingerprint = $fp;
        }

        $this->dispatch('pos-ai-speak-alert', text: $script, fingerprint: $fp, userId: (int) $user->id);
    }

    /**
     * @return array{has: bool, lists_ready: bool, amounts_match: bool, can_confirm: bool, kind: string, confirm_label: string}
     */
    public function invoiceReviewButtons(): array
    {
        $pending = VendorInvoicePurchaseOrderService::review();
        if ($pending === null) {
            return [
                'has' => false,
                'lists_ready' => false,
                'amounts_match' => false,
                'can_confirm' => false,
                'kind' => 'po',
                'confirm_label' => 'Confirm & create PO',
            ];
        }

        $matched = $pending['matched'];
        $kind = (string) ($matched['document_kind'] ?? 'po');
        $amountsMatch = VendorInvoicePurchaseOrderService::amountsMatch($matched);
        $canConfirm = $amountsMatch;
        if ($kind === 'rtv') {
            $canConfirm = $amountsMatch && (auth()->user()?->canAccessFeature('purchasing.rtv', 'edit') ?? false);
        }
        $listsReady = VendorInvoicePurchaseOrderService::listsAreReady($matched);
        $label = $kind === 'rtv' ? 'Confirm & create RTV' : 'Confirm & create PO';
        if ($kind === 'po' && $listsReady) {
            // Catalog-ready PO can confirm even if OCR missed a few rows (warning still shown).
            $canConfirm = true;
        }

        return [
            'has' => true,
            'lists_ready' => $listsReady,
            'amounts_match' => $amountsMatch,
            'can_confirm' => $canConfirm,
            'kind' => $kind,
            'confirm_label' => $label,
        ];
    }

    protected function posAiInvoiceNotice(string $text, string $tool = 'invoice_review'): void
    {
        if (property_exists($this, 'messages') && is_array($this->messages) && method_exists($this, 'posAiMakeMessage')) {
            $this->messages[] = $this->posAiMakeMessage('assistant', $text, $tool);
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
