from __future__ import annotations

from hashlib import sha256
import json
from typing import Any
from uuid import uuid4

LIBRARY_VERSION = "5.69.0"
BACKEND_VERSION = "2.80.0"
CONTRACT = "sc-library-wordpress-state-migration/1.0"
READINESS_CONTRACT = "sc-library-wordpress-state-migration-readiness/1.0"
MANIFEST_CONTRACT = "sc-library-wordpress-state-migration-manifest/1.0"
CERTIFICATION_CONTRACT = "sc-library-wordpress-state-retirement-certification/1.0"

MIGRATABLE_DOMAINS: dict[str, dict[str, Any]] = {
    "workspaces": {"source_kinds": ["table"], "authority": "library-service"},
    "collaboration": {"source_kinds": ["table"], "authority": "library-service"},
    "knowledge-graph": {"source_kinds": ["table"], "authority": "library-service"},
    "relationships": {"source_kinds": ["table"], "authority": "library-service"},
    "orchestration": {"source_kinds": ["table"], "authority": "library-service"},
    "document-production": {"source_kinds": ["table"], "authority": "library-service"},
    "multimedia-research": {"source_kinds": ["table"], "authority": "library-service"},
    "preservation-lineage": {"source_kinds": ["table"], "authority": "library-service"},
    "research-planning": {"source_kinds": ["post_type"], "authority": "library-service"},
    "research-rooms": {"source_kinds": ["post_type"], "authority": "library-service"},
    "curated-research-spaces": {"source_kinds": ["post_type"], "authority": "library-service"},
    "reading-notebooks": {"source_kinds": ["post_type"], "authority": "library-service"},
    "evidence-matrices": {"source_kinds": ["post_type"], "authority": "library-service"},
    "federation-shares": {"source_kinds": ["post_type"], "authority": "library-service"},
    "metadata-review": {"source_kinds": ["post_type"], "authority": "library-service"},
    "team-libraries": {"source_kinds": ["post_type"], "authority": "library-service"},
}

PROHIBITED_KEY_FRAGMENTS = (
    "password", "passwd", "secret", "api_key", "apikey", "token", "credential",
    "session_cookie", "authorization", "private_key", "client_secret",
)


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _hash(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "wordpress_source_state_remains_unchanged_during_import": True,
        "migration_requires_explicit_signed_admin_request": True,
        "credentials_and_sessions_are_never_migrated": True,
        "rebuildable_caches_and_indexes_are_not_authoritative_migration_state": True,
        "migration_item_hashes_are_verified": True,
        "retirement_requires_certified_complete_migration": True,
        "retirement_means_authority_retirement_not_destructive_deletion": True,
        "automatic_wordpress_data_deletion": False,
        "rollback_copy_must_be_retained": True,
        "wordpress_remains_publishing_and_routing_adapter": True,
        "library_service_owns_migrated_research_state": True,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "migration-boundary-ready",
        "source": "wordpress-legacy-research-state",
        "destination": "library-service-postgresql",
        "manifest_contract": MANIFEST_CONTRACT,
        "retirement_certification_contract": CERTIFICATION_CONTRACT,
        "migratable_domains": MIGRATABLE_DOMAINS,
        "excluded_state": [
            "wordpress-users-and-passwords", "library-session-tokens", "api-keys-and-signing-secrets",
            "wordpress-editorial-pages", "seo-and-marketing-content", "rebuildable-search-indexes",
            "cache-state", "transient-state",
        ],
        "workflow": ["inventory", "export", "validate", "import", "certify", "authority-retire"],
        "physical_deletion_supported": False,
        "guardrails": guardrails(),
    }


def _find_secret_paths(value: Any, path: str = "$", out: list[str] | None = None) -> list[str]:
    out = out if out is not None else []
    if isinstance(value, dict):
        for key, child in value.items():
            key_text = str(key).lower()
            child_path = f"{path}.{key}"
            if any(fragment in key_text for fragment in PROHIBITED_KEY_FRAGMENTS):
                out.append(child_path)
            _find_secret_paths(child, child_path, out)
    elif isinstance(value, list):
        for i, child in enumerate(value):
            _find_secret_paths(child, f"{path}[{i}]", out)
    return out


