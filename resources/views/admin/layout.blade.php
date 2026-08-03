<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Admin') · Cheias RS</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="min-h-dvh bg-stone-100 text-stone-900 font-sans">
    <div class="admin-shell">
        @auth
            <aside class="admin-sidebar">
                <a href="{{ route('admin.dashboard') }}" class="admin-brand">
                    <span class="dot" aria-hidden="true"></span>
                    Cheias RS · Admin
                </a>

                <nav class="admin-nav">
                    <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Painel</a>
                    <a href="{{ route('admin.stations.index') }}" @if (request()->routeIs('admin.stations.*')) aria-current="page" @endif>Estações</a>
                    <a href="{{ route('admin.reports.index') }}" @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif>Relatos</a>
                    <a href="{{ route('admin.settings.edit') }}" @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif>Configurações</a>
                </nav>

                <form method="POST" action="{{ route('admin.logout') }}" class="admin-logout-form">
                    @csrf
                    <button type="submit" class="admin-logout">Sair</button>
                </form>
            </aside>
        @endauth

        <main class="admin-main">
            @yield('content')
        </main>
    </div>
</body>
</html>
