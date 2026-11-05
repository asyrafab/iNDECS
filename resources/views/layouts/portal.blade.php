<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Portal Awam') · iNDECS</title>
    <link rel="icon" href="{{ asset('img/indecs-mark.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
    <script src="{{ asset('js/indecs.js') }}" defer></script>
    <script src="{{ asset('js/portal.js') }}" defer></script>
    <script src="{{ asset('js/amira-bubble.js') }}" defer></script>
</head>
<body class="portal-page">
    <a class="skip-link" href="#main-content">Langkau ke kandungan</a>
    <header class="portal-header">
        <a class="portal-brand" href="{{ route('portal.home') }}"><x-mark /><span><strong>iNDECS</strong><small>Portal Awam · MBSP</small></span></a>
        <div class="portal-header-tools">
            <span class="portal-user-name">{{ auth()->user()->name }}</span>
            <details class="portal-settings" data-portal-settings>
                <summary class="icon-button" aria-label="Tetapan pengguna" title="Tetapan pengguna"><x-icon name="settings" /></summary>
                <div class="portal-settings-panel">
                    <strong>Tetapan &amp; bantuan</strong>
                    <a href="{{ route('portal.account') }}"><x-icon name="settings-2" />Akaun saya</a>
                    <a href="{{ route('portal.home') }}"><x-icon name="files" />Fail saya</a>
                    <a href="{{ route('about') }}"><x-icon name="circle-dot" />Tentang iNDECS</a>
                    <a href="#portal-helpdesk" data-portal-open="portal-helpdesk"><x-icon name="messages-square" />Helpdesk</a>
                    @hasSection('counter')
                        <button type="button" data-amira-sheet-open aria-controls="amira-counter" aria-expanded="false"><x-icon name="messages-square" />Panduan AMIRA</button>
                    @else
                        <button type="button" data-amira-open aria-controls="amira-panel" aria-expanded="false"><x-icon name="messages-square" />Panduan AMIRA</button>
                    @endif
                    <div class="portal-settings-appearance"><span>Paparan</span>@include('partials.theme-control')</div>
                    <form method="POST" action="{{ route('portal.logout') }}">@csrf<button type="submit"><x-icon name="log-out" />Log keluar</button></form>
                </div>
            </details>
        </div>
    </header>
    <main class="portal-main" id="main-content" tabindex="-1">
        @if(! request()->routeIs('portal.home'))<a class="back-link" href="{{ route('portal.home') }}"><x-icon name="arrow-left" />Halaman utama portal</a>@endif
        @if(session('status'))<div class="alert alert-success" role="status"><x-icon name="circle-check" /><div>{{ session('status') }}</div></div>@endif
        @if($errors->any())<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div><strong>Sila semak maklumat anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
        @yield('content')
    </main>
    <footer class="portal-footer"><span>Majlis Bandaraya Seberang Perai</span><span>iNDECS · Satu platform, pelbagai permohonan pembangunan</span></footer>
    <dialog class="portal-dialog" id="portal-helpdesk" aria-labelledby="portal-helpdesk-title">
        <div class="portal-dialog-head"><span class="eyebrow">Bantuan pengguna</span><button class="icon-button" type="button" data-portal-close aria-label="Tutup helpdesk"><x-icon name="x" /></button></div>
        <h2 id="portal-helpdesk-title">Bagaimana kami boleh membantu?</h2>
        <p>Penghantaran tiket helpdesk belum tersedia. Anda boleh merujuk maklumat sistem atau menggunakan panduan tempatan AMIRA.</p>
        <a class="button button-secondary" href="{{ route('about') }}">Maklumat &amp; soalan lazim<x-icon name="arrow-right" /></a>
    </dialog>
    <noscript><style>.portal-dialog { display: block; position: static; width: auto; margin: var(--space-6); } [data-portal-close] { display: none; }</style></noscript>
    @hasSection('counter')<noscript><style>@media (max-width: 900px) { .counter-amira { display: flex; position: static; border-radius: var(--radius-lg); } .amira-counter-bar { display: none; } }</style></noscript>@endif
    {{-- Kaunter Maya pages render AMIRA inline (x-portal-counter); others keep the drawer. --}}
    @unless(View::hasSection('counter'))
    @include('partials.amira', ['assistantContext' => 'Portal Awam · Persediaan permohonan', 'portalGuide' => 'Pilih Permohonan baru untuk memilih jenis dan menyediakan draf. Semak Permohonan memaparkan ketersediaan rekod serta draf anda yang belum dihantar. Tetapan pengguna menyediakan Akaun saya, bantuan dan paparan. Penghantaran rasmi, bayaran dan semakan awal dokumen belum dibuka.'])
    @endunless
</body>
</html>