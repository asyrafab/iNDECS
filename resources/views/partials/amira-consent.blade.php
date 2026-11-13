{{-- ADR-369: one-time agreement before AMIRA uses the PSP's information. Opened automatically on first visit. --}}
<dialog class="portal-dialog amira-consent" id="amira-consent" data-amira-consent aria-labelledby="amira-consent-title">
    <div class="portal-dialog-head"><span class="eyebrow">AMIRA · Pegawai Kaunter Maya</span><button class="icon-button" type="button" data-portal-close aria-label="Tutup persetujuan"><x-icon name="x" /></button></div>
    <h2 id="amira-consent-title">Persetujuan penggunaan maklumat oleh AMIRA</h2>
    <p>AMIRA ialah pembantu AI MBSP yang berjalan pada pelayan dalaman. Untuk membantu anda, AMIRA menggunakan maklumat berikut dalam perbualan dan analisis persediaan permohonan:</p>
    <ul>
        <li>Akaun anda: nama, emel, daerah dan status pengesahan emel.</li>
        <li>Draf anda: tajuk, alamat tapak, catatan, butiran projek, status semakan awal dan nama fail dokumen.</li>
        <li>Soalan yang anda tanya kepada AMIRA.</li>
    </ul>
    <p><strong>Had penggunaan.</strong> AMIRA tidak sekali-kali menggunakan kata laluan, nombor kad pengenalan, token log masuk, kandungan dokumen yang dimuat naik atau maklumat pengguna lain. Maklumat anda tidak dihantar kepada perkhidmatan AI luar.</p>
    <p>Perbualan disimpan selama {{ $retentionMonths }} bulan untuk audit, kemudian dipadam. AMIRA membantu; pegawai MBSP yang memutuskan permohonan anda.</p>
    <form method="POST" action="{{ route('portal.amira.consent') }}" class="amira-consent-actions">
        @csrf
        <button type="submit" class="button button-primary"><x-icon name="check" />Saya setuju</button>
        <button type="submit" class="button button-secondary" formmethod="dialog" formnovalidate>Tidak sekarang</button>
    </form>
</dialog>
