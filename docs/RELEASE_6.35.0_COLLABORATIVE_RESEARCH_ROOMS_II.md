# Sustainable Catalyst Library v6.35.0 — Collaborative Research Rooms II

## Release generations

- Library: `6.35.0`
- Backend: `3.35.0`
- Web: `2.35.0`
- SDK: `1.35.0`
- API: `v1` stable

## Purpose

Collaborative Research Rooms II adds a governed collaboration control plane around existing Sustainable Catalyst research objects and portable exchanges. A room composes explicit members, roles, scoped object references, human-authored activity, review packets, access-policy observations, and exchange handoffs without replacing the authorities that own the underlying research state.

## New surface

- Web: `/research/rooms`
- API: `/api/library/v1/research-rooms`

## Operations

- `create-room`
- `membership`
- `object-manifest`
- `activity-stream`
- `review-request`
- `review-decision`
- `access-audit`
- `exchange-handoff`
- `export`

## Role model

Supported roles are `owner`, `steward`, `editor`, `reviewer`, `contributor`, and `viewer`. Permissions are explicit and deterministic. Room access audits report what the room role matrix would allow, but they do not authenticate a user, verify identity, or replace an external enforcement boundary.

## Authority guardrails

The room layer is not the identity, project-persistence, research-object, citation, evidence, truth, or publication authority. It never silently copies or rewrites authoritative research payloads. Object sharing is reference-based. Activity volume and room consensus do not imply evidence strength or truth. Review decisions remain explicit human assertions and do not mutate reviewed objects. Portable exchange handoffs do not automatically deliver, import, persist, merge, or promote any research object.

No database migration is required. WordPress remains optional and non-authoritative.

## Next release

`v6.36.0 — Library–Librarian Unified Research Intelligence`
