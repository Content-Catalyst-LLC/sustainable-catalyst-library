from __future__ import annotations

import base64
from dataclasses import dataclass
from datetime import datetime, timezone
from hashlib import sha256
import json
import re
import unicodedata
from typing import Any

from .global_source_federation import registry as source_registry

CORPUS_CONTRACT = "sc-library-original-language-corpus/1.0"
CAPTURE_CONTRACT = "sc-library-original-language-capture/1.0"
REPRESENTATION_CONTRACT = "sc-library-text-representation/1.0"
TRANSFORMATION_CONTRACT = "sc-library-text-transformation/1.0"
VALIDATION_CONTRACT = "sc-library-original-language-validation/1.0"
READINESS_CONTRACT = "sc-library-original-language-corpus-readiness/1.0"

LANGUAGE_RE = re.compile(r"^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$")
SCRIPT_RE = re.compile(r"^[A-Z][a-z]{3}$")
ALLOWED_NORMALIZATION_FORMS = {"NFC", "NFD", "NFKC", "NFKD"}


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


def _iso_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _source_exists(source_id: str) -> bool:
    if not source_id:
        return True
    try:
        source_registry.source(source_id)
        return True
    except KeyError:
        return False


def _decode_payload(payload: dict[str, Any]) -> tuple[bytes, str, str]:
    charset = _clean(payload.get("charset")) or "utf-8"
    raw_payload_b64 = _clean(payload.get("raw_payload_base64"))
    raw_text = payload.get("raw_text")
    if raw_payload_b64:
        try:
            raw_bytes = base64.b64decode(raw_payload_b64, validate=True)
        except Exception as exc:
            raise ValueError("raw_payload_base64 must be valid base64") from exc
        try:
            decoded = raw_bytes.decode(charset, errors="strict")
        except (LookupError, UnicodeDecodeError) as exc:
            raise ValueError(f"raw payload cannot be decoded with charset {charset!r}") from exc
        if raw_text is not None and str(raw_text) != decoded:
            raise ValueError("raw_text does not exactly match raw_payload_base64 decoded with charset")
        return raw_bytes, decoded, charset
    if raw_text is None:
        raise ValueError("raw_text or raw_payload_base64 is required")
    decoded = str(raw_text)
    try:
        raw_bytes = decoded.encode(charset, errors="strict")
    except (LookupError, UnicodeEncodeError) as exc:
        raise ValueError(f"raw_text cannot be encoded with charset {charset!r}") from exc
    return raw_bytes, decoded, charset


