# Handoff Security and Delivery — v3.4.0

## Bearer delivery links

A delivery link contains a random token. Possession of the token grants temporary access to that handoff bundle.

Treat the link as sensitive.

## Token storage

The plaintext token is never stored.

The database stores:

```text
HMAC-SHA-256(token, WordPress auth salt)
expiry timestamp
```

Validation uses constant-time comparison.

## Expiry

Allowed expiry:

```text
1 to 30 days
```

Default:

```text
7 days
```

Rotating the token immediately invalidates the earlier link.

## Token permissions

A token can:

- read its handoff bundle;
- mark it Opened;
- report Accepted, In Progress, Completed, or Failed;
- submit a result URL and bounded metadata.

A token cannot:

- edit the Research Project;
- create another handoff;
- rotate itself;
- archive the handoff;
- change product configuration;
- access other handoffs.

## REST caching

Authenticated and token-authorized responses use:

```text
Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0
Vary: Cookie, Authorization
```

## Bundle content review

Editors should review a bundle before sending it to an external service. Private project, Source, Evidence Note, and Claim data may be included when the corresponding bundle sections are selected.

## Product routes

A launch URL can include the delivery URL and return link. Do not configure a product route on an untrusted domain.

## Revocation

Rotate the token or move the handoff to Cancelled or Archived. Token rotation is the immediate cryptographic revocation mechanism.
