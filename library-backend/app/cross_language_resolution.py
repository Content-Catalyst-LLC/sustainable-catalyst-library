from __future__ import annotations

from collections import defaultdict
from hashlib import sha256
import json
import re
import unicodedata
from typing import Any

AUTHORITY_CONTRACT = "sc-library-cross-language-entity-authority/1.0"
NAME_FORM_CONTRACT = "sc-library-entity-name-form/1.0"
TOPONYM_CONTRACT = "sc-library-historical-toponym/1.0"
VALIDATION_CONTRACT = "sc-library-cross-language-authority-validation/1.0"
CANDIDATE_CONTRACT = "sc-library-entity-resolution-candidate/1.0"
CASE_CONTRACT = "sc-library-entity-resolution-case/1.0"
DECISION_CONTRACT = "sc-library-entity-resolution-decision/1.0"
READINESS_CONTRACT = "sc-library-cross-language-resolution-readiness/1.0"

LANGUAGE_RE = re.compile(r"^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$")
SCRIPT_RE = re.compile(r"^[A-Z][a-z]{3}$")
ENTITY_TYPES = {"person","organization","place","work","event","concept","group","jurisdiction","other"}
NAME_RELATIONS = {"canonical","alias","variant","historical","endonym","exonym","transliteration","abbreviation","former","other"}
DECISION_STATES = {"accepted","rejected","ambiguous","unresolved"}


def _canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fingerprint(value: Any) -> str:
    return sha256(_canonical_json(value).encode("utf-8")).hexdigest()


def _clean(value: Any) -> str:
    return str(value or "").strip()


def _year(value: Any) -> int | None:
    if value in (None, ""):
        return None
    n = int(value)
    if n < -10000 or n > 3000:
        raise ValueError("year-out-of-range")
    return n


def normalize_name_key(text: str) -> str:
    """Comparison-only key. The original supplied name is never replaced."""
    text = unicodedata.normalize("NFKC", str(text or "")).casefold()
    chars=[]
    pending_space=False
    for ch in text:
        cat=unicodedata.category(ch)
        if ch.isalnum() or cat.startswith("L") or cat.startswith("M"):
            if pending_space and chars: chars.append(" ")
            chars.append(ch); pending_space=False
        else:
            pending_space=True
    return "".join(chars).strip()


def diacritic_fold_key(text: str) -> str:
    """Comparison-only diacritic fold; this is not transliteration."""
    base=unicodedata.normalize("NFKD", str(text or ""))
    base="".join(ch for ch in base if not unicodedata.combining(ch))
    return normalize_name_key(base)


def _normalize_name_form(raw: dict[str, Any], entity_id: str, index: int, errors: list[str]) -> dict[str, Any]:
    text=_clean(raw.get("text"))
    if not text: errors.append(f"name-{index}-text-required")
    language=_clean(raw.get("language_bcp47") or raw.get("language"))
    if language and not LANGUAGE_RE.match(language): errors.append(f"name-{index}-invalid-language-bcp47")
    script=_clean(raw.get("script_iso15924"))
    if script and not SCRIPT_RE.match(script): errors.append(f"name-{index}-invalid-script-iso15924")
    relation=_clean(raw.get("relation_type")) or "alias"
    if relation not in NAME_RELATIONS: errors.append(f"name-{index}-invalid-relation-type")
    valid_from=_year(raw.get("valid_from_year"))
    valid_to=_year(raw.get("valid_to_year"))
    if valid_from is not None and valid_to is not None and valid_to < valid_from:
        errors.append(f"name-{index}-invalid-validity-window")
    basis={
        "entity_id":entity_id,"text":text,"language_bcp47":language or None,"script_iso15924":script or None,
        "relation_type":relation,"valid_from_year":valid_from,"valid_to_year":valid_to,
        "language_variant":_clean(raw.get("language_variant")) or None,
        "orthography_variant":_clean(raw.get("orthography_variant")) or None,
        "transliteration_system":_clean(raw.get("transliteration_system")) or None,
    }
    form_id=_clean(raw.get("form_id")) or "nameform:"+_fingerprint(basis)[:32]
    return {
        "schema": NAME_FORM_CONTRACT,
        "form_id":form_id,"entity_id":entity_id,"text":text,"normalized_key":normalize_name_key(text),
        "diacritic_fold_key":diacritic_fold_key(text),"language_bcp47":basis["language_bcp47"],
        "script_iso15924":basis["script_iso15924"],"language_variant":basis["language_variant"],
        "orthography_variant":basis["orthography_variant"],"relation_type":relation,
        "transliteration_system":basis["transliteration_system"],"valid_from_year":valid_from,"valid_to_year":valid_to,
        "source_reference":_clean(raw.get("source_reference")) or None,
        "metadata":dict(raw.get("metadata") or {}) if isinstance(raw.get("metadata"),dict) else {},
    }


