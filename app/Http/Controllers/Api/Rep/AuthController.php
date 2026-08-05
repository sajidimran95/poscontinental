<?php

namespace App\Http\Controllers\Api\Rep;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Sales rep login (system user — same users as Sales Rep dropdown).
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::query()
            ->with('role:id,name,label')
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return ApiResponse::error('User account is inactive. Ask your admin to activate the account.', 403);
        }

        if (! $user->company_id) {
            return ApiResponse::error('User is not assigned to a company.', 403);
        }

        if (! $user->isSalesRep()) {
            return ApiResponse::error(
                'Only Sales Representative users can log into this app. Admin must create your user with role Sales Rep.',
                403
            );
        }

        $tokenName = $data['device_name'] ?? 'sales-rep-mobile';
        $token = $user->createToken($tokenName)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('role:id,name,label', 'site:id,code,name');

        return ApiResponse::success([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'company_id' => $user->company_id,
            'site_id' => $user->site_id,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'label' => $user->role->label,
            ] : null,
            'site' => $user->relationLoaded('site') && $user->site ? [
                'id' => $user->site->id,
                'code' => $user->site->code,
                'name' => $user->site->name,
            ] : null,
            'is_sales_rep' => $user->isSalesRep(),
            'is_admin' => $user->isAdmin(),
        ];
    }
}
