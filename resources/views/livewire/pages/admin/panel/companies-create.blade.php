<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.admin'), Title('Register Company')] class extends Component
{
    public string $title = 'Register Company';

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public bool $customer_app_api_active = true;

    public string $site_code = 'WS';

    public string $site_name = 'Main Site';

    public string $admin_name = '';

    public string $admin_username = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('companies', 'code')],
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'customer_app_api_active' => 'boolean',
            'site_code' => 'required|string|max:32',
            'site_name' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_username' => 'required|string|max:64',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:6|max:120',
        ]);

        DB::transaction(function () use ($data) {
            $company = Company::query()->create([
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'is_active' => $data['is_active'],
                'customer_app_api_active' => $data['customer_app_api_active'],
            ]);

            $site = Site::query()->create([
                'company_id' => $company->id,
                'code' => strtoupper($data['site_code']),
                'name' => $data['site_name'],
                'is_active' => true,
            ]);

            $adminRole = Role::query()->firstOrCreate(
                ['name' => 'admin'],
                ['label' => 'Administrator']
            );

            Role::query()->firstOrCreate(['name' => 'sales_rep'], ['label' => 'Sales Rep']);
            Role::query()->firstOrCreate(['name' => 'buyer'], ['label' => 'Buyer']);
            Role::query()->firstOrCreate(['name' => 'warehouse'], ['label' => 'Warehouse']);

            User::query()->create([
                'company_id' => $company->id,
                'site_id' => $site->id,
                'role_id' => $adminRole->id,
                'name' => $data['admin_name'],
                'username' => $data['admin_username'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'email_verified_at' => now(),
                'is_active' => true,
                'is_platform_admin' => false,
            ]);
        });

        session()->flash('status', 'Company registered successfully.');
        $this->redirect(route('admin.panel.companies'), navigate: true);
    }
}; ?>

<div>
    <div class="admin-card">
        <p class="text-sm text-slate-400 mb-4">
            Creates a company, default site, and the first POS company admin user.
            Customer App API can be toggled here or later from the companies list.
        </p>

        <form wire:submit="save" class="admin-form-grid">
            <div class="admin-field">
                <label for="code">Company code</label>
                <input id="code" wire:model="code" class="font-mono uppercase" placeholder="CWI" />
                @error('code') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="name">Company name</label>
                <input id="name" wire:model="name" placeholder="Continental Wholesale Inc" />
                @error('name') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>

            <div class="admin-field">
                <label for="site_code">Site code</label>
                <input id="site_code" wire:model="site_code" />
                @error('site_code') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="site_name">Site name</label>
                <input id="site_name" wire:model="site_name" />
                @error('site_name') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>

            <div class="admin-field">
                <label>
                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-600 bg-slate-900 text-sky-500" />
                    Company active
                </label>
            </div>
            <div class="admin-field">
                <label>
                    <input type="checkbox" wire:model="customer_app_api_active" class="rounded border-slate-600 bg-slate-900 text-sky-500" />
                    Customer App API active
                </label>
            </div>

            <div class="admin-field" style="grid-column: 1 / -1">
                <div class="admin-stat-lbl mb-2">First POS admin user</div>
            </div>

            <div class="admin-field">
                <label for="admin_name">Name</label>
                <input id="admin_name" wire:model="admin_name" />
                @error('admin_name') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="admin_username">Username (POS login)</label>
                <input id="admin_username" wire:model="admin_username" />
                @error('admin_username') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="admin_email">Email</label>
                <input id="admin_email" type="email" wire:model="admin_email" />
                @error('admin_email') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="admin_password">Password</label>
                <input id="admin_password" type="password" wire:model="admin_password" autocomplete="new-password" />
                @error('admin_password') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>

            <div class="admin-field" style="grid-column: 1 / -1; display:flex; gap:0.5rem; margin-top:0.5rem">
                <button type="submit" class="admin-btn admin-btn-primary">Create company</button>
                <a href="{{ route('admin.panel.companies') }}" wire:navigate class="admin-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>
