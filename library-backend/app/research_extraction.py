from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
import hashlib
import re
from typing import Any, Iterable

from psycopg.types.json import Jsonb
from pydantic import BaseModel, Field, field_validator, model_validator

from .db import get_pool
from .platform_core import CoreOutboxRequest, enqueue_operation

EXTRACTION_CONTRACT = "sc-library-research-extraction/1.0"
EXTRACTION_READINESS_CONTRACT = "sc-library-research-extraction-readiness/1.0"
CORE_PROMOTION_CONTRACT = "sc-library-core-research-candidate-handoff/1.0"

CANDIDATE_TYPES = {"entity", "finding", "claim"}
ENTITY_TYPES = {"person", "organization", "place", "concept", "method", "dataset", "identifier", "other"}
REVIEW_STATES = {"pending", "accepted", "rejected", "superseded"}
EXTRACTION_METHODS = {"metadata", "rule-based", "manual", "connector", "parser", "model-assisted"}

# Conservative cue patterns. These produce *candidates*, never governed findings or claims.
FINDING_CUES = re.compile(
    r"\b(?:we\s+(?:found|observed|detected)|results?\s+(?:show|shows|showed|indicate|indicates|indicated|suggest|suggests|suggested)|"
    r"analysis\s+(?:shows|showed|indicates|indicated|suggests|suggested)|the\s+study\s+(?:found|observed|reports?|reported)|"
    r"was\s+(?:observed|detected|associated\s+with)|were\s+(?:observed|detected|associated\s+with))\b",
    re.I,
)
CLAIM_CUES = re.compile(
    r"\b(?:argues?\s+that|concludes?\s+that|suggests?\s+that|indicates?\s+that|demonstrates?\s+that|shows?\s+that|"
    r"proposes?\s+that|hypothesizes?\s+that|contends?\s+that|supports?\s+the\s+(?:claim|view)\s+that|"
    r"is\s+evidence\s+that)\b",
    re.I,
)
PROPER_NOUN = re.compile(r"\b(?:[A-Z][A-Za-z0-9&'’.-]{2,})(?:\s+[A-Z][A-Za-z0-9&'’.-]{2,}){0,4}\b")
SENTENCE = re.compile(r"[^\n.!?]+(?:[.!?]+|$)")


class ExtractionRequest(BaseModel):
    record_id: str = Field(min_length=1, max_length=500)
    candidate_types: list[str] = Field(default_factory=lambda: ["entity", "finding", "claim"], min_length=1, max_length=3)
    replace_pending: bool = True
    include_metadata_entities: bool = True
    include_text_entities: bool = True
    max_candidates_per_type: int = Field(default=100, ge=1, le=500)

    @field_validator("candidate_types")
    @classmethod
    def validate_candidate_types(cls, values: list[str]) -> list[str]:
        cleaned: list[str] = []
        for value in values:
            item = str(value).strip().lower()
            if item not in CANDIDATE_TYPES:
                raise ValueError(f"unsupported candidate type: {item}")
            if item not in cleaned:
                cleaned.append(item)
        return cleaned


class CandidateReviewRequest(BaseModel):
    review_state: str = Field(pattern="^(accepted|rejected|superseded)$")
    reviewer: str = Field(min_length=1, max_length=200)
    review_note: str = Field(default="", max_length=4000)
    metadata: dict[str, Any] = Field(default_factory=dict)


class CandidatePromotionRequest(BaseModel):
    candidate_id: int = Field(gt=0)
    core_project_id: str = Field(min_length=1, max_length=500)
    created_by: str = Field(default="library-operator", min_length=1, max_length=200)
    finding_type: str = Field(default="result", max_length=80)
    claim_type: str = Field(default="interpretive", max_length=80)
    core_metadata: dict[str, Any] = Field(default_factory=dict)

    @model_validator(mode="after")
    def validate_project(self) -> "CandidatePromotionRequest":
        if any(ch in self.core_project_id for ch in "?#"):
            raise ValueError("core_project_id must be a Core entity identifier, not a URL")
        return self


@dataclass(frozen=True)
class Segment:
    locator: str
    text: str
    source_chunk_ordinal: int | None = None


def _normalize_text(value: str) -> str:
    return " ".join(str(value or "").split()).strip()


def _stable_candidate_key(record_id: str, candidate_type: str, text: str, locator: str, start: int, end: int) -> str:
    raw = "\x1f".join([record_id, candidate_type, _normalize_text(text).casefold(), locator, str(start), str(end)])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


