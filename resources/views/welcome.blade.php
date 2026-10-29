<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kenali iNDECS, Sistem Bersepada Kawalan Pembangunan Majlis Bandaraya Seberang Perai. Akses akaun awam dan ruang kerja kakitangan MBSP.">
    <title>iNDECS · Sistem Bersepada Kawalan Pembangunan</title>
    <link rel="icon" href="{{ asset('img/indecs-mark.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
</head>
<body class="landing-page">
    <a class="skip-link" href="#main-content">Langkau ke kandungan</a>
    <header class="public-header landing-header">
        <a href="{{ route('dashboard') }}" class="public-brand" aria-label="Laman utama iNDECS">
            <img src="{{ asset('img/mbsp.png') }}" alt="Majlis Bandaraya Seberang Perai" class="public-crest">
            <span class="public-wordmark"><x-mark /><span>iNDECS<small>Kawalan Pembangunan</small></span></span>
        </a>
        <nav class="landing-nav" aria-label="Navigasi utama">
            <a href="#jabatan">Jabatan</a><a href="#panduan">Panduan akses</a><a href="#soalan">Soalan lazim</a>
        </nav>
        <div class="public-header-actions">@include('partials.theme-control')</div>
    </header>
    <main id="main-content" tabindex="-1">
        <section class="landing-hero landing-container" aria-labelledby="landing-title">
            <div class="landing-intro">
                <span class="eyebrow">Majlis Bandaraya Seberang Perai</span>
                <h1 id="landing-title">Urus pembangunan.<br><span>Dengan lebih teratur.</span></h1>
                <p class="landing-lead">iNDECS menghimpunkan urusan kawalan pembangunan MBSP dalam satu platform bersama.</p>
                <p class="landing-description">Sistem Bersepada Kawalan Pembangunan menghubungkan rekod permohonan, dokumen dan semakan jabatan untuk menyokong urusan yang jelas dan boleh dijejaki.</p>
                <div class="action-links">
                    <a class="button button-primary button-large" href="{{ route('portal.login') }}">Akses portal awam <x-icon name="arrow-right" /></a>
                    <a class="button button-secondary button-large" href="{{ route('login') }}"><x-icon name="log-in" />Kakitangan MBSP</a>
                </div>
                <a class="text-link landing-register" href="{{ route('portal.register') }}">Pengguna baharu? Daftar akaun <x-icon name="arrow-up-right" /></a>
                <div class="landing-signature"><x-mark class="mark-small" /><span>Satu rekod bersama. Semakan mengikut jabatan.</span></div>
            </div>
            <aside class="landing-access" aria-labelledby="access-title">
                <div class="landing-access-heading"><span class="eyebrow">Mulakan di sini</span><h2 id="access-title">Pilih akses anda</h2><p>Ruang yang sesuai untuk setiap urusan.</p></div>
                <a class="landing-access-link" href="{{ route('portal.login') }}"><span class="landing-icon"><x-icon name="folder-open" /></span><span><strong>Orang awam &amp; perunding</strong><small>Log masuk atau urus akaun awam anda.</small></span><x-icon name="arrow-up-right" /></a>
                <a class="landing-access-link" href="{{ route('portal.email') }}"><span class="landing-icon"><x-icon name="file-text" /></span><span><strong>Akses melalui emel</strong><small>Terima pautan untuk masuk tanpa kata laluan.</small></span><x-icon name="arrow-up-right" /></a>
                <a class="landing-access-link" href="{{ route('login') }}"><span class="landing-icon"><x-icon name="layout-dashboard" /></span><span><strong>Kakitangan MBSP</strong><small>Masuk ke ruang kerja jabatan yang dibenarkan.</small></span><x-icon name="arrow-up-right" /></a>
                <div class="landing-access-note"><x-icon name="circle-alert" /><p>Akses akaun awam tersedia. Penghantaran permohonan, bayaran dan penjejakan awam belum dibuka.</p></div>
            </aside>
        </section>
        <section class="landing-departments" id="jabatan" aria-labelledby="departments-title">
            <div class="landing-container">
                <div class="landing-section-heading"><div><span class="eyebrow">Kenali iNDECS</span><h2 id="departments-title">Empat jabatan. Satu platform.</h2></div><p>Permohonan mengikut bidang, dengan rekod dan dokumen yang tersusun.</p></div>
                <div class="landing-department-grid">
                    <article class="landing-department"><x-icon name="building-2" /><span class="status-badge status-info">Ruang kerja kakitangan</span><h3>iNDECS Bangunan</h3><p>Pelan bangunan, tambahan dan ubah suai kecil, bangunan sementara, permit serta struktur telekomunikasi.</p><span class="landing-codes">PB · PBK · PBS · PMT · TEL</span></article>
                    <article class="landing-department"><x-icon name="map" /><span class="status-badge status-info">Ruang kerja kakitangan</span><h3>iNDECS Perancang</h3><p>Pendaftaran dan semakan kelengkapan permohonan kebenaran merancang.</p><span class="landing-codes">PKM</span></article>
                    <article class="landing-department"><x-icon name="settings-2" /><span class="status-badge status-pending">Dalam perancangan</span><h3>iNDECS Kejuruteraan</h3><p>Urusan pelan kerja tanah dan pelan saliran dalam pengembangan platform seterusnya.</p><span class="landing-codes">PKT · PKP</span></article>
                    <article class="landing-department"><x-icon name="map" /><span class="status-badge status-pending">Dalam perancangan</span><h3>iNDECS Landskap</h3><p>Urusan pelan landskap dan keperluan landskap pembangunan dalam pengembangan seterusnya.</p><span class="landing-codes">PL</span></article>
                </div>
            </div>
        </section>
        <section class="landing-container landing-guide" id="panduan" aria-labelledby="guide-title">
            <div><span class="eyebrow">Panduan akses</span><h2 id="guide-title">Bersedia untuk bermula?</h2><p class="landing-description">Pilih laluan mengikut peranan anda. Akses jabatan untuk kakitangan tertakluk kepada kebenaran akaun.</p><a class="text-link" href="{{ route('portal.register') }}">Daftar akaun awam <x-icon name="arrow-right" /></a></div>
            <ol class="landing-steps">
                <li><span class="landing-step-number" aria-hidden="true">01</span><div><h3>Pilih jenis akses</h3><p>Gunakan portal awam untuk pemohon dan perunding, atau akses kakitangan untuk pegawai MBSP.</p></div></li>
                <li><span class="landing-step-number" aria-hidden="true">02</span><div><h3>Log masuk atau daftar</h3><p>Daftar menggunakan emel anda. Anda juga boleh meminta pautan akses melalui emel tanpa kata laluan.</p></div></li>
                <li><span class="landing-step-number" aria-hidden="true">03</span><div><h3>Semak akaun anda</h3><p>Ikuti arahan pengesahan emel untuk akaun baharu. Kakitangan boleh meneruskan urusan melalui ruang kerja yang dibenarkan.</p></div></li>
            </ol>
        </section>
        <section class="landing-container landing-faq" id="soalan" aria-labelledby="faq-title">
            <div><span class="eyebrow">Soalan lazim</span><h2 id="faq-title">Perkara yang perlu diketahui</h2></div>
            <div class="landing-questions">
                <details><summary>Siapakah yang boleh menggunakan iNDECS?</summary><p>Orang awam dan perunding boleh mengakses akaun awam. Kakitangan MBSP menggunakan akses kakitangan, dengan ruang kerja ditentukan oleh kebenaran jabatan masing-masing.</p></details>
                <details><summary>Bolehkah saya menghantar permohonan sekarang?</summary><p>Penghantaran permohonan, bayaran dan penjejakan status melalui portal awam belum tersedia. Buat masa ini, portal awam menyediakan pendaftaran dan akses akaun.</p></details>
                <details><summary>Bagaimana jika saya terlupa kata laluan?</summary><p>Gunakan <a class="text-link" href="{{ route('portal.forgot') }}">tetapan semula kata laluan</a> untuk akaun awam, atau <a class="text-link" href="{{ route('portal.email') }}">minta pautan akses melalui emel</a>. Kakitangan perlu menggunakan kelayakan akaun yang telah dibenarkan oleh MBSP.</p></details>
                <details><summary>Adakah semua jabatan sudah tersedia?</summary><p>Ruang kerja kakitangan Bangunan dan Perancang tersedia mengikut kebenaran akaun. Kejuruteraan dan Landskap masih dalam perancangan.</p></details>
            </div>
        </section>
    </main>
    <footer class="landing-footer"><div class="landing-container"><div class="landing-footer-brand"><x-mark class="mark-small" /><span><strong>iNDECS</strong><small>Sistem Bersepada Kawalan Pembangunan</small></span></div><span>Majlis Bandaraya Seberang Perai</span><a class="text-link" href="#main-content">Kembali ke atas <x-icon name="arrow-up-right" /></a></div></footer>
</body>
</html>
