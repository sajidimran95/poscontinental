<?php

namespace App\Http\Controllers\Api\Rep;

use App\Http\Controllers\Controller;
use App\Http\Resources\Rep\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use App\Services\Rep\SalesRepScope;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Assigned customers only (Section 11.9).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        $query = SalesRepScope::customersQuery($user)
            ->with('priceLevel:id,name')
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_inactive', false))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('customer_id', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('contact', 'like', $term)
                        ->orWhere('telephone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('company_name');

        $paginator = $query->paginate($perPage);

        return ApiResponse::paginated(
            $paginator,
            fn (Customer $c) => (new CustomerResource($c))->resolve($request)
        );
    }

    /**
     * Account card: Balance, Credit Limit, Available Credit, Messages & Alerts.
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        SalesRepScope::assertCustomerAccess($user, $customer);

        $customer->load('priceLevel:id,name', 'paymentTerm:id,name');

        return ApiResponse::success([
            'customer' => (new CustomerResource($customer))->resolve($request),
        ]);
    }
}
