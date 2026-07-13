# Sustainable Catalyst Library v1.20.0

Library v1.20.0 is the final pre-2.0 hardening release. It preserves every v1.19.0 feature and adds a production-readiness layer across accessibility, mobile behavior, large-library performance, security, preservation, and operational diagnostics.

## Production Readiness

Open `SC Library → Production Readiness` to review:

- WordPress, PHP, HTTPS, and environment health
- keyboard, focus, reduced-motion, media-description, and PDF fallback checks
- mobile record cards, touch targets, responsive tables, and native interface checks
- Library index volume, cursor reconciliation, public cache, object cache, and cron status
- debug exposure, file editing, SSL administration, rate limits, API-key storage, and remote-media boundaries
- preservation snapshots, integrity audits, PDF extraction failures, and off-site backup reminders

Public summary:

```text
[sc_library_readiness_status]
```

REST endpoints:

```text
/wp-json/sustainable-catalyst/v1/library/readiness/status
/wp-json/sustainable-catalyst/v1/library/readiness/report
/wp-json/sustainable-catalyst/v1/library/readiness/run
/wp-json/sustainable-catalyst-library/v1/readiness
```

## Portable data

```text
sc-library-portable-export/2.1
```

New entity:

```text
readiness_runs
```

## Installation

Upload `sustainable-catalyst-library-v1.20.0.zip`, choose **Replace current with uploaded**, open **SC Library → Production Readiness**, run the complete report, repair maintenance schedules when needed, and clear all site/CDN caches.

See `ACCESSIBILITY_SECURITY_HARDENING_SETUP_v1.20.0.md` and `RELEASE_NOTES_1.20.0.md`.
