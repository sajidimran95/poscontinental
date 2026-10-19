<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime);

        return str_starts_with($mime, 'image/');
    }
}
