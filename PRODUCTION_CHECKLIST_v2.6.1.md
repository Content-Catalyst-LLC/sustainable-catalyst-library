# Production Validation Checklist — Knowledge Library v2.6.1

## Baseline

- Confirm the plugin reports v2.6.1.
- Confirm all v2.6.0 providers remain visible.
- Confirm Citation Manager, Research Sources, Research Projects, OCR Review, and the document repository remain available.

## Provider health

- Run a successful query and confirm Healthy status.
- Confirm latency and last-success time appear.
- Simulate or observe a provider failure and confirm Degraded status.
- Produce three consecutive failures in a safe test environment and confirm Open status.
- Reset the provider and confirm Unknown status.
- Confirm one open provider does not prevent other providers from returning results.

## Retry behavior

- Test a temporary transport failure followed by success.
- Test HTTP 503 followed by success.
- Test HTTP 429 with Retry-After.
- Confirm WordPress does not sleep for a long provider cooldown.
- Confirm captured rate-limit headers appear in the health API.

## Conditional cache

- Confirm ETag or Last-Modified is stored when returned.
- Confirm a 304 response reuses the retained body.
- Remove the body cache and confirm the connector retries without validators.

## Stale recovery

- Run and cache a known query.
- Make the provider unavailable.
- Confirm retained results appear with a stale warning.
- Confirm the stale result receives a new import token.
- Confirm the UI does not label stale data as live.

## Import idempotency

- Import a result.
- Retry with the same idempotency key.
- Confirm the same Source result is returned.
- Confirm a duplicate Source is not created.
- Repeat with a provider/import fingerprint match.

## Conflicts

- Import into a Source with a different title, DOI, and publisher.
- Confirm local values remain unchanged in `fill_empty` mode.
- Confirm three conflicts are recorded.
- Resolve one with Use Provider Value.
- Resolve one with Keep Current Value.
- Dismiss one.
- Confirm provider-value use marks metadata unverified.

## Holdings

- Recheck a DOI Source.
- Recheck an ISBN Source.
- Recheck a Source with a Library Profile.
- Confirm freshness fields are stored.
- Change a timestamp to an expired value and confirm Stale count increases.
- Confirm duplicate URLs collapse into one location.
- Confirm newer location metadata wins.

## Library profiles

- Save an HTTP URL and confirm validation fails.
- Save a localhost URL and confirm validation fails.
- Save an unsupported catalog token and confirm validation fails.
- Publish an invalid profile and confirm it does not appear publicly.
- Correct and validate the profile.
- Confirm the published, enabled, valid profile appears publicly.

## Maintenance

- Confirm the hourly event is scheduled.
- Trigger the event manually.
- Confirm the maintenance lock prevents overlap.
- Confirm no more than 10 due Sources are rechecked in one run.
- Confirm migration advances in 40-record batches.

## REST API

- Test provider health as an editor.
- Confirm anonymous health access is rejected.
- Reset a provider as an administrator.
- Confirm a non-administrator cannot reset provider state.
- Test holdings read and recheck.
- Test conflict read and resolution.
- Test Library Profile validation.
