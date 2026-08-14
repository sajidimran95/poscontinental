<?php

namespace App\Livewire;

use App\Livewire\Concerns\PersistsPosAiChat;
use App\Models\Company;
use App\Services\JapsAi\JapsAiChatService;
use Livewire\Component;

class PosAiWidget extends Component
{
    use PersistsPosAiChat;

    public bool $open = false;

    public string $message = '';

    public string $activeQuick = '';

    /** @var list<array{role: string, text: string, tool?: string|null}> */
    public array $messages = [];

    public function mount(): void
    {
        $this->loadPersistedChat();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        if ($this->open) {
            $this->loadPersistedChat();
            $this->scrollBottom();
        }
    }

    public function close(): void
    {
        $this->persistChat();
        $this->open = false;
    }

    public function runQuick(string $intent): void
    {
        $label = collect(JapsAiChatService::QUICK_PROMPTS)->firstWhere('intent', $intent)['label']
            ?? $intent;
        $this->activeQuick = $intent;
        $this->open = true;
        $this->sendChat($label, $intent);
    }

    public function send(): void
    {
        $this->sendChat(trim($this->message), null);
    }

    private function sendChat(string $text, ?string $forcedIntent): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $this->messages[] = $this->posAiMakeMessage('user', $text);
        $this->message = '';
        $this->scrollBottom();

        try {
            $company = Company::query()->findOrFail(auth()->user()->company_id);
            $result = JapsAiChatService::forCompany($company)->handle($text, $forcedIntent);
            $this->messages[] = $this->posAiMakeMessage('assistant', $result['reply'], $result['tool'] ?? null);
        } catch (\Throwable $e) {
            $this->messages[] = $this->posAiMakeMessage('assistant', 'Could not read live data: '.$e->getMessage(), 'error');
        }

        $this->scrollBottom();
        $this->persistChat();
    }

    private function scrollBottom(): void
    {
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    const box = document.getElementById('posai-widget-messages');
                    if (box) box.scrollTop = box.scrollHeight;
                });
            });
        JS);
    }

    public function formatReply(string $text): string
    {
        $escaped = e($text);
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/^### (.+)$/m', '<div class="posai-w-h3">$1</div>', $escaped) ?? $escaped;

        return nl2br($escaped);
    }

    public function render()
    {
        return view('livewire.pos-ai-widget');
    }
}
