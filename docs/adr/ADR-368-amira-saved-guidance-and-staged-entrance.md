# ADR-368: Saved local guidance and AMIRA's staged entrance

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

Kaunter Maya starts with an empty conversation surface. AMIRA emerges in the
middle, gains mist and her orbit, moves up, reveals her name/title, then types
her greeting and today's suggested guidance. Returning users see their saved
thread after the entrance; previous replies are not retyped. Phone sheets start
the sequence when opened. Reduced motion and hidden pages skip the sequence.

Persist local-guidance exchanges in `core.amira_guidance_turns`, scoped to the
authenticated public account and home/draft context. Keep this deterministic
guidance separate from the unfinished queued AI conversation channel. Generate
replies on the server from authorised context; never accept assistant text from
the browser. Opening turns and UUID-tagged requests are idempotent. Records are
append-only, shown chronologically in pages of 30 with an older-message cursor.
Draft contexts require ownership on every read and write. No message body is
logged, sent to AMIRA/AIRS or used to alter a record. Twelve-month inactivity
retention follows the existing conversation configuration; a scheduled command
removes complete inactive context threads.

Append new exchange boxes instead of replacing earlier ones. Scroll within the
conversation area; preserve position when prepending older messages and do not
pull readers away from earlier messages during typing. Keep the existing fast
typing, polite complete-reply announcement and reduced-motion behaviour.
Provide a visible saving/retry error and preserve unsent questions on failure.
No-JavaScript keeps the static greeting/tasks. Entrance failure has a bounded
recovery. All appearance and motion values remain tokens.
