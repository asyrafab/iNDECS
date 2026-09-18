<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'iNDECS') · Intergrated Development Control System</title>
    <meta name="description" content="iNDECS — Sistem Bersepada Kawalan Pembangunan MBSP">
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
    <script src="{{ asset('js/indecs.js') }}" defer></script>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="Dashboard iNDECS">
                <span class="brand-mark">iN</span>
                <span>
                    <strong>iNDECS</strong>
                    <small>Sistem Bersepada Kawalan Pembangunan</small>
                </span>
            </a>

            <nav class="primary-nav" aria-label="Navigasi utama">
                <span class="nav-label">Ruang kerja</span>
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="nav-icon">⌂</span>
                    Ringkasan
                </a>
                <a href="{{ route('perancang.index') }}" class="nav-link {{ request()->routeIs('perancang.*') ? 'active' : '' }}">
                    <span class="nav-icon">▤</span>
                    Permohonan PKM
                </a>

                <span class="nav-label nav-label-spaced">Akses pantas</span>
                <a href="{{ route('perancang.create') }}" class="nav-link">
                    <span class="nav-icon">＋</span>
                    Daftar PKM
                </a>
            </nav>

            <div class="sidebar-note">
                <span class="dot-live"></span>
                <div>
                    <strong>Modul perintis</strong>
                    <small>Jabatan Perancang Bandar</small>
                </div>
            </div>
        </aside>

        <div class="page-shell">
            <header class="topbar">
                <button class="menu-button" type="button" data-menu-toggle aria-label="Buka menu">☰</button>
                <div class="topbar-context">
                    <span>Majlis Bandaraya Seberang Perai</span>
                    <strong>Modul Kebenaran Merancang</strong>
                </div>
                @include('partials.theme-control')
                <div class="user-chip" aria-label="Pengguna semasa">
                    <span class="avatar">{{ collect(explode(' ', auth()->user()->name))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') }}</span>
                    <span>
                        <strong>{{ auth()->user()->name }}</strong>
                        <small>{{ str(auth()->user()->role)->replace('_', ' ')->title() }}</small>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-button" aria-label="Log keluar" title="Log keluar">↪</button>
                    </form>
                </div>
            </header>

            <main class="page-content">
                @if (session('success'))
                    <div class="alert alert-success" role="status">
                        <span>✓</span>
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-error" role="alert">
                        <span>!</span>
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
