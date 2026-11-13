# Fakta, istilah dan soal jawab sokongan

> **Penyelarasan NADI — 9 November 2026:** [Hala tuju semasa](../../eosc/nadi-direction.md): **NADI > eOSC**. iNDECS mendaftarkan kertas di bawah eOSC untuk mesyuarat yang ditetapkan dan berkomunikasi dengan NADI; NADI ialah aplikasi yang digunakan semasa mesyuarat. Jenis mesyuarat lain boleh ditambah sebagai modul NADI kemudian. Nama jawatankuasa kekal Jawatankuasa OSC. Huraian lama yang meletakkan keseluruhan pengalaman mesyuarat dalam iNDECS ialah rujukan sejarah; pelaksanaan integrasi masih perlu direka.

Tarikh rujukan: 6 November 2026. Sokongan penjanaan dan persediaan pembentang; bukan slaid tambahan.

## Asas dan batas fakta

| Pernyataan | Asas | Batas |
|---|---|---|
| Keperluan platform bersepadu merentas jabatan | Konteks pembentang dalam perbincangan ini | Jangan mendakwa inventori semasa telah diaudit atau semua jabatan tiada sistem |
| Sistem silo meninggalkan isu penyelarasan | Hujah reka bentuk integrasi | Risiko seperti rekod terpisah dan kemasukan berulang; tiada angka prestasi |
| Empat aplikasi jabatan dan kod | AGENTS.md | Skop sasaran; bukan semua modul siap |
| iNDECS rekod rasmi, AIRS semakan, spatial berasingan | ADR-352 dan kontrak integrasi | Pembahagian tanggungjawab sasaran |
| eOSC dalam iNDECS | AGENTS.md dan reka bentuk eOSC | Fungsi bersama, bukan jabatan kelima |
| Draf, muat naik dan pra-semak tempatan mempunyai pelaksanaan | Runbook portal/pra-semak | Bukan bukti penerimaan rasmi telah dibuka |
| Penyambung AIRS mempunyai kawalan bayaran/versi | Runbook AIRS | Bukan bukti semua peraturan jabatan aktif |
| Perbualan AMIRA dan panduan sandaran | ADR-369 dan runbook AMIRA | Bergantung pada konfigurasi; bukan kuasa bertindak sendiri |

## Istilah konsisten

- **Integrated Development Control**: konsep kawalan pemajuan bersepadu; ejaan Integrated, bukan Intergrated.
- **iNDECS**: kekalkan huruf kecil i dan huruf besar NDECS.
- **Penghantaran permohonan dalam talian**: istilah umum untuk keseluruhan urusan tanpa kertas, bukan nama produk Esri.
- **E-Plan Submission (Esri)**: penghantaran CAD/Excel dan pemprosesan data GIS. Elakkan “e-submission” tanpa penjelasan.
- **AIRS — AI Review Studio**: subsistem semakan; memiliki peraturan, bukti dan dapatan semakan.
- **AMIRA**: panduan/bantuan dalam kebenaran akses pengguna.
- **eOSC**: pendaftaran kertas dalam iNDECS dan modul mesyuarat pertama di bawah NADI.
- **Pra-semak percuma**: kelengkapan/kebolehgunaan fail, pemerolehan teks dan OCR tempatan secara terhad; bukan pematuhan teknikal atau kelulusan.
- **Satu pembangunan, beberapa permohonan**: rekod dihubungkan, bukan satu kelulusan yang menggantikan semua kuasa jabatan. Akses data kekal terkawal.
- **Kod**: Perancang—PKM; Bangunan—PB, PBK, PBS, PMT, TEL; Kejuruteraan—PKT, PKP; Landskap—PL. Jangan gunakan PB sebagai nama modul atau nama eBangunan.

## Status jika ditanya

Pembentangan menerangkan konsep sasaran. Pek ini tidak mengesahkan kesiapsiagaan operasi mana-mana persekitaran.

