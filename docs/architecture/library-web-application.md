# Independent Library Web Application

Knowledge Library v5.60.0 introduces the first deployable web client outside WordPress.

## Boundary

`library-web` owns presentation state only. The authoritative research service remains `/api/library/v1`, backed by Python/PostgreSQL/workers/artifacts/pipelines/native runtimes. The web client stores no API secrets and owns no research records.

## Deployment

The initial client is a static standards-based SPA served by its own Nginx container. Nginx proxies same-origin `/api/library/*` calls to `sc-library-backend:8080` on the private `sc-internal` network. The public deployment can be mapped to `library.sustainablecatalyst.com` without WordPress in the execution path.

## Foundation surfaces

1. Search — hybrid, lexical, or semantic discovery through API v1.
2. Reader — direct record loading and metadata/body rendering.
3. Discover — API capability-family browser.
4. System — direct runtime/readiness observability.

WordPress may link to or embed the application, but it is neither required nor authoritative.
