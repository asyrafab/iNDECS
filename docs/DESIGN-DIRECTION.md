# iNDECS — Design direction

> **NADI alignment — 9 November 2026:** [Current direction](eosc/nadi-direction.md): **NADI > eOSC**. iNDECS registers papers under eOSC for assigned meetings and communicates with NADI, the application used within meetings. Other meeting types may become sibling NADI modules later. The committee remains Jawatankuasa OSC. Earlier all-inside-iNDECS meeting descriptions are historical where they conflict; integration implementation remains to be designed.

## AIRS integration and applicant submission (accepted 2026-11-04)

[ADR-352](adr/ADR-352-indecs-airs-submission-and-precheck.md) and the [shared integration contract](integration/indecs-airs-contract.md) govern the target journey. iNDECS owns identity, drafts, formal submission, authoritative document versions, payments, corrections, assignment/SLA, notifications, official decisions and letters. AIRS is its review subsystem and owns review rules, evidence, findings and AI-run history; AMIRA assists within record permissions.

Free applicant precheck covers checklist/file usability and bounded embedded-text/local-OCR retrieval, with manual confirmation and resource limits. No paid generative AI/cloud processing, technical compliance review or readiness score is allowed before submission. Payment must clear before formal acceptance (only approved audited policy waivers); accepted submission then dispatches formal AIRS review. Fee amounts and correction-round charging remain undecided. Scores are advisory; they never reject, hide findings or change state. This documents agreed direction, not completed public submission/payment/integration functionality.

Companion to AGENTS.md. This file is the specification a coding agent follows for
naming, structure, components and screens. Updated 2026-09-18 to the Calm Intelligence
direction shared with AIRS and to the programme in `docs/bangunan/`. Department modules are built concurrently (ADR-212, 2026-10-29); each project has its own folder under `docs/`.

## 1. Naming (System A)

| Application | Full name | Menu | Route prefix | Table prefix | Type codes | Reference format |
|---|---|---|---|---|---|---|
| Planning | iNDECS Planning | Planning | `/perancang` | `APP_PKM_` | PKM | `PKM/2026/00125` |
| Building | iNDECS Building | Building | `/bangunan` | `APP_PB_` | PB, PBK, PBS, PMT, TEL | `PB/2026/00310`, `PBK/2026/00042`, `TEL/2026/00007` |
| Engineering | iNDECS Engineering | Engineering | `/kejuruteraan` | `APP_PKT_`, `APP_PKP_`, `APP_LJUP_` | PKT, PKP, LJUP | `PKT/2026/00054` |
| Landscape | iNDECS Landscape | Landscape | `/landskap` | `APP_PL_` | PL | `PL/2026/00021` |
| eOSC | eOSC (Pendaftaran kertas; NADI > eOSC untuk mesyuarat) | eOSC | `/osc` | `core.osc_` | — | `Bil. 21/2026` |

Bangunan type codes: PB pelan bangunan penuh; PBK Pelan Tambahan / Ubahsuai Kecil; PBS
Pelan Bangunan Sementara and Permit Sementara Seksyen 19; PMT permits (meletak bahan
binaan, perancah, runtuh, tukar guna); TEL telco structures and renewable permits.

Short codes for menus and compact UI only, never in letters or reports: RANC,
BINA, KEJU, LAND.

The PKM slice's route (`/perancang`) and table prefix (`app_pkm_`, the lowercase
snake_case form of `APP_PKM_`) were reconciled to this table on 2026-09-18. PHP class
names (`PkmApplication`, `PkmApplicationController`, and so on) were kept as-is, since
they name the PKM application type, not the module identity — see `AGENTS.md`, "type
codes stay as-is". Do not add new tables or routes in any other style.

Platform name is always `iNDECS` (lowercase i, uppercase NDECS). English name:
Intergrated Development Control System. Bahasa Melayu: Sistem Bersepada Kawalan
Pembangunan. Tagline (login and print only): Satu Platform, Pelbagai Permohonan
Pembangunan. The Bangunan module is always `iNDECS Bangunan`; no other brand.

### Database ownership (ADR-393)

