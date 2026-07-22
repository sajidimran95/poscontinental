<?php

use App\Livewire\Forms\LoginForm;
use App\Models\Company;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.pos-login'), Title('Sign in')] class extends Component
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

<div class="pos-login">
    <div class="pos-login-stage" aria-hidden="true"></div>

    <div class="pos-login-window" role="dialog" aria-labelledby="pos-login-title">
        <div class="pos-login-chrome">
            <div class="pos-login-dots" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
            <div class="pos-login-chrome-title">JAPS POS</div>
            <div class="pos-login-chrome-spacer"></div>
        </div>

        <div class="pos-login-body-grid">
            <aside class="pos-login-brand">
                <div class="pos-login-brand-mark">J</div>
                <h1 id="pos-login-title" class="pos-login-brand-name">JAPS POS</h1>
                <p class="pos-login-brand-tag">Wholesale desk terminal</p>
                <ul class="pos-login-brand-points">
                    <li>Sales, inventory & purchasing</li>
                    <li>On-premises company workspace</li>
                    <li>Session locked to site & user</li>
                </ul>
            </aside>

            <section class="pos-login-panel">
                <div class="pos-login-panel-head">
                    <h2>Sign in</h2>
                    <p>Select your company, then enter your User ID and password.</p>
                </div>

                <x-auth-session-status class="pos-login-status" :status="session('status')" />

                <form wire:submit="login" class="pos-login-form">
                    <div class="pos-login-field">
                        <label for="company_id">Company</label>
                        <select wire:model="form.company_id" id="company_id" name="company_id" required>
                            <option value="">Select company…</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('form.company_id')" class="pos-login-error" />
                    </div>

                    <div class="pos-login-field">
                        <label for="username">User ID</label>
                        <input
                            wire:model="form.username"
                            id="username"
                            type="text"
                            name="username"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="e.g. yimran"
                        />
                        <x-input-error :messages="$errors->get('form.username')" class="pos-login-error" />
                    </div>

                    <div class="pos-login-field">
                        <label for="password">Password</label>
                        <input
                            wire:model="form.password"
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                        <x-input-error :messages="$errors->get('form.password')" class="pos-login-error" />
                    </div>

                    <div class="pos-login-actions">
                        <label class="pos-login-remember" for="remember">
                            <input wire:model="form.remember" id="remember" type="checkbox" name="remember">
                            <span>Keep me signed in</span>
                        </label>

                        <button type="submit" class="pos-login-submit">
                            <span wire:loading.remove wire:target="login">Open workstation</span>
                            <span wire:loading wire:target="login">Signing in…</span>
                        </button>
                    </div>
                </form>

                <div class="pos-login-footer">
                    <span>On-premises · Continental Wholesale</span>
                </div>
            </section>
        </div>
    </div>
</div>
