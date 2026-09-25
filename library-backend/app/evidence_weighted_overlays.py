from __future__ import annotations

from collections import defaultdict
from typing import Any

from .db import get_pool

OVERLAY_CONTRACT = "sc-library-evidence-weighted-research-overlay/1.0"
EXPLICIT_SUPPORT = {"supports", "corroborates", "consistent-with", "reinforces"}
EXPLICIT_CONTRADICTION = {"contradicts", "challenges", "rebuts", "disputes", "inconsistent-with"}


def _bounded(value: Any) -> float:
    try:
        return max(0.0, min(1.0, float(value)))
    except Exception:
        return 0.0


def _traceability_weight(row: dict[str, Any], current_hash: str | None) -> float:
    """Rendering weight for source traceability, never epistemic truth confidence."""
    confidence = _bounded(row.get("confidence"))
    has_locator = 1.0 if str(row.get("source_locator") or "").strip() else 0.0
    current = 1.0 if current_hash and str(row.get("source_content_hash") or "") == current_hash else 0.0
    reviewed = 1.0 if str(row.get("reviewer") or "").strip() else 0.0
    return round(.50 * confidence + .20 * has_locator + .20 * current + .10 * reviewed, 6)


def build_evidence_weighted_overlays(records: dict[str, dict[str, Any]]) -> dict[str, Any]:
    record_ids = list(records)
    if not record_ids:
        return {"schema": OVERLAY_CONTRACT, "items": [], "relations": [], "publication_summaries": [], "metrics": {"finding_count": 0, "claim_count": 0, "support_relation_count": 0, "contradiction_relation_count": 0}}

    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT candidate_id,candidate_key,record_id,candidate_type,candidate_text,extraction_method,
                   confidence,source_locator,source_chunk_ordinal,char_start,char_end,source_content_hash,
                   review_state,reviewer,review_note,reviewed_at,core_operation,core_outbox_event_id,metadata
              FROM library_research_candidates
             WHERE record_id=ANY(%s)
               AND candidate_type IN ('finding','claim')
               AND review_state='accepted'
             ORDER BY record_id,candidate_type,confidence DESC,candidate_id ASC
            """,
            (record_ids,),
        )
        rows=[dict(r) for r in cur.fetchall()]

    items=[]; by_id={}; by_key={}; per_pub=defaultdict(lambda:{"findings":0,"claims":0,"current_source_bound":0,"core_handoff_count":0})
    for row in rows:
        rid=str(row.get('record_id') or '')
        typ=str(row.get('candidate_type') or '')
        nid=f"{typ}:{int(row['candidate_id'])}"
        current_hash=str((records.get(rid) or {}).get('content_hash') or '') or None
        weight=_traceability_weight(row,current_hash)
        current=bool(current_hash and str(row.get('source_content_hash') or '')==current_hash)
        meta=row.get('metadata') if isinstance(row.get('metadata'),dict) else {}
        item={
            "id":nid,"candidate_id":int(row['candidate_id']),"candidate_key":row.get('candidate_key'),
            "record_id":rid,"kind":typ,"label":str(row.get('candidate_text') or '')[:500],
            "candidate_text":row.get('candidate_text'),"extraction_method":row.get('extraction_method'),
            "candidate_confidence":_bounded(row.get('confidence')),"evidence_traceability_weight":weight,
            "source_locator":row.get('source_locator'),"source_chunk_ordinal":row.get('source_chunk_ordinal'),
            "source_content_hash":row.get('source_content_hash'),"source_hash_current":current,
            "reviewer":row.get('reviewer'),"review_note":row.get('review_note'),"reviewed_at":row.get('reviewed_at'),
            "core_operation":row.get('core_operation'),"core_outbox_event_id":row.get('core_outbox_event_id'),
            "metadata":meta,"review_state":"accepted","truth_determined":False,
        }
        items.append(item); by_id[int(row['candidate_id'])]=item; by_key[str(row.get('candidate_key') or '')]=item
        per_pub[rid]['findings' if typ=='finding' else 'claims']+=1
        if current: per_pub[rid]['current_source_bound']+=1
        if row.get('core_outbox_event_id'): per_pub[rid]['core_handoff_count']+=1

    relations=[]
    for item in items:
        meta=item.get('metadata') or {}
        relation=str(meta.get('relationship') or meta.get('relation') or meta.get('stance') or '').strip().lower()
        if relation not in EXPLICIT_SUPPORT|EXPLICIT_CONTRADICTION: continue
        target=None
        if meta.get('target_candidate_id') is not None:
            try: target=by_id.get(int(meta.get('target_candidate_id')))
            except Exception: target=None
        if target is None and str(meta.get('target_candidate_key') or ''):
            target=by_key.get(str(meta.get('target_candidate_key')))
        if not target or target['id']==item['id']: continue
        family='support' if relation in EXPLICIT_SUPPORT else 'contradiction'
        relations.append({
            "source":item['id'],"target":target['id'],"relationship_family":family,
            "relationship_basis":"reviewed-explicit-support" if family=='support' else "reviewed-explicit-contradiction",
            "relation":relation,"weight":round(min(item['evidence_traceability_weight'],target['evidence_traceability_weight']),6),
            "explicitly_encoded":True,"truth_assertion":False,"causal_assertion":False,
        })

    summaries=[]
    for rid,counts in per_pub.items():
        summaries.append({"record_id":rid,**counts,"reviewed_candidate_count":counts['findings']+counts['claims']})
    summaries.sort(key=lambda x:(x['reviewed_candidate_count'],x['record_id']),reverse=True)
    return {
        "schema":OVERLAY_CONTRACT,"items":items,"relations":relations,"publication_summaries":summaries,
        "metrics":{
            "finding_count":sum(1 for x in items if x['kind']=='finding'),
            "claim_count":sum(1 for x in items if x['kind']=='claim'),
            "support_relation_count":sum(1 for x in relations if x['relationship_family']=='support'),
            "contradiction_relation_count":sum(1 for x in relations if x['relationship_family']=='contradiction'),
            "current_source_bound_count":sum(1 for x in items if x['source_hash_current']),
        },
        "weighting":{
            "field":"evidence_traceability_weight",
            "formula":"0.50 candidate confidence + 0.20 source locator + 0.20 current source hash + 0.10 named reviewer",
            "purpose":"visual prominence of reviewed, source-traceable research objects",
            "epistemic_truth_score":False,
        },
        "interpretation":{
            "accepted_candidates_only":True,"overlay_creates_new_claims":False,"weight_means_truth":False,
            "contradiction_requires_explicit_reviewed_relation":True,"support_requires_explicit_reviewed_relation":True,
            "absence_of_contradiction_relation_means_agreement":False,
        },
    }
