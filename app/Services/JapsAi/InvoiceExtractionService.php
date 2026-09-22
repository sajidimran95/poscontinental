<?php

namespace App\Services\JapsAi;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\Supplier;
use App\Support\ItemSearch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * JapsAI vendor invoice / bill reader (japspos workflow).
 * Extracts structured lines via OpenAI. Persistence of the PO is handled by VendorInvoicePurchaseOrderService.
 */
class InvoiceExtractionService
{
    protected const SYSTEM_PROMPT = <<<'PROMPT'
You are reading a photo, scan, or PDF of a vendor purchase invoice / bill for a wholesale POS system.
Extract the data and return ONLY a single valid JSON object (no markdown, no commentary) with this exact shape:

{
  "supplier_name": string|null,
  "ref_no": string|null,
  "invoice_date": string|null,
  "currency": string|null,
  "lines": [
    { "name": string, "sku": string|null, "quantity": number, "unit_price": number, "line_total": number|null }
  ],
  "subtotal": number|null,
  "tax_amount": number|null,
  "total": number|null
}

Rules:
- "invoice_date" must be formatted as YYYY-MM-DD if you can determine it, else null.
- "lines" must contain one entry per distinct product/item row on the invoice. If the file has multiple pages, include line items from every page.
- "quantity" and "unit_price" must be plain numbers only (no currency symbols, no thousands separators).
- "unit_price" is the billed cost for one of the quantity units on that row (what the vendor charged), not a catalog or list price.
- "line_total" is the extended amount printed for that row when available (quantity × unit_price). If unit_price is missing, compute unit_price = line_total / quantity.
- "quantity" and "unit_price" must use the same unit as the billed row (do not convert cases to eaches unless the invoice already shows eaches).
- If a SKU / item code / barcode is printed next to a line, put it in "sku", else null.
- Do not invent data that is not visibly on the document. Use null when unsure.
- Return ONLY the JSON object, nothing else.
PROMPT;

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

