from __future__ import annotations

from hashlib import sha256
import json
import re
from typing import Any

MATRIX_CONTRACT = "sc-library-translation-transliteration-alignment-matrix/1.0"
LINK_CONTRACT = "sc-library-text-alignment-link/1.0"
PACKAGE_CONTRACT = "sc-library-translation-alignment-package/1.0"
VALIDATION_CONTRACT = "sc-library-translation-alignment-validation/1.0"
READINESS_CONTRACT = "sc-library-translation-alignment-readiness/1.0"

LANGUAGE_RE = re.compile(r"^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$")
SCRIPT_RE = re.compile(r"^[A-Z][a-z]{3}$")
TRANSFORMATION_KINDS = {"translation", "transliteration"}
RELATION_TYPES = {"aligned", "partial", "omitted", "added", "uncertain"}
REVIEW_STATES = {"unreviewed", "machine-reviewed", "human-reviewed", "accepted", "rejected", "needs-review"}
ALIGNMENT_METHODS = {"declared", "manual", "rule-based", "statistical", "neural", "hybrid", "imported"}


def _clean(v: Any) -> str:
    return str(v or "").strip()


def _canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fingerprint(value: Any) -> str:
    return sha256(_canonical_json(value).encode("utf-8")).hexdigest()


def _text_sha(text: str) -> str:
    return sha256(text.encode("utf-8")).hexdigest()


def guardrails() -> dict[str, Any]:
    return {
        "original_language_remains_canonical": True,
        "translation_is_derived_representation": True,
        "transliteration_is_derived_representation": True,
        "alignment_generates_translation": False,
        "alignment_generates_transliteration": False,
        "alignment_confidence_is_truth_probability": False,
        "alignment_equivalence_implies_semantic_identity": False,
        "alignment_implies_evidence_equivalence": False,
        "automatic_entity_merge": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "explicit_ambiguity_and_unaligned_spans_supported": True,
    }


def _validate_representation(raw: Any, role: str, errors: list[str]) -> dict[str, Any]:
    if not isinstance(raw, dict):
        errors.append(f"{role}-representation-must-be-object")
        raw = {}
    rid = _clean(raw.get("representation_id"))
    text = str(raw.get("text") if raw.get("text") is not None else raw.get("text_content") or "")
    lang = _clean(raw.get("language_bcp47") or raw.get("language"))
    script = _clean(raw.get("script_iso15924"))
    kind = _clean(raw.get("representation_kind"))
    if not rid: errors.append(f"{role}-representation-id-required")
    if not text: errors.append(f"{role}-text-required")
    if not lang or not LANGUAGE_RE.match(lang): errors.append(f"{role}-valid-language-bcp47-required")
    if script and not SCRIPT_RE.match(script): errors.append(f"{role}-invalid-script-iso15924")
    if role == "source" and kind in {"translation", "transliteration"}:
        errors.append("source-must-not-be-translation-or-transliteration")
    if role == "target" and kind not in TRANSFORMATION_KINDS:
        errors.append("target-representation-kind-must-be-translation-or-transliteration")
    supplied_hash = _clean(raw.get("text_sha256"))
    computed_hash = _text_sha(text) if text else ""
    if supplied_hash and supplied_hash != computed_hash:
        errors.append(f"{role}-text-sha256-mismatch")
    return {
        "representation_id": rid,
        "representation_kind": kind or ("original" if role == "source" else "translation"),
        "language_bcp47": lang,
        "script_iso15924": script or None,
        "language_variant": _clean(raw.get("language_variant")) or None,
        "orthography_variant": _clean(raw.get("orthography_variant")) or None,
        "text": text,
        "text_sha256": computed_hash,
        "character_count": len(text),
        "capture_id": _clean(raw.get("capture_id")) or None,
        "transformation_id": _clean(raw.get("transformation_id")) or None,
    }


def _span(raw: Any, text: str, role: str, link_index: int, span_index: int, errors: list[str]) -> dict[str, Any]:
    if not isinstance(raw, dict):
        errors.append(f"link-{link_index}-{role}-span-{span_index}-must-be-object")
        raw = {}
    try:
        start = int(raw.get("start"))
        end = int(raw.get("end"))
    except Exception:
        errors.append(f"link-{link_index}-{role}-span-{span_index}-invalid-offsets")
        start, end = 0, 0
    if start < 0 or end < start or end > len(text):
        errors.append(f"link-{link_index}-{role}-span-{span_index}-out-of-range")
        start = max(0, min(start, len(text)))
        end = max(start, min(end, len(text)))
    content = text[start:end]
    supplied = raw.get("text")
    if supplied is not None and str(supplied) != content:
        errors.append(f"link-{link_index}-{role}-span-{span_index}-text-mismatch")
    return {
        "start": start,
        "end": end,
        "length": end - start,
        "text_sha256": _text_sha(content),
        "segment_id": _clean(raw.get("segment_id")) or None,
    }


