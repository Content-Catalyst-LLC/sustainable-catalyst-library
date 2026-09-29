from __future__ import annotations

import base64
from datetime import datetime, timezone
from hashlib import sha256
import json
import re
from typing import Any

from .global_source_federation import registry as source_registry

LINEAGE_CONTRACT = "sc-library-ocr-htr-transcription-lineage/1.0"
SOURCE_ASSET_CONTRACT = "sc-library-source-media-asset/1.0"
RUN_CONTRACT = "sc-library-text-derivation-run/1.0"
SEGMENT_CONTRACT = "sc-library-text-derivation-segment/1.0"
VALIDATION_CONTRACT = "sc-library-text-derivation-validation/1.0"
READINESS_CONTRACT = "sc-library-ocr-htr-transcription-readiness/1.0"
REPRESENTATION_CONTRACT = "sc-library-text-representation/1.1"

DERIVATION_KINDS = {"ocr", "htr", "transcription"}
REVIEW_STATES = {"unreviewed", "in-review", "human-reviewed", "accepted", "rejected"}
LANGUAGE_RE = re.compile(r"^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$")
SCRIPT_RE = re.compile(r"^[A-Z][a-z]{3}$")


def _canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fingerprint(value: Any) -> str:
    return sha256(_canonical_json(value).encode("utf-8")).hexdigest()


def _hash_bytes(value: bytes) -> str:
    return sha256(value).hexdigest()


def _hash_text(value: str) -> str:
    return sha256(value.encode("utf-8")).hexdigest()


def _clean(value: Any) -> str:
    return str(value or "").strip()


def _source_exists(source_id: str) -> bool:
    if not source_id:
        return True
    try:
        source_registry.source(source_id)
        return True
    except KeyError:
        return False


def _source_payload(payload: dict[str, Any]) -> tuple[bytes | None, str | None]:
    raw = _clean(payload.get("source_payload_base64"))
    if not raw:
        return None, None
    try:
        value = base64.b64decode(raw, validate=True)
    except Exception as exc:
        raise ValueError("source_payload_base64 must be valid base64") from exc
    return value, _hash_bytes(value)


def _bounded_confidence(value: Any, field: str, errors: list[str]) -> float | None:
    if value is None or value == "":
        return None
    try:
        number = float(value)
    except (TypeError, ValueError):
        errors.append(f"{field}-must-be-number")
        return None
    if number < 0.0 or number > 1.0:
        errors.append(f"{field}-must-be-between-zero-and-one")
    return number


