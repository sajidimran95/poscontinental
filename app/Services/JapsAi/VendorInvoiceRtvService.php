<?php

namespace App\Services\JapsAi;

use App\Models\Company;
use App\Models\Item;
use App\Models\ReturnToVendor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persist a New Return to Vendor from a POS AI credit-memo / return scan.
 * Status stays New — stock only changes when the user later processes the RTV
 * (same as manual RTV save; this service never calls processRtv).
 */
class VendorInvoiceRtvService
{
    /**
     * @param  array<string, mixed>  $matched
     * @return array{success: bool, rtv?: ReturnToVendor, edit_url?: string, matched_count: int, unmatched_count: int, msg: string}
     */
    public function createFromMatch(Company $company, User $user, array $matched): array
    {
        // Mixed invoices keep purchases in lines and returns in return_lines.
        $sourceKey = ! empty($matched['return_lines']) ? 'return_lines' : 'lines';
        $lines = is_array($matched[$sourceKey] ?? null) ? $matched[$sourceKey] : [];
        $catalogLines = collect($lines)->filter(fn ($row) => (int) ($row['item_id'] ?? 0) > 0)->values();
        $unmatchedCount = count($lines) - $catalogLines->count();
        $supplierId = (int) ($matched['supplier_id'] ?? 0);
        $poSvc = app(VendorInvoicePurchaseOrderService::class);
        $missingBrief = $poSvc->missingCatalogBrief($matched);

        if ($supplierId <= 0) {
            return [
                'success' => false,
                'matched_count' => $catalogLines->count(),
                'unmatched_count' => $unmatchedCount,
                'msg' => $missingBrief !== ''
                    ? $missingBrief
                    : 'Could not create RTV: the supplier on the document was not in your supplier list.',
            ];
        }

        $kind = (string) ($matched['document_kind'] ?? 'rtv');
        // Mixed invoices: PO amount check covers purchases; RTV only needs matched return rows.
        $amountsOk = $kind === 'mixed' ? true : VendorInvoicePurchaseOrderService::amountsMatch($matched);
        if (! $amountsOk || ($unmatchedCount > 0 && $catalogLines->isNotEmpty())) {
            return [
                'success' => false,
                'matched_count' => $catalogLines->count(),
                'unmatched_count' => $unmatchedCount,
                'msg' => $amountsOk
                    ? "Cannot create the RTV yet: {$unmatchedCount} return item(s) are not in your item list. Click **Review & add to lists**, then Confirm."
                    : VendorInvoicePurchaseOrderService::amountMismatchText($matched),
            ];
        }

        if ($catalogLines->isEmpty()) {
            return [
                'success' => false,
                'matched_count' => 0,
                'unmatched_count' => $unmatchedCount,
                'msg' => $missingBrief !== ''
                    ? $missingBrief
                    : 'Could not create RTV: no document lines were in your item list.',
            ];
        }

        $invoiceDate = $this->validDate($matched['invoice_date'] ?? null) ?? now()->toDateString();
        $refNo = trim((string) ($matched['ref_no'] ?? '')) ?: null;
        $supplierName = trim((string) ($matched['supplier_name'] ?? '')) ?: 'supplier';

        $existing = null;
        if ($refNo) {
            $existing = ReturnToVendor::query()
                ->where('company_id', $company->id)
                ->where('supplier_id', $supplierId)
                ->where('reference_no', $refNo)
                ->where('status', 'New')
                ->latest('id')
                ->first();
        }

        $rtv = null;

        DB::transaction(function () use (
            $company, $user, $matched, $catalogLines, $supplierId, $invoiceDate, $refNo, $supplierName,
            $existing, &$rtv
        ) {
            if ($existing) {
                $existing->lines()->delete();
                $rtv = $existing;
            }

            $itemIds = $catalogLines->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->all();
            $items = Item::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $lineNo = 0;
            $prepared = [];

            foreach ($catalogLines as $row) {
                $item = $items->get((int) $row['item_id']);
                if (! $item) {
                    continue;
                }

                $qty = max(0.01, abs((float) ($row['quantity'] ?? 1)));
                $ext = (float) ($row['line_total'] ?? 0);
                $cost = $ext > 0 ? abs($ext) / $qty : abs((float) ($row['unit_price'] ?? 0));
                if ($cost <= 0) {
                    $cost = max(0, (float) ($item->current_cost ?: $item->standard_cost ?: $item->last_cost));
                }
                $lineExt = round($qty * $cost, 4);
                $subtotal += $lineExt;
                $lineNo++;
                $prepared[] = [
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description ?: (string) ($row['name'] ?? ''),
                    'uom' => $item->unit_of_measure,
                    'qty' => $qty,
                    'unit_cost' => round($cost, 4),
                    'extended_cost' => $lineExt,
                    'line_no' => $lineNo,
                ];
            }

            if ($prepared === []) {
                throw new \RuntimeException('No catalog items could be loaded for this credit memo.');
            }

            $freight = abs((float) ($matched['freight'] ?? 0));
            $total = round($subtotal + $freight, 4);
            $comments = 'Created from POS AI credit memo / return for '.$supplierName
                .'. Status is New — stock is unchanged until you Process the RTV.';

            $payload = [
                'company_id' => $company->id,
                'rtv_date' => $invoiceDate,
                'status' => 'New',
                'reference_no' => $refNo,
                'supplier_id' => $supplierId,
                'requested_by_id' => $user->id,
                'site_id' => $user->site_id ?: ($rtv?->site_id),
                'comments' => $comments,
                'subtotal' => round($subtotal, 4),
                'discount' => 0,
                'freight' => round($freight, 4),
                'total' => $total,
            ];

            if ($rtv) {
                $payload['rtv_number'] = $rtv->rtv_number;
                $rtv->update($payload);
                $rtv = $rtv->fresh();
            } else {
                $payload['rtv_number'] = ReturnToVendor::nextNumber((int) $company->id);
                $rtv = ReturnToVendor::query()->create($payload);
            }

            foreach ($prepared as $line) {
                $rtv->lines()->create($line);
            }
        });

        $rtv = $rtv?->fresh('lines');

        return [
            'success' => true,
            'rtv' => $rtv,
            'edit_url' => $rtv ? route('purchasing.rtv.index', ['rtv' => $rtv->id]) : null,
            'matched_count' => $catalogLines->count(),
            'unmatched_count' => $unmatchedCount,
            'msg' => 'RTV '.($rtv?->rtv_number ?? '').' created as New from the credit memo. Stock is unchanged until you Process it.',
        ];
    }

