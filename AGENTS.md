# AGENTS.md — iNDECS

> **NADI direction — 9 November 2026:** [ADR-378](docs/adr/ADR-378-nadi-meeting-application-boundary.md) updates the earlier ADR-213 product boundary. **eOSC within iNDECS registers each paper for its assigned meeting. NADI — Navigation, Analysis, Decisions & Intelligence — is the application used within the meeting**, with eOSC as its first module and other meeting types open for later needs. Earlier descriptions of the entire live meeting as eOSC inside C10 are historical where they conflict. Existing code/identifiers remain; NADI deployment and integration require design. See [current direction](docs/eosc/nadi-direction.md).


> **AI ecosystem alignment — 9 November 2026:** [Current roles and integration boundaries](docs/integration/ai-ecosystem.md). AMIRA remains application assistance; MATRIX is the planned document specialist; MINDA is internal intelligence. Existing authority, free-precheck and payment gates remain.


> **AIRS merged into iNDECS — 13/14 November 2026:** the standalone AIRS application is retired; its review engine is an in-process module in `app/Airs` (schema `airs`), reached only through `App\Airs\CheckService`. The HTTP connector of ADR-354 is removed. Owner decisions (paid check before final submit, two checks with one amendment, missing documents never auto-close, score threshold shadow-only) are in [the merge design](docs/superpowers/specs/2026-11-13-airs-module-merge-design.md). The LJUP applicant journey (Plan 2: payment, check 1, one amendment and check 2, handover to JKEJ) is built with a staging payment driver only; the real payment gateway, officer waiver, officer workspace (Plan 3) and the superseding ADR are still to be done; where older text below says AIRS is a separate service with its own API, the design takes precedence.

## AIRS integration and applicant submission (accepted 2026-11-04)

[ADR-352](docs/adr/ADR-352-indecs-airs-submission-and-precheck.md) and the [shared integration contract](docs/integration/indecs-airs-contract.md) govern the target journey. iNDECS owns identity, drafts, formal submission, authoritative document versions, payments, corrections, assignment/SLA, notifications, official decisions and letters. AIRS is its review subsystem and owns review rules, evidence, findings and AI-run history; AMIRA assists within record permissions.

Free applicant precheck covers checklist/file usability and bounded embedded-text/local-OCR retrieval, with manual confirmation and resource limits. No paid generative AI/cloud processing, technical compliance review or readiness score is allowed before submission. Payment must clear before formal acceptance (only approved audited policy waivers); accepted submission then dispatches formal AIRS review. Fee amounts and correction-round charging remain undecided. Scores are advisory; they never reject, hide findings or change state. This documents agreed direction, not completed public submission/payment/integration functionality.

Binding rules for any coding agent (Claude Code, Codex, Antigravity) working in this
repository. Read this file and `docs/DESIGN-DIRECTION.md` before writing UI. Read
`docs/bangunan/` before touching anything Bangunan, core identity, or integration.

## The system

iNDECS is a Laravel application for MBSP development-control applications. It is
one shared core with four departmental applications, and it is the **main portal and
identity provider** for a family of cooperating systems:

| Application | Department | Application types |
|---|---|---|
| iNDECS Perancang | Jabatan Perancang Bandar | PKM |
| iNDECS Bangunan | Jabatan Bangunan | PB, PBK, PBS, PMT, TEL |
| iNDECS Kejuruteraan | Jabatan Kejuruteraan | PKT, PKP |
| iNDECS Landskap | Jabatan Landskap | PL |

| Cooperating system | Relationship |
|---|---|
| AIRS (AI Review Studio) | Plan-review sub-system. Reviews documents against rule packs and returns evidence-linked findings through a versioned API. Authenticates against iNDECS. Never a system of record |
| E-Plan Submission System (Esri Malaysia) | Spatial submission sub-system. PSPs submit CAD and Excel; ETL loads GIS. iNDECS stores its feature references and status |
| Sistem Bangunan 2.0 (legacy) | Read-only nightly sync during transition; retired at Bangunan cut-over |

iNDECS holds the record. AIRS reviews. E-Submission converts spatial data. Every system
must run alone if another is down. Contracts are versioned APIs, never shared databases.
See `docs/bangunan/12-identity-and-integration-contracts.md`.

Never name a module after a plan code alone: "PB" is ambiguous (Perancang Bandar
vs Pelan Bangunan). The Bangunan module is **iNDECS Bangunan**; the name "eBangunan" is
not used anywhere. Route prefixes, table prefixes and reference formats are in
`docs/DESIGN-DIRECTION.md` — follow them exactly, do not invent new ones.

## Platform decisions (2026-09-18)