def validate_authority_payload(payload: dict[str, Any]) -> dict[str, Any]:
    errors=[]; warnings=[]
    if not isinstance(payload,dict):
        return {"schema":VALIDATION_CONTRACT,"valid":False,"errors":["payload-must-be-object"],"warnings":[]}
    raw_entities=payload.get("entities") or []
    if not isinstance(raw_entities,list):
        return {"schema":VALIDATION_CONTRACT,"valid":False,"errors":["entities-must-be-array"],"warnings":[]}
    if not raw_entities: errors.append("at-least-one-entity-required")
    entities=[]; seen=set()
    for i,raw in enumerate(raw_entities,start=1):
        if not isinstance(raw,dict): errors.append(f"entity-{i}-must-be-object"); continue
        entity_type=_clean(raw.get("entity_type")) or "other"
        if entity_type not in ENTITY_TYPES: errors.append(f"entity-{i}-invalid-entity-type")
        canonical=_clean(raw.get("canonical_name"))
        if not canonical: errors.append(f"entity-{i}-canonical-name-required")
        basis={"canonical_name":canonical,"entity_type":entity_type,"authority_namespace":_clean(raw.get("authority_namespace")) or None,"authority_key":_clean(raw.get("authority_key")) or None}
        entity_id=_clean(raw.get("entity_id")) or "entity:"+_fingerprint(basis)[:32]
        if entity_id in seen: errors.append(f"entity-{i}-duplicate-entity-id")
        seen.add(entity_id)
        names_raw=raw.get("names") or []
        if not isinstance(names_raw,list): errors.append(f"entity-{i}-names-must-be-array"); names_raw=[]
        # Canonical name is always represented explicitly.
        if not any(isinstance(n,dict) and _clean(n.get("text"))==canonical and _clean(n.get("relation_type"))=="canonical" for n in names_raw):
            names_raw=[{"text":canonical,"relation_type":"canonical","language_bcp47":raw.get("language_bcp47"),"script_iso15924":raw.get("script_iso15924")},*names_raw]
        names=[_normalize_name_form(n,entity_id,j,errors) for j,n in enumerate(names_raw,start=1) if isinstance(n,dict)]
        if entity_type=="place" and not any(n["relation_type"] in {"canonical","historical","former","endonym","exonym"} for n in names):
            warnings.append(f"entity-{i}-place-without-toponym-form")
        entities.append({
            "schema":AUTHORITY_CONTRACT,"entity_id":entity_id,"entity_type":entity_type,"canonical_name":canonical,
            "authority_namespace":basis["authority_namespace"],"authority_key":basis["authority_key"],
            "country_code":_clean(raw.get("country_code")) or None,
            "latitude":float(raw["latitude"]) if raw.get("latitude") not in (None,"") else None,
            "longitude":float(raw["longitude"]) if raw.get("longitude") not in (None,"") else None,
            "names":names,"metadata":dict(raw.get("metadata") or {}) if isinstance(raw.get("metadata"),dict) else {},
            "provenance":dict(raw.get("provenance") or {}) if isinstance(raw.get("provenance"),dict) else {},
        })
    for e in entities:
        if e["latitude"] is not None and not (-90<=e["latitude"]<=90): errors.append(f"{e['entity_id']}-latitude-out-of-range")
        if e["longitude"] is not None and not (-180<=e["longitude"]<=180): errors.append(f"{e['entity_id']}-longitude-out-of-range")
    prohibited={
        "automatic_entity_merge":"automatic-entity-merge-prohibited","automatic_resolution":"automatic-resolution-prohibited",
        "automatic_translation":"automatic-translation-prohibited","automatic_transliteration":"automatic-transliteration-prohibited",
        "automatic_truth_promotion":"automatic-truth-promotion-prohibited","automatic_evidence_promotion":"automatic-evidence-promotion-prohibited",
    }
    for field,error in prohibited.items():
        if bool(payload.get(field,False)): errors.append(error)
    normalized={"title":_clean(payload.get("title")) or "Cross-language authority registry","entities":entities,"metadata":dict(payload.get("metadata") or {}) if isinstance(payload.get("metadata"),dict) else {}}
    normalized["authority_fingerprint_sha256"]=_fingerprint({"entities":entities,"metadata":normalized["metadata"]})
    return {"schema":VALIDATION_CONTRACT,"valid":not errors,"errors":errors,"warnings":warnings,"normalized":normalized,"guardrails":guardrails()}


