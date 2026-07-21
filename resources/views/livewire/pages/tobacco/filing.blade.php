<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\TobaccoStampInventory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app'), Title('Tobacco XML Filing')] class extends Component
{
    public string $filer_type = 'secondary_wholesaler';

    public string $product = 'cigarettes';

    public string $period_start = '';

    public string $period_end = '';

    public function mount(): void
    {
        $this->period_start = now()->startOfMonth()->toDateString();
        $this->period_end = now()->endOfMonth()->toDateString();
    }

    public function downloadXml(): StreamedResponse
    {
        $companyId = auth()->user()->company_id;
        $company = auth()->user()->company;

        $suppliers = Supplier::query()->where('company_id', $companyId)->whereNotNull('fein_no')->get();
        $customers = Customer::query()->where('company_id', $companyId)->whereNotNull('fein_no')->get();
        $invoices = Invoice::query()
            ->with('customer', 'salesOrder')
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$this->period_start, $this->period_end])
            ->get();
        $stamps = TobaccoStampInventory::query()
            ->where('company_id', $companyId)
            ->whereDate('period_start', '>=', $this->period_start)
            ->whereDate('period_end', '<=', $this->period_end)
            ->latest('id')
            ->first();

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><MichiganTobaccoReturn/>');
        $xml->addAttribute('filerType', $this->filer_type);
        $xml->addAttribute('product', $this->product);
        $xml->addChild('CompanyName', htmlspecialchars((string) ($company?->name ?? '')));
        $xml->addChild('PeriodStart', $this->period_start);
        $xml->addChild('PeriodEnd', $this->period_end);

        $sellers = $xml->addChild('PurchaserSellers');
        foreach ($suppliers as $s) {
            $node = $sellers->addChild('Seller');
            $node->addChild('Name', htmlspecialchars((string) $s->name));
            $node->addChild('PurchaserSellerFEIN', htmlspecialchars((string) $s->fein_no));
        }
        foreach ($customers as $c) {
            $node = $sellers->addChild('Buyer');
            $node->addChild('Name', htmlspecialchars((string) ($c->company_name ?: $c->contact)));
            $node->addChild('PurchaserSellerFEIN', htmlspecialchars((string) $c->fein_no));
        }

        $sales = $xml->addChild('Sales');
        foreach ($invoices as $inv) {
            $row = $sales->addChild('Sale');
            $row->addChild('InvoiceNo', htmlspecialchars((string) $inv->invoice_number));
            $row->addChild('InvoiceDate', optional($inv->invoice_date)?->format('Y-m-d') ?? '');
            $row->addChild('CustomerFEIN', htmlspecialchars((string) ($inv->customer?->fein_no ?? '')));
            $row->addChild('WholesaleTotal', number_format((float) $inv->invoice_total, 2, '.', ''));
        }

        if ($this->filer_type === 'unclassified_acquirer' && $this->product === 'cigarettes' && $stamps) {
            $stamp = $xml->addChild('StampInventory');
            $stamp->addChild('R1_BeginningUnaffixed', (string) $stamps->r1_beginning_unaffixed);
            $stamp->addChild('R2_BeginningAffixed', (string) $stamps->r2_beginning_affixed);
            $stamp->addChild('R3_Purchased', (string) $stamps->r3_purchased);
            $stamp->addChild('R4_Affixed', (string) $stamps->r4_affixed);
            $stamp->addChild('R5_EndingUnaffixed', (string) $stamps->r5_ending_unaffixed);
            $stamp->addChild('R6_EndingAffixed', (string) $stamps->r6_ending_affixed);
        }

        $payload = $xml->asXML();
        $filename = 'tobacco-'.$this->filer_type.'-'.$this->product.'-'.$this->period_start.'.xml';

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, ['Content-Type' => 'application/xml']);
    }
}; ?>

<div class="chief-panel bg-white p-4 space-y-3">
    <x-action-bar title="Michigan Treasury Tobacco XML Filing" />
    <p class="text-sm text-slate-600">Generates Secondary Wholesaler / Unclassified Acquirer XML for Cigarettes or OTP from supplier/customer FEIN and invoice sales in the selected period.</p>
    <div class="flex flex-wrap gap-3 items-end">
        <div class="chief-field">
            <label>Filer Type</label>
            <select wire:model="filer_type" class="chief-input">
                <option value="secondary_wholesaler">Secondary Wholesaler</option>
                <option value="unclassified_acquirer">Unclassified Acquirer</option>
            </select>
        </div>
        <div class="chief-field">
            <label>Product</label>
            <select wire:model="product" class="chief-input">
                <option value="cigarettes">Cigarettes</option>
                <option value="otp">OTP</option>
            </select>
        </div>
        <div class="chief-field"><label>From</label><input type="date" wire:model="period_start" class="chief-input" /></div>
        <div class="chief-field"><label>To</label><input type="date" wire:model="period_end" class="chief-input" /></div>
        <button type="button" wire:click="downloadXml" class="chief-btn-primary">Download XML</button>
        <a href="{{ route('tobacco.stamp-inventory') }}" wire:navigate class="chief-btn">Stamp Inventory (UA)</a>
    </div>
</div>
