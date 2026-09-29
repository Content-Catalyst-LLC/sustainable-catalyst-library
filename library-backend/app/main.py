from __future__ import annotations

from contextlib import asynccontextmanager, suppress
import asyncio
from datetime import datetime, timezone
import json
from time import perf_counter
from typing import Any

from fastapi import FastAPI, Header, HTTPException, Query, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import ValidationError

from . import __version__
from .db import close_pool, get_pool, initialize_database
from .models import EdgeBatch, IntegrityAuditRequest, PruneRequest, RecordBatch
from .query import explorer_bootstrap, facets, get_record, graph_neighborhood, related_records, stats, timeline
from .hybrid_retrieval import hybrid_search_records
from .representation_search import (
    representation_descriptor, representation_search_readiness, semantic_text_search, similar_records,
)
from .semantic import embedding_jobs_status, process_embedding_jobs_once, semantic_readiness, default_embedding_client
from .embedding_governance import (
    claim_workspace_handoff, complete_workspace_handoff, current_embedding_specification,
    embedding_governance_readiness, fail_workspace_handoff, prepare_workspace_handoffs,
    queue_embedding_backfill, workspace_handoff_status,
)
from .citation_graph import (
    CitationCreateRequest, CoreScholarlyCitationHandoffRequest, citation_graph, citation_readiness,
    enqueue_core_scholarly_citation, import_record_metadata_citations, list_citations, upsert_citation,
)
from .research_extraction import (
    CandidatePromotionRequest, CandidateReviewRequest, ExtractionRequest,
    enqueue_core_candidate, extract_record_candidates, extraction_readiness, list_candidates, review_candidate,
)
from .publication_visualizations import (
    VisualizationBuildRequest, VisualizationCoreHandoffRequest, VisualizationReviewRequest,
    build_publication_visualizations, enqueue_core_visualization, get_publication_visualization,
    list_publication_visualizations, review_publication_visualization, visualization_readiness,
)
from .publication_knowledge_maps import build_publication_knowledge_map, knowledge_map_readiness
from .publication_corpus_maps import build_publication_corpus_knowledge_map
from .publication_embedding_maps import (
    build_publication_embedding_map, embedding_map_readiness, semantic_neighborhood,
)
from .visual_research_sessions import build_visual_research_session_package
from .visual_evidence_trace import build_visual_evidence_trace
from .research_graph_pathfinding import find_research_paths, query_research_graph
from .native_graph_runtime import NATIVE_GRAPH_CONTRACT, NATIVE_QUERY_CONTRACT, native_graph_runtime_status
from .native_graph_query import query_native_graph
from .scientific_document_intelligence import build_scientific_document_intelligence, load_scientific_document_intelligence
from .source_identity_resolution import build_source_identity_analysis
from .retrieval_evaluation import (
    adaptive_rerank, build_adaptive_ranking_profile, evaluate_retrieval, rerank_results,
)
from .neural_reranking import (
    default_reranker_client, evaluate_reranking, rerank_candidates, reranking_readiness,
)
from .temporal_knowledge import temporal_request, knowledge_snapshot, compare_snapshots
from .methodology_intelligence import methodology_request
from .research_gap_novelty import research_gap_novelty_request
from .literature_review import literature_review_request, build_literature_review
from .living_evidence import living_evidence_request, build_living_evidence
from .ingestion_job_fabric import ingestion_fabric_status, submit_ingestion_job, list_ingestion_jobs, get_ingestion_job, cancel_ingestion_job
from .durable_job_queue import (
    JOB_CONTRACT as RESEARCH_JOB_CONTRACT, READINESS_CONTRACT as EXECUTION_FABRIC_READINESS_CONTRACT,
    build_job_package, cancel_job as cancel_research_job, complete_job as complete_research_job,
    execution_fabric_readiness, fail_job as fail_research_job, get_job as get_research_job,
    heartbeat_job as heartbeat_research_job, lease_next_job, list_jobs as list_research_jobs,
    recover_expired_leases, start_job as start_research_job, submit_job as submit_research_job,
    validate_job_payload,
)
from .specialized_worker_runtime import (
    worker_profiles, validate_worker_profile, register_worker, heartbeat_worker, get_worker, list_workers,
    quarantine_worker, release_worker, lease_for_worker, isolate_worker_failure, list_dead_letters, worker_readiness,
)
from .research_corpus_builder import build_research_corpus, export_research_corpus
from .unified_runtime_contract import (
    EXECUTION_ENVELOPE_SCHEMA,
    ROUTING_DECISION_SCHEMA,
    RUNTIME_CONTRACT,
    execute_runtime,
    resolve_runtime,
    runtime_contract_status,
)
from .execution_lineage import (
    REPRODUCIBILITY_RECORD_SCHEMA,
    RUNTIME_VERIFICATION_SCHEMA,
    create_reproducibility_record,
    execution_environment_snapshot,
    reproducibility_status,
    verify_reproducibility,
)
from .repository import delete_record, ingest_edges, ingest_records
from .security import constant_time_equal, sha256_hex, sign_request, valid_timestamp
from .settings import settings
from .operations import integrity_audit, operations_status, prune_records
from .institutional_sources import InstitutionalSourceError, build_registry
from .global_source_federation import registry as global_source_federation_registry
from .original_language_corpus import (
    CAPTURE_CONTRACT as ORIGINAL_LANGUAGE_CAPTURE_CONTRACT,
    CORPUS_CONTRACT as ORIGINAL_LANGUAGE_CORPUS_CONTRACT,
    READINESS_CONTRACT as ORIGINAL_LANGUAGE_READINESS_CONTRACT,
    build_capture_package,
    get_capture as get_original_language_capture,
    ingest_capture as ingest_original_language_capture,
    readiness as original_language_corpus_readiness,
    validate_capture_payload,
)
from .ocr_htr_transcription_lineage import (
    LINEAGE_CONTRACT as OCR_HTR_TRANSCRIPTION_LINEAGE_CONTRACT,
    READINESS_CONTRACT as OCR_HTR_TRANSCRIPTION_READINESS_CONTRACT,
    RUN_CONTRACT as TEXT_DERIVATION_RUN_CONTRACT,
    build_derivation_package,
    get_derivation as get_text_derivation_run,
    ingest_derivation as ingest_text_derivation,
    readiness as ocr_htr_transcription_readiness,
    validate_derivation_payload,
)
from .cross_language_resolution import (
    AUTHORITY_CONTRACT as CROSS_LANGUAGE_AUTHORITY_CONTRACT,
    CASE_CONTRACT as ENTITY_RESOLUTION_CASE_CONTRACT,
    DECISION_CONTRACT as ENTITY_RESOLUTION_DECISION_CONTRACT,
    READINESS_CONTRACT as CROSS_LANGUAGE_RESOLUTION_READINESS_CONTRACT,
    build_authority_package as build_cross_language_authority_package,
    build_resolution_case,
    generate_candidates as generate_entity_resolution_candidates,
    get_resolution_case,
    ingest_authority_registry,
    ingest_resolution_case,
    ingest_resolution_decision,
    readiness as cross_language_resolution_readiness,
    validate_authority_payload as validate_cross_language_authority_payload,
    validate_decision_payload,
)
from .linguistic_corpus import (
    CORPUS_CONTRACT as LINGUISTIC_CORPUS_CONTRACT,
    CONCORDANCE_CONTRACT as CONCORDANCE_QUERY_CONTRACT,
    KWIC_CONTRACT as KWIC_RESULT_CONTRACT,
    READINESS_CONTRACT as LINGUISTIC_CORPUS_READINESS_CONTRACT,
    build_corpus_package as build_linguistic_corpus_package,
    frequency_table_from_package as linguistic_frequency_table,
    get_corpus as get_linguistic_corpus,
    ingest_corpus as ingest_linguistic_corpus,
    kwic_from_package as linguistic_kwic_from_package,
    kwic_persisted_corpus,
    readiness as linguistic_corpus_readiness,
    validate_corpus_payload as validate_linguistic_corpus_payload,
)
from .biomedical_sources import BiomedicalSourceError, build_biomedical_registry
from .fda_regulatory import FDARegulatoryError, build_fda_regulatory_registry
from .medical_terminology import MedicalTerminologyError, MedicalTerminologyResolver, WHOICD11Connector
from .clinical_trials import ClinicalTrialIntelligence, ClinicalTrialIntelligenceError
from .evidence_grading import EvidenceGradingEngine
from .biomedical_evidence_graph import BiomedicalEvidenceGraphEngine
from .institutional_research_network import InstitutionalResearchNetwork
from .carbon_nature import CarbonNatureKnowledgeFoundation
from .energy_systems import EnergySystemsKnowledgeFoundation
from .energy_global import GlobalEnergyDataError
from .platform_core import (
    CoreBindingRequest,
    CoreOutboxRequest,
    bridge_readiness,
    default_client as platform_core_client,
    enqueue_operation as enqueue_core_operation,
    list_bindings as list_core_bindings,
    outbox_status as core_outbox_status,
    process_outbox_once as process_core_outbox_once,
    reconcile_binding as reconcile_core_binding,
    upsert_binding as upsert_core_binding,
)
from .private_knowledge import (
    PrivateHandoffRequest,
    PrivateKnowledgeIngestRequest,
    PrivateKnowledgeSearchRequest,
    PrivateOrganizationalKnowledge,
    PrivateRecordRequest,
)


async def _embedding_worker_loop() -> None:
    while True:
        try:
            if settings.database_url:
                if settings.embedding_compute_target in {"workspace_preferred", "workspace_only"}:
                    await asyncio.to_thread(prepare_workspace_handoffs, settings.embedding_worker_batch_size)
                if settings.embedding_worker_enabled and default_embedding_client().configured:
                    await asyncio.to_thread(process_embedding_jobs_once, settings.embedding_worker_batch_size)
        except Exception:
            # Individual job/handoff failures are persisted by the semantic queue. A worker-level
            # failure must not take down public Library search.
            pass
        await asyncio.sleep(settings.embedding_worker_interval_seconds)


@asynccontextmanager
async def lifespan(_: FastAPI):
    if settings.database_url:
        initialize_database()
    worker: asyncio.Task[Any] | None = None
    if settings.embedding_worker_enabled:
        worker = asyncio.create_task(_embedding_worker_loop())
    try:
        yield
    finally:
        if worker is not None:
            worker.cancel()
            with suppress(asyncio.CancelledError):
                await worker
        close_pool()


institutional_sources = build_registry(settings.institutional_source_timeout_seconds)
biomedical_sources = build_biomedical_registry(
    settings.biomedical_source_timeout_seconds,
    ncbi_tool=settings.ncbi_tool, ncbi_email=settings.ncbi_email, ncbi_api_key=settings.ncbi_api_key,
)
fda_regulatory_sources = build_fda_regulatory_registry(
    settings.fda_source_timeout_seconds, api_key=settings.openfda_api_key,
)
icd11_source = WHOICD11Connector(
    settings.medical_terminology_timeout_seconds,
    base_url=settings.who_icd_base_url,
    token_url=settings.who_icd_token_url,
    client_id=settings.who_icd_client_id,
    client_secret=settings.who_icd_client_secret,
    release_id=settings.who_icd_release_id,
    language=settings.who_icd_language,
    local_mode=settings.who_icd_local_mode,
)
medical_terminology = MedicalTerminologyResolver(icd11_source, biomedical_sources)
clinical_trials = ClinicalTrialIntelligence(settings.clinical_trial_timeout_seconds)
evidence_grading = EvidenceGradingEngine(biomedical_sources, clinical_trials)
biomedical_evidence_graph = BiomedicalEvidenceGraphEngine(evidence_grading, clinical_trials, medical_terminology, fda_regulatory_sources)
institutional_research_network = InstitutionalResearchNetwork(timeout_seconds=settings.institutional_source_timeout_seconds)
private_organizational_knowledge = PrivateOrganizationalKnowledge()
carbon_nature = CarbonNatureKnowledgeFoundation()
energy_systems = EnergySystemsKnowledgeFoundation()


app = FastAPI(
    title="Sustainable Catalyst Library Research Intelligence Backend",
    version=__version__,
    docs_url="/docs" if settings.enable_docs else None,
    redoc_url=None,
    openapi_url="/openapi.json" if settings.enable_docs else None,
    lifespan=lifespan,
)

if settings.allowed_origins:
    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.allowed_origins,
        allow_credentials=False,
        allow_methods=["GET", "HEAD", "OPTIONS"],
        allow_headers=["Accept", "Content-Type"],
        max_age=600,
    )


@app.middleware("http")
async def request_context(request: Request, call_next):
    started = perf_counter()
    response = await call_next(request)
    response.headers["X-SC-Library-Backend-Version"] = __version__
    response.headers["X-Content-Type-Options"] = "nosniff"
    response.headers["Referrer-Policy"] = "no-referrer"
    response.headers["X-Request-Duration-Ms"] = str(int((perf_counter() - started) * 1000))
    return response


async def authorize_write(
    request: Request,
    authorization: str | None,
    timestamp: str | None,
    signature: str | None,
) -> bytes:
    if not settings.api_key:
        raise HTTPException(status_code=503, detail="SC_LIBRARY_BACKEND_API_KEY is not configured")
    token = ""
    if authorization and authorization.lower().startswith("bearer "):
        token = authorization[7:].strip()
    if not token or not constant_time_equal(token, settings.api_key):
        raise HTTPException(status_code=401, detail="invalid server credential")
    if not timestamp or not valid_timestamp(timestamp, settings.request_skew_seconds):
        raise HTTPException(status_code=401, detail="invalid or expired request timestamp")
    body = await request.body()
    if len(body) > settings.max_body_bytes:
        raise HTTPException(
            status_code=413,
            detail="request body exceeds configured limit",
            headers={
                "X-SC-Max-Body-Bytes": str(settings.max_body_bytes),
                "X-SC-Max-Batch-Records": str(settings.max_batch_records),
            },
        )
    expected = sign_request(request.method, request.url.path, timestamp, body, settings.api_key)
    if not signature or not constant_time_equal(signature, expected):
        raise HTTPException(status_code=401, detail="invalid request signature")
    return body


def database_state() -> tuple[str, str | None]:
    if not settings.database_url:
        return "not_configured", "DATABASE_URL is not configured"
    try:
        pool = get_pool()
        with pool.connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute("SELECT current_database() AS db, current_setting('server_version') AS version")
            row = cur.fetchone()
        return "online", f"{row['db']} / PostgreSQL {row['version']}"
    except Exception as exc:
        return "unavailable", exc.__class__.__name__


