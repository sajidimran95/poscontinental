<?php

use App\Models\CreditMemo;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Credit Memos')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public string $memo_number = '';

    public string $memo_date = '';

    public ?int $customer_id = null;

    public string $amount = '0';

    public string $comments = '';

    public ?int $emailMemoId = null;

    public string $emailTo = '';

    public string $emailSubject = '';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'memos' => CreditMemo::query()
                ->with('customer')
                ->where('company_id', $companyId)
                ->when($this->search !== '', fn ($q) => $q->where('memo_number', 'like', '%'.$this->search.'%'))
                ->orderByDesc('id')
                ->paginate(50),
            'customers' => Customer::query()->where('company_id', $companyId)->orderBy('company_name')->get(),
            'favorites' => ['all' => 'All Credit Memos'],
            'emailMemo' => $this->emailMemoId
                ? CreditMemo::query()->with('customer')->find($this->emailMemoId)
                : null,
        ];
    }

    public function startNew(): void
    {
        $this->showForm = true;
        $this->memo_number = CreditMemo::nextNumber(auth()->user()->company_id);
        $this->memo_date = now()->toDateString();
        $this->customer_id = null;
        $this->amount = '0';
        $this->comments = '';
    }

    public function openEmail(int $id): void
    {
        $memo = CreditMemo::query()->with('customer')->findOrFail($id);
        abort_unless($memo->company_id === auth()->user()->company_id, 403);
        $this->emailMemoId = $memo->id;
        $this->emailTo = $memo->customer?->email ?? '';
        $this->emailSubject = 'Credit Memo '.$memo->memo_number;
    }

    public function closeEmail(): void
    {
        $this->emailMemoId = null;
    }

    public function save(): void
    {
        $this->validate([
            'memo_number' => 'required',
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        CreditMemo::query()->create([
            'company_id' => auth()->user()->company_id,
            'memo_number' => $this->memo_number,
            'memo_date' => $this->memo_date,
            'customer_id' => $this->customer_id,
            'amount' => $this->amount,
            'status' => 'Open',
            'comments' => $this->comments,
        ]);

        $this->showForm = false;
        session()->flash('status', 'Credit memo created.');
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="'all'" />
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        @if (session('status'))
            <div class="mx-2 mt-1 border border-sky-400 bg-sky-50 px-2 py-1 text-xs" role="status">{{ session('status') }}</div>
        @endif
        @if ($showForm)
            <form wire:submit="save" class="p-3 space-y-2 max-w-lg">
                <div class="chief-field"><label>Memo No.</label><input wire:model="memo_number" class="chief-input w-40 font-mono" /></div>
                <div class="chief-field"><label>Memo Date</label><input type="date" wire:model="memo_date" class="chief-input" /></div>
                <div class="chief-field">
                    <label>Customer</label>
                    <select wire:model="customer_id" class="chief-input w-64">
                        <option value="">—</option>
                        @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->company_name }}</option>@endforeach
                    </select>
                </div>
                <div class="chief-field"><label>Amount</label><input wire:model="amount" class="chief-input w-32 text-right" /></div>
                <div class="chief-field chief-field-top"><label>Comments</label><textarea wire:model="comments" rows="3" class="chief-input w-full"></textarea></div>
                <div class="flex gap-2 ms-[9.5rem]">
                    <button type="button" wire:click="$set('showForm', false)" class="chief-btn">Cancel</button>
                    <button type="submit" class="chief-btn-primary">Save</button>
                </div>
            </form>
        @else
            <x-list-chrome label="Search Credit Memos:" model="search" />
            <div class="px-2 py-1 font-semibold border-b border-slate-300">Credit Memos</div>
            <div class="chief-grid flex-1 overflow-auto">
                <table>
                    <thead><tr><th>Memo No.</th><th>Date</th><th>Customer</th><th class="text-right">Amount</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse ($memos as $m)
                            <tr>
                                <td class="font-mono">{{ $m->memo_number }}</td>
                                <td>{{ optional($m->memo_date)?->format('n/j/Y') }}</td>
                                <td>{{ $m->customer?->company_name }}</td>
                                <td class="text-right">${{ number_format($m->amount, 2) }}</td>
                                <td>{{ $m->status }}</td>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('sales.credit-memos.pdf', $m) }}" class="text-sky-700 underline text-xs me-2" target="_blank">PDF</a>
                                    <button type="button" wire:click="openEmail({{ $m->id }})" class="text-sky-700 underline text-xs">Email</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-2 py-6 text-slate-500">No credit memos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-record-count :count="$memos->total()">
                <button type="button" wire:click="startNew" class="chief-btn-primary">New Credit Memo</button>
                {{ $memos->links() }}
            </x-record-count>
        @endif
    </div>

    @if ($emailMemo)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeEmail">
            <div class="bg-white border border-slate-500 shadow-xl w-full max-w-md">
                <div class="chief-action-bar px-3 py-1.5 flex justify-between">
                    <span>Email Credit Memo {{ $emailMemo->memo_number }}</span>
                    <button type="button" wire:click="closeEmail" class="text-white hover:text-red-200" aria-label="Close">×</button>
                </div>
                <form method="POST" action="{{ route('sales.credit-memos.email', $emailMemo) }}" class="p-3 space-y-2 text-sm">
                    @csrf
                    <div>
                        <label class="block text-xs mb-1" for="cm-email">Recipient</label>
                        <input id="cm-email" name="email" type="email" value="{{ $emailTo }}" required class="chief-input w-full" />
                    </div>
                    <div>
                        <label class="block text-xs mb-1" for="cm-subject">Subject</label>
                        <input id="cm-subject" name="subject" value="{{ $emailSubject }}" class="chief-input w-full" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeEmail" class="chief-btn">Cancel</button>
                        <button type="submit" class="chief-btn-primary">Send</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
