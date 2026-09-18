<?php

namespace App\Livewire;

use App\Livewire\Concerns\PersistsPosAiChat;
use App\Models\Company;
use App\Services\JapsAi\InvoiceExtractionService;
use App\Services\JapsAi\JapsAiChatService;
use Livewire\Component;
use Livewire\WithFileUploads;

class PosAiWidget extends Component
{
    use PersistsPosAiChat;
    use WithFileUploads;

    public bool $open = false;

    public string $message = '';

    public string $activeQuick = '';

    /** @var mixed */
    public $chatInvoiceFile = null;

    /** @var list<array{role: string, text: string, tool?: string|null}> */
    public array $messages = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);
        $this->loadPersistedChat();
    }

    public function toggle(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);
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

    public function updatedChatInvoiceFile(): void
    {
        if ($this->chatInvoiceFile) {
            $this->processChatVendorInvoice();
        }
    }

    public function processChatVendorInvoice(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);

        $this->open = true;
        $this->resetValidation('chatInvoiceFile');

        try {
            $this->validate([
                'chatInvoiceFile' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4800'],
            ]);
        } catch (\Throwable $e) {
            $this->messages[] = $this->posAiMakeMessage(
                'assistant',
                'Could not attach that file. Use JPG, PNG, WebP, or PDF (max about 4.5 MB).',
                'error'
            );
            $this->chatInvoiceFile = null;
            $this->scrollBottom();
            $this->persistChat();

            return;
        }

        $this->messages[] = $this->posAiMakeMessage('user', 'Vendor invoice attached — reading with POS AI…');
        $this->scrollBottom();

        try {
            $company = Company::query()->findOrFail(auth()->user()->company_id);
            $result = InvoiceExtractionService::forCompany($company)->extract($this->chatInvoiceFile);

            if (empty($result['success'])) {
                $this->messages[] = $this->posAiMakeMessage(
                    'assistant',
                    (string) ($result['msg'] ?? 'Could not read that vendor invoice.'),
                    'error'
                );
                $this->chatInvoiceFile = null;
                $this->scrollBottom();
                $this->persistChat();

                return;
            }

            $matched = InvoiceExtractionService::forCompany($company)->matchToCatalog($result['data'] ?? []);
            $lines = $matched['lines'] ?? [];
            $matchedCount = collect($lines)->where('item_id', '>', 0)->count();
            $totalLines = count($lines);

            session([
                'pos_ai_pending_vendor_invoice' => [
                    'header' => [
                        'supplier_name' => $matched['supplier_name'] ?? null,
                        'supplier_id' => $matched['supplier_id'] ?? null,
                        'ref_no' => $matched['ref_no'] ?? null,
                        'invoice_date' => $matched['invoice_date'] ?? null,
                        'total' => $matched['total'] ?? null,
                    ],
                    'lines' => $lines,
                    'status' => $totalLines > 0
                        ? 'From POS AI chat — review matched lines, then Insert into PO.'
                        : 'No line items found on this document.',
                ],
            ]);

            $poUrl = route('purchasing.orders.create', ['ai_invoice' => 1]);
            $supplier = trim((string) ($matched['supplier_name'] ?? '')) ?: '—';
            $reply = "Read vendor invoice for **{$company->name}**.\n\n"
                ."- **Supplier:** {$supplier}\n"
                ."- **Lines found:** {$totalLines} ({$matchedCount} matched to catalog)\n\n"
                ."### Next step\n"
                ."Open **New Purchase Order** to review and insert lines:\n"
                ."{$poUrl}\n\n"
                .'You can also upload from **Purchasing → Orders → New → Scan vendor invoice (POS AI)**.';

            $this->messages[] = $this->posAiMakeMessage('assistant', $reply, 'openai');
        } catch (\Throwable $e) {
            $this->messages[] = $this->posAiMakeMessage(
                'assistant',
                'Invoice scan failed: '.$e->getMessage(),
                'error'
            );
        }

        $this->chatInvoiceFile = null;
        $this->scrollBottom();
        $this->persistChat();
    }

    public function runQuick(string $intent): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);
        $label = collect(JapsAiChatService::QUICK_PROMPTS)->firstWhere('intent', $intent)['label']
            ?? $intent;
        $this->activeQuick = $intent;
        $this->open = true;
        $this->sendChat($label, $intent);
    }

    public function send(): void
    {
        abort_unless(auth()->user()?->canUsePosAiChat() ?? false, 403);
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
        $escaped = preg_replace(
            '#(https?://[^\s<]+)#',
            '<a class="posai-w-a" href="$1">$1</a>',
            $escaped
        ) ?? $escaped;

        return nl2br($escaped);
    }

    public function render()
    {
        return view('livewire.pos-ai-widget');
    }
}
