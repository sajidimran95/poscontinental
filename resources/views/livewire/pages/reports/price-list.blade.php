<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\PriceLevel;
use App\Services\DocumentPdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app'), Title('Price List')] class extends Component
{
    #[Url]
    public ?int $department_id = null;

    #[Url]
    public ?int $category_id = null;

    #[Url]
    public ?int $price_level_id = null;

    #[Url]
    public string $search = '';

    public bool $includeInactive = false;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $items = Item::query()
            ->with(['prices', 'department', 'category'])
            ->where('company_id', $companyId)
            ->when(! $this->includeInactive, fn ($q) => $q->where('is_inactive', false))
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('item_code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('primary_upc', 'like', $term);
                });
            })
            ->orderBy('item_code')
            ->limit(500)
            ->get();

        return [
            'departments' => Department::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'categories' => Category::query()
                ->where('company_id', $companyId)
                ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
                ->orderBy('name')
                ->get(),
            'priceLevels' => PriceLevel::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'items' => $items,
        ];
    }

    public function updatedDepartmentId(): void
    {
        $this->category_id = null;
    }

    public function downloadPdf(DocumentPdfService $pdfs): StreamedResponse
    {
        $items = $pdfs->queryPriceListItems(
            auth()->user()->company_id,
            $this->department_id,
            $this->category_id,
            $this->search,
            $this->includeInactive
        );

        $title = 'Price List';
        if ($this->price_level_id) {
            $level = PriceLevel::query()->find($this->price_level_id);
            $title .= $level ? ' — '.$level->name : '';
        }

        return $pdfs->streamDownload(
            $pdfs->priceListPdf($items, auth()->user(), $title),
            'price-list-'.now()->format('Ymd-His').'.pdf'
        );
    }

    public function downloadCsv(): StreamedResponse
    {
        $companyId = auth()->user()->company_id;
        $departmentId = $this->department_id;
        $categoryId = $this->category_id;
        $search = $this->search;
        $includeInactive = $this->includeInactive;

        return response()->streamDownload(function () use ($companyId, $departmentId, $categoryId, $search, $includeInactive) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Item Code', 'Description', 'UPC', 'Department', 'Category', 'UOM', 'List Price', 'MSRP', 'Std Cost']);

            Item::query()
                ->with(['department', 'category'])
                ->where('company_id', $companyId)
                ->when(! $includeInactive, fn ($q) => $q->where('is_inactive', false))
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->when($search !== '', function ($q) use ($search) {
                    $term = '%'.$search.'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('item_code', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhere('primary_upc', 'like', $term);
                    });
                })
                ->orderBy('item_code')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $item) {
                        fputcsv($out, [
                            $item->item_code,
                            $item->description,
                            $item->primary_upc,
                            $item->department?->name,
                            $item->category?->name,
                            $item->unit_of_measure,
                            number_format((float) $item->list_price, 2, '.', ''),
                            number_format((float) $item->msrp, 2, '.', ''),
                            number_format((float) $item->standard_cost, 2, '.', ''),
                        ]);
                    }
                });

            fclose($out);
        }, 'price-list-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}; ?>

<div class="flex gap-2 h-full">
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Price List Generator" />
        <div class="flex flex-wrap items-end gap-2 px-2 py-2 bg-slate-100 border-b border-slate-300">
            <div>
                <label class="block text-xs text-slate-600" for="pl-dept">Department</label>
                <select id="pl-dept" wire:model.live="department_id" class="chief-input w-44">
                    <option value="">All</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600" for="pl-cat">Category</label>
                <select id="pl-cat" wire:model.live="category_id" class="chief-input w-44">
                    <option value="">All</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600" for="pl-level">Price Level</label>
                <select id="pl-level" wire:model.live="price_level_id" class="chief-input w-44">
                    <option value="">List Price</option>
                    @foreach ($priceLevels as $pl)<option value="{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600" for="pl-search">Search</label>
                <input id="pl-search" wire:model.live.debounce.300ms="search" class="chief-input w-48" />
            </div>
            <label class="inline-flex items-center gap-2 text-sm pb-1">
                <input type="checkbox" wire:model.live="includeInactive" /> Include inactive
            </label>
            <button type="button" wire:click="downloadCsv" class="chief-btn">Download CSV</button>
            <button type="button" wire:click="downloadPdf" class="chief-btn-primary">Download PDF</button>
        </div>

        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Price List</div>
        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>UPC</th>
                        <th>Department</th>
                        <th>Category</th>
                        <th>UOM</th>
                        <th class="text-right">List Price</th>
                        <th class="text-right">MSRP</th>
                        <th class="text-right">Std Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="font-mono">{{ $item->item_code }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="font-mono">{{ $item->primary_upc }}</td>
                            <td>{{ $item->department?->name }}</td>
                            <td>{{ $item->category?->name }}</td>
                            <td>{{ $item->unit_of_measure }}</td>
                            <td class="text-right">${{ number_format($item->list_price, 2) }}</td>
                            <td class="text-right">${{ number_format($item->msrp, 2) }}</td>
                            <td class="text-right">${{ number_format($item->standard_cost, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-2 py-6 text-slate-500">No items match the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-count :count="$items->count()" />
    </div>
</div>
