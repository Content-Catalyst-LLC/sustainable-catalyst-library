# Release Notes — Sustainable Catalyst Library v4.3.30
## Unified Research Projects & Source Bundles

### Added
- Private front-end Research Projects using the existing canonical `sc_research_project` type.
- Stable v3.4 project UUID/URN reuse rather than a second project identity system.
- References-only project links across Citation Studio sources, My Library, saved searches, watchlists, research queue items, source collections, Research Document Builder drafts, saved courses, Knowledge Pathways, and external references.
- Project-level source bundles with stable UUIDs and `urn:sc:source-bundle:*` identities.
- Bundle manifests that resolve current references and include SHA-256 checksums.
- `[sc_unified_research_projects]` shortcode.
- Authenticated `/wp-json/sc-library/v1/research-projects` project API and project/bundle subroutes.
- Stable hooks for later Workspace and Research Librarian integration.

### Privacy and duplication boundary
Projects are private by default and owned by the same WordPress account used throughout the Library and Workspace continuity layer. Project links and source bundles store references rather than copies. v4.3.30 does not duplicate private source content or binary attachments, publish projects automatically, or write into Workspace automatically.

### Compatibility
The release preserves v4.3.29 Saved Research, v4.3.28 My Library, v4.3.27 canonical `/knowledge-libraries/` routing, v4.3.26 Public Library Network, v4.3.25 institutional connectors, v4.3.24 Access Intelligence, v4.3.23 Research Document Builder, Citation Studio, Open Course Finder, and the v4.3.22.4 Publications stack.
