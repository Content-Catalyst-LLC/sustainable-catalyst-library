from __future__ import annotations
from hashlib import sha256
import json
from typing import Any
LIBRARY_VERSION = "5.78.0"; BACKEND_VERSION = "2.89.0"
CONTRACT="sc-library-independent-release-engineering/1.0"; READINESS_CONTRACT="sc-library-independent-release-engineering-readiness/1.0"
def _canon(v:Any)->str: return json.dumps(v,sort_keys=True,separators=(",",":"),ensure_ascii=False,default=str)
def _fp(v:Any)->str: return sha256(_canon(v).encode()).hexdigest()
def guardrails(): return {"wordpress_required_for_library_release":False,"wordpress_required_for_backend_deploy":False,"wordpress_required_for_web_deploy":False,"artifact_sha256_required":True,"preflight_required":True,"rollback_plan_required":True,"immutable_release_manifest":True,"deployment_is_environment_explicit":True,"release_success_implies_research_truth":False,"automatic_platform_core_promotion":False}
def release_manifest(environment="production"):
    basis={"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,"web_version":"1.2.0","api_version":"1.0","environment":environment,"components":["library-backend","library-web","postgresql","redis","workers","artifact-storage","native-runtimes"],"wordpress":{"role":"optional-thin-adapter","required":False},"guardrails":guardrails()}
    return {"schema":CONTRACT,"release_id":"library-release:"+_fp(basis)[:32],"manifest_sha256":_fp(basis),**basis}
def validate_deployment_plan(payload:dict[str,Any]):
    errors=[]; env=str(payload.get("environment") or "")
    if env not in {"staging","production","development"}: errors.append("unsupported-environment")
    arts=payload.get("artifacts") if isinstance(payload.get("artifacts"),list) else []
    if not arts: errors.append("artifacts-required")
    for i,a in enumerate(arts):
        if not isinstance(a,dict) or not a.get("name") or not a.get("sha256"): errors.append(f"artifact[{i}]-name-and-sha256-required")
    if payload.get("wordpress_required") is True: errors.append("wordpress-release-dependency-prohibited")
    if not payload.get("rollback_plan"): errors.append("rollback-plan-required")
    return {"schema":"sc-library-deployment-plan-validation/1.0","valid":not errors,"errors":errors,"guardrails":guardrails()}
def readiness(): return {"schema":READINESS_CONTRACT,"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,"state":"ready","release_manifest":release_manifest(),"guardrails":guardrails()}
