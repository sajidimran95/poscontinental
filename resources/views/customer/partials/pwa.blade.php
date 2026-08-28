{{-- Customer PWA install bar (login + portal) --}}
<style>
    .ca-install {
        position: fixed;
        left: 0; right: 0;
        bottom: calc(88px + env(safe-area-inset-bottom, 0px));
        z-index: 120;
        padding: 0 16px 8px;
        pointer-events: none;
        display: flex; align-items: flex-end; justify-content: center;
        box-sizing: border-box;
    }
    .ca-install[hidden] { display: none !important; }
    .ca-install__inner {
        pointer-events: auto;
        width: 100%; max-width: 420px;
        display: flex; align-items: center; gap: 10px;
        background: #0f172a; color: #fff; border-radius: 14px; padding: 12px 14px;
        box-shadow: 0 12px 40px rgba(15, 23, 42, .4);
        font-family: Outfit, system-ui, sans-serif;
    }
    .ca-install__logo {
        width: 36px; height: 36px; border-radius: 9px;
        background: #e11d48; object-fit: cover; flex-shrink: 0;
    }
    .ca-install__btn {
        flex-shrink: 0; background: #e11d48; color: #fff; border: 0; border-radius: 8px;
        padding: 8px 12px; font-weight: 800; font-size: 12px; cursor: pointer;
    }
    .ca-install__close {
        width: 28px; height: 28px; border: 0; border-radius: 999px;
        background: rgba(255,255,255,.12); color: #fff; font-size: 16px; cursor: pointer; line-height: 1;
    }
    body.customer-pwa-standalone .ca-install { display: none !important; }
    /* Login (no bottom nav) — sit lower */
    body.japs-auth-body .ca-install {
        bottom: calc(16px + env(safe-area-inset-bottom, 0px));
    }
    @media (min-width: 1024px) {
        .ca-install { bottom: 24px; }
    }
</style>

<div id="cust_pwa_install_bar" class="ca-install" hidden>
    <div class="ca-install__inner">
        <img src="{{ asset('pwa/customer-icon-192.png') }}" alt="" class="ca-install__logo" width="36" height="36"
             onerror="this.src='{{ asset('pwa/sale-icon-192.png') }}'">
        <div style="flex:1;min-width:0">
            <div style="font-size:12px;font-weight:800">Install Customer App</div>
            <div style="font-size:10px;opacity:.7">Faster access — open from home screen</div>
        </div>
        <button type="button" id="cust_pwa_install_btn" class="ca-install__btn">Install</button>
        <button type="button" id="cust_pwa_dismiss_btn" class="ca-install__close" aria-label="Dismiss">×</button>
    </div>
</div>

<script>
window.__cust_pwa__ = {
    swUrl: @json(url('/customer/pwa/sw.js')),
    manifestUrl: @json(url('/customer/pwa/manifest.webmanifest')),
    iconUrl: @json(asset('pwa/customer-icon-192.png')),
    startUrl: @json(url('/customer/login'))
};
</script>
<script src="{{ asset('js/customer-pwa.js') }}" defer></script>
