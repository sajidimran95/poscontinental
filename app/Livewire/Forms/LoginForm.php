<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class LoginForm extends Form
{
    public ?int $company_id = null;

    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        $user = User::query()
            ->where('company_id', $this->company_id)
            ->where('username', $this->username)
            ->where('is_active', true)
            ->first();

        if (! $user || ! Auth::attempt([
            'email' => $user->email,
            'password' => $this->password,
        ], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.username' => trans('auth.failed'),
            ]);
        }

        Auth::login($user->load(['company', 'site', 'role']), $this->remember);

        session([
            'company_id' => $user->company_id,
            'site_id' => $user->site_id,
            'company_name' => $user->company?->name,
            'site_code' => $user->site?->code,
        ]);

        RateLimiter::clear($this->throttleKey());
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->username).'|'.$this->company_id.'|'.request()->ip());
    }
}
