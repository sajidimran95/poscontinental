@php
    $teamChatUnreadUrl = $teamChatUnreadUrl ?? null;
    $teamChatSoundKey = 'pos.notify.chat.'.substr(md5($teamChatUnreadUrl.'|'.(auth()->id() ?: auth('sale')->id())), 0, 12);
@endphp
@if ($teamChatUnreadUrl)
@include('layouts.partials.notify-sounds')
<a id="team-chat-toast" class="pos-red-toast pos-red-toast-chat" hidden role="status" href="{{ \Illuminate\Support\Facades\Route::has('team-chat.index') ? route('team-chat.index') : ( \Illuminate\Support\Facades\Route::has('sale.chat') ? route('sale.chat') : '#' ) }}">
    <span class="pos-red-toast-kicker">Team chat</span>
    <span class="pos-red-toast-title"></span>
    <span class="pos-red-toast-body"></span>
    <button type="button" class="pos-red-toast-x" aria-label="Dismiss">&times;</button>
</a>
<style>
    .tc-nav-link { position: relative; display: inline-flex; align-items: center; gap: .35rem; }
    .tc-nav-badge {
        min-width: 1.1rem; height: 1.1rem; padding: 0 .28rem;
        border-radius: 999px; background: #dc2626; color: #fff;
        font-size: 10px; font-weight: 800; line-height: 1.1rem; text-align: center;
    }
    .pos-red-toast-chat { top: 3.1rem; }
    #web-order-toast.pos-red-toast { top: 3.1rem; }
    #team-chat-toast.pos-red-toast { top: 3.1rem; }
    /* Stack chat below order toast when both visible */
    #web-order-toast.pos-red-toast:not([hidden]) ~ #team-chat-toast.pos-red-toast:not([hidden]),
    body:has(#web-order-toast.pos-red-toast:not([hidden])) #team-chat-toast.pos-red-toast {
        top: 8.4rem;
    }
    .sale-tab { position: relative; }
    .sale-side-link { position: relative; }
    .sale-chat-badge {
        position: absolute; top: 4px; right: calc(50% - 18px);
        min-width: 16px; height: 16px; padding: 0 4px;
        border-radius: 999px; background: #e11d48; color: #fff;
        font-size: 9px; font-weight: 800; line-height: 16px; text-align: center;
    }
    .sale-side-link .sale-chat-badge { top: 8px; right: 10px; }
</style>
<script>
(function () {
    const url = @json($teamChatUnreadUrl);
    const soundKey = @json($teamChatSoundKey);
    window.posChatSoundKey = soundKey;
    const toast = document.getElementById('team-chat-toast');

    if (toast) {
        const x = toast.querySelector('.pos-red-toast-x');
        if (x) {
            x.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toast.hidden = true;
            });
        }
    }

    function showChatToast(preview) {
        if (! toast) return;
        const from = (preview && preview.from) || 'New message';
        const body = (preview && preview.body) || '';
        const ch = (preview && preview.channel) ? preview.channel : 'Team chat';
        toast.querySelector('.pos-red-toast-kicker').textContent = 'Team chat';
        toast.querySelector('.pos-red-toast-title').textContent = from + (ch ? ' · ' + ch : '');
        toast.querySelector('.pos-red-toast-body').textContent = body || 'New message';
        toast.hidden = false;
        clearTimeout(toast._hide);
        toast._hide = setTimeout(function () { toast.hidden = true; }, 12000);
    }

    function chatVoice(preview) {
        const from = preview && preview.from ? String(preview.from).trim() : '';
        if (from) {
            return 'You have a new team chat message from ' + from + '.';
        }
        return 'You have a new team chat message.';
    }

    function notifyIncoming(id, preview) {
        if (! window.posNotifyClaim(soundKey, id)) return;
        // Red toast + short spoken voice.
        const p = preview || { from: 'Someone', body: 'New message', channel: 'chat' };
        showChatToast(p);
        if (typeof window.playPosNotify === 'function') {
            window.playPosNotify('chat', chatVoice(p));
        }
    }

    async function tick() {
        try {
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            const n = Number(data.unread || 0);
            document.querySelectorAll('[data-chat-unread]').forEach(function (el) {
                el.hidden = n < 1;
                el.textContent = n > 9 ? '9+' : String(n);
            });
            if (Number(data.latest_id || 0) > 0) {
                notifyIncoming(data.latest_id, data.preview);
            }
        } catch (e) {}
    }

    function incomingId(payload) {
        if (! payload) return 0;
        if (typeof payload === 'number') return payload;
        if (payload.id) return Number(payload.id);
        if (Array.isArray(payload) && payload[0]) return Number(payload[0].id || payload[0]);
        if (payload.detail) return incomingId(payload.detail);
        return 0;
    }

    window.addEventListener('team-chat-incoming', function (e) {
        notifyIncoming(incomingId(e.detail || e), null);
    });
    document.addEventListener('livewire:init', function () {
        if (! window.Livewire || ! Livewire.on || window.__posTeamChatSoundBound) return;
        window.__posTeamChatSoundBound = true;
        Livewire.on('team-chat-incoming', function (payload) {
            notifyIncoming(incomingId(payload), null);
        });
    });
    tick();
    setInterval(tick, 8000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') tick();
    });
})();
</script>
@endif
