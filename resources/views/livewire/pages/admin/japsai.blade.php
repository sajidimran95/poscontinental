<?php

use App\Models\Company;
use App\Services\JapsAi\BusinessInsightsService;
use App\Services\JapsAi\JapsAiChatService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('POS AI')] class extends Component
{
    /** insights | chat | settings */
    public string $panel = 'insights';

    public string $message = '';

    public string $activeQuick = '';

    /** @var list<array{role: string, text: string, tool?: string|null}> */
    public array $messages = [];

    // Settings form
    public bool $japs_ai_enabled = false;

    public bool $japs_ai_widget_enabled = false;

    public string $japs_ai_api_key = '';

    public bool $clear_api_key = false;

    public string $japs_ai_model = 'gpt-4o-mini';

    public bool $has_saved_api_key = false;

    public string $statusMessage = '';

    /** @var array<string, mixed>|null */
    public ?array $overview = null;

    public function mount(): void
    {
        $this->loadSettingsForm();
        $this->refreshOverview();
        $this->messages = [[
            'role' => 'assistant',
            'text' => "Hi! I'm POS AI. Ask me about today's sales, inventory, invoices, or pipeline — I summarise live company data and suggest next actions.",
            'tool' => null,
        ]];
    }

    public function setPanel(string $panel): void
    {
        if (! in_array($panel, ['insights', 'chat', 'settings'], true)) {
            return;
        }
        $this->panel = $panel;
        $this->statusMessage = '';
        if ($panel === 'insights') {
            $this->refreshOverview();
        }
        if ($panel === 'settings') {
            $this->loadSettingsForm();
        }
        if ($panel === 'chat') {
            $this->dispatchScroll();
        }
    }

    public function refreshOverview(): void
    {
        $companyId = (int) auth()->user()->company_id;
        $this->overview = BusinessInsightsService::forCompany($companyId)->overview();
    }

    public function loadSettingsForm(): void
    {
        $company = auth()->user()?->company;
        $this->japs_ai_enabled = (bool) ($company?->japs_ai_enabled ?? false);
        $this->japs_ai_widget_enabled = (bool) ($company?->japs_ai_widget_enabled ?? false);
        $this->japs_ai_model = (string) ($company?->japs_ai_model ?: 'gpt-4o-mini');
        $this->japs_ai_api_key = '';
        $this->clear_api_key = false;
        $this->has_saved_api_key = filled($company?->japs_ai_api_key);
    }

    public function saveSettings(): void
    {
        $this->statusMessage = '';
        $this->validate([
            'japs_ai_enabled' => ['boolean'],
            'japs_ai_widget_enabled' => ['boolean'],
            'japs_ai_model' => ['required', 'string', 'max:64'],
            'japs_ai_api_key' => ['nullable', 'string', 'max:500'],
            'clear_api_key' => ['boolean'],
        ]);

        $company = Company::query()->findOrFail(auth()->user()->company_id);
        $data = [
            'japs_ai_enabled' => $this->japs_ai_enabled,
            'japs_ai_widget_enabled' => $this->japs_ai_widget_enabled,
            'japs_ai_model' => trim($this->japs_ai_model) ?: 'gpt-4o-mini',
        ];

        if ($this->clear_api_key) {
            $data['japs_ai_api_key'] = null;
        } elseif (trim($this->japs_ai_api_key) !== '') {
            $data['japs_ai_api_key'] = trim($this->japs_ai_api_key);
        }

        $company->update($data);
        $this->loadSettingsForm();
        $this->statusMessage = 'POS AI settings saved.';
    }

    public function runQuick(string $intent): void
    {
        $label = collect(JapsAiChatService::QUICK_PROMPTS)->firstWhere('intent', $intent)['label']
            ?? $intent;
        $this->activeQuick = $intent;
        $this->panel = 'chat';
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

        $this->messages[] = ['role' => 'user', 'text' => $text, 'tool' => null];
        $this->message = '';
        $this->dispatchScroll();

        try {
            $company = Company::query()->findOrFail(auth()->user()->company_id);
            $svc = JapsAiChatService::forCompany($company);
            $result = $svc->handle($text, $forcedIntent);
            $this->messages[] = [
                'role' => 'assistant',
                'text' => $result['reply'],
                'tool' => $result['tool'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->messages[] = [
                'role' => 'assistant',
                'text' => 'Something went wrong reading live data: '.$e->getMessage(),
                'tool' => 'error',
            ];
        }

        $this->refreshOverview();
        $this->dispatchScroll();
    }

    private function dispatchScroll(): void
    {
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    const box = document.getElementById('posai-messages');
                    if (box) {
                        box.scrollTop = box.scrollHeight;
                    }
                });
            });
        JS);
    }

    public function formatReply(string $text): string
    {
        $escaped = e($text);
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/^### (.+)$/m', '<div class="posai-h3">$1</div>', $escaped) ?? $escaped;

        return nl2br($escaped);
    }
}; ?>

