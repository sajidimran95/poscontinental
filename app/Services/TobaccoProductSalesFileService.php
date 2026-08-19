<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Support\TobaccoItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * JAPS POS fixed-width tobacco product MSA sales file.
 * Layout locked to the approved sample:
 * requirment/NASHVILLE_GOODS_DISTRIBUTION_TOB_17033752_20260808.txt
 *
 * HID 337 · BID 275 · SID 551 · PUR 158 · TOT 194
 * Tobacco items only (type / brand / sticks / oz). No all-category fallback.
 */
class TobaccoProductSalesFileService
{
    public const HID_LEN = 337;

    public const BID_LEN = 275;

    public const SID_LEN = 551;

    public const PUR_LEN = 158;

    public const TOT_LEN = 194;

    /** Company / customer name width (approved sample uses 32). */
    public const NAME_LEN = 32;

    /** Street / location address width (approved sample uses 90). */
    public const ADDR_LEN = 90;

    /** Approved HID field after version 0002 — always 00000004. */
    public const HID_FORMAT_ID = '00000004';

    /** BID description starts after BID(3)+UPC14+SKU14. */
    public const BID_DESC_AT = 31;

    public const BID_DESC_LEN = 100;

    public const BID_SIZE_AT = 131;

    public const BID_NACS_AT = 144;

    public const BID_PROMO_AT = 166;

    public const BID_STATE_AT = 207;

    public const BID_PRICE_TAG_AT = 247;

    public function build(Company $company, string $periodStart, string $periodEnd, Collection $invoices, string $product = 'all'): string
    {
        $product = $this->normalizeProduct($product);
        [$periodStart, $periodEnd] = $this->msaSundayToSaturday($periodStart, $periodEnd);
        $periodYmd = $this->ymd($periodEnd) ?: now()->format('Ymd');
        $fileYmd = now()->format('Ymd');
        $license = $this->licenseDigits($company, $product);
        $items = $this->collectItemsFromInvoices($invoices, $product);
        $customers = $this->collectCustomerSalesFromInvoices($invoices, $product);

        $lines = [];
        $lines[] = $this->hid($company, $license, $periodYmd, $fileYmd);

        $itemIndex = [];
        $companyState = $this->companyLocation($company)['state'];
        $totalOnHand = 0;
        foreach ($items as $itemId => $meta) {
            $code14 = $this->itemCode14($meta['item']);
            $itemIndex[(int) $itemId] = $code14;
            $onHand = $this->onHandQty($meta['item']);
            $totalOnHand += $onHand;
            $lines[] = $this->bid($code14, $meta['item'], $companyState, $onHand);
        }

        $purCount = 0;
        $totalQty = 0;
        $totalAmountCents = 0;
        $sidCount = 0;

        foreach ($customers as $bucket) {
            $sidCount++;
            $custKey = $this->customerKey($bucket['customer'], $sidCount);
            $lines[] = $this->sid($custKey, $bucket['customer'], $company, $sidCount);

            foreach ($bucket['lines'] as $sale) {
                /** @var Item $item */
                $item = $sale['item'];
                $code14 = $itemIndex[(int) $item->id] ?? $this->itemCode14($item);
                $qty = (float) $sale['qty'];
                $amount = (float) $sale['amount'];
                $qtyInt = max(0, (int) round($qty));
                $cents = max(0, (int) round($amount * 100));
                $lines[] = $this->pur($custKey, $code14, $sale['date'], $qtyInt, $cents);
                $purCount++;
                $totalQty += $qtyInt;
                $totalAmountCents += $cents;
            }
        }

        $lines[] = $this->tot(
            $license,
            $periodYmd,
            $items->count(),
            $sidCount,
            $purCount,
            $totalQty,
            $totalAmountCents,
            $totalOnHand
        );

        // One record = one line. Never allow CR/LF inside a field (address, name, desc).
        $lines = array_map(fn (string $line) => str_replace(["\r", "\n"], '', $line), $lines);

        return implode("\r\n", $lines).($lines === [] ? '' : "\r\n");
    }

