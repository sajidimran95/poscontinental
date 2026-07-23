<?php

use App\Models\Company;
use App\Services\CompanyMailConfig;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Email Setup')] class extends Component
{
    public string $mail_mailer = 'log';

    public string $mail_host = '';

    public string $mail_port = '587';

    public string $mail_username = '';

    public string $mail_password = '';

    public string $mail_encryption = 'tls';

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    public string $test_to = '';

    public string $status = '';

    public string $error = '';

    public function mount(): void
    {
        $company = Company::query()->find(auth()->user()->company_id);
        if (! $company) {
            return;
        }

        $this->mail_mailer = $company->mail_mailer ?: 'log';
        $this->mail_host = (string) ($company->mail_host ?? '');
        $this->mail_port = (string) ($company->mail_port ?: 587);
        $this->mail_username = (string) ($company->mail_username ?? '');
        $this->mail_password = '';
        $this->mail_encryption = (string) ($company->mail_encryption ?: 'tls');
        $this->mail_from_address = (string) ($company->mail_from_address ?? '');
        $this->mail_from_name = (string) ($company->mail_from_name ?: $company->name);
        $this->test_to = (string) (auth()->user()->email ?? '');
    }

    public function save(): void
    {
        $this->error = '';
        $this->status = '';

        $this->validate([
            'mail_mailer' => 'required|in:log,smtp',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|in:tls,ssl,',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
        ], [
            'mail_from_address.email' => 'Enter a valid From email address.',
        ]);

        if ($this->mail_mailer === 'smtp') {
            if (blank($this->mail_host)) {
                $this->addError('mail_host', 'SMTP host is required.');

                return;
            }
            if (blank($this->mail_from_address)) {
                $this->addError('mail_from_address', 'From address is required for SMTP.');

                return;
            }
        }

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $data = [
            'mail_mailer' => $this->mail_mailer,
            'mail_host' => $this->mail_host ?: null,
            'mail_port' => filled($this->mail_port) ? (int) $this->mail_port : null,
            'mail_username' => $this->mail_username ?: null,
            'mail_encryption' => $this->mail_encryption !== '' ? $this->mail_encryption : null,
            'mail_from_address' => $this->mail_from_address ?: null,
            'mail_from_name' => $this->mail_from_name ?: null,
        ];
        if (filled($this->mail_password)) {
            $data['mail_password'] = $this->mail_password;
        }

        $company->update($data);
        $this->mail_password = '';
        $this->status = 'Email settings saved.';
    }

    public function sendTest(): void
    {
        $this->error = '';
        $this->status = '';

        $this->validate([
            'test_to' => 'required|email',
        ], [
            'test_to.required' => 'Enter a test recipient email.',
            'test_to.email' => 'Enter a valid test recipient email.',
        ]);

        $this->save();
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        CompanyMailConfig::apply($company->fresh());

        try {
            Mail::raw(
                'This is a test message from JAPS POS Email Setup for '.$company->name.'.',
                function ($message) use ($company) {
                    $message->to($this->test_to)
                        ->subject('JAPS POS test email — '.$company->name);
                }
            );
            $this->status = 'Test email sent to '.$this->test_to.'.';
        } catch (\Throwable $e) {
            $this->error = 'Could not send test email: '.$e->getMessage();
        }
    }
}; ?>

<div class="desk-page">
    <div class="desk-main" style="max-width:42rem">
        <x-action-bar title="Email Setup" />

        @if ($status)
            <div class="desk-flash" role="status">{{ $status }}</div>
        @endif
        @if ($error)
            <div class="desk-flash" style="background:#fef2f2;border-color:#fecaca;color:#991b1b" role="alert">{{ $error }}</div>
        @endif

        <form wire:submit="save" class="inv-card" style="margin:1rem 0.85rem">
            <div class="inv-card-title">Outgoing mail</div>
            <p class="item-hint" style="border:0;margin:0 0 1rem;padding:0">
                Used when emailing invoices and credit memos. Choose <strong>SMTP</strong> for real delivery, or <strong>Log</strong> for local testing (no email sent).
            </p>

            <div class="so-form-row so-form-row-side sc-field">
                <label class="so-form-lbl" for="mail_mailer">Mailer</label>
                <select id="mail_mailer" wire:model.live="mail_mailer" class="so-input">
                    <option value="log">Log (testing only)</option>
                    <option value="smtp">SMTP</option>
                </select>
            </div>

            @if ($mail_mailer === 'smtp')
                <div class="so-form-row so-form-row-side sc-field">
                    <label class="so-form-lbl" for="mail_host">SMTP Host</label>
                    <input id="mail_host" wire:model="mail_host" class="so-input @error('mail_host') is-invalid @enderror" placeholder="smtp.gmail.com" />
                </div>
                @error('mail_host') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror

                <div class="so-form-row so-form-row-side sc-field">
                    <label class="so-form-lbl" for="mail_port">Port</label>
                    <input id="mail_port" wire:model="mail_port" class="so-input" style="max-width:6rem" placeholder="587" />
                </div>

                <div class="so-form-row so-form-row-side sc-field">
                    <label class="so-form-lbl" for="mail_encryption">Encryption</label>
                    <select id="mail_encryption" wire:model="mail_encryption" class="so-input">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="">None</option>
                    </select>
                </div>

                <div class="so-form-row so-form-row-side sc-field">
                    <label class="so-form-lbl" for="mail_username">Username</label>
                    <input id="mail_username" wire:model="mail_username" class="so-input" autocomplete="off" />
                </div>

                <div class="so-form-row so-form-row-side sc-field">
                    <label class="so-form-lbl" for="mail_password">Password</label>
                    <input id="mail_password" type="password" wire:model="mail_password" class="so-input" placeholder="Leave blank to keep current" autocomplete="new-password" />
                </div>
            @endif

            <div class="so-form-row so-form-row-side sc-field">
                <label class="so-form-lbl" for="mail_from_address">From Email</label>
                <input id="mail_from_address" type="email" wire:model="mail_from_address" class="so-input @error('mail_from_address') is-invalid @enderror" placeholder="noreply@company.com" />
            </div>
            @error('mail_from_address') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror

            <div class="so-form-row so-form-row-side sc-field">
                <label class="so-form-lbl" for="mail_from_name">From Name</label>
                <input id="mail_from_name" wire:model="mail_from_name" class="so-input" placeholder="Company name" />
            </div>

            <div class="rpt-actions" style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
                <button type="submit" class="desk-btn desk-btn-primary">Save Settings</button>
            </div>
        </form>

        <div class="inv-card" style="margin:0 0.85rem 1rem">
            <div class="inv-card-title">Send test email</div>
            <div class="so-form-row so-form-row-side sc-field">
                <label class="so-form-lbl" for="test_to">To</label>
                <input id="test_to" type="email" wire:model="test_to" class="so-input @error('test_to') is-invalid @enderror" />
            </div>
            @error('test_to') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
            <div class="rpt-actions" style="margin-top:0.75rem">
                <button type="button" wire:click="sendTest" class="desk-btn">Send Test</button>
            </div>
        </div>
    </div>
</div>