def validate_derivation_payload(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["payload-must-be-object"], "warnings": []}
    errors: list[str] = []
    warnings: list[str] = []

    kind = _clean(payload.get("derivation_kind")).lower()
    if kind not in DERIVATION_KINDS:
        errors.append("derivation-kind-must-be-ocr-htr-or-transcription")

    source_id = _clean(payload.get("source_id"))
    if source_id and not _source_exists(source_id):
        errors.append("unknown-global-source-id")

    source_asset_id = _clean(payload.get("source_asset_id"))
    input_representation_id = _clean(payload.get("input_representation_id"))
    source_bytes: bytes | None = None
    source_sha: str | None = None
    try:
        source_bytes, source_sha = _source_payload(payload)
    except ValueError as exc:
        errors.append(str(exc))

    supplied_source_sha = _clean(payload.get("source_asset_sha256"))
    source_asset_sha = supplied_source_sha or source_sha
    if source_asset_sha and not re.fullmatch(r"[0-9a-f]{64}", source_asset_sha):
        errors.append("invalid-source-asset-sha256")
    if supplied_source_sha and source_sha and supplied_source_sha != source_sha:
        errors.append("source-asset-sha256-does-not-match-payload")
    if not (source_asset_id or input_representation_id or source_bytes is not None):
        errors.append("source-asset-input-representation-or-source-payload-required")

    media_type = _clean(payload.get("media_type"))
    if source_bytes is not None and not media_type:
        errors.append("media-type-required-for-source-payload")

    output_text = payload.get("output_text")
    if output_text is None:
        errors.append("output-text-required")
        output_text = ""
    else:
        output_text = str(output_text)
    if not output_text:
        warnings.append("empty-derived-text")

    language = _clean(payload.get("language_bcp47") or payload.get("language"))
    if not language:
        errors.append("language-bcp47-required")
    elif not LANGUAGE_RE.match(language):
        errors.append("invalid-language-bcp47")
    script = _clean(payload.get("script_iso15924"))
    if script and not SCRIPT_RE.match(script):
        errors.append("invalid-script-iso15924")

    engine = payload.get("engine") if isinstance(payload.get("engine"), dict) else {}
    engine_provider = _clean(engine.get("provider") or payload.get("engine_provider"))
    engine_name = _clean(engine.get("name") or payload.get("engine_name"))
    if not engine_provider:
        errors.append("engine-provider-required")
    if not engine_name:
        errors.append("engine-name-required")

    review_state = _clean(payload.get("review_state")) or "unreviewed"
    if review_state not in REVIEW_STATES:
        errors.append("invalid-review-state")

    confidence_summary = dict(payload.get("confidence_summary") or {}) if isinstance(payload.get("confidence_summary"), dict) else {}
    for key in ("mean", "median", "minimum", "maximum"):
        if key in confidence_summary:
            confidence_summary[key] = _bounded_confidence(confidence_summary.get(key), f"confidence-summary-{key}", errors)

    segments_out: list[dict[str, Any]] = []
    seen_sequences: set[int] = set()
    raw_segments = payload.get("segments") or []
    if not isinstance(raw_segments, list):
        errors.append("segments-must-be-array")
        raw_segments = []
    for index, item in enumerate(raw_segments, start=1):
        if not isinstance(item, dict):
            errors.append(f"segment-{index}-must-be-object")
            continue
        try:
            sequence = int(item.get("sequence", index))
        except (TypeError, ValueError):
            errors.append(f"segment-{index}-sequence-invalid")
            continue
        if sequence < 1 or sequence in seen_sequences:
            errors.append(f"segment-{index}-sequence-invalid")
        seen_sequences.add(sequence)
        text = str(item.get("text") or "")
        confidence = _bounded_confidence(item.get("confidence"), f"segment-{index}-confidence", errors)
        start_ms = item.get("start_ms")
        end_ms = item.get("end_ms")
        if start_ms is not None or end_ms is not None:
            try:
                start_ms = int(start_ms or 0); end_ms = int(end_ms or 0)
                if start_ms < 0 or end_ms < start_ms:
                    errors.append(f"segment-{index}-time-range-invalid")
            except (TypeError, ValueError):
                errors.append(f"segment-{index}-time-range-invalid")
        bbox = item.get("bounding_box")
        if bbox is not None:
            if not isinstance(bbox, list) or len(bbox) != 4:
                errors.append(f"segment-{index}-bounding-box-invalid")
            else:
                try:
                    bbox = [float(v) for v in bbox]
                except (TypeError, ValueError):
                    errors.append(f"segment-{index}-bounding-box-invalid")
        segments_out.append({
            "sequence": sequence,
            "segment_kind": _clean(item.get("segment_kind")) or ("time-span" if kind == "transcription" else "block"),
            "page_number": int(item["page_number"]) if str(item.get("page_number", "")).isdigit() else None,
            "start_ms": start_ms,
            "end_ms": end_ms,
            "bounding_box": bbox,
            "speaker_label": _clean(item.get("speaker_label")) or None,
            "text": text,
            "text_sha256": _hash_text(text),
            "confidence": confidence,
            "review_state": _clean(item.get("review_state")) or review_state,
        })
        if segments_out[-1]["review_state"] not in REVIEW_STATES:
            errors.append(f"segment-{index}-review-state-invalid")

    if bool(payload.get("automatic_translation", False)):
        errors.append("automatic-translation-prohibited")
    if bool(payload.get("output_is_canonical_original", False)):
        errors.append("derived-output-cannot-be-canonical-original")
    if bool(payload.get("replace_original", False)):
        errors.append("original-replacement-prohibited")

    engine_spec = {
        "provider": engine_provider,
        "name": engine_name,
        "version": _clean(engine.get("version") or payload.get("engine_version")) or None,
        "model": _clean(engine.get("model") or payload.get("model_name")) or None,
        "model_version": _clean(engine.get("model_version") or payload.get("model_version")) or None,
        "parameters": dict(payload.get("parameters") or {}) if isinstance(payload.get("parameters"), dict) else {},
    }
    engine_spec["fingerprint_sha256"] = _fingerprint(engine_spec)

    normalized = {
        "derivation_kind": kind,
        "source_asset_id": source_asset_id or None,
        "source_asset_sha256": source_asset_sha or None,
        "input_representation_id": input_representation_id or None,
        "source_id": source_id or None,
        "source_record_id": _clean(payload.get("source_record_id")) or None,
        "record_id": _clean(payload.get("record_id")) or None,
        "source_uri": _clean(payload.get("source_uri")) or None,
        "media_type": media_type or None,
        "language_bcp47": language or None,
        "script_iso15924": script or None,
        "language_variant": _clean(payload.get("language_variant")) or None,
        "orthography_variant": _clean(payload.get("orthography_variant")) or None,
        "engine": engine_spec,
        "output_text_sha256": _hash_text(output_text),
        "output_character_length": len(output_text),
        "confidence_summary": confidence_summary,
        "review_state": review_state,
        "segments": segments_out,
    }
    normalized["basis_fingerprint_sha256"] = _fingerprint(normalized)
    return {
        "schema": VALIDATION_CONTRACT,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "normalized": normalized,
        "guardrails": {
            "derived_text_replaces_original": False,
            "confidence_is_truth_probability": False,
            "ocr_htr_transcription_is_evidence_truth": False,
            "automatic_translation": False,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
            "automatic_platform_core_promotion": False,
            "human_review_state_is_explicit": True,
        },
    }