- **Database:** PostgreSQL 16 with PostGIS, `pgvector` and `pg_trgm`. One database, schemas
  `core`, `bangunan`, `perancang`, `kejuruteraan`, `landskap`, `ai`, `integration`. SQLite is
  not used, not even locally: Docker Compose provides PostgreSQL. Tests run against PostgreSQL.
- **Identity:** iNDECS issues OpenID Connect (Laravel Passport). Staff authenticate via the
  MBSP staff API or Active Directory behind one interface; public users via email and phone
  OTP, shadow accounts and magic links. Applications authorise from token claims.
- **Core before module:** workflow engine, RBAC, fees, payments, notifications, SLA, letters,
  meetings, units registry and domain events are built in `core`, generically, when the first
  module needs them. Department modules are extension tables plus configuration.
- **Concurrent modules (ADR-212):** Perancang, Bangunan, Kejuruteraan, Landskap and eOSC
  are built at the same time. Each project's documentation lives in its own `docs/` folder
  (`docs/bangunan/`, `docs/eosc/`, `docs/perancang/` …). A core change made for one module
  must keep every other module's tests green.
- **eOSC / NADI (ADR-378, revising ADR-213):** eOSC in this codebase registers papers for assigned meetings. NADI is the in-meeting application; eOSC is its first module. Existing C10 agenda/map code is the reuse baseline, not a settled NADI topology. Preserve technical identifiers and meeting-map resilience (replicated layers, never live ArcGIS). Current direction: `docs/eosc/nadi-direction.md`; OSC design baseline: `docs/eosc/README.md`.
- **AI:** cloud-hosted models behind the provider-neutral `AiProvider` contract shared with
  AIRS; provider identifiers confined to audit tables; self-hosted later. AI suggests, the
  officer decides. No AI output changes state.
- **Hygiene:** every change through a pull request with green CI; secrets never in the
  repository; Conventional Commits; ADRs in `docs/adr/`.

## Visual direction — Calm Intelligence

The visual reference for the whole family is the AIRS creative direction
(`Plan Review/docs/21_AIRS_CREATIVE_DIRECTION.md`): calm, precise, evidence-first,
soft technical minimalism. Navy for structure, teal for the one accent, soft teal ground,
white working surfaces, neutral borders and muted text for hierarchy.

**Every value comes from `public/css/tokens.css`.**

- Never hard-code a hex, a font family, or a spacing/radius number the tokens carry. Use
  `var(--color-*)`, `var(--font-*)`, `var(--space-*)`, `var(--radius-*)`, `var(--shadow-*)`.
- Colour roles: `--color-navy` structure and headings, `--color-accent` intelligent teal for
  primary actions, links, active states and AI or progress accents, `--color-bg` main ground,
  `--color-bg-soft` soft teal ground for calm panels, `--color-surface` white cards,
  `--color-border`, `--color-text`, `--color-text-muted`. Use ramp steps rather than ad-hoc
  `color-mix()`. For accent-coloured body text use the darker accent step; the base accent is
  tuned for fills, not small text.
- Status colours are semantic and limited to the six in the tokens: compliant, needs
  attention, critical, information, AI analysis, pending. Status is shown as a badge with a
  tinted fill and dark text of the same hue. Do not add a seventh.
- Type: **Google Sans** for everything, loaded from Google Fonts with a `Segoe UI, system-ui`
  fallback. `--font-heading` is Google Sans 600, `--font-body` Google Sans 400. Interface text
  never below 12px; table and form labels 11px uppercase at most. Figures use tabular numerals.
  Generated documents (letters, papers, decks) also use Google Sans.
- Structure: white cards on the ground with `1px solid var(--color-border)` and
  `--radius-md` (8px). One soft shadow token for raised elements only. Sections inside a card
  are separated by `1px solid var(--color-border-soft)`. Headings and body copy are flush
  left; only figures in metric tiles may be large.
- Interaction: hover tint and pressed state one ramp step past base. Keyboard focus is
  `outline: 2px solid var(--color-accent); outline-offset: 2px` — never the browser default.
- Icons: Lucide only. No emoji in the interface.
- AI content is always visually separated from evidence and from officer decisions: evidence
  block, AI explanation block labelled as such, officer action block. AI suggestions carry a
  confidence indicator and a source reference.
- Motion: slow, purposeful, optional, with reduced-motion support.

## Do not

- Do not add gradients, glows, blur, neon, holographic or decorative panels. The sole
  exception is the AMIRA avatar canvas (`public/js/amira-bubble.js`, ADR-361).
- Do not add a second accent colour or an accent that is not in the tokens.
- Do not centre body copy or button labels.
- Do not introduce Tailwind utility styling into the Blade views alongside `indecs.css`; this
  app styles with the class layer in `public/css/indecs.css`.
