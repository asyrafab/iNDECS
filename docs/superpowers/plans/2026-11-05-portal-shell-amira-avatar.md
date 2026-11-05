# Portal Shell and AMIRA Avatar (Kaunter Maya, step 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two-choice public portal entrance with the Kaunter Maya
split layout: AMIRA (with the animated bubble avatar) on the left and the
PSP's "Fail saya" on the right. The draft editor and the phone status view
use the same layout. It runs over the existing preparation drafts only.

**Architecture:** A small `PortalBoard` service turns the owner's drafts and
account state into sections, AMIRA to-dos and an AMIRA mood. A Blade
component `<x-portal-counter>` renders the split, with a persistent
`partials/amira-counter` panel on the left. A self-contained canvas script,
`public/js/amira-bubble.js`, animates any `[data-amira-bubble]` element from
its `data-mood` and `data-speaking` attributes. It reads colours from CSS
tokens. AMIRA stays local guidance only: there is no AI call in this step.

**Tech Stack:** Laravel 12 / PHP 8.3, Blade, plain CSS in `public/css/indecs.css`
plus `tokens.css`, vanilla JS (no build step for portal scripts), PHPUnit
feature tests on PostgreSQL (`composer test`, DB on port 5434 via docker).

**Spec:** `docs/superpowers/specs/2026-11-05-public-portal-kaunter-maya-design.md`
(sections 4, 5, 7, and 9 step 1). Avatar reference implementation:
`.superpowers/brainstorm/47-1793782350/content/amira-emotion.html`.

## Global Constraints

- Every colour, font, spacing, radius, duration and easing comes from `public/css/tokens.css`. No hex values in CSS or JS. JS reads colours with `getComputedStyle(document.documentElement).getPropertyValue('--token')`.
- Theme differences are tokens only. Never `[data-theme="dark"] .component`.
- Bahasa Melayu interface text; Lucide icons via `<x-icon>`; no emoji.
- Reduced motion (`prefers-reduced-motion` or `html[data-motion="reduced"]`) means the avatar draws one still frame and does not animate.
- AMIRA remains labelled local guidance. No external or LLM calls, no fabricated statuses, no automatic submissions. Footer copy stays "AMIRA membantu. Pegawai memutuskan."
- Drafts are never presented as received applications. Each draft row carries a "Draf" badge and "Belum dihantar kepada MBSP".
- Every draft query is scoped with `ApplicantDraft::query()->ownedBy($user)`.
- The header brand stays iNDECS · Portal Awam · MBSP. AMIRA is an officer inside the page, not the system brand.
- Gradient and glow are allowed **only** inside the AMIRA avatar canvas (owner-approved exception recorded in ADR-361, Task 6).
- Work starts from the current working tree. The portal files (`PublicPortalController`, drafts) are not yet committed on `feat/eosc-map`, so branch from the current state on a new branch `feat/kaunter-maya-shell`. Do not use a clean worktree from `main`.

## Review Focus

1. **Unverified account:** the home page still renders. AMIRA shows the "Sahkan emel anda" to-do with mood `alert`, and Permohonan baharu shows the verify notice rather than type links. Test in Task 3.
2. **No drafts at all:** Fail saya shows an inviting empty state with the new-application action, not empty section headings. Test in Task 3.
3. **Another applicant's drafts:** they never appear in Fail saya, the AMIRA to-dos or the counts. Test in Task 1 (service) and Task 3 (page).
4. **No JavaScript or reduced motion:** the avatar shows a static token-coloured shape, and everything on the page still works. Test in Task 4 (markup fallback) plus a manual check.
5. **Long titles and names, narrow phone width:** text wraps, there is no horizontal scroll, and the AMIRA sheet opens and closes with focus return. Manual check in Task 5.

---

### Task 1: PortalBoard service and AmiraMood

**Files:**
- Create: `app/Enums/AmiraMood.php`
- Create: `app/Services/Portal/PortalBoard.php`
- Test: `tests/Feature/Portal/PortalBoardTest.php`

