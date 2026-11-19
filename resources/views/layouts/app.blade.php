@php
    $staff = auth()->user();
    $workspaceContext = app(\App\Services\DepartmentWorkspace::class);
    $navigationDepartment = $workspaceContext->resolve(request(), $staff);
    $workspaceMenu = $workspaceContext->menu(request(), $staff, $navigationDepartment);
    $isOsc = request()->routeIs('osc.*');
    $identity = match ($navigationDepartment) {
        'perancang' => ['iNDECS Planning', 'Planning', 'Jabatan Perancang Bandar'],
        'bangunan' => ['iNDECS Building', 'Building', 'Jabatan Bangunan'],
        'kejuruteraan' => ['iNDECS Engineering', 'Engineering', 'Jabatan Kejuruteraan'],
        'landskap' => ['iNDECS Landscape', 'Landscape', 'Jabatan Landskap'],
        default => ['iNDECS', $isOsc ? 'Daftar Mesyuarat' : 'Ruang kerja bersama', 'Ruang kerja bersama'],
    };
    [$workspaceName, $workspaceShort, $workspaceDepartment] = $identity;
    $workspaceDepartmentIcon = $navigationDepartment;
    $homeUrl = $workspaceMenu['work'][0]['url'] ?? $workspaceMenu['work'][1]['url'] ?? route('home');
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
    <script src="{{ asset('js/workspace-switcher.js') }}" defer></script>
</head>
<body class="@yield('body-class')">
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
                <summary><span class="workspace-current">@if ($navigationDepartment)<x-department-icon :department="$workspaceDepartmentIcon" compact />@elseif ($isOsc)<x-eosc-icon compact />@else<x-mark class="mark-reversed mark-small" />@endif<span><small>Ruang jabatan</small><strong>{{ $workspaceShort }}</strong></span></span><x-icon name="chevron-down" /></summary>
                <nav aria-label="Pilih ruang jabatan">
                    @include('partials.workspace-applications')
                </nav>
            </details>
            <nav class="primary-nav" aria-label="Navigasi utama">
                @include('partials.workspace-navigation')
            </nav>
            <div class="sidebar-note"><x-mark class="mark-reversed mark-small" /><div><strong>Persekitaran iNDECS</strong><small>Majlis Bandaraya Seberang Perai</small></div></div>
        </aside>
        <button type="button" class="sidebar-edge-toggle" data-sidebar-collapse aria-controls="sidebar" aria-expanded="true" aria-label="Sembunyi menu" title="Sembunyi menu"><x-icon name="chevron-left" /></button>
        <div class="page-shell">
            <header class="topbar">
                <button class="menu-button icon-button" type="button" data-menu-toggle aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar"><x-icon name="menu" /></button>
                <div class="topbar-context">@if ($navigationDepartment)<x-department-icon :department="$workspaceDepartmentIcon" compact />@elseif ($isOsc)<x-eosc-icon compact />@else<x-mark class="mark-small" />@endif<div><strong>{{ $workspaceName }}</strong><span>{{ $workspaceDepartment }}</span></div></div>
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
