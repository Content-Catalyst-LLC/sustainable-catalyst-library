# WordPress Thin Adapter — v5.62.0

WordPress is a client adapter for the Sustainable Catalyst Knowledge Library, not a research runtime.

## Allowed responsibilities

- public routing
- SEO and public metadata
- launch and embed surfaces
- health and status display
- optional identity handoff
- legacy presentation compatibility

## Prohibited authorities

WordPress may not become authoritative for research objects, research execution, jobs, artifacts, pipelines, compute, identities, sessions, credentials, federation state, trust policy, or Platform Core promotion. Existing compatibility modules may render or proxy legacy presentation surfaces, but their presence does not alter the Library service authority boundary.

## Request direction

`WordPress -> /api/library/v1 -> Library backend -> PostgreSQL/workers/storage/pipelines/compute`

Independent Library Web continues to call `/api/library/v1` directly and does not traverse WordPress.
