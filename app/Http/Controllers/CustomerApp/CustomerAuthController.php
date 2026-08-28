<?php

namespace App\Http\Controllers\CustomerApp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('customer')->check() && Auth::guard('customer')->user()?->canUseCustomerApp()) {
            return redirect()->route('customer.home');
        }

        return view('customer.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim($data['login']);
        $digits = preg_replace('/\D+/', '', $login) ?: '';

        $customer = Customer::query()
            ->with('company')
            ->where('is_inactive', false)
            ->where('portal_active', true)
            ->where(function ($q) use ($login, $digits) {
                $q->whereRaw('LOWER(email) = ?', [mb_strtolower($login)])
                    ->orWhereRaw('LOWER(portal_email) = ?', [mb_strtolower($login)]);
                if ($digits !== '' && strlen($digits) >= 7) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(mobile,''),'-',''),' ',''),'(',''),')','') = ?", [$digits])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(telephone,''),'-',''),' ',''),'(',''),')','') = ?", [$digits]);
                }
            })
            ->first();

        $hash = $customer?->portal_password;
        if (! $customer || ! $hash || ! Hash::check($data['password'], $hash)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid email / mobile or password.'],
            ]);
        }

        if (! $customer->canUseCustomerApp()) {
            return redirect()->route('customer.login')
                ->with('status', ['success' => 0, 'msg' => 'Customer app access is turned off for this account.']);
        }

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->intended(route('customer.home'));
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
