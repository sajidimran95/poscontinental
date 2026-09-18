<?php

namespace App\Livewire\Concerns;

use App\Models\Company;
use App\Models\Item;
use App\Services\JapsAi\InvoiceExtractionService;
use Livewire\WithFileUploads;

/**
 * JapsAI vendor-invoice scan → review → apply lines (japspos-style workflow).
 * Host component must own $lines / supplier fields and fillLineFromItem-compatible APIs.
 */
trait ScansVendorInvoiceWithAi
{
    use WithFileUploads;

    public bool $showAiInvoiceModal = false;

    /** @var mixed */
    public $aiInvoiceFile = null;

    public string $aiInvoiceStatus = '';

    public string $aiInvoiceError = '';

    /** @var array<string, mixed>|null */
    public ?array $aiInvoiceHeader = null;

    /**
     * Review rows after extract + catalog match.
     *
     * @var list<array<string, mixed>>
     */
    public array $aiInvoiceLines = [];

    public function openAiInvoiceModal(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);
        abort_if(property_exists($this, 'viewMode') && $this->viewMode, 403);

        $this->resetAiInvoiceModal();
        $this->showAiInvoiceModal = true;
    }

    public function closeAiInvoiceModal(): void
    {
        $this->resetAiInvoiceModal();
        $this->showAiInvoiceModal = false;
    }

    public function resetAiInvoiceModal(): void
    {
        $this->aiInvoiceFile = null;
        $this->aiInvoiceStatus = '';
        $this->aiInvoiceError = '';
        $this->aiInvoiceHeader = null;
        $this->aiInvoiceLines = [];
        $this->resetValidation('aiInvoiceFile');
    }

    public function extractAiInvoice(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);
        abort_if(property_exists($this, 'viewMode') && $this->viewMode, 403);

        $this->aiInvoiceError = '';
        $this->aiInvoiceStatus = 'Reading vendor invoice with POS AI…';
        $this->validate([
            'aiInvoiceFile' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4800'],
        ]);

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $result = InvoiceExtractionService::forCompany($company)->extract($this->aiInvoiceFile);

        if (empty($result['success'])) {
            $this->aiInvoiceStatus = '';
            $this->aiInvoiceError = (string) ($result['msg'] ?? 'Could not read invoice.');

            return;
        }

        $matched = InvoiceExtractionService::forCompany($company)->matchToCatalog($result['data'] ?? []);
        $this->aiInvoiceHeader = [
            'supplier_name' => $matched['supplier_name'] ?? null,
            'supplier_id' => $matched['supplier_id'] ?? null,
            'ref_no' => $matched['ref_no'] ?? null,
            'invoice_date' => $matched['invoice_date'] ?? null,
            'total' => $matched['total'] ?? null,
        ];
        $this->aiInvoiceLines = $matched['lines'] ?? [];
        $this->aiInvoiceStatus = count($this->aiInvoiceLines) > 0
            ? 'Review matched lines, then Insert into PO.'
            : 'No line items found on this document.';
    }

    public function toggleAiInvoiceLine(int $index): void
    {
        if (! isset($this->aiInvoiceLines[$index])) {
            return;
        }
        $this->aiInvoiceLines[$index]['selected'] = ! (bool) ($this->aiInvoiceLines[$index]['selected'] ?? false);
    }

    public function applyAiInvoiceToPurchaseOrder(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);
        abort_if(property_exists($this, 'viewMode') && $this->viewMode, 403);

        if ($this->aiInvoiceLines === []) {
            $this->aiInvoiceError = 'Nothing to insert. Extract an invoice first.';

            return;
        }

        // Catalog-only hosts (Items list) — review matches; no PO lines to fill.
        if (! property_exists($this, 'lines') || ! is_array($this->lines ?? null)) {
            $matched = collect($this->aiInvoiceLines)->where('item_id', '>', 0)->count();
            $unmatched = count($this->aiInvoiceLines) - $matched;
            $this->closeAiInvoiceModal();
            session()->flash(
                'status',
                "POS AI found {$matched} matched and {$unmatched} unmatched line(s). Use Create item on unmatched rows, then scan again from a Purchase Order to insert."
            );

            return;
        }

        $header = $this->aiInvoiceHeader ?? [];
        if (! empty($header['supplier_id']) && property_exists($this, 'supplier_id')) {
            $this->supplier_id = (int) $header['supplier_id'];
        }
        if (! empty($header['ref_no']) && property_exists($this, 'reference_no') && trim((string) $this->reference_no) === '') {
            $this->reference_no = (string) $header['ref_no'];
        }
        if (! empty($header['invoice_date']) && property_exists($this, 'requisition_date')) {
            // Keep existing PO date unless empty
            if (trim((string) ($this->requisition_date ?? '')) === '') {
                $this->requisition_date = (string) $header['invoice_date'];
            }
        }

        $companyId = (int) auth()->user()->company_id;
        $inserted = 0;

        foreach ($this->aiInvoiceLines as $row) {
            if (empty($row['selected'])) {
                continue;
            }
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $item = Item::query()->where('company_id', $companyId)->find($itemId);
            if (! $item) {
                continue;
            }

            $qty = max(0.01, (float) ($row['quantity'] ?? 1));
            $cost = (float) ($row['unit_price'] ?? 0);

            if (method_exists($this, 'applyAiMatchedItem')) {
                $this->applyAiMatchedItem($item, $qty, $cost);
            } elseif (method_exists($this, 'applyItemToOrder')) {
                $this->applyItemToOrder($item);
                // Override last filled line qty/cost when possible
                $this->patchLastAiLine($item->id, $qty, $cost);
            }
            $inserted++;
        }

        if ($inserted === 0) {
            $this->aiInvoiceError = 'No matched catalog items were selected. Match items or create them first, then re-scan.';

            return;
        }

        if (property_exists($this, 'activeTab')) {
            $this->activeTab = 'items';
        }

        $this->closeAiInvoiceModal();
        session()->flash('status', "POS AI inserted {$inserted} line(s) from the vendor invoice. Review costs, then save the PO. Receiving still updates stock as usual.");
    }

    protected function patchLastAiLine(int $itemId, float $qty, float $cost): void
    {
        if (! property_exists($this, 'lines') || ! is_array($this->lines)) {
            return;
        }
        $lines = array_values($this->lines);
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if ((int) ($lines[$i]['item_id'] ?? 0) !== $itemId) {
                continue;
            }
            $lines[$i]['qty_ordered'] = method_exists($this, 'formatQty')
                ? $this->formatQty($qty)
                : (string) $qty;
            if ($cost > 0) {
                $lines[$i]['unit_cost'] = method_exists($this, 'formatTwoDecimals')
                    ? $this->formatTwoDecimals($cost)
                    : number_format($cost, 2, '.', '');
            }
            $this->lines = $lines;

            return;
        }
    }
}
