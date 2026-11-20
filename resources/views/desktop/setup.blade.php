<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Desktop setup - {{ config('app.name') }}</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; background: #f3f5f8; margin: 0; color: #1f2937; }
        .card { max-width: 520px; margin: 60px auto; background: #fff; border-radius: 10px; padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        h1 { font-size: 22px; margin: 0 0 6px; }
        p.lead { color: #6b7280; margin: 0 0 24px; }
        label { display: block; font-weight: 600; margin: 16px 0 6px; }
        input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; }
        small { color: #6b7280; }
        button { margin-top: 24px; width: 100%; padding: 12px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; font-size: 16px; cursor: pointer; }
        button[disabled] { background: #93c5fd; cursor: wait; }
        .error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 12px; border-radius: 6px; margin-bottom: 12px; }
        .wait { display: none; margin-top: 16px; color: #2563eb; }
        .progress-msg { font-size: 16px; font-weight: 600; margin: 8px 0; color: #2563eb; }
        .progress-log { background: #111827; color: #d1d5db; font: 12px Consolas, monospace; padding: 10px; border-radius: 6px; height: 260px; overflow-y: auto; white-space: pre-wrap; margin-top: 12px; }
        .muted { color: #6b7280; font-size: 13px; }
        .bar { height: 22px; background: #e5e7eb; border-radius: 11px; overflow: hidden; margin: 14px 0 6px; }
        .bar-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #3b82f6); transition: width .6s ease; }
        .percent { font-size: 34px; font-weight: 700; color: #1f2937; text-align: center; margin-top: 4px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Connect this PC</h1>
    <p class="lead">This desktop works offline and syncs automatically with your online POS.</p>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    @if (!empty($progress) && in_array($progress['state'], ['running', 'failed', 'done']))
        <div id="progress">
            <div id="p-error" class="error" style="{{ $progress['state'] === 'failed' ? '' : 'display:none' }}">{{ $progress['error'] ?? '' }}</div>
            <div id="p-percent" class="percent">{{ (int) floor($progress['percent'] ?? 0) }}%</div>
            <div class="bar"><div id="p-bar" class="bar-fill" style="width: {{ (float) ($progress['percent'] ?? 0) }}%"></div></div>
            <div id="p-msg" class="progress-msg">{{ $progress['message'] ?? '' }}</div>
            <div class="muted">Downloading all data from the server. Keep the app open. <span id="p-time"></span></div>
            <div id="p-log" class="progress-log">{{ implode("\n", $progress['log'] ?? []) }}</div>
            <form id="p-retry" method="POST" action="{{ route('desktop.setup.retry') }}" style="{{ $progress['state'] === 'failed' ? '' : 'display:none' }}">
                @csrf
                <button type="submit">Try again</button>
            </form>
        </div>
        <script>
            (function () {
                var started = {{ (int) ($progress['started_at'] ?? time()) }};
                function tick() {
                    fetch('{{ route('desktop.setup.progress') }}', {headers: {'Accept': 'application/json'}, cache: 'no-store'})
                        .then(function (r) { return r.json(); })
                        .then(function (p) {
                            if (p.state === 'done') { window.location.href = '{{ url('/login') }}'; return; }
                            if (p.state === 'none') { window.location.reload(); return; }
                            document.getElementById('p-msg').textContent = p.message || '';
                            var pct = Math.max(0, Math.min(100, p.percent || 0));
                            document.getElementById('p-percent').textContent = Math.floor(pct) + '%';
                            document.getElementById('p-bar').style.width = pct + '%';
                            document.title = Math.floor(pct) + '% - Downloading data';
                            var log = document.getElementById('p-log');
                            var atBottom = log.scrollTop + log.clientHeight >= log.scrollHeight - 5;
                            log.textContent = (p.log || []).join('\n');
                            if (atBottom) log.scrollTop = log.scrollHeight;
                            var failed = p.state === 'failed';
                            document.getElementById('p-error').style.display = failed ? '' : 'none';
                            document.getElementById('p-error').textContent = p.error || '';
                            document.getElementById('p-retry').style.display = failed ? '' : 'none';
                            if (!failed) setTimeout(tick, 2000);
                        })
                        .catch(function () { setTimeout(tick, 3000); });
                }
                setInterval(function () {
                    var s = Math.floor(Date.now() / 1000) - started, m = Math.floor(s / 60);
                    document.getElementById('p-time').textContent = '(' + m + 'm ' + (s % 60) + 's)';
                }, 1000);
                var log = document.getElementById('p-log'); log.scrollTop = log.scrollHeight;
                @if ($progress['state'] !== 'failed') tick(); @endif
            })();
        </script>
    @else
    <form method="POST" action="{{ url('/desktop/setup') }}" onsubmit="document.getElementById('go').disabled = true; document.getElementById('wait').style.display = 'block';">
        @csrf
        <label for="server_url">Online server address</label>
        <input id="server_url" name="server_url" type="url" required placeholder="https://pos.yourdomain.com"
               value="{{ old('server_url', $settings['server_url'] ?? '') }}">

        <label for="token">Desktop key</label>
        <input id="token" name="token" type="text" required autocomplete="off" value="{{ old('token') }}">
        <small>Created by an admin in the online POS under <strong>File → Desktop devices</strong> (Add device &amp; generate key).</small>

        <button id="go" type="submit">Connect and download data</button>
        <div id="wait" class="wait">Connecting to the server...</div>
    </form>
    @endif
</div>
</body>
</html>
