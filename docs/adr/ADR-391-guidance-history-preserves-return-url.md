# ADR-391: Guidance history preserves the applicant return URL

Date: 2026-11-18

Status: Accepted (browser validation defect fix)

## Context

LJUP browser validation exposed a duplicate-file error redirecting to AMIRA's
history JSON. The history fetch advertised JSON but omitted `X-Requested-With`.
Laravel therefore recorded this background GET as the session's previous URL;
validation redirects without a Referer could return to it instead of the draft.

## Decision

Mark the local guidance history GET as `XMLHttpRequest`, matching the existing
checklist and chat pollers. Background history must not replace the applicant's
last navigated page. Keep native upload validation, authentication and ownership
checks unchanged.

## Verification

Repeat a duplicate-file upload after the draft's guidance history loads. The
browser must return to the draft and display the duplicate-file error, retaining
the existing documents.