- Do not tint or colourise photographs.
- Do not present AI output as a decision, or place an AI persona as the system brand.

## Themes

Light and dark modes are token-only: `data-theme` on `<html>`, switched by
`public/js/theme.js` and stored in localStorage. See `docs/DESIGN-DIRECTION.md` section 7.

- No component rule may branch on theme. If a value must differ per theme, add a TOKEN in
  `tokens.css` — never a `[data-theme="dark"] .some-component` override.
- Do not write `#fff` on an accent fill; use `var(--color-on-accent)`.
- Accent choice is not user-selectable. The family shares one teal accent so that iNDECS
  and AIRS read as one product line.

## Assets

- ADR-376 adopts the owner-supplied transparent `public/img/eosc-temporary.png` as temporary eOSC identity. Use `<x-eosc-icon compact />` for 32px workspace navigation/header identity and the original image at 64px in the chooser. Preserve its colours, alpha and proportions; keep action icons Lucide and system branding unchanged. Provenance is in `eosc-temporary.README.md`.

- ADR-375 activates the E-Plan Submission chooser link to the owner-supplied `https://spgis.mbsp.gov.my/ePlanSubmission`, using same-tab native navigation and the existing administrator visibility check. It is a direct external link only; do not add credentials, tokens or record parameters, or imply SSO/API integration.

- ADR-374 supersedes Esri's fixed white identity ground: `.application-icon-esri` is transparent and borderless. `--filter-esri-logo` displays the original black SVG normally in light mode and white in dark mode. Keep theme differences token-only and the full globe/wordmark geometry intact.

- ADR-373: E-Plan Submission uses the unchanged full `public/img/esri-logo.svg`, with Esri attribution/provenance in `esri-logo.README.md`. `.application-icon-esri` supplies a token-based fixed white ground and neutral border for the black logo in both themes; preserve its aspect ratio and full wordmark. This provider identity exception does not activate the planned entry or alter its access checks.

- ADR-372: AIRS's application chooser entry uses the unchanged official `public/img/airs-icon.svg` (`application-brand-logo` inside `.application-icon-brand`), with provenance in `airs-icon.README.md`. Its original gradient/colours are a brand identity exception. No extra tint, recolouring or added shadow; access checks and the AIRS route are unchanged.

- ADR-371 adopts department artwork in existing permission-filtered application tiles (64px), workspace switcher entries/summary and departmental topbar context (compact 32px). `.application-icon-department` removes the extra rounded/tinted icon container. Planned K/L tiles remain unlinked and keep existing visibility checks. System/public branding and nondepartment entries keep their identities; action icons stay Lucide. This supersedes the initial preview-only delivery boundary below.

- K subsequently follows the same raster-backed approach as L: `scripts/brand/kejuruteraan-soft.png` preserves the owner's supplied K reference with quieter blueprint lines (ADR-370). Its supplied dimension labels are decorative artwork only. K/L use embedded images in the shared SVG wrappers; P/B remain vector. Do not regenerate K from the superseded authored bridge geometry.

- L artwork update under ADR-370: use the owner's supplied `scripts/brand/landskap-approved.png` directly through the generated L SVG wrappers. This is intentionally raster-backed artwork preserving the exact reference, not a pure-vector redraw. Do not replace it with the rejected procedural botanical patterns. P/B/K remain vector.

- Department identity artwork (ADR-370) uses `<x-department-icon department="perancang|bangunan|kejuruteraan|landskap" />`, with `compact` for small sizes and optional accessible `label` when no adjacent name exists. `.department-icon` and its department/size variants live in `indecs.css`; colours, patterns and shadows use `tokens.css`. Sharp squares, same-hue dark frames, gradients, centred outlined letters and interior patterns are owner-approved identity exceptions. P uses asymmetrical topography; B tiled hipped roof; K bridge drafting without numerical dimensions; L large detailed leaves. See `public/img/departments/README.md` for rebuild/preview. This first increment does not replace live navigation. Teal and the system mark (three squares plus the circular Landskap canopy, ADR-380) remain the system/public identity.

- `.button-google` is the Google sign-in provider button (ADR-356), using the unchanged official `public/img/google-g.png` and `--color-google-*`/Google sizing tokens. Its centred label, fixed light provider treatment and official multicolour logo are owner-requested identity exceptions; other buttons/icons/accents retain the normal rules. Provenance is in `public/img/google-g.README.md`.
- In the welcome dialog, `.welcome-login-divider` separates password/Google login with `atau`; `.google-login-option` and `.google-login-logo` place the unchanged G outside the action outline on a white round ground, preserving its left alignment (ADR-357).

