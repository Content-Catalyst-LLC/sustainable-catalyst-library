# Institutional Migration — v4.0.0

## Scope

The migration registers supported v2–v4 records with institutional UUIDs, URNs, visibility, governance state, optional default institution, registry hash, and timestamps.

## Defaults

Published public record families become Public and Published.

Private records become Institutional and Managed.

Draft and pending records become Institutional and Draft.

Institutions default to Active.

Research units default to Active and Research center.

## Reliability

```text
25 records per batch
Stable post-ID cursor
180-second lock
Bounded failure history
Hourly cron
AJAX
REST
WP-CLI
```

## Non-destructive behavior

The migration does not rewrite document content, publish private records, assign unit membership, approve governance states, create handoffs, issue API tokens, or change source and review records.
