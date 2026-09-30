<?php

use App\Models\EcomContactMessage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Contact Messages')] class extends Component
{
    public string $filter = 'unread';

    public string $search = '';

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['unread', 'all'], true) ? $filter : 'unread';
    }

    public function markRead(int $id): void
    {
        $this->scoped()->whereKey($id)->update(['read_at' => now()]);
    }

    public function markUnread(int $id): void
    {
        $this->scoped()->whereKey($id)->update(['read_at' => null]);
    }

    public function markAllRead(): void
    {
        $this->scoped()->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function delete(int $id): void
    {
        $this->scoped()->whereKey($id)->delete();
    }

    private function scoped()
    {
        return EcomContactMessage::query()->where('company_id', auth()->user()->company_id);
    }

    public function with(): array
    {
        $term = trim($this->search);

        return [
            'messages' => $this->scoped()
                ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%")))
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'unreadCount' => $this->scoped()->whereNull('read_at')->count(),
            'totalCount' => $this->scoped()->count(),
        ];
    }
}; ?>

<div class="stamp-inv-page">
    <x-action-bar title="Contact Messages" />

    <div class="stamp-inv-body">
        <p class="stamp-inv-hint">Messages sent from the online store's <strong>Contact Us</strong> page.</p>

        <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-bottom:.6rem;">
            <button type="button" wire:click="setFilter('unread')" @class(['desk-btn', 'desk-btn-primary' => $filter === 'unread'])>Unread ({{ $unreadCount }})</button>
            <button type="button" wire:click="setFilter('all')" @class(['desk-btn', 'desk-btn-primary' => $filter === 'all'])>All ({{ $totalCount }})</button>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="desk-btn">Mark all read</button>
            @endif
            <input type="search" wire:model.live.debounce.300ms="search" class="desk-input" placeholder="Search name, email, phone, message…" style="margin-left:auto;min-width:16rem;" />
        </div>

        <div class="chief-grid" style="max-height:calc(100vh - 16rem);overflow:auto;">
            <table class="desk-table">
                <thead>
                    <tr><th>Date</th><th>Name</th><th>Email / Phone</th><th>Message</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($messages as $msg)
                        <tr wire:key="msg-{{ $msg->id }}" @style(['font-weight:600' => ! $msg->read_at])>
                            <td style="white-space:nowrap;">{{ $msg->created_at?->format('n/j/Y g:i A') }}</td>
                            <td>{{ $msg->name }}</td>
                            <td>
                                <a href="mailto:{{ $msg->email }}" class="text-blue-700 underline">{{ $msg->email }}</a>
                                @if ($msg->phone)
                                    <br><a href="tel:{{ preg_replace('/[^0-9+]/', '', $msg->phone) }}" class="text-slate-500">{{ $msg->phone }}</a>
                                @endif
                            </td>
                            <td style="white-space:pre-line;max-width:32rem;">{{ $msg->message }}</td>
                            <td style="white-space:nowrap;">
                                @if ($msg->read_at)
                                    <button type="button" class="desk-btn" wire:click="markUnread({{ $msg->id }})">Mark unread</button>
                                @else
                                    <button type="button" class="desk-btn desk-btn-primary" wire:click="markRead({{ $msg->id }})">Mark read</button>
                                @endif
                                <button type="button" class="desk-btn" wire:click="delete({{ $msg->id }})" wire:confirm="Delete this message?">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-6 text-slate-500">{{ $filter === 'unread' ? 'No unread messages.' : 'No messages yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
