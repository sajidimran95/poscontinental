<?php

namespace App\Http\Controllers;

use App\Services\TeamChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamChatNavController extends Controller
{
    public function unread(Request $request, TeamChatService $chat): JsonResponse
    {
        abort_unless($request->user()?->canAccessFeature('team.chat', 'view'), 403);

        return response()->json($chat->unreadSummary($request->user()));
    }
}