    /**
     * @param  array<string, mixed>  $matched
     */
    public function reviewReply(Company $company, array $matched): string
    {
        $kind = (string) ($matched['document_kind'] ?? 'rtv');
        $returnSource = ! empty($matched['return_lines']) ? $matched['return_lines'] : ($matched['lines'] ?? []);
        $returnLines = (array) $returnSource;
        $poLines = $kind === 'mixed' ? (array) ($matched['lines'] ?? []) : [];
        $count = count($returnLines);
        $matchedCount = collect($returnLines)->where('item_id', '>', 0)->count();
        $supplier = trim((string) ($matched['supplier_name'] ?? '')) ?: 'Unknown supplier';
        $ref = trim((string) ($matched['ref_no'] ?? ''));
        $supplierOk = (int) ($matched['supplier_id'] ?? 0) > 0;
        $returnTotal = round((float) collect($returnLines)->sum(fn ($r) => abs((float) ($r['line_total'] ?? 0))), 2);

        if ($kind === 'mixed') {
            $poCount = count($poLines);
            $poMatched = collect($poLines)->where('item_id', '>', 0)->count();
            $reply = "Read a **purchase invoice with returns** from **{$supplier}**";
            if ($ref !== '') {
                $reply .= ", ref {$ref}";
            }
            $reply .= " for **{$company->name}**.\n";
            $reply .= "**Rule:** positive qty/amount = **bought → PO**; negative qty/amount = **return → RTV** (never on the PO).\n";
            $reply .= $supplierOk ? "Supplier is in your supplier list.\n" : "Supplier is **not** in your supplier list.\n";
            $reply .= "Purchases: **{$poMatched}/{$poCount}** matched · Returns: **{$matchedCount}/{$count}** matched"
                .($returnTotal > 0 ? ' ($'.number_format($returnTotal, 2).')' : '').".\n";
            $reply .= 'PO merchandise check: $'.number_format(VendorInvoicePurchaseOrderService::linesTotal($matched), 2);
            $target = VendorInvoicePurchaseOrderService::invoiceTarget($matched);
            if ($target > 0) {
                $reply .= ' · invoice goods $'.number_format($target, 2);
            }
            $reply .= ".\n";
            $reply .= "\n".app(VendorInvoicePurchaseOrderService::class)->missingCatalogBrief($matched);

            if (! VendorInvoicePurchaseOrderService::amountsMatch($matched)) {
                $reply .= "\n\n".VendorInvoicePurchaseOrderService::amountMismatchText($matched);
                if (VendorInvoicePurchaseOrderService::listsAreReady($matched)) {
                    $reply .= "\n\nClick **Confirm & create PO + RTV** anyway — uses the matched purchase + return lines. Review the PO for any missing items.";
                }
            } elseif (VendorInvoicePurchaseOrderService::listsAreReady($matched)) {
                $reply .= "\n\n**All are OK.** Click **Confirm & create PO + RTV** — purchases only on the PO, returns only on the RTV. Stock unchanged until you Process the RTV.";
            } else {
                $reply .= "\n\nClick **Review & add to lists**, then Confirm.";
            }

            return $reply;
        }

        $head = "Read a **credit memo / return** — **{$count}** item(s) from **{$supplier}**";
        if ($ref !== '') {
            $head .= ", ref {$ref}";
        }
        if ($returnTotal > 0) {
            $head .= ', credit $'.number_format($returnTotal, 2);
        }
        $head .= ' for **'.$company->name.'**.';

        $reply = $head."\n";
        $reply .= "Document type: **Return to Vendor (RTV)** — not a purchase order.\n";
        $reply .= $supplierOk
            ? "Supplier is in your supplier list.\n"
            : "Supplier is **not** in your supplier list.\n";
        $reply .= "{$matchedCount} of {$count} line(s) are in your item list.\n";

        $notInList = collect($returnLines)->filter(fn ($row) => (int) ($row['item_id'] ?? 0) <= 0)->values();
        if ($notInList->isNotEmpty()) {
            $reply .= "Not in your item list ({$notInList->count()}):\n";
            foreach ($notInList->take(40) as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                $reply .= '- '.($sku !== '' ? $sku : 'no SKU').($name !== '' ? ' — '.$name : '')."\n";
            }
        } else {
            $reply .= "Every line was found on your item list.\n";
        }

        $reply .= 'Return lines $'.number_format($returnTotal, 2)."\n";
        $reply .= "\n".app(VendorInvoicePurchaseOrderService::class)->missingCatalogBrief(array_merge($matched, ['lines' => $returnLines]));

        if (! VendorInvoicePurchaseOrderService::amountsMatch($matched)) {
            $reply .= "\n\n".VendorInvoicePurchaseOrderService::amountMismatchText($matched)."\nConfirm stays blocked until the amounts match.";
        } elseif (collect($returnLines)->every(fn ($r) => (int) ($r['item_id'] ?? 0) > 0) && (int) ($matched['supplier_id'] ?? 0) > 0) {
            $reply .= "\n\n**All are OK** — amounts match and the supplier and items are in your lists.\n"
                ."Click **Confirm & create RTV** to save a New return to vendor. **Stock will not change** until you Process the RTV.";
        } else {
            $reply .= "\n\n**Amounts match.** Click **Review & add to lists**, then **Confirm & create RTV**.";
        }

        return $reply;
    }

    /**
     * @param  array<string, mixed>  $matched
     * @param  array{success: bool, rtv?: ReturnToVendor, msg: string}  $created
     */
    public function formatChatReply(Company $company, array $matched, array $created): string
    {
        if (empty($created['success']) || empty($created['rtv'])) {
            return (string) ($created['msg'] ?? 'Could not create the RTV.');
        }

        /** @var ReturnToVendor $rtv */
        $rtv = $created['rtv'];

        return "Created **RTV {$rtv->rtv_number}** (New) for **{$company->name}** from the credit memo.\n"
            ."Lines: {$rtv->lines->count()} · Total $".number_format((float) $rtv->total, 2)."\n"
            ."Stock is unchanged until you open Return to Vendor and click Process.";
    }

    protected function validDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
