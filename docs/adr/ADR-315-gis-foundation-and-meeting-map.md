# ADR-315: GIS foundation and the eOSC+ meeting map

Date: 2026-10-29. Status: Accepted. Builds on ADR-213 and ADR-314 (eOSC+ is core capability C10; GIS is core capability C13).

## Context

The OSC meeting map must show each agenda item's site over current lots, zoning and reference layers, the
applicant's layout plan in place, imagery and past OSC decisions. It must keep working when the MBSP ArcGIS
Enterprise portal is slow or down, and it must not depend on any other system running. MBSP source data is in
two local Cassini projections that PostGIS does not ship.

## Decision

- **Storage in PostGIS, SRID 4326.** All stored geometry is EPSG:4326. `core.gis_features` also keeps a
  `geom_web` column in 3857 so vector tiles need no per-request transform. Every stored or accepted geometry must
  intersect the MBSP box `[100.33, 5.10, 100.60, 5.62]`.
- **Two MBSP source SRIDs**, registered in `spatial_ref_sys` with `auth_name 'MBSP'`:
  990001 (GDM2000 Cassini Pulau Pinang & Seberang Perai, the ArcGIS feature-layer CRS) and 990002 (legacy MJKP
  Kertau Cassini Penang, with `towgs84=-11,851,5`). 990001 is checked against ArcGIS at test time and agrees
  within 1 m. Alignment check against real legacy layout plans (Task 4,
  20 random MJKP papers that have both an ArcGIS point and a georeferenced plan): the point falls inside the
  image corners for 17 of 20 (85 %) under 990001 and 17 of 20 (85 %) under 990002; the three misses are the same
  under both and are far off (3 to 38 km), so they are bad legacy points or mismatched plans, not a projection
  error. The two SRIDs differ by only about 10 m at plan scale, so the check cannot separate them. The default
  for `osc:import-legacy --srid` is **990001**, the ArcGIS layer CRS. Most legacy `plan_` names are
  `<file ref>_<scan>.jpg` and only the scan part has a `.jgw`, so the importer also tries the part after the
  last underscore.
- **Snapshot replication with ogr2ogr.** Reference layers are copied from ArcGIS into dated snapshots
  (`core.gis_layer_snapshots`); a layer points at its active snapshot, and a failed import never replaces it.
  ArcGIS queries are anonymous; a token, if ever needed, lives in `.env` only and is redacted from logs.
- **Vector tiles from PostGIS.** Reference layers are served as Mapbox vector tiles with `ST_AsMVT`.
- **OpenLayers vendored** under `public/vendor/openlayers` at a pinned 10.x version, with its licence and
  `VERSION` file. There is no build step.
- **Layout placement by corners.** A scanned layout plan is stored as an image plus a four-corner polygon in 4326
  (top-left, top-right, bottom-right, bottom-left, closing point). The client draws it with an affine
  transform; no raster is reprojected.
- **The meeting map never calls ArcGIS.** Only the replication command talks to `spgis.mbsp.gov.my`. Tiles,
  layers, items and layout images come from iNDECS. Only third-party imagery tiles are fetched by the browser,
  and their failure shows a notice while local layers keep rendering.
- **Public submission drawing is deferred** until public submission forms exist. Boundaries are drawn by staff at
  registration for now.

## Consequences

- PostGIS is a hard requirement (already in the platform decision). The migration creates the extension and the
  two SRIDs; its `down()` refuses to run while any GIS data exists.
- Reference data can be up to one refresh old; the snapshot date is shown on the map.
- Eloquent models do not read or write geometry columns; PostGIS SQL does.
