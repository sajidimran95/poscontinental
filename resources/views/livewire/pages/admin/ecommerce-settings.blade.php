<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\EcomContactMessage;
use App\Models\EcomPromotion;
use App\Models\SalesOrder;
use App\Support\Store\StoreCatalog;
use App\Support\Store\WholesaleAccount;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Ecommerce Settings')] class extends Component
{
    public bool $ecommerce_enabled = false;

    public string $store_name = '';

    public string $tagline = '';

    public string $phone = '';

    public string $email = '';

    public string $hours = '';

    public string $statusMessage = '';

    /** @var list<int> Store header categories, in display order. */
    public array $nav_category_ids = [];

    public bool $nav_custom = false;

    public string $nav_add_id = '';

    public function mount(): void
    {
        $company = auth()->user()->company;
        $saved = array_values(array_filter(array_map('intval', (array) $company->ecommerce_nav_category_ids)));
        $this->nav_custom = $saved !== [];
        $this->nav_category_ids = $this->nav_custom
            ? $saved
            : StoreCatalog::navCategories((int) $company->id, StoreCatalog::HEADER_CATEGORY_LIMIT)->pluck('id')->all();
        $this->ecommerce_enabled = (bool) $company->ecommerce_enabled;
        $this->store_name = (string) ($company->ecommerce_store_name ?? '');
        $this->tagline = (string) ($company->ecommerce_tagline ?? '');
        $this->phone = (string) ($company->ecommerce_phone ?? '');
        $this->email = (string) ($company->ecommerce_email ?? '');
        $this->hours = (string) ($company->ecommerce_hours ?? '');
    }

    public function addNavCategory(): void
    {
        $id = (int) $this->nav_add_id;
        $this->nav_add_id = '';
        if ($id <= 0 || in_array($id, $this->nav_category_ids, true) || count($this->nav_category_ids) >= StoreCatalog::HEADER_CATEGORY_LIMIT) {
            return;
        }
        $this->nav_category_ids[] = $id;
        $this->nav_custom = true;
    }

    public function removeNavCategory(int $index): void
    {
        unset($this->nav_category_ids[$index]);
        $this->nav_category_ids = array_values($this->nav_category_ids);
        $this->nav_custom = true;
    }

    public function moveNavCategory(int $index, int $step): void
    {
        $to = $index + $step;
        if (! isset($this->nav_category_ids[$index], $this->nav_category_ids[$to])) {
            return;
        }
        [$this->nav_category_ids[$index], $this->nav_category_ids[$to]] = [$this->nav_category_ids[$to], $this->nav_category_ids[$index]];
        $this->nav_custom = true;
    }

    public function useAutomaticNav(): void
    {
        $this->nav_custom = false;
        $this->nav_category_ids = StoreCatalog::navCategories((int) auth()->user()->company_id, StoreCatalog::HEADER_CATEGORY_LIMIT)->pluck('id')->all();
    }

    public function save(): void
    {
        $this->statusMessage = '';
        $storeCategoryIds = StoreCatalog::categories((int) auth()->user()->company_id)->pluck('id')->all();
        $this->nav_category_ids = array_values(array_unique(array_filter(
            array_map('intval', $this->nav_category_ids),
            fn (int $id) => in_array($id, $storeCategoryIds, true),
        )));
        $this->validate([
            'nav_category_ids' => ['array', 'max:'.StoreCatalog::HEADER_CATEGORY_LIMIT],
            'ecommerce_enabled' => ['boolean'],
            'store_name' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:191'],
            'hours' => ['nullable', 'string', 'max:160'],
        ]);

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $company->update([
            'ecommerce_enabled' => $this->ecommerce_enabled,
            'ecommerce_store_name' => trim($this->store_name) ?: null,
            'ecommerce_tagline' => trim($this->tagline) ?: null,
            'ecommerce_phone' => trim($this->phone) ?: null,
            'ecommerce_email' => trim($this->email) ?: null,
            'ecommerce_hours' => trim($this->hours) ?: null,
            'ecommerce_nav_category_ids' => $this->nav_custom && $this->nav_category_ids !== [] ? array_values($this->nav_category_ids) : null,
        ]);
        if ($this->nav_category_ids === []) {
            $this->useAutomaticNav();
        }
        StoreCatalog::forgetCache((int) $company->id);

        $this->statusMessage = 'Ecommerce settings saved: online store is '.($this->ecommerce_enabled ? 'ON' : 'OFF').'.';
    }

    public function with(): array
    {
        $companyId = (int) auth()->user()->company_id;
        $user = auth()->user();

        $storeCategories = StoreCatalog::categories($companyId)->keyBy('id');

        return [
            'sellableCount' => StoreCatalog::query($companyId)->count(),
            'storeCategories' => $storeCategories,
            'navLimit' => StoreCatalog::HEADER_CATEGORY_LIMIT,
            'shortcuts' => array_values(array_filter([
                [
                    'title' => 'Ecommerce orders',
                    'value' => SalesOrder::query()->where('company_id', $companyId)->where('order_source', SalesOrder::SOURCE_ECOMMERCE)->whereNotIn('status', ['Invoiced', 'Cancelled', 'Void', 'Closed'])->count(),
                    'sub' => 'open in Sales Orders (source “Ecommerce”)',
                    'route' => 'sales.orders.index',
                    'feature' => 'sales.orders',
                ],
                [
                    'title' => 'Wholesale applications',
                    'value' => Customer::query()->where('company_id', $companyId)->whereNotNull('web_registered_at')->where('web_status', WholesaleAccount::PENDING)->count(),
                    'sub' => 'waiting for approval',
                    'route' => 'admin.ecommerce-applications',
                    'feature' => 'admin.ecommerce_applications',
                ],
                [
                    'title' => 'Store promotions',
                    'value' => EcomPromotion::query()->where('company_id', $companyId)->running()->count(),
                    'sub' => 'running now',
                    'route' => 'admin.ecommerce-promotions',
                    'feature' => 'admin.ecommerce_promotions',
                ],
                [
                    'title' => 'Contact messages',
                    'value' => EcomContactMessage::query()->where('company_id', $companyId)->whereNull('read_at')->count(),
                    'sub' => 'unread',
                    'route' => 'admin.ecommerce-messages',
                    'feature' => 'admin.ecommerce_messages',
                ],
            ], fn ($s) => $user->canAccessFeature($s['feature']))),
        ];
    }
}; ?>

