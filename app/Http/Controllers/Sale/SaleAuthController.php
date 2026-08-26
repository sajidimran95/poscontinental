<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SaleAppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SaleAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('sale')->check() && SaleAppAccess::allows(Auth::guard('sale')->user())) {
            return redirect()->route('sale.home');
        }

        return view('sale.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => 'required_without:email|nullable|string',
            'email' => 'required_without:username|nullable|string',
            'password' => 'required|string',
        ]);

        $login = strtolower(trim((string) ($data['username'] ?? $data['email'] ?? '')));
        $user = User::query()
            ->with('role')
            ->where(function ($q) use ($login) {
                $q->where('email', $login)->orWhere('username', $login);
            })
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        if (! SaleAppAccess::allows($user)) {
            return redirect()->route('sale.login')
                ->with('status', ['success' => 0, 'msg' => SaleAppAccess::denyMessage($user)]);
        }

        Auth::guard('sale')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        if ($user->site_id) {
            $request->session()->put('user.default_location_id', $user->site_id);
        }

        return redirect()->intended(route('sale.home'));
    }

    public function logout(Request $request)
    {
        Auth::guard('sale')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('sale.login');
    }
}
