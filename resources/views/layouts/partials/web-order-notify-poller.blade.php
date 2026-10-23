@include('layouts.partials.notify-sounds')
<a id="web-order-toast" class="wo-toast" hidden role="status" href="#">
    <span class="wo-toast-title"></span>
    <span class="wo-toast-body"></span>
    <button type="button" class="wo-toast-x" aria-label="Dismiss">&times;</button>
</a>
<style>
    .wo-toast {
        position: fixed; right: 1rem; bottom: 1rem; z-index: 90;
        display: flex; flex-direction: column; gap: .15rem;
        min-width: 16rem; max-width: 22rem; padding: .75rem 2.1rem .75rem .9rem;
        background: #b71c1c; color: #fff; text-decoration: none;
        border-radius: 10px; box-shadow: 0 12px 30px rgba(15,23,42,.32);
        font-size: .8rem; line-height: 1.35;
    }
    .wo-toast[hidden] { display: none; }
    .wo-toast:hover { background: #9a1515; }
    .wo-toast-title { font-weight: 800; font-size: .85rem; }
    .wo-toast-body { font-weight: 600; opacity: .95; }
    .wo-toast-x {
        position: absolute; top: .35rem; right: .45rem;
        background: transparent; border: 0; color: #fff; font-size: 1.1rem; line-height: 1; cursor: pointer; opacity: .85;
    }
</style>
<script>
(function () {
    const url = @json(route('notify.web-orders', [], false));
    const soundKey = @json('pos.notify.web-order.'.auth()->user()->company_id);
    const toast = document.getElementById('web-order-toast');
    let stored = null;

    toast.querySelector('.wo-toast-x').addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toast.hidden = true;
    });

    function show(data) {
        const p = data.preview || {};
        const more = Number(data.new_count || 0) > 1 ? ' (+' + (Number(data.new_count) - 1) + ' more)' : '';
        toast.querySelector('.wo-toast-title').textContent = 'New ' + (p.source || 'website') + ' order ' + (p.order_number || '') + more;
        toast.querySelector('.wo-toast-body').textContent = [p.customer, p.total ? '$' + p.total : ''].filter(Boolean).join(' · ');
        toast.href = p.url || '#';
        toast.hidden = false;
        clearTimeout(toast._hide);
        toast._hide = setTimeout(function () { toast.hidden = true; }, 30000);
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
                show(data);
            }
        } catch (e) {}
    }
    tick();
    setInterval(tick, 15000);
})();
</script>