<div class="posai-wrap {{ $panel === 'chat' ? 'is-chat' : '' }}">
    <x-action-bar title="POS AI" />

    <div class="posai-shell {{ $panel === 'chat' ? 'is-chat' : '' }}">
        <header class="posai-head">
            <div class="posai-brand">
                <span class="posai-mark" aria-hidden="true">AI</span>
                <div>
                    <div class="posai-brand-title">POS AI</div>
                    <div class="posai-brand-sub">Live sales · stock · invoices · pipeline</div>
                </div>
            </div>
            <div class="posai-tabs" role="tablist">
                <button type="button" class="posai-tab {{ $panel === 'insights' ? 'is-active' : '' }}" wire:click="setPanel('insights')">Insights</button>
                <button type="button" class="posai-tab {{ $panel === 'chat' ? 'is-active' : '' }}" wire:click="setPanel('chat')">Chat</button>
                <button type="button" class="posai-tab {{ $panel === 'settings' ? 'is-active' : '' }}" wire:click="setPanel('settings')">Settings</button>
            </div>
        </header>

        @if ($panel === 'settings')
            <div class="posai-panel">
                <h3 class="posai-section-title">Settings</h3>

                @if ($statusMessage !== '')
                    <div class="stamp-inv-flash stamp-inv-flash-ok" role="status" style="margin:0 0 .85rem;">{{ $statusMessage }}</div>
                @endif
                @if ($errors->any())
                    <div class="stamp-inv-flash stamp-inv-flash-err" role="alert" style="margin:0 0 .85rem;">
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form wire:submit.prevent="saveSettings" class="posai-settings-form" autocomplete="off">
                    <label class="posai-check">
                        <input type="checkbox" wire:model="japs_ai_enabled" />
                        <span><strong>Enable POS AI</strong> free-form OpenAI answers</span>
                    </label>

                    <label class="posai-check">
                        <input type="checkbox" wire:model="japs_ai_widget_enabled" />
                        <span><strong>Show chat widget</strong> on all pages (bottom-right icon)</span>
                    </label>

                    <label class="posai-field">
                        <span>OpenAI API Key</span>
                        <input type="password" wire:model="japs_ai_api_key" class="desk-input" placeholder="{{ $has_saved_api_key ? '•••••••• (saved)' : 'sk-…' }}" autocomplete="new-password" />
                        <small>Leave blank to use OPENAI_API_KEY from .env{{ $has_saved_api_key ? ' (company key is saved)' : '' }}</small>
                    </label>

                    <label class="posai-check">
                        <input type="checkbox" wire:model="clear_api_key" />
                        <span>Clear saved API key</span>
                    </label>

                    <label class="posai-field">
                        <span>Model</span>
                        <input type="text" wire:model="japs_ai_model" class="desk-input" list="posai-models" />
                        <datalist id="posai-models">
                            <option value="gpt-4o-mini"></option>
                            <option value="gpt-4o"></option>
                            <option value="gpt-4.1-mini"></option>
                        </datalist>
                    </label>

                    <div class="posai-actions-row">
                        <button type="submit" class="desk-btn desk-btn-primary">Save</button>
                        <button type="button" class="desk-btn" wire:click="setPanel('chat')">Back to Chat</button>
                    </div>
                </form>
                <p class="posai-hint">
                    Quick answers use live POS data (no OpenAI required). Free-form chat needs enable + API key.
                    When the widget is on, an AI button appears bottom-right on every page for users with POS AI access.
                </p>
            </div>
        @elseif ($panel === 'insights')
            @php $o = $overview ?? []; @endphp
            <div class="posai-panel">
                <div class="posai-cards">
                    <article class="posai-card accent-sales">
                        <div class="posai-card-top">
                            <span class="posai-card-ico" aria-hidden="true">$</span>
                            <span class="posai-card-label">Sales</span>
                        </div>
                        <div class="posai-card-hero">${{ number_format((float) data_get($o, 'sales.today.total', 0), 2) }}</div>
                        <div class="posai-card-sub">Today · {{ (int) data_get($o, 'sales.today.orders', 0) }} orders</div>
                        <div class="posai-card-divider"></div>
                        <ul class="posai-card-meta">
                            <li><span>Last 30 days</span><strong>${{ number_format((float) data_get($o, 'sales.last_30_days.total', 0), 2) }}</strong></li>
                            <li><span>Orders (30d)</span><strong>{{ (int) data_get($o, 'sales.last_30_days.orders', 0) }}</strong></li>
                            <li><span>Avg order</span><strong>${{ number_format((float) data_get($o, 'sales.last_30_days.avg', 0), 2) }}</strong></li>
                            <li><span>Customers</span><strong>{{ (int) data_get($o, 'sales.customers_on_file', 0) }}</strong></li>
                        </ul>
                    </article>

                    <article class="posai-card accent-stock">
                        <div class="posai-card-top">
                            <span class="posai-card-ico" aria-hidden="true">#</span>
                            <span class="posai-card-label">Inventory</span>
                        </div>
                        <div class="posai-card-hero sm">
                            {{ (int) data_get($o, 'inventory.need_attention', 0) }}
                            <span class="posai-card-hero-soft">of {{ (int) data_get($o, 'inventory.products', 0) }}</span>
                        </div>
                        <div class="posai-card-sub">Products need attention</div>
                        <div class="posai-card-divider"></div>
                        <ul class="posai-card-meta">
                            <li><span>Out of stock</span><strong>{{ (int) data_get($o, 'inventory.out_of_stock', 0) }}</strong></li>
                            <li><span>Below reorder</span><strong>{{ (int) data_get($o, 'inventory.below_reorder', 0) }}</strong></li>
                        </ul>
                        @if (! empty(data_get($o, 'inventory.samples')))
                            <ul class="posai-card-list">
                                @foreach (array_slice(data_get($o, 'inventory.samples', []), 0, 3) as $s)
                                    <li><span class="trunc">{{ $s['name'] }}</span> <em>{{ $s['qty'] }}/{{ $s['reorder'] }}</em></li>
                                @endforeach
                            </ul>
                        @endif
                    </article>

                    <article class="posai-card accent-ar">
                        <div class="posai-card-top">
                            <span class="posai-card-ico" aria-hidden="true">i</span>
                            <span class="posai-card-label">Invoices</span>
                        </div>
                        <div class="posai-card-hero">${{ number_format((float) data_get($o, 'invoices.outstanding', 0), 2) }}</div>
                        <div class="posai-card-sub">Outstanding open balance</div>
                        <div class="posai-card-divider"></div>
                        <ul class="posai-card-meta">
                            <li><span>Open invoices</span><strong>{{ (int) data_get($o, 'invoices.open', 0) }}</strong></li>
                            <li><span>Older open</span><strong>{{ (int) data_get($o, 'invoices.overdue', 0) }}</strong></li>
                            <li><span>Older amount</span><strong>${{ number_format((float) data_get($o, 'invoices.overdue_amount', 0), 2) }}</strong></li>
                        </ul>
                    </article>
                </div>

                <section class="posai-suggest">
                    <div class="posai-suggest-head">
                        <span class="posai-suggest-title">Suggested actions</span>
                        <span class="posai-count">{{ count(data_get($o, 'actions', [])) }}</span>
                    </div>
                    @forelse (data_get($o, 'actions', []) as $a)
                        <div class="posai-suggest-row">
                            <span class="posai-badge">{{ $a['priority'] }}</span>
                            <div>
                                <div class="posai-suggest-name">{{ $a['title'] }}</div>
                                <div class="posai-suggest-detail">{{ $a['detail'] }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="posai-suggest-empty">No urgent actions right now — inventory and AR look clear.</div>
                    @endforelse
                </section>

                <div class="posai-quick-block">
                    <div class="posai-quick-label">Quick answers</div>
                    <div class="posai-pills">
                        @foreach (\App\Services\JapsAi\JapsAiChatService::QUICK_PROMPTS as $q)
                            <button type="button" class="posai-pill" wire:click="runQuick('{{ $q['intent'] }}')" wire:loading.attr="disabled">
                                {{ $q['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="posai-chat">
                <div class="posai-messages" id="posai-messages" wire:key="posai-messages-{{ count($messages) }}">
                    @foreach ($messages as $m)
                        <div class="posai-msg posai-msg-{{ $m['role'] }}">
                            @if ($m['role'] === 'assistant')
                                <span class="posai-avatar ai" aria-hidden="true">AI</span>
                            @else
                                <span class="posai-avatar user" aria-hidden="true">U</span>
                            @endif
                            <div class="posai-bubble-wrap">
                                @if (! empty($m['tool']) && $m['role'] === 'assistant' && ! in_array($m['tool'], ['help', 'error'], true))
                                    <div class="posai-tool">✓ lookup {{ str_replace('_', ' ', $m['tool']) }}</div>
                                @endif
                                <div class="posai-bubble">{!! $this->formatReply($m['text']) !!}</div>
                            </div>
                        </div>
                    @endforeach
                    <div wire:loading wire:target="send,runQuick" class="posai-msg posai-msg-assistant">
                        <span class="posai-avatar ai">AI</span>
                        <div class="posai-bubble muted">Checking live data…</div>
                    </div>
                    <div id="posai-msg-end" style="height:1px;"></div>
                </div>

                <div class="posai-chat-footer">
                    <div class="posai-pills">
                        @foreach (\App\Services\JapsAi\JapsAiChatService::QUICK_PROMPTS as $q)
                            <button
                                type="button"
                                class="posai-pill {{ $activeQuick === $q['intent'] ? 'is-active' : '' }}"
                                wire:click="runQuick('{{ $q['intent'] }}')"
                                wire:loading.attr="disabled"
                            >{{ $q['label'] }}</button>
                        @endforeach
                    </div>
                    <form wire:submit.prevent="send" class="posai-composer" autocomplete="off">
                        <input
                            type="text"
                            wire:model="message"
                            class="desk-input posai-composer-input"
                            placeholder="Ask POS AI about your business…"
                            maxlength="2000"
                        />
                        <button type="submit" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">Send</button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <style>
        .posai-wrap {
            padding: .15rem .75rem 1rem;
            box-sizing: border-box;
        }
        .posai-wrap.is-chat {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 5.75rem);
            min-height: 420px;
            padding-bottom: .5rem;
            overflow: hidden;
        }
        .posai-shell {
            max-width: 880px;
            margin: .55rem auto 0;
            background: #fff;
            border: 1px solid #b8c0cc;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(30, 41, 59, .08);
            overflow: hidden;
        }
        .posai-wrap.is-chat .posai-shell {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 880px;
            margin-top: .4rem;
        }
        .posai-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .65rem 1rem;
            padding: .85rem 1rem;
            background: linear-gradient(180deg, #f7f8fa 0%, #eef1f5 100%);
            border-bottom: 1px solid #c5ccd6;
            flex-shrink: 0;
        }
        .posai-brand {
            display: flex;
            align-items: center;
            gap: .7rem;
        }
        .posai-mark {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 8px;
            display: grid;
            place-items: center;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .04em;
            color: #fff;
            background: linear-gradient(145deg, #3a6db0, var(--chief-action, #2b5797) 55%, #1e3f70);
            box-shadow: 0 2px 6px rgba(43, 87, 151, .28);
            flex-shrink: 0;
        }
        .posai-brand-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -.02em;
            line-height: 1.15;
        }
        .posai-brand-sub {
            margin-top: .12rem;
            font-size: .76rem;
            color: #64748b;
        }
        .posai-tabs {
            display: inline-flex;
            gap: .2rem;
            padding: .18rem;
            background: #e2e6ec;
            border: 1px solid #c5ccd6;
            border-radius: 8px;
        }
        .posai-tab {
            border: 0;
            background: transparent;
            color: #475569;
            font-size: .8rem;
            font-weight: 600;
            padding: .42rem .9rem;
            border-radius: 6px;
            cursor: pointer;
        }
        .posai-tab:hover { background: rgba(255,255,255,.55); color: #1e293b; }
        .posai-tab.is-active {
            background: var(--chief-orange-tab, #f39c12);
            color: #111827;
            box-shadow: 0 1px 2px rgba(0,0,0,.08);
        }
        .posai-panel { padding: 1rem 1.05rem 1.1rem; background: #f4f6f8; }
        .posai-section-title {
            margin: 0 0 .85rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--chief-action, #2b5797);
        }
        .posai-settings-form {
            max-width: 24rem;
            display: flex;
            flex-direction: column;
            gap: .8rem;
            padding: 1rem;
            background: #fff;
            border: 1px solid #d0d7e0;
            border-radius: 8px;
        }
        .posai-field {
            display: flex;
            flex-direction: column;
            gap: .28rem;
            font-size: .88rem;
            color: #334155;
        }
        .posai-field small { color: #64748b; font-size: .75rem; }
        .posai-check {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .88rem;
            cursor: pointer;
        }
        .posai-check input { width: 1.05rem; height: 1.05rem; }
        .posai-actions-row { display: flex; gap: .45rem; margin-top: .15rem; }
        .posai-hint {
            margin: .9rem 0 0;
            font-size: .78rem;
            color: #64748b;
            line-height: 1.45;
            max-width: 36rem;
        }
        .posai-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }
        @media (max-width: 820px) {
            .posai-cards { grid-template-columns: 1fr; }
            .posai-wrap { padding-left: .4rem; padding-right: .4rem; }
            .posai-wrap.is-chat { height: calc(100vh - 5.5rem); }
        }
        .posai-card {
            position: relative;
            background: #fff;
            border: 1px solid #d0d7e0;
            border-radius: 10px;
            padding: .85rem .9rem .8rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .05);
            overflow: hidden;
        }
        .posai-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
        }
        .posai-card.accent-sales::before { background: var(--chief-action, #2b5797); }
        .posai-card.accent-stock::before { background: var(--chief-action-green, #2d5a3d); }
        .posai-card.accent-ar::before { background: var(--chief-orange, #e8a317); }
        .posai-card-top {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .4rem;
        }
        .posai-card-ico {
            width: 1.55rem;
            height: 1.55rem;
            border-radius: 6px;
            display: grid;
            place-items: center;
            font-size: .72rem;
            font-weight: 800;
            color: #fff;
            background: var(--chief-action, #2b5797);
        }
        .accent-stock .posai-card-ico { background: var(--chief-action-green, #2d5a3d); }
        .accent-ar .posai-card-ico { background: #d4920e; color: #fff; }
        .posai-card-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #64748b;
        }
        .posai-card-hero {
            font-size: 1.55rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -.03em;
            line-height: 1.15;
        }
        .posai-card-hero.sm {
            font-size: 1.45rem;
            display: flex;
            align-items: baseline;
            gap: .35rem;
            flex-wrap: wrap;
        }
        .posai-card-hero-soft {
            font-size: .85rem;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 0;
        }
        .posai-card-sub {
            margin-top: .25rem;
            font-size: .78rem;
            color: #64748b;
        }
        .posai-card-divider {
            height: 1px;
            background: #e8edf3;
            margin: .65rem 0 .55rem;
        }
        .posai-card-meta {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: .28rem;
        }
        .posai-card-meta li {
            display: flex;
            justify-content: space-between;
            gap: .5rem;
            font-size: .78rem;
            color: #64748b;
        }
        .posai-card-meta strong {
            color: #1e293b;
            font-weight: 600;
            text-align: right;
        }
        .posai-card-list {
            margin: .55rem 0 0;
            padding: 0;
            list-style: none;
            border-top: 1px dashed #e2e8f0;
            padding-top: .45rem;
        }
        .posai-card-list li {
            display: flex;
            justify-content: space-between;
            gap: .4rem;
            font-size: .72rem;
            color: #475569;
            padding: .12rem 0;
        }
        .posai-card-list .trunc {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .posai-card-list em {
            font-style: normal;
            color: #94a3b8;
            flex-shrink: 0;
        }
        .posai-suggest {
            margin-top: .9rem;
            background: #fff;
            border: 1px solid #d0d7e0;
            border-radius: 10px;
            padding: .75rem .9rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
        }
        .posai-suggest-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .45rem;
        }
        .posai-suggest-title {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #9a3412;
        }
        .posai-count {
            min-width: 1.35rem;
            height: 1.35rem;
            padding: 0 .35rem;
            display: inline-grid;
            place-items: center;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            background: var(--chief-orange-tab, #f39c12);
            color: #111;
            border: 1px solid #d4a017;
        }
        .posai-suggest-row {
            display: flex;
            gap: .6rem;
            padding: .55rem 0;
            border-top: 1px solid #eef1f5;
        }
        .posai-suggest-name {
            font-weight: 600;
            font-size: .88rem;
            color: #1e293b;
        }
        .posai-suggest-detail {
            font-size: .78rem;
            color: #64748b;
            margin-top: .15rem;
            line-height: 1.4;
        }
        .posai-suggest-empty {
            font-size: .84rem;
            color: #64748b;
            padding: .35rem 0 .15rem;
            border-top: 1px solid #eef1f5;
        }
        .posai-badge {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            padding: .18rem .4rem;
            border-radius: 4px;
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            height: fit-content;
            flex-shrink: 0;
        }
        .posai-quick-block {
            margin-top: .9rem;
            background: #fff;
            border: 1px solid #d0d7e0;
            border-radius: 10px;
            padding: .75rem .9rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
        }
        .posai-quick-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--chief-status, #4b5563);
            margin-bottom: .45rem;
        }
        .posai-pills { display: flex; flex-wrap: wrap; gap: .4rem; }
        .posai-pill {
            border: 1px solid #c5ccd6;
            background: #f8fafc;
            color: #334155;
            font-size: .78rem;
            padding: .38rem .7rem;
            border-radius: 999px;
            cursor: pointer;
            transition: background .12s ease, border-color .12s ease, color .12s ease;
        }
        .posai-pill:hover {
            border-color: var(--chief-action, #2b5797);
            color: var(--chief-action, #2b5797);
            background: #e8f0fa;
        }
        .posai-pill.is-active {
            border-color: var(--chief-action, #2b5797);
            background: var(--chief-action, #2b5797);
            color: #fff;
            font-weight: 600;
        }
        .posai-chat {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            background: linear-gradient(180deg, #f0f3f7 0%, #e8ecf1 100%);
        }
        .posai-messages {
            flex: 1 1 auto;
            min-height: 0;
            height: auto;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: .7rem;
            padding: .9rem 1rem;
            scroll-behavior: smooth;
        }
        .posai-msg {
            display: flex;
            gap: .5rem;
            align-items: flex-start;
            max-width: 90%;
        }
        .posai-msg-user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        .posai-avatar {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: .62rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .posai-avatar.ai {
            background: var(--chief-action, #2b5797);
            color: #fff;
            box-shadow: 0 1px 3px rgba(43, 87, 151, .3);
        }
        .posai-avatar.user {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        .posai-bubble-wrap { min-width: 0; }
        .posai-bubble {
            background: #fff;
            border: 1px solid #d0d7e0;
            border-radius: 10px;
            padding: .6rem .75rem;
            font-size: .86rem;
            line-height: 1.45;
            color: #1e293b;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
        }
        .posai-msg-user .posai-bubble {
            background: var(--chief-action, #2b5797);
            color: #fff;
            border-color: #1e3f70;
            box-shadow: 0 2px 6px rgba(43, 87, 151, .2);
        }
        .posai-bubble.muted {
            color: #64748b;
            font-style: italic;
            background: #f8fafc;
        }
        .posai-bubble .posai-h3 {
            font-weight: 700;
            margin: .35rem 0 .08rem;
            font-size: .86rem;
            color: var(--chief-action, #2b5797);
        }
        .posai-msg-user .posai-bubble .posai-h3 { color: #dbeafe; }
        .posai-tool {
            display: inline-flex;
            align-items: center;
            font-size: .7rem;
            color: var(--chief-action-green, #2d5a3d);
            background: #eef6f0;
            border: 1px solid #b7d0bf;
            border-radius: 999px;
            padding: .12rem .5rem;
            margin-bottom: .28rem;
        }
        .posai-chat-footer {
            flex-shrink: 0;
            border-top: 1px solid #c5ccd6;
            background: #fff;
            padding: .7rem .9rem .8rem;
            display: flex;
            flex-direction: column;
            gap: .55rem;
        }
        .posai-composer {
            display: flex;
            gap: .45rem;
            align-items: center;
        }
        .posai-composer-input {
            flex: 1;
            min-width: 0;
            border-radius: 8px !important;
            min-height: 2.4rem;
        }
    </style>
</div>
