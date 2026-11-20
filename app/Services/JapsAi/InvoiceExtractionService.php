<?php

namespace App\Services\JapsAi;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\Supplier;
use App\Support\ItemSearch;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Smalot\PdfParser\Parser;

/**
 * JapsAI vendor invoice / bill reader (japspos workflow).
 * Extracts structured lines via OpenAI. Persistence of the PO is handled by VendorInvoicePurchaseOrderService.
 */
class InvoiceExtractionService
{
    protected const SYSTEM_PROMPT = <<<'PROMPT'
You are reading a photo, scan, or PDF of a vendor purchase invoice / bill OR a vendor credit memo / return document for a wholesale POS system.
Extract the data and return ONLY a single valid JSON object (no markdown, no commentary) with this exact shape:

{
  "document_type": "purchase_invoice"|"credit_memo"|"return",
  "supplier_name": string|null,
  "ref_no": string|null,
  "invoice_date": string|null,
  "currency": string|null,
  "lines": [
    { "name": string, "sku": string|null, "barcode": string|null, "quantity": number, "unit_price": number, "line_total": number|null }
  ],
  "subtotal": number|null,
  "freight": number|null,
  "other_fees": number|null,
  "tax_amount": number|null,
  "total": number|null
}

Rules:
- "document_type" is required. Use "credit_memo" or "return" only when the WHOLE document is a credit memo / return (almost every merchandise amount is negative). Use "purchase_invoice" for a normal bill, including invoices that also have a few damaged-return / credit rows mixed in.
- On a purchase invoice, POSITIVE quantity / extended amount rows are purchases. NEGATIVE quantity or negative extended amount rows (e.g. qty -1, Ext. Price 3.10-, or "***DAMAGED RETURNS***") are invoice credits — still include them in "lines" with NEGATIVE line_total and positive unit_price (they are not a separate return document).
- "invoice_date" must be formatted as YYYY-MM-DD if you can determine it, else null.
- "lines" must contain one entry per product/item row printed on the invoice, in printed order. If the file has multiple pages, include line items from every page.
- Never skip, summarise or merge rows. If the same product is printed on two rows, output both rows.
- Include every row that has a quantity and an amount, under every section heading. Do not output subtotal / total / tax / page-total summary rows as lines.
- Product rows are only in the item table (rows with an item number / UPC / description). Never output rows from summary or recap tables such as "P & L information", category / department / class totals (e.g. "GROCERIES", "CONFECTIONS", "BEVERAGES/FROZEN"), "Your Cost / Your Retail / GP %", piece-count boxes, tax summaries or totals — they are not products.
- Credit / return rows (amount printed with a minus sign like "9.01-" or "-9.01", or in parentheses) must be included with a NEGATIVE line_total and a positive quantity and unit_price.
- "line_total" is the extended cost the buyer pays for the row (Ext. Price / Amount), never the extended retail value.
- "subtotal" is the merchandise amount only: the sum of the product rows. It is not the invoice total.
- Delivery, freight, shipping, fuel surcharge, handling, service charges and any other fee are NOT product rows. Do not put them in "lines" and do not add them into "subtotal".
- "freight" is the delivery / freight / shipping charge. "other_fees" is every other non-tax fee (fuel, handling, service, miscellaneous). Use 0 when that charge is not printed.
- "total" is the invoice grand total (subtotal + freight + other_fees + tax). Never make the product lines add up to "total".
- Before answering, add up line_total for all product lines (credits count as negative). It must equal the invoice subtotal, not the invoice total. If it is lower, you missed product rows: go back and find them.
- "quantity" and "unit_price" must be plain numbers only (no currency symbols, no thousands separators).
- "unit_price" is the billed cost for one of the quantity units on that row (what the vendor charged), not a catalog or list price.
- "line_total" is the extended amount printed for that row when available (quantity × unit_price). If unit_price is missing, compute unit_price = line_total / quantity.
- "quantity" and "unit_price" must use the same unit as the billed row (do not convert cases to eaches unless the invoice already shows eaches).
- "sku" is the item number / vendor item code printed on the row, exactly as printed, else null.
- "barcode" is the UPC / EAN / barcode number printed on that same row, exactly as printed, else null. If the row shows only one code, put that code in "sku" and also in "barcode" when it is all digits. Never output a placeholder like 000000.
- Do not invent data that is not visibly on the document. Use null when unsure.
- Return ONLY the JSON object, nothing else.
PROMPT;

    protected const RESPONSES_URL = 'https://api.openai.com/v1/responses';

    protected const CACHE_VERSION = 'v12';

    protected const MAX_PAGE_READS = 30;

    protected const PARALLEL_REQUESTS = 6;

    protected const MAX_COMPLETION_ATTEMPTS = 4;

    protected ?string $lastError = null;

    public function __construct(public Company $company) {}

    public static function forCompany(Company $company): self
    {
        return new self($company);
    }

    /**
     * @return array{success: bool, msg?: string, data?: array<string, mixed>}
     */
    public function extract(UploadedFile|TemporaryUploadedFile $file): array
    {
        if (! $this->company->japs_ai_enabled) {
            return ['success' => false, 'msg' => 'POS AI is disabled. Enable it under Admin → POS AI Settings.'];
        }

        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            return ['success' => false, 'msg' => 'No OpenAI API key. Add one in POS AI Settings or OPENAI_API_KEY in .env.'];
        }

        $model = trim((string) ($this->company->japs_ai_model ?: 'gpt-4o-mini')) ?: 'gpt-4o-mini';
        if (! preg_match('/gpt-4o|gpt-4\.1|gpt-5|omni/i', $model)) {
            $model = 'gpt-4o-mini';
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $originalName = (string) ($file->getClientOriginalName() ?: 'invoice.pdf');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $path = $file->getRealPath();
        if (! $path || ! is_file($path)) {
            return ['success' => false, 'msg' => 'Could not read the uploaded file.'];
        }

        $hash = (string) (hash_file('sha256', $path) ?: '');
        $cacheKey = 'pos-ai-invoice-extract:'.self::CACHE_VERSION.':'.$this->company->id.':'.$model.':'.$hash;
        if ($hash !== '') {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ! empty($cached['lines'])) {
                return ['success' => true, 'data' => $cached];
            }
        }

