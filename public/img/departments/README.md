# Department icons — ADR-370

P's vector contour opacity is reduced by 45%, from 0.5 to 0.275, using
--opacity-department-perancang-pattern. Both sizes preserve the existing geometry,
letter, frame and gradient while matching K/L's quieter background treatment.

## Current K artwork

K now uses scripts/brand/kejuruteraan-soft.png, a built-in ImageGen edit of the
owner-supplied kejuruteraan-approved.png. The edit prompt reduced bridge,
blueprint grid, dimension guide and small-label contrast by approximately 45%
while preserving the white K, dark blue frame, gradient and supplied composition.
The dimension labels are decorative source artwork, not application measurements.
Both K variants embed the edited PNG inside an SVG viewport. K and L are
raster-backed SVGs; P and B remain vector. Earlier authored K geometry is
superseded for active rendering. Both reference originals remain unchanged.

## Current L artwork

Active source: scripts/brand/landskap-soft.png, a built-in ImageGen edit of the
supplied reference to soften leaf/flower/stem line contrast by about 45% at the
owner's request. The prompt specified preserving the composition, white L,
gradient and dark frame while attenuating only botanical strokes. The original
scripts/brand/landskap-approved.png is retained unchanged for provenance.

L uses the exact image explicitly supplied by the owner after the botanical
redraws were rejected. The unchanged source is
scripts/brand/landskap-approved.png (clipboard reference
codex-clipboard-38fdf853-5df2-4856-a839-75b64c8a4686.png). Both L exports and its
sprite symbols embed that PNG with an SVG viewport excluding the surrounding
screenshot. The UI supplies its normal external shadow. This preserves the
actual leaf/flower composition, letter, frame and colours.

**L is raster-backed SVG, not pure vector.** It uses the same artwork at both
sizes, and its colour is baked into the supplied image. The 274 px crop supports
the current 240 px preview and smaller interface icons; enlargement will not
gain detail. P/B/K remain vector. The earlier botanical source and notes below
record superseded attempts and must not be used to regenerate L.

Owner-approved vector redraw of the concept reviewed on 6 November 2026.
The first procedural background redraw was rejected by the owner. The current
background paths are centre-line traces of the approved concept, stored in
scripts/brand/department-patterns.json. Keep those compositions; do not replace
them with generic contour rings, simple leaf repeats or an invented truss.
Source concept: exec-cbb66073-1da7-42a4-a68a-54594d40e894.png, generated in this
review chat. No raster images are embedded in the exported SVGs. Compact artwork
keeps the same motifs with a stronger stroke for small-size rendering.
K was subsequently refined at the owner's request with precise authored bridge
geometry, replacing the rough trace: paired chords, member plates, bearings,
tapered piers and regular foundation hatching. Its compact version omits the
grid, dimension guides and joint details. L was then refined with smooth authored
botanical curves: broad split leaves, curved lance leaves, complete midribs and
veins and restrained flowers. Compact L omits fine perforations and secondary
veins. P/B keep their approved traced motifs.
The botanical authoring source is scripts/brand/botanical.py. Its final refinement
returns to the reference's quadrant composition with smaller complete silhouettes,
veins evaluated on the leaf contour, secondary venation and five-petal flowers.
Avoid enlarging leaves into sparsely detailed cropped shapes again.
Sharp squares, darker same-colour frames, diagonal gradients and fine overlays:

- Perancang Bandar: asymmetrical topographic contours, indigo.
- Bangunan: four tiled roof slopes, terracotta. Geometry follows the roof
  reference supplied by the owner; no photographic pixels are embedded.
- Kejuruteraan: bridge truss and structural drafting, steel blue. No dimensions
  or numerical engineering claims are part of the artwork.
- Landskap: broad botanical leaves with outlines and veins, forest green.

The owner-supplied iNDECS logo (three squares and an integrated circular Landskap canopy, ADR-380) is the system/public identity.
These are department identifiers, not application type codes or status badges.

## Use

```blade
{{-- Decorative when an adjacent label already names the department. --}}
<x-department-icon department="perancang" />
<x-department-icon department="bangunan" compact />
{{-- Supply an accessible label if the artwork is presented on its own. --}}
<x-department-icon department="landskap" label="Jabatan Landskap" />
```

Use detailed artwork at 64 px and above and compact artwork at 32–48 px.
Keep a full department name beside navigation identifiers. The component uses
an external SVG sprite and inherits shared CSS tokens. No scripts, fonts or
remote resources are inside the SVGs. Glyphs are Google Sans Bold outlines,
extracted from the owner's locally installed font using Windows GlyphTypeface.
The stored path source is scripts/brand/department-glyphs.json.

SVG artwork has a transparent exterior and a 120 × 120 viewBox. Its frame is
8 units wide, matching the visible proportion approved in the concept sheet.
The shared .department-icon class supplies the outer shadow. Standalone SVGs
contain the square artwork without a shadow margin, for clean placement/export.

## Rebuild and preview

From the repository root:

```powershell
python scripts/brand/build-department-icons.py
python -m http.server 8766 --bind 127.0.0.1
```

Open http://127.0.0.1:8766/output/department-icons/ in a browser. It shows detailed
and compact instances of the actual shared sprite, download links and a
light/dark switch. Serve locally: browsers restrict SVG use across file URLs.
The preview does not require Laravel or a database.

All colours, pattern appearance, display sizes and shadow values originate in
public/css/tokens.css. The builder embeds gradient stop snapshots in the sprite
(external SVG gradient definitions cannot reliably inherit use-instance variables)
and colour snapshots in the eight portable SVG exports; regenerate after token
edits. Frame, line and letter properties inherit from the component. Sprite geometry and standalone
exports are produced by the same script. Do not edit generated SVGs directly.

ADR-371 adopts the reviewed assets in the permission-filtered application chooser
and staff workspace switcher/topbar. Detailed chooser icons are 64 px; navigation
icons are compact 32 px. Other application identities and all action icons retain
their existing treatment. Planned K/L cards remain unlinked.
