from __future__ import annotations

from datetime import datetime, timezone
import hashlib
import json
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator, model_validator



PrivateAccessLevel = Literal["organization", "restricted", "project"]
PrivateHandoffTarget = Literal["research-librarian", "workspace", "lab"]



def _get_pool():
    from .db import get_pool
    return get_pool()


def _jsonb(value: Any):
    from psycopg.types.json import Jsonb
    return Jsonb(value)


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), sort_keys=True, default=str)


def _stable_hash(value: Any) -> str:
    return hashlib.sha256(_canonical_json(value).encode("utf-8")).hexdigest()


def normalize_org_key(value: str) -> str:
    raw = str(value or "").strip().lower()
    out = "".join(ch if ch.isalnum() or ch in {"-", "_", "."} else "-" for ch in raw)
    while "--" in out:
        out = out.replace("--", "-")
    return out.strip("-.")[:120]


def private_record_key(org_key: str, record_id: str) -> str:
    return hashlib.sha256(f"org:{normalize_org_key(org_key)}\nrecord:{str(record_id).strip()}".encode("utf-8")).hexdigest()


def access_scope_allows(access_level: str, record_scopes: list[str], actor_scopes: list[str]) -> bool:
    if access_level == "organization":
        return True
    allowed = {str(v).strip() for v in record_scopes if str(v).strip()}
    actor = {str(v).strip() for v in actor_scopes if str(v).strip()}
    return bool(allowed & actor)


def lab_handoff_eligible(object_type: str, metadata: dict[str, Any] | None) -> bool:
    kind = str(object_type or "").strip().lower()
    data_like = kind in {"dataset", "data", "data-table", "spreadsheet", "csv", "json"}
    return data_like and bool((metadata or {}).get("analysis_allowed", False))


class PrivateActor(BaseModel):
    model_config = ConfigDict(extra="forbid")

    actor_id: str = Field(min_length=1, max_length=191)
    scopes: list[str] = Field(default_factory=list, max_length=200)

    @field_validator("scopes")
    @classmethod
    def clean_scopes(cls, values: list[str]) -> list[str]:
        out: list[str] = []
        seen: set[str] = set()
        for value in values:
            item = str(value or "").strip()[:191]
            if item and item not in seen:
                out.append(item)
                seen.add(item)
        return out


class PrivateOrganizationPacket(BaseModel):
    model_config = ConfigDict(extra="forbid")

    org_key: str = Field(min_length=1, max_length=120)
    name: str = Field(min_length=1, max_length=255)
    metadata: dict[str, Any] = Field(default_factory=dict)

    @field_validator("org_key")
    @classmethod
    def clean_org_key(cls, value: str) -> str:
        cleaned = normalize_org_key(value)
        if not cleaned:
            raise ValueError("organization key is empty after normalization")
        return cleaned


class PrivateSourcePacket(BaseModel):
    model_config = ConfigDict(extra="forbid")

    source_key: str = Field(min_length=1, max_length=191)
    name: str = Field(min_length=1, max_length=255)
    source_type: str = Field(default="internal", min_length=1, max_length=80)
    canonical_url: str | None = Field(default=None, max_length=2000)
    owner: str | None = Field(default=None, max_length=255)
    metadata: dict[str, Any] = Field(default_factory=dict)


