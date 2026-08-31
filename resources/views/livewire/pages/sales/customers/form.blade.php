<?php

use App\Models\CigaretteTaxClass;
use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\CustomerLookupOption;
use App\Models\CustomerShippingAddress;
use App\Models\DiscountSchedule;
use App\Models\PaymentTerm;
use App\Models\PriceLevel;
use App\Models\PurchaseLimitSchedule;
use App\Models\RouteLookup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Customer')] class extends Component
{
    public ?Customer $customer = null;

    public bool $viewMode = false;

    public string $activeTab = 'name';

    public string $customer_id = '';

    public bool $is_inactive = false;

    public bool $portal_active = false;

    public string $portal_email = '';

    public string $portal_password = '';

    public bool $portal_has_password = false;

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
        $this->viewMode = request()->routeIs('sales.customers.show');

        if ($customer?->exists) {
            abort_unless($customer->company_id === auth()->user()->company_id, 403);
            $this->customer = $customer->load('shippingAddresses');

            $stringFields = [
                'customer_id', 'contact', 'company_name', 'address', 'city', 'state',
                'zip_code', 'country', 'telephone', 'telephone2', 'mobile', 'fax', 'email', 'web_page',
                'lead_source', 'customer_category', 'fein_no', 'account_type',
                'messages_alerts', 'comments', 'tax_certificate_no', 'order_day', 'location_no',
                'owner_name', 'owner_address', 'owner_city', 'owner_state', 'owner_zip',
                'owner_country', 'owner_telephone', 'owner_fax', 'owner_email',
            ];
            foreach ($stringFields as $field) {
                $this->{$field} = (string) ($customer->{$field} ?? '');
            }

            $this->is_inactive = (bool) $customer->is_inactive;
            $this->portal_active = (bool) $customer->portal_active;
            $this->portal_email = (string) ($customer->portal_email ?? '');
            $this->portal_has_password = filled($customer->portal_password);
            $this->portal_password = '';
            $this->opt_out_catalog = (bool) $customer->opt_out_catalog;
            $this->opt_out_email = (bool) $customer->opt_out_email;
            $this->opt_out_telemarketing = (bool) $customer->opt_out_telemarketing;
            $this->opt_out_mobile = (bool) $customer->opt_out_mobile;
            $this->opt_out_all = (bool) $customer->opt_out_all;
            $this->is_tax_exempt = (bool) $customer->is_tax_exempt;
            $this->certificate_on_file = (bool) $customer->certificate_on_file;
            $this->drivers_accept_returns = (bool) $customer->drivers_accept_returns;
            $this->is_employee = (bool) $customer->is_employee;

            $this->price_level_id = $customer->price_level_id !== null ? (int) $customer->price_level_id : null;
            $this->cigarette_tax_class_id = $customer->cigarette_tax_class_id !== null ? (int) $customer->cigarette_tax_class_id : null;
            $this->discount_schedule_id = $customer->discount_schedule_id !== null ? (int) $customer->discount_schedule_id : null;
            $this->purchase_limit_schedule_id = $customer->purchase_limit_schedule_id !== null ? (int) $customer->purchase_limit_schedule_id : null;
            $this->payment_term_id = $customer->payment_term_id !== null ? (int) $customer->payment_term_id : null;
            $this->sales_rep_id = $customer->sales_rep_id !== null ? (int) $customer->sales_rep_id : null;
            $this->delivery_route_id = $customer->delivery_route_id !== null ? (int) $customer->delivery_route_id : null;

            $this->credit_limit = number_format((float) ($customer->credit_limit ?? 0), 2, '.', '');
            $this->balance = number_format((float) ($customer->balance ?? 0), 2, '.', '');
            $this->number_of_orders = (string) (int) ($customer->number_of_orders ?? 0);
            $this->total_sales = number_format((float) ($customer->total_sales ?? 0), 2, '.', '');
            $this->bad_checks_count = (string) (int) ($customer->bad_checks_count ?? 0);
            $this->replacements_count = (string) (int) ($customer->replacements_count ?? 0);
            $this->returns_count = (string) (int) ($customer->returns_count ?? 0);

            $this->tax_certificate_exp = optional($customer->tax_certificate_exp)?->format('Y-m-d') ?? '';
            $this->customer_since = optional($customer->customer_since)?->format('Y-m-d');
            $this->last_order_on = optional($customer->last_order_on)?->format('Y-m-d');
            $this->owner_ssn = (string) ($customer->owner_ssn ?? '');
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

        $openCreditBalance = 0.0;
        if ($this->customer?->exists) {
            $openCreditBalance = (float) CreditMemo::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $this->customer->id)
                ->where('status', 'Open')
                ->get()
                ->sum(fn (CreditMemo $m) => $m->remaining_amount);
        }

        return [
            'priceLevels' => PriceLevel::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'cigaretteTaxes' => CigaretteTaxClass::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'discountSchedules' => DiscountSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'purchaseLimits' => PurchaseLimitSchedule::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'paymentTerms' => PaymentTerm::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'salesReps' => User::assignableSalesRepsQuery($companyId, $this->sales_rep_id)->get(),
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
            'openCreditBalance' => round($openCreditBalance, 2),
        ];
    }

    public function addShipTo(): void
    {
        abort_if($this->viewMode, 403);

        $this->shippingAddresses[] = [
            'name' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => '',
            'telephone' => '', 'fax' => '', 'class' => '', 'is_primary' => false,
        ];
    }

    public function removeShipTo(int $index): void
    {
        abort_if($this->viewMode, 403);

        unset($this->shippingAddresses[$index]);
        $this->shippingAddresses = array_values($this->shippingAddresses);
        if ($this->shippingAddresses === []) {
            $this->addShipTo();
        }
    }

    public function setPrimaryShipTo(int $index): void
    {
        abort_if($this->viewMode, 403);

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
        abort_if($this->viewMode, 403);

        $portalLoginErrors = [];
        if ($this->portalAppOn()) {
            $hasEmail = trim($this->portal_email) !== '' || trim($this->email) !== '';
            $hasMobile = trim(preg_replace('/\D+/', '', $this->mobile) ?? '') !== '';
            if (! $hasEmail && ! $hasMobile) {
                $portalLoginErrors = [
                    'email' => 'Customer App Active requires Email or Mobile.',
                    'mobile' => 'Customer App Active requires Email or Mobile.',
                ];
            }
        }

        try {
            $this->validate([
                'customer_id' => 'required|string|max:64',
                'company_name' => 'required|string|max:255',
                'contact' => 'nullable|string|max:255',
                'telephone' => 'nullable|string|max:40',
                'email' => 'nullable|email|max:255',
                'portal_email' => 'nullable|email|max:255',
                'portal_password' => $this->portalAppOn() && ! $this->portal_has_password ? 'required|string|min:4|max:72' : 'nullable|string|min:4|max:72',
                'credit_limit' => 'nullable|numeric|min:0',
                'fein_no' => 'nullable|string|max:32',
                'tax_certificate_no' => $this->is_tax_exempt ? 'required|string|max:64' : 'nullable|string|max:64',
                'tax_certificate_exp' => $this->is_tax_exempt ? 'required|date' : 'nullable|date',
            ], [
                'customer_id.required' => 'Customer ID is required.',
                'company_name.required' => 'Company name is required.',
                'email.email' => 'Enter a valid email address.',
                'portal_password.required' => 'Set a password for Customer App login.',
                'tax_certificate_no.required' => 'Certificate No. is required for tax-exempt customers.',
                'tax_certificate_exp.required' => 'Certificate expiry date is required for tax-exempt customers.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $merged = $e->errors();
            foreach ($portalLoginErrors as $field => $message) {
                $merged[$field] = array_values(array_unique(array_merge($merged[$field] ?? [], [$message])));
            }
            $keys = array_keys($merged);
            if (array_intersect($keys, ['customer_id', 'company_name', 'contact', 'telephone', 'email', 'mobile', 'portal_email', 'portal_password'])) {
                $this->activeTab = 'name';
            } elseif (array_intersect($keys, ['fein_no', 'credit_limit'])) {
                $this->activeTab = 'account';
            } elseif (array_intersect($keys, ['tax_certificate_no', 'tax_certificate_exp'])) {
                $this->activeTab = 'other';
            }
            throw \Illuminate\Validation\ValidationException::withMessages($merged);
        }

        if ($portalLoginErrors !== []) {
            $this->activeTab = 'name';
            throw \Illuminate\Validation\ValidationException::withMessages($portalLoginErrors);
        }

        if ($this->reveal_ssn || (! str_contains($this->owner_ssn_display, '*') && filled($this->owner_ssn_display))) {
            $this->owner_ssn = $this->owner_ssn_display;
        }

        $nullableId = static fn ($v) => filled($v) ? (int) $v : null;

        $data = [
            'company_id' => auth()->user()->company_id,
            'customer_id' => $this->customer_id,
            'is_inactive' => $this->is_inactive,
            'portal_active' => in_array($this->portal_active, [true, 1, '1'], true),
            'portal_email' => trim($this->portal_email) !== '' ? trim($this->portal_email) : (trim($this->email) ?: null),
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
        if ($this->portalAppOn() && filled($this->portal_password)) {
            $data['portal_password'] = Hash::make($this->portal_password);
        }

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

        Cache::forget('orders.filter_customers.'.(int) auth()->user()->company_id);
        Cache::forget('orders.filter_customers.v2.'.(int) auth()->user()->company_id);

        $this->redirect(route('sales.customers.index'), navigate: true);
    }

    public function portalAppOn(): bool
    {
        return in_array($this->portal_active, [true, 1, '1'], true);
    }

    public function refreshForm(): void
    {
        session()->flash('status', 'Refresh is not used until the customer is saved.');
    }

    public function cancelAction(): mixed
    {
        return $this->redirect(route('sales.customers.index'), navigate: true);
    }
}; ?>

<div class="desk-page entity-page">
    <form wire:submit="save" class="desk-main entity-form" @class(['entity-form-readonly' => $viewMode])>
        <x-action-bar :title="$viewMode
            ? 'View Customer — '.$customer_id
            : ($customer ? 'Edit Customer — '.$customer_id : 'New Customer')">
            <x-slot:menu>
                <x-action-item label="Save Changes" kbd="Ctrl+S" wire:click="save" :disabled="$viewMode" />
                <x-action-item label="Refresh" :disabled="true" sep />
                <x-action-item label="Cancel" kbd="Ctrl+Q" sep wire:click="cancelAction" />
            </x-slot:menu>
        </x-action-bar>

        <fieldset class="entity-body" @disabled($viewMode)>
            <div class="entity-header">
                <div class="so-form-row so-form-row-pair entity-header-row">
                    <label class="so-form-lbl so-field-req" for="customer_id">Customer ID</label>
                    <input id="customer_id" wire:model="customer_id" class="so-input font-mono @error('customer_id') is-invalid @enderror" @disabled($customer) />
                    <span class="so-form-lbl">Status</span>
                    <div class="entity-status-btns">
                        <button type="button" wire:click="$set('is_inactive', false)" @class(['desk-btn desk-btn-sm', 'is-on' => ! $is_inactive])>Active</button>
                        <button type="button" wire:click="$set('is_inactive', true)" @class(['desk-btn desk-btn-sm', 'is-on-danger' => $is_inactive])>Inactive</button>
                    </div>
                </div>
                @error('customer_id') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                <div class="so-form-row so-form-row-pair entity-header-row" style="margin-top:.75rem">
                    <span class="so-form-lbl">Customer App</span>
                    <div class="entity-status-btns" role="radiogroup" aria-label="Customer App status">
                        <label class="desk-btn desk-btn-sm {{ $this->portalAppOn() ? 'is-on' : '' }}" style="cursor:pointer">
                            <input type="radio" wire:model.live="portal_active" value="1" style="margin-right:.35rem"> Active
                        </label>
                        <label class="desk-btn desk-btn-sm {{ ! $this->portalAppOn() ? 'is-on-danger' : '' }}" style="cursor:pointer">
                            <input type="radio" wire:model.live="portal_active" value="0" style="margin-right:.35rem"> Inactive
                        </label>
                    </div>
                </div>
                @if ($this->portalAppOn())
                    <p class="text-xs text-slate-500" style="margin:.35rem 0 .5rem">Sign-in at <strong>/customer</strong> uses Email or Mobile (at least one is required) plus this password.</p>
                    <div class="so-form-row">
                        <label class="so-form-lbl" for="portal_email">App login email</label>
                        <input id="portal_email" type="email" wire:model="portal_email" class="so-input" placeholder="Leave blank to use Email" />
                    </div>
                    @error('portal_email') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                    <div class="so-form-row">
                        <label class="so-form-lbl so-field-req" for="portal_password">App password</label>
                        <input id="portal_password" type="password" wire:model="portal_password" class="so-input" autocomplete="new-password" placeholder="{{ $portal_has_password ? 'Leave blank to keep current' : 'Set password' }}" />
                    </div>
                    @error('portal_password') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                @endif
                @if ($activeTab === 'account')
                    <div class="entity-balance" style="display:flex;flex-wrap:wrap;gap:0.75rem 1.5rem;align-items:baseline">
                        <span>Balance owed: <strong>${{ number_format((float) $balance, 2) }}</strong></span>
                        @if ($openCreditBalance > 0.0001)
                            <span>Open credit: <strong style="color:#0a7a32">${{ number_format($openCreditBalance, 2) }}</strong>
                                <a href="{{ route('sales.credit-memos.index') }}" class="desk-link" style="font-size:0.85em;margin-left:0.35rem">View credit memos</a>
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            @if ($activeTab === 'name')
                <div class="entity-grid-2">
                    <div class="entity-col">
                        <div class="so-form-row"><label class="so-form-lbl" for="contact">Contact</label><input id="contact" wire:model="contact" class="so-input" /></div>
                        <div class="so-form-row">
                            <label class="so-form-lbl so-field-req" for="company_name">Company</label>
                            <input id="company_name" wire:model="company_name" class="so-input @error('company_name') is-invalid @enderror" />
                        </div>
                        @error('company_name') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        <div class="so-form-row"><label class="so-form-lbl" for="address">Address</label><input id="address" wire:model="address" class="so-input" /></div>
                        <div class="so-form-row so-form-row-city">
                            <label class="so-form-lbl" for="city">City</label>
                            <input id="city" wire:model="city" class="so-input" />
                            <label class="so-form-lbl so-form-lbl-sm" for="state">State</label>
                            <input id="state" wire:model="state" class="so-input so-w-state" />
                            <label class="so-form-lbl so-form-lbl-sm" for="zip_code">ZIP</label>
                            <input id="zip_code" wire:model="zip_code" class="so-input so-w-zip" />
                        </div>
                        <div class="so-form-row"><label class="so-form-lbl" for="country">Country</label><input id="country" wire:model="country" class="so-input" style="max-width:6rem" /></div>
                    </div>
                    <div class="entity-col">
                        <div class="so-form-row"><label class="so-form-lbl" for="telephone">Telephone</label><input id="telephone" wire:model="telephone" class="so-input" placeholder="( ) -" /></div>
                        <div class="so-form-row"><label class="so-form-lbl" for="telephone2">2nd phone</label><input id="telephone2" wire:model="telephone2" class="so-input" placeholder="( ) -" /></div>
                        <div class="so-form-row">
                            <label class="so-form-lbl {{ $this->portalAppOn() ? 'so-field-req' : '' }}" for="mobile">Mobile</label>
                            <input id="mobile" wire:model="mobile" class="so-input @error('mobile') is-invalid @enderror" placeholder="( ) -" />
                        </div>
                        @error('mobile') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        <div class="so-form-row"><label class="so-form-lbl" for="fax">Fax number</label><input id="fax" wire:model="fax" class="so-input" placeholder="( ) -" /></div>
                        <div class="so-form-row">
                            <label class="so-form-lbl {{ $this->portalAppOn() ? 'so-field-req' : '' }}" for="email">Email</label>
                            <input id="email" wire:model="email" type="email" class="so-input @error('email') is-invalid @enderror" />
                        </div>
                        @error('email') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        <div class="so-form-row"><label class="so-form-lbl" for="web_page">Web page</label><input id="web_page" wire:model="web_page" class="so-input" /></div>
                    </div>
                </div>

                <div class="entity-section">
                    <div class="entity-section-head">
                        <h3 class="entity-section-title">Shipping Addresses</h3>
                        @unless ($viewMode)
                            <button type="button" wire:click="addShipTo" class="desk-btn desk-btn-sm">Add Ship-To</button>
                        @endunless
                    </div>
                    <div class="desk-grid entity-ship-grid">
                        <table class="desk-table">
                            <thead>
                                <tr>
                                    <th class="text-center">Primary</th>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th>City</th>
                                    <th>State</th>
                                    <th>ZIP</th>
                                    <th>Telephone</th>
                                    <th>Fax No.</th>
                                    <th>Class</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shippingAddresses as $i => $row)
                                    <tr>
                                        <td class="text-center"><input type="radio" name="primary_ship" wire:click="setPrimaryShipTo({{ $i }})" @checked($row['is_primary'] ?? false) /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.name" class="so-input ship-col-name" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.address" class="so-input ship-col-address" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.city" class="so-input ship-col-city" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.state" class="so-input ship-col-state" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.zip" class="so-input ship-col-zip" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.telephone" class="so-input ship-col-phone" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.fax" class="so-input ship-col-fax" /></td>
                                        <td><input wire:model="shippingAddresses.{{ $i }}.class" class="so-input ship-col-class" /></td>
                                        <td>
                                            @unless ($viewMode)
                                                <button type="button" wire:click="removeShipTo({{ $i }})" class="desk-btn desk-btn-sm">Remove</button>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @elseif ($activeTab === 'general')
                <div class="entity-grid-2">
                    <div class="entity-col">
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="price_level_id">Price Level</label>
                            <select id="price_level_id" wire:model="price_level_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($priceLevels as $pl)<option value="{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="cigarette_tax_class_id">Cigarette Tax</label>
                            <select id="cigarette_tax_class_id" wire:model="cigarette_tax_class_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($cigaretteTaxes as $ct)<option value="{{ $ct->id }}">{{ $ct->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="discount_schedule_id">Discount Sched.</label>
                            <select id="discount_schedule_id" wire:model="discount_schedule_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($discountSchedules as $ds)<option value="{{ $ds->id }}">{{ $ds->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="purchase_limit_schedule_id">Purchase Limits</label>
                            <select id="purchase_limit_schedule_id" wire:model="purchase_limit_schedule_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($purchaseLimits as $pl)<option value="{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="lead_source">Lead Source</label>
                            <select id="lead_source" wire:model="lead_source" class="so-input">
                                <option value="">—</option>
                                @foreach ($leadSources as $opt)
                                    <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="sales_rep_id">Sales Rep</label>
                            <select id="sales_rep_id" wire:model="sales_rep_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($salesReps as $rep)
                                    <option value="{{ $rep->id }}">{{ $rep->name }}@if($rep->role) — {{ $rep->role->label }}@endif@if(!$rep->is_active) (inactive)@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="customer_category">Category</label>
                            <select id="customer_category" wire:model="customer_category" class="so-input">
                                <option value="">—</option>
                                @foreach ($customerCategories as $opt)
                                    <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="entity-col">
                        <fieldset class="entity-fieldset">
                            <legend>Opt-Out Options</legend>
                            <label class="entity-check"><input type="checkbox" wire:model="opt_out_catalog" /> Catalog Mailings</label>
                            <label class="entity-check"><input type="checkbox" wire:model="opt_out_email" /> Email Marketing</label>
                            <label class="entity-check"><input type="checkbox" wire:model="opt_out_telemarketing" /> Telemarketing Calls</label>
                            <label class="entity-check"><input type="checkbox" wire:model="opt_out_mobile" /> Mobile Marketing</label>
                            <label class="entity-check"><input type="checkbox" wire:model.live="opt_out_all" /> All</label>
                        </fieldset>
                        <div class="so-form-row so-form-row-top">
                            <label class="so-form-lbl" for="comments">Comments</label>
                            <textarea id="comments" wire:model="comments" rows="6" class="so-input so-input-area"></textarea>
                        </div>
                    </div>
                </div>

            @elseif ($activeTab === 'account')
                <div class="entity-grid-2">
                    <div class="entity-col">
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="account_type">Account Type</label>
                            <select id="account_type" wire:model="account_type" class="so-input">
                                <option value="">—</option>
                                @foreach ($accountTypes as $opt)
                                    <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="fein_no">FEIN No.</label>
                            <input id="fein_no" wire:model="fein_no" class="so-input font-mono @error('fein_no') is-invalid @enderror" />
                        </div>
                        @error('fein_no') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="payment_term_id">Payment Terms</label>
                            <select id="payment_term_id" wire:model="payment_term_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($paymentTerms as $pt)<option value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row"><label class="so-form-lbl" for="customer_since">Customer Since</label><input id="customer_since" type="date" wire:model="customer_since" class="so-input" readonly /></div>
                        <div class="so-form-row"><label class="so-form-lbl" for="last_order_on">Last Order On</label><input id="last_order_on" type="date" wire:model="last_order_on" class="so-input" readonly /></div>
                        <div class="so-form-row"><label class="so-form-lbl" for="number_of_orders">No. of Orders</label><input id="number_of_orders" wire:model="number_of_orders" class="so-input text-right" readonly /></div>
                        <div class="so-form-row"><label class="so-form-lbl" for="total_sales">Total Sales</label><input id="total_sales" wire:model="total_sales" class="so-input text-right" readonly /></div>
                        <div class="so-form-row"><label class="so-form-lbl" for="credit_limit">Credit Limit</label><input id="credit_limit" wire:model.live="credit_limit" class="so-input text-right" /></div>
                        <div class="so-form-row"><span class="so-form-lbl">Available Credit</span><span class="entity-value">${{ number_format($availableCredit, 2) }}</span></div>
                        <div class="so-form-row">
                            <span class="so-form-lbl">Open Credit</span>
                            <span class="entity-value" @if ($openCreditBalance > 0.0001) style="color:#0a7a32;font-weight:600" @endif>
                                ${{ number_format($openCreditBalance, 2) }}
                            </span>
                        </div>
                        @if ($openCreditBalance > 0.0001)
                            <p class="desk-muted" style="margin:0 0 0.75rem;font-size:0.875rem;max-width:28rem">
                                Extra payments / credit memos not yet applied to an invoice. Apply them from
                                <a href="{{ route('sales.invoices.index') }}" class="desk-link">Invoices</a>
                                or review under
                                <a href="{{ route('sales.credit-memos.index') }}" class="desk-link">Credit Memos</a>
                                (Open).
                            </p>
                        @endif
                        <fieldset class="entity-fieldset">
                            <legend>Negative Points</legend>
                            <div class="so-form-row"><label class="so-form-lbl" for="bad_checks_count">Bad Checks</label><input id="bad_checks_count" wire:model="bad_checks_count" class="so-input text-right" style="max-width:5rem" /></div>
                            <div class="so-form-row"><label class="so-form-lbl" for="replacements_count">Replacements</label><input id="replacements_count" wire:model="replacements_count" class="so-input text-right" style="max-width:5rem" /></div>
                            <div class="so-form-row"><label class="so-form-lbl" for="returns_count">Returns</label><input id="returns_count" wire:model="returns_count" class="so-input text-right" style="max-width:5rem" /></div>
                        </fieldset>
                    </div>
                    <div class="entity-col">
                        <div class="so-form-row so-form-row-top">
                            <label class="so-form-lbl" for="messages_alerts">Messages & Alerts</label>
                            <textarea id="messages_alerts" wire:model="messages_alerts" rows="14" class="so-input so-input-area" placeholder="Shown when customer is selected on a sales order"></textarea>
                        </div>
                    </div>
                </div>

            @else
                <div class="entity-grid-2">
                    <div class="entity-col">
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="order_day">Order Day</label>
                            <select id="order_day" wire:model="order_day" class="so-input">
                                <option value="">—</option>
                                @foreach ($orderDays as $day)<option value="{{ $day }}">{{ $day }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row">
                            <label class="so-form-lbl" for="delivery_route_id">Delivery Route</label>
                            <select id="delivery_route_id" wire:model="delivery_route_id" class="so-input">
                                <option value="">—</option>
                                @foreach ($routes as $route)<option value="{{ $route->id }}">{{ $route->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="so-form-row"><label class="so-form-lbl" for="location_no">Location No.</label><input id="location_no" wire:model="location_no" class="so-input" /></div>
                        <div class="so-form-row"><span class="so-form-lbl"></span><label class="entity-check"><input type="checkbox" wire:model="drivers_accept_returns" /> Drivers Accept Returns</label></div>
                        <div class="so-form-row"><span class="so-form-lbl"></span><label class="entity-check"><input type="checkbox" wire:model.live="is_tax_exempt" /> Customer is Tax Exempt</label></div>
                        @if ($is_tax_exempt)
                            <div class="so-form-row">
                                <label class="so-form-lbl so-field-req" for="tax_certificate_no">Certificate No.</label>
                                <input id="tax_certificate_no" wire:model="tax_certificate_no" class="so-input @error('tax_certificate_no') is-invalid @enderror" />
                            </div>
                            @error('tax_certificate_no') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row">
                                <label class="so-form-lbl so-field-req" for="tax_certificate_exp">Exp. Date</label>
                                <input id="tax_certificate_exp" type="date" wire:model="tax_certificate_exp" class="so-input @error('tax_certificate_exp') is-invalid @enderror" />
                            </div>
                            @error('tax_certificate_exp') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
                            <div class="so-form-row"><span class="so-form-lbl"></span><label class="entity-check"><input type="checkbox" wire:model="certificate_on_file" /> Certificate on File</label></div>
                        @endif
                        <div class="so-form-row"><span class="so-form-lbl"></span><label class="entity-check"><input type="checkbox" wire:model="is_employee" /> Customer is an Employee</label></div>
                    </div>
                    <div class="entity-col">
                        <fieldset class="entity-fieldset">
                            <legend>Owner / Grantor</legend>
                            <div class="so-form-row"><label class="so-form-lbl" for="owner_name">Name</label><input id="owner_name" wire:model="owner_name" class="so-input" /></div>
                            <div class="so-form-row">
                                <label class="so-form-lbl" for="owner_ssn_display">SSN</label>
                                <div class="so-lookup-row">
                                    <input id="owner_ssn_display" wire:model.live="owner_ssn_display" class="so-input font-mono" @disabled(! $reveal_ssn && filled($owner_ssn) && str_contains($owner_ssn_display, '*')) />
                                    <button type="button" wire:click="toggleRevealSsn" class="desk-btn desk-btn-sm">{{ $reveal_ssn ? 'Hide' : 'Reveal' }}</button>
                                </div>
                            </div>
                            <div class="so-form-row"><label class="so-form-lbl" for="owner_address">Address</label><input id="owner_address" wire:model="owner_address" class="so-input" /></div>
                            <div class="so-form-row so-form-row-city">
                                <label class="so-form-lbl" for="owner_city">City</label>
                                <input id="owner_city" wire:model="owner_city" class="so-input" />
                                <label class="so-form-lbl so-form-lbl-sm" for="owner_state">State</label>
                                <input id="owner_state" wire:model="owner_state" class="so-input so-w-state" />
                                <label class="so-form-lbl so-form-lbl-sm" for="owner_zip">ZIP</label>
                                <input id="owner_zip" wire:model="owner_zip" class="so-input so-w-zip" />
                            </div>
                            <div class="so-form-row"><label class="so-form-lbl" for="owner_country">Country</label><input id="owner_country" wire:model="owner_country" class="so-input" style="max-width:6rem" /></div>
                            <div class="so-form-row"><label class="so-form-lbl" for="owner_telephone">Telephone</label><input id="owner_telephone" wire:model="owner_telephone" class="so-input" /></div>
                            <div class="so-form-row"><label class="so-form-lbl" for="owner_fax">Fax</label><input id="owner_fax" wire:model="owner_fax" class="so-input" /></div>
                            <div class="so-form-row"><label class="so-form-lbl" for="owner_email">Email</label><input id="owner_email" wire:model="owner_email" class="so-input" /></div>
                        </fieldset>
                    </div>
                </div>
            @endif
        </fieldset>

        <div class="entity-footer">
            <div class="entity-tabs" role="tablist" aria-label="Customer sections">
                @foreach ($tabs as $key => $label)
                    <button
                        type="button"
                        role="tab"
                        wire:click="$set('activeTab', '{{ $key }}')"
                        aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                        @class(['entity-tab', 'is-active' => $activeTab === $key])
                    >{{ $label }}</button>
                @endforeach
            </div>
            <div class="entity-footer-actions">
                <a href="{{ route('sales.customers.index') }}" wire:navigate class="desk-btn">{{ $viewMode ? 'Close' : 'Cancel' }}</a>
                @if ($viewMode && $customer)
                    <a href="{{ route('sales.customers.edit', $customer) }}" wire:navigate class="desk-btn desk-btn-primary">Edit Customer</a>
                @elseif (! $viewMode)
                    <button type="submit" class="desk-btn desk-btn-primary">Save Changes</button>
                @endif
            </div>
        </div>
    </form>
</div>