Shared operational tables live in `core`. Extensions live in `perancang`, `bangunan`,
`kejuruteraan` and `landskap`, with the lowercase prefixes above, keyed by
`application_id` (ULID primary/foreign key), timestamps and restrictive deletion.
The existing Bangunan numeric ID is retained as a unique compatibility identifier.
Draft particulars use matching departmental extension tables keyed by `draft_id`;
LJUP's paid-check lighting snapshot uses `journey_id`. Existing shared identity and
Laravel infrastructure remain in `public`; historical/imported schemas are preserved.
Storage parity does not enable unimplemented services or equate departmental workflows.

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
| Nav | `.sidebar`, `.nav-link`, `.nav-link.active` | persistent navy environment; active = light teal text on the sidebar active token with `--radius-sm` |
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
Phase D intelligence and linkage, months 15 to 21. Since 2026-10-29 (ADR-212,
superseding ADR-211) the department modules — Perancang, Bangunan, Kejuruteraan,
Landskap — and eOSC paper-registration capability are built concurrently, each documented in its own `docs/` folder
(`docs/bangunan/`, `docs/eosc/`, and `docs/perancang/`, `docs/kejuruteraan/`,
`docs/landskap/` as they start). PKM workflow migration runs alongside Bangunan;
neither gates the other. Each module's scope and dates are estimated separately.

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

ADR-370 adds department identity artwork as a narrow owner-approved exception:
sharp square P/B/K/L identifiers in indigo/terracotta/steel blue/forest green,
same-hue dark frames, diagonal gradients, soft shadows and fine interior patterns.
Use `<x-department-icon>` and the shared department tokens; the detailed and
compact SVG sources and preview workflow are in `public/img/departments/README.md`.
These identity colours do not change status or interaction colours. System/public
surfaces keep teal and the iNDECS mark (three squares plus the circular Landskap canopy,
ADR-380). ADR-371 adopts the
reviewed artwork in departmental chooser tiles and compact workspace navigation
and topbar context. Permission checks, available routes and planned-module
visibility are unchanged; nondepartment entries retain their existing identities.

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

## 9. Approved navy workspace and AMIRA (2026-10-28)

ADR-311 records the programme owner's approval of the interactive navy concept.
Google Sans remains the font throughout, with Segoe UI and system-ui fallbacks.
Navy is the shared environment, not a department colour picker: the sidebar uses
`--color-sidebar-*` in both modes, and working surfaces follow the selected mode.
Departments are distinguished by their names, icons and permission-filtered links.
Unimplemented departments are not advertised as working navigation.

Use the existing card, form, review and table classes. `.workspace-switcher` is the
native-details department picker. `.workspace-body` holds content and the AMIRA
rail. `.record-tabs` links only to sections and routes that actually exist. Existing
Bangunan, document-catalogue, verification, Perancang and legacy pages share them.
The public account surface remains unframed and carries the MBSP crest first.

`<x-mark>` repeats the three squares and integrated lower-right circular canopy in sidebar,
workspace header, footer and identity surfaces (ADR-380). The squares represent
Perancang, Bangunan and Kejuruteraan; the circular canopy represents Landskap. On the
landing page, an inline finite animation separates realistic building layers
diagonally before fading them into the final mark, including the canopy.
The architectural reveal remains the visual origin story (ADR-316).
Reduced motion displays the final symbol immediately; replay and skip are provided.
ADR-318 adds finite roof-piece assembly, an opening door and restrained material
dust to this introduction. The roof and walls separate together (ADR-317).
During assembly only the roof is visible. The door opens outward and exterior
planting blooms during separation, then fades before the logo settles (ADR-319).
ADR-320 refines this to an eight-second sequence: an opaque uncovering of the
lower layers and a roughly half-second transition into the logo. ADR-322 restores
seven instances of the original plant, without lawn, footpath or palm.
All planting stays clear of moving layers.
ADR-323 places the seven plants closer to the footprint with slightly larger,
varied sizes, rotations and irregular spacing.
ADR-324 adds two plants at the owner's screenshot-marked positions, above and
to the right of the footprint, blooming once the walls have cleared them.
ADR-325 shuffles all nine bloom times on each replay, with clearance constraints
for the two side plants. The last bloom finishes 2.5 seconds after separation;
landscape fades away before the quick logo transition.
ADR-327 clarifies that only plant blooming is accelerated: eight-second playback
and the roof / building pace are retained, while the random 260 ms plant blooms
are individually quick. ADR-328 starts the first bloom immediately when the roof
begins moving aside; remaining blooms keep their random order and clearance rules.
ADR-329 clarifies the current timing: each plant grows at the normal 700 ms speed,
while all nine starts are randomly packed into one second, beginning with roof
movement. Growth overlaps; the roof and building keep their original pace.
ADR-330 brings the logo crossfade forward to 4.4–4.96 seconds; planting fades
away just ahead of it. Assembly, separation and plant growth timing are retained.
ADR-331 advances the logo another half-second, to 3.9–4.46 seconds, with the
landscape fading at 3.75–4.14 seconds after plant growth completes.
ADR-332 adds the iNDECS wordmark below the final symbol, revealing it at
4.64–4.96 seconds after the visible building transition has finished.
ADR-333 centres the brand wordmark across the whole symbol and extends its fade
to 4.64–6.08 seconds, without changing other motion.
ADR-334 adds token-based, alpha-aware shadows beneath the photographic roof,
walls and plants. They fade with the imagery; the final mark and wording stay flat.
Cards do not loop, pulse or shimmer. Panel and interaction transitions use motion
tokens. Both the accessibility control and OS reduced-motion preference suppress
animations. Keyboard users can skip to content and close drawers with Escape.

