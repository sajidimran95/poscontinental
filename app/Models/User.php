<?php

namespace App\Models;

use App\Support\AppFeatures;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'company_id',
        'site_id',
        'department_id',
        'role_id',
        'name',
        'username',
        'job_title',
        'avatar_path',
        'email',
        'password',
        'is_active',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    private ?bool $adminCache = null;

    /** @var array<string, bool> */
    private array $featureAccessCache = [];

    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar_path)) {
            return null;
        }

        $path = str_replace('\\', '/', ltrim((string) $this->avatar_path, '/'));

        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'users/avatars/')) {
            return null;
        }

        $published = public_path('uploads/'.$path);
        if (is_file($published)) {
            return '/uploads/'.$path.'?v='.(filemtime($published) ?: time());
        }

        if (Storage::disk('public')->exists($path)) {
            $version = Storage::disk('public')->lastModified($path);

            return '/media/'.$path.'?v='.$version;
        }

        return null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isSalesRep(): bool
    {
        return $this->role?->name === 'sales_rep';
    }

    /**
     * Users who can be assigned as sales rep on customers / orders (desktop POS).
     * Active company staff — not limited to role sales_rep (field app still is).
     */
    public static function assignableSalesRepsQuery(int $companyId, ?int $includeUserId = null)
    {
        return static::query()
            ->with('role:id,name,label')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($includeUserId) {
                $q->where('is_active', true);
                if ($includeUserId) {
                    $q->orWhere('id', $includeUserId);
                }
            })
            ->orderBy('name');
    }

    public function isAdmin(): bool
    {
        return $this->adminCache ??= ($this->role?->name === 'admin');
    }

    public function canAccessFeature(string $feature, string $action = 'view'): bool
    {
        $cacheKey = $feature.'.'.$action;
        if (array_key_exists($cacheKey, $this->featureAccessCache)) {
            return $this->featureAccessCache[$cacheKey];
        }

        return $this->featureAccessCache[$cacheKey] = $this->resolveFeatureAccess($feature, $action);
    }

    protected function resolveFeatureAccess(string $feature, string $action): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Per-user permissions override the role when saved on the user.
        if (is_array($this->permissions)) {
            $map = AppFeatures::expand($this->permissions) ?? [];

            return in_array($action, $map[$feature] ?? [], true);
        }

        if (! $this->role) {
            return true;
        }

        return $this->role->allows($feature, $action);
    }

    /** Floating AI chat widget — View on POS AI Chat, or any POS AI Settings access. */
    public function canUsePosAiChat(): bool
    {
        return $this->canAccessFeature('admin.japsai_chat', 'view')
            || $this->canAccessFeature('admin.japsai', 'view');
    }

    /** File → POS AI settings (API key, enable widget). Not granted by chat-only View. */
    public function canManagePosAiSettings(): bool
    {
        return $this->canAccessFeature('admin.japsai', 'edit');
    }
}
