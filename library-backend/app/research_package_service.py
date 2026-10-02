from __future__ import annotations

import base64
from datetime import datetime, timezone
from hashlib import sha256
import json
from typing import Any

from .artifact_storage import artifact_storage_readiness, get_artifact, persist_artifact, read_artifact_bytes, verify_artifact
from .checkpointed_pipeline import get_pipeline_run, pipeline_readiness
from .db import get_pool
from .execution_lineage import reproducibility_status, verify_reproducibility

LIBRARY_VERSION = "5.79.0"
BACKEND_VERSION = "2.90.0"
CONTRACT = "sc-library-python-research-package-reproducibility-service/1.0"
READINESS_CONTRACT = "sc-library-python-research-package-reproducibility-readiness/1.0"
PACKAGE_CONTRACT = "sc-library-research-reproducibility-package/1.0"
PACKAGE_VALIDATION_CONTRACT = "sc-library-research-package-validation/1.0"
PACKAGE_VERIFICATION_CONTRACT = "sc-library-research-package-verification/1.0"
REPRO_RECORD_SCHEMA = "sc-library-reproducibility-record/1.0"


def _now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00","Z")

def _canon(value: Any) -> str:
    return json.dumps(value,sort_keys=True,separators=(",",":"),ensure_ascii=False,default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def _clean(value: Any) -> str:
    return str(value or "").strip()

def _unique_strings(values: Any, *, limit: int=500) -> list[str]:
    if not isinstance(values,list): return []
    out=[]
    for raw in values:
        value=_clean(raw)
        if value and value not in out: out.append(value)
        if len(out)>=limit: break
    return out

def guardrails() -> dict[str,bool]:
    return {
        "python_is_research_package_authority": True,
        "python_is_reproducibility_authority": True,
        "wordpress_php_is_research_package_authority": False,
        "postgresql_remains_structured_state_authority": True,
        "existing_artifact_store_remains_package_byte_authority": True,
        "research_package_is_second_artifact_store": False,
        "research_package_is_second_pipeline_engine": False,
        "package_creation_reruns_research_automatically": False,
        "package_verification_reruns_research_by_default": False,
        "artifact_integrity_implies_research_truth": False,
        "matching_runtime_output_proves_scientific_equivalence": False,
        "pipeline_completion_implies_research_truth": False,
        "reproducibility_package_proves_source_validity": False,
        "automatic_platform_core_promotion": False,
    }

def contract() -> dict[str,Any]:
    resources=["research-packages","artifact-manifests","record-snapshots","pipeline-run-snapshots","execution-lineage","runtime-reproducibility-records","package-integrity-verification"]
    basis={"authority":"python-backend","resources":resources,"guardrails":guardrails()}
    return {
        "schema":CONTRACT,
        "service_id":"library-reproducibility:"+_fp(basis)[:32],
        "service_fingerprint_sha256":_fp(basis),
        "library_version":LIBRARY_VERSION,
        "backend_version":BACKEND_VERSION,
        "state":"authoritative",
        "authority":"python-backend",
        "resources":resources,
        "storage":{"package_metadata":"research-artifact-manifest","package_bytes":"existing-content-addressed-artifact-store","structured_state":"postgresql"},
        "wordpress":{"role":"presentation-and-api-client","required":False,"authoritative":False},
        "guardrails":guardrails(),
    }

def _record_snapshots(record_ids: list[str]) -> list[dict[str,Any]]:
    if not record_ids: return []
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""SELECT record_id,source_key,object_type,title,canonical_url,content_hash,revision,publication_status,published_at,indexed_at FROM library_records WHERE record_id=ANY(%s)""",(record_ids,))
        rows={str(r["record_id"]):dict(r) for r in cur.fetchall()}
    missing=[rid for rid in record_ids if rid not in rows]
    if missing: raise ValueError("record-not-found:"+",".join(missing[:10]))
    return [{
        "record_id":rows[rid].get("record_id"),"source_key":rows[rid].get("source_key"),"object_type":rows[rid].get("object_type"),
        "title":rows[rid].get("title"),"canonical_url":rows[rid].get("canonical_url"),"content_hash":rows[rid].get("content_hash"),
        "revision":rows[rid].get("revision"),"publication_status":rows[rid].get("publication_status"),"published_at":rows[rid].get("published_at"),"indexed_at":rows[rid].get("indexed_at")
    } for rid in record_ids]

def _artifact_snapshots(artifact_ids: list[str]) -> list[dict[str,Any]]:
    out=[]
    for aid in artifact_ids:
        try: a=get_artifact(aid)
        except KeyError as exc: raise ValueError("artifact-not-found:"+aid) from exc
        out.append({"artifact_id":a.get("artifact_id"),"content_sha256":a.get("content_sha256"),"byte_length":a.get("byte_length"),"artifact_type":a.get("artifact_type"),"media_type":a.get("media_type"),"manifest_fingerprint":a.get("manifest_fingerprint"),"lifecycle_state":a.get("lifecycle_state")})
    return out

def _pipeline_snapshots(run_ids: list[str]) -> list[dict[str,Any]]:
    out=[]
    for run_id in run_ids:
        try: package=get_pipeline_run(run_id)
        except KeyError as exc: raise ValueError("pipeline-run-not-found:"+run_id) from exc
        run=dict(package.get("run") or {}); stages=list(package.get("stages") or [])
        out.append({"run_id":run.get("run_id"),"pipeline_id":run.get("pipeline_id"),"state":run.get("state"),"pipeline_fingerprint":run.get("pipeline_fingerprint"),"input_manifest_fingerprint":run.get("input_manifest_fingerprint"),"stage_count":len(stages),"completed_stage_count":sum(1 for s in stages if s.get("state") in {"complete","skipped"}),"checkpoint_fingerprints":[s.get("checkpoint_fingerprint") for s in stages if s.get("checkpoint_fingerprint")]})
    return out

def _repro_records(values: Any) -> list[dict[str,Any]]:
    if values is None: return []
    if not isinstance(values,list): raise ValueError("reproducibility-records-must-be-array")
    out=[]
    for i,item in enumerate(values):
        if not isinstance(item,dict) or item.get("schema")!=REPRO_RECORD_SCHEMA: raise ValueError(f"invalid-reproducibility-record:{i}")
        out.append({k:item.get(k) for k in ["schema","record_id","workload","input_fingerprint_sha256","semantic_result_fingerprint_sha256","reproducibility_fingerprint_sha256","execution_environment","routing","lineage","input","result_fingerprint_sha256"]})
    return out

def validate_package_payload(payload: dict[str,Any]) -> dict[str,Any]:
    errors=[]; warnings=[]
    if not isinstance(payload,dict): return {"schema":PACKAGE_VALIDATION_CONTRACT,"valid":False,"errors":["payload-must-be-object"],"warnings":[]}
    record_ids=_unique_strings(payload.get("record_ids")); artifact_ids=_unique_strings(payload.get("artifact_ids")); pipeline_run_ids=_unique_strings(payload.get("pipeline_run_ids")); repro=payload.get("reproducibility_records")
    if repro is not None and not isinstance(repro,list): errors.append("reproducibility-records-must-be-array")
    if not (record_ids or artifact_ids or pipeline_run_ids or (isinstance(repro,list) and repro)): warnings.append("package-has-no-research-object-references")
    if any(not x.startswith("artifact:sha256:") for x in artifact_ids): errors.append("artifact-id-must-use-content-addressed-identity")
    normalized={"title":_clean(payload.get("title")) or "Research reproducibility package","description":_clean(payload.get("description")),"project_id":_clean(payload.get("project_id")) or None,"record_ids":record_ids,"artifact_ids":artifact_ids,"pipeline_run_ids":pipeline_run_ids,"reproducibility_records":repro if isinstance(repro,list) else [],"metadata":payload.get("metadata") if isinstance(payload.get("metadata"),dict) else {}}
    return {"schema":PACKAGE_VALIDATION_CONTRACT,"valid":not errors,"errors":errors,"warnings":warnings,"normalized":normalized,"guardrails":guardrails()}

def build_package(payload: dict[str,Any]) -> dict[str,Any]:
    validation=validate_package_payload(payload)
    if not validation["valid"]: raise ValueError("; ".join(validation["errors"]))
    n=validation["normalized"]; runtime=reproducibility_status(); env=runtime.get("execution_environment") if isinstance(runtime,dict) else {}
    environment_snapshot={"runtime_contract_fingerprint_sha256":(env or {}).get("runtime_contract_fingerprint_sha256"),"environment_fingerprint_sha256":(env or {}).get("environment_fingerprint_sha256"),"runtimes":(env or {}).get("runtimes") or []}
    basis={"title":n["title"],"description":n["description"],"project_id":n["project_id"],"records":_record_snapshots(n["record_ids"]),"artifacts":_artifact_snapshots(n["artifact_ids"]),"pipeline_runs":_pipeline_snapshots(n["pipeline_run_ids"]),"reproducibility_records":_repro_records(n["reproducibility_records"]),"execution_environment":environment_snapshot,"metadata":n["metadata"]}
    fingerprint=_fp(basis)
    return {"schema":PACKAGE_CONTRACT,"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,"package_fingerprint_sha256":fingerprint,"created_at":_now(),"basis":basis,"counts":{"records":len(basis["records"]),"artifacts":len(basis["artifacts"]),"pipeline_runs":len(basis["pipeline_runs"]),"reproducibility_records":len(basis["reproducibility_records"])},"guardrails":guardrails()}

def create_package(payload: dict[str,Any]) -> dict[str,Any]:
    manifest=build_package(payload); raw=_canon(manifest).encode("utf-8")
    artifact=persist_artifact({"content_base64":base64.b64encode(raw).decode("ascii"),"artifact_type":"reproducibility-package","media_type":"application/vnd.sustainable-catalyst.research-package+json","original_filename":"research-package-"+manifest["package_fingerprint_sha256"][:24]+".json","derivation_operation":"research-package-assembly","parent_artifact_ids":[x["artifact_id"] for x in manifest["basis"]["artifacts"]],"provenance":{"schema":PACKAGE_CONTRACT,"package_fingerprint_sha256":manifest["package_fingerprint_sha256"],"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,"wordpress_required":False}})
    return {"schema":PACKAGE_CONTRACT,"package_id":artifact["artifact_id"],"package_fingerprint_sha256":manifest["package_fingerprint_sha256"],"artifact":artifact,"manifest":manifest,"persisted":True,"guardrails":guardrails()}

def get_package(package_id: str, include_manifest: bool=True) -> dict[str,Any]:
    artifact,data=read_artifact_bytes(package_id)
    if artifact.get("artifact_type")!="reproducibility-package": raise KeyError("research-package-not-found")
    manifest=json.loads(data.decode("utf-8")); integrity=_fp(manifest.get("basis") or {})==manifest.get("package_fingerprint_sha256")
    out={"schema":PACKAGE_CONTRACT,"package_id":package_id,"artifact":artifact,"package_fingerprint_sha256":manifest.get("package_fingerprint_sha256"),"manifest_integrity":integrity,"guardrails":guardrails()}
    if include_manifest: out["manifest"]=manifest
    return out

def verify_package(package_id: str, payload: dict[str,Any]|None=None) -> dict[str,Any]:
    payload=payload if isinstance(payload,dict) else {}; package=get_package(package_id,include_manifest=True); manifest=package["manifest"]; basis=manifest.get("basis") or {}; artifact_check=verify_artifact(package_id); errors=[]
    if not package["manifest_integrity"]: errors.append("package-manifest-fingerprint-mismatch")
    if not artifact_check.get("valid"): errors.append("package-artifact-integrity-failed")
    record_checks=[]
    if basis.get("records"):
        ids=[str(x.get("record_id")) for x in basis["records"] if x.get("record_id")]; current={x["record_id"]:x for x in _record_snapshots(ids)}
        for snap in basis["records"]:
            cur=current.get(str(snap.get("record_id"))) or {}; ok=cur.get("content_hash")==snap.get("content_hash") and cur.get("revision")==snap.get("revision")
            record_checks.append({"record_id":snap.get("record_id"),"snapshot_matches_current":ok,"snapshot_content_hash":snap.get("content_hash"),"current_content_hash":cur.get("content_hash")})
    referenced=[]; deep=bool(payload.get("verify_artifact_bytes",False))
    for snap in basis.get("artifacts") or []:
        aid=str(snap.get("artifact_id") or "")
        try:
            current=get_artifact(aid); item={"artifact_id":aid,"metadata_match":current.get("content_sha256")==snap.get("content_sha256") and current.get("byte_length")==snap.get("byte_length")}
            if deep: item["integrity"]=verify_artifact(aid)
            referenced.append(item)
        except KeyError:
            referenced.append({"artifact_id":aid,"metadata_match":False,"missing":True}); errors.append("referenced-artifact-missing:"+aid)
    pipeline_checks=[]
    for snap in basis.get("pipeline_runs") or []:
        rid=str(snap.get("run_id") or "")
        try:
            cur=get_pipeline_run(rid).get("run") or {}; pipeline_checks.append({"run_id":rid,"pipeline_id_match":cur.get("pipeline_id")==snap.get("pipeline_id"),"snapshot_state":snap.get("state"),"current_state":cur.get("state")})
        except KeyError:
            pipeline_checks.append({"run_id":rid,"missing":True}); errors.append("pipeline-run-missing:"+rid)
    replay=[]
    if bool(payload.get("replay_reproducibility_records",False)):
        for record in basis.get("reproducibility_records") or []: replay.append(verify_reproducibility({"record":record,"target_runtime":payload.get("target_runtime") or "auto"}))
    return {"schema":PACKAGE_VERIFICATION_CONTRACT,"package_id":package_id,"verified_at":_now(),"valid":not errors,"errors":errors,"package_artifact_integrity":artifact_check,"manifest_fingerprint_match":package["manifest_integrity"],"record_checks":record_checks,"referenced_artifact_checks":referenced,"pipeline_checks":pipeline_checks,"reproducibility_replay_results":replay,"guardrails":guardrails()}

def readiness() -> dict[str,Any]:
    artifact=artifact_storage_readiness(); pipeline=pipeline_readiness(); runtime=reproducibility_status(); artifact_state=str(artifact.get("state") or "unavailable"); pipeline_state=str(pipeline.get("state") or "unavailable"); state="ready" if artifact_state=="ready" and pipeline_state=="ready" else "degraded"
    return {"schema":READINESS_CONTRACT,"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,"state":state,"authority":"python-backend","wordpress_required":False,"components":{"artifact_storage":artifact,"checkpointed_pipeline":pipeline,"execution_reproducibility":{"state":"ready","schema":runtime.get("schema"),"backend_version":runtime.get("backend_version"),"capabilities":runtime.get("capabilities") or []}},"guardrails":guardrails()}
