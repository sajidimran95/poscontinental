<?php

use App\Models\Supplier;
use App\Models\SupplierContact;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Supplier')] class extends Component
{
    public ?Supplier $supplier = null;

    public string $supplier_id = '';

    public bool $is_inactive = false;

    public bool $is_tobacco_supplier = false;

    public string $name = '';

    public string $contact_name = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $zip_code = '';

    public string $country = 'US';

    public string $fein_no = '';

    public string $phone1 = '';

    public string $phone2 = '';

    public string $fax = '';

    public string $email = '';

    public string $web_page = '';

    /** @var array<int, array{department:string,contact_name:string,title:string,phone:string,ext:string}> */
    public array $contacts = [];

    public function mount(?Supplier $supplier = null): void
    {
        if ($supplier?->exists) {
            abort_unless($supplier->company_id === auth()->user()->company_id, 403);
            $this->supplier = $supplier->load('contacts');
            $this->fill($supplier->only([
                'supplier_id', 'is_inactive', 'is_tobacco_supplier', 'name', 'contact_name',
                'address', 'city', 'state', 'zip_code', 'country', 'fein_no',
                'phone1', 'phone2', 'fax', 'email', 'web_page',
            ]));
            $this->contacts = $supplier->contacts->map(fn ($c) => [
                'department' => $c->department ?? '',
                'contact_name' => $c->contact_name,
                'title' => $c->title ?? '',
                'phone' => $c->phone ?? '',
                'ext' => $c->ext ?? '',
            ])->all();
        }

        if ($this->contacts === []) {
            $this->contacts[] = [
                'department' => '',
                'contact_name' => '',
                'title' => '',
                'phone' => '',
                'ext' => '',
            ];
        }
    }

    public function addContact(): void
    {
        $this->contacts[] = [
            'department' => '',
            'contact_name' => '',
            'title' => '',
            'phone' => '',
            'ext' => '',
        ];
    }

    public function removeContact(int $index): void
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
        if ($this->contacts === []) {
            $this->addContact();
        }
    }

    public function save(): void
    {
        $rules = [
            'supplier_id' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'is_tobacco_supplier' => 'boolean',
            'fein_no' => $this->is_tobacco_supplier ? 'required|string|max:32' : 'nullable|string|max:32',
            'contacts.*.contact_name' => 'nullable|string|max:255',
        ];

        $this->validate($rules);

        $data = [
            'company_id' => auth()->user()->company_id,
            'supplier_id' => $this->supplier_id,
            'is_inactive' => $this->is_inactive,
            'is_tobacco_supplier' => $this->is_tobacco_supplier,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'fein_no' => $this->fein_no,
            'phone1' => $this->phone1,
            'phone2' => $this->phone2,
            'fax' => $this->fax,
            'email' => $this->email,
            'web_page' => $this->web_page,
        ];

        if ($this->supplier) {
            $this->supplier->update($data);
            $supplier = $this->supplier;
            $supplier->contacts()->delete();
        } else {
            $supplier = Supplier::query()->create($data);
        }

        foreach ($this->contacts as $contact) {
            if (trim($contact['contact_name'] ?? '') === '') {
                continue;
            }
            $supplier->contacts()->create($contact);
        }

        session()->flash('status', 'Supplier saved.');
        $this->redirect(route('purchasing.suppliers.index'), navigate: true);
    }
}; ?>

