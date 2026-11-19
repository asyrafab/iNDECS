@php
    $staff = auth()->user();
    $canBangunan = $staff->canAccessBangunan();
    $canPerancang = $staff->canAccessPkm();
    $canLegacy = $staff->canAccessBangunanLegacy();
    $canOsc = $staff->canAccessOsc();
    $canKejuruteraan = $staff->canAccessKejuruteraan();
    $canLandskap = $staff->canAccessLandskap();
    $isTasks = request()->routeIs('tasks.*');
    $isOsc = request()->routeIs('osc.*');
    $isBangunan = ! $isOsc && request()->routeIs('bangunan.*');
    $isKejuruteraan = ! $isOsc && request()->routeIs('kejuruteraan.*');
    $isLandskap = ! $isOsc && request()->routeIs('landskap.*');
    $workspaceName = $isOsc ? 'Daftar Mesyuarat' : ($isBangunan ? 'iNDECS Building' : ($isKejuruteraan ? 'iNDECS Engineering' : ($isLandskap ? 'iNDECS Landscape' : 'iNDECS Planning')));
    $workspaceShort = $isOsc ? 'Daftar Mesyuarat' : ($isBangunan ? 'Building' : ($isKejuruteraan ? 'Engineering' : ($isLandskap ? 'Landscape' : 'Planning')));
    $workspaceDepartment = $isOsc ? 'Pendaftaran kertas mesyuarat OSC' : ($isBangunan ? 'Jabatan Bangunan' : ($isKejuruteraan ? 'Jabatan Kejuruteraan' : ($isLandskap ? 'Jabatan Landskap' : 'Jabatan Perancang Bandar')));
    $workspaceDepartmentIcon = $isBangunan ? 'bangunan' : ($isKejuruteraan ? 'kejuruteraan' : ($isLandskap ? 'landskap' : 'perancang'));
    if ($isTasks) {
        $workspaceName = 'iNDECS';
        $workspaceShort = 'Tugasan pegawai';
        $workspaceDepartment = 'Ruang kerja bersama';
    }
    $homeUrl = $isOsc ? route('osc.meetings.index') : ($isBangunan ? ($canBangunan ? route('bangunan.dashboard') : route('bangunan.legacy.index')) : ($isKejuruteraan ? route('kejuruteraan.dashboard') : ($isLandskap ? route('landskap.dashboard') : route('perancang.dashboard'))));
    if ($isTasks) {
        $homeUrl = route('home');
    }
    $canRegisterBangunan = $canBangunan && collect(\App\Models\BangunanApplication::TYPES)->contains(fn ($family) => $staff->canPerformBangunan('access', $family) && $staff->canPerformBangunan('register', $family));
    $canRegisterLandskap = $canLandskap && $staff->canPerformLandskap('register');
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
                <summary><span class="workspace-current">@if ($isTasks)<x-mark class="mark-reversed mark-small" />@elseif ($isOsc)<x-eosc-icon compact />@else<x-department-icon :department="$workspaceDepartmentIcon" compact />@endif<span><small>Ruang jabatan</small><strong>{{ $workspaceShort }}</strong></span></span><x-icon name="chevron-down" /></summary>
                <nav aria-label="Pilih ruang jabatan">
                    <a href="{{ route('home') }}"><x-icon name="layout-dashboard" />Semua aplikasi</a>
                    @include('partials.workspace-applications')
                </nav>
            </details>
            <nav class="primary-nav" aria-label="Navigasi utama">
                <span class="nav-label">Ruang kerja</span>
                @if ($isTasks)
                    @if ($canPerancang)<a class="nav-link" href="{{ route('perancang.index') }}"><x-department-icon department="perancang" compact />Permohonan Perancang</a>@endif
                    @if ($canBangunan)<a class="nav-link" href="{{ route('bangunan.index') }}"><x-department-icon department="bangunan" compact />Permohonan Bangunan</a>@endif
                    @if ($canKejuruteraan)<a class="nav-link" href="{{ route('kejuruteraan.index') }}"><x-department-icon department="kejuruteraan" compact />Permohonan LJUP</a>@endif
                    @if ($canLandskap)<a class="nav-link" href="{{ route('landskap.index') }}"><x-department-icon department="landskap" compact />Permohonan Landskap</a>@endif
                @elseif ($isBangunan)
                    @if ($canBangunan)
                    <a href="{{ route('bangunan.dashboard') }}" class="nav-link {{ request()->routeIs('bangunan.dashboard') ? 'active' : '' }}" @if (request()->routeIs('bangunan.dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" />Dashboard jabatan</a>
                        <a href="{{ route('bangunan.index') }}" class="nav-link {{ request()->routeIs('bangunan.index', 'bangunan.show', 'bangunan.documents.*', 'bangunan.document-catalogue.*') ? 'active' : '' }}" @if (request()->routeIs('bangunan.index', 'bangunan.show', 'bangunan.documents.*', 'bangunan.document-catalogue.*')) aria-current="page" @endif><x-icon name="layout-dashboard" />Permohonan</a>
                        @if ($canRegisterBangunan)<a href="{{ route('bangunan.create') }}" class="nav-link {{ request()->routeIs('bangunan.create') ? 'active' : '' }}" @if (request()->routeIs('bangunan.create')) aria-current="page" @endif><x-icon name="file-plus-2" />Daftar permohonan</a>@endif
                    @endif
                    @if ($canLegacy)<a href="{{ route('bangunan.legacy.index') }}" class="nav-link {{ request()->routeIs('bangunan.legacy.*') ? 'active' : '' }}" @if (request()->routeIs('bangunan.legacy.*')) aria-current="page" @endif><x-icon name="archive" />Carian Rekod Warisan</a>@endif
                @elseif ($isOsc)
                    {{-- Meeting registration is shared below with its existing access guard. --}}
                @elseif ($isKejuruteraan)
                    <a href="{{ route('kejuruteraan.dashboard') }}" class="nav-link {{ request()->routeIs('kejuruteraan.dashboard') ? 'active' : '' }}" @if (request()->routeIs('kejuruteraan.dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" />Dashboard jabatan</a>
                    <a href="{{ route('kejuruteraan.index') }}" class="nav-link {{ request()->routeIs('kejuruteraan.index', 'kejuruteraan.show') ? 'active' : '' }}" @if (request()->routeIs('kejuruteraan.index', 'kejuruteraan.show')) aria-current="page" @endif><x-icon name="files" />Permohonan LJUP</a>
                @elseif ($isLandskap)
                    <a href="{{ route('landskap.dashboard') }}" class="nav-link {{ request()->routeIs('landskap.dashboard') ? 'active' : '' }}" @if (request()->routeIs('landskap.dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" />Dashboard jabatan</a>
                    <a href="{{ route('landskap.index') }}" class="nav-link {{ request()->routeIs('landskap.index', 'landskap.show', 'landskap.documents.*', 'landskap.document-catalogue.*') ? 'active' : '' }}" @if (request()->routeIs('landskap.index', 'landskap.show', 'landskap.documents.*', 'landskap.document-catalogue.*')) aria-current="page" @endif><x-icon name="layout-dashboard" />Permohonan</a>
                    @if ($canRegisterLandskap)<a href="{{ route('landskap.create') }}" class="nav-link {{ request()->routeIs('landskap.create') ? 'active' : '' }}" @if (request()->routeIs('landskap.create')) aria-current="page" @endif><x-icon name="file-plus-2" />Daftar permohonan</a>@endif
                @else
                    <a href="{{ route('perancang.dashboard') }}" class="nav-link {{ request()->routeIs('dashboard', 'perancang.dashboard') ? 'active' : '' }}" @if (request()->routeIs('dashboard', 'perancang.dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" />Dashboard jabatan</a>
                    <a href="{{ route('perancang.index') }}" class="nav-link {{ request()->routeIs('perancang.index', 'perancang.show') ? 'active' : '' }}" @if (request()->routeIs('perancang.index', 'perancang.show')) aria-current="page" @endif><x-icon name="files" />Permohonan PKM</a>
                    @if ($staff->canPerformPkm('perancang.pkm.register'))<a href="{{ route('perancang.create') }}" class="nav-link {{ request()->routeIs('perancang.create') ? 'active' : '' }}" @if (request()->routeIs('perancang.create')) aria-current="page" @endif><x-icon name="file-plus-2" />Daftar PKM</a>@endif
                @endif
                @if (app(\App\Services\Tasks\OfficerTaskAccess::class)->types($staff) !== [])<a href="{{ route('tasks.index') }}" class="nav-link {{ $isTasks ? 'active' : '' }}" @if ($isTasks) aria-current="page" @endif><x-icon name="files" />Tugasan pegawai</a>@endif
                @if ($canOsc)<a href="{{ route('osc.meetings.index') }}" class="nav-link {{ $isOsc ? 'active' : '' }}" @if ($isOsc) aria-current="page" @endif><x-icon name="folder-open" />Daftar Mesyuarat</a>@endif
                <span class="nav-label nav-label-spaced">Bantuan</span>
                @if ($staff->canViewLibrary())<a href="{{ route('library.index') }}" class="nav-link {{ request()->routeIs('library.*') ? 'active' : '' }}" @if (request()->routeIs('library.*')) aria-current="page" @endif><x-icon name="files" />Perpustakaan jabatan</a>@endif
                <button class="nav-link" type="button" data-amira-open aria-controls="amira-panel" aria-expanded="false"><x-icon name="messages-square" />Panduan AMIRA</button>
            </nav>
            <div class="sidebar-note"><x-mark class="mark-reversed mark-small" /><div><strong>Persekitaran iNDECS</strong><small>Majlis Bandaraya Seberang Perai</small></div></div>
        </aside>
        <button type="button" class="sidebar-edge-toggle" data-sidebar-collapse aria-controls="sidebar" aria-expanded="true" aria-label="Sembunyi menu" title="Sembunyi menu"><x-icon name="chevron-left" /></button>
        <div class="page-shell">
            <header class="topbar">
                <button class="menu-button icon-button" type="button" data-menu-toggle aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar"><x-icon name="menu" /></button>
                <div class="topbar-context">@if ($isTasks)<x-mark class="mark-small" />@elseif ($isOsc)<x-eosc-icon compact />@else<x-department-icon :department="$workspaceDepartmentIcon" compact />@endif<div><strong>{{ $workspaceName }}</strong><span>{{ $workspaceDepartment }}</span></div></div>
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