def build_authority_package(payload: dict[str, Any]) -> dict[str, Any]:
    validation=validate_authority_payload(payload)
    if not validation["valid"]: raise ValueError("; ".join(validation["errors"]))
    n=validation["normalized"]
    return {"schema":AUTHORITY_CONTRACT,"registry_id":"authority:"+n["authority_fingerprint_sha256"][:32],"authority_fingerprint_sha256":n["authority_fingerprint_sha256"],"title":n["title"],"entity_count":len(n["entities"]),"name_form_count":sum(len(e["names"]) for e in n["entities"]),"entities":n["entities"],"metadata":n["metadata"],"guardrails":validation["guardrails"],"persisted":False}


def _temporal_status(form: dict[str,Any], query_year: int|None) -> str:
    if query_year is None: return "not-evaluated"
    start=form.get("valid_from_year"); end=form.get("valid_to_year")
    if start is not None and query_year < start: return "outside-window"
    if end is not None and query_year > end: return "outside-window"
    if start is None and end is None: return "undated"
    return "within-window"


def generate_candidates(authority_package: dict[str,Any], query: dict[str,Any], *, limit:int=25) -> dict[str,Any]:
    name=_clean(query.get("name") or query.get("query"))
    if not name: raise ValueError("query-name-required")
    if limit<1 or limit>100: raise ValueError("limit must be between 1 and 100")
    qlang=_clean(query.get("language_bcp47") or query.get("language")) or None
    qscript=_clean(query.get("script_iso15924")) or None
    qtype=_clean(query.get("entity_type")) or None
    if qlang and not LANGUAGE_RE.match(qlang): raise ValueError("invalid-query-language-bcp47")
    if qscript and not SCRIPT_RE.match(qscript): raise ValueError("invalid-query-script-iso15924")
    if qtype and qtype not in ENTITY_TYPES: raise ValueError("invalid-query-entity-type")
    qyear=_year(query.get("year"))
    query_forms=[name]+[_clean(x) for x in (query.get("alternate_forms") or []) if _clean(x)]
    qkeys={normalize_name_key(x) for x in query_forms}; qfolds={diacritic_fold_key(x) for x in query_forms}
    rows=[]
    for entity in authority_package.get("entities") or []:
        for form in entity.get("names") or []:
            signals=[]; score=0.0
            exact=form.get("normalized_key") in qkeys
            folded=form.get("diacritic_fold_key") in qfolds
            if exact: score+=0.65; signals.append("normalized-name-exact")
            elif folded: score+=0.45; signals.append("diacritic-fold-match")
            else: continue
            if form.get("relation_type")=="transliteration": score+=0.08; signals.append("declared-transliteration-form")
            if qlang and form.get("language_bcp47")==qlang: score+=0.07; signals.append("language-match")
            if qscript and form.get("script_iso15924")==qscript: score+=0.05; signals.append("script-match")
            if qtype and entity.get("entity_type")==qtype: score+=0.05; signals.append("entity-type-match")
            temporal=_temporal_status(form,qyear)
            if temporal=="within-window": score+=0.10; signals.append("historical-validity-match")
            elif temporal=="outside-window": score-=0.20; signals.append("historical-validity-conflict")
            score=max(0.0,min(1.0,score))
            basis={"entity_id":entity["entity_id"],"form_id":form["form_id"],"query":name,"query_year":qyear,"query_language":qlang,"query_script":qscript}
            rows.append({
                "schema":CANDIDATE_CONTRACT,"candidate_id":"resolution-candidate:"+_fingerprint(basis)[:32],
                "entity_id":entity["entity_id"],"entity_type":entity["entity_type"],"canonical_name":entity["canonical_name"],
                "matched_form_id":form["form_id"],"matched_form":form["text"],"relation_type":form["relation_type"],
                "language_bcp47":form.get("language_bcp47"),"script_iso15924":form.get("script_iso15924"),
                "valid_from_year":form.get("valid_from_year"),"valid_to_year":form.get("valid_to_year"),"temporal_status":temporal,
                "score":round(score,6),"signals":signals,"score_is_probability":False,"candidate_is_resolved_identity":False,
            })
    # retain highest scoring name-form candidate per entity while preserving matched form provenance
    best={}
    for row in rows:
        current=best.get(row["entity_id"])
        if current is None or (-row["score"],row["candidate_id"]) < (-current["score"],current["candidate_id"]): best[row["entity_id"]]=row
    ranked=sorted(best.values(),key=lambda r:(-r["score"],r["canonical_name"].casefold(),r["entity_id"]))[:limit]
    for i,row in enumerate(ranked,start=1): row["rank"]=i
    return {
        "schema":CASE_CONTRACT,"query":{"name":name,"alternate_forms":query_forms[1:],"language_bcp47":qlang,"script_iso15924":qscript,"entity_type":qtype,"year":qyear},
        "query_fingerprint_sha256":_fingerprint({"name":name,"alternate_forms":query_forms[1:],"language":qlang,"script":qscript,"entity_type":qtype,"year":qyear}),
        "authority_fingerprint_sha256":authority_package.get("authority_fingerprint_sha256"),"candidate_count":len(ranked),"candidates":ranked,
        "ambiguity":{"top_score_tied":len(ranked)>1 and ranked[0]["score"]==ranked[1]["score"],"automatic_decision":False},
        "guardrails":guardrails(),
    }


