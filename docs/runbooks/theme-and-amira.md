# Calm Intelligence workspace

Implemented under ADR-311, 2026-10-28.

## Appearance

The shared stylesheet covers staff login, public access, Perancang, operational
Bangunan and read-only legacy lookups. Google Sans loads from Google Fonts;
Segoe UI/system-ui remain available without the font service. The navy sidebar
stays consistent across departments, with one shared teal accent.

Terang, Gelap and Sistem persist per browser. The accessibility button toggles
reduced motion; the OS preference also suppresses animations. Browser storage
being disabled does not prevent the interface or controls from working. Old red,
green, blue and amber accent preferences are no longer used.

Tokens are in `public/css/tokens.css`; components remain in `public/css/indecs.css`.
`<x-icon>` uses the local `public/img/lucide.svg` sprite from lucide-static 1.62.0.
Its ISC/upstream licences are retained in `public/vendor/lucide-LICENSE.txt`.
There is no icon CDN or required frontend build for these screens.

## AMIRA availability

The AMIRA button opens a context panel. Desktop staff pages keep the panel beside
the content and remember its open state for the session. Mobile/public/login
surfaces use a dismissible dialog. Escape closes it and returns focus to its
opener. Mobile navigation has a separate close button and keyboard containment.

Current responses are explicitly **local page guidance**, using visible labels,
permitted actions and the authorised route-bound application. No conversation is
sent to the independent AMIRA backend, AIRS or another model. No business data is changed by the assistant.
Free-text questions outside its supported guidance receive an availability
explanation rather than a simulated AI answer. The UI must state that live AMIRA
conversation is unavailable until a real connection is implemented. AIRS document-review availability is a separate capability.

To add live conversation, implement the versioned, authenticated independent AMIRA client as separate
work, with scoped iNDECS/AIRS source access; ground answers in authorised evidence, retain officer confirmation and
audit, and provide an unavailable-service fallback. An availability label alone
must never be changed to imply that an integration is working.

## Verification

Run `php vendor/bin/pint --test`, `php artisan test`, `php artisan view:cache`,
`node --check public/js/theme.js` and `node --check public/js/indecs.js`.
Tests use the guarded `indecs_test` PostgreSQL database.

For the ADR-365 avatar, also run `node --test tests/Js/amira-bubble.test.cjs`
and `node --check public/js/amira-bubble.js`. Check the fluid body, mist, orbit
and random bubble release in both themes, plus compact phone-bar rendering.
The response wave is a brief local-guidance animation, not audio playback.
Reduced motion must show a still frame without satellite bubbles; hidden and
offscreen avatars must pause without building up release bursts on return.

For ADR-367 replies, run `node --test tests/Js/amira-reply.test.cjs`. Check fast
typing and box enlargement in both drawer and counter. Repeated prompts must
replace the active reveal cleanly. Reduced motion (including a live preference
change) and hidden pages must complete replies immediately. Screen readers get
the final polite announcement rather than each character.

`pint.json` excludes generated `output/` artefacts and extraction backups from source formatting. Application code, routes, tests and configuration remain checked.

`WorkspacePresentationTest` verifies navigation permissions, authorised record
context, public identity rendering and unchanged business history during reads.
For local visual review only, set `INDECS_CAPTURE_THEME=1` when running that test;
it writes rendered disposable fixtures to the ignored `.tools/theme-ui/` folder.
Inspect light/dark desktop layouts, 390px forms, long document names, assistant
guidance, keyboard dismissal and reduced motion. Never use production records
or alter the normal application database to obtain screenshots.

## Integrated applicant assistance policy (2026-11-04)

[ADR-352](../adr/ADR-352-indecs-airs-submission-and-precheck.md) limits applicant precheck AMIRA to prepared/local guidance with no paid generative AI or cloud processing. Explain checklist gaps and local extraction/OCR suggestions; do not assess technical compliance or show approval/rejection scores. Formal AIRS review follows payment clearance and accepted submission. Future officer assistance may explain authorised evidence/findings but cannot change state. This policy does not enable live conversation; the current availability notice remains truthful.

## Independent AMIRA backend (2026-11-04)

AMIRA backend ownership was moved to C:\Users\Asyra\Documents\AMIRA under ADR-353 (2026-11-04). It is an independent prepared-guidance/catalogue service; conversation, model inference and record retrieval remain unimplemented. AIRS retains its review AiProvider and UI/assets. iNDECS keeps local guidance with no new remote dependency. Future assistant APIs belong to AMIRA, with scoped source APIs and iNDECS identity; free applicant guidance stays local and uses no paid AI.
