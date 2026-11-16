@include('layouts.partials.notify-sounds')
<a id="web-order-toast" class="pos-red-toast" hidden role="status" href="#">
    <span class="pos-red-toast-kicker">Ecommerce order</span>
    <span class="pos-red-toast-title"></span>
    <span class="pos-red-toast-body"></span>
    <button type="button" class="pos-red-toast-x" aria-label="Dismiss">&times;</button>
</a>
<style>
    .pos-red-toast {
        position: fixed; right: 1rem; top: 3.1rem; z-index: 95;
        display: flex; flex-direction: column; gap: .2rem;
        min-width: 17rem; max-width: 24rem; padding: .8rem 2.2rem .85rem 1rem;
        background: #b71c1c; color: #fff; text-decoration: none;
        border-radius: 10px; box-shadow: 0 14px 34px rgba(183, 28, 28, .45);
        font-size: .8rem; line-height: 1.35;
        animation: pos-red-toast-in .28s ease-out;
    }
    .pos-red-toast[hidden] { display: none !important; }
    .pos-red-toast:hover { background: #9a1515; }
    .pos-red-toast-kicker {
        font-size: .68rem; font-weight: 800; letter-spacing: .04em;
        text-transform: uppercase; opacity: .9;
    }
    .pos-red-toast-title { font-weight: 800; font-size: .9rem; }
    .pos-red-toast-body { font-weight: 600; opacity: .95; }
    .pos-red-toast-x {
        position: absolute; top: .35rem; right: .45rem;
        background: transparent; border: 0; color: #fff; font-size: 1.15rem; line-height: 1; cursor: pointer; opacity: .9;
    }
    @keyframes pos-red-toast-in {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
<script>
(function () {
    const url = @json(route('notify.web-orders', [], false));
    const soundKey = @json('pos.notify.web-order.'.auth()->user()->company_id);
    const toast = document.getElementById('web-order-toast');
    let stored = null;

    toast.querySelector('.pos-red-toast-x').addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toast.hidden = true;
    });

    function show(data) {
        const p = data.preview || {};
        const more = Number(data.new_count || 0) > 1 ? ' (+' + (Number(data.new_count) - 1) + ' more)' : '';
        toast.querySelector('.pos-red-toast-kicker').textContent = (p.source || 'Ecommerce') + ' order';
        toast.querySelector('.pos-red-toast-title').textContent = (p.order_number ? ('Order ' + p.order_number) : 'New order') + more;
        toast.querySelector('.pos-red-toast-body').textContent = [p.customer, p.total ? '$' + p.total : ''].filter(Boolean).join(' · ');
        toast.href = p.url || '#';
        toast.hidden = false;
        clearTimeout(toast._hide);
        toast._hide = setTimeout(function () { toast.hidden = true; }, 30000);
    }

    function orderVoice(data) {
        const p = data.preview || {};
        const total = p.total ? String(p.total) : '';
        // Short spoken alert only: new ecommerce order + amount.
        if (total) {
            return 'You received a new ecommerce order. Amount ' + total + ' dollars.';
        }
        return 'You received a new ecommerce order.';
    }

    function notifyNew(data) {
        show(data);
        if (typeof window.playPosNotify === 'function') {
            window.playPosNotify('order', orderVoice(data));
        }
    }

    async function tick() {
        try {
            try { stored = localStorage.getItem(soundKey); } catch (e) {}
            const res = await fetch(url + (stored ? '?after=' + encodeURIComponent(stored) : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (! res.ok) return;
            const data = await res.json();
            if (window.posNotifyClaim(soundKey, data.latest_id)) {
                notifyNew(data);
            } else if (data.play_sound && Number(data.latest_id || 0) > 0) {
                notifyNew(data);
            }
        } catch (e) {}
    }
    tick();
    setInterval(tick, 10000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') tick();
    });
})();
</script>
