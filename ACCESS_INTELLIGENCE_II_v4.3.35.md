# Access Intelligence II — v4.3.35

## Purpose

Access Intelligence II turns access evidence into a transparent ranked pathway without changing the underlying v4.3.24 classification model.

## Architecture

- v4.3.24 `SC_Library_Research_Librarian_Access_Intelligence` remains the evidence/classification authority.
- v4.3.35 `SC_Library_Access_Intelligence_II` adds ranking, confidence, connected-library fit, unresolved questions, and fallback sequencing.
- `sc_library_my_libraries_v4319` is read as a user-declared relationship only. It is not proof of active credentials, subscription coverage, borrowing eligibility, or a holding.
- Public fallback discovery uses the existing Public Library Network registry.
- Connected institutional fallbacks reuse the v4.3.25 Institutional Connector registry where possible.

## Ranking

The ranking is deterministic and inspectable. Direct public routes receive a bonus; stale routes receive a penalty; a user-declared connected library can improve relevance; search-only routes receive a penalty because they do not confirm holdings. Every ranked path carries its score and `rank_reasons`.

## Confidence

Confidence describes the route evidence, not whether the user is entitled to use the resource: `direct-route-identified`, `provider-route-identified`, `connected-library-search-path`, `discovery-fallback`, `stale-route`, or `unconfirmed`.

## Hard boundaries

- Availability is not entitlement.
- A catalog search is not a holding.
- A holding is not user eligibility.
- A connected library is a user-declared relationship, not credential verification.
- Sustainable Catalyst stores no external-library passwords or PINs.
- Provider and library sites remain authoritative for current availability, rights, authentication, borrowing, and request conditions.
- No automatic subscription or access claim is made.
