# ADR-370: Department letter icons

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

Use sharp square department identifiers with centred Google Sans Bold letters:
P (Perancang Bandar, indigo), B (Bangunan, terracotta), K (Kejuruteraan, steel
blue), L (Landskap, forest green). Each has a restrained diagonal gradient,
a wider darker outline of its own hue and a small downward shadow.

Interior linework follows the approved concept: organic asymmetrical topographic
contours for P; four tiled hipped-roof slopes meeting at diagonal ridges for B;
bridge trusses and structural drafting for K; large detailed botanical leaves
covering the interior for L. Do not include fictitious dimension numbers in K.
Patterns are subordinate to the opaque letter and clipped inside the frame.

Detailed and simplified compact SVG variants share geometry and colour tokens.
The owner rejected the first generic procedural redraw. Background outlines
must follow the approved concept's actual composition. Store traced centre-line
paths in scripts/brand/department-patterns.json; do not substitute generic motifs.
The compact variant preserves those paths with an adjusted stroke weight.
The owner subsequently requested a stronger K refinement: replace its noisy
trace with precise authored bridge linework in the same composition. Use paired
truss chords, joints, tapered piers, foundation hatching and sparse dimension
guides without numbers. Keep P, B and L unchanged. K's compact variant omits
secondary drafting detail to preserve the letter at small sizes.
The owner also requested L refinement: author smooth botanical outlines in the
approved large-leaf composition. Use broad split leaves, curved lance leaves,
connected midribs and secondary veins, with restrained flowers in the gaps.
Cover the coloured face and keep all strokes behind the opaque letter. Compact
L omits fine vein subdivisions and leaf perforations. P and B remain traced.
The first authored L was rejected for losing the reference's botanical detail.
Refine L with balanced complete silhouettes, connected fine venation and natural
five-petal flowers. Keep that authored source in scripts/brand/botanical.py.

### Superseding L artwork choice

The owner supplied an explicit L image after rejecting the botanical redraws.
Use that supplied artwork directly, preserving its foliage, flowers, letter,
gradient and frame. Store the unchanged PNG as
scripts/brand/landskap-approved.png and embed it in the L SVG wrappers, with a
viewport excluding the screenshot's surrounding page and shadow. The component
continues to provide its outer shadow. Detailed and compact L use the same image.
L is now raster-backed SVG, not pure vector; do not describe it as outlined
vector artwork or replace it with procedural botanical geometry. The 274 px
source crop supports the 240 px preview and smaller UI uses. Higher-resolution
or genuinely vector reproduction remains separate work if requested. P/B/K
remain vector, with shared appearance tokens unchanged.

The owner then requested quieter botanical outlines. Keep the original supplied
PNG for provenance and use scripts/brand/landskap-soft.png, an ImageGen edit
reducing only the botanical stroke contrast by approximately 45%. Preserve the
same composition, opaque L and dark frame. The SVG viewport follows the new
image dimensions; it remains raster-backed artwork.

The owner supplied a matching K reference and requested the same softening.
Use scripts/brand/kejuruteraan-soft.png, the ImageGen edit of the unchanged
kejuruteraan-approved.png reference, with blueprint line contrast reduced by
approximately 45%. Preserve its bridge, K, frame and supplied decorative dimension
labels; these labels are artwork, not measurements of an actual application.
This supersedes the authored K vector for active artwork. Both K and L are now
raster-backed SVG wrappers; P and B remain vector. Keep the previous sources for
provenance and do not substitute a different composition.

Apply the same quieter background treatment to P on the owner's request.
Reduce its contour opacity by 45% (0.5 to 0.275) through a dedicated token,
preserving the existing contour geometry, letter, gradient and frame. P remains
vector; both detailed and compact variants inherit that opacity.
Outline the four Google Sans glyphs so exported artwork does not depend on font
availability. Keep standalone exports reproducible from the same source as the
shared SVG sprite. Department colours are identity colours only, not statuses.
Teal remains the system/public portal and common interaction colour. Keep the
existing three-square iNDECS mark; the concept sheet's teal i is not a new logo.

These department identifiers are owner-approved exceptions to the Lucide-only,
single-accent, no-gradient, no-decorative-pattern and centred-text restrictions.
Exceptions apply only inside the department artwork. Shared UI stays unchanged.

## Delivery

First deliver the SVG set, a reusable Blade component and a responsive browser
preview with light/dark surfaces and small-size comparisons. Do not replace live
navigation in this first increment. Preview approval precedes live adoption.

All appearance values live in public/css/tokens.css. External SVG symbol usage
inherits the component's department colour properties; standalone exports embed
a generated snapshot of those same tokens. Update exports when tokens change.

## Verification

Check all eight exports as SVG/XML, repeated instances of the shared component,
light/dark and phone preview rendering, and small-size letter legibility. No
database, identity, workflow or module behaviour changes are part of this work.
