<?php

namespace App\Http\Controllers\Api\Rep;

use App\Http\Controllers\Controller;
use App\Http\Resources\Rep\SalesOrderResource;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Rep\CreateSalesOrderFromRep;
use App\Services\Rep\SalesRepScope;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    /**
     * Order history for the rep's assigned customers (read-only list).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        $query = SalesRepScope::salesOrdersQuery($user)
            ->with(['customer:id,customer_id,company_name', 'salesRep:id,name'])
            ->when($request->filled('customer_id'), function ($q) use ($request, $user) {
                $customer = Customer::query()->findOrFail($request->integer('customer_id'));
                SalesRepScope::assertCustomerAccess($user, $customer);
                $q->where('customer_id', $customer->id);
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', $term)
                        ->orWhere('customer_po_no', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhereHas('customer', function ($c) use ($term) {
                            $c->where('company_name', 'like', $term)
                                ->orWhere('customer_id', 'like', $term);
                        });
                });
            })
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        return ApiResponse::paginated(
            $paginator,
            fn (SalesOrder $order) => (new SalesOrderResource($order))->resolve($request)
        );
    }

    public function show(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $salesOrder->load(['customer', 'salesRep', 'lines']);
        SalesRepScope::assertOrderAccess($user, $salesOrder);

        return ApiResponse::success([
            'order' => (new SalesOrderResource($salesOrder))->resolve($request),
        ]);
    }

    /**
     * Create Sales Order New status for selected assigned customer (Section 4.1 parity).
     */
    public function store(Request $request, CreateSalesOrderFromRep $creator): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'required_date' => 'nullable|date',
            'customer_po_no' => 'nullable|string|max:64',
            'reference_no' => 'nullable|string|max:64',
            'comments' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.item_code' => 'required|string|max:64',
            'lines.*.qty_ordered' => 'required|numeric|min:0.0001',
            'lines.*.price' => 'nullable|numeric|min:0',
            'lines.*.uom' => 'nullable|string|max:16',
            'lines.*.line_message' => 'nullable|string|max:255',
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        $order = $creator->handle($user, $customer, $data);

        return ApiResponse::created([
            'order' => (new SalesOrderResource($order))->resolve($request),
        ], 'Sales order created.');
    }
}
