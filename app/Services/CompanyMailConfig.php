<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Config;

class CompanyMailConfig
{
    public static function apply(?Company $company): void
    {
        if (! $company) {
            return;
        }

        $mailer = $company->mail_mailer ?: 'log';

        Config::set('mail.default', $mailer);
        Config::set('mail.from.address', $company->mail_from_address ?: config('mail.from.address'));
        Config::set('mail.from.name', $company->mail_from_name ?: ($company->name ?: config('mail.from.name')));

        if ($mailer === 'smtp') {
            Config::set('mail.mailers.smtp.transport', 'smtp');
            Config::set('mail.mailers.smtp.host', $company->mail_host ?: '127.0.0.1');
            Config::set('mail.mailers.smtp.port', (int) ($company->mail_port ?: 587));
            Config::set('mail.mailers.smtp.username', $company->mail_username);
            // Desktop app cannot decrypt the server's saved password (different APP_KEY):
            // fall back to MAIL_PASSWORD from its .env.
            Config::set('mail.mailers.smtp.password', $company->decryptedOrNull('mail_password') ?? config('mail.mailers.smtp.password'));
            Config::set('mail.mailers.smtp.encryption', $company->mail_encryption ?: null);
            Config::set('mail.mailers.smtp.timeout', 30);
        }
    }
}