**Interfaces:**
- Produces:
  - `enum App\Enums\AmiraMood: string { case Calm = 'calm'; case Happy = 'happy'; case Think = 'think'; case Concern = 'concern'; case Alert = 'alert'; }`
  - `final class App\Services\Portal\PortalBoard` with `public function for(User $user, bool $justSaved = false): array` returning
    `['sections' => ['action' => Collection, 'active' => Collection, 'closed' => Collection], 'draftCount' => int, 'todos' => list<array{text: string, href: string}>, 'mood' => AmiraMood, 'greeting' => string]`.
  - `active` holds the owner's drafts (with `applicationType`), newest `updated_at` first, limit 20. `action` and `closed` are empty collections in this step (formal files arrive in later steps).

- [ ] **Step 1: Write the failing tests**

```php
public function test_unverified_user_gets_verify_todo_and_alert_mood(): void
{
    $user = $this->applicant(verified: false);
    $board = app(PortalBoard::class)->for($user);
    $this->assertSame(AmiraMood::Alert, $board['mood']);
    $this->assertSame('Sahkan emel anda untuk mula menyediakan permohonan.', $board['todos'][0]['text']);
    $this->assertSame(route('portal.account'), $board['todos'][0]['href']);
}

public function test_verified_user_with_drafts_is_calm_and_sees_only_own_drafts(): void
{
    // owner has 2 drafts, another applicant has 1
    $board = app(PortalBoard::class)->for($owner);
    $this->assertSame(AmiraMood::Calm, $board['mood']);
    $this->assertSame(2, $board['draftCount']);
    $this->assertCount(2, $board['sections']['active']);
    $this->assertTrue($board['sections']['active']->every(fn ($d) => $d->user_id === $owner->id));
    $this->assertSame([], $board['todos']);
}

public function test_just_saved_makes_amira_happy(): void
{
    $this->assertSame(AmiraMood::Happy, app(PortalBoard::class)->for($verifiedUser, justSaved: true)['mood']);
}

public function test_greeting_names_the_user(): void
{
    $this->assertSame('Selamat datang, Ar. Lim.', app(PortalBoard::class)->for($userNamedArLim)['greeting']);
}
```

