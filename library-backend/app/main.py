from __future__ import annotations

from contextlib import asynccontextmanager
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
from .query import explorer_bootstrap, facets, get_record, graph_neighborhood, related_records, search_records, stats, timeline
from .repository import delete_record, ingest_edges, ingest_records
from .security import constant_time_equal, sha256_hex, sign_request, valid_timestamp
from .settings import settings
from .operations import integrity_audit, operations_status, prune_records
from .institutional_sources import InstitutionalSourceError, build_registry
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
from .private_knowledge import (
    PrivateHandoffRequest,
    PrivateKnowledgeIngestRequest,
    PrivateKnowledgeSearchRequest,
    PrivateOrganizationalKnowledge,
    PrivateRecordRequest,
)


@asynccontextmanager
async def lifespan(_: FastAPI):
    if settings.database_url:
        initialize_database()
    yield
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
            "semantic_embeddings": "adapter-ready",
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
            "energy_systems_domain_version": "1.5.0",
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
    limit: int = Query(default=20, ge=1, le=100),
    offset: int = Query(default=0, ge=0, le=100000),
) -> dict[str, Any]:
    return search_records(q, object_type, source_key, topic, year_from, year_to, sort, limit, offset)


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