def _normalize_link(raw: Any, source: dict[str, Any], target: dict[str, Any], index: int, errors: list[str]) -> dict[str, Any]:
    if not isinstance(raw, dict):
        errors.append(f"link-{index}-must-be-object")
        raw = {}
    relation = _clean(raw.get("relation_type")) or "aligned"
    if relation not in RELATION_TYPES: errors.append(f"link-{index}-invalid-relation-type")
    source_spans_raw = raw.get("source_spans") or []
    target_spans_raw = raw.get("target_spans") or []
    if not isinstance(source_spans_raw, list): source_spans_raw = []; errors.append(f"link-{index}-source-spans-must-be-array")
    if not isinstance(target_spans_raw, list): target_spans_raw = []; errors.append(f"link-{index}-target-spans-must-be-array")
    if relation == "added" and source_spans_raw: errors.append(f"link-{index}-added-must-not-have-source-spans")
    if relation == "omitted" and target_spans_raw: errors.append(f"link-{index}-omitted-must-not-have-target-spans")
    if relation not in {"added"} and not source_spans_raw: errors.append(f"link-{index}-source-span-required")
    if relation not in {"omitted"} and not target_spans_raw: errors.append(f"link-{index}-target-span-required")
    src = [_span(v, source["text"], "source", index, i, errors) for i, v in enumerate(source_spans_raw, 1)]
    tgt = [_span(v, target["text"], "target", index, i, errors) for i, v in enumerate(target_spans_raw, 1)]
    confidence = raw.get("confidence")
    if confidence is not None:
        try: confidence = float(confidence)
        except Exception: errors.append(f"link-{index}-invalid-confidence"); confidence = None
        if confidence is not None and not 0 <= confidence <= 1: errors.append(f"link-{index}-confidence-out-of-range")
    review_state = _clean(raw.get("review_state")) or "unreviewed"
    if review_state not in REVIEW_STATES: errors.append(f"link-{index}-invalid-review-state")
    basis = {
        "source_representation_id": source["representation_id"],
        "target_representation_id": target["representation_id"],
        "source_spans": src, "target_spans": tgt, "relation_type": relation,
        "sequence": int(raw.get("sequence") or index),
    }
    return {
        "schema": LINK_CONTRACT,
        "alignment_id": _clean(raw.get("alignment_id")) or "alignment-link:" + _fingerprint(basis)[:32],
        "sequence": basis["sequence"],
        "relation_type": relation,
        "source_spans": src,
        "target_spans": tgt,
        "cardinality": f"{len(src)}:{len(tgt)}",
        "confidence": confidence,
        "confidence_kind": _clean(raw.get("confidence_kind")) or ("declared" if confidence is not None else None),
        "review_state": review_state,
        "rationale": _clean(raw.get("rationale")) or None,
        "provenance": dict(raw.get("provenance") or {}) if isinstance(raw.get("provenance"), dict) else {},
        "metadata": dict(raw.get("metadata") or {}) if isinstance(raw.get("metadata"), dict) else {},
    }


