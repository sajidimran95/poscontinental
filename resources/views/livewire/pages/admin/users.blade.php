<?php

use App\Livewire\Concerns\PaginatesDeskLists;
use App\Livewire\Concerns\SelectsDeskRows;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\Department;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Support\AppFeatures;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Users & Roles')] class extends Component
{
    use SortsDeskList;
    use PaginatesDeskLists;
    use SelectsDeskRows;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'users';

    /** '' | active | inactive */
    public string $statusFilter = '';

    public ?int $selectedId = null;

    public bool $compactView = false;

    public bool $showUserForm = false;

    public bool $viewMode = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $update_password = false;

    public ?int $role_id = null;

    public ?int $site_id = null;

    public ?int $department_id = null;

    public string $job_title = '';

    public bool $is_active = true;

    /** @var array<int, string> Per-user menu permissions (does not change the role). */
    public array $user_permissions = [];

    public bool $showRoleForm = false;

    public ?int $editingRoleId = null;

    public string $role_name = '';

    public string $role_label = '';

    /** @var array<int, string> */
    public array $role_permissions = [];

    public function with(): array
    {
        $this->ensureSystemRoles();

        $companyId = auth()->user()->company_id;

        $usersQuery = User::query()
            ->with(['role', 'site', 'department'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('job_title', 'like', $term)
                        ->orWhereHas('role', fn ($r) => $r->where('label', 'like', $term)->orWhere('name', 'like', $term))
                        ->orWhereHas('site', fn ($s) => $s->where('code', 'like', $term)->orWhere('name', 'like', $term))
                        ->orWhereHas('department', fn ($d) => $d->where('name', 'like', $term)->orWhere('code', 'like', $term));
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false));

        $listTitle = match (true) {
            $this->favorite === 'roles' => 'Roles List',
            $this->statusFilter === 'active' => 'Users List (Active)',
            $this->statusFilter === 'inactive' => 'Users List (Inactive)',
            default => 'Users List',
        };

        $rolesQuery = Role::query()
            ->withCount('users')
            ->when($this->favorite === 'roles' && $this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('label', 'like', $term);
                });
            });

        if ($this->favorite === 'roles') {
            $rolesQuery = $this->applyDeskSort($rolesQuery, 'label', 'asc');
        } else {
            $usersQuery = $this->applyDeskSort($usersQuery);
            $rolesQuery = $rolesQuery->orderBy('label');
        }

        $userScroll = $this->scrollDeskList($usersQuery);

        return [
            'users' => $userScroll['rows'],
            'listHasMore' => $userScroll['hasMore'],
            'listShown' => $userScroll['shown'],
            'roles' => $rolesQuery->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'departments' => Department::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'favorites' => [
                'users' => 'Users',
                'roles' => 'Roles',
            ],
            'listTitle' => $listTitle,
            'isShowingForm' => $this->showUserForm || $this->showRoleForm,
            'featureGroups' => AppFeatures::grouped(),
            'menuCards' => AppFeatures::menuCards(),
            'permActions' => AppFeatures::ACTIONS,
            'selectedRoleLabel' => $this->role_id
                ? (Role::query()->whereKey($this->role_id)->value('label') ?? 'Role')
                : null,
            'selectedRoleName' => $this->role_id
                ? (Role::query()->whereKey($this->role_id)->value('name') ?? '')
                : '',
        ];
    }

    protected function deskSortMap(): array
    {
        if ($this->favorite === 'roles') {
            return [
                'role_code' => 'name',
                'role_label' => 'label',
                'users_count' => 'users_count',
            ];
        }

        return [
            'user_name' => 'name',
            'email' => 'email',
            'role' => ['relation' => 'role', 'column' => 'label'],
            'department' => ['relation' => 'department', 'column' => 'name'],
            'job_title' => 'job_title',
            'site' => ['relation' => 'site', 'column' => 'code'],
            'is_active' => 'is_active',
        ];
    }

    /**
     * Ensure Sales Rep and other core roles exist so admin can assign them to users.
     */
    protected function ensureSystemRoles(): void
    {
        $defaults = AppFeatures::defaultRolePermissionTokens();

        foreach ([
            ['name' => 'admin', 'label' => 'Administrator'],
            ['name' => 'sales_rep', 'label' => 'Sales Rep'],
            ['name' => 'buyer', 'label' => 'Buyer'],
            ['name' => 'warehouse', 'label' => 'Warehouse'],
            ['name' => 'delivery', 'label' => 'Delivery'],
        ] as $role) {
            $existing = Role::query()->firstOrCreate(
                ['name' => $role['name']],
                [
                    'label' => $role['label'],
                    'permissions' => $role['name'] === 'admin'
                        ? AppFeatures::permissionTokens()
                        : ($role['name'] === 'delivery'
                            ? ['delivery.driver.view', 'delivery.driver.edit']
                            : $defaults),
                ]
            );

            // Repair empty non-admin role permission sets once.
            if ($role['name'] !== 'admin' && empty($existing->permissions)) {
                $existing->update([
                    'permissions' => $role['name'] === 'delivery'
                        ? ['delivery.driver.view', 'delivery.driver.edit']
                        : $defaults,
                ]);
            }
        }
    }

    /** @return list<string> */
    protected function permissionsFromRole(?int $roleId): array
    {
        if (! $roleId) {
            return [];
        }

        $role = Role::query()->find($roleId);
        if (! $role) {
            return [];
        }

        if ($role->name === 'admin') {
            return AppFeatures::permissionTokens();
        }

        $map = AppFeatures::expand($role->permissions);

        // Null legacy = operational menus only (File admin items stay inactive until enabled).
        if ($map === null) {
            return AppFeatures::defaultRolePermissionTokens();
        }

        return AppFeatures::checkboxTokens($role->permissions);
    }

    public function updatedRoleId(mixed $value): void
    {
        if (! $this->showUserForm) {
            return;
        }

        $this->user_permissions = $this->permissionsFromRole($value ? (int) $value : null);

        $roleName = Role::query()->whereKey($value)->value('name');
        if ($roleName === 'sales_rep' && trim($this->job_title) === '') {
            $this->job_title = 'Sales Representative';
        }
    }

    public function applyRolePermissionsToUser(): void
    {
        $this->user_permissions = $this->permissionsFromRole($this->role_id);
    }

    public function selectAllUserPermissions(): void
    {
        $this->user_permissions = AppFeatures::permissionTokens();
    }

    public function clearAllUserPermissions(): void
    {
        $this->user_permissions = [];
    }

    public function selectUserMenuGroup(string $menu): void
    {
        $current = $this->user_permissions;
        foreach (AppFeatures::featuresForMenu($menu) as $feature) {
            foreach (AppFeatures::ACTIONS as $action) {
                $token = AppFeatures::token($feature, $action);
                if (! in_array($token, $current, true)) {
                    $current[] = $token;
                }
            }
        }
        $this->user_permissions = array_values($current);
    }

    public function clearUserMenuGroup(string $menu): void
    {
        $remove = [];
        foreach (AppFeatures::featuresForMenu($menu) as $feature) {
            foreach (AppFeatures::ACTIONS as $action) {
                $remove[] = AppFeatures::token($feature, $action);
            }
        }
        $this->user_permissions = array_values(array_diff($this->user_permissions, $remove));
    }

    public function toggleUserFeatureRow(string $feature): void
    {
        if (! in_array($feature, AppFeatures::keys(), true)) {
            return;
        }

        $tokens = array_map(fn (string $a) => AppFeatures::token($feature, $a), AppFeatures::ACTIONS);
        $allOn = collect($tokens)->every(fn (string $t) => in_array($t, $this->user_permissions, true));

        if ($allOn) {
            $this->user_permissions = array_values(array_diff($this->user_permissions, $tokens));
        } else {
            $this->user_permissions = array_values(array_unique([...$this->user_permissions, ...$tokens]));
        }
    }

    public function updatingSearch(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
    }

    public function updatedFavorite(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
        $this->showUserForm = false;
        $this->showRoleForm = false;
        $this->statusFilter = '';
        $this->search = '';
    }

    public function updatedStatusFilter(): void
    {
        $this->resetDeskList();
        $this->selectedId = null;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetDeskList();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->selectedId = null;
        $this->resetDeskList();
    }

    public function toggleCompactView(): void
    {
        $this->compactView = ! $this->compactView;
    }

    public function refreshList(): void
    {
        $this->resetDeskList();
    }

    public function viewSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', $this->favorite === 'roles' ? 'Select a role first.' : 'Select a user first.');

            return;
        }

        if ($this->favorite === 'roles') {
            $this->viewRole($this->selectedId);

            return;
        }

        $this->viewUser($this->selectedId);
    }

    public function editSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', $this->favorite === 'roles' ? 'Select a role first.' : 'Select a user first.');

            return;
        }

        if ($this->favorite === 'roles') {
            $this->editRole($this->selectedId);

            return;
        }

        $this->editUser($this->selectedId);
    }

    public function deleteSelected(): void
    {
        if (! auth()->user()?->canAccessFeature('admin.users', 'delete')) {
            session()->flash('status', 'Your role cannot delete users or roles.');

            return;
        }

        if (! $this->selectedId) {
            session()->flash('status', $this->favorite === 'roles' ? 'Select a role first.' : 'Select a user first.');

            return;
        }

        if ($this->favorite === 'roles') {
            $role = Role::query()->withCount('users')->find($this->selectedId);
            if (! $role) {
                session()->flash('status', 'Role not found.');

                return;
            }
            if ($role->users_count > 0) {
                session()->flash('status', 'Role has users and cannot be deleted.');

                return;
            }
            $role->delete();
            $this->selectedId = null;
            session()->flash('status', 'Role deleted.');

            return;
        }

        if ($this->selectedId === auth()->id()) {
            session()->flash('status', 'You cannot delete your own account.');

            return;
        }

        $user = User::query()
            ->where('company_id', auth()->user()->company_id)
            ->find($this->selectedId);

        if (! $user) {
            session()->flash('status', 'User not found.');

            return;
        }

        $user->delete();
        $this->selectedId = null;
        session()->flash('status', 'User deleted.');
    }

    public function printSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', $this->favorite === 'roles' ? 'Select a role first.' : 'Select a user first.');

            return;
        }

        if ($this->favorite === 'roles') {
            $this->editRole($this->selectedId);
        } else {
            $this->editUser($this->selectedId);
        }
        $this->dispatch('print-user');
    }

    public function toggleActive(int $id): void
    {
        if ($id === auth()->id()) {
            session()->flash('status', 'You cannot deactivate your own account.');

            return;
        }

        $user = User::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
        $this->selectedId = $id;
    }

    public function startNewUser(): void
    {
        if (! auth()->user()?->canAccessFeature('admin.users', 'edit')) {
            session()->flash('status', 'Your role cannot create users.');

            return;
        }

        $this->favorite = 'users';
        $this->showUserForm = true;
        $this->showRoleForm = false;
        $this->viewMode = false;
        $this->editingUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->update_password = true;
        $this->role_id = Role::query()->where('name', 'sales_rep')->value('id')
            ?? Role::query()->orderBy('label')->value('id');
        $this->site_id = auth()->user()->site_id;
        $this->department_id = null;
        $this->job_title = 'Sales Representative';
        $this->is_active = true;
        $this->user_permissions = $this->permissionsFromRole($this->role_id);
        $this->resetErrorBag();
    }

    public function viewUser(int $id): void
    {
        $this->fillUserForm($id, true);
    }

    public function editUser(int $id): void
    {
        if (! auth()->user()?->canAccessFeature('admin.users', 'edit')) {
            session()->flash('status', 'Your role cannot edit users.');

            return;
        }

        $this->fillUserForm($id, false);
    }

    protected function fillUserForm(int $id, bool $viewMode): void
    {
        $user = User::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->favorite = 'users';
        $this->showUserForm = true;
        $this->showRoleForm = false;
        $this->viewMode = $viewMode;
        $this->editingUserId = $user->id;
        $this->selectedId = $user->id;
        $this->name = $user->name;
        $this->email = (string) $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->update_password = false;
        $this->role_id = $user->role_id;
        $this->site_id = $user->site_id;
        $this->department_id = $user->department_id;
        $this->job_title = (string) ($user->job_title ?? '');
        $this->is_active = (bool) $user->is_active;
        $this->user_permissions = is_array($user->permissions)
            ? AppFeatures::checkboxTokens($user->permissions)
            : $this->permissionsFromRole($user->role_id);
        $this->resetErrorBag();
    }

    public function cancelUserForm(): void
    {
        $this->showUserForm = false;
        $this->viewMode = false;
        $this->resetErrorBag();
    }

    public function saveUser(): void
    {
        if ($this->viewMode) {
            return;
        }

        if (! auth()->user()?->canAccessFeature('admin.users', 'edit')) {
            session()->flash('status', 'Your role cannot save users.');

            return;
        }

        $companyId = auth()->user()->company_id;
        $allowed = AppFeatures::permissionTokens();
        $needsPassword = ! $this->editingUserId || $this->update_password;
        $email = strtolower(trim($this->email));

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'role_id' => 'required|exists:roles,id',
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'job_title' => 'nullable|string|max:255',
            'password' => $needsPassword ? 'required|string|min:6|confirmed' : 'nullable',
            'user_permissions' => 'array',
            'user_permissions.*' => 'string|in:'.implode(',', $allowed),
        ], [
            'email.required' => 'User ID (email) is required.',
            'email.unique' => 'That email User ID is already in use.',
        ]);

        // Email is the login User ID; keep username in sync for legacy uniqueness.
        $data = [
            'company_id' => $companyId,
            'name' => $this->name,
            'username' => $email,
            'email' => $email,
            'role_id' => $this->role_id,
            'site_id' => $this->site_id ?: null,
            'department_id' => $this->department_id ?: null,
            'job_title' => $this->job_title !== '' ? $this->job_title : null,
            'is_active' => $this->is_active,
            'permissions' => AppFeatures::persistTokens(array_values(array_intersect($this->user_permissions, $allowed))),
        ];

        if ($needsPassword && $this->password !== '') {
            $data['password'] = $this->password;
        }

        if ($this->editingUserId) {
            $user = User::query()->where('company_id', $companyId)->whereKey($this->editingUserId)->firstOrFail();
            $user->update($data);
            $status = 'User updated.';
        } else {
            User::query()->create($data);
            $status = 'User created.';
        }

        session()->flash('status', $status);

        $this->showUserForm = false;
        $this->viewMode = false;
    }

    public function startNewRole(): void
    {
        if (! auth()->user()?->canAccessFeature('admin.users', 'edit')) {
            session()->flash('status', 'Your role cannot create roles.');

            return;
        }

        $this->favorite = 'roles';
        $this->showRoleForm = true;
        $this->showUserForm = false;
        $this->viewMode = false;
        $this->editingRoleId = null;
        $this->role_name = '';
        $this->role_label = '';
        $this->role_permissions = AppFeatures::defaultRolePermissionTokens();
        $this->resetErrorBag();
    }

    public function viewRole(int $id): void
    {
        $this->fillRoleForm($id, true);
    }

    public function editRole(int $id): void
    {
        if (! auth()->user()?->canAccessFeature('admin.users', 'edit')) {
            session()->flash('status', 'Your role cannot edit roles.');

            return;
        }

        $this->fillRoleForm($id, false);
    }

    protected function fillRoleForm(int $id, bool $viewMode): void
    {
        $role = Role::query()->findOrFail($id);
        $this->favorite = 'roles';
        $this->showRoleForm = true;
        $this->showUserForm = false;
        $this->viewMode = $viewMode;
        $this->editingRoleId = $role->id;
        $this->role_name = $role->name;
        $this->role_label = $role->label;
        $map = AppFeatures::expand($role->permissions);
        if ($role->name === 'admin') {
            $this->role_permissions = AppFeatures::permissionTokens();
        } elseif ($map === null) {
            $this->role_permissions = AppFeatures::defaultRolePermissionTokens();
        } else {
            $this->role_permissions = AppFeatures::checkboxTokens($role->permissions);
        }
        $this->resetErrorBag();
    }

    public function selectAllPermissions(): void
    {
        if ($this->viewMode) {
            return;
        }

        $this->role_permissions = AppFeatures::roleBulkPermissionTokens();
    }

    public function clearAllPermissions(): void
    {
        $this->role_permissions = [];
    }

    public function selectAllAction(string $action): void
    {
        if (! in_array($action, AppFeatures::ACTIONS, true)) {
            return;
        }

        $current = $this->role_permissions;
        foreach (AppFeatures::keys() as $feature) {
            if (AppFeatures::isRestrictedCapability($feature)) {
                continue;
            }
            $token = AppFeatures::token($feature, $action);
            if (! in_array($token, $current, true)) {
                $current[] = $token;
            }
        }
        $this->role_permissions = array_values($current);
    }

    public function clearAllAction(string $action): void
    {
        if (! in_array($action, AppFeatures::ACTIONS, true)) {
            return;
        }

        $this->role_permissions = array_values(array_filter(
            $this->role_permissions,
            fn (string $token) => ! str_ends_with($token, '.'.$action)
        ));
    }

    public function toggleFeatureRow(string $feature): void
    {
        if (! in_array($feature, AppFeatures::keys(), true)) {
            return;
        }

        $tokens = array_map(fn (string $a) => AppFeatures::token($feature, $a), AppFeatures::ACTIONS);
        $allOn = collect($tokens)->every(fn (string $t) => in_array($t, $this->role_permissions, true));

        if ($allOn) {
            $this->role_permissions = array_values(array_diff($this->role_permissions, $tokens));
        } else {
            $this->role_permissions = array_values(array_unique([...$this->role_permissions, ...$tokens]));
        }
    }

    public function selectMenuGroup(string $menu): void
    {
        $current = $this->role_permissions;
        foreach (AppFeatures::featuresForMenu($menu) as $feature) {
            if (AppFeatures::isRestrictedCapability($feature)) {
                continue;
            }
            foreach (AppFeatures::ACTIONS as $action) {
                $token = AppFeatures::token($feature, $action);
                if (! in_array($token, $current, true)) {
                    $current[] = $token;
                }
            }
        }
        $this->role_permissions = array_values($current);
    }

    public function clearMenuGroup(string $menu): void
    {
        $remove = [];
        foreach (AppFeatures::featuresForMenu($menu) as $feature) {
            foreach (AppFeatures::ACTIONS as $action) {
                $remove[] = AppFeatures::token($feature, $action);
            }
        }
        $this->role_permissions = array_values(array_diff($this->role_permissions, $remove));
    }

    public function cancelRoleForm(): void
    {
        $this->showRoleForm = false;
        $this->viewMode = false;
        $this->resetErrorBag();
    }

    public function saveRole(): void
    {
        if ($this->viewMode) {
            return;
        }

        if (! auth()->user()?->canAccessFeature('admin.users', 'edit')) {
            session()->flash('status', 'Your role cannot save roles.');

            return;
        }

        $allowed = AppFeatures::permissionTokens();

        $this->validate([
            'role_name' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('roles', 'name')->ignore($this->editingRoleId),
            ],
            'role_label' => 'required|string|max:255',
            'role_permissions' => 'array',
            'role_permissions.*' => 'string|in:'.implode(',', $allowed),
        ]);

        $payload = [
            'name' => strtolower($this->role_name),
            'label' => $this->role_label,
            'permissions' => AppFeatures::persistTokens(array_values(array_intersect($this->role_permissions, $allowed))),
        ];

        if ($this->editingRoleId) {
            Role::query()->whereKey($this->editingRoleId)->update($payload);
            session()->flash('status', 'Role updated.');
        } else {
            Role::query()->create($payload);
            session()->flash('status', 'Role created.');
        }

        $this->showRoleForm = false;
        $this->viewMode = false;
    }
}; ?>

