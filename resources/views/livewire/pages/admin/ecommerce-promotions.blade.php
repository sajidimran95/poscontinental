<?php

use App\Models\Category;
use App\Models\EcomPromotion;
use App\Models\Item;
use App\Support\Store\StoreCatalog;
use App\Support\Store\StorePromotions;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Store Promotions')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $subtitle = '';

    public string $tag = '';

    public string $target = 'category';

    public ?int $category_id = null;

    public string $brand = '';

    /** @var list<int> */
    public array $itemIds = [];

    public string $itemSearch = '';

    public string $discount_type = 'percent';

    public string $discount_value = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public bool $is_active = true;

    public int $sort_order = 0;

    public string $statusMessage = '';

    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->sort_order = (int) EcomPromotion::query()->where('company_id', $this->companyId())->max('sort_order') + 1;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $p = EcomPromotion::query()->where('company_id', $this->companyId())->findOrFail($id);
        $this->resetForm();
        $this->editingId = $p->id;
        $this->title = $p->title;
        $this->subtitle = (string) $p->subtitle;
        $this->tag = (string) $p->tag;
        $this->target = $p->target;
        $this->category_id = $p->category_id;
        $this->brand = (string) $p->brand;
        $this->itemIds = $p->items()->pluck('items.id')->map(fn ($v) => (int) $v)->all();
        $this->discount_type = $p->discount_type;
        $this->discount_value = $p->discount_type === 'none' ? '' : rtrim(rtrim(number_format($p->discount_value, 2, '.', ''), '0'), '.');
        $this->starts_on = (string) $p->starts_on?->toDateString();
        $this->ends_on = (string) $p->ends_on?->toDateString();
        $this->is_active = $p->is_active;
        $this->sort_order = (int) $p->sort_order;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function addItem(int $itemId): void
    {
        if (StoreCatalog::query($this->companyId())->whereKey($itemId)->exists() && ! in_array($itemId, $this->itemIds, true)) {
            $this->itemIds[] = $itemId;
        }
        $this->itemSearch = '';
    }

    public function removeItem(int $itemId): void
    {
        $this->itemIds = array_values(array_filter($this->itemIds, fn ($id) => $id !== $itemId));
    }

    public function save(): void
    {
        $companyId = $this->companyId();
        $this->statusMessage = '';
        $data = $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:191'],
            'tag' => ['nullable', 'string', 'max:20'],
            'target' => ['required', Rule::in(array_keys(EcomPromotion::TARGETS))],
            'category_id' => ['nullable', 'required_if:target,category', Rule::exists('categories', 'id')->where('company_id', $companyId)],
            'brand' => ['nullable', 'required_if:target,brand', 'string', 'max:191'],
            'itemIds' => ['array', 'required_if:target,items', 'max:500'],
            'discount_type' => ['required', Rule::in(array_keys(EcomPromotion::DISCOUNT_TYPES))],
            'discount_value' => ['nullable', 'required_unless:discount_type,none', 'numeric', 'min:0.01', $this->discount_type === 'percent' ? 'max:100' : 'max:100000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ], [
            'category_id.required_if' => 'Choose the category this promotion covers.',
            'brand.required_if' => 'Choose the brand this promotion covers.',
            'itemIds.required_if' => 'Add at least one item.',
        ]);

        $attrs = [
            'company_id' => $companyId,
            'title' => trim($data['title']),
            'subtitle' => trim((string) $data['subtitle']) ?: null,
            'tag' => mb_strtoupper(trim((string) $data['tag'])) ?: null,
            'target' => $data['target'],
            'category_id' => $data['target'] === 'category' ? $data['category_id'] : null,
            'brand' => $data['target'] === 'brand' ? $data['brand'] : null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_type'] === 'none' ? 0 : (float) $data['discount_value'],
            'starts_on' => $data['starts_on'] ?: null,
            'ends_on' => $data['ends_on'] ?: null,
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ];

        $promo = $this->editingId
            ? tap(EcomPromotion::query()->where('company_id', $companyId)->findOrFail($this->editingId))->update($attrs)
            : EcomPromotion::query()->create($attrs);
        $promo->items()->sync($data['target'] === 'items' ? $this->itemIds : []);
        StorePromotions::forget($companyId);

        $this->statusMessage = 'Promotion “'.$promo->title.'” saved.';
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $p = EcomPromotion::query()->where('company_id', $this->companyId())->findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
        StorePromotions::forget($this->companyId());
        $this->statusMessage = '“'.$p->title.'” is now '.($p->is_active ? 'on' : 'off').'.';
    }

    public function delete(int $id): void
    {
        $p = EcomPromotion::query()->where('company_id', $this->companyId())->findOrFail($id);
        $p->delete();
        StorePromotions::forget($this->companyId());
        $this->statusMessage = 'Promotion “'.$p->title.'” deleted.';
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'title', 'subtitle', 'tag', 'target', 'category_id', 'brand', 'itemIds', 'itemSearch', 'discount_type', 'discount_value', 'starts_on', 'ends_on', 'is_active', 'sort_order']);
        $this->resetValidation();
    }

    public function with(): array
    {
        $companyId = $this->companyId();
        $term = trim($this->itemSearch);

        return [
            'promotions' => EcomPromotion::query()
                ->where('company_id', $companyId)
                ->with('category:id,name')
                ->withCount('items')
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->get()
                ->each(fn (EcomPromotion $p) => $p->setAttribute(
                    'product_count',
                    StorePromotions::productCount(StorePromotions::resolveScope($p, $companyId), $companyId),
                )),
            'categories' => $this->showForm ? Category::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'brands' => $this->showForm ? StoreCatalog::brands($companyId) : collect(),
            'selectedItems' => $this->showForm && $this->itemIds
                ? Item::query()->whereIn('id', $this->itemIds)->get(['id', 'item_code', 'description'])->sortBy(fn ($i) => array_search((int) $i->id, $this->itemIds, true))->values()
                : collect(),
            'itemResults' => $this->showForm && $this->target === 'items' && mb_strlen($term) >= 2
                ? StoreCatalog::query($companyId)->looseSearch($term)->whereNotIn('items.id', $this->itemIds ?: [0])->orderBy('items.description')->limit(12)->get(['items.id', 'items.item_code', 'items.description'])
                : collect(),
        ];
    }
}; ?>

