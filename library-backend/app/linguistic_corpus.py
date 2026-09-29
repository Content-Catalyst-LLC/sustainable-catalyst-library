from __future__ import annotations

from collections import Counter
from hashlib import sha256
import json
import re
from typing import Any

CORPUS_CONTRACT = "sc-library-linguistic-corpus/1.0"
DOCUMENT_CONTRACT = "sc-library-linguistic-document/1.0"
TOKEN_CONTRACT = "sc-library-linguistic-token/1.0"
TOKENIZER_CONTRACT = "sc-library-tokenizer-specification/1.0"
VALIDATION_CONTRACT = "sc-library-linguistic-corpus-validation/1.0"
CONCORDANCE_CONTRACT = "sc-library-concordance-query/1.0"
KWIC_CONTRACT = "sc-library-kwic-result/1.0"
FREQUENCY_CONTRACT = "sc-library-corpus-frequency-table/1.0"
READINESS_CONTRACT = "sc-library-linguistic-corpus-readiness/1.0"

TOKEN_PATTERN = re.compile(r"\w+(?:[’'\-]\w+)*|[^\w\s]", re.UNICODE)
LANGUAGE_RE = re.compile(r"^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$")
SCRIPT_RE = re.compile(r"^[A-Z][a-z]{3}$")
SOURCE_KINDS = {"original", "unicode-normalized", "ocr", "htr", "transcription", "other-derived"}


def _canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fingerprint(value: Any) -> str:
    return sha256(_canonical_json(value).encode("utf-8")).hexdigest()


def _hash_text(value: str) -> str:
    return sha256(value.encode("utf-8")).hexdigest()


def _clean(value: Any) -> str:
    return str(value or "").strip()


def tokenizer_specification() -> dict[str, Any]:
    spec = {
        "schema": TOKENIZER_CONTRACT,
        "profile": "unicode-word-v1",
        "version": "1.0",
        "algorithm": "unicode-regex-word-plus-punctuation",
        "pattern": TOKEN_PATTERN.pattern,
        "casefold_field": True,
        "character_offsets": "python-unicode-codepoint-index",
        "whitespace_tokens_emitted": False,
        "punctuation_tokens_emitted": True,
        "language_specific_segmentation": False,
        "morphology_inferred": False,
        "lemma_inferred": False,
        "part_of_speech_inferred": False,
        "syntax_inferred": False,
    }
    spec["fingerprint_sha256"] = _fingerprint({k: v for k, v in spec.items() if k not in {"schema", "fingerprint_sha256"}})
    return spec


def tokenize_text(text: str) -> list[dict[str, Any]]:
    tokens: list[dict[str, Any]] = []
    for sequence, match in enumerate(TOKEN_PATTERN.finditer(text), start=1):
        token = match.group(0)
        token_kind = "word" if any(ch.isalnum() or ch == "_" for ch in token) else "punctuation"
        tokens.append({
            "schema": TOKEN_CONTRACT,
            "sequence": sequence,
            "text": token,
            "normalized_text": token.casefold(),
            "token_kind": token_kind,
            "start_char": match.start(),
            "end_char": match.end(),
            "text_sha256": _hash_text(token),
        })
    return tokens


def _normalize_document(item: dict[str, Any], index: int, errors: list[str], warnings: list[str]) -> dict[str, Any]:
    representation_id = _clean(item.get("representation_id"))
    text = item.get("text")
    if not representation_id:
        errors.append(f"document-{index}-representation-id-required")
    if text is None:
        errors.append(f"document-{index}-text-required")
        text = ""
    else:
        text = str(text)
    language = _clean(item.get("language_bcp47") or item.get("language"))
    if not language:
        errors.append(f"document-{index}-language-bcp47-required")
    elif not LANGUAGE_RE.match(language):
        errors.append(f"document-{index}-invalid-language-bcp47")
    script = _clean(item.get("script_iso15924"))
    if script and not SCRIPT_RE.match(script):
        errors.append(f"document-{index}-invalid-script-iso15924")
    source_kind = _clean(item.get("source_kind") or item.get("representation_kind")) or "other-derived"
    if source_kind not in SOURCE_KINDS:
        errors.append(f"document-{index}-invalid-source-kind")
    if not text:
        warnings.append(f"document-{index}-empty-text")
    return {
        "representation_id": representation_id or None,
        "record_id": _clean(item.get("record_id")) or None,
        "capture_id": _clean(item.get("capture_id")) or None,
        "source_asset_id": _clean(item.get("source_asset_id")) or None,
        "derivation_run_id": _clean(item.get("derivation_run_id")) or None,
        "source_kind": source_kind,
        "language_bcp47": language or None,
        "script_iso15924": script or None,
        "language_variant": _clean(item.get("language_variant")) or None,
        "orthography_variant": _clean(item.get("orthography_variant")) or None,
        "review_state": _clean(item.get("review_state")) or None,
        "text": text,
        "text_sha256": _hash_text(text),
        "metadata": dict(item.get("metadata") or {}) if isinstance(item.get("metadata"), dict) else {},
    }


