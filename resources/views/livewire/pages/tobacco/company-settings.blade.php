<?php

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Company Settings')] class extends Component
{
    public string $code = '';

    public string $name = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $zip_code = '';

    public string $phone = '';

    public string $fax = '';

    public string $email = '';

    public string $contact_name = '';

    public string $fein_no = '';

    public string $state_license_number = '';

    public string $transmitter_account_number = '';

    public bool $is_active = true;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->loadCompany();
    }

    public function loadCompany(): void
    {
        $company = auth()->user()->company;
        $this->code = (string) ($company?->code ?? '');
        $this->name = (string) ($company?->name ?? '');
        $this->address = (string) ($company?->address ?? '');
        $this->city = (string) ($company?->city ?? '');
        $this->state = (string) ($company?->state ?? '');
        $this->zip_code = (string) ($company?->zip_code ?? '');
        $this->phone = (string) ($company?->phone ?? '');
        $this->fax = (string) ($company?->fax ?? '');
        $this->email = (string) ($company?->email ?? '');
        $this->contact_name = (string) ($company?->contact_name ?? '');
        $this->fein_no = (string) ($company?->fein_no ?? '');
        $this->state_license_number = (string) ($company?->state_license_number ?? '');
        $this->transmitter_account_number = (string) ($company?->transmitter_account_number ?? '');
        $this->is_active = (bool) ($company?->is_active ?? true);
    }

    public function fillDemo(): void
    {
        $this->code = $this->code !== '' ? $this->code : 'CWI';
        $this->name = 'Continental Wholesale Inc';
        $this->address = '3802 TRADE CENTER DR';
        $this->city = 'ANN ARBOR';
        $this->state = 'MI';
        $this->zip_code = '48108';
        $this->phone = '7346773510';
        $this->fax = '7346773567';
        $this->email = 'office@continentalwholesale.test';
        $this->contact_name = 'Office Desk';
        $this->fein_no = '38-1234567';
        $this->state_license_number = '10001234';
        $this->transmitter_account_number = '381234567';
        $this->is_active = true;
        $this->statusMessage = 'Demo company address & filing values loaded — click Save Settings to keep them.';
    }

    public function save(): void
    {
        $this->statusMessage = '';
        $this->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:2'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:40'],
            'fax' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'fein_no' => ['required', 'string', 'max:32', 'regex:/^[0-9\-]+$/'],
            'state_license_number' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'transmitter_account_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]*$/'],
            'is_active' => ['boolean'],
        ], [
            'fein_no.required' => 'Company FEIN is required for MSA tobacco filing.',
            'fein_no.regex' => 'FEIN must be digits (dashes allowed).',
            'state_license_number.required' => 'State License Number is required.',
            'state_license_number.regex' => 'State License Number must be numeric.',
            'transmitter_account_number.regex' => 'Transmitter must be numeric (State Employer Account Number).',
        ]);

        $company = Company::query()->findOrFail(auth()->user()->company_id);

        $codeTaken = Company::query()
            ->where('code', $this->code)
            ->where('id', '!=', $company->id)
            ->exists();
        if ($codeTaken) {
            $this->addError('code', 'That company code is already in use.');

            return;
        }

        $company->update([
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address !== '' ? $this->address : null,
            'city' => $this->city !== '' ? $this->city : null,
            'state' => $this->state !== '' ? strtoupper($this->state) : null,
            'zip_code' => $this->zip_code !== '' ? $this->zip_code : null,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'fax' => $this->fax !== '' ? $this->fax : null,
            'email' => $this->email !== '' ? $this->email : null,
            'contact_name' => $this->contact_name !== '' ? $this->contact_name : null,
            'fein_no' => $this->fein_no,
            'state_license_number' => $this->state_license_number,
            'transmitter_account_number' => $this->transmitter_account_number !== ''
                ? $this->transmitter_account_number
                : null,
            'is_active' => $this->is_active,
        ]);

        session(['company_name' => $company->name]);
        $this->statusMessage = 'Company settings saved. Address & contact now print on invoices, sales orders, and other PDFs.';
    }
}; ?>