class PrivateRecordPacket(BaseModel):
    model_config = ConfigDict(extra="forbid")

    record_id: str = Field(min_length=1, max_length=255)
    object_type: str = Field(min_length=1, max_length=80)
    title: str = Field(min_length=1, max_length=1000)
    canonical_url: str | None = Field(default=None, max_length=2000)
    abstract: str = Field(default="", max_length=40000)
    body_text: str = Field(default="", max_length=2_000_000)
    language: str = Field(default="en", min_length=2, max_length=16)
    original_format: str = Field(default="text", min_length=1, max_length=40)
    source_updated_at: datetime | None = None
    authors: list[str] = Field(default_factory=list, max_length=100)
    topics: list[str] = Field(default_factory=list, max_length=200)
    tags: list[str] = Field(default_factory=list, max_length=300)
    identifiers: dict[str, str] = Field(default_factory=dict)
    metadata: dict[str, Any] = Field(default_factory=dict)
    access_level: PrivateAccessLevel = "organization"
    access_scopes: list[str] = Field(default_factory=list, max_length=200)
    project_key: str | None = Field(default=None, max_length=191)
    department: str | None = Field(default=None, max_length=191)
    retention_label: str | None = Field(default=None, max_length=191)

    @field_validator("authors", "topics", "tags", "access_scopes")
    @classmethod
    def clean_strings(cls, values: list[str]) -> list[str]:
        out: list[str] = []
        seen: set[str] = set()
        for value in values:
            item = " ".join(str(value or "").split()).strip()[:500]
            if item and item.casefold() not in seen:
                out.append(item)
                seen.add(item.casefold())
        return out

    @model_validator(mode="after")
    def validate_access_boundary(self):
        if self.access_level in {"restricted", "project"} and not self.access_scopes:
            raise ValueError("restricted/project records require at least one access scope")
        if self.access_level == "project" and not str(self.project_key or "").strip():
            raise ValueError("project-scoped records require project_key")
        return self


class PrivateKnowledgeIngestRequest(BaseModel):
    model_config = ConfigDict(extra="forbid", populate_by_name=True)

    schema_id: str = Field(default="sc-private-organizational-knowledge-ingest/1.0", alias="schema", serialization_alias="schema")
    actor: PrivateActor
    organization: PrivateOrganizationPacket
    source: PrivateSourcePacket
    records: list[PrivateRecordPacket] = Field(min_length=1, max_length=200)

    @field_validator("schema_id")
    @classmethod
    def supported_schema(cls, value: str) -> str:
        if value != "sc-private-organizational-knowledge-ingest/1.0":
            raise ValueError("unsupported private knowledge ingest schema")
        return value


class PrivateKnowledgeSearchRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    organization_key: str = Field(min_length=1, max_length=120)
    actor: PrivateActor
    q: str = Field(default="", max_length=500)
    object_type: str | None = Field(default=None, max_length=80)
    source_key: str | None = Field(default=None, max_length=191)
    project_key: str | None = Field(default=None, max_length=191)
    department: str | None = Field(default=None, max_length=191)
    limit: int = Field(default=20, ge=1, le=100)
    offset: int = Field(default=0, ge=0, le=100000)

    @field_validator("organization_key")
    @classmethod
    def clean_org_key(cls, value: str) -> str:
        cleaned = normalize_org_key(value)
        if not cleaned:
            raise ValueError("organization key is empty after normalization")
        return cleaned


class PrivateRecordRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    organization_key: str = Field(min_length=1, max_length=120)
    actor: PrivateActor
    record_id: str = Field(min_length=1, max_length=255)

    @field_validator("organization_key")
    @classmethod
    def clean_org_key(cls, value: str) -> str:
        return normalize_org_key(value)


class PrivateHandoffRequest(PrivateRecordRequest):
    target: PrivateHandoffTarget


