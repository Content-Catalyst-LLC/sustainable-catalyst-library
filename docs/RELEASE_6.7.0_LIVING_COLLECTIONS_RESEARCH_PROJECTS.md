# Sustainable Catalyst Library v6.7.0 — Living Collections & Research Projects

## Generations

- Library: 6.7.0
- Python backend: 3.7.0
- Independent Library Web: 2.7.0
- First-party SDKs: 1.7.0
- API generation: v1 (stable)

## Purpose

v6.7.0 turns saved research state into a continuously usable research workspace without creating a second persistence authority. Existing PostgreSQL research-state tables remain canonical. Living-collection behavior is expressed through existing collection metadata and collection membership tables.

## Capabilities

- Living collection definitions backed by an explicit discovery query and filters.
- Read-only refresh previews that identify candidate additions and retained matches.
- Explicit apply actions that revalidate candidates and persist only user-selected records.
- Project-linked living collections.
- Derived project briefs showing references, bundles, queue state, living collections, and optional research-graph context.
- Independent Web 2.7 controls for creating, refreshing, reviewing, and applying living collections.
- Python and JavaScript SDK methods for all v6.7 living-research APIs.

## Authority and safety rules

- `research_state.py` / PostgreSQL remains persistence authority.
- Advanced Discovery remains retrieval authority.
- Research Graph & Evidence Navigation remains graph authority.
- Refresh never mutates collection membership.
- Apply requires explicit selected record IDs, Library session ownership, and CSRF protection.
- Collection/project membership is organizational state, not a truth, importance, or evidence-quality score.
- No automatic publication or Platform Core promotion.
- WordPress remains an optional adapter.

## Database impact

No database migration is required.