- `public/img/indecs-mark.svg` — the unchanged owner-supplied logo (ADR-380): three offset squares
  and an integrated circular Landskap canopy. Preserve the source geometry and colours;
  use dedicated `--color-mark-*` tokens for inline branding. Provenance: `public/img/brand/indecs-approved.README.md`.
- `public/img/indecs-mark-reversed.svg` — for dark grounds
- `public/img/indecs-lockup.svg` — mark + wordmark + BM name
- `public/img/mbsp.png` — the MBSP crest. Crest always LEADS, then a rule, then iNDECS. Use
  on login, letterhead, printed approvals — not in the app header.

The three squares represent Perancang, Bangunan and Kejuruteraan; the lower-right
circular canopy represents Landskap (ADR-380, superseding ADR-379 and ADR-316's primary explanation).
The architectural reveal remains the visual origin story. The separate relationship
indicator uses one square = standalone application, two = linked pair, three = development case.

## Working rules

- The approved workspace (ADR-311) has a persistent navy sidebar shared by all
  departments. On desktop the `.sidebar-edge-toggle` arrow on its right edge collapses it (`html[data-sidebar="collapsed"]`,
  remembered in `localStorage` key `indecs.sidebar` and applied by `theme.js` before paint); below 901px it is the
  off-canvas menu as before. `.workspace-switcher` exposes authorised departments only;
  `.workspace-body`, `.topbar-actions`, `.workspace-footer`, `.record-tabs` and
  `.catalogue-tools` organise existing screens. `.indecs-mark` renders the three squares
  and the circular canopy using shared SVG paths (ADR-380); `mark-reversed`, `mark-small` and `mark-entry` are its variants.
- `<x-icon>` uses the local Lucide sprite and `<x-mark>` uses shared token colours.
  The upstream icon licence and pinned version live in `public/vendor/`.
- `.amira-panel`, `.amira-context`, `.amira-prompts`, `.amira-answer` and
  `.amira-composer` are shared assistant components. Until AIRS integration exists,
  AMIRA provides explicitly labelled local page guidance only. Derive record
  context from the authorised route-bound model, never a Blade loop variable.
  No external AI calls, fabricated findings, automatic form submissions or status
  changes. Keep evidence separate from guidance and officer actions.
- `data-motion="reduced"` and the OS preference disable motion. Animation durations
  and easing are tokens. Accent-filled primary actions use the deeper action token
  to keep white labels readable; the brand accent stays teal.

- Public identity pages use `.public-header`, `.public-brand`, `.public-shell`,
  `.public-tabs`, `.public-form`, `.public-section` and `.public-details` in
  `indecs.css`. They are unframed account surfaces using the shared tokens.

- The guest landing page at `/` is the full-height welcome entrance (ADR-335).
  `.welcome-home`, `.welcome-header`, `.welcome-main`, `.welcome-copy` and
  `.welcome-actions` arrange three entry choices. `.welcome-choice` native details
  provides tracking availability; the learn link navigates to `about` at `/tentang-indecs`;
  `.welcome-learn` alone uses the token handwriting font, an owner-approved exception.
  `.welcome-gallery` holds exactly three `.welcome-photo` clipped carousel panels
  fed by ten local photographs (ADR-340). ADR-337 moves the boxes themselves on a diagonal
  conveyor, opposite mouse position and faster near its ends. ADR-338 makes it
  originally static except while the pointer is over an actual image, doubles hover speed
  approximately, removes carousel controls and adds token-based image shadows.
  ADR-339 uses four hover tiers (.35, .75, 1.2, 2.4 track units/second), a 3px
  navy logo divider and `.welcome-title-brand` for prominent teal iNDECS text.
  ADR-341 supersedes the image-only hit gate: pointer position anywhere in the
  gallery, including gaps, steers the conveyor. Pointer-captured mouse, pen and
  touch drags spin it with bounded decaying release velocity, followed by an
  eased glide to the next aligned rest. Keep the boxes continuous.
  ADR-349 protects release momentum from hover until 20% of its release speed
  remains, then applies pending regional steering. The spin
  friction is 1.2/second for a longer coast. New drags add their velocity
  to the decaying prior spin, capped by the existing maximum; opposite drags brake.
  ADR-348 blends the coast into alignment at .65 track units/second using a
  velocity-matched Hermite glide, with a forward braking distance and zero final
  velocity. Duration follows speed and distance, capped at 1440ms. No pause or
  independent ease-out restart between spinning and alignment.
  Never teleport boxes. Reduced motion uses aligned, discrete gesture navigation.
  New photos appear only when a box recycles at its faded edge. At rest, show three fully opaque
  boxes in order with the upper-right box on top; remove visual image captions.
  Retain crops and slight hover scale.
  Hover steers; keyboard focus, open panels, hidden pages and reduced
  motion pause playback. Keep only clean architecture and maintained landscaping
  in the pool; active earthwork may be rough. Controls and touch gestures navigate.
  Keep real photographs untinted; sources are in `public/img/welcome/README.md`.
  `partials/welcome-login.blade.php` provides the native `.welcome-login-dialog`
  (ADR-340): a trigger-origin overshoot, token-based grey/frosted `::backdrop`
  (owner-approved blur exception), public/staff tabs and credential fields. Submit
  to the existing CSRF-protected, throttled routes, respecting the configured staff
  identifier. Keep staff recovery/provisioning administrator-managed. Errors reopen
  the matching tab without replaying the introduction or rendering a password.
  Trap focus, support Escape/backdrop/close and restore focus to Log Masuk. The
  ADR-342 presents the login tabs as a two-position sliding-thumb switch with
  centred labels (owner-approved alignment exception) and a bold selected label.
  Public/staff surfaces invert their backgrounds through staff palette tokens,
  including in dark mode; no component theme branches. The submit button comes
  before the recovery/registration links in both forms (ADR-341).
  ADR-350 adds `.welcome-login-layout` and a right-side `.welcome-login-portrait`
  in 2:5 proportion, selected with the public/staff tab via `data-login-portrait`.
  Keep generated photographs untinted and decorative, with provenance in
  `public/img/login/README.md`. Below 601px the portrait follows the form.
  ADR-351 removes image insets/rounding and stretches the desktop photo to the
  popup's full height, flush with its right edge. Public uses a Chinese woman and
  architect in an office; distinct staff bodies angle left at a construction site,
  with faces turned toward the viewer (staff portrait v3).
  Both logos sit immediately above the heading in `.welcome-copy`. The decorative
  `.welcome-topography` inline SVG uses nested closed hill contours with visible
  summits and index contours behind the
  welcome. The supplied `public/img/mbsp-logo.svg` links to https://www.mbsp.gov.my/ and
  leads the existing `<x-mark>` and wordmark.
  `.welcome-entrance` plays the existing finite architectural introduction before
  revealing the stationary welcome through a tilted right-to-left wipe, without
  skip, replay or Escape dismissal. The welcome fits one dynamic viewport with no
  page scrolling; information panels overlay it with bounded internal overflow.
  Set `data-welcome` before paint and keep the introduction SVG hidden until playing
  so neither final logo flashes during loading. Begin the unchanged tilted wipe
  at `--duration-mark-complete` (6080ms), when the final wordmark settles, without
  waiting through the remainder of the eight-second story (ADR-339). Reduced motion,
  missing textures and no JavaScript reveal the welcome directly. The welcome is
  inert only during the finite introduction. Public tracking remains unavailable.
  The inline `.landing-introduction` uses `.building-mark`, `.building-layer`,
  `.building-texture` and `.building-symbol` for the architectural reveal; its
  three local photographic layers fade into the final SVG mark, including the Landskap canopy (ADR-380).
  ADR-318 adds `.building-roof-tile`, `.building-door`, `.building-dust` and
  `.building-dust-particle`: finite roof assembly, hinged door and restrained
  impact/movement dust inside this introduction only, with token timings.
  ADR-319 hides lower layers during assembly, opens the door outward and adds
  `.building-landscape` / `.building-plant` for finite exterior planting bloom.
  ADR-320 adds `.building-underlay` for instantaneous visibility beneath the roof.
  ADR-322 restores seven instances of the original `.building-plant`, without
  lawn, footpath or palm, clear of the moving envelope. Only landscape fades in.
  ADR-324 adds two `.building-plant-late` instances at the owner's marked sites;
  these bloom after the walls clear their positions.
  ADR-325 randomises bloom order on every replay, including the two marked plants,
  and schedules the last bloom 2.5 seconds after separation. `.building-landscape`
  handles the shared final fade; individual plants grow once at their scheduled time.
  ADR-327 retains eight-second playback and the original roof pace, accelerating
  only individual plant blooms. ADR-328 starts the first bloom exactly when the
  roof begins separating, with random order and clearance rules preserved.
  ADR-329 restores 700 ms individual growth and packs random bloom starts into
  one second. The two side plants wait until their positions clear during movement.
  ADR-330 starts the logo transition at 4.4 seconds and settles it at 4.96 seconds,
  with landscape fading away just beforehand.
  ADR-331 advances those logo timings to 3.9–4.46 seconds, retaining plant timings.
  ADR-332 adds `.building-wordmark` beneath the settled symbol, revealed after the
  visual transition and shown immediately in the static / reduced-motion fallback.
  ADR-333 centres only this brand wordmark across the mark and slows its fade to
  1.44 seconds; body copy remains flush left.
  ADR-334 applies material-shadow tokens to the roof / wall photographs and
  plants only; keep the final mark and wordmark free of shadows.

- The public information page (ADR-343), `resources/views/public/about.blade.php`, uses
  `.about-shell`, `.about-layout`, `.about-contents`, `.about-section`, `.about-card-grid`,
  `.about-definitions` and `.about-questions` in `indecs.css`. It scrolls naturally,
  with section navigation and native FAQ details. Explain the system, departments,
  roles and access; distinguish available services from planned public capabilities.

- eOSC screens (`resources/views/osc/`) use `.agenda-list`, `.agenda-row`, `.agenda-row-number`,
  `.agenda-row-body`, `.agenda-row-meta`, `.agenda-row-actions`, `.inline-form` and `.panel-stack`
  in `indecs.css`; rows collapse to one column on phones.

- Map mode: a page that sets `@section('body-class', 'map-mode')` (the eOSC meeting map) fills the space under the topbar
  and beside the sidebar with the map; everything else floats over it. Its `[data-header-toggle]` arrow tab collapses the topbar
  with a height transition (`html[data-header="collapsed"]`, `localStorage` key `indecs.header`, applied by `theme.js`).
- The eOSC meeting map (`resources/views/osc/map.blade.php`, `public/js/gis-map.js`, `public/js/osc-map.js`, OpenLayers
  vendored in `public/vendor/openlayers/`, version in `VERSION`) uses `.map-page`, `.map-layout`, `.map-stage`, `.map-canvas`,
  `.header-edge-toggle` (navy arrow tab centred on the map's top edge that collapses the topbar), `.map-title` (floating top-centre meeting title with the MBSP crest, linking back to the meeting), `.map-panel` / `.map-sheet` (floating list from 768px with `.map-panel-head`, `.map-panel-collapse` folding `.map-panel-body` to its heading; bottom sheet below 768px), `.map-sheet-toggle`, `.map-tools` (floating icon toolbar at the top right of the map: measure distance, measure area, compare, zoom to site; the measurement shows beside it in `.map-measure-output`),
  `.map-measure-output`, `.map-layers` (floating at the bottom left of the map: `.map-layers-toggle` icon button opens `.map-layers-panel`, which fades and rises in; Escape closes it), `.map-layer-list`, `.map-layer-row` (with `.map-layer-grip` drag handle and `.map-layer-move` up/down buttons on every layer except base maps, which sit under a `.map-layer-group` "Peta asas" heading), `.map-item-list`, `.map-item-button` (Bil. in `.map-item-bil`, then `.map-item-body`: type | paper reference, title, developer; with `.map-item-muted` and a `.status-badge` for items not yet accepted), `.map-layout-toggle`
  (the per-application "Lihat pelan susun atur" button in the map popup; `aria-pressed` marks the one plan shown), `.map-notice`,
  `.map-swipe` and `.map-popup` in `indecs.css`. Map colours are read from CSS custom properties at runtime in JS, never
  hex; the page config JSON (`#osc-map-config`) carries no item data, which comes only from `osc.meetings.map-data`.
- Layout plan positioning (`resources/views/osc/layout.blade.php`, control-points mode in `public/js/gis-draw.js`, served by
  `App\Services\Osc\LayoutImageService`) uses `.layout-editor`, `.layout-editor-status`, `.layout-editor-panes`,
  `.layout-editor-image`, `.layout-editor-map`, `.layout-editor-wrap` and `.layout-marker` in `indecs.css`. The server recomputes
  the corners from the three submitted point pairs; the browser preview is only a guide. Map features carry `layout_url` and
  `layout_corners` (TL, TR, BR, BL, EPSG:4326) only for layouts the user may see.
- Boundary drawing (`resources/views/partials/gis-boundary-field.blade.php`, `public/js/gis-draw.js`) uses
  `.boundary-field`, `.boundary-field-head`, `.boundary-field-toolbar`, `.boundary-field-area` and `.boundary-field-stage`
  in `indecs.css`. It is optional: the hidden input stays empty without JavaScript. Boundaries are validated and
  repaired by `App\Services\Gis\BoundaryService` (PostGIS) and stored as MultiPolygon 4326; never interpolate GeoJSON into SQL.

- Bahasa Melayu is the interface language (`lang/ms`). Application type codes stay as-is.
- Reuse the existing class names in `public/css/indecs.css` before inventing new ones. If you
  need a new component, add it to that file in the same idiom and document it here.
- Keep the audit and history model intact: never overwrite a stage or status without writing
  a history row.
- Keep the module boundary intact: department fields live in extension tables, never on
  `core.applications`. Cross-module side effects go through domain events.
- Decisions are written before code, in `docs/adr/`.

- Authenticated homepage application selection (ADR-344) uses `.application-dialog`,
  `.application-dialog-head`, `.application-grid`, `.application-tile` and
  `.application-icon`. Only authorised applications link to existing routes.
  ADR-345 centres chooser text/icons, removes tile action/status captions, adds a
  token-based hover scale and shares the login dialog's origin animation/backdrop.
  This is an owner-approved alignment exception. Planned entries remain unlinked.
  The homepage logout uses the shared secondary button. The root is `home`;
  the existing `dashboard` route is at `/dashboard`. Homepage password login opens
  the chooser; intended protected links keep their destination. Use
  `staff:superadmin <staff_number>` for explicit audited promotion to the existing
  unscoped `system_admin` role. Never infer privileges from a staff number at login.

## Independent AMIRA backend (2026-11-04)

AMIRA backend ownership was moved to C:\Users\Asyra\Documents\AMIRA under ADR-353 (2026-11-04). It is an independent prepared-guidance/catalogue service; conversation, model inference and record retrieval remain unimplemented. AIRS retains its review AiProvider and UI/assets. iNDECS keeps local guidance with no new remote dependency. Future assistant APIs belong to AMIRA, with scoped source APIs and iNDECS identity; free applicant guidance stays local and uses no paid AI.

## Public portal preparation (2026-11-04)

Public preparation drafts are implemented under ADR-358; see the
[portal runbook](docs/runbooks/public-portal.md). The authenticated `/portal`
workspace uses `layouts/portal.blade.php`, a dashboard, private drafts and
`/portal/account`. Verified public and email-link guest accounts can save common
draft fields. Scope every draft query to its owner. `core.applicant_drafts` is
separate from official applications; each save records an immutable snapshot in
`core.applicant_draft_revisions` with optimistic version checks. No formal
application, payment, acceptance or AIRS dispatch is created. Document upload,
precheck and formal submission remain future increments. `.portal-overview`,
`.portal-columns`, `.portal-summary`, `.portal-count`, `.portal-section-head`,
`.portal-draft-list`, `.portal-journey` and `.portal-pagination` extend shared
cards/forms in `indecs.css`.

ADR-359 supersedes the public navy sidebar with the owner-requested two-choice
entrance: Permohonan baru / Semak Permohonan. `layouts/portal.blade.php` uses
`.portal-header`, `.portal-settings` and `.portal-main`; the homepage uses
`.portal-entrance`, `.portal-welcome`, `.portal-choices`, `.portal-choice` and a
raised `.portal-status` panel. `.portal-dialog` / `.portal-type-list` provide
native popup choices with `public/js/portal.js`, keyboard focus return and a
no-JavaScript fallback. Keep draft records separate from received applications;
formal public intake and applicant access to official records remain unavailable.
The gear menu offers account, helpdesk availability, local guidance and appearance.
Staff sidebars are unchanged. All colours and spacing continue to use tokens.

ADR-361 supersedes that entrance with the Kaunter Maya counter. `portal.home` and
the draft editor declare `@section('counter')` and render `x-portal-counter`:
`partials/amira-counter` (navy, persistent, local guidance only, `#amira-counter`)
beside `public/partials/file-board` (Fail saya). `App\Services\Portal\PortalBoard`
builds the owner-scoped sections, AMIRA to-dos and `App\Enums\AmiraMood`. Counter
pages omit the drawer, and the shared guidance engine in `indecs.js` serves both.
The phone layout uses `.amira-counter-bar` plus a sheet driven by `portal.js`.
`public/js/amira-bubble.js` animates `[data-amira-bubble]` from token colours only.
Its gradient/glow is the only permitted exception.

ADR-365 refines the canvas avatar to a fluid uneven body, faint mist, one tilted
orbiting voice wave and at most six small bubbles that bud, drift and pop at
varied intervals. All effects stay inside the avatar canvas. Response activity
animates the orbit via `AmiraBubble.setSpeaking` while the reply appears;
this is local guidance feedback, not audio or live AI. Durations and avatar
sizes are tokens. Offscreen/hidden avatars pause; reduced motion draws a still
frame without satellite bubbles. Compact phone-bar avatars omit satellites.

ADR-366 centres the avatar, heading and subtitle within `.amira-counter-identity`
(an owner-approved text-alignment exception) and removes the wave extension
toward the conversation. The response wave stays around the bubble.

ADR-367 adds fast text reveal and a gentle enlargement/fade for each requested
local-guidance exchange, shared by the drawer and counter through
`animateAmiraReply` in `indecs.js`. Reserve final text height during reveal;
cancel prior reveals on a new request. `aria-busy` defers live-region output
until the complete reply. Reduced motion and hidden pages finish immediately.
Use the `--duration-amira-character`, `--duration-amira-reply-max`,
`--duration-amira-message-enter` and `--scale-amira-message-enter` tokens.

ADR-360 adds a sibling `.portal-type-download` icon to each `.portal-type-row`.
It opens a type-labelled `.portal-download-dialog` above the chooser, with two
disabled `.portal-download-options` (Senarai semak / Garis panduan), marked
Belum tersedia. Documents will be configured later; do not invent download URLs
or repurpose internal catalogue files. Closing returns focus to the download icon.

## Department library and checklist rules (2026-11-05)

[ADR-362](docs/adr/ADR-362-department-library-and-checklist-rules.md); see the
[runbook](docs/runbooks/department-library.md).

- **Library.** All staff read `/library`. Only a `penolong_pengarah` of the
  same department changes it, checked by `User::canManageLibrary`, which also
  requires the role. Write only through `App\Services\Library\LibraryService`:
  PDF, at most 50 MB, private `local` disk, audited. Versions are append-only
  by trigger. Retiring is final.
- **Portal.** It offers only `LibraryDocument::publicFor($type)` and serves only
  the current version of a public, active document. Anything else returns 404.
  Never expose internal documents.
- **Checklist rules.** Rules are the Bangunan catalogue (`document_requirements`
  plus `conditions`/`guidance`), published by `system_admin` only. Option keys
  live in `config/kaunter.php`; never rename or remove a key that published
  rules use. `ChecklistRuleEvaluator` is deterministic: a set condition must
  match and an unknown particular never matches. The payment gate in later
  steps must use it, never AI output.
- `LibraryDocument::forReference($dept, $type)` is the sanctioned input for
  AIRS and AMIRA reference.

## Free applicant pre-check (2026-11-05)

[ADR-363](docs/adr/ADR-363-free-precheck.md); see the
[worker runbook](docs/runbooks/precheck-worker.md).

- **Uploads.** Draft uploads go only through
  `App\Services\Precheck\DraftDocumentService`, which checks the owner, the
  checklist item, the document-type limits and a `finfo` content sniff, then
  runs the ClamAV scan (fail closed), dedupes by SHA-256, and dispatches
  `PrecheckDraftDocument` to the `precheck` queue. Overlapping checks of one
  draft wait (`WithoutOverlapping`) and are never dropped, and a failed check
  falls back to "Perlu pengesahan pemohon".
- **Text and OCR.** Use only `App\Contracts\TextExtractor` (`LocalTextExtractor`:
  pdftotext, then Ghostscript plus Tesseract `msa+eng`). Never add a cloud or
  AI path here. Missing tools mean "Perlu pengesahan pemohon". Never store
  extracted text.
- **Labels.** Use only `App\Enums\PrecheckLabel` and show the contract
  sentence. No pass, fail or score wording.
- **Readiness.** `DraftChecklist::for($draft)['ready']` is the single
  readiness signal that payment (step 5) must use.

## AMIRA conversation (2026-11-06)

ADR-369 and `docs/runbooks/amira-chat.md`.

- Conversation logic lives in the AMIRA backend (stateless). iNDECS stores the conversations, enforces the limits
  and pushes a minimised context.
- AMIRA may receive the signed-in PSP's own account and drafts (`ContextBuilder`). Never send credentials, staff
  fields, document contents or another user's data.
- AMIRA never changes state. Proposals are cards the PSP applies and saves; re-validate anything AMIRA proposes
  with `ProposalSanitiser`.
- Every failure becomes `FallbackGuidance` ("Panduan asas"); never leave a question pending.
- Label AI replies "Jawapan AI". Keep the officer line for outcome words.
- Keep `AMIRA_ENABLED=false` in tests and fake `Http` for the AMIRA client.

## AIRS connector (2026-11-04)

The shared service connector, review workspace and payment/version guards are implemented under [ADR-354](docs/adr/ADR-354-airs-review-connector.md); see the [integration runbook](docs/runbooks/airs-integration.md). No public paid intake or departmental review rule pack is activated by this increment. AMIRA remains separate.

## Opening landscape material (ADR-381, 2026-11-12)

The architectural introduction adds `.building-garden`: natural grass inside the lower-right circular landscape element, with a stone walking path along its arc. SVG clips/masks follow the approved logo footprint; the path is wider inward during the material phase for legibility. It fades into the unchanged shared logo at the existing symbol transition. Preserve existing planting and timing. Reduced motion hides the material garden and shows the final mark. The lawn and stone texture join the existing asset decode/failure fallback. Provenance is in `public/img/brand/README.md`.

ADR-382 refines the opening garden with `.building-garden-edging`, `.building-garden-joints` and `.building-garden-contact`: narrow textured stone borders within the existing footprint, fine paving joints and a restrained grass contact seam. They share the garden animation; the final mark and timing stay unchanged.
