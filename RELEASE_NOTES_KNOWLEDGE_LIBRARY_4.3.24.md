# Sustainable Catalyst Library v4.3.24

## Research Librarian Access Intelligence

v4.3.24 turns Research Access evidence into a clear, ranked access decision layer for the Research Librarian and the public Research Access interface.

### Added

- Research Librarian `access` intent and Research Access routing target.
- Explicit access states for open access, public digital collections, library membership, institution login, preview, ILL/request, physical holdings, catalog checks, metadata-only records, and unconfirmed access.
- `Check access` action on normalized Research Access results.
- Non-consuming sealed-result access check so access evaluation does not invalidate subsequent Save to My Sources / import actions.
- Source-level access intelligence shortcode and REST route.
- Access evidence rendering in compact and full Research Librarian interfaces.
- Freshness/staleness warnings and explicit availability-versus-entitlement boundaries.

### Reused

The release uses the existing connector holdings-reliability layer, OpenURL/library routes, My Libraries context, and Citation Source Manager rather than introducing a second holdings model.

### Correctness boundary

A resource being discoverable or held somewhere does not establish that a particular user is entitled to open it. Sustainable Catalyst identifies legitimate routes and evidence; provider/library/institution systems remain authoritative for access.

### Preserved

- Research Document Builder v4.3.23 and DOCX/PDF exports.
- Citation Studio and personal source ownership.
- Course Access / Learning Pathways.
- Global Library Search / My Libraries.
- Publications v4.3.22.4 all-fields stack with 14 major fields rendered simultaneously.