def validate_manifest(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    warnings: list[str] = []
    if not isinstance(payload, dict):
        payload = {}
        errors.append("manifest-must-be-object")
    if payload.get("schema") != MANIFEST_CONTRACT:
        errors.append("invalid-manifest-contract")
    items = payload.get("items")
    if not isinstance(items, list):
        items = []
        errors.append("items-must-be-array")
    if len(items) > 5000:
        errors.append("manifest-item-limit-exceeded")
    seen: set[str] = set()
    normalized: list[dict[str, Any]] = []
    for idx, raw in enumerate(items):
        prefix = f"item[{idx}]"
        if not isinstance(raw, dict):
            errors.append(prefix + ":must-be-object")
            continue
        domain = str(raw.get("domain") or "")
        source_kind = str(raw.get("source_kind") or "")
        source_key = str(raw.get("source_key") or "")
        source_id = str(raw.get("source_id") or "")
        item_payload = raw.get("payload")
        if domain not in MIGRATABLE_DOMAINS:
            errors.append(prefix + ":unsupported-domain")
        elif source_kind not in MIGRATABLE_DOMAINS[domain]["source_kinds"]:
            errors.append(prefix + ":unsupported-source-kind")
        if not source_key or not source_id:
            errors.append(prefix + ":missing-source-identity")
        secret_paths = _find_secret_paths(item_payload)
        if secret_paths:
            errors.append(prefix + ":credential-or-session-material-prohibited")
        computed = _hash(item_payload)
        supplied = str(raw.get("content_sha256") or "")
        if supplied and supplied != computed:
            errors.append(prefix + ":content-hash-mismatch")
        item_id = "wp-state:" + sha256(f"{domain}|{source_kind}|{source_key}|{source_id}|{computed}".encode()).hexdigest()[:40]
        if item_id in seen:
            errors.append(prefix + ":duplicate-item")
        seen.add(item_id)
        normalized.append({
            "item_id": item_id,
            "domain": domain,
            "source_kind": source_kind,
            "source_key": source_key,
            "source_id": source_id,
            "content_sha256": computed,
            "payload": item_payload,
            "provenance": dict(raw.get("provenance") or {}),
        })
    declared = payload.get("item_count")
    if declared is not None and int(declared) != len(items):
        errors.append("declared-item-count-mismatch")
    source_site = str(payload.get("source_site") or "")
    if not source_site:
        warnings.append("source-site-not-declared")
    manifest_basis = {
        "schema": MANIFEST_CONTRACT,
        "source_site": source_site,
        "items": normalized,
    }
    manifest_sha = _hash(manifest_basis)
    return {
        "schema": "sc-library-wordpress-state-migration-validation/1.0",
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "manifest_sha256": manifest_sha,
        "item_count": len(normalized),
        "normalized_items": normalized if not errors else [],
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    counts = {"runs": 0, "items": 0, "events": 0, "certifications": 0}
    db_state = "unavailable"
    try:
        from .db import get_pool
        tables = [
            ("library_wordpress_state_migration_runs", "runs"),
            ("library_wordpress_state_migration_items", "items"),
            ("library_wordpress_state_migration_events", "events"),
            ("library_wordpress_retirement_certifications", "certifications"),
        ]
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for table, key in tables:
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[key] = int(cur.fetchone()["n"])
            db_state = "ready"
    except Exception:
        pass
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if db_state == "ready" else "degraded",
        "database": db_state,
        "counts": counts,
        "physical_deletion_supported": False,
        "guardrails": guardrails(),
    }



def get_certification(certification_id: str) -> dict[str, Any]:
    from .db import get_pool
    with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
        cur.execute("SELECT certification_id,run_id,certification_sha256,status,item_count,rollback_copy_retained,destructive_delete_allowed,certified_at FROM library_wordpress_retirement_certifications WHERE certification_id=%s", (certification_id,))
        row = cur.fetchone()
    if not row:
        raise KeyError(certification_id)
    return {
        "schema": CERTIFICATION_CONTRACT,
        "certification_id": row["certification_id"],
        "run_id": row["run_id"],
        "certification_sha256": row["certification_sha256"],
        "status": row["status"],
        "item_count": int(row["item_count"]),
        "rollback_copy_retained": bool(row["rollback_copy_retained"]),
        "physical_deletion_authorized": bool(row["destructive_delete_allowed"]),
        "certified_at": row["certified_at"].isoformat() if row["certified_at"] else None,
        "guardrails": guardrails(),
    }

def import_manifest(payload: dict[str, Any], provenance: dict[str, Any] | None = None) -> dict[str, Any]:
    validation = validate_manifest(payload)
    if not validation["valid"]:
        raise ValueError("migration-manifest-invalid:" + ",".join(validation["errors"]))
    from psycopg.types.json import Jsonb
    from .db import get_pool
    run_id = "wp-migration:" + uuid4().hex
    manifest_sha = validation["manifest_sha256"]
    items = validation["normalized_items"]
    provenance = dict(provenance or {})
    source_site = str(payload.get("source_site") or "")
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO library_wordpress_state_migration_runs(
                run_id,manifest_sha256,source_site,expected_items,imported_items,status,provenance,guardrails
            ) VALUES (%s,%s,%s,%s,0,'importing',%s,%s)""",
            (run_id, manifest_sha, source_site, len(items), Jsonb(provenance), Jsonb(guardrails())),
        )
        imported = 0
        for item in items:
            cur.execute(
                """INSERT INTO library_wordpress_state_migration_items(
                    item_id,run_id,domain,source_kind,source_key,source_id,content_sha256,payload,provenance
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON CONFLICT(item_id) DO NOTHING""",
                (item["item_id"], run_id, item["domain"], item["source_kind"], item["source_key"], item["source_id"], item["content_sha256"], Jsonb(item["payload"]), Jsonb(item["provenance"])),
            )
            imported += int(cur.rowcount or 0)
        status = "imported" if imported == len(items) else "partial"
        cur.execute("UPDATE library_wordpress_state_migration_runs SET imported_items=%s,status=%s WHERE run_id=%s", (imported, status, run_id))
        cur.execute("INSERT INTO library_wordpress_state_migration_events(run_id,event_type,details) VALUES (%s,'manifest-imported',%s)", (run_id, Jsonb({"manifest_sha256": manifest_sha, "expected": len(items), "imported": imported, "status": status})))
        conn.commit()
    return {"schema": CONTRACT, "run_id": run_id, "manifest_sha256": manifest_sha, "expected_items": len(items), "imported_items": imported, "status": status, "certified": False, "guardrails": guardrails()}


def certify_migration(run_id: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    payload = dict(payload or {})
    if payload.get("delete_wordpress_source_data") is True:
        raise ValueError("destructive-wordpress-deletion-prohibited")
    if payload.get("rollback_copy_retained") is not True:
        raise ValueError("rollback-copy-retention-required")
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT run_id,manifest_sha256,source_site,expected_items,imported_items,status FROM library_wordpress_state_migration_runs WHERE run_id=%s", (run_id,))
        row = cur.fetchone()
        if not row:
            raise KeyError(run_id)
        cur.execute("SELECT count(*) AS n FROM library_wordpress_state_migration_items WHERE run_id=%s", (run_id,))
        actual = int(cur.fetchone()["n"])
        expected = int(row["expected_items"])
        imported = int(row["imported_items"])
        if actual != expected or imported != expected or row["status"] not in {"imported", "certified"}:
            raise ValueError("migration-is-not-complete")
        basis = {"run_id": run_id, "manifest_sha256": row["manifest_sha256"], "item_count": actual, "source_site": row["source_site"], "rollback_copy_retained": True, "destructive_delete": False}
        cert_sha = _hash(basis)
        certification_id = "wp-retirement-cert:" + cert_sha[:32]
        cur.execute(
            """INSERT INTO library_wordpress_retirement_certifications(
                certification_id,run_id,certification_sha256,status,item_count,rollback_copy_retained,destructive_delete_allowed,details
            ) VALUES (%s,%s,%s,'certified',%s,true,false,%s)
            ON CONFLICT(run_id) DO UPDATE SET certification_id=EXCLUDED.certification_id,certification_sha256=EXCLUDED.certification_sha256,status='certified',item_count=EXCLUDED.item_count,rollback_copy_retained=true,destructive_delete_allowed=false,details=EXCLUDED.details""",
            (certification_id, run_id, cert_sha, actual, Jsonb(basis)),
        )
        cur.execute("UPDATE library_wordpress_state_migration_runs SET status='certified',certified_at=now(),retirement_eligible=true WHERE run_id=%s", (run_id,))
        cur.execute("INSERT INTO library_wordpress_state_migration_events(run_id,event_type,details) VALUES (%s,'retirement-certified',%s)", (run_id, Jsonb({"certification_id": certification_id, "certification_sha256": cert_sha, "item_count": actual})))
        conn.commit()
    return {
        "schema": CERTIFICATION_CONTRACT,
        "certification_id": certification_id,
        "run_id": run_id,
        "certification_sha256": cert_sha,
        "status": "certified",
        "item_count": actual,
        "wordpress_authority_retirement_eligible": True,
        "physical_deletion_authorized": False,
        "rollback_copy_retained": True,
        "guardrails": guardrails(),
    }
