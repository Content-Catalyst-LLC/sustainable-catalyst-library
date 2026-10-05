from __future__ import annotations

from collections import Counter
from hashlib import sha256
import json
from typing import Any

from .linguistic_corpus import (
    build_corpus_package,
    frequency_table_from_package,
    get_corpus,
    kwic_from_package,
    readiness as linguistic_corpus_readiness,
    tokenizer_specification,
)

LIBRARY_VERSION = "6.18.0"
BACKEND_VERSION = "3.18.0"
WEB_VERSION = "2.18.0"
SDK_VERSION = "1.18.0"

CONTRACT = "sc-library-corpus-computational-linguistics-workspace/1.0"
READINESS_CONTRACT = "sc-library-corpus-computational-linguistics-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-corpus-computational-linguistics-bootstrap/1.0"
NGRAM_CONTRACT = "sc-library-computational-linguistics-ngram-analysis/1.0"
COOCCURRENCE_CONTRACT = "sc-library-computational-linguistics-cooccurrence-analysis/1.0"
EXPORT_CONTRACT = "sc-library-computational-linguistics-analysis-package/1.0"
HANDOFF_CONTRACT = "sc-library-linguistic-corpus-persistence-handoff-preview/1.0"

MAX_PREVIEW_DOCUMENTS = 250
MAX_TEXT_CHARS_PER_DOCUMENT = 2_000_000
MAX_N = 5
MAX_ROWS = 1000


def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 10000) -> str:
    return str(value or "").strip()[:limit]


def guardrails() -> dict[str, bool]:
    return {
        "existing_v547_linguistic_corpus_is_durable_authority": True,
        "workspace_creates_parallel_corpus_store": False,
        "workspace_preview_is_persisted": False,
        "persistence_requires_explicit_signed_existing_corpus_authority": True,
        "original_language_remains_canonical": True,
        "translation_is_derived_representation": True,
        "transliteration_is_derived_representation": True,
        "ocr_htr_transcription_are_derived_representations": True,
        "tokenization_is_morphological_analysis": False,
        "tokenization_is_lemmatization": False,
        "tokenization_is_part_of_speech_tagging": False,
        "tokenization_is_syntax_parsing": False,
        "frequency_implies_importance": False,
        "frequency_is_evidence_truth": False,
        "kwic_context_establishes_meaning_or_intent": False,
        "ngram_frequency_establishes_phrase_significance": False,
        "cooccurrence_establishes_semantic_relationship": False,
        "cooccurrence_establishes_causation": False,
        "cooccurrence_count_is_statistical_significance": False,
        "automatic_translation": False,
        "automatic_transliteration": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "corpus-preview", "persisted-corpus-analysis", "token-and-frequency-analysis",
        "concordance-kwic", "n-gram-analysis", "co-occurrence-analysis",
        "representation-lineage", "analysis-package-export", "explicit-persistence-handoff-preview",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "workspace_id": "corpus-computational-linguistics:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "durable_corpus_authority": "v5.47.0-python-linguistic-corpus-postgresql",
        "route": "/research/corpus",
        "api_base": "/api/library/v1/corpus-workspace",
        "resources": resources,
        "limits": {
            "preview_documents": MAX_PREVIEW_DOCUMENTS,
            "text_chars_per_document": MAX_TEXT_CHARS_PER_DOCUMENT,
            "ngram_n_max": MAX_N,
            "analysis_rows_max": MAX_ROWS,
        },
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    dependency = linguistic_corpus_readiness()
    dep_state = str(dependency.get("state") or "unknown")
    blocking = [] if dep_state == "ready" else ["v5.47-linguistic-corpus-not-ready"]
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready" if not blocking else "degraded",
        "ready": not blocking,
        "blocking": blocking,
        "degraded": [],
        "authority": "python-backend-composition",
        "durable_corpus_authority": "v5.47.0-python-linguistic-corpus-postgresql",
        "database_migration_required": False,
        "wordpress_required": False,
        "server_side_workspace_state": False,
        "dependencies": {"linguistic_corpus_v5_47": dependency},
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "route": "/research/corpus",
        "readiness": readiness(),
        "tokenizer": tokenizer_specification(),
        "source_kinds": ["original", "unicode-normalized", "ocr", "htr", "transcription", "other-derived"],
        "analysis_modes": ["preview", "persisted-corpus"],
        "operations": ["preview", "frequency", "kwic", "ngrams", "cooccurrence", "export", "persistence-handoff-preview"],
        "persistence": {
            "workspace_auto_persists": False,
            "existing_signed_validate_endpoint": "/api/library/v1/admin/language/corpora/validate",
            "existing_signed_create_endpoint": "/api/library/v1/admin/language/corpora",
        },
        "guardrails": guardrails(),
    }