def validate_corpus_payload(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["payload-must-be-object"], "warnings": []}
    errors: list[str] = []
    warnings: list[str] = []
    raw_documents = payload.get("documents") or []
    if not isinstance(raw_documents, list):
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["documents-must-be-array"], "warnings": []}
    if not raw_documents:
        errors.append("at-least-one-document-required")
    documents: list[dict[str, Any]] = []
    seen_representation_ids: set[str] = set()
    for index, item in enumerate(raw_documents, start=1):
        if not isinstance(item, dict):
            errors.append(f"document-{index}-must-be-object")
            continue
        doc = _normalize_document(item, index, errors, warnings)
        rid = doc.get("representation_id")
        if rid:
            if rid in seen_representation_ids:
                errors.append(f"document-{index}-duplicate-representation-id")
            seen_representation_ids.add(rid)
        documents.append(doc)

    prohibited = {
        "automatic_translation": "automatic-translation-prohibited",
        "automatic_transliteration": "automatic-transliteration-prohibited",
        "automatic_lemmatization": "automatic-lemmatization-prohibited",
        "automatic_pos_tagging": "automatic-pos-tagging-prohibited",
        "automatic_syntax_parsing": "automatic-syntax-parsing-prohibited",
        "automatic_evidence_promotion": "automatic-evidence-promotion-prohibited",
        "automatic_truth_promotion": "automatic-truth-promotion-prohibited",
    }
    for field, error in prohibited.items():
        if bool(payload.get(field, False)):
            errors.append(error)

    title = _clean(payload.get("title")) or "Untitled linguistic corpus"
    description = _clean(payload.get("description")) or None
    spec = tokenizer_specification()
    normalized = {
        "title": title,
        "description": description,
        "documents": documents,
        "tokenizer": spec,
        "metadata": dict(payload.get("metadata") or {}) if isinstance(payload.get("metadata"), dict) else {},
    }
    normalized["basis_fingerprint_sha256"] = _fingerprint({
        "title": title,
        "description": description,
        "documents": [
            {
                "representation_id": d.get("representation_id"),
                "text_sha256": d.get("text_sha256"),
                "language_bcp47": d.get("language_bcp47"),
                "script_iso15924": d.get("script_iso15924"),
                "source_kind": d.get("source_kind"),
                "derivation_run_id": d.get("derivation_run_id"),
            }
            for d in documents
        ],
        "tokenizer_fingerprint": spec["fingerprint_sha256"],
        "metadata": normalized["metadata"],
    })
    return {
        "schema": VALIDATION_CONTRACT,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "normalized": normalized,
        "guardrails": {
            "tokenization_is_morphological_analysis": False,
            "tokenization_is_part_of_speech_tagging": False,
            "tokenization_is_syntax_parsing": False,
            "frequency_implies_importance": False,
            "kwic_context_establishes_meaning_or_intent": False,
            "derived_source_lineage_preserved": True,
            "automatic_translation": False,
            "automatic_transliteration": False,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
            "automatic_platform_core_promotion": False,
        },
    }