<div class="stamp-inv-page">
    <x-action-bar title="Store Promotions" />

    @if ($statusMessage !== '')
        <div class="stamp-inv-flash stamp-inv-flash-ok" role="status">{{ $statusMessage }}</div>
    @endif

    <div class="stamp-inv-body">
        <p class="stamp-inv-hint">
            Promotions show on the online store home page (<strong>Current Promotions</strong>) and the <strong>Promotions</strong> page.
            A discount lowers the online price of every covered item (customers keep their own price level when it is lower), and flows into the Sales Order.
        </p>

        @unless ($showForm)
            <div style="margin-bottom:.75rem;">
                <button type="button" class="desk-btn desk-btn-primary" wire:click="create">+ New promotion</button>
            </div>
        @endunless

        @if ($showForm)
            <form wire:submit.prevent="save" class="stamp-inv-form chief-grid" style="max-width:56rem;padding:1rem 1.1rem;margin-bottom:1.25rem;" autocomplete="off">
                <h3 class="msa-section-title" style="margin-top:0;">{{ $editingId ? 'Edit promotion' : 'New promotion' }}</h3>

                @if ($errors->any())
                    <div class="stamp-inv-flash stamp-inv-flash-err" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="msa-field-grid">
                    <label class="stamp-inv-field">
                        <span>Title *</span>
                        <input type="text" wire:model="title" class="desk-input" placeholder="e.g. Summer Drinks Sale" />
                    </label>
                    <label class="stamp-inv-field">
                        <span>Badge tag</span>
                        <input type="text" wire:model="tag" class="desk-input" maxlength="20" placeholder="HOT, NEW, DEALS…" />
                    </label>
                    <label class="stamp-inv-field" style="grid-column:1 / -1;">
                        <span>Subtitle</span>
                        <input type="text" wire:model="subtitle" class="desk-input" placeholder="Short line shown under the title" />
                    </label>

                    <label class="stamp-inv-field">
                        <span>Applies to</span>
                        <select wire:model.live="target" class="desk-input">
                            @foreach (\App\Models\EcomPromotion::TARGETS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if ($target === 'category')
                        <label class="stamp-inv-field">
                            <span>Category</span>
                            <select wire:model="category_id" class="desk-input">
                                <option value="">Choose…</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @elseif ($target === 'brand')
                        <label class="stamp-inv-field">
                            <span>Brand</span>
                            <select wire:model="brand" class="desk-input">
                                <option value="">Choose…</option>
                                @foreach ($brands as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->products_count }})</option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        <div class="stamp-inv-field" style="grid-column:1 / -1;">
                            <span>Items ({{ count($itemIds) }})</span>
                            <div style="position:relative;">
                                <input type="search" wire:model.live.debounce.300ms="itemSearch" class="desk-input" placeholder="Search item code, UPC or description to add…" />
                                @if ($itemResults->count())
                                    <div class="chief-grid" style="position:absolute;z-index:20;left:0;right:0;top:100%;max-height:16rem;overflow:auto;background:#fff;">
                                        @foreach ($itemResults as $r)
                                            <button type="button" wire:click="addItem({{ $r->id }})" style="display:block;width:100%;text-align:left;padding:.4rem .6rem;border-bottom:1px solid #eef2f7;">
                                                <span class="font-mono text-slate-500">{{ $r->item_code }}</span> {{ $r->description }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            @if ($selectedItems->count())
                                <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.5rem;">
                                    @foreach ($selectedItems as $si)
                                        <span class="desk-pill desk-pill-muted" wire:key="sel-{{ $si->id }}" style="gap:.35rem;">
                                            {{ $si->item_code }} · {{ \Illuminate\Support\Str::limit($si->description, 40) }}
                                            <button type="button" wire:click="removeItem({{ $si->id }})" aria-label="Remove" style="font-weight:800;">×</button>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <label class="stamp-inv-field">
                        <span>Discount</span>
                        <select wire:model.live="discount_type" class="desk-input">
                            @foreach (\App\Models\EcomPromotion::DISCOUNT_TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($discount_type !== 'none')
                        <label class="stamp-inv-field">
                            <span>{{ $discount_type === 'percent' ? 'Percent off (%)' : 'Amount off per unit ($)' }}</span>
                            <input type="number" step="0.01" min="0" wire:model="discount_value" class="desk-input" placeholder="{{ $discount_type === 'percent' ? '10' : '1.00' }}" />
                        </label>
                    @endif

                    <label class="stamp-inv-field">
                        <span>Starts on</span>
                        <input type="date" wire:model="starts_on" class="desk-input" />
                    </label>
                    <label class="stamp-inv-field">
                        <span>Ends on</span>
                        <input type="date" wire:model="ends_on" class="desk-input" />
                    </label>
                    <label class="stamp-inv-field">
                        <span>Display order</span>
                        <input type="number" min="0" wire:model="sort_order" class="desk-input" />
                    </label>
                    <label class="stamp-inv-field" style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;cursor:pointer;">
                        <input type="checkbox" wire:model="is_active" style="width:1.1rem;height:1.1rem;" />
                        <strong>Active</strong>
                    </label>
                </div>

                <div class="stamp-inv-actions" style="margin-top:1rem;">
                    <button type="submit" class="desk-btn desk-btn-primary">Save promotion</button>
                    <button type="button" class="desk-btn" wire:click="cancel">Cancel</button>
                </div>
            </form>
        @endif

        <div class="chief-grid" style="overflow:auto;">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Promotion</th>
                        <th>Applies to</th>
                        <th>Discount</th>
                        <th>Dates</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($promotions as $p)
                        @php $st = $p->status(); @endphp
                        <tr wire:key="promo-{{ $p->id }}" @style(['opacity:.6' => $st !== 'Running'])>
                            <td class="desk-num">{{ $p->sort_order }}</td>
                            <td>
                                <strong>{{ $p->title }}</strong>
                                @if ($p->tag)<span class="desk-pill desk-pill-muted" style="margin-left:.35rem;">{{ $p->tag }}</span>@endif
                                @if ($p->subtitle)<br><span class="text-slate-500">{{ $p->subtitle }}</span>@endif
                            </td>
                            <td>
                                {{ \App\Models\EcomPromotion::TARGETS[$p->target] ?? $p->target }}:
                                {{ match ($p->target) { 'category' => $p->category?->name ?? '—', 'brand' => $p->brand ?: '—', default => $p->items_count.' item(s)' } }}
                            </td>
                            <td>{{ $p->hasDiscount() ? $p->badge() : '—' }}</td>
                            <td style="white-space:nowrap;">{{ $p->starts_on?->format('n/j/Y') ?? 'Now' }} – {{ $p->ends_on?->format('n/j/Y') ?? 'No end' }}</td>
                            <td class="desk-num">{{ number_format($p->product_count) }}</td>
                            <td>
                                <span @class(['desk-pill', 'desk-pill-invoiced' => $st === 'Running', 'desk-pill-new' => $st === 'Scheduled', 'desk-pill-muted' => in_array($st, ['Off', 'Ended'], true)])>{{ $st }}</span>
                            </td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="desk-btn" wire:click="edit({{ $p->id }})">Edit</button>
                                <button type="button" class="desk-btn" wire:click="toggleActive({{ $p->id }})">{{ $p->is_active ? 'Turn off' : 'Turn on' }}</button>
                                <button type="button" class="desk-btn" wire:click="delete({{ $p->id }})" wire:confirm="Delete this promotion?">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-2 py-6 text-slate-500">No promotions yet. Click “New promotion” to create one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