Reuse the `applicant()` and department/type setup from `tests/Feature/PublicPortalTest.php` (extract them into a trait `tests/Feature/Portal/BuildsApplicants.php` with `applicant(string $email = 'applicant@example.test', bool $verified = true, string $name = 'Pemohon Ujian'): User`, `seedPortalTypes(): void` and `data(): array`, and use it from all portal test classes).

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=PortalBoardTest`
Expected: FAIL, "Class App\Services\Portal\PortalBoard not found"

- [ ] **Step 3: Implement `AmiraMood` and `PortalBoard::for()`**

Mood rule: `Alert` if any to-do exists, else `Happy` if `$justSaved`, else `Calm`.

- [ ] **Step 4: Run to verify they pass**

Run: `php artisan test --filter="PortalBoardTest|PublicPortalTest"`
Expected: PASS (PublicPortalTest unchanged and still green after the trait extraction)

- [ ] **Step 5: Commit**

```bash
git add app/Enums/AmiraMood.php app/Services/Portal tests/Feature/Portal tests/Feature/PublicPortalTest.php
git commit -m "feat(portal): PortalBoard sections, AMIRA to-dos and mood"
```

---

### Task 2: Counter layout and persistent AMIRA panel

**Files:**
- Create: `resources/views/components/portal-counter.blade.php`
- Create: `resources/views/partials/amira-counter.blade.php`
- Modify: `resources/views/layouts/portal.blade.php` (do not include the drawer `partials.amira` on pages that render the counter; add `amira-bubble.js` script tag, `defer`)
- Modify: `public/css/indecs.css` (new `.counter*` and `.amira-counter*` block after the existing `.portal-*` rules)
- Modify: `public/css/tokens.css` (tokens below)
- Test: `tests/Feature/Portal/CounterLayoutTest.php`

**Interfaces:**
- Consumes: `PortalBoard::for()` array (Task 1).
- Produces:
  - `<x-portal-counter :board="$board">` with a named slot `file` (right side). Renders `<div class="counter">` → `<aside class="counter-amira" aria-labelledby="amira-counter-title">@include('partials.amira-counter')</aside>` + `<section class="counter-file">{{ $file }}</section>`.
  - `partials/amira-counter` markup contract (Tasks 4 and 5 rely on it):
    - `<div class="amira-bubble" data-amira-bubble data-mood="{{ $board['mood']->value }}" data-speaking="false" role="img" aria-label="AMIRA"><canvas></canvas></div>`
    - `<h2 id="amira-counter-title">AMIRA</h2>` with the subtitle `Pegawai Kaunter Maya`
    - Greeting paragraph `{{ $board['greeting'] }}`, then a to-do list `.amira-counter-todos` (one link per to-do), or "Tiada tindakan diperlukan hari ini." when there are none.
    - Prompt chips and composer reuse the existing `data-amira-prompt`, `data-amira-form`, `data-amira-answer*` hooks so `indecs.js` local guidance keeps working. The composer label is "Tanya AMIRA (panduan tempatan)", and a badge reads "Perbualan AI belum tersedia".
    - Footer: `<x-mark class="mark-small" />AMIRA membantu. Pegawai memutuskan.`
- New tokens in `:root` (plus dark values where they differ): `--color-amira-panel-bg: var(--color-sidebar-bg)`, `--color-amira-panel-text: var(--color-sidebar-text)`, `--color-amira-panel-muted: var(--color-sidebar-muted)`, `--color-amira-panel-raised: var(--color-sidebar-hover)`, `--color-amira-highlight: #ffffff` (dark: `#dceaf5`), `--size-amira-bubble: 160px`, `--size-amira-bubble-small: 40px`, `--width-counter-amira: 360px`.

- [ ] **Step 1: Write the failing test**

```php
public function test_home_renders_counter_with_amira_and_file_side(): void
{
    $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
        ->assertSee('class="counter"', false)
        ->assertSee('data-amira-bubble', false)
        ->assertSee('data-mood="calm"', false)
        ->assertSee('Pegawai Kaunter Maya')
        ->assertSee('Perbualan AI belum tersedia')
        ->assertSee('AMIRA membantu. Pegawai memutuskan.')
        ->assertDontSee('id="amira-panel"', false); // drawer not duplicated on counter pages
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=CounterLayoutTest`
Expected: FAIL (no `class="counter"`)

- [ ] **Step 3: Implement the component, partial, tokens and CSS, and pass `$board` from `PublicPortalController::home`**

Desktop (≥ 901px): a two-column grid, `var(--width-counter-amira)` + `1fr`. The AMIRA column uses the `--color-amira-panel-*` ground, is sticky at full height, and stacks the bubble above the conversation. Bubble box size: `var(--size-amira-bubble)`. Static fallback: `.amira-bubble` has a circular `background: var(--color-accent-400)` with `border-radius: var(--radius-full)`, which is hidden once the script adds `.is-live`.

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter="CounterLayoutTest|PublicPortalTest"`
Expected: PASS, except the PublicPortalTest home assertions that Task 3 rewrites. If they fail here, leave them failing and note it in the commit message.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/portal-counter.blade.php resources/views/partials/amira-counter.blade.php resources/views/layouts/portal.blade.php public/css app/Http/Controllers/PublicPortalController.php tests/Feature/Portal/CounterLayoutTest.php
git commit -m "feat(portal): Kaunter Maya split layout with persistent AMIRA panel"
```

---

### Task 3: "Fail saya" home and draft editor in the counter

