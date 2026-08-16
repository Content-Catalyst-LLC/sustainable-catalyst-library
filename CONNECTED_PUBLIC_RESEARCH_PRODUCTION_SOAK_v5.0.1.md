# v5.0.1 — Connected Public Research Production Soak & Integration Hardening

## Purpose

v5.0.1 hardens the v5.0.0 Connected Public Research Infrastructure without adding a second graph, cache, diagnostics store, or content store. It reuses the existing Production Readiness engine and the v5.0 public composition layer.

## Ten bounded scenarios

1. Release and schema alignment.
2. v4.9 public API dependency and public object profiles.
3. Malformed index/context requests reject before data exposure.
4. Explicit one-hop network and 120-connection cap.
5. Deterministic SHA-256 manifest behavior.
6. Explicit safe-route cache allowlist.
7. CORS and cache/freshness observability.
8. Published-only federation boundary.
9. Private research and governance separation.
10. First-party degradation boundary: no third-party network calls and no private research-body inspection.

## Cache boundary

The existing Library hardening cache now recognizes only explicit v5 public route prefixes: the v4.9 Library API, v5 Connected Public Research, and the public federation node/manifest routes. The implementation does not broadly classify `/sc-library/v1` as public. Authenticated requests, authorization headers, API-key headers, WordPress nonces, private research routes, reports, diagnostics, writes, and governance surfaces remain non-cacheable.

Cacheable responses expose `X-SC-Library-Cache`, `X-SC-Library-Cache-Age`, `X-SC-Library-Data-State`, and `X-SC-Library-Freshness-Window`. Allowed cross-origin clients can read these headers without credentials. Cache invalidation remains generation-based and is triggered by the established save/term hooks.

## Soak endpoints

- Public summary: `/wp-json/sc-library/v1/runtime/connected-public-research-soak`
- Administrator details: `/wp-json/sc-library/v1/runtime/connected-public-research-soak/details`

The public summary contains scenario status only. The details endpoint is administrator-only and contains no private research bodies or credentials.

## Front-end degradation

The Connected Public Research index request now has a bounded 12-second timeout, explicitly omits credentials, distinguishes HTTP 429 from generic degradation, and falls back to a truthful message that canonical Library records remain available.

## Non-goals

No automatic remote polling, external synthetic traffic, third-party health dependency, private-data inspection, semantic inference, publication, evidence promotion, federation acceptance, or Workspace write is added.