def _classify_entity(text: str, source: str) -> str:
    if source == "authors":
        return "person"
    if source in {"topics", "tags"}:
        return "concept"
    if source == "identifiers":
        return "identifier"
    lower = text.casefold()
    if any(token in lower for token in ("university", "institute", "agency", "department", "corporation", "company", "council", "organization")):
        return "organization"
    if any(token in lower for token in ("method", "model", "analysis", "framework", "protocol", "algorithm")):
        return "method"
    return "other"


def _load_record(record_id: str) -> tuple[dict[str, Any], list[Segment]]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT record_id,title,abstract,body_text,authors,topics,tags,identifiers,metadata,content_hash,
                   source_key,object_type,canonical_url,published_at,source_updated_at
              FROM library_records
             WHERE record_id=%s
            """,
            (record_id,),
        )
        row = cur.fetchone()
        if not row:
            raise ValueError("record_id does not exist")
        record = dict(row)
        cur.execute(
            """
            SELECT ordinal,heading,text
              FROM library_record_chunks
             WHERE record_id=%s
             ORDER BY ordinal ASC
            """,
            (record_id,),
        )
        chunks = [dict(item) for item in cur.fetchall()]
    segments: list[Segment] = []
    abstract = str(record.get("abstract") or "").strip()
    if abstract:
        segments.append(Segment(locator="abstract", text=abstract))
    for chunk in chunks:
        text = str(chunk.get("text") or "").strip()
        if not text:
            continue
        ordinal = int(chunk["ordinal"])
        heading = _normalize_text(str(chunk.get("heading") or ""))
        locator = f"chunk:{ordinal}" + (f":{heading[:120]}" if heading else "")
        segments.append(Segment(locator=locator, text=text, source_chunk_ordinal=ordinal))
    if not chunks:
        body = str(record.get("body_text") or "").strip()
        if body:
            segments.append(Segment(locator="body", text=body))
    return record, segments


def _candidate(
    *, record_id: str, candidate_type: str, text: str, locator: str, start: int, end: int,
    confidence: float, extraction_method: str, source_chunk_ordinal: int | None = None,
    entity_type: str | None = None, metadata: dict[str, Any] | None = None,
) -> dict[str, Any]:
    normalized = _normalize_text(text)
    return {
        "candidate_key": _stable_candidate_key(record_id, candidate_type, normalized, locator, start, end),
        "record_id": record_id,
        "candidate_type": candidate_type,
        "candidate_text": normalized,
        "entity_type": entity_type,
        "extraction_method": extraction_method,
        "confidence": max(0.0, min(1.0, float(confidence))),
        "source_locator": locator,
        "source_chunk_ordinal": source_chunk_ordinal,
        "char_start": max(0, int(start)),
        "char_end": max(int(start), int(end)),
        "metadata": metadata or {},
    }


def _metadata_entities(record: dict[str, Any]) -> Iterable[dict[str, Any]]:
    record_id = str(record["record_id"])
    for source in ("authors", "topics", "tags"):
        for index, value in enumerate(record.get(source) or []):
            text = _normalize_text(str(value))
            if not text:
                continue
            yield _candidate(
                record_id=record_id,
                candidate_type="entity",
                text=text,
                locator=f"metadata:{source}:{index}",
                start=0,
                end=len(text),
                confidence=0.98 if source == "authors" else 0.92,
                extraction_method="metadata",
                entity_type=_classify_entity(text, source),
                metadata={"metadata_field": source, "machine_generated": True, "governed": False},
            )
    for key, value in sorted(dict(record.get("identifiers") or {}).items()):
        text = _normalize_text(str(value))
        if text:
            yield _candidate(
                record_id=record_id,
                candidate_type="entity",
                text=f"{key}:{text}",
                locator=f"metadata:identifiers:{key}",
                start=0,
                end=len(text),
                confidence=1.0,
                extraction_method="metadata",
                entity_type="identifier",
                metadata={"identifier_type": key, "identifier_value": text, "machine_generated": True, "governed": False},
            )


def _text_entities(record_id: str, segments: list[Segment]) -> Iterable[dict[str, Any]]:
    stop = {"The", "This", "These", "Those", "Results", "Methods", "Discussion", "Conclusion", "Conclusions", "Introduction", "Figure", "Table"}
    seen: set[str] = set()
    for segment in segments:
        for match in PROPER_NOUN.finditer(segment.text):
            text = _normalize_text(match.group(0))
            if not text or text in stop or len(text) > 180:
                continue
            key = text.casefold()
            if key in seen:
                continue
            seen.add(key)
            yield _candidate(
                record_id=record_id,
                candidate_type="entity",
                text=text,
                locator=segment.locator,
                start=match.start(),
                end=match.end(),
                confidence=0.58,
                extraction_method="rule-based",
                source_chunk_ordinal=segment.source_chunk_ordinal,
                entity_type=_classify_entity(text, "text"),
                metadata={"rule": "capitalized-phrase", "machine_generated": True, "governed": False},
            )


def _sentence_candidates(record_id: str, segments: list[Segment], candidate_type: str) -> Iterable[dict[str, Any]]:
    cue = FINDING_CUES if candidate_type == "finding" else CLAIM_CUES
    for segment in segments:
        for match in SENTENCE.finditer(segment.text):
            sentence = _normalize_text(match.group(0))
            if len(sentence) < 25 or len(sentence) > 1200:
                continue
            if not cue.search(sentence):
                continue
            # An empirical result cue is classified as a finding first instead of
            # duplicating the same sentence as both a finding and a claim.
            if candidate_type == "claim" and FINDING_CUES.search(sentence):
                continue
            confidence = 0.78 if candidate_type == "finding" else 0.74
            yield _candidate(
                record_id=record_id,
                candidate_type=candidate_type,
                text=sentence,
                locator=segment.locator,
                start=match.start(),
                end=match.end(),
                confidence=confidence,
                extraction_method="rule-based",
                source_chunk_ordinal=segment.source_chunk_ordinal,
                metadata={
                    "rule": "finding-cue" if candidate_type == "finding" else "claim-cue",
                    "machine_generated": True,
                    "governed": False,
                    "truth_determined": False,
                },
            )


def _persist_candidates(record: dict[str, Any], candidates: list[dict[str, Any]], replace_pending: bool, requested_types: list[str]) -> dict[str, Any]:
    pool = get_pool()
    now = datetime.now(timezone.utc)
    record_id = str(record["record_id"])
    with pool.connection() as conn, conn.cursor() as cur:
        if replace_pending:
            requested_types = sorted(set(requested_types))
            if requested_types:
                cur.execute(
                    """
                    UPDATE library_research_candidates
                       SET review_state='superseded', updated_at=%s
                     WHERE record_id=%s AND review_state='pending' AND candidate_type = ANY(%s)
                    """,
                    (now, record_id, requested_types),
                )
        inserted = 0
        items: list[dict[str, Any]] = []
        for item in candidates:
            cur.execute(
                """
                INSERT INTO library_research_candidates(
                    candidate_key,record_id,candidate_type,candidate_text,entity_type,extraction_method,
                    confidence,source_locator,source_chunk_ordinal,char_start,char_end,source_content_hash,
                    review_state,metadata,created_at,updated_at
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,'pending',%s,%s,%s)
                ON CONFLICT (candidate_key) DO UPDATE SET
                    confidence=GREATEST(library_research_candidates.confidence,EXCLUDED.confidence),
                    source_content_hash=EXCLUDED.source_content_hash,
                    metadata=library_research_candidates.metadata || EXCLUDED.metadata,
                    review_state=CASE WHEN library_research_candidates.review_state='superseded' THEN 'pending' ELSE library_research_candidates.review_state END,
                    updated_at=EXCLUDED.updated_at
                RETURNING candidate_id,candidate_key,record_id,candidate_type,candidate_text,entity_type,
                          extraction_method,confidence,source_locator,source_chunk_ordinal,char_start,char_end,
                          source_content_hash,review_state,reviewer,review_note,reviewed_at,core_operation,
                          core_outbox_event_id,metadata,created_at,updated_at
                """,
                (
                    item["candidate_key"], item["record_id"], item["candidate_type"], item["candidate_text"],
                    item.get("entity_type"), item["extraction_method"], item["confidence"], item["source_locator"],
                    item.get("source_chunk_ordinal"), item["char_start"], item["char_end"], record.get("content_hash"),
                    Jsonb(item.get("metadata") or {}), now, now,
                ),
            )
            row = dict(cur.fetchone())
            inserted += 1
            items.append(row)
        conn.commit()
    return {"schema": EXTRACTION_CONTRACT, "record_id": record_id, "candidate_count": inserted, "items": items}


def extract_record_candidates(request: ExtractionRequest) -> dict[str, Any]:
    record, segments = _load_record(request.record_id)
    candidates: list[dict[str, Any]] = []
    limits = {kind: 0 for kind in CANDIDATE_TYPES}

    def append(items: Iterable[dict[str, Any]], kind: str) -> None:
        for item in items:
            if limits[kind] >= request.max_candidates_per_type:
                break
            candidates.append(item)
            limits[kind] += 1

    if "entity" in request.candidate_types:
        if request.include_metadata_entities:
            append(_metadata_entities(record), "entity")
        if request.include_text_entities:
            append(_text_entities(request.record_id, segments), "entity")
    if "finding" in request.candidate_types:
        append(_sentence_candidates(request.record_id, segments, "finding"), "finding")
    if "claim" in request.candidate_types:
        append(_sentence_candidates(request.record_id, segments, "claim"), "claim")

    # Deduplicate stable keys while preserving source order.
    deduped: list[dict[str, Any]] = []
    seen: set[str] = set()
    for item in candidates:
        if item["candidate_key"] in seen:
            continue
        seen.add(item["candidate_key"])
        deduped.append(item)
    result = _persist_candidates(record, deduped, request.replace_pending, request.candidate_types)
    result["extraction"] = {
        "candidate_types": request.candidate_types,
        "method": "deterministic-metadata-and-rule-based",
        "machine_generated_candidates_only": True,
        "governed_findings_created": False,
        "governed_claims_created": False,
        "truth_determination_performed": False,
        "requires_human_review_before_core_promotion": True,
    }
    return result


def list_candidates(record_id: str, candidate_type: str | None = None, review_state: str | None = None, limit: int = 200) -> dict[str, Any]:
    clauses = ["record_id=%s"]
    params: list[Any] = [record_id]
    if candidate_type:
        if candidate_type not in CANDIDATE_TYPES:
            raise ValueError("unsupported candidate_type")
        clauses.append("candidate_type=%s")
        params.append(candidate_type)
    if review_state:
        if review_state not in REVIEW_STATES:
            raise ValueError("unsupported review_state")
        clauses.append("review_state=%s")
        params.append(review_state)
    params.append(max(1, min(1000, int(limit))))
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT candidate_id,candidate_key,record_id,candidate_type,candidate_text,entity_type,
                   extraction_method,confidence,source_locator,source_chunk_ordinal,char_start,char_end,
                   source_content_hash,review_state,reviewer,review_note,reviewed_at,core_operation,
                   core_outbox_event_id,metadata,created_at,updated_at
              FROM library_research_candidates
             WHERE {' AND '.join(clauses)}
             ORDER BY candidate_type, confidence DESC, candidate_id ASC
             LIMIT %s
            """,
            tuple(params),
        )
        items = [dict(row) for row in cur.fetchall()]
    return {"schema": EXTRACTION_CONTRACT, "record_id": record_id, "items": items, "count": len(items)}