**Files:**
- Modify: `resources/views/public/home.blade.php` (replace `.portal-entrance` with `<x-portal-counter>`. Keep the `portal-new` and `portal-download-*` dialogs. Delete the `portal-check` dialog.)
- Create: `resources/views/public/partials/file-board.blade.php`
- Modify: `resources/views/public/drafts/edit.blade.php` (wrap in `<x-portal-counter>`; the right side shows a "Fail anda" summary above the existing form)
- Modify: `app/Http/Controllers/PublicPortalController.php` (`home`, `create`, `edit` pass `board`; `edit` passes `justSaved: session()->has('status')`)
- Modify: `resources/views/layouts/portal.blade.php` (gear menu: "Draf saya" becomes "Fail saya", linking to `portal.home`)
- Modify: `tests/Feature/PublicPortalTest.php`

**Interfaces:**
- Consumes: `<x-portal-counter>`, `PortalBoard::for()`.
- Produces: file-board markup. `<h1>Fail saya</h1>` with a count, a primary `Permohonan baharu` link (`href="#portal-new" data-portal-open="portal-new"`), then one `<section class="file-section" data-section="action|active|closed">` per **non-empty** section. Headings: "Perlu tindakan anda", "Draf belum dihantar", "Selesai / Ditutup". Rows are `.file-row` with the title link, type name, a `status-badge status-pending` reading "Draf · {code}", the stage text "Persediaan · Belum dihantar kepada MBSP" and a "Sambung" link. A "Semua draf" link to `portal.drafts.index` appears when `draftCount > 20`.

- [ ] **Step 1: Update and add the failing tests in `PublicPortalTest`**

```php
// replaces the Semak Permohonan assertions in test_application_chooser_filters_types_and_prefills_only_an_available_type
->assertSee('Fail saya')->assertSee('Permohonan baharu')
->assertDontSee('Semak Permohonan')->assertDontSee('id="portal-check"', false)
->assertSee(route('portal.drafts.create', ['type' => 'PKM']), false)

public function test_empty_board_shows_invitation_not_empty_sections(): void
{
    $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
        ->assertSee('Belum ada fail')->assertDontSee('data-section="active"', false);
}

public function test_unverified_home_shows_alert_amira_and_verify_notice(): void
{
    $this->actingAs($this->applicant(verified: false))->get(route('portal.home'))->assertOk()
        ->assertSee('data-mood="alert"', false)
        ->assertSee('Sahkan emel anda untuk mula menyediakan permohonan.')
        ->assertSee('Sahkan emel anda sebelum memulakan draf.');
}

public function test_draft_rows_are_labelled_as_unsent_drafts(): void
{
    // after storing a draft
    $this->get(route('portal.home'))->assertSee('data-section="active"', false)
        ->assertSee('Draf · PKM')->assertSee('Belum dihantar kepada MBSP');
}

public function test_draft_editor_uses_counter_and_amira_is_happy_after_save(): void
{
    // post store → follow redirect
    $this->followingRedirects()->post(route('portal.drafts.store'), $this->data())
        ->assertSee('class="counter"', false)->assertSee('data-mood="happy"', false);
}
```

The existing privacy test (`test_drafts_are_private_on_dashboard_list_read_and_update`) stays unchanged and must pass.

- [ ] **Step 2: Run to verify the new and changed tests fail**

Run: `php artisan test --filter=PublicPortalTest`
Expected: FAIL on the new assertions

- [ ] **Step 3: Implement the home, file-board, editor wrap and controller changes**

Empty state copy: heading "Belum ada fail", body "Ceritakan kepada AMIRA apa yang anda rancang, atau mulakan permohonan baharu.", and the Permohonan baharu button.

- [ ] **Step 4: Run the whole suite**

Run: `composer test`
Expected: PASS (all suites green)

- [ ] **Step 5: Commit**

```bash
git add resources/views app/Http/Controllers/PublicPortalController.php tests/Feature/PublicPortalTest.php public/css/indecs.css
git commit -m "feat(portal): Fail saya home and draft editor inside Kaunter Maya"
```

---

### Task 4: AMIRA bubble avatar

**Files:**
- Create: `public/js/amira-bubble.js`
- Modify: `public/css/indecs.css` (`.amira-bubble`, `.amira-bubble.is-live`, `.amira-bubble canvas`)
- Test: `tests/Feature/Portal/AmiraBubbleMarkupTest.php`

