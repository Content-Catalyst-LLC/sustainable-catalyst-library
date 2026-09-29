from __future__ import annotations

import base64
import binascii
from dataclasses import dataclass
from datetime import datetime, timezone
from hashlib import sha256
import json
import mimetypes
import os
from pathlib import Path
import re
import tempfile
from typing import Any

from .settings import settings

ARTIFACT_CONTRACT = "sc-library-research-artifact/1.0"
DERIVATION_CONTRACT = "sc-library-artifact-derivation/1.0"
READINESS_CONTRACT = "sc-library-artifact-storage-readiness/1.0"
VALIDATION_CONTRACT = "sc-library-artifact-validation/1.0"
MANIFEST_CONTRACT = "sc-library-artifact-manifest/1.0"
LIFECYCLE_STATES = {"active", "retained", "quarantined", "tombstoned"}
ARTIFACT_TYPES = {
    "source-document", "source-media", "parsed-document", "ocr-artifact", "htr-artifact",
    "transcript", "corpus", "dataset", "embedding-export", "model-output", "visualization",
    "investigation-package", "reproducibility-package", "runtime-output", "other",
}
SHA256_RE = re.compile(r"^[0-9a-f]{64}$")


def _now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def _clean(value: Any) -> str:
    return str(value or "").strip()


def _json_hash(value: Any) -> str:
    return sha256(json.dumps(value, sort_keys=True, separators=(",", ":"), default=str).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "postgresql_authoritative_artifact_metadata": True,
        "artifact_bytes_stored_outside_postgresql": True,
        "content_addressed_identity": True,
        "artifact_content_is_immutable": True,
        "same_bytes_same_artifact_identity": True,
        "artifact_presence_implies_evidence_truth": False,
        "artifact_integrity_implies_source_validity": False,
        "automatic_platform_core_promotion": False,
        "tombstone_physically_deletes_bytes": False,
        "s3_provider_is_identity_authority": False,
    }


def content_sha256(data: bytes) -> str:
    return sha256(data).hexdigest()


def artifact_id_for_sha(digest: str) -> str:
    digest = digest.lower().strip()
    if not SHA256_RE.fullmatch(digest):
        raise ValueError("invalid-sha256")
    return f"artifact:sha256:{digest}"


def storage_key_for_sha(digest: str) -> str:
    digest = digest.lower().strip()
    if not SHA256_RE.fullmatch(digest):
        raise ValueError("invalid-sha256")
    return f"sha256/{digest[:2]}/{digest[2:4]}/{digest}"


def _decode_content(payload: dict[str, Any]) -> bytes:
    raw = payload.get("content_base64")
    if not isinstance(raw, str) or not raw.strip():
        raise ValueError("content-base64-required")
    try:
        data = base64.b64decode(raw.encode("ascii"), validate=True)
    except (UnicodeEncodeError, binascii.Error) as exc:
        raise ValueError("invalid-content-base64") from exc
    if len(data) > settings.artifact_max_inline_bytes:
        raise ValueError("artifact-exceeds-inline-limit")
    return data


def _normalized_metadata(payload: dict[str, Any], data: bytes) -> dict[str, Any]:
    artifact_type = _clean(payload.get("artifact_type") or "other").lower()
    errors: list[str] = []
    if artifact_type not in ARTIFACT_TYPES:
        errors.append("unsupported-artifact-type")
    media_type = _clean(payload.get("media_type")).lower()
    filename = _clean(payload.get("original_filename"))
    if not media_type and filename:
        media_type = mimetypes.guess_type(filename)[0] or "application/octet-stream"
    media_type = media_type or "application/octet-stream"
    digest = content_sha256(data)
    provided_sha = _clean(payload.get("content_sha256")).lower()
    if provided_sha and provided_sha != digest:
        errors.append("content-sha256-mismatch")
    parents = []
    for item in payload.get("parent_artifact_ids") or []:
        item = _clean(item)
        if item and item not in parents:
            parents.append(item)
    provenance = payload.get("provenance") if isinstance(payload.get("provenance"), dict) else {}
    normalized = {
        "artifact_id": artifact_id_for_sha(digest),
        "content_sha256": digest,
        "byte_length": len(data),
        "artifact_type": artifact_type,
        "media_type": media_type,
        "original_filename": filename or None,
        "source_uri": _clean(payload.get("source_uri")) or None,
        "created_by_job": _clean(payload.get("created_by_job")) or None,
        "parent_artifact_ids": parents,
        "derivation_operation": _clean(payload.get("derivation_operation")) or None,
        "provenance": provenance,
        "immutable": True,
    }
    normalized["manifest_fingerprint_sha256"] = _json_hash({k: v for k, v in normalized.items() if k != "manifest_fingerprint_sha256"})
    return {"errors": errors, "normalized": normalized}