@app.get("/health")
def health() -> dict[str, Any]:
    db_state, detail = database_state()
    return {
        "ok": db_state == "online",
        "service": settings.service_name,
        "version": __version__,
        "environment": settings.environment,
        "database": db_state,
        "database_detail": detail,
        "capabilities": {
            "postgresql": True,
            "weighted_full_text_search": True,
            "trigram_title_matching": True,
            "record_chunks": True,
            "provenance": True,
            "knowledge_graph": True,
            "platform_core_research_bridge": True,
            "release_certification_alignment": True,
            "platform_core_governed_promotion": True,
            "platform_core_idempotent_outbox": True,
            "platform_core_durable_bindings": True,
            "platform_core_automatic_truth_promotion": False,
            "record_timeline": True,
            "facets": True,
            "signed_ingestion": True,
            "adaptive_ingestion": True,
            "server_chunk_fallback": True,
            "operations_recovery": True,
            "dynamic_explorer": True,
            "progressive_discovery": True,
            "filterable_search": True,
            "progressive_record_detail": True,
            "integrity_audit": True,
            "targeted_pruning": True,
            "hybrid_retrieval": True,
            "hybrid_rank_fusion": "weighted-reciprocal-rank-fusion",
            "semantic_vector_store": True,
            "semantic_embeddings": "configured" if default_embedding_client().configured else "not-configured",
            "semantic_embedding_provider": settings.embedding_provider,
            "semantic_embedding_worker": settings.embedding_worker_enabled,
            "scientific_embedding_governance": True,
            "embedding_specification_provenance": True,
            "embedding_representation_lineage": True,
            "embedding_deterministic_backfill": True,
            "workspace_embedding_compute_handoff": True,
            "workspace_embedding_result_ingestion": True,
            "embedding_automatic_evidence_promotion": False,
            "embedding_automatic_truth_promotion": False,
            "embedding_automatic_core_promotion": False,
            "semantic_similarity_representation_search": True,
            "semantic_similarity_current_specification_only": True,
            "semantic_similarity_current_content_only": True,
            "semantic_record_to_record_without_provider": True,
            "semantic_similarity_automatic_evidence_promotion": False,
            "semantic_similarity_automatic_truth_promotion": False,
            "semantic_similarity_automatic_causality_inference": False,
            "global_source_federation_registry": True,
            "global_source_connector_contracts": True,
            "global_source_registry_reuses_legacy_connectors": True,
            "global_source_registry_reuses_v4_8_federation_transport": True,
            "global_source_registry_parallel_execution_stack": False,
            "global_source_registry_membership_implies_endorsement": False,
            "global_source_registry_membership_implies_partnership": False,
            "global_source_connector_health_implies_source_quality": False,
            "global_source_connector_health_implies_evidence_truth": False,
            "global_source_automatic_import": False,
            "global_source_automatic_evidence_promotion": False,
            "global_source_automatic_truth_promotion": False,
            "global_source_automatic_platform_core_promotion": False,
            "global_source_original_language_preserved_as_received": True,
            "global_source_automatic_translation": False,
            "original_language_corpus_ingestion": True,
            "original_language_raw_payload_preservation": True,
            "original_language_raw_text_preservation": True,
            "original_language_sha256_content_addressing": True,
            "original_language_script_variant_identity": True,
            "original_language_unicode_normalization_is_derived": True,
            "original_language_translation_is_derived": True,
            "original_language_normalized_text_replaces_original": False,
            "original_language_automatic_translation": False,
            "original_language_automatic_evidence_promotion": False,
            "original_language_automatic_truth_promotion": False,
            "original_language_automatic_platform_core_promotion": False,
            "ocr_htr_transcription_lineage": True,
            "ocr_htr_transcription_source_media_preservation": True,
            "ocr_htr_transcription_engine_model_provenance": True,
            "ocr_htr_transcription_segment_lineage": True,
            "ocr_htr_transcription_review_state": True,
            "ocr_htr_transcription_confidence_is_truth_probability": False,
            "ocr_htr_transcription_output_replaces_original": False,
            "ocr_htr_transcription_automatic_translation": False,
            "ocr_htr_transcription_automatic_evidence_promotion": False,
            "ocr_htr_transcription_automatic_truth_promotion": False,
            "ocr_htr_transcription_automatic_platform_core_promotion": False,
            "linguistic_corpus_objects": True,
            "linguistic_document_objects": True,
            "linguistic_token_objects": True,
            "linguistic_concordance": True,
            "linguistic_kwic": True,
            "linguistic_frequency_tables": True,
            "linguistic_representation_lineage": True,
            "linguistic_deterministic_tokenizer": True,
            "linguistic_tokenizer_is_morphological_analysis": False,
            "linguistic_kwic_context_establishes_meaning_or_intent": False,
            "linguistic_frequency_implies_importance": False,
            "linguistic_automatic_translation": False,
            "linguistic_automatic_evidence_promotion": False,
            "linguistic_automatic_truth_promotion": False,
            "linguistic_automatic_platform_core_promotion": False,
            "cross_language_entity_resolution": True,
            "cross_language_name_forms": True,
            "historical_toponym_validity_windows": True,
            "explicit_transliteration_forms": True,
            "entity_resolution_ambiguity_preserved": True,
            "entity_resolution_automatic_merge": False,
            "entity_resolution_automatic_truth_promotion": False,
            "core_aware_search_results": True,
            "citation_graph": True,
            "citation_exact_identifier_resolution": True,
            "citation_unresolved_reference_preservation": True,
            "scholarly_lineage": True,
            "platform_core_scholarly_citation_handoff": True,
            "automatic_citation_inference": False,
            "research_candidate_extraction": True,
            "entity_candidate_extraction": True,
            "finding_candidate_extraction": True,
            "claim_candidate_extraction": True,
            "candidate_source_spans": True,
            "candidate_human_review_gate": True,
            "platform_core_finding_claim_promotion": True,
            "automatic_finding_promotion": False,
            "automatic_claim_promotion": False,
            "automatic_truth_promotion_from_extraction": False,
            "publication_visualizations": True,
            "publication_visualization_renderer_neutral_specs": True,
            "publication_visualization_human_review_gate": True,
            "publication_visualization_research_library_delivery": True,
            "platform_core_visual_research_handoff": True,
            "automatic_visual_truth_promotion": False,
            "publication_knowledge_mapping": True,
            "publication_scientific_graphical_analysis": True,
            "publication_topic_relationship_mapping": True,
            "publication_semantic_similarity": "stored-embeddings-only",
            "publication_knowledge_map_workspace_portable": True,
            "publication_corpus_integration": True,
            "multi_publication_knowledge_landscape": True,
            "publication_embedding_maps": True,
            "publication_embedding_map_deterministic_pca": True,
            "publication_embedding_map_same_specification_only": True,
            "publication_embedding_map_current_content_only": True,
            "publication_semantic_neighborhoods": True,
            "publication_embedding_map_provider_required_to_render_stored_map": False,
            "publication_embedding_map_proximity_is_evidence": False,
            "publication_embedding_map_proximity_is_truth": False,
            "publication_embedding_map_proximity_is_causality": False,
            "publication_topic_regions": True,
            "publication_temporal_dynamics": True,
            "publication_relationship_matrix": True,
            "publication_four_dimensional_model_ready": True,
            "publication_four_dimensional_knowledge_terrain": True,
            "publication_terrain_elevation_metrics": True,
            "publication_terrain_time_playback": True,
            "publication_terrain_topic_anchors": True,
            "publication_linked_scientific_views": True,
            "publication_visual_query_contract": True,
            "publication_cross_view_selection": True,
            "publication_async_corpus_transport": True,
            "publication_corpus_post_transport": True,
            "publication_renderer_visibility_repair": True,
            "publication_4d_terrain_recovery": True,
            "publication_peak_preserving_terrain_surface": True,
            "publication_reproducible_visual_sessions": True,
            "publication_visual_session_export": True,
            "publication_visual_evidence_trace": True,
            "publication_source_drilldown": True,
            "publication_evidence_weighted_findings_claims": True,
            "publication_explicit_reviewed_contradiction_overlays": True,
            "publication_cross_publication_evidence_synthesis": True,
            "publication_support_connected_structures": True,
            "publication_explicit_competing_hypotheses": True,
            "publication_argument_path_visualization": True,
            "publication_research_graph_query": True,
            "publication_evidence_pathfinding": True,
            "publication_direction_aware_graph_traversal": True,
            "publication_analytical_path_edges_opt_in": True,
            "native_rust_graph_runtime_foundation": True,
            "native_graph_runtime_contract": NATIVE_GRAPH_CONTRACT,
            "native_graph_runtime_python_fallback": True,
            "native_rust_evidence_graph_acceleration": True,
            "go_research_ingestion_job_fabric": True,
            "go_ingestion_concurrency": True,
            "go_ingestion_retries": True,
            "go_ingestion_cancellation": True,
            "go_ingestion_backpressure": True,
            "go_ingestion_worker_health": True,
            "go_ingestion_job_state_implies_source_validity": False,
            "go_ingestion_job_state_implies_evidence_truth": False,
            "go_ingestion_automatic_core_promotion": False,
            "durable_research_job_queue": True,
            "durable_research_execution_state": True,
            "research_job_postgresql_authority": True,
            "research_job_redis_dispatch": True,
            "research_job_redis_authoritative": False,
            "research_job_idempotent_submission": True,
            "research_job_worker_leases": True,
            "research_job_lease_heartbeats": True,
            "research_job_retry_state": True,
            "research_job_progress_reporting": True,
            "research_job_expired_lease_recovery": True,
            "research_job_worker_fleet_active": True,
            "specialized_worker_runtime": True,
            "worker_failure_isolation": True,
            "worker_dead_letters": True,
            "research_job_completion_implies_evidence_truth": False,
            "research_job_automatic_core_promotion": False,
            "native_graph_query_engine": True,
            "native_graph_query_contract": NATIVE_QUERY_CONTRACT,
            "native_graph_filtered_neighborhoods": True,
            "native_graph_reachability": True,
            "native_graph_connected_components": True,
            "native_graph_induced_subgraphs": True,
            "native_graph_structural_statistics": True,
            "native_graph_analytical_relationships_opt_in": True,
            "scientific_document_intelligence": True,
            "scientific_document_figures_charts": True,
            "scientific_document_tables": True,
            "scientific_document_equations": True,
            "scientific_document_captions": True,
            "scientific_document_appendices_supplements": True,
            "scientific_document_datasets": True,
            "scientific_object_source_provenance": True,
            "scientific_object_graph_overlay": True,
            "scientific_visual_values_inferred_from_pixels": False,
            "source_identity_resolution": True,
            "source_identity_exact_doi_resolution": True,
            "source_identity_exact_content_hash_resolution": True,
            "source_identity_normalized_url_resolution": True,
            "source_identity_version_family_detection": True,
            "source_identity_duplicate_candidate_review": True,
            "author_orcid_resolution": True,
            "institution_ror_resolution": True,
            "dataset_doi_url_resolution": True,
            "source_identity_automatic_merge": False,
            "source_identity_title_only_merge": False,
            "retrieval_evaluation": True,
            "retrieval_precision_recall_ndcg_mrr_map": True,
            "retrieval_evidence_coverage_diagnostics": True,
            "retrieval_judged_feedback": True,
            "adaptive_ranking_profiles": True,
            "adaptive_ranking_bounded_rerank": True,
            "adaptive_ranking_automatic_filtering": False,
            "adaptive_ranking_truth_promotion": False,
            "neural_reranking": True,
            "neural_reranking_provider": settings.rerank_provider,
            "neural_reranking_configured": default_reranker_client().configured,
            "neural_reranking_baseline_rank_preserved": True,
            "neural_reranking_score_components_exposed": True,
            "neural_reranking_retrieval_evaluation": True,
            "neural_reranking_automatic_filtering": False,
            "neural_reranking_evidence_promotion": False,
            "neural_reranking_truth_promotion": False,
            "neural_reranking_score_is_probability": False,
            "temporal_knowledge_evolution": True,
            "temporal_historical_availability_snapshots": True,
            "temporal_retrospective_status_lens": True,
            "temporal_correction_retraction_tracking": True,
            "temporal_research_change_sets": True,
            "temporal_later_events_projected_backward_by_default": False,
            "temporal_coincidence_implies_causality": False,
            "methodology_intelligence": True,
            "methodology_study_design_structuring": True,
            "methodology_population_sample_methods_uncertainty": True,
            "methodology_transparency_reproducibility_signals": True,
            "methodology_reporting_coverage": True,
            "methodology_automatic_quality_score": False,
            "methodology_automatic_risk_of_bias_judgment": False,
            "methodology_profile_truth_promotion": False,
            "research_gap_novelty_discovery": True,
            "research_gap_candidate_objects": True,
            "research_gap_external_verification_required": True,
            "research_novelty_candidate_objects": True,
            "research_novelty_automatic_claim": False,
            "research_gap_global_absence_claim": False,
            "reproducible_literature_review_engine": True,
            "literature_review_protocol_fingerprints": True,
            "literature_review_human_screening_decisions": True,
            "literature_review_extraction_lineage": True,
            "literature_review_reproducible_snapshots": True,
            "literature_review_review_state_comparison": True,
            "literature_review_automatic_screening": False,
            "literature_review_automatic_inclusion_exclusion": False,
            "literature_review_automatic_meta_analysis": False,
            "living_evidence_research_evolution": True,
            "living_evidence_review_update_candidates": True,
            "living_evidence_snapshot_comparison": True,
            "living_evidence_explicit_change_events": True,
            "living_evidence_surveillance_plan": True,
            "living_evidence_automatic_search": False,
            "living_evidence_automatic_review_state_change": False,
            "living_evidence_newer_evidence_truth_promotion": False,
            "research_corpus_builder": True,
            "research_corpus_deterministic_manifests": True,
            "research_corpus_row_level_provenance": True,
            "research_corpus_json_export": True,
            "research_corpus_jsonl_export": True,
            "research_corpus_csv_export": True,
            "research_corpus_automatic_quality_judgment": False,
            "research_corpus_automatic_truth_promotion": False,
            "research_corpus_automatic_core_promotion": False,
            "unified_research_runtime_contract": True,
            "unified_runtime_discovery": True,
            "unified_runtime_routing": True,
            "unified_runtime_execution_envelopes": True,
            "unified_runtime_explicit_fallback_policy": True,
            "unified_runtime_python_semantics_authority": True,
            "unified_runtime_cross_runtime_equivalence_claim": False,
            "unified_runtime_automatic_core_promotion": False,
            "cross_runtime_reproducibility_execution_lineage": True,
            "execution_environment_capture": True,
            "execution_input_output_fingerprints": True,
            "execution_parent_child_lineage": True,
            "execution_replay_verification": True,
            "cross_runtime_observed_output_comparison": True,
            "cross_runtime_equivalence_claim": False,
            "execution_lineage_automatic_core_promotion": False,
            "publication_workspace_visual_handoff_package": True,
            "publication_visual_query_portable_state": True,
            "publication_corpus_default_source": "wordpress-main",
            "publication_corpus_live_library_records": True,
            "publication_corpus_canonical_manifest": True,
            "publication_corpus_nonpublication_types_excluded": True,
            "institutional_sources": True,
            "johns_hopkins_dataverse": True,
            "license_reuse_normalization": True,
            "biomedical_evidence": True,
            "pubmed": True,
            "pubmed_central": True,
            "clinicaltrials_gov": True,
            "mesh_2026": True,
            "rxnorm": True,
            "fda_regulatory_intelligence": True,
            "drugs_at_fda": True,
            "fda_drug_labeling": True,
            "fda_ndc_directory": True,
            "faers_adverse_events": True,
            "fda_drug_recalls": True,
            "fda_drug_shortages": True,
            "fda_orange_book": True,
            "medical_terminology": True,
            "icd11_2026": True,
            "mesh_rxnorm_crosswalk": True,
            "semantic_equivalence_guardrail": True,
            "clinical_trial_intelligence": True,
            "clinical_trial_structured_search": True,
            "clinical_trial_comparison": True,
            "clinical_trial_results_state": True,
            "trial_publication_linkage": True,
            "trial_retraction_signals": True,
            "biomedical_evidence_grading": True,
            "study_design_intelligence": True,
            "evidence_body_mapping": True,
            "certainty_domain_readiness": True,
            "automated_formal_grade": False,
            "biomedical_evidence_graph": True,
            "evidence_synthesis": True,
            "trial_publication_graph_linkage": True,
            "regulatory_evidence_graph_context": True,
            "terminology_candidate_graph_context": True,
            "automated_pooled_effect": False,
            "evidence_graph_reliability": True,
            "graph_provenance_ledger": True,
            "graph_content_fingerprint": True,
            "graph_partial_failure_containment": True,
            "institutional_research_network_ii": True,
            "institutional_cross_repository_search": True,
            "institutional_exact_doi_deduplication": True,
            "institutional_provenance_ledger": True,
            "institutional_source_failure_containment": True,
            "institutional_graph_fingerprint": True,
            "private_organizational_knowledge": True,
            "private_organization_scoping": True,
            "private_access_scope_enforcement": True,
            "private_version_lineage": True,
            "private_audit_events": True,
            "private_cross_product_handoffs": True,
            "private_public_search_separation": True,
            "carbon_nature_intelligence": True,
            "carbon_nature_domain_version": "0.5.0",
            "carbon_sequestration_measure_registry": True,
            "carbon_measure_comparison_packets": True,
            "carbon_measure_research_context": True,
            "carbon_evidence_registry": True,
            "carbon_methodology_registry": True,
            "carbon_evidence_methodology_graph": True,
            "carbon_evidence_graph_neighborhoods": True,
            "carbon_evidence_aware_research_context": True,
            "carbon_project_object_model": True,
            "carbon_project_provenance_model": True,
            "carbon_project_packet_template": True,
            "carbon_project_packet_validation": True,
            "carbon_project_packet_persistence": False,
            "automatic_carbon_project_claim_generation": False,
            "automatic_carbon_project_eligibility_determination": False,
            "automatic_carbon_methodology_selection": False,
            "automatic_carbon_claim_validation": False,
            "afolu_knowledge_foundation": True,
            "nature_based_solutions_knowledge_foundation": True,
            "carbon_nature_relationship_registry": True,
            "carbon_nature_research_context_packets": True,
            "afolu_research_librarian_intelligence": True,
            "afolu_research_intent_classification": True,
            "afolu_research_source_planning": True,
            "afolu_evidence_gap_diagnostics": True,
            "afolu_policy_market_freshness_flags": True,
            "automatic_afolu_research_conclusion_generation": False,
            "energy_systems_intelligence": True,
            "energy_systems_domain_version": "1.6.0",
            "sustainable_energy_knowledge_foundation": True,
            "energy_concept_registry": True,
            "energy_relationship_registry": True,
            "energy_source_provenance_registry": True,
            "energy_knowledge_map": True,
            "energy_platform_handoffs": True,
            "energy_numeric_conversion_registry": True,
            "energy_unit_registry": True,
            "energy_carbon_factor_registry": True,
            "energy_heat_content_registry": True,
            "energy_source_bound_conversion_calculator": True,
            "energy_source_bound_carbon_calculator": True,
            "energy_source_bound_heat_content_calculator": True,
            "energy_current_factor_defaults": False,
            "energy_workbench_execution": True,
            "energy_modeling_uncertainty": True,
            "energy_modeling_uncertainty_lab_minimum_version": "0.102.0",
            "energy_modeling_uncertainty_seeded_designs": True,
            "energy_modeling_uncertainty_automatic_workbench_execution": False,
            "energy_spatial_global_intelligence": True,
            "energy_spatial_global_site_intelligence_minimum_version": "4.41.0",
            "energy_spatial_global_site_suitability_scoring": False,
            "energy_spatial_global_automatic_external_fetch": False,
            "energy_grid_storage_reliability": True,
            "energy_grid_storage_workbench_minimum_version": "6.3.0",
            "energy_grid_storage_lab_minimum_version": "0.103.0",
            "energy_grid_storage_real_grid_reliability_declaration": False,
            "energy_grid_storage_missing_parameter_inference": False,
            "energy_indicator_framework": True,
            "energy_indicator_definition_registry": True,
            "energy_indicator_observation_contracts": True,
            "energy_indicator_official_methodology_loaded": False,
            "energy_indicator_calculation": False,
            "energy_indicator_engine": False,
            "energy_renewable_technology_registry": True,
            "energy_renewable_resource_class_registry": True,
            "energy_renewable_technology_assessment_contracts": True,
            "energy_renewable_resource_observation_contracts": True,
            "energy_quantitative_technology_profiles_loaded": False,
            "energy_live_resource_potential_datasets_loaded": False,
            "energy_renewable_suitability_assessment": False,
            "energy_scenario_modeling": True,
            "energy_balance_framework": True,
            "energy_conversion_chain_model": True,
            "energy_supply_demand_balance_model": True,
            "energy_capacity_factor_generation_estimate": True,
            "energy_balance_scenario_contracts": True,
            "energy_time_series_dispatch_simulation": False,
            "energy_grid_reliability_or_adequacy_model": False,
            "energy_storage_physics_simulation": False,
            "energy_economic_optimization": False,
            "energy_scenario_persistence": False,
            "energy_scenario_economics": True,
            "energy_cost_comparison": True,
            "energy_simple_payback": True,
            "energy_net_present_value": True,
            "energy_cost_benefit_analysis": True,
            "energy_cost_efficiency_analysis": True,
            "energy_levelized_cost_estimate": True,
            "energy_economic_scenario_contracts": True,
            "energy_external_price_feed": False,
            "energy_technology_cost_database": False,
            "energy_discount_rate_inference": False,
            "energy_investment_recommendation": False,
            "energy_biological_carbon_bioenergy_integration": True,
            "energy_bioenergy_feedstock_registry": True,
            "energy_bioenergy_pathway_registry": True,
            "energy_carbon_nature_bridge_registry": True,
            "energy_bioenergy_explicit_input_calculators": True,
            "energy_bioenergy_carbon_scenario_contract": True,
            "energy_biomass_carbon_neutrality_assumed": False,
            "energy_bioenergy_lifecycle_emissions_inferred": False,
            "energy_bioenergy_avoided_emissions_inferred": False,
            "energy_carbon_credit_eligibility_determined": False,
            "energy_global_energy_intelligence": True,
            "energy_global_energy_metric_registry": True,
            "energy_global_energy_live_world_bank": True,
            "energy_global_energy_country_profiles": True,
            "energy_global_energy_country_comparison": True,
            "energy_global_energy_embedded_current_values": False,
            "energy_global_energy_latest_observation_is_current_assumed": False,
            "energy_global_energy_cross_source_harmonization_assumed": False,
            "energy_decision_intelligence": True,
            "energy_decision_criterion_registry": True,
            "energy_decision_packet_contract": True,
            "energy_decision_comparison_matrix": True,
            "energy_decision_readiness_inspection": True,
            "energy_decision_automatic_normalization": False,
            "energy_decision_automatic_weight_assignment": False,
            "energy_decision_composite_score": False,
            "energy_decision_automatic_ranking": False,
            "energy_decision_winner_selection": False,
            "energy_decision_studio_execution": False,
            "energy_integrated_platform": True,
            "energy_integrated_platform_release_layers": 9,
            "energy_cross_product_contract_registry": True,
            "energy_integrated_study_contract": True,
            "energy_platform_structural_certification": True,
            "energy_platform_scientific_validation": False,
            "energy_platform_live_deployment_audit": False,
            "energy_cross_product_execution_claimed": True,
            "energy_workbench_explicit_execution_certified": True,
            "energy_workbench_minimum_runtime_version": "6.2.0",
            "energy_workbench_automatic_execution": False,
            "energy_cross_product_runtime_activation_gateway": True,
            "energy_cross_product_runtime_targets": 5,
            "energy_cross_product_handoff_packet_builders": 5,
            "energy_cross_product_pull_transport": True,
            "energy_cross_product_target_consumption_certified": True,
            "energy_cross_product_outbound_push_delivery": False,
            "energy_cross_product_persistence": False,
            "energy_site_intelligence_execution": False,
            "automatic_energy_technology_ranking": False,
            "automatic_energy_policy_recommendation": False,
            "automated_clinical_recommendation": False,
        },
        "ingest_limits": {
            "max_batch_records": settings.max_batch_records,
            "max_body_bytes": settings.max_body_bytes,
            "max_body_mb": settings.max_body_bytes // (1024 * 1024),
        },
        "time": datetime.now(timezone.utc).isoformat(),
    }


