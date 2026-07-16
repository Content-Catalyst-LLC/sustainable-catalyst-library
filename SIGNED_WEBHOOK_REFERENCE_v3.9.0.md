# Signed Webhook Reference — v3.9.0

## Signature

```text
signature = HMAC-SHA256(secret, timestamp + "." + raw_body)
```

Header:

```text
X-SC-Webhook-Signature: sha256=<hex digest>
```

## Verification

Consumers should:

1. read the raw request body;
2. read the timestamp header;
3. reject stale timestamps;
4. reconstruct the signed string;
5. compute HMAC-SHA256 with the shared secret;
6. compare signatures with a constant-time function;
7. deduplicate by delivery ID;
8. process idempotently.

## Retry behavior

The Knowledge Library uses bounded exponential delay and stops after five failed attempts.

Redirects are disabled.

## Events

Initial event vocabulary:

```text
export.completed
document.published
publication.published
source.integrity.changed
review.approved
*
```

## Privacy

Webhook payloads must remain metadata-bounded.

Do not include raw tokens, private document bodies, private reviewer notes, conflicts, or filesystem paths.
