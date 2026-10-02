from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

CERTIFICATION_CONTRACT = "sc-library-runtime-authority-certification/1.0"
READINESS_CONTRACT = "sc-library-runtime-authority-readiness/1.0"
VALIDATION_CONTRACT = "sc-library-runtime-authority-validation/1.0"
CLIENT_ADAPTER_CONTRACT = "sc-library-client-adapter/1.0"

AUTHORITATIVE_COMPONENTS: dict[str, dict[str, Any]] = {
    "library-api": {"authority": "service", "required": True},
    "python-backend": {"authority": "runtime", "required": True},
    "postgresql": {"authority": "research-state", "required": True},
    "search-vector-infrastructure": {"authority": "retrieval-index", "required": True},
    "durable-job-fabric": {"authority": "execution-state", "required": True},
    "specialized-worker-runtime": {"authority": "execution", "required": True},
    "artifact-storage-fabric": {"authority": "artifact-metadata-and-integrity", "required": True},
    "checkpointed-pipeline-engine": {"authority": "pipeline-state", "required": True},
    "distributed-compute-broker": {"authority": "operational-placement", "required": True},
    "native-runtimes": {"authority": "bounded-native-execution", "required": True},
    "global-knowledge-federation": {"authority": "federation-contracts", "required": True},
    "platform-core-contracts": {"authority": "governed-research-meaning-and-exchange", "required": True},
}

CLIENT_ADAPTERS: dict[str, dict[str, Any]] = {
    "wordpress": {
        "role": "publishing-routing-embed-adapter",
        "authoritative": False,
        "required_for_research_execution": False,
        "allowed_responsibilities": [
            "institutional-pages", "editorial-publications", "seo-metadata", "public-routing",
            "launch-library", "embed-library-views", "authentication-handoff", "health-status",
            "shortcode-block-compatibility",
        ],
    },
    "research-librarian": {"role": "service-client", "authoritative": False, "required_for_research_execution": False},
    "workspace": {"role": "service-client-compute-provider", "authoritative": False, "required_for_research_execution": False},
    "research-lab": {"role": "service-client", "authoritative": False, "required_for_research_execution": False},
    "workbench": {"role": "service-client", "authoritative": False, "required_for_research_execution": False},
    "site-intelligence": {"role": "service-client", "authoritative": False, "required_for_research_execution": False},
    "decision-studio": {"role": "service-client", "authoritative": False, "required_for_research_execution": False},
}

RESEARCH_OBJECT_OWNERSHIP = {
    "source-record": "library",
    "publication-research-object": "library",
    "corpus": "library",
    "embedding": "library",
    "annotation": "library",
    "research-project": "library",
    "saved-search": "library",
    "watchlist": "library",
    "research-artifact": "library",
    "ocr-result": "library",
    "job-execution-state": "library",
    "research-graph": "library-or-platform-core",
    "entity-resolution": "library-or-platform-core",
    "translation-object": "library-with-platform-core-contracts",
    "provenance": "library-or-platform-core",
    "publication-editorial-page": "wordpress",
    "marketing-copy": "wordpress",
    "seo-metadata": "wordpress",
    "institutional-page": "wordpress",
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "library_api_is_authoritative_runtime_boundary": True,
        "python_backend_is_authoritative_library_runtime": True,
        "postgresql_is_authoritative_research_state": True,
        "wordpress_is_authoritative_research_runtime": False,
        "wordpress_required_for_research_execution": False,
        "new_research_capability_may_require_wordpress": False,
        "wordpress_may_own_editorial_publishing_state": True,
        "wordpress_may_route_embed_and_publish": True,
        "wordpress_may_mutate_authoritative_library_research_state_directly": False,
        "library_clients_may_call_library_api_directly": True,
        "platform_core_defines_governed_research_meaning": True,
        "library_defines_platform_core_meaning": False,
        "runtime_independence_implies_research_truth": False,
        "automatic_platform_core_promotion": False,
    }


def default_component_snapshot() -> dict[str, dict[str, Any]]:
    return {k: {"ready": True, **v} for k, v in AUTHORITATIVE_COMPONENTS.items()}


def default_client_snapshot() -> dict[str, dict[str, Any]]:
    return {k: dict(v) for k, v in CLIENT_ADAPTERS.items()}


def dependency_graph() -> dict[str, Any]:
    edges = [
        ["library-api", "python-backend"],
        ["python-backend", "postgresql"],
        ["python-backend", "search-vector-infrastructure"],
        ["python-backend", "durable-job-fabric"],
        ["durable-job-fabric", "specialized-worker-runtime"],
        ["specialized-worker-runtime", "native-runtimes"],
        ["python-backend", "artifact-storage-fabric"],
        ["python-backend", "checkpointed-pipeline-engine"],
        ["checkpointed-pipeline-engine", "durable-job-fabric"],
        ["python-backend", "distributed-compute-broker"],
        ["distributed-compute-broker", "durable-job-fabric"],
        ["python-backend", "global-knowledge-federation"],
        ["python-backend", "platform-core-contracts"],
    ]
    return {
        "schema": "sc-library-runtime-dependency-graph/1.0",
        "nodes": sorted(AUTHORITATIVE_COMPONENTS),
        "edges": edges,
        "wordpress_dependency_count": 0,
        "wordpress_present_in_authoritative_graph": False,
    }


