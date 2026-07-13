# Library v1.20.0 Accessibility, Mobile, Performance, and Security Setup

## Install

1. Upload `sustainable-catalyst-library-v1.20.0.zip` in WordPress.
2. Choose **Replace current with uploaded**.
3. Confirm version 1.20.0.
4. Clear WordPress, page-builder, Cloudflare, and browser caches.
5. Open **SC Library → Production Readiness**.
6. Select **Run complete readiness report**.

## Recommended initial settings

- Hardening layer: enabled
- Public REST cache: enabled
- Cache lifetime: 300 seconds
- Anonymous REST limit: 240 requests per five minutes per route and IP
- Minimum touch target: 44 pixels

The cache is restricted to explicit public GET routes. Signed-in requests, API-key requests, nonce-authenticated requests, protected routes, jobs, diagnostics, sessions, webhook operations, extraction, migration, synchronization, and reindex operations are never cached.

## Public status page

Create a WordPress page named **Library Status** and add:

```text
[sc_library_readiness_status]
```

The public component displays only aggregate status and category counts. It does not expose file paths, credentials, private workspace data, internal URLs, or detailed security findings.

## Accessibility verification

Test the public Library with keyboard-only navigation:

1. Use Tab from the top of the page and confirm the skip link appears.
2. Confirm every actionable control has a visible focus indicator.
3. Confirm result, graph, Notebook, PDF, multimedia, and archive controls are reachable.
4. Enable reduced motion in the operating system and confirm animations become effectively instant.
5. At 200% browser zoom, confirm no essential control is clipped.
6. Test tables at 390 pixels and confirm they become labeled horizontal regions rather than shrinking text.

## Mobile verification

Test at 390 and 320 CSS pixels:

- record cards remain full width
- action controls become one column on narrow screens
- form fields use mobile-safe font sizing
- touch controls remain at least 44 pixels high
- PDF records retain explicit Open and Download actions
- graph and board canvases stay inside the viewport

## Performance verification

The Production Readiness screen reports the current index count and scheduled reconciliation state. For a large Library, confirm:

- indexed records are close to eligible records
- the daily reconciliation is scheduled
- public cache returns `X-SC-Library-Cache: MISS` on the first request and `HIT` on a repeated anonymous request
- authenticated or protected responses never return a public cache hit

## Security boundaries

The release adds `X-Content-Type-Options`, `Referrer-Policy`, and a restrictive camera/microphone/geolocation Permissions Policy. It does not add a global Content Security Policy or frame policy because those could break approved WordPress and embedded platform workflows.

Production recommendations reported by the dashboard include HTTPS, disabled public debug output, disabled dashboard file editing, secure administration, scoped API-key hashes, disabled remote media by default, and verified off-site backups.

## Maintenance

The daily hardening job records a readiness report and advances the public-cache generation. Use **Repair maintenance schedules** if WordPress cron events are missing.
