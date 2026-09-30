# Sustainable Catalyst Knowledge Library Web

**Web:** v1.0.0  
**Library:** v5.60.0  
**API:** v1 (`/api/library/v1`)

This is the first independent Knowledge Library web application. It is a deployable static client served by Nginx and talks directly to the authoritative Library API. WordPress is not required for application execution.

## Foundation surfaces

- Search: hybrid/lexical/semantic Library search.
- Reader: direct record retrieval and readable record body/metadata.
- Discover: API capability-family browser.
- System: API/runtime-authority/federation/artifact/pipeline/compute readiness.

The application contains no API keys or research state. Nginx proxies same-origin `/api/library/*` requests directly to `sc-library-backend:8080` on the `sc-internal` network.
