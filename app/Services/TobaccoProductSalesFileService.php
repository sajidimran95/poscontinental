<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Support\StickCount;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * JAPS POS fixed-width tobacco product MSA sales file.
 * Sample: JAPS_POS_TOB_00001111_20260808.txt
 *
 * Field map (verified against sample):
 * HID 337: lic8 · TOB TW · period · name32 · address90 · city25 · st2 · zip9 · USA · CONTACT20 · contact20 · fein30 · email60 · ver4 · bidCnt8 · fileYmd8 · flag1
 * BID 275: type2 · seq5 · product8 · code14 · desc100 · size6 · N · sp6 · nacs6 · sp10 · z6 · sp41 · st2 · z32 · sp6 · 003 · price11 · 006 · z11
 * SID 551: keys3×8 · name32 · acct8 · addr90 · city25 · st2 · zip9 · USA · st2 · sp3 · phone10 · type20 · z7 · YN · pad66 · name2 32 · addr2 90 · city2 25 · st · zip · USA · sp5 · phone2 · z14 · flag2 · pad · z11
 * PUR 158: keys · product14 · sp33 · date8 · sp20 · 001 · qty11 · pack4 · sticks10 · 004 · cents14 · z11
 * TOT 194: lic · period · bid9 · sid9 · pur9 · sp40 · 001 · sticks15 · sp18 · 003 · cents15 · 006 · z…
 */
class TobaccoProductSalesFileService
{
    public const HID_LEN = 337;

    public const BID_LEN = 275;

    public const SID_LEN = 551;

    public const PUR_LEN = 158;

    public const TOT_LEN = 194;

    /** Company / customer name width (sample uses 32, not 30). */
    public const NAME_LEN = 32;

    /** Street / location address width (sample uses 90, not 85). */
    public const ADDR_LEN = 90;

    public function build(Company $company, string $periodStart, string $periodEnd, Collection $invoices): string
    {
        $periodYmd = $this->ymd($periodEnd) ?: $this->ymd($periodStart) ?: now()->format('Ymd');
        $fileYmd = now()->format('Ymd');
        $license = $this->licenseDigits($company);
        $items = $this->collectTobaccoItems($invoices);
        $customers = $this->collectCustomerSales($invoices);

        $lines = [];
        $lines[] = $this->hid($company, $license, $periodYmd, $fileYmd, $items->count());

        $itemSeq = 0;
        $itemIndex = [];
        $companyState = $this->companyLocation($company)['state'];
        foreach ($items as $itemId => $meta) {
            $itemSeq++;
            $productKey = $this->num($itemSeq, 8);
            $itemIndex[(int) $itemId] = $productKey;
            $lines[] = $this->bid($itemSeq, $productKey, $meta['item'], $companyState);
        }

        $purCount = 0;
        $totalSticks = 0;
        $totalAmountCents = 0;
        $sidSeq = 0;

        foreach ($customers as $bucket) {
            $sidSeq++;
            $custKey = $this->num($sidSeq, 8);
            $lines[] = $this->sid($custKey, $bucket['customer'], $company, $sidSeq);

            foreach ($bucket['lines'] as $sale) {
                /** @var Item $item */
                $item = $sale['item'];
                $productKey = $itemIndex[(int) $item->id] ?? $this->num((int) $item->id, 8);
                $qty = (float) $sale['qty'];
                $amount = (float) $sale['amount'];
                $packSize = max(1, (int) ($item->cigarette_pack_size ?: 20));
                $sticks = StickCount::forLine($item, $qty);
                if ($sticks <= 0) {
                    $sticks = (int) round($qty * $packSize);
                }
                $lines[] = $this->pur($custKey, $productKey, $sale['date'], $qty, $packSize, $sticks, $amount);
                $purCount++;
                $totalSticks += $sticks;
                $totalAmountCents += (int) round($amount * 100);
            }
        }

        $lines[] = $this->tot(
            $license,
            $periodYmd,
            $items->count(),
            $sidSeq,
            $purCount,
            $totalSticks,
            $totalAmountCents
        );

        return implode("\r\n", $lines).($lines === [] ? '' : "\r\n");
    }

