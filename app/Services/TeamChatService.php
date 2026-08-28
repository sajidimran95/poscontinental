<?php

namespace App\Services;

use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamChatService
{
    public function provisionForUser(User $user): void
    {
        $companyId = (int) $user->company_id;
        if ($companyId <= 0) {
            return;
        }

        $general = ChatChannel::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'type' => ChatChannel::TYPE_CHANNEL,
                'name' => 'general',
            ],
            [
                'is_default' => true,
                'created_by' => $user->id,
            ]
        );

        if (! $general->is_default) {
            $general->is_default = true;
            $general->save();
        }

        $this->ensureDefaultChannelMembers($general, $companyId);
    }

    /**
     * #general always includes every company user.
     * Other channels only include users who were added.
     */
    public function ensureDefaultChannelMembers(ChatChannel $channel, int $companyId): void
    {
        if (! $channel->isChannel() || (! $channel->is_default && $channel->name !== 'general')) {
            return;
        }

        if (! $channel->is_default) {
            $channel->is_default = true;
            $channel->save();
        }

        $userIds = User::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $now = now();
        foreach ($userIds as $userId) {
            ChatChannelMember::query()->firstOrCreate(
                [
                    'channel_id' => $channel->id,
                    'user_id' => $userId,
                ],
                ['joined_at' => $now]
            );
        }
    }

    public function syncPublicChannelMembers(int $companyId): void
    {
        $general = ChatChannel::query()
            ->where('company_id', $companyId)
            ->where('type', ChatChannel::TYPE_CHANNEL)
            ->where('is_default', true)
            ->first();

        if ($general) {
            $this->ensureDefaultChannelMembers($general, $companyId);
        }
    }

    public function membership(ChatChannel $channel, User $user): ?ChatChannelMember
    {
        return ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function assertMember(ChatChannel $channel, User $user): ChatChannelMember
    {
        abort_unless($channel->company_id === $user->company_id, 403);

        if ($user->canManageTeamChatChannels() && $channel->isChannel()) {
            return ChatChannelMember::query()->firstOrCreate(
                [
                    'channel_id' => $channel->id,
                    'user_id' => $user->id,
                ],
                ['joined_at' => now()]
            );
        }

        $member = $this->membership($channel, $user);
        abort_unless($member, 403, 'You are not a member of this channel.');

        return $member;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForUser(User $user): Collection
    {
        $this->provisionForUser($user);

        $channels = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->when(
                ! $user->canManageTeamChatChannels(),
                fn ($q) => $q->whereHas('memberships', fn ($m) => $m->where('user_id', $user->id))
            )
            ->when(
                $user->canManageTeamChatChannels(),
                fn ($q) => $q->where(function ($inner) use ($user) {
                    $inner->where('type', ChatChannel::TYPE_CHANNEL)
                        ->orWhereHas('memberships', fn ($m) => $m->where('user_id', $user->id));
                })
            )
            ->with(['memberships' => fn ($q) => $q->where('user_id', $user->id)])
            ->withCount('members')
            ->orderByRaw("CASE WHEN type = 'channel' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $peerIds = $channels->where('type', ChatChannel::TYPE_DM)->pluck('id');
        $peerNames = [];
        if ($peerIds->isNotEmpty()) {
            $rows = ChatChannelMember::query()
                ->whereIn('channel_id', $peerIds)
                ->where('user_id', '!=', $user->id)
                ->with('user:id,name,is_active')
                ->get();
            foreach ($rows as $row) {
                $peerNames[$row->channel_id][] = $row->user;
            }
        }

        return $channels->map(function (ChatChannel $channel) use ($user, $peerNames) {
            $pivot = $channel->memberships->first();
            $lastRead = (int) ($pivot?->last_read_message_id ?? 0);
            $unread = ChatMessage::query()
                ->where('channel_id', $channel->id)
                ->where('id', '>', $lastRead)
                ->whereNull('deleted_at')
                ->count();

            $label = $channel->name;
            $peers = $peerNames[$channel->id] ?? [];
            if ($channel->isDm()) {
                $names = collect($peers)->pluck('name')->filter()->values();
                $label = $names->isNotEmpty() ? $names->implode(', ') : 'Direct message';
            }

            return [
                'id' => $channel->id,
                'name' => $channel->name,
                'label' => $label,
                'type' => $channel->type,
                'is_default' => (bool) $channel->is_default,
                'unread' => $unread,
                'member_count' => (int) $channel->members_count,
                'online_hint' => collect($peers)->contains(fn ($u) => (bool) ($u->is_active ?? false)),
                'peer_ids' => collect($peers)->pluck('id')->all(),
            ];
        });
    }

    /**
     * Unread count + latest preview for navbar badges (other people's messages only).
     *
     * @return array{unread: int, preview: ?array{from: string, body: string, channel: string}, latest_id: int}
     */
    public function unreadSummary(User $user): array
    {
        $userId = (int) $user->id;
        $companyId = (int) $user->company_id;

        $query = ChatMessage::query()
            ->join('chat_channel_members as mem', function ($join) use ($userId) {
                $join->on('mem.channel_id', '=', 'chat_messages.channel_id')
                    ->where('mem.user_id', '=', $userId);
            })
            ->join('chat_channels as ch', 'ch.id', '=', 'chat_messages.channel_id')
            ->where('ch.company_id', $companyId)
            ->where('chat_messages.sender_id', '!=', $userId)
            ->whereRaw('chat_messages.id > COALESCE(mem.last_read_message_id, 0)');

        $unread = (int) (clone $query)->count('chat_messages.id');

        $latest = (clone $query)
            ->with('sender:id,name')
            ->orderByDesc('chat_messages.id')
            ->select('chat_messages.*', 'ch.name as channel_name', 'ch.type as channel_type')
            ->first();

        $preview = null;
        if ($latest) {
            $channelLabel = (string) ($latest->channel_name ?? 'Chat');
            if (($latest->channel_type ?? '') === ChatChannel::TYPE_DM) {
                $channelLabel = 'Direct message';
            }

            $preview = [
                'from' => (string) ($latest->sender?->name ?: 'Someone'),
                'body' => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $latest->body) ?? ''), 90),
                'channel' => $channelLabel,
            ];
        }

        return [
            'unread' => $unread,
            'preview' => $preview,
            'latest_id' => (int) ($latest?->id ?? 0),
        ];
    }

    public function createChannel(User $user, string $name): ChatChannel
    {
        $name = Str::lower(trim($name));
        $name = preg_replace('/[^a-z0-9\-_]/', '', $name) ?: '';
        abort_if($name === '', 422, 'Channel name is required.');

        $exists = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->where('type', ChatChannel::TYPE_CHANNEL)
            ->where('name', $name)
            ->exists();
        abort_if($exists, 422, 'A channel with that name already exists.');
        $this->assertChannelAdmin($user);

        return DB::transaction(function () use ($user, $name) {
            $channel = ChatChannel::query()->create([
                'company_id' => $user->company_id,
                'name' => $name,
                'type' => ChatChannel::TYPE_CHANNEL,
                'is_default' => false,
                'created_by' => $user->id,
            ]);
            ChatChannelMember::query()->firstOrCreate(
                [
                    'channel_id' => $channel->id,
                    'user_id' => $user->id,
                ],
                ['joined_at' => now()]
            );

            return $channel->fresh();
        });
    }

    public function getOrCreateDm(User $user, int $otherUserId): ChatChannel
    {
        abort_if($otherUserId === (int) $user->id, 422, 'Cannot start a DM with yourself.');

        $other = User::query()
            ->where('company_id', $user->company_id)
            ->find($otherUserId);
        abort_unless($other, 404, 'User not found.');

        $existing = ChatChannel::query()
            ->where('company_id', $user->company_id)
            ->where('type', ChatChannel::TYPE_DM)
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $other->id))
            ->withCount('members')
            ->get()
            ->first(fn (ChatChannel $c) => (int) $c->members_count === 2);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $other) {
            $channel = ChatChannel::query()->create([
                'company_id' => $user->company_id,
                'name' => null,
                'type' => ChatChannel::TYPE_DM,
                'is_default' => false,
                'created_by' => $user->id,
            ]);
            $now = now();
            ChatChannelMember::query()->create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'joined_at' => $now,
            ]);
            ChatChannelMember::query()->create([
                'channel_id' => $channel->id,
                'user_id' => $other->id,
                'joined_at' => $now,
            ]);

            return $channel;
        });
    }

    /**
     * @return array{messages: list<array<string, mixed>>, next_cursor: int|null, latest_id: int}
     */
    public function messages(ChatChannel $channel, User $user, ?int $beforeId = null, int $limit = 80): array
    {
        $this->assertMember($channel, $user);
        $limit = max(1, min(100, $limit));

        $q = ChatMessage::query()
            ->with('sender:id,name')
            ->where('channel_id', $channel->id)
            ->orderByDesc('id')
            ->limit($limit + 1);

        if ($beforeId) {
            $q->where('id', '<', $beforeId);
        }

        $rows = $q->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->slice(0, $limit);
        }

        $ordered = $rows->reverse()->values();
        $messages = $ordered->map(fn (ChatMessage $m) => $this->serializeMessage($m))->all();

        return [
            'messages' => $messages,
            'next_cursor' => $hasMore ? (int) $ordered->first()->id : null,
            'latest_id' => (int) ($ordered->last()?->id ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesAfter(ChatChannel $channel, User $user, int $afterId): array
    {
        $this->assertMember($channel, $user);

        return ChatMessage::query()
            ->with('sender:id,name')
            ->where('channel_id', $channel->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(80)
            ->get()
            ->map(fn (ChatMessage $m) => $this->serializeMessage($m))
            ->all();
    }

    public function send(ChatChannel $channel, User $user, string $body): ChatMessage
    {
        $this->assertMember($channel, $user);
        $body = trim($body);
        abort_if($body === '', 422, 'Message cannot be empty.');
        abort_if(mb_strlen($body) > 8000, 422, 'Message is too long.');

        $message = ChatMessage::query()->create([
            'channel_id' => $channel->id,
            'sender_id' => $user->id,
            'body' => $body,
        ]);

        ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->update(['last_read_message_id' => $message->id]);

        return $message->load('sender:id,name');
    }

    public function edit(ChatMessage $message, User $user, string $body): ChatMessage
    {
        abort_unless((int) $message->sender_id === (int) $user->id, 403);
        abort_unless($message->channel?->company_id === $user->company_id, 403);
        $body = trim($body);
        abort_if($body === '', 422, 'Message cannot be empty.');

        $message->body = $body;
        $message->edited_at = now();
        $message->save();

        return $message->load('sender:id,name');
    }

    public function deleteOwn(ChatMessage $message, User $user): void
    {
        abort_unless((int) $message->sender_id === (int) $user->id, 403);
        abort_unless($message->channel?->company_id === $user->company_id, 403);
        $message->delete();
    }

    public function markRead(ChatChannel $channel, User $user, ?int $messageId = null): void
    {
        $member = $this->assertMember($channel, $user);
        $maxId = $messageId
            ?: (int) ChatMessage::query()->where('channel_id', $channel->id)->max('id');
        if ($maxId <= 0) {
            return;
        }
        if ((int) $member->last_read_message_id >= $maxId) {
            return;
        }
        $member->last_read_message_id = $maxId;
        $member->save();
    }

    public function addMember(ChatChannel $channel, User $actor, int $userId): ChatChannelMember
    {
        abort_unless($channel->company_id === $actor->company_id, 403);
        $this->assertChannelAdmin($actor);
        abort_unless($channel->isChannel(), 422, 'Cannot add members to a DM this way.');
        abort_if($channel->is_default || $channel->name === 'general', 422, '#general already includes every user.');

        $target = User::query()
            ->where('company_id', $actor->company_id)
            ->find($userId);
        abort_unless($target, 404, 'User not found.');

        return ChatChannelMember::query()->firstOrCreate(
            [
                'channel_id' => $channel->id,
                'user_id' => $target->id,
            ],
            ['joined_at' => now()]
        );
    }

    public function removeMember(ChatChannel $channel, User $actor, int $userId): void
    {
        abort_unless($channel->company_id === $actor->company_id, 403);
        $this->assertChannelAdmin($actor);
        abort_if($channel->is_default && $channel->name === 'general', 422, 'Cannot remove members from #general.');

        ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * @return list<array{id:int,name:string,is_active:bool}>
     */
    public function channelMembers(ChatChannel $channel): array
    {
        if ($channel->isChannel() && ($channel->is_default || $channel->name === 'general')) {
            $this->ensureDefaultChannelMembers($channel, (int) $channel->company_id);

            return User::query()
                ->where('company_id', $channel->company_id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active'])
                ->map(fn (User $u) => [
                    'id' => (int) $u->id,
                    'name' => (string) $u->name,
                    'is_active' => true,
                ])
                ->all();
        }

        return $channel->members()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.is_active'])
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'is_active' => true,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,is_active:bool}>
     */
    public function companyUsers(User $user, bool $activeOnly = false): array
    {
        return User::query()
            ->where('company_id', $user->company_id)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->whereKeyNot($user->id)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'is_active' => true,
            ])
            ->all();
    }

    protected function assertChannelAdmin(User $user): void
    {
        abort_unless($user->canManageTeamChatChannels(), 403, 'You do not have permission to manage channels.');
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMessage(ChatMessage $message): array
    {
        $name = (string) ($message->sender?->name ?? 'User');
        $initials = collect(preg_split('/\s+/', $name) ?: [])
            ->filter()
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->take(2)
            ->implode('');

        return [
            'id' => $message->id,
            'channel_id' => $message->channel_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $name,
            'sender_initials' => $initials !== '' ? $initials : 'U',
            'body' => $message->body,
            'body_html' => $this->highlightMentions((string) $message->body),
            'edited_at' => optional($message->edited_at)?->toIso8601String(),
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'created_label' => optional($message->created_at)?->timezone(config('app.timezone'))->format('g:i a'),
            'deleted' => $message->trashed(),
        ];
    }

    public function highlightMentions(string $body): string
    {
        $escaped = nl2br(e($body));

        return preg_replace(
            '/@([A-Za-z0-9._\-]+)/',
            '<span class="tc-mention">@$1</span>',
            $escaped
        ) ?? $escaped;
    }
}