<div class="stamp-inv-page">
    <x-action-bar title="Company Settings" />

    @if ($statusMessage !== '')
        <div class="stamp-inv-flash stamp-inv-flash-ok" role="status">{{ $statusMessage }}</div>
    @endif

    @if ($errors->any())
        <div class="stamp-inv-flash stamp-inv-flash-err" role="alert">
            <strong>Could not save:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stamp-inv-body">
        <div class="msa-how-box">
            <h3>Where this prints</h3>
            <ul>
                <li><strong>Company name + address + phone/fax/email</strong> — letterhead on Invoices, Sales Orders, Credit Memos, Pick Lists, Payment Receipts, and other PDFs.</li>
                <li><strong>Company FEIN / license / transmitter</strong> — MSA tobacco XML filer identity (not supplier/customer).</li>
                <li><strong>Supplier / Customer FEIN</strong> — still set on Supplier and Customer forms for MSA schedule parties.</li>
            </ul>
        </div>

        <form wire:submit.prevent="save" class="stamp-inv-form" style="max-width: 40rem;" autocomplete="off">
            <p class="stamp-inv-hint">
                System company identity for documents and Michigan MSA filing. Use <strong>Fill Demo</strong> for Ann Arbor sample values.
            </p>

            <h3 class="msa-section-title">Company</h3>
            <div class="msa-field-grid">
                <label class="stamp-inv-field">
                    <span>Company Code <em>*</em></span>
                    <input type="text" wire:model="code" class="desk-input" />
                </label>
                <label class="stamp-inv-field">
                    <span>Active</span>
                    <select wire:model="is_active" class="desk-select">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </label>
            </div>

            <label class="stamp-inv-field">
                <span>Company Name <em>*</em></span>
                <input type="text" wire:model="name" class="desk-input" placeholder="Continental Wholesale Inc" />
            </label>

            <label class="stamp-inv-field">
                <span>Contact Name</span>
                <input type="text" wire:model="contact_name" class="desk-input" placeholder="Office / A/R contact" />
            </label>

            <h3 class="msa-section-title">Address (prints on invoices &amp; sales docs)</h3>
            <label class="stamp-inv-field">
                <span>Street Address</span>
                <input type="text" wire:model="address" class="desk-input" placeholder="3802 TRADE CENTER DR" />
            </label>
            <div class="msa-field-grid" style="grid-template-columns: 1.4fr 0.6fr 0.8fr;">
                <label class="stamp-inv-field">
                    <span>City</span>
                    <input type="text" wire:model="city" class="desk-input" placeholder="ANN ARBOR" />
                </label>
                <label class="stamp-inv-field">
                    <span>State</span>
                    <input type="text" wire:model="state" class="desk-input" maxlength="2" placeholder="MI" />
                </label>
                <label class="stamp-inv-field">
                    <span>ZIP</span>
                    <input type="text" wire:model="zip_code" class="desk-input" placeholder="48108" />
                </label>
            </div>

            <h3 class="msa-section-title">Contact</h3>
            <div class="msa-field-grid">
                <label class="stamp-inv-field">
                    <span>Phone</span>
                    <input type="text" wire:model="phone" class="desk-input" placeholder="7346773510" />
                </label>
                <label class="stamp-inv-field">
                    <span>Fax</span>
                    <input type="text" wire:model="fax" class="desk-input" placeholder="7346773567" />
                </label>
            </div>
            <label class="stamp-inv-field">
                <span>Email</span>
                <input type="email" wire:model="email" class="desk-input" placeholder="office@continentalwholesale.test" />
            </label>

            <h3 class="msa-section-title">MSA / Tobacco filing</h3>
            <label class="stamp-inv-field">
                <span>Company FEIN <em>*</em></span>
                <input type="text" wire:model="fein_no" class="desk-input" placeholder="38-1234567" />
            </label>
            <label class="stamp-inv-field">
                <span>State License Number <em>*</em></span>
                <input type="text" wire:model="state_license_number" class="desk-input" placeholder="Numeric Michigan tobacco license #" />
            </label>
            <label class="stamp-inv-field">
                <span>Transmitter <small>(State Employer Account #)</small></span>
                <input type="text" wire:model="transmitter_account_number" class="desk-input" placeholder="Optional — defaults to FEIN digits" />
            </label>

            <div class="stamp-inv-actions">
                <button type="submit" class="desk-btn desk-btn-primary">Save Settings</button>
                <button type="button" wire:click="fillDemo" class="desk-btn">Fill Demo</button>
                <a href="{{ route('reports.msa') }}" wire:navigate class="desk-btn">Back to MSA Report</a>
            </div>
        </form>
    </div>
</div>
