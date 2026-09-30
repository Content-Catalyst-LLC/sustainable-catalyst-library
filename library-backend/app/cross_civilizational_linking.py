from __future__ import annotations
from hashlib import sha256
import json,re
from typing import Any
LINK_CONTRACT="sc-library-cross-civilizational-evidence-link/1.0"
PACKAGE_CONTRACT="sc-library-cross-civilizational-link-package/1.0"
VALIDATION_CONTRACT="sc-library-cross-civilizational-link-validation/1.0"
READINESS_CONTRACT="sc-library-cross-civilizational-linking-readiness/1.0"
OBJECT_TYPES={"text-representation","alignment-matrix","entity-authority","publication","claim","finding","dataset","observation","measurement","scientific-object","geospatial-object","timeline-event","artifact","corpus","record","external-source"}
EVIDENCE_CLASSES={"textual","documentary","archaeological","observational","experimental","environmental","geospatial","statistical","clinical","administrative","economic","computational","dataset","other"}
LINK_TYPES={"corroborates","contrasts","contextualizes","same-phenomenon","measurement-correspondence","methodologically-comparable","temporal-overlap","temporal-predecessor","temporal-successor","geospatial-overlap","translation-correspondence","citation-reference","derived-from","uncertain"}
REVIEW_STATES={"unreviewed","machine-reviewed","human-reviewed","accepted","rejected","needs-review"}
LANGUAGE_RE=re.compile(r"^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$"); SCRIPT_RE=re.compile(r"^[A-Z][a-z]{3}$")
def _clean(v): return str(v or "").strip()
def _canon(v): return json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(",",":"),default=str)
def _fp(v): return sha256(_canon(v).encode()).hexdigest()
def guardrails(): return {"link_is_evidence_truth":False,"link_is_causal_proof":False,"link_is_consensus":False,"link_implies_civilizational_equivalence":False,"link_implies_methodological_equivalence":False,"link_implies_unit_equivalence":False,"link_implies_translation_equivalence":False,"confidence_is_truth_probability":False,"automatic_claim_creation":False,"automatic_evidence_promotion":False,"automatic_platform_core_promotion":False,"source_traditions_remain_distinct":True,"scientific_methods_remain_distinct":True,"original_language_context_preserved":True,"temporal_geographic_context_preserved":True,"explicit_interpretation_boundary_required":True}
def _time(raw,prefix,errors):
 if raw in (None,"",{}): return None
 if not isinstance(raw,dict): errors.append(f"{prefix}-time-context-must-be-object"); return None
 out={"start":_clean(raw.get("start")) or None,"end":_clean(raw.get("end")) or None,"label":_clean(raw.get("label")) or None,"calendar":_clean(raw.get("calendar")) or None,"precision":_clean(raw.get("precision")) or None}
 if not any(out.values()): errors.append(f"{prefix}-time-context-empty")
 return out
def _geo(raw,prefix,errors):
 if raw in (None,"",{}): return None
 if not isinstance(raw,dict): errors.append(f"{prefix}-geographic-context-must-be-object"); return None
 out={"place_id":_clean(raw.get("place_id")) or None,"name":_clean(raw.get("name")) or None,"country_code":_clean(raw.get("country_code")) or None,"region":_clean(raw.get("region")) or None}
 if raw.get("lat") is not None or raw.get("lon") is not None:
  try:
   lat=float(raw.get("lat")); lon=float(raw.get("lon")); assert -90<=lat<=90 and -180<=lon<=180; out.update({"lat":lat,"lon":lon})
  except Exception: errors.append(f"{prefix}-invalid-coordinate")
 if not any(v is not None for v in out.values()): errors.append(f"{prefix}-geographic-context-empty")
 return out