AMIRA is labelled **Pembantu AI iNDECS**, supporting the iNDECS brand. The current
implementation offers local page guidance, form-label assistance and links to
visible actions. It explicitly states that live AMIRA conversation is unavailable; AIRS document review is a separate integration.
It does not simulate a live model, fabricate confidence or review findings, send
data externally, fill forms, submit decisions or bypass permission checks. Record
context comes only from the authorised route-bound record. A future AIRS adapter
must follow document 12's integration boundary and document 14's evidence rules.

Primary buttons use `--color-action` (the deeper teal fill) and
`--color-on-accent`; small text uses the darker accent step. Six semantic status
roles each define matching background and text tokens in both modes. Legacy
browser accent preferences are ignored and removed by `theme.js`.

## 10. Welcome entrance (ADR-335, 2026-11-03)

The owner-approved guest home is a full-height soft teal welcome with exactly
three entry actions: Semak Permohonan, Log Masuk and Ketahui lebih lanjut.
The last invitation alone uses Caveat via `--font-handwriting`; the rest stays
Google Sans. Authentic MBSP SVG leads the shared iNDECS mark and wordmark.
Exactly three cropped photographic boxes travel on a diagonal conveyor (ADR-337).
Mouse position controls opposite travel direction and four speed tiers throughout
the gallery region, including gaps between photos (ADR-341). A calm centre dead
zone stops steering. When steering stops, glide to the next aligned rest rather
than freezing midway or teleporting. Pointer-captured drags directly move the
boxes and release with bounded, decaying inertia before the alignment glide.
Reduced motion uses immediate aligned photos and discrete gesture navigation.
ADR-339 defines slow, medium, fast and super fast tiers at .35, .75, 1.2 and 2.4
track units per second, with no background drift or visible carousel controls.
Replace photos at the faded recycling edge. At rest, settle all three boxes
fully opaque and ordered with the upper-right box on top (ADR-340). The active
pool contains ten photographs; visual captions are removed. Keep
clean architecture and maintained garden imagery, allowing active earthwork.
Keyboard navigation and touch gestures remain available. Keyboard focus,
open information panels, hidden pages and reduced motion pause the conveyor.
Both logos sit directly above the welcome heading, separated by a bold navy
divider. The iNDECS heading text stands out in darker teal, weight 700 and a
modestly larger size (ADR-339). A faint token-coloured SVG
field of nested closed hill contours with visible summits lies behind the page, with a dedicated material shadow token
under the image boxes. The MBSP crest links to its official site. Sources live
with the local assets.

The header, copy, actions and gallery fit one dynamic viewport on desktop, phones
and landscape displays. Information panels overlay the page with bounded internal
scrolling. The architectural introduction is primed before first paint, then a
tilted clip boundary wipes right to left to reveal the stationary welcome. It runs
once per arrival. The wipe begins at 6080ms when the final wordmark fade ends,
removing the final static hold without changing visible animation or wipe speed.
It has no skip, replay or Escape dismissal. This supersedes the skip/replay
behaviour in section 9. Reduced motion, no JavaScript and failed texture loading
show the welcome directly. Tracking remains an availability notice. Native
details surfaces offer existing public and staff login routes and information.


## 11. Welcome login dialog (ADR-340)

Log Masuk opens a native modal with public/consultant and staff tabs, each showing
its own username and password fields. The popup enlarges from the trigger with
one restrained overshoot. Its token-defined backdrop greys and frosts the rest of
the page; this is the owner's explicit exception to the general no-blur rule.
Use existing authentication endpoints, CSRF tokens and configured staff identifier;
keep public recovery/registration links and truthful administrator-managed staff
recovery guidance. Validation errors reopen the correct tab, without password
values or an introduction replay. Native modal isolation, an explicit Tab loop,
Escape/close/backdrop dismissal and focus return are required. Small screens keep
the popup within the viewport with internal overflow only when needed.


ADR-341 gives the selected login tab a deeper teal fill with on-accent text.
Both login forms place the submit button directly after the password, followed
by the recovery and registration links. Authentication behaviour is unchanged.


