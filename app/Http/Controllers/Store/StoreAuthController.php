<?php

namespace App\Http\Controllers\Store;

use App\Models\Customer;
use App\Models\EcomCustomerLicense;
use App\Support\Store\StoreCart;
use App\Support\Store\WholesaleAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StoreAuthController extends StoreController
{
    public function __construct(private StoreCart $cart) {}

    public function showLogin()
    {
        if ($this->customer()) {
            return redirect()->route('ecommerce.account');
        }

        return view('store.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string|max:191',
            'password' => 'required|string|max:100',
        ]);

        $login = mb_strtolower(trim($data['login']));
        $customer = Customer::query()
            ->where('company_id', $this->companyId())
            ->where('is_inactive', false)
            ->whereNotNull('portal_password')
            ->where(fn ($q) => $q->whereRaw('LOWER(portal_email) = ?', [$login])->orWhereRaw('LOWER(email) = ?', [$login]))
            ->orderByRaw('CASE WHEN LOWER(portal_email) = ? THEN 0 ELSE 1 END', [$login])
            ->first();

        if (! $customer || ! Hash::check($data['password'], (string) $customer->portal_password)) {
            throw ValidationException::withMessages(['login' => 'Invalid email or password.']);
        }

        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();
        $this->cart->mergeGuestInto($customer);

        return redirect()->intended(route('ecommerce.account'));
    }

    public function showRegister()
    {
        if ($this->customer()) {
            return redirect()->route('ecommerce.account');
        }

        return view('store.auth.register', ['us_states' => $this->usStates()]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:191',
            'mobile' => 'required|string|max:32',
            'tax_number' => 'nullable|string|max:40',
            'tax_exempt_code' => 'nullable|string|max:60',
            'contact_name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'billing_email' => 'nullable|email|max:191',
            'password' => ['required', 'string', 'min:7', 'max:16', 'regex:/^\S+$/', 'confirmed'],
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:40',
            'zip_code' => 'required|string|max:20',
            'licenses' => 'nullable|array|max:12',
            'licenses.*.type' => 'nullable|string|max:30',
            'licenses.*.label' => 'nullable|string|max:191',
            'licenses.*.number' => 'nullable|string|max:100',
            'licenses.*.expires_at' => 'nullable|date',
            'licenses.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:8192',
            'agree_terms' => 'accepted',
        ], [
            'password.regex' => 'Password cannot contain spaces.',
        ]);

        $companyId = $this->companyId();
        $email = mb_strtolower(trim($data['email']));
        $taken = Customer::query()
            ->where('company_id', $companyId)
            ->where(fn ($q) => $q->whereRaw('LOWER(portal_email) = ?', [$email])->orWhereRaw('LOWER(email) = ?', [$email]))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists. Please sign in or contact us.']);
        }

        $customer = DB::transaction(function () use ($data, $companyId, $email, $request) {
            $code = 'W'.now()->format('ymdHis');
            while (Customer::query()->where('company_id', $companyId)->where('customer_id', $code)->exists()) {
                $code = 'W'.now()->format('ymdHis').random_int(10, 99);
            }

            $customer = Customer::query()->create([
                'company_id' => $companyId,
                'customer_id' => $code,
                'contact' => $data['contact_name'],
                'company_name' => $data['business_name'],
                'address' => trim($data['address_line_1'].' '.($data['address_line_2'] ?? '')),
                'city' => $data['city'],
                'state' => $data['state'],
                'zip_code' => $data['zip_code'],
                'country' => 'United States',
                'telephone' => $data['mobile'],
                'mobile' => $data['mobile'],
                'email' => ($data['billing_email'] ?? null) ?: $email,
                'portal_email' => $email,
                'portal_password' => Hash::make($data['password']),
                'portal_active' => false,
                'web_status' => WholesaleAccount::PENDING,
                'web_registered_at' => now(),
                'fein_no' => $data['tax_number'] ?? null,
                'tax_certificate_no' => $data['tax_exempt_code'] ?? null,
                'lead_source' => 'Online Store',
                'is_inactive' => false,
                'customer_since' => now()->toDateString(),
            ]);

            foreach ($data['licenses'] ?? [] as $key => $row) {
                $file = $request->file("licenses.$key.file");
                if (! filled($row['number'] ?? null) && ! $file) {
                    continue;
                }
                $type = array_key_exists((string) ($row['type'] ?? ''), EcomCustomerLicense::TYPES) ? $row['type'] : 'other';
                EcomCustomerLicense::query()->create([
                    'customer_id' => $customer->id,
                    'license_type' => $type,
                    'label' => $row['label'] ?? null,
                    'certificate_number' => $row['number'] ?? null,
                    'expires_at' => $row['expires_at'] ?? null,
                    'file_path' => $file?->store('ecom-licenses/'.$customer->id, 'local'),
                    'original_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 191) : null,
                ]);
            }

            return $customer;
        });

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $this->cart->mergeGuestInto($customer);

        return redirect()->route('ecommerce.account')
            ->with('success', 'Application submitted. You can browse and build your cart — checkout unlocks after we approve your account.');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('ecommerce.home');
    }
}
