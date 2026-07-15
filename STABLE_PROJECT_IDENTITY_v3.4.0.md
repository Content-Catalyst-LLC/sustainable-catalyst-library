# Stable Cross-Product Project Identity — v3.4.0

## Identity schema

```text
sc-platform-project-identity/1.0
```

A Research Project receives:

```text
uuid
urn
wordpress_id
slug
title
canonical_url
edit_url
aliases
created_at
updated_at
```

## URN

```text
urn:sc:research-project:{uuid}
```

The UUID is generated once and stored in project metadata. The URN is deterministic from the UUID.

## URL changes

When the project slug changes, the earlier URL is retained in the alias list. The UUID and URN do not change.

## Migration

Existing Research Projects are processed in stable post-ID order.

Migration state:

```text
version
status
cursor
total
processed
failures
last_error
started_at
updated_at
completed_at
```

The migration runs in 25-project batches and can resume after timeout or deployment interruption.

## Use by other products

Target products should store the UUID or URN as the canonical cross-product identifier. WordPress IDs and URLs should be treated as local references.
