<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\ChatChannel;
use App\Models\User;
use App\Services\TeamChatService;
use Illuminate\Http\Request;

class SaleChatController extends Controller
{
    public function __construct(protected TeamChatService $chat) {}

    protected function user(): User
    {
        return auth('sale')->user();
    }

    public function index(?ChatChannel $channel = null)
    {
        $user = $this->user();
        $this->chat->provisionForUser($user);
        $list = $this->chat->listForUser($user);
        $channels = $list->where('type', 'channel')->values();
        $dms = $list->where('type', 'dm')->values();

        $isInbox = $channel === null;
        $activeId = $channel?->id ?: 0;
        if ($activeId < 1) {
            $general = $list->first(fn ($c) => ($c['type'] ?? '') === 'channel' && ($c['name'] ?? '') === 'general')
                ?? $list->first();
            $activeId = $general ? (int) $general['id'] : 0;
        }

        $thread = [];
        $hasOlder = false;
        $olderCursor = null;
        $latestId = 0;
        $activeMeta = $list->firstWhere('id', $activeId);
        $model = null;

        if ($activeId > 0) {
            $model = ChatChannel::query()
                ->where('company_id', $user->company_id)
                ->findOrFail($activeId);
            $payload = $this->chat->messages($model, $user, null, 80);
            $thread = $payload['messages'];
            $hasOlder = $payload['next_cursor'] !== null;
            $olderCursor = $payload['next_cursor'];
            $latestId = (int) $payload['latest_id'];
            $this->chat->markRead($model, $user, $latestId ?: null);
        }

        return view('sale.chat', [
            'sidebarChannels' => $channels,
            'sidebarDms' => $dms,
            'activeId' => $activeId,
            'activeMeta' => $activeMeta,
            'thread' => $thread,
            'hasOlder' => $hasOlder,
            'olderCursor' => $olderCursor,
            'latestId' => $latestId,
            'meId' => (int) $user->id,
            'canSend' => $user->canAccessFeature('team.chat', 'edit') || $user->isSalesRep(),
            'companyUsers' => $this->chat->companyUsers($user),
            'isInbox' => $isInbox,
        ]);
    }

    public function send(Request $request, ChatChannel $channel)
    {
        $user = $this->user();
        abort_unless($user->canAccessFeature('team.chat', 'edit') || $user->isSalesRep(), 403);
        abort_unless((int) $channel->company_id === (int) $user->company_id, 403);
        $data = $request->validate(['body' => 'required|string|max:8000']);
        $message = $this->chat->send($channel, $user, $data['body']);

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['message' => $this->chat->serializeMessage($message)]);
        }

        return redirect()->route('sale.chat', ['channel' => $channel->id]);
    }

    public function poll(Request $request, ChatChannel $channel)
    {
        $user = $this->user();
        abort_unless((int) $channel->company_id === (int) $user->company_id, 403);
        $after = $request->integer('after');
        $incoming = $this->chat->messagesAfter($channel, $user, $after);
        if ($incoming !== []) {
            $this->chat->markRead($channel, $user, (int) last($incoming)['id']);
        }

        return response()->json(['messages' => $incoming]);
    }

    public function older(Request $request, ChatChannel $channel)
    {
        $user = $this->user();
        abort_unless((int) $channel->company_id === (int) $user->company_id, 403);
        $before = $request->integer('before');
        $payload = $this->chat->messages($channel, $user, $before ?: null, 80);

        return response()->json($payload);
    }

    public function dm(Request $request)
    {
        $user = $this->user();
        abort_unless($user->canAccessFeature('team.chat', 'edit') || $user->isSalesRep(), 403);
        $data = $request->validate(['user_id' => 'required|integer']);
        $ch = $this->chat->getOrCreateDm($user, (int) $data['user_id']);

        return redirect()->route('sale.chat', ['channel' => $ch->id]);
    }
}