def build_derivation_package(payload: dict[str, Any]) -> dict[str, Any]:
    validation = validate_derivation_payload(payload)
    if not validation["valid"]:
        raise ValueError("; ".join(validation["errors"]))
    n = validation["normalized"]
    source_bytes, calculated_sha = _source_payload(payload)
    source_asset_id = n["source_asset_id"]
    source_asset: dict[str, Any] | None = None
    if source_bytes is not None:
        asset_basis = {
            "source_id": n["source_id"], "source_record_id": n["source_record_id"], "record_id": n["record_id"],
            "source_uri": n["source_uri"], "media_type": n["media_type"], "raw_payload_sha256": calculated_sha,
        }
        source_asset_id = "srcasset:" + _fingerprint(asset_basis)[:32]
        source_asset = {
            "schema": SOURCE_ASSET_CONTRACT,
            "source_asset_id": source_asset_id,
            "asset_fingerprint_sha256": _fingerprint(asset_basis),
            **asset_basis,
            "raw_byte_length": len(source_bytes),
        }
    source_locator = {
        "source_asset_id": source_asset_id,
        "source_asset_sha256": n["source_asset_sha256"] or calculated_sha,
        "input_representation_id": n["input_representation_id"],
    }
    run_basis = {
        "derivation_kind": n["derivation_kind"],
        "source": source_locator,
        "engine_fingerprint_sha256": n["engine"]["fingerprint_sha256"],
        "language_bcp47": n["language_bcp47"],
        "script_iso15924": n["script_iso15924"],
        "output_text_sha256": n["output_text_sha256"],
        "segments": [{"sequence": s["sequence"], "text_sha256": s["text_sha256"], "page_number": s["page_number"], "start_ms": s["start_ms"], "end_ms": s["end_ms"]} for s in n["segments"]],
    }
    run_fingerprint = _fingerprint(run_basis)
    run_id = "textrun:" + run_fingerprint[:32]
    output_representation_id = "textrep:" + _fingerprint({"run_id": run_id, "kind": n["derivation_kind"], "text_sha256": n["output_text_sha256"]})[:32]
    segments = []
    for s in n["segments"]:
        segment_id = "textseg:" + _fingerprint({"run_id": run_id, "sequence": s["sequence"], "text_sha256": s["text_sha256"], "page_number": s["page_number"], "start_ms": s["start_ms"], "end_ms": s["end_ms"]})[:32]
        segments.append({"schema": SEGMENT_CONTRACT, "segment_id": segment_id, "run_id": run_id, **s})
    return {
        "schema": RUN_CONTRACT,
        "lineage_contract": LINEAGE_CONTRACT,
        "run_id": run_id,
        "run_fingerprint_sha256": run_fingerprint,
        "derivation_kind": n["derivation_kind"],
        "source_asset": source_asset,
        "source_asset_id": source_asset_id,
        "source_asset_sha256": n["source_asset_sha256"] or calculated_sha,
        "input_representation_id": n["input_representation_id"],
        "output_representation": {
            "schema": REPRESENTATION_CONTRACT,
            "representation_id": output_representation_id,
            "capture_id": None,
            "source_asset_id": source_asset_id,
            "representation_kind": n["derivation_kind"],
            "language_bcp47": n["language_bcp47"],
            "script_iso15924": n["script_iso15924"],
            "language_variant": n["language_variant"],
            "orthography_variant": n["orthography_variant"],
            "text_sha256": n["output_text_sha256"],
            "canonical_original": False,
            "derived": True,
            "normalization_form": None,
            "derivation_run_id": run_id,
        },
        "engine": n["engine"],
        "confidence_summary": n["confidence_summary"],
        "review_state": n["review_state"],
        "segments": segments,
        "guardrails": validation["guardrails"],
        "_source_bytes": source_bytes,
        "_output_text": str(payload.get("output_text") or ""),
        "_source_metadata": dict(payload.get("source_metadata") or {}) if isinstance(payload.get("source_metadata"), dict) else {},
        "_provenance": dict(payload.get("provenance") or {}) if isinstance(payload.get("provenance"), dict) else {},
    }


