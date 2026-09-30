<?php

namespace App\Support\Store;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class StoreContext
{
    /**
     * The company whose catalog the public storefront sells (first company with the store turned on).
     */
    public static function company(): ?Company
    {
        return once(static function (): ?Company {
            try {
                return Company::query()->where('ecommerce_enabled', true)->orderBy('id')->first();
            } catch (QueryException) {
                // Store migration not run yet: behave as if the store is off.
                return null;
            }
        });
    }

    public static function enabled(): bool
    {
        return static::company() !== null;
    }

    public static function companyId(): int
    {
        return (int) static::company()?->id;
    }

    /** Logged-in store customer, only when they belong to the store company. */
    public static function customer(): ?Customer
    {
        $customer = auth('customer')->user();
        if (! $customer instanceof Customer || (int) $customer->company_id !== static::companyId()) {
            return null;
        }

        return $customer;
    }

    /**
     * Branding + contact block used by every storefront view.
     *
     * @return array<string, mixed>
     */
    public static function shopInfo(?Company $company = null): array
    {
        $company ??= static::company();

        $name = trim((string) ($company?->ecommerce_store_name ?: Str::title((string) ($company?->name ?? 'Wholesale Store'))));
        $words = preg_split('/\s+/', $name) ?: [$name];
        $line2 = count($words) > 1 ? array_pop($words) : 'Wholesale';
        $line1 = implode(' ', $words);

        $phone = trim((string) ($company?->ecommerce_phone ?: $company?->phone));
        $email = trim((string) ($company?->ecommerce_email ?: $company?->email));
        $cityState = trim(Str::title((string) $company?->city).', '.strtoupper((string) $company?->state).' '.$company?->zip_code, ', ');
        $address = trim(implode(', ', array_filter([Str::title((string) $company?->address), $cityState])));

        return [
            'name' => $name,
            'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            'logo_line1' => mb_strtoupper($line1),
            'logo_line2' => mb_strtoupper($line2),
            'logo_line1_title' => $line1,
            'logo_url' => null,
            'tagline' => $company?->ecommerce_tagline ?: 'Wholesale Distributor for Licensed Retailers',
            'phone' => $phone !== '' ? $phone : null,
            'phone_tel' => preg_replace('/[^\d+]/', '', $phone),
            'email' => $email !== '' ? $email : null,
            'hours' => $company?->ecommerce_hours ?: null,
            'address_line' => $address !== '' ? $address : null,
            'about_short' => $name.' serves licensed retailers with tobacco, cigarettes, candy, snacks, drinks and general merchandise at competitive wholesale prices.',
            'announcements' => [
                ['t' => 'New Arrivals Every Week — Shop the Latest Products', 'a' => 'View New Arrivals →', 'href' => route('ecommerce.shop', ['new' => 1])],
                ['t' => 'Log In to See Your Account Pricing', 'a' => 'Log In →', 'href' => route('ecommerce.login')],
                ['t' => 'Licensed Retailer? Apply for a Wholesale Account Today', 'a' => 'Apply Now →', 'href' => route('ecommerce.register')],
            ],
        ];
    }
}
