<?php

namespace App\Http\Controllers;

use App\Models\ChatMessageAttachment;
use App\Models\User;
use App\Services\TeamChatService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamChatAttachmentController extends Controller
{
    public function __construct(protected TeamChatService $chat) {}

    public function show(ChatMessageAttachment $attachment): StreamedResponse
    {
        $user = $this->resolveUser();
        abort_unless($user, 401);

        $attachment->loadMissing('message.channel');
        $message = $attachment->message;
        $channel = $message?->channel;
        abort_unless($message && $channel, 404);
        abort_unless((int) $channel->company_id === (int) $user->company_id, 403);

        $this->chat->assertMember($channel, $user);

        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);

        $disposition = $attachment->isImage() ? 'inline' : 'attachment';

        return Storage::disk($disk)->response(
            $attachment->path,
            $attachment->original_name ?: basename($attachment->path),
            [
                'Content-Type' => $attachment->mime ?: 'application/octet-stream',
            ],
            $disposition
        );
    }

    protected function resolveUser(): ?User
    {
        /** @var User|null $user */
        $user = auth()->user() ?? auth('sale')->user();

        return $user;
    }
}