def validate_capture_payload(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    warnings: list[str] = []
    if not isinstance(payload, dict):
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["payload-must-be-object"], "warnings": []}

    source_id = _clean(payload.get("source_id"))
    if source_id and not _source_exists(source_id):
        errors.append("unknown-global-source-id")
    language = _clean(payload.get("language_bcp47") or payload.get("language"))
    if not language:
        errors.append("language-bcp47-required")
    elif not LANGUAGE_RE.match(language):
        errors.append("invalid-language-bcp47")
    script = _clean(payload.get("script_iso15924"))
    if script and not SCRIPT_RE.match(script):
        errors.append("invalid-script-iso15924")
    normalization_form = _clean(payload.get("normalized_form")) or "NFC"
    if normalization_form not in ALLOWED_NORMALIZATION_FORMS:
        errors.append("invalid-normalization-form")
    if bool(payload.get("automatic_translation", False)):
        errors.append("automatic-translation-prohibited")
    if bool(payload.get("replace_original_with_normalized", False)):
        errors.append("original-replacement-prohibited")

    raw_bytes = b""
    raw_text = ""
    charset = _clean(payload.get("charset")) or "utf-8"
    try:
        raw_bytes, raw_text, charset = _decode_payload(payload)
    except ValueError as exc:
        errors.append(str(exc))

    if not raw_text:
        warnings.append("empty-original-text")
    if unicodedata.normalize("NFC", raw_text) != raw_text:
        warnings.append("original-text-not-nfc-normalized-preserved-as-received")

    normalized = {
        "source_id": source_id or None,
        "source_record_id": _clean(payload.get("source_record_id")) or None,
        "record_id": _clean(payload.get("record_id")) or None,
        "source_uri": _clean(payload.get("source_uri")) or None,
        "language_bcp47": language or None,
        "script_iso15924": script or None,
        "language_variant": _clean(payload.get("language_variant")) or None,
        "orthography_variant": _clean(payload.get("orthography_variant")) or None,
        "media_type": _clean(payload.get("media_type")) or "text/plain",
        "charset": charset,
        "normalized_form": normalization_form,
        "preserve_raw_payload": True,
        "preserve_raw_text": True,
        "create_normalized_derivative": bool(payload.get("create_normalized_derivative", True)),
        "automatic_translation": False,
        "replace_original_with_normalized": False,
        "raw_payload_sha256": _hash_bytes(raw_bytes) if raw_bytes or raw_text == "" else None,
        "raw_text_sha256": _hash_text(raw_text) if not errors or raw_text else (_hash_text(raw_text) if raw_text else None),
        "raw_byte_length": len(raw_bytes),
        "raw_character_length": len(raw_text),
    }
    normalized["capture_basis_fingerprint_sha256"] = _fingerprint(normalized)
    return {
        "schema": VALIDATION_CONTRACT,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "normalized": normalized,
        "guardrails": {
            "original_language_is_canonical": True,
            "translation_is_derived_representation": True,
            "normalization_replaces_original": False,
            "automatic_translation": False,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
            "automatic_platform_core_promotion": False,
        },
    }


def build_capture_package(payload: dict[str, Any]) -> dict[str, Any]:
    validation = validate_capture_payload(payload)
    if not validation["valid"]:
        raise ValueError("; ".join(validation["errors"]))
    normalized = validation["normalized"]
    raw_bytes, raw_text, charset = _decode_payload(payload)
    capture_basis = {
        "source_id": normalized["source_id"],
        "source_record_id": normalized["source_record_id"],
        "record_id": normalized["record_id"],
        "source_uri": normalized["source_uri"],
        "language_bcp47": normalized["language_bcp47"],
        "script_iso15924": normalized["script_iso15924"],
        "language_variant": normalized["language_variant"],
        "orthography_variant": normalized["orthography_variant"],
        "media_type": normalized["media_type"],
        "charset": charset,
        "raw_payload_sha256": _hash_bytes(raw_bytes),
        "raw_text_sha256": _hash_text(raw_text),
    }
    capture_fingerprint = _fingerprint(capture_basis)
    capture_id = "olc:" + capture_fingerprint[:32]
    original_representation_id = "textrep:" + _fingerprint({"capture_id": capture_id, "kind": "original", "text_sha256": _hash_text(raw_text)})[:32]
    original_representation = {
        "schema": REPRESENTATION_CONTRACT,
        "representation_id": original_representation_id,
        "capture_id": capture_id,
        "representation_kind": "original",
        "language_bcp47": normalized["language_bcp47"],
        "script_iso15924": normalized["script_iso15924"],
        "language_variant": normalized["language_variant"],
        "orthography_variant": normalized["orthography_variant"],
        "text_sha256": _hash_text(raw_text),
        "canonical_original": True,
        "derived": False,
        "normalization_form": None,
    }
    representations = [original_representation]
    transformations: list[dict[str, Any]] = []
    if normalized["create_normalized_derivative"]:
        form = normalized["normalized_form"]
        derived_text = unicodedata.normalize(form, raw_text)
        derived_id = "textrep:" + _fingerprint({"capture_id": capture_id, "kind": "unicode-normalized", "form": form, "text_sha256": _hash_text(derived_text)})[:32]
        representations.append({
            "schema": REPRESENTATION_CONTRACT,
            "representation_id": derived_id,
            "capture_id": capture_id,
            "representation_kind": "unicode-normalized",
            "language_bcp47": normalized["language_bcp47"],
            "script_iso15924": normalized["script_iso15924"],
            "language_variant": normalized["language_variant"],
            "orthography_variant": normalized["orthography_variant"],
            "text_sha256": _hash_text(derived_text),
            "canonical_original": False,
            "derived": True,
            "normalization_form": form,
        })
        transformation_id = "textxfm:" + _fingerprint({"input": original_representation_id, "output": derived_id, "operation": "unicode-normalization", "form": form})[:32]
        transformations.append({
            "schema": TRANSFORMATION_CONTRACT,
            "transformation_id": transformation_id,
            "capture_id": capture_id,
            "input_representation_id": original_representation_id,
            "output_representation_id": derived_id,
            "operation": "unicode-normalization",
            "parameters": {"normalization_form": form},
            "automatic": True,
            "lossless_claim": False,
            "translation": False,
        })
    return {
        "schema": CAPTURE_CONTRACT,
        "capture_id": capture_id,
        "capture_fingerprint_sha256": capture_fingerprint,
        "source_id": normalized["source_id"],
        "source_record_id": normalized["source_record_id"],
        "record_id": normalized["record_id"],
        "source_uri": normalized["source_uri"],
        "language_bcp47": normalized["language_bcp47"],
        "script_iso15924": normalized["script_iso15924"],
        "language_variant": normalized["language_variant"],
        "orthography_variant": normalized["orthography_variant"],
        "media_type": normalized["media_type"],
        "charset": charset,
        "raw_payload_sha256": _hash_bytes(raw_bytes),
        "raw_text_sha256": _hash_text(raw_text),
        "raw_byte_length": len(raw_bytes),
        "raw_character_length": len(raw_text),
        "representations": representations,
        "transformations": transformations,
        "guardrails": validation["guardrails"],
        "_raw_bytes": raw_bytes,
        "_raw_text": raw_text,
        "_normalized_text": unicodedata.normalize(normalized["normalized_form"], raw_text) if normalized["create_normalized_derivative"] else None,
        "_source_metadata": dict(payload.get("source_metadata") or {}) if isinstance(payload.get("source_metadata"), dict) else {},
        "_provenance": dict(payload.get("provenance") or {}) if isinstance(payload.get("provenance"), dict) else {},
    }


