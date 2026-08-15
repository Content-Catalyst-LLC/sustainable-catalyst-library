# Sustainable Catalyst Library v4.6.0 — Collaborative Research Rooms

## Purpose

v4.6.0 adds a private collaboration layer around the existing personal research environment. A Collaborative Research Room is anchored to one canonical Research Project owned by its creator, but the room is not a replacement project and does not transfer project ownership to room members.

## Core model

The canonical room record is the private hidden post type `sc_research_room`.

Stable room identity:

- schema: `sc-library-collaborative-research-room/1.0`
- room URN: `urn:sc:research-room:{uuid}`
- owner: WordPress `post_author`
- anchor: one existing `sc_research_project`

Room metadata:

- `_sc_research_room_project_id_v460`
- `_sc_research_room_urn_v460`
- `_sc_research_room_members_v460`
- `_sc_research_room_references_v460`
- `_sc_research_room_notes_v460`
- `_sc_research_room_decisions_v460`
- `_sc_research_room_activity_v460`

## Roles

- **Owner** — owns the room and underlying project; manages members; shares references; adds notes; records decisions.
- **Editor** — shares references, adds review notes, and records decisions.
- **Reviewer** — reads the room and adds review notes.
- **Observer** — read-only room access.

Only the room owner can manage membership. An `owner` role cannot be assigned to another account through room membership.

## Project boundary

Room membership does **not**:

- transfer project ownership;
- grant direct access to the owner’s complete Research Project;
- grant access to all Source Bundles or project references;
- expose My Library, saved research, Reading Notebook bodies, Evidence Matrix bodies, or Workspace state.

Only references explicitly shared into the room become visible to room members.

## Reference sharing

Room shares are references-only metadata using `sc-library-research-room-reference/1.0`. They can carry:

- stable/canonical ID or URN;
- title;
- URL;
- reference kind;
- provenance;
- sharing user and timestamp.

The room does not copy source binaries, credentials, attachment paths, or private record bodies.

## Review notes and decisions

Review notes use `sc-library-research-room-note/1.0`. They are human-authored collaboration records and may optionally point at a shared room reference.

Decision records use `sc-library-research-room-decision/1.0`. They capture a human-entered statement, optional rationale, status, recording user, and timestamp. They are not inferred conclusions and do not alter Evidence Matrix claim states.

## Activity lineage

Each explicit room mutation writes a bounded append-only activity record under `sc-library-research-room-activity/1.0`. Activity captures actor, action, object kind/ID, summary, and timestamp. The bounded history prevents unlimited user-meta/post-meta growth while preserving recent collaboration lineage.

## API

Authenticated base:

- `GET /wp-json/sc-library/v1/research-rooms`
- `POST /wp-json/sc-library/v1/research-rooms`
- `GET /wp-json/sc-library/v1/research-rooms/{id}`
- `POST /wp-json/sc-library/v1/research-rooms/{id}/members`
- `POST /wp-json/sc-library/v1/research-rooms/{id}/references`
- `POST /wp-json/sc-library/v1/research-rooms/{id}/notes`
- `POST /wp-json/sc-library/v1/research-rooms/{id}/decisions`

All room REST routes require the authenticated Sustainable Catalyst/WordPress account. Per-room role checks are applied again inside each mutation.

## User interface

Shortcode:

`[sc_collaborative_research_rooms title="Collaborative Research Rooms"]`

The Research Library places the surface immediately after the Unified Personal Research Environment so collaboration is treated as a continuation of private project work rather than as public publishing.

## Explicit non-goals

v4.6.0 does not provide:

- public rooms;
- anonymous guest links;
- automatic email invitations;
- real-time WebSocket presence;
- simultaneous collaborative document editing;
- automatic source/evidence synchronization;
- automatic publication;
- automatic Evidence Matrix promotion or claim changes;
- automatic Workspace writes;
- implicit exposure of a member’s personal research.
