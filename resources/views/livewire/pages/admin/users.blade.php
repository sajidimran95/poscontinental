<?php

use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Users & Roles')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $favorite = 'users';

    /** '' | active | inactive */
    public string $statusFilter = '';

    public ?int $selectedId = null;

    public bool $compactView = false;

    public bool $showUserForm = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public ?int $role_id = null;

    public ?int $site_id = null;

    public bool $is_active = true;

    public bool $showRoleForm = false;

    public ?int $editingRoleId = null;

    public string $role_name = '';

    public string $role_label = '';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $usersQuery = User::query()
            ->with(['role', 'site'])
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('role', fn ($r) => $r->where('label', 'like', $term)->orWhere('name', 'like', $term))
                        ->orWhereHas('site', fn ($s) => $s->where('code', 'like', $term)->orWhere('name', 'like', $term));
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name');

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
            })
            ->orderBy('label');

        return [
            'users' => $usersQuery->paginate(40),
            'roles' => $rolesQuery->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'favorites' => [
                'users' => 'Users',
                'roles' => 'Roles',
            ],
            'listTitle' => $listTitle,
            'isShowingForm' => $this->showUserForm || $this->showRoleForm,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->showUserForm = false;
        $this->showRoleForm = false;
        $this->statusFilter = '';
        $this->search = '';
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function newSearch(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->selectedId = null;
        $this->resetPage();
    }

    public function toggleCompactView(): void
    {
        $this->compactView = ! $this->compactView;
    }

    public function refreshList(): void
    {
        $this->resetPage();
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
        $this->favorite = 'users';
        $this->showUserForm = true;
        $this->showRoleForm = false;
        $this->editingUserId = null;
        $this->name = '';
        $this->username = '';
        $this->email = '';
        $this->password = '';
        $this->role_id = Role::query()->where('name', 'sales_rep')->value('id')
            ?? Role::query()->orderBy('id')->value('id');
        $this->site_id = auth()->user()->site_id;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function editUser(int $id): void
    {
        $user = User::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->favorite = 'users';
        $this->showUserForm = true;
        $this->showRoleForm = false;
        $this->editingUserId = $user->id;
        $this->selectedId = $user->id;
        $this->name = $user->name;
        $this->username = (string) $user->username;
        $this->email = (string) $user->email;
        $this->password = '';
        $this->role_id = $user->role_id;
        $this->site_id = $user->site_id;
        $this->is_active = (bool) $user->is_active;
        $this->resetErrorBag();
    }

    public function cancelUserForm(): void
    {
        $this->showUserForm = false;
        $this->resetErrorBag();
    }

    public function saveUser(): void
    {
        $companyId = auth()->user()->company_id;
        $this->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:64',
                Rule::unique('users', 'username')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($this->editingUserId),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'role_id' => 'required|exists:roles,id',
            'site_id' => 'nullable|exists:sites,id',
            'password' => $this->editingUserId ? 'nullable|string|min:6' : 'required|string|min:6',
        ]);

        $data = [
            'company_id' => $companyId,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role_id' => $this->role_id,
            'site_id' => $this->site_id ?: null,
            'is_active' => $this->is_active,
        ];
        if ($this->password !== '') {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingUserId) {
            User::query()->where('company_id', $companyId)->whereKey($this->editingUserId)->update($data);
            session()->flash('status', 'User updated.');
        } else {
            User::query()->create($data);
            session()->flash('status', 'User created.');
        }

        $this->showUserForm = false;
    }

    public function startNewRole(): void
    {
        $this->favorite = 'roles';
        $this->showRoleForm = true;
        $this->showUserForm = false;
        $this->editingRoleId = null;
        $this->role_name = '';
        $this->role_label = '';
        $this->resetErrorBag();
    }

    public function editRole(int $id): void
    {
        $role = Role::query()->findOrFail($id);
        $this->favorite = 'roles';
        $this->showRoleForm = true;
        $this->showUserForm = false;
        $this->editingRoleId = $role->id;
        $this->role_name = $role->name;
        $this->role_label = $role->label;
        $this->resetErrorBag();
    }

    public function cancelRoleForm(): void
    {
        $this->showRoleForm = false;
        $this->resetErrorBag();
    }

    public function saveRole(): void
    {
        $this->validate([
            'role_name' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('roles', 'name')->ignore($this->editingRoleId),
            ],
            'role_label' => 'required|string|max:255',
        ]);

        $payload = [
            'name' => strtolower($this->role_name),
            'label' => $this->role_label,
        ];

        if ($this->editingRoleId) {
            Role::query()->whereKey($this->editingRoleId)->update($payload);
            session()->flash('status', 'Role updated.');
        } else {
            Role::query()->create($payload);
            session()->flash('status', 'Role created.');
        }

        $this->showRoleForm = false;
    }
}; ?>

