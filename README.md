# Sustainable Catalyst Library v1.9.0

Library v1.9.0 adds the **Content Planner, Complete Public Registry, and Roadmap Tracker** to the Sustainable Catalyst knowledge base.

The release unifies published articles, current documentation, planned content, active development, article maps, superseded records, and archives in one searchable registry. Planned content remains visibly distinct from published knowledge and can be converted into a native WordPress draft without losing its roadmap history.

## Included

- Native `sc_content_plan` WordPress post type with revisions and REST support
- Planning statuses from Idea through Published, Deferred, Cancelled, and Superseded
- Optional expected release date, month, quarter, year, product release, or no date
- Article-map scanner for headings and linked entries
- Bulk registration of missing article-map entries as planned content
- Planned-to-draft and planned-to-published workflows
- Complete public registry of enabled published posts, documentation, plans, and historical records
- Public roadmap tracker with counts by area, product, status, type, and article map
- Administrative tracker, release warnings, unassigned-area detection, and overdue expectations
- Distinct public labels for Published, Current Documentation, In Development, Planned, Scheduled, Superseded, and Archived records
- Search and filters for record state, content type, area, product, collection, map, and expected release
- CSV and JSON registry exports
- Foundations-filtered registry and tracker support through the existing Library Collections taxonomy
- Planned records in the normal Research Library search when explicitly marked public

## WordPress installation

Upload `sustainable-catalyst-library-v1.9.0.zip`, replace the existing plugin, activate it, open **SC Library**, enable the Content Planner, save settings, and rebuild the Library index.

After installation:

1. Open **SC Library → Content Planner** to create planned records.
2. Open **SC Library → Article Map Planner** to scan an existing article map and register missing entries.
3. Open **SC Library → Roadmap Tracker** to inspect totals and planning gaps.
4. Mark a published planning record as public to include it in Library and registry results.
5. Use **Create WordPress draft** from a plan when drafting begins.

## Shortcodes

Complete public registry:

```text
[sc_library_registry mode="public"]
```

Public roadmap tracker:

```text
[sc_library_planner_tracker mode="public"]
```

Registry through the main Library shortcode:

```text
[sc_library mode="registry"]
```

Foundations-filtered registry:

```text
[sc_library_registry collection="foundations"]
```

Foundations-filtered tracker:

```text
[sc_library_planner_tracker collection="foundations" mode="public"]
```

The main Research Library shortcode remains:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
```

## REST endpoints

- `/wp-json/sustainable-catalyst/v1/library/registry`
- `/wp-json/sustainable-catalyst/v1/library/registry/facets`
- `/wp-json/sustainable-catalyst/v1/library/roadmap/tracker`
- `/wp-json/sustainable-catalyst/v1/library/planner/statuses`
- `/wp-json/sustainable-catalyst/v1/library/plans/{id}`

## Architectural boundary

The Content Planner does not duplicate published posts. A planned record links forward to a WordPress draft and then to the canonical published record. Once the linked post publishes, the normal Library and registry treat the published post as primary while retaining the planning record and history in WordPress administration.

The complete public registry is built from the existing Library index and enabled WordPress post types. It does not use an iframe, does not replace WordPress as the publishing source of truth, and does not expose private planning notes.
