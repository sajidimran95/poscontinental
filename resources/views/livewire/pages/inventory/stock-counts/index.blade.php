<?php

use App\Models\Item;
use App\Models\Site;
use App\Models\StockCount;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Stock Counts')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $favorite = 'all';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'counts' => StockCount::query()
                ->with('site')
                ->where('company_id', $companyId)
                ->when($this->search !== '', fn ($q) => $q->where('stock_count_no', 'like', '%'.$this->search.'%'))
                ->orderByDesc('id')
                ->paginate(50),
            'favorites' => ['all' => 'All Counts'],
        ];
    }

    public function process(int $id): void
    {
        $count = StockCount::query()->findOrFail($id);
        abort_unless($count->company_id === auth()->user()->company_id, 403);
        app(InventoryService::class)->processStockCount($count);
        session()->flash('status', 'Stock count processed.');
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" variant="green" />
        <x-list-chrome label="Search Stock Counts:" model="search" />
        <div class="px-2 py-1 font-semibold border-b border-slate-300 bg-white">Stock Counts</div>
        <div class="chief-grid flex-1 overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>Stock Count No.</th>
                        <th>Date Created</th>
                        <th>Status</th>
                        <th>Site</th>
                        <th>Date Processed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($counts as $count)
                        <tr>
                            <td class="font-mono">
                                <a href="{{ route('inventory.stock-counts.edit', $count) }}" wire:navigate class="hover:underline">{{ $count->stock_count_no }}</a>
                            </td>
                            <td>{{ optional($count->date_created)?->format('n/j/Y') }}</td>
                            <td>{{ $count->status }}</td>
                            <td>{{ $count->site?->code }}</td>
                            <td>{{ optional($count->date_processed)?->format('n/j/Y') }}</td>
                            <td>
                                @if ($count->status !== 'Processed')
                                    <button type="button" wire:click="process({{ $count->id }})" wire:confirm="Process count and update In Stock?" class="chief-btn text-xs">Process</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-2 py-6 text-slate-500">No stock counts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-count :count="$counts->total()">
            <a href="{{ route('inventory.stock-counts.create') }}" wire:navigate class="chief-btn-primary">New Stock Count</a>
            {{ $counts->links() }}
        </x-record-count>
    </div>
</div>
