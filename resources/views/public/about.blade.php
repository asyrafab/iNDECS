<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kenali iNDECS, platform kawalan pemajuan MBSP: jabatan, jenis permohonan, cara akses, fungsi dan ketersediaan perkhidmatan.">
    <title>Kenali iNDECS · MBSP</title>
    <link rel="icon" href="{{ asset('img/indecs-mark.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
</head>
<body class="public-page about-page">
    <a class="skip-link" href="#main-content">Langkau ke kandungan</a>
    <header class="public-header about-header">
        <div class="public-brand">
            <a href="https://www.mbsp.gov.my/" aria-label="Laman rasmi Majlis Bandaraya Seberang Perai"><img src="{{ asset('img/mbsp-logo.svg') }}" alt="Majlis Bandaraya Seberang Perai" class="public-crest"></a>
            <a href="{{ route('dashboard') }}" class="public-wordmark" aria-label="Laman utama iNDECS"><x-mark /><span>iNDECS<small>Sistem Bersepada Kawalan Pembangunan</small></span></a>
        </div>
        <div class="about-header-actions">
            @include('partials.theme-control')
            <a class="button button-secondary button-small" href="{{ route('dashboard') }}">Laman utama</a>
            @auth
                <a class="button button-primary button-small" href="{{ route('dashboard') }}">Ruang kerja saya <x-icon name="arrow-right" /></a>
            @else
                <a class="button button-primary button-small" href="{{ route('portal.login') }}">Log masuk <x-icon name="log-in" /></a>
            @endauth
        </div>
    </header>
    <main class="about-shell" id="main-content" tabindex="-1">
        <section class="about-introduction" aria-labelledby="about-title">
            <span class="eyebrow">Kenali platform anda</span>
            <h1 id="about-title">Kenali <span class="welcome-title-brand">iNDECS</span></h1>
            <p class="about-lead">Satu platform untuk menghubungkan urusan kawalan pemajuan Majlis Bandaraya Seberang Perai.</p>
            <p>iNDECS menghimpunkan maklumat permohonan, dokumen, lokasi tapak dan urusan jabatan dalam satu persekitaran. Setiap jabatan mengekalkan bidang tugas, keperluan semakan dan kuasa keputusannya.</p>
            <div class="about-availability-note"><x-icon name="circle-alert" /><p><strong>Akses akaun awam tersedia.</strong> Penghantaran permohonan, bayaran dan penjejakan status melalui portal awam belum dibuka.</p></div>
        </section>
        <div class="about-layout">
            <nav class="about-contents" aria-label="Kandungan halaman">
                <strong>Dalam halaman ini</strong>
                <a href="#tujuan">Tujuan iNDECS</a>
                <a href="#pengguna">Siapa yang menggunakan?</a>
                <a href="#jabatan">Jabatan & permohonan</a>
                <a href="#cara-kerja">Cara sistem berfungsi</a>
                <a href="#ketersediaan">Ketersediaan semasa</a>
                <a href="#ekosistem">Sistem yang bekerjasama</a>
                <a href="#identiti">Makna identiti iNDECS</a>
                <a href="#akses">Mula menggunakan</a>
                <a href="#soalan">Soalan lazim</a>
            </nav>
            <div class="about-content">
                <section class="about-section" id="tujuan" aria-labelledby="purpose-title">
                    <span class="eyebrow">01 · Tujuan</span>
                    <h2 id="purpose-title">Maklumat yang berkaitan, dalam satu tempat</h2>
                    <p>Urusan pembangunan boleh melibatkan beberapa jabatan, dokumen dan lokasi yang sama. iNDECS dibangunkan untuk mengurangkan pengisian berulang, membantu penyelarasan antara jabatan dan memudahkan rujukan kepada rekod yang berkaitan.</p>
                    <ul class="about-list">
                        <li><strong>Rekod yang tersusun.</strong> Butiran permohonan, pemohon, perunding, tapak dan lot boleh dirujuk dalam konteks yang sama.</li>
                        <li><strong>Proses mengikut urusan.</strong> Borang, keperluan dokumen dan aliran semakan disesuaikan dengan jenis permohonan.</li>
                        <li><strong>Sejarah yang boleh dijejaki.</strong> Pergerakan, semakan dokumen dan tindakan pegawai direkodkan untuk rujukan semula.</li>
                        <li><strong>Penyelarasan pembangunan.</strong> Permohonan berkaitan boleh dihubungkan tanpa memaksa semua urusan melalui satu turutan yang sama.</li>
                    </ul>
                </section>
                <section class="about-section" id="pengguna" aria-labelledby="users-title">
                    <span class="eyebrow">02 · Pengguna</span>
                    <h2 id="users-title">Akses yang sesuai dengan peranan anda</h2>
                    <div class="about-card-grid">
                        <article class="content-card"><h3>Orang awam & pemohon</h3><p>Mendaftar dan mengesahkan akaun awam, log masuk atau mendapatkan pautan akses melalui emel. Sediakan, simpan dan sambung draf maklumat asas dalam Portal Awam. Fungsi penghantaran rasmi dan penjejakan awam akan dibuka secara berperingkat.</p></article>
                        <article class="content-card"><h3>Perunding / PSP</h3><p>Menggunakan akses awam bagi urusan pemohon dan perunding. Dokumen serta keperluan teknikal tetap bergantung pada jenis permohonan dan panduan jabatan yang berkenaan.</p></article>
                        <article class="content-card"><h3>Kakitangan MBSP</h3><p>Menggunakan ruang kerja dalaman mengikut jabatan dan kebenaran akaun. Pendaftaran awam tidak memberikan akses kakitangan atau kuasa membuat keputusan.</p></article>
                    </div>
                </section>
                <section class="about-section" id="jabatan" aria-labelledby="departments-title">
                    <span class="eyebrow">03 · Skop jabatan</span>
                    <h2 id="departments-title">Empat jabatan, satu platform bersama</h2>
                    <p>Skop berikut menerangkan keluarga permohonan yang disokong dalam reka bentuk iNDECS. Fungsi setiap modul dibangunkan secara berperingkat; senarai ini bukan pengumuman bahawa semua perkhidmatan awam telah dibuka.</p>
                    <div class="about-card-grid">
                        <article class="content-card"><span class="eyebrow">Jabatan Perancang Bandar</span><h3>iNDECS Perancang</h3><p><strong>PKM</strong> · Permohonan Kebenaran Merancang.</p><p>Maklumat cadangan pembangunan, guna tanah, tapak, lot dan semakan perancangan.</p></article>
                        <article class="content-card"><span class="eyebrow">Jabatan Bangunan</span><h3>iNDECS Bangunan</h3><p><strong>PB · PBK · PBS · PMT · TEL</strong></p><ul class="about-list"><li>PB — Pelan Bangunan.</li><li>PBK — Pelan Tambahan / Ubahsuai Kecil.</li><li>PBS — Pelan Bangunan Sementara dan permit sementara berkaitan.</li><li>PMT — Urusan permit seperti bahan binaan, perancah, meruntuh dan tukar guna.</li><li>TEL — Struktur telekomunikasi dan tenaga boleh baharu.</li></ul></article>
                        <article class="content-card"><span class="eyebrow">Jabatan Kejuruteraan</span><h3>iNDECS Kejuruteraan</h3><p><strong>PKT · PKP</strong> · Pelan Kerja Tanah dan pelan saliran / parit.</p><p>Cadangan kerja tanah, aras tapak, saliran dan keperluan kejuruteraan pembangunan.</p></article>
                        <article class="content-card"><span class="eyebrow">Jabatan Landskap</span><h3>iNDECS Landskap</h3><p><strong>PL</strong> · Pelan Landskap.</p><p>Cadangan tanaman, kawasan lapang, landskap lembut dan kejur serta keperluan penyelenggaraan berkaitan.</p></article>
                    </div>
                </section>
                <section class="about-section" id="cara-kerja" aria-labelledby="workflow-title">
                    <span class="eyebrow">04 · Cara kerja</span>
                    <h2 id="workflow-title">Mengikut keperluan sebenar permohonan</h2>
                    <div class="content-card">
                        <h3>Permohonan boleh berdiri sendiri atau berkaitan</h3>
                        <p>Satu urusan tidak semestinya memerlukan semua jenis pelan. Permohonan bagi pembangunan yang sama boleh dihubungkan untuk rujukan bersama, manakala proses serta penilaian jabatan kekal berasingan.</p>
                        <h3>Dokumen dan lokasi memberi konteks kepada semakan</h3>
                        <p>Dalam ruang kerja yang dibenarkan, pegawai boleh merujuk maklumat tapak dan lot, peta, dokumen serta versi dokumen. Semakan kelengkapan dan tindakan pembetulan membantu memastikan rekod yang dinilai dapat dikenal pasti.</p>
                        <h3>Aliran dan keputusan mengikut jabatan</h3>
                        <p>Pendaftaran, semakan, penilaian dan keputusan bergantung pada kategori urusan. iNDECS tidak menetapkan satu turutan wajib yang seragam untuk semua pembangunan. Fi, mesyuarat dan keperluan kelulusan tertakluk kepada proses yang berkenaan.</p>
                        <h3>Pegawai membuat keputusan</h3>
                        <p>Maklumat sistem dan bantuan AI menyokong semakan. Cadangan AI bukan kelulusan dan tidak menukar status permohonan secara automatik. Keputusan kekal pada pegawai atau pihak berkuasa yang diberi kuasa.</p>
                    </div>
                </section>
                <section class="about-section" id="ketersediaan" aria-labelledby="availability-title">
                    <span class="eyebrow">05 · Ketersediaan semasa</span>
                    <h2 id="availability-title">Apa yang boleh digunakan sekarang?</h2>
                    <div class="about-card-grid">
                        <article class="content-card"><span class="status-badge status-info">Akses tersedia</span><h3>Akaun & akses</h3><ul class="about-list"><li>Pendaftaran dan pengesahan emel akaun awam.</li><li>Log masuk awam dan pemulihan kata laluan.</li><li>Pautan akses sekali guna melalui emel.</li><li>Akses kakitangan bagi akaun yang telah dibenarkan.</li></ul></article>
                        <article class="content-card"><span class="status-badge status-info">Ruang kerja dalaman</span><h3>Pemprosesan pegawai</h3><p>Fungsi pendaftaran, semakan dokumen dan rekod tindakan untuk Perancang dan Bangunan, serta urusan mesyuarat eOSC+, tersedia mengikut peranan yang dibenarkan. Modul jabatan terus dikembangkan secara berperingkat.</p></article>
                        <article class="content-card"><span class="status-badge status-pending">Belum dibuka</span><h3>Urusan awam seterusnya</h3><p>Penghantaran permohonan, bayaran dan penjejakan status melalui portal awam belum tersedia. Pendaftaran akaun tidak bermaksud fungsi tersebut telah dibuka.</p></article>
                        <article class="content-card"><span class="status-badge status-pending">Integrasi berperingkat</span><h3>Sambungan sistem</h3><p>Integrasi semakan AIRS dan penyerahan spatial E-Plan merupakan arah pembangunan. Sambungan tersebut belum ditawarkan sebagai perkhidmatan awam yang aktif di portal ini.</p></article>
                    </div>
                </section>
                <section class="about-section" id="ekosistem" aria-labelledby="ecosystem-title">
                    <span class="eyebrow">06 · Ekosistem</span>
                    <h2 id="ecosystem-title">Peranan yang jelas untuk setiap sistem</h2>
                    <dl class="about-definitions">
                        <div><dt>iNDECS</dt><dd>Platform utama dan pemegang rekod permohonan. Menyatukan konteks urusan serta akses mengikut peranan pengguna.</dd></div>
                        <div><dt>AIRS · AI Review Studio</dt><dd>Subsistem semakan pelan yang dirancang untuk mengembalikan dapatan berserta rujukan bukti. AIRS membantu semakan; rekod rasmi permohonan dan keputusan kekal dalam iNDECS.</dd></div>
                        <div><dt>E-Plan Submission System</dt><dd>Subsistem penyerahan spatial yang dirancang untuk menukar data pelan kepada maklumat GIS. iNDECS merujuk maklumat dan status penyerahan tersebut apabila sambungan tersedia.</dd></div>
                        <div><dt>eOSC+</dt><dd>Keupayaan mesyuarat OSC dalam iNDECS: agenda, konteks tapak pada peta dan rekod keputusan, melalui ruang kerja pegawai yang dibenarkan.</dd></div>
                        <div><dt>AMIRA</dt><dd>Pembantu panduan halaman iNDECS. Pada peringkat semasa, panduan adalah setempat dan tidak menjalankan semakan rasmi AIRS atau membuat keputusan bagi pihak pegawai.</dd></div>
                    </dl>
                </section>
                <section class="about-section" id="identiti" aria-labelledby="identity-title">
                    <span class="eyebrow">07 · Identiti</span>
                    <h2 id="identity-title">Tiga lapisan, satu hubungan pembangunan</h2>
                    <div class="content-card about-identity"><x-mark /><div><p>Logo iNDECS mengambil bentuk bumbung, dinding dan jejak bangunan dari pandangan atas. Tiga segi empat yang beranjak merangkum hubungan antara pelan, bangunan dan tapaknya.</p><p>Ia turut mencerminkan permohonan yang berdiri sendiri, pasangan permohonan berkaitan dan kelompok urusan bagi satu pembangunan.</p></div></div>
                </section>
                <section class="about-section" id="akses" aria-labelledby="access-title">
                    <span class="eyebrow">08 · Akses</span>
                    <h2 id="access-title">Mulakan dengan akaun yang sesuai</h2>
                    <div class="about-card-grid">
                        <article class="content-card"><h3>Orang awam & perunding</h3><p>Daftar menggunakan emel yang boleh diakses dan lengkapkan pengesahan emel. Anda juga boleh mendapatkan pautan akses emel sekali guna.</p><div class="about-links"><a class="button button-primary" href="{{ route('portal.register') }}">Daftar akaun awam <x-icon name="arrow-right" /></a><a class="text-link" href="{{ route('portal.login') }}">Log masuk awam</a><a class="text-link" href="{{ route('portal.email') }}">Dapatkan pautan akses emel</a><a class="text-link" href="{{ route('portal.forgot') }}">Lupa kata laluan?</a></div></article>
                        <article class="content-card"><h3>Kakitangan MBSP</h3><p>Gunakan akses kakitangan yang telah dibenarkan. Hubungi pentadbir akaun MBSP / iNDECS untuk pemulihan kelayakan atau permohonan akses jabatan.</p><div class="about-links"><a class="button button-secondary" href="{{ route('login') }}">Log masuk kakitangan <x-icon name="log-in" /></a><a class="text-link" href="https://www.mbsp.gov.my/">Laman rasmi MBSP <x-icon name="arrow-up-right" /></a></div></article>
                    </div>
                </section>
                <section class="about-section" id="soalan" aria-labelledby="faq-title">
                    <span class="eyebrow">09 · Soalan lazim</span>
                    <h2 id="faq-title">Perkara yang mungkin anda ingin tahu</h2>
                    <div class="about-questions">
                        <details><summary>Bolehkah saya menghantar permohonan atau membuat bayaran sekarang?</summary><p>Belum. Penghantaran permohonan dan bayaran melalui portal awam belum dibuka. Anda boleh menyediakan akaun awam dan mengesahkan emel terlebih dahulu.</p></details>
                        <details><summary>Mengapa Semak Permohonan belum memaparkan status?</summary><p>Penjejakan status awam belum disambungkan. Pilihan tersebut memaklumkan ketersediaan semasa dan tidak menjalankan carian rekod permohonan awam.</p></details>
                        <details><summary>Adakah semua jenis pelan mesti dikemukakan mengikut satu turutan?</summary><p>Aliran bergantung pada jenis cadangan dan urusan jabatan. Sistem membenarkan permohonan berdiri sendiri atau dihubungkan apabila berkaitan. Rujuk panduan jabatan untuk menentukan keperluan bagi cadangan anda.</p></details>
                        <details><summary>Adakah akaun awam memberikan akses pegawai?</summary><p>Tidak. Akses kakitangan dan jabatan memerlukan kebenaran yang berasingan. Akaun awam tidak boleh digunakan untuk memasuki ruang kerja pegawai.</p></details>
                        <details><summary>Adakah dapatan AI bermaksud permohonan telah diluluskan?</summary><p>Tidak. AI hanya menyokong semakan. Kelulusan atau keputusan rasmi mesti dibuat oleh pegawai atau pihak berkuasa yang diberi kuasa.</p></details>
                        <details><summary>Di mana saya boleh mendapatkan maklumat lanjut tentang urusan MBSP?</summary><p>Rujuk <a class="text-link" href="https://www.mbsp.gov.my/">laman rasmi MBSP</a> untuk saluran perhubungan dan maklumat jabatan. Keperluan dokumen, fi dan tempoh urusan perlu dirujuk kepada panduan rasmi yang berkenaan.</p></details>
                    </div>
                </section>
                <footer class="workspace-footer about-footer"><x-mark class="mark-small" /><span>iNDECS · Majlis Bandaraya Seberang Perai</span><a class="text-link" href="{{ route('dashboard') }}">Kembali ke laman utama</a></footer>
            </div>
        </div>
    </main>
</body>
</html>
