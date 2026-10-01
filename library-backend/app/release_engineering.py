from __future__ import annotations

from datetime import datetime, timezone
from hashlib import sha256
import json
import re
from typing import Any

LIBRARY_VERSION = "5.66.0"
BACKEND_VERSION = "2.77.0"
WEB_VERSION = "1.2.0"
WORDPRESS_VERSION = "5.66.0"
PREDECESSOR_LIBRARY_VERSION = "5.65.0"
PREDECESSOR_BACKEND_VERSION = "2.76.0"
CONTRACT = "sc-library-release-engineering/1.0"
READINESS_CONTRACT = "sc-library-release-engineering-readiness/1.0"
MANIFEST_CONTRACT = "sc-library-release-manifest/1.0"
PLAN_CONTRACT = "sc-library-deployment-plan/1.0"
PREFLIGHT_CONTRACT = "sc-library-deployment-preflight/1.0"
ROLLBACK_CONTRACT = "sc-library-rollback-plan/1.0"
CERTIFICATION_CONTRACT = "sc-library-deployment-certification/1.0"
_SHA256 = re.compile(r"^[0-9a-f]{64}$")


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "release_authority_is_library_service": True,
        "wordpress_is_release_authority": False,
        "wordpress_can_execute_deployments": False,
        "artifact_sha256_required": True,
        "backend_deploys_before_web": True,
        "wordpress_installs_last": True,
        "preflight_required_before_apply": True,
        "rollback_plan_required_before_apply": True,
        "current_deployment_snapshot_required": True,
        "production_deployment_requires_certification": True,
        "release_certification_implies_research_truth": False,
        "deployment_can_delete_research_state": False,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "wordpress_version": WORDPRESS_VERSION,
        "predecessor": {"library_version": PREDECESSOR_LIBRARY_VERSION, "backend_version": PREDECESSOR_BACKEND_VERSION},
        "authority": "library-service",
        "supported_environments": ["staging", "production"],
        "deployment_order": ["preflight", "snapshot", "backend", "database-readiness", "web-if-changed", "wordpress-last", "postflight", "certify"],
        "rollback_order": ["stop-new-release", "restore-backend-artifact", "restore-web-artifact-if-changed", "verify-database-compatibility", "post-rollback-readiness"],
        "artifact_components": ["repository", "backend", "library-web", "wordpress"],
        "wordpress_required_for_release_engineering": False,
        "guardrails": guardrails(),
    }


def validate_release_manifest(payload: dict[str, Any] | None) -> dict[str, Any]:
    payload = dict(payload or {})
    errors: list[str] = []
    release_version = str(payload.get("release_version") or "")
    backend_version = str(payload.get("backend_version") or "")
    web_version = str(payload.get("web_version") or "")
    wordpress_version = str(payload.get("wordpress_version") or "")
    if release_version != LIBRARY_VERSION: errors.append("release-version-mismatch")
    if backend_version != BACKEND_VERSION: errors.append("backend-version-mismatch")
    if web_version != WEB_VERSION: errors.append("web-version-mismatch")
    if wordpress_version != WORDPRESS_VERSION: errors.append("wordpress-version-mismatch")
    artifacts = payload.get("artifacts") or []
    if not isinstance(artifacts, list) or not artifacts:
        errors.append("artifacts-required")
        artifacts=[]
    seen=set()
    normalized=[]
    for raw in artifacts:
        if not isinstance(raw,dict): errors.append("artifact-must-be-object"); continue
        component=str(raw.get("component") or "").strip()
        name=str(raw.get("name") or "").strip()
        digest=str(raw.get("sha256") or "").strip().lower()
        try: size=int(raw.get("size_bytes") or 0)
        except Exception: size=0
        if component not in {"repository","backend","library-web","wordpress"}: errors.append(f"invalid-component:{component}")
        if not name: errors.append("artifact-name-required")
        if not _SHA256.match(digest): errors.append(f"invalid-sha256:{component or 'unknown'}")
        if size <= 0: errors.append(f"invalid-size:{component or 'unknown'}")
        if component in seen: errors.append(f"duplicate-component:{component}")
        seen.add(component)
        normalized.append({"component":component,"name":name,"sha256":digest,"size_bytes":size,"required":bool(raw.get("required",True))})
    for required in ("repository","backend","library-web","wordpress"):
        if required not in seen: errors.append(f"missing-component:{required}")
    basis={"release_version":release_version,"backend_version":backend_version,"web_version":web_version,"wordpress_version":wordpress_version,"artifacts":normalized}
    fp=_fp(basis)
    return {"schema":MANIFEST_CONTRACT,"valid":not errors,"errors":errors,"manifest_id":"library-release-manifest:"+fp[:32],"manifest_sha256":fp,**basis,"guardrails":guardrails()}


