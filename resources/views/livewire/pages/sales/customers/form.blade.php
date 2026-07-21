<?php

use App\Models\CigaretteTaxClass;
use App\Models\Customer;
use App\Models\CustomerLookupOption;
use App\Models\CustomerShippingAddress;
use App\Models\DiscountSchedule;
use App\Models\PaymentTerm;
use App\Models\PriceLevel;
use App\Models\PurchaseLimitSchedule;
use App\Models\RouteLookup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Customer')] class extends Component
{
    public ?Customer $customer = null;

    public string $activeTab = 'name';

    public string $customer_id = '';

    public bool $is_inactive = false;

    public string $contact = '';

    public string $company_name = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $zip_code = '';

    public string $country = 'US';

    public string $telephone = '';

    public string $telephone2 = '';

    public string $mobile = '';

    public string $fax = '';

    public string $email = '';

    public string $web_page = '';

    public ?int $price_level_id = null;

    public ?int $cigarette_tax_class_id = null;

    public ?int $discount_schedule_id = null;

    public ?int $purchase_limit_schedule_id = null;

    public ?int $payment_term_id = null;

    public ?int $sales_rep_id = null;

    public ?int $delivery_route_id = null;

    public string $lead_source = '';

    public string $customer_category = '';

    public bool $opt_out_catalog = false;

    public bool $opt_out_email = false;

    public bool $opt_out_telemarketing = false;

    public bool $opt_out_mobile = false;

    public bool $opt_out_all = false;

    public string $fein_no = '';

    public string $account_type = '';

    public string $credit_limit = '0.00';

    public string $balance = '0.00';

    public ?string $customer_since = null;

    public ?string $last_order_on = null;

    public string $number_of_orders = '0';

    public string $total_sales = '0.00';

    public string $bad_checks_count = '0';

    public string $replacements_count = '0';

    public string $returns_count = '0';

    public string $messages_alerts = '';

    public string $comments = '';

    public bool $is_tax_exempt = false;

    public string $tax_certificate_no = '';

    public string $tax_certificate_exp = '';

    public bool $certificate_on_file = false;

    public string $order_day = '';

    public string $location_no = '';

    public bool $drivers_accept_returns = false;

    public bool $is_employee = false;

    public string $owner_name = '';

    public string $owner_ssn = '';

    public string $owner_ssn_display = '';

    public bool $reveal_ssn = false;

    public string $owner_address = '';

    public string $owner_city = '';

    public string $owner_state = '';

    public string $owner_zip = '';

    public string $owner_country = 'US';

    public string $owner_telephone = '';

    public string $owner_fax = '';

    public string $owner_email = '';

    /** @var array<int, array{name:string,address:string,telephone:string,fax:string,class:string,is_primary:bool}> */
    public array $shippingAddresses = [];

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            abort_unless($customer->company_id === auth()->user()->company_id, 403);
            $this->customer = $customer->load('shippingAddresses');
            $this->fill($customer->only([
                'customer_id', 'is_inactive', 'contact', 'company_name', 'address', 'city', 'state',
                'zip_code', 'country', 'telephone', 'telephone2', 'mobile', 'fax', 'email', 'web_page',
                'price_level_id', 'cigarette_tax_class_id', 'discount_schedule_id', 'purchase_limit_schedule_id',
                'payment_term_id', 'sales_rep_id', 'delivery_route_id', 'lead_source', 'customer_category',
                'opt_out_catalog', 'opt_out_email', 'opt_out_telemarketing', 'opt_out_mobile', 'opt_out_all',
                'fein_no', 'account_type', 'credit_limit', 'balance', 'number_of_orders', 'total_sales',
                'bad_checks_count', 'replacements_count', 'returns_count', 'messages_alerts', 'comments',
                'is_tax_exempt', 'tax_certificate_no', 'certificate_on_file', 'order_day', 'location_no',
                'drivers_accept_returns', 'is_employee', 'owner_name', 'owner_address', 'owner_city',
                'owner_state', 'owner_zip', 'owner_country', 'owner_telephone', 'owner_fax', 'owner_email',
            ]));
            $this->tax_certificate_exp = optional($customer->tax_certificate_exp)?->format('Y-m-d') ?? '';
            $this->customer_since = optional($customer->customer_since)?->format('Y-m-d');
            $this->last_order_on = optional($customer->last_order_on)?->format('Y-m-d');
            $this->owner_ssn = $customer->owner_ssn ?? '';
            $this->owner_ssn_display = $customer->owner_ssn_masked;
            $this->shippingAddresses = $customer->shippingAddresses->map(fn (CustomerShippingAddress $a) => [
                'name' => $a->name ?? '',
                'address' => $a->address ?? '',
                'city' => $a->city ?? '',
                'state' => $a->state ?? '',
                'zip' => $a->zip ?? '',
                'telephone' => $a->telephone ?? '',
                'fax' => $a->fax ?? '',
                'class' => $a->class ?? '',
                'is_primary' => (bool) $a->is_primary,
            ])->all();
        }

        if ($this->shippingAddresses === []) {
            $this->shippingAddresses[] = [
                'name' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => '',
                'telephone' => '', 'fax' => '', 'class' => '', 'is_primary' => true,
            ];
        }
    }

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'priceLevels' => PriceLevel::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'cigaretteTaxes' => CigaretteTaxClass::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'discountSchedules' => DiscountSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'purchaseLimits' => PurchaseLimitSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'paymentTerms' => PaymentTerm::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'salesReps' => User::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'routes' => RouteLookup::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'leadSources' => CustomerLookupOption::optionsFor($companyId, 'lead_source'),
            'customerCategories' => CustomerLookupOption::optionsFor($companyId, 'customer_category'),
            'accountTypes' => CustomerLookupOption::optionsFor($companyId, 'account_type'),
            'orderDays' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'tabs' => [
                'name' => 'Name & Address',
                'general' => 'General Information',
                'account' => 'Account Summary',
                'other' => 'Other Information',
            ],
            'availableCredit' => (float) $this->credit_limit - (float) $this->balance,
        ];
    }

    public function addShipTo(): void
    {
        $this->shippingAddresses[] = [
            'name' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => '',
            'telephone' => '', 'fax' => '', 'class' => '', 'is_primary' => false,
        ];
    }

    public function removeShipTo(int $index): void
    {
        unset($this->shippingAddresses[$index]);
        $this->shippingAddresses = array_values($this->shippingAddresses);
        if ($this->shippingAddresses === []) {
            $this->addShipTo();
        }
    }

    public function setPrimaryShipTo(int $index): void
    {
        foreach ($this->shippingAddresses as $i => $row) {
            $this->shippingAddresses[$i]['is_primary'] = $i === $index;
        }
    }

    public function toggleRevealSsn(): void
    {
        $this->reveal_ssn = ! $this->reveal_ssn;
        if ($this->reveal_ssn) {
            $this->owner_ssn_display = $this->owner_ssn;
        } else {
            $digits = preg_replace('/\D/', '', $this->owner_ssn) ?? '';
            $this->owner_ssn_display = strlen($digits) >= 4 ? '***-**-'.substr($digits, -4) : ($this->owner_ssn ? '***' : '');
        }
    }

    public function updatedOwnerSsnDisplay(): void
    {
        if ($this->reveal_ssn || ! str_contains($this->owner_ssn_display, '*')) {
            $this->owner_ssn = $this->owner_ssn_display;
        }
    }

    public function updatedOptOutAll($value): void
    {
        if ($value) {
            $this->opt_out_catalog = true;
            $this->opt_out_email = true;
            $this->opt_out_telemarketing = true;
            $this->opt_out_mobile = true;
        }
    }

    public function save(): void
    {
        $this->validate([
            'customer_id' => 'required|string|max:64',
            'company_name' => 'nullable|string|max:255',
            'credit_limit' => 'numeric',
            'email' => 'nullable|email',
        ]);

        if ($this->reveal_ssn || (! str_contains($this->owner_ssn_display, '*') && filled($this->owner_ssn_display))) {
            $this->owner_ssn = $this->owner_ssn_display;
        }

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;

        $data = [
            'company_id' => auth()->user()->company_id,
            'customer_id' => $this->customer_id,
            'is_inactive' => $this->is_inactive,
            'contact' => $this->contact,
            'company_name' => $this->company_name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'telephone' => $this->telephone,
            'telephone2' => $this->telephone2,
            'mobile' => $this->mobile,
            'fax' => $this->fax,
            'email' => $this->email,
            'web_page' => $this->web_page,
            'price_level_id' => $nullableId($this->price_level_id),
            'cigarette_tax_class_id' => $nullableId($this->cigarette_tax_class_id),
            'discount_schedule_id' => $nullableId($this->discount_schedule_id),
            'purchase_limit_schedule_id' => $nullableId($this->purchase_limit_schedule_id),
            'payment_term_id' => $nullableId($this->payment_term_id),
            'sales_rep_id' => $nullableId($this->sales_rep_id),
            'delivery_route_id' => $nullableId($this->delivery_route_id),
            'lead_source' => $this->lead_source,
            'customer_category' => $this->customer_category,
            'opt_out_catalog' => $this->opt_out_catalog,
            'opt_out_email' => $this->opt_out_email,
            'opt_out_telemarketing' => $this->opt_out_telemarketing,
            'opt_out_mobile' => $this->opt_out_mobile,
            'opt_out_all' => $this->opt_out_all,
            'fein_no' => $this->fein_no,
            'account_type' => $this->account_type,
            'credit_limit' => $this->credit_limit,
            'balance' => $this->balance,
            'customer_since' => $this->customer_since ?: null,
            'last_order_on' => $this->last_order_on ?: null,
            'number_of_orders' => (int) $this->number_of_orders,
            'total_sales' => $this->total_sales,
            'bad_checks_count' => (int) $this->bad_checks_count,
            'replacements_count' => (int) $this->replacements_count,
            'returns_count' => (int) $this->returns_count,
            'messages_alerts' => $this->messages_alerts,
            'comments' => $this->comments,
            'is_tax_exempt' => $this->is_tax_exempt,
            'tax_certificate_no' => $this->tax_certificate_no,
            'tax_certificate_exp' => $this->tax_certificate_exp ?: null,
            'certificate_on_file' => $this->certificate_on_file,
            'order_day' => $this->order_day,
            'location_no' => $this->location_no,
            'drivers_accept_returns' => $this->drivers_accept_returns,
            'is_employee' => $this->is_employee,
            'owner_name' => $this->owner_name,
            'owner_ssn' => $this->owner_ssn ?: null,
            'owner_address' => $this->owner_address,
            'owner_city' => $this->owner_city,
            'owner_state' => $this->owner_state,
            'owner_zip' => $this->owner_zip,
            'owner_country' => $this->owner_country,
            'owner_telephone' => $this->owner_telephone,
            'owner_fax' => $this->owner_fax,
            'owner_email' => $this->owner_email,
        ];

        DB::transaction(function () use ($data) {
            if ($this->customer) {
                $this->customer->update($data);
                $customer = $this->customer->fresh();
            } else {
                if (empty($data['customer_since'])) {
                    $data['customer_since'] = now()->toDateString();
                }
                $customer = Customer::query()->create($data);
            }

            $customer->shippingAddresses()->delete();
            foreach (array_values($this->shippingAddresses) as $i => $row) {
                if (! filled($row['name'] ?? null) && ! filled($row['address'] ?? null)) {
                    continue;
                }
                $customer->shippingAddresses()->create([
                    'name' => $row['name'] ?: null,
                    'address' => $row['address'] ?: null,
                    'city' => $row['city'] ?: null,
                    'state' => $row['state'] ?: null,
                    'zip' => $row['zip'] ?: null,
                    'telephone' => $row['telephone'] ?: null,
                    'fax' => $row['fax'] ?: null,
                    'class' => $row['class'] ?: null,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                    'sort_order' => $i,
                ]);
            }
        });

        $this->redirect(route('sales.customers.index'), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="save" class="chief-panel bg-white flex flex-col min-h-[72vh]">
        <x-action-bar :title="$customer ? 'Edit Customer — '.$customer_id : 'New Customer'" />

        <div class="flex-1 p-3 overflow-auto">
            <div class="flex flex-wrap items-center gap-4 mb-3">
                <div class="chief-field">
                    <label>Customer ID</label>
                    <input wire:model="customer_id" class="chief-input w-40 font-mono" @disabled($customer) />
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="is_inactive" /> Customer is inactive
                </label>
                @if ($activeTab === 'account')
                    <div class="ms-auto text-base font-semibold text-slate-800">Balance: ${{ number_format((float) $balance, 2) }}</div>
                @endif
            </div>

            @if ($activeTab === 'name')
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-x-8">
                    <div class="space-y-1">
                        <div class="chief-field"><label>Contact</label><input wire:model="contact" class="chief-input w-full max-w-md" /></div>
                        <div class="chief-field"><label>Company</label><input wire:model="company_name" class="chief-input w-full max-w-md" /></div>
                        <div class="chief-field"><label>Address</label><input wire:model="address" class="chief-input w-full max-w-md" /></div>
                        <div class="chief-field"><label>City</label><input wire:model="city" class="chief-input w-48" /></div>
                        <div class="chief-field"><label>State</label><input wire:model="state" class="chief-input w-20" /></div>
                        <div class="chief-field"><label>Zip code</label><input wire:model="zip_code" class="chief-input w-28" /></div>
                        <div class="chief-field"><label>Country</label><input wire:model="country" class="chief-input w-24" /></div>
                    </div>
                    <div class="space-y-1">
                        <div class="chief-field"><label>Telephone</label><input wire:model="telephone" class="chief-input w-48" placeholder="( ) -" /></div>
                        <div class="chief-field"><label>2nd phone</label><input wire:model="telephone2" class="chief-input w-48" placeholder="( ) -" /></div>
                        <div class="chief-field"><label>Mobile</label><input wire:model="mobile" class="chief-input w-48" placeholder="( ) -" /></div>
                        <div class="chief-field"><label>Fax number</label><input wire:model="fax" class="chief-input w-48" placeholder="( ) -" /></div>
                        <div class="chief-field"><label>Email Address</label><input wire:model="email" type="email" class="chief-input w-full max-w-md" /></div>
                        <div class="chief-field"><label>Web page</label><input wire:model="web_page" class="chief-input w-full max-w-md" /></div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-sm font-semibold">Shipping Addresses</h3>
                        <button type="button" wire:click="addShipTo" class="chief-btn text-xs">Add Ship-To</button>
                    </div>
                    <div class="chief-grid border border-slate-300 overflow-auto">
                        <table>
                            <thead>
                                <tr>
                                    <th class="w-14 text-center">Primary</th>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th>City</th>
                                    <th>State</th>
                                    <th>ZIP</th>
                                    <th>Telephone</th>
                                    <th>Fax No.</th>
                                    <th>Class</th>
                                    <th class="w-16"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shippingAddresses as $i => $row)
                                    <tr>
                                        <td class="text-center"><input type="radio" name="primary_ship" wire:click="setPrimaryShipTo({{ $i }})" @checked($row['is_primary'] ?? false) /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.name" class="chief-input w-full" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.address" class="chief-input w-full min-w-[10rem]" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.city" class="chief-input w-28" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.state" class="chief-input w-16" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.zip" class="chief-input w-20" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.telephone" class="chief-input w-28" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.fax" class="chief-input w-24" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.class" class="chief-input w-20" /></td>
                                        <td><button type="button" wire:click="removeShipTo({{ $i }})" class="text-xs text-red-700 hover:underline">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @elseif ($activeTab === 'general')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10">
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>Price Level</label>
                            <select wire:model="price_level_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($priceLevels as $pl)<option value="{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Cigarette Tax</label>
                            <select wire:model="cigarette_tax_class_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($cigaretteTaxes as $ct)<option value="{{ $ct->id }}">{{ $ct->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Discount Schedule</label>
                            <select wire:model="discount_schedule_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($discountSchedules as $ds)<option value="{{ $ds->id }}">{{ $ds->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Purchase Limits</label>
                            <select wire:model="purchase_limit_schedule_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($purchaseLimits as $pl)<option value="{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Lead Source</label>
                            <select wire:model="lead_source" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($leadSources as $opt)
                                    <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Sales Rep</label>
                            <select wire:model="sales_rep_id" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($salesReps as $rep)<option value="{{ $rep->id }}">{{ $rep->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Category</label>
                            <select wire:model="customer_category" class="chief-input w-64">
                                <option value="">—</option>
                                @foreach ($customerCategories as $opt)
                                    <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <fieldset class="border border-slate-300 p-2">
                            <legend class="px-1 text-xs font-semibold">Opt-Out Options</legend>
                            <label class="flex items-center gap-2 text-sm py-0.5"><input type="checkbox" wire:model="opt_out_catalog" /> Catalog Mailings</label>
                            <label class="flex items-center gap-2 text-sm py-0.5"><input type="checkbox" wire:model="opt_out_email" /> Email Marketing</label>
                            <label class="flex items-center gap-2 text-sm py-0.5"><input type="checkbox" wire:model="opt_out_telemarketing" /> Telemarketing Calls</label>
                            <label class="flex items-center gap-2 text-sm py-0.5"><input type="checkbox" wire:model="opt_out_mobile" /> Mobile Marketing</label>
                            <label class="flex items-center gap-2 text-sm py-0.5"><input type="checkbox" wire:model.live="opt_out_all" /> All</label>
                        </fieldset>
                        <div>
                            <label class="block text-xs font-medium mb-1">Comments</label>
                            <textarea wire:model="comments" rows="6" class="chief-input w-full"></textarea>
                        </div>
                    </div>
                </div>

            @elseif ($activeTab === 'account')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10">
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>Account Type</label>
                            <select wire:model="account_type" class="chief-input w-56">
                                <option value="">—</option>
                                @foreach ($accountTypes as $opt)
                                    <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="chief-field"><label>FEIN No.</label><input wire:model="fein_no" class="chief-input w-56 font-mono" /></div>
                        <div class="chief-field">
                            <label>Payment Terms</label>
                            <select wire:model="payment_term_id" class="chief-input w-56">
                                <option value="">—</option>
                                @foreach ($paymentTerms as $pt)<option value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field"><label>Customer Since</label><input type="date" wire:model="customer_since" class="chief-input bg-slate-50" readonly /></div>
                        <div class="chief-field"><label>Last Order On</label><input type="date" wire:model="last_order_on" class="chief-input bg-slate-50" readonly /></div>
                        <div class="chief-field"><label>Number of Orders</label><input wire:model="number_of_orders" class="chief-input w-28 text-right bg-slate-50" readonly /></div>
                        <div class="chief-field"><label>Total Sales</label><input wire:model="total_sales" class="chief-input w-36 text-right bg-slate-50" readonly /></div>
                        <div class="chief-field"><label>Credit Limit</label><input wire:model.live="credit_limit" class="chief-input w-36 text-right" /></div>
                        <div class="chief-field"><label>Available Credit</label><span class="font-semibold">${{ number_format($availableCredit, 2) }}</span></div>
                        <fieldset class="border border-slate-300 p-2 mt-2">
                            <legend class="px-1 text-xs font-semibold">Negative Points</legend>
                            <div class="chief-field"><label>Bad Checks</label><input wire:model="bad_checks_count" class="chief-input w-20 text-right" /></div>
                            <div class="chief-field"><label>Replacements</label><input wire:model="replacements_count" class="chief-input w-20 text-right" /></div>
                            <div class="chief-field"><label>Returns</label><input wire:model="returns_count" class="chief-input w-20 text-right" /></div>
                        </fieldset>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold mb-1">Messages & Alerts</h3>
                        <textarea wire:model="messages_alerts" rows="12" class="chief-input w-full" placeholder="Shown when customer is selected on a sales order"></textarea>
                    </div>
                </div>

            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10">
                    <div class="space-y-1">
                        <div class="chief-field">
                            <label>Order Day</label>
                            <select wire:model="order_day" class="chief-input w-40">
                                <option value="">—</option>
                                @foreach ($orderDays as $day)<option value="{{ $day }}">{{ $day }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field">
                            <label>Delivery Route</label>
                            <select wire:model="delivery_route_id" class="chief-input w-56">
                                <option value="">—</option>
                                @foreach ($routes as $route)<option value="{{ $route->id }}">{{ $route->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="chief-field"><label>Location No.</label><input wire:model="location_no" class="chief-input w-40" /></div>
                        <label class="inline-flex items-center gap-2 text-sm ms-[9.5rem] py-1"><input type="checkbox" wire:model="drivers_accept_returns" /> Drivers Accept Returns</label>
                        <label class="inline-flex items-center gap-2 text-sm ms-[9.5rem] py-1"><input type="checkbox" wire:model.live="is_tax_exempt" /> Customer is Tax Exempt</label>
                        @if ($is_tax_exempt)
                            <div class="chief-field"><label>Certificate No.</label><input wire:model="tax_certificate_no" class="chief-input w-56" /></div>
                            <div class="chief-field"><label>Exp. Date</label><input type="date" wire:model="tax_certificate_exp" class="chief-input" /></div>
                            <label class="inline-flex items-center gap-2 text-sm ms-[9.5rem] py-1"><input type="checkbox" wire:model="certificate_on_file" /> Certificate on File</label>
                        @endif
                        <label class="inline-flex items-center gap-2 text-sm ms-[9.5rem] py-1"><input type="checkbox" wire:model="is_employee" /> Customer is an Employee</label>
                    </div>
                    <div class="space-y-1">
                        <fieldset class="border border-slate-300 p-2">
                            <legend class="px-1 text-xs font-semibold">Owner / Grantor</legend>
                            <div class="chief-field"><label>Name</label><input wire:model="owner_name" class="chief-input w-full max-w-xs" /></div>
                            <div class="chief-field">
                                <label>SSN</label>
                                <input wire:model.live="owner_ssn_display" class="chief-input w-40 font-mono" @disabled(! $reveal_ssn && filled($owner_ssn) && str_contains($owner_ssn_display, '*')) />
                                <button type="button" wire:click="toggleRevealSsn" class="chief-btn text-xs">{{ $reveal_ssn ? 'Hide' : 'Reveal' }}</button>
                            </div>
                            <div class="chief-field"><label>Address</label><input wire:model="owner_address" class="chief-input w-full max-w-xs" /></div>
                            <div class="chief-field"><label>City</label><input wire:model="owner_city" class="chief-input w-40" /></div>
                            <div class="chief-field"><label>State</label><input wire:model="owner_state" class="chief-input w-16" /></div>
                            <div class="chief-field"><label>Zip</label><input wire:model="owner_zip" class="chief-input w-24" /></div>
                            <div class="chief-field"><label>Country</label><input wire:model="owner_country" class="chief-input w-24" /></div>
                            <div class="chief-field"><label>Telephone</label><input wire:model="owner_telephone" class="chief-input w-40" /></div>
                            <div class="chief-field"><label>Fax</label><input wire:model="owner_fax" class="chief-input w-40" /></div>
                            <div class="chief-field"><label>Email</label><input wire:model="owner_email" class="chief-input w-full max-w-xs" /></div>
                        </fieldset>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between border-t border-slate-300 bg-slate-100 px-1 flex-wrap gap-2">
            <div class="flex flex-wrap">
                @foreach ($tabs as $key => $label)
                    <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                        @class(['px-3 py-1.5 text-sm border-r border-slate-300 whitespace-nowrap', 'bg-white font-semibold text-sky-800' => $activeTab === $key, 'text-slate-600 hover:bg-slate-200' => $activeTab !== $key])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex gap-2 py-2 pe-2">
                <a href="{{ route('sales.customers.index') }}" wire:navigate class="chief-btn">Cancel</a>
                <button type="submit" class="chief-btn-primary">Save Changes</button>
            </div>
        </div>
    </form>
</div>
