# Library v6.24.0 — Geospatial & Place-Based Research Workspace

Library v6.24.0 adds a geospatial and place-based research workspace over existing Entity/Place, Statistical Evidence, Structured Evidence, Federation, Investigation, and Evidence Matrix layers.

Generations: Library 6.24.0 / Backend 3.24.0 / Web 2.24.0 / SDK 1.24.0 / API v1 stable.

Public route: `/research/geospatial`.

API base: `/api/library/v1/geospatial-research`.

Capabilities include place, layer, and spatial-feature inventories; explicit coordinate-reference-system metadata; temporal validity; point and bounding-box relation previews; WGS84 point-distance previews; spatial coverage audits; evidence-matrix handoff previews; investigation-gap handoff previews; and reproducible JSON export.

Spatial proximity, overlap, containment, clustering, raster resolution, coordinate precision, and map appearance never establish causation, identity, jurisdiction, evidence strength, or truth. Missing CRS is never silently assumed. Coordinate transformations must preserve lineage and are not performed automatically by this workspace.

No database migration. WordPress remains optional/non-authoritative.
