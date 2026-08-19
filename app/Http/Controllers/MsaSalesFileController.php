<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\TobaccoProductSalesFileService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MsaSalesFileController extends Controller
{
    public function __invoke(Request $request, TobaccoProductSalesFileService $files): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $company = $user->company;
        abort_unless($company, 422, 'Company settings are required before downloading the MSA report.');

        $start = (string) $request->query('from', now()->startOfMonth()->toDateString());
        $end = (string) $request->query('to', now()->toDateString());

        $product = (string) $request->query('product', 'all');
        if (! in_array($product, ['all', 'otp', 'cigarettes'], true)) {
            $product = 'all';
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start = now()->startOfMonth()->toDateString();
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end = now()->toDateString();
        }

        [$start, $end] = $files->msaSundayToSaturday($start, $end);

        $invoices = Invoice::query()
            ->with([
                'customer.shippingAddresses',
                'salesOrder.customer.shippingAddresses',
                'salesOrder.lines.item.category',
                'salesOrder.lines.item.subcategory',
            ])
            ->where('company_id', (int) $user->company_id)
            ->whereDate('invoice_date', '>=', $start)
            ->whereDate('invoice_date', '<=', $end)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $payload = $files->build($company, $start, $end, $invoices, $product);
        $filename = $files->filename($company, $end, $product);

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store',
        ]);
    }
}