def ingest_capture(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    package = build_capture_package(payload)
    raw_bytes = package.pop("_raw_bytes")
    raw_text = package.pop("_raw_text")
    normalized_text = package.pop("_normalized_text")
    source_metadata = package.pop("_source_metadata")
    provenance = package.pop("_provenance")
    pool = get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO library_original_language_captures(
                    capture_id,capture_fingerprint,record_id,source_id,source_record_id,source_uri,
                    language_bcp47,script_iso15924,language_variant,orthography_variant,media_type,charset,
                    raw_payload,raw_payload_sha256,raw_text,raw_text_sha256,source_metadata,provenance,retrieved_at
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,COALESCE(%s::timestamptz,now()))
                ON CONFLICT (capture_id) DO NOTHING
                """,
                (
                    package["capture_id"], package["capture_fingerprint_sha256"], package["record_id"], package["source_id"],
                    package["source_record_id"], package["source_uri"], package["language_bcp47"], package["script_iso15924"],
                    package["language_variant"], package["orthography_variant"], package["media_type"], package["charset"],
                    raw_bytes, package["raw_payload_sha256"], raw_text, package["raw_text_sha256"], Jsonb(source_metadata), Jsonb(provenance),
                    _clean(payload.get("retrieved_at")) or None,
                ),
            )
            for rep in package["representations"]:
                text = raw_text if rep["representation_kind"] == "original" else normalized_text
                cur.execute(
                    """
                    INSERT INTO library_text_representations(
                        representation_id,capture_id,representation_kind,language_bcp47,script_iso15924,
                        language_variant,orthography_variant,text_content,text_sha256,canonical_original,derived,normalization_form,provenance
                    ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON CONFLICT (representation_id) DO NOTHING
                    """,
                    (
                        rep["representation_id"], package["capture_id"], rep["representation_kind"], rep["language_bcp47"],
                        rep["script_iso15924"], rep["language_variant"], rep["orthography_variant"], text or "", rep["text_sha256"],
                        rep["canonical_original"], rep["derived"], rep["normalization_form"], Jsonb({"capture_fingerprint_sha256": package["capture_fingerprint_sha256"]}),
                    ),
                )
            for xfm in package["transformations"]:
                cur.execute(
                    """
                    INSERT INTO library_text_transformations(
                        transformation_id,capture_id,input_representation_id,output_representation_id,operation,
                        parameters,automatic,lossless_claim,translation,provenance
                    ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON CONFLICT (transformation_id) DO NOTHING
                    """,
                    (
                        xfm["transformation_id"], package["capture_id"], xfm["input_representation_id"], xfm["output_representation_id"],
                        xfm["operation"], Jsonb(xfm["parameters"]), xfm["automatic"], xfm["lossless_claim"], xfm["translation"],
                        Jsonb({"capture_fingerprint_sha256": package["capture_fingerprint_sha256"]}),
                    ),
                )
        conn.commit()
    package["persisted"] = True
    package["raw_payload_in_response"] = False
    package["raw_text_in_response"] = False
    return package


def get_capture(capture_id: str, include_text: bool = False) -> dict[str, Any]:
    from .db import get_pool
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_original_language_captures WHERE capture_id=%s", (capture_id,))
        capture = cur.fetchone()
        if not capture:
            raise KeyError(capture_id)
        cur.execute("SELECT * FROM library_text_representations WHERE capture_id=%s ORDER BY canonical_original DESC, created_at ASC", (capture_id,))
        reps = [dict(row) for row in cur.fetchall()]
        cur.execute("SELECT * FROM library_text_transformations WHERE capture_id=%s ORDER BY created_at ASC", (capture_id,))
        transforms = [dict(row) for row in cur.fetchall()]
    capture_out = dict(capture)
    raw_payload = capture_out.pop("raw_payload", None)
    raw_text = capture_out.pop("raw_text", None)
    if include_text:
        capture_out["raw_text"] = raw_text
        capture_out["raw_payload_base64"] = base64.b64encode(bytes(raw_payload or b"")).decode("ascii")
    for rep in reps:
        if not include_text:
            rep.pop("text_content", None)
    return {
        "schema": CAPTURE_CONTRACT,
        "capture": capture_out,
        "representations": reps,
        "transformations": transforms,
        "raw_content_included": include_text,
    }


def readiness() -> dict[str, Any]:
    from .db import get_pool
    counts = {"captures": 0, "representations": 0, "transformations": 0}
    state = "ready"
    try:
        pool = get_pool()
        with pool.connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute("SELECT count(*) AS n FROM library_original_language_captures")
            counts["captures"] = int(cur.fetchone()["n"])
            cur.execute("SELECT count(*) AS n FROM library_text_representations")
            counts["representations"] = int(cur.fetchone()["n"])
            cur.execute("SELECT count(*) AS n FROM library_text_transformations")
            counts["transformations"] = int(cur.fetchone()["n"])
    except Exception:
        state = "schema-unavailable"
    return {
        "schema": READINESS_CONTRACT,
        "contract": CORPUS_CONTRACT,
        "version": "5.45.0",
        "state": state,
        "counts": counts,
        "preservation": {
            "raw_payload_preserved": True,
            "raw_text_preserved": True,
            "sha256_content_addressing": True,
            "original_representation_canonical": True,
            "unicode_normalization_is_derived": True,
            "source_language_identity_preserved": True,
            "script_identity_supported": True,
            "orthography_variant_supported": True,
            "global_source_registry_link_supported": True,
        },
        "guardrails": {
            "translation_is_derived_representation": True,
            "automatic_translation": False,
            "normalized_text_replaces_original": False,
            "original_language_capture_is_evidence_truth": False,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
            "automatic_platform_core_promotion": False,
        },
        "next_lineage": {
            "ocr_htr_transcription_lineage": "v5.46.0",
            "linguistic_corpus_objects": "v5.47.0",
        },
    }
