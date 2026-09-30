<?php

namespace App\Http\Controllers\Store;

use App\Models\CustomerShippingAddress;
use App\Models\EcomCustomerLicense;
use App\Models\EcomOrderMeta;
use App\Models\EcomWishlist;
use App\Models\SalesOrder;
use App\Support\Store\StoreCatalog;
use App\Support\Store\WholesaleAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StoreAccountController extends StoreController
{
    public function index()
    {
        $customer = $this->requireCustomer();

        return view('store.account.index', [
            'contact' => $customer,
            'first_name' => explode(' ', trim((string) ($customer->contact ?: $customer->company_name ?: 'there')))[0],
            'approval' => WholesaleAccount::status($customer),
            'orders' => SalesOrder::query()->where('customer_id', $customer->id)->orderByDesc('id')->limit(5)->get(['id', 'order_number', 'total', 'order_date', 'status']),
        ]);
    }

    public function orders()
    {
        $customer = $this->requireCustomer();
        $orders = SalesOrder::query()->where('customer_id', $customer->id)->orderByDesc('id')->paginate(15);
        $metas = EcomOrderMeta::query()->whereIn('sales_order_id', $orders->pluck('id'))->get()->keyBy('sales_order_id');

        return view('store.account.orders', compact('orders', 'metas'));
    }

    public function licenses(Request $request)
    {
        $customer = $this->requireCustomer();

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'license_type' => ['required', Rule::in(array_keys(EcomCustomerLicense::TYPES))],
                'label' => 'nullable|string|max:191',
                'certificate_number' => 'nullable|string|max:100',
                'expires_at' => 'nullable|date',
                'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:8192',
            ]);
            $file = $request->file('file');
            if (! $file && ! filled($data['certificate_number'] ?? null)) {
                return back()->withErrors(['file' => 'Add a certificate number or upload a file.']);
            }

            EcomCustomerLicense::query()->create([
                'customer_id' => $customer->id,
                'license_type' => $data['license_type'],
                'label' => $data['label'] ?? null,
                'certificate_number' => $data['certificate_number'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'file_path' => $file?->store('ecom-licenses/'.$customer->id, 'local'),
                'original_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 191) : null,
            ]);

            if (WholesaleAccount::status($customer) === WholesaleAccount::REJECTED) {
                $customer->update(['web_status' => WholesaleAccount::PENDING]);
            }

            return redirect()->route('ecommerce.account.licenses')->with('success', 'License submitted for review.');
        }

        return view('store.account.licenses', [
            'approval' => WholesaleAccount::status($customer),
            'licenses' => EcomCustomerLicense::query()->where('customer_id', $customer->id)->orderByDesc('id')->get(),
        ]);
    }

    public function licenseFile(EcomCustomerLicense $license)
    {
        $customer = $this->requireCustomer();
        abort_unless((int) $license->customer_id === (int) $customer->id && $license->file_path, 404);
        abort_unless(Storage::disk('local')->exists($license->file_path), 404);

        return Storage::disk('local')->response($license->file_path, $license->original_name ?: basename($license->file_path));
    }

    public function profile(Request $request)
    {
        $customer = $this->requireCustomer();

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:191',
                'mobile' => 'required|string|max:32',
            ]);
            $email = mb_strtolower(trim($data['email']));
            $taken = $customer->newQuery()
                ->where('company_id', $customer->company_id)
                ->whereKeyNot($customer->id)
                ->where(fn ($q) => $q->whereRaw('LOWER(portal_email) = ?', [$email])->orWhereRaw('LOWER(email) = ?', [$email]))
                ->exists();
            if ($taken) {
                return back()->withErrors(['email' => 'That email is used by another account.'])->withInput();
            }

            $customer->update([
                'contact' => trim($data['first_name'].' '.$data['last_name']),
                'portal_email' => $email,
                'mobile' => $data['mobile'],
            ]);

            return back()->with('success', 'Profile saved.');
        }

        $parts = explode(' ', trim((string) $customer->contact), 2) + ['', ''];

        return view('store.account.profile', ['profile' => [
            'first_name' => $parts[0],
            'last_name' => $parts[1],
            'email' => $customer->loginEmail(),
            'mobile' => $customer->mobile ?: $customer->telephone,
        ]]);
    }

    public function password(Request $request)
    {
        $customer = $this->requireCustomer();

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'current_password' => 'required|string',
                'password' => ['required', 'string', 'min:7', 'max:72', 'confirmed'],
            ]);
            if (! Hash::check($data['current_password'], (string) $customer->portal_password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }
            $customer->portal_password = Hash::make($data['password']);
            $customer->save();

            return back()->with('success', 'Password updated.');
        }

        return view('store.account.password');
    }

    public function addresses(Request $request)
    {
        $customer = $this->requireCustomer();

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'company' => 'nullable|string|max:191',
                'address_line_1' => 'required|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:40',
                'zip_code' => 'required|string|max:20',
                'is_default' => 'nullable|boolean',
            ]);
            $isDefault = $request->boolean('is_default');
            if ($isDefault) {
                $customer->shippingAddresses()->update(['is_primary' => false]);
            }
            $customer->shippingAddresses()->create([
                'name' => ($data['company'] ?? null) ?: trim($data['first_name'].' '.$data['last_name']),
                'address' => trim($data['address_line_1'].' '.($data['address_line_2'] ?? '')),
                'city' => $data['city'],
                'state' => $data['state'],
                'zip' => $data['zip_code'],
                'telephone' => $customer->mobile ?: $customer->telephone,
                'is_primary' => $isDefault,
                'sort_order' => (int) $customer->shippingAddresses()->max('sort_order') + 1,
            ]);

            return back()->with('success', 'Address saved.');
        }

        $addresses = $customer->shippingAddresses()->get()->map(fn (CustomerShippingAddress $a) => (object) [
            'first_name' => $a->name ?: 'Location',
            'last_name' => '',
            'is_default' => (bool) $a->is_primary,
            'formatted' => implode(', ', array_filter([$a->address, $a->city, trim($a->state.' '.$a->zip)])),
        ]);

        return view('store.account.addresses', compact('addresses'));
    }

    public function wishlist()
    {
        $customer = $this->requireCustomer();
        $itemIds = EcomWishlist::query()->where('customer_id', $customer->id)->orderByDesc('id')->pluck('item_id')->all();
        $items = StoreCatalog::query($this->companyId())->with(['prices', 'category'])->whereIn('items.id', $itemIds ?: [0])->get();

        return view('store.account.wishlist', [
            'items' => StoreCatalog::presentMany($items, $customer, $this->company())->map(fn ($p) => (object) ['product' => $p]),
        ]);
    }

    public function toggleWishlist(Request $request)
    {
        $customer = $this->requireCustomer();
        $itemId = (int) $request->input('item_id');
        abort_unless(StoreCatalog::query($this->companyId())->whereKey($itemId)->exists(), 404);

        $row = EcomWishlist::query()->where('customer_id', $customer->id)->where('item_id', $itemId)->first();
        if ($row) {
            $row->delete();

            return back()->with('success', 'Removed from wishlist.');
        }
        EcomWishlist::query()->create(['customer_id' => $customer->id, 'item_id' => $itemId]);

        return back()->with('success', 'Added to wishlist.');
    }
}
