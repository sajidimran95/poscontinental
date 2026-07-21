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
            $this->supplier = $supplier;
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

<div>
    <form wire:submit="save" class="chief-panel bg-white">
        <x-action-bar title="Action" />

        <div class="p-3 space-y-3">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <label class="block text-xs text-slate-600">Supplier ID</label>
                    <input wire:model="supplier_id" class="chief-input w-40" @disabled($supplier) />
                    @error('supplier_id') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                </div>
                <div class="flex items-center gap-2 mt-4 text-sm">
                    <span class="font-medium">Status:</span>
                    <button type="button" wire:click="$set('is_inactive', false)" @class(['chief-btn text-xs', 'chief-btn-primary' => ! $is_inactive])>Active</button>
                    <button type="button" wire:click="$set('is_inactive', true)" @class(['chief-btn text-xs', 'chief-btn-primary' => $is_inactive])>Inactive</button>
                </div>
                <label class="inline-flex items-center gap-2 mt-4 text-sm">
                    <input type="checkbox" wire:model.live="is_tobacco_supplier" class="rounded border-slate-400" />
                    Tobacco supplier (FEIN required)
                </label>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div>
                        <label class="block text-xs text-slate-600">Name</label>
                        <input wire:model="name" class="chief-input w-full" />
                        @error('name') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Contact Name</label>
                        <input wire:model="contact_name" class="chief-input w-full" />
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Address</label>
                        <input wire:model="address" class="chief-input w-full" />
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="col-span-2">
                            <label class="block text-xs text-slate-600">City</label>
                            <input wire:model="city" class="chief-input w-full" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600">State</label>
                            <input wire:model="state" class="chief-input w-full" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600">Zip</label>
                            <input wire:model="zip_code" class="chief-input w-full" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Country</label>
                        <input wire:model="country" class="chief-input w-32" />
                    </div>
                </div>

                <div class="space-y-2">
                    <div>
                        <label class="block text-xs text-slate-600">FEIN No.</label>
                        <input wire:model="fein_no" class="chief-input w-full" />
                        @error('fein_no') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-slate-600">Phone 1</label>
                            <input wire:model="phone1" class="chief-input w-full" placeholder="( ) -" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600">Phone 2</label>
                            <input wire:model="phone2" class="chief-input w-full" placeholder="( ) -" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600">Fax</label>
                            <input wire:model="fax" class="chief-input w-full" placeholder="( ) -" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Email Address</label>
                        <input wire:model="email" type="email" class="chief-input w-full" />
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Web page address</label>
                        <input wire:model="web_page" class="chief-input w-full" />
                    </div>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <h3 class="font-semibold text-slate-700">Departments & Contacts</h3>
                    <button type="button" wire:click="addContact" class="chief-btn">+ Add</button>
                </div>
                <div class="chief-grid border border-slate-300">
                    <table>
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Contact Name</th>
                                <th>Title</th>
                                <th>Phone</th>
                                <th>Ext</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contacts as $i => $contact)
                                <tr>
                                    <td><input wire:model="contacts.{{ $i }}.department" class="chief-input w-full" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.contact_name" class="chief-input w-full" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.title" class="chief-input w-full" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.phone" class="chief-input w-full" /></td>
                                    <td><input wire:model="contacts.{{ $i }}.ext" class="chief-input w-16" /></td>
                                    <td><button type="button" wire:click="removeContact({{ $i }})" class="text-red-600 px-2">−</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 px-3 py-2 border-t border-slate-300 bg-slate-50">
            <a href="{{ route('purchasing.suppliers.index') }}" wire:navigate class="chief-btn">Cancel</a>
            <button type="submit" class="chief-btn-primary">Save Changes</button>
        </div>
    </form>
</div>
