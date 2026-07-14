# Connector Reliability Guide — v2.6.1

## Health states

### Unknown

No request outcome has been recorded.

### Healthy

The most recent provider request succeeded.

### Degraded

A request failed, but the failure threshold has not been reached.

### Open

The connector has paused requests because of repeated failures, rate limiting, or a provider-directed cooldown.

### Half-open

The cooldown expired. The next request acts as a recovery probe.

## Failure threshold

Default circuit threshold:

```text
3 consecutive failures
```

Default circuit cooldown:

```text
10 minutes
```

A provider `Retry-After` value can replace the default cooldown within the configured safety bounds.

## Retry policy

Maximum attempts:

```text
3 total attempts
```

Retryable statuses:

```text
408
425
429
500
502
503
504
```

Long delays are not performed inside a user-facing WordPress request. When a provider asks for a longer wait, the connector opens its circuit and returns control to WordPress.

## Stale search results

The normal provider search cache follows the duration configured in the Providers screen.

A separate recovery cache can remain for up to:

```text
7 days
```

Recovery results are always marked stale. They are not represented as current provider responses.

## Conditional response cache

ETag and Last-Modified validators are stored separately from response bodies.

A `304` response can reuse the cached JSON body. Missing-body recovery removes the invalid validator pair and retries without conditional headers.

## Health resets

Use:

```text
SC Library → Source Discovery → Providers
```

Then choose **Reset Provider State**.

This clears the local health and circuit state. It does not change provider credentials or external quotas.

## Cache clearing

**Clear Retained Connector Cache** removes:

- retained stale-search caches
- linked fresh-search transients
- indexed conditional-response bodies
- stored HTTP validators when clearing all providers

## Scheduled maintenance

The hourly maintenance task is protected by a 20-minute lock.

It processes at most 10 due Source records per run after examining a bounded candidate window.

A traffic-free WordPress site may require a real server cron that calls WordPress cron reliably.
