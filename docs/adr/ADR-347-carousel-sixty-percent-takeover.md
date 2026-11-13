# ADR-347: Carousel hover takeover at sixty percent

Date: 2026-11-03. Status: Accepted.

The owner requested a 60% stopping range. Hover steering may take over once
the spin falls to 60% of its release speed, replacing ADR-346's absolute near-stop
threshold. Store the release speed per gesture and use the shared .6 ratio token.
Retain friction at 1.2/second, additive drags, the speed cap and existing motion
accessibility and modal behaviour. Without hover, the spin still coasts to rest.
