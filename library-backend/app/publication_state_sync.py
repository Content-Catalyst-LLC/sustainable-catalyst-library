"""Signed source-state ledger for opt-in WordPress Publications discovery.

Only the WordPress server may submit events; the FastAPI route authenticates
the existing bearer/HMAC protocol. This module enforces monotonic sequence,
idempotent replay, an expiring approval lease and permanent withdraw tombstones.

IMPORTANT: This is discovery-specific state, not a change to research authority.
"""
from __future__ import annotations

from datetime import datetime, timezone
import hashlib
import json
import re
from typing import Any

from .db import get_pool

EVENT_SCHEMA = "sc-publications-state-event/1.0"
MAX_LEASE_SECONDS = 900
RECORD_ID_PATTERN = re.compile(r"^wordpress:[1-9][0-9]*:post:[1-9][0-9]*$")
FINGERPRINT_PATTERN = re.compile(r"^[a-f0-9]{64}$")
FIELD_PATTERN = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")


class PublicationStateError(ValueError):
    """Bad, conflicting, out-of-order or not-yet-indexed event."""


def normalize_event(payload: Any) -> dict[str, Any]:
    """Validate strictly; do not accept arbitrary JSON metadata as authority."""
    if not isinstance(payload, dict) or payload.get("schema") != EVENT_SCHEMA:
        raise PublicationStateError("unsupported-event-schema")
    if set(payload) != {
        "schema", "record_id", "sequence", "action", "canonical_url",
        "fingerprint", "field", "map_key",
    }:
        raise PublicationStateError("unexpected-event-fields")
    record_id = payload.get("record_id")
    seq = payload.get("sequence")
    action = payload.get("action")
    url = payload.get("canonical_url")
    fingerprint = payload.get("fingerprint")
    field = payload.get("field")
    map_key = payload.get("map_key")
    if not isinstance(record_id, str) or not RECORD_ID_PATTERN.fullmatch(record_id):
        raise PublicationStateError("invalid-wordpress-record-id")
    if type(seq) is not int or seq < 1 or seq > 9223372036854775807:
        raise PublicationStateError("invalid-sequence")
    if action not in ("approve", "withdraw"):
        raise PublicationStateError("invalid-action")
    if type(url) is not str or len(url) > 2048:
        raise PublicationStateError("invalid-canonical-url")
    if type(fingerprint) is not str or not FINGERPRINT_PATTERN.fullmatch(fingerprint):
        raise PublicationStateError("invalid-fingerprint")
    if not isinstance(field, str) or not isinstance(map_key, str):
        raise PublicationStateError("invalid-field-map")
    if action == "approve":
        from .publication_discovery_service import canonical_publication_url
        if canonical_publication_url(url) != url:
            raise PublicationStateError("invalid-approved-canonical")
        if not FIELD_PATTERN.fullmatch(field) or not FIELD_PATTERN.fullmatch(map_key):
            raise PublicationStateError("invalid-approved-field-map")
    elif url or field or map_key:
        raise PublicationStateError("withdrawal-must-not-contain-public-metadata")
    return {
        "record_id": record_id,
        "sequence": seq,
        "action": action,
        "canonical_url": url,
        "fingerprint": fingerprint,
        "field": field,
        "map_key": map_key,
    }


def event_digest(event: dict[str, Any]) -> str:
    raw = json.dumps(event, sort_keys=True, separators=(",", ":"), ensure_ascii=True)
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


def can_approve_indexed(indexed: dict[str, Any] | None, event: dict[str, Any]) -> bool:
    """The existing indexed record must corroborate the source-signed manifest."""
    if not isinstance(indexed, dict):
        return False
    metadata = indexed.get("metadata")
    if not isinstance(metadata, dict):
        return False
    return (
        indexed.get("visibility") == "public"
        and indexed.get("publication_status") == "published"
        and indexed.get("object_type") == "post"
        and indexed.get("canonical_url") == event["canonical_url"]
        and metadata.get("publication_manifest_schema") == "sc-publications-editorial-manifest/0.1"
        and metadata.get("publication_discovery_approved") is True
        and metadata.get("wordpress_public_verified") is True
        and metadata.get("wordpress_password_protected") is False
        and metadata.get("publication_fingerprint") == event["fingerprint"]
        and metadata.get("publication_field") == event["field"]
        and metadata.get("publication_map_key") == event["map_key"]
    )


def apply_publication_event(payload: Any) -> dict[str, Any]:
    """Atomic acknowledgement after index verification and durable ledger commit.

    Retries of a previously acknowledged sequence never extend an old lease.
    A higher-sequence withdrawal survives even when its catalog row is absent.
    """
    event = normalize_event(payload)
    digest = event_digest(event)
    record_id = event["record_id"]
    seq = event["sequence"]
    pool = get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            # A transaction-level per-record advisory lock also serializes the
            # not-yet-existing ledger row case. Hash collisions only add
            # contention, not incorrect authorization or ordering.
            cur.execute(
                "SELECT pg_advisory_xact_lock(hashtextextended(%s, 0))",
                (record_id,),
            )
            cur.execute(
                "SELECT sequence, event_digest, approved FROM publication_discovery_states "
                "WHERE record_id=%s FOR UPDATE",
                (record_id,),
            )
            existing = cur.fetchone()
            if existing and int(existing["sequence"]) >= seq:
                if int(existing["sequence"]) == seq and existing["event_digest"] == digest:
                    result = "replayed"
                elif int(existing["sequence"]) == seq:
                    raise PublicationStateError("conflicting-sequence")
                else:
                    result = "superseded"
                return {
                    "schema": "sc-publications-state-ack/1.0",
                    "record_id": record_id,
                    "sequence": int(existing["sequence"]),
                    "state": result,
                    "approved": bool(existing["approved"]),
                }

            if event["action"] == "approve":
                cur.execute(
                    "SELECT object_type, canonical_url, visibility, publication_status, metadata "
                    "FROM library_records WHERE record_id=%s",
                    (record_id,),
                )
                if not can_approve_indexed(cur.fetchone(), event):
                    raise PublicationStateError("index-not-current-or-unapproved")

            cur.execute(
                """
                INSERT INTO publication_discovery_states
                    (record_id, sequence, event_digest, approved, canonical_url,
                     fingerprint, field_key, map_key, expires_at, updated_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,
                        CASE WHEN %s THEN now() + interval '15 minutes' ELSE NULL END,
                        now())
                ON CONFLICT (record_id) DO UPDATE SET
                    sequence=EXCLUDED.sequence,
                    event_digest=EXCLUDED.event_digest,
                    approved=EXCLUDED.approved,
                    canonical_url=EXCLUDED.canonical_url,
                    fingerprint=EXCLUDED.fingerprint,
                    field_key=EXCLUDED.field_key,
                    map_key=EXCLUDED.map_key,
                    expires_at=EXCLUDED.expires_at,
                    updated_at=now()
                """,
                (
                    record_id, seq, digest, event["action"] == "approve",
                    event["canonical_url"], event["fingerprint"],
                    event["field"], event["map_key"], event["action"] == "approve",
                ),
            )
        conn.commit()

    return {
        "schema": "sc-publications-state-ack/1.0",
        "record_id": record_id,
        "sequence": seq,
        "state": "accepted",
        "approved": event["action"] == "approve",
    }
