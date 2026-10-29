@php
    $staff = auth()->user();
    $canBangunan = $staff->canAccessBangunan();
    $canPerancang = $staff->canAccessPkm();
    $canLegacy = $staff->canAccessBangunanLegacy();
    $canOsc = $staff->canAccessOsc();
    $isOsc = request()->routeIs('osc.*');
    $isBangunan = ! $isOsc && request()->routeIs('bangunan.*');
    $workspaceName = $isOsc ? 'eOSC+' : ($isBangunan ? 'iNDECS Bangunan' : 'iNDECS Perancang');
    $workspaceShort = $isOsc ? 'eOSC+' : ($isBangunan ? 'Bangunan' : 'Perancang');
    $workspaceDepartment = $isOsc ? 'Mesyuarat OSC' : ($isBangunan ? 'Jabatan Bangunan' : 'Jabatan Perancang Bandar');
    $workspaceIcon = $isOsc ? 'folder-open' : ($isBangunan ? 'building-2' : 'map');
    $homeUrl = $isOsc ? route('osc.meetings.index') : ($isBangunan ? ($canBangunan ? route('bangunan.index') : route('bangunan.legacy.index')) : ($canBangunan ? route('perancang.index') : route('dashboard')));
    $canRegisterBangunan = $canBangunan && collect(\App\Models\BangunanApplication::TYPES)->contains(fn ($family) => $staff->canPerformBangunan('access', $family) && $staff->canPerformBangunan('register', $family));