def review_candidate(candidate_id: int, request: CandidateReviewRequest) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            UPDATE library_research_candidates
               SET review_state=%s,reviewer=%s,review_note=%s,reviewed_at=now(),
                   metadata=metadata || %s,updated_at=now()
             WHERE candidate_id=%s
             RETURNING *
            """,
            (request.review_state, request.reviewer, request.review_note, Jsonb(request.metadata), candidate_id),
        )
        row = cur.fetchone()
        if not row:
            raise ValueError("candidate_id does not exist")
        result = dict(row)
        conn.commit()
    return {"schema": EXTRACTION_CONTRACT, "candidate": result}


def _core_payload(candidate: dict[str, Any], request: CandidatePromotionRequest) -> tuple[str, dict[str, Any]]:
    candidate_type = str(candidate["candidate_type"])
    text = str(candidate["candidate_text"])
    provenance = {
        "source_product": "knowledge-library",
        "library_record_id": candidate["record_id"],
        "library_candidate_id": candidate["candidate_id"],
        "source_locator": candidate["source_locator"],
        "source_chunk_ordinal": candidate.get("source_chunk_ordinal"),
        "char_start": candidate["char_start"],
        "char_end": candidate["char_end"],
        "source_content_hash": candidate.get("source_content_hash"),
        "extraction_method": candidate["extraction_method"],
        "candidate_confidence": candidate["confidence"],
        "human_review_state": candidate["review_state"],
        "reviewed_by": candidate.get("reviewer"),
        "machine_generated_candidate": True,
    }
    common_metadata = {
        "library_candidate": True,
        "candidate_id": candidate["candidate_id"],
        "candidate_key": candidate["candidate_key"],
        **dict(request.core_metadata or {}),
    }
    if candidate_type == "finding":
        return "research-finding.create", {
            "project_id": request.core_project_id,
            "data": {
                "finding_key": f"library:{candidate['candidate_key']}",
                "title": text[:300],
                "statement": text,
                "finding_type": request.finding_type,
                "status": "proposed",
                "source_refs": [str(candidate["record_id"])],
                "metadata": common_metadata,
                "provenance": provenance,
                "created_by": request.created_by,
                "generate_finding_by_core": False,
                "infer_truth_by_core": False,
            },
        }
    if candidate_type == "claim":
        return "research-claim.create", {
            "project_id": request.core_project_id,
            "data": {
                "claim_key": f"library:{candidate['candidate_key']}",
                "claim_text": text,
                "claim_type": request.claim_type,
                "status": "proposed",
                "polarity": "not_applicable",
                "metadata": common_metadata,
                "provenance": provenance,
                "created_by": request.created_by,
                "generate_claim_by_core": False,
                "infer_truth_by_core": False,
            },
        }
    raise ValueError("entity candidates remain Library-owned and are not promoted as findings or claims")


def enqueue_core_candidate(request: CandidatePromotionRequest) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_candidates WHERE candidate_id=%s", (request.candidate_id,))
        row = cur.fetchone()
        if not row:
            raise ValueError("candidate_id does not exist")
        candidate = dict(row)
    if candidate["review_state"] != "accepted":
        raise ValueError("candidate must be human-reviewed and accepted before Core promotion")
    operation, payload = _core_payload(candidate, request)
    outbox = enqueue_operation(CoreOutboxRequest(
        library_record_id=str(candidate["record_id"]),
        operation=operation,
        payload=payload,
        metadata={
            "contract": CORE_PROMOTION_CONTRACT,
            "candidate_id": candidate["candidate_id"],
            "candidate_type": candidate["candidate_type"],
            "explicit_governed_promotion": True,
        },
    ))
    event = dict(outbox["event"])
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            UPDATE library_research_candidates
               SET core_operation=%s,core_outbox_event_id=%s,updated_at=now()
             WHERE candidate_id=%s
            """,
            (operation, event["event_id"], request.candidate_id),
        )
        conn.commit()
    return {
        "schema": CORE_PROMOTION_CONTRACT,
        "candidate_id": request.candidate_id,
        "candidate_type": candidate["candidate_type"],
        "operation": operation,
        "core_project_id": request.core_project_id,
        "outbox_event": event,
        "governed_object_created": False,
        "queued_for_core": True,
    }