    public function filename(Company $company, string $periodEnd, string $product = 'all'): string
    {
        $product = $this->normalizeProduct($product);
        $name = (string) ($company->name ?: $company->code ?: 'COMPANY');
        $slug = Str::upper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($name)) ?: 'COMPANY');
        $slug = trim($slug, '_');
        $tag = $product === 'cigarettes' ? 'CIG' : 'TOB';
        $license = $this->licenseDigits($company, $product);
        $date = $this->ymd($periodEnd) ?: now()->format('Ymd');

        return $slug.'_'.$tag.'_'.$license.'_'.$date.'.txt';
    }

    /**
     * One MSA TOB file = Sunday through Saturday. End date is always Saturday.
     *
     * @return array{0: string, 1: string}
     */
    public function msaSundayToSaturday(string $periodStart, string $periodEnd): array
    {
        $end = $this->parseDay($periodEnd) ?? $this->parseDay($periodStart) ?? Carbon::today();
        $lastSaturday = Carbon::today()->dayOfWeek === Carbon::SATURDAY
            ? Carbon::today()->startOfDay()
            : Carbon::today()->previous(Carbon::SATURDAY)->startOfDay();

        $weekEnd = $end->copy()->endOfWeek(Carbon::SATURDAY)->startOfDay();
        if ($weekEnd->gt($lastSaturday)) {
            $weekEnd = $lastSaturday->copy();
        }

        $weekStart = $weekEnd->copy()->subDays(6);

        return [$weekStart->toDateString(), $weekEnd->toDateString()];
    }

    protected function parseDay(string $date): ?Carbon
    {
        $ymd = $this->ymd($date);
        if (strlen($ymd) !== 8) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Ymd', $ymd)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function ymd(string $date): string
    {
        $digits = preg_replace('/\D+/', '', $date) ?? '';
        if (strlen($digits) >= 8) {
            return substr($digits, 0, 8);
        }

        return '';
    }

    protected function normalizeProduct(string $product): string
    {
        return match ($product) {
            'cigarettes' => 'cigarettes',
            'otp' => 'otp',
            default => 'all',
        };
    }

    /**
     * MULTICAT HID/TOT columns 4–11: MSA Distributor ID from Company Settings (not hardcoded).
     */
    protected function licenseDigits(Company $company, string $product = 'all'): string
    {
        $raw = $company->msaDistributorId();
        if ($raw === '') {
            $raw = match ($product) {
                'cigarettes' => $company->msaLicenseNumber('cigarettes'),
                'otp' => $company->msaLicenseNumber('otp'),
                default => $company->msaLicenseNumber('otp') ?: $company->msaLicenseNumber('cigarettes'),
            };
        }

        $digits = preg_replace('/\D+/', '', (string) ($raw ?: '0')) ?: '0';

        return $this->numDigits($digits, 8);
    }

    /**
     * @return Collection<int, array{item: Item, qty: float, amount: float}>
     */
    protected function collectTobaccoItems(Collection $invoices): Collection
    {
        return $this->collectItemsFromInvoices($invoices, 'all');
    }

    /**
     * @return Collection<int, array{item: Item, qty: float, amount: float}>
     */
    protected function collectItemsFromInvoices(Collection $invoices, string $product): Collection
    {
        $map = [];

        foreach ($invoices as $invoice) {
            foreach ($this->invoiceLines($invoice) as $line) {
                $item = $line->item ?? null;
                if (! $item) {
                    continue;
                }
                if (! $this->itemMatchesFileProduct($item, $product)) {
                    continue;
                }
                $id = (int) $item->id;
                $qty = $this->lineQty($line);
                if ($qty == 0.0) {
                    continue;
                }
                $amount = max(0, $qty * (float) $line->price - (float) ($line->discount ?? 0));
                if (! isset($map[$id])) {
                    $map[$id] = ['item' => $item, 'qty' => 0.0, 'amount' => 0.0];
                }
                $map[$id]['qty'] += $qty;
                $map[$id]['amount'] += $amount;
            }
        }

        ksort($map);

        return collect($map)->sortBy(fn ($row) => $this->itemCode14($row['item']))->values()
            ->mapWithKeys(function ($row) {
                return [(int) $row['item']->id => $row];
            });
    }

    /**
     * @return array<int, array{customer: ?Customer, lines: list<array{item: Item, qty: float, amount: float, date: string}>}>
     */
    protected function collectCustomerSales(Collection $invoices): array
    {
        return $this->collectCustomerSalesFromInvoices($invoices, 'all');
    }

    /**
     * @return array<int, array{customer: ?Customer, lines: list<array{item: Item, qty: float, amount: float, date: string}>}>
     */
    protected function collectCustomerSalesFromInvoices(Collection $invoices, string $product): array
    {
        $out = [];

        foreach ($invoices as $invoice) {
            $customer = $invoice->customer ?? $invoice->salesOrder?->customer;
            $cid = (int) ($customer?->id ?: 0);
            if (! isset($out[$cid])) {
                $out[$cid] = ['customer' => $customer, 'lines' => []];
            }

            foreach ($this->invoiceLines($invoice) as $line) {
                $item = $line->item ?? null;
                if (! $item) {
                    continue;
                }
                if (! $this->itemMatchesFileProduct($item, $product)) {
                    continue;
                }
                $qty = $this->lineQty($line);
                if ($qty == 0.0) {
                    continue;
                }
                $amount = max(0, $qty * (float) $line->price - (float) ($line->discount ?? 0));
                $out[$cid]['lines'][] = [
                    'item' => $item,
                    'qty' => $qty,
                    'amount' => $amount,
                    'date' => optional($invoice->invoice_date)?->format('Ymd') ?: now()->format('Ymd'),
                ];
            }

            if ($out[$cid]['lines'] === []) {
                unset($out[$cid]);
            }
        }

        ksort($out);

        return $out;
    }

    protected function lineQty(object $line): float
    {
        $shipped = (float) ($line->qty_shipped ?? 0);
        $ordered = (float) ($line->qty_ordered ?? 0);

        // Prefer shipped when present; otherwise ordered (many invoiced orders leave shipped = 0).
        return $shipped > 0 ? $shipped : $ordered;
    }

    protected function invoiceLines(Invoice $invoice): Collection
    {
        $order = $invoice->salesOrder;
        if (! $order) {
            return collect();
        }
        if (! $order->relationLoaded('lines')) {
            $order->load(['lines.item.category', 'lines.item.subcategory']);
        }

        return collect($order->lines ?? []);
    }

    protected function isTobaccoItem(?Item $item): bool
    {
        return TobaccoItem::isTobacco($item);
    }

    protected function itemMatchesFileProduct(?Item $item, string $product): bool
    {
        if (! $item || ! TobaccoItem::isTobacco($item) || $this->isExcludedFromTobFile($item)) {
            return false;
        }

        if ($product === 'all') {
            return true;
        }

        $isCig = TobaccoItem::kind($item) === 'cigarettes';

        return $product === 'cigarettes' ? $isCig : ! $isCig;
    }

    protected function isExcludedFromTobFile(Item $item): bool
    {
        $hay = strtolower(trim(
            (string) ($item->description ?? '').' '
            .(string) ($item->item_code ?? '').' '
            .(string) ($item->category?->name ?? '').' '
            .(string) ($item->subcategory?->name ?? '')
        ));

        foreach (['hemp', 'thc', 'delta-8', 'delta-9', 'delta 8', 'delta 9', 'delta8', 'delta9', 'cannabis'] as $needle) {
            if (str_contains($hay, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Items per selling unit (pack 20, carton 200, 5ct pouch 5). */
    protected function itemsPerSellingUnit(Item $item): int
    {
        $desc = strtoupper((string) ($item->description ?: $item->item_code));
        if (preg_match('/\b(\d+)\s*CT\b/', $desc, $m)) {
            return max(1, (int) $m[1]);
        }
        if (str_contains($desc, 'CARTON')) {
            return 200;
        }

        $pack = (int) ($item->cigarette_pack_size ?: 0);
        $sticks = (int) ($item->tobacco_stick_count ?: 0);
        $kind = TobaccoItem::kind($item);

        if ($kind === 'cigarettes') {
            if (in_array($pack, [10, 20, 25, 200], true)) {
                return $pack;
            }
            if ($sticks >= 10) {
                return $sticks;
            }

            return 20;
        }

        if ($sticks > 0) {
            return $sticks;
        }
        if ($pack > 1) {
            return $pack;
        }

        return 1;
    }

    protected function onHandQty(Item $item): int
    {
        return max(0, (int) round((float) ($item->quantity_in_stock ?? 0)));
    }

    /** 10-digit phone only. Short values like "2" are left blank — never 0000000002. */
    protected function phone10(?string ...$raws): string
    {
        foreach ($raws as $raw) {
            $digits = preg_replace('/\D+/', '', (string) $raw) ?: '';
            if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                $digits = substr($digits, 1);
            }
            if (strlen($digits) === 10) {
                return $digits;
            }
        }

        return str_repeat(' ', 10);
    }

    /**
     * 14-digit numeric product code from Primary UPC, else item code, else id.
     * Approved BID/PUR product identity is this UPC — not a sequential 1,2,3 key.
     */
    protected function itemCode14(Item $item): string
    {
        $raw = preg_replace('/\D+/', '', (string) ($item->primary_upc ?: '')) ?: '';
        if ($raw === '') {
            $raw = preg_replace('/\D+/', '', (string) ($item->item_code ?: '')) ?: '';
        }
        if ($raw === '') {
            $raw = (string) $item->id;
        }

        return $this->numDigits($raw, 14);
    }

    /**
     * Customer account key (8 digits) — matches SID/PUR keys in the approved file.
     */
    protected function customerKey(?Customer $customer, int $fallbackSeq): string
    {
        $acctSource = (string) ($customer?->customer_id ?: $customer?->id ?: $fallbackSeq);
        $acctDigits = preg_replace('/\D+/', '', $acctSource) ?: (string) $fallbackSeq;

        return $this->numDigits($acctDigits, 8);
    }

    /**
     * HID contact: last name (20) then first name (20), same as approved HOSSAIN / EMRAN.
     *
     * @return array{last: string, first: string}
     */
    protected function contactNames(Company $company): array
    {
        $raw = strtoupper(trim((string) ($company->contact_name ?: '')));
        if ($raw === '') {
            $raw = strtoupper(trim((string) ($company->name ?: 'OFFICE STAFF')));
            $raw = preg_replace('/\b(INC|LLC|LTD|CO|CORP|COMPANY)\b\.?/', '', $raw) ?? $raw;
            $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? '') ?: 'OFFICE STAFF';
        }

        $parts = preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: ['OFFICE'];
        if (count($parts) === 1) {
            return ['last' => $parts[0], 'first' => 'OFFICE'];
        }
        $last = (string) array_pop($parts);

        return ['last' => $last, 'first' => implode(' ', $parts)];
    }

    protected function isPromoItem(Item $item): bool
    {
        return filled($item->manu_promotion_item)
            || filled($item->manu_promotion_code)
            || filled($item->manu_promotion_description);
    }

    /**
     * HID — 337 chars (name32 + location/address90 + city/state/zip).
     * After version 0002 the approved file always writes 00000004 (not BID count).
     */
    protected function hid(Company $company, string $license, string $periodYmd, string $fileYmd): string
    {
        $loc = $this->companyLocation($company);
        $names = $this->contactNames($company);
        $phone = $this->numDigits(preg_replace('/\D+/', '', $loc['phone']) ?: '0', 10);
        $fax = $this->numDigits(preg_replace('/\D+/', '', $loc['fax']) ?: '0', 10);

        return $this->fields([
            ['HID', 3],
            [$license, 8, '0', STR_PAD_LEFT],
            ['TOB', 4],
            [' ', 1],
            ['W', 1],
            [$periodYmd, 8],
            [$this->upperAscii($loc['name']), self::NAME_LEN],
            [$this->upperAscii($loc['address']), self::ADDR_LEN],
            [$this->upperAscii($loc['city']), 25],
            [$this->upperAscii($loc['state']), 2],
            [$this->zip9($loc['zip']), 9, '0', STR_PAD_RIGHT],
            ['USA', 3],
            [$this->upperAscii($names['last']), 20],
            [$this->upperAscii($names['first']), 20],
            ['00000', 5, '0', STR_PAD_LEFT],
            [$phone, 10, '0', STR_PAD_LEFT],
            ['00000', 5, '0', STR_PAD_LEFT],
            [$fax, 10, '0', STR_PAD_LEFT],
            [strtolower($this->ascii($loc['email'])), 60],
            ['0002', 4],
            [self::HID_FORMAT_ID, 8],
            [$fileYmd, 8],
            ['0', 1],
        ], self::HID_LEN);
    }

    /**
     * BID — 275 chars. UPC and SKU are GTIN-14 (no 10-digit + spaces).
     * Col 132–137 = items per selling unit. Measure 003 = on-hand qty, not price.
     */
    protected function bid(string $code14, Item $item, string $companyState, int $onHand = 0): string
    {
        $desc = $this->upperAscii((string) ($item->description ?: $item->item_code));
        $unitSize = $this->itemsPerSellingUnit($item);
        $promo = $this->isPromoItem($item);
        $state = $this->upperAscii(substr($companyState ?: 'MI', 0, 2));

        return $this->fields([
            ['BID', 3],
            [$code14, 14, '0', STR_PAD_LEFT],
            [$code14, 14, '0', STR_PAD_LEFT],
            [$desc, self::BID_DESC_LEN],
            [$this->num($unitSize, 6), 6, '0', STR_PAD_LEFT],
            [$promo ? 'Y' : 'N', 1],
            ['', 6],
            [$this->nacsCode($item), 6],
            ['', 10],
            ['0', 6, '0', STR_PAD_LEFT],
            [$promo ? str_repeat(' ', 11).'PROMO'.str_repeat(' ', 25) : '', 41],
            [$state, 2],
            ['0', 32, '0', STR_PAD_LEFT],
            ['', 6],
            ['003', 3],
            [$this->num($onHand, 11), 11, '0', STR_PAD_LEFT],
            ['006', 3],
            ['0', 11, '0', STR_PAD_LEFT],
        ], self::BID_LEN);
    }

    /**
     * SID — 551 chars (customer + location bill-to / ship-to).
     */
    protected function sid(string $custKey, ?Customer $customer, Company $company, int $seq): string
    {
        $co = $this->companyLocation($company);
        $ship = $this->customerShipTo($customer);

        $isWalkIn = ! $customer
            || strtoupper((string) ($customer->customer_id ?? '')) === 'WALKIN'
            || stripos((string) ($customer->company_name ?? ''), 'walk') !== false;

        $name = $this->upperAscii((string) (
            $customer?->company_name
            ?: $customer?->contact
            ?: $ship['name']
            ?: 'WALK-IN CUSTOMER'
        ));

        $addr = $this->upperAscii(trim((string) (
            $customer?->address
            ?: $ship['address']
            ?: $customer?->owner_address
            ?: ''
        )));
        $city = $this->upperAscii(trim((string) (
            $customer?->city
            ?: $ship['city']
            ?: $customer?->owner_city
            ?: ''
        )));
        $state = $this->upperAscii(substr(trim((string) (
            $customer?->state
            ?: $ship['state']
            ?: $customer?->owner_state
            ?: ''
        )), 0, 2));
        $zip = $this->zip9((string) (
            $customer?->zip_code
            ?: $ship['zip']
            ?: $customer?->owner_zip
            ?: ''
        ));
        $phone = $this->phone10(
            $customer?->telephone,
            $customer?->mobile,
            $customer?->telephone2,
            $ship['phone'],
            $customer?->owner_telephone
        );

        if ($isWalkIn) {
            $name = $this->upperAscii($name !== '' && $name !== 'WALK-IN CUSTOMER' ? $name : $co['name']);
            $addr = $this->upperAscii($addr !== '' ? $addr : $co['address']);
            $city = $this->upperAscii($city !== '' ? $city : $co['city']);
            $state = $this->upperAscii($state !== '' ? $state : $co['state']);
            $zip = $zip !== $this->zip9('') ? $zip : $this->zip9($co['zip']);
            $phone = $phone !== str_repeat(' ', 10)
                ? $phone
                : $this->phone10($co['phone']);
            $name2 = $this->upperAscii($co['name']);
            $addr2 = $this->upperAscii($co['address']);
            $city2 = $this->upperAscii($co['city']);
            $state2 = $this->upperAscii($co['state']);
            $zip2 = $this->zip9($co['zip']);
            $phone2 = $this->phone10($co['phone']);
            $accountYn = 'YN';
            $shipFlag = ' N';
        } else {
            $name2 = $name;
            $addr2 = $addr;
            $city2 = $city;
            $state2 = $state;
            $zip2 = $zip;
            $phone2 = $phone;
            $accountYn = 'NN';
            $shipFlag = ' Y';
        }

        $typeLabel = 'RETAIL';
        $acctType = $this->upperAscii((string) ($customer?->account_type ?? ''));
        if (str_contains($acctType, 'DISTRIB') || str_contains($acctType, 'WHOLE') || str_contains($name, 'DISTRIBUTOR')) {
            $typeLabel = 'DISTRIBUTOR';
        }
        if ($isWalkIn) {
            $typeLabel = 'RETAIL';
        }

        return $this->fields([
            ['SID', 3],
            [$custKey, 8, '0', STR_PAD_LEFT],
            [$custKey, 8, '0', STR_PAD_LEFT],
            [$custKey, 8, '0', STR_PAD_LEFT],
            [$name, self::NAME_LEN],
            [$custKey, 8, '0', STR_PAD_LEFT],
            [$addr, self::ADDR_LEN],
            [$city, 25],
            [$state, 2],
            [$zip, 9, '0', STR_PAD_RIGHT],
            ['USA', 3],
            [$state, 2],
            ['', 3],
            [$phone, 10],
            [$typeLabel, 20],
            ['0', 7, '0', STR_PAD_LEFT],
            [$accountYn, 2],
            ['', 66],
            [$name2, self::NAME_LEN],
            [$addr2, self::ADDR_LEN],
            [$city2, 25],
            [$state2, 2],
            [$zip2, 9, '0', STR_PAD_RIGHT],
            ['USA', 3],
            ['', 5],
            [$phone2, 10],
            ['0', 14, '0', STR_PAD_LEFT],
            [$shipFlag, 2],
            ['', 41],
            ['0', 11, '0', STR_PAD_LEFT],
        ], self::SID_LEN);
    }

    /**
     * @return array{name: string, address: string, city: string, state: string, zip: string, phone: string}
     */
    protected function customerShipTo(?Customer $customer): array
    {
        $empty = ['name' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => '', 'phone' => ''];
        if (! $customer) {
            return $empty;
        }

        $rows = $customer->relationLoaded('shippingAddresses')
            ? $customer->shippingAddresses
            : collect();
        $row = $rows->firstWhere('is_primary', true) ?: $rows->first();
        if (! $row) {
            return $empty;
        }

        return [
            'name' => trim((string) ($row->name ?? '')),
            'address' => trim((string) ($row->address ?? '')),
            'city' => trim((string) ($row->city ?? '')),
            'state' => trim((string) ($row->state ?? '')),
            'zip' => trim((string) ($row->zip ?? '')),
            'phone' => trim((string) ($row->telephone ?? '')),
        ];
    }

    /**
     * @return array{name: string, address: string, city: string, state: string, zip: string, contact: string, email: string, phone: string, fax: string, fein: string}
     */
    protected function companyLocation(Company $company): array
    {
        $address = trim((string) ($company->address ?: ''));
        if ($address === '') {
            $address = (string) config('company.address', '');
        }

        $city = trim((string) ($company->city ?: ''));
        $state = strtoupper(substr(trim((string) ($company->state ?: '')), 0, 2));
        $zip = trim((string) ($company->zip_code ?: ''));

        if ($city === '' && $state === '' && $zip === '') {
            $cityLine = (string) config('company.city_line', 'ANN ARBOR, MI 48108');
            if (preg_match('/^(.+?),\s*([A-Z]{2})\s*(\d{5})/i', $cityLine, $m)) {
                $city = trim($m[1]);
                $state = strtoupper($m[2]);
                $zip = $m[3];
            }
        }

        if ($state === '') {
            $state = 'MI';
        }
        if ($zip === '') {
            $zip = '00000';
        }

        return [
            'name' => trim((string) ($company->name ?: $company->code ?: 'COMPANY')),
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'contact' => trim((string) ($company->contact_name ?: 'OFFICE')),
            'email' => trim((string) ($company->email ?: '')),
            'phone' => trim((string) ($company->phone ?: '')),
            'fax' => trim((string) ($company->fax ?: '')),
            'fein' => (string) ($company->fein_no ?: '0'),
        ];
    }

    /**
     * PUR — 158 chars. 001 qty, 002 dollars, 004 saleable returns, 005 filler.
     */
    protected function pur(
        string $custKey,
        string $productKey,
        string $saleDateYmd,
        int $qty,
        int $cents
    ): string {
        return $this->fields([
            ['PUR', 3],
            [$custKey, 8, '0', STR_PAD_LEFT],
            [$custKey, 8, '0', STR_PAD_LEFT],
            [$custKey, 8, '0', STR_PAD_LEFT],
            [$productKey, 14, '0', STR_PAD_LEFT],
            ['', 33],
            [$saleDateYmd, 8],
            ['', 20],
            ['001', 3],
            [$this->num($qty, 11), 11, '0', STR_PAD_LEFT],
            ['002', 3],
            [$this->num($cents, 11), 11, '0', STR_PAD_LEFT],
            ['004', 3],
            ['0', 11, '0', STR_PAD_LEFT],
            ['005', 3],
            ['0', 11, '0', STR_PAD_LEFT],
        ], self::PUR_LEN);
    }

    /**
     * TOT — 194 chars. 001 qty, 002 dollars, 003 on-hand, then 006/004/005.
     */
    protected function tot(
        string $license,
        string $periodYmd,
        int $bidCount,
        int $sidCount,
        int $purCount,
        int $totalQty,
        int $totalAmountCents,
        int $totalOnHand
    ): string {
        return $this->fields([
            ['TOT', 3],
            [$license, 8, '0', STR_PAD_LEFT],
            [$periodYmd, 8],
            [$this->num($bidCount, 9), 9, '0', STR_PAD_LEFT],
            [$this->num($sidCount, 9), 9, '0', STR_PAD_LEFT],
            [$this->num($purCount, 9), 9, '0', STR_PAD_LEFT],
            ['', 40],
            ['001', 3],
            [$this->num($totalQty, 15), 15, '0', STR_PAD_LEFT],
            ['002', 3],
            [$this->num($totalAmountCents, 15), 15, '0', STR_PAD_LEFT],
            ['003', 3],
            [$this->num($totalOnHand, 15), 15, '0', STR_PAD_LEFT],
            ['006', 3],
            ['0', 15, '0', STR_PAD_LEFT],
            ['004', 3],
            ['0', 15, '0', STR_PAD_LEFT],
            ['005', 3],
            ['0', 15, '0', STR_PAD_LEFT],
        ], self::TOT_LEN);
    }

    protected function nacsCode(Item $item): string
    {
        $type = TobaccoItem::kind($item);

        return match (true) {
            in_array($type, ['cigarettes', 'cigarette', 'cig'], true) => '003231',
            in_array($type, ['ryo'], true) => '003221',
            in_array($type, ['pipe'], true) => '003241',
            in_array($type, ['cigarillo'], true) => '003251',
            in_array($type, ['little_cigar', 'filtered_cigar'], true) => '003252',
            in_array($type, ['vape', 'ecig'], true) => '003292',
            in_array($type, ['pc1', 'premium_cigar'], true) => '003261',
            default => '003211',
        };
    }

    protected function zip9(string $zip): string
    {
        $digits = preg_replace('/\D+/', '', $zip) ?: '00000';
        if (strlen($digits) < 9) {
            $digits = str_pad(substr($digits, 0, 5), 9, '0', STR_PAD_RIGHT);
        }

        return $this->pad(substr($digits, 0, 9), 9, '0', STR_PAD_RIGHT);
    }

    /**
     * Concatenate exact-width ASCII fields so every column starts on the same
     * character for every record of that type (Nashville BID desc @ 31, NACS @ 144, …).
     *
     * @param  list<array{0:string,1:int,2?:string,3?:int}>  $parts
     */
    protected function fields(array $parts, int $len): string
    {
        $out = '';
        foreach ($parts as $part) {
            $out .= $this->pad(
                (string) ($part[0] ?? ''),
                (int) $part[1],
                (string) ($part[2] ?? ' '),
                (int) ($part[3] ?? STR_PAD_RIGHT)
            );
        }

        return $this->fixed($out, $len);
    }

    protected function upperAscii(string $value): string
    {
        return strtoupper($this->ascii($value));
    }

    /** Single-byte printable ASCII only — 1 char = 1 column. */
    protected function ascii(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted === false) {
            $converted = @iconv('UTF-8', 'ASCII//IGNORE', $value);
        }
        if (! is_string($converted)) {
            $converted = $value;
        }

        return preg_replace('/[^\x20-\x7E]/', ' ', $converted) ?? '';
    }

    protected function pad(string $value, int $len, string $pad = ' ', int $style = STR_PAD_RIGHT): string
    {
        if ($len <= 0) {
            return '';
        }

        $padChar = $pad === '' ? ' ' : substr($pad, 0, 1);
        $value = $this->ascii($value);
        if (strlen($value) > $len) {
            $value = substr($value, 0, $len);
        }

        return str_pad($value, $len, $padChar, $style);
    }

    protected function num(int $value, int $len): string
    {
        $value = max(0, $value);
        $s = (string) $value;
        if (strlen($s) > $len) {
            $s = substr($s, -$len);
        }

        return str_pad($s, $len, '0', STR_PAD_LEFT);
    }

    protected function numDigits(string $digits, int $len): string
    {
        $digits = preg_replace('/\D+/', '', $digits) ?: '0';
        if (strlen($digits) > $len) {
            $digits = substr($digits, -$len);
        }

        return str_pad($digits, $len, '0', STR_PAD_LEFT);
    }

    protected function fixed(string $value, int $len): string
    {
        if (strlen($value) > $len) {
            return substr($value, 0, $len);
        }

        return str_pad($value, $len, ' ', STR_PAD_RIGHT);
    }
}
