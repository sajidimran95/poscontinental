<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Page Not Found — {{ config('app.name', 'JAPS POS') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|jetbrains-mono:500&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Plus Jakarta Sans", system-ui, sans-serif;
            color: #0f172a;
            background: #1e293b;
        }
        .stage {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
        }
        .stage::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(900px 520px at 12% 18%, rgba(56, 189, 248, 0.18), transparent 55%),
                radial-gradient(700px 480px at 88% 78%, rgba(14, 116, 144, 0.22), transparent 50%),
                linear-gradient(145deg, #0f172a 0%, #1e3a5f 48%, #0b1220 100%);
        }
        .card {
            position: relative;
            z-index: 1;
            width: min(440px, 100%);
            border-radius: 14px;
            overflow: hidden;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.35) inset,
                0 28px 60px rgba(2, 6, 23, 0.45);
            text-align: center;
            padding: 2.25rem 1.75rem 1.75rem;
        }
        .code {
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 3.5rem;
            font-weight: 500;
            letter-spacing: -0.04em;
            line-height: 1;
            color: #0e7490;
            margin: 0 0 0.75rem;
        }
        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
        }
        p {
            margin: 0 0 1.5rem;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #64748b;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            justify-content: center;
        }
        a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.5rem;
            padding: 0 1.15rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s ease, transform 0.1s ease;
        }
        .btn-primary {
            background: #0e7490;
            color: #fff;
        }
        .btn-primary:hover { background: #0f766e; }
        .btn-secondary {
            background: #e2e8f0;
            color: #334155;
        }
        .btn-secondary:hover { background: #cbd5e1; }
        .brand {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="stage">
        <div class="card">
            <p class="code">404</p>
            <h1>Page not found</h1>
            <p>The page you requested does not exist or may have been moved.</p>
            <div class="actions">
                @auth
                    <a class="btn-primary" href="{{ route('home') }}">Go to Home</a>
                @else
                    <a class="btn-primary" href="{{ route('login') }}">Sign in</a>
                @endauth
                <a class="btn-secondary" href="javascript:history.back()">Go back</a>
            </div>
            <div class="brand">{{ config('app.name', 'JAPS POS') }}</div>
        </div>
    </div>
</body>
</html>
