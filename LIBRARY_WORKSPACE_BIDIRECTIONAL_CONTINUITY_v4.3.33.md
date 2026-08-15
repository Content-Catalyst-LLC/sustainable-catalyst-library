# Library ↔ Workspace Bidirectional Continuity — v4.3.33

## Purpose

v4.3.33 connects the private Library research environment to Sustainable Catalyst Workspace without introducing a second account or a second source of truth. It reuses the v3.4 signed cross-product handoff transport and v3.4 stable Research Project identity.

## Outbound Library → Workspace

A signed-in user explicitly chooses a project-anchored context: the Research Project itself, a Source Bundle, a Reading Notebook attached to that project, or an Evidence Matrix attached to that project. The Library creates a signed, expiring, references-only handoff record targeted to `workspace`. The receiving Workspace must explicitly consume the continuity fragment and retrieve the signed endpoint.

## Workspace → Library reopen

Workspace can retain the Library return URL or stable Library URN/reference. The authenticated resolver `/wp-json/sc-library/v1/workspace-continuity/resolve?ref=...` maps a current user's project, bundle, reading notebook, or evidence-matrix reference back to the canonical `/knowledge-libraries/` surface. The existing handoff status endpoint may also record a returned result URL and status using the signed delivery token.

## Canonical ownership

Library records remain canonical in the Library. Workspace records remain canonical in Workspace. The shared Sustainable Catalyst account remains the identity boundary. No underlying Library record or private binary file is copied automatically. No handoff automatically publishes, promotes evidence, changes claim status, or writes changes back into either product.

## Supported continuity contexts

Research Projects, references-only Source Bundles, Reading Notebooks, Evidence Matrices, and the project-linked record families already supported by v4.3.30: Citation Studio sources and collections, My Library items/personal recommendations, saved searches, watchlists, research queue entries, Research Document Builder drafts, saved course-plan entries, and Knowledge Pathways.
