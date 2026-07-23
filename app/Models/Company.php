<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'code', 'name', 'is_active', 'customer_app_api_active',
        'mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_password',
        'mail_encryption', 'mail_from_address', 'mail_from_name',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'customer_app_api_active' => 'boolean',
            'mail_password' => 'encrypted',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
