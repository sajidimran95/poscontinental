<?php

use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Services\TeamChatService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app'), Title('Team Chat')] class extends Component
{
    use WithFileUploads;

    public ?int $activeChannelId = null;

    public string $draft = '';

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public $pendingFiles = [];

    public string $newChannelName = '';

    public string $errorMessage = '';

    public bool $showNewChannel = false;

    public bool $showNewDm = false;

    public bool $showAddMember = false;

    public int $latestMessageId = 0;

    public ?int $olderCursor = null;

    public bool $hasOlder = false;

    /** @var list<array<string, mixed>> */
    public array $thread = [];

    public function mount(): void
    {
        $chat = app(TeamChatService::class);
        $chat->provisionForUser(auth()->user());
        $list = $chat->listForUser(auth()->user());
        $general = $list->first(fn ($c) => ($c['type'] ?? '') === 'channel' && ($c['name'] ?? '') === 'general')
            ?? $list->first();
        if ($general) {
            $this->openChannel((int) $general['id']);
        }
    }

    public function with(): array
    {
        $chat = app(TeamChatService::class);
        $user = auth()->user();
        $list = $chat->listForUser($user);
        $channels = $list->where('type', 'channel')->values();
        $dms = $list->where('type', 'dm')->values();
        $active = $list->firstWhere('id', $this->activeChannelId);
        $activeMembers = [];
        $addableUsers = [];
        if ($this->activeChannelId) {
            $channel = ChatChannel::query()
                ->where('company_id', $user->company_id)
                ->find($this->activeChannelId);
            if ($channel) {
                $activeMembers = $chat->channelMembers($channel);
                $memberIds = collect($activeMembers)->pluck('id')->all();
                $addableUsers = collect($chat->companyUsers($user))
                    ->reject(fn ($u) => in_array((int) $u['id'], $memberIds, true))
                    ->values()
                    ->all();
            }
        }

        return [
            'sidebarChannels' => $channels,
            'sidebarDms' => $dms,
            'activeMeta' => $active,
            'activeMembers' => $activeMembers,
            'addableUsers' => $addableUsers,
            'companyUsers' => $chat->companyUsers($user),
            'canEdit' => $user->canAccessFeature('team.chat', 'edit'),
            'canDelete' => $user->canAccessFeature('team.chat', 'delete'),
            'canManageChannels' => $user->canManageTeamChatChannels(),
            'meId' => (int) $user->id,
        ];
    }

    public function openChannel(int $id): void
    {
        $user = auth()->user();
        $channel = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->find($id);
        if (! $channel) {
            return;
        }

        $chat = app(TeamChatService::class);
        $payload = $chat->messages($channel, $user, null, 80);
        $this->activeChannelId = $id;
        $this->thread = $payload['messages'];
        $this->latestMessageId = (int) $payload['latest_id'];
        $this->olderCursor = $payload['next_cursor'];
        $this->hasOlder = $payload['next_cursor'] !== null;
        $this->errorMessage = '';
        $this->showAddMember = false;
        $this->pendingFiles = [];
        $chat->markRead($channel, $user, $this->latestMessageId ?: null);
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("tc-thread"); if (el) el.scrollTop = el.scrollHeight; })');
    }

    public function removePendingFile(int $index): void
    {
        $files = is_array($this->pendingFiles) ? $this->pendingFiles : [];
        unset($files[$index]);
        $this->pendingFiles = array_values($files);
    }

    /**
     * AJAX poll — new messages appear without a full page reload.
     */
    public function pollLive(): void
    {
        if (! $this->activeChannelId) {
            return;
        }

        $user = auth()->user();
        $channel = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->find($this->activeChannelId);
        if (! $channel) {
            return;
        }

        $chat = app(TeamChatService::class);
        $incoming = $chat->messagesAfter($channel, $user, $this->latestMessageId);
        if ($incoming === []) {
            return;
        }

        $existing = collect($this->thread)->pluck('id')->all();
        $newestFromOthers = 0;
        foreach ($incoming as $row) {
            if (in_array($row['id'], $existing, true)) {
                continue;
            }
            $this->thread[] = $row;
            $this->latestMessageId = max($this->latestMessageId, (int) $row['id']);
            if ((int) $row['sender_id'] !== (int) $user->id) {
                $newestFromOthers = max($newestFromOthers, (int) $row['id']);
            }
        }
        $chat->markRead($channel, $user, $this->latestMessageId);
        if ($newestFromOthers > 0) {
            $this->dispatch('team-chat-incoming', id: $newestFromOthers);
        }
        $this->js('requestAnimationFrame(() => { const el = document.getElementById("tc-thread"); if (el) el.scrollTop = el.scrollHeight; })');
    }

    public function loadOlder(): void
    {
        if (! $this->activeChannelId || ! $this->olderCursor) {
            return;
        }

        $user = auth()->user();
        $channel = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->find($this->activeChannelId);
        if (! $channel) {
            return;
        }

        $payload = app(TeamChatService::class)->messages($channel, $user, $this->olderCursor, 80);
        $existing = collect($this->thread)->pluck('id')->all();
        $older = [];
        foreach ($payload['messages'] as $row) {
            if (! in_array($row['id'], $existing, true)) {
                $older[] = $row;
            }
        }
        $this->thread = array_values(array_merge($older, $this->thread));
        $this->olderCursor = $payload['next_cursor'];
        $this->hasOlder = $payload['next_cursor'] !== null;
    }

    public function send(): void
    {
        if (! auth()->user()->canAccessFeature('team.chat', 'edit')) {
            $this->errorMessage = 'You do not have permission to send messages.';

            return;
        }
        if (! $this->activeChannelId) {
            return;
        }
        $body = trim($this->draft);
        $files = is_array($this->pendingFiles) ? array_values($this->pendingFiles) : [];
        if ($body === '' && $files === []) {
            return;
        }

        $user = auth()->user();
        $channel = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->find($this->activeChannelId);
        if (! $channel) {
            return;
        }

        try {
            $this->validate([
                'draft' => 'nullable|string|max:8000',
                'pendingFiles' => 'nullable|array|max:5',
                'pendingFiles.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip',
            ], [], [
                'pendingFiles.*' => 'attachment',
            ]);

            $message = app(TeamChatService::class)->send($channel, $user, $body, $files);
            $row = app(TeamChatService::class)->serializeMessage($message);
            $this->thread[] = $row;
            $this->latestMessageId = max($this->latestMessageId, (int) $row['id']);
            $this->draft = '';
            $this->pendingFiles = [];
            $this->errorMessage = '';
            $this->js('requestAnimationFrame(() => { const el = document.getElementById("tc-thread"); if (el) el.scrollTop = el.scrollHeight; document.getElementById("tc-composer")?.focus(); })');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = collect($e->validator->errors()->all())->first() ?: 'Invalid attachment.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function createChannel(): void
    {
        if (! auth()->user()->canManageTeamChatChannels()) {
            $this->errorMessage = 'You do not have permission to create channels.';

            return;
        }
        try {
            $channel = app(TeamChatService::class)->createChannel(auth()->user(), $this->newChannelName);
            $this->newChannelName = '';
            $this->showNewChannel = false;
            $this->openChannel((int) $channel->id);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function startDm(int $userId): void
    {
        if (! auth()->user()->canAccessFeature('team.chat', 'edit')) {
            return;
        }
        try {
            $channel = app(TeamChatService::class)->getOrCreateDm(auth()->user(), $userId);
            $this->showNewDm = false;
            $this->openChannel((int) $channel->id);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function addMember(int $userId): void
    {
        if (! auth()->user()->canManageTeamChatChannels() || ! $this->activeChannelId) {
            $this->errorMessage = 'You do not have permission to add people to a channel.';

            return;
        }
        $user = auth()->user();
        $channel = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->find($this->activeChannelId);
        if (! $channel) {
            return;
        }
        try {
            app(TeamChatService::class)->addMember($channel, $user, $userId);
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function removeMember(int $userId): void
    {
        if (! auth()->user()->canManageTeamChatChannels() || ! $this->activeChannelId) {
            $this->errorMessage = 'You do not have permission to remove people from a channel.';

            return;
        }
        $user = auth()->user();
        $channel = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->find($this->activeChannelId);
        if (! $channel) {
            return;
        }
        try {
            app(TeamChatService::class)->removeMember($channel, $user, $userId);
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteMessage(int $id): void
    {
        if (! auth()->user()->canAccessFeature('team.chat', 'delete')) {
            return;
        }
        $user = auth()->user();
        $message = ChatMessage::query()->with('channel')->find($id);
        if (! $message) {
            return;
        }
        try {
            app(TeamChatService::class)->deleteOwn($message, $user);
            $this->thread = array_values(array_filter($this->thread, fn ($m) => (int) $m['id'] !== $id));
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }
}; ?>

<div class="desk-page tc-page" wire:poll.2s="pollLive">
    <div class="tc-shell">
        <aside class="tc-sidebar" aria-label="Channels and direct messages">
            <div class="tc-brand">
                <span class="tc-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8"><path d="M5 12h.01M9 12h.01M13 12h.01"/><path d="M4 7h11a3 3 0 013 3v5a3 3 0 01-3 3H9l-4 3v-3H4a2 2 0 01-2-2V9a2 2 0 012-2z"/></svg>
                </span>
                <span class="tc-brand-title">Team chat</span>
            </div>

            <p class="tc-section-label">Channels</p>
            @foreach ($sidebarChannels as $ch)
                <button
                    type="button"
                    wire:click="openChannel({{ $ch['id'] }})"
                    @class(['tc-row', 'is-active' => $activeChannelId === (int) $ch['id']])
                >
                    <span class="tc-hash">#</span>
                    <span class="tc-row-name">{{ $ch['label'] }}</span>
                    @if ((int) $ch['unread'] > 0 && $activeChannelId !== (int) $ch['id'])
                        <span class="tc-badge">{{ $ch['unread'] > 9 ? '9+' : $ch['unread'] }}</span>
                    @endif
                </button>
            @endforeach
            @if ($canManageChannels)
                @if ($showNewChannel)
                    <form wire:submit="createChannel" class="tc-inline-form">
                        <input type="text" wire:model="newChannelName" class="so-input" placeholder="channel-name" maxlength="80" />
                        <button type="submit" class="desk-btn desk-btn-sm desk-btn-primary">Add</button>
                        <button type="button" class="desk-btn desk-btn-sm" wire:click="$set('showNewChannel', false)">Cancel</button>
                    </form>
                @else
                    <button type="button" class="tc-add" wire:click="$set('showNewChannel', true)">+ New channel</button>
                @endif
            @endif

            <p class="tc-section-label">Direct messages</p>
            @foreach ($sidebarDms as $dm)
                <button
                    type="button"
                    wire:click="openChannel({{ $dm['id'] }})"
                    @class(['tc-row', 'is-active' => $activeChannelId === (int) $dm['id']])
                >
                    <span @class(['tc-dot', 'is-on' => $dm['online_hint']])></span>
                    <span class="tc-row-name">{{ $dm['label'] }}</span>
                    @if ((int) $dm['unread'] > 0 && $activeChannelId !== (int) $dm['id'])
                        <span class="tc-badge">{{ $dm['unread'] > 9 ? '9+' : $dm['unread'] }}</span>
                    @endif
                </button>
            @endforeach
            @if ($canEdit)
                @if ($showNewDm)
                    <div class="tc-dm-pick">
                        @forelse ($companyUsers as $cu)
                            <button type="button" class="tc-row" wire:click="startDm({{ $cu['id'] }})">{{ $cu['name'] }}</button>
                        @empty
                            <p class="item-hint">No other users in this company.</p>
                        @endforelse
                        <button type="button" class="tc-add" wire:click="$set('showNewDm', false)">Cancel</button>
                    </div>
                @else
                    <button type="button" class="tc-add" wire:click="$set('showNewDm', true)">+ New message</button>
                @endif
            @endif
        </aside>

        <section class="tc-main" aria-label="Message thread">
            <header class="tc-thread-head">
                @if ($activeMeta)
                    @if (($activeMeta['type'] ?? '') === 'channel')
                        <span class="tc-hash">#</span>
                    @endif
                    <span class="tc-thread-name">{{ $activeMeta['label'] }}</span>
                    <span class="tc-thread-meta">{{ count($activeMembers) }} members</span>
                    @if ($canManageChannels && ($activeMeta['type'] ?? '') === 'channel')
                        <button type="button" class="desk-btn desk-btn-sm" wire:click="$toggle('showAddMember')">
                            {{ $showAddMember ? 'Close members' : 'Add / remove members' }}
                        </button>
                    @endif
                @else
                    <span class="tc-thread-name">Team chat</span>
                @endif
            </header>

            @if ($showAddMember && $canManageChannels && ($activeMeta['type'] ?? '') === 'channel')
                @php
                    $isGeneralChannel = ($activeMeta['is_default'] ?? false) || ($activeMeta['name'] ?? '') === 'general';
                @endphp
                <div class="tc-people" role="region" aria-label="Channel members">
                    <div class="tc-people-col">
                        <p class="tc-section-label" style="margin-top:0">{{ $isGeneralChannel ? '#general members' : 'In this channel' }}</p>
                        @forelse ($activeMembers as $mem)
                            <div class="tc-people-row">
                                <span class="tc-people-name">
                                    <span @class(['tc-dot', 'is-on' => $mem['is_active']])></span>
                                    {{ $mem['name'] }}
                                    <span class="tc-people-status">{{ $mem['is_active'] ? 'Active' : 'Inactive' }}</span>
                                </span>
                                @if (! $isGeneralChannel && (int) $mem['id'] !== $meId)
                                    <button type="button" class="tc-msg-del" wire:click="removeMember({{ $mem['id'] }})" wire:confirm="Remove {{ $mem['name'] }} from this channel?">Remove</button>
                                @endif
                            </div>
                        @empty
                            <p class="item-hint">No members.</p>
                        @endforelse
                    </div>
                    <div class="tc-people-col">
                        <p class="tc-section-label" style="margin-top:0">Add member</p>
                        @if ($isGeneralChannel)
                            <p class="item-hint">Everyone is already in #general. Add and remove members on other channels only.</p>
                        @else
                            @forelse ($addableUsers as $au)
                                <button type="button" class="tc-row" wire:click="addMember({{ $au['id'] }})">
                                    <span @class(['tc-dot', 'is-on' => $au['is_active']])></span>
                                    + {{ $au['name'] }}
                                </button>
                            @empty
                                <p class="item-hint">Everyone in the company is already in this channel.</p>
                            @endforelse
                        @endif
                    </div>
                </div>
            @endif

            @if ($errorMessage !== '')
                <p class="tc-error" role="alert">{{ $errorMessage }}</p>
            @endif

            <div class="tc-thread" id="tc-thread" wire:key="thread-{{ $activeChannelId }}">
                @if ($hasOlder)
                    <div style="text-align:center;padding:0.5rem 0">
                        <button type="button" class="desk-btn desk-btn-sm" wire:click="loadOlder">Load older messages</button>
                    </div>
                @endif
                @forelse ($thread as $m)
                    <article class="tc-msg" wire:key="msg-{{ $m['id'] }}">
                        <div class="tc-avatar" aria-hidden="true">{{ $m['sender_initials'] }}</div>
                        <div class="tc-msg-body">
                            <div class="tc-msg-meta">
                                <strong>{{ $m['sender_name'] }}</strong>
                                <time>{{ $m['created_label'] }}</time>
                                @if (! empty($m['edited_at']))
                                    <span class="tc-edited">edited</span>
                                @endif
                                @if ($canDelete && (int) $m['sender_id'] === $meId)
                                    <button type="button" class="tc-msg-del" wire:click="deleteMessage({{ $m['id'] }})" wire:confirm="Delete this message?">Delete</button>
                                @endif
                            </div>
                            @if (! empty($m['body_html']))
                                <p class="tc-msg-text">{!! $m['body_html'] !!}</p>
                            @endif
                            @if (! empty($m['attachments']))
                                <div class="tc-attach-list">
                                    @foreach ($m['attachments'] as $att)
                                        @if (! empty($att['is_image']))
                                            <a class="tc-attach-img" href="{{ $att['url'] }}" target="_blank" rel="noopener">
                                                <img src="{{ $att['url'] }}" alt="{{ $att['name'] }}" loading="lazy">
                                            </a>
                                        @else
                                            <a class="tc-attach-file" href="{{ $att['url'] }}" target="_blank" rel="noopener">
                                                <span class="tc-attach-ico" aria-hidden="true">📎</span>
                                                <span class="tc-attach-meta">
                                                    <strong>{{ $att['name'] }}</strong>
                                                    <small>{{ $att['size_label'] ?? '' }}</small>
                                                </span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="tc-empty">No messages yet. Say hello to the team.</p>
                @endforelse
            </div>

            @if (is_array($pendingFiles) && count($pendingFiles) > 0)
                <div class="tc-pending">
                    @foreach ($pendingFiles as $i => $file)
                        <span class="tc-pending-chip">
                            {{ method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'File' }}
                            <button type="button" wire:click="removePendingFile({{ $i }})" aria-label="Remove file">&times;</button>
                        </span>
                    @endforeach
                </div>
            @endif

            <form class="tc-composer" wire:submit="send">
                <label class="tc-attach-btn" title="Attach pictures or files" @class(['is-disabled' => ! $canEdit || ! $activeChannelId])>
                    <input
                        type="file"
                        wire:model="pendingFiles"
                        multiple
                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,image/*"
                        @disabled(! $canEdit || ! $activeChannelId)
                    >
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                </label>
                <input
                    id="tc-composer"
                    type="text"
                    wire:model="draft"
                    class="so-input"
                    placeholder="{{ $activeMeta ? ((($activeMeta['type'] ?? '') === 'channel') ? 'Message #'.$activeMeta['label'] : 'Message '.$activeMeta['label']) : 'Message' }}"
                    autocomplete="off"
                    maxlength="8000"
                    @disabled(! $canEdit || ! $activeChannelId)
                />
                <button type="submit" class="tc-send" aria-label="Send message" wire:loading.attr="disabled" @disabled(! $canEdit || ! $activeChannelId)>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                </button>
            </form>
            <div class="tc-upload-hint" wire:loading wire:target="pendingFiles">Uploading…</div>
        </section>
    </div>
</div>
