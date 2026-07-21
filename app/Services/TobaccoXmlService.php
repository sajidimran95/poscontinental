<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\TobaccoStampInventory;
use Illuminate\Support\Collection;
use SimpleXMLElement;

class TobaccoXmlService
{
    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Invoice>  $invoices
     */
    public function build(
        ?Company $company,
        string $filerType,
        string $product,
        string $periodStart,
        string $periodEnd,
        Collection $suppliers,
        Collection $customers,
        Collection $invoices,
        ?TobaccoStampInventory $stamps = null,
    ): string {
        // Build XML declaration without a literal close-php sequence in this file.
        $declaration = chr(60).'?xml version="1.0" encoding="UTF-8"'.'?'.chr(62);
        $root = $declaration.'<MichiganTobaccoReturn xmlns="http://www.michigan.gov/treasury/tobacco"/>';

        $xml = new SimpleXMLElement($root);
        $xml->addAttribute('filerType', $filerType);
        $xml->addAttribute('product', $product);
        $xml->addAttribute('schemaVersion', '1.1');
        $xml->addChild('CompanyName', htmlspecialchars((string) ($company?->name ?? ''), ENT_XML1));
        $xml->addChild('CompanyFEIN', '');
        $xml->addChild('PeriodStart', $periodStart);
        $xml->addChild('PeriodEnd', $periodEnd);
        $xml->addChild('GeneratedAt', now()->toIso8601String());

        $sellers = $xml->addChild('PurchaserSellers');
        foreach ($suppliers as $s) {
            $node = $sellers->addChild('Seller');
            $node->addChild('Name', htmlspecialchars((string) $s->name, ENT_XML1));
            $node->addChild('PurchaserSellerFEIN', htmlspecialchars((string) $s->fein_no, ENT_XML1));
            $node->addChild('Address', htmlspecialchars((string) ($s->address ?? ''), ENT_XML1));
            $node->addChild('City', htmlspecialchars((string) ($s->city ?? ''), ENT_XML1));
            $node->addChild('State', htmlspecialchars((string) ($s->state ?? ''), ENT_XML1));
            $node->addChild('ZIP', htmlspecialchars((string) ($s->zip_code ?? ''), ENT_XML1));
        }
        foreach ($customers as $c) {
            $node = $sellers->addChild('Buyer');
            $node->addChild('Name', htmlspecialchars((string) ($c->company_name ?: $c->contact), ENT_XML1));
            $node->addChild('PurchaserSellerFEIN', htmlspecialchars((string) $c->fein_no, ENT_XML1));
            $node->addChild('Address', htmlspecialchars((string) ($c->address ?? ''), ENT_XML1));
            $node->addChild('City', htmlspecialchars((string) ($c->city ?? ''), ENT_XML1));
            $node->addChild('State', htmlspecialchars((string) ($c->state ?? ''), ENT_XML1));
            $node->addChild('ZIP', htmlspecialchars((string) ($c->zip_code ?? ''), ENT_XML1));
        }

        $sales = $xml->addChild('Sales');
        foreach ($invoices as $inv) {
            $row = $sales->addChild('Sale');
            $row->addChild('InvoiceNo', htmlspecialchars((string) $inv->invoice_number, ENT_XML1));
            $row->addChild('InvoiceDate', optional($inv->invoice_date)?->format('Y-m-d') ?? '');
            $row->addChild('CustomerFEIN', htmlspecialchars((string) ($inv->customer?->fein_no ?? ''), ENT_XML1));
            $row->addChild('CustomerName', htmlspecialchars((string) (($inv->customer?->company_name ?: $inv->customer?->contact) ?? ''), ENT_XML1));
            $row->addChild('OrderNo', htmlspecialchars((string) ($inv->salesOrder?->order_number ?? ''), ENT_XML1));
            $row->addChild('WholesaleTotal', number_format((float) $inv->invoice_total, 2, '.', ''));
            $row->addChild('TaxAmount', number_format((float) ($inv->tax ?? 0), 2, '.', ''));
        }

        if ($filerType === 'unclassified_acquirer' && $product === 'cigarettes' && $stamps) {
            $stamp = $xml->addChild('StampInventory');
            $stamp->addChild('R1_BeginningUnaffixed', (string) $stamps->r1_beginning_unaffixed);
            $stamp->addChild('R2_BeginningAffixed', (string) $stamps->r2_beginning_affixed);
            $stamp->addChild('R3_Purchased', (string) $stamps->r3_purchased);
            $stamp->addChild('R4_Affixed', (string) $stamps->r4_affixed);
            $stamp->addChild('R5_EndingUnaffixed', (string) $stamps->r5_ending_unaffixed);
            $stamp->addChild('R6_EndingAffixed', (string) $stamps->r6_ending_affixed);
            $stamp->addChild('PeriodStart', optional($stamps->period_start)?->format('Y-m-d') ?? '');
            $stamp->addChild('PeriodEnd', optional($stamps->period_end)?->format('Y-m-d') ?? '');
        }

        if ($product === 'otp') {
            $otp = $xml->addChild('OtherTobaccoProducts');
            $otp->addChild('Note', 'OTP sales summarized from invoice totals in period; line-level OTP SKUs attach via item cigarette_tax_class when configured.');
            $otp->addChild('InvoiceCount', (string) $invoices->count());
            $otp->addChild('WholesaleTotal', number_format((float) $invoices->sum('invoice_total'), 2, '.', ''));
        }

        return $xml->asXML() ?: '';
    }
}
