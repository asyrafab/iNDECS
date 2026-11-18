# ADR-402: Glossy glass finish for the floating review tool

Date: 2026-11-18. Status: accepted under the owner's explicit request.

Apply an owner-authorised visual exception to the floating review tool only: a token-defined gloss gradient, translucent semantic surface, frosted backdrop and inset highlight/shadow. The rest of the workspace retains Calm Intelligence's normal solid, gradient-free surfaces. Keep the bright yellow/black outline attention treatment, semantic score bands, capsule morph, stable rail, drag/navigation behavior and readable labels.

Use an opaque gloss fallback when backdrop filtering is unavailable; print retains a plain solid surface. No new motion, image assets, layout measurements, workflow changes or dependencies are introduced.

Owner refinement: increase glass transparency (yellow about 65%, dark score bands about 80%) and lighten red/green foregrounds with a softer highlight to retain text contrast. Dark-band controls darken on hover while yellow controls use light fills to preserve contrast. Keep text itself fully opaque, with solid fallback and the same layout/interaction behavior.

Owner refinement: red and green now use light coral/mint glass at the same approximately 65% opacity as yellow, with fully opaque black labels and the same light sheen/control fills. This supersedes the earlier dark red/green treatment; the white rim and advisory score thresholds remain unchanged.