def build_resolution_case(authority_payload: dict[str,Any], query: dict[str,Any], *, limit:int=25) -> dict[str,Any]:
    authority=build_authority_package(authority_payload)
    result=generate_candidates(authority,query,limit=limit)
    case_id="resolution-case:"+_fingerprint({"authority":authority["authority_fingerprint_sha256"],"query":result["query_fingerprint_sha256"]})[:32]
    return {**result,"case_id":case_id,"persisted":False}


def validate_decision_payload(case: dict[str,Any], payload: dict[str,Any]) -> dict[str,Any]:
    errors=[]
    state=_clean(payload.get("state")) or "unresolved"
    if state not in DECISION_STATES: errors.append("invalid-decision-state")
    selected=_clean(payload.get("selected_candidate_id")) or None
    candidate_ids={c.get("candidate_id") for c in case.get("candidates") or []}
    if selected and selected not in candidate_ids: errors.append("selected-candidate-not-in-case")
    if state=="accepted" and not selected: errors.append("accepted-decision-requires-selected-candidate")
    if state in {"ambiguous","unresolved","rejected"} and selected: errors.append("non-accepted-decision-must-not-select-candidate")
    adjudicator=_clean(payload.get("adjudicator")) or None
    rationale=_clean(payload.get("rationale")) or None
    if state in {"accepted","rejected","ambiguous"} and not rationale: errors.append("decision-rationale-required")
    normalized={"state":state,"selected_candidate_id":selected,"adjudicator":adjudicator,"rationale":rationale,"evidence_refs":[_clean(x) for x in (payload.get("evidence_refs") or []) if _clean(x)],"metadata":dict(payload.get("metadata") or {}) if isinstance(payload.get("metadata"),dict) else {}}
    normalized["decision_fingerprint_sha256"]=_fingerprint({"case_id":case.get("case_id"),**normalized})
    return {"schema":DECISION_CONTRACT,"valid":not errors,"errors":errors,"normalized":normalized,"guardrails":{"decision_is_truth":False,"decision_is_evidence":False,"human_or_explicit_adjudication_required":True}}


def guardrails() -> dict[str,Any]:
    return {
        "candidate_score_is_probability":False,"candidate_rank_is_truth":False,"name_similarity_establishes_identity":False,
        "historical_toponym_overlap_establishes_identity":False,"automatic_entity_merge":False,"automatic_resolution":False,
        "automatic_translation":False,"automatic_transliteration":False,"explicit_transliteration_forms_supported":True,
        "ambiguity_preserved":True,"automatic_evidence_promotion":False,"automatic_truth_promotion":False,
        "automatic_platform_core_promotion":False,
    }