def extraction_readiness() -> dict[str, Any]:
    pool = get_pool()
    counts: dict[str, int] = {}
    reviews: dict[str, int] = {}
    storage_ready = False
    storage_error: str | None = None
    try:
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute("SELECT candidate_type,count(*) AS count FROM library_research_candidates GROUP BY candidate_type ORDER BY candidate_type")
            counts = {str(row["candidate_type"]): int(row["count"]) for row in cur.fetchall()}
            cur.execute("SELECT review_state,count(*) AS count FROM library_research_candidates GROUP BY review_state ORDER BY review_state")
            reviews = {str(row["review_state"]): int(row["count"]) for row in cur.fetchall()}
        storage_ready = True
    except Exception as exc:
        storage_error = exc.__class__.__name__
    return {
        "schema": EXTRACTION_READINESS_CONTRACT,
        "entity_candidates": True,
        "finding_candidates": True,
        "claim_candidates": True,
        "source_spans": True,
        "content_hash_binding": True,
        "storage_ready": storage_ready,
        "storage_error": storage_error,
        "human_review_required": True,
        "candidate_counts": counts,
        "review_counts": reviews,
        "platform_core": {
            "finding_promotion": "explicit-after-human-review",
            "claim_promotion": "explicit-after-human-review",
            "entity_candidate_promotion": False,
        },
        "boundaries": {
            "automatic_truth_promotion": False,
            "automatic_claim_promotion": False,
            "automatic_finding_promotion": False,
            "core_generates_findings": False,
            "core_generates_claims": False,
            "library_candidates_are_governed_objects": False,
        },
    }
