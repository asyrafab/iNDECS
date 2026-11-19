# ADR-409: Compact workspace navigation

Date: 2026-11-19

Status: Accepted at the owner's request to simplify the sidebar.

Keep one compact department identity in the native workspace switcher. Its
authorised alternate destinations are hidden while closed, with an explicit CSS
rule so component display rules cannot expose closed disclosure contents. Omit
the current workspace from the alternate list and rotate the disclosure chevron
when open. Preserve keyboard/native details behaviour and existing access checks.

Department navigation starts with its dashboard and records, followed by shared
officer tasks. NADI appears in the switcher only; its own workspace retains the
meeting navigation. Remove the duplicate cross-workspace NADI link from the
primary menu. Preserve the navy shell, approved identity artwork, tokens,
desktop sidebar collapse and mobile off-canvas behaviour. No permissions or
record operations change.
