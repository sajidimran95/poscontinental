<div class="posai-w" wire:key="pos-ai-widget">
    {{-- Floating launcher (hidden while panel is open) --}}
    @if (! $open)
        <button
            type="button"
            class="posai-w-fab"
            wire:click="toggle"
            title="Open POS AI"
            aria-label="Open POS AI chat"
            aria-expanded="false"
        >
            <span class="posai-w-fab-label">AI</span>
        </button>
    @endif

    {{-- Backdrop (mobile / click outside) --}}
    @if ($open)
        <div class="posai-w-backdrop" wire:click="close" aria-hidden="true"></div>
    @endif

    {{-- Full-height side panel --}}
    <aside
        class="posai-w-panel {{ $open ? 'is-open' : '' }}"
        role="dialog"
        aria-modal="true"
        aria-label="POS AI chat"
        @if (! $open) aria-hidden="true" @endif
    >
        <header class="posai-w-head">
            <div class="posai-w-brand">
                <span class="posai-w-mark">AI</span>
                <div>
                    <div class="posai-w-title">POS AI</div>
                    <div class="posai-w-sub">Live company data assistant</div>
                </div>
            </div>
            <div class="posai-w-head-actions">
                <button type="button" class="posai-w-link" wire:click="clearChat" title="Start a new chat">Clear</button>
                @if (Route::has('admin.japsai'))
                    <a href="{{ route('admin.japsai') }}" wire:navigate class="posai-w-link" title="Open full POS AI">Full</a>
                @endif
                <button type="button" class="posai-w-icon-btn" wire:click="close" title="Close" aria-label="Close">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>
        </header>

        <div class="posai-w-messages" id="posai-widget-messages" wire:key="widget-msgs-{{ count($messages) }}">
            @foreach ($messages as $m)
                <div class="posai-w-msg posai-w-msg-{{ $m['role'] }}">
                    @if ($m['role'] === 'assistant')
                        <span class="posai-w-avatar ai">AI</span>
                    @else
                        <span class="posai-w-avatar user">U</span>
                    @endif
                    <div class="posai-w-bubble-wrap">
                        @if (! empty($m['tool']) && $m['role'] === 'assistant' && ! in_array($m['tool'], ['help', 'error', 'scope', 'openai'], true))
                            <div class="posai-w-tool">✓ live data · {{ str_replace('_', ' ', $m['tool']) }}</div>
                        @endif
                        @if (($m['tool'] ?? null) === 'openai' && $m['role'] === 'assistant')
                            <div class="posai-w-tool">OpenAI · this company only</div>
                        @endif
                        <div class="posai-w-bubble">{!! $this->formatReply($m['text']) !!}</div>
                    </div>
                </div>
            @endforeach
            <div wire:loading wire:target="send,runQuick" class="posai-w-msg posai-w-msg-assistant">
                <span class="posai-w-avatar ai">AI</span>
                <div class="posai-w-bubble muted">Checking live data…</div>
            </div>
        </div>

        <div class="posai-w-footer">
                <div class="posai-w-suggest">
                    <div class="posai-w-suggest-label">Suggested questions · free live data (no OpenAI)</div>
                    <div class="posai-w-pills">
                        @foreach (\App\Services\JapsAi\JapsAiChatService::QUICK_PROMPTS as $q)
                            <button
                                type="button"
                                class="posai-w-pill {{ $activeQuick === $q['intent'] ? 'is-active' : '' }}"
                                wire:click="runQuick('{{ $q['intent'] }}')"
                                wire:loading.attr="disabled"
                            >{{ $q['label'] }}</button>
                        @endforeach
                    </div>
                </div>
                <form wire:submit.prevent="send" class="posai-w-composer" autocomplete="off">
                    <input
                        type="text"
                        wire:model="message"
                        class="posai-w-input"
                        placeholder="POS only… or use free Suggested questions"
                        maxlength="2000"
                    />
                <button type="submit" class="posai-w-send" wire:loading.attr="disabled" title="Send">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                        <path d="M3.4 20.6l17.5-7.6c.8-.3.8-1.5 0-1.8L3.4 3.4c-.7-.3-1.4.3-1.2 1l1.7 6.3c.1.4.4.7.8.8l8.2.9-8.2.9c-.4.1-.7.4-.8.8L2.2 19.6c-.2.7.5 1.3 1.2 1z"/>
                    </svg>
                </button>
            </form>
        </div>
    </aside>

    <style>
        .posai-w {
            --posai-blue: var(--chief-action, #2b5797);
            --posai-orange: var(--chief-orange-tab, #f39c12);
            --posai-green: var(--chief-action-green, #2d5a3d);
            --posai-panel-w: min(520px, 100vw);
            font-family: inherit;
            z-index: 80;
        }
        .posai-w-fab {
            position: fixed;
            right: 1.1rem;
            bottom: 5.75rem;
            z-index: 82;
            width: 3.25rem;
            height: 3.25rem;
            border: 0;
            border-radius: 999px;
            background: linear-gradient(145deg, #3a6db0, var(--posai-blue) 55%, #1e3f70);
            color: #fff;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(43, 87, 151, .38);
            display: grid;
            place-items: center;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .posai-w-fab:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(43, 87, 151, .45);
        }
        .posai-w-fab-label {
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .06em;
        }
        .posai-w-backdrop {
            position: fixed;
            inset: 0;
            z-index: 80;
            background: rgba(15, 23, 42, .28);
        }
        .posai-w-panel {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 81;
            width: var(--posai-panel-w);
            max-width: 100vw;
            background: #fff;
            border-left: 1px solid #b8c0cc;
            box-shadow: -8px 0 28px rgba(15, 23, 42, .16);
            display: flex;
            flex-direction: column;
            transform: translateX(105%);
            transition: transform .22s ease;
            pointer-events: none;
        }
        .posai-w-panel.is-open {
            transform: translateX(0);
            pointer-events: auto;
        }
        .posai-w-head {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .85rem .9rem;
            background: linear-gradient(180deg, #f7f8fa 0%, #e8ecf1 100%);
            border-bottom: 1px solid #c5ccd6;
        }
        .posai-w-brand {
            display: flex;
            align-items: center;
            gap: .55rem;
            min-width: 0;
        }
        .posai-w-mark {
            width: 2.15rem;
            height: 2.15rem;
            border-radius: 8px;
            display: grid;
            place-items: center;
            font-size: .68rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(145deg, #3a6db0, var(--posai-blue));
            flex-shrink: 0;
        }
        .posai-w-title {
            font-size: .95rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .posai-w-sub {
            font-size: .7rem;
            color: #64748b;
            margin-top: .08rem;
        }
        .posai-w-head-actions {
            display: flex;
            align-items: center;
            gap: .35rem;
            flex-shrink: 0;
        }
        .posai-w-link {
            font-size: .72rem;
            font-weight: 600;
            color: var(--posai-blue);
            text-decoration: none;
            padding: .28rem .45rem;
            border: 1px solid #c5ccd6;
            border-radius: 6px;
            background: #fff;
        }
        .posai-w-link:hover { background: #eef3f9; }
        .posai-w-icon-btn {
            border: 1px solid #c5ccd6;
            background: #fff;
            color: #475569;
            width: 2rem;
            height: 2rem;
            border-radius: 6px;
            display: grid;
            place-items: center;
            cursor: pointer;
        }
        .posai-w-icon-btn:hover { background: #f1f5f9; color: #0f172a; }
        .posai-w-messages {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: .85rem .8rem;
            display: flex;
            flex-direction: column;
            gap: .65rem;
            background: linear-gradient(180deg, #f0f3f7 0%, #e9eef4 100%);
            scroll-behavior: smooth;
        }
        .posai-w-msg {
            display: flex;
            gap: .4rem;
            align-items: flex-start;
            max-width: 96%;
        }
        .posai-w-msg-user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        .posai-w-avatar {
            width: 1.55rem;
            height: 1.55rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: .55rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .posai-w-avatar.ai {
            background: var(--posai-blue);
            color: #fff;
        }
        .posai-w-avatar.user {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        .posai-w-bubble-wrap { min-width: 0; }
        .posai-w-bubble {
            background: #fff;
            border: 1px solid #d0d7e0;
            border-radius: 10px;
            padding: .5rem .65rem;
            font-size: .82rem;
            line-height: 1.45;
            color: #1e293b;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .posai-w-msg-user .posai-w-bubble {
            background: var(--posai-blue);
            color: #fff;
            border-color: #1e3f70;
        }
        .posai-w-bubble.muted {
            color: #64748b;
            font-style: italic;
            background: #f8fafc;
        }
        .posai-w-bubble .posai-w-h3 {
            font-weight: 700;
            margin: .3rem 0 .06rem;
            font-size: .82rem;
            color: var(--posai-blue);
        }
        .posai-w-msg-user .posai-w-bubble .posai-w-h3 { color: #dbeafe; }
        .posai-w-tool {
            display: inline-flex;
            font-size: .65rem;
            color: var(--posai-green);
            background: #eef6f0;
            border: 1px solid #b7d0bf;
            border-radius: 999px;
            padding: .08rem .4rem;
            margin-bottom: .22rem;
        }
        .posai-w-footer {
            flex-shrink: 0;
            border-top: 1px solid #c5ccd6;
            background: #f4f6f9;
            padding: .7rem .8rem .75rem;
            display: flex;
            flex-direction: column;
            gap: .55rem;
        }
        .posai-w-suggest {
            background: #fff;
            border: 1px solid #c5ccd6;
            border-radius: 10px;
            padding: .55rem .6rem .6rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
        }
        .posai-w-suggest-label {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--posai-blue);
            margin-bottom: .4rem;
        }
        .posai-w-pills {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            max-height: 8.5rem;
            overflow-y: auto;
        }
        .posai-w-pill {
            border: 1.5px solid var(--posai-blue);
            background: #e8f0fa;
            color: #1e3f70;
            font-size: .78rem;
            font-weight: 600;
            padding: .4rem .7rem;
            border-radius: 999px;
            cursor: pointer;
            line-height: 1.25;
            white-space: nowrap;
        }
        .posai-w-pill:hover {
            border-color: #1e3f70;
            color: #fff;
            background: var(--posai-blue);
        }
        .posai-w-pill.is-active {
            border-color: var(--posai-orange);
            background: var(--posai-orange);
            color: #111;
            font-weight: 700;
        }
        .posai-w-composer {
            display: flex;
            gap: .4rem;
            align-items: center;
        }
        .posai-w-input {
            flex: 1;
            min-width: 0;
            border: 1px solid #c5ccd6;
            border-radius: 999px;
            padding: .6rem .95rem;
            font-size: .88rem;
            outline: none;
            background: #fff;
        }
        .posai-w-input:focus {
            border-color: var(--posai-blue);
            background: #fff;
            box-shadow: 0 0 0 2px rgba(43, 87, 151, .15);
        }
        .posai-w-send {
            width: 2.4rem;
            height: 2.4rem;
            border: 0;
            border-radius: 999px;
            background: var(--posai-blue);
            color: #fff;
            display: grid;
            place-items: center;
            cursor: pointer;
            flex-shrink: 0;
        }
        .posai-w-send:hover { background: #1e3f70; }
        .posai-w-send:disabled { opacity: .6; cursor: wait; }
        @media (max-width: 480px) {
            .posai-w-fab { right: .75rem; bottom: 5.25rem; }
            .posai-w-panel { width: 100vw; }
        }
    </style>
</div>
