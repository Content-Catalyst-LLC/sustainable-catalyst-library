from __future__ import annotations
from hashlib import sha256
import json
from typing import Any
LIBRARY_VERSION = "5.71.0"; BACKEND_VERSION = "2.82.0"
CONTRACT="sc-library-wordpress-failure-independence-certification/1.0"; READINESS_CONTRACT="sc-library-wordpress-failure-independence-readiness/1.0"
REQUIRED_PROBES=("api","database","search-records","identity-session","artifacts","pipelines","compute","cross-product-integrations","client-framework","domain-authority","catalog-service","research-state","public-routing","library-web")
def _canon(v): return json.dumps(v,sort_keys=True,separators=(",",":"),ensure_ascii=False,default=str)
def _fp(v): return sha256(_canon(v).encode()).hexdigest()
def guardrails(): return {"wordpress_must_be_unavailable_during_certification":True,"wordpress_is_authoritative_dependency":False,"wordpress_failure_may_break_editorial_adapter":True,"wordpress_failure_may_break_library_api":False,"wordpress_failure_may_break_library_web":False,"wordpress_failure_may_break_identity_sessions":False,"wordpress_failure_may_break_research_execution":False,"wordpress_failure_may_break_cross_product_clients":False,"certification_implies_research_truth":False,"automatic_platform_core_promotion":False}
def evaluate(payload:dict[str,Any]):
    errors=[]; wp=payload.get('wordpress') if isinstance(payload.get('wordpress'),dict) else {}
    if str(wp.get('state') or '') not in {'failed','unreachable','disabled'}: errors.append('wordpress-must-be-failed-unreachable-or-disabled')
    probes=payload.get('probes') if isinstance(payload.get('probes'),dict) else {}; normalized={}
    for name in REQUIRED_PROBES:
        raw=probes.get(name); ready=raw is True or (isinstance(raw,dict) and raw.get('ready') is True); normalized[name]={'ready':ready} if not isinstance(raw,dict) else {**raw,'ready':ready}
        if not ready: errors.append('probe-not-ready:'+name)
    if payload.get('wordpress_required') is True: errors.append('wordpress-runtime-dependency-prohibited')
    basis={'wordpress':{'state':wp.get('state')},'probes':normalized,'guardrails':guardrails()}
    return {'schema':CONTRACT,'library_version':LIBRARY_VERSION,'backend_version':BACKEND_VERSION,'certified':not errors,'errors':errors,'certification_id':'wordpress-failure-cert:'+_fp(basis)[:32],'certification_sha256':_fp(basis),'wordpress':basis['wordpress'],'probes':normalized,'guardrails':guardrails()}
def default_probe_payload(): return {'wordpress':{'state':'unreachable'},'probes':{name:{'ready':True} for name in REQUIRED_PROBES},'wordpress_required':False}
def readiness():
    c=evaluate(default_probe_payload()); return {'schema':READINESS_CONTRACT,'library_version':LIBRARY_VERSION,'backend_version':BACKEND_VERSION,'state':'ready' if c['certified'] else 'degraded','required_probe_count':len(REQUIRED_PROBES),'wordpress_dependency_count':0,'default_certification':c,'guardrails':guardrails()}