def _obj(raw,role,errors):
 if not isinstance(raw,dict): errors.append(f"{role}-object-must-be-object"); raw={}
 oid=_clean(raw.get("object_id") or raw.get("id")); ot=_clean(raw.get("object_type")); ec=_clean(raw.get("evidence_class")) or "other"; lang=_clean(raw.get("language_bcp47")); script=_clean(raw.get("script_iso15924"))
 if not oid: errors.append(f"{role}-object-id-required")
 if ot not in OBJECT_TYPES: errors.append(f"{role}-invalid-object-type")
 if ec not in EVIDENCE_CLASSES: errors.append(f"{role}-invalid-evidence-class")
 if lang and not LANGUAGE_RE.match(lang): errors.append(f"{role}-invalid-language-bcp47")
 if script and not SCRIPT_RE.match(script): errors.append(f"{role}-invalid-script-iso15924")
 method=raw.get("method") if isinstance(raw.get("method"),dict) else {}; meas=raw.get("measurement") if isinstance(raw.get("measurement"),dict) else {}
 return {"object_id":oid,"object_type":ot,"evidence_class":ec,"source_id":_clean(raw.get("source_id")) or None,"institution_id":_clean(raw.get("institution_id")) or None,"civilization_id":_clean(raw.get("civilization_id")) or None,"tradition_id":_clean(raw.get("tradition_id")) or None,"language_bcp47":lang or None,"script_iso15924":script or None,"representation_id":_clean(raw.get("representation_id")) or None,"alignment_matrix_id":_clean(raw.get("alignment_matrix_id")) or None,"time_context":_time(raw.get("time_context"),role,errors),"geographic_context":_geo(raw.get("geographic_context"),role,errors),"method":{"method_id":_clean(method.get("method_id")) or None,"method_type":_clean(method.get("method_type")) or None,"description":_clean(method.get("description")) or None} if method else None,"measurement":{"variable":_clean(meas.get("variable")) or None,"unit":_clean(meas.get("unit")) or None,"value":meas.get("value"),"uncertainty":meas.get("uncertainty")} if meas else None,"content_sha256":_clean(raw.get("content_sha256")) or None,"provenance":dict(raw.get("provenance") or {}) if isinstance(raw.get("provenance"),dict) else {},"metadata":dict(raw.get("metadata") or {}) if isinstance(raw.get("metadata"),dict) else {}}
def validate_link_payload(payload):
 errors=[]; warnings=[]
 if not isinstance(payload,dict): return {"schema":VALIDATION_CONTRACT,"valid":False,"errors":["payload-must-be-object"],"warnings":[],"guardrails":guardrails()}
 src=_obj(payload.get("source"),"source",errors); tgt=_obj(payload.get("target"),"target",errors); lt=_clean(payload.get("link_type")); rs=_clean(payload.get("review_state")) or "unreviewed"; ib=_clean(payload.get("interpretation_boundary") or payload.get("rationale")); basis=payload.get("basis") if isinstance(payload.get("basis"),dict) else {}
 if lt not in LINK_TYPES: errors.append("invalid-link-type")
 if src["object_id"] and src["object_id"]==tgt["object_id"] and src["object_type"]==tgt["object_type"]: errors.append("source-and-target-must-differ")
 if rs not in REVIEW_STATES: errors.append("invalid-review-state")
 if not ib: errors.append("interpretation-boundary-required")
 if not basis: errors.append("link-basis-required")
 conf=payload.get("confidence")
 if conf is not None:
  try: conf=float(conf)
  except Exception: errors.append("invalid-confidence"); conf=None
  if conf is not None and not 0<=conf<=1: errors.append("confidence-out-of-range")
 if lt=="measurement-correspondence":
  if not src.get("measurement") or not tgt.get("measurement"): errors.append("measurement-correspondence-requires-measurements")
  su=(src.get("measurement") or {}).get("unit"); tu=(tgt.get("measurement") or {}).get("unit")
  if su and tu and su!=tu and not basis.get("unit_conversion"): warnings.append("measurement-units-differ-without-explicit-conversion")
 if payload.get("promote_to_core") is True: errors.append("automatic-platform-core-promotion-prohibited")
 if payload.get("create_claim") is True: errors.append("automatic-claim-creation-prohibited")
 n={"source":src,"target":tgt,"link_type":lt,"basis":basis,"interpretation_boundary":ib,"confidence":conf,"confidence_kind":_clean(payload.get("confidence_kind")) or ("declared" if conf is not None else None),"review_state":rs,"provenance":dict(payload.get("provenance") or {}) if isinstance(payload.get("provenance"),dict) else {},"metadata":dict(payload.get("metadata") or {}) if isinstance(payload.get("metadata"),dict) else {}}
 return {"schema":VALIDATION_CONTRACT,"valid":not errors,"errors":errors,"warnings":warnings,"normalized":n,"guardrails":guardrails()}
def build_link(payload):
 v=validate_link_payload(payload)
 if not v["valid"]: raise ValueError(";".join(v["errors"]))
 n=v["normalized"]; fp=_fp({k:n[k] for k in ("source","target","link_type","basis","interpretation_boundary")})
 return {"schema":LINK_CONTRACT,"link_id":"cross-civilizational-link:"+fp[:32],"link_fingerprint_sha256":fp,**n,"warnings":v["warnings"],"guardrails":v["guardrails"],"persisted":False}
