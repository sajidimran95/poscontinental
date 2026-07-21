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

    public string $tab = 'users';

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

        return [
            'users' => User::query()
                ->with(['role', 'site'])
                ->where('company_id', $companyId)
                ->when($this->search !== '', function ($q) {
                    $term = '%'.$this->search.'%';
                    $q->where(fn ($i) => $i->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('email', 'like', $term));
                })
                ->orderBy('name')
                ->paginate(40),
            'roles' => Role::query()->withCount('users')->orderBy('label')->get(),
            'sites' => Site::query()->where('company_id', $companyId)->orderBy('code')->get(),
            'favorites' => [
                'users' => 'Users',
                'roles' => 'Roles',
            ],
        ];
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->showUserForm = false;
        $this->showRoleForm = false;
    }

    public function startNewUser(): void
    {
        $this->showUserForm = true;
        $this->editingUserId = null;
        $this->name = '';
        $this->username = '';
        $this->email = '';
        $this->password = '';
        $this->role_id = Role::query()->where('name', 'sales_rep')->value('id');
        $this->site_id = auth()->user()->site_id;
        $this->is_active = true;
    }

    public function editUser(int $id): void
    {
        $user = User::query()->where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->showUserForm = true;
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->username = (string) $user->username;
        $this->email = (string) $user->email;
        $this->password = '';
        $this->role_id = $user->role_id;
        $this->site_id = $user->site_id;
        $this->is_active = (bool) $user->is_active;
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
            'site_id' => $this->site_id,
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
        $this->showRoleForm = true;
        $this->editingRoleId = null;
        $this->role_name = '';
        $this->role_label = '';
    }

    public function editRole(int $id): void
    {
        $role = Role::query()->findOrFail($id);
        $this->showRoleForm = true;
        $this->editingRoleId = $role->id;
        $this->role_name = $role->name;
        $this->role_label = $role->label;
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

<div class="flex gap-2 h-full">
    <aside class="w-44 shrink-0 border border-slate-400 bg-slate-50" aria-label="Admin sections">
        <div class="bg-slate-200 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Admin</div>
        <ul class="text-sm">
            @foreach (['users' => 'Users', 'roles' => 'Roles'] as $key => $label)
                <li>
                    <button
                        type="button"
                        wire:click="$set('tab', '{{ $key }}')"
                        aria-current="{{ $tab === $key ? 'true' : 'false' }}"
                        @class([
                            'w-full text-left px-2 py-1.5 border-b border-slate-200',
                            'bg-sky-100 font-medium text-sky-900' => $tab === $key,
                            'hover:bg-slate-100' => $tab !== $key,
                        ])
                    >{{ $label }}</button>
                </li>
            @endforeach
        </ul>
    </aside>

    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        @if (session('status'))
            <div class="mx-2 mt-1 border border-sky-400 bg-sky-50 px-2 py-1 text-xs" role="status">{{ session('status') }}</div>
        @endif

        @if ($tab === 'users')
            @if ($showUserForm)
                <form wire:submit="saveUser" class="p-3 space-y-2 max-w-xl">
                    <div class="chief-field"><label for="u-name">Name</label><input id="u-name" wire:model="name" class="chief-input w-64" /></div>
                    <div class="chief-field"><label for="u-user">Username</label><input id="u-user" wire:model="username" class="chief-input w-40 font-mono" /></div>
                    <div class="chief-field"><label for="u-email">Email</label><input id="u-email" type="email" wire:model="email" class="chief-input w-64" /></div>
                    <div class="chief-field"><label for="u-pass">Password</label><input id="u-pass" type="password" wire:model="password" class="chief-input w-48" placeholder="{{ $editingUserId ? 'Leave blank to keep' : '' }}" /></div>
                    <div class="chief-field">
                        <label for="u-role">Role</label>
                        <select id="u-role" wire:model="role_id" class="chief-input w-48">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="chief-field">
                        <label for="u-site">Site</label>
                        <select id="u-site" wire:model="site_id" class="chief-input w-40">
                            <option value="">—</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="chief-field">
                        <label for="u-active">Active</label>
                        <input id="u-active" type="checkbox" wire:model="is_active" class="chief-input w-auto" />
                    </div>
                    <div class="flex gap-2 ms-[9.5rem]">
                        <button type="button" wire:click="$set('showUserForm', false)" class="chief-btn">Cancel</button>
                        <button type="submit" class="chief-btn-primary">Save</button>
                    </div>
                </form>
            @else
                <x-list-chrome label="Search Users:" model="search" />
                <div class="px-2 py-1 font-semibold border-b border-slate-300">Users</div>
                <div class="chief-grid flex-1 overflow-auto">
                    <table>
                        <thead>
                            <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Site</th><th>Active</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td class="font-mono">{{ $user->username }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->role?->label }}</td>
                                    <td>{{ $user->site?->code }}</td>
                                    <td>{{ $user->is_active ? 'Yes' : 'No' }}</td>
                                    <td><button type="button" wire:click="editUser({{ $user->id }})" class="text-sky-700 underline text-xs">Edit</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-2 py-6 text-slate-500">No users.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-record-count :count="$users->total()">
                    <button type="button" wire:click="startNewUser" class="chief-btn-primary">New User</button>
                    {{ $users->links() }}
                </x-record-count>
            @endif
        @else
            @if ($showRoleForm)
                <form wire:submit="saveRole" class="p-3 space-y-2 max-w-md">
                    <div class="chief-field"><label for="r-name">Code</label><input id="r-name" wire:model="role_name" class="chief-input w-40 font-mono" @disabled($editingRoleId) /></div>
                    <div class="chief-field"><label for="r-label">Label</label><input id="r-label" wire:model="role_label" class="chief-input w-56" /></div>
                    <div class="flex gap-2 ms-[9.5rem]">
                        <button type="button" wire:click="$set('showRoleForm', false)" class="chief-btn">Cancel</button>
                        <button type="submit" class="chief-btn-primary">Save</button>
                    </div>
                </form>
            @else
                <div class="px-2 py-1 font-semibold border-b border-slate-300">Roles</div>
                <div class="chief-grid flex-1 overflow-auto">
                    <table>
                        <thead><tr><th>Code</th><th>Label</th><th>Users</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td class="font-mono">{{ $role->name }}</td>
                                    <td>{{ $role->label }}</td>
                                    <td>{{ $role->users_count }}</td>
                                    <td><button type="button" wire:click="editRole({{ $role->id }})" class="text-sky-700 underline text-xs">Edit</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-300 px-2 py-1.5">
                    <button type="button" wire:click="startNewRole" class="chief-btn-primary">New Role</button>
                </div>
            @endif
        @endif
    </div>
</div>
