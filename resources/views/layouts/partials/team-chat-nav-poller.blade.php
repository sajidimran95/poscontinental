@php
    $teamChatUnreadUrl = $teamChatUnreadUrl ?? null;
@endphp
@if ($teamChatUnreadUrl)
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
    let lastCount = -1;
    let audioCtx = null;
    const toast = document.getElementById('team-chat-toast');

    function unlockAudio() {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return;
        if (!audioCtx) audioCtx = new AC();
        if (audioCtx.state === 'suspended') audioCtx.resume();
    }
    document.addEventListener('pointerdown', unlockAudio, { once: true });
    document.addEventListener('keydown', unlockAudio, { once: true });

    function playChime() {
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            if (!audioCtx) audioCtx = new AC();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            const t = audioCtx.currentTime;
            function tone(freq, start, dur, gain) {
                const osc = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                g.gain.setValueAtTime(0.0001, t + start);
                g.gain.exponentialRampToValueAtTime(gain, t + start + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + start + dur);
                osc.connect(g);
                g.connect(audioCtx.destination);
                osc.start(t + start);
                osc.stop(t + start + dur + 0.02);
            }
            tone(880, 0, 0.14, 0.12);
            tone(1174, 0.12, 0.18, 0.1);
        } catch (e) {}
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
            if (lastCount >= 0 && n > lastCount && data.preview) {
                playChime();
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
            lastCount = n;
        } catch (e) {}
    }
    tick();
    setInterval(tick, 25000);
})();
</script>
@endif
