<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Services\TeamChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TeamChatController extends Controller
{
    public function __construct(protected TeamChatService $chat) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeChat($request);
        $rows = $this->chat->listForUser($request->user());

        return ApiResponse::success($rows->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeChat($request, 'edit');
        abort_unless($request->user()?->canManageTeamChatChannels(), 403, 'You do not have permission to create channels.');
        $data = $request->validate([
            'name' => 'required|string|max:80',
        ]);
        $channel = $this->chat->createChannel($request->user(), $data['name']);

        return ApiResponse::created(['id' => $channel->id, 'name' => $channel->name, 'type' => $channel->type]);
    }

    public function messages(Request $request, ChatChannel $channel): JsonResponse
    {
        $this->authorizeChat($request);
        $this->assertCompany($request, $channel);
        $payload = $this->chat->messages(
            $channel,
            $request->user(),
            $request->integer('before') ?: null,
            $request->integer('limit') ?: 50
        );

        return ApiResponse::success($payload);
    }

    public function send(Request $request, ChatChannel $channel): JsonResponse
    {
        $this->authorizeChat($request, 'edit');
        $this->assertCompany($request, $channel);
        $data = $request->validate(['body' => 'required|string|max:8000']);
        $message = $this->chat->send($channel, $request->user(), $data['body']);

        return ApiResponse::created($this->chat->serializeMessage($message));
    }

    public function updateMessage(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeChat($request, 'edit');
        $message->load('channel');
        $this->assertCompany($request, $message->channel);
        $data = $request->validate(['body' => 'required|string|max:8000']);
        $updated = $this->chat->edit($message, $request->user(), $data['body']);

        return ApiResponse::success($this->chat->serializeMessage($updated));
    }

    public function destroyMessage(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeChat($request, 'delete');
        $message->load('channel');
        $this->assertCompany($request, $message->channel);
        $this->chat->deleteOwn($message, $request->user());

        return ApiResponse::success(null, 'Deleted.');
    }

    public function addMember(Request $request, ChatChannel $channel): JsonResponse
    {
        $this->authorizeChat($request, 'edit');
        abort_unless($request->user()?->canManageTeamChatChannels(), 403, 'You do not have permission to add people to a channel.');
        $this->assertCompany($request, $channel);
        $data = $request->validate(['user_id' => 'required|integer']);
        $member = $this->chat->addMember($channel, $request->user(), (int) $data['user_id']);

        return ApiResponse::created(['id' => $member->id, 'user_id' => $member->user_id]);
    }

    public function removeMember(Request $request, ChatChannel $channel, int $userId): JsonResponse
    {
        $this->authorizeChat($request, 'edit');
        abort_unless($request->user()?->canManageTeamChatChannels(), 403, 'You do not have permission to remove people from a channel.');
        $this->assertCompany($request, $channel);
        $this->chat->removeMember($channel, $request->user(), $userId);

        return ApiResponse::success(null, 'Removed.');
    }

    public function dm(Request $request): JsonResponse
    {
        $this->authorizeChat($request, 'edit');
        $data = $request->validate(['user_id' => 'required|integer']);
        $channel = $this->chat->getOrCreateDm($request->user(), (int) $data['user_id']);

        return ApiResponse::success(['id' => $channel->id, 'type' => $channel->type]);
    }

    public function read(Request $request, ChatChannel $channel): JsonResponse
    {
        $this->authorizeChat($request);
        $this->assertCompany($request, $channel);
        $this->chat->markRead($channel, $request->user(), $request->integer('message_id') ?: null);

        return ApiResponse::success(null);
    }

    protected function authorizeChat(Request $request, string $action = 'view'): void
    {
        abort_unless($request->user()?->canAccessFeature('team.chat', $action), 403);
    }

    protected function assertCompany(Request $request, ?ChatChannel $channel): void
    {
        abort_unless($channel && $channel->company_id === $request->user()->company_id, 403);
    }
}