def validate_alignment_payload(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []; warnings: list[str] = []
    if not isinstance(payload, dict):
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["payload-must-be-object"], "warnings": [], "guardrails": guardrails()}
    source = _validate_representation(payload.get("source"), "source", errors)
    target = _validate_representation(payload.get("target"), "target", errors)
    transformation_kind = _clean(payload.get("transformation_kind")) or target["representation_kind"]
    if transformation_kind not in TRANSFORMATION_KINDS: errors.append("invalid-transformation-kind")
    if target["representation_kind"] and target["representation_kind"] != transformation_kind:
        errors.append("target-kind-transformation-kind-mismatch")
    if source["representation_id"] and source["representation_id"] == target["representation_id"]:
        errors.append("source-and-target-representation-must-differ")
    method = _clean(payload.get("alignment_method")) or "declared"
    if method not in ALIGNMENT_METHODS: errors.append("invalid-alignment-method")
    links_raw = payload.get("links") or []
    if not isinstance(links_raw, list): errors.append("links-must-be-array"); links_raw = []
    if not links_raw: errors.append("at-least-one-alignment-link-required")
    links = [_normalize_link(v, source, target, i, errors) for i, v in enumerate(links_raw, 1)]
    sequences = [v["sequence"] for v in links]
    if len(sequences) != len(set(sequences)): errors.append("duplicate-link-sequence")
    transformation_id = _clean(payload.get("transformation_id") or target.get("transformation_id")) or None
    if transformation_kind in TRANSFORMATION_KINDS and not transformation_id:
        warnings.append("transformation-id-not-supplied")
    if payload.get("generate_translation") is True: errors.append("automatic-translation-generation-prohibited")
    if payload.get("generate_transliteration") is True: errors.append("automatic-transliteration-generation-prohibited")
    matrix_basis = {
        "source_representation_id": source["representation_id"], "source_text_sha256": source["text_sha256"],
        "target_representation_id": target["representation_id"], "target_text_sha256": target["text_sha256"],
        "transformation_kind": transformation_kind, "transformation_id": transformation_id,
        "alignment_method": method,
        "links": [{k:v for k,v in link.items() if k not in {"provenance","metadata"}} for link in links],
    }
    fp = _fingerprint(matrix_basis)
    normalized = {
        "matrix_id": _clean(payload.get("matrix_id")) or "alignment-matrix:" + fp[:32],
        "matrix_fingerprint_sha256": fp,
        "source": source, "target": target,
        "transformation_kind": transformation_kind, "transformation_id": transformation_id,
        "alignment_method": method,
        "alignment_engine": _clean(payload.get("alignment_engine")) or None,
        "alignment_engine_version": _clean(payload.get("alignment_engine_version")) or None,
        "links": sorted(links, key=lambda x:(x["sequence"],x["alignment_id"])),
        "review_state": _clean(payload.get("review_state")) or "unreviewed",
        "provenance": dict(payload.get("provenance") or {}) if isinstance(payload.get("provenance"), dict) else {},
        "metadata": dict(payload.get("metadata") or {}) if isinstance(payload.get("metadata"), dict) else {},
    }
    if normalized["review_state"] not in REVIEW_STATES: errors.append("invalid-matrix-review-state")
    return {"schema": VALIDATION_CONTRACT, "valid": not errors, "errors": errors, "warnings": warnings, "normalized": normalized, "guardrails": guardrails()}


def build_alignment_matrix(payload: dict[str, Any]) -> dict[str, Any]:
    v = validate_alignment_payload(payload)
    if not v["valid"]: raise ValueError("; ".join(v["errors"]))
    n = v["normalized"]
    counts = {k: 0 for k in sorted(RELATION_TYPES)}
    for link in n["links"]: counts[link["relation_type"]] += 1
    return {
        "schema": MATRIX_CONTRACT,
        **n,
        "link_count": len(n["links"]),
        "relation_counts": counts,
        "guardrails": v["guardrails"],
        "persisted": False,
    }


def build_alignment_package(payload: dict[str, Any]) -> dict[str, Any]:
    matrices_raw = payload.get("matrices") if isinstance(payload, dict) else None
    if matrices_raw is None:
        matrices_raw = [payload]
    if not isinstance(matrices_raw, list) or not matrices_raw:
        raise ValueError("at-least-one-matrix-required")
    matrices = [build_alignment_matrix(x) for x in matrices_raw]
    package_fp = _fingerprint([m["matrix_fingerprint_sha256"] for m in matrices])
    return {
        "schema": PACKAGE_CONTRACT,
        "package_id": "translation-alignment-package:" + package_fp[:32],
        "package_fingerprint_sha256": package_fp,
        "matrix_count": len(matrices),
        "matrices": matrices,
        "guardrails": guardrails(),
    }