def build_deployment_plan(payload: dict[str, Any] | None) -> dict[str, Any]:
    payload=dict(payload or {})
    environment=str(payload.get("environment") or "").strip().lower()
    if environment not in {"staging","production"}: raise ValueError("environment must be staging or production")
    manifest=validate_release_manifest(payload.get("manifest") or {})
    if not manifest["valid"]: raise ValueError("release manifest is invalid: "+",".join(manifest["errors"]))
    current=dict(payload.get("current") or {})
    current_library=str(current.get("library_version") or "")
    current_backend=str(current.get("backend_version") or "")
    errors=[]
    if current_library != PREDECESSOR_LIBRARY_VERSION: errors.append("unexpected-current-library-version")
    if current_backend != PREDECESSOR_BACKEND_VERSION: errors.append("unexpected-current-backend-version")
    snapshot_required=bool(payload.get("snapshot_retained",False))
    if not snapshot_required: errors.append("current-deployment-snapshot-required")
    basis={"environment":environment,"manifest_sha256":manifest["manifest_sha256"],"current":current,"snapshot_retained":snapshot_required}
    fp=_fp(basis)
    return {
        "schema":PLAN_CONTRACT,"plan_id":"library-deployment-plan:"+fp[:32],"valid":not errors,"errors":errors,
        "environment":environment,"manifest":manifest,"current":current,"snapshot_retained":snapshot_required,
        "phases":[
            {"phase":"preflight","required":True,"mutates":False},
            {"phase":"snapshot","required":True,"mutates":False},
            {"phase":"backend","required":True,"mutates":True,"target":BACKEND_VERSION},
            {"phase":"database-readiness","required":True,"mutates":False},
            {"phase":"library-web","required":False,"mutates":True,"target":WEB_VERSION,"condition":"artifact-changed"},
            {"phase":"wordpress","required":False,"mutates":True,"target":WORDPRESS_VERSION,"condition":"explicit-adapter-install"},
            {"phase":"postflight","required":True,"mutates":False},
            {"phase":"certify","required":environment=="production","mutates":True},
        ],
        "rollback": build_rollback_plan({"manifest":manifest,"current":current,"snapshot_retained":snapshot_required}),
        "guardrails":guardrails(),
    }


def validate_preflight(payload: dict[str, Any] | None) -> dict[str, Any]:
    p=dict(payload or {})
    required={
        "artifact_hashes_verified": bool(p.get("artifact_hashes_verified")),
        "database_ready": bool(p.get("database_ready")),
        "backend_health_ready": bool(p.get("backend_health_ready")),
        "rollback_snapshot_retained": bool(p.get("rollback_snapshot_retained")),
        "disk_capacity_ready": bool(p.get("disk_capacity_ready")),
        "port_allocation_safe": bool(p.get("port_allocation_safe")),
    }
    errors=[k for k,v in required.items() if not v]
    return {"schema":PREFLIGHT_CONTRACT,"valid":not errors,"errors":errors,"checks":required,"guardrails":guardrails()}


def build_rollback_plan(payload: dict[str, Any] | None) -> dict[str, Any]:
    p=dict(payload or {})
    manifest=dict(p.get("manifest") or {})
    current=dict(p.get("current") or {})
    snapshot_retained=bool(p.get("snapshot_retained"))
    basis={"manifest_sha256":manifest.get("manifest_sha256"),"current":current,"snapshot_retained":snapshot_retained}
    fp=_fp(basis)
    return {
        "schema":ROLLBACK_CONTRACT,"rollback_id":"library-rollback-plan:"+fp[:32],"available":snapshot_retained,
        "restore_library_version":current.get("library_version"),"restore_backend_version":current.get("backend_version"),
        "steps":["stop-new-release","restore-backend-artifact","restore-web-artifact-if-changed","verify-database-compatibility","post-rollback-readiness"],
        "database_destructive_rollback":False,"research_state_deletion":False,"guardrails":guardrails(),
    }


