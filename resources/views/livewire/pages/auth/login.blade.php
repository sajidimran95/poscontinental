<?php

use App\Livewire\Forms\LoginForm;
use App\Models\Company;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function mount(): void
    {
        $first = Company::query()->where('is_active', true)->orderBy('name')->first();
        if ($first) {
            $this->form->company_id = $first->id;
        }
    }

    public function with(): array
    {
        return [
            'companies' => Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function login(): void
    {
        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('home', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-semibold text-slate-800">Continental Wholesale POS</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to continue</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <x-input-label for="company_id" :value="__('Company')" />
            <select wire:model="form.company_id" id="company_id" name="company_id" required
                class="mt-1 block w-full rounded-md border-gray-300 bg-white text-slate-900 shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Select company…</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('form.company_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="username" :value="__('User ID')" />
            <x-text-input wire:model="form.username" id="username" class="block mt-1 w-full" type="text" name="username" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.username')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-sky-600 shadow-sm focus:ring-sky-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>

            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>
        </div>

        <p class="mt-4 text-center text-xs text-slate-500">
            <a href="{{ route('admin.panel.login') }}" class="text-sky-700 hover:underline" wire:navigate>Platform Admin login</a>
        </p>
    </form>
</div>