<div class="desk-page {{ $isShowingForm ? 'entity-page' : '' }}">
    @unless ($isShowingForm)
        <x-favorite-list :favorites="$favorites" :active="$favorite" />
    @endunless

    <div class="desk-main {{ $isShowingForm ? 'entity-form item-form' : 'desk-main-rail-layout' }}{{ ($showRoleForm || $showUserForm) ? ' role-perm-form' : '' }}{{ $viewMode ? ' entity-form-readonly' : '' }}">
        <x-action-bar :title="$showUserForm ? ($viewMode ? 'View User' : ($editingUserId ? 'Edit User' : 'New User')) : ($showRoleForm ? ($viewMode ? 'View Role' : ($editingRoleId ? 'Edit Role' : 'New Role')) : 'Action')" />

        @if (session('status'))
            <div class="desk-flash" role="status">{{ session('status') }}</div>
        @endif

        @if ($showUserForm)
            <form wire:submit="saveUser" class="contents">
                <div class="entity-body">
                    <div class="entity-header">
                        <div class="so-form-row so-form-row-pair entity-header-row">
                            <label class="so-form-lbl" for="u-name">Name</label>
                            <input id="u-name" wire:model="name" class="so-input" />
                            <span class="so-form-lbl">Status</span>
                            <span @class(['desk-pill', 'desk-pill-invoiced' => $is_active, 'desk-pill-muted' => ! $is_active])>
                                {{ $is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>

                    <div class="sc-general-grid role-perm-layout">
                        <div class="inv-card">
                            <div class="inv-card-title">Account</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-name-full">Full Name</label>
                                <input id="u-name-full" wire:model="name" class="so-input" />
                            </div>
                            @error('name') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-email">User ID (Email)</label>
                                <input id="u-email" type="email" wire:model="email" class="so-input" autocomplete="username" placeholder="name@company.com" />
                            </div>
                            @error('email') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <p class="item-hint" style="padding:0 0 0.35rem">Email is the login User ID — not a separate username.</p>

                            @unless ($viewMode)
                                @if (! $editingUserId)
                                    <div class="so-form-row so-form-row-side sc-field">
                                        <label class="so-form-lbl" for="u-pass">Password</label>
                                        <input id="u-pass" type="password" wire:model="password" class="so-input" autocomplete="new-password" />
                                    </div>
                                    @error('password') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                                    <div class="so-form-row so-form-row-side sc-field">
                                        <label class="so-form-lbl" for="u-pass2">Confirm Password</label>
                                        <input id="u-pass2" type="password" wire:model="password_confirmation" class="so-input" autocomplete="new-password" />
                                    </div>
                                @else
                                    <div class="so-form-row so-form-row-side sc-field">
                                        <span class="so-form-lbl"></span>
                                        <label class="entity-check">
                                            <input type="checkbox" wire:model.live="update_password" /> Update password
                                        </label>
                                    </div>
                                    @if ($update_password)
                                        <div class="so-form-row so-form-row-side sc-field">
                                            <label class="so-form-lbl" for="u-pass">New Password</label>
                                            <input id="u-pass" type="password" wire:model="password" class="so-input" autocomplete="new-password" />
                                        </div>
                                        @error('password') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                                        <div class="so-form-row so-form-row-side sc-field">
                                            <label class="so-form-lbl" for="u-pass2">Confirm Password</label>
                                            <input id="u-pass2" type="password" wire:model="password_confirmation" class="so-input" autocomplete="new-password" />
                                        </div>
                                    @endif
                                @endif
                            @endunless
                        </div>

                        <div class="inv-card">
                            <div class="inv-card-title">Access &amp; Organization</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl so-field-req" for="u-role">Role</label>
                                <select id="u-role" wire:model.live="role_id" class="so-input">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('role_id') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-dept">Department</label>
                                <select id="u-dept" wire:model="department_id" class="so-input">
                                    <option value="">— Select department —</option>
                                    @forelse ($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name }}@if($dept->code) ({{ $dept->code }})@endif</option>
                                    @empty
                                        <option value="" disabled>No departments — add in Lookups</option>
                                    @endforelse
                                </select>
                            </div>
                            @error('department_id') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-job">Job Title</label>
                                <input id="u-job" wire:model="job_title" class="so-input" placeholder="e.g. Sales Representative" />
                            </div>
                            @error('job_title') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-site">Site</label>
                                <select id="u-site" wire:model="site_id" class="so-input">
                                    <option value="">—</option>
                                    @foreach ($sites as $site)
                                        <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <span class="so-form-lbl"></span>
                                <label class="entity-check">
                                    <input type="checkbox" wire:model="is_active" /> Active (required for login)
                                </label>
                            </div>
                        </div>

                        <div class="inv-card role-perm-wrap" style="grid-column:1 / -1">
                            <div class="inv-card-title" style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap">
                                <span>User feature permissions</span>
                                <span class="flex gap-1 flex-wrap">
                                    <button type="button" wire:click="applyRolePermissionsToUser" class="desk-btn desk-btn-sm" title="Reset to selected role defaults">Reset from role</button>
                                    <button type="button" wire:click="selectAllUserPermissions" class="desk-btn desk-btn-sm">All</button>
                                    <button type="button" wire:click="clearAllUserPermissions" class="desk-btn desk-btn-sm">None</button>
                                </span>
                            </div>
                            <p class="item-hint" style="padding:0 0 0.65rem">
                                @if ($viewMode)
                                    Permissions assigned to this user (read-only).
                                @else
                                    Loaded from role
                                    <strong>{{ $selectedRoleLabel ?? '—' }}</strong>.
                                    Change features for this user only — the role itself is not updated.
                                    Change Order Price stays off unless you turn on <strong>Edit</strong> for this user.
                                    POS AI Chat stays off unless you turn on <strong>View</strong> — that user can chat only, not change AI settings.
                                    Lookups and Team Chat stay on by default. Uncheck <strong>View</strong> to hide them for this user.
                                    Create channels &amp; add members stays off unless you turn on <strong>Edit</strong>.
                                @endif
                            </p>

                            <div class="role-menu-grid">
                                @foreach ($menuCards as $menu => $submenus)
                                    @php $seenFeatures = []; @endphp
                                    <section class="role-menu-card">
                                        <header class="role-menu-card-head">
                                            <div class="role-menu-card-title">
                                                <span class="role-menu-card-badge">{{ $menu }}</span>
                                                <span class="role-menu-card-count">{{ count($submenus) }} submenu{{ count($submenus) === 1 ? '' : 's' }}</span>
                                            </div>
                                            <div class="role-menu-card-tools">
                                                <button type="button" wire:click="selectUserMenuGroup('{{ $menu }}')" class="desk-btn desk-btn-sm">All</button>
                                                <button type="button" wire:click="clearUserMenuGroup('{{ $menu }}')" class="desk-btn desk-btn-sm">None</button>
                                            </div>
                                        </header>
                                        <div class="role-menu-col-heads" aria-hidden="true">
                                            <span class="role-menu-col-submenu">Submenu</span>
                                            @foreach (['View', 'Edit', 'Delete'] as $actionLabel)
                                                <span class="role-menu-col-action">{{ $actionLabel }}</span>
                                            @endforeach
                                        </div>
                                        <ul class="role-menu-sublist">
                                            @foreach ($submenus as $row)
                                                @php
                                                    $feature = $row['feature'];
                                                    $label = $row['label'];
                                                    $isRepeat = isset($seenFeatures[$feature]);
                                                    $seenFeatures[$feature] = $seenFeatures[$feature] ?? $label;
                                                    $primaryLabel = $seenFeatures[$feature];
                                                @endphp
                                                <li @class(['role-menu-subrow', 'role-menu-subrow-linked' => $isRepeat])>
                                                    <button
                                                        type="button"
                                                        class="role-menu-sublabel"
                                                        wire:click="toggleUserFeatureRow('{{ $feature }}')"
                                                        title="{{ $isRepeat ? 'Uses same permissions as '.$primaryLabel : 'Toggle for '.$label }}"
                                                    >
                                                        {{ $label }}
                                                        @if ($isRepeat)
                                                            <span class="role-menu-same">same as {{ $primaryLabel }}</span>
                                                        @endif
                                                    </button>
                                                    @if ($isRepeat)
                                                        <span class="role-menu-dash" aria-hidden="true">·</span>
                                                        <span class="role-menu-dash" aria-hidden="true">·</span>
                                                        <span class="role-menu-dash" aria-hidden="true">·</span>
                                                    @else
                                                        @foreach ($permActions as $action)
                                                            <label class="role-perm-check" title="{{ $label }} — {{ ucfirst($action) }}">
                                                                <input
                                                                    type="checkbox"
                                                                    value="{{ $feature }}.{{ $action }}"
                                                                    wire:model="user_permissions"
                                                                />
                                                                <span class="sr-only">{{ $label }} {{ $action }}</span>
                                                            </label>
                                                        @endforeach
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </section>
                                @endforeach
                            </div>
                            @error('user_permissions') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            @error('user_permissions.*') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="entity-footer">
                    <div class="entity-tabs"><span class="entity-tab is-active">{{ $viewMode ? 'View' : 'User' }}</span></div>
                    <div class="entity-footer-actions">
                        <button type="button" wire:click="cancelUserForm" class="desk-btn">{{ $viewMode ? 'Close' : 'Cancel' }}</button>
                        @if ($viewMode)
                            @if (auth()->user()?->canAccessFeature('admin.users', 'edit'))
                                <button type="button" wire:click="editUser({{ $editingUserId }})" class="desk-btn desk-btn-primary">Edit User</button>
                            @endif
                        @else
                            <button type="submit" class="desk-btn desk-btn-primary">Save User</button>
                        @endif
                    </div>
                </div>
            </form>

        @elseif ($showRoleForm)
            <form wire:submit="saveRole" class="contents">
                <div class="entity-body">
                    <div class="entity-header">
                        <div class="so-form-row so-form-row-pair entity-header-row">
                            <label class="so-form-lbl" for="r-label-head">Role</label>
                            <input id="r-label-head" wire:model="role_label" class="so-input" placeholder="Display label" />
                        </div>
                    </div>
                    <div class="sc-general-grid role-perm-layout">
                        <div class="inv-card">
                            <div class="inv-card-title">Role details</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="r-name">Code</label>
                                <input id="r-name" wire:model="role_name" class="so-input font-mono" @disabled($editingRoleId) placeholder="e.g. sales_rep" />
                            </div>
                            @error('role_name') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="r-label">Label</label>
                                <input id="r-label" wire:model="role_label" class="so-input" />
                            </div>
                            @error('role_label') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            @if ($role_name === 'admin' || strtolower($role_name) === 'admin')
                                <p class="item-hint" style="padding:0.5rem 0 0">Admin always has full access to every feature.</p>
                            @endif
                        </div>
                        <div class="inv-card role-perm-wrap">
                            <div class="inv-card-title" style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap">
                                <span>Menu permissions</span>
                                <span class="flex gap-1 flex-wrap">
                                    <button type="button" wire:click="selectAllPermissions" class="desk-btn desk-btn-sm">All menus</button>
                                    <button type="button" wire:click="clearAllPermissions" class="desk-btn desk-btn-sm">None</button>
                                </span>
                            </div>
                            <p class="item-hint" style="padding:0 0 0.65rem">Each card is a top menu. Under it, every submenu has View / Edit / Delete. File items (Company Settings, Overselling Settings, Email, Users &amp; Roles, POS AI Settings) stay off by default. POS AI Chat is also off by default — turn on <strong>View</strong> for a user so they can use the chat widget only (they cannot change AI settings). Change Order Price stays off unless you enable Edit for that user. Team Chat and Lookups are on by default — uncheck <strong>View</strong> to hide them. Create channels &amp; add members stays off unless you enable <strong>Edit</strong>.</p>

                            <div class="role-menu-grid">
                                @foreach ($menuCards as $menu => $submenus)
                                    @php
                                        $seenFeatures = [];
                                    @endphp
                                    <section class="role-menu-card">
                                        <header class="role-menu-card-head">
                                            <div class="role-menu-card-title">
                                                <span class="role-menu-card-badge">{{ $menu }}</span>
                                                <span class="role-menu-card-count">{{ count($submenus) }} submenu{{ count($submenus) === 1 ? '' : 's' }}</span>
                                            </div>
                                            <div class="role-menu-card-tools">
                                                <button type="button" wire:click="selectMenuGroup('{{ $menu }}')" class="desk-btn desk-btn-sm">All</button>
                                                <button type="button" wire:click="clearMenuGroup('{{ $menu }}')" class="desk-btn desk-btn-sm">None</button>
                                            </div>
                                        </header>

                                        <div class="role-menu-col-heads" aria-hidden="true">
                                            <span class="role-menu-col-submenu">Submenu</span>
                                            @foreach (['View', 'Edit', 'Delete'] as $actionLabel)
                                                <span class="role-menu-col-action">{{ $actionLabel }}</span>
                                            @endforeach
                                        </div>

                                        <ul class="role-menu-sublist">
                                            @foreach ($submenus as $row)
                                                @php
                                                    $feature = $row['feature'];
                                                    $label = $row['label'];
                                                    $isRepeat = isset($seenFeatures[$feature]);
                                                    $seenFeatures[$feature] = $seenFeatures[$feature] ?? $label;
                                                    $primaryLabel = $seenFeatures[$feature];
                                                @endphp
                                                <li @class(['role-menu-subrow', 'role-menu-subrow-linked' => $isRepeat])>
                                                    <button
                                                        type="button"
                                                        class="role-menu-sublabel"
                                                        wire:click="toggleFeatureRow('{{ $feature }}')"
                                                        title="{{ $isRepeat ? 'Uses same permissions as '.$primaryLabel : 'Toggle View / Edit / Delete for '.$label }}"
                                                    >
                                                        {{ $label }}
                                                        @if ($isRepeat)
                                                            <span class="role-menu-same">same as {{ $primaryLabel }}</span>
                                                        @endif
                                                    </button>
                                                    @if ($isRepeat)
                                                        <span class="role-menu-dash" aria-hidden="true">·</span>
                                                        <span class="role-menu-dash" aria-hidden="true">·</span>
                                                        <span class="role-menu-dash" aria-hidden="true">·</span>
                                                    @else
                                                        @foreach ($permActions as $action)
                                                            <label class="role-perm-check" title="{{ $label }} — {{ ucfirst($action) }}">
                                                                <input
                                                                    type="checkbox"
                                                                    value="{{ $feature }}.{{ $action }}"
                                                                    wire:model="role_permissions"
                                                                />
                                                                <span class="sr-only">{{ $label }} {{ $action }}</span>
                                                            </label>
                                                        @endforeach
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </section>
                                @endforeach
                            </div>

                            @error('role_permissions') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            @error('role_permissions.*') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <div class="entity-footer">
                    <div class="entity-tabs"><span class="entity-tab is-active">{{ $viewMode ? 'View' : 'Role' }}</span></div>
                    <div class="entity-footer-actions">
                        <button type="button" wire:click="cancelRoleForm" class="desk-btn">{{ $viewMode ? 'Close' : 'Cancel' }}</button>
                        @if ($viewMode)
                            @if (auth()->user()?->canAccessFeature('admin.users', 'edit'))
                                <button type="button" wire:click="editRole({{ $editingRoleId }})" class="desk-btn desk-btn-primary">Edit Role</button>
                            @endif
                        @else
                            <button type="submit" class="desk-btn desk-btn-primary">Save Role</button>
                        @endif
                    </div>
                </div>
            </form>

        @elseif ($favorite === 'users')
            <div class="desk-main-split">
                <div class="desk-main-body">
                    <div class="desk-toolbar orders-toolbar">
                        <label class="desk-toolbar-label" for="users-search">Search Users:</label>
                        <input
                            id="users-search"
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Name, email, role, department…"
                            class="desk-search orders-search-input"
                            aria-label="Search Users"
                        />
                        <div class="orders-toolbar-right">
                            <button type="button" wire:click="newSearch" class="desk-btn" title="Reset search and filters">
                                <svg class="orders-toolbar-ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                                    <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                                    <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                                </svg>
                                New Search
                            </button>
                            <select
                                id="users-status-filter"
                                wire:model.live="statusFilter"
                                class="desk-select orders-status-select"
                                aria-label="Active filter"
                            >
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button type="button" wire:click="clearSearch" class="so-icon-btn" title="Clear search" aria-label="Clear search">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path d="M4 4l8 8M12 4l-8 8"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="desk-titlebar">
                        <h2 class="desk-title">{{ $listTitle }}</h2>
                        <span class="desk-title-meta">{{ number_format($listShown) }}{{ $listHasMore ? '+' : '' }} records</span>
                    </div>

                    <x-desk-scroll-grid :has-more="$listHasMore" class="{{ $compactView ? 'is-compact' : '' }}">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:2rem"></th>
                                    <x-desk-sort-th field="user_name" label="Name" />
                                    <x-desk-sort-th field="email" label="User ID (Email)" />
                                    <x-desk-sort-th field="role" label="Role" />
                                    <x-desk-sort-th field="department" label="Department" />
                                    <x-desk-sort-th field="job_title" label="Job Title" />
                                    <x-desk-sort-th field="site" label="Site" />
                                    <x-desk-sort-th field="is_active" label="Active" align="center" />
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr
                                        wire:click="selectRow({{ $user->id }})"
                                        wire:dblclick="editUser({{ $user->id }})"
                                        @class(['is-selected' => $selectedId === $user->id, 'cursor-pointer'])
                                    >
                                        <td class="text-center" wire:click.stop>
                                            <input
                                                type="radio"
                                                name="user_select"
                                                value="{{ $user->id }}"
                                                @checked($selectedId === $user->id)
                                                wire:click="selectRow({{ $user->id }})"
                                                aria-label="Select user {{ $user->email }}"
                                            />
                                        </td>
                                        <td>{{ $user->name }}</td>
                                        <td class="desk-num">
                                            @if ($user->email)
                                                <a href="mailto:{{ $user->email }}" wire:click.stop>{{ $user->email }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $user->role?->label ?: '—' }}</td>
                                        <td>{{ $user->department?->name ?: '—' }}</td>
                                        <td>{{ $user->job_title ?: '—' }}</td>
                                        <td class="desk-num">{{ $user->site?->code ?: '—' }}</td>
                                        <td class="text-center" wire:click.stop>
                                            <button
                                                type="button"
                                                wire:click="toggleActive({{ $user->id }})"
                                                @class([
                                                    'desk-pill',
                                                    'desk-pill-invoiced' => $user->is_active,
                                                    'desk-pill-muted' => ! $user->is_active,
                                                ])
                                                title="{{ $user->is_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}"
                                            >{{ $user->is_active ? 'Yes' : 'No' }}</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="8">No users found. Use the <strong>+</strong> button to create one.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-desk-scroll-grid>

                    <x-record-count :count="$listShown">
                        <button type="button" wire:click="startNewUser" class="desk-btn desk-btn-primary">New User</button>
                        <x-desk-load-more :has-more="$listHasMore" />
                    </x-record-count>
                </div>

                <aside class="desk-rail" aria-label="User actions">
                    <button type="button" wire:click="toggleCompactView" class="desk-rail-btn" title="{{ $compactView ? 'Normal view' : 'Compact view' }}" aria-label="Toggle list view">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <rect x="2" y="2" width="5" height="5" rx="0.5"/>
                            <rect x="9" y="2" width="5" height="5" rx="0.5"/>
                            <rect x="2" y="9" width="5" height="5" rx="0.5"/>
                            <rect x="9" y="9" width="5" height="5" rx="0.5"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="viewSelected" class="desk-rail-btn" title="View selected" aria-label="View selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                            <circle cx="8" cy="8" r="2"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit selected" aria-label="Edit selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        wire:click="deleteSelected"
                        wire:confirm="Delete the selected user? This cannot be undone."
                        class="desk-rail-btn desk-rail-btn-danger"
                        title="Delete selected"
                        aria-label="Delete selected"
                        @disabled(! $selectedId)
                    >
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <rect x="3.5" y="3.5" width="9" height="9" rx="1"/>
                            <path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke-width="1.6"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print selected" aria-label="Print selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                            <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M13 8a5 5 0 11-1.2-3.3"/>
                            <path d="M13 3v3h-3"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="startNewUser" class="desk-rail-btn desk-rail-btn-primary" title="New User" aria-label="New User">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M8 3v10M3 8h10"/>
                        </svg>
                    </button>
                </aside>
            </div>

        @else
            <div class="desk-main-split">
                <div class="desk-main-body">
                    <div class="desk-toolbar orders-toolbar">
                        <label class="desk-toolbar-label" for="roles-search">Search Roles:</label>
                        <input
                            id="roles-search"
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Code, label…"
                            class="desk-search orders-search-input"
                            aria-label="Search Roles"
                        />
                        <div class="orders-toolbar-right">
                            <button type="button" wire:click="newSearch" class="desk-btn" title="Reset search">
                                <svg class="orders-toolbar-ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                                    <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                                    <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
                                </svg>
                                New Search
                            </button>
                            <button type="button" wire:click="clearSearch" class="so-icon-btn" title="Clear search" aria-label="Clear search">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path d="M4 4l8 8M12 4l-8 8"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="desk-titlebar">
                        <h2 class="desk-title">{{ $listTitle }}</h2>
                        <span class="desk-title-meta">{{ number_format($roles->count()) }} records</span>
                    </div>

                    <div class="desk-grid {{ $compactView ? 'is-compact' : '' }}">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:2rem"></th>
                                    <x-desk-sort-th field="role_code" label="Code" />
                                    <x-desk-sort-th field="role_label" label="Label" />
                                    <x-desk-sort-th field="users_count" label="Users" align="money" />
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($roles as $role)
                                    <tr
                                        wire:click="selectRow({{ $role->id }})"
                                        wire:dblclick="editRole({{ $role->id }})"
                                        @class(['is-selected' => $selectedId === $role->id, 'cursor-pointer'])
                                    >
                                        <td class="text-center" wire:click.stop>
                                            <input
                                                type="radio"
                                                name="role_select"
                                                value="{{ $role->id }}"
                                                @checked($selectedId === $role->id)
                                                wire:click="selectRow({{ $role->id }})"
                                                aria-label="Select role {{ $role->name }}"
                                            />
                                        </td>
                                        <td class="desk-num">{{ $role->name }}</td>
                                        <td>{{ $role->label }}</td>
                                        <td class="desk-money">{{ $role->users_count }}</td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="4">No roles found. Use the <strong>+</strong> button to create one.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <x-record-count :count="$roles->count()">
                        <button type="button" wire:click="startNewRole" class="desk-btn desk-btn-primary">New Role</button>
                    </x-record-count>
                </div>

                <aside class="desk-rail" aria-label="Role actions">
                    <button type="button" wire:click="toggleCompactView" class="desk-rail-btn" title="{{ $compactView ? 'Normal view' : 'Compact view' }}" aria-label="Toggle list view">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <rect x="2" y="2" width="5" height="5" rx="0.5"/>
                            <rect x="9" y="2" width="5" height="5" rx="0.5"/>
                            <rect x="2" y="9" width="5" height="5" rx="0.5"/>
                            <rect x="9" y="9" width="5" height="5" rx="0.5"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="viewSelected" class="desk-rail-btn" title="View selected" aria-label="View selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                            <circle cx="8" cy="8" r="2"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="editSelected" class="desk-rail-btn" title="Edit selected" aria-label="Edit selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        wire:click="deleteSelected"
                        wire:confirm="Delete the selected role? This cannot be undone."
                        class="desk-rail-btn desk-rail-btn-danger"
                        title="Delete selected"
                        aria-label="Delete selected"
                        @disabled(! $selectedId)
                    >
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <rect x="3.5" y="3.5" width="9" height="9" rx="1"/>
                            <path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke-width="1.6"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print selected" aria-label="Print selected" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                            <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M13 8a5 5 0 11-1.2-3.3"/>
                            <path d="M13 3v3h-3"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="startNewRole" class="desk-rail-btn desk-rail-btn-primary" title="New Role" aria-label="New Role">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M8 3v10M3 8h10"/>
                        </svg>
                    </button>
                </aside>
            </div>
        @endif
    </div>
</div>

@script
<script>
    $wire.on('print-user', () => {
        setTimeout(() => { try { window.print(); } catch (e) {} }, 400);
    });
</script>
@endscript