class PrivateOrganizationalKnowledge:
    SUPPORTED_FORMATS = ("pdf", "docx", "txt", "text", "md", "markdown", "csv", "json", "html", "other")
    HANDOFF_TARGETS = ("research-librarian", "workspace", "lab")

    def manifest(self) -> dict[str, Any]:
        return {
            "schema": "sc-private-organizational-knowledge-manifest/1.0",
            "framework": {
                "key": "private-organizational-knowledge",
                "name": "Private Organizational Knowledge Foundation",
                "version": "1.0",
                "capabilities": [
                    "organization-scoped-private-records",
                    "private-source-registry",
                    "version-lineage",
                    "access-scope-enforcement",
                    "private-full-text-search",
                    "private-provenance",
                    "private-audit-events",
                    "research-librarian-handoff",
                    "workspace-handoff",
                    "lab-handoff-policy",
                ],
                "governance": {
                    "public_search_includes_private_records": False,
                    "cross_organization_search": False,
                    "cross_organization_identity_merge": False,
                    "automatic_publication": False,
                    "browser_backend_secret_exposure": False,
                    "server_signed_private_requests_required": True,
                    "organization_scope_required": True,
                    "access_scope_enforced": True,
                    "handoff_must_preserve_private_boundary": True,
                    "raw_search_query_written_to_audit_log": False,
                },
            },
            "storage": {
                "separate_private_tables": True,
                "public_record_table": "library_records",
                "private_record_table": "library_private_records",
                "public_query_path_reads_private_tables": False,
                "version_history": True,
                "audit_log": True,
            },
            "ingestion": {
                "mode": "normalized-text-packets",
                "supported_original_formats": list(self.SUPPORTED_FORMATS),
                "binary_parser_claimed": False,
                "note": "PDF/DOCX and other binary files must be converted/extracted before backend ingest; existing Library conversion/OCR adapters can supply normalized text.",
            },
            "access_levels": {
                "organization": "Available to authenticated organization members admitted by the server-side WordPress proxy.",
                "restricted": "Requires at least one matching actor scope.",
                "project": "Requires a matching project/user/role scope and a project key.",
            },
            "handoffs": {
                "targets": list(self.HANDOFF_TARGETS),
                "lab": "Metadata/text handoff only in this foundation; dataset analysis eligibility is explicit and does not infer permission.",
            },
            "retrieved_at": _now(),
        }

    @staticmethod
    def _record_content_hash(record: PrivateRecordPacket) -> str:
        material = record.model_dump(mode="json")
        return _stable_hash(material)

    @staticmethod
    def _request_fingerprint(*parts: Any) -> str:
        return _stable_hash(parts)

    def _audit(
        self,
        *,
        org_key: str,
        actor_id: str,
        action: str,
        private_key: str | None = None,
        request_fingerprint: str | None = None,
        metadata: dict[str, Any] | None = None,
    ) -> None:
        pool = _get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO library_private_access_events(
                    org_key,actor_id,action,private_record_key,request_fingerprint,metadata
                ) VALUES (%s,%s,%s,%s,%s,%s)
                """,
                (org_key, actor_id, action, private_key, request_fingerprint, _jsonb(metadata or {})),
            )
            conn.commit()

    def ingest(self, packet: PrivateKnowledgeIngestRequest, request_hash: str) -> dict[str, Any]:
        org = packet.organization
        source = packet.source
        pool = _get_pool()
        changed = 0
        unchanged = 0
        record_ids: list[str] = []
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO library_private_organizations(org_key,name,metadata,updated_at)
                VALUES (%s,%s,%s,now())
                ON CONFLICT (org_key) DO UPDATE SET name=EXCLUDED.name,metadata=EXCLUDED.metadata,updated_at=now()
                """,
                (org.org_key, org.name, _jsonb(org.metadata)),
            )
            cur.execute(
                """
                INSERT INTO library_private_sources(org_key,source_key,name,source_type,canonical_url,owner,metadata,updated_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s,now())
                ON CONFLICT (org_key,source_key) DO UPDATE SET
                    name=EXCLUDED.name,source_type=EXCLUDED.source_type,canonical_url=EXCLUDED.canonical_url,
                    owner=EXCLUDED.owner,metadata=EXCLUDED.metadata,updated_at=now()
                """,
                (org.org_key, source.source_key, source.name, source.source_type, source.canonical_url, source.owner, _jsonb(source.metadata)),
            )
            for record in packet.records:
                pkey = private_record_key(org.org_key, record.record_id)
                digest = self._record_content_hash(record)
                record_ids.append(record.record_id)
                cur.execute(
                    "SELECT content_hash,revision FROM library_private_records WHERE private_record_key=%s FOR UPDATE",
                    (pkey,),
                )
                existing = cur.fetchone()
                if existing and existing["content_hash"] == digest:
                    unchanged += 1
                    continue
                revision = int(existing["revision"]) + 1 if existing else 1
                scopes = list(record.access_scopes)
                if record.access_level == "project" and record.project_key:
                    project_scope = f"project:{record.project_key}"
                    if project_scope not in scopes:
                        scopes.append(project_scope)
                provenance = {
                    "organization_key": org.org_key,
                    "source_key": source.source_key,
                    "source_name": source.name,
                    "source_type": source.source_type,
                    "source_owner": source.owner,
                    "canonical_url": source.canonical_url,
                    "source_updated_at": record.source_updated_at.isoformat() if record.source_updated_at else None,
                }
                cur.execute(
                    """
                    INSERT INTO library_private_records(
                        private_record_key,org_key,record_id,source_key,object_type,title,canonical_url,abstract,body_text,language,
                        original_format,access_level,access_scopes,project_key,department,retention_label,source_updated_at,
                        authors,topics,tags,identifiers,metadata,provenance,content_hash,revision,indexed_at
                    ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,now())
                    ON CONFLICT (private_record_key) DO UPDATE SET
                        source_key=EXCLUDED.source_key,object_type=EXCLUDED.object_type,title=EXCLUDED.title,
                        canonical_url=EXCLUDED.canonical_url,abstract=EXCLUDED.abstract,body_text=EXCLUDED.body_text,
                        language=EXCLUDED.language,original_format=EXCLUDED.original_format,access_level=EXCLUDED.access_level,
                        access_scopes=EXCLUDED.access_scopes,project_key=EXCLUDED.project_key,department=EXCLUDED.department,
                        retention_label=EXCLUDED.retention_label,source_updated_at=EXCLUDED.source_updated_at,
                        authors=EXCLUDED.authors,topics=EXCLUDED.topics,tags=EXCLUDED.tags,identifiers=EXCLUDED.identifiers,
                        metadata=EXCLUDED.metadata,provenance=EXCLUDED.provenance,content_hash=EXCLUDED.content_hash,
                        revision=EXCLUDED.revision,indexed_at=now()
                    """,
                    (
                        pkey, org.org_key, record.record_id, source.source_key, record.object_type, record.title,
                        record.canonical_url, record.abstract, record.body_text, record.language, record.original_format,
                        record.access_level, _jsonb(scopes), record.project_key, record.department, record.retention_label,
                        record.source_updated_at, _jsonb(record.authors), _jsonb(record.topics), _jsonb(record.tags),
                        _jsonb(record.identifiers), _jsonb(record.metadata), _jsonb(provenance), digest, revision,
                    ),
                )
                snapshot = {
                    **record.model_dump(mode="json"),
                    "private_record_key": pkey,
                    "organization_key": org.org_key,
                    "source_key": source.source_key,
                    "access_scopes": scopes,
                    "content_hash": digest,
                    "revision": revision,
                    "provenance": provenance,
                }
                cur.execute(
                    """
                    INSERT INTO library_private_record_versions(private_record_key,revision,content_hash,snapshot)
                    VALUES (%s,%s,%s,%s)
                    ON CONFLICT (private_record_key,revision) DO NOTHING
                    """,
                    (pkey, revision, digest, _jsonb(snapshot)),
                )
                changed += 1
            cur.execute(
                """
                INSERT INTO library_private_ingest_events(org_key,source_key,actor_id,received_count,changed_count,request_hash)
                VALUES (%s,%s,%s,%s,%s,%s)
                """,
                (org.org_key, source.source_key, packet.actor.actor_id, len(packet.records), changed, request_hash),
            )
            conn.commit()
        self._audit(
            org_key=org.org_key,
            actor_id=packet.actor.actor_id,
            action="ingest",
            request_fingerprint=request_hash,
            metadata={"source_key": source.source_key, "received": len(packet.records), "changed": changed},
        )
        return {
            "ok": True,
            "schema": "sc-private-organizational-knowledge-ingest-result/1.0",
            "organization_key": org.org_key,
            "received": len(packet.records),
            "changed": changed,
            "unchanged": unchanged,
            "record_ids": record_ids,
        }

    @staticmethod
    def _access_sql(alias: str = "r") -> str:
        return (
            f"({alias}.access_level='organization' OR EXISTS ("
            f"SELECT 1 FROM jsonb_array_elements_text({alias}.access_scopes) AS s(value) WHERE s.value = ANY(%s)"
            f"))"
        )

    def search(self, request: PrivateKnowledgeSearchRequest) -> dict[str, Any]:
        org_key = request.organization_key
        actor_scopes = list(request.actor.scopes)
        clauses = ["r.org_key=%s", self._access_sql("r")]
        params: list[Any] = [org_key, actor_scopes]
        q = " ".join(request.q.split()).strip()
        if q:
            clauses.append("r.search_vector @@ websearch_to_tsquery('english', %s)")
            params.append(q)
        if request.object_type:
            clauses.append("r.object_type=%s")
            params.append(request.object_type)
        if request.source_key:
            clauses.append("r.source_key=%s")
            params.append(request.source_key)
        if request.project_key:
            clauses.append("r.project_key=%s")
            params.append(request.project_key)
        if request.department:
            clauses.append("r.department=%s")
            params.append(request.department)
        where = " AND ".join(clauses)
        rank = "ts_rank_cd(r.search_vector, websearch_to_tsquery('english', %s))" if q else "0.0"
        if q:
            params_for_select = [q, *params]
        else:
            params_for_select = params
        sql = f"""
            SELECT r.record_id,r.source_key,r.object_type,r.title,r.abstract,r.original_format,r.access_level,
                   r.project_key,r.department,r.authors,r.topics,r.tags,r.identifiers,r.metadata,r.provenance,
                   r.revision,r.source_updated_at,r.indexed_at,{rank} AS rank
            FROM library_private_records r
            WHERE {where}
            ORDER BY rank DESC,r.indexed_at DESC,r.title ASC
            LIMIT %s OFFSET %s
        """
        params_for_select.extend([request.limit, request.offset])
        count_sql = f"SELECT count(*) AS n FROM library_private_records r WHERE {where}"
        pool = _get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute(sql, params_for_select)
            rows = list(cur.fetchall())
            cur.execute(count_sql, params)
            total = int(cur.fetchone()["n"])
        records = []
        for row in rows:
            records.append({
                "record_id": row["record_id"],
                "source_key": row["source_key"],
                "object_type": row["object_type"],
                "title": row["title"],
                "abstract": row["abstract"],
                "original_format": row["original_format"],
                "access_level": row["access_level"],
                "project_key": row["project_key"],
                "department": row["department"],
                "authors": row["authors"],
                "topics": row["topics"],
                "tags": row["tags"],
                "identifiers": row["identifiers"],
                "metadata": row["metadata"],
                "provenance": row["provenance"],
                "revision": int(row["revision"]),
                "source_updated_at": row["source_updated_at"].isoformat() if row["source_updated_at"] else None,
                "indexed_at": row["indexed_at"].isoformat() if row["indexed_at"] else None,
                "rank": float(row["rank"] or 0.0),
            })
        fingerprint = self._request_fingerprint(org_key, q, request.object_type, request.source_key, request.project_key, request.department)
        self._audit(
            org_key=org_key,
            actor_id=request.actor.actor_id,
            action="search",
            request_fingerprint=fingerprint,
            metadata={"result_count": len(records), "total": total, "raw_query_logged": False},
        )
        return {
            "ok": True,
            "schema": "sc-private-organizational-knowledge-search/1.0",
            "organization_key": org_key,
            "record_count": len(records),
            "total": total,
            "limit": request.limit,
            "offset": request.offset,
            "records": records,
            "query_fingerprint": fingerprint,
            "raw_query_logged_to_audit": False,
        }

    def _get_authorized_row(self, request: PrivateRecordRequest) -> dict[str, Any] | None:
        pkey = private_record_key(request.organization_key, request.record_id)
        pool = _get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute(
                f"""
                SELECT r.* FROM library_private_records r
                WHERE r.private_record_key=%s AND r.org_key=%s AND {self._access_sql('r')}
                """,
                (pkey, request.organization_key, list(request.actor.scopes)),
            )
            row = cur.fetchone()
        return row

    def get_record(self, request: PrivateRecordRequest) -> dict[str, Any]:
        row = self._get_authorized_row(request)
        if not row:
            raise KeyError("private record not found or not authorized")
        pkey = row["private_record_key"]
        self._audit(org_key=request.organization_key, actor_id=request.actor.actor_id, action="read", private_key=pkey)
        return {
            "ok": True,
            "schema": "sc-private-organizational-knowledge-record/1.0",
            "organization_key": request.organization_key,
            "record": {
                "record_id": row["record_id"],
                "source_key": row["source_key"],
                "object_type": row["object_type"],
                "title": row["title"],
                "canonical_url": row["canonical_url"],
                "abstract": row["abstract"],
                "body_text": row["body_text"],
                "language": row["language"],
                "original_format": row["original_format"],
                "access_level": row["access_level"],
                "access_scopes": row["access_scopes"],
                "project_key": row["project_key"],
                "department": row["department"],
                "retention_label": row["retention_label"],
                "source_updated_at": row["source_updated_at"].isoformat() if row["source_updated_at"] else None,
                "authors": row["authors"],
                "topics": row["topics"],
                "tags": row["tags"],
                "identifiers": row["identifiers"],
                "metadata": row["metadata"],
                "provenance": row["provenance"],
                "content_hash": row["content_hash"],
                "revision": int(row["revision"]),
                "indexed_at": row["indexed_at"].isoformat() if row["indexed_at"] else None,
            },
        }

    def versions(self, request: PrivateRecordRequest) -> dict[str, Any]:
        row = self._get_authorized_row(request)
        if not row:
            raise KeyError("private record not found or not authorized")
        pkey = row["private_record_key"]
        pool = _get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute(
                """
                SELECT revision,content_hash,observed_at FROM library_private_record_versions
                WHERE private_record_key=%s ORDER BY revision DESC LIMIT 100
                """,
                (pkey,),
            )
            rows = list(cur.fetchall())
        self._audit(org_key=request.organization_key, actor_id=request.actor.actor_id, action="versions", private_key=pkey)
        return {
            "ok": True,
            "schema": "sc-private-organizational-knowledge-versions/1.0",
            "organization_key": request.organization_key,
            "record_id": request.record_id,
            "versions": [
                {"revision": int(v["revision"]), "content_hash": v["content_hash"], "observed_at": v["observed_at"].isoformat()}
                for v in rows
            ],
        }

    def handoff(self, request: PrivateHandoffRequest) -> dict[str, Any]:
        row = self._get_authorized_row(request)
        if not row:
            raise KeyError("private record not found or not authorized")
        pkey = row["private_record_key"]
        metadata = row["metadata"] if isinstance(row["metadata"], dict) else {}
        object_type = str(row["object_type"] or "").lower()
        lab_eligible = lab_handoff_eligible(object_type, metadata)
        target_policy = {
            "research-librarian": {
                "eligible": True,
                "payload_mode": "bounded-private-context",
                "content_excerpt_chars": 24000,
            },
            "workspace": {
                "eligible": True,
                "payload_mode": "private-record-reference",
                "content_excerpt_chars": 8000,
            },
            "lab": {
                "eligible": lab_eligible,
                "payload_mode": "private-analysis-reference" if lab_eligible else "metadata-only",
                "content_excerpt_chars": 12000 if lab_eligible else 0,
                "analysis_permission_inferred": False,
                "requires_explicit_analysis_allowed": True,
            },
        }[request.target]
        excerpt_chars = int(target_policy.get("content_excerpt_chars", 0) or 0)
        excerpt = str(row["body_text"] or "")[:excerpt_chars] if excerpt_chars else ""
        packet = {
            "organization_key": request.organization_key,
            "record_id": row["record_id"],
            "source_key": row["source_key"],
            "title": row["title"],
            "object_type": row["object_type"],
            "revision": int(row["revision"]),
            "content_hash": row["content_hash"],
            "project_key": row["project_key"],
            "department": row["department"],
            "access_level": row["access_level"],
            "access_scopes": row["access_scopes"],
            "provenance": row["provenance"],
            "content_excerpt": excerpt,
            "private_boundary": {
                "preserve_organization_scope": True,
                "preserve_access_scopes": True,
                "automatic_publication": False,
                "external_persistence_without_policy_check": False,
            },
        }
        self._audit(
            org_key=request.organization_key,
            actor_id=request.actor.actor_id,
            action=f"handoff:{request.target}",
            private_key=pkey,
            metadata={"eligible": bool(target_policy.get("eligible")), "payload_mode": target_policy.get("payload_mode")},
        )
        return {
            "ok": True,
            "schema": "sc-private-organizational-knowledge-handoff/1.0",
            "target": request.target,
            "eligible": bool(target_policy.get("eligible")),
            "policy": target_policy,
            "packet": packet,
        }
