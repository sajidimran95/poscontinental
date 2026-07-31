<?php

namespace App\Models;

use App\Support\AppFeatures;
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

    public function allows(string $feature, string $action = 'view'): bool
    {
        if ($this->name === 'admin') {
            return true;
        }

        $map = AppFeatures::expand($this->permissions);
        if ($map === null) {
            // Legacy roles with null permissions keep full access until edited.
            return true;
        }

        if ($map === []) {
            return false;
        }

        $actions = $map[$feature] ?? [];

        return in_array($action, $actions, true);
    }

    /** Whether the role can see the feature menu at all (any action). */
    public function allowsAny(string $feature): bool
    {
        if ($this->name === 'admin') {
            return true;
        }

        $map = AppFeatures::expand($this->permissions);
        if ($map === null) {
            return true;
        }

        return ($map[$feature] ?? []) !== [];
    }
}