@app.get("/v1/platform-core/readiness")
def platform_core_readiness() -> dict[str, Any]:
    return bridge_readiness()


@app.get("/v1/platform-core/capabilities")
def platform_core_capabilities() -> dict[str, Any]:
    return platform_core_client().capabilities()


@app.post("/v1/platform-core/bindings")
async def platform_core_binding_upsert(
    payload: CoreBindingRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return upsert_core_binding(payload)


@app.get("/v1/platform-core/bindings/{library_record_id:path}")
async def platform_core_binding_list(
    library_record_id: str,
    request: Request,
    limit: int = Query(default=100, ge=1, le=500),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return list_core_bindings(library_record_id, limit=limit)


@app.post("/v1/platform-core/outbox")
async def platform_core_outbox_enqueue(
    payload: CoreOutboxRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return enqueue_core_operation(payload)


@app.get("/v1/platform-core/outbox")
async def platform_core_outbox_read(
    request: Request,
    limit: int = Query(default=100, ge=1, le=500),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return core_outbox_status(limit=limit)


@app.post("/v1/platform-core/outbox/run-once")
async def platform_core_outbox_run_once(
    request: Request,
    limit: int = Query(default=25, ge=1, le=100),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return process_core_outbox_once(limit=limit)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.post("/v1/platform-core/reconcile/{library_record_id:path}")
async def platform_core_reconcile(
    library_record_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return reconcile_core_binding(library_record_id)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.get("/ready")
def ready() -> JSONResponse:
    db_state, detail = database_state()
    if db_state != "online":
        return JSONResponse(status_code=503, content={"ok": False, "database": db_state, "detail": detail})
    try:
        payload = stats()
    except Exception as exc:
        return JSONResponse(status_code=503, content={"ok": False, "database": "online", "detail": exc.__class__.__name__})
    return JSONResponse(content={"ok": True, "database": "online", "version": __version__, **payload})


@app.post("/v1/ingest/records")
async def ingest_records_route(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        batch = RecordBatch.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    if len(batch.records) > settings.max_batch_records:
        raise HTTPException(
            status_code=413,
            detail="record batch exceeds configured maximum",
            headers={
                "X-SC-Max-Body-Bytes": str(settings.max_body_bytes),
                "X-SC-Max-Batch-Records": str(settings.max_batch_records),
            },
        )
    if any(record.source_key != batch.source.source_key for record in batch.records):
        raise HTTPException(status_code=400, detail="record source_key does not match batch source")
    return ingest_records(batch, sha256_hex(body))


@app.post("/v1/ingest/edges")
async def ingest_edges_route(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        batch = EdgeBatch.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    return ingest_edges(batch)


@app.delete("/v1/records/{record_id}")
async def delete_record_route(
    record_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return {"ok": True, "record_id": record_id, "deleted": delete_record(record_id)}


@app.get("/v1/biomedical-sources")
def list_biomedical_sources() -> dict[str, Any]:
    return {"schema": "sc-biomedical-sources/1.0", "sources": biomedical_sources.list_sources()}


@app.get("/v1/biomedical-sources/{source_key}/search")
def biomedical_source_search(
    source_key: str,
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=10, ge=1, le=50),
    cursor: str = Query(default="", max_length=500),
) -> dict[str, Any]:
    try:
        return biomedical_sources.get(source_key).search(q, limit=limit, cursor=cursor)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="biomedical source not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except BiomedicalSourceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/biomedical/search")
def biomedical_unified_search(
    q: str = Query(..., min_length=1, max_length=500),
    sources: str = Query(default="", max_length=200),
    limit: int = Query(default=5, ge=1, le=20),
) -> dict[str, Any]:
    requested = [item.strip() for item in sources.split(",") if item.strip()] or None
    try:
        return biomedical_sources.unified_search(q, limit=limit, source_keys=requested)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/fda-sources")
def list_fda_sources() -> dict[str, Any]:
    return {"schema": "sc-fda-sources/1.0", "sources": fda_regulatory_sources.list_sources()}


@app.get("/v1/fda-sources/{source_key}/search")
def fda_source_search(
    source_key: str,
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=10, ge=1, le=20),
    cursor: str = Query(default="", max_length=20),
) -> dict[str, Any]:
    try:
        return fda_regulatory_sources.get(source_key).search(q, limit=limit, cursor=cursor)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="FDA regulatory source not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except FDARegulatoryError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/fda/search")
def fda_unified_search(
    q: str = Query(..., min_length=1, max_length=500),
    sources: str = Query(default="", max_length=300),
    limit: int = Query(default=4, ge=1, le=10),
) -> dict[str, Any]:
    requested = [item.strip() for item in sources.split(",") if item.strip()] or None
    try:
        return fda_regulatory_sources.unified_search(q, limit=limit, source_keys=requested)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/biomedical/intelligence/search")
def biomedical_intelligence_search(
    q: str = Query(..., min_length=1, max_length=500),
    biomedical_limit: int = Query(default=3, ge=1, le=10),
    regulatory_limit: int = Query(default=3, ge=1, le=10),
) -> dict[str, Any]:
    return {
        "schema": "sc-biomedical-intelligence/1.0",
        "query": q,
        "biomedical": biomedical_sources.unified_search(q, limit=biomedical_limit),
        "regulatory": fda_regulatory_sources.unified_search(q, limit=regulatory_limit),
        "governance": {
            "research_only": True,
            "clinical_decision_support": False,
            "evidence_classes_preserved": True,
            "notice": "Biomedical literature and FDA regulatory records are returned as separate evidence families and must not be treated as equivalent evidence."
        },
        "time": datetime.now(timezone.utc).isoformat(),
    }


@app.get("/v1/medical-terminology")
def medical_terminology_manifest() -> dict[str, Any]:
    return {
        "schema": "sc-medical-terminology-sources/1.0",
        "sources": medical_terminology.source_manifest(),
        "icd11": {
            "configured": icd11_source.configured(),
            "release_id": settings.who_icd_release_id,
            "language": settings.who_icd_language,
            "local_mode": settings.who_icd_local_mode,
        },
        "governance": {
            "research_only": True,
            "clinical_decision_support": False,
            "semantic_equivalence_asserted": False,
        },
    }


@app.get("/v1/medical-terminology/icd11/search")
def icd11_search(
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=10, ge=1, le=25),
) -> dict[str, Any]:
    try:
        return icd11_source.search(q, limit=limit)
    except MedicalTerminologyError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/medical-terminology/resolve")
def medical_terminology_resolve(
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=5, ge=1, le=10),
) -> dict[str, Any]:
    return medical_terminology.resolve(q, limit=limit)


@app.get("/v1/clinical-trials")
def clinical_trial_manifest() -> dict[str, Any]:
    return clinical_trials.manifest()


@app.get("/v1/clinical-trials/search")
def clinical_trial_search(
    q: str = Query(default="", max_length=500),
    condition: str = Query(default="", max_length=300),
    intervention: str = Query(default="", max_length=300),
    sponsor: str = Query(default="", max_length=300),
    location: str = Query(default="", max_length=300),
    status: str = Query(default="", max_length=200),
    phase: str = Query(default="", max_length=40),
    study_type: str = Query(default="", max_length=40),
    limit: int = Query(default=10, ge=1, le=50),
    cursor: str = Query(default="", max_length=500),
) -> dict[str, Any]:
    try:
        return clinical_trials.search(
            query=q, condition=condition, intervention=intervention, sponsor=sponsor,
            location=location, status=status, phase=phase, study_type=study_type,
            limit=limit, cursor=cursor,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except ClinicalTrialIntelligenceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/clinical-trials/compare")
def clinical_trial_compare(
    nct_ids: str = Query(..., min_length=1, max_length=200),
) -> dict[str, Any]:
    ids = [item.strip() for item in nct_ids.split(",") if item.strip()]
    try:
        return clinical_trials.compare(ids)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="clinical trial not found") from exc
    except ClinicalTrialIntelligenceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/clinical-trials/{nct_id}")
def clinical_trial_detail(nct_id: str) -> dict[str, Any]:
    try:
        return clinical_trials.get_study(nct_id)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="clinical trial not found") from exc
    except ClinicalTrialIntelligenceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/evidence-grading")
def evidence_grading_manifest() -> dict[str, Any]:
    return evidence_grading.manifest()


@app.get("/v1/evidence-grading/search")
def evidence_grading_search(
    q: str = Query(..., min_length=1, max_length=500),
    literature_limit: int = Query(default=8, ge=1, le=20),
    trial_limit: int = Query(default=8, ge=1, le=20),
) -> dict[str, Any]:
    try:
        return evidence_grading.search_body(q, literature_limit=literature_limit, trial_limit=trial_limit)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/evidence-grading/trial/{nct_id}")
def evidence_grading_trial(nct_id: str) -> dict[str, Any]:
    try:
        return evidence_grading.trial_profile(nct_id)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="clinical trial not found") from exc
    except ClinicalTrialIntelligenceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/biomedical-evidence-graph")
def biomedical_evidence_graph_manifest() -> dict[str, Any]:
    return biomedical_evidence_graph.manifest()


@app.get("/v1/biomedical-evidence-graph/build")
def biomedical_evidence_graph_build(
    q: str = Query(..., min_length=1, max_length=500),
    literature_limit: int = Query(default=8, ge=1, le=20),
    trial_limit: int = Query(default=8, ge=1, le=20),
    concept_limit: int = Query(default=3, ge=1, le=5),
    regulatory_limit: int = Query(default=2, ge=1, le=5),
) -> dict[str, Any]:
    try:
        return biomedical_evidence_graph.build_graph(
            q, literature_limit=literature_limit, trial_limit=trial_limit,
            concept_limit=concept_limit, regulatory_limit=regulatory_limit,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/biomedical-evidence-graph/synthesis")
def biomedical_evidence_graph_synthesis(
    q: str = Query(..., min_length=1, max_length=500),
    literature_limit: int = Query(default=8, ge=1, le=20),
    trial_limit: int = Query(default=8, ge=1, le=20),
    concept_limit: int = Query(default=3, ge=1, le=5),
    regulatory_limit: int = Query(default=2, ge=1, le=5),
) -> dict[str, Any]:
    try:
        return biomedical_evidence_graph.synthesis(
            q, literature_limit=literature_limit, trial_limit=trial_limit,
            concept_limit=concept_limit, regulatory_limit=regulatory_limit,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/biomedical-evidence-graph/reproducibility")
def biomedical_evidence_graph_reproducibility(
    q: str = Query(..., min_length=1, max_length=500),
    literature_limit: int = Query(default=8, ge=1, le=20),
    trial_limit: int = Query(default=8, ge=1, le=20),
    concept_limit: int = Query(default=3, ge=1, le=5),
    regulatory_limit: int = Query(default=2, ge=1, le=5),
) -> dict[str, Any]:
    try:
        return biomedical_evidence_graph.reproducibility_capsule(
            q, literature_limit=literature_limit, trial_limit=trial_limit,
            concept_limit=concept_limit, regulatory_limit=regulatory_limit,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/biomedical-evidence-graph/trial/{nct_id}")
def biomedical_evidence_graph_trial(nct_id: str) -> dict[str, Any]:
    try:
        return biomedical_evidence_graph.trial_neighborhood(nct_id)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="clinical trial not found") from exc
    except ClinicalTrialIntelligenceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/private-organizational-knowledge")
def private_organizational_knowledge_manifest() -> dict[str, Any]:
    return private_organizational_knowledge.manifest()


@app.post("/v1/private-organizational-knowledge/ingest")
async def private_organizational_knowledge_ingest(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        packet = PrivateKnowledgeIngestRequest.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    if len(packet.records) > settings.max_batch_records:
        raise HTTPException(status_code=413, detail="private record batch exceeds configured maximum")
    return private_organizational_knowledge.ingest(packet, sha256_hex(body))


@app.post("/v1/private-organizational-knowledge/search")
async def private_organizational_knowledge_search(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        packet = PrivateKnowledgeSearchRequest.model_validate_json(body)
        return private_organizational_knowledge.search(packet)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc


@app.post("/v1/private-organizational-knowledge/record")
async def private_organizational_knowledge_record(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        packet = PrivateRecordRequest.model_validate_json(body)
        return private_organizational_knowledge.get_record(packet)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="private record not found or not authorized") from exc


@app.post("/v1/private-organizational-knowledge/versions")
async def private_organizational_knowledge_versions(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        packet = PrivateRecordRequest.model_validate_json(body)
        return private_organizational_knowledge.versions(packet)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="private record not found or not authorized") from exc


@app.post("/v1/private-organizational-knowledge/handoff")
async def private_organizational_knowledge_handoff(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        packet = PrivateHandoffRequest.model_validate_json(body)
        return private_organizational_knowledge.handoff(packet)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="private record not found or not authorized") from exc


@app.get("/v1/energy-systems")
def energy_systems_manifest() -> dict[str, Any]:
    return energy_systems.manifest()


@app.get("/v1/energy-systems/platform-framework")
def energy_systems_platform_framework() -> dict[str, Any]:
    return energy_systems.platform_framework()


@app.get("/v1/energy-systems/platform-contracts")
def energy_systems_platform_contracts() -> dict[str, Any]:
    return energy_systems.platform_contracts()


@app.get("/v1/energy-systems/platform-study-template")
def energy_systems_platform_study_template() -> dict[str, Any]:
    return energy_systems.platform_study_template()


@app.get("/v1/energy-systems/platform-certification")
def energy_systems_platform_certification() -> dict[str, Any]:
    return energy_systems.platform_certification()


@app.get("/v1/energy-systems/runtime-framework")
def energy_systems_runtime_framework() -> dict[str, Any]:
    return energy_systems.runtime_framework()


@app.get("/v1/energy-systems/modeling-uncertainty-framework")
def energy_systems_modeling_uncertainty_framework() -> dict[str, Any]:
    return energy_systems.modeling_uncertainty_framework()


@app.get("/v1/energy-systems/uncertainty-study-template")
def energy_systems_uncertainty_study_template() -> dict[str, Any]:
    return energy_systems.uncertainty_study_template()


@app.get("/v1/energy-systems/spatial-global-framework")
def energy_systems_spatial_global_framework() -> dict[str, Any]:
    return energy_systems.spatial_global_framework()


@app.get("/v1/energy-systems/spatial-profile-template")
def energy_systems_spatial_profile_template() -> dict[str, Any]:
    return energy_systems.spatial_profile_template()


@app.get("/v1/energy-systems/grid-storage-reliability-framework")
def energy_systems_grid_storage_reliability_framework() -> dict[str, Any]:
    return energy_systems.grid_storage_reliability_framework()


@app.get("/v1/energy-systems/storage-scenario-template")
def energy_systems_storage_scenario_template() -> dict[str, Any]:
    return energy_systems.storage_scenario_template()


@app.get("/v1/energy-systems/reliability-scenario-template")
def energy_systems_reliability_scenario_template() -> dict[str, Any]:
    return energy_systems.reliability_scenario_template()




@app.get("/v1/energy-systems/runtime-consumers")
def energy_systems_runtime_consumers() -> dict[str, Any]:
    return energy_systems.runtime_consumers()


@app.get("/v1/energy-systems/runtime-targets")
def energy_systems_runtime_targets() -> dict[str, Any]:
    return energy_systems.runtime_targets()


@app.get("/v1/energy-systems/runtime-targets/{target_key}")
def energy_systems_runtime_target(target_key: str) -> dict[str, Any]:
    try:
        return energy_systems.runtime_target(target_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy runtime target not found") from exc


@app.get("/v1/energy-systems/runtime-handoff-template/{target_key}")
def energy_systems_runtime_handoff_template(target_key: str) -> dict[str, Any]:
    try:
        return energy_systems.runtime_handoff_template(target_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy runtime target not found") from exc


@app.get("/v1/energy-systems/runtime-handoff/{target_key}")
def energy_systems_runtime_handoff(
    target_key: str,
    study: str = Query(..., min_length=2, max_length=12000),
) -> dict[str, Any]:
    try:
        return energy_systems.runtime_handoff(target_key=target_key, study_json=study)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy runtime target not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/runtime-readiness/{target_key}")
def energy_systems_runtime_readiness(
    target_key: str,
    study: str = Query(..., min_length=2, max_length=12000),
) -> dict[str, Any]:
    try:
        return energy_systems.runtime_readiness(target_key=target_key, study_json=study)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy runtime target not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/concepts")
def energy_systems_concepts(
    q: str = Query(default="", max_length=500),
    concept_type: str = Query(default="", max_length=100),
    domain: str = Query(default="", max_length=100),
    source: str = Query(default="", max_length=160),
    limit: int = Query(default=100, ge=1, le=250),
) -> dict[str, Any]:
    return energy_systems.concepts(q=q, concept_type=concept_type, domain=domain, source=source, limit=limit)


@app.get("/v1/energy-systems/concepts/{concept_key}")
def energy_systems_concept(concept_key: str) -> dict[str, Any]:
    try:
        return energy_systems.concept(concept_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy concept not found") from exc


@app.get("/v1/energy-systems/relationships")
def energy_systems_relationships(
    subject: str = Query(default="", max_length=160),
    predicate: str = Query(default="", max_length=160),
    object_key: str = Query(default="", alias="object", max_length=160),
    limit: int = Query(default=250, ge=1, le=500),
) -> dict[str, Any]:
    return energy_systems.relationships(subject=subject, predicate=predicate, object_key=object_key, limit=limit)


@app.get("/v1/energy-systems/sources")
def energy_systems_sources() -> dict[str, Any]:
    return energy_systems.sources()


@app.get("/v1/energy-systems/sources/{source_key}")
def energy_systems_source(source_key: str) -> dict[str, Any]:
    try:
        return energy_systems.source(source_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy source not found") from exc


@app.get("/v1/energy-systems/knowledge-map")
def energy_systems_knowledge_map() -> dict[str, Any]:
    return energy_systems.knowledge_map()


@app.get("/v1/energy-systems/handoffs")
def energy_systems_handoffs() -> dict[str, Any]:
    return energy_systems.handoffs()


@app.get("/v1/energy-systems/registry")
def energy_systems_registry() -> dict[str, Any]:
    return energy_systems.registry()


@app.get("/v1/energy-systems/units")
def energy_systems_units() -> dict[str, Any]:
    return energy_systems.units()


@app.get("/v1/energy-systems/conversion-factors")
def energy_systems_conversion_factors() -> dict[str, Any]:
    return energy_systems.conversion_factors()


@app.get("/v1/energy-systems/carbon-factors")
def energy_systems_carbon_factors(
    q: str = Query(default="", max_length=500),
    fuel: str = Query(default="", max_length=120),
    unit: str = Query(default="", max_length=80),
    limit: int = Query(default=100, ge=1, le=250),
) -> dict[str, Any]:
    return energy_systems.carbon_factors(q=q, fuel=fuel, unit=unit, limit=limit)


@app.get("/v1/energy-systems/heat-content-factors")
def energy_systems_heat_content_factors(
    q: str = Query(default="", max_length=500),
    fuel: str = Query(default="", max_length=120),
    unit: str = Query(default="", max_length=80),
    limit: int = Query(default=100, ge=1, le=250),
) -> dict[str, Any]:
    return energy_systems.heat_content_factors(q=q, fuel=fuel, unit=unit, limit=limit)


@app.get("/v1/energy-systems/methodology-rules")
def energy_systems_methodology_rules() -> dict[str, Any]:
    return energy_systems.methodology_rules()


@app.get("/v1/energy-systems/indicator-framework")
def energy_systems_indicator_framework() -> dict[str, Any]:
    return energy_systems.indicator_framework()


@app.get("/v1/energy-systems/indicators")
def energy_systems_indicators(
    q: str = Query(default="", max_length=500),
    dimension: str = Query(default="", max_length=80),
    theme: str = Query(default="", max_length=120),
    subtheme: str = Query(default="", max_length=160),
    limit: int = Query(default=100, ge=1, le=100),
) -> dict[str, Any]:
    return energy_systems.indicators(q=q, dimension=dimension, theme=theme, subtheme=subtheme, limit=limit)


@app.get("/v1/energy-systems/indicators/{indicator_code}")
def energy_systems_indicator(indicator_code: str) -> dict[str, Any]:
    try:
        return energy_systems.indicator(indicator_code)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy sustainability indicator not found") from exc


@app.get("/v1/energy-systems/indicator-observation-template/{indicator_code}")
def energy_systems_indicator_observation_template(indicator_code: str) -> dict[str, Any]:
    try:
        return energy_systems.indicator_observation_template(indicator_code)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy sustainability indicator not found") from exc


@app.get("/v1/energy-systems/technology-framework")
def energy_systems_technology_framework() -> dict[str, Any]:
    return energy_systems.technology_framework()


@app.get("/v1/energy-systems/technologies")
def energy_systems_technologies(
    q: str = Query(default="", max_length=500),
    family: str = Query(default="", max_length=100),
    output: str = Query(default="", max_length=100),
    resource_class: str = Query(default="", max_length=160),
    limit: int = Query(default=100, ge=1, le=100),
) -> dict[str, Any]:
    return energy_systems.technologies(q=q, family=family, output=output, resource_class=resource_class, limit=limit)


@app.get("/v1/energy-systems/technologies/{technology_key}")
def energy_systems_technology(technology_key: str) -> dict[str, Any]:
    try:
        return energy_systems.technology(technology_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Renewable technology not found") from exc


@app.get("/v1/energy-systems/resource-classes")
def energy_systems_resource_classes() -> dict[str, Any]:
    return energy_systems.resource_classes()


@app.get("/v1/energy-systems/resource-classes/{resource_key}")
def energy_systems_resource_class(resource_key: str) -> dict[str, Any]:
    try:
        return energy_systems.resource_class(resource_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Renewable resource class not found") from exc


@app.get("/v1/energy-systems/technology-assessment-template/{technology_key}")
def energy_systems_technology_assessment_template(technology_key: str) -> dict[str, Any]:
    try:
        return energy_systems.technology_assessment_template(technology_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Renewable technology not found") from exc


@app.get("/v1/energy-systems/resource-observation-template/{resource_key}")
def energy_systems_resource_observation_template(resource_key: str) -> dict[str, Any]:
    try:
        return energy_systems.resource_observation_template(resource_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Renewable resource class not found") from exc


@app.get("/v1/energy-systems/technology-comparison-template")
def energy_systems_technology_comparison_template() -> dict[str, Any]:
    return energy_systems.technology_comparison_template()


@app.get("/v1/energy-systems/convert")
def energy_systems_convert(
    value: str = Query(..., min_length=1, max_length=80),
    from_unit: str = Query(..., alias="from", min_length=1, max_length=80),
    to_unit: str = Query(..., alias="to", min_length=1, max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.convert_energy(value=value, from_unit=from_unit, to_unit=to_unit)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy conversion unit not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/carbon-estimate")
def energy_systems_carbon_estimate(
    factor_key: str = Query(..., min_length=1, max_length=160),
    quantity: str = Query(..., min_length=1, max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.estimate_carbon(factor_key=factor_key, quantity=quantity)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy carbon factor not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/heat-content-estimate")
def energy_systems_heat_content_estimate(
    factor_key: str = Query(..., min_length=1, max_length=160),
    quantity: str = Query(..., min_length=1, max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.estimate_heat_content(factor_key=factor_key, quantity=quantity)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Energy heat-content factor not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/balance-framework")
def energy_systems_balance_framework() -> dict[str, Any]:
    return energy_systems.balance_framework()


@app.get("/v1/energy-systems/conversion-chain")
def energy_systems_conversion_chain(
    input_kwh: str = Query(..., min_length=1, max_length=80),
    efficiencies: str = Query(..., min_length=1, max_length=500),
    labels: str = Query(default="", max_length=500),
) -> dict[str, Any]:
    try:
        return energy_systems.conversion_chain(input_kwh=input_kwh, efficiencies=efficiencies, labels=labels)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/supply-demand-balance")
def energy_systems_supply_demand_balance(
    domestic_supply_kwh: str = Query(default="0", max_length=80),
    imports_kwh: str = Query(default="0", max_length=80),
    storage_discharge_kwh: str = Query(default="0", max_length=80),
    final_demand_kwh: str = Query(default="0", max_length=80),
    exports_kwh: str = Query(default="0", max_length=80),
    storage_charge_kwh: str = Query(default="0", max_length=80),
    losses_kwh: str = Query(default="0", max_length=80),
    tolerance_kwh: str = Query(default="0.001", max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.supply_demand_balance(
            domestic_supply_kwh=domestic_supply_kwh,
            imports_kwh=imports_kwh,
            storage_discharge_kwh=storage_discharge_kwh,
            final_demand_kwh=final_demand_kwh,
            exports_kwh=exports_kwh,
            storage_charge_kwh=storage_charge_kwh,
            losses_kwh=losses_kwh,
            tolerance_kwh=tolerance_kwh,
        )
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/generation-estimate")
def energy_systems_generation_estimate(
    capacity_kw: str = Query(..., min_length=1, max_length=80),
    capacity_factor_pct: str = Query(..., min_length=1, max_length=80),
    hours: str = Query(default="8760", min_length=1, max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.generation_estimate(capacity_kw=capacity_kw, capacity_factor_pct=capacity_factor_pct, hours=hours)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/balance-scenario-template")
def energy_systems_balance_scenario_template() -> dict[str, Any]:
    return energy_systems.balance_scenario_template()

@app.get("/v1/energy-systems/economics-framework")
def energy_systems_economics_framework() -> dict[str, Any]:
    return energy_systems.economics_framework()

@app.get("/v1/energy-systems/energy-cost-comparison")
def energy_systems_energy_cost_comparison(baseline_energy_kwh: str = Query(..., min_length=1, max_length=80), baseline_price_per_kwh: str = Query(..., min_length=1, max_length=80), candidate_energy_kwh: str = Query(..., min_length=1, max_length=80), candidate_price_per_kwh: str = Query(..., min_length=1, max_length=80), baseline_fixed_cost: str = Query(default="0", max_length=80), candidate_fixed_cost: str = Query(default="0", max_length=80), currency: str = Query(default="currency-unit", max_length=24)) -> dict[str, Any]:
    try: return energy_systems.energy_cost_comparison(baseline_energy_kwh=baseline_energy_kwh, baseline_price_per_kwh=baseline_price_per_kwh, candidate_energy_kwh=candidate_energy_kwh, candidate_price_per_kwh=candidate_price_per_kwh, baseline_fixed_cost=baseline_fixed_cost, candidate_fixed_cost=candidate_fixed_cost, currency=currency)
    except ValueError as exc: raise HTTPException(status_code=422, detail=str(exc)) from exc

@app.get("/v1/energy-systems/simple-payback")
def energy_systems_simple_payback(initial_cost: str = Query(..., min_length=1, max_length=80), annual_net_savings: str = Query(..., min_length=1, max_length=80), currency: str = Query(default="currency-unit", max_length=24)) -> dict[str, Any]:
    try: return energy_systems.simple_payback(initial_cost=initial_cost, annual_net_savings=annual_net_savings, currency=currency)
    except ValueError as exc: raise HTTPException(status_code=422, detail=str(exc)) from exc

@app.get("/v1/energy-systems/npv")
def energy_systems_npv(initial_cost: str = Query(..., min_length=1, max_length=80), annual_net_cash_flow: str = Query(..., min_length=1, max_length=80), discount_rate_pct: str = Query(..., min_length=1, max_length=80), years: str = Query(..., min_length=1, max_length=8), residual_value: str = Query(default="0", max_length=80), currency: str = Query(default="currency-unit", max_length=24)) -> dict[str, Any]:
    try: return energy_systems.npv(initial_cost=initial_cost, annual_net_cash_flow=annual_net_cash_flow, discount_rate_pct=discount_rate_pct, years=years, residual_value=residual_value, currency=currency)
    except ValueError as exc: raise HTTPException(status_code=422, detail=str(exc)) from exc

@app.get("/v1/energy-systems/cost-benefit")
def energy_systems_cost_benefit(initial_cost: str = Query(..., min_length=1, max_length=80), annual_cost: str = Query(..., min_length=1, max_length=80), annual_benefit: str = Query(..., min_length=1, max_length=80), discount_rate_pct: str = Query(..., min_length=1, max_length=80), years: str = Query(..., min_length=1, max_length=8), residual_value: str = Query(default="0", max_length=80), currency: str = Query(default="currency-unit", max_length=24)) -> dict[str, Any]:
    try: return energy_systems.cost_benefit(initial_cost=initial_cost, annual_cost=annual_cost, annual_benefit=annual_benefit, discount_rate_pct=discount_rate_pct, years=years, residual_value=residual_value, currency=currency)
    except ValueError as exc: raise HTTPException(status_code=422, detail=str(exc)) from exc

@app.get("/v1/energy-systems/cost-efficiency")
def energy_systems_cost_efficiency(total_cost: str = Query(..., min_length=1, max_length=80), energy_saved_kwh: str = Query(default="0", max_length=80), co2e_avoided_kg: str = Query(default="0", max_length=80), currency: str = Query(default="currency-unit", max_length=24)) -> dict[str, Any]:
    try: return energy_systems.cost_efficiency(total_cost=total_cost, energy_saved_kwh=energy_saved_kwh, co2e_avoided_kg=co2e_avoided_kg, currency=currency)
    except ValueError as exc: raise HTTPException(status_code=422, detail=str(exc)) from exc

@app.get("/v1/energy-systems/levelized-energy-cost")
def energy_systems_levelized_energy_cost(initial_cost: str = Query(..., min_length=1, max_length=80), annual_operating_cost: str = Query(..., min_length=1, max_length=80), annual_energy_kwh: str = Query(..., min_length=1, max_length=80), discount_rate_pct: str = Query(..., min_length=1, max_length=80), years: str = Query(..., min_length=1, max_length=8), residual_value: str = Query(default="0", max_length=80), currency: str = Query(default="currency-unit", max_length=24)) -> dict[str, Any]:
    try: return energy_systems.levelized_energy_cost(initial_cost=initial_cost, annual_operating_cost=annual_operating_cost, annual_energy_kwh=annual_energy_kwh, discount_rate_pct=discount_rate_pct, years=years, residual_value=residual_value, currency=currency)
    except ValueError as exc: raise HTTPException(status_code=422, detail=str(exc)) from exc

@app.get("/v1/energy-systems/economic-scenario-template")
def energy_systems_economic_scenario_template() -> dict[str, Any]:
    return energy_systems.economic_scenario_template()


@app.get("/v1/energy-systems/global-energy-framework")
def energy_systems_global_energy_framework() -> dict[str, Any]:
    return energy_systems.global_energy_framework()


@app.get("/v1/energy-systems/global-energy-sources")
def energy_systems_global_energy_sources() -> dict[str, Any]:
    return energy_systems.global_energy_sources()


@app.get("/v1/energy-systems/global-energy-metrics")
def energy_systems_global_energy_metrics(
    q: str = Query(default="", max_length=500),
    category: str = Query(default="", max_length=80),
    limit: int = Query(default=100, ge=1, le=100),
) -> dict[str, Any]:
    return energy_systems.global_energy_metrics(q=q, category=category, limit=limit)


@app.get("/v1/energy-systems/global-energy-profile-template")
def energy_systems_global_energy_profile_template() -> dict[str, Any]:
    return energy_systems.global_energy_profile_template()


@app.get("/v1/energy-systems/global-energy-country-profile")
def energy_systems_global_energy_country_profile(
    country: str = Query(..., min_length=2, max_length=3),
    start_year: int | None = Query(default=None, ge=1960, le=2100),
    end_year: int | None = Query(default=None, ge=1960, le=2100),
    include_series: bool = Query(default=True),
) -> dict[str, Any]:
    try:
        return energy_systems.global_energy_country_profile(
            country=country, start_year=start_year, end_year=end_year, include_series=include_series
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except GlobalEnergyDataError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/energy-systems/global-energy-compare")
def energy_systems_global_energy_compare(
    countries: str = Query(..., min_length=2, max_length=80),
    metric: str = Query(..., min_length=1, max_length=100),
    start_year: int | None = Query(default=None, ge=1960, le=2100),
    end_year: int | None = Query(default=None, ge=1960, le=2100),
) -> dict[str, Any]:
    try:
        return energy_systems.global_energy_compare(
            countries=countries, metric_key=metric, start_year=start_year, end_year=end_year
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except GlobalEnergyDataError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/energy-systems/decision-framework")
def energy_systems_decision_framework() -> dict[str, Any]:
    return energy_systems.decision_framework()


@app.get("/v1/energy-systems/decision-criteria")
def energy_systems_decision_criteria(
    q: str = Query(default="", max_length=500),
    dimension: str = Query(default="", max_length=80),
    limit: int = Query(default=100, ge=1, le=100),
) -> dict[str, Any]:
    return energy_systems.decision_criteria(q=q, dimension=dimension, limit=limit)


@app.get("/v1/energy-systems/decision-packet-template")
def energy_systems_decision_packet_template() -> dict[str, Any]:
    return energy_systems.decision_packet_template()


@app.get("/v1/energy-systems/decision-comparison-matrix")
def energy_systems_decision_comparison_matrix(
    packet: str = Query(..., min_length=2, max_length=7000),
) -> dict[str, Any]:
    try:
        return energy_systems.decision_comparison_matrix(packet_json=packet)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/decision-readiness")
def energy_systems_decision_readiness(
    packet: str = Query(..., min_length=2, max_length=7000),
) -> dict[str, Any]:
    try:
        return energy_systems.decision_readiness(packet_json=packet)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/bioenergy-framework")
def energy_systems_bioenergy_framework() -> dict[str, Any]:
    return energy_systems.bioenergy_framework()


@app.get("/v1/energy-systems/bioenergy-feedstocks")
def energy_systems_bioenergy_feedstocks(
    q: str = Query(default="", max_length=500),
    limit: int = Query(default=100, ge=1, le=100),
) -> dict[str, Any]:
    return energy_systems.bioenergy_feedstocks(q=q, limit=limit)


@app.get("/v1/energy-systems/bioenergy-pathways")
def energy_systems_bioenergy_pathways(
    q: str = Query(default="", max_length=500),
    family: str = Query(default="", max_length=120),
    limit: int = Query(default=100, ge=1, le=100),
) -> dict[str, Any]:
    return energy_systems.bioenergy_pathways(q=q, family=family, limit=limit)


@app.get("/v1/energy-systems/bioenergy-pathways/{pathway_key}")
def energy_systems_bioenergy_pathway(pathway_key: str) -> dict[str, Any]:
    try:
        return energy_systems.bioenergy_pathway(pathway_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Bioenergy pathway not found") from exc


@app.get("/v1/energy-systems/biological-carbon-bridges")
def energy_systems_biological_carbon_bridges() -> dict[str, Any]:
    return energy_systems.biological_carbon_bridges()


@app.get("/v1/energy-systems/feedstock-energy-estimate")
def energy_systems_feedstock_energy_estimate(
    mass_tonnes: str = Query(..., min_length=1, max_length=80),
    energy_content_kwh_per_tonne: str = Query(..., min_length=1, max_length=80),
    conversion_efficiency_pct: str = Query(default="100", min_length=1, max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.feedstock_energy_estimate(mass_tonnes=mass_tonnes, energy_content_kwh_per_tonne=energy_content_kwh_per_tonne, conversion_efficiency_pct=conversion_efficiency_pct)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/anaerobic-digestion-energy-estimate")
def energy_systems_anaerobic_digestion_energy_estimate(
    feedstock_mass_tonnes: str = Query(..., min_length=1, max_length=80),
    biogas_yield_m3_per_tonne: str = Query(..., min_length=1, max_length=80),
    methane_fraction_pct: str = Query(..., min_length=1, max_length=80),
    methane_energy_kwh_per_m3: str = Query(..., min_length=1, max_length=80),
    conversion_efficiency_pct: str = Query(default="100", min_length=1, max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.anaerobic_digestion_energy_estimate(feedstock_mass_tonnes=feedstock_mass_tonnes, biogas_yield_m3_per_tonne=biogas_yield_m3_per_tonne, methane_fraction_pct=methane_fraction_pct, methane_energy_kwh_per_m3=methane_energy_kwh_per_m3, conversion_efficiency_pct=conversion_efficiency_pct)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/biochar-carbon-estimate")
def energy_systems_biochar_carbon_estimate(
    biochar_mass_kg: str = Query(..., min_length=1, max_length=80),
    carbon_fraction_pct: str = Query(..., min_length=1, max_length=80),
    stable_fraction_pct: str = Query(..., min_length=1, max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.biochar_carbon_estimate(biochar_mass_kg=biochar_mass_kg, carbon_fraction_pct=carbon_fraction_pct, stable_fraction_pct=stable_fraction_pct)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/biomass-to-oil-energy-estimate")
def energy_systems_biomass_to_oil_energy_estimate(
    feedstock_mass_tonnes: str = Query(..., min_length=1, max_length=80),
    oil_yield_mass_pct: str = Query(..., min_length=1, max_length=80),
    oil_energy_content_kwh_per_tonne: str = Query(..., min_length=1, max_length=80),
    downstream_conversion_efficiency_pct: str = Query(default="100", min_length=1, max_length=80),
) -> dict[str, Any]:
    try:
        return energy_systems.biomass_to_oil_energy_estimate(feedstock_mass_tonnes=feedstock_mass_tonnes, oil_yield_mass_pct=oil_yield_mass_pct, oil_energy_content_kwh_per_tonne=oil_energy_content_kwh_per_tonne, downstream_conversion_efficiency_pct=downstream_conversion_efficiency_pct)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/energy-systems/bioenergy-scenario-template")
def energy_systems_bioenergy_scenario_template() -> dict[str, Any]:
    return energy_systems.bioenergy_scenario_template()


@app.get("/v1/carbon-nature")
def carbon_nature_manifest() -> dict[str, Any]:
    return carbon_nature.manifest()


@app.get("/v1/carbon-nature/concepts")
def carbon_nature_concepts(
    concept_type: str | None = Query(default=None, max_length=80),
    domain: str | None = Query(default=None, max_length=120),
    q: str | None = Query(default=None, max_length=500),
) -> dict[str, Any]:
    return carbon_nature.concepts(concept_type=concept_type, domain=domain, q=q)


@app.get("/v1/carbon-nature/concepts/{concept_key}")
def carbon_nature_concept(concept_key: str) -> dict[str, Any]:
    try:
        return carbon_nature.concept(concept_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Carbon & Nature concept not found") from exc


@app.get("/v1/carbon-nature/relationships")
def carbon_nature_relationships(
    subject: str | None = Query(default=None, max_length=120),
    predicate: str | None = Query(default=None, max_length=120),
    object_key: str | None = Query(default=None, alias="object", max_length=120),
) -> dict[str, Any]:
    return carbon_nature.relationships(subject=subject, predicate=predicate, object_key=object_key)


@app.get("/v1/carbon-nature/measures")
def carbon_nature_measures(
    q: str | None = Query(default=None, max_length=500),
    family: str | None = Query(default=None, max_length=120),
    system: str | None = Query(default=None, max_length=120),
    pool: str | None = Query(default=None, max_length=120),
    gas: str | None = Query(default=None, max_length=120),
    mrv_family: str | None = Query(default=None, max_length=120),
    limit: int = Query(default=50, ge=1, le=100),
) -> dict[str, Any]:
    return carbon_nature.measures(
        q=q, family=family, system=system, pool=pool, gas=gas, mrv_family=mrv_family, limit=limit
    )


@app.get("/v1/carbon-nature/measures/compare")
def carbon_nature_measure_comparison(
    keys: str = Query(..., min_length=1, max_length=800),
) -> dict[str, Any]:
    requested = [item.strip() for item in keys.split(",") if item.strip()]
    try:
        return carbon_nature.compare_measures(requested)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Carbon & Nature measure not found") from exc


@app.get("/v1/carbon-nature/measures/{measure_key}")
def carbon_nature_measure(measure_key: str) -> dict[str, Any]:
    try:
        return carbon_nature.measure(measure_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Carbon & Nature measure not found") from exc


@app.get("/v1/carbon-nature/evidence")
def carbon_nature_evidence(
    q: str | None = Query(default=None, max_length=500),
    record_type: str | None = Query(default=None, max_length=120),
    authority_class: str | None = Query(default=None, max_length=160),
    concept: str | None = Query(default=None, max_length=160),
    measure: str | None = Query(default=None, max_length=160),
    methodology: str | None = Query(default=None, max_length=160),
    limit: int = Query(default=50, ge=1, le=100),
) -> dict[str, Any]:
    return carbon_nature.evidence_records(
        q=q,
        record_type=record_type,
        authority_class=authority_class,
        concept=concept,
        measure=measure,
        methodology=methodology,
        limit=limit,
    )


@app.get("/v1/carbon-nature/evidence/{evidence_key}")
def carbon_nature_evidence_record(evidence_key: str) -> dict[str, Any]:
    try:
        return carbon_nature.evidence_record(evidence_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Carbon & Nature evidence record not found") from exc


@app.get("/v1/carbon-nature/methodologies")
def carbon_nature_methodologies(
    q: str | None = Query(default=None, max_length=500),
    family: str | None = Query(default=None, max_length=120),
    measure: str | None = Query(default=None, max_length=160),
    outcome: str | None = Query(default=None, max_length=160),
    limit: int = Query(default=50, ge=1, le=100),
) -> dict[str, Any]:
    return carbon_nature.methodologies(q=q, family=family, measure=measure, outcome=outcome, limit=limit)


@app.get("/v1/carbon-nature/methodologies/{methodology_key}")
def carbon_nature_methodology(methodology_key: str) -> dict[str, Any]:
    try:
        return carbon_nature.methodology(methodology_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Carbon & Nature methodology not found") from exc


@app.get("/v1/carbon-nature/evidence-graph")
def carbon_nature_evidence_graph(
    node_type: str | None = Query(default=None, max_length=40),
    node_key: str | None = Query(default=None, max_length=180),
    predicate: str | None = Query(default=None, max_length=180),
    limit: int = Query(default=200, ge=1, le=500),
) -> dict[str, Any]:
    return carbon_nature.evidence_graph(
        node_type=node_type,
        node_key=node_key,
        predicate=predicate,
        limit=limit,
    )


@app.get("/v1/carbon-nature/evidence-graph/neighborhood/{node_key}")
def carbon_nature_evidence_graph_neighborhood(
    node_key: str,
    limit: int = Query(default=100, ge=1, le=300),
) -> dict[str, Any]:
    try:
        return carbon_nature.evidence_graph_neighborhood(node_key, limit=limit)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Carbon & Nature evidence graph node not found") from exc


@app.get("/v1/carbon-nature/project-object-model")
def carbon_nature_project_object_model() -> dict[str, Any]:
    return carbon_nature.project_object_model()


@app.get("/v1/carbon-nature/project-object-types")
def carbon_nature_project_object_types() -> dict[str, Any]:
    return carbon_nature.project_object_types()


@app.get("/v1/carbon-nature/project-object-types/{object_type_key}")
def carbon_nature_project_object_type(object_type_key: str) -> dict[str, Any]:
    try:
        return carbon_nature.project_object_type(object_type_key)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="Carbon & Nature project object type not found") from exc


@app.get("/v1/carbon-nature/provenance-event-types")
def carbon_nature_provenance_event_types() -> dict[str, Any]:
    return carbon_nature.provenance_event_types()


@app.get("/v1/carbon-nature/project-packet-template")
def carbon_nature_project_packet_template() -> dict[str, Any]:
    return carbon_nature.project_packet_template()


@app.post("/v1/carbon-nature/project-packets/validate")
async def carbon_nature_validate_project_packet(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=422, detail="project packet must be valid UTF-8 JSON") from exc
    return carbon_nature.validate_project_packet(payload)


@app.get("/v1/carbon-nature/research-librarian")
def carbon_nature_research_librarian_manifest() -> dict[str, Any]:
    return carbon_nature.research_librarian_manifest()


@app.get("/v1/carbon-nature/research-librarian/intents")
def carbon_nature_research_intents() -> dict[str, Any]:
    return carbon_nature.research_intents()


@app.get("/v1/carbon-nature/research-librarian/source-roles")
def carbon_nature_research_source_roles() -> dict[str, Any]:
    return carbon_nature.research_source_roles()


@app.get("/v1/carbon-nature/research-librarian/guidance")
def carbon_nature_research_guidance(
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=12, ge=1, le=30),
) -> dict[str, Any]:
    try:
        return carbon_nature.research_guidance(q, limit=limit)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/carbon-nature/research-context")
def carbon_nature_research_context(
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=12, ge=1, le=30),
) -> dict[str, Any]:
    try:
        return carbon_nature.research_context(q, limit=limit)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/global-source-federation/readiness")
def global_source_federation_readiness() -> dict[str, Any]:
    return global_source_federation_registry.readiness()


@app.get("/v1/global-source-federation/registry")
def global_source_federation_registry_snapshot(
    family: str = Query(default="", max_length=120),
    capability: str = Query(default="", max_length=120),
    collection: str = Query(default="", max_length=120),
    authority: str = Query(default="", max_length=160),
    q: str = Query(default="", max_length=240),
) -> dict[str, Any]:
    return global_source_federation_registry.snapshot(
        family=family, capability=capability, collection=collection, authority=authority, q=q,
    )


@app.get("/v1/global-source-federation/collections")
def global_source_federation_collections() -> dict[str, Any]:
    return global_source_federation_registry.collections()


@app.get("/v1/global-source-federation/sources/{source_id}")
def global_source_federation_source(source_id: str) -> dict[str, Any]:
    try:
        return global_source_federation_registry.source(source_id)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="global source not found") from exc


@app.post("/v1/global-source-federation/connectors/validate")
def global_source_federation_validate_connector(payload: dict[str, Any]) -> dict[str, Any]:
    return global_source_federation_registry.validate_connector_manifest(payload)


@app.get("/v1/global-source-federation/connectors/{connector_id}")
def global_source_federation_connector(connector_id: str) -> dict[str, Any]:
    try:
        return global_source_federation_registry.connector(connector_id)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="global source connector not found") from exc


@app.get("/v1/original-language-corpus/readiness")
def original_language_corpus_readiness_endpoint() -> dict[str, Any]:
    return original_language_corpus_readiness()


@app.post("/v1/original-language-corpus/validate")
def original_language_corpus_validate(payload: dict[str, Any]) -> dict[str, Any]:
    return validate_capture_payload(payload)


@app.post("/v1/original-language-corpus/package")
def original_language_corpus_package(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        package = build_capture_package(payload)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    for key in ("_raw_bytes", "_raw_text", "_normalized_text", "_source_metadata", "_provenance"):
        package.pop(key, None)
    package["persisted"] = False
    return package


@app.post("/v1/admin/original-language-corpus/captures")
async def original_language_corpus_ingest(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8")) if body else {}
        if not isinstance(payload, dict):
            raise ValueError("capture payload must be an object")
        return ingest_original_language_capture(payload)
    except (ValueError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/admin/original-language-corpus/captures/{capture_id}")
async def original_language_corpus_capture(
    capture_id: str,
    request: Request,
    include_text: bool = Query(default=False),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return get_original_language_capture(capture_id, include_text=include_text)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="original-language capture not found") from exc


@app.get("/v1/ocr-htr-transcription/readiness")
def ocr_htr_transcription_readiness_endpoint() -> dict[str, Any]:
    return ocr_htr_transcription_readiness()


@app.post("/v1/ocr-htr-transcription/validate")
def ocr_htr_transcription_validate(payload: dict[str, Any]) -> dict[str, Any]:
    return validate_derivation_payload(payload)


@app.post("/v1/ocr-htr-transcription/package")
def ocr_htr_transcription_package(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        package = build_derivation_package(payload)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    for key in ("_source_bytes", "_output_text", "_source_metadata", "_provenance"):
        package.pop(key, None)
    package["persisted"] = False
    return package


@app.post("/v1/admin/ocr-htr-transcription/runs")
async def ocr_htr_transcription_ingest(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8")) if body else {}
        if not isinstance(payload, dict):
            raise ValueError("derivation payload must be an object")
        return ingest_text_derivation(payload)
    except (ValueError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/admin/ocr-htr-transcription/runs/{run_id}")
async def ocr_htr_transcription_run(
    run_id: str,
    request: Request,
    include_text: bool = Query(default=False),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return get_text_derivation_run(run_id, include_text=include_text)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="text derivation run not found") from exc


@app.get("/v1/linguistic-corpus/readiness")
def linguistic_corpus_readiness_endpoint() -> dict[str, Any]:
    return linguistic_corpus_readiness()


@app.post("/v1/linguistic-corpus/validate")
def linguistic_corpus_validate(payload: dict[str, Any]) -> dict[str, Any]:
    return validate_linguistic_corpus_payload(payload)


@app.post("/v1/linguistic-corpus/package")
def linguistic_corpus_package(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        package = build_linguistic_corpus_package(payload)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    # Package construction is non-persisting and may return token text, but never source-media bytes.
    return package


@app.post("/v1/linguistic-corpus/kwic")
def linguistic_corpus_kwic(payload: dict[str, Any]) -> dict[str, Any]:
    corpus_payload = payload.get("corpus") if isinstance(payload.get("corpus"), dict) else payload
    query = str(payload.get("query") or "")
    try:
        package = build_linguistic_corpus_package(corpus_payload)
        return linguistic_kwic_from_package(
            package, query,
            window_tokens=int(payload.get("window_tokens", 5)),
            case_sensitive=bool(payload.get("case_sensitive", False)),
            limit=int(payload.get("limit", 100)),
            offset=int(payload.get("offset", 0)),
        )
    except (ValueError, TypeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/v1/linguistic-corpus/frequencies")
def linguistic_corpus_frequencies(payload: dict[str, Any]) -> dict[str, Any]:
    corpus_payload = payload.get("corpus") if isinstance(payload.get("corpus"), dict) else payload
    try:
        package = build_linguistic_corpus_package(corpus_payload)
        return linguistic_frequency_table(
            package,
            min_count=int(payload.get("min_count", 1)),
            limit=int(payload.get("limit", 100)),
            words_only=bool(payload.get("words_only", True)),
        )
    except (ValueError, TypeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/v1/admin/linguistic-corpora")
async def linguistic_corpus_ingest(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8")) if body else {}
        if not isinstance(payload, dict):
            raise ValueError("linguistic corpus payload must be an object")
        return ingest_linguistic_corpus(payload)
    except (ValueError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/admin/linguistic-corpora/{corpus_id}")
async def linguistic_corpus_get(
    corpus_id: str,
    request: Request,
    include_tokens: bool = Query(default=False),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return get_linguistic_corpus(corpus_id, include_tokens=include_tokens)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="linguistic corpus not found") from exc


@app.post("/v1/admin/linguistic-corpora/{corpus_id}/kwic")
async def linguistic_corpus_persisted_kwic(
    corpus_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8")) if body else {}
        if not isinstance(payload, dict):
            raise ValueError("KWIC query payload must be an object")
        return kwic_persisted_corpus(
            corpus_id, str(payload.get("query") or ""),
            window_tokens=int(payload.get("window_tokens", 5)),
            case_sensitive=bool(payload.get("case_sensitive", False)),
            limit=int(payload.get("limit", 100)),
            offset=int(payload.get("offset", 0)),
        )
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="linguistic corpus not found") from exc
    except (ValueError, TypeError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc



@app.get("/v1/cross-language-resolution/readiness")
def cross_language_resolution_readiness_endpoint() -> dict[str, Any]:
    return cross_language_resolution_readiness()


@app.post("/v1/cross-language-resolution/validate-authority")
def cross_language_resolution_validate_authority(payload: dict[str, Any]) -> dict[str, Any]:
    return validate_cross_language_authority_payload(payload)


@app.post("/v1/cross-language-resolution/candidates")
def cross_language_resolution_candidates(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        authority_payload = payload.get("authority") or {}
        query = payload.get("query") or {}
        limit = int(payload.get("limit", 25))
        authority = build_cross_language_authority_package(authority_payload)
        return generate_entity_resolution_candidates(authority, query, limit=limit)
    except (ValueError, TypeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/v1/cross-language-resolution/case")
def cross_language_resolution_case(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return build_resolution_case(payload.get("authority") or {}, payload.get("query") or {}, limit=int(payload.get("limit",25)))
    except (ValueError, TypeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/v1/admin/cross-language-authorities")
async def cross_language_authority_ingest(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8")) if body else {}
        if not isinstance(payload, dict): raise ValueError("authority payload must be an object")
        return ingest_authority_registry(payload)
    except (ValueError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/v1/admin/cross-language-resolution-cases")
async def cross_language_resolution_case_ingest(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8")) if body else {}
        return ingest_resolution_case(payload.get("query") or payload, limit=int(payload.get("limit",25)))
    except (ValueError, TypeError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/admin/cross-language-resolution-cases/{case_id:path}")
async def cross_language_resolution_case_get(
    case_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try: return get_resolution_case(case_id)
    except KeyError as exc: raise HTTPException(status_code=404, detail="resolution case not found") from exc


@app.post("/v1/admin/cross-language-resolution-cases/{case_id:path}/decision")
async def cross_language_resolution_decision_ingest(
    case_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8")) if body else {}
        return ingest_resolution_decision(case_id, payload)
    except KeyError as exc: raise HTTPException(status_code=404, detail="resolution case not found") from exc
    except (ValueError, json.JSONDecodeError) as exc: raise HTTPException(status_code=400, detail=str(exc)) from exc



@app.get("/v1/execution-fabric/readiness")
def execution_fabric_readiness_endpoint() -> dict[str, Any]:
    return execution_fabric_readiness()


@app.post("/v1/execution-fabric/validate-job")
def execution_fabric_validate_job(payload: dict[str, Any]) -> dict[str, Any]:
    return validate_job_payload(payload)


@app.post("/v1/execution-fabric/package-job")
def execution_fabric_package_job(payload: dict[str, Any]) -> dict[str, Any]:
    try: return build_job_package(payload)
    except ValueError as exc: raise HTTPException(status_code=400,detail=str(exc)) from exc


@app.post("/v1/admin/research-jobs")
async def research_job_submit_endpoint(request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature)
    try:
        payload=json.loads(body.decode("utf-8")) if body else {}
        return submit_research_job(payload)
    except (ValueError,json.JSONDecodeError) as exc: raise HTTPException(status_code=400,detail=str(exc)) from exc


@app.get("/v1/admin/research-jobs")
async def research_job_list_endpoint(request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None), state: str="", capability: str="", limit: int=100) -> dict[str,Any]:
    await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature)
    try: return list_research_jobs(state=state,capability=capability,limit=limit)
    except ValueError as exc: raise HTTPException(status_code=400,detail=str(exc)) from exc


@app.get("/v1/admin/research-jobs/{job_id:path}")
async def research_job_get_endpoint(job_id: str, request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature)
    try: return get_research_job(job_id)
    except KeyError as exc: raise HTTPException(status_code=404,detail="research job not found") from exc


@app.post("/v1/admin/research-jobs/lease")
async def research_job_lease_endpoint(request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature)
    try: return lease_next_job(json.loads(body.decode("utf-8")) if body else {})
    except (ValueError,json.JSONDecodeError) as exc: raise HTTPException(status_code=400,detail=str(exc)) from exc


@app.post("/v1/admin/research-jobs/{job_id:path}/start")
async def research_job_start_endpoint(job_id: str, request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}
    try: return start_research_job(job_id,str(payload.get("worker_id") or ""))
    except KeyError as exc: raise HTTPException(status_code=404,detail="research job not found") from exc
    except ValueError as exc: raise HTTPException(status_code=409,detail=str(exc)) from exc


@app.post("/v1/admin/research-jobs/{job_id:path}/heartbeat")
async def research_job_heartbeat_endpoint(job_id: str, request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}
    try: return heartbeat_research_job(job_id,str(payload.get("worker_id") or ""),payload.get("progress"))
    except KeyError as exc: raise HTTPException(status_code=404,detail="research job not found") from exc
    except ValueError as exc: raise HTTPException(status_code=409,detail=str(exc)) from exc


@app.post("/v1/admin/research-jobs/{job_id:path}/complete")
async def research_job_complete_endpoint(job_id: str, request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}
    try: return complete_research_job(job_id,str(payload.get("worker_id") or ""),payload.get("output_manifest") if isinstance(payload.get("output_manifest"),dict) else {})
    except KeyError as exc: raise HTTPException(status_code=404,detail="research job not found") from exc
    except ValueError as exc: raise HTTPException(status_code=409,detail=str(exc)) from exc


@app.post("/v1/admin/research-jobs/{job_id:path}/fail")
async def research_job_fail_endpoint(job_id: str, request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}
    try: return fail_research_job(job_id,str(payload.get("worker_id") or ""),error_class=str(payload.get("error_class") or ""),error_detail=str(payload.get("error_detail") or ""),retryable=bool(payload.get("retryable",True)),retry_delay_seconds=int(payload.get("retry_delay_seconds") or 30))
    except KeyError as exc: raise HTTPException(status_code=404,detail="research job not found") from exc
    except ValueError as exc: raise HTTPException(status_code=409,detail=str(exc)) from exc


@app.post("/v1/admin/research-jobs/{job_id:path}/cancel")
async def research_job_cancel_endpoint(job_id: str, request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}
    try: return cancel_research_job(job_id,reason=str(payload.get("reason") or ""))
    except KeyError as exc: raise HTTPException(status_code=404,detail="research job not found") from exc


@app.post("/v1/admin/research-jobs/recover-expired")
async def research_job_recover_endpoint(request: Request, authorization: str|None=Header(default=None), x_sc_timestamp: str|None=Header(default=None), x_sc_signature: str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}
    return recover_expired_leases(limit=int(payload.get("limit") or 100))

@app.get("/v1/worker-runtime/profiles")
def worker_runtime_profiles_endpoint() -> dict[str,Any]: return worker_profiles()

@app.get("/v1/worker-runtime/readiness")
def worker_runtime_readiness_endpoint() -> dict[str,Any]: return worker_readiness()

@app.post("/v1/worker-runtime/validate-profile")
def worker_runtime_validate_profile_endpoint(payload:dict[str,Any]) -> dict[str,Any]: return validate_worker_profile(payload)

@app.get("/v1/admin/workers")
def workers_list_endpoint(authorization:str|None=Header(default=None)) -> dict[str,Any]:
    require_admin_bearer(authorization); return list_workers()

@app.get("/v1/admin/workers/{worker_id:path}")
def worker_get_endpoint(worker_id:str,authorization:str|None=Header(default=None)) -> dict[str,Any]:
    require_admin_bearer(authorization)
    try: return get_worker(worker_id)
    except KeyError as exc: raise HTTPException(status_code=404,detail="worker not found") from exc

@app.post("/v1/admin/workers/register")
async def worker_register_endpoint(request:Request,authorization:str|None=Header(default=None),x_sc_timestamp:str|None=Header(default=None),x_sc_signature:str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); return register_worker(json.loads(body.decode("utf-8")) if body else {})

@app.post("/v1/admin/workers/{worker_id:path}/heartbeat")
async def worker_heartbeat_endpoint(worker_id:str,request:Request,authorization:str|None=Header(default=None),x_sc_timestamp:str|None=Header(default=None),x_sc_signature:str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}; return heartbeat_worker(worker_id,payload.get("metadata") if isinstance(payload,dict) else {})

@app.post("/v1/admin/workers/{worker_id:path}/lease")
async def worker_lease_endpoint(worker_id:str,request:Request,authorization:str|None=Header(default=None),x_sc_timestamp:str|None=Header(default=None),x_sc_signature:str|None=Header(default=None)) -> dict[str,Any]:
    await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); return lease_for_worker(worker_id)

@app.post("/v1/admin/workers/{worker_id:path}/quarantine")
async def worker_quarantine_endpoint(worker_id:str,request:Request,authorization:str|None=Header(default=None),x_sc_timestamp:str|None=Header(default=None),x_sc_signature:str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}; return quarantine_worker(worker_id,str(payload.get("reason") or "manual-quarantine"))

@app.post("/v1/admin/workers/{worker_id:path}/release")
async def worker_release_endpoint(worker_id:str,request:Request,authorization:str|None=Header(default=None),x_sc_timestamp:str|None=Header(default=None),x_sc_signature:str|None=Header(default=None)) -> dict[str,Any]:
    await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); return release_worker(worker_id)

@app.post("/v1/admin/workers/{worker_id:path}/fail-job/{job_id:path}")
async def worker_fail_job_endpoint(worker_id:str,job_id:str,request:Request,authorization:str|None=Header(default=None),x_sc_timestamp:str|None=Header(default=None),x_sc_signature:str|None=Header(default=None)) -> dict[str,Any]:
    body=await authorize_write(request,authorization,x_sc_timestamp,x_sc_signature); payload=json.loads(body.decode("utf-8")) if body else {}; return isolate_worker_failure(worker_id,job_id,error_class=str(payload.get("error_class") or "WorkerFailure"),error_detail=str(payload.get("error_detail") or ""),retryable=bool(payload.get("retryable",True)),retry_delay_seconds=int(payload.get("retry_delay_seconds") or 30))

@app.get("/v1/admin/dead-letters")
def dead_letters_endpoint(state:str=Query(default="open",max_length=20),limit:int=Query(default=100,ge=1,le=500),authorization:str|None=Header(default=None)) -> dict[str,Any]:
    require_admin_bearer(authorization); return list_dead_letters(state=state,limit=limit)

@app.get("/v1/institutional-research-network")
def institutional_research_network_manifest() -> dict[str, Any]:
    return institutional_research_network.manifest()


@app.get("/v1/institutional-research-network/search")
def institutional_research_network_search(
    q: str = Query(..., min_length=1, max_length=500),
    sources: str | None = Query(default=None, max_length=500),
    limit_per_source: int = Query(default=8, ge=1, le=25),
) -> dict[str, Any]:
    keys = [item.strip() for item in (sources or "").split(",") if item.strip()] or None
    try:
        return institutional_research_network.search(q, source_keys=keys, limit_per_source=limit_per_source)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/institutional-research-network/graph")
def institutional_research_network_graph(
    q: str = Query(..., min_length=1, max_length=500),
    sources: str | None = Query(default=None, max_length=500),
    limit_per_source: int = Query(default=8, ge=1, le=25),
) -> dict[str, Any]:
    keys = [item.strip() for item in (sources or "").split(",") if item.strip()] or None
    try:
        return institutional_research_network.graph(q, source_keys=keys, limit_per_source=limit_per_source)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/institutional-sources")
def list_institutional_sources() -> dict[str, Any]:
    return {
        "schema": "sc-institutional-sources/1.0",
        "sources": institutional_sources.list_sources(),
    }


@app.get("/v1/institutional-sources/{source_key}/search")
def institutional_source_search(
    source_key: str,
    q: str = Query(default="", max_length=500),
    object_type: str = Query(default="dataset", max_length=40),
    limit: int = Query(default=10, ge=1, le=50),
    start: int = Query(default=0, ge=0, le=100000),
) -> dict[str, Any]:
    try:
        source = institutional_sources.get(source_key)
        return source.search(q, limit=limit, start=start, object_type=object_type)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="institutional source not found") from exc
    except InstitutionalSourceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/institutional-sources/{source_key}/record")
def institutional_source_record(
    source_key: str,
    persistent_id: str = Query(..., min_length=1, max_length=300),
) -> dict[str, Any]:
    try:
        source = institutional_sources.get(source_key)
        return source.get_record(persistent_id)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="institutional source not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except InstitutionalSourceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/search")
def search(
    q: str = Query(default="", max_length=500),
    object_type: str | None = Query(default=None, max_length=80),
    source_key: str | None = Query(default=None, max_length=191),
    topic: str | None = Query(default=None, max_length=500),
    year_from: int | None = Query(default=None, ge=1000, le=3000),
    year_to: int | None = Query(default=None, ge=1000, le=3000),
    sort: str = Query(default="relevance", max_length=20),
    mode: str = Query(default="hybrid", pattern="^(hybrid|lexical|semantic)$"),
    include_core: bool = Query(default=True),
    limit: int = Query(default=20, ge=1, le=100),
    offset: int = Query(default=0, ge=0, le=100000),
) -> dict[str, Any]:
    return hybrid_search_records(
        q, object_type, source_key, topic, year_from, year_to, sort, limit, offset,
        mode=mode, include_core=include_core,
    )


@app.get("/v1/semantic-similarity/readiness")
def semantic_similarity_readiness() -> dict[str, Any]:
    return representation_search_readiness()


@app.get("/v1/semantic-similarity/search")
def semantic_similarity_search(
    q: str = Query(min_length=1, max_length=500),
    object_type: str | None = Query(default=None, max_length=80),
    source_key: str | None = Query(default=None, max_length=191),
    topic: str | None = Query(default=None, max_length=500),
    year_from: int | None = Query(default=None, ge=1000, le=3000),
    year_to: int | None = Query(default=None, ge=1000, le=3000),
    min_similarity: float | None = Query(default=None, ge=-1.0, le=1.0),
    limit: int = Query(default=20, ge=1, le=100),
    offset: int = Query(default=0, ge=0, le=100000),
) -> dict[str, Any]:
    try:
        return semantic_text_search(
            q, object_type=object_type, source_key=source_key, topic=topic,
            year_from=year_from, year_to=year_to, min_similarity=min_similarity,
            limit=limit, offset=offset,
        )
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/semantic-similarity/records/{record_id:path}")
def semantic_similarity_for_record(
    record_id: str,
    object_type: str | None = Query(default=None, max_length=80),
    source_key: str | None = Query(default=None, max_length=191),
    topic: str | None = Query(default=None, max_length=500),
    year_from: int | None = Query(default=None, ge=1000, le=3000),
    year_to: int | None = Query(default=None, ge=1000, le=3000),
    min_similarity: float | None = Query(default=None, ge=-1.0, le=1.0),
    limit: int = Query(default=20, ge=1, le=100),
    offset: int = Query(default=0, ge=0, le=100000),
) -> dict[str, Any]:
    try:
        return similar_records(
            record_id, object_type=object_type, source_key=source_key, topic=topic,
            year_from=year_from, year_to=year_to, min_similarity=min_similarity,
            limit=limit, offset=offset,
        )
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.get("/v1/semantic-representations/{record_id:path}")
def semantic_representation_read(record_id: str) -> dict[str, Any]:
    try:
        return representation_descriptor(record_id)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.get("/v1/citations/readiness")
def citations_readiness() -> dict[str, Any]:
    return citation_readiness()


@app.post("/v1/citations")
async def citation_create(
    payload: CitationCreateRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return upsert_citation(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


# IMPORTANT: register suffix-specific citation routes before the catch-all path
# route below. Starlette resolves routes in declaration order, and {record_id:path}
# otherwise consumes suffixes such as /graph as part of the record identifier.
@app.get("/v1/citations/{record_id:path}/graph")
def citation_graph_read(
    record_id: str,
    depth: int = Query(default=2, ge=1, le=4),
    limit: int = Query(default=250, ge=1, le=1000),
    include_core: bool = Query(default=True),
) -> dict[str, Any]:
    try:
        return citation_graph(record_id, depth=depth, limit=limit, include_core=include_core)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.post("/v1/citations/{record_id:path}/import-metadata")
async def citation_metadata_import(
    record_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return import_record_metadata_citations(record_id)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.get("/v1/citations/{record_id:path}")
def citation_list(
    record_id: str,
    direction: str = Query(default="both", pattern="^(outgoing|incoming|both)$"),
    limit: int = Query(default=100, ge=1, le=500),
) -> dict[str, Any]:
    return list_citations(record_id, direction=direction, limit=limit)


@app.post("/v1/citations/core-handoff")
async def citation_core_handoff(
    payload: CoreScholarlyCitationHandoffRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return enqueue_core_scholarly_citation(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/research-extraction/readiness")
def research_extraction_readiness() -> dict[str, Any]:
    return extraction_readiness()


@app.post("/v1/research-extraction/extract")
async def research_extraction_run(
    payload: ExtractionRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return extract_record_candidates(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/research-extraction/candidates")
async def research_extraction_candidates(
    request: Request,
    record_id: str = Query(min_length=1, max_length=500),
    candidate_type: str | None = Query(default=None, pattern="^(entity|finding|claim)$"),
    review_state: str | None = Query(default=None, pattern="^(pending|accepted|rejected|superseded)$"),
    limit: int = Query(default=200, ge=1, le=1000),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return list_candidates(record_id, candidate_type=candidate_type, review_state=review_state, limit=limit)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/research-extraction/candidates/{candidate_id}/review")
async def research_extraction_review(
    candidate_id: int,
    payload: CandidateReviewRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return review_candidate(candidate_id, payload)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.post("/v1/research-extraction/core-handoff")
async def research_extraction_core_handoff(
    payload: CandidatePromotionRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return enqueue_core_candidate(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/publication-visualizations/readiness")
def publication_visualizations_readiness() -> dict[str, Any]:
    return visualization_readiness()


@app.get("/v1/publication-knowledge-maps/readiness")
def publication_knowledge_maps_readiness() -> dict[str, Any]:
    return knowledge_map_readiness()


@app.get("/v1/publication-knowledge-maps/corpus")
def publication_corpus_knowledge_maps_read(
    source_key: str = "wordpress-main",
    object_type: str = "",
    record_ids: str = "",
    include_citations: bool = True,
    include_semantic_similarity: bool = True,
    semantic_threshold: float = 0.72,
    max_publications: int = 250,
    max_topics_per_publication: int = 36,
) -> dict[str, Any]:
    return build_publication_corpus_knowledge_map(
        source_key=source_key,
        object_type=object_type,
        record_ids=[x.strip() for x in record_ids.split(",") if x.strip()],
        include_citations=include_citations,
        include_semantic_similarity=include_semantic_similarity,
        semantic_threshold=semantic_threshold,
        max_publications=max_publications,
        max_topics_per_publication=max_topics_per_publication,
    )


@app.post("/v1/publication-knowledge-maps/corpus")
def publication_corpus_knowledge_maps_post(payload: dict[str, Any]) -> dict[str, Any]:
    """POST transport for research-sized corpus manifests.

    This mirrors the GET contract but carries record IDs in the JSON body so large
    canonical Publication Library manifests are not constrained by URL length.
    """
    record_ids_raw = payload.get("record_ids") or []
    if isinstance(record_ids_raw, str):
        record_ids = [x.strip() for x in record_ids_raw.split(",") if x.strip()]
    elif isinstance(record_ids_raw, list):
        record_ids = [str(x).strip() for x in record_ids_raw if str(x).strip()]
    else:
        record_ids = []
    return build_publication_corpus_knowledge_map(
        source_key=str(payload.get("source_key") or "wordpress-main"),
        object_type=str(payload.get("object_type") or ""),
        record_ids=record_ids[:1000],
        include_citations=bool(payload.get("include_citations", True)),
        include_semantic_similarity=bool(payload.get("include_semantic_similarity", True)),
        semantic_threshold=float(payload.get("semantic_threshold", 0.72)),
        max_publications=int(payload.get("max_publications", 250)),
        max_topics_per_publication=int(payload.get("max_topics_per_publication", 36)),
    )


@app.post("/v1/publication-knowledge-maps/session-package")
def publication_visual_session_package(payload: dict[str, Any]) -> dict[str, Any]:
    corpus_request = payload.get("corpus_request") if isinstance(payload.get("corpus_request"), dict) else {}
    record_ids_raw = corpus_request.get("record_ids") or []
    if isinstance(record_ids_raw, str):
        record_ids = [x.strip() for x in record_ids_raw.split(",") if x.strip()]
    elif isinstance(record_ids_raw, list):
        record_ids = [str(x).strip() for x in record_ids_raw if str(x).strip()]
    else:
        record_ids = []
    corpus = build_publication_corpus_knowledge_map(
        source_key=str(corpus_request.get("source_key") or "wordpress-main"),
        object_type=str(corpus_request.get("object_type") or ""),
        record_ids=record_ids[:1000],
        include_citations=bool(corpus_request.get("include_citations", True)),
        include_semantic_similarity=bool(corpus_request.get("include_semantic_similarity", True)),
        semantic_threshold=float(corpus_request.get("semantic_threshold", 0.72)),
        max_publications=int(corpus_request.get("max_publications", 250)),
        max_topics_per_publication=int(corpus_request.get("max_topics_per_publication", 36)),
    )
    return build_visual_research_session_package(
        corpus,
        payload.get("visual_state") if isinstance(payload.get("visual_state"), dict) else {},
        session_name=str(payload.get("session_name") or ""),
        note=str(payload.get("note") or ""),
        target_workspace=bool(payload.get("target_workspace", False)),
    )


@app.post("/v1/publication-knowledge-maps/evidence-trace")
def publication_visual_evidence_trace(payload: dict[str, Any]) -> dict[str, Any]:
    corpus_request = payload.get("corpus_request") if isinstance(payload.get("corpus_request"), dict) else {}
    record_ids_raw = corpus_request.get("record_ids") or []
    if isinstance(record_ids_raw, str):
        record_ids = [x.strip() for x in record_ids_raw.split(",") if x.strip()]
    elif isinstance(record_ids_raw, list):
        record_ids = [str(x).strip() for x in record_ids_raw if str(x).strip()]
    else:
        record_ids = []
    corpus = build_publication_corpus_knowledge_map(
        source_key=str(corpus_request.get("source_key") or "wordpress-main"),
        object_type=str(corpus_request.get("object_type") or ""),
        record_ids=record_ids[:1000],
        include_citations=bool(corpus_request.get("include_citations", True)),
        include_semantic_similarity=bool(corpus_request.get("include_semantic_similarity", True)),
        semantic_threshold=float(corpus_request.get("semantic_threshold", 0.72)),
        max_publications=int(corpus_request.get("max_publications", 250)),
        max_topics_per_publication=int(corpus_request.get("max_topics_per_publication", 36)),
    )
    return build_visual_evidence_trace(
        corpus,
        payload.get("visual_state") if isinstance(payload.get("visual_state"), dict) else {},
    )


def _publication_corpus_from_payload(payload: dict[str, Any]) -> dict[str, Any]:
    corpus_request = payload.get("corpus_request") if isinstance(payload.get("corpus_request"), dict) else {}
    record_ids_raw = corpus_request.get("record_ids") or []
    if isinstance(record_ids_raw, str):
        record_ids = [x.strip() for x in record_ids_raw.split(",") if x.strip()]
    elif isinstance(record_ids_raw, list):
        record_ids = [str(x).strip() for x in record_ids_raw if str(x).strip()]
    else:
        record_ids = []
    return build_publication_corpus_knowledge_map(
        source_key=str(corpus_request.get("source_key") or "wordpress-main"),
        object_type=str(corpus_request.get("object_type") or ""),
        record_ids=record_ids[:1000],
        include_citations=bool(corpus_request.get("include_citations", True)),
        include_semantic_similarity=bool(corpus_request.get("include_semantic_similarity", True)),
        semantic_threshold=float(corpus_request.get("semantic_threshold", 0.72)),
        max_publications=int(corpus_request.get("max_publications", 250)),
        max_topics_per_publication=int(corpus_request.get("max_topics_per_publication", 36)),
    )


@app.get("/v1/publication-embedding-maps/readiness")
def publication_embedding_maps_readiness() -> dict[str, Any]:
    return embedding_map_readiness()


@app.post("/v1/publication-embedding-maps/map")
def publication_embedding_maps_build(payload: dict[str, Any]) -> dict[str, Any]:
    raw_ids = payload.get("record_ids") or []
    if isinstance(raw_ids, str):
        record_ids = [x.strip() for x in raw_ids.split(",") if x.strip()]
    elif isinstance(raw_ids, list):
        record_ids = [str(x).strip() for x in raw_ids if str(x).strip()]
    else:
        record_ids = []
    return build_publication_embedding_map(
        source_key=str(payload.get("source_key") or "wordpress-main"),
        object_type=str(payload.get("object_type") or ""),
        record_ids=record_ids[:1000],
        similarity_threshold=float(payload.get("similarity_threshold", 0.72)),
        neighbors_per_point=int(payload.get("neighbors_per_point", 8)),
        max_edges=int(payload.get("max_edges", 1200)),
        max_publications=int(payload.get("max_publications", 250)),
    )


@app.get("/v1/publication-embedding-maps/{record_id}/neighborhood")
def publication_embedding_neighborhood(record_id: str, limit: int = 12, min_similarity: float = 0.0) -> dict[str, Any]:
    try:
        return semantic_neighborhood(record_id, limit=limit, min_similarity=min_similarity)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/retrieval-evaluation/evaluate")
def retrieval_evaluation_evaluate(payload: dict[str, Any]) -> dict[str, Any]:
    return evaluate_retrieval(payload)


@app.post("/v1/retrieval-evaluation/profile")
def retrieval_evaluation_profile(payload: dict[str, Any]) -> dict[str, Any]:
    return build_adaptive_ranking_profile(payload)


@app.post("/v1/retrieval-evaluation/rerank")
def retrieval_evaluation_rerank(payload: dict[str, Any]) -> dict[str, Any]:
    return adaptive_rerank(payload)


@app.get("/v1/neural-reranking/readiness")
def neural_reranking_readiness() -> dict[str, Any]:
    return reranking_readiness()


@app.post("/v1/neural-reranking/rerank")
def neural_reranking_rerank(payload: dict[str, Any]) -> dict[str, Any]:
    results = [dict(x) for x in (payload.get("results") or []) if isinstance(x, dict)]
    return rerank_candidates(str(payload.get("query") or ""), results)


@app.post("/v1/neural-reranking/evaluate")
def neural_reranking_evaluate(payload: dict[str, Any]) -> dict[str, Any]:
    return evaluate_reranking(payload)


@app.post("/v1/search/neural-reranked")
def search_neural_reranked(payload: dict[str, Any]) -> dict[str, Any]:
    search_payload = payload.get("search") if isinstance(payload.get("search"), dict) else payload
    query = str(search_payload.get("q") or "")
    requested_limit = max(1, min(100, int(search_payload.get("limit", 20))))
    requested_offset = max(0, min(100000, int(search_payload.get("offset", 0))))
    candidate_limit = max(
        requested_limit + requested_offset,
        min(settings.rerank_max_candidates, max(20, int(search_payload.get("candidate_limit", settings.rerank_max_candidates)))),
    )
    candidate_limit = min(100, settings.rerank_max_candidates, candidate_limit)
    base = hybrid_search_records(
        query,
        str(search_payload.get("object_type") or "") or None,
        str(search_payload.get("source_key") or "") or None,
        str(search_payload.get("topic") or "") or None,
        int(search_payload["year_from"]) if search_payload.get("year_from") else None,
        int(search_payload["year_to"]) if search_payload.get("year_to") else None,
        str(search_payload.get("sort") or "relevance"),
        candidate_limit,
        0,
        mode=str(search_payload.get("mode") or "hybrid"),
        include_core=bool(search_payload.get("include_core", True)),
    )
    reranked = rerank_candidates(query, [dict(x) for x in base.get("results", [])])
    base["results"] = reranked["results"][requested_offset:requested_offset + requested_limit]
    base["limit"] = requested_limit
    base["offset"] = requested_offset
    base["neural_reranking"] = {
        key: reranked.get(key)
        for key in ("schema", "run_id", "available", "reason", "specification", "candidate_count", "provider_scored_candidate_count", "max_provider_candidates", "guardrails")
    }
    return base


@app.post("/v1/search/adaptive")
def search_adaptive(payload: dict[str, Any]) -> dict[str, Any]:
    search_payload = payload.get("search") if isinstance(payload.get("search"), dict) else {}
    profile = payload.get("profile") if isinstance(payload.get("profile"), dict) else {}
    requested_limit = max(1, min(100, int(search_payload.get("limit", 20))))
    requested_offset = max(0, min(100000, int(search_payload.get("offset", 0))))
    candidate_limit = max(requested_limit + requested_offset, min(100, int(search_payload.get("candidate_limit", 80))))
    base = hybrid_search_records(
        str(search_payload.get("q") or ""),
        str(search_payload.get("object_type") or "") or None,
        str(search_payload.get("source_key") or "") or None,
        str(search_payload.get("topic") or "") or None,
        int(search_payload["year_from"]) if search_payload.get("year_from") else None,
        int(search_payload["year_to"]) if search_payload.get("year_to") else None,
        str(search_payload.get("sort") or "relevance"),
        candidate_limit,
        0,
        mode=str(search_payload.get("mode") or "hybrid"),
        include_core=bool(search_payload.get("include_core", True)),
    )
    ranked = rerank_results([dict(x) for x in base.get("results", [])], profile)
    base["results"] = ranked[requested_offset:requested_offset + requested_limit]
    base["limit"] = requested_limit
    base["offset"] = requested_offset
    base["adaptive_ranking"] = {
        "schema": "sc-library-adaptive-reranking/1.0",
        "profile_id": profile.get("profile_id"),
        "active": bool(profile.get("active")),
        "candidate_count": len(ranked),
        "result_set_filtered": False,
        "truth_status_changed": False,
        "evidence_relations_changed": False,
    }
    return base


@app.post("/v1/temporal-knowledge/analyze")
def temporal_knowledge_analyze(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return temporal_request(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/publication-knowledge-maps/temporal-evolution")
def publication_temporal_evolution(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = _publication_corpus_from_payload(payload)
    result = dict(corpus.get("temporal_knowledge_evolution") or {})
    try:
        if payload.get("as_of"):
            result["snapshot"] = knowledge_snapshot(result, payload.get("as_of"), lens=str(payload.get("lens") or "historical-availability"))
        if payload.get("from_date") and payload.get("to_date"):
            result["change_set"] = compare_snapshots(result, payload.get("from_date"), payload.get("to_date"), lens=str(payload.get("lens") or "historical-availability"))
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    result["corpus"] = corpus.get("corpus") or {}
    result["reproducibility"] = corpus.get("reproducibility") or {}
    return result


@app.post("/v1/methodology-intelligence/analyze")
def methodology_intelligence_analyze(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return methodology_request(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/publication-knowledge-maps/methodology-intelligence")
def publication_methodology_intelligence(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = _publication_corpus_from_payload(payload)
    result = dict(corpus.get("methodology_intelligence") or {})
    result["corpus"] = corpus.get("corpus") or {}
    result["reproducibility"] = corpus.get("reproducibility") or {}
    return result


@app.post("/v1/research-gap-novelty/analyze")
def research_gap_novelty_analyze(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return research_gap_novelty_request(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/publication-knowledge-maps/research-gap-novelty")
def publication_research_gap_novelty(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = _publication_corpus_from_payload(payload)
    result = dict(corpus.get("research_gap_novelty") or {})
    result["corpus"] = corpus.get("corpus") or {}
    result["reproducibility"] = corpus.get("reproducibility") or {}
    return result


@app.post("/v1/literature-reviews/build")
def literature_review_build(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return build_literature_review(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/literature-reviews/compare")
def literature_review_compare(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        request_payload = dict(payload)
        request_payload["mode"] = "compare"
        return literature_review_request(request_payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/publication-knowledge-maps/literature-review")
def publication_literature_review(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        result = build_literature_review(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    result["publication_map_context"] = {
        "renderer_neutral": True,
        "graph_overlay_default_evidence_path": False,
        "platform_core_governance_changed": False,
    }
    return result


@app.post("/v1/living-evidence/analyze")
def living_evidence_analyze(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return living_evidence_request(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/publication-knowledge-maps/living-evidence")
def publication_living_evidence(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        result = build_living_evidence(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    result["publication_map_context"] = {
        "renderer_neutral": True,
        "graph_overlay_default_evidence_path": False,
        "prior_review_snapshots_immutable": True,
        "platform_core_governance_changed": False,
    }
    return result


@app.post("/v1/research-corpora/build")
def research_corpus_build(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return build_research_corpus(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/research-corpora/export")
def research_corpus_export(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return export_research_corpus(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/runtime/research/status")
def unified_research_runtime_status() -> dict[str, Any]:
    return runtime_contract_status()


@app.post("/v1/runtime/research/resolve")
def unified_research_runtime_resolve(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        return resolve_runtime(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.post("/v1/runtime/research/execute")
async def unified_research_runtime_execute(
    payload: dict[str, Any], request: Request, authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None), x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return execute_runtime(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.get("/v1/runtime/reproducibility/status")
def runtime_reproducibility_status() -> dict[str, Any]:
    return reproducibility_status()


@app.get("/v1/runtime/reproducibility/environment")
def runtime_reproducibility_environment() -> dict[str, Any]:
    return execution_environment_snapshot()


@app.post("/v1/runtime/reproducibility/record")
async def runtime_reproducibility_record(
    payload: dict[str, Any], request: Request, authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None), x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return create_reproducibility_record(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.post("/v1/runtime/reproducibility/verify")
async def runtime_reproducibility_verify(
    payload: dict[str, Any], request: Request, authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None), x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return verify_reproducibility(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.post("/v1/publication-knowledge-maps/research-corpus")
def publication_research_corpus(payload: dict[str, Any]) -> dict[str, Any]:
    corpus_map = _publication_corpus_from_payload(payload)
    publication_records = []
    for node in corpus_map.get("nodes") or []:
        if isinstance(node, dict) and node.get("kind") == "publication":
            publication_records.append({
                "record_id": node.get("id"),
                "title": node.get("label"),
                "url": node.get("canonical_url"),
                "published_at": node.get("published_at"),
                "source_type": node.get("object_type"),
                "source_key": node.get("source_key"),
                "source_hash": node.get("source_content_hash"),
                "authors": node.get("authors") or [],
            })
    request_payload = dict(payload)
    request_payload["records"] = publication_records
    source_context = dict(request_payload.get("source_context") or {})
    source_context.update({
        "publication_corpus_fingerprint_sha256": (corpus_map.get("reproducibility") or {}).get("corpus_fingerprint_sha256"),
        "publication_source_hashes": (corpus_map.get("reproducibility") or {}).get("publication_source_hashes") or {},
        "publication_corpus": corpus_map.get("corpus") or {},
    })
    request_payload["source_context"] = source_context
    try:
        result = build_research_corpus(request_payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    result["publication_map_context"] = {
        "publication_record_count": len(publication_records),
        "renderer_neutral": True,
        "platform_core_governance_changed": False,
    }
    return result


@app.post("/v1/scientific-document-intelligence/analyze")
def scientific_document_intelligence_analyze(payload: dict[str, Any]) -> dict[str, Any]:
    document = payload.get("document") if isinstance(payload.get("document"), dict) else payload
    return build_scientific_document_intelligence(document)


@app.post("/v1/source-identity/analyze")
def source_identity_analyze(payload: dict[str, Any]) -> dict[str, Any]:
    return build_source_identity_analysis(payload)


@app.get("/v1/scientific-document-intelligence/record/{record_id}")
def scientific_document_intelligence_record(record_id: str) -> dict[str, Any]:
    try:
        return load_scientific_document_intelligence(record_id)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.post("/v1/publication-knowledge-maps/source-identity")
def publication_source_identity(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = _publication_corpus_from_payload(payload)
    result = dict(corpus.get("source_identity_resolution") or {})
    result["corpus"] = corpus.get("corpus") or {}
    result["reproducibility"] = corpus.get("reproducibility") or {}
    return result




@app.get("/v1/runtime/ingestion-fabric/status")
def ingestion_job_fabric_status() -> dict[str, Any]:
    return ingestion_fabric_status()


@app.post("/v1/ingestion-jobs")
async def ingestion_job_submit(
    payload: dict[str, Any], request: Request, authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None), x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return submit_ingestion_job(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.get("/v1/ingestion-jobs")
async def ingestion_job_list(
    request: Request, authorization: str | None = Header(default=None), x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None), state: str = "", job_type: str = "",
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return list_ingestion_jobs(state=state, job_type=job_type)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.get("/v1/ingestion-jobs/{job_id}")
async def ingestion_job_read(
    job_id: str, request: Request, authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None), x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return get_ingestion_job(job_id)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except LookupError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.post("/v1/ingestion-jobs/{job_id}/cancel")
async def ingestion_job_cancel(
    job_id: str, request: Request, authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None), x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return cancel_ingestion_job(job_id)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except LookupError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc

@app.get("/v1/runtime/native-graph/status")
def native_graph_status() -> dict[str, Any]:
    return native_graph_runtime_status()


@app.post("/v1/runtime/native-graph/pathfind")
def native_graph_pathfind(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = payload.get("corpus") if isinstance(payload.get("corpus"), dict) else payload
    query = payload.get("query") if isinstance(payload.get("query"), dict) else {}
    query = dict(query)
    query["runtime"] = "rust"
    return find_research_paths(corpus, query)

@app.post("/v1/runtime/native-graph/query")
def native_graph_query(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = payload.get("corpus") if isinstance(payload.get("corpus"), dict) else payload
    query = payload.get("query") if isinstance(payload.get("query"), dict) else {}
    return query_native_graph(corpus, query)


@app.post("/v1/publication-knowledge-maps/native-graph-query")
def publication_native_graph_query(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = _publication_corpus_from_payload(payload)
    query = payload.get("query") if isinstance(payload.get("query"), dict) else {}
    result = query_native_graph(corpus, query)
    result["corpus"] = corpus.get("corpus") or {}
    result["reproducibility"] = corpus.get("reproducibility") or {}
    return result

@app.post("/v1/publication-knowledge-maps/research-graph-query")
def publication_research_graph_query(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = _publication_corpus_from_payload(payload)
    query = payload.get("query") if isinstance(payload.get("query"), dict) else {}
    result = query_research_graph(corpus, query)
    result["corpus"] = corpus.get("corpus") or {}
    result["reproducibility"] = corpus.get("reproducibility") or {}
    return result


@app.post("/v1/publication-knowledge-maps/evidence-pathfind")
def publication_evidence_pathfind(payload: dict[str, Any]) -> dict[str, Any]:
    corpus = _publication_corpus_from_payload(payload)
    query = payload.get("query") if isinstance(payload.get("query"), dict) else {}
    result = find_research_paths(corpus, query)
    result["corpus"] = corpus.get("corpus") or {}
    result["reproducibility"] = corpus.get("reproducibility") or {}
    return result


@app.get("/v1/publication-knowledge-maps")
def publication_knowledge_maps_read(
    record_id: str,
    include_citations: bool = True,
    include_semantic_similarity: bool = True,
    semantic_threshold: float = 0.72,
    max_neighbors: int = 40,
    max_topics_per_publication: int = 36,
) -> dict[str, Any]:
    try:
        return build_publication_knowledge_map(
            record_id,
            include_citations=include_citations,
            include_semantic_similarity=include_semantic_similarity,
            semantic_threshold=semantic_threshold,
            max_neighbors=max_neighbors,
            max_topics_per_publication=max_topics_per_publication,
        )
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.post("/v1/publication-visualizations/build")
async def publication_visualizations_build(
    payload: VisualizationBuildRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return build_publication_visualizations(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/publication-visualizations")
def publication_visualizations_list(
    record_id: str = Query(min_length=1, max_length=500),
    limit: int = Query(default=50, ge=1, le=200),
) -> dict[str, Any]:
    return list_publication_visualizations(record_id, published_only=True, limit=limit)


@app.get("/v1/publication-visualizations/{visualization_id}")
def publication_visualization_read(visualization_id: int) -> dict[str, Any]:
    try:
        return get_publication_visualization(visualization_id, published_only=True)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.post("/v1/publication-visualizations/{visualization_id}/review")
async def publication_visualization_review(
    visualization_id: int,
    payload: VisualizationReviewRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return review_publication_visualization(visualization_id, payload)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@app.post("/v1/publication-visualizations/core-handoff")
async def publication_visualization_core_handoff(
    payload: VisualizationCoreHandoffRequest,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return enqueue_core_visualization(payload)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@app.get("/v1/search/readiness")
def search_readiness() -> dict[str, Any]:
    semantic = semantic_readiness()
    return {
        "schema": "sc-library-hybrid-retrieval-readiness/1.0",
        "hybrid_retrieval": True,
        "lexical_retrieval": True,
        "semantic_retrieval": bool(semantic.get("configured")),
        "semantic": semantic,
        "embedding_governance": {
            "enabled": True,
            "compute_target": settings.embedding_compute_target,
            "specification_fingerprint_sha256": current_embedding_specification()["fingerprint_sha256"],
            "workspace_handoff": True,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
        },
        "representation_search": representation_search_readiness(),
        "semantic_similarity_contract": "sc-library-semantic-similarity/1.0",
        "representation_search_contract": "sc-library-representation-search/1.0",
        "semantic_similarity_guardrail": "similarity-is-a-retrieval-signal-not-evidence-truth-or-causality",
        "fusion": "weighted-reciprocal-rank-fusion",
        "core_aware_results": True,
        "core_binding_source": "library_core_bindings",
        "platform_core_role": "governed-research-reasoning-and-provenance",
        "library_role": "source-intelligence-indexing-and-retrieval",
        "retrieval_evaluation": True,
        "adaptive_ranking_profiles": True,
        "adaptive_ranking_guardrail": "rerank-only-no-filter-no-truth-promotion",
        "neural_reranking": reranking_readiness(),
        "neural_reranking_contract": "sc-library-neural-reranking/1.0",
        "neural_reranking_evaluation_contract": "sc-library-neural-reranking-evaluation/1.0",
        "neural_reranking_guardrail": "provider-relevance-score-is-not-probability-evidence-or-truth-and-result-set-is-preserved",
        "publication_embedding_maps": embedding_map_readiness(),
        "publication_embedding_map_contract": "sc-library-publication-embedding-map/1.0",
        "semantic_knowledge_landscape_contract": "sc-library-semantic-knowledge-landscape/1.0",
        "publication_embedding_map_guardrail": "projection-and-proximity-are-analytical-signals-not-evidence-truth-or-causality",
        "global_source_federation": global_source_federation_registry.readiness(),
        "global_source_federation_contract": "sc-library-global-source-federation-registry/1.0",
        "global_source_connector_contract": "sc-library-global-source-connector-contract/1.0",
        "global_source_federation_guardrail": "registry-membership-and-connector-health-do-not-imply-source-quality-evidence-truth-endorsement-or-partnership",
        "original_language_corpus": original_language_corpus_readiness(),
        "original_language_corpus_contract": ORIGINAL_LANGUAGE_CORPUS_CONTRACT,
        "original_language_capture_contract": ORIGINAL_LANGUAGE_CAPTURE_CONTRACT,
        "original_language_corpus_guardrail": "original-source-bytes-and-text-remain-canonical-and-translation-normalization-are-derived-representations",
        "ocr_htr_transcription": ocr_htr_transcription_readiness(),
        "ocr_htr_transcription_lineage_contract": OCR_HTR_TRANSCRIPTION_LINEAGE_CONTRACT,
        "text_derivation_run_contract": TEXT_DERIVATION_RUN_CONTRACT,
        "ocr_htr_transcription_guardrail": "recognition-and-transcription-output-is-derived-text-and-confidence-is-not-truth-probability",
        "linguistic_corpus": linguistic_corpus_readiness(),
        "linguistic_corpus_contract": LINGUISTIC_CORPUS_CONTRACT,
        "concordance_query_contract": CONCORDANCE_QUERY_CONTRACT,
        "kwic_result_contract": KWIC_RESULT_CONTRACT,
        "linguistic_corpus_guardrail": "tokenization-concordance-and-frequency-are-reproducible-analytical-views-not-morphology-meaning-intent-evidence-or-truth",
        "cross_language_resolution": cross_language_resolution_readiness(),
        "cross_language_resolution_contract": CROSS_LANGUAGE_RESOLUTION_READINESS_CONTRACT,
        "cross_language_resolution_guardrail": "candidate-ranking-and-name-toponym-similarity-do-not-establish-identity-evidence-or-truth",
        "temporal_knowledge_evolution": True,
        "temporal_snapshot_guardrail": "historical-availability-is-distinct-from-retrospective-status",
        "methodology_intelligence": True,
        "methodology_guardrail": "structured-reporting-is-not-quality-or-truth",
        "research_gap_novelty_discovery": True,
        "research_gap_novelty_guardrail": "corpus-gap-is-not-global-absence-and-novelty-candidate-is-not-novelty-claim",
        "reproducible_literature_review_engine": True,
        "literature_review_guardrail": "human-screening-and-explicit-protocol-no-automatic-inclusion-or-meta-analysis",
        "living_evidence_research_evolution": True,
        "living_evidence_guardrail": "snapshot-comparison-and-update-candidates-no-automatic-search-state-change-or-truth-promotion",
        "rust_evidence_graph_acceleration_native_query_engine": True,
        "native_graph_query_guardrail": "native-structural-results-do-not-imply-evidence-truth-causality-consensus-or-quality",
    }


@app.get("/v1/embeddings/specification")
def embedding_specification() -> dict[str, Any]:
    return current_embedding_specification()


@app.get("/v1/embeddings/governance/readiness")
def embedding_governance_status() -> dict[str, Any]:
    return embedding_governance_readiness()


@app.post("/v1/admin/embeddings/backfill")
async def admin_embedding_backfill(
    request: Request,
    dry_run: bool = Query(default=True),
    limit: int = Query(default=1000, ge=1, le=10000),
    execution_target: str | None = Query(default=None, pattern="^(local|workspace)$"),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return queue_embedding_backfill(dry_run=dry_run, limit=limit, execution_target=execution_target)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/admin/embeddings/handoffs")
async def admin_embedding_handoffs_status(
    request: Request,
    limit: int = Query(default=100, ge=1, le=500),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return workspace_handoff_status(limit=limit)


@app.post("/v1/admin/embeddings/handoffs/prepare")
async def admin_embedding_handoffs_prepare(
    request: Request,
    limit: int = Query(default=25, ge=1, le=100),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return prepare_workspace_handoffs(limit=limit)


@app.post("/v1/admin/embeddings/handoffs/claim")
async def admin_embedding_handoff_claim(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8") or "{}")
        return claim_workspace_handoff(str(payload.get("worker_id") or ""))
    except (ValueError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/v1/admin/embeddings/handoffs/{handoff_id}/complete")
async def admin_embedding_handoff_complete(
    handoff_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8") or "{}")
        values = payload.get("embedding") if isinstance(payload.get("embedding"), list) else payload.get("values")
        if not isinstance(values, list):
            raise ValueError("embedding values are required")
        return complete_workspace_handoff(
            handoff_id=handoff_id,
            values=values,
            workspace_execution_id=str(payload.get("workspace_execution_id") or payload.get("execution_id") or ""),
        )
    except (ValueError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/v1/admin/embeddings/handoffs/{handoff_id}/fail")
async def admin_embedding_handoff_fail(
    handoff_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = json.loads(body.decode("utf-8") or "{}")
        return fail_workspace_handoff(
            handoff_id=handoff_id,
            error=str(payload.get("error") or "workspace embedding execution failed"),
            retry_after_seconds=int(payload.get("retry_after_seconds") or 30),
        )
    except (ValueError, TypeError, json.JSONDecodeError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/admin/embeddings/status")
async def admin_embedding_status(
    request: Request,
    limit: int = Query(default=100, ge=1, le=500),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return embedding_jobs_status(limit=limit)


@app.post("/v1/admin/embeddings/run-once")
async def admin_embedding_run_once(
    request: Request,
    limit: int = Query(default=25, ge=1, le=100),
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        return process_embedding_jobs_once(limit=limit)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.get("/v1/explorer/bootstrap")
def explorer_public_bootstrap(
    featured_limit: int = Query(default=4, ge=1, le=12),
    recent_limit: int = Query(default=4, ge=1, le=12),
) -> dict[str, Any]:
    return explorer_bootstrap(featured_limit, recent_limit)


@app.get("/v1/records/{record_id}")
def record(record_id: str, include_body: bool = Query(default=True)) -> dict[str, Any]:
    row = get_record(record_id, include_body=include_body)
    if row is None:
        raise HTTPException(status_code=404, detail="public record not found")
    return {"schema": "sc-library-record/1.0", "record": row}


@app.get("/v1/records/{record_id}/related")
def related(record_id: str, limit: int = Query(default=12, ge=1, le=50)) -> dict[str, Any]:
    return {"schema": "sc-library-related/1.0", "record_id": record_id, "results": related_records(record_id, limit)}


@app.get("/v1/records/{record_id}/timeline")
def record_timeline(record_id: str, limit: int = Query(default=25, ge=1, le=100)) -> dict[str, Any]:
    return {"schema": "sc-library-record-timeline/1.0", "record_id": record_id, "versions": timeline(record_id, limit)}


@app.get("/v1/graph/{record_id}")
def graph(record_id: str, limit: int = Query(default=100, ge=1, le=500)) -> dict[str, Any]:
    return graph_neighborhood(record_id, limit)


@app.get("/v1/facets")
def public_facets() -> dict[str, Any]:
    return facets()


@app.get("/v1/admin/status")
async def admin_status(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return operations_status()


@app.post("/v1/admin/integrity")
async def admin_integrity(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = IntegrityAuditRequest.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    return integrity_audit(payload)


@app.post("/v1/admin/prune")
async def admin_prune(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = PruneRequest.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    return prune_records(payload)