def validate_artifact_payload(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    try:
        data = _decode_content(payload)
        result = _normalized_metadata(payload, data)
        errors.extend(result["errors"])
        normalized = result["normalized"]
    except ValueError as exc:
        errors.append(str(exc))
        normalized = {}
    return {
        "schema": VALIDATION_CONTRACT,
        "version": "5.51.0",
        "backend_version": "2.62.0",
        "valid": not errors,
        "errors": errors,
        "normalized": normalized,
        "guardrails": guardrails(),
    }


def build_artifact_manifest(payload: dict[str, Any]) -> dict[str, Any]:
    validation = validate_artifact_payload(payload)
    if not validation["valid"]:
        raise ValueError("; ".join(validation["errors"]))
    n = dict(validation["normalized"])
    return {
        "schema": MANIFEST_CONTRACT,
        "version": "5.51.0",
        "backend_version": "2.62.0",
        "artifact": n,
        "storage": {
            "backend": settings.artifact_store_backend,
            "key": storage_key_for_sha(n["content_sha256"]),
            "content_addressed": True,
        },
        "guardrails": guardrails(),
    }


class ArtifactStore:
    backend: str
    def put(self, key: str, data: bytes, media_type: str) -> None: raise NotImplementedError
    def get(self, key: str) -> bytes: raise NotImplementedError
    def exists(self, key: str) -> bool: raise NotImplementedError
    def describe(self) -> dict[str, Any]: raise NotImplementedError


@dataclass
class FilesystemArtifactStore(ArtifactStore):
    root: Path
    backend: str = "filesystem"

    def _path(self, key: str) -> Path:
        candidate = (self.root / key).resolve()
        root = self.root.resolve()
        if root != candidate and root not in candidate.parents:
            raise ValueError("invalid-storage-key")
        return candidate

    def put(self, key: str, data: bytes, media_type: str) -> None:
        path = self._path(key)
        path.parent.mkdir(parents=True, exist_ok=True)
        if path.exists():
            existing = path.read_bytes()
            if content_sha256(existing) != content_sha256(data):
                raise RuntimeError("content-address-collision")
            return
        fd, tmp = tempfile.mkstemp(prefix=".artifact-", dir=str(path.parent))
        try:
            with os.fdopen(fd, "wb") as fh:
                fh.write(data); fh.flush(); os.fsync(fh.fileno())
            os.chmod(tmp, 0o640)
            os.replace(tmp, path)
        finally:
            if os.path.exists(tmp): os.unlink(tmp)

    def get(self, key: str) -> bytes:
        return self._path(key).read_bytes()

    def exists(self, key: str) -> bool:
        return self._path(key).is_file()

    def describe(self) -> dict[str, Any]:
        self.root.mkdir(parents=True, exist_ok=True)
        writable = os.access(self.root, os.W_OK)
        return {"backend": self.backend, "root": str(self.root), "available": writable, "s3_compatible": False}


@dataclass
class S3ArtifactStore(ArtifactStore):
    bucket: str
    prefix: str
    backend: str = "s3"

    def _client(self):
        try:
            import boto3
        except ImportError as exc:
            raise RuntimeError("boto3-not-installed") from exc
        return boto3.client(
            "s3",
            endpoint_url=settings.artifact_s3_endpoint_url or None,
            region_name=settings.artifact_s3_region or None,
            aws_access_key_id=settings.artifact_s3_access_key or None,
            aws_secret_access_key=settings.artifact_s3_secret_key or None,
        )

    def _key(self, key: str) -> str:
        prefix = self.prefix.strip("/")
        return f"{prefix}/{key}" if prefix else key

    def put(self, key: str, data: bytes, media_type: str) -> None:
        client = self._client(); target = self._key(key)
        try:
            from botocore.exceptions import ClientError
            head = client.head_object(Bucket=self.bucket, Key=target)
            if int(head.get("ContentLength", -1)) == len(data):
                existing = client.get_object(Bucket=self.bucket, Key=target)["Body"].read()
                if content_sha256(existing) == content_sha256(data): return
            raise RuntimeError("content-address-collision")
        except ClientError as exc:
            code = str(exc.response.get("Error", {}).get("Code", ""))
            if code not in {"404", "NoSuchKey", "NotFound"}: raise
        client.put_object(Bucket=self.bucket, Key=target, Body=data, ContentType=media_type, Metadata={"sha256": content_sha256(data)})

    def get(self, key: str) -> bytes:
        return self._client().get_object(Bucket=self.bucket, Key=self._key(key))["Body"].read()

    def exists(self, key: str) -> bool:
        try:
            self._client().head_object(Bucket=self.bucket, Key=self._key(key)); return True
        except Exception:
            return False

    def describe(self) -> dict[str, Any]:
        configured = bool(self.bucket and settings.artifact_s3_endpoint_url and settings.artifact_s3_access_key and settings.artifact_s3_secret_key)
        return {"backend": self.backend, "bucket": self.bucket, "prefix": self.prefix, "endpoint_configured": bool(settings.artifact_s3_endpoint_url), "available": configured, "s3_compatible": True}


def default_store() -> ArtifactStore:
    if settings.artifact_store_backend == "s3":
        return S3ArtifactStore(settings.artifact_s3_bucket, settings.artifact_s3_prefix)
    return FilesystemArtifactStore(Path(settings.artifact_filesystem_root))


def _public_row(row: Any) -> dict[str, Any] | None:
    if not row: return None
    d = dict(row)
    d["schema"] = ARTIFACT_CONTRACT
    for k, v in list(d.items()):
        if hasattr(v, "isoformat"): d[k] = v.isoformat().replace("+00:00", "Z")
    d["guardrails"] = guardrails()
    return d


def persist_artifact(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    validation = validate_artifact_payload(payload)
    if not validation["valid"]: raise ValueError("; ".join(validation["errors"]))
    data = _decode_content(payload); n = validation["normalized"]; store = default_store(); key = storage_key_for_sha(n["content_sha256"])
    store.put(key, data, n["media_type"])
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""INSERT INTO library_research_artifacts(artifact_id,content_sha256,artifact_type,media_type,byte_length,storage_backend,storage_key,original_filename,source_uri,provenance,created_by_job,lifecycle_state,immutable,manifest_fingerprint,created_at,updated_at)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,'active',true,%s,now(),now())
        ON CONFLICT(artifact_id) DO UPDATE SET updated_at=now() RETURNING *""",(
            n["artifact_id"],n["content_sha256"],n["artifact_type"],n["media_type"],n["byte_length"],store.backend,key,n["original_filename"],n["source_uri"],Jsonb(n["provenance"]),n["created_by_job"],n["manifest_fingerprint_sha256"])); row=cur.fetchone()
        cur.execute("INSERT INTO library_artifact_events(artifact_id,event_type,details) VALUES (%s,'observed',%s)",(n["artifact_id"],Jsonb({"source_uri":n["source_uri"],"created_by_job":n["created_by_job"],"manifest_fingerprint_sha256":n["manifest_fingerprint_sha256"]})))
        for parent in n["parent_artifact_ids"]:
            op = n["derivation_operation"] or "derived"
            fp = _json_hash({"parent":parent,"child":n["artifact_id"],"operation":op,"created_by_job":n["created_by_job"]})
            relation_id = f"artifact-derivation:{fp[:32]}"
            cur.execute("""INSERT INTO library_artifact_derivations(relation_id,parent_artifact_id,child_artifact_id,operation,created_by_job,provenance,relation_fingerprint)
            VALUES (%s,%s,%s,%s,%s,%s,%s) ON CONFLICT(relation_id) DO NOTHING""",(relation_id,parent,n["artifact_id"],op,n["created_by_job"],Jsonb(n["provenance"]),fp))
        conn.commit()
    return _public_row(row) or {}


def get_artifact(artifact_id: str) -> dict[str, Any]:
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_artifacts WHERE artifact_id=%s", (_clean(artifact_id),)); row=cur.fetchone()
    if not row: raise KeyError("artifact-not-found")
    return _public_row(row) or {}


def read_artifact_bytes(artifact_id: str) -> tuple[dict[str, Any], bytes]:
    artifact = get_artifact(artifact_id)
    data = default_store().get(str(artifact["storage_key"]))
    digest = content_sha256(data)
    if digest != artifact["content_sha256"] or len(data) != int(artifact["byte_length"]):
        raise RuntimeError("artifact-integrity-failure")
    return artifact, data


def verify_artifact(artifact_id: str) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    artifact = get_artifact(artifact_id); store = default_store(); key = str(artifact["storage_key"])
    exists = store.exists(key); digest = None; size = None; valid = False
    if exists:
        data = store.get(key); digest = content_sha256(data); size = len(data); valid = digest == artifact["content_sha256"] and size == int(artifact["byte_length"])
    with get_pool().connection() as conn, conn.cursor() as cur:
        if valid: cur.execute("UPDATE library_research_artifacts SET verified_at=now(),updated_at=now() WHERE artifact_id=%s",(artifact_id,))
        cur.execute("INSERT INTO library_artifact_events(artifact_id,event_type,details) VALUES (%s,%s,%s)",(artifact_id,"integrity-verified" if valid else "integrity-failed",Jsonb({"exists":exists,"observed_sha256":digest,"observed_byte_length":size}))); conn.commit()
    return {"schema":"sc-library-artifact-integrity/1.0","artifact_id":artifact_id,"valid":valid,"exists":exists,"expected_sha256":artifact["content_sha256"],"observed_sha256":digest,"expected_byte_length":artifact["byte_length"],"observed_byte_length":size,"verified_at":_now(),"guardrails":guardrails()}


def set_lifecycle(artifact_id: str, state: str, reason: str = "") -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    state = _clean(state).lower()
    if state not in LIFECYCLE_STATES: raise ValueError("invalid-lifecycle-state")
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("UPDATE library_research_artifacts SET lifecycle_state=%s,updated_at=now() WHERE artifact_id=%s RETURNING *",(state,artifact_id)); row=cur.fetchone()
        if not row: raise KeyError("artifact-not-found")
        cur.execute("INSERT INTO library_artifact_events(artifact_id,event_type,details) VALUES (%s,'lifecycle-changed',%s)",(artifact_id,Jsonb({"state":state,"reason":_clean(reason)}))); conn.commit()
    return _public_row(row) or {}


def artifact_storage_readiness() -> dict[str, Any]:
    store = default_store(); storage = store.describe(); counts: dict[str, int] = {}; db_state = "unavailable"
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute("SELECT lifecycle_state,count(*) AS n FROM library_research_artifacts GROUP BY lifecycle_state"); counts={str(x["lifecycle_state"]):int(x["n"]) for x in cur.fetchall()}
        db_state = "ready"
    except Exception as exc:
        return {"schema":READINESS_CONTRACT,"version":"5.51.0","backend_version":"2.62.0","state":"unavailable","postgresql":{"state":"unavailable","authoritative":True},"storage":storage,"error_class":exc.__class__.__name__,"guardrails":guardrails()}
    state = "ready" if storage.get("available") else "degraded"
    return {"schema":READINESS_CONTRACT,"version":"5.51.0","backend_version":"2.62.0","state":state,"postgresql":{"state":db_state,"authoritative":True},"storage":storage,"counts":counts,"capabilities":{"content_addressed_storage":True,"sha256_integrity":True,"immutable_artifacts":True,"artifact_derivation_lineage":True,"artifact_lifecycle":True,"filesystem_backend":True,"s3_compatible_backend":True,"signed_persistence":True,"signed_integrity_verification":True,"wordpress_raw_content_access":False},"guardrails":guardrails()}
