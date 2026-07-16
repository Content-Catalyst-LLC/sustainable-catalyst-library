# Production Checklist — Knowledge Library v3.9.0

## Installation

- Confirm plugin version 3.9.0.
- Confirm API, Export & Federation appears under SC Library.
- Confirm Federation Peers and Webhooks appear.
- Confirm v3.8.0 Review & Publishing remains available.

## Public API

- Test capabilities.
- Test each catalog type.
- Test record lookup.
- Test cursor pagination.
- Test limits.
- Test ETag and 304 behavior.
- Confirm public cache headers.
- Confirm private no-store headers.
- Confirm security headers.
- Confirm redaction.

## Tokens

- Create a token.
- Confirm it displays once.
- Verify only the hash is stored.
- Test every scope.
- Test expiration.
- Test revocation.
- Test rate limiting.
- Rotate the test token.

## Exports

- Test JSON.
- Test JSON-LD.
- Test NDJSON.
- Test CSV.
- Test ZIP bundle.
- Interrupt and resume.
- Verify counts and hashes.
- Verify private export storage.
- Test authenticated download.
- Test deliberately public exports.
- Test expiration and cleanup policy.

## Federation peers

- Test valid HTTPS peer.
- Test HTTP rejection.
- Test localhost and private-IP rejection.
- Test incompatible schema.
- Test degraded, suspended, and blocked states.
- Verify trust and scopes.

## Webhooks

- Test HMAC signatures.
- Test timestamp validation in the consumer.
- Test no-redirect behavior.
- Test retries.
- Test idempotency.
- Rotate the secret.
- Confirm private data is absent.

## Imports

- Test valid quarantine.
- Test missing schema.
- Test malformed records.
- Test oversized payload.
- Test insufficient peer trust.
- Approve metadata.
- Reject and archive.
- Confirm no automatic public content creation.

## Operations

- Run migration to Complete.
- Test cron queues.
- Test REST and WP-CLI.
- Inspect redacted audit logs.
- Test large-catalog performance.
- Test multisite or distributed rate-limit behavior where applicable.

## Regression

- Run the explicit v3.9.0 release manifest.
- Confirm all retained v2.4.0-v3.8.0 contracts.
- Confirm ZIP integrity.
- Confirm no tokens, secrets, export files, imports, or private content are packaged.
