from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .cross_language_resolution import (
    get_resolution_case, ingest_authority_registry, ingest_resolution_case,
    ingest_resolution_decision, readiness as cross_language_readiness,
)
from .linguistic_corpus import (
    get_corpus, ingest_corpus, kwic_persisted_corpus,
    readiness as linguistic_corpus_readiness, validate_corpus_payload,
)
from .ocr_htr_transcription_lineage import (
    get_derivation, ingest_derivation, readiness as derivation_readiness,
    validate_derivation_payload,
)
from .original_language_corpus import (
    get_capture, ingest_capture, readiness as original_language_readiness,
    validate_capture_payload,
)
from .scientific_document_intelligence import load_scientific_document_intelligence
from .translation_alignment import (
    get_alignment_matrix, ingest_alignment_matrix,
    readiness as alignment_readiness, validate_alignment_payload,
)

LIBRARY_VERSION = "6.2.0"
BACKEND_VERSION = "3.2.0"
CONTRACT = "sc-library-python-language-document-service/1.0"
READINESS_CONTRACT = "sc-library-python-language-document-readiness/1.0"
DOCUMENT_INTELLIGENCE_CONTRACT = "sc-library-language-document-intelligence-envelope/1.0"


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "python_is_language_intelligence_authority": True,
        "python_is_document_processing_authority": True,
        "wordpress_php_is_language_authority": False,
        "wordpress_php_is_document_processing_authority": False,
        "original_language_remains_canonical": True,
        "unicode_normalization_replaces_original": False,
        "ocr_htr_transcription_are_derived_representations": True,
        "translation_is_derived_representation": True,
        "transliteration_is_derived_representation": True,
        "alignment_confidence_is_truth_probability": False,
        "entity_resolution_score_is_truth_probability": False,
        "automatic_entity_merge": False,
        "scientific_document_extraction_implies_claim_truth": False,
        "automated_language_processing_implies_semantic_correctness": False,
        "human_review_state_remains_explicit": True,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "original-language-captures", "text-representations", "text-transformations",
        "ocr-htr-transcription-lineage", "linguistic-corpora", "kwic",
        "cross-language-entity-resolution", "translation-transliteration-alignment",
        "scientific-document-intelligence",
    ]
    basis = {"authority":"python-backend","resources":resources,"guardrails":guardrails()}
    return {
        "schema": CONTRACT,
        "service_id": "library-language-document:" + _fp(basis)[:32],
        "service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative",
        "authority": "python-backend",
        "database": "postgresql",
        "resources": resources,
        "wordpress": {"role":"presentation-and-api-client","required":False,"authoritative":False},
        "principles": {
            "analyze_original_language_first": True,
            "translation_is_derived": True,
            "preserve_every_transformation_and_provenance": True,
            "source_quality_is_separate_from_user_trust": True,
        },
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    components = {
        "original_language": original_language_readiness(),
        "ocr_htr_transcription": derivation_readiness(),
        "linguistic_corpus": linguistic_corpus_readiness(),
        "cross_language_resolution": cross_language_readiness(),
        "translation_alignment": alignment_readiness(),
        "scientific_document_intelligence": {"state":"ready","runtime":"python","persisted_source":"library-records-and-chunks"},
    }
    states = [str((v or {}).get("state") or "ready") for v in components.values()]
    degraded = any(x in {"schema-unavailable","unavailable","degraded"} for x in states)
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "degraded" if degraded else "ready",
        "authority": "python-backend",
        "wordpress_required": False,
        "components": components,
        "guardrails": guardrails(),
    }


def validate_capture(payload: dict[str, Any]) -> dict[str, Any]: return validate_capture_payload(payload)
def create_capture(payload: dict[str, Any]) -> dict[str, Any]: return ingest_capture(payload)
def capture(capture_id: str, include_text: bool=False) -> dict[str, Any]: return get_capture(capture_id, include_text=include_text)

def validate_derivation(payload: dict[str, Any]) -> dict[str, Any]: return validate_derivation_payload(payload)
def create_derivation(payload: dict[str, Any]) -> dict[str, Any]: return ingest_derivation(payload)
def derivation(run_id: str, include_text: bool=False) -> dict[str, Any]: return get_derivation(run_id, include_text=include_text)

def validate_corpus(payload: dict[str, Any]) -> dict[str, Any]: return validate_corpus_payload(payload)
def create_corpus(payload: dict[str, Any]) -> dict[str, Any]: return ingest_corpus(payload)
def corpus(corpus_id: str, include_tokens: bool=False) -> dict[str, Any]: return get_corpus(corpus_id, include_tokens=include_tokens)
def corpus_kwic(corpus_id: str, query: str, *, window_tokens: int=5, case_sensitive: bool=False, limit: int=100, offset: int=0) -> dict[str, Any]:
    return kwic_persisted_corpus(corpus_id, query, window_tokens=window_tokens, case_sensitive=case_sensitive, limit=limit, offset=offset)

def create_authority_registry(payload: dict[str, Any]) -> dict[str, Any]: return ingest_authority_registry(payload)
def create_resolution_case(payload: dict[str, Any]) -> dict[str, Any]:
    query = payload.get("query") if isinstance(payload.get("query"), dict) else payload
    limit = int(payload.get("limit") or 25) if isinstance(payload, dict) else 25
    return ingest_resolution_case(query, limit=max(1,min(100,limit)))
def resolution_case(case_id: str) -> dict[str, Any]: return get_resolution_case(case_id)
def resolution_decision(case_id: str, payload: dict[str, Any]) -> dict[str, Any]: return ingest_resolution_decision(case_id,payload)

def validate_alignment(payload: dict[str, Any]) -> dict[str, Any]: return validate_alignment_payload(payload)
def create_alignment(payload: dict[str, Any]) -> dict[str, Any]: return ingest_alignment_matrix(payload)
def alignment(matrix_id: str) -> dict[str, Any]: return get_alignment_matrix(matrix_id)

def scientific_document(record_id: str) -> dict[str, Any]:
    result=load_scientific_document_intelligence(record_id)
    basis={"record_id":record_id,"reproducibility":result.get("reproducibility"),"metrics":result.get("metrics")}
    return {
        "schema": DOCUMENT_INTELLIGENCE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "record_id": record_id,
        "document_intelligence": result,
        "envelope_fingerprint_sha256": _fp(basis),
        "guardrails": guardrails(),
    }
