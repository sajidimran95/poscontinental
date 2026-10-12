@php
    $teamChatUnreadUrl = $teamChatUnreadUrl ?? null;
    $teamChatSoundKey = 'pos.notify.chat.'.substr(md5($teamChatUnreadUrl.'|'.(auth()->id() ?: auth('sale')->id())), 0, 12);
@endphp
@if ($teamChatUnreadUrl)
@include('layouts.partials.notify-sounds')
<div id="team-chat-toast" class="tc-nav-toast" hidden role="status"></div>
<style>
    .tc-nav-link { position: relative; display: inline-flex; align-items: center; gap: .35rem; }
    .tc-nav-badge {
        min-width: 1.1rem; height: 1.1rem; padding: 0 .28rem;
        border-radius: 999px; background: #dc2626; color: #fff;
        font-size: 10px; font-weight: 800; line-height: 1.1rem; text-align: center;
    }
    .tc-nav-toast {
        position: fixed; top: 2.75rem; right: 1rem; z-index: 80;
        max-width: 20rem; background: #0f172a; color: #fff;
        border-radius: 10px; padding: .65rem .85rem; font-size: .8rem; font-weight: 600;
        box-shadow: 0 10px 28px rgba(15,23,42,.28); line-height: 1.35;
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
            if (window.posNotifyClaim(soundKey, data.latest_id) && data.preview) {
                window.playPosNotify('chat');
                if (toast) {
                    const from = data.preview.from || 'New message';
                    const body = data.preview.body || '';
                    const ch = data.preview.channel ? ' · ' + data.preview.channel : '';
                    toast.textContent = from + ch + ': ' + body;
                    toast.hidden = false;
                    clearTimeout(toast._hide);
                    toast._hide = setTimeout(function () { toast.hidden = true; }, 7000);
                }
            }
        } catch (e) {}
    }
    window.addEventListener('team-chat-incoming', function (e) {
        const id = e.detail && (e.detail.id || (e.detail[0] && e.detail[0].id));
        if (window.posNotifyClaim(soundKey, id)) window.playPosNotify('chat');
    });
    tick();
    setInterval(tick, 12000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') tick();
    });
})();
</script>
@endif
