<?php

namespace App\Services\JapsAi;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CompanyMailConfig;
use App\Services\InventoryService;
use App\Services\ItemPriceHistoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Persist a New purchase order from a POS AI vendor-invoice match.
 * Invoice billed amounts become PO line costs. Status stays New (not sent).
 */
class VendorInvoicePurchaseOrderService
{
    public const ADMIN_ALERT_CACHE = 'pos-ai-admin-alerts:';

    /**
     * @param  array<string, mixed>  $matched
     * @return array{success: bool, po?: PurchaseOrder, edit_url?: string, matched_count: int, unmatched_count: int, cost_updated: int, alerts: list<string>, msg: string}
     */
    public function createFromMatch(Company $company, User $user, array $matched): array
    {
        $lines = is_array($matched['lines'] ?? null) ? $matched['lines'] : [];
        $catalogLines = collect($lines)->filter(fn ($row) => (int) ($row['item_id'] ?? 0) > 0)->values();
        $unmatchedCount = count($lines) - $catalogLines->count();
        $supplierId = (int) ($matched['supplier_id'] ?? 0);
        $missingBrief = $this->missingCatalogBrief($matched);

        if ($supplierId <= 0) {
            $this->notifyCatalogGaps($company, $missingBrief, null);

            return [
                'success' => false,
                'matched_count' => $catalogLines->count(),
                'unmatched_count' => $unmatchedCount,
                'cost_updated' => 0,
                'alerts' => [],
                'missing_brief' => $missingBrief,
                'msg' => $missingBrief !== ''
                    ? $missingBrief
                    : 'Could not create a purchase order: the supplier on the document was not in your supplier list.',
            ];
        }

        if ($catalogLines->isEmpty()) {
            $this->notifyCatalogGaps($company, $missingBrief, null);

            return [
                'success' => false,
                'matched_count' => 0,
                'unmatched_count' => $unmatchedCount,
                'cost_updated' => 0,
                'alerts' => [],
                'missing_brief' => $missingBrief,
                'msg' => $missingBrief !== ''
                    ? $missingBrief
                    : 'Could not create a purchase order: no invoice lines were in your item list.',
            ];
        }

        $invoiceDate = $this->validDate($matched['invoice_date'] ?? null) ?? now()->toDateString();
        $refNo = trim((string) ($matched['ref_no'] ?? '')) ?: null;
        $supplierName = trim((string) ($matched['supplier_name'] ?? '')) ?: 'supplier';

        $existing = null;
        if ($refNo) {
            $existing = PurchaseOrder::query()
                ->where('company_id', $company->id)
                ->where('supplier_id', $supplierId)
                ->where('reference_no', $refNo)
                ->where('status', 'New')
                ->latest('id')
                ->first();
        }

        $po = null;
        $syncItemIds = [];
        $costUpdated = 0;
        $alerts = [];
        $previousItemIds = [];

        DB::transaction(function () use (
            $company, $user, $matched, $catalogLines, $supplierId, $invoiceDate, $refNo, $supplierName, $missingBrief,
            $existing, &$po, &$syncItemIds, &$costUpdated, &$alerts, &$previousItemIds
        ) {
            if ($existing) {
                $existing->loadMissing('lines');
                $previousItemIds = $existing->lines->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->all();
                $existing->lines()->delete();
                $po = $existing;
            }

            $itemIds = $catalogLines->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->all();
            $items = Item::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $history = app(ItemPriceHistoryService::class);
            $subtotal = 0.0;
            $lineNo = 0;
            $prepared = [];
            $usedIds = [];

            foreach ($catalogLines as $row) {
                $item = $items->get((int) $row['item_id']);
                if (! $item) {
                    $alerts[] = 'Catalog item id '.(int) $row['item_id'].' could not be loaded — invoice row skipped.';

                    continue;
                }

                $qty = max(0.01, (float) ($row['quantity'] ?? 1));
                $invoiceCost = $this->invoiceUnitCost($row, $qty);
                $prevCost = (float) ($item->current_cost ?: $item->standard_cost ?: $item->last_cost);
                $cost = $invoiceCost;
                if ($cost <= 0) {
                    $cost = max(0, $prevCost);
                    $alerts[] = ($item->item_code ?: 'Item').': invoice had no unit price; PO used last cost $'.number_format($cost, 2).'.';
                }

                $ext = round($qty * $cost, 4);
                $subtotal += $ext;
                $lineNo++;
                $prepared[] = [
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description ?: (string) ($row['name'] ?? ''),
                    'uom' => $item->unit_of_measure,
                    'qty_ordered' => $qty,
                    'qty_received' => 0,
                    'unit_cost' => round($cost, 4),
                    'extended_cost' => $ext,
                    'line_no' => $lineNo,
                    '_invoice_cost' => $invoiceCost,
                    '_prev_cost' => $prevCost,
                ];
                $usedIds[] = (int) $item->id;
            }

            if ($prepared === []) {
                throw new \RuntimeException('No catalog items could be loaded for this invoice.');
            }

            $skipped = count($matched['lines'] ?? []) - count($prepared);
            $missingSkus = collect($matched['lines'] ?? [])
                ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) <= 0)
                ->map(function ($row) {
                    $sku = trim((string) ($row['sku'] ?? ''));
                    $name = trim((string) ($row['name'] ?? ''));

                    return $sku !== '' ? $sku : $name;
                })
                ->filter()
                ->take(12)
                ->implode(', ');
            $tax = max(0, (float) ($matched['tax_amount'] ?? 0));
            $invoiceTotal = isset($matched['total']) && is_numeric($matched['total']) ? (float) $matched['total'] : null;
            $poTotal = round($subtotal + $tax, 4);

            $comments = 'Created from POS AI vendor invoice for '.$supplierName
                .'. Line costs are the invoice billed amounts. Status is New — not emailed to the vendor.'
                .($skipped > 0 ? " {$skipped} item(s) not in item list"
                    .($missingSkus !== '' ? ': '.$missingSkus : '')
                    .'.' : '');

            $payload = [
                'company_id' => $company->id,
                'order_type' => 'Standard',
                'reference_no' => $refNo,
                'requisition_date' => $invoiceDate,
                'status' => 'New',
                'buyer_id' => $user->id,
                'ship_to_site_id' => $user->site_id ?: ($po?->ship_to_site_id),
                'supplier_id' => $supplierId,
                'comments' => $comments,
                'subtotal' => round($subtotal, 4),
                'trade_discount' => 0,
                'freight' => 0,
                'miscellaneous' => 0,
                'tax' => $tax,
                'total' => $poTotal,
            ];

            if ($po) {
                $po->update($payload);
                $po = $po->fresh();
            } else {
                $payload['po_number'] = PurchaseOrder::nextNumber((int) $company->id);
                $po = PurchaseOrder::query()->create($payload);
            }

            foreach ($prepared as $line) {
                $invoiceCost = (float) $line['_invoice_cost'];
                $prevCost = (float) $line['_prev_cost'];
                unset($line['_invoice_cost'], $line['_prev_cost']);
                $po->lines()->create($line);

                Item::query()->where('id', $line['item_id'])->update([
                    'last_ordered_at' => $invoiceDate,
                ]);

                if ($invoiceCost > 0) {
                    $item = $items->get((int) $line['item_id']);
                    if ($item && $history->applyPoUnitCost(
                        $item,
                        $invoiceCost,
                        (int) $po->id,
                        (string) $po->po_number,
                        $supplierId
                    )) {
                        $costUpdated++;
                        $items->put((int) $item->id, $item->fresh());
                        if (abs($prevCost - $invoiceCost) > 0.00005 && $prevCost > 0) {
                            $alerts[] = $line['item_code'].': item cost updated from $'.number_format($prevCost, 2)
                                .' to invoice $'.number_format($invoiceCost, 2).'. Sales price was not changed.';
                        }
                    }
                }
            }

            if ($invoiceTotal !== null && $invoiceTotal > 0 && abs($invoiceTotal - $poTotal) > max(0.05, $poTotal * 0.02)) {
                $alerts[] = 'Invoice total $'.number_format($invoiceTotal, 2).' vs PO total $'.number_format($poTotal, 2).'.';
            }
            if ($skipped > 0) {
                $alerts[] = $skipped.' invoice line(s) were not added because they are not in the item list.';
                if ($missingBrief !== '') {
                    $comments .= ' Missing from item list: see POS AI brief.';
                }
            }

            $syncItemIds = array_values(array_unique(array_merge($usedIds, $previousItemIds)));
        });

        if ($syncItemIds !== []) {
            app(InventoryService::class)->syncOnOrderQty($syncItemIds);
        }

        $this->notifyAdmins($company, $po, $costUpdated, $alerts, $unmatchedCount);
        if ($missingBrief !== '') {
            $this->notifyCatalogGaps($company, $missingBrief, $po);
        }

        $editUrl = route('purchasing.orders.edit', $po);

        return [
            'success' => true,
            'po' => $po,
            'edit_url' => $editUrl,
            'matched_count' => $catalogLines->count(),
            'unmatched_count' => $unmatchedCount,
            'cost_updated' => $costUpdated,
            'alerts' => $alerts,
            'missing_brief' => $missingBrief,
            'msg' => 'Purchase order '.$po->po_number.' saved as New from the vendor invoice. Line costs are the invoice amounts.'
                .($costUpdated > 0 ? " Updated PO cost on {$costUpdated} item(s)." : '')
                .($unmatchedCount > 0 ? " {$unmatchedCount} invoice item(s) were not in your item list — see the brief." : '')
                .' Review, then receive to update stock. Nothing was sent to the vendor.',
        ];
    }

    /**
     * @param  array<string, mixed>  $matched
     * @param  array{success: bool, po?: PurchaseOrder, edit_url?: string, matched_count: int, unmatched_count: int, cost_updated?: int, alerts?: list<string>, msg: string}  $created
     */
    public function formatChatReply(Company $company, array $matched, array $created): string
    {
        $supplier = trim((string) ($matched['supplier_name'] ?? '')) ?: '—';
        $totalLines = count($matched['lines'] ?? []);
        $matchedCount = (int) ($created['matched_count'] ?? 0);

        $reply = "Read vendor invoice for **{$company->name}**.\n\n"
            ."- **Supplier:** {$supplier}"
            .(! empty($matched['supplier_id']) ? " _(in supplier list)_\n" : " _(not in supplier list)_\n")
            ."- **Lines found:** {$totalLines} ({$matchedCount} in item list, "
            .(int) ($created['unmatched_count'] ?? max(0, $totalLines - $matchedCount))." not in item list)\n";

        $missingBrief = (string) ($created['missing_brief'] ?? $this->missingCatalogBrief($matched));
        if ($missingBrief !== '') {
            $reply .= "\n".$missingBrief."\n";
        }

        if (! empty($created['success']) && ! empty($created['po'])) {
            $po = $created['po'];
            $costUpdated = (int) ($created['cost_updated'] ?? 0);
            $reply .= "\n### Purchase order created\n"
                ."**{$po->po_number}** is saved as **New**. Open it to review:\n"
                .($created['edit_url'] ?? '')."\n\n"
                .'PO line costs are the **invoice billed amounts**. '
                .($costUpdated > 0 ? "Item PO cost was updated on **{$costUpdated}** SKU(s). " : '')
                .'It is not sent to the vendor. Receiving still updates stock.';
            if ((int) ($created['unmatched_count'] ?? 0) > 0) {
                $reply .= ' Unmatched items were left off the PO — listed above.';
            }
            $alerts = array_values(array_filter((array) ($created['alerts'] ?? [])));
            if ($alerts !== []) {
                $reply .= "\n\n### Admin alert\nAdmins were notified. Review:\n"
                    .implode("\n", array_map(fn ($a) => '- '.$a, array_slice($alerts, 0, 12)));
            }
        } else {
            $createUrl = route('purchasing.orders.create', ['ai_invoice' => 1]);
            $msg = trim((string) ($created['msg'] ?? ''));
            if ($msg !== '' && $msg !== $missingBrief) {
                $reply .= "\n".$msg."\n";
            }
            $reply .= "\nNo purchase order was created until the supplier and items are in your lists.\n"
                ."Open **New Purchase Order** after you add them:\n{$createUrl}";
        }

        try {
            $note = PosAiIntelligenceService::forCompany((int) $company->id)
                ->scannedInvoiceNote($matched, $created['po'] ?? null);
            if ($note !== '') {
                $reply .= "\n\n".$note;
            }
        } catch (\Throwable) {
            // Scan result still stands if the 3-way note cannot run.
        }

        return $reply;
    }

    /**
     * Plain briefing when the invoice supplier or SKUs are not in this company's lists.
     *
     * @param  array<string, mixed>  $matched
     */
    public function missingCatalogBrief(array $matched): string
    {
        $blocks = [];
        $supplierId = (int) ($matched['supplier_id'] ?? 0);
        $supplierName = trim((string) ($matched['supplier_name'] ?? ''));
        if ($supplierId <= 0) {
            if ($supplierName === '') {
                $blocks[] = "**Supplier not found**\nThe invoice did not match any vendor in **Purchasing → Suppliers**. Add the supplier, then scan again.";
            } else {
                $blocks[] = "**Supplier not found in list**\n- Invoice name: **{$supplierName}**\nAdd this vendor under **Purchasing → Suppliers**, then scan again (or pick them on the PO).";
            }
        }

        $missing = collect($matched['lines'] ?? [])
            ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) <= 0)
            ->values();
        if ($missing->isNotEmpty()) {
            $rows = ["**Items not found in item list ({$missing->count()})**"];
            foreach ($missing->take(25) as $row) {
                $sku = trim((string) ($row['sku'] ?? '')) ?: 'no SKU';
                $name = trim((string) ($row['name'] ?? '')) ?: '—';
                $qty = number_format((float) ($row['quantity'] ?? 0), 2);
                $price = (float) ($row['unit_price'] ?? 0);
                $rows[] = "- **{$sku}** — {$name} · qty {$qty} @ $".number_format($price, 2);
            }
            if ($missing->count() > 25) {
                $rows[] = '- … and '.($missing->count() - 25).' more.';
            }
            $rows[] = 'Create these under **Inventory → Items → New**, then scan the invoice again to put them on the PO.';
            $blocks[] = implode("\n", $rows);
        }

        if ($blocks === []) {
            return '';
        }

        return "### Not in your list\n".implode("\n\n", $blocks);
    }

    public const REVIEW_SESSION = 'pos_ai_invoice_review';

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $matched
     */
    public static function storeReview(array $raw, array $matched, bool $listsReady = false): void
    {
        session([
            self::REVIEW_SESSION => [
                'raw' => $raw,
                'matched' => $matched,
                'lists_ready' => $listsReady,
            ],
        ]);
    }

    /**
     * @return array{raw: array<string, mixed>, matched: array<string, mixed>, lists_ready: bool}|null
     */
    public static function review(): ?array
    {
        $row = session(self::REVIEW_SESSION);
        if (! is_array($row) || empty($row['matched'])) {
            return null;
        }

        return [
            'raw' => is_array($row['raw'] ?? null) ? $row['raw'] : [],
            'matched' => $row['matched'],
            'lists_ready' => (bool) ($row['lists_ready'] ?? false),
        ];
    }

    public static function clearReview(): void
    {
        session()->forget(self::REVIEW_SESSION);
    }

    public static function listsAreReady(array $matched): bool
    {
        if ((int) ($matched['supplier_id'] ?? 0) <= 0) {
            return false;
        }
        $lines = (array) ($matched['lines'] ?? []);
        if ($lines === []) {
            return false;
        }

        return collect($lines)->every(fn ($row) => (int) ($row['item_id'] ?? 0) > 0);
    }

    /**
     * Screenshot-style scan summary before any PO is created.
     *
     * @param  array<string, mixed>  $matched
     */
    public function reviewReply(Company $company, array $matched): string
    {
        $lines = (array) ($matched['lines'] ?? []);
        $count = count($lines);
        $matchedCount = collect($lines)->where('item_id', '>', 0)->count();
        $supplier = trim((string) ($matched['supplier_name'] ?? '')) ?: 'Unknown supplier';
        $ref = trim((string) ($matched['ref_no'] ?? ''));
        $total = $matched['total'] ?? null;
        $supplierOk = (int) ($matched['supplier_id'] ?? 0) > 0;

        $head = "Read **{$count}** item(s) from **{$supplier}**";
        if ($ref !== '') {
            $head .= ", ref {$ref}";
        }
        if (is_numeric($total)) {
            $head .= ', total '.number_format((float) $total, 2);
        }
        $head .= ' for **'.$company->name.'**.';

        $unmatchedNames = collect($lines)
            ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) <= 0)
            ->map(fn ($row) => trim((string) ($row['name'] ?? $row['sku'] ?? '')))
            ->filter()
            ->unique()
            ->take(12)
            ->values();

        $reply = $head."\n";
        $reply .= $supplierOk
            ? "Supplier is in your supplier list.\n"
            : "Supplier is **not** in your supplier list.\n";
        $reply .= "{$matchedCount} of {$count} line(s) are in your item list.\n";

        if ($unmatchedNames->isNotEmpty()) {
            $reply .= 'Unmatched: '.$unmatchedNames->implode(', ');
            if (collect($lines)->where('item_id', '<=', 0)->count() > 12) {
                $reply .= '…';
            }
            $reply .= "\n";
        }

        $reply .= "\n".$this->missingCatalogBrief($matched);

        if (self::listsAreReady($matched)) {
            $reply .= "\n\n**All are OK** — supplier and items are in your lists.\nClick **Confirm & create PO** to save a New purchase order. Nothing is sent to the vendor.";
        } else {
            $reply .= "\n\nClick **Review & add to lists** to auto-add the missing supplier and items. When the reply says all are OK, click **Confirm & create PO**.";
        }

        return $reply;
    }

    /**
     * Create missing supplier + items from the invoice, then rematch.
     *
     * @param  array<string, mixed>  $matched
     * @return array{matched: array<string, mixed>, added_supplier: ?string, added_items: list<string>, all_ok: bool, reply: string}
     */
    public function addMissingToLists(Company $company, array $raw, array $matched): array
    {
        $extractor = InvoiceExtractionService::forCompany($company);
        $addedSupplier = null;
        $addedItems = [];

        DB::transaction(function () use ($company, &$matched, &$addedSupplier, &$addedItems) {
            if ((int) ($matched['supplier_id'] ?? 0) <= 0) {
                $name = trim((string) ($matched['supplier_name'] ?? ''));
                if ($name !== '') {
                    $supplier = Supplier::query()->create([
                        'company_id' => $company->id,
                        'supplier_id' => $this->nextSupplierCode((int) $company->id),
                        'name' => mb_substr($name, 0, 191),
                        'is_inactive' => false,
                        'is_tobacco_supplier' => false,
                    ]);
                    $matched['supplier_id'] = $supplier->id;
                    $addedSupplier = $supplier->name.' ('.$supplier->supplier_id.')';
                }
            }

            $supplierId = (int) ($matched['supplier_id'] ?? 0);

            foreach ($matched['lines'] ?? [] as $i => $row) {
                if ((int) ($row['item_id'] ?? 0) > 0) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($name === '' && $sku === '') {
                    continue;
                }
                $cost = $this->invoiceUnitCost($row, max(0.01, (float) ($row['quantity'] ?? 1)));
                $code = $this->uniqueItemCode((int) $company->id, $sku, $name);
                $item = Item::query()->create([
                    'company_id' => $company->id,
                    'item_code' => $code,
                    'item_type' => 'Standard Item',
                    'description' => mb_substr($name !== '' ? $name : $code, 0, 191),
                    'list_price' => 0,
                    'standard_cost' => $cost,
                    'current_cost' => $cost,
                    'last_cost' => $cost,
                    'average_cost' => $cost,
                    'quantity_in_stock' => 0,
                    'can_order' => true,
                    'can_sell' => true,
                    'is_inactive' => false,
                    'primary_upc' => $sku !== '' && strlen($sku) <= 32 ? $sku : null,
                ]);
                if ($supplierId > 0) {
                    ItemSupplier::query()->create([
                        'item_id' => $item->id,
                        'supplier_id' => $supplierId,
                        'supplier_item_code' => $sku !== '' ? mb_substr($sku, 0, 64) : null,
                        'last_cost' => $cost,
                        'avg_cost' => $cost,
                        'is_default' => true,
                        'sort_order' => 0,
                    ]);
                }
                $addedItems[] = $item->item_code.' — '.$item->description;
                $matched['lines'][$i]['item_id'] = $item->id;
                $matched['lines'][$i]['item_code'] = $item->item_code;
                $matched['lines'][$i]['status'] = 'matched';
                $matched['lines'][$i]['selected'] = true;
            }
        });

        $rematch = $extractor->matchToCatalog(array_merge($raw, [
            'supplier_name' => $matched['supplier_name'] ?? ($raw['supplier_name'] ?? null),
            'lines' => $raw['lines'] ?? $matched['lines'] ?? [],
        ]));
        if ((int) ($rematch['supplier_id'] ?? 0) <= 0 && (int) ($matched['supplier_id'] ?? 0) > 0) {
            $rematch['supplier_id'] = $matched['supplier_id'];
        }
        foreach ($rematch['lines'] ?? [] as $i => $line) {
            if ((int) ($line['item_id'] ?? 0) > 0) {
                continue;
            }
            $fallback = $matched['lines'][$i] ?? null;
            if (is_array($fallback) && (int) ($fallback['item_id'] ?? 0) > 0) {
                $rematch['lines'][$i] = array_merge($line, [
                    'item_id' => $fallback['item_id'],
                    'item_code' => $fallback['item_code'] ?? $line['item_code'] ?? null,
                    'status' => 'matched',
                    'selected' => true,
                ]);
            }
        }

        $allOk = self::listsAreReady($rematch);
        self::storeReview($raw, $rematch, $allOk);

        $reply = '';
        if ($addedSupplier) {
            $reply .= "Supplier added to the list: **{$addedSupplier}**. OK.\n";
        }
        if ($addedItems !== []) {
            $reply .= count($addedItems).' item(s) added to the item list. OK.\n';
            foreach (array_slice($addedItems, 0, 20) as $label) {
                $reply .= '- '.$label."\n";
            }
        }
        if ($addedSupplier === null && $addedItems === []) {
            $reply .= "Nothing new to add — supplier and items were already in your lists.\n";
        }
        $reply .= $allOk
            ? "\n**All are OK.** Click **Confirm & create PO** to save the New purchase order."
            : "\nSome lines still could not be added. Check the names and try Confirm only after they show as matched.";

        return [
            'matched' => $rematch,
            'added_supplier' => $addedSupplier,
            'added_items' => $addedItems,
            'all_ok' => $allOk,
            'reply' => $reply,
        ];
    }

    private function nextSupplierCode(int $companyId): string
    {
        $last = (string) (Supplier::query()->where('company_id', $companyId)->orderByDesc('id')->value('supplier_id') ?? '');
        $n = (int) preg_replace('/\D/', '', $last);
        $next = $n > 0 ? $n + 1 : 1001;
        $code = 'V'.$next;
        while (Supplier::query()->where('company_id', $companyId)->where('supplier_id', $code)->exists()) {
            $next++;
            $code = 'V'.$next;
        }

        return $code;
    }

    private function uniqueItemCode(int $companyId, string $sku, string $name): string
    {
        $candidates = [];
        if ($sku !== '' && preg_match('/^[A-Za-z0-9._\-]{1,32}$/', $sku)) {
            $candidates[] = $sku;
        }
        $fromName = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'ITEM', 0, 10));
        $candidates[] = $fromName;
        foreach ($candidates as $base) {
            $code = $base;
            $i = 0;
            while (Item::query()->where('company_id', $companyId)->where('item_code', $code)->exists()) {
                $i++;
                $code = mb_substr($base, 0, 12).'-'.$i;
            }
            if ($i === 0 || $base === $fromName) {
                return $code;
            }
        }

        return 'AI'.now()->format('His').random_int(10, 99);
    }

    /**
     * @return list<array{at: string, po_number: string, text: string}>
     */
    public static function adminAlerts(int $companyId): array
    {
        $rows = Cache::get(self::ADMIN_ALERT_CACHE.$companyId, []);
        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[$i]['text'] = self::displayMoneyTwoDecimals((string) ($row['text'] ?? ''));
        }

        return $rows;
    }

    public static function displayMoneyTwoDecimals(string $text): string
    {
        $text = preg_replace_callback(
            '/\$(\d{1,3}(?:,\d{3})*|\d+)\.(\d+)/',
            function (array $m): string {
                $n = (float) str_replace(',', '', $m[1].'.'.$m[2]);

                return '$'.number_format($n, 2);
            },
            $text
        ) ?? $text;

        return preg_replace_callback(
            '/(?<![\d.])(\d+)\.(\d{3,})(?![\d])/',
            function (array $m): string {
                if (strlen($m[1]) >= 7) {
                    return $m[0];
                }

                return number_format((float) ($m[1].'.'.$m[2]), 2);
            },
            $text
        ) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function invoiceUnitCost(array $row, float $qty): float
    {
        $unit = (float) ($row['unit_price'] ?? 0);
        $ext = (float) ($row['line_total'] ?? 0);
        if ($unit <= 0 && $ext > 0 && $qty > 0) {
            $unit = $ext / $qty;
        }

        return round(max(0, $unit), 4);
    }

    /**
     * @param  list<string>  $alerts
     */
    private function notifyAdmins(Company $company, PurchaseOrder $po, int $costUpdated, array $alerts, int $unmatchedCount): void
    {
        if ($alerts === [] && $costUpdated === 0 && $unmatchedCount === 0) {
            return;
        }

        $summary = 'POS AI PO '.$po->po_number.' from vendor invoice. '
            .$costUpdated.' item cost(s) updated from invoice amounts.'
            .($unmatchedCount > 0 ? ' '.$unmatchedCount.' line(s) not added (no catalog match).' : '');
        if ($alerts !== []) {
            $summary .= ' '.implode(' ', array_slice($alerts, 0, 8));
        }

        $stored = self::adminAlerts((int) $company->id);
        array_unshift($stored, [
            'at' => now()->toDateTimeString(),
            'po_number' => (string) $po->po_number,
            'text' => self::displayMoneyTwoDecimals($summary),
        ]);
        Cache::put(self::ADMIN_ALERT_CACHE.$company->id, array_slice($stored, 0, 25), now()->addDays(14));

        $body = $summary."\n\nOpen: ".route('purchasing.orders.edit', $po)
            ."\nSales prices were not changed. This PO was not sent to the vendor.";

        try {
            CompanyMailConfig::apply($company);
            $to = [];
            $companyEmail = trim((string) $company->email);
            if ($companyEmail !== '' && filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                $to[] = $companyEmail;
            }
            foreach (User::query()->where('company_id', $company->id)->where('is_active', true)->with('role')->get() as $admin) {
                if (! $admin->isAdmin()) {
                    continue;
                }
                $email = trim((string) $admin->email);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $to[] = $email;
                }
            }
            $to = array_values(array_unique($to));
            if ($to === []) {
                return;
            }
            Mail::raw($body, function ($message) use ($company, $to, $po) {
                $message->to($to)
                    ->subject('POS AI: invoice PO '.$po->po_number.' — cost update — '.$company->name);
            });
        } catch (\Throwable) {
            // PO is already saved; mail is best-effort.
        }
    }

    private function notifyCatalogGaps(Company $company, string $brief, ?PurchaseOrder $po): void
    {
        $plain = trim(preg_replace('/[*_#]+/', '', $brief) ?? $brief);
        if ($plain === '') {
            return;
        }

        $stored = self::adminAlerts((int) $company->id);
        array_unshift($stored, [
            'at' => now()->toDateTimeString(),
            'po_number' => $po?->po_number ? (string) $po->po_number : '—',
            'text' => self::displayMoneyTwoDecimals(mb_substr($plain, 0, 400)),
        ]);
        Cache::put(self::ADMIN_ALERT_CACHE.$company->id, array_slice($stored, 0, 25), now()->addDays(14));

        $body = $plain;
        if ($po) {
            $body .= "\n\nPO: ".$po->po_number."\n".route('purchasing.orders.edit', $po);
        }

        try {
            CompanyMailConfig::apply($company);
            $to = [];
            $companyEmail = trim((string) $company->email);
            if ($companyEmail !== '' && filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                $to[] = $companyEmail;
            }
            foreach (User::query()->where('company_id', $company->id)->where('is_active', true)->with('role')->get() as $admin) {
                if (! $admin->isAdmin()) {
                    continue;
                }
                $email = trim((string) $admin->email);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $to[] = $email;
                }
            }
            $to = array_values(array_unique($to));
            if ($to === []) {
                return;
            }
            Mail::raw($body, function ($message) use ($company, $to, $po) {
                $message->to($to)
                    ->subject('POS AI: supplier/item not in list'.($po ? ' — PO '.$po->po_number : '').' — '.$company->name);
            });
        } catch (\Throwable) {
            // Briefing in chat still stands if mail cannot send.
        }
    }

    private function validDate(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        return $raw;
    }
}