def validate_certification_payload(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    if not isinstance(payload, dict):
        payload = {}
        errors.append("payload-must-be-object")

    supplied_components = payload.get("components") if isinstance(payload.get("components"), dict) else {}
    components: dict[str, dict[str, Any]] = {}
    for key, meta in AUTHORITATIVE_COMPONENTS.items():
        raw = supplied_components.get(key, {})
        if isinstance(raw, bool):
            raw = {"ready": raw}
        if not isinstance(raw, dict):
            raw = {}
        ready = bool(raw.get("ready", False))
        authoritative = raw.get("authoritative", True) is not False
        components[key] = {"ready": ready, "authoritative": authoritative, **meta}
        if meta["required"] and not ready:
            errors.append(f"component-not-ready:{key}")
        if not authoritative:
            errors.append(f"authoritative-component-marked-non-authoritative:{key}")

    clients = default_client_snapshot()
    supplied_clients = payload.get("clients") if isinstance(payload.get("clients"), dict) else {}
    for name, raw in supplied_clients.items():
        if name not in clients or not isinstance(raw, dict):
            continue
        clients[name] = {**clients[name], **raw}

    wp = clients["wordpress"]
    if wp.get("authoritative") is True:
        errors.append("wordpress-authoritative-research-runtime-prohibited")
    if wp.get("required_for_research_execution") is True:
        errors.append("wordpress-research-runtime-dependency-prohibited")
    if payload.get("new_research_capability_requires_wordpress") is True:
        errors.append("new-research-capability-wordpress-dependency-prohibited")
    if payload.get("automatic_platform_core_promotion") is True:
        errors.append("automatic-platform-core-promotion-prohibited")

    normalized = {
        "authority_id": str(payload.get("authority_id") or "library-runtime-authority:default"),
        "components": components,
        "clients": clients,
        "ownership": dict(RESEARCH_OBJECT_OWNERSHIP),
        "dependency_graph": dependency_graph(),
        "provenance": dict(payload.get("provenance") or {}),
    }
    return {
        "schema": VALIDATION_CONTRACT,
        "valid": not errors,
        "errors": errors,
        "normalized": normalized,
        "guardrails": guardrails(),
    }


def build_certification(payload: dict[str, Any]) -> dict[str, Any]:
    validation = validate_certification_payload(payload)
    if not validation["valid"]:
        raise ValueError(";".join(validation["errors"]))
    n = validation["normalized"]
    fingerprint = _fp({
        "authority_id": n["authority_id"],
        "components": n["components"],
        "clients": n["clients"],
        "ownership": n["ownership"],
        "dependency_graph": n["dependency_graph"],
    })
    return {
        "schema": CERTIFICATION_CONTRACT,
        "version": "6.0.0",
        "backend_version": "3.0.0",
        "certification_id": "library-runtime-authority-certification:" + fingerprint[:32],
        "certification_fingerprint_sha256": fingerprint,
        **n,
        "authoritative_component_count": len(n["components"]),
        "ready_component_count": sum(1 for x in n["components"].values() if x["ready"]),
        "wordpress_role": n["clients"]["wordpress"]["role"],
        "wordpress_required_for_research_execution": False,
        "state": "certified",
        "guardrails": guardrails(),
        "persisted": False,
    }


def build_default_certification() -> dict[str, Any]:
    return build_certification({
        "components": default_component_snapshot(),
        "clients": default_client_snapshot(),
        "provenance": {"authority": "knowledge-library", "basis": "v6.0-runtime-authority-contract"},
    })


def ingest_certification(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool

    result = build_certification(payload)
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO library_runtime_authority_certifications(
                certification_id,authority_id,state,component_snapshot,client_snapshot,ownership_snapshot,
                dependency_graph,certification_fingerprint,provenance,guardrails
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON CONFLICT(certification_id) DO NOTHING""",
            (
                result["certification_id"], result["authority_id"], result["state"],
                Jsonb(result["components"]), Jsonb(result["clients"]), Jsonb(result["ownership"]),
                Jsonb(result["dependency_graph"]), result["certification_fingerprint_sha256"],
                Jsonb(result["provenance"]), Jsonb(result["guardrails"]),
            ),
        )
        cur.execute(
            "INSERT INTO library_runtime_authority_events(certification_id,event_type,details) VALUES (%s,'certified',%s)",
            (result["certification_id"], Jsonb({
                "ready_component_count": result["ready_component_count"],
                "wordpress_role": result["wordpress_role"],
                "wordpress_required_for_research_execution": False,
            })),
        )
        conn.commit()
    result["persisted"] = True
    return result


def readiness() -> dict[str, Any]:
    counts = {"certifications": 0, "events": 0}
    database = "unavailable"
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for table, key in [
                ("library_runtime_authority_certifications", "certifications"),
                ("library_runtime_authority_events", "events"),
            ]:
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[key] = int(cur.fetchone()["n"])
            database = "ready"
    except Exception:
        pass
    c = build_default_certification()
    return {
        "schema": READINESS_CONTRACT,
        "version": "6.0.0",
        "backend_version": "3.0.0",
        "state": "ready" if database == "ready" else "degraded",
        "database": database,
        "counts": counts,
        "authoritative_component_count": c["authoritative_component_count"],
        "ready_component_count": c["ready_component_count"],
        "wordpress": c["clients"]["wordpress"],
        "wordpress_dependency_count": c["dependency_graph"]["wordpress_dependency_count"],
        "ownership": c["ownership"],
        "dependency_graph": c["dependency_graph"],
        "guardrails": guardrails(),
    }
