@extends('sale.layout')
@section('title', ($activeMeta['label'] ?? 'Chat'))
@section('header', 'Chat')
@section('content')
@php
    $isChannel = ($activeMeta['type'] ?? '') === 'channel';
    $title = $activeMeta['label'] ?? 'Team chat';
    $subtitle = $isChannel
        ? (($activeMeta['member_count'] ?? 0) ? $activeMeta['member_count'].' members' : 'Channel')
        : 'Direct message';
    $titleInitials = strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $title) ?: 'C', 0, 2));
    $tones = ['#0f766e', '#0369a1', '#6d28d9', '#c2410c', '#be185d', '#0f766e'];
@endphp
<style>
    .sc { --sc-teal:#0f766e; --sc-ink:#0b1220; --sc-mute:#64748b; --sc-line:#e8eef4; --sc-nav:72px; display:flex; flex-direction:column; background:#fff; min-height:0; width:100%; }
    .sc-list, .sc-pane { display:flex; flex-direction:column; min-height:0; height:100%; width:100%; }
    .sc-list { width:100%; background:#fff; position:relative; }
    .sc-pane { flex:1; min-width:0; background:#eef4f6; }
    .sc-list-head, .sc-pane-head {
        display:flex; align-items:center; gap:10px;
        padding:10px 14px; padding-top:calc(10px + env(safe-area-inset-top,0));
        background:#fff; border-bottom:1px solid var(--sc-line); min-height:52px; flex-shrink:0;
    }
    .sc-list-head h1, .sc-pane-head h1 { margin:0; font-size:20px; font-weight:800; letter-spacing:-.03em; }
    .sc-list-head p { display:none; }
    .sc-search { margin:0 12px 8px; position:relative; flex-shrink:0; }
    .sc-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; }
    .sc-search input { width:100%; border:0; background:#f1f5f9; border-radius:12px; padding:10px 12px 10px 38px; font-size:15px; outline:none; }
    .sc-scroll { flex:1 1 auto; overflow-y:auto; -webkit-overflow-scrolling:touch; min-height:0; padding-bottom:88px; }
    .sc-sec { padding:12px 16px 4px; font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; }
    .sc-item { display:flex; align-items:center; gap:12px; padding:11px 14px; text-decoration:none; color:inherit; }
    .sc-item:active { background:#f8fafc; }
    .sc-item.is-on { background:#f0fdfa; }
    .sc-av { width:48px; height:48px; border-radius:16px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:14px; flex-shrink:0; }
    .sc-av--sm { width:36px; height:36px; border-radius:12px; font-size:12px; }
    .sc-av--ch { background:linear-gradient(160deg,#0f766e,#14b8a6); }
    .sc-item__body { min-width:0; flex:1; }
    .sc-item__name { font-weight:800; font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sc-item__sub { font-size:12px; color:var(--sc-mute); margin-top:2px; }
    .sc-pill { margin-left:auto; background:var(--sc-teal); color:#fff; min-width:20px; height:20px; border-radius:999px; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; padding:0 6px; }
    .sc-new { display:none; }
    .sc-fab {
        position:absolute; right:16px; bottom:16px; z-index:5;
        width:56px; height:56px; border:0; border-radius:18px;
        background:var(--sc-teal); color:#fff; box-shadow:0 8px 20px rgba(15,118,110,.35);
        display:flex; align-items:center; justify-content:center; cursor:pointer;
    }
    .sc-sheet { position:absolute; inset:0; z-index:20; background:rgba(15,23,42,.4); display:none; align-items:flex-end; }
    .sc-sheet.is-open { display:flex; }
    .sc-sheet__panel { width:100%; background:#fff; border-radius:20px 20px 0 0; padding:16px 16px calc(20px + env(safe-area-inset-bottom,0)); }
    .sc-sheet__panel h2 { margin:0 0 12px; font-size:16px; font-weight:800; }
    .sc-back { width:40px; height:40px; border:0; background:#f1f5f9; border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--sc-teal); text-decoration:none; flex-shrink:0; }
    .sc-pane-head__text { min-width:0; flex:1; }
    .sc-pane-head__text h1 { font-size:16px; }
    .sc-pane-head__text p { margin:1px 0 0; font-size:12px; color:var(--sc-mute); font-weight:600; }
    .sc-thread { flex:1 1 0; overflow-y:auto; min-height:0; padding:12px 12px 16px; background:#eef4f6; -webkit-overflow-scrolling:touch; }
    .sc-day { display:flex; align-items:center; gap:10px; margin:14px 0 12px; color:#94a3b8; font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
    .sc-day:before, .sc-day:after { content:""; flex:1; height:1px; background:#e2e8f0; }
    .sc-row { display:flex; gap:8px; margin-bottom:8px; align-items:flex-end; max-width:min(86vw, 420px); }
    .sc-row.is-me { margin-left:auto; flex-direction:row-reverse; }
    .sc-bubble { border-radius:18px; padding:9px 12px; font-size:15px; line-height:1.45; word-break:break-word; box-shadow:0 1px 1px rgba(15,23,42,.04); }
    .sc-row:not(.is-me) .sc-bubble { background:#fff; color:var(--sc-ink); border-bottom-left-radius:6px; }
    .sc-row.is-me .sc-bubble { background:var(--sc-teal); color:#fff; border-bottom-right-radius:6px; }
    .sc-who { font-size:11px; font-weight:800; margin-bottom:3px; color:#0f766e; }
    .sc-row.is-me .sc-who { display:none; }
    .sc-time { font-size:10px; font-weight:700; margin-top:4px; opacity:.65; text-align:right; }
    .sc-empty { text-align:center; padding:48px 20px; color:var(--sc-mute); }
    .sc-empty__ico { width:72px; height:72px; margin:0 auto 12px; border-radius:24px; background:#fff; color:var(--sc-teal); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 24px rgba(15,118,110,.12); }
    .sc-empty h2 { margin:0 0 6px; font-size:18px; color:var(--sc-ink); }
    .sc-composer { display:flex; gap:8px; align-items:center; padding:8px 10px; background:#fff; border-top:1px solid var(--sc-line); flex-shrink:0; }
    .sc-composer input { flex:1; min-width:0; border:1px solid #e2e8f0; background:#f8fafc; border-radius:22px; padding:10px 14px; font-size:16px; outline:none; }
    .sc-composer input:focus { border-color:#99f6e4; background:#fff; box-shadow:0 0 0 3px rgba(15,118,110,.12); }
    .sc-send { width:44px; height:44px; border:0; border-radius:14px; background:var(--sc-teal); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; }
    .sc-send:disabled { opacity:.4; }
    .sc-older { text-align:center; margin-bottom:12px; }
    .sc-older button { border:0; background:#fff; color:var(--sc-teal); font-weight:800; font-size:12px; padding:8px 14px; border-radius:999px; cursor:pointer; }
    .sc-mention { font-weight:800; color:#0f766e; }
    .sc-row.is-me .sc-mention { color:#ccfbf1; }
    @media (max-width:1023px) {
        .sc {
            position:fixed; left:0; right:0; top:0; width:100%;
            height:calc(100dvh - var(--sc-nav) - env(safe-area-inset-bottom,0));
            z-index:40;
        }
        body.sale-chat-inbox .sc-pane { display:none !important; }
        body.sale-chat-thread .sc-list { display:none !important; }
        body.sale-chat-thread .sc-pane { display:flex !important; flex-direction:column; height:100%; flex:1; }
    }
    @media (min-width:1024px) {
        .sc { position:relative; height:calc(100dvh - 64px); flex-direction:row; }
        .sc-list { width:340px; max-width:36%; flex:0 0 340px; border-right:1px solid var(--sc-line); }
        .sc-list-head { padding-top:14px; }
        .sc-list-head p { display:block; margin:2px 0 0; font-size:12px; color:var(--sc-mute); font-weight:600; }
        .sc-back { display:none; }
        .sc-fab, .sc-sheet { display:none !important; }
        .sc-new { display:block; padding:12px 14px 16px; border-top:1px solid var(--sc-line); flex-shrink:0; }
        .sc-scroll { padding-bottom:8px; }
        .sc-list-head, .sc-pane-head { padding-top:12px; }
    }
</style>

<div class="sc" id="saleChat">
    <aside class="sc-list" aria-label="Conversations">
        <div class="sc-list-head">
            <div>
                <h1>Chats</h1>
                <p>Team channels & messages</p>
            </div>
        </div>
        <div class="sc-search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
            <input type="search" id="scFilter" placeholder="Search people or channels" autocomplete="off">
        </div>
        <div class="sc-scroll" id="scConvos">
            <div class="sc-sec">Channels</div>
            @forelse($sidebarChannels as $ch)
                @php $on = (int) $activeId === (int) $ch['id']; @endphp
                <a href="{{ route('sale.chat', $ch['id']) }}" class="sc-item {{ $on ? 'is-on' : '' }}" data-filter="{{ strtolower('#'.$ch['label']) }}">
                    <span class="sc-av sc-av--ch">#</span>
                    <span class="sc-item__body">
                        <div class="sc-item__name">{{ $ch['label'] }}</div>
                        <div class="sc-item__sub">{{ (int) ($ch['member_count'] ?? 0) }} members</div>
                    </span>
                    @if((int) $ch['unread'] > 0 && ! $on)
                        <span class="sc-pill">{{ $ch['unread'] > 9 ? '9+' : $ch['unread'] }}</span>
                    @endif
                </a>
            @empty
                <p class="px-4 py-2 text-sm text-slate-400">No channels yet</p>
            @endforelse

            <div class="sc-sec">Direct messages</div>
            @forelse($sidebarDms as $dm)
                @php
                    $on = (int) $activeId === (int) $dm['id'];
                    $ini = strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $dm['label']) ?: 'U', 0, 2));
                    $tone = $tones[((int) $dm['id']) % count($tones)];
                @endphp
                <a href="{{ route('sale.chat', $dm['id']) }}" class="sc-item {{ $on ? 'is-on' : '' }}" data-filter="{{ strtolower($dm['label']) }}">
                    <span class="sc-av" style="background:{{ $tone }}">{{ $ini }}</span>
                    <span class="sc-item__body">
                        <div class="sc-item__name">{{ $dm['label'] }}</div>
                        <div class="sc-item__sub">Private chat</div>
                    </span>
                    @if((int) $dm['unread'] > 0 && ! $on)
                        <span class="sc-pill">{{ $dm['unread'] > 9 ? '9+' : $dm['unread'] }}</span>
                    @endif
                </a>
            @empty
                <p class="px-4 py-2 text-sm text-slate-400">No direct messages yet</p>
            @endforelse
        </div>
        @if($canSend && count($companyUsers))
            <button type="button" class="sc-fab" id="scFab" aria-label="New message">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="sc-sheet" id="scSheet">
                <div class="sc-sheet__panel">
                    <h2>New message</h2>
                    <form method="POST" action="{{ route('sale.chat.dm') }}">
                        @csrf
                        <select name="user_id" class="sale-input !rounded-xl" required>
                            <option value="">Choose a teammate…</option>
                            @foreach($companyUsers as $cu)
                                <option value="{{ $cu['id'] }}">{{ $cu['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="sale-btn !mt-3 !rounded-xl">Start chat</button>
                        <button type="button" class="sale-btn-ghost !mt-2 !rounded-xl" id="scSheetClose">Cancel</button>
                    </form>
                </div>
            </div>
            <form method="POST" action="{{ route('sale.chat.dm') }}" class="sc-new">
                @csrf
                <select name="user_id" class="sale-input !py-2.5 !rounded-xl" required>
                    <option value="">Message a teammate…</option>
                    @foreach($companyUsers as $cu)
                        <option value="{{ $cu['id'] }}">{{ $cu['name'] }}</option>
                    @endforeach
                </select>
                <button type="submit" class="sale-btn !mt-2 !rounded-xl">Start chat</button>
            </form>
        @endif
    </aside>

    <section class="sc-pane">
        <header class="sc-pane-head">
            <a href="{{ route('sale.chat') }}" class="sc-back" aria-label="Back to chats">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <span class="sc-av sc-av--sm {{ $isChannel ? 'sc-av--ch' : '' }}" @if(! $isChannel) style="background:{{ $tones[($activeId ?: 0) % count($tones)] }}" @endif>{{ $isChannel ? '#' : $titleInitials }}</span>
            <div class="sc-pane-head__text">
                <h1 class="truncate">{{ $title }}</h1>
                <p>{{ $subtitle }}</p>
            </div>
        </header>

        <div class="sc-thread" id="saleChatThread">
            @if($hasOlder && $activeId)
                <div class="sc-older"><button type="button" id="saleChatOlder" data-before="{{ $olderCursor }}">Load earlier messages</button></div>
            @endif
            <div id="saleChatMsgs">
                @php $lastDay = null; @endphp
                @forelse($thread as $m)
                    @php
                        $dayCarbon = !empty($m['created_at']) ? \Carbon\Carbon::parse($m['created_at'])->timezone(config('app.timezone')) : null;
                        $day = $dayCarbon ? $dayCarbon->toDateString() : '';
                        if ($dayCarbon && $dayCarbon->isToday()) {
                            $dayLabel = 'Today';
                        } elseif ($dayCarbon && $dayCarbon->isYesterday()) {
                            $dayLabel = 'Yesterday';
                        } else {
                            $dayLabel = $dayCarbon ? $dayCarbon->format('M j, Y') : '';
                        }
                        $mine = (int) $m['sender_id'] === (int) $meId;
                        $tone = $tones[((int) $m['sender_id']) % count($tones)];
                    @endphp
                    @if($day && $day !== $lastDay)
                        <div class="sc-day">{{ $dayLabel }}</div>
                        @php $lastDay = $day; @endphp
                    @endif
                    <article class="sc-row {{ $mine ? 'is-me' : '' }}" data-id="{{ $m['id'] }}" data-sender="{{ $m['sender_id'] }}">
                        @unless($mine)
                            <span class="sc-av sc-av--sm" style="background:{{ $tone }}">{{ $m['sender_initials'] }}</span>
                        @endunless
                        <div class="sc-bubble">
                            <div class="sc-who">{{ $m['sender_name'] }}</div>
                            <div class="sc-text">{!! $m['body_html'] !!}</div>
                            <div class="sc-time">{{ $m['created_label'] }}</div>
                        </div>
                    </article>
                @empty
                    <div class="sc-empty">
                        <div class="sc-empty__ico">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h2>Start the conversation</h2>
                        <p>Say hello — the team will see it here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <form class="sc-composer" method="POST" action="{{ $activeId ? route('sale.chat.send', $activeId) : '#' }}" id="saleChatForm">
            @csrf
            <input type="text" name="body" id="saleChatInput" placeholder="{{ $isChannel ? 'Message #'.$title : 'Message '.$title }}" autocomplete="off" maxlength="8000" @disabled(! $canSend || ! $activeId) required>
            <button type="submit" class="sc-send" @disabled(! $canSend || ! $activeId) aria-label="Send">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            </button>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const meId = @json((int) $meId);
    const tones = @json($tones);
    const thread = document.getElementById('saleChatThread');
    const msgs = document.getElementById('saleChatMsgs');
    const form = document.getElementById('saleChatForm');
    const input = document.getElementById('saleChatInput');
    const olderBtn = document.getElementById('saleChatOlder');
    const filter = document.getElementById('scFilter');
    const channelId = @json($activeId);
    const pollUrl = @json($activeId ? route('sale.chat.poll', $activeId) : '');
    const olderUrl = @json($activeId ? route('sale.chat.older', $activeId) : '');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (thread) thread.scrollTop = thread.scrollHeight;

    if (filter) {
        filter.addEventListener('input', () => {
            const q = filter.value.trim().toLowerCase();
            document.querySelectorAll('#scConvos .sc-item').forEach(el => {
                el.style.display = !q || (el.dataset.filter || '').includes(q) ? '' : 'none';
            });
        });
    }

    const fab = document.getElementById('scFab');
    const sheet = document.getElementById('scSheet');
    const sheetClose = document.getElementById('scSheetClose');
    function openSheet() { if (sheet) sheet.classList.add('is-open'); }
    function closeSheet() { if (sheet) sheet.classList.remove('is-open'); }
    if (fab) fab.addEventListener('click', openSheet);
    if (sheetClose) sheetClose.addEventListener('click', closeSheet);
    if (sheet) sheet.addEventListener('click', (e) => { if (e.target === sheet) closeSheet(); });

    const shell = document.getElementById('saleChat');
    function fitChat() {
        if (!shell || window.innerWidth >= 1024) {
            if (shell) { shell.style.height = ''; shell.style.top = ''; }
            return;
        }
        const nav = document.querySelector('.sale-bottom-nav');
        const navH = nav ? nav.getBoundingClientRect().height : 0;
        const vv = window.visualViewport;
        const top = vv ? vv.offsetTop : 0;
        const vh = vv ? vv.height : window.innerHeight;
        shell.style.top = top + 'px';
        shell.style.height = Math.max(240, vh - navH) + 'px';
        if (thread) thread.scrollTop = thread.scrollHeight;
    }
    fitChat();
    window.addEventListener('resize', fitChat);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', fitChat);
        window.visualViewport.addEventListener('scroll', fitChat);
    }

    function lastId() {
        const nodes = msgs ? [...msgs.querySelectorAll('[data-id]')] : [];
        return nodes.length ? Number(nodes[nodes.length - 1].dataset.id) : @json((int) $latestId);
    }

    function bubbleHtml(m) {
        const mine = Number(m.sender_id) === meId;
        const tone = tones[Number(m.sender_id) % tones.length];
        const av = mine ? '' : `<span class="sc-av sc-av--sm" style="background:${tone}">${m.sender_initials || 'U'}</span>`;
        return `<article class="sc-row ${mine ? 'is-me' : ''}" data-id="${m.id}" data-sender="${m.sender_id}">${av}<div class="sc-bubble"><div class="sc-who">${m.sender_name || ''}</div><div class="sc-text">${m.body_html || ''}</div><div class="sc-time">${m.created_label || ''}</div></div></article>`;
    }

    function appendMsg(m) {
        if (!msgs || msgs.querySelector('[data-id="'+m.id+'"]')) return;
        const empty = msgs.querySelector('.sc-empty');
        if (empty) empty.remove();
        msgs.insertAdjacentHTML('beforeend', bubbleHtml(m));
        thread.scrollTop = thread.scrollHeight;
    }

    async function poll() {
        if (!pollUrl) return;
        try {
            const res = await fetch(pollUrl + '?after=' + lastId(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            (data.messages || []).forEach(appendMsg);
        } catch (e) {}
    }
    if (pollUrl) setInterval(poll, 2500);

    if (olderBtn && olderUrl) {
        olderBtn.addEventListener('click', async () => {
            const before = olderBtn.dataset.before;
            if (!before) return;
            const res = await fetch(olderUrl + '?before=' + encodeURIComponent(before), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            const html = (data.messages || []).filter(m => !msgs.querySelector('[data-id="'+m.id+'"]')).map(bubbleHtml).join('');
            msgs.insertAdjacentHTML('afterbegin', html);
            if (data.next_cursor) olderBtn.dataset.before = data.next_cursor;
            else olderBtn.parentElement.remove();
        });
    }

    if (form && channelId) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = (input.value || '').trim();
            if (!body) return;
            const fd = new FormData(form);
            input.value = '';
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                body: fd
            });
            if (!res.ok) {
                const err = await res.json().catch(() => null);
                alert((err && (err.message || err.msg)) || 'Could not send');
                return;
            }
            const data = await res.json().catch(() => ({}));
            if (data.message) appendMsg(data.message);
            else await poll();
        });
    }
})();
</script>
@endpush
