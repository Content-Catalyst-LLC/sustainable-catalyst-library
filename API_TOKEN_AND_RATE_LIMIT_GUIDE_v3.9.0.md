# API Token and Rate-Limit Guide — v3.9.0

## Token creation

Tokens begin with:

```text
sckl_
```

The full token is displayed only once.

The database retains:

```text
SHA-256 token hash
Short prefix
Scopes
Expiration
Revocation state
Last-used time
Rate limit
Creator
```

## Handling

Store tokens in an environment variable or secret manager.

Do not place tokens in URLs, source control, browser JavaScript, screenshots, or public documentation.

## Rotation

Rotate when:

- scope changes;
- staff access changes;
- a token appears in logs;
- a client system is compromised;
- expiration approaches;
- a federation agreement ends.

## Rate limits

Default:

```text
120 requests per minute
```

Maximum configurable token rate:

```text
5,000 requests per minute
```

The WordPress option-based limiter is suitable for the current release. Large distributed deployments should move limiting to Redis, an API gateway, Cloudflare, or another shared edge layer.