ADR-342 supersedes the individual filled tabs with an on/off-style segmented
switch: a single thumb slides between equal-width halves, labels are centred
inside each half, and the selected label uses weight 700. This alignment exception
is specific to this selector. Public login uses the normal surface; staff uses
navy/pale inverse colours, reversed in dark mode. Account palette values live in
tokens.css; components branch only on account selection. Background, input, link
and submit colours follow the account palette. Reduced motion skips the slide.

ADR-350 adds a tall 2:5 portrait beside the login content, matching the selected
account type. Public shows a woman and helmeted architect with a rolled plan;
staff shows office and site officers. Keep photographs untinted. The panel moves
below the form on narrow screens. Hidden states follow the existing tab selection
and server-rendered validation-error state; authentication remains unchanged.

ADR-351 makes the desktop portrait full-height and flush with the popup edge,
without its own border, margins or corner rounding. Public uses an office scene
with a Chinese woman and architect. Distinct staff bodies angle left at a
construction site, with their faces and eyes toward the viewer (v3 clarification).
Keep object-fit cropping for taller validation/help content.

## 12. Public system information (ADR-343)

The handwriting invitation `Ketahui lebih lanjut` navigates to `/tentang-indecs`
in the same tab. This public page uses shared branding, tokens and appearance
controls, with a contents navigation, department cards, service availability,
ecosystem roles, account links and native FAQ details. It scrolls naturally while
the welcome entrance retains its single-viewport layout. State current access
and unavailable or planned public capabilities separately. Do not expose internal
configuration or imply that planned integrations are active public services.

ADR-349 protects a carousel release from hover steering until 20% of its release
speed remains, retaining ADR-346's spin friction of 1.2/second for a
longer coast. Apply the latest pending hover position at that threshold. New
drags add gesture velocity to the decaying prior spin, bounded by the existing
maximum; reverse gestures brake or reverse. Pointer exit preserves inertia.
Reduced motion and modal/focus/visibility parking retain priority.

ADR-348 joins coasting and alignment with a velocity-matched Hermite curve.
Begin braking at .65 track units/second, choose a forward aligned stop with
enough runway, and finish at zero velocity. Scale duration to speed/distance,
capped at 1440ms. Exponential coasting uses exact frame-independent integration.

## 13. Application chooser (ADR-344)

ADR-410 (19 November 2026) updates staff visibility: show all departmental and
cooperating application entries, with grey disabled non-links where access is
absent or deployment is unavailable. Existing route guards remain unchanged.
The switcher uses the same registry; Daftar Mesyuarat is a separate registration
module under Ruang kerja, while NADI is the separate meeting application.

The authenticated homepage opens `.application-dialog` with a responsive
`.application-grid` of `.application-tile` entries and distinct local Lucide icons
in `.application-icon`. Filter links server-side using existing module access
checks, including meeting membership. Public users receive Portal Awam only.
The system administrator sees planned modules and integrations as muted cards
without links. ADR-345 centres all chooser text and icons (owner-approved alignment
exception), removes Buka aplikasi / Belum tersedia captions, and adds subtle
token-based hover enlargement. The popup shares the login trigger-origin overshoot
and frosted backdrop. Homepage logout uses the shared secondary button and icon.
The root route is `home`; `dashboard` moves to `/dashboard`.
Homepage login returns to the chooser unless an intended protected URL exists.
Skip the architectural introduction for authenticated arrivals. Escape, close and
backdrop dismissal restore focus to Pilih aplikasi. Without JavaScript, show the
same authorised links inline. The workspace switcher links back to the chooser.

## 14. Public preparation workspace (ADR-358, 2026-11-04)

The shell in this section is superseded by ADR-359 below; draft ownership and
submission boundaries remain in force.

The authenticated public portal shares the navy workspace shell, sidebar controls
and theme tokens through `layouts/portal.blade.php`. Its dashboard shows real
owner-scoped drafts, account access and explicit service availability. `Draf saya`
saves common preparation fields; `Akaun saya` retains public account controls.
Public identity entry pages retain the unframed public layout. Drafts are not
official applications; no reference, payment, acceptance, document review or
submission is implied. Responsive `.portal-*` components are documented in
AGENTS.md and reuse existing cards, forms and badges.

## 15. Public action entrance (ADR-359, 2026-11-04)

