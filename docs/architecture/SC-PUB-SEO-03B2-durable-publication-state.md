# SC-PUB-SEO-03B2 — Durable Publications State Reconciliation

**Status:** implementation candidate only; production NO-GO pending staging and performance acceptance.  
**Dependency:** stacked PR #15 → #16 → #17 → 03B2; no merges/deployment have been authorized here.

## Purpose

Make the visitor-interest discovery candidate service fail closed when an editorial WordPress publication is withdrawn, becomes password-protected, loses an Article Map relationship, changes canonical URL, or its source-sync is no longer fresh. Preserve the existing WordPress publication workflow and Library catalog authority.

## Implementation

1. **MySQL WordPress source outbox** (`class-sc-library-publications-state-outbox.php`): feature-gated to local/staging, enqueue source-sequenced events in non-autoloaded WordPress options before network delivery. WordPress cron attempts delivery with bounded exponential retries. A periodic published-post scan re-queues current editorial state for expiring approval refreshes. No visitor information is collected by this code. The previous WordPress signed record indexing and deletion hooks remain unchanged.
2. **Signed Python event endpoint** `POST /v1/publications/manifest/events`: uses the existing bearer/API HMAC authorization and is disabled in production. Strict versioned event schema; only `wordpress:{site}:post:{id}` source identities accepted. Approval requires corroboration by the **currently indexed** WordPress record, matching exact permalink, manifest schema, field, Article Map key, and source fingerprint.
3. **Durable PostgreSQL ledger** `publication_discovery_states`: highest source sequence stored for each record, including a withdrawal tombstone after the main record is removed. Duplicate events are idempotent; equal sequence with a different body is rejected; earlier events cannot revive a newer withdrawal. Approvals expire after 15 minutes without renewal, and absent/expired ledger state denies discovery.
4. **Fail-closed discovery query**: candidate SQL now INNER JOINs the ledger requiring an unexpired approved lease, matching source fingerprint, canonical URL, field and map key. No direct public candidate access.
5. **Server-to-server reader**: `GET /api/library/v1/publications/discovery` now also requires the existing signed bearer/HMAC protocol and rejects production runtimes. WordPress’s staging preview uses `SC_Library_Python_Backend::signed_request` instead of an anonymous GET, and then re-verifies the current actual WordPress post and map before generating any public HTML links.
6. **Tests**: Python schema/sequence/idempotency/withdrawal/privacy matching; PHP editorial manifest fingerprint and outbox event serialization; Library baseline CI.

## Read-only staging preflight

A standalone Python tool at `tools/publications-03b2-staging-preflight.py` refuses ordinary production hostnames, follows no redirects, and performs **anonymous GETs only**. It checks for an HTML Publications page with crawlable field links and an API endpoint that rejects anonymous access.

From a checked-out copy of this branch, and only after staging hosts are supplied:

```bash
python3 tools/publications-03b2-staging-preflight.py \
  --wp-base "https://YOUR-WP-STAGING-HOST" \
  --api-base "https://YOUR-API-STAGING-HOST" \
  --report /tmp/SC-PUB-SEO-03B2-anonymous-preflight.json
```

The script **does not** enable discovery, modify content, or certify the signed lifecycle. Every `not_covered` item in its evidence report remains a separate acceptance obligation.

**Outbox throughput:** up to 40 state renewals are enqueued every 2 minutes; the retry worker handles up to 40 events per minute. A matching signed approval is renewed without re-indexing the full WordPress article. Staging must confirm the total eligible count and actual processing cadence against the 15-minute lease. A backlog reaching the 800-row scan ceiling is a hard NO-GO. With missing cron triggers or exhausted lease capacity, discovery fails closed by expiry.

## Source flow

```
WordPress publish/edit/status/password/delete
  → queue editorial event and global sequence in wp_options
  → WordPress signed /v1/ingest/records (for current eligible posts)
  → signed POST /v1/publications/manifest/events
  → PostgreSQL sequence ledger (ack only after commit)
  → signed server GET for discovery candidates
  → WordPress current-status/permalink verification
  → ordinary HTML links for visitor
```

No event can be used as an authorization to expose a nonpublic WordPress record. The Python catalog does **not** override WordPress access control.

## Critical release acceptance still required

- **Source sync/WordPress environment:** staging run with a production-equivalent WordPress clone, real PostgreSQL migration and signed credentials. Verify publish → update → protect → unprotect → change permalink → withdraw → republish → delete, with deliberate API outages/reorder/retries. Test 410/404 and cache behavior. No live production attempts yet.
- **Index delivery and freshness:** confirm that every current approved post is properly indexed before the ledger is approved, and that the PHP outbox continues retrying after a network interruption. Test WordPress cron under the actual hosting scheduler, including event option persistence and MySQL concurrency.
- **Lease/cardinality:** the current 15-minute lease and 40-record/2-minute reconciliation batches can renew approximately 300 distinct records per 15 minutes in ideal conditions, but repeated events, transient failures, and Bluehost cron execution budgets reduce actual capacity. Measure content count, request timings, job throughput and expiry failures. **Do not enable the public UI** until these are tuned and soak-tested.
- **Actual HTTP visibility:** verify a direct anonymous GET to Python candidates returns 401/404 and no public metadata, while authenticated WordPress can read only accepted candidates and the public page still checks WordPress status. Review WAF and proxy logs to avoid logging bearer keys/visitor search terms.
- **SEO and route quality:** preserve all valid published canonical pages; do not use a stale map redirect or guess the three known 404 aliases; reconcile Issue #13 before creating those links. No automatically Private maps.
- **Query performance:** run `EXPLAIN (ANALYZE, BUFFERS)` and concurrency simulations against realistic staging data; the interest OR predicates may need more suitable indexed search before production.
- **Security review:** confirm cryptographic signature verification, replay/skew controls, idempotent and conflicting event behavior, no event body storage beyond hash, and only local/staging feature enablement.
- **Rollback:** toggles remain off by default. Backup WordPress plugin/options and PostgreSQL staging ledger; rollback code independently from state ledger and preserve source/editorial post status. Full release requires a separately approved deploy/rollback procedure.
- **Publication authority:** Source manifest is a stale snapshot until signed delivery and acknowledgement. The ledger is an additional **deny** gate, never sufficient by itself for publication authorization.

## Flags

- WordPress `SC_LIBRARY_PUBLICATIONS_MANIFEST_SYNC` must be true **and** `wp_get_environment_type()` in `local/staging`.
- WordPress `SC_LIBRARY_PUBLICATIONS_INTEREST_PREVIEW` must be true **and** staging/local.
- Python `SC_PUBLICATIONS_DISCOVERY_API_ENABLED=1` **and** `SC_LIBRARY_ENVIRONMENT` in `staging/local/development/test`.

Do **not** set these on production. The PHP runtime gates are hard coded to reject production. The Python endpoints return 404 there irrespective of the flag.

## Ownership

WordPress/Library editorial system is authoritative over canonical public posts and editorial connections. Python is authoritative over candidate retrieval and the durable approval ledger. Research Hub continues owning formal scientific outputs such as Wexford; this implementation does not alter research publication state.

**Related:** PUB-01 Issue #13; PUB-03 Issue #14; GEO Issue #18; PUB-TOPIC-01 Issue #19 (the topic CTA links to the existing Feedback page, not a new form).

## Next acceptance step

Run the real cross-service staging lifecycle with fixtures and measure renewal throughput. The technical implementation can be reviewed now, but **PUB-03B2 is not production-ready** until those acceptance gates have been completed successfully.