**Interfaces:**
- Consumes: the `[data-amira-bubble]` markup contract from Task 2.
- Produces:
  - `window.AmiraBubble = { setMood(el: Element, mood: 'calm'|'happy'|'think'|'concern'|'alert'): void, setSpeaking(el: Element, on: boolean): void }`. Steps 3 to 4 of the overall project call these when chat arrives. Setting the attributes directly also works: the script observes `data-mood` and `data-speaking` with a `MutationObserver`.
  - Mood → token map (body light / mid / deep, ring):
    - calm: `--color-accent-200` / `--color-accent-400` / `--color-accent-600`, ring `--color-accent-400`
    - happy: `--color-accent-100` / `--color-accent-300` / `--color-accent-500`, ring `--color-accent-300`
    - think: `--status-ai-bg` / `--status-ai` / `--status-ai-text`, ring `--status-ai`
    - concern: `--color-neutral-200` / `--color-neutral-500` / `--color-neutral-700`, ring `--color-neutral-500`
    - alert: `--status-attention-bg` / `--status-attention` / `--status-attention-text`, ring `--status-attention`
    - highlight: `--color-amira-highlight`

- [ ] **Step 1: Write the failing markup test (fallback and wiring)**

```php
public function test_bubble_has_static_fallback_and_script(): void
{
    $html = $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()->getContent();
    $this->assertStringContainsString('role="img" aria-label="AMIRA"', $html);
    $this->assertStringContainsString(asset('js/amira-bubble.js'), $html);
    $this->assertMatchesRegularExpression('/data-mood="(calm|happy|think|concern|alert)"/', $html);
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=AmiraBubbleMarkupTest`
Expected: FAIL (script not referenced)

- [ ] **Step 3: Port the reference animation into `public/js/amira-bubble.js`**

Port from `amira-emotion.html`, keeping its behaviour (spec §5):
- The uneven body outline uses `lumpAt(a, t, seed) = 0.5·sin(2a + 1.1t + seed) + 0.35·sin(3a − 0.7t + 2·seed) + 0.15·sin(5a + 1.9t)`.
- Per-mood motion parameters, copied from the reference `MOODS` table: `lump`, `spd`, `sx`, `sy`, `dy`, `bounce`, `lean`, `nudge`. Colours come from the token map above, not from the reference RGB values.
- Three horizontal rings at radius ×1.55, ×1.85 and ×2.15, with flatness 0.24. The back halves are drawn before the body and the front halves after it. Back halves use 0.4 × the opacity of the front halves.
- Quiet: a gentle ripple of amplitude 1.5. Speaking: a sound-wave shape whose envelope is `|cos a|^0.6`, eased in and out over about 200 ms.
- Moods blend by easing every parameter towards the target (factor `min(1, dt·3)`).
- The canvas is sized to its box × `devicePixelRatio` and redrawn on resize.
- Token colours are re-read when `data-theme` on `<html>` changes (MutationObserver).
- Reduced motion draws one frame at t = 0 and stops. Animation pauses when `document.hidden` is true.
- On init, add `.is-live` to the bubble.

- [ ] **Step 4: Run the test, then check in the browser**

Run: `php artisan test --filter=AmiraBubbleMarkupTest` (expected: PASS).
Then start the app (`php artisan serve`, or the `run` skill). Open `/portal` as a verified test applicant and check:
- the bubble animates;
- `AmiraBubble.setMood(document.querySelector('[data-amira-bubble]'), 'think')` in the console turns it purple and faster;
- `setSpeaking(..., true)` makes the rings wave;
- with dark theme toggled, the colours stay correct;
- with `document.documentElement.dataset.motion='reduced'` set and the page reloaded, there is a still frame and no animation;
- with JS disabled, there is a static teal circle.

- [ ] **Step 5: Commit**

```bash
git add public/js/amira-bubble.js public/css/indecs.css resources/views/layouts/portal.blade.php tests/Feature/Portal/AmiraBubbleMarkupTest.php
git commit -m "feat(portal): animated AMIRA bubble with mood and voice rings"
```