def build_corpus_package(payload: dict[str, Any]) -> dict[str, Any]:
    validation = validate_corpus_payload(payload)
    if not validation["valid"]:
        raise ValueError("; ".join(validation["errors"]))
    normalized = validation["normalized"]
    corpus_fingerprint = normalized["basis_fingerprint_sha256"]
    corpus_id = "lingcorpus:" + corpus_fingerprint[:32]
    documents_out: list[dict[str, Any]] = []
    token_count = 0
    languages: Counter[str] = Counter()
    scripts: Counter[str] = Counter()
    source_kinds: Counter[str] = Counter()
    for sequence, doc in enumerate(normalized["documents"], start=1):
        document_fingerprint = _fingerprint({
            "corpus_id": corpus_id,
            "representation_id": doc["representation_id"],
            "text_sha256": doc["text_sha256"],
            "sequence": sequence,
        })
        document_id = "lingdoc:" + document_fingerprint[:32]
        tokens = tokenize_text(doc["text"])
        for token in tokens:
            token["token_id"] = "lingtok:" + _fingerprint({
                "document_id": document_id,
                "sequence": token["sequence"],
                "start_char": token["start_char"],
                "end_char": token["end_char"],
                "text_sha256": token["text_sha256"],
            })[:32]
            token["document_id"] = document_id
            token["representation_id"] = doc["representation_id"]
        token_count += len(tokens)
        if doc["language_bcp47"]:
            languages[doc["language_bcp47"]] += 1
        if doc["script_iso15924"]:
            scripts[doc["script_iso15924"]] += 1
        source_kinds[doc["source_kind"]] += 1
        documents_out.append({
            "schema": DOCUMENT_CONTRACT,
            "document_id": document_id,
            "corpus_id": corpus_id,
            "sequence": sequence,
            "representation_id": doc["representation_id"],
            "record_id": doc["record_id"],
            "capture_id": doc["capture_id"],
            "source_asset_id": doc["source_asset_id"],
            "derivation_run_id": doc["derivation_run_id"],
            "source_kind": doc["source_kind"],
            "language_bcp47": doc["language_bcp47"],
            "script_iso15924": doc["script_iso15924"],
            "language_variant": doc["language_variant"],
            "orthography_variant": doc["orthography_variant"],
            "review_state": doc["review_state"],
            "text_sha256": doc["text_sha256"],
            "character_count": len(doc["text"]),
            "token_count": len(tokens),
            "tokens": tokens,
            "metadata": doc["metadata"],
        })
    return {
        "schema": CORPUS_CONTRACT,
        "corpus_id": corpus_id,
        "corpus_fingerprint_sha256": corpus_fingerprint,
        "title": normalized["title"],
        "description": normalized["description"],
        "tokenizer": normalized["tokenizer"],
        "document_count": len(documents_out),
        "token_count": token_count,
        "language_distribution": dict(sorted(languages.items())),
        "script_distribution": dict(sorted(scripts.items())),
        "source_kind_distribution": dict(sorted(source_kinds.items())),
        "documents": documents_out,
        "metadata": normalized["metadata"],
        "guardrails": validation["guardrails"],
        "persisted": False,
    }


def _query_tokens(query: str) -> list[str]:
    return [t["normalized_text"] for t in tokenize_text(query) if t["token_kind"] == "word"]


