<?php

use App\Livewire\Concerns\SortsDeskList;
use App\Models\DocumentEmailLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Email Send Log')] class extends Component
{
    use WithPagination;
    use SortsDeskList;

    #[Url]
    public string $search = '';

    public string $favorite = 'all';

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $logsQuery = DocumentEmailLog::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($i) => $i->where('recipient', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('document_type', 'like', $term));
            });

        return [
            'logs' => $this->applyDeskSort($logsQuery, 'created_at', 'desc')->paginate(50),
            'favorites' => ['all' => 'All Sends'],
        ];
    }

    protected function deskSortMap(): array
    {
        return [
            'created_at' => 'created_at',
            'document_type' => 'document_type',
            'document_id' => 'document_id',
            'recipient' => 'recipient',
            'subject' => 'subject',
            'user_name' => ['relation' => 'user', 'column' => 'name'],
        ];
    }
}; ?>

<div class="flex gap-2 h-full">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />
    <div class="flex-1 chief-panel flex flex-col min-w-0">
        <x-action-bar title="Action" />
        <x-list-chrome label="Search Email Log:" model="search" />
        <div class="px-2 py-1 font-semibold border-b border-slate-300">Document Email Send Log</div>
        <div class="chief-grid flex-1 overflow-auto">
            <table class="desk-table">
                <thead>
                    <tr>
                        <x-desk-sort-th field="created_at" label="When" />
                        <x-desk-sort-th field="document_type" label="Type" />
                        <x-desk-sort-th field="document_id" label="Doc #" />
                        <x-desk-sort-th field="recipient" label="Recipient" />
                        <x-desk-sort-th field="subject" label="Subject" />
                        <x-desk-sort-th field="user_name" label="User" />
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('n/j/Y g:i A') }}</td>
                            <td>{{ $log->document_type }}</td>
                            <td class="font-mono">{{ $log->document_id }}</td>
                            <td>{{ $log->recipient }}</td>
                            <td>{{ $log->subject }}</td>
                            <td>{{ $log->user?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-2 py-6 text-slate-500">No email sends logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-count :count="$logs->total()">
            {{ $logs->links() }}
        </x-record-count>
    </div>
</div>