<div class="desk-page {{ $isShowingForm ? 'entity-page' : '' }}">
    @unless ($isShowingForm)
        <x-favorite-list :favorites="$favorites" :active="$favorite" />
    @endunless

    <div class="desk-main {{ $isShowingForm ? 'entity-form item-form' : 'desk-main-rail-layout' }}">
        <x-action-bar :title="$showUserForm ? ($editingUserId ? 'Edit User' : 'New User') : ($showRoleForm ? ($editingRoleId ? 'Edit Role' : 'New Role') : 'Action')" />

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

                    <div class="sc-general-grid">
                        <div class="inv-card">
                            <div class="inv-card-title">Account</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-name-full">Full Name</label>
                                <input id="u-name-full" wire:model="name" class="so-input" />
                            </div>
                            @error('name') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-user">Username</label>
                                <input id="u-user" wire:model="username" class="so-input font-mono" />
                            </div>
                            @error('username') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-email">Email Address</label>
                                <input id="u-email" type="email" wire:model="email" class="so-input" />
                            </div>
                            @error('email') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-pass">Password</label>
                                <input id="u-pass" type="password" wire:model="password" class="so-input" placeholder="{{ $editingUserId ? 'Leave blank to keep' : 'Required' }}" />
                            </div>
                            @error('password') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div class="inv-card">
                            <div class="inv-card-title">Access</div>
                            <div class="so-form-row so-form-row-side sc-field">
                                <label class="so-form-lbl" for="u-role">Role</label>
                                <select id="u-role" wire:model="role_id" class="so-input">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('role_id') <p class="text-xs text-red-700 px-1" role="alert">{{ $message }}</p> @enderror
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
                                    <input type="checkbox" wire:model="is_active" /> Active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="entity-footer">
                    <div class="entity-tabs"><span class="entity-tab is-active">User</span></div>
                    <div class="entity-footer-actions">
                        <button type="button" wire:click="cancelUserForm" class="desk-btn">Cancel</button>
                        <button type="submit" class="desk-btn desk-btn-primary">Save User</button>
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
                    <div class="inv-card" style="max-width:28rem;margin:1rem">
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
                    </div>
                </div>
                <div class="entity-footer">
                    <div class="entity-tabs"><span class="entity-tab is-active">Role</span></div>
                    <div class="entity-footer-actions">
                        <button type="button" wire:click="cancelRoleForm" class="desk-btn">Cancel</button>
                        <button type="submit" class="desk-btn desk-btn-primary">Save Role</button>
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
                            placeholder="Name, username, email, role…"
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
                        <span class="desk-title-meta">{{ number_format($users->total()) }} records</span>
                    </div>

                    <div class="desk-grid {{ $compactView ? 'is-compact' : '' }}">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:2rem"></th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email Address</th>
                                    <th>Role</th>
                                    <th>Site</th>
                                    <th class="text-center">Active</th>
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
                                                aria-label="Select user {{ $user->username }}"
                                            />
                                        </td>
                                        <td>{{ $user->name }}</td>
                                        <td class="desk-num">{{ $user->username }}</td>
                                        <td>
                                            @if ($user->email)
                                                <a href="mailto:{{ $user->email }}" wire:click.stop>{{ $user->email }}</a>
                                            @endif
                                        </td>
                                        <td>{{ $user->role?->label ?: '—' }}</td>
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
                                        <td colspan="7">No users found. Use the <strong>+</strong> button to create one.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <x-record-count :count="$users->total()">
                        <button type="button" wire:click="startNewUser" class="desk-btn desk-btn-primary">New User</button>
                        {{ $users->links() }}
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
                    <button type="button" wire:click="newSearch" class="desk-rail-btn" title="New Search (clear filters)" aria-label="New Search">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                            <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                            <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
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
                                    <th>Code</th>
                                    <th>Label</th>
                                    <th class="desk-money">Users</th>
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
                    <button type="button" wire:click="newSearch" class="desk-rail-btn" title="New Search (clear filters)" aria-label="New Search">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.45" aria-hidden="true">
                            <path d="M10.8 2.8l2.4 2.4L6.5 12H4v-2.5L10.8 2.8z"/>
                            <path d="M3.2 13.2l9.6-9.6" stroke-width="1.7"/>
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