---

### Task 5: Phone layout and AMIRA sheet

**Files:**
- Modify: `public/css/indecs.css` (`@media (max-width: 900px)` rules for `.counter`)
- Modify: `resources/views/partials/amira-counter.blade.php` (add the phone bar)
- Modify: `public/js/portal.js` (sheet open/close)
- Test: `tests/Feature/Portal/CounterLayoutTest.php`

**Interfaces:**
- Consumes: the counter markup (Tasks 2 and 3), `AmiraBubble`.
- Produces: `<button class="amira-counter-bar" type="button" data-amira-sheet-open aria-controls="amira-counter" aria-expanded="false">` containing a small bubble (`data-amira-bubble`, size `--size-amira-bubble-small`), the to-do count and the text "Tanya AMIRA". The panel root gets `id="amira-counter"`, and the close button is `data-amira-sheet-close`.

Below 901px: Fail saya comes first and fills the width. The AMIRA column is hidden behind the fixed bottom bar. Tapping the bar opens the panel as a full-height sheet. Escape and the close button close it, and focus returns to the bar. The draft editor is still allowed on the phone (it is text only). Spec §4.3's no-upload rule applies from step 3 onwards.

- [ ] **Step 1: Write the failing test**

```php
public function test_counter_has_phone_bar_controlling_amira_sheet(): void
{
    $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
        ->assertSee('data-amira-sheet-open', false)
        ->assertSee('aria-controls="amira-counter"', false)
        ->assertSee('Tanya AMIRA');
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=CounterLayoutTest`
Expected: FAIL

- [ ] **Step 3: Implement the bar, CSS and sheet JS**

- [ ] **Step 4: Run the tests, then check in the browser at 375×812**

Run: `php artisan test --filter=CounterLayoutTest` (expected: PASS). Then check in the browser pane with the mobile preset:
- no horizontal scroll with a 500-character draft title and a long user name;
- the bar opens the sheet;
- Escape closes it and focus returns to the bar;
- the bubble animates in the bar and in the sheet.

- [ ] **Step 5: Commit**

```bash
git add public/css/indecs.css public/js/portal.js resources/views/partials/amira-counter.blade.php tests/Feature/Portal/CounterLayoutTest.php
git commit -m "feat(portal): phone layout with AMIRA bottom bar and sheet"
```

---

### Task 6: Decision record and documentation

**Files:**
- Create: `docs/adr/ADR-361-kaunter-maya-portal-and-amira-avatar.md`
- Modify: `docs/adr/README.md` (index line)
- Modify: `AGENTS.md`: in "Public portal preparation", replace the ADR-359 paragraph's entrance description with the counter layout. In "Do not", add the avatar exception: "except the AMIRA avatar canvas (ADR-361)".
- Modify: `docs/DESIGN-DIRECTION.md` (new dated section: counter layout, AMIRA panel tokens, bubble avatar, mood → token map)
- Modify: `docs/runbooks/public-portal.md` (how the home and phone views work; how to test moods from the console)
- Modify: `.gitignore` (add `.superpowers/`)

ADR-361 content:
- Context: the programme owner's Kaunter Maya design (link to the spec).
- Decision:
  - Supersede ADR-359's two-choice entrance with the counter: AMIRA left, Fail saya right.
  - The bubble avatar is an owner-approved exception to the no-gradient/no-glow rule, limited to the avatar canvas.
  - AMIRA is presented as "Pegawai Kaunter Maya" inside the page; the brand remains iNDECS · MBSP.
- Boundaries: local guidance only, drafts are not applications, no new data.
- Validation: the tests from Tasks 1 to 5.

- [ ] **Step 1: Write the ADR and the documentation updates**
- [ ] **Step 2: Run `composer test`**. Expected: PASS
- [ ] **Step 3: Commit**

```bash
git add docs AGENTS.md .gitignore
git commit -m "docs: ADR-361 Kaunter Maya portal shell and AMIRA avatar"
```