- Portal persediaan awam mempunyai draf persendirian, dokumen dan pra-semak tempatan mengikut runbook.
- Penerimaan rasmi awam, bayaran dan akses pemohon kepada rekod rasmi belum tersedia sebagai perjalanan menyeluruh dalam runbook portal.
- Penyambung/ruang kerja AIRS telah dibina; pengaktifan memerlukan bukti penerimaan/bayaran dan peraturan jabatan yang sah. Jangan mendakwa semua jenis pelan sudah disemak.
- AMIRA mempunyai kemas kini perbualan dalam ADR-369; bergantung pada model, backend, worker dan konfigurasi. Panduan asas tersedia jika gagal. Ini mengatasi pernyataan lama bahawa perbualan belum dilaksanakan.
- Kejuruteraan/Landskap dalam skop sasaran; jenis persediaan portal menunggu definisi pengambilan mengikut runbook.
- Kadar fi, caj pembetulan, tarikh pelancaran dan angka penjimatan tidak diputuskan dalam sumber pek ini.

## Soal jawab

### Adakah ini sama dengan E-Plan Submission?

Skopnya saling melengkapi. E-Plan Submission mengendalikan spatial. iNDECS mengurus rekod dan perjalanan permohonan, dengan AIRS menyokong semakan teknikal.

### Mengapa bukan sistem berasingan setiap jabatan?

Sistem jabatan membantu kerja dalaman. Platform bersama menghubungkan maklumat pembangunan, dokumen dan status serta berkongsi fungsi teras. Proses teknikal dan akses jabatan kekal khusus.

### Adakah AI menggantikan pegawai atau meluluskan pelan?

Tidak. AIRS menghasilkan dapatan dengan bukti untuk pertimbangan pegawai. AMIRA memberi bantuan. Keputusan dan perubahan status kekal melalui pihak berkuasa serta proses yang berkenaan.

### Adakah pemohon menghantar permohonan rasmi di dua portal?

Sasaran ialah satu perjalanan rasmi melalui iNDECS. AIRS ialah subsistem semakan. Langkah spatial dan penghubungan akaun diperincikan melalui integrasi; jangan menjanjikan satu log masuk semua sistem telah beroperasi.

### Adakah semua permohonan wajib ke OSC?

Laluan bergantung pada jenis permohonan dan proses yang disahkan jabatan. Diagram keseluruhan bukan arahan bahawa semua kes mesti ke OSC.

### Bila sistem digunakan sepenuhnya?

Pelaksanaan berperingkat. Sesi jabatan memperincikan keperluan, pengujian dan keutamaan sebelum pengesahan kesiapsiagaan operasi. Tiada tarikh pelancaran dijanjikan.

### Apa yang diperlukan daripada ketua jabatan sekarang?

Kefahaman bersama tentang konsep dan skop, serta penyertaan jabatan dalam sesi susulan proses dan keperluan.

## Rujukan dalaman

Laluan relatif kepada akar repositori. Tidak perlu memuat naik dokumen asal: pek ini sudah mengandungi fakta yang diperlukan.

1. AGENTS.md — skop, pemilikan sistem dan dasar.
2. docs/adr/ADR-352-indecs-airs-submission-and-precheck.md — perjalanan tunggal, pra-semak, bayaran dan semakan.
3. docs/integration/indecs-airs-contract.md — tanggungjawab dan gerbang pelaksanaan.
4. docs/bangunan/12-identity-and-integration-contracts.md — spatial dan kontrak sasaran.
5. docs/eosc/README.md — reka bentuk mesyuarat; status reka bentuk lama bukan pengesahan operasi.
6. docs/runbooks/public-portal.md — kemampuan persediaan dan batas penerimaan rasmi.
7. docs/runbooks/precheck-worker.md — pra-semak tempatan.
8. docs/runbooks/airs-integration.md — penyambung dan gerbang pengaktifan.
9. docs/adr/ADR-369-amira-conversation-on-local-qwen.md dan docs/runbooks/amira-chat.md — kemas kini AMIRA.
10. docs/DESIGN-DIRECTION.md dan public/css/tokens.css — arah visual.

Jika sumber lama bercanggah, utamakan ADR diterima dan kemas kini khusus yang terkini. Tiada bahan internet atau spesifikasi vendor baharu digunakan.

> Media SVG/PNG dan fail persembahan sedia ada mungkin masih memaparkan penjenamaan atau sempadan lama. Semak dan jana semula sebelum digunakan; teks semasa dan ADR-378 mengatasi media lama.
