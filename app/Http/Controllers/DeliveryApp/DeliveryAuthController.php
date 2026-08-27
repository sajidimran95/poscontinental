<?php

namespace App\Http\Controllers\DeliveryApp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DeliveryAppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeliveryAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('delivery')->check() && DeliveryAppAccess::allows(Auth::guard('delivery')->user())) {
            return redirect()->route('delivery.app.home');
        }

        return view('delivery-app.login');
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

        if (! DeliveryAppAccess::allows($user)) {
            return redirect()->route('delivery.app.login')
                ->with('status', ['success' => 0, 'msg' => DeliveryAppAccess::denyMessage($user)]);
        }

        Auth::guard('delivery')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('delivery.app.home'));
    }

    public function logout(Request $request)
    {
        Auth::guard('delivery')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('delivery.app.login');
    }
}
