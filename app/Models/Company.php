<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'code', 'name', 'address', 'city', 'state', 'zip_code',
        'phone', 'fax', 'email', 'contact_name',
        'fein_no',
        'secondary_tob_number',
        'secondary_cig_number',
        'state_license_number',
        'transmitter_account_number',
        'is_active',
        'mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_password',
        'mail_encryption', 'mail_from_address', 'mail_from_name',
        'allow_negative_stock',
        'japs_ai_enabled', 'japs_ai_api_key', 'japs_ai_model', 'japs_ai_widget_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'mail_password' => 'encrypted',
            'japs_ai_enabled' => 'boolean',
            'japs_ai_api_key' => 'encrypted',
            'japs_ai_widget_enabled' => 'boolean',
        ];
    }

    public function allowsNegativeStock(): bool
    {
        return (bool) ($this->allow_negative_stock ?? true);
    }

    /**
     * MSA StateLicenseNumber: OTP uses secondary tob #; cigarettes use secondary cig #.
     * Falls back to legacy state_license_number when the product field is empty.
     */
    public function msaLicenseNumber(?string $product = null): string
    {
        $product = $product === 'otp' ? 'otp' : 'cigarettes';
        $raw = $product === 'otp'
            ? ($this->secondary_tob_number ?: $this->state_license_number)
            : ($this->secondary_cig_number ?: $this->state_license_number);

        return preg_replace('/\D+/', '', (string) $raw) ?: '';
    }

    public function msaLicenseLabel(string $product): string
    {
        return $product === 'otp' ? 'Secondary Tob Number' : 'Secondary Cig Number';
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Street line for invoices / sales order letterhead. */
    public function letterheadAddress(): string
    {
        $address = trim((string) $this->address);
        if ($address !== '') {
            return strtoupper($address);
        }

        return (string) config('company.address', '3802 TRADE CENTER DR');
    }

    /** City, ST ZIP line for letterhead. */
    public function letterheadCityLine(): string
    {
        $city = trim((string) $this->city);
        $state = strtoupper(trim((string) $this->state));
        $zip = trim((string) $this->zip_code);

        if ($city !== '' || $state !== '' || $zip !== '') {
            $left = collect([$city !== '' ? strtoupper($city) : null, $state !== '' ? $state : null])
                ->filter()
                ->implode(', ');

            return trim($left.($zip !== '' ? ' '.$zip : ''));
        }

        return (string) config('company.city_line', 'ANN ARBOR, MI 48108');
    }

    public function letterheadTel(): string
    {
        $phone = preg_replace('/\s+/', '', (string) $this->phone);
        if ($phone !== '') {
            return str_starts_with(strtolower($phone), 'tel') ? $phone : 'Tel:'.$phone;
        }

        return (string) config('company.tel', 'Tel:7346773510');
    }

    public function letterheadFax(): string
    {
        $fax = preg_replace('/\s+/', '', (string) $this->fax);
        if ($fax !== '') {
            return str_starts_with(strtolower($fax), 'fax') ? $fax : 'Fax:'.$fax;
        }

        return (string) config('company.fax', 'Fax:7346773567');
    }

    /**
     * @return array{companyAddress: string, companyCityLine: string, companyTel: string, companyFax: string, companyEmail: string, companyContact: string}
     */
    public function letterhead(): array
    {
        return [
            'companyAddress' => $this->letterheadAddress(),
            'companyCityLine' => $this->letterheadCityLine(),
            'companyTel' => $this->letterheadTel(),
            'companyFax' => $this->letterheadFax(),
            'companyEmail' => trim((string) $this->email),
            'companyContact' => trim((string) $this->contact_name),
        ];
    }
}
