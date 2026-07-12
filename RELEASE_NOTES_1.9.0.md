# Release Notes — Sustainable Catalyst Library v1.9.0

## Content Planner

- Added `sc_content_plan` as a native WordPress record type.
- Added status, type, priority, area, product, responsibility, sources, dependencies, expected artifacts, and internal notes.
- Added public-roadmap visibility controls.
- Added optional release timing with exact date, month, quarter, year, product release, and no-date modes.

## Article Map Planner

- Added article-map scanning for headings and links.
- Added published, draft, planned, and missing-entry detection.
- Added bulk creation of missing entries as planned content.
- Added taxonomy inheritance and sequence preservation.

## Draft and publication workflow

- Added one-click WordPress draft creation.
- Added plan-to-draft links and taxonomy transfer.
- Added automatic plan reconciliation when a connected draft publishes.
- Prevented published plans from competing with their canonical published record in the Library index.

## Complete Public Registry

- Added all enabled published Library posts and documents.
- Added public planned and in-development content.
- Added current, superseded, PDF snapshot, and archived documentation states.
- Added record-state, type, area, product, collection, article-map, and release filters.
- Added CSV and JSON exports.

## Roadmap Tracker

- Added public and administrative totals.
- Added breakdowns by area, product, state, type, and article map.
- Added warnings for missing areas, scheduled plans without drafts, published plans without canonical posts, and overdue expectations.

## Shortcodes

- `[sc_library_registry]`
- `[sc_library_planner_tracker]`
- `[sc_library mode="registry"]`
- `[sc_library mode="planner"]`

## REST

- `registry`
- `registry/facets`
- `roadmap/tracker`
- `planner/statuses`
- `plans/{id}`
