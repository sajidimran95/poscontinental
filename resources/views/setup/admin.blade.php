<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup admin — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 2rem 1rem; }
        .box { max-width: 28rem; margin: 2rem auto; background: #fff; border-radius: 16px; padding: 1.5rem 1.6rem; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { font-size: .9rem; color: #475569; line-height: 1.45; }
        .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: .75rem 1rem; border-radius: 10px; font-weight: 700; }
        .warn { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; padding: .75rem 1rem; border-radius: 10px; }
        code { background: #f1f5f9; padding: .1rem .35rem; border-radius: 4px; }
        button { width: 100%; margin-top: 1rem; background: #0f766e; color: #fff; border: 0; border-radius: 10px; padding: .85rem; font-weight: 800; cursor: pointer; }
        a { color: #0f766e; font-weight: 700; }
    </style>
</head>
<body>
<div class="box">
    <h1>Seed one admin</h1>
    <p>This does <strong>not</strong> delete other users, orders, or items. It only creates or restores <code>admin@gmail.com</code> as Administrator.</p>

    @if (! empty($result))
        <div class="ok">
            @if ($result['created'])
                Admin created.
            @elseif ($result['promoted'])
                Existing user is now Administrator.
            @else
                Admin is ready.
            @endif
            <div style="margin-top:.5rem;font-size:.85rem">
                Username: <code>{{ $result['email'] }}</code><br>
                @if ($result['password'])
                    Password: <code>{{ $result['password'] }}</code>
                @else
                    Password was not changed.
                @endif
            </div>
        </div>
        <p style="margin-top:1rem"><a href="{{ url('/login') }}">Go to admin login</a></p>
    @elseif (! empty($locked))
        <div class="warn">An Administrator already exists. Other users were left as they are. Use the main login. To reset this admin password, run <code>php artisan pos:seed-admin --reset-password</code> on the server.</div>
        <p><a href="{{ url('/login') }}">Go to login</a></p>
    @else
        <form method="POST" action="{{ url('/setup/admin') }}">
            @csrf
            <button type="submit">Create admin user</button>
        </form>
    @endif
</div>
</body>
</html>
