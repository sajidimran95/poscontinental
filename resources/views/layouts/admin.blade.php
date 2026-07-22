<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin Panel' }} · JAPS POS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|jetbrains-mono:500&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="admin-body antialiased">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <span class="admin-brand-mark">J</span>
                <div>
                    <div class="admin-brand-name">JAPS Admin</div>
                    <div class="admin-brand-sub">Platform control</div>
                </div>
            </div>

            <nav class="admin-nav">
                <a href="{{ route('admin.panel.dashboard') }}" wire:navigate @class(['admin-nav-link', 'is-active' => request()->routeIs('admin.panel.dashboard')])>
                    Dashboard
                </a>
                <a href="{{ route('admin.panel.companies') }}" wire:navigate @class(['admin-nav-link', 'is-active' => request()->routeIs('admin.panel.companies') && ! request()->routeIs('admin.panel.companies.create')])>
                    Companies
                </a>
                <a href="{{ route('admin.panel.companies.create') }}" wire:navigate @class(['admin-nav-link', 'is-active' => request()->routeIs('admin.panel.companies.create')])>
                    Register company
                </a>
            </nav>

            <div class="admin-sidebar-foot">
                <div class="admin-user">{{ auth()->user()?->name }}</div>
                <div class="admin-user-email">{{ auth()->user()?->email }}</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="admin-logout">Sign out</button>
                </form>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-top">
                <h1 class="admin-page-title">{{ $title ?? 'Admin' }}</h1>
                @if (Route::has('home'))
                    <a href="{{ route('home') }}" class="admin-ghost-link">Open POS</a>
                @endif
            </header>
            <main class="admin-content">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
