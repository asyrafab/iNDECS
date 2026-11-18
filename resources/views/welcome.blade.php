<!DOCTYPE html>
<html lang="ms" @if(session('skip_welcome_introduction')) data-skip-welcome-introduction @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="iNDECS — Portal Perkhidmatan Pembangunan Majlis Bandaraya Seberang Perai. Panduan urusan pembangunan dan akses akaun awam serta kakitangan.">
    <title>Selamat datang ke iNDECS · MBSP</title>
    <link rel="icon" href="{{ asset('img/indecs-mark.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="{{ asset('js/theme.js') }}"></script>
    <script>
        // Establish the entrance before the first paint, with a bounded recovery.
        if (!document.documentElement.hasAttribute('data-skip-welcome-introduction') && !@json(auth()->check() || ($errors->any() && old('_welcome_login') === '1')) && !matchMedia('(prefers-reduced-motion: reduce)').matches && document.documentElement.dataset.motion !== 'reduced') {
            document.documentElement.dataset.welcome = 'pending';
            window.indecsWelcomeRecovery = setTimeout(function () {
                delete document.documentElement.dataset.welcome;
                document.querySelector('[data-welcome-introduction]')?.setAttribute('hidden', '');
                document.querySelector('[data-welcome-home]')?.removeAttribute('inert');
                document.body?.classList.remove('welcome-is-introducing');
            }, 12000);
        }
    </script>
    <script src="{{ asset('js/landing.js') }}" defer></script>
    @auth<script src="{{ asset('js/application-launcher.js') }}" defer></script>@endauth
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
</head>
<body class="landing-page welcome-page">
    <a class="skip-link" href="#main-content">Langkau ke kandungan</a>
    <div class="welcome-entrance" data-welcome-introduction hidden aria-label="Pengenalan iNDECS">
        @include('partials.architectural-mark')
    </div>
    @include('partials.welcome-topography')
    <div class="welcome-home" data-welcome-home>

        <main class="welcome-main" id="main-content" tabindex="-1">
            <section class="welcome-copy" aria-labelledby="welcome-title">
        <header class="public-header welcome-header">
            <div class="public-brand" aria-label="Majlis Bandaraya Seberang Perai · iNDECS">
                <a href="https://www.mbsp.gov.my/" aria-label="Laman rasmi Majlis Bandaraya Seberang Perai"><img src="{{ asset('img/mbsp-logo.svg') }}" alt="Majlis Bandaraya Seberang Perai" class="public-crest"></a>
                <span class="public-wordmark"><x-mark class="mark-entry" /><span>iNDECS</span></span>
            </div>
        </header>
                <h1 id="welcome-title">Selamat datang<br>ke <strong class="welcome-title-brand">iNDECS</strong></h1>
                <p class="welcome-description">Portal permohonan kawalan pemajuan<br>Majlis Bandaraya Seberang Perai</p>
                <div class="welcome-actions">
                    <details class="welcome-choice">
                        <summary class="button button-primary"><x-icon name="search" />Semak Permohonan</summary>
                        <div class="welcome-choice-panel" role="region" aria-label="Semakan permohonan">
                            <button class="icon-button welcome-panel-close" type="button" aria-label="Tutup maklumat" data-welcome-close><x-icon name="x" /></button>
                            <h2>Semak permohonan</h2>
                            <p>Penjejakan status awam belum tersedia. Penghantaran permohonan dan bayaran awam belum dibuka.</p>
                            <p>Jika anda mempunyai akaun, log masuk untuk melihat urusan yang tersedia.</p>
                        </div>
                    </details>
                    <div class="welcome-choice">
                        @auth
                        <button class="button button-secondary" type="button" aria-haspopup="dialog" aria-controls="application-launcher" data-application-launcher-open><x-icon name="layout-dashboard" />Pilih aplikasi</button>
                        @else
                        <a class="button button-secondary" href="{{ route('portal.login') }}" aria-haspopup="dialog" aria-controls="welcome-login" data-welcome-login>Log Masuk <x-icon name="arrow-right" /></a>
                        <noscript><a class="text-link" href="{{ route('login') }}">Log masuk kakitangan</a></noscript>
                        @endauth
                    </div>
                    @auth
                    <form class="welcome-choice" method="POST" action="{{ auth()->user()->isPublicAccount() ? route('portal.logout') : route('logout') }}">@csrf<button class="button button-secondary" type="submit"><x-icon name="log-out" />Log keluar</button></form>
                    @endauth
                    <div class="welcome-choice welcome-learn">
                        <a href="{{ route('about') }}">Ketahui lebih lanjut <svg viewBox="0 0 100 30" aria-hidden="true"><path d="M3 23 Q46 3 91 12 M78 3 L94 12 L80 23" /></svg></a>
                    </div>
                </div>
            </section>
            @php
                $photos = [
                    ['image' => 'plan', 'label' => 'Pelan', 'alt' => 'Pelan seni bina dilihat dari atas', 'crop' => 'standard'],
                    ['image' => 'earthwork', 'label' => 'Kerja tanah', 'alt' => 'Pandangan udara kerja tanah dan jentera pembinaan', 'crop' => 'standard'],
                    ['image' => 'homes-klang', 'label' => 'Bangunan', 'alt' => 'Pandangan udara kediaman terancang di Klang, Malaysia', 'crop' => 'klang'],
                    ['image' => 'garden-clean', 'label' => 'Landskap', 'alt' => 'Rumput terurus, tanaman rapi dan laluan berturap', 'crop' => 'standard'],
                    ['image' => 'earthwork-aerial', 'label' => 'Pembangunan', 'alt' => 'Pandangan atas kawasan pembinaan dan jentera', 'crop' => 'standard'],
                    ['image' => 'garden-avenues', 'label' => 'Taman', 'alt' => 'Pandangan atas taman terancang dengan laluan simetri dan rumput terurus', 'crop' => 'standard'],
                    ['image' => 'garden-circular', 'label' => 'Landskap', 'alt' => 'Taman terurus dengan tempat duduk, laluan dan tanaman rapi', 'crop' => 'standard'],
                    ['image' => 'homes-aerial', 'label' => 'Kediaman', 'alt' => 'Bumbung kediaman dan rangkaian jalan dilihat dari atas', 'crop' => 'standard'],
                    ['image' => 'garden-curves', 'label' => 'Taman', 'alt' => 'Laluan melengkung di antara rumput taman yang terurus', 'crop' => 'standard'],
                    ['image' => 'homes-grid', 'label' => 'Perancangan', 'alt' => 'Susunan blok kediaman dan jalan dalam pandangan udara', 'crop' => 'standard'],
                ];
                $slides = array_map(fn ($photo) => [...$photo, 'src' => asset('img/welcome/'.$photo['image'].'.jpg')], $photos);
            @endphp
            <div class="welcome-gallery" aria-label="Slideshow pembangunan" aria-roledescription="karusel" data-welcome-carousel>
                @include('partials.welcome-garden-circle')
                @foreach (array_slice($slides, 0, 3) as $position => $photo)
                    <button type="button" class="welcome-photo welcome-photo-slot-{{ $position }}" aria-label="Pilih imej: {{ $photo['label'] }}" data-welcome-photo>
                        <span class="welcome-frame"><img class="photo-{{ $photo['crop'] }}" src="{{ $photo['src'] }}" alt="{{ $photo['alt'] }}" decoding="sync"></span>
                    </button>
                @endforeach
                <span class="sr-only" aria-live="polite" data-carousel-status></span>
                <script type="application/json" id="welcome-slides">@json($slides)</script>
            </div>
        </main>
    </div>
    @auth
        @include('partials.application-launcher')
    @else
        @include('partials.welcome-login')
    @endauth
</body>
</html>