def _workspace_payload(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    if payload.get("documents") is not None:
        docs = _list(payload.get("documents"))
    else:
        text = str(payload.get("text") or "")
        if not text:
            raise ValueError("documents-or-text-required")
        docs = [{
            "representation_id": _clean(payload.get("representation_id"), 500) or "workspace-text:1",
            "record_id": _clean(payload.get("record_id"), 500) or None,
            "source_kind": _clean(payload.get("source_kind"), 100) or "original",
            "language_bcp47": _clean(payload.get("language_bcp47") or payload.get("language"), 100) or "und",
            "script_iso15924": _clean(payload.get("script_iso15924"), 20) or None,
            "language_variant": _clean(payload.get("language_variant"), 200) or None,
            "orthography_variant": _clean(payload.get("orthography_variant"), 200) or None,
            "review_state": _clean(payload.get("review_state"), 100) or None,
            "text": text,
            "metadata": _dict(payload.get("document_metadata")),
        }]
    if not docs:
        raise ValueError("at-least-one-document-required")
    if len(docs) > MAX_PREVIEW_DOCUMENTS:
        raise ValueError(f"preview-document-limit-exceeded:{MAX_PREVIEW_DOCUMENTS}")
    normalized_docs = []
    for index, raw in enumerate(docs, start=1):
        if not isinstance(raw, dict):
            raise ValueError(f"document-{index}-must-be-object")
        row = dict(raw)
        text = str(row.get("text") or "")
        if len(text) > MAX_TEXT_CHARS_PER_DOCUMENT:
            raise ValueError(f"document-{index}-text-limit-exceeded:{MAX_TEXT_CHARS_PER_DOCUMENT}")
        if not row.get("representation_id"):
            row["representation_id"] = f"workspace-text:{index}"
        if not row.get("language_bcp47") and not row.get("language"):
            row["language_bcp47"] = "und"
        if not row.get("source_kind") and not row.get("representation_kind"):
            row["source_kind"] = "original"
        normalized_docs.append(row)
    return {
        "title": _clean(payload.get("title"), 500) or "Computational linguistics working corpus",
        "description": _clean(payload.get("description"), 5000) or None,
        "documents": normalized_docs,
        "metadata": _dict(payload.get("metadata")),
    }


def _persisted_package(corpus_id: str) -> dict[str, Any]:
    raw = get_corpus(corpus_id, include_tokens=True)
    corpus = _dict(raw.get("corpus"))
    docs = []
    for raw_doc in _list(raw.get("documents")):
        doc = dict(raw_doc)
        tokens = []
        for raw_token in _list(doc.get("tokens")):
            token = dict(raw_token)
            if "text" not in token and "token_text" in token:
                token["text"] = token.get("token_text")
            tokens.append(token)
        doc["tokens"] = tokens
        docs.append(doc)
    return {
        "corpus_id": corpus_id,
        "title": corpus.get("title"),
        "description": corpus.get("description"),
        "tokenizer": corpus.get("tokenizer_spec") or tokenizer_specification(),
        "document_count": corpus.get("document_count") or len(docs),
        "token_count": corpus.get("token_count"),
        "language_distribution": corpus.get("language_distribution") or {},
        "script_distribution": corpus.get("script_distribution") or {},
        "source_kind_distribution": corpus.get("source_kind_distribution") or {},
        "documents": docs,
        "metadata": corpus.get("metadata") or {},
        "persisted": True,
    }


def _resolve_package(payload: dict[str, Any]) -> tuple[dict[str, Any], str]:
    corpus_id = _clean(payload.get("corpus_id"), 500)
    if corpus_id:
        return _persisted_package(corpus_id), "persisted-corpus"
    return build_corpus_package(_workspace_payload(payload)), "preview"


def preview_corpus(payload: dict[str, Any]) -> dict[str, Any]:
    package, mode = _resolve_package(payload)
    return {
        "schema": "sc-library-computational-linguistics-corpus-preview/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "mode": mode,
        "corpus": package,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }


def frequency_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    package, mode = _resolve_package(payload)
    result = frequency_table_from_package(
        package,
        min_count=max(1, int(payload.get("min_count") or 1)),
        limit=max(1, min(MAX_ROWS, int(payload.get("limit") or 100))),
        words_only=bool(payload.get("words_only", True)),
    )
    return {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "mode": mode,
        **result,
        "guardrails": {**guardrails(), **_dict(result.get("guardrails"))},
    }


def kwic_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    package, mode = _resolve_package(payload)
    result = kwic_from_package(
        package,
        _clean(payload.get("query"), 1000),
        window_tokens=max(0, min(50, int(payload.get("window_tokens") or 5))),
        case_sensitive=bool(payload.get("case_sensitive", False)),
        limit=max(1, min(500, int(payload.get("limit") or 100))),
        offset=max(0, int(payload.get("offset") or 0)),
    )
    return {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "mode": mode,
        **result,
        "guardrails": {**guardrails(), **_dict(result.get("guardrails"))},
    }


def _analysis_tokens(doc: dict[str, Any], words_only: bool = True) -> list[dict[str, Any]]:
    rows = []
    for token in _list(doc.get("tokens")):
        t = dict(token)
        if words_only and t.get("token_kind") != "word":
            continue
        if not t.get("normalized_text"):
            continue
        rows.append(t)
    return rows


def ngram_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    package, mode = _resolve_package(payload)
    n = int(payload.get("n") or 2)
    if n < 1 or n > MAX_N:
        raise ValueError(f"n-must-be-between-1-and-{MAX_N}")
    limit = max(1, min(MAX_ROWS, int(payload.get("limit") or 100)))
    words_only = bool(payload.get("words_only", True))
    counts: Counter[tuple[str, ...]] = Counter()
    forms: dict[tuple[str, ...], Counter[str]] = {}
    for doc in _list(package.get("documents")):
        tokens = _analysis_tokens(doc, words_only=words_only)
        values = [str(t.get("normalized_text") or "") for t in tokens]
        display = [str(t.get("text") or t.get("normalized_text") or "") for t in tokens]
        for i in range(0, max(0, len(values) - n + 1)):
            key = tuple(values[i:i+n])
            if not all(key):
                continue
            counts[key] += 1
            forms.setdefault(key, Counter())[" ".join(display[i:i+n])] += 1
    rows = []
    for key, count in sorted(counts.items(), key=lambda kv: (-kv[1], kv[0]))[:limit]:
        variants = [{"form": form, "count": c} for form, c in sorted(forms[key].items(), key=lambda kv: (-kv[1], kv[0]))[:20]]
        rows.append({"ngram": " ".join(key), "tokens": list(key), "count": count, "forms": variants})
    basis = {"corpus_id": package.get("corpus_id"), "n": n, "words_only": words_only, "rows": rows}
    return {
        "schema": NGRAM_CONTRACT,
        "analysis_id": "ngram:" + _fp(basis)[:32],
        "analysis_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "mode": mode,
        "corpus_id": package.get("corpus_id"),
        "n": n,
        "words_only": words_only,
        "rows": rows,
        "guardrails": guardrails(),
    }


def cooccurrence_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    package, mode = _resolve_package(payload)
    term = _clean(payload.get("term"), 500).casefold()
    if not term:
        raise ValueError("term-required")
    window = max(1, min(50, int(payload.get("window_tokens") or 5)))
    limit = max(1, min(MAX_ROWS, int(payload.get("limit") or 100)))
    counts: Counter[str] = Counter()
    target_occurrences = 0
    for doc in _list(package.get("documents")):
        tokens = _analysis_tokens(doc, words_only=True)
        values = [str(t.get("normalized_text") or "") for t in tokens]
        for i, value in enumerate(values):
            if value != term:
                continue
            target_occurrences += 1
            lo = max(0, i-window)
            hi = min(len(values), i+window+1)
            for j in range(lo, hi):
                if j == i:
                    continue
                neighbor = values[j]
                if neighbor and neighbor != term:
                    counts[neighbor] += 1
    rows = [{"term": key, "count": count} for key, count in sorted(counts.items(), key=lambda kv: (-kv[1], kv[0]))[:limit]]
    basis = {"corpus_id": package.get("corpus_id"), "term": term, "window_tokens": window, "rows": rows}
    return {
        "schema": COOCCURRENCE_CONTRACT,
        "analysis_id": "cooccurrence:" + _fp(basis)[:32],
        "analysis_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "mode": mode,
        "corpus_id": package.get("corpus_id"),
        "term": term,
        "window_tokens": window,
        "target_occurrences": target_occurrences,
        "rows": rows,
        "guardrails": guardrails(),
    }


def persistence_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    working = _workspace_payload(payload)
    preview = build_corpus_package(working)
    basis = {
        "corpus_id": preview.get("corpus_id"),
        "basis_fingerprint_sha256": preview.get("corpus_fingerprint_sha256"),
        "endpoint": "/api/library/v1/admin/language/corpora",
    }
    return {
        "schema": HANDOFF_CONTRACT,
        "handoff_id": "linguistic-corpus-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "preview_only": True,
        "automatic_persistence": False,
        "signed_request_required": True,
        "existing_authority": "v5.47.0-python-linguistic-corpus-postgresql",
        "validate_endpoint": "/api/library/v1/admin/language/corpora/validate",
        "create_endpoint": "/api/library/v1/admin/language/corpora",
        "corpus_payload": working,
        "expected_corpus_id": preview.get("corpus_id"),
        "guardrails": guardrails(),
    }


def export_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    package, mode = _resolve_package(payload)
    base_payload = {**payload, "corpus_id": package.get("corpus_id")} if mode == "persisted-corpus" else payload
    analyses: dict[str, Any] = {}
    query = _clean(payload.get("query"), 1000)
    term = _clean(payload.get("term"), 500)
    if bool(payload.get("include_frequency", True)):
        analyses["frequency"] = frequency_analysis(base_payload)
    if query:
        analyses["kwic"] = kwic_analysis(base_payload)
    if payload.get("n"):
        analyses["ngrams"] = ngram_analysis(base_payload)
    if term:
        analyses["cooccurrence"] = cooccurrence_analysis(base_payload)
    corpus_summary = {
        "corpus_id": package.get("corpus_id"),
        "title": package.get("title"),
        "description": package.get("description"),
        "document_count": package.get("document_count"),
        "token_count": package.get("token_count"),
        "language_distribution": package.get("language_distribution"),
        "script_distribution": package.get("script_distribution"),
        "source_kind_distribution": package.get("source_kind_distribution"),
        "tokenizer": package.get("tokenizer"),
        "persisted": bool(package.get("persisted")),
    }
    basis = {"mode": mode, "corpus_summary": corpus_summary, "analyses": analyses}
    package_out = {
        "schema": EXPORT_CONTRACT,
        "package_id": "computational-linguistics-analysis:" + _fp(basis)[:32],
        "package_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "mode": mode,
        "corpus": corpus_summary,
        "analyses": analyses,
        "workspace_persisted": False,
        "automatic_import": False,
        "guardrails": guardrails(),
    }
    return {
        **package_out,
        "filename": "sustainable-catalyst-computational-linguistics-analysis.json",
        "media_type": "application/json",
        "content": json.dumps(package_out, ensure_ascii=False, sort_keys=True, indent=2),
    }
