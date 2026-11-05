# ADR-360: Application document download choices

Date: 2026-11-04. Status: Accepted by the programme owner.

Each available application type in the new-application chooser has a separate
download icon. It opens a type-labelled native popup with two choices: Senarai
semak (checklist) and Garis panduan. Both are disabled and labelled Belum tersedia
until the owner configures the documents in a subsequent increment. Do not create
placeholder files, invent document URLs or expose internal catalogue documents.

Keep the application link and download control as siblings. The document popup
opens over the chooser; closing it returns focus to that type's download icon
and preserves the chooser's scroll position. Reuse the shared modal keyboard,
backdrop and no-JavaScript behaviour. All spacing and colours use existing tokens.