def ingest_authority_registry(payload: dict[str,Any]) -> dict[str,Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    package=build_authority_package(payload); pool=get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            for entity in package["entities"]:
                cur.execute("""INSERT INTO library_cross_language_entities(entity_id,entity_type,canonical_name,authority_namespace,authority_key,country_code,latitude,longitude,metadata,provenance) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT (entity_id) DO NOTHING""",(entity["entity_id"],entity["entity_type"],entity["canonical_name"],entity["authority_namespace"],entity["authority_key"],entity["country_code"],entity["latitude"],entity["longitude"],Jsonb(entity["metadata"]),Jsonb(entity["provenance"])))
                for form in entity["names"]:
                    cur.execute("""INSERT INTO library_entity_name_forms(form_id,entity_id,name_text,normalized_key,diacritic_fold_key,language_bcp47,script_iso15924,language_variant,orthography_variant,relation_type,transliteration_system,valid_from_year,valid_to_year,source_reference,metadata) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT (form_id) DO NOTHING""",(form["form_id"],entity["entity_id"],form["text"],form["normalized_key"],form["diacritic_fold_key"],form["language_bcp47"],form["script_iso15924"],form["language_variant"],form["orthography_variant"],form["relation_type"],form["transliteration_system"],form["valid_from_year"],form["valid_to_year"],form["source_reference"],Jsonb(form["metadata"])))
        conn.commit()
    package["persisted"]=True
    return package


def _load_persisted_authority() -> dict[str,Any]:
    from .db import get_pool
    pool=get_pool(); entities=[]
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_cross_language_entities ORDER BY entity_id")
        entity_rows=[dict(r) for r in cur.fetchall()]
        cur.execute("SELECT * FROM library_entity_name_forms ORDER BY entity_id, created_at, form_id")
        forms=[dict(r) for r in cur.fetchall()]
    by_entity=defaultdict(list)
    for f in forms:
        by_entity[f["entity_id"]].append({"schema":NAME_FORM_CONTRACT,"form_id":f["form_id"],"entity_id":f["entity_id"],"text":f["name_text"],"normalized_key":f["normalized_key"],"diacritic_fold_key":f["diacritic_fold_key"],"language_bcp47":f["language_bcp47"],"script_iso15924":f["script_iso15924"],"language_variant":f["language_variant"],"orthography_variant":f["orthography_variant"],"relation_type":f["relation_type"],"transliteration_system":f["transliteration_system"],"valid_from_year":f["valid_from_year"],"valid_to_year":f["valid_to_year"],"source_reference":f["source_reference"],"metadata":f["metadata"] or {}})
    for e in entity_rows:
        entities.append({"schema":AUTHORITY_CONTRACT,"entity_id":e["entity_id"],"entity_type":e["entity_type"],"canonical_name":e["canonical_name"],"authority_namespace":e["authority_namespace"],"authority_key":e["authority_key"],"country_code":e["country_code"],"latitude":e["latitude"],"longitude":e["longitude"],"names":by_entity[e["entity_id"]],"metadata":e["metadata"] or {},"provenance":e["provenance"] or {}})
    fingerprint=_fingerprint({"entities":entities})
    return {"schema":AUTHORITY_CONTRACT,"registry_id":"authority:"+fingerprint[:32],"authority_fingerprint_sha256":fingerprint,"title":"Persisted cross-language authority registry","entity_count":len(entities),"name_form_count":sum(len(e["names"]) for e in entities),"entities":entities,"guardrails":guardrails(),"persisted":True}


def ingest_resolution_case(query: dict[str,Any], *, limit:int=25) -> dict[str,Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    authority=_load_persisted_authority(); result=generate_candidates(authority,query,limit=limit)
    case_id="resolution-case:"+_fingerprint({"authority":authority["authority_fingerprint_sha256"],"query":result["query_fingerprint_sha256"]})[:32]
    result={**result,"case_id":case_id,"persisted":True}
    pool=get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute("""INSERT INTO library_entity_resolution_cases(case_id,query_payload,query_fingerprint,authority_fingerprint,candidate_count,ambiguity,guardrails) VALUES (%s,%s,%s,%s,%s,%s,%s) ON CONFLICT (case_id) DO NOTHING""",(case_id,Jsonb(result["query"]),result["query_fingerprint_sha256"],authority["authority_fingerprint_sha256"],result["candidate_count"],Jsonb(result["ambiguity"]),Jsonb(result["guardrails"])))
            for c in result["candidates"]:
                cur.execute("""INSERT INTO library_entity_resolution_candidates(candidate_id,case_id,entity_id,matched_form_id,rank,score,signals,temporal_status,payload) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT (candidate_id) DO NOTHING""",(c["candidate_id"],case_id,c["entity_id"],c["matched_form_id"],c["rank"],c["score"],Jsonb(c["signals"]),c["temporal_status"],Jsonb(c)))
        conn.commit()
    return result


def get_resolution_case(case_id: str) -> dict[str,Any]:
    from .db import get_pool
    pool=get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_entity_resolution_cases WHERE case_id=%s",(case_id,)); case=cur.fetchone()
        if not case: raise KeyError(case_id)
        cur.execute("SELECT payload FROM library_entity_resolution_candidates WHERE case_id=%s ORDER BY rank ASC,candidate_id ASC",(case_id,)); candidates=[r["payload"] for r in cur.fetchall()]
        cur.execute("SELECT * FROM library_entity_resolution_decisions WHERE case_id=%s ORDER BY created_at DESC LIMIT 1",(case_id,)); decision=cur.fetchone()
    return {"schema":CASE_CONTRACT,"case_id":case_id,"query":case["query_payload"],"query_fingerprint_sha256":case["query_fingerprint"],"authority_fingerprint_sha256":case["authority_fingerprint"],"candidate_count":case["candidate_count"],"candidates":candidates,"ambiguity":case["ambiguity"],"guardrails":case["guardrails"],"latest_decision":dict(decision) if decision else None,"persisted":True}


def ingest_resolution_decision(case_id: str, payload: dict[str,Any]) -> dict[str,Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    case=get_resolution_case(case_id); validation=validate_decision_payload(case,payload)
    if not validation["valid"]: raise ValueError("; ".join(validation["errors"]))
    n=validation["normalized"]; decision_id="resolution-decision:"+n["decision_fingerprint_sha256"][:32]
    pool=get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("""INSERT INTO library_entity_resolution_decisions(decision_id,case_id,state,selected_candidate_id,adjudicator,rationale,evidence_refs,metadata,decision_fingerprint) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT (decision_id) DO NOTHING""",(decision_id,case_id,n["state"],n["selected_candidate_id"],n["adjudicator"],n["rationale"],Jsonb(n["evidence_refs"]),Jsonb(n["metadata"]),n["decision_fingerprint_sha256"]))
        conn.commit()
    return {"schema":DECISION_CONTRACT,"decision_id":decision_id,"case_id":case_id,**n,"guardrails":validation["guardrails"],"persisted":True}


def readiness() -> dict[str,Any]:
    counts={"entities":0,"name_forms":0,"resolution_cases":0,"resolution_candidates":0,"resolution_decisions":0}; state="ready"
    try:
        from .db import get_pool
        pool=get_pool()
        with pool.connection(timeout=3) as conn, conn.cursor() as cur:
            for key,table in [("entities","library_cross_language_entities"),("name_forms","library_entity_name_forms"),("resolution_cases","library_entity_resolution_cases"),("resolution_candidates","library_entity_resolution_candidates"),("resolution_decisions","library_entity_resolution_decisions")]:
                cur.execute(f"SELECT count(*) AS n FROM {table}"); counts[key]=int(cur.fetchone()["n"])
    except Exception:
        state="schema-unavailable"
    return {"schema":READINESS_CONTRACT,"version":"5.48.0","backend_version":"2.59.0","state":state,"counts":counts,
            "capabilities":{"cross_language_entity_authorities":True,"multilingual_name_forms":True,"explicit_transliteration_forms":True,"historical_toponym_validity_windows":True,"temporal_context_candidate_ranking":True,"ambiguity_preservation":True,"explicit_resolution_decisions":True,"signed_persistence":True},
            "guardrails":guardrails(),"lineage":{"linguistic_corpus":"v5.47.0","ocr_htr_transcription":"v5.46.0","original_language":"v5.45.0"},
            "next_lineage":{"translation_transliteration_alignment_matrix":"v5.49.0"}}
