<?php

namespace App\Livewire\Concerns;

use App\Models\PosAiChat;
use Illuminate\Support\Facades\Schema;

/**
 * Keep POS AI chat history across close, page changes, and later logins.
 */
trait PersistsPosAiChat
{
    /** Keep a long rolling history (greeting + recent turns). */
    protected int $posAiMaxMessages = 400;

    protected function posAiGreeting(): array
    {
        return $this->posAiMakeMessage(
            'assistant',
            "Hi! I'm POS AI for this company only. "
                .'**Suggested questions are free** (live POS data, no OpenAI credits). '
                .'I only discuss wholesale POS topics — not general questions. '
                .'Typed free-form text needs an OpenAI key with available credits.'
        );
    }

    /**
     * @return array{role: string, text: string, tool: string|null, at: string}
     */
    protected function posAiMakeMessage(string $role, string $text, ?string $tool = null): array
    {
        return [
            'role' => $role,
            'text' => $text,
            'tool' => $tool,
            'at' => now()->toIso8601String(),
        ];
    }

    public function formatChatDay(?string $at): string
    {
        $dt = $this->parseChatAt($at);
        if (! $dt) {
            return '';
        }

        if ($dt->isToday()) {
            return 'Today · '.$dt->format('M j, Y');
        }
        if ($dt->isYesterday()) {
            return 'Yesterday · '.$dt->format('M j, Y');
        }

        return $dt->format('l, M j, Y');
    }

    public function formatChatTime(?string $at): string
    {
        $dt = $this->parseChatAt($at);

        return $dt ? $dt->format('g:i A') : '';
    }

    protected function parseChatAt(?string $at): ?\Carbon\CarbonInterface
    {
        if (! is_string($at) || trim($at) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($at)->timezone((string) (config('app.timezone') ?: 'UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    protected function loadPersistedChat(): void
    {
        $user = auth()->user();
        if (! $user) {
            $this->messages = [$this->posAiGreeting()];

            return;
        }

        if (! $this->posAiChatTableReady()) {
            $this->messages = [$this->posAiGreeting()];

            return;
        }

        try {
            $row = PosAiChat::query()
                ->where('company_id', (int) $user->company_id)
                ->where('user_id', (int) $user->id)
                ->first();
        } catch (\Throwable) {
            $this->messages = [$this->posAiGreeting()];

            return;
        }

        $msgs = is_array($row?->messages) ? $row->messages : [];
        $msgs = array_values(array_filter($msgs, function ($m) {
            return is_array($m)
                && in_array($m['role'] ?? '', ['user', 'assistant'], true)
                && is_string($m['text'] ?? null)
                && trim($m['text']) !== '';
        }));

        $this->messages = $msgs !== [] ? $msgs : [$this->posAiGreeting()];
    }

    protected function persistChat(): void
    {
        $user = auth()->user();
        if (! $user || ! $this->posAiChatTableReady()) {
            return;
        }

        try {
            PosAiChat::query()->updateOrCreate(
                [
                    'company_id' => (int) $user->company_id,
                    'user_id' => (int) $user->id,
                ],
                [
                    'messages' => $this->trimmedChatMessages(),
                ]
            );
        } catch (\Throwable) {
            // Table missing or not migrated yet — keep chatting without saved history.
        }
    }

    protected function posAiChatTableReady(): bool
    {
        try {
            return Schema::hasTable('pos_ai_chats');
        } catch (\Throwable) {
            return false;
        }
    }

    public function clearChat(): void
    {
        $this->messages = [$this->posAiGreeting()];
        $this->activeQuick = '';
        $this->persistChat();
    }

    /** @return list<array{role: string, text: string, tool?: string|null}> */
    protected function trimmedChatMessages(): array
    {
        $msgs = [];
        foreach ($this->messages as $m) {
            if (! is_array($m) || ! isset($m['role'], $m['text'])) {
                continue;
            }
            $msgs[] = [
                'role' => (string) $m['role'],
                'text' => (string) $m['text'],
                'tool' => $m['tool'] ?? null,
                'at' => is_string($m['at'] ?? null) ? $m['at'] : null,
            ];
        }

        $max = max(20, $this->posAiMaxMessages);
        if (count($msgs) <= $max) {
            return $msgs;
        }

        $keepGreeting = ($msgs[0]['role'] ?? '') === 'assistant' && ! isset($msgs[0]['tool']);
        if ($keepGreeting) {
            return array_merge([$msgs[0]], array_slice($msgs, -($max - 1)));
        }

        return array_slice($msgs, -$max);
    }
}