<div class="stamp-inv-page">
    <x-action-bar title="Ecommerce Settings" />

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
        <form wire:submit.prevent="save" class="stamp-inv-form" style="max-width: 48rem;" autocomplete="off">
            <p class="stamp-inv-hint">
                Public online store at <strong>{{ url('/') }}</strong>. When ON, the site home page shows the store
                (staff still sign in at <strong>{{ url('/login') }}</strong>). When OFF, the store is hidden and the home page goes to the POS login.
            </p>

            <h3 class="msa-section-title">Online store</h3>

            <label class="stamp-inv-field" style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;padding:.5rem 0;">
                <input type="checkbox" wire:model="ecommerce_enabled" style="margin-top:.35rem;width:1.15rem;height:1.15rem;" />
                <span>
                    <strong>Ecommerce active</strong>
                    <span class="block text-slate-600" style="font-size:.9em;line-height:1.4;margin-top:.35rem;">
                        Shows all {{ number_format($sellableCount) }} active, sellable items. Guests see list prices; approved customers see their own price level.
                        Online orders are saved as <strong>Sales Orders</strong> (source “Ecommerce”).
                    </span>
                </span>
            </label>

            <div class="msa-field-grid" style="margin-top:.75rem;">
                <label class="stamp-inv-field">
                    <span>Store name</span>
                    <input type="text" wire:model="store_name" class="desk-input" placeholder="{{ \Illuminate\Support\Str::title(auth()->user()->company->name) }}" />
                </label>
                <label class="stamp-inv-field">
                    <span>Tagline</span>
                    <input type="text" wire:model="tagline" class="desk-input" placeholder="Wholesale Distributor for Licensed Retailers" />
                </label>
                <label class="stamp-inv-field">
                    <span>Store phone</span>
                    <input type="text" wire:model="phone" class="desk-input" placeholder="{{ auth()->user()->company->phone }}" />
                </label>
                <label class="stamp-inv-field">
                    <span>Store email</span>
                    <input type="email" wire:model="email" class="desk-input" placeholder="{{ auth()->user()->company->email }}" />
                </label>
                <label class="stamp-inv-field" style="grid-column: 1 / -1;">
                    <span>Business hours</span>
                    <input type="text" wire:model="hours" class="desk-input" placeholder="Mon–Fri 9AM–6PM | Sat 10AM–4PM" />
                </label>
            </div>

            <h3 class="msa-section-title" style="margin-top:1.5rem;">Navbar categories</h3>
            <p class="stamp-inv-hint">
                Up to {{ $navLimit }} categories shown in the store's top menu, left to right.
                @if ($nav_custom)
                    <strong>Custom list</strong> — click Save to apply changes.
                @else
                    <strong>Automatic</strong> — the {{ $navLimit }} categories with the most items. Change the list below to choose your own.
                @endif
            </p>

            <div class="chief-grid" style="max-width:32rem;">
                <table class="desk-table">
                    <tbody>
                        @forelse ($nav_category_ids as $i => $catId)
                            @php $cat = $storeCategories->get($catId); @endphp
                            <tr wire:key="navcat-{{ $catId }}">
                                <td style="width:2rem;color:#64748b;">{{ $i + 1 }}</td>
                                <td>
                                    @if ($cat)
                                        <strong>{{ $cat->name }}</strong> <span class="text-slate-500">({{ number_format($cat->products_count) }} items)</span>
                                    @else
                                        <span class="text-slate-500">Category #{{ $catId }} (no sellable items — hidden)</span>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;text-align:right;">
                                    <button type="button" class="desk-btn" wire:click="moveNavCategory({{ $i }}, -1)" @disabled($i === 0) title="Move up">↑</button>
                                    <button type="button" class="desk-btn" wire:click="moveNavCategory({{ $i }}, 1)" @disabled($i === count($nav_category_ids) - 1) title="Move down">↓</button>
                                    <button type="button" class="desk-btn" wire:click="removeNavCategory({{ $i }})" title="Remove">Remove</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-2 py-3 text-slate-500">No categories chosen — saving will switch back to automatic.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-top:.5rem;max-width:32rem;">
                @if (count($nav_category_ids) < $navLimit)
                    <select wire:model="nav_add_id" class="desk-input" style="flex:1;min-width:12rem;">
                        <option value="">— Add a category —</option>
                        @foreach ($storeCategories->sortBy('name') as $cat)
                            @unless (in_array($cat->id, $nav_category_ids, true))
                                <option value="{{ $cat->id }}">{{ $cat->name }} ({{ number_format($cat->products_count) }})</option>
                            @endunless
                        @endforeach
                    </select>
                    <button type="button" class="desk-btn" wire:click="addNavCategory">Add</button>
                @else
                    <span class="text-slate-500" style="flex:1;">Maximum {{ $navLimit }} reached — remove one to add another.</span>
                @endif
                @if ($nav_custom)
                    <button type="button" class="desk-btn" wire:click="useAutomaticNav">Use automatic</button>
                @endif
            </div>

            <div class="stamp-inv-actions" style="margin-top:1.25rem;">
                <button type="submit" class="desk-btn desk-btn-primary">Save</button>
                @if ($ecommerce_enabled)
                    <a href="{{ url('/') }}" target="_blank" class="desk-btn">Open store</a>
                @endif
            </div>
        </form>
        @if (count($shortcuts))
            <h3 class="msa-section-title" style="margin-top:2rem;">Online store activity</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));gap:.75rem;max-width:48rem;">
                @foreach ($shortcuts as $s)
                    <a href="{{ route($s['route']) }}" wire:navigate class="chief-grid" style="display:block;padding:.9rem 1rem;text-decoration:none;color:inherit;">
                        <span style="display:block;font-size:.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">{{ $s['title'] }}</span>
                        <span style="display:block;font-size:1.75rem;font-weight:700;line-height:1.2;margin-top:.2rem;">{{ number_format($s['value']) }}</span>
                        <span style="display:block;font-size:.85rem;color:#64748b;">{{ $s['sub'] }} →</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>