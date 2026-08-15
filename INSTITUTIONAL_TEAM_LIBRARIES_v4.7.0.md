# Sustainable Catalyst Library v4.7.0 — Institutional & Team Libraries

## Purpose

v4.7.0 adds durable private organizational curation spaces above the v4.6.0 Collaborative Research Rooms layer. Research Rooms remain project-scoped collaboration spaces; Team Libraries are longer-lived shared libraries for teams, programs, labs, departments, and institutional research communities.

## Canonical identity reuse

The build does not create a parallel institution registry. Optional institutional context resolves against the existing v4.0 `sc_institution` and `sc_research_unit` records. A stored institution or unit ID is contextual metadata only. It does not prove legal ownership, employment, membership, authentication, subscription entitlement, borrowing rights, or access to restricted institutional records.

## Private storage

Team Libraries use the private `sc_team_library` post type with `post_author` as the accountable owner. Versioned metadata stores:

- library URN
- optional canonical institution ID
- optional canonical research-unit ID
- explicit team memberships
- team-defined collections
- explicitly contributed references
- bounded activity lineage

No My Library item, Research Project, Research Room, Reading Notebook body, Evidence Matrix body, private source binary, credential, or Workspace state is copied automatically.

## Roles

- **Owner** — accountable creator; membership, collection, contribution, and governance authority.
- **Steward** — delegated team administration and curation authority.
- **Editor** — manages team collections and contributes references.
- **Contributor** — contributes references.
- **Reader** — read-only member.

Membership is scoped to the Team Library. It grants no automatic access to a member's personal Library, private projects, Research Rooms, notebooks, matrices, or linked institutional systems.

## Collections and references

Team collections are organizational shelves inside the Team Library. They are not migrations of personal collections. References are explicit, references-only records containing a stable ID/URN when available, title, URL when available, type, provenance, contributor identity, and contribution time. No referenced binary or underlying private record body is copied.

## REST and UI

Shortcode:

`[sc_institutional_team_libraries title="Institutional & Team Libraries"]`

Authenticated REST base:

`/wp-json/sc-library/v1/team-libraries`

Member, collection, and reference writes use role checks in addition to the signed-in REST boundary.

## Safety and governance boundaries

- No automatic publication.
- No automatic evidence promotion.
- No automatic Workspace write.
- No private-source binary copying.
- No personal-library copying.
- No notebook/matrix/project body copying.
- No institution membership or entitlement inference.
- Activity lineage is bounded and append-oriented.
- Existing institutional/archive, Research Room, Knowledge Graph, Project, and personal-research stores remain canonical.
