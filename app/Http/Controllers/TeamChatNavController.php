<?php

namespace App\Http\Controllers;

use App\Services\TeamChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamChatNavController extends Controller
{
    public function unread(Request $request, TeamChatService $chat): JsonResponse
    {
        if (! $request->user()?->canAccessFeature('team.chat', 'view')) {
            return response()->json(['unread' => 0]);
        }

        return response()->json($chat->unreadSummary($request->user()));
    }
}
