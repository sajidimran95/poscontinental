<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=5">
    <title>{{ $title ?? 'Invoice' }} — {{ config('app.name', 'JAPS POS') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #e8edf3;
            font-family: system-ui, -apple-system, sans-serif;
            color: #0b1220;
        }
        .sale-inv-bar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            padding-top: max(10px, env(safe-area-inset-top, 0px));
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }
        .sale-inv-bar a,
        .sale-inv-bar button {
            appearance: none;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #0b1220;
            border-radius: 10px;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }
        .sale-inv-bar .primary {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }
        .sale-inv-stage {
            padding: 12px;
            padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        }
        .sale-inv-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 16px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }

        /* Mobile: scale full US Letter page to screen width (no crop) */
        @media (max-width: 767px) {
            .sale-inv-stage {
                padding: 8px;
                padding-bottom: calc(16px + env(safe-area-inset-bottom, 0px));
                overflow-x: hidden;
            }
            .sale-inv-scale-host {
                width: 100%;
                overflow: hidden;
            }
            .sale-inv-wrap {
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
                background: #fff;
                transform-origin: top left;
                width: 8.5in; /* natural US Letter width for accurate scale */
            }
            .sale-inv-wrap .usl-wrap {
                max-width: none !important;
                width: 100% !important;
            }
        }

        @media print {
            .sale-inv-bar { display: none !important; }
            body { background: #fff; }
            .sale-inv-stage { padding: 0 !important; overflow: visible !important; }
            .sale-inv-scale-host {
                width: auto !important;
                height: auto !important;
                overflow: visible !important;
            }
            .sale-inv-wrap {
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                max-width: none !important;
                width: auto !important;
                transform: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="sale-inv-bar">
        <a href="{{ route('sale.orders') }}">← Orders</a>
        <div style="display:flex;gap:8px;">
            <button type="button" class="primary" onclick="window.print()">Print / PDF</button>
        </div>
    </div>
    <div class="sale-inv-stage">
        <div class="sale-inv-scale-host" id="saleInvHost">
            <div class="sale-inv-wrap" id="receipt_section">
                {!! $html !!}
            </div>
        </div>
    </div>
    <script>
        (function () {
            var host = document.getElementById('saleInvHost');
            var wrap = document.getElementById('receipt_section');
            if (!host || !wrap) return;

            function fitMobileInvoice() {
                // Desktop / print: no scale
                if (window.matchMedia('(min-width: 768px)').matches) {
                    wrap.style.transform = '';
                    wrap.style.width = '';
                    host.style.height = '';
                    return;
                }

                // Natural letter width for consistent ratio
                var letterPx = 8.5 * 96; // 816px at 96dpi
                wrap.style.width = letterPx + 'px';
                wrap.style.transform = 'none';

                // Force layout, then scale to available screen width
                var available = host.clientWidth || (window.innerWidth - 16);
                var scale = Math.min(1, available / letterPx);
                if (scale <= 0) scale = 1;

                wrap.style.transformOrigin = 'top left';
                wrap.style.transform = 'scale(' + scale + ')';

                // Host height = scaled content height so nothing is clipped
                var fullH = wrap.scrollHeight || wrap.offsetHeight;
                host.style.height = Math.ceil(fullH * scale) + 'px';
            }

            fitMobileInvoice();
            window.addEventListener('resize', fitMobileInvoice);
            window.addEventListener('orientationchange', function () {
                setTimeout(fitMobileInvoice, 150);
            });
            // Images (logo/letterhead) can change height after load
            wrap.querySelectorAll('img').forEach(function (img) {
                if (!img.complete) {
                    img.addEventListener('load', fitMobileInvoice);
                }
            });
            setTimeout(fitMobileInvoice, 300);
        })();
    </script>
</body>
</html>
