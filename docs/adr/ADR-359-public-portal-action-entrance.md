# ADR-359: Public portal with two primary choices

Date: 2026-11-04. Status: Accepted by the programme owner.

## Decision

Replace the public portal's departmental-style sidebar with a simple header and
two front-page choices: Permohonan baru and Semak Permohonan. Native dialogs
provide available preparation types and the applicant's current records. A raised
status panel sits beside the choices on desktop and follows them on phones. The
header's gear opens account, appearance, guidance, helpdesk availability and logout
controls. Other public portal pages share this header and a link home. Staff
workspaces retain their navy sidebar. This supersedes ADR-358's public shell only.

Application type choices come from the same active server registry as the draft
form. Choosing a type prefills the form without creating a record. Keep native
modal isolation, focus trapping/return, Escape/backdrop/close and a no-JavaScript
fallback. No new UI framework or independent theme styling is introduced.

## Record boundary

Public formal intake and applicant-to-official-record permissions are not yet
implemented. Semak Permohonan therefore states that no application has been
received through this portal and invites a new preparation draft. Existing owned
drafts are shown separately as Draf belum dihantar, never as received applications.
Do not infer ownership from matching email addresses, query staff records or
invent accepted statuses. Helpdesk ticket submission is unavailable and labelled
as such; existing local guidance and system information remain usable.
