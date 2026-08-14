# Sustainable Catalyst Library v4.3.30
## Unified Research Projects & Source Bundles

v4.3.30 makes the existing `sc_research_project` record the account-level container for ongoing research and adds source bundles that group references without copying their underlying records.

### Canonical project identity
The release reuses the established Research Project post type and the v3.4 stable project identity contract:

- `sc_research_project`
- `sc-platform-project-identity/1.0`
- `urn:sc:research-project:{uuid}`

A signed-in front-end user creates a private draft project owned by that WordPress account. The Library does not create a second user identity or a parallel project database.

### Unified project references
Project links use schema:

`sc-library-project-reference-link/1.0`

Supported reference families:

- Citation Studio / Research Source
- My Library item
- saved search
- watchlist item
- research queue item
- Citation Studio collection
- Research Document Builder draft
- saved course
- published Knowledge Pathway
- external reference

Each link stores a stable reference ID, project role, optional project note, and optional URL. The linked record remains canonical in its original system.

Project post-meta contracts:

- `_sc_project_unified_v4330`
- `_sc_project_unified_links_v4330`
- `_sc_project_source_bundles_v4330`
- `_sc_project_unified_updated_at_v4330`

### Source bundles
Bundle schema:

`sc-library-source-bundle/1.0`

Each source bundle has:

- stable UUID
- stable `urn:sc:source-bundle:{uuid}`
- title
- purpose
- description
- list of project-link IDs
- created/updated provenance

A bundle manifest resolves current references at read time and includes a SHA-256 checksum. Missing references are reported rather than silently deleted.

### No-duplication boundary
Source bundles are intentionally **references-only**:

- source content is not copied
- personal-library records are not copied
- saved searches/watchlists/queue items are not copied
- research-document drafts are not copied
- private binary attachments are not copied
- no project or bundle is automatically published
- no Workspace write happens automatically

This keeps Citation Studio, My Library, Saved Research, Document Builder, course plans, and Knowledge Pathways authoritative for their own records.

### Front-end interface
Shortcode:

`[sc_unified_research_projects title="Research Projects & Source Bundles"]`

The interface allows a signed-in user to:

1. create a private project;
2. add a research question and project status;
3. link existing Library/account records;
4. add external references;
5. remove project links without deleting underlying records;
6. create named source bundles from linked references;
7. retain stable project and bundle identities.

### Authenticated API
Current-user project state:

`GET /wp-json/sc-library/v1/research-projects`

Create project:

`POST /wp-json/sc-library/v1/research-projects`

Project state/update:

`GET|POST|PUT|PATCH /wp-json/sc-library/v1/research-projects/{id}`

Project references:

`POST|DELETE /wp-json/sc-library/v1/research-projects/{id}/links`

Source bundles:

`POST /wp-json/sc-library/v1/research-projects/{id}/bundles`

Bundle manifest/remove:

`GET|DELETE /wp-json/sc-library/v1/research-projects/{id}/bundles/{bundle_id}`

All project access is scoped to the signed-in post author on this front-end surface.

### Integration hooks
- `sc_library_unified_project_created`
- `sc_library_unified_project_updated`
- `sc_library_project_reference_linked`
- `sc_library_project_reference_unlinked`
- `sc_library_source_bundle_created`
- `sc_library_source_bundle_deleted`
- `sc_library_unified_project_state`
- `sc_library_source_bundle_manifest`
- `sc_library_link_project_reference`
- `sc_library_create_source_bundle`

### Preserved systems
v4.3.30 preserves v4.3.29 Saved Searches/Watchlists/Research Queue, v4.3.28 My Library, v4.3.27 canonical routing/account continuity, Public Library Network, institutional connectors, Access Intelligence, Research Document Builder, Citation Studio, Open Course Finder, and the v4.3.22.4 Publications stack.