def ingest_alignment_matrix(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    m = build_alignment_matrix(payload)
    pool = get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            # Alignment persistence is only valid for already-preserved text representations.
            for role in ("source", "target"):
                cur.execute("SELECT representation_kind,language_bcp47,script_iso15924,text_sha256 FROM library_text_representations WHERE representation_id=%s", (m[role]["representation_id"],))
                row = cur.fetchone()
                if not row: raise ValueError(f"{role}-representation-not-found")
                if row["text_sha256"] != m[role]["text_sha256"]: raise ValueError(f"{role}-persisted-text-sha256-mismatch")
            if m.get("transformation_id"):
                cur.execute("SELECT input_representation_id,output_representation_id,operation FROM library_text_transformations WHERE transformation_id=%s", (m["transformation_id"],))
                tr = cur.fetchone()
                if not tr: raise ValueError("transformation-id-not-found")
                if tr["input_representation_id"] != m["source"]["representation_id"] or tr["output_representation_id"] != m["target"]["representation_id"]:
                    raise ValueError("transformation-lineage-does-not-match-representations")
            cur.execute("""INSERT INTO library_text_alignment_matrices(matrix_id,source_representation_id,target_representation_id,transformation_kind,transformation_id,source_language_bcp47,source_script_iso15924,target_language_bcp47,target_script_iso15924,source_text_sha256,target_text_sha256,alignment_method,alignment_engine,alignment_engine_version,review_state,matrix_fingerprint,provenance,metadata) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT(matrix_id) DO NOTHING""",
                (m["matrix_id"],m["source"]["representation_id"],m["target"]["representation_id"],m["transformation_kind"],m["transformation_id"],m["source"]["language_bcp47"],m["source"]["script_iso15924"],m["target"]["language_bcp47"],m["target"]["script_iso15924"],m["source"]["text_sha256"],m["target"]["text_sha256"],m["alignment_method"],m["alignment_engine"],m["alignment_engine_version"],m["review_state"],m["matrix_fingerprint_sha256"],Jsonb(m["provenance"]),Jsonb(m["metadata"])))
            for link in m["links"]:
                cur.execute("""INSERT INTO library_text_alignment_links(alignment_id,matrix_id,sequence,relation_type,source_spans,target_spans,cardinality,confidence,confidence_kind,review_state,rationale,provenance,metadata) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT(alignment_id) DO NOTHING""",
                    (link["alignment_id"],m["matrix_id"],link["sequence"],link["relation_type"],Jsonb(link["source_spans"]),Jsonb(link["target_spans"]),link["cardinality"],link["confidence"],link["confidence_kind"],link["review_state"],link["rationale"],Jsonb(link["provenance"]),Jsonb(link["metadata"])))
        conn.commit()
    m["persisted"] = True
    return m


def get_alignment_matrix(matrix_id: str) -> dict[str, Any]:
    from .db import get_pool
    pool=get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_text_alignment_matrices WHERE matrix_id=%s", (matrix_id,)); row=cur.fetchone()
        if not row: raise KeyError(matrix_id)
        cur.execute("SELECT * FROM library_text_alignment_links WHERE matrix_id=%s ORDER BY sequence,alignment_id", (matrix_id,)); links=[dict(x) for x in cur.fetchall()]
    r=dict(row)
    return {"schema":MATRIX_CONTRACT,"matrix_id":r["matrix_id"],"matrix_fingerprint_sha256":r["matrix_fingerprint"],"source_representation_id":r["source_representation_id"],"target_representation_id":r["target_representation_id"],"transformation_kind":r["transformation_kind"],"transformation_id":r["transformation_id"],"source_language_bcp47":r["source_language_bcp47"],"source_script_iso15924":r["source_script_iso15924"],"target_language_bcp47":r["target_language_bcp47"],"target_script_iso15924":r["target_script_iso15924"],"source_text_sha256":r["source_text_sha256"],"target_text_sha256":r["target_text_sha256"],"alignment_method":r["alignment_method"],"alignment_engine":r["alignment_engine"],"alignment_engine_version":r["alignment_engine_version"],"review_state":r["review_state"],"provenance":r["provenance"] or {},"metadata":r["metadata"] or {},"links":links,"link_count":len(links),"guardrails":guardrails(),"persisted":True}


def readiness() -> dict[str, Any]:
    counts={"matrices":0,"links":0,"translation_matrices":0,"transliteration_matrices":0,"reviewed_matrices":0}; state="ready"
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute("SELECT count(*) AS n,count(*) FILTER (WHERE transformation_kind='translation') AS translations,count(*) FILTER (WHERE transformation_kind='transliteration') AS transliterations,count(*) FILTER (WHERE review_state IN ('human-reviewed','accepted')) AS reviewed FROM library_text_alignment_matrices")
            row=cur.fetchone(); counts.update({"matrices":int(row["n"]),"translation_matrices":int(row["translations"]),"transliteration_matrices":int(row["transliterations"]),"reviewed_matrices":int(row["reviewed"])})
            cur.execute("SELECT count(*) AS n FROM library_text_alignment_links"); counts["links"]=int(cur.fetchone()["n"])
    except Exception:
        state="schema-unavailable"
    return {
        "schema":READINESS_CONTRACT,"version":"5.54.0","backend_version":"2.65.0","state":state,"counts":counts,
        "capabilities":{"translation_alignment_matrices":True,"transliteration_alignment_matrices":True,"one_to_many_alignment":True,"many_to_one_alignment":True,"many_to_many_alignment":True,"unaligned_omitted_added_spans":True,"character_offset_integrity":True,"text_hash_binding":True,"transformation_lineage":True,"explicit_review_state":True,"signed_persistence":True,"durable_worker_capability":"language.align"},
        "guardrails":guardrails(),
        "lineage":{"original_language":"v5.45.0","ocr_htr_transcription":"v5.46.0","linguistic_corpus":"v5.47.0","cross_language_resolution":"v5.48.0","distributed_compute":"v5.53.0"},
        "next_lineage":{"cross_civilizational_evidence_scientific_data_linking":"v5.55.0"},
    }
