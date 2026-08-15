# Sustainable Catalyst Library v4.3.38

## Research Librarian II — Project-Aware Guidance

This release adds authenticated project-aware research guidance while retaining the existing Research Librarian as the canonical site-scoped retrieval/orchestration engine.

### Added

- `[sc_research_librarian_ii]`
- `/wp-json/sc-library/v1/research-librarian-v2/catalog`
- `/wp-json/sc-library/v1/research-librarian-v2/guidance`
- owned-project context catalog spanning Source Bundles, Reading Notebooks, and Evidence Matrices;
- deterministic source/read/evidence/access/learning/publication workflow diagnostics;
- safe handoff into the existing Research Librarian using only the question and public Research Source IDs;
- checksummed private guidance packets.

### Privacy and authority boundaries

Private project context is not forwarded to optional remote synthesis. The release does not automatically modify projects, notebooks, evidence, claims, publication state, or Workspace. Guidance remains descriptive and user-controlled.

### Preserved

v4.3.37 Publications ↔ Research Graph, v4.3.36 Open Learning II, v4.3.35 Access Intelligence II, v4.3.34 Metadata Quality, v4.3.33 Workspace continuity, v4.3.32 Evidence Matrix, v4.3.31 Reading Notebooks, and v4.3.30 Research Projects/Source Bundles remain intact.
