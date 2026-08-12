# Sustainable Catalyst Library v4.3.26 — Public Library Network & Local Access

## Added

- Public Library Network shortcode: `[sc_public_library_network]`.
- 11-library launch registry spanning major public systems, Library of Congress, and WorldCat.
- Query-aware public catalog handoffs where supported.
- Digital-resource, card/eligibility, and request/ILL handoffs where appropriate.
- Account-scoped “I have access / membership” and “Research Library” connection actions.
- Read-only `sc-library/v1/public-library-network` endpoint.
- Research Library page integration directly inside Research Access.

## Integrated

- Reuses the existing My Libraries user-meta contract.
- New public-library IDs are recognized by the existing source locator and Access Intelligence paths.
- Connected-library relationships remain distinct from entitlement: no holding or licensed access is inferred simply because a library is connected.

## Preserved

- v4.3.25 Institutional Connector Expansion.
- v4.3.24 Research Librarian Access Intelligence.
- v4.3.23 Research Document Builder and DOCX/PDF export.
- v4.3.22 Citation Studio.
- v4.3.22.4 Publications 14-field all-fields stack.
- Open Course Finder, Research Librarian, Research Access and Workspace handoffs.