def ingest_derivation(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    package = build_derivation_package(payload)
    source_bytes = package.pop("_source_bytes")
    output_text = package.pop("_output_text")
    source_metadata = package.pop("_source_metadata")
    provenance = package.pop("_provenance")
    rep = package["output_representation"]
    pool = get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            if package["source_asset"] is not None:
                a = package["source_asset"]
                cur.execute(
                    """
                    INSERT INTO library_source_media_assets(
                      source_asset_id,asset_fingerprint,record_id,source_id,source_record_id,source_uri,media_type,
                      raw_payload,raw_payload_sha256,source_metadata,provenance
                    ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON CONFLICT (source_asset_id) DO NOTHING
                    """,
                    (a["source_asset_id"], a["asset_fingerprint_sha256"], payload.get("record_id") or None, payload.get("source_id") or None,
                     payload.get("source_record_id") or None, payload.get("source_uri") or None, payload.get("media_type") or "application/octet-stream",
                     source_bytes, a["raw_payload_sha256"], Jsonb(source_metadata), Jsonb(provenance)),
                )
            elif package["source_asset_id"]:
                cur.execute("SELECT raw_payload_sha256 FROM library_source_media_assets WHERE source_asset_id=%s", (package["source_asset_id"],))
                found = cur.fetchone()
                if not found:
                    raise ValueError("source_asset_id not found")
                if package["source_asset_sha256"] and found["raw_payload_sha256"] != package["source_asset_sha256"]:
                    raise ValueError("source_asset_sha256 does not match stored asset")
            if package["input_representation_id"]:
                cur.execute("SELECT representation_id FROM library_text_representations WHERE representation_id=%s", (package["input_representation_id"],))
                if not cur.fetchone():
                    raise ValueError("input_representation_id not found")

            cur.execute(
                """
                INSERT INTO library_text_representations(
                  representation_id,capture_id,source_asset_id,representation_kind,language_bcp47,script_iso15924,
                  language_variant,orthography_variant,text_content,text_sha256,canonical_original,derived,normalization_form,provenance
                ) VALUES (%s,NULL,%s,%s,%s,%s,%s,%s,%s,%s,false,true,NULL,%s)
                ON CONFLICT (representation_id) DO NOTHING
                """,
                (rep["representation_id"], package["source_asset_id"], package["derivation_kind"], rep["language_bcp47"], rep["script_iso15924"],
                 rep["language_variant"], rep["orthography_variant"], output_text, rep["text_sha256"], Jsonb({"derivation_run_id": package["run_id"], **provenance})),
            )
            cur.execute(
                """
                INSERT INTO library_text_derivation_runs(
                  run_id,run_fingerprint,derivation_kind,source_asset_id,input_representation_id,output_representation_id,
                  engine_provider,engine_name,engine_version,model_name,model_version,engine_spec_fingerprint,parameters,
                  language_bcp47,script_iso15924,output_text_sha256,confidence_summary,review_state,provenance
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON CONFLICT (run_id) DO NOTHING
                """,
                (package["run_id"], package["run_fingerprint_sha256"], package["derivation_kind"], package["source_asset_id"], package["input_representation_id"],
                 rep["representation_id"], package["engine"]["provider"], package["engine"]["name"], package["engine"]["version"], package["engine"]["model"],
                 package["engine"]["model_version"], package["engine"]["fingerprint_sha256"], Jsonb(package["engine"]["parameters"]), rep["language_bcp47"], rep["script_iso15924"],
                 rep["text_sha256"], Jsonb(package["confidence_summary"]), package["review_state"], Jsonb(provenance)),
            )
            for s in package["segments"]:
                cur.execute(
                    """
                    INSERT INTO library_text_derivation_segments(
                      segment_id,run_id,sequence,segment_kind,page_number,start_ms,end_ms,bounding_box,speaker_label,
                      text_content,text_sha256,confidence,review_state,provenance
                    ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON CONFLICT (segment_id) DO NOTHING
                    """,
                    (s["segment_id"], package["run_id"], s["sequence"], s["segment_kind"], s["page_number"], s["start_ms"], s["end_ms"],
                     Jsonb(s["bounding_box"]) if s["bounding_box"] is not None else None, s["speaker_label"], s["text"], s["text_sha256"], s["confidence"], s["review_state"], Jsonb(provenance)),
                )
        conn.commit()
    package["persisted"] = True
    package["source_payload_in_response"] = False
    package["output_text_in_response"] = False
    return package


def get_derivation(run_id: str, include_text: bool = False) -> dict[str, Any]:
    from .db import get_pool
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_text_derivation_runs WHERE run_id=%s", (run_id,))
        run = cur.fetchone()
        if not run:
            raise KeyError(run_id)
        cur.execute("SELECT * FROM library_text_derivation_segments WHERE run_id=%s ORDER BY sequence ASC", (run_id,))
        segments = [dict(row) for row in cur.fetchall()]
        cur.execute("SELECT * FROM library_text_representations WHERE representation_id=%s", (run["output_representation_id"],))
        rep = cur.fetchone()
    run_out = dict(run)
    rep_out = dict(rep) if rep else None
    if not include_text:
        if rep_out: rep_out.pop("text_content", None)
        for s in segments: s.pop("text_content", None)
    return {"schema": RUN_CONTRACT, "run": run_out, "output_representation": rep_out, "segments": segments, "text_included": include_text}


def readiness() -> dict[str, Any]:
    from .db import get_pool
    counts = {"source_assets": 0, "runs": 0, "segments": 0, "ocr_runs": 0, "htr_runs": 0, "transcription_runs": 0}
    state = "ready"
    try:
        pool = get_pool()
        with pool.connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute("SELECT count(*) AS n FROM library_source_media_assets"); counts["source_assets"] = int(cur.fetchone()["n"])
            cur.execute("SELECT derivation_kind,count(*) AS n FROM library_text_derivation_runs GROUP BY derivation_kind")
            for row in cur.fetchall(): counts[f"{row['derivation_kind']}_runs"] = int(row["n"])
            counts["runs"] = counts["ocr_runs"] + counts["htr_runs"] + counts["transcription_runs"]
            cur.execute("SELECT count(*) AS n FROM library_text_derivation_segments"); counts["segments"] = int(cur.fetchone()["n"])
    except Exception:
        state = "schema-unavailable"
    return {
        "schema": READINESS_CONTRACT,
        "contract": LINEAGE_CONTRACT,
        "version": "5.46.0",
        "state": state,
        "counts": counts,
        "lineage": {
            "source_media_payload_preserved": True,
            "source_media_sha256_content_addressing": True,
            "engine_and_model_identity_preserved": True,
            "engine_parameters_preserved": True,
            "segment_geometry_and_timecodes_supported": True,
            "confidence_preserved_as_measurement": True,
            "review_state_explicit": True,
            "derived_text_representation_linked": True,
            "ocr_htr_transcription_outputs_are_derived": True,
        },
        "guardrails": {
            "derived_text_replaces_original": False,
            "confidence_is_truth_probability": False,
            "ocr_htr_transcription_is_evidence_truth": False,
            "automatic_translation": False,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
            "automatic_platform_core_promotion": False,
        },
        "next_lineage": {"linguistic_corpus_objects_concordance_kwic": "v5.47.0"},
    }
