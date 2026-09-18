# iNDECS — Design direction

Companion to AGENTS.md. This file is the specification a coding agent follows for
naming, structure, components and screens. Updated 2026-09-18 to the Calm Intelligence
direction shared with AIRS and to the Bangunan-led programme in `docs/bangunan/`.

## 1. Naming (System A)

| Application | Full name | Menu | Route prefix | Table prefix | Type codes | Reference format |
|---|---|---|---|---|---|---|
| Perancang | iNDECS Perancang | Perancang | `/perancang` | `APP_PKM_` | PKM | `PKM/2026/00125` |
| Bangunan | iNDECS Bangunan | Bangunan | `/bangunan` | `APP_PB_` | PB, PBK, PBS, PMT, TEL | `PB/2026/00310`, `PBK/2026/00042`, `TEL/2026/00007` |
| Kejuruteraan | iNDECS Kejuruteraan | Kejuruteraan | `/kejuruteraan` | `APP_PKT_`, `APP_PKP_` | PKT, PKP | `PKT/2026/00054` |
| Landskap | iNDECS Landskap | Landskap | `/landskap` | `APP_PL_` | PL | `PL/2026/00021` |

Bangunan type codes: PB pelan bangunan penuh; PBK Pelan Tambahan / Ubahsuai Kecil; PBS
Pelan Bangunan Sementara and Permit Sementara Seksyen 19; PMT permits (meletak bahan
binaan, perancah, runtuh, tukar guna); TEL telco structures and renewable permits.

Short codes for menus and compact UI only, never in letters or reports: RANC,
BINA, KEJU, LAND.

The existing PKM slice uses route `/pkm` and table prefix `pkm_`. It is brought into
line with this table (route `/perancang`, prefix `APP_PKM_`) when PKM migrates onto the
core workflow engine. Until then, do not add new tables or routes in the old style.

Platform name is always `iNDECS` (lowercase i, uppercase NDECS). English name:
Intergrated Development Control System. Bahasa Melayu: Sistem Bersepada Kawalan
Pembangunan. Tagline (login and print only): Satu Platform, Pelbagai Permohonan
Pembangunan. The Bangunan module is always `iNDECS Bangunan`; no other brand.

## 2. Screen inventory

Every departmental application uses the same workspace shell with the same tab
order. Only `Borang Jabatan` changes per department.

```
Ringkasan · Butiran · Pemohon & Perunding · Tapak & Lot · Peta ·
Borang Jabatan · Dokumen · Fi & Bayaran · Pergerakan Fail ·
Ulasan Teknikal · Tugasan · Mesyuarat · Keputusan · Kelulusan ·
Permohonan Berkaitan · Garis Masa · Sejarah Audit
```

Department form fields:

- **Perancang (PKM)** — kategori dan jenis kebenaran merancang, guna tanah sedia ada dan dicadangkan, komponen pembangunan, kepadatan, nisbah plot, bilangan unit, tingkat, ketinggian, syarat perancangan, tempoh sah kelulusan.
- **Bangunan (PB, PBK, PBS, PMT, TEL)** — kategori dan kegunaan bangunan, jenis pelan (senibina, struktur, sanitari), bilangan blok, tingkat, jumlah luas lantai dan luas lantai mengikut kegunaan, ketinggian, anjakan, tempat letak kenderaan, kebolehcapaian OKU (MS 1184), OTTV, SPAH, nombor pelan diluluskan, syarat teknikal; bagi PBS, PMT dan TEL: tempoh sah, pembaharuan, inventori struktur, wang cagaran.
- **Kejuruteraan (PKT/PKP)** — aras sedia ada dan platform, korekan dan timbunan, cerun, struktur penahan, kawalan hakisan dan kelodak; kawasan tadahan, kadar alir reka bentuk, dimensi dan rizab parit, takungan, titik lepasan.
- **Landskap (PL)** — kategori landskap, kawasan lapang, softscape dan hardscape, pokok sedia ada dan dikekalkan, pokok dicadangkan dan spesies, tanaman penampan, kemudahan rekreasi, syarat tanaman gantian.

Two additional panels appear inside the workspace when data exists, for every department:

- **Semakan AIRS** inside `Ulasan Teknikal`: findings returned by AIRS with status, confidence, evidence links and officer confirmation controls. Evidence, AI explanation and officer decision are three visually separate blocks.
- **Penyerahan Spatial** inside `Peta`: E-Submission status and GIS feature references.

## 3. Workflow stages

All stages run on the core workflow engine; these lists are the default templates.

- **Perancang** — Pendaftaran, Semakan kelengkapan, Taksiran fi, Pengesahan bayaran, Edaran teknikal, Penilaian perancangan, Mesyuarat jawatankuasa, Keputusan, Kelulusan, Tutup.
- **Bangunan (PB)** — Pendaftaran, Pengesahan dokumen dan pelan, Taksiran fi, Penilaian teknikal bangunan, Edaran teknikal, Pindaan, Pengesahan pindaan, Pembentangan, Kelulusan, Pengesahan pelan, Kebenaran mula kerja, Pembinaan, CCC, Tutup.
- **Bangunan (PBK)** — as PB without Edaran teknikal; Pembentangan only above the delegated-authority limit; CCC simplified to Borang F.
- **Bangunan (PBS, PMT, TEL)** — Pendaftaran, Pengesahan dokumen, Taksiran fi, Penilaian teknikal, Kelulusan, Aktif, Tamat (with renewal back to Taksiran fi).
- **Kejuruteraan** — Pendaftaran, Semakan kelengkapan, Penilaian selari (Kejuruteraan + Perancang Bandar), Pindaan, Syor bersepadu, Kelulusan, Tutup.
- **Landskap** — Pendaftaran, Ulasan teknikal landskap, Semakan pelan, Pindaan, Kelulusan, Pemantauan tempoh penyelenggaraan, Tutup.

