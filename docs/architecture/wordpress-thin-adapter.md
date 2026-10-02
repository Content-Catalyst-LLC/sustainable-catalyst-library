# WordPress Thin Adapter Consolidation — v5.79.0

WordPress is an optional presentation/editorial adapter for the Sustainable Catalyst Knowledge Library. It is not the Library research runtime, identity/session authority, job fabric, federation runtime, or canonical research-state store.

## Allowed responsibilities

- public routing
- SEO and public metadata
- launch and embed surfaces
- health and status display
- optional identity handoff
- legacy presentation compatibility
- API-client adaptation

## Consolidation rule

New Library research/domain behavior must enter through `/api/library/v1`. Existing PHP modules classified as `retire-candidate` may remain temporarily for presentation or compatibility, but their presence does not confer domain authority.

v5.79 deliberately does not mass-delete the historical PHP surface. The v5.78 inventory contains 144 include files: 15 adapter/presentation files and 129 retire candidates. Removal is deferred to measured follow-up work after the independent-application gate so current WordPress pages and shortcodes are not broken by a one-shot cleanup.

## Next gate

v5.80.0 certifies the independent Library application with WordPress unavailable.
