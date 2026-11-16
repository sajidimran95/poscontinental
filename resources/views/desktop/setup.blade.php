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
    </style>
</head>
<body>
<div class="card">
    <h1>Connect this PC</h1>
    <p class="lead">This desktop works offline and syncs automatically with your online POS.</p>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ url('/desktop/setup') }}" onsubmit="document.getElementById('go').disabled = true; document.getElementById('wait').style.display = 'block';">
        @csrf
        <label for="server_url">Online server address</label>
        <input id="server_url" name="server_url" type="url" required placeholder="https://pos.yourdomain.com"
               value="{{ old('server_url', $settings['server_url'] ?? '') }}">

        <label for="token">Device token</label>
        <input id="token" name="token" type="text" required autocomplete="off" value="{{ old('token') }}">
        <small>Created on the server under File → Desktop devices, or <code>php artisan sync:device add "PC name"</code>.</small>

        <button id="go" type="submit">Connect and download data</button>
        <div id="wait" class="wait">Downloading all data from the server. This can take a few minutes, keep the app open...</div>
    </form>
</div>
</body>
</html>
