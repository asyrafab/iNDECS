# Panduan visual dan inventori media

> **Penyelarasan NADI — 9 November 2026:** [Hala tuju semasa](../../eosc/nadi-direction.md): **NADI > eOSC**. iNDECS mendaftarkan kertas di bawah eOSC untuk mesyuarat yang ditetapkan dan berkomunikasi dengan NADI; NADI ialah aplikasi yang digunakan semasa mesyuarat. Jenis mesyuarat lain boleh ditambah sebagai modul NADI kemudian. Nama jawatankuasa kekal Jawatankuasa OSC. Huraian lama yang meletakkan keseluruhan pengalaman mesyuarat dalam iNDECS ialah rujukan sejarah; pelaksanaan integrasi masih perlu direka.

## Arah visual

Format 16:9. Gaya Calm Intelligence: ruang lapang, struktur navy, satu aksen teal, latar cerah dan tipografi Google Sans. Teks rata kiri. Tiada gradient, glow, robot AI, ilustrasi dekoratif atau dashboard palsu. Tidak perlu foto: fokus pertemuan ini ialah konsep dan hubungan sistem.

Cadangan saiz akhir slaid: tajuk 32–36 pt, isi 22–26 pt, nota sasaran sekurang-kurangnya 14 pt. Kurangkan teks sebelum mengecilkan fon. Grafik ialah rujukan visual lengkap; jangan ulang teks penuh grafik di sisi imej yang sama. NotebookLM boleh membina semula susun atur dengan mesej yang sama.

Warna dan fon berasal daripada `public/css/tokens.css`, bukan palet baharu. Nilai eksport semasa: navy `#102a43`, aksen `#2c9da0`, teal teks `#14676b`, latar `#f7f9fb`, latar lembut `#e6f4f4`, permukaan `#ffffff`, teks `#172b3a`, teks sekunder `#596e80`, sempadan `#dce4ea`. Fail SVG membawa salinan token yang diperlukan untuk berdiri sendiri; PNG ialah eksport tetap untuk pemindahan. Penjana membaca nilai token repositori semasa dijalankan.

## Tujuh grafik utama

Setiap grafik mempunyai PNG **2400 × 1350** dan SVG **1600 × 900 viewBox**. SVG menyimpan bentuk dan teks yang boleh disunting; PNG menggunakan Google Sans yang tersedia pada komputer pembina. SVG memerlukan Google Sans pada komputer penerima untuk padanan tipografi yang tepat. Jangan ubah nisbah aspek.

| Slaid | Nama asas dalam media/ | Maksud |
|---|---|---|
| 1 | 01-penyelarasan | Risiko rekod jabatan terpisah dan konsep platform bersama |
| 2 | 02-dua-skop | Perbezaan penghantaran permohonan dalam talian dengan spatial |
| 3 | 03-konsep-indecs | Pemohon, iNDECS, empat jabatan dan fungsi eOSC di dalamnya |
| 4 | 04-skop-jabatan | Empat jabatan, kod permohonan dan perkhidmatan teras |
| 5 | 05-fungsi-pengguna | Fungsi mengikut pemohon, pegawai dan pengurusan/OSC |
| 6 | 06-ekosistem | Peranan iNDECS, AIRS, AMIRA, Esri dan eOSC |
| 7 | 07-aliran-sasaran | Enam peringkat dan sesi jabatan seterusnya |

`media/00-apercu-grafik.png` ialah helaian tinjauan semua grafik, bukan slaid kelapan. Gunakan tujuh imej berasingan untuk hasil akhir.

## Aset jenama

| Fail | Asal / penggunaan |
|---|---|
| media/mbsp-logo.svg | Salinan tanpa perubahan daripada public/img/mbsp-logo.svg |
| media/mbsp-logo.png | Eksport PNG telus daripada SVG yang sama; maksimum 1200 px |
| media/mbsp.png | Salinan tanpa perubahan daripada public/img/mbsp.png |
| media/indecs-mark.svg | Salinan tanpa perubahan daripada public/img/indecs-mark.svg |
| media/indecs-mark.png | Eksport PNG telus tanda iNDECS; maksimum 1200 px |
| media/indecs-mark-reversed.svg | Salinan aset asal untuk latar gelap |
| media/indecs-mark-reversed.png | Eksport PNG telus varian asal untuk latar gelap |

Lambang MBSP sentiasa mendahului tanda dan nama iNDECS, dipisahkan oleh garis ringkas jika disusun sebaris. Kekalkan warna, perkadaran dan geometri asal. Pilih satu versi lambang MBSP, bukan kedua-duanya serentak. Nama iNDECS boleh ditaip dalam Google Sans bersebelahan tanda. Jangan lukis semula lambang atau mereka logo komponen lain. Tiada fail fon disalin atau diagihkan dalam pek.

## Asal grafik

Tujuh diagram disediakan khusus untuk pek ini berdasarkan mesej yang dipersetujui dalam perbincangan dan rujukan dalam fail 04. Tiada foto stok, imej internet atau imej generatif digunakan. Diagram menerangkan konsep sasaran, bukan tangkapan skrin sistem atau bukti semua integrasi sudah beroperasi. Aset identiti datang daripada repositori MBSP/iNDECS dan disertakan untuk kegunaan pembentangan projek.

## Jana semula media

Daripada akar repositori:

```powershell
python docs/meeting/introduction/tools/build_media.py
```

Memerlukan PyMuPDF dan Google Sans yang dipasang. Penjana tidak membuat panggilan rangkaian. PNG dan SVG menggunakan koordinat kandungan sama. Semak semula susun atur selepas menukar teks atau token.

> Media SVG/PNG dan fail persembahan sedia ada mungkin masih memaparkan penjenamaan atau sempadan lama. Semak dan jana semula sebelum digunakan; teks semasa dan ADR-378 mengatasi media lama.
