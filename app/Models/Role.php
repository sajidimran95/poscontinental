<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'permissions'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function allows(string $feature): bool
    {
        if ($this->name === 'admin') {
            return true;
        }

        $perms = $this->permissions;
        if ($perms === null) {
            // Legacy roles with null permissions keep full access until edited.
            return true;
        }

        if ($perms === []) {
            return false;
        }

        return in_array($feature, $perms, true);
    }
}