Pelan Kerja Tanah is ONE application record with TWO department process rows
running in parallel. Neither department overwrites the other's assessment; the
consolidated recommendation and single approval come from Kejuruteraan.

## 4. Component states

Every component below exists in `public/css/indecs.css`. Use these; do not build
parallel components.

| Component | Class | States to honour |
|---|---|---|
| Button | `.button` + `-primary`, `-secondary`, `-ghost`, `-danger`, `-small`, `-large`, `.disabled` | hover tint, pressed one ramp step deeper, 2px accent focus ring, 45% opacity disabled |
| Input / select / textarea | bare elements | hover border, focus ring, placeholder in `--color-text-muted`, disabled 45% |
| Table | `.data-table` | 1px header rule, 1px soft row rules, soft ground row hover, tabular numerals |
| Status | `.status-badge` + `.status-compliant`, `.status-attention`, `.status-critical`, `.status-info`, `.status-ai`, `.status-pending` | tinted fill, dark text of same hue; never more than the six |
| Card | `.content-card`, `.form-card`, `.detail-card`, `.side-card` | white surface, 1px border, `--radius-md`, no shadow except `.raised` |
| Nav | `.sidebar`, `.nav-link`, `.nav-link.active` | active = accent text on soft-teal fill with `--radius-sm` |
| Alert | `.alert`, `-success`, `-error`, `-info` | tinted fill with 4px left edge in the status hue |
| Metrics | `.metric-grid`, `.metric-card` | navy figures, muted labels; one card may carry the accent |
| Timeline | `.timeline` | done steps accent filled, current step accent outline, future steps neutral |
| AI block | `.ai-explanation` | soft ground, AI status hue label "Penjelasan AI", small portrait or icon, never adjacent to evidence without a rule between |
| Evidence block | `.evidence` | white surface, document and page reference, quote or value |

## 5. Delivery sequence

Bangunan-led, per `docs/bangunan/11-indecs-core-roadmap.md`: Phase A foundation (hygiene,
PostgreSQL, identity, RBAC) months 0 to 3; Phase B Bangunan operational with the core
engine, fees, letters, meetings and units, legacy retired, months 3 to 10; Phase C public
portal, AIRS Building Plan review, E-Submission and OSC3Plus links, months 10 to 15;
Phase D intelligence and linkage, months 15 to 21; Phase E field operations and second
department onboarding. PKM migrates onto the core engine during Phase B.

## 6. Print and documents

Approval letters, endorsements and reports carry the MBSP crest leading, a 1px rule,
then iNDECS. Every issued letter carries a verification QR in the footer. Photographs
print greyscale. Body copy flush left.

## 7. Themes and user preference

One axis, token-only:

| Attribute on `<html>` | Values | Default |
|---|---|---|
| `data-theme` | `light`, `dark`, absent = follow OS | absent |

`public/js/theme.js` reads and writes it, persists to localStorage, and keeps
the control's `aria-pressed` state in sync. Load it in `<head>` before the page
renders so there is no flash of the wrong theme.

The control markup is `resources/views/partials/theme-control.blade.php` — a
segmented Terang / Gelap / Sistem group, placed in the topbar before `.user-chip`.
The accent swatches are removed: the family shares one teal accent.

**Rules**

- Dark mode reverses the neutral ramp so component CSS never branches on theme. Navy
  structure becomes the ground; the teal accent stays.
- Accent fills use `var(--color-on-accent)` for their text, never a literal white.

**To persist per user instead of per browser:** store `theme_mode` on the users table,
render it as the attribute on `<html>` in `layouts/app.blade.php`, and POST the change
from `theme.js`'s setter.

## 8. Tokens

`public/css/tokens.css` carries, at minimum:

```
--color-navy        #102A43   structure, headings
--color-slate       #334E68   secondary structure
--color-accent      #2C9DA0   intelligent teal, the one accent
--color-accent-700  darker step for accent text
--color-bg          #F7F9FB   main ground
--color-bg-soft     #E6F4F4   soft teal ground
--color-surface     #FFFFFF   cards
--color-border      #DCE4EA
--color-border-soft lighter step
--color-text        #172B3A
--color-text-muted  #6B7C8C
--color-on-accent   #FFFFFF
--status-compliant  #2F8F63
--status-attention  #D89B2B
--status-critical   #C94B4B
--status-info       #3F7CAC
--status-ai         #7A68C9
--status-pending    #8A99A8
--radius-sm 4px  --radius-md 8px  --radius-lg 12px
--shadow-raised     one soft shadow
--font-heading      "Google Sans", "Segoe UI", system-ui, sans-serif   weight 600
--font-body         "Google Sans", "Segoe UI", system-ui, sans-serif   weight 400
                    loaded via https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700
```

Values are the AIRS creative direction palette so that iNDECS and AIRS read as one
product line. Changing a value here changes it for the family; do it in `mbsp-ui` once
that package exists.