<div class="desk-page entity-page">
    <form wire:submit="save" class="desk-main entity-form item-form">
        <x-action-bar :title="$supplier ? 'Edit Supplier — '.$supplier_id : 'New Supplier'" />

        <div class="entity-body">
            <div class="entity-header">
                <div class="sup-header-bar">
                    <div class="sup-header-id">
                        <label class="so-form-lbl" for="supplier_id">Supplier ID</label>
                        <input id="supplier_id" wire:model="supplier_id" class="so-input font-mono" style="width:10rem" @disabled($supplier) />
                    </div>
                    <div class="sup-header-status">
                        <span class="sup-status-lbl">Status</span>
                        <div class="entity-status-btns">
                            <button type="button" wire:click="$set('is_inactive', false)" @class(['desk-btn desk-btn-sm', 'is-on' => ! $is_inactive])>Active</button>
                            <button type="button" wire:click="$set('is_inactive', true)" @class(['desk-btn desk-btn-sm', 'is-on-danger' => $is_inactive])>Inactive</button>
                        </div>
                    </div>
                    <label class="entity-check sup-header-tobacco">
                        <input type="checkbox" wire:model.live="is_tobacco_supplier" />
                        Tobacco supplier (FEIN required)
                    </label>
                </div>
            </div>
            @error('supplier_id') <p class="text-xs text-red-700 mb-2" role="alert">{{ $message }}</p> @enderror

            <div class="sc-general-grid">
                <div class="inv-card">
                    <div class="inv-card-title">Company</div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="name">Company Name</label>
                        <input id="name" wire:model="name" class="so-input" />
                    </div>
                    @error('name') <p class="text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="contact_name">Contact Name</label>
                        <input id="contact_name" wire:model="contact_name" class="so-input" />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="address">Address</label>
                        <input id="address" wire:model="address" class="so-input" />
                    </div>
                    <div class="so-form-row so-form-row-side so-form-row-city sc-field sc-field-city">
                        <label class="so-form-lbl" for="city">City</label>
                        <input id="city" wire:model="city" class="so-input" />
                        <label class="so-form-lbl so-form-lbl-sm" for="state">State</label>
                        <input id="state" wire:model="state" class="so-input so-w-state" />
                        <label class="so-form-lbl so-form-lbl-sm" for="zip_code">ZIP</label>
                        <input id="zip_code" wire:model="zip_code" class="so-input so-w-zip" />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="country">Country</label>
                        <input id="country" wire:model="country" class="so-input" style="max-width:6rem" />
                    </div>
                </div>

                <div class="inv-card">
                    <div class="inv-card-title">Contact & tax</div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="fein_no">FEIN No.</label>
                        <input id="fein_no" wire:model="fein_no" class="so-input" />
                    </div>
                    @error('fein_no') <p class="text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="phone1">Telephone</label>
                        <input id="phone1" wire:model="phone1" class="so-input" placeholder="( ) -" />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="phone2">Phone 2</label>
                        <input id="phone2" wire:model="phone2" class="so-input" placeholder="( ) -" />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="fax">Fax</label>
                        <input id="fax" wire:model="fax" class="so-input" placeholder="( ) -" />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="email">Email Address</label>
                        <input id="email" type="email" wire:model="email" class="so-input" />
                    </div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl" for="web_page">Web Site</label>
                        <input id="web_page" wire:model="web_page" class="so-input" placeholder="www.example.com" />
                    </div>
                </div>
            </div>

            <div class="entity-section">
                <div class="entity-section-head">
                    <h3 class="entity-section-title">Departments & Contacts</h3>
                    <button type="button" wire:click="addContact" class="desk-btn desk-btn-sm">Add Contact</button>
                </div>
                <div class="desk-grid item-lines-wrap">
                    <table class="desk-table item-lines-table sup-contact-table">
                        <colgroup>
                            <col class="col-dept" />
                            <col class="col-name" />
                            <col class="col-title" />
                            <col class="col-phone" />
                            <col class="col-ext" />
                            <col class="col-action" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Contact Name</th>
                                <th>Title</th>
                                <th>Phone</th>
                                <th class="text-center">Ext</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contacts as $i => $contact)
                                <tr>
                                    <td><input wire:model="contacts.{{ $i }}.department" class="so-input item-cell-ctl" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.contact_name" class="so-input item-cell-ctl" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.title" class="so-input item-cell-ctl" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.phone" class="so-input item-cell-ctl" /></td>
                                    <td class="text-center"><input wire:model="contacts.{{ $i }}.ext" class="so-input text-center item-cell-ctl" style="max-width:4rem;margin:0 auto" /></td>
                                    <td class="text-center"><button type="button" wire:click="removeContact({{ $i }})" class="desk-btn desk-btn-sm">Remove</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="entity-footer">
            <div class="entity-tabs"><span class="entity-tab is-active">Supplier</span></div>
            <div class="entity-footer-actions">
                <a href="{{ route('purchasing.suppliers.index') }}" wire:navigate class="desk-btn">Cancel</a>
                <button type="submit" class="desk-btn desk-btn-primary">Save Changes</button>
            </div>
        </div>
    </form>
</div>