def kwic_from_package(
    package: dict[str, Any],
    query: str,
    *,
    window_tokens: int = 5,
    case_sensitive: bool = False,
    limit: int = 100,
    offset: int = 0,
) -> dict[str, Any]:
    query = str(query or "").strip()
    if not query:
        raise ValueError("query-required")
    if window_tokens < 0 or window_tokens > 50:
        raise ValueError("window_tokens must be between 0 and 50")
    if limit < 1 or limit > 500:
        raise ValueError("limit must be between 1 and 500")
    if offset < 0:
        raise ValueError("offset must be non-negative")
    q_tokens = tokenize_text(query)
    if not q_tokens:
        raise ValueError("query-produced-no-tokens")
    q_values = [t["text"] if case_sensitive else t["normalized_text"] for t in q_tokens]
    matches: list[dict[str, Any]] = []
    total = 0
    for doc in package.get("documents") or []:
        tokens = doc.get("tokens") or []
        values = [t["text"] if case_sensitive else t["normalized_text"] for t in tokens]
        width = len(q_values)
        for i in range(0, max(0, len(values) - width + 1)):
            if values[i:i + width] != q_values:
                continue
            total += 1
            if total <= offset or len(matches) >= limit:
                continue
            start = max(0, i - window_tokens)
            end = min(len(tokens), i + width + window_tokens)
            match_tokens = tokens[i:i + width]
            context_tokens = tokens[start:end]
            matches.append({
                "document_id": doc["document_id"],
                "representation_id": doc["representation_id"],
                "record_id": doc.get("record_id"),
                "language_bcp47": doc.get("language_bcp47"),
                "script_iso15924": doc.get("script_iso15924"),
                "source_kind": doc.get("source_kind"),
                "derivation_run_id": doc.get("derivation_run_id"),
                "review_state": doc.get("review_state"),
                "left": " ".join(t["text"] for t in tokens[start:i]),
                "match": " ".join(t["text"] for t in match_tokens),
                "right": " ".join(t["text"] for t in tokens[i + width:end]),
                "token_start": i + 1,
                "token_end": i + width,
                "char_start": match_tokens[0]["start_char"],
                "char_end": match_tokens[-1]["end_char"],
                "context_token_start": context_tokens[0]["sequence"] if context_tokens else None,
                "context_token_end": context_tokens[-1]["sequence"] if context_tokens else None,
            })
    fingerprint_basis = {
        "corpus_id": package.get("corpus_id"),
        "query": query,
        "window_tokens": window_tokens,
        "case_sensitive": case_sensitive,
        "limit": limit,
        "offset": offset,
        "tokenizer_fingerprint": (package.get("tokenizer") or {}).get("fingerprint_sha256"),
    }
    return {
        "schema": KWIC_CONTRACT,
        "concordance_schema": CONCORDANCE_CONTRACT,
        "query_fingerprint_sha256": _fingerprint(fingerprint_basis),
        "corpus_id": package.get("corpus_id"),
        "query": query,
        "query_tokens": [t["text"] for t in q_tokens],
        "window_tokens": window_tokens,
        "case_sensitive": case_sensitive,
        "offset": offset,
        "limit": limit,
        "total_matches": total,
        "returned_matches": len(matches),
        "matches": matches,
        "guardrails": {
            "kwic_context_establishes_meaning_or_intent": False,
            "frequency_or_occurrence_is_evidence_truth": False,
            "source_representation_lineage_preserved": True,
        },
    }


def frequency_table_from_package(
    package: dict[str, Any], *, min_count: int = 1, limit: int = 100, words_only: bool = True
) -> dict[str, Any]:
    if min_count < 1:
        raise ValueError("min_count must be at least 1")
    if limit < 1 or limit > 1000:
        raise ValueError("limit must be between 1 and 1000")
    counts: Counter[str] = Counter()
    forms: dict[str, Counter[str]] = {}
    for doc in package.get("documents") or []:
        for token in doc.get("tokens") or []:
            if words_only and token.get("token_kind") != "word":
                continue
            key = token.get("normalized_text") or ""
            if not key:
                continue
            counts[key] += 1
            forms.setdefault(key, Counter())[token.get("text") or key] += 1
    rows = []
    for normalized, count in sorted(counts.items(), key=lambda kv: (-kv[1], kv[0])):
        if count < min_count:
            continue
        variants = [{"form": form, "count": n} for form, n in sorted(forms[normalized].items(), key=lambda kv: (-kv[1], kv[0]))]
        rows.append({"normalized_text": normalized, "count": count, "forms": variants})
        if len(rows) >= limit:
            break
    return {
        "schema": FREQUENCY_CONTRACT,
        "corpus_id": package.get("corpus_id"),
        "words_only": words_only,
        "min_count": min_count,
        "limit": limit,
        "rows": rows,
        "guardrails": {
            "frequency_implies_importance": False,
            "frequency_is_evidence_truth": False,
            "tokenizer_is_language_specific_morphology": False,
        },
    }


