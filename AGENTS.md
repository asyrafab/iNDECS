# AGENTS.md — iNDECS

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
  units registry and domain events are built in `core`, generically, in the order Bangunan
  needs them. Department modules are extension tables plus configuration.
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

- Do not add gradients, glows, blur, neon, holographic or decorative panels.
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

- `public/img/indecs-mark.svg` — the mark (three offset squares). Recolour the leading
  square to the teal accent when the tokens change; the geometry stays.
- `public/img/indecs-mark-reversed.svg` — for dark grounds
- `public/img/indecs-lockup.svg` — mark + wordmark + BM name
- `public/img/mbsp.png` — the MBSP crest. Crest always LEADS, then a rule, then iNDECS. Use
  on login, letterhead, printed approvals — not in the app header.

The mark's three squares double as the relationship indicator: one square = standalone
application, two = linked pair, three = development case.

## Working rules

- Public identity pages use `.public-header`, `.public-brand`, `.public-shell`,
  `.public-tabs`, `.public-form`, `.public-section` and `.public-details` in
  `indecs.css`. They are unframed account surfaces using the shared tokens.

- Bahasa Melayu is the interface language (`lang/ms`). Application type codes stay as-is.
- Reuse the existing class names in `public/css/indecs.css` before inventing new ones. If you
  need a new component, add it to that file in the same idiom and document it here.
- Keep the audit and history model intact: never overwrite a stage or status without writing
  a history row.
- Keep the module boundary intact: department fields live in extension tables, never on
  `core.applications`. Cross-module side effects go through domain events.
- Decisions are written before code, in `docs/adr/`.
