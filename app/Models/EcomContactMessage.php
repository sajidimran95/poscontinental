<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcomContactMessage extends Model
{
    protected $fillable = ['company_id', 'name', 'email', 'phone', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
