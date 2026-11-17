# LJUP decisions and letters — proposed implementation sequence

Design: [ADR-392](../../adr/ADR-392-ljup-officer-decisions-and-letters.md). Status: proposed; departmental policy is not yet confirmed. Base: consolidation PR #2, commit `a2c07d1`. Work on `codex/ljup-decisions-letters`; keep PR #2 unchanged.

## Increment 1: Internal recommendations and letter drafts

1. Add generic `core` recommendation and letter-draft revision records, with application/actor foreign keys, immutable source-version references and append-only database guards. Define a migration rollback guard that retains operational records.
2. Add a core preparation service with an LJUP adapter. Require the existing review permission and the same scoped application visibility as the officer workspace. Require handed-over final completed review and accepted-submission proof. Lock in a consistent order with journey/check services; reject stale expected revisions. Snapshot the source evidence and record audit in the same transaction.
3. Add an internal BM recommendation form/history to `/kejuruteraan`. Separate human recommendation from AI evidence. Proposed recommendation outcomes remain labelled as recommendations. Explain a missing-document exception explicitly without imposing an AI pass/score gate.
4. Add a draft-only letter preview using a frozen content snapshot. Escape all officer-entered text and show `DRAF — BUKAN SURAT RASMI`. Use the existing tokens/classes; add shared classes only if necessary and document them in AGENTS.md. No issuance reference, signature, QR token, applicant access or notification.
5. Verify meaningful PostgreSQL tests: unauthorized/cross-department/cross-district access, public access denied, handover and evidence pinning, stale/concurrent revisions, append-only enforcement, audit failure rollback, low-score recommendation, missing-document explanation and preview escaping. Verify that preparation changes neither application stage/status nor AIRS outputs. Keep every department's regression suite green.

Exit: an authorized reviewer can prepare and revise a recommendation and inspect an internal letter draft. This is not an issued official decision or letter.

## Increment 2: Official decision and issuance

Start after the confirmations listed in ADR-392. Record confirmed policy before code.

1. Configure explicit scoped decision/issuance grants and delegation; do not infer them from a senior role or system-admin status. Define recent staff reauthentication using the existing staff identity contract; public email OTP is not a substitute.
2. Extend the shared workflow access contract to LJUP, configure permitted transitions/guards, and atomically write decision, stage history and audit. Verify separation of duties and stale source/recommendation protection at the service boundary.
3. Confirm approved template/numbering/signature mechanism, select a renderer and produce versioned private artifacts with content hashes. Verify Google Sans and letterhead layout visually. Render failures/retries must not leave a falsely issued application or duplicate letters.
4. Implement explicit issuance, applicant-safe authorized download and durable notifications/publication. Implement a minimal verification surface only if confirmed, without exposing private applicant data or claiming unsupported digital signatures.
5. Cover decision/issuance races, revoked delegation, stale authentication, file/notification failures, retries, ownership, immutable issued content and supersession. Enable Perlu pindaan only with its complete post-handover resubmission contract and tests; never create an unauthorized third AIRS check.

Exit: the department-approved outcomes can be issued by confirmed delegated officers, with complete history and delivery evidence.

## Review and deployment

Use a focused stacked PR against the consolidation branch until PR #2 is merged; retarget to main afterwards. Hosted CI is currently blocked by GitHub account billing. Keep local PostgreSQL and asset checks reproducible and report the hosted limitation accurately. Do not migrate operational databases, issue real letters, send notifications or merge as part of design preparation.
