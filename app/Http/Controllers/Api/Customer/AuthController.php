<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:120',
        ]);

        $customer = Customer::query()
            ->with('company')
            ->where('portal_email', $data['email'])
            ->first();

        if (! $customer || ! filled($customer->portal_password) || ! Hash::check($data['password'], $customer->portal_password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($customer->is_inactive) {
            return response()->json(['message' => 'Customer account is inactive. Contact your sales office.'], 403);
        }

        if ($customer->company && ! $customer->company->customer_app_api_active) {
            return response()->json(['message' => 'Customer app API is deactivated.'], 403);
        }

        $token = $customer->createToken($data['device_name'] ?? 'customer-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => $this->customerPayload($customer),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $customer->load('priceLevel:id,code,name');

        return response()->json($this->customerPayload($customer));
    }

    /** @return array<string, mixed> */
    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_id' => $customer->customer_id,
            'company_name' => $customer->company_name,
            'contact' => $customer->contact,
            'email' => $customer->email,
            'portal_email' => $customer->portal_email,
            'telephone' => $customer->telephone,
            'address' => $customer->address,
            'city' => $customer->city,
            'state' => $customer->state,
            'zip_code' => $customer->zip_code,
            'balance' => (float) $customer->balance,
            'credit_limit' => (float) $customer->credit_limit,
            'available_credit' => $customer->available_credit,
            'messages_alerts' => $customer->messages_alerts,
            'price_level' => $customer->priceLevel
                ? [
                    'id' => $customer->priceLevel->id,
                    'code' => $customer->priceLevel->code,
                    'name' => $customer->priceLevel->name,
                ]
                : null,
            'portal_active' => (bool) $customer->portal_active,
        ];
    }
}
