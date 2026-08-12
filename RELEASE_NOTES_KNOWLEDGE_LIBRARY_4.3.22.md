# Release Notes — Sustainable Catalyst Library v4.3.22
## Citation Studio & Source Manager

v4.3.22 adds the personal source-management layer between Research Access and document production.

### Added
- New `[sc_citation_studio]` public-page interface.
- Private **My Sources** for signed-in Sustainable Catalyst / Workspace accounts.
- **Save to My Sources** action on normalized Research Access results.
- Personal source notes and collections.
- Search and collection filtering inside Citation Studio.
- Citation-style switching and copy controls.
- Harvard, APA 7, MLA 9, Chicago Author-Date, Chicago Notes & Bibliography, IEEE, Vancouver, AMA, and ACS Author-Date profiles.
- BibTeX, RIS, and CSL-JSON export.
- DOI/ISBN normalization and personal duplicate checks.
- Research Access provenance retained on saved personal sources.
- New Citation Studio section on the Research Library page.

### Preserved
- v4.3.21.1 Publications runtime recovery and server-side fallback navigation.
- v4.3.21 Course Access Intelligence & Learning Pathways.
- v4.3.20.2 Knowledge Pathway editorial index.
- Global Research Access, My Libraries, Digital Access Resolver, scholarly connectors, Research Librarian, Workspace, Field Spotlight, and Publications registry.

### Account boundary
Public discovery does not require an account. Source saving, private notes, private collections, and personal exports require the same authenticated Sustainable Catalyst / Workspace account used elsewhere in the research environment.

### Deployment
Replace the Research Library page body with `RESEARCH_LIBRARY_PAGE_v4.3.22.html` after plugin deployment. The Publications page body does not need replacement.
