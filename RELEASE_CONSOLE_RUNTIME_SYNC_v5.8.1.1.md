# v5.8.1.1 — Release Console Runtime Synchronization

## Problem repaired

The public homepage/research console was introduced in Library v5.7.1 and retained `SC_Library_Homepage_Console::VERSION = 5.7.1`. That historical module version was incorrectly reused as the public release identity in localized browser configuration, allowing the visible console to remain on an older version after the main plugin advanced.

## Identity model

v5.8.1.1 separates two concepts:

1. **Current Library release** — canonical `SC_LIBRARY_VERSION`; this is what public release surfaces display.
2. **Module provenance** — component-local `VERSION` constants; these continue to identify when a module line originated.

Historical module constants are therefore not mass-rewritten during every Library release.

## Runtime route

`GET /wp-json/sc-library/v1/runtime/release`

Returns a bounded public status object containing:

- current Library release version;
- installed WordPress Library version;
- version synchronization boolean;
- backend configured/online/state/service/version fields;
- homepage-console module provenance version.

The route does not expose API keys, backend credentials, private records, user state, or database credentials. Backend health is cached server-side for 60 seconds while the public response itself is marked `no-store`.

## Visible console

The Research Network terminal footer now carries independent runtime labels:

`LIBRARY: v5.8.1.1`

`BACKEND: v1.4.0 · ONLINE`

If backend health is unavailable, the Library release remains visible and authoritative; backend state degrades independently.

## Deployment

WordPress/plugin deployment only. Backend v1.4.0 remains current and requires no redeploy. No database migration is required.
