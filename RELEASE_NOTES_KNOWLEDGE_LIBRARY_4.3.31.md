# Sustainable Catalyst Knowledge Library v4.3.31

## Reading, Notebook & Annotation Workspace

v4.3.31 adds an authenticated, account-persistent reading layer to the Research Library while retaining the legacy browser-local Research Notebook for compatibility.

### Added

- Private `sc_reading_notebook` records owned by the signed-in WordPress/Sustainable Catalyst account.
- Stable Reading Notebook UUIDs and `urn:sc:reading-notebook:{uuid}` identities.
- Optional attachment to canonical v4.3.30 Research Projects and Source Bundles with ownership validation.
- Source-linked reading notes, questions, observations, summaries, methods, and reusable excerpts.
- Source annotations for page, section, timestamp, paragraph, and custom locators.
- Tags, pinning, and explicit ordering for notes and annotations.
- Authenticated current-user Reading Notebooks REST API and checksummed private notebook manifests.
- `[sc_reading_notebook_workspace]` front-end surface.
- Reading & Annotation section on the canonical `/knowledge-libraries/` page.
- Shared-account identity health now includes reading notebooks, reading notes, and source annotations.

### Preserved boundaries

- The existing browser-local `SC_Library_Notebook` and browser storage migration chain are not removed or rewritten.
- v4.3.30 Research Projects and Source Bundles remain the canonical project/source-bundle layer.
- Source records and private binary files are referenced rather than copied into notebooks.
- Notes and excerpts are user-authored or user-selected; no automatic AI note generation is introduced.
- An annotation is not automatically promoted to evidence or a claim.
- Private notebooks are not automatically published.
- No automatic Workspace write occurs in v4.3.31.

### Retained systems

v4.3.30 Research Projects, v4.3.29 Saved Research, v4.3.28 My Library, v4.3.27 canonical routing/account continuity, v4.3.26 Public Library Network, v4.3.25 institutional connectors, v4.3.24 Access Intelligence, v4.3.23 Research Document Builder, Citation Studio, Open Course Finder, and the v4.3.22.4 Publications stack remain intact.
