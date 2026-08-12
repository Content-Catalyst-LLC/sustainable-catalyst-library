# Sustainable Catalyst Library v4.3.27 — Canonical Routing, Identity & Account Continuity

## Added

- Canonical public Library route contract for `/knowledge-libraries/`.
- Safe permanent redirect from the retired public `/library/` route.
- Query-string preservation on the legacy redirect.
- Route and identity health endpoint at `sc-library/v1/runtime/identity-health`.
- Shared-account continuity contract documenting one Sustainable Catalyst/WordPress account across private Library tools and Workspace.
- `[sc_library_account_continuity]` shortcode and restrained account-continuity presentation.

## Repaired

- Plugin URI now points to the canonical public Knowledge Library route instead of `/library/`.
- Public routing is separated from API namespace semantics so stale-route cleanup cannot mutate stable REST contracts.

## Preserved

- Public discovery without sign-in.
- Existing My Libraries account data.
- Existing Citation Studio personal source and collection ownership.
- Existing Open Course learning-plan data.
- Existing Research Document Builder drafts.
- v4.3.26 Public Library Network & Local Access.
- v4.3.25 Institutional Connector Expansion.
- v4.3.24 Research Librarian Access Intelligence.
- v4.3.22.4 Publications all-fields runtime.

## Deployment note

After plugin deployment, replace the Research Library / Knowledge Libraries page body with `RESEARCH_LIBRARY_PAGE_v4.3.27.html`, confirm that the WordPress page slug is `knowledge-libraries`, clear caches, and then check `/wp-json/sc-library/v1/runtime/identity-health`.