The public portal uses a simple brand header with a right-hand gear menu, without
a departmental sidebar. Two left-aligned choice buttons open native dialogs:
Permohonan baru selects an available preparation type; Semak Permohonan shows
received-record availability and separately lists owned drafts. A raised status
panel sits to the right on desktop and below the choices on smaller screens.
Account, draft list, helpdesk availability, appearance and local guidance remain
accessible through the gear. Other portal pages share the simple header and a
link home. Preserve native modal focus, Escape, backdrop close and input-free
GET type selection. No received application or helpdesk service is fabricated.

ADR-360 adds a download icon beside each available application type. Its popup
offers Senarai semak (Checklist) and Garis panduan, both disabled and labelled
Belum tersedia pending document configuration. The chooser remains beneath the
popup; close/Escape/backdrop returns focus to the selected download icon.

## 16. Kaunter Maya counter and AMIRA avatar (ADR-361, 2026-11-05)

Superseding section 15's entrance, the portal home and draft editor use
`x-portal-counter`. `.counter-amira` is a persistent navy panel on the left
(`--color-amira-panel-*`, `--width-counter-amira`). `.counter-file` on the
right holds **Fail saya** (`.file-board`, `.file-section`, `.file-row`). Only
non-empty sections render, and an empty board shows `.file-board-empty`.
Below 901px, `.amira-counter-bar` replaces the panel and opens it as a
full-height sheet (`.counter-amira.is-open`).

The AMIRA avatar (`.amira-bubble`, `public/js/amira-bubble.js`) is an uneven
bubble (her body) with three horizontal wavy rings (her voice). Mood and token
mapping, as body light / mid / deep and ring:

| Mood | Tokens |
|---|---|
| calm | accent-200 / 400 / 600, ring accent-400 |
| happy | accent-100 / 300 / 500, ring accent-300 |
| think | status-ai-bg / status-ai / status-ai-text |
| concern | neutral-200 / 500 / 700 |
| alert | status-attention-bg / status-attention / status-attention-text |

The highlight comes from `--color-amira-highlight`. The canvas's gradient and
glow are the only permitted exception to the no-gradient/no-glow rule. Reduced
motion draws a still frame, and without JavaScript a flat token circle shows.
`window.AmiraBubble.setMood(el, mood)` and `setSpeaking(el, on)` drive the
avatar.

ADR-365 replaces the three rings with one tilted orbiting wave, a fluid uneven
body, faint mist and bounded random satellite bubbles that bud, drift and pop.
The larger avatar uses `--size-amira-bubble`; compact avatars omit satellites.
`--color-amira-body-*` and the mood-specific light/deep tokens preserve material
lighting in both themes. `AmiraBubble.setSpeaking(el, on)` pulses the orbit while
local guidance appears. This is visual feedback only.
Effects stay inside the canvas exception. Reduced motion draws a still frame;
offscreen and hidden avatars pause. Release, lifetime and response timings use
`--duration-amira-*` tokens.

ADR-366 centres the avatar and its name/subtitle in `.amira-counter-identity`,
an owner-approved identity alignment exception. The orbit stays around the
bubble, with no line extending toward chat messages.

ADR-367 reveals requested guidance replies quickly in grapheme order, with each
user/reply box enlarging and fading into view. The final reply height is reserved
while typing. The counter reply uses the shared raised panel colour and rounded
message shape. A new exchange cancels the previous reveal. Reduced motion and
hidden pages show complete text immediately; the polite live region is busy
until completion. Timings and entrance scale come from AMIRA tokens.

## Independent AMIRA backend (2026-11-04)

AMIRA backend ownership was moved to C:\Users\Asyra\Documents\AMIRA under ADR-353 (2026-11-04). It is an independent prepared-guidance/catalogue service; conversation, model inference and record retrieval remain unimplemented. AIRS retains its review AiProvider and UI/assets. iNDECS keeps local guidance with no new remote dependency. Future assistant APIs belong to AMIRA, with scoped source APIs and iNDECS identity; free applicant guidance stays local and uses no paid AI.

ADR-380 adopts the owner-supplied `iNDECS_New.svg` as the authoritative logo. Preserve its exact paths and supplied dark teal; shared inline geometry uses `--color-mark-outline` and `--color-mark-fill`, with token-only outline reversal for dark grounds. See `public/img/brand/indecs-approved.README.md`.

ADR-381 adds a circular grass garden with an enclosing stone walking path to the opening material sequence. `.building-garden` uses natural image textures clipped to the approved landscape footprint, appears during layer separation and fades during the existing symbol transition. The final logo stays unfilled inside its circular outline; reduced-motion and asset-failure behaviour remain unchanged.

ADR-382 adds small stone edging on both sides of the opening garden path. Reuse the stone material at a finer scale, with restrained joints and a contact seam beside the grass. All detail stays inside the existing garden footprint and fades with the material layer.
