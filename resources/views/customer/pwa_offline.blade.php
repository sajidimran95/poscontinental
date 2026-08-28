<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $theme_color ?? '#0f766e' }}">
    <title>Offline — Customer</title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;background:{{ $background_color ?? '#0b1220' }};color:#fff;padding:24px;text-align:center}
        a{display:inline-block;margin-top:16px;padding:12px 18px;background:#0f766e;color:#fff;text-decoration:none;border-radius:10px;font-weight:700}
    </style>
</head>
<body>
<div>
    <h1 style="margin:0 0 8px;font-size:1.25rem">You're offline</h1>
    <p style="margin:0;opacity:.75">Reconnect and try again.</p>
    <a href="{{ url('/customer') }}">Back to Customer app</a>
</div>
</body>
</html>