@endphp
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ruang kerja') · {{ $workspaceName }}</title>
    <meta name="description" content="iNDECS — Sistem Bersepada Kawalan Pembangunan MBSP">
    <link rel="icon" href="{{ asset('img/indecs-mark.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
    <script src="{{ asset('js/indecs.js') }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Langkau ke kandungan</a>
    <div class="app-shell">
        <button class="sidebar-backdrop" data-menu-close aria-label="Tutup menu navigasi" hidden></button>
        <aside class="sidebar" id="sidebar" aria-label="Navigasi iNDECS">
            <button type="button" class="icon-button sidebar-close" data-menu-close-inline aria-label="Tutup menu"><x-icon name="x" /></button>
            <a class="brand" href="{{ $homeUrl }}" aria-label="{{ $workspaceName }}">
                <x-mark class="mark-reversed mark-entry" />
                <span><strong>iNDECS</strong><small>Kawalan Pembangunan</small></span>
            </a>
            <details class="workspace-switcher">
                <summary><span><small>Ruang jabatan</small><strong><x-icon :name="$workspaceIcon" />{{ $workspaceShort }}</strong></span><x-icon name="chevron-down" /></summary>
                <nav aria-label="Pilih ruang jabatan">
                    @if ($canPerancang)<a href="{{ $canBangunan ? route('perancang.index') : route('dashboard') }}" @if (! $isBangunan && ! $isOsc) aria-current="page" @endif><x-icon name="map" />iNDECS Perancang</a>@endif
                    @if ($canBangunan)<a href="{{ route('bangunan.index') }}" @if ($isBangunan && ! request()->routeIs('bangunan.legacy.*')) aria-current="page" @endif><x-icon name="building-2" />iNDECS Bangunan</a>@elseif ($canLegacy)<a href="{{ route('bangunan.legacy.index') }}" aria-current="page"><x-icon name="archive" />Bangunan · Rekod warisan</a>@endif
                    @if ($canOsc)<a href="{{ route('osc.meetings.index') }}" @if ($isOsc) aria-current="page" @endif><x-icon name="folder-open" />eOSC+</a>@endif
                </nav>
            </details>
            <nav class="primary-nav" aria-label="Navigasi utama">
                <span class="nav-label">Ruang kerja</span>
                @if ($isBangunan)
                    @if ($canBangunan)
                        <a href="{{ route('bangunan.index') }}" class="nav-link {{ request()->routeIs('bangunan.index', 'bangunan.show', 'bangunan.documents.*', 'bangunan.document-catalogue.*') ? 'active' : '' }}" @if (request()->routeIs('bangunan.index', 'bangunan.show', 'bangunan.documents.*', 'bangunan.document-catalogue.*')) aria-current="page" @endif><x-icon name="layout-dashboard" />Ringkasan &amp; permohonan</a>
                        @if ($canRegisterBangunan)<a href="{{ route('bangunan.create') }}" class="nav-link {{ request()->routeIs('bangunan.create') ? 'active' : '' }}" @if (request()->routeIs('bangunan.create')) aria-current="page" @endif><x-icon name="file-plus-2" />Daftar permohonan</a>@endif
                    @endif
                    @if ($canLegacy)<a href="{{ route('bangunan.legacy.index') }}" class="nav-link {{ request()->routeIs('bangunan.legacy.*') ? 'active' : '' }}" @if (request()->routeIs('bangunan.legacy.*')) aria-current="page" @endif><x-icon name="archive" />Carian Rekod Warisan</a>@endif
                @elseif ($isOsc)
                    <a href="{{ route('osc.meetings.index') }}" class="nav-link active" aria-current="page"><x-icon name="folder-open" />Mesyuarat OSC</a>
                @else
                    @if (! $canBangunan)<a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" />Ringkasan</a>@endif
                    <a href="{{ route('perancang.index') }}" class="nav-link {{ request()->routeIs('perancang.index', 'perancang.show') ? 'active' : '' }}" @if (request()->routeIs('perancang.index', 'perancang.show')) aria-current="page" @endif><x-icon name="files" />Permohonan PKM</a>
                    @if ($staff->canPerformPkm('perancang.pkm.register'))<a href="{{ route('perancang.create') }}" class="nav-link {{ request()->routeIs('perancang.create') ? 'active' : '' }}" @if (request()->routeIs('perancang.create')) aria-current="page" @endif><x-icon name="file-plus-2" />Daftar PKM</a>@endif
                @endif
                @if ($canOsc && ! $isOsc)<a href="{{ route('osc.meetings.index') }}" class="nav-link"><x-icon name="folder-open" />eOSC+</a>@endif
                <span class="nav-label nav-label-spaced">Bantuan</span>
                <button class="nav-link" type="button" data-amira-open aria-controls="amira-panel" aria-expanded="false"><x-icon name="messages-square" />Panduan AMIRA</button>
            </nav>
            <div class="sidebar-note"><x-mark class="mark-reversed mark-small" /><div><strong>Persekitaran iNDECS</strong><small>Majlis Bandaraya Seberang Perai</small></div></div>
        </aside>
        <div class="page-shell">
            <header class="topbar">
                <button class="menu-button icon-button" type="button" data-menu-toggle aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar"><x-icon name="menu" /></button>
                <div class="topbar-context"><x-mark class="mark-small" /><div><strong>{{ $workspaceName }}</strong><span>{{ $workspaceDepartment }}</span></div></div>
                <div class="topbar-actions">
                    @include('partials.theme-control')
                    <button class="button button-secondary button-small amira-toggle" type="button" data-amira-open aria-controls="amira-panel" aria-expanded="false"><x-icon name="messages-square" /><span>AMIRA</span></button>
                    <div class="user-chip" aria-label="Pengguna semasa"><span class="avatar">{{ collect(explode(' ', $staff->name))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') }}</span><span><strong>{{ $staff->name }}</strong><small>{{ str($staff->role)->replace('_', ' ')->title() }}</small></span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="logout-button" aria-label="Log keluar" title="Log keluar"><x-icon name="log-out" /></button></form></div>
                </div>
            </header>
            <div class="workspace-body">
                <main class="page-content" id="main-content" tabindex="-1">
                    @if (session('success'))<div class="alert alert-success" role="status"><x-icon name="circle-check" /><div>{{ session('success') }}</div></div>@endif
                    @if (session('status'))<div class="alert alert-success" role="status"><x-icon name="circle-check" /><div>{{ session('status') }}</div></div>@endif
                    @if (session('error'))<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div>{{ session('error') }}</div></div>@endif
                    @yield('content')
                    <footer class="workspace-footer"><x-mark class="mark-small" /><span>Persekitaran iNDECS <span class="muted">· {{ $workspaceDepartment }}</span></span></footer>
                </main>
                @include('partials.amira', ['assistantContext' => $workspaceName, 'assistantStaff' => true])
            </div>
        </div>
    </div>
</body>
</html>