def build_certification(payload: dict[str, Any] | None) -> dict[str, Any]:
    p=dict(payload or {})
    plan=dict(p.get("plan") or {})
    preflight=validate_preflight(p.get("preflight") or {})
    postflight=dict(p.get("postflight") or {})
    errors=[]
    if not plan.get("valid"): errors.append("deployment-plan-invalid")
    if not preflight["valid"]: errors.append("preflight-invalid")
    for key in ("backend_ready","api_ready","database_ready","runtime_authority_preserved"):
        if not bool(postflight.get(key)): errors.append("postflight-"+key+"-failed")
    if not bool(plan.get("snapshot_retained")): errors.append("rollback-snapshot-not-retained")
    basis={"plan_id":plan.get("plan_id"),"postflight":postflight,"preflight":preflight}
    fp=_fp(basis)
    return {"schema":CERTIFICATION_CONTRACT,"certification_id":"library-deployment-certification:"+fp[:32],"certified":not errors,"errors":errors,"certification_sha256":fp,"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,"certified_at":datetime.now(timezone.utc).isoformat(),"guardrails":guardrails()}


def readiness() -> dict[str, Any]:
    counts={"manifests":0,"plans":0,"events":0,"certifications":0}; database="unavailable"
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for table,key in [
                ("library_release_manifests","manifests"),("library_deployment_plans","plans"),
                ("library_deployment_events","events"),("library_deployment_certifications","certifications")]:
                cur.execute(f"SELECT count(*) AS n FROM {table}"); counts[key]=int(cur.fetchone()["n"])
        database="ready"
    except Exception:
        database="unavailable"
    return {"schema":READINESS_CONTRACT,"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,"state":"ready","database":database,"counts":counts,"contract":contract(),"guardrails":guardrails()}


def persist_release_manifest(payload: dict[str, Any]) -> dict[str, Any]:
    manifest=validate_release_manifest(payload)
    if not manifest["valid"]: raise ValueError("invalid release manifest: "+",".join(manifest["errors"]))
    from .db import get_pool, json_value
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""INSERT INTO library_release_manifests(manifest_id,release_version,backend_version,web_version,wordpress_version,manifest_sha256,artifacts,guardrails)
        VALUES (%s,%s,%s,%s,%s,%s,%s::jsonb,%s::jsonb) ON CONFLICT(manifest_id) DO NOTHING""",
        (manifest["manifest_id"],manifest["release_version"],manifest["backend_version"],manifest["web_version"],manifest["wordpress_version"],manifest["manifest_sha256"],json_value(manifest["artifacts"]),json_value(manifest["guardrails"])))
        cur.execute("INSERT INTO library_deployment_events(event_type,manifest_id,details) VALUES ('manifest-registered',%s,%s::jsonb)",(manifest["manifest_id"],json_value({"manifest_sha256":manifest["manifest_sha256"]})))
        conn.commit()
    return manifest


def persist_deployment_plan(payload: dict[str, Any]) -> dict[str, Any]:
    plan=build_deployment_plan(payload)
    if not plan["valid"]: raise ValueError("invalid deployment plan: "+",".join(plan["errors"]))
    from .db import get_pool, json_value
    persist_release_manifest(plan["manifest"])
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""INSERT INTO library_deployment_plans(plan_id,manifest_id,environment,current_state,snapshot_retained,phases,rollback,status)
        VALUES (%s,%s,%s,%s::jsonb,%s,%s::jsonb,%s::jsonb,'planned') ON CONFLICT(plan_id) DO NOTHING""",
        (plan["plan_id"],plan["manifest"]["manifest_id"],plan["environment"],json_value(plan["current"]),plan["snapshot_retained"],json_value(plan["phases"]),json_value(plan["rollback"])))
        cur.execute("INSERT INTO library_deployment_events(event_type,manifest_id,plan_id,details) VALUES ('deployment-planned',%s,%s,%s::jsonb)",(plan["manifest"]["manifest_id"],plan["plan_id"],json_value({"environment":plan["environment"]})))
        conn.commit()
    return plan


def persist_certification(payload: dict[str, Any]) -> dict[str, Any]:
    cert=build_certification(payload)
    if not cert["certified"]: raise ValueError("deployment certification failed: "+",".join(cert["errors"]))
    from .db import get_pool, json_value
    plan=payload["plan"]
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""INSERT INTO library_deployment_certifications(certification_id,plan_id,certification_sha256,library_version,backend_version,details)
        VALUES (%s,%s,%s,%s,%s,%s::jsonb) ON CONFLICT(certification_id) DO NOTHING""",
        (cert["certification_id"],plan.get("plan_id"),cert["certification_sha256"],LIBRARY_VERSION,BACKEND_VERSION,json_value(cert)))
        cur.execute("UPDATE library_deployment_plans SET status='certified',certified_at=now() WHERE plan_id=%s",(plan.get("plan_id"),))
        cur.execute("INSERT INTO library_deployment_events(event_type,plan_id,details) VALUES ('deployment-certified',%s,%s::jsonb)",(plan.get("plan_id"),json_value({"certification_id":cert["certification_id"]})))
        conn.commit()
    return cert
