# Sustainable Catalyst Library v4.3.27 — Canonical Routing, Identity & Account Continuity

## Purpose

v4.3.27 establishes one authoritative public Library boundary before the next personal-library expansion.

## Canonical public route

- Canonical public Library URL: `https://sustainablecatalyst.com/knowledge-libraries/`.
- Legacy public route: `/library/`.
- The legacy public route receives a permanent `301` redirect to `/knowledge-libraries/`.
- Existing query strings are retained by the redirect.
- Redirect handling is limited to normal front-end `GET` and `HEAD` requests.
- WordPress admin, AJAX, and REST requests are excluded.

## API safety boundary

The release does **not** rename REST routes that contain `/library/`. Those strings are internal API namespaces and are stable application contracts, not stale public navigation.

Examples preserved include:

- `/wp-json/sustainable-catalyst/v1/library/...`
- `/wp-json/sc-library/v1/...`

This prevents canonical public-page cleanup from breaking Notebook, Orchestrator, Foundation Documents, multimedia, graph, integrations, or other existing API clients.

## Shared-account continuity

The Library does not create a second account system. Private Library state and Workspace continuity use the authenticated WordPress/Sustainable Catalyst user.

Existing account-owned contracts explicitly preserved:

- My Libraries: `sc_library_my_libraries_v4319`
- Citation Studio collections: `sc_library_source_collections_v4322`
- Research Document Builder drafts: `sc_library_research_documents_v4323`
- Open Course learning plan: `sc_library_course_plan_v4321`

Personal source ownership remains user-scoped through the existing Citation Studio source-owner contract.

External library passwords, PINs, and institutional credentials are not stored by this continuity layer.

## Runtime diagnostics

Read-only route:

`/wp-json/sc-library/v1/runtime/identity-health`

The health payload reports:

- plugin/release version alignment;
- canonical URL and page presence;
- legacy redirect contract;
- whether a legacy WordPress page still exists;
- API-namespace preservation;
- the shared-account continuity contract.

A runtime status of `ok` requires the plugin version to match v4.3.27 and the canonical `knowledge-libraries` WordPress page to exist in published state.

## Public account-continuity surface

Shortcode:

`[sc_library_account_continuity]`

Signed-out users see that public discovery remains open and that one Sustainable Catalyst sign-in enables private Library persistence and Workspace continuity.

Signed-in users see that My Sources, My Libraries, course plans, and research documents remain attached to their existing account, plus a direct Workspace handoff.

## Preserved systems

- v4.3.26 Public Library Network & Local Access
- v4.3.25 Institutional Connector Expansion
- v4.3.24 Research Librarian Access Intelligence
- v4.3.23 Research Document Builder
- v4.3.22 Citation Studio / My Sources
- v4.3.22.4 restored 14-field Publications stack
- Open Course Finder and learning-plan persistence
- Existing REST/API namespace contracts
