# Sustainable Catalyst Library v1.5.0

Library v1.5.0 adds a **cross-application research layer** connecting the WordPress knowledge base and local-first Research Notebook to Sustainable Catalyst Workbench, Decision Studio, and Site Intelligence.

The Library does not embed duplicate application interfaces or iframes. It creates compact Library-specific context panels and versioned `sc-library-handoff/1.0` packets, then opens the full application only when deeper analysis is requested.

## Included

- Library Workbench handoffs for tools, equations, datasets, technical questions, matrices, and validation work
- Library Decision Studio handoffs for research questions, claims, evidence, assumptions, uncertainties, tradeoffs, and knowledge gaps
- Library Site Intelligence handoffs for countries, places, indicators, source registry IDs, datasets, maps, and event context
- Record-level connected-tool panels
- Notebook handoff builder for saved records, collections, notes, sources, matrices, Whiteboards, and Chalkboards
- Cross-origin compact handoff payloads carried in the URL fragment
- Downloadable and copyable handoff JSON
- Server-generated record context endpoints
- Configurable launch and health URLs
- Cached service health checks with independent status states
- New editor fields for target-specific context
- `sc-library-workspace/1.3` local workspace schema with migration from earlier versions

## WordPress installation

Upload `sustainable-catalyst-library-v1.5.0.zip`, replace the existing plugin, activate it, open **SC Library**, configure the three application URLs and optional health endpoints, save settings, and rebuild the Library index.

Recommended Library shortcode:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
```

Standalone connected-tools studio:

```text
[sc_library_integrations]
```

Open the Notebook directly to connected tools:

```text
[sc_library_notebook tab="integrations"]
```

## REST endpoints

- `/wp-json/sustainable-catalyst/v1/library/integrations`
- `/wp-json/sustainable-catalyst/v1/library/integrations/status`
- `/wp-json/sustainable-catalyst/v1/library/integration-schema`
- `/wp-json/sustainable-catalyst/v1/library/items/{id}/handoff?target=workbench`
- `/wp-json/sustainable-catalyst/v1/library/items/{id}/handoff?target=decision_studio`
- `/wp-json/sustainable-catalyst/v1/library/items/{id}/handoff?target=site_intelligence`

Canonical publications and public relationship metadata remain in WordPress. Personal Notebook objects and saved application handoffs remain in the visitor's browser unless exported.
