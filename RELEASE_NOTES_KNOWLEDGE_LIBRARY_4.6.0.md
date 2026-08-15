# Sustainable Catalyst Library v4.6.0 — Collaborative Research Rooms

v4.6.0 introduces private, project-anchored Collaborative Research Rooms for controlled multi-user research review.

## Added

- Private `sc_research_room` records with stable `urn:sc:research-room:{uuid}` identity.
- Owner/editor/reviewer/observer role model.
- Explicit membership management restricted to the room owner.
- References-only room sharing with provenance and stable identifiers.
- Human-authored review notes and decision records.
- Bounded append-only collaboration activity lineage.
- Authenticated `/wp-json/sc-library/v1/research-rooms` REST surface.
- `[sc_collaborative_research_rooms]` signed-in interface.
- Unified Personal Research Environment count/action integration.
- Production-readiness certification for the new private module, assets, and REST boundary.

## Preserved boundaries

Room membership does not transfer Research Project ownership and does not grant blanket access to the underlying project. My Library records, saved research, private notebook bodies, Evidence Matrix bodies, source binaries, credentials, and Workspace state remain private unless a safe references-only representation is deliberately shared into a room.

No automatic publication, evidence promotion, project write, or Workspace write is introduced.
