# Architectural animation layers

Created 2026-11-03 using the built-in imagegen tool and the owner's approved
realistic three-layer concept as the reference. These are generated illustrations,
not photographs of a real property. Natural material colours are preserved.

Production assets: `roof.webp`, `walls.webp`, `footprint.webp`. Each is 1254 × 1254
with alpha. PNG originals remain in the session's generated-image folder and
local `output/brand-animation/source/`. WebP encoding uses quality 90 and alpha
quality 100; no recolouring or geometric edits were applied.

`landscape.webp` (also 1254 × 1254 with alpha) was generated with the built-in
imagegen tool for ADR-319 and encoded with the same WebP settings. Its original is
`exec-a407148e-bfd4-4c47-a0e6-b99f796eada6.png` in the session generated-image folder.

`lawn.webp` is an opaque 1254 × 1254 natural lawn texture for ADR-320, generated
with the built-in imagegen tool and WebP encoded at quality 90 without recolouring.
Original: `exec-e0c02908-8ee0-4529-96ce-2d8e03a42ed7.png` in that same folder.
The lawn is no longer used in the introduction after ADR-321.

`palm.webp` is a transparent 1254 × 1254 photographic palm canopy generated with
the built-in imagegen tool, WebP encoded at quality 90 / alpha quality 100, with
natural colours preserved. Original: `exec-444c05d6-7cd2-40fe-9d44-0074d61ff0be.png`.
The trunk is a separate token-coloured SVG detail, not part of the canopy image.
The palm is no longer used after ADR-322; the introduction uses the original
`landscape.webp` plant again.

## Final prompt set

Each asset uses this shared prompt followed by its layer-specific prompt:

> Create ONE standalone architectural animation layer matching the realistic
> Malaysian square house in the reference. Strict vertical overhead orthographic
> view, edges horizontal and vertical, no perspective tilt. Single square object
> centred and filling the canvas as closely as possible, tiny equal margins only.
> Transparent background outside the object, no cast shadow outside square, no
> labels, no text, no other layers or objects. Photoreal material detail, soft even
> natural lighting.

**Roof:** The ROOF ONLY: square hipped Malaysian terracotta clay tile roof, four
sloping faces meeting centrally, realistic curved orange-brown tiles and ridge
caps, subtle natural weathering. No visible walls or slab.

**Walls:** The WALL PLAN ONLY with roof entirely removed: square white plastered
masonry perimeter walls, a few simple internal partitions, windows and a wooden
entry door, neutral pale concrete interior floor. Exterior perimeter forms a
complete square. No roof, no exterior slab, no furniture. Floor must be opaque
within perimeter.

**Footprint:** The FOOTPRINT ONLY: a square pale grey concrete ground slab, subtle
aggregate and a fine continuous inset perimeter construction line. Perfectly
square with straight edges. No walls, no roof, no landscaping.

**Landscape, complete prompt:** Create one standalone photorealistic landscape
animation sprite: a small lush tropical Malaysian garden shrub viewed strictly
vertically from directly above, orthographic plan view with no perspective tilt.
A naturally rounded but irregular compact cluster of glossy green leaves, a few
tiny white frangipani-like blossoms among the leaves, fine layered foliage detail,
realistic sunlight gentle and even. Natural green colours, not recoloured. Entire
plant canopy visible with clear irregular leaf silhouette, centred, very small
equal margins. Genuinely transparent background outside the leaves, no ground,
no soil rectangle, no planter, no pot, no cast shadow beyond plant, no house, no
text, no labels, no logos. A botanical photographic cutout to accompany realistic
clay-tile roof and white plastered walls in a top-down architectural exploded-view
animation. One plant only, square canvas.

**Lawn, complete prompt:** Create a square seamless-looking photorealistic material
texture of a neatly maintained Malaysian tropical lawn, viewed strictly vertically
overhead, orthographic. Fine short natural green grass blades, subtly varied
natural green tones, dense healthy turf, evenly lit soft daylight, consistent
detail and scale across the entire frame. Edge-to-edge grass only, no border,
no trees, no flowers, no shrubs, no objects, no paving, no soil, no shadows from
external objects, no perspective horizon, no text. This is a natural photographic
lawn material for small top-down architectural garden patches, not a user
interface colour or recoloured photograph.

**Palm, complete prompt:** One photorealistic coconut palm canopy as a top-down
architectural animation sprite. Strict vertical overhead orthographic view. A
graceful mature Malaysian coconut palm crown, 9 to 11 naturally arching long
feathered green fronds radiating outward from one compact central growing point.
Realistic fine leaflets with gaps between the fronds, natural green botanical
colour, even soft daylight, complete irregular star-shaped crown visible, small
equal margins. Genuinely transparent background outside and between fronds. No
ground, no grass patch, no soil, no pot, no scenery, no cast shadow outside the
foliage, no text, no labels. Only one canopy, not a collection of plants. Central
attachment point should be clear to align over a separately animated trunk.
Square canvas. A natural photographic cutout matching a realistic overhead
Malaysian clay-roof building model.

## Circular garden (ADR-381, 12 November 2026)

The opening now reuses `lawn.webp` inside the approved circular landscape footprint. `stone-path.png` is an opaque natural flagstone paving texture generated with the built-in imagegen tool, preserved without recolouring. Original: `C:/Users/Asyra/.codex/generated_images/01a1cd0f-ab13-7db1-a033-f0dd9a8c8ca1/exec-d50cfe26-f12d-4668-be35-dfd2dbcbc022.png`.

Final stone prompt: Create one square photorealistic material texture of a natural pale grey stone garden walking path, seen strictly vertically from above, orthographic, no perspective. Edge-to-edge small irregular flat flagstone pavers with narrow dark joints, subtly weathered matte limestone/granite surfaces, quiet even daylight, fine stone grain, natural grey with a few warm-grey stones. Uniform scale across whole image, around twelve to sixteen stones across the image width. This will be clipped into a narrow circular walking path in an architectural animation. No grass, no plants, no garden scene, no circular composition, no border, no text, no watermark, no shadows from external objects. Opaque background, texture only.

Grass and stone are clipped/masked in the SVG, remain untinted and fade out at the existing logo transition. The path is wider inward during the material phase; its outer arc matches the approved mark. The final logo paths are unchanged.

ADR-382 adds narrow inner/outer stone edging using the same `stone-path.png` at a smaller texture scale. Fine SVG joints and a token-based contact seam connect the paving to the lawn. No new raster asset, recolouring or logo geometry change.
