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
sent to AIRS or another model. No business data is changed by the assistant.
Free-text questions outside its supported guidance receive an availability
explanation rather than a simulated AI answer. The UI must keep the AIRS
unavailability notice until a real service is implemented.

To add live AI, implement the versioned, authenticated AIRS contract as separate
work; ground answers in authorised evidence, retain officer confirmation and
audit, and provide an unavailable-service fallback. An availability label alone
must never be changed to imply that an integration is working.

## Verification

Run `php vendor/bin/pint --test`, `php artisan test`, `php artisan view:cache`,
`node --check public/js/theme.js` and `node --check public/js/indecs.js`.
Tests use the guarded `indecs_test` PostgreSQL database.

`WorkspacePresentationTest` verifies navigation permissions, authorised record
context, public identity rendering and unchanged business history during reads.
For local visual review only, set `INDECS_CAPTURE_THEME=1` when running that test;
it writes rendered disposable fixtures to the ignored `.tools/theme-ui/` folder.
Inspect light/dark desktop layouts, 390px forms, long document names, assistant
guidance, keyboard dismissal and reduced motion. Never use production records
or alter the normal application database to obtain screenshots.
