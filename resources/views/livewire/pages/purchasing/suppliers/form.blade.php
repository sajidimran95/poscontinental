<?php

use App\Livewire\Concerns\ReturnsToDeskList;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Supplier')] class extends Component
{
    use ReturnsToDeskList;

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
            $data = $supplier->only([
                'supplier_id', 'is_inactive', 'is_tobacco_supplier', 'name', 'contact_name',
                'address', 'city', 'state', 'zip_code', 'country', 'fein_no',
                'phone1', 'phone2', 'fax', 'email', 'web_page',
            ]);
            foreach ([
                'supplier_id', 'name', 'contact_name', 'address', 'city', 'state', 'zip_code',
                'country', 'fein_no', 'phone1', 'phone2', 'fax', 'email', 'web_page',
            ] as $stringProp) {
                $data[$stringProp] = (string) ($data[$stringProp] ?? '');
            }
            $this->fill($data);
            $this->contacts = $supplier->contacts->map(fn ($c) => [
                'department' => (string) ($c->department ?? ''),
                'contact_name' => (string) ($c->contact_name ?? ''),
                'title' => (string) ($c->title ?? ''),
                'phone' => (string) ($c->phone ?? ''),
                'ext' => (string) ($c->ext ?? ''),
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
        $companyId = (int) auth()->user()->company_id;

        $this->validate([
            'supplier_id' => [
                'required',
                'string',
                'max:64',
                Rule::unique('suppliers', 'supplier_id')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($this->supplier?->id),
            ],
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:32',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:64',
            'is_tobacco_supplier' => 'boolean',
            'fein_no' => $this->is_tobacco_supplier ? 'required|string|max:32' : 'nullable|string|max:32',
            'phone1' => 'nullable|string|max:32',
            'phone2' => 'nullable|string|max:32',
            'fax' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'web_page' => 'nullable|string|max:255',
            'contacts.*.department' => 'nullable|string|max:255',
            'contacts.*.contact_name' => 'nullable|string|max:255',
            'contacts.*.title' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:32',
            'contacts.*.ext' => 'nullable|string|max:16',
        ], [
            'supplier_id.required' => 'Supplier ID is required.',
            'supplier_id.max' => 'Supplier ID can be at most 64 characters.',
            'supplier_id.unique' => 'A supplier with this ID already exists.',
            'name.required' => 'Company name is required.',
            'name.max' => 'Company name can be at most 255 characters.',
            'fein_no.required' => 'FEIN No. is required for tobacco suppliers.',
            'fein_no.max' => 'FEIN No. can be at most 32 characters.',
            'phone1.max' => 'Telephone can be at most 32 characters.',
            'phone2.max' => 'Phone 2 can be at most 32 characters.',
            'fax.max' => 'Fax can be at most 32 characters.',
            'email.email' => 'Enter a valid email address.',
            'state.max' => 'State can be at most 32 characters.',
            'zip_code.max' => 'ZIP code can be at most 20 characters.',
            'contacts.*.phone.max' => 'Contact phone can be at most 32 characters.',
            'contacts.*.ext.max' => 'Phone extension can be at most 16 characters.',
        ]);

        $data = [
            'company_id' => $companyId,
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

        try {
            DB::transaction(function () use ($data) {
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
                    $supplier->contacts()->create([
                        'department' => $contact['department'] ?? '',
                        'contact_name' => $contact['contact_name'],
                        'title' => $contact['title'] ?? '',
                        'phone' => $contact['phone'] ?? '',
                        'ext' => $contact['ext'] ?? '',
                    ]);
                }
            });
        } catch (QueryException $e) {
            $this->addError('name', $this->supplierSaveErrorMessage($e));

            return;
        }

        session()->flash('status', 'Supplier saved.');
        $this->returnToDeskList('purchasing.suppliers.index');
    }

    protected function supplierSaveErrorMessage(QueryException $e): string
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $message = $e->getMessage();

        if ($sqlState === '22001' || str_contains($message, 'Data too long')) {
            if (str_contains($message, "'ext'")) {
                return 'Phone extension is too long. Use at most 16 characters (for example 1234).';
            }
            if (str_contains($message, "'phone'")) {
                return 'A phone number is too long. Use at most 32 characters.';
            }

            return 'One or more fields are too long. Shorten the highlighted values and try again.';
        }

        if ($sqlState === '23000' || str_contains($message, 'Duplicate')) {
            return 'A supplier with this ID already exists. Choose a different Supplier ID.';
        }

        return 'Unable to save this supplier. Check the form and try again.';
    }

    public function cancelAction(): mixed
    {
        return $this->redirect(route('purchasing.suppliers.index'), navigate: true);
    }
}; ?>

<div class="desk-page entity-page">
    <form wire:submit="save" class="desk-main entity-form item-form">
        <x-action-bar :title="$supplier ? 'Edit Supplier — '.$supplier_id : 'New Supplier'">
            <x-slot:menu>
                <x-action-item label="Save Changes" kbd="Ctrl+S" wire:click="save" />
                <x-action-item label="Cancel" kbd="Ctrl+Q" sep wire:click="cancelAction" />
            </x-slot:menu>
        </x-action-bar>

        @if ($errors->any())
            <div class="desk-flash bp-flash-error" role="alert">
                <strong>Unable to save supplier.</strong>
                <ul style="margin:0.35rem 0 0;padding-left:1.15rem">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="entity-body">
            <div class="entity-header">
                <div class="sup-header-bar">
                    <div class="sup-header-id">
                        <label class="so-form-lbl so-field-req" for="supplier_id">Supplier ID</label>
                        <input id="supplier_id" wire:model="supplier_id" class="so-input font-mono @error('supplier_id') is-invalid @enderror" style="width:10rem" @disabled($supplier) />
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
            @error('supplier_id') <p class="so-field-error mb-2" role="alert">{{ $message }}</p> @enderror

            <div class="sc-general-grid">
                <div class="inv-card">
                    <div class="inv-card-title">Company</div>
                    <div class="so-form-row so-form-row-side sc-field">
                        <label class="so-form-lbl so-field-req" for="name">Company Name</label>
                        <input id="name" wire:model="name" class="so-input @error('name') is-invalid @enderror" />
                    </div>
                    @error('name') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
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
                        <label @class(['so-form-lbl', 'so-field-req' => $is_tobacco_supplier]) for="fein_no">FEIN No.</label>
                        <input id="fein_no" wire:model="fein_no" class="so-input @error('fein_no') is-invalid @enderror" />
                    </div>
                    @error('fein_no') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
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
                                    <td><input wire:model="contacts.{{ $i }}.department" class="so-input item-cell-ctl @error('contacts.'.$i.'.department') is-invalid @enderror" maxlength="255" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.contact_name" class="so-input item-cell-ctl @error('contacts.'.$i.'.contact_name') is-invalid @enderror" maxlength="255" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.title" class="so-input item-cell-ctl @error('contacts.'.$i.'.title') is-invalid @enderror" maxlength="255" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.phone" class="so-input item-cell-ctl @error('contacts.'.$i.'.phone') is-invalid @enderror" maxlength="32" /></td>
                                    <td class="text-center"><input wire:model="contacts.{{ $i }}.ext" class="so-input text-center item-cell-ctl @error('contacts.'.$i.'.ext') is-invalid @enderror" maxlength="16" style="max-width:4rem;margin:0 auto" title="Phone extension, up to 16 characters" /></td>
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