def _hydrate_documents_from_db(payload: dict[str, Any]) -> dict[str, Any]:
    from .db import get_pool
    raw_documents = payload.get("documents") or []
    if not isinstance(raw_documents, list):
        return payload
    hydrated = []
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        for item in raw_documents:
            if not isinstance(item, dict):
                hydrated.append(item)
                continue
            rep_id = _clean(item.get("representation_id"))
            if not rep_id:
                hydrated.append(item)
                continue
            cur.execute(
                """
                SELECT r.*, d.run_id AS derivation_run_id, d.derivation_kind,
                       d.source_asset_id AS derivation_source_asset_id, d.engine_spec_fingerprint,
                       d.review_state AS derivation_review_state
                  FROM library_text_representations r
                  LEFT JOIN library_text_derivation_runs d ON d.output_representation_id=r.representation_id
                 WHERE r.representation_id=%s
                """,
                (rep_id,),
            )
            row = cur.fetchone()
            if not row:
                raise ValueError(f"representation_id not found: {rep_id}")
            source_kind = row["representation_kind"]
            hydrated.append({
                **item,
                "text": row["text_content"],
                "capture_id": row["capture_id"],
                "source_asset_id": row["source_asset_id"] or row["derivation_source_asset_id"],
                "derivation_run_id": row["derivation_run_id"],
                "source_kind": source_kind if source_kind in SOURCE_KINDS else "other-derived",
                "language_bcp47": item.get("language_bcp47") or row["language_bcp47"],
                "script_iso15924": item.get("script_iso15924") or row["script_iso15924"],
                "language_variant": item.get("language_variant") or row["language_variant"],
                "orthography_variant": item.get("orthography_variant") or row["orthography_variant"],
                "review_state": item.get("review_state") or row["derivation_review_state"],
            })
    return {**payload, "documents": hydrated}


