# Sustainable Catalyst Library v1.20.0 — Accessibility, Mobile, Performance, and Security Hardening

## Accessibility

- Added a consistent skip link and focus destination for Library interfaces.
- Added visible `:focus-visible` treatment, polite live announcements, reduced-motion support, forced-colors support, labeled scrollable tables, mobile-safe form sizing, and canvas accessibility labels.
- Added public readiness status with aggregate-only data.

## Mobile

- Enforced configurable touch targets, single-column narrow-screen actions, responsive tables, bounded dialogs, and viewport-safe media and graph surfaces.
- Retained the v1.14.1 public record-card width repair and print fallbacks.

## Performance

- Added bounded transient/object-cache support for an explicit allowlist of anonymous public REST GET endpoints.
- Added generation-based invalidation after content or taxonomy changes.
- Added cache HIT/MISS headers, configurable TTL, index-volume diagnostics, cron diagnostics, and persistent-object-cache reporting.

## Security

- Added route-specific anonymous REST throttling.
- Added `nosniff`, strict-origin referrer policy, and restrictive device-permission headers.
- Added checks for HTTPS, debug display, dashboard file editing, SSL administration, API-key hash storage, and remote-media configuration.
- Authenticated, protected, session, job, webhook, extraction, migration, synchronization, and reindex operations are excluded from public caching.

## Operations and portability

- Added `wp_sc_library_readiness_runs` for report history.
- Added a resumable operational boundary through daily readiness evaluation and one-click schedule repair.
- Advanced portable exports to `sc-library-portable-export/2.1` with normalized `readiness_runs`.