        try {
            $isPdf = $mime === 'application/pdf' || $extension === 'pdf';
            if ($isPdf) {
                // OpenAI file ingest is case-sensitive: ".PDF" is rejected, ".pdf" is not.
                return $this->extractFromPdf($apiKey, $model, $path, 'invoice.pdf');
            }

            if ($mime === '') {
                $mime = 'image/jpeg';
            }

            $mime = $this->normalizeImageMime($mime, $extension);

            return $this->extractFromImage($apiKey, $model, $path, $mime);
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
        $lines = [];
        foreach ((array) ($data['lines'] ?? []) as $line) {
            $name = trim((string) ($line['name'] ?? ''));
            $sku = trim((string) ($line['sku'] ?? ''));
            if ($name === '' && $sku === '') {
                continue;
            }
            $item = $this->matchItem($companyId, $sku !== '' ? $sku : null, $name !== '' ? $name : null);
            $lines[] = [
                'name' => $name !== '' ? $name : ($sku ?: 'Item'),
                'sku' => $sku !== '' ? $sku : null,
                'quantity' => max(0.01, (float) ($line['quantity'] ?? 1)),
                'unit_price' => max(0, (float) ($line['unit_price'] ?? 0)),
                'line_total' => isset($line['line_total']) ? (float) $line['line_total'] : null,
                'selected' => true,
                'item_id' => $item?->id,
                'item_code' => $item?->item_code,
                'description' => $item?->description ?? $name,
                'status' => $item ? 'matched' : 'unmatched',
            ];
        }

        $supplierId = $this->matchSupplier($companyId, $data['supplier_name'] ?? null)
            ?? $this->inferSupplierFromLines($companyId, $lines);

        return [
            'supplier_id' => $supplierId,
            'supplier_name' => $data['supplier_name'] ?? null,
            'ref_no' => $data['ref_no'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'currency' => $data['currency'] ?? null,
            'subtotal' => $data['subtotal'] ?? null,
            'tax_amount' => $data['tax_amount'] ?? null,
            'total' => $data['total'] ?? null,
            'lines' => $lines,
        ];
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

    protected function matchItem(int $companyId, ?string $sku, ?string $name): ?Item
    {
        if ($sku) {
            $byCode = Item::findByScanCode($companyId, $sku);
            if ($byCode) {
                return $byCode;
            }
            $exact = Item::query()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($sku) {
                    $q->where('item_code', $sku)->orWhere('primary_upc', $sku);
                })
                ->first();
            if ($exact) {
                return $exact;
            }
        }

        if ($name) {
            $q = Item::query()->where('company_id', $companyId)->where('is_inactive', false);
            ItemSearch::constrain($q, $name);
            $row = $q->orderBy('item_code')->first();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    protected function resolveApiKey(): string
    {
        $fromCompany = trim((string) ($this->company->japs_ai_api_key ?? ''));
        if ($fromCompany !== '') {
            return $fromCompany;
        }

        return trim((string) env('OPENAI_API_KEY', ''));
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
        $encoded = base64_encode((string) file_get_contents($path));
        $dataUri = 'data:'.$mime.';base64,'.$encoded;

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Extract the invoice data as instructed.'],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return ['success' => false, 'msg' => $response->json('error.message') ?: $response->body()];
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (empty($content)) {
            return ['success' => false, 'msg' => 'OpenAI returned an empty response.'];
        }

        $parsed = $this->parseJsonLoosely((string) $content);
        if (! is_array($parsed)) {
            return ['success' => false, 'msg' => 'Could not read structured data from this image. Try a clearer photo.'];
        }

        return ['success' => true, 'data' => $this->normalize($parsed)];
    }

    /**
     * @return array{success: bool, msg?: string, data?: array<string, mixed>}
     */
    protected function extractFromPdf(string $apiKey, string $model, string $path, string $filename = 'invoice.pdf'): array
    {
        $safeName = 'invoice.pdf';
        $upload = Http::withToken($apiKey)
            ->timeout(60)
            ->attach('file', (string) file_get_contents($path), $safeName)
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
            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'temperature' => 0,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => [
                                ['type' => 'input_text', 'text' => self::SYSTEM_PROMPT],
                            ],
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'input_text', 'text' => 'Extract the invoice data as instructed.'],
                                ['type' => 'input_file', 'file_id' => $fileId],
                            ],
                        ],
                    ],
                ]);
        } finally {
            try {
                Http::withToken($apiKey)->delete('https://api.openai.com/v1/files/'.$fileId);
            } catch (\Throwable) {
                // ignore cleanup errors
            }
        }

        if (! $response->successful()) {
            return ['success' => false, 'msg' => $response->json('error.message') ?: $response->body()];
        }

        $content = $this->extractResponsesText((array) $response->json());
        if (empty($content)) {
            return ['success' => false, 'msg' => 'OpenAI returned an empty response for this PDF.'];
        }

        $parsed = $this->parseJsonLoosely($content);
        if (! is_array($parsed)) {
            return ['success' => false, 'msg' => 'Could not read structured data from this PDF.'];
        }

        return ['success' => true, 'data' => $this->normalize($parsed)];
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
            if (empty($line['name']) && empty($line['sku'])) {
                continue;
            }
            $qty = is_numeric($line['quantity'] ?? null) ? (float) $line['quantity'] : 1.0;
            $qty = $qty > 0 ? $qty : 1.0;
            $unit = is_numeric($line['unit_price'] ?? null) ? (float) $line['unit_price'] : 0.0;
            $ext = is_numeric($line['line_total'] ?? null) ? (float) $line['line_total'] : 0.0;
            if ($unit <= 0 && $ext > 0) {
                $unit = $ext / $qty;
            }
            $lines[] = [
                'name' => (string) ($line['name'] ?? $line['sku'] ?? 'Item'),
                'sku' => ! empty($line['sku']) ? (string) $line['sku'] : null,
                'quantity' => $qty,
                'unit_price' => max(0, $unit),
                'line_total' => $ext > 0 ? $ext : round($qty * max(0, $unit), 4),
            ];
        }

        return [
            'supplier_name' => ! empty($parsed['supplier_name']) ? (string) $parsed['supplier_name'] : null,
            'ref_no' => ! empty($parsed['ref_no']) ? (string) $parsed['ref_no'] : null,
            'invoice_date' => ! empty($parsed['invoice_date']) ? (string) $parsed['invoice_date'] : null,
            'currency' => ! empty($parsed['currency']) ? (string) $parsed['currency'] : null,
            'lines' => $lines,
            'subtotal' => is_numeric($parsed['subtotal'] ?? null) ? (float) $parsed['subtotal'] : null,
            'tax_amount' => is_numeric($parsed['tax_amount'] ?? null) ? (float) $parsed['tax_amount'] : null,
            'total' => is_numeric($parsed['total'] ?? null) ? (float) $parsed['total'] : null,
        ];
    }
}