        try {
            $isPdf = $mime === 'application/pdf' || $extension === 'pdf';
            if ($isPdf) {
                // OpenAI file ingest is case-sensitive: ".PDF" is rejected, ".pdf" is not.
                $result = $this->extractFromPdf($apiKey, $model, $path, 'invoice.pdf');
            } else {
                $mime = $this->normalizeImageMime($mime !== '' ? $mime : 'image/jpeg', $extension);
                $result = $this->extractFromImage($apiKey, $model, $path, $mime);
            }

            // Only a read whose lines add up to the invoice subtotal is reused for the same file.
            if (! empty($result['success']) && $hash !== '' && $this->isCompleteRead($result['data'] ?? [])) {
                Cache::put($cacheKey, $result['data'], now()->addHours(12));
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('InvoiceExtractionService: '.$e->getMessage());

            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * Match extracted lines + supplier against this company's catalog.
     *
     * @param  array<string, mixed>  $data
     * @return array{supplier_id: ?int, supplier_name: ?string, lines: list<array<string, mixed>>}
     */
    public function matchToCatalog(array $data): array
    {
        $companyId = (int) $this->company->id;
        $pureReturn = self::detectReturnDocument($data);
        $lines = [];
        $returnLines = [];
        $credits = [];

        foreach ((array) ($data['lines'] ?? []) as $line) {
            $name = trim((string) ($line['name'] ?? ''));
            $sku = trim((string) ($line['sku'] ?? ''));
            $barcode = trim((string) ($line['barcode'] ?? ''));
            if ($name === '' && $sku === '' && $barcode === '') {
                continue;
            }

            $rawQty = (float) ($line['quantity'] ?? 0);
            $lineTotal = isset($line['line_total']) ? (float) $line['line_total'] : null;
            // Negative qty or negative extended = return to vendor; positive = purchase.
            $isReturnRow = ($lineTotal !== null && $lineTotal < -0.0001) || $rawQty < -0.0001;

            $qty = max(0.01, abs($rawQty > 0 || $rawQty < 0 ? $rawQty : 1));
            $unit = max(0, abs((float) ($line['unit_price'] ?? 0)));
            $ext = $lineTotal !== null ? abs($lineTotal) : null;
            if (($ext === null || $ext <= 0) && $unit > 0) {
                $ext = round($qty * $unit, 4);
            }
            // Ext. Price is source of truth when it disagrees with qty×unit (retail vs cost).
            if ($ext !== null && $ext > 0 && $unit > 0 && abs(round($qty * $unit, 4) - $ext) > 0.02) {
                $unit = round($ext / $qty, 4);
            } elseif ($unit <= 0 && $ext !== null && $ext > 0) {
                $unit = round($ext / $qty, 4);
            }

            $hit = $this->matchInvoiceItem($companyId, $sku !== '' ? $sku : null, $barcode !== '' ? $barcode : null, $name !== '' ? $name : null);
            $item = $hit['item'];
            $row = [
                'name' => $name !== '' ? $name : ($sku !== '' ? $sku : ($barcode !== '' ? $barcode : 'Item')),
                'sku' => $sku !== '' ? $sku : null,
                'barcode' => $barcode !== '' ? $barcode : null,
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $ext,
                'selected' => true,
                'item_id' => $item?->id,
                'item_code' => $item?->item_code,
                'description' => $item?->description ?? $name,
                'status' => $item ? 'matched' : 'unmatched',
                'matched_by' => $hit['via'],
            ];

            if ($isReturnRow || $pureReturn) {
                // Pure credit memo → RTV lines. On a purchase invoice, negative rows are
                // invoice credits only (no RTV: stock was never received for that return).
                if ($pureReturn) {
                    $returnLines[] = $row;
                }
                $credits[] = [
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'barcode' => $row['barcode'],
                    'quantity' => $qty,
                    'line_total' => -1 * (float) ($ext ?? 0),
                    'item_id' => $item?->id,
                    'item_code' => $item?->item_code,
                ];

                continue;
            }

            $lines[] = $row;
        }

        // Pure credit memo: return rows are the only catalog lines.
        if ($pureReturn && $lines === [] && $returnLines !== []) {
            $lines = $returnLines;
        }

        $supplierId = $this->matchSupplier($companyId, $data['supplier_name'] ?? null)
            ?? $this->inferSupplierFromLines($companyId, $lines !== [] ? $lines : $returnLines);

        // Purchase invoice (+ optional credit rows) → PO only.
        // Whole-document credit memo → RTV only. Never PO+RTV from one scan.
        $kind = $pureReturn ? 'rtv' : 'po';

        $subtotal = $data['subtotal'] ?? null;
        $total = $data['total'] ?? null;
        if ($kind === 'rtv') {
            if (is_numeric($subtotal)) {
                $subtotal = abs((float) $subtotal);
            }
            if (is_numeric($total)) {
                $total = abs((float) $total);
            }
        }

        return [
            'document_type' => $kind === 'rtv' ? 'credit_memo' : (string) ($data['document_type'] ?? 'purchase_invoice'),
            'document_kind' => $kind,
            'supplier_id' => $supplierId,
            'supplier_name' => $data['supplier_name'] ?? null,
            'ref_no' => $data['ref_no'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'currency' => $data['currency'] ?? null,
            'subtotal' => $subtotal,
            'freight' => $kind === 'rtv' ? abs((float) ($data['freight'] ?? 0)) : ($data['freight'] ?? null),
            'other_fees' => $kind === 'rtv' ? abs((float) ($data['other_fees'] ?? 0)) : ($data['other_fees'] ?? null),
            'tax_amount' => $kind === 'rtv' ? abs((float) ($data['tax_amount'] ?? 0)) : ($data['tax_amount'] ?? null),
            'total' => $total,
            'lines' => $lines,
            // Only filled for pure credit memos (RTV). Purchase invoices keep credits off the PO.
            'return_lines' => $kind === 'rtv' ? $returnLines : [],
            'credits' => $credits,
        ];
    }

    /**
     * Whole document is a credit memo / return (not a purchase bill with a few return rows).
     *
     * @param  array<string, mixed>  $data
     */
    public static function detectReturnDocument(array $data): bool
    {
        $type = strtolower(trim((string) ($data['document_type'] ?? '')));
        if (in_array($type, ['credit_memo', 'credit', 'return', 'rtv', 'creditmemo'], true)) {
            return true;
        }

        $sub = (float) ($data['subtotal'] ?? 0);
        $total = (float) ($data['total'] ?? 0);
        if ($sub < -0.01 || $total < -0.01) {
            return true;
        }

        $rawLines = (array) ($data['lines'] ?? []);
        if ($rawLines === []) {
            return false;
        }

        $neg = 0;
        $pos = 0;
        foreach ($rawLines as $line) {
            $ext = (float) ($line['line_total'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            if ($ext < -0.0001 || $qty < -0.0001) {
                $neg++;
            } elseif ($ext > 0.0001 || $qty > 0.0001) {
                $pos++;
            }
        }

        // Only treat as pure RTV when every product row is a return.
        return $neg > 0 && $pos === 0;
    }

    protected function matchSupplier(int $companyId, ?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $normalized = $this->normalizeSupplierName($name);
        $tokens = array_values(array_filter(
            explode(' ', $normalized),
            fn (string $t) => strlen($t) >= 3
        ));
        if ($tokens === [] && $normalized !== '') {
            $tokens = explode(' ', $normalized);
        }

        $query = Supplier::query()->where('company_id', $companyId);
        $query->where(function ($q) use ($name, $tokens) {
            $q->where('name', $name)
                ->orWhereRaw('UPPER(name) = ?', [mb_strtoupper($name)])
                ->orWhere('supplier_id', $name)
                ->orWhere('name', 'like', $name.'%')
                ->orWhere('name', 'like', '%'.$name.'%');
            foreach (array_slice($tokens, 0, 4) as $token) {
                $q->orWhere('name', 'like', '%'.$token.'%');
            }
        });

        $bestId = null;
        $bestScore = 0.0;
        foreach ($query->limit(80)->get(['id', 'name', 'supplier_id']) as $row) {
            $score = $this->supplierNameScore(
                $normalized,
                $this->normalizeSupplierName((string) $row->name)
            );
            if (strcasecmp((string) $row->supplier_id, $name) === 0) {
                $score = max($score, 0.99);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (int) $row->id;
            }
        }

        return $bestScore >= 0.72 ? $bestId : null;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function inferSupplierFromLines(int $companyId, array $lines): ?int
    {
        $itemIds = collect($lines)
            ->pluck('item_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($itemIds->count() < 2) {
            return null;
        }

        $top = ItemSupplier::query()
            ->whereIn('item_id', $itemIds)
            ->selectRaw('supplier_id, COUNT(*) as c')
            ->groupBy('supplier_id')
            ->orderByDesc('c')
            ->first();

        if (! $top || (int) $top->c < max(2, (int) ceil($itemIds->count() * 0.45))) {
            return null;
        }

        $exists = Supplier::query()
            ->where('company_id', $companyId)
            ->where('id', (int) $top->supplier_id)
            ->exists();

        return $exists ? (int) $top->supplier_id : null;
    }

    protected function normalizeSupplierName(string $name): string
    {
        $s = mb_strtoupper(trim($name));
        $s = str_replace(['&', '+'], ' AND ', $s);
        $s = preg_replace('/[^A-Z0-9]+/', ' ', $s) ?? $s;
        $s = preg_replace(
            '/\b(INC|INCORPORATED|LLC|LTD|LIMITED|CORP|CORPORATION|CO|COMPANY|THE|PRODUCT|PRODUCTS|SALES|DIVISION|DIV)\b/',
            ' ',
            $s
        ) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    protected function supplierNameScore(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            $short = min(strlen($a), strlen($b));
            $long = max(strlen($a), strlen($b));

            return $long > 0 ? $short / $long : 0.0;
        }
        similar_text($a, $b, $pct);

        return ((float) $pct) / 100;
    }

    /**
     * Invoice row is in the item list when the printed code matches the item code,
     * an alias, or a barcode. A missing alias alone does not make it "not in list".
     *
     * @return array{item: ?Item, via: ?string}
     */
    protected function matchInvoiceItem(int $companyId, ?string $sku, ?string $barcode, ?string $name): array
    {
        $seen = [];
        foreach ([$sku, $barcode] as $raw) {
            foreach ($this->invoiceCodeNeedles($raw, false) as $needle) {
                if (isset($seen[$needle])) {
                    continue;
                }
                $seen[$needle] = true;
                $found = $this->findItemByCatalogCode($companyId, $needle);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        foreach ($this->invoiceCodeNeedles($name, true) as $needle) {
            if (isset($seen[$needle])) {
                continue;
            }
            $seen[$needle] = true;
            $found = $this->findItemByCatalogCode($companyId, $needle);
            if ($found !== null) {
                return $found;
            }
        }

        if ($name) {
            $q = Item::query()->where('company_id', $companyId)->where('is_inactive', false);
            ItemSearch::constrain($q, $name);
            $row = $q->orderBy('item_code')->first();
            if ($row) {
                return ['item' => $row, 'via' => 'name'];
            }
        }

        return ['item' => null, 'via' => null];
    }

    /**
     * @return list<string>
     */
    protected function invoiceCodeNeedles(?string $raw, bool $digitsOnly): array
    {
        $raw = trim((string) $raw);
        if ($raw === '' || preg_match('/^0+$/', $raw)) {
            return [];
        }

        $out = [];
        $push = function (string $value) use (&$out, $digitsOnly): void {
            $value = trim($value);
            if ($value === '' || preg_match('/^0+$/', $value)) {
                return;
            }
            if ($digitsOnly && preg_match('/\d/', $value) !== 1) {
                return;
            }
            if (mb_strlen($value) < ($digitsOnly ? 6 : 1)) {
                return;
            }
            $out[$value] = true;
            $compact = preg_replace('/[\s\-]+/', '', $value) ?? $value;
            if ($compact !== '' && $compact !== $value) {
                $out[$compact] = true;
            }
        };

        if (! $digitsOnly) {
            $push($raw);
        }
        if (preg_match_all('/\d[\d\s\-]{5,}\d/', $raw, $groups)) {
            foreach ($groups[0] as $group) {
                $push($group);
            }
        }
        if (preg_match_all('/[A-Za-z0-9][A-Za-z0-9._\-]{3,}/', $raw, $tokens)) {
            foreach ($tokens[0] as $token) {
                $push($token);
            }
        }

        return array_keys($out);
    }

    /**
     * @return array{item: Item, via: string}|null
     */
    protected function findItemByCatalogCode(int $companyId, string $code): ?array
    {
        $keys = Item::scanCodeLookupKeys($code);
        if ($keys === []) {
            return null;
        }

        $item = Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->whereIn('item_code', $keys)
            ->orderBy('item_code')
            ->first();
        if ($item) {
            return ['item' => $item, 'via' => 'sku'];
        }

        $aliasId = $this->catalogItemId($companyId, 'item_prices', 'alias_code', $keys);
        if ($aliasId) {
            $item = Item::query()->where('company_id', $companyId)->where('is_inactive', false)->where('id', $aliasId)->first();
            if ($item) {
                return ['item' => $item, 'via' => 'alias'];
            }
        }

        $item = Item::query()
            ->where('company_id', $companyId)
            ->where('is_inactive', false)
            ->whereIn('primary_upc', $keys)
            ->orderBy('item_code')
            ->first();
        if ($item) {
            return ['item' => $item, 'via' => 'barcode'];
        }

        $upcId = $this->catalogItemId($companyId, 'item_upcs', 'upc', $keys);
        if ($upcId) {
            $item = Item::query()->where('company_id', $companyId)->where('is_inactive', false)->where('id', $upcId)->first();
            if ($item) {
                return ['item' => $item, 'via' => 'barcode'];
            }
        }

        $supplierId = $this->catalogItemId($companyId, 'item_suppliers', 'supplier_item_code', $keys);
        if ($supplierId) {
            $item = Item::query()->where('company_id', $companyId)->where('is_inactive', false)->where('id', $supplierId)->first();
            if ($item) {
                return ['item' => $item, 'via' => 'supplier'];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     */
    protected function catalogItemId(int $companyId, string $table, string $column, array $keys): ?int
    {
        $id = DB::table($table)
            ->join('items', 'items.id', '=', $table.'.item_id')
            ->where('items.company_id', $companyId)
            ->where('items.is_inactive', false)
            ->whereIn($table.'.'.$column, $keys)
            ->value($table.'.item_id');

        return $id ? (int) $id : null;
    }

    protected function matchItem(int $companyId, ?string $sku, ?string $name): ?Item
    {
        return $this->matchInvoiceItem($companyId, $sku, null, $name)['item'];
    }

    protected function resolveApiKey(): string
    {
        // Server: key saved in POS AI settings. Desktop app: that value is encrypted with the
        // server's APP_KEY and cannot be read here, so fall back to OPENAI_API_KEY in .env.
        $fromCompany = $this->company->decryptedOrNull('japs_ai_api_key');
        if ($fromCompany !== null) {
            return $fromCompany;
        }

        return trim((string) (config('services.openai.key') ?? ''));
    }

    protected function normalizeImageMime(string $mime, string $extension): string
    {
        $mime = strtolower(trim($mime));
        $extension = strtolower(trim($extension));

        $byExt = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];

        if (isset($byExt[$extension])) {
            return $byExt[$extension];
        }

        if ($mime === 'image/jpg' || $mime === 'image/pjpeg') {
            return 'image/jpeg';
        }

        if (str_starts_with($mime, 'image/')) {
            return $mime;
        }

        return 'image/jpeg';
    }

    /**
     * @return array{success: bool, msg?: string, data?: array<string, mixed>}
     */
    protected function extractFromImage(string $apiKey, string $model, string $path, string $mime): array
    {
        $attachment = [
            'type' => 'input_image',
            'image_url' => 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path)),
            'detail' => 'high',
        ];

        $results = $this->runParallel($apiKey, $model, ['full' => $this->fullReadText(1)], $attachment);
        $data = $results['full'] ?? null;
        if ($data === null) {
            return ['success' => false, 'msg' => $this->lastError ?: 'Could not read structured data from this image. Try a clearer photo.'];
        }

        return ['success' => true, 'data' => $this->completeLineSet($apiKey, $model, $data, $attachment)];
    }

    /**
     * @return array{success: bool, msg?: string, data?: array<string, mixed>}
     */
    protected function extractFromPdf(string $apiKey, string $model, string $path, string $filename = 'invoice.pdf'): array
    {
        $bytes = (string) file_get_contents($path);
        $pageTexts = $this->pdfPageTexts($path);
        $pageCount = count($pageTexts) ?: $this->pdfPageCount($bytes);

        // Prefer deterministic text-layer parse when the PDF has selectable text and
        // purchase + credit rows reconcile to the printed subtotal (no AI drift).
        $fromText = $this->parseTextLayerInvoice($pageTexts);
        if ($fromText !== null && $this->isCompleteRead($fromText)) {
            Log::info('InvoiceExtractionService: text-layer exact match', [
                'lines' => count($fromText['lines']),
                'purchase_sum' => $this->extractedPurchaseSum($fromText),
                'return_sum' => $this->extractedReturnSum($fromText),
                'subtotal' => $fromText['subtotal'],
                'ref_no' => $fromText['ref_no'] ?? null,
            ]);

            return ['success' => true, 'data' => $fromText];
        }

        // Text PDFs: read each page's own text in parallel. Page boundaries are exact, so no row is skipped or doubled.
        $byPage = $this->readPageTexts($apiKey, $model, $pageTexts);
        if ($byPage !== null && $this->isCompleteRead($byPage)) {
            return ['success' => true, 'data' => $byPage];
        }
        // If AI page-read is close but text-layer was closer, keep the better of the two.
        if ($fromText !== null) {
            $target = $this->extractedMerchandiseTarget($fromText);
            $byPage = $this->pickCloserRead($byPage, $fromText, $target);
            if ($byPage !== null && $this->isCompleteRead($byPage)) {
                return ['success' => true, 'data' => $byPage];
            }
        }

        $upload = Http::withToken($apiKey)
            ->timeout(90)
            ->attach('file', $bytes, 'invoice.pdf')
            ->post('https://api.openai.com/v1/files', [
                'purpose' => 'user_data',
            ]);

        if (! $upload->successful()) {
            return ['success' => false, 'msg' => $upload->json('error.message') ?: $upload->body()];
        }

        $fileId = $upload->json('id');
        if (empty($fileId)) {
            return ['success' => false, 'msg' => 'Could not upload PDF to OpenAI.'];
        }

        try {
            $attachment = ['type' => 'input_file', 'file_id' => $fileId];
            $data = $this->readWholePdf($apiKey, $model, $attachment, $pageCount, $byPage);
            if ($data === null) {
                return ['success' => false, 'msg' => $this->lastError ?: 'Could not read structured data from this PDF.'];
            }

            return ['success' => true, 'data' => $this->completeLineSet($apiKey, $model, $data, $attachment)];
        } finally {
            try {
                Http::withToken($apiKey)->delete('https://api.openai.com/v1/files/'.$fileId);
            } catch (\Throwable) {
                // ignore cleanup errors
            }
        }
    }

    /**
     * Deterministic parse of wholesale invoice text layers (Capital Sales-style columns).
     * Returns null when too few rows or the line sum does not reconcile to the printed subtotal.
     *
     * @param  array<int, string>  $pageTexts
     * @return array<string, mixed>|null
     */
    protected function parseTextLayerInvoice(array $pageTexts): ?array
    {
        if ($pageTexts === []) {
            return null;
        }

        $lines = [];
        $supplier = null;
        $ref = null;
        $date = null;
        $subtotal = null;
        $freight = 0.0;
        $tax = 0.0;
        $total = null;

        foreach ($pageTexts as $page) {
            $page = (string) $page;
            if ($supplier === null && preg_match('/^\s*([A-Z][A-Za-z0-9 &.\'\-]{2,80})\s*$/m', $page, $m)) {
                $candidate = trim($m[1]);
                if (! preg_match('/^(invoice|qty|page|sold|ship|total)/i', $candidate)) {
                    $supplier = $candidate;
                }
            }
            if ($ref === null && preg_match('/Invoice\s*#\s*:\s*(\S+)/i', $page, $m)) {
                $ref = trim($m[1]);
            }
            if ($date === null && preg_match('/\bDate:\s*(\d{1,2}\/\d{1,2}\/\d{2,4})/', $page, $m)) {
                try {
                    $date = \Carbon\Carbon::createFromFormat(
                        strlen($m[1]) > 8 ? 'm/d/Y' : 'm/d/y',
                        $m[1]
                    )->format('Y-m-d');
                } catch (\Throwable) {
                    $date = null;
                }
            }
            if ($subtotal === null && preg_match('/Sub\s*Total:\s*([\d,]+\.\d{2})/i', $page, $m)) {
                $subtotal = (float) str_replace(',', '', $m[1]);
            }
            if (preg_match('/Shipping:\s*([\d,]+\.\d{2})/i', $page, $m)) {
                $freight = max($freight, (float) str_replace(',', '', $m[1]));
            }
            if (preg_match('/Sales\s*Tax:\s*([\d,]+\.\d{2})/i', $page, $m)) {
                $tax = max($tax, (float) str_replace(',', '', $m[1]));
            }
            if ($total === null && preg_match('/^\s*Total:\s*([\d,]+\.\d{2})/mi', $page, $m)) {
                $total = (float) str_replace(',', '', $m[1]);
            }

            foreach (preg_split("/\r\n|\n|\r/", $page) ?: [] as $rawLine) {
                $parsed = $this->parseTextProductLine((string) $rawLine);
                if ($parsed !== null) {
                    $lines[] = $parsed;
                }
            }
        }

        if (count($lines) < 3 || $subtotal === null || $subtotal <= 0) {
            return null;
        }

        $data = [
            'document_type' => 'purchase_invoice',
            'supplier_name' => $supplier,
            'ref_no' => $ref,
            'invoice_date' => $date,
            'currency' => 'USD',
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
            'freight' => round($freight, 2),
            'other_fees' => 0.0,
            'tax_amount' => round($tax, 2),
            'total' => $total !== null ? round($total, 2) : round($subtotal + $freight + $tax, 2),
        ];

        // Accept only when net of credits equals printed subtotal (ground truth).
        $net = round($this->extractedLineSum($data), 2);
        if (abs($net - $subtotal) > 0.02) {
            Log::info('InvoiceExtractionService: text-layer sum mismatch', [
                'lines' => count($lines),
                'net' => $net,
                'subtotal' => $subtotal,
            ]);

            return null;
        }

        return $data;
    }

    /**
     * Capital Sales / similar: Qty Item# [UPC] Description CasePrc UntPrc[T] Extended
     *
     * @return array{name: string, sku: string|null, barcode: string|null, quantity: float, unit_price: float, line_total: float}|null
     */
    protected function parseTextProductLine(string $line): ?array
    {
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
        if ($line === '' || ! preg_match('/^-?\d/', $line)) {
            return null;
        }
        if (preg_match('/^(Sub\s*Total|Shipping|Sales\s*Tax|Total|Payments|Prev\s*Bal|Invoice|Date|Time|Page)\b/i', $line)) {
            return null;
        }

        // qty item# mid… casePrc (unitPrc[T]|T) extended
        if (! preg_match(
            '/^(-?\d+(?:\.\d+)?)\s+(\S+)\s+(.+)\s+(\d+(?:\.\d+)?)\s+(?:(\d+(?:\.\d+)?)T?|T)\s+(-?\d{1,3}(?:,\d{3})*\.\d{2}-?)$/',
            $line,
            $m
        )) {
            return null;
        }

        $rawQty = (float) $m[1];
        $sku = trim($m[2]);
        $mid = trim($m[3]);
        $casePrc = (float) $m[4];
        $ext = $this->toNumber($m[6]);
        if ($ext === null) {
            return null;
        }

        $barcode = null;
        $name = $mid;
        if (preg_match('/^(\d{8,14})\s+(.+)$/', $mid, $u)) {
            $barcode = $u[1];
            $name = trim($u[2]);
        }

        if ($name === '' || preg_match('/^(subtotal|shipping|sales tax|total|payments)\b/i', $name)) {
            return null;
        }

        $qty = abs($rawQty) > 0 ? abs($rawQty) : 1.0;
        $unit = $casePrc;
        if (abs($qty * $casePrc - abs($ext)) > 0.02) {
            $unit = round(abs($ext) / $qty, 4);
        }

        return [
            'name' => $name,
            'sku' => $sku !== '' ? $sku : null,
            'barcode' => $barcode,
            'quantity' => $qty,
            'unit_price' => $unit,
            'line_total' => $ext,
        ];
    }

    /**
     * Plain text of every page (1-based). Empty when the PDF has no text layer (scans) or cannot be parsed.
     *
     * @return array<int, string>
     */
    protected function pdfPageTexts(string $path): array
    {
        if (! class_exists(Parser::class)) {
            return [];
        }

        try {
            $pdf = (new Parser)->parseFile($path);
            $texts = [];
            foreach ($pdf->getPages() as $i => $page) {
                try {
                    $texts[$i + 1] = trim((string) $page->getText());
                } catch (\Throwable) {
                    $texts[$i + 1] = '';
                }
            }

            return $texts;
        } catch (\Throwable $e) {
            Log::info('InvoiceExtractionService: pdf text layer unreadable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<int, string>  $pageTexts
     * @return array<string, mixed>|null
     */
    protected function readPageTexts(string $apiKey, string $model, array $pageTexts): ?array
    {
        $pageCount = count($pageTexts);
        if ($pageCount === 0 || $pageCount > self::MAX_PAGE_READS) {
            return null;
        }
        foreach ($pageTexts as $text) {
            if (mb_strlen($text) < 40 || ! preg_match('/\d/', $text)) {
                return null;
            }
        }

        $prompts = [];
        foreach ($pageTexts as $p => $text) {
            $prompts['p'.$p] = $this->pageTextPrompt($p, $pageCount, $text);
        }

        $results = $this->runParallel($apiKey, $model, $prompts, null);
        $failed = array_filter($prompts, fn ($key) => ($results[$key] ?? null) === null, ARRAY_FILTER_USE_KEY);
        if ($failed !== []) {
            $results = array_merge($results, $this->runParallel($apiKey, $model, $failed, null));
        }

        $pages = [];
        foreach (array_keys($pageTexts) as $p) {
            if (! is_array($results['p'.$p] ?? null)) {
                Log::warning('InvoiceExtractionService: page text read failed', ['page' => $p]);

                return null;
            }
            $pages[$p] = $results['p'.$p];
        }

        $merged = $this->dropSummaryRows($this->mergePages($pages));
        Log::info('InvoiceExtractionService: page text read', [
            'pages' => $pageCount,
            'lines_per_page' => array_map(fn ($page) => count($page['lines']), $pages),
            'lines' => count($merged['lines']),
            'sum' => round($this->extractedLineSum($merged), 2),
            'target' => round($this->extractedHeaderTarget($merged), 2),
        ]);

        return $merged;
    }

    /**
     * Whole-file read (needed for scans, or when page text did not add up), compared with the page-text read.
     *
     * @param  array<string, mixed>  $attachment
     * @param  array<string, mixed>|null  $byPage
     * @return array<string, mixed>|null
     */
    protected function readWholePdf(string $apiKey, string $model, array $attachment, int $pageCount, ?array $byPage): ?array
    {
        $results = $this->runParallel($apiKey, $model, ['full' => $this->fullReadText($pageCount)], $attachment);
        $full = isset($results['full']) ? $this->dropSummaryRows($results['full']) : null;

        $target = max(
            $this->extractedHeaderTarget($full ?? []),
            $this->extractedHeaderTarget($byPage ?? [])
        );
        if ($target > 0.05) {
            if ($full !== null && $this->extractedHeaderTarget($full) <= 0.05) {
                $full['subtotal'] = $target;
            }
            if ($byPage !== null && $this->extractedHeaderTarget($byPage) <= 0.05) {
                $byPage['subtotal'] = $target;
            }
        }

        Log::info('InvoiceExtractionService: whole pdf read', [
            'pages' => $pageCount,
            'target' => round($target, 2),
            'full_lines' => $full ? count($full['lines']) : null,
            'full_sum' => $full ? round($this->extractedLineSum($full), 2) : null,
            'page_lines' => $byPage ? count($byPage['lines']) : null,
            'page_sum' => $byPage ? round($this->extractedLineSum($byPage), 2) : null,
        ]);

        $best = $this->pickCloserRead($full, $byPage, $target);
        if ($best === null) {
            return null;
        }

        foreach (['subtotal', 'freight', 'other_fees', 'tax_amount', 'total', 'supplier_name', 'ref_no', 'invoice_date', 'currency'] as $field) {
            if (empty($best[$field])) {
                $best[$field] = $full[$field] ?? $byPage[$field] ?? null;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>|null  $a
     * @param  array<string, mixed>|null  $b
     * @return array<string, mixed>|null
     */
    protected function pickCloserRead(?array $a, ?array $b, float $target): ?array
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }
        if ($target <= 0.05) {
            return count($b['lines']) >= count($a['lines']) ? $b : $a;
        }

        // Prefer the read whose positive purchase rows are closest to merchandise target
        // (header subtotal is net of credits — do not compare net line sum).
        $merchA = $this->extractedMerchandiseTarget($a) ?: $target;
        $merchB = $this->extractedMerchandiseTarget($b) ?: $target;
        $gapA = abs($merchA - $this->extractedPurchaseSum($a));
        $gapB = abs($merchB - $this->extractedPurchaseSum($b));

        return $gapB <= $gapA ? $b : $a;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages  keyed by page number
     * @return array<string, mixed>
     */
    protected function mergePages(array $pages): array
    {
        ksort($pages);
        $merged = [
            'supplier_name' => null,
            'ref_no' => null,
            'invoice_date' => null,
            'currency' => null,
            'lines' => [],
            'subtotal' => null,
            'freight' => null,
            'other_fees' => null,
            'tax_amount' => null,
            'total' => null,
        ];
        $pageOfLine = [];

        foreach ($pages as $p => $page) {
            foreach (['supplier_name', 'ref_no', 'invoice_date', 'currency'] as $field) {
                if (empty($merged[$field]) && ! empty($page[$field])) {
                    $merged[$field] = $page[$field];
                }
            }
            // Per-page subtotals can appear on every page; the grand figure is the largest one.
            foreach (['subtotal', 'total'] as $field) {
                if (($page[$field] ?? null) !== null && (float) $page[$field] > (float) ($merged[$field] ?? 0)) {
                    $merged[$field] = (float) $page[$field];
                    if ($field === 'total') {
                        $merged['tax_amount'] = $page['tax_amount'] ?? $merged['tax_amount'];
                        $merged['freight'] = $page['freight'] ?? $merged['freight'];
                        $merged['other_fees'] = $page['other_fees'] ?? $merged['other_fees'];
                    }
                }
            }
            foreach (['freight', 'other_fees'] as $field) {
                if ($merged[$field] === null && ($page[$field] ?? null) !== null) {
                    $merged[$field] = (float) $page[$field];
                }
            }
            if ($merged['tax_amount'] === null && ($page['tax_amount'] ?? null) !== null) {
                $merged['tax_amount'] = (float) $page['tax_amount'];
            }
            foreach ($page['lines'] as $line) {
                $merged['lines'][] = $line;
                $pageOfLine[] = $p;
            }
        }

        // A row read on both sides of a page break shows up twice; drop repeats only while purchases overshoot.
        $merged = $this->reconcilePurchaseAmounts($merged);
        $target = $this->extractedMerchandiseTarget($merged);
        if ($target > 0.05 && $this->extractedPurchaseSum($merged) > $target * 1.005) {
            $seen = [];
            $keep = [];
            $sum = $this->extractedPurchaseSum($merged);
            foreach ($merged['lines'] as $i => $line) {
                $key = $this->lineKey($line);
                $p = $pageOfLine[$i] ?? 0;
                $ext = (float) ($line['line_total'] ?? 0);
                if ($ext > 0 && $sum > $target * 1.005 && isset($seen[$key]) && $seen[$key] !== $p) {
                    $sum -= $ext;

                    continue;
                }
                $seen[$key] = $p;
                $keep[] = $line;
            }
            $merged['lines'] = $keep;
        }

        return $this->reconcilePurchaseAmounts($merged);
    }

    /**
     * Keep asking for rows that are still missing until the lines add up to the invoice subtotal.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attachment
     * @return array<string, mixed>
     */
    protected function completeLineSet(string $apiKey, string $model, array $data, array $attachment): array
    {
        $data = $this->reconcilePurchaseAmounts($data);

        for ($attempt = 1; $attempt <= self::MAX_COMPLETION_ATTEMPTS; $attempt++) {
            // Purchase rows are positive; header subtotal is usually net of credit/return rows.
            $target = $this->extractedMerchandiseTarget($data);
            $sum = $this->extractedPurchaseSum($data);
            if ($target <= 0.05 || $this->isCompleteRead($data) || $sum >= $target) {
                break;
            }

            $found = collect($data['lines'])
                ->map(fn ($l) => ($l['sku'] ?: mb_substr((string) $l['name'], 0, 30)).' x'.round((float) $l['quantity'], 2).' = '.number_format((float) $l['line_total'], 2, '.', ''))
                ->take(250)
                ->implode('; ');

            $ask = 'INCOMPLETE EXTRACTION. Purchase merchandise should total '.number_format($target, 2, '.', '')
                .' (invoice subtotal '.number_format($this->extractedHeaderTarget($data), 2, '.', '')
                .' plus any credit/return rows), but positive product rows so far only add up to '.number_format($sum, 2, '.', '')
                .' (missing '.number_format($target - $sum, 2, '.', '').').'
                ."\nRows already found (sku or name x qty = line total): ".$found
                ."\nRead every page again from top to bottom and return ONLY the missing POSITIVE purchase product rows that are NOT in the list above."
                .' Do not re-send credit/return (negative) rows already found. Use Ext. Price / cost extended amounts only (never retail).'
                .' Return the same JSON shape; put only the missing purchase rows in "lines". If nothing is missing, return "lines": [].';

            $results = $this->runParallel($apiKey, $model, ['more' => $ask], $attachment);
            $more = $results['more'] ?? null;
            if ($more === null) {
                continue;
            }

            $before = count($data['lines']);
            $candidate = $this->reconcilePurchaseAmounts($this->dropSummaryRows(array_merge($data, [
                'lines' => $this->mergeNewLines($data['lines'], $more['lines'] ?? []),
            ])));
            $candSum = $this->extractedPurchaseSum($candidate);
            $oldGap = abs($target - $sum);
            $candGap = abs($target - $candSum);
            // Do not accept a completion that flips a short read into a large overshoot
            // (that was producing "over by $110" after filling a short gap).
            $crossedToOver = $sum <= $target + 1.0 && $candSum > $target + 1.0;
            if ($candGap + 0.01 < $oldGap && ! ($crossedToOver && $candGap > max(5.0, $target * 0.002))) {
                $data = $candidate;
            }
            $added = count($data['lines']) - $before;

            Log::info('InvoiceExtractionService: completion pass', [
                'attempt' => $attempt,
                'added' => $added,
                'lines' => count($data['lines']),
                'purchase_sum' => round($this->extractedPurchaseSum($data), 2),
                'target' => round($target, 2),
            ]);

            if ($added === 0) {
                break;
            }
        }

        return $this->reconcilePurchaseAmounts($data);
    }

    /**
     * Fix qty×unit vs Ext mismatches, drop duplicate rows, and trim overshoot toward the merchandise target.
     * Credit/return (negative) rows stay; purchase match uses subtotal + |credits|.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function reconcilePurchaseAmounts(array $data): array
    {
        $lines = array_values((array) ($data['lines'] ?? []));
        if ($lines === []) {
            return $data;
        }

        // Prefer printed Ext. Price when qty×unit disagrees (OCR often swaps in retail).
        foreach ($lines as $i => $line) {
            $qty = abs((float) ($line['quantity'] ?? 0));
            if ($qty < 0.0001) {
                $qty = 1.0;
            }
            $unit = abs((float) ($line['unit_price'] ?? 0));
            $ext = (float) ($line['line_total'] ?? 0);
            $absExt = abs($ext);
            $computed = round($qty * $unit, 4);
            $isCredit = $ext < -0.0001;

            if ($absExt > 0.0001 && $unit > 0 && abs($computed - $absExt) > 0.02) {
                // Keep printed extended; derive unit from it.
                $unit = round($absExt / $qty, 4);
                $computed = $absExt;
            } elseif ($absExt <= 0.0001 && $unit > 0) {
                $absExt = $computed;
            } elseif ($unit <= 0 && $absExt > 0.0001) {
                $unit = round($absExt / $qty, 4);
            }

            $lines[$i]['quantity'] = $qty;
            $lines[$i]['unit_price'] = $unit;
            $lines[$i]['line_total'] = $isCredit ? -1 * $absExt : $absExt;
        }
        $data['lines'] = $lines;

        $target = $this->extractedMerchandiseTarget($data);
        if ($target <= 0.05) {
            return $data;
        }

        $purchaseSum = $this->extractedPurchaseSum($data);
        if ($purchaseSum <= $target + max(1.0, $target * 0.0002)) {
            return $data;
        }

        // Drop exact duplicates (same sku/name + qty + ext) — page-break / completion doubles.
        $seen = [];
        $deduped = [];
        foreach ($lines as $line) {
            $key = $this->lineKey($line);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $line;
        }
        $data['lines'] = $deduped;
        $purchaseSum = $this->extractedPurchaseSum($data);
        if ($purchaseSum <= $target + max(1.0, $target * 0.0002)) {
            Log::info('InvoiceExtractionService: trimmed exact duplicate lines', [
                'dropped' => count($lines) - count($deduped),
                'purchase_sum' => round($purchaseSum, 2),
                'target' => round($target, 2),
            ]);

            return $data;
        }

        // Soft duplicates: same identity + qty (amount may differ slightly). Keep the first;
        // only drop later ones while still over.
        $softSeen = [];
        $softKeep = [];
        $running = 0.0;
        $creditsOnly = [];
        foreach ($deduped as $line) {
            $ext = (float) ($line['line_total'] ?? 0);
            if ($ext < -0.0001) {
                $creditsOnly[] = $line;

                continue;
            }
            $soft = $this->softLineKey($line);
            if (isset($softSeen[$soft]) && ($running - $target) > 1.0) {
                // Skip this duplicate while overshoot remains.
                continue;
            }
            $softSeen[$soft] = true;
            $softKeep[] = $line;
            $running += max(0, $ext);
        }
        $merged = array_merge($softKeep, $creditsOnly);
        $candidate = array_merge($data, ['lines' => $merged]);
        if (abs($target - $this->extractedPurchaseSum($candidate)) < abs($target - $purchaseSum)) {
            Log::info('InvoiceExtractionService: trimmed soft duplicate purchase lines', [
                'dropped' => count($deduped) - count($merged),
                'purchase_sum' => round($this->extractedPurchaseSum($candidate), 2),
                'target' => round($target, 2),
            ]);

            return $candidate;
        }

        return $data;
    }

    /**
     * Identity for soft dedupe (ignores tiny amount OCR drift).
     *
     * @param  array<string, mixed>  $line
     */
    protected function softLineKey(array $line): string
    {
        $id = trim((string) ($line['sku'] ?? ''))
            ?: trim((string) ($line['barcode'] ?? ''))
            ?: mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) ($line['name'] ?? ''))) ?? '');

        return $id.'|'.number_format(abs((float) ($line['quantity'] ?? 0)), 2, '.', '');
    }

    /**
     * @param  array<string, string>  $prompts  key => user prompt
     * @param  array<string, mixed>|null  $attachment  file / image part, or null for text-only prompts
     * @return array<string, array<string, mixed>|null> key => normalized read (null when that call failed)
     */
    protected function runParallel(string $apiKey, string $model, array $prompts, ?array $attachment): array
    {
        $out = [];
        foreach (array_chunk($prompts, self::PARALLEL_REQUESTS, true) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $apiKey, $model, $attachment) {
                $requests = [];
                foreach ($chunk as $key => $text) {
                    $requests[] = $pool->as((string) $key)
                        ->withToken($apiKey)
                        ->timeout(240)
                        ->post(self::RESPONSES_URL, $this->responsesPayload($model, $text, $attachment));
                }

                return $requests;
            });

            foreach (array_keys($chunk) as $key) {
                $out[$key] = $this->readResponse($responses[$key] ?? null, (string) $key);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readResponse(mixed $response, string $key): ?array
    {
        if (! $response instanceof Response) {
            $this->lastError = $response instanceof \Throwable ? $response->getMessage() : 'OpenAI request failed.';
            Log::warning('InvoiceExtractionService: request failed', ['key' => $key, 'error' => $this->lastError]);

            return null;
        }
        if (! $response->successful()) {
            $this->lastError = (string) ($response->json('error.message') ?: $response->body());
            Log::warning('InvoiceExtractionService: request failed', ['key' => $key, 'status' => $response->status(), 'error' => $this->lastError]);

            return null;
        }

        $content = $this->extractResponsesText((array) $response->json());
        $parsed = $content ? $this->parseJsonLoosely($content) : null;
        if (! is_array($parsed)) {
            $this->lastError = 'OpenAI did not return readable invoice data.';
            Log::warning('InvoiceExtractionService: unreadable JSON', [
                'key' => $key,
                'status' => $response->json('status'),
                'reason' => $response->json('incomplete_details.reason'),
                'chars' => strlen((string) $content),
                'tail' => mb_substr((string) $content, -300),
            ]);

            return null;
        }

        return $this->normalize($parsed);
    }

    /**
     * @param  array<string, mixed>|null  $attachment
     * @return array<string, mixed>
     */
    protected function responsesPayload(string $model, string $userText, ?array $attachment): array
    {
        $content = [['type' => 'input_text', 'text' => $userText]];
        if ($attachment !== null) {
            $content[] = $attachment;
        }
        $payload = [
            'model' => $model,
            'max_output_tokens' => 16000,
            'text' => ['format' => ['type' => 'json_object']],
            'input' => [
                [
                    'role' => 'system',
                    'content' => [['type' => 'input_text', 'text' => self::SYSTEM_PROMPT]],
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ];
        // Reasoning models reject a temperature setting.
        if (! preg_match('/^(gpt-5|o\d)/i', $model)) {
            $payload['temperature'] = 0;
        }

        return $payload;
    }

    protected function fullReadText(int $pageCount): string
    {
        $pages = $pageCount > 1
            ? 'This document has '.$pageCount.' pages. Read page 1, then page 2, and so on to page '.$pageCount.'. '
            : '';

        return 'Extract the invoice data as instructed and return the JSON object. '.$pages
            .'Every product row on every page must be in "lines". The sum of line_total must equal the invoice subtotal (merchandise only), not the invoice total. Put delivery in freight and other charges in other_fees.';
    }

    protected function pageTextPrompt(int $page, int $pageCount, string $text): string
    {
        return 'Below is the exact text of page '.$page.' of '.$pageCount.' of a vendor invoice PDF (table columns are separated by tabs or spaces).'
            .' Return every product row in this text, in order, none skipped — from the first row to the last.'
            .' Fill supplier_name, ref_no and invoice_date only if they appear in this text.'
            .' Fill subtotal, freight, other_fees, tax_amount and total only if those figures appear in this text, otherwise null. Subtotal is merchandise only. Do not put delivery or other fees in lines.'
            .' Return the JSON object.'
            ."\n\n----- PAGE ".$page." TEXT -----\n".$text."\n----- END OF PAGE ".$page.' -----';
    }

    protected function pdfPageCount(string $bytes): int
    {
        $count = $this->pageCountFromPdfText($bytes);
        if ($count > 0) {
            return $count;
        }

        // PDF 1.5+ often hides page objects inside compressed object streams.
        $inflated = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $m)) {
            foreach ($m[1] as $stream) {
                $plain = @gzuncompress($stream);
                if ($plain === false) {
                    $plain = @gzinflate(substr($stream, 2));
                }
                if (is_string($plain) && str_contains($plain, '/Type')) {
                    $inflated .= $plain."\n";
                }
            }
        }

        return $this->pageCountFromPdfText($inflated);
    }

    protected function pageCountFromPdfText(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $fromTree = 0;
        if (preg_match_all('/<<(?:(?!<<|>>).)*?\/Type\s*\/Pages\b(?:(?!<<|>>).)*>>/s', $text, $m)) {
            foreach ($m[0] as $dict) {
                if (preg_match('/\/Count\s+(\d+)/', $dict, $c)) {
                    $fromTree = max($fromTree, (int) $c[1]);
                }
            }
        }
        if ($fromTree > 0) {
            return $fromTree;
        }

        return (int) preg_match_all('/\/Type\s*\/Page(?![A-Za-z])/', $text);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array<string, mixed>>  $extra
     * @return list<array<string, mixed>>
     */
    protected function mergeNewLines(array $lines, array $extra): array
    {
        $seen = [];
        foreach ($lines as $line) {
            $seen[$this->lineKey($line)] = true;
        }
        foreach ($extra as $line) {
            $key = $this->lineKey($line);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * When the rows overshoot the subtotal, drop category / recap rows (no item code, no digits in the name)
     * if that brings the sum closer to the subtotal.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function dropSummaryRows(array $data): array
    {
        $target = $this->extractedMerchandiseTarget($data);
        $sum = $this->extractedPurchaseSum($data);
        if ($target <= 0.05 || $sum <= $target + max(1.0, $target * 0.0002)) {
            return $data;
        }

        $keep = array_values(array_filter(
            (array) ($data['lines'] ?? []),
            fn (array $line) => trim((string) ($line['sku'] ?? '')) !== ''
                || preg_match('/\d/', (string) ($line['name'] ?? ''))
        ));
        $trimmed = array_merge($data, ['lines' => $keep]);
        if ($keep !== [] && abs($target - $this->extractedPurchaseSum($trimmed)) < abs($target - $sum)) {
            Log::info('InvoiceExtractionService: dropped summary rows', [
                'dropped' => count($data['lines']) - count($keep),
                'sum' => round($this->extractedPurchaseSum($trimmed), 2),
                'target' => round($target, 2),
            ]);

            return $trimmed;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function lineKey(array $line): string
    {
        $id = trim((string) ($line['sku'] ?? '')) ?: mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) ($line['name'] ?? ''))) ?? '');

        return $id.'|'.number_format((float) ($line['quantity'] ?? 0), 2, '.', '')
            .'|'.number_format((float) ($line['line_total'] ?? 0), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractedLineSum(array $data): float
    {
        return (float) collect($data['lines'] ?? [])->sum(fn ($l) => (float) ($l['line_total'] ?? 0));
    }

    /** Sum of purchase (positive) product rows only. */
    public function extractedPurchaseSum(array $data): float
    {
        return round((float) collect($data['lines'] ?? [])->sum(fn ($l) => max(0, (float) ($l['line_total'] ?? 0))), 2);
    }

    /** Absolute sum of credit/return (negative) product rows. */
    public function extractedReturnSum(array $data): float
    {
        return round((float) collect($data['lines'] ?? [])->sum(function ($l) {
            $ext = (float) ($l['line_total'] ?? 0);

            return $ext < 0 ? abs($ext) : 0;
        }), 2);
    }

    /**
     * What positive purchase lines must add up to.
     * Vendor subtotals are usually net of credits, so purchases ≈ subtotal + returns.
     *
     * @param  array<string, mixed>  $data
     */
    public function extractedMerchandiseTarget(array $data): float
    {
        $subtotal = $this->extractedHeaderTarget($data);
        if ($subtotal <= 0.05) {
            return 0.0;
        }

        return round($subtotal + $this->extractedReturnSum($data), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractedHeaderTarget(array $data): float
    {
        $subtotal = (float) ($data['subtotal'] ?? 0);
        if ($subtotal > 0) {
            return $subtotal;
        }
        // Invoice total includes delivery and other fees. Use it only to back out those charges.
        $fees = (float) ($data['freight'] ?? 0)
            + (float) ($data['other_fees'] ?? 0)
            + (float) ($data['tax_amount'] ?? 0);
        $total = (float) ($data['total'] ?? 0);
        if ($total > 0 && $fees > 0.0001) {
            return max(0, $total - $fees);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function isCompleteRead(array $data): bool
    {
        $target = $this->extractedMerchandiseTarget($data);
        if ($target <= 0.05 || empty($data['lines'])) {
            return false;
        }

        return abs($target - $this->extractedPurchaseSum($data)) <= max(1.0, $target * 0.0002);
    }

    protected function extractResponsesText(array $json): ?string
    {
        if (! empty($json['output_text'])) {
            return (string) $json['output_text'];
        }

        foreach ((array) ($json['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (in_array($part['type'] ?? null, ['output_text', 'text'], true) && ! empty($part['text'])) {
                    return (string) $part['text'];
                }
            }
        }

        return null;
    }

    protected function parseJsonLoosely(string $content): ?array
    {
        $parsed = json_decode($content, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        if (preg_match('/\{.*\}/s', $content, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    protected function normalize(array $parsed): array
    {
        $lines = [];
        foreach ((array) ($parsed['lines'] ?? []) as $line) {
            if (empty($line['name']) && empty($line['sku']) && empty($line['barcode'])) {
                continue;
            }
            $rawQty = $this->toNumber($line['quantity'] ?? null) ?? 1.0;
            $rawUnit = $this->toNumber($line['unit_price'] ?? null) ?? 0.0;
            $rawExt = $this->toNumber($line['line_total'] ?? null);
            $isCredit = ($rawExt !== null && $rawExt < 0) || $rawQty < 0 || $rawUnit < 0;
            $qty = abs($rawQty) > 0 ? abs($rawQty) : 1.0;
            $unit = abs($rawUnit);
            $ext = abs((float) ($rawExt ?? 0));
            // Ext. Price wins when qty×unit disagrees (cost vs retail column mix-ups).
            if ($ext > 0 && $unit > 0 && abs(round($qty * $unit, 4) - $ext) > 0.02) {
                $unit = round($ext / $qty, 4);
            } elseif ($unit <= 0 && $ext > 0) {
                $unit = $ext / $qty;
            }
            if ($ext <= 0) {
                $ext = round($qty * $unit, 4);
            }
            $sku = trim((string) ($line['sku'] ?? ''));
            $barcode = trim((string) ($line['barcode'] ?? ''));
            if ($sku !== '' && preg_match('/^0+$/', $sku)) {
                $sku = '';
            }
            if ($barcode !== '' && preg_match('/^0+$/', $barcode)) {
                $barcode = '';
            }
            $lines[] = [
                'name' => (string) ($line['name'] ?? $line['sku'] ?? $line['barcode'] ?? 'Item'),
                'sku' => $sku !== '' ? $sku : null,
                'barcode' => $barcode !== '' ? $barcode : null,
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $isCredit ? -$ext : $ext,
            ];
        }

        $freight = max(0, (float) ($this->toNumber($parsed['freight'] ?? null) ?? 0));
        $otherFees = max(0, (float) ($this->toNumber($parsed['other_fees'] ?? null) ?? 0));
        $productLines = [];
        foreach ($lines as $line) {
            $fee = $this->feeKind($line);
            if ($fee === 'freight') {
                if ($freight <= 0) {
                    $freight += abs((float) $line['line_total']);
                }

                continue;
            }
            if ($fee === 'other') {
                if ($otherFees <= 0) {
                    $otherFees += abs((float) $line['line_total']);
                }

                continue;
            }
            $productLines[] = $line;
        }

        return [
            'supplier_name' => ! empty($parsed['supplier_name']) ? (string) $parsed['supplier_name'] : null,
            'ref_no' => ! empty($parsed['ref_no']) ? (string) $parsed['ref_no'] : null,
            'invoice_date' => ! empty($parsed['invoice_date']) ? (string) $parsed['invoice_date'] : null,
            'currency' => ! empty($parsed['currency']) ? (string) $parsed['currency'] : null,
            'lines' => $productLines,
            'subtotal' => $this->toNumber($parsed['subtotal'] ?? null),
            'freight' => round($freight, 2),
            'other_fees' => round($otherFees, 2),
            'tax_amount' => $this->toNumber($parsed['tax_amount'] ?? null),
            'total' => $this->toNumber($parsed['total'] ?? null),
        ];
    }

    /**
     * Delivery and other charges are not merchandise. A real item code stays a product.
     *
     * @param  array<string, mixed>  $line
     * @return 'freight'|'other'|null
     */
    protected function feeKind(array $line): ?string
    {
        $sku = trim((string) ($line['sku'] ?? ''));
        $barcode = trim((string) ($line['barcode'] ?? ''));
        foreach ([$sku, $barcode] as $code) {
            if ($code !== '' && ! preg_match('/fee|frt|ship|deliv|fuel|handl/i', $code) && preg_match('/\d/', $code)) {
                return null;
            }
        }
        $name = mb_strtolower(trim((string) ($line['name'] ?? '')));
        if ($name === '') {
            return null;
        }
        if (preg_match('/\b(delivery|freight|shipping|ship(?:ping)? charge)\b/', $name)) {
            return 'freight';
        }
        if (preg_match('/\b(fuel|handling|service fee|other fee|miscellaneous|surcharge)\b/', $name)) {
            return 'other';
        }

        return null;
    }

    protected function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        $negative = str_starts_with($value, '-') || str_ends_with($value, '-')
            || (str_starts_with($value, '(') && str_ends_with($value, ')'));
        $s = preg_replace('/[^0-9.]/', '', $value) ?? '';
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return $negative ? -(float) $s : (float) $s;
    }
}
