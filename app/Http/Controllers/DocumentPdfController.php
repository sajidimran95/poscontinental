<?php

namespace App\Http\Controllers;

use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\ReturnToVendor;
use App\Models\SalesOrder;
use App\Services\DocumentPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentPdfController extends Controller
{
    public function invoice(Invoice $invoice, DocumentPdfService $pdfs): Response
    {
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

        return $pdfs->invoicePdf($invoice, auth()->user())->stream('invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function salesOrder(SalesOrder $salesOrder, DocumentPdfService $pdfs): Response
    {
        abort_unless($salesOrder->company_id === auth()->user()->company_id, 403);

        return $pdfs->streamSalesOrderInvoiceStyle($salesOrder, auth()->user());
    }

    public function purchaseOrder(PurchaseOrder $purchaseOrder, DocumentPdfService $pdfs): Response
    {
        abort_unless($purchaseOrder->company_id === auth()->user()->company_id, 403);

        return $pdfs->streamPurchaseOrder($purchaseOrder, auth()->user());
    }

    public function receiving(InventoryReceiving $receiving, DocumentPdfService $pdfs): Response
    {
        abort_unless($receiving->company_id === auth()->user()->company_id, 403);

        return $pdfs->streamReceiving($receiving, auth()->user());
    }

    public function rtv(ReturnToVendor $rtv, DocumentPdfService $pdfs): Response
    {
        abort_unless($rtv->company_id === auth()->user()->company_id, 403);

        return $pdfs->streamRtv($rtv, auth()->user());
    }

    public function creditMemo(CreditMemo $memo, DocumentPdfService $pdfs): Response
    {
        abort_unless($memo->company_id === auth()->user()->company_id, 403);

        return $pdfs->downloadCreditMemo($memo);
    }

    public function emailInvoice(Request $request, Invoice $invoice, DocumentPdfService $pdfs): RedirectResponse
    {
        abort_unless($invoice->company_id === auth()->user()->company_id, 403);

        $data = $request->validate([
            'email' => 'required|email',
            'subject' => 'nullable|string|max:255',
        ]);

        $pdfs->emailInvoice($invoice, $data['email'], auth()->user(), $data['subject'] ?? null);

        return back()->with('status', 'Invoice emailed to '.$data['email']);
    }

    public function emailCreditMemo(Request $request, CreditMemo $memo, DocumentPdfService $pdfs): RedirectResponse
    {
        abort_unless($memo->company_id === auth()->user()->company_id, 403);

        $data = $request->validate([
            'email' => 'required|email',
            'subject' => 'nullable|string|max:255',
        ]);

        $pdfs->emailCreditMemo($memo, $data['email'], auth()->user(), $data['subject'] ?? null);

        return back()->with('status', 'Credit memo emailed to '.$data['email']);
    }

    public function itemsList(Request $request, DocumentPdfService $pdfs): Response
    {
        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'search' => 'nullable|string|max:255',
            'favorite' => 'nullable|string|max:64',
            'status' => 'nullable|string|in:active,inactive',
            'title' => 'nullable|string|max:120',
        ]);

        $favorite = $data['favorite'] ?? 'all';
        $status = $data['status'] ?? '';
        $search = $data['search'] ?? '';

        $items = Item::query()
            ->with('department')
            ->where('company_id', $companyId)
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term)
                        ->orWhere('manufacturer', 'like', $term);
                });
            })
            ->when($favorite === 'new', fn ($q) => $q->newItems())
            ->when($favorite === 'active' || $status === 'active', fn ($q) => $q->where('is_inactive', false))
            ->when($favorite === 'inactive' || $status === 'inactive', fn ($q) => $q->where('is_inactive', true))
            ->when($favorite === 'low_stock', fn ($q) => $q->lowStock())
            ->when(str_starts_with($favorite, 'dept:'), fn ($q) => $q->where('department_id', (int) substr($favorite, 5)))
            ->when(str_starts_with($favorite, 'cat:'), fn ($q) => $q->where('category_id', (int) substr($favorite, 4)))
            ->when(str_starts_with($favorite, 'sub:'), fn ($q) => $q->where('subcategory_id', (int) substr($favorite, 4)))
            ->orderBy('item_code')
            ->limit(2000)
            ->get();

        $title = $data['title'] ?: 'Items List';

        return $pdfs->itemsListPdf($items, auth()->user(), $title)
            ->stream('items-list-'.now()->format('Ymd-His').'.pdf');
    }

    public function priceList(Request $request, DocumentPdfService $pdfs): Response
    {
        $data = $request->validate([
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer',
            'price_level_ids' => 'nullable|array',
            'price_level_ids.*' => 'integer',
            'search' => 'nullable|string|max:255',
            'include_inactive' => 'nullable|boolean',
            'title' => 'nullable|string|max:160',
        ]);

        $items = $pdfs->queryPriceListItems(
            auth()->user()->company_id,
            null,
            null,
            $data['search'] ?? '',
            (bool) ($data['include_inactive'] ?? false),
            $data['department_ids'] ?? [],
            $data['category_ids'] ?? [],
        );

        $levelIds = $data['price_level_ids'] ?? [];

        return $pdfs->priceListPdf(
            $items,
            auth()->user(),
            $data['title'] ?? 'Price List',
            null,
            $levelIds
        )->stream('price-list-'.now()->format('Ymd-His').'.pdf');
    }

    public function customersList(Request $request, DocumentPdfService $pdfs): Response
    {
        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'search' => 'nullable|string|max:255',
            'favorite' => 'nullable|string|max:64',
            'status' => 'nullable|string|in:active,inactive',
            'customer_id' => 'nullable|integer',
            'title' => 'nullable|string|max:120',
        ]);

        $query = Customer::query()
            ->with(['salesRep', 'priceLevel', 'paymentTerm', 'deliveryRoute'])
            ->where('company_id', $companyId);

        if (! empty($data['customer_id'])) {
            $query->whereKey((int) $data['customer_id']);
        } else {
            $search = $data['search'] ?? '';
            $favorite = $data['favorite'] ?? 'all';
            $status = $data['status'] ?? '';

            $query
                ->when($search !== '', function ($q) use ($search) {
                    $term = '%'.$search.'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('customer_id', 'like', $term)
                            ->orWhere('company_name', 'like', $term)
                            ->orWhere('contact', 'like', $term)
                            ->orWhere('telephone', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
                })
                ->when($favorite === 'active' || $status === 'active', fn ($q) => $q->where('is_inactive', false))
                ->when($favorite === 'inactive' || $status === 'inactive', fn ($q) => $q->where('is_inactive', true));
        }

        $customers = $query->orderBy('company_name')->limit(500)->get();
        $title = $data['title'] ?: (! empty($data['customer_id']) ? 'Customer Detail' : 'Customers List');

        return $pdfs->customersListPdf($customers, auth()->user(), $title)
            ->stream('customers-'.now()->format('Ymd-His').'.pdf');
    }
}