    public function filename(Company $company, string $periodEnd): string
    {
        $name = (string) ($company->name ?: $company->code ?: 'COMPANY');
        $slug = Str::upper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($name)) ?: 'COMPANY');
        $slug = trim($slug, '_');
        $license = $this->licenseDigits($company);
        $date = $this->ymd($periodEnd) ?: now()->format('Ymd');

        return $slug.'_TOB_'.$license.'_'.$date.'.txt';
    }

    protected function ymd(string $date): string
    {
        $digits = preg_replace('/\D+/', '', $date) ?? '';
        if (strlen($digits) >= 8) {
            return substr($digits, 0, 8);
        }

        return '';
    }

    protected function licenseDigits(Company $company): string
    {
        $raw = $company->secondary_tob_number
            ?: $company->secondary_cig_number
            ?: $company->state_license_number
            ?: '0';
        $digits = preg_replace('/\D+/', '', (string) $raw) ?: '0';

        return $this->num((int) substr($digits, 0, 8), 8);
    }

    /**
     * @return Collection<int, array{item: Item, qty: float, amount: float}>
     */
    protected function collectTobaccoItems(Collection $invoices): Collection
    {
        $map = $this->collectItemsFromInvoices($invoices, tobaccoOnly: true);

        // Catalog may not have tobacco flags set — still export sold products so the file is not empty zeros.
        if ($map->isEmpty()) {
            $map = $this->collectItemsFromInvoices($invoices, tobaccoOnly: false);
        }

        return $map;
    }

    /**
     * @return Collection<int, array{item: Item, qty: float, amount: float}>
     */
    protected function collectItemsFromInvoices(Collection $invoices, bool $tobaccoOnly): Collection
    {
        $map = [];

        foreach ($invoices as $invoice) {
            foreach ($this->invoiceLines($invoice) as $line) {
                $item = $line->item ?? null;
                if (! $item) {
                    continue;
                }
                if ($tobaccoOnly && ! $this->isTobaccoItem($item)) {
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

        return collect($map);
    }

    /**
     * @return array<int, array{customer: ?Customer, lines: list<array{item: Item, qty: float, amount: float, date: string}>}>
     */
    protected function collectCustomerSales(Collection $invoices): array
    {
        $out = $this->collectCustomerSalesFromInvoices($invoices, tobaccoOnly: true);

        if ($out === []) {
            $out = $this->collectCustomerSalesFromInvoices($invoices, tobaccoOnly: false);
        }

        return $out;
    }

    /**
     * @return array<int, array{customer: ?Customer, lines: list<array{item: Item, qty: float, amount: float, date: string}>}>
     */
    protected function collectCustomerSalesFromInvoices(Collection $invoices, bool $tobaccoOnly): array
    {
        $out = [];

        foreach ($invoices as $invoice) {
            $customer = $invoice->customer;
            $cid = (int) ($customer?->id ?: 0);
            if (! isset($out[$cid])) {
                $out[$cid] = ['customer' => $customer, 'lines' => []];
            }

            foreach ($this->invoiceLines($invoice) as $line) {
                $item = $line->item ?? null;
                if (! $item) {
                    continue;
                }
                if ($tobaccoOnly && ! $this->isTobaccoItem($item)) {
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
            $order->load(['lines.item']);
        }

        return collect($order->lines ?? []);
    }

    protected function isTobaccoItem(?Item $item): bool
    {
        if (! $item) {
            return false;
        }

        $type = strtolower(trim((string) ($item->tobacco_product_type ?? '')));
        if ($type !== '') {
            return true;
        }

        return filled($item->tobacco_brand_code)
            || (int) ($item->tobacco_stick_count ?? 0) > 0
            || (float) ($item->tobacco_total_oz ?? 0) > 0;
    }

    /**
     * HID — 337 chars (name32 + location/address90 + city/state/zip).
     */
    protected function hid(Company $company, string $license, string $periodYmd, string $fileYmd, int $bidCount): string
    {
        $loc = $this->companyLocation($company);

        return $this->fixed(
            'HID'
            .$license
            .'TOB TW'
            .$periodYmd
            .$this->pad(strtoupper($loc['name']), self::NAME_LEN)
            .$this->pad(strtoupper($loc['address']), self::ADDR_LEN)
            .$this->pad(strtoupper($loc['city']), 25)
            .$this->pad($loc['state'], 2)
            .$this->zip9($loc['zip'])
            .'USA'
            .$this->pad('CONTACT', 20)
            .$this->pad(strtoupper($loc['contact']), 20)
            .$this->numDigits($loc['fein'], 30)
            .$this->pad(strtolower($loc['email']), 60)
            .'0002'
            .$this->num($bidCount, 8)
            .$fileYmd
            .'0',
            self::HID_LEN
        );
    }

    /**
     * BID — 275 chars (product catalog line).
     */
    protected function bid(int $seq, string $productKey, Item $item, string $companyState): string
    {
        $desc = $this->pad(mb_strtoupper((string) ($item->description ?: $item->item_code)), 100);
        $codeDigits = preg_replace('/\D+/', '', (string) $item->item_code) ?: (string) $item->id;
        $itemCode14 = $this->numDigits($codeDigits, 14);
        $unitSize = $this->num((int) ($item->cigarette_pack_size ?: 200), 6);
        $nacs = $this->nacsCode($item);
        $priceCents = $this->num((int) round((float) $item->list_price * 100), 11);
        $state = $this->pad(strtoupper(substr($companyState ?: 'MI', 0, 2)), 2);

        return $this->fixed(
            'BID'
            .'2'
            .$this->num($seq, 5)
            .$productKey
            .$itemCode14
            .$desc
            .$unitSize
            .'N'
            .$this->pad('', 6)
            .$nacs
            .$this->pad('', 10)
            .$this->num(0, 6)
            .$this->pad('', 41)
            .$state
            .$this->num(0, 32)
            .$this->pad('', 6)
            .'003'
            .$priceCents
            .'006'
            .$this->num(0, 11),
            self::BID_LEN
        );
    }

    /**
     * SID — 551 chars (customer + location bill-to / ship-to).
     */
    protected function sid(string $custKey, ?Customer $customer, Company $company, int $seq): string
    {
        $co = $this->companyLocation($company);

        $isWalkIn = ! $customer
            || strtoupper((string) ($customer->customer_id ?? '')) === 'WALKIN'
            || stripos((string) ($customer->company_name ?? ''), 'walk') !== false;

        $name = mb_strtoupper((string) (
            $customer?->company_name
            ?: $customer?->contact
            ?: 'WALK-IN CUSTOMER'
        ));
        $acctSource = (string) ($customer?->customer_id ?: $customer?->id ?: $seq);
        $acctDigits = preg_replace('/\D+/', '', $acctSource) ?: (string) $seq;
        $acct = $this->numDigits($acctDigits, 8);

        $addr = mb_strtoupper(trim((string) ($customer?->address ?: $co['address'])));
        $city = mb_strtoupper(trim((string) ($customer?->city ?: $co['city'])));
        $state = strtoupper(substr(trim((string) ($customer?->state ?: $co['state'])), 0, 2));
        $zip = $this->zip9((string) ($customer?->zip_code ?: $co['zip']));
        $phoneRaw = (string) ($customer?->telephone ?: $customer?->mobile ?: $customer?->telephone2 ?: $co['phone']);
        $phone = $this->numDigits(preg_replace('/\D+/', '', $phoneRaw) ?: '0', 10);

        if ($isWalkIn) {
            $name2 = mb_strtoupper($co['name']);
            $addr2 = mb_strtoupper($co['address']);
            $city2 = mb_strtoupper($co['city']);
            $state2 = $co['state'];
            $zip2 = $this->zip9($co['zip']);
            $phone2 = $this->numDigits(preg_replace('/\D+/', '', $co['phone']) ?: '0', 10);
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
        $acctType = strtoupper((string) ($customer?->account_type ?? ''));
        if (str_contains($acctType, 'DISTRIB') || str_contains($acctType, 'WHOLE') || str_contains(strtoupper($name), 'DISTRIBUTOR')) {
            $typeLabel = 'DISTRIBUTOR';
        }
        if ($isWalkIn) {
            $typeLabel = 'RETAIL';
        }

        return $this->fixed(
            'SID'
            .$custKey
            .$custKey
            .$custKey
            .$this->pad($name, self::NAME_LEN)
            .$acct
            .$this->pad($addr, self::ADDR_LEN)
            .$this->pad($city, 25)
            .$this->pad($state, 2)
            .$zip
            .'USA'
            .$this->pad($state, 2)
            .$this->pad('', 3)
            .$phone
            .$this->pad($typeLabel, 20)
            .$this->num(0, 7)
            .$accountYn
            .$this->pad('', 66)
            .$this->pad($name2, self::NAME_LEN)
            .$this->pad($addr2, self::ADDR_LEN)
            .$this->pad($city2, 25)
            .$this->pad($state2, 2)
            .$zip2
            .'USA'
            .$this->pad('', 5)
            .$phone2
            .$this->num(0, 14)
            .$shipFlag
            .$this->pad('', 41)
            .$this->num(0, 11),
            self::SID_LEN
        );
    }

    /**
     * @return array{name: string, address: string, city: string, state: string, zip: string, contact: string, email: string, phone: string, fein: string}
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
            'fein' => (string) ($company->fein_no ?: '0'),
        ];
    }

    /**
     * PUR — 158 chars (sale line under customer).
     */
    protected function pur(
        string $custKey,
        string $productKey,
        string $saleDateYmd,
        float $qty,
        int $packSize,
        int $sticks,
        float $amount
    ): string {
        $qtyInt = max(0, (int) round($qty));
        $cents = max(0, (int) round($amount * 100));

        return $this->fixed(
            'PUR'
            .$custKey
            .$custKey
            .$custKey
            .$this->pad($productKey, 14, '0', STR_PAD_LEFT)
            .$this->pad('', 33)
            .$this->pad($saleDateYmd, 8)
            .$this->pad('', 20)
            .'001'
            .$this->num($qtyInt, 11)
            .$this->num($packSize, 4)
            .$this->num($sticks, 10)
            .'004'
            .$this->num($cents, 14)
            .$this->num(0, 11),
            self::PUR_LEN
        );
    }

    /**
     * TOT — 194 chars.
     */
    protected function tot(
        string $license,
        string $periodYmd,
        int $bidCount,
        int $sidCount,
        int $purCount,
        int $totalSticks,
        int $totalAmountCents
    ): string {
        return $this->fixed(
            'TOT'
            .$license
            .$periodYmd
            .$this->num($bidCount, 9)
            .$this->num($sidCount, 9)
            .$this->num($purCount, 9)
            .$this->pad('', 40)
            .'001'
            .$this->num($totalSticks, 15)
            .$this->pad('', 18)
            .'003'
            .$this->num($totalAmountCents, 15)
            .'006'
            .$this->num(0, 15)
            .$this->num(0, 15)
            .$this->num(0, 15),
            self::TOT_LEN
        );
    }

    protected function nacsCode(Item $item): string
    {
        $type = strtolower(trim((string) ($item->tobacco_product_type ?? '')));

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

    protected function pad(string $value, int $len, string $pad = ' ', int $style = STR_PAD_RIGHT): string
    {
        $value = preg_replace("/[\r\n\t]+/", ' ', $value) ?? $value;
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = substr($value, 0, $len);

        return str_pad($value, $len, $pad, $style);
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