def build_link_package(payload):
 raw=payload.get("links") if isinstance(payload,dict) else None
 if raw is None: raw=[payload]
 if not isinstance(raw,list) or not raw: raise ValueError("at-least-one-link-required")
 links=[build_link(x) for x in raw]; fp=_fp([x["link_fingerprint_sha256"] for x in links]); return {"schema":PACKAGE_CONTRACT,"package_id":"cross-civilizational-link-package:"+fp[:32],"package_fingerprint_sha256":fp,"link_count":len(links),"links":links,"guardrails":guardrails()}
def ingest_link(payload):
 from psycopg.types.json import Jsonb
 from .db import get_pool
 x=build_link(payload)
 with get_pool().connection() as conn, conn.cursor() as cur:
  cur.execute("""INSERT INTO library_cross_civilizational_links(link_id,source_object_id,source_object_type,target_object_id,target_object_type,source_evidence_class,target_evidence_class,link_type,basis,interpretation_boundary,confidence,confidence_kind,review_state,source_context,target_context,link_fingerprint,provenance,metadata) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT(link_id) DO NOTHING""",(x["link_id"],x["source"]["object_id"],x["source"]["object_type"],x["target"]["object_id"],x["target"]["object_type"],x["source"]["evidence_class"],x["target"]["evidence_class"],x["link_type"],Jsonb(x["basis"]),x["interpretation_boundary"],x["confidence"],x["confidence_kind"],x["review_state"],Jsonb(x["source"]),Jsonb(x["target"]),x["link_fingerprint_sha256"],Jsonb(x["provenance"]),Jsonb(x["metadata"])))
  cur.execute("INSERT INTO library_cross_civilizational_link_events(link_id,event_type,details) VALUES (%s,'created',%s)",(x["link_id"],Jsonb({"review_state":x["review_state"],"link_type":x["link_type"]}))); conn.commit()
 x["persisted"]=True; return x
def get_link(link_id):
 from .db import get_pool
 with get_pool().connection() as conn, conn.cursor() as cur:
  cur.execute("SELECT * FROM library_cross_civilizational_links WHERE link_id=%s",(_clean(link_id),)); row=cur.fetchone()
  if not row: raise KeyError(link_id)
 r=dict(row); return {"schema":LINK_CONTRACT,"link_id":r["link_id"],"link_fingerprint_sha256":r["link_fingerprint"],"source":r["source_context"],"target":r["target_context"],"link_type":r["link_type"],"basis":r["basis"],"interpretation_boundary":r["interpretation_boundary"],"confidence":r["confidence"],"confidence_kind":r["confidence_kind"],"review_state":r["review_state"],"provenance":r["provenance"] or {},"metadata":r["metadata"] or {},"guardrails":guardrails(),"persisted":True}
def readiness():
 counts={"links":0,"reviewed_links":0,"cross_language_links":0,"measurement_links":0}; state="ready"
 try:
  from .db import get_pool
  with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
   cur.execute("SELECT count(*) AS n,count(*) FILTER (WHERE review_state IN ('human-reviewed','accepted')) AS reviewed,count(*) FILTER (WHERE link_type='measurement-correspondence') AS measurement FROM library_cross_civilizational_links"); row=cur.fetchone(); counts.update({"links":int(row["n"]),"reviewed_links":int(row["reviewed"]),"measurement_links":int(row["measurement"])})
   cur.execute("SELECT count(*) AS n FROM library_cross_civilizational_links WHERE COALESCE(source_context->>'language_bcp47','') <> COALESCE(target_context->>'language_bcp47','')"); counts["cross_language_links"]=int(cur.fetchone()["n"])
 except Exception: state="schema-unavailable"
 return {"schema":READINESS_CONTRACT,"version":"5.55.0","backend_version":"2.66.0","state":state,"counts":counts,"capabilities":{"cross_civilizational_links":True,"text_to_scientific_data_links":True,"scientific_data_to_scientific_data_links":True,"temporal_context":True,"geographic_context":True,"method_context":True,"measurement_context":True,"translation_alignment_reference":True,"explicit_interpretation_boundary":True,"signed_persistence":True,"durable_worker_capability":"knowledge.link.cross-civilizational"},"guardrails":guardrails(),"lineage":{"original_language":"v5.45.0","linguistic_corpus":"v5.47.0","cross_language_resolution":"v5.48.0","distributed_compute":"v5.53.0","translation_alignment":"v5.54.0"},"next_lineage":{"source_transparency_quality_signals_user_trust":"v5.56.0"}}