def ingest_corpus(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    hydrated = _hydrate_documents_from_db(payload)
    package = build_corpus_package(hydrated)
    pool = get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO library_linguistic_corpora(
                  corpus_id,corpus_fingerprint,title,description,tokenizer_spec,tokenizer_spec_fingerprint,
                  language_distribution,script_distribution,source_kind_distribution,document_count,token_count,metadata
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON CONFLICT (corpus_id) DO NOTHING
                """,
                (
                    package["corpus_id"], package["corpus_fingerprint_sha256"], package["title"], package["description"],
                    Jsonb(package["tokenizer"]), package["tokenizer"]["fingerprint_sha256"],
                    Jsonb(package["language_distribution"]), Jsonb(package["script_distribution"]), Jsonb(package["source_kind_distribution"]),
                    package["document_count"], package["token_count"], Jsonb(package["metadata"]),
                ),
            )
            for doc in package["documents"]:
                cur.execute(
                    """
                    INSERT INTO library_linguistic_documents(
                      document_id,corpus_id,sequence,representation_id,record_id,capture_id,source_asset_id,derivation_run_id,
                      source_kind,language_bcp47,script_iso15924,language_variant,orthography_variant,review_state,
                      text_sha256,character_count,token_count,metadata
                    ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON CONFLICT (document_id) DO NOTHING
                    """,
                    (
                        doc["document_id"], doc["corpus_id"], doc["sequence"], doc["representation_id"], doc["record_id"],
                        doc["capture_id"], doc["source_asset_id"], doc["derivation_run_id"], doc["source_kind"],
                        doc["language_bcp47"], doc["script_iso15924"], doc["language_variant"], doc["orthography_variant"],
                        doc["review_state"], doc["text_sha256"], doc["character_count"], doc["token_count"], Jsonb(doc["metadata"]),
                    ),
                )
                for token in doc["tokens"]:
                    cur.execute(
                        """
                        INSERT INTO library_linguistic_tokens(
                          token_id,corpus_id,document_id,representation_id,sequence,token_text,normalized_text,
                          token_kind,start_char,end_char,text_sha256
                        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                        ON CONFLICT (token_id) DO NOTHING
                        """,
                        (
                            token["token_id"], package["corpus_id"], doc["document_id"], doc["representation_id"], token["sequence"],
                            token["text"], token["normalized_text"], token["token_kind"], token["start_char"], token["end_char"], token["text_sha256"],
                        ),
                    )
        conn.commit()
    clean = _public_package(package, include_tokens=False)
    clean["persisted"] = True
    return clean


def _public_package(package: dict[str, Any], include_tokens: bool = True) -> dict[str, Any]:
    out = {k: v for k, v in package.items() if k != "documents"}
    documents = []
    for doc in package.get("documents") or []:
        d = {k: v for k, v in doc.items() if k != "_text" and (include_tokens or k != "tokens")}
        documents.append(d)
    out["documents"] = documents
    return out


def get_corpus(corpus_id: str, include_tokens: bool = False) -> dict[str, Any]:
    from .db import get_pool
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_linguistic_corpora WHERE corpus_id=%s", (corpus_id,))
        corpus = cur.fetchone()
        if not corpus:
            raise KeyError(corpus_id)
        cur.execute("SELECT * FROM library_linguistic_documents WHERE corpus_id=%s ORDER BY sequence ASC", (corpus_id,))
        docs = [dict(r) for r in cur.fetchall()]
        if include_tokens:
            for doc in docs:
                cur.execute("SELECT * FROM library_linguistic_tokens WHERE document_id=%s ORDER BY sequence ASC", (doc["document_id"],))
                doc["tokens"] = [dict(r) for r in cur.fetchall()]
    return {"schema": CORPUS_CONTRACT, "corpus": dict(corpus), "documents": docs, "tokens_included": include_tokens}


def kwic_persisted_corpus(corpus_id: str, query: str, **kwargs: Any) -> dict[str, Any]:
    from .db import get_pool
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_linguistic_corpora WHERE corpus_id=%s", (corpus_id,))
        corpus = cur.fetchone()
        if not corpus:
            raise KeyError(corpus_id)
        cur.execute("SELECT * FROM library_linguistic_documents WHERE corpus_id=%s ORDER BY sequence ASC", (corpus_id,))
        docs = [dict(r) for r in cur.fetchall()]
        package = {
            "corpus_id": corpus_id,
            "tokenizer": corpus["tokenizer_spec"],
            "documents": [],
        }
        for doc in docs:
            cur.execute("SELECT * FROM library_linguistic_tokens WHERE document_id=%s ORDER BY sequence ASC", (doc["document_id"],))
            tokens = []
            for row in cur.fetchall():
                t = dict(row)
                t["text"] = t.pop("token_text")
                tokens.append(t)
            package["documents"].append({**doc, "tokens": tokens})
    return kwic_from_package(package, query, **kwargs)


def readiness() -> dict[str, Any]:
    from .db import get_pool
    counts = {"corpora": 0, "documents": 0, "tokens": 0, "original_documents": 0, "derived_documents": 0}
    state = "ready"
    try:
        pool = get_pool()
        with pool.connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute("SELECT count(*) AS n FROM library_linguistic_corpora"); counts["corpora"] = int(cur.fetchone()["n"])
            cur.execute("SELECT count(*) AS n FROM library_linguistic_documents"); counts["documents"] = int(cur.fetchone()["n"])
            cur.execute("SELECT count(*) AS n FROM library_linguistic_tokens"); counts["tokens"] = int(cur.fetchone()["n"])
            cur.execute("SELECT count(*) AS n FROM library_linguistic_documents WHERE source_kind='original'"); counts["original_documents"] = int(cur.fetchone()["n"])
            counts["derived_documents"] = max(0, counts["documents"] - counts["original_documents"])
    except Exception:
        state = "schema-unavailable"
    return {
        "schema": READINESS_CONTRACT,
        "contract": CORPUS_CONTRACT,
        "version": "5.47.0",
        "state": state,
        "counts": counts,
        "tokenizer": tokenizer_specification(),
        "capabilities": {
            "corpus_objects": True,
            "document_objects": True,
            "token_objects": True,
            "character_offsets": True,
            "representation_lineage": True,
            "ocr_htr_transcription_lineage": True,
            "concordance": True,
            "kwic": True,
            "frequency_tables": True,
            "case_sensitive_queries": True,
            "phrase_queries": True,
            "deterministic_query_fingerprints": True,
        },
        "guardrails": {
            "tokenization_is_morphological_analysis": False,
            "tokenization_is_part_of_speech_tagging": False,
            "tokenization_is_syntax_parsing": False,
            "frequency_implies_importance": False,
            "kwic_context_establishes_meaning_or_intent": False,
            "automatic_translation": False,
            "automatic_transliteration": False,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
            "automatic_platform_core_promotion": False,
        },
        "next_lineage": {"cross_language_entity_name_historical_toponym_resolution": "v5.48.0"},
    }
