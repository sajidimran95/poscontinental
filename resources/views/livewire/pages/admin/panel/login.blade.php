<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.admin-guest'), Title('Admin Login')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $key = Str::lower($this->email).'|admin|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $user = User::query()
            ->where('email', $this->email)
            ->where('is_platform_admin', true)
            ->where('is_active', true)
            ->first();

        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages([
                'email' => 'Invalid admin credentials.',
            ]);
        }

        Auth::login($user, $this->remember);
        session()->regenerate();
        RateLimiter::clear($key);

        $this->redirect(route('admin.panel.dashboard'), navigate: true);
    }
}; ?>

<div class="admin-login-wrap">
    <div class="admin-login-card">
        <h1>Platform Admin</h1>
        <p>Manage companies and Customer App API</p>

        <form wire:submit="login" class="space-y-4">
            <div class="admin-field">
                <label for="admin-email">Email</label>
                <input id="admin-email" type="email" wire:model="email" autocomplete="username" required />
                @error('email') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>
            <div class="admin-field">
                <label for="admin-password">Password</label>
                <input id="admin-password" type="password" wire:model="password" autocomplete="current-password" required />
                @error('password') <div class="admin-field-error">{{ $message }}</div> @enderror
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-400">
                <input type="checkbox" wire:model="remember" class="rounded border-slate-600 bg-slate-900 text-sky-500" />
                Remember me
            </label>
            <button type="submit" class="admin-btn admin-btn-primary w-full">Sign in to Admin Panel</button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500">
            <a href="{{ route('login') }}" class="text-sky-400 hover:underline" wire:navigate>POS login</a>
        </p>
    </div>
</div>
