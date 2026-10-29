<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="iNDECS — Portal Perkhidmatan Pembangunan Majlis Bandaraya Seberang Perai. Panduan urusan pembangunan dan akses akaun awam serta kakitangan.">
    <title>iNDECS · Portal Perkhidmatan Pembangunan MBSP</title>
    <link rel="icon" href="{{ asset('img/indecs-mark.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="{{ asset('js/theme.js') }}"></script>
    <script src="{{ asset('js/landing.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
</head>
<body class="landing-page">
    <a class="skip-link" href="#main-content">Langkau ke kandungan</a>
    <div class="landing-introduction" aria-hidden="true"><div><x-mark class="mark-reversed mark-entry" /><strong>iNDECS</strong></div><p>Portal Perkhidmatan Pembangunan MBSP</p></div>
    <header class="public-header landing-header">
        <a href="{{ route('dashboard') }}" class="public-brand" aria-label="Laman utama iNDECS">
            <img src="{{ asset('img/mbsp.png') }}" alt="Majlis Bandaraya Seberang Perai" class="public-crest">
            <span class="public-wordmark"><x-mark class="mark-entry" /><span>iNDECS<small>Majlis Bandaraya Seberang Perai</small></span></span>
        </a>
        <nav class="landing-nav" aria-label="Navigasi utama">
            <a href="#services">Perkhidmatan</a><a href="#tracker">Semak permohonan</a><a href="#panduan">Panduan</a><a href="#bantuan">Bantuan</a>
        </nav>
        <div class="landing-account-actions"><a class="button button-secondary button-small" href="{{ route('portal.login') }}"><x-icon name="log-in" />Log masuk</a><a class="button button-primary button-small" href="{{ route('login') }}">Portal agensi <x-icon name="arrow-right" /></a></div>
    </header>
    <main id="main-content" tabindex="-1">
        <section class="landing-hero" id="tracker" aria-labelledby="landing-title">
            <svg class="landing-skyline" viewBox="0 0 1920 280" preserveAspectRatio="none" fill="none" aria-hidden="true" focusable="false"><path vector-effect="non-scaling-stroke" d="M0 250H60V210H90V175H125V230H145V140H185V110H205V140H220V250H240V160H275V200H300V90H320L325 55L330 90H345V250H360V180H395V130H430V165H450V250H475V120H510V195H535V80H555V65H575L580 35L585 65H600V150H630V220H655V140H690V175H720V105H755V250H780V160H820V120H850V190H880V70H910L925 95H950V180H985V250H1010V135H1045V90H1070L1075 45L1080 90H1100V155H1130V210H1160V125H1200V75H1225L1230 25L1235 75H1255V145H1290V250H1315V170H1350V115H1390V160H1420V85H1450L1470 110H1500V185H1530V250H1560V130H1600V65H1620L1625 30L1630 65H1650V120H1680V175H1715V140H1750V210H1785V155H1820V225H1860V250H1920" /></svg>
            <div class="landing-container landing-hero-content">
                <span class="eyebrow">Portal Perkhidmatan Pembangunan</span>
                <h1 id="landing-title">Urusan Pembangunan Anda,<br>Dalam Satu Tempat</h1>
                <p class="landing-lead">Kenali dan akses perkhidmatan pembangunan Majlis Bandaraya Seberang Perai dengan lebih mudah.</p>
                <div class="landing-tracker" aria-describedby="tracking-note">
                    <label for="tracking-reference"><x-icon name="search" />Semak status permohonan</label>
                    <input id="tracking-reference" type="text" placeholder="PBK/2026/00042" disabled aria-describedby="tracking-note">
                    <button class="button button-primary" type="button" disabled>Semak status</button>
                </div>
                <p class="landing-tracking-note" id="tracking-note">Penjejakan status awam belum tersedia. Penghantaran permohonan dan bayaran awam belum dibuka.</p>
                <a class="landing-hero-link" href="#services">Lihat semua perkhidmatan <x-icon name="arrow-right" /></a>
            </div>
        </section>
        <section class="landing-container landing-services" id="services" aria-labelledby="services-title">
            <div class="landing-section-mark" aria-hidden="true"></div>
            <h2 id="services-title">Apa yang anda ingin lakukan?</h2>
            <p class="landing-description">Pilih urusan anda untuk mendapatkan panduan dan perkhidmatan yang tepat.</p>
            @php
                $services = [
                    ['id' => 'ubah-suai', 'icon' => 'building-2', 'title' => 'Ubah suai kediaman', 'description' => 'Permohonan ubah suai, penambahan kecil dan kerja kediaman.', 'department' => 'iNDECS Bangunan', 'codes' => 'PBK', 'guidance' => 'Kenal pasti skop tambahan atau ubah suai, lokasi tapak dan dokumen pelan yang berkaitan.'],
                    ['id' => 'bangunan-baharu', 'icon' => 'building-2', 'title' => 'Bina bangunan baharu', 'description' => 'Permohonan pembinaan bangunan komersial, perindustrian dan kediaman.', 'department' => 'iNDECS Bangunan', 'codes' => 'PB', 'guidance' => 'Sediakan butiran cadangan pembangunan, tapak dan perunding untuk urusan pelan bangunan penuh.'],
                    ['id' => 'kebenaran-merancang', 'icon' => 'map', 'title' => 'Kebenaran merancang', 'description' => 'Urusan kebenaran merancang dan cadangan pembangunan.', 'department' => 'iNDECS Perancang', 'codes' => 'PKM', 'guidance' => 'Kenal pasti jenis cadangan, guna tanah, tapak dan lot yang terlibat untuk semakan perancangan.'],
                    ['id' => 'permit', 'icon' => 'file-text', 'title' => 'Kerja sementara & permit', 'description' => 'Bangunan sementara, bahan binaan, perancah dan lain-lain permit.', 'department' => 'iNDECS Bangunan', 'codes' => 'PBS · PMT · TEL', 'guidance' => 'Kenal pasti kategori permit, lokasi dan tempoh kerja atau penggunaan yang dicadangkan.'],
                    ['id' => 'kejuruteraan', 'icon' => 'settings-2', 'title' => 'Pelan kejuruteraan', 'description' => 'Urusan pelan kerja tanah dan pelan saliran pembangunan.', 'department' => 'iNDECS Kejuruteraan', 'codes' => 'PKT · PKP', 'guidance' => 'Modul ini masih dalam perancangan. Perkhidmatan dalam talian belum tersedia.'],
                    ['id' => 'landskap', 'icon' => 'map', 'title' => 'Kelulusan landskap', 'description' => 'Permohonan pelan landskap dan kerja landskap di tapak pembangunan.', 'department' => 'iNDECS Landskap', 'codes' => 'PL', 'guidance' => 'Modul ini masih dalam perancangan. Perkhidmatan dalam talian belum tersedia.'],
                ];
            @endphp
            <div class="landing-service-grid">
                @foreach ($services as $service)
                    <a class="landing-service-card" href="#panduan-{{ $service['id'] }}"><x-icon :name="$service['icon']" /><h3>{{ $service['title'] }}</h3><p>{{ $service['description'] }}</p><span class="landing-service-action">Lihat panduan <x-icon name="arrow-right" /></span></a>
                @endforeach
            </div>
            <div class="landing-callout"><x-icon name="circle-alert" /><div><strong>Tidak pasti perkhidmatan yang sesuai?</strong><p>Dapatkan panduan ringkas berdasarkan keperluan anda.</p></div><a class="button button-primary" href="#panduan">Cari perkhidmatan <x-icon name="arrow-right" /></a></div>
        </section>
        <section class="landing-container landing-guidance" id="panduan" aria-labelledby="guidance-title">
            <div><span class="eyebrow">Panduan perkhidmatan</span><h2 id="guidance-title">Kenali urusan anda</h2><p class="landing-description">Panduan awal mengikut bidang jabatan. Penghantaran awam belum dibuka; akaun awam boleh didaftarkan sekarang.</p><a class="text-link" href="{{ route('portal.register') }}">Daftar akaun awam <x-icon name="arrow-right" /></a></div>
            <div class="landing-questions">
                @foreach ($services as $service)
                    <details id="panduan-{{ $service['id'] }}"><summary>{{ $service['title'] }}</summary><p><strong>{{ $service['department'] }} · {{ $service['codes'] }}</strong></p><p>{{ $service['guidance'] }}</p>@if (! in_array($service['id'], ['kejuruteraan', 'landskap']))<p>Penghantaran permohonan melalui portal awam belum tersedia.</p>@endif</details>
                @endforeach
            </div>
        </section>
        <section class="landing-container landing-help" id="bantuan" aria-labelledby="help-title"><div><span class="eyebrow">Bantuan akses</span><h2 id="help-title">Akses yang sesuai untuk anda</h2><p class="landing-description">Orang awam dan perunding menggunakan akaun awam. Ruang kerja kakitangan tertakluk kepada kebenaran jabatan.</p></div><div class="landing-help-links"><a class="text-link" href="{{ route('portal.email') }}">Dapatkan pautan akses melalui emel <x-icon name="arrow-up-right" /></a><a class="text-link" href="{{ route('portal.forgot') }}">Tetapkan semula kata laluan awam <x-icon name="arrow-up-right" /></a><a class="text-link" href="{{ route('login') }}">Log masuk kakitangan MBSP <x-icon name="arrow-up-right" /></a></div></section>
    </main>
    <footer class="landing-footer"><div class="landing-container"><div class="landing-footer-brand"><x-mark class="mark-small" /><span><strong>iNDECS</strong><small>Sistem Bersepada Kawalan Pembangunan</small></span></div><div class="landing-footer-controls">@include('partials.theme-control')<button class="button button-ghost button-small" type="button" data-introduction-replay>Main semula pengenalan <x-icon name="arrow-right" /></button></div></div></footer>
</body>
</html>
