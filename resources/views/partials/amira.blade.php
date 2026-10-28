@php
    $assistantStaff = $assistantStaff ?? false;
    $assistantContext = $assistantContext ?? 'Portal Awam iNDECS';
    $assistantApplication = request()->route('application');
    $assistantLegacy = request()->route('legacyPermohonan');
    $assistantReference = $assistantApplication instanceof \App\Models\Application ? $assistantApplication->application_reference : ($assistantLegacy instanceof \App\Models\LegacyPermohonan ? $assistantLegacy->pmn_no_rujukan : null);
    $assistantStage = $assistantApplication instanceof \App\Models\Application ? $assistantApplication->current_stage->label() : null;
    $assistantGuide = request()->routeIs('*.create') ? 'Lengkapkan maklumat permohonan, pihak dan tapak. Semak medan wajib sebelum anda menghantar borang.' : (request()->routeIs('bangunan.documents.*') ? 'Semak senarai dokumen, revisi semasa dan catatan pegawai. Pengesahan serta peralihan dibuat oleh pegawai melalui borang yang tersedia.' : (request()->routeIs('bangunan.legacy.*') ? 'Halaman warisan memaparkan salinan baca sahaja. Gunakan carian dan penapis untuk mencari rekod; perubahan dibuat melalui proses jabatan.' : (request()->routeIs('bangunan.document-catalogue.*') ? 'Senarai semak perlu berdasarkan sumber yang disahkan jabatan. Semak rujukan dan keperluan sebelum mengesahkan katalog.' : ($assistantStaff ? 'Gunakan senarai permohonan untuk mencari rujukan. Buka rekod bagi melihat peringkat, dokumen dan tindakan yang tersedia untuk peranan anda.' : 'Gunakan kaedah akses yang dipaparkan. Jangan kongsikan kata laluan atau pautan akses emel. Penghantaran permohonan awam belum dibuka.'))));
@endphp
<aside class="amira-panel" id="amira-panel" aria-labelledby="amira-title" data-guide="{{ $assistantGuide }}" data-reference="{{ $assistantReference }}" data-stage="{{ $assistantStage }}" hidden>
    <div class="amira-panel-inner">
        <header class="amira-header"><div class="amira-identity"><x-icon name="messages-square" /><div><h2 id="amira-title">AMIRA</h2><p>Pembantu AI iNDECS</p></div></div><button type="button" class="icon-button" data-amira-close aria-label="Tutup AMIRA"><x-icon name="x" /></button></header>
        <p class="amira-availability"><span class="status-badge status-pending">Panduan sistem</span> Sambungan AIRS belum tersedia</p>
        <div class="amira-context"><x-icon name="panel-right" /><span>{{ $assistantReference ?: $assistantContext }}@if ($assistantStage)<small>{{ $assistantStage }}</small>@endif</span></div>
        <div class="amira-conversation">
            <p class="amira-greeting">Saya AMIRA. Saya boleh membantu anda memahami halaman ini dan mencari langkah seterusnya.</p>
            <div class="amira-prompts"><button type="button" class="button button-secondary button-small" data-amira-prompt="page">Panduan halaman ini</button><button type="button" class="button button-secondary button-small" data-amira-prompt="fields">Bantu isi borang</button><button type="button" class="button button-secondary button-small" data-amira-prompt="next">Langkah seterusnya</button></div>
            <div class="amira-user-message" data-amira-user hidden></div>
            <section class="amira-answer" data-amira-answer aria-live="polite" aria-atomic="true" hidden>
                <span class="eyebrow">Panduan tempatan</span><h3 data-amira-answer-title></h3><p data-amira-answer-copy></p>
                <div class="evidence"><x-icon name="file-text" /><a href="#main-content" data-amira-source>Sumber: Maklumat halaman ini</a></div>
                <p class="helper-text">Panduan berdasarkan halaman semasa. Penilaian AI dokumen melalui AIRS belum disambungkan.</p>
            </section>
        </div>
        <form class="amira-composer" data-amira-form><label for="amira-question">Tanya tentang halaman ini</label><div><input id="amira-question" name="question" maxlength="300" autocomplete="off" placeholder="Bagaimana saya boleh teruskan?"><button type="submit" class="button button-primary" aria-label="Hantar soalan kepada panduan AMIRA"><x-icon name="arrow-up" /></button></div></form>
        <footer class="amira-footer"><x-mark class="mark-small" /><span>AMIRA membantu. Pegawai memutuskan.</span></footer>
    </div>
</aside>
