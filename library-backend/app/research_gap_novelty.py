from __future__ import annotations

from collections import Counter, defaultdict
from hashlib import sha256
import itertools
import json
from typing import Any, Iterable

GAP_SCHEMA = "sc-library-research-gap-novelty/1.0"
GAP_OBJECT_SCHEMA = "sc-library-research-gap-candidate/1.0"
NOVELTY_SCHEMA = "sc-library-novelty-candidate/1.0"


def _clean(value: Any) -> str:
    return " ".join(str(value or "").strip().split())


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    if value is None:
        return []
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return [value]


def _topics(record: dict[str, Any]) -> list[str]:
    meta = _dict(record.get("metadata"))
    raw = record.get("topics") or meta.get("topics") or meta.get("keywords") or meta.get("subjects") or []
    out, seen = [], set()
    for item in _list(raw):
        if isinstance(item, dict):
            item = item.get("label") or item.get("name") or item.get("value")
        text = _clean(item)
        key = text.casefold()
        if text and key not in seen:
            seen.add(key); out.append(text)
    return out


def _year(record: dict[str, Any]) -> int | None:
    meta = _dict(record.get("metadata"))
    for value in (record.get("publication_year"), meta.get("publication_year"), meta.get("year"), record.get("year")):
        try:
            y = int(str(value)[:4])
            if 1000 <= y <= 3000:
                return y
        except Exception:
            pass
    return None


def _candidate_id(kind: str, key: str) -> str:
    return f"gap:{kind}:" + sha256(key.encode("utf-8")).hexdigest()[:18]


def _novelty_id(kind: str, key: str) -> str:
    return f"novelty:{kind}:" + sha256(key.encode("utf-8")).hexdigest()[:18]


def _gap(kind: str, label: str, *, scope: str, basis: dict[str, Any], records: list[str] | None = None,
         topics: list[str] | None = None, priority: str = "review", caveat: str = "") -> dict[str, Any]:
    key = json.dumps({"kind":kind,"records":sorted(records or []),"topics":sorted(topics or [])}, sort_keys=True)
    return {
        "schema": GAP_OBJECT_SCHEMA,
        "id": _candidate_id(kind, key),
        "kind": kind,
        "label": label,
        "scope": scope,
        "candidate_only": True,
        "priority": priority,
        "record_ids": sorted(set(records or [])),
        "topics": sorted(set(topics or [])),
        "basis": basis,
        "requires_external_verification": True,
        "caveat": caveat or "This is a gap signal within the analyzed Sustainable Catalyst corpus, not proof that the broader literature lacks evidence.",
    }


def _novelty(kind: str, label: str, *, basis: dict[str, Any], records: list[str] | None = None,
             topics: list[str] | None = None, caveat: str = "") -> dict[str, Any]:
    key = json.dumps({"kind":kind,"records":sorted(records or []),"topics":sorted(topics or [])}, sort_keys=True)
    return {
        "schema": NOVELTY_SCHEMA,
        "id": _novelty_id(kind, key),
        "kind": kind,
        "label": label,
        "candidate_only": True,
        "record_ids": sorted(set(records or [])),
        "topics": sorted(set(topics or [])),
        "basis": basis,
        "requires_external_search": True,
        "requires_human_novelty_assessment": True,
        "novelty_claim": False,
        "caveat": caveat or "Low frequency or a new combination in this corpus is a discovery lead, not a scholarly novelty claim.",
    }


def build_research_gap_novelty(records: Iterable[dict[str, Any]], *, nodes: Iterable[dict[str, Any]] | None = None,
                               edges: Iterable[dict[str, Any]] | None = None,
                               methodology_intelligence: dict[str, Any] | None = None) -> dict[str, Any]:
    records = [dict(r) for r in records if isinstance(r, dict) and _clean(r.get("record_id") or r.get("id"))]
    by_id = {_clean(r.get("record_id") or r.get("id")): r for r in records}
    nodes = [dict(n) for n in (nodes or []) if isinstance(n, dict)]
    edges = [dict(e) for e in (edges or []) if isinstance(e, dict)]
    methodology = _dict(methodology_intelligence)
    profiles = {_clean(p.get("record_id")): p for p in methodology.get("profiles", []) if isinstance(p, dict) and _clean(p.get("record_id"))}

    topic_records: dict[str, set[str]] = defaultdict(set)
    topic_labels: dict[str, str] = {}
    record_topics: dict[str, list[str]] = {}
    for rid, record in by_id.items():
        ts = _topics(record)
        record_topics[rid] = ts
        for t in ts:
            key=t.casefold(); topic_labels[key]=t; topic_records[key].add(rid)

    gaps: list[dict[str, Any]] = []
    novelty: list[dict[str, Any]] = []

    # 1. Explicit research objects lacking reviewed evidence/support edges in this indexed graph.
    evidence_bases = {"supports", "contradicts", "reviewed-support", "reviewed-contradiction", "evidence-for", "explicit-evidence"}
    research_kinds = {"claim", "finding", "hypothesis"}
    research_nodes = {str(n.get("id")): n for n in nodes if str(n.get("kind") or "") in research_kinds}
    touched = defaultdict(int)
    contrad = defaultdict(int)
    supported = defaultdict(int)
    for e in edges:
        basis = str(e.get("relationship_basis") or "")
        s,t = str(e.get("source") or ""), str(e.get("target") or "")
        if basis in evidence_bases or "support" in basis or "contradict" in basis:
            for nid in (s,t):
                if nid in research_nodes:
                    touched[nid]+=1
                    if "contradict" in basis: contrad[nid]+=1
                    if "support" in basis: supported[nid]+=1
    for nid,node in sorted(research_nodes.items()):
        if touched[nid] == 0:
            rid=_clean(node.get("record_id"))
            gaps.append(_gap(
                "indexed-evidence-linkage-gap",
                f"Indexed evidence linkage gap · {_clean(node.get('label') or nid)}",
                scope="research-object",
                basis={"research_object_id":nid,"research_object_kind":node.get("kind"),"reviewed_evidence_edge_count":0},
                records=[rid] if rid else [],
                caveat="No reviewed evidence/support relationship is represented for this research object in the analyzed graph. This does not establish that no evidence exists in the source or broader literature."
            ))
        elif contrad[nid] > 0 and supported[nid] > 0:
            gaps.append(_gap(
                "competing-evidence-review-priority",
                f"Competing evidence review priority · {_clean(node.get('label') or nid)}",
                scope="research-object",
                basis={"research_object_id":nid,"support_edge_count":supported[nid],"contradiction_edge_count":contrad[nid]},
                records=[_clean(node.get("record_id"))] if _clean(node.get("record_id")) else [],
                priority="high",
                caveat="Explicit support and contradiction relationships coexist in the indexed graph. This is a review priority, not a conclusion that the literature is unresolved or evenly divided."
            ))

    # 2. Topic methodology diversity and reporting gaps.
    for tkey, rids in sorted(topic_records.items()):
        if len(rids) < 2:
            continue
        families=[]; geography_reported=0; population_reported=0
        for rid in rids:
            p=_dict(profiles.get(rid))
            d=_dict(p.get("study_design"))
            fam=_clean(d.get("family"))
            if fam and fam != "unclassified": families.append(fam)
            fields=_dict(p.get("fields"))
            if _dict(fields.get("geography")).get("reported"): geography_reported += 1
            if _dict(fields.get("population")).get("reported"): population_reported += 1
        distinct=sorted(set(families))
        if families and len(distinct) == 1 and len(rids) >= 3:
            gaps.append(_gap(
                "methodology-diversity-gap",
                f"Narrow indexed methodology mix · {topic_labels[tkey]}",
                scope="topic",
                basis={"publication_count":len(rids),"explicit_design_families":distinct,"records_with_classified_design":len(families)},
                records=list(rids), topics=[topic_labels[tkey]],
                caveat="The indexed records with explicit methodology for this topic use one mapped design family. This does not prove other study designs are absent from the broader literature."
            ))
        for field_name,count in (("geography",geography_reported),("population",population_reported)):
            if len(rids) >= 3 and count / len(rids) < 0.5:
                gaps.append(_gap(
                    f"{field_name}-reporting-gap",
                    f"Low structured {field_name} reporting · {topic_labels[tkey]}",
                    scope="topic",
                    basis={"publication_count":len(rids),f"records_with_explicit_{field_name}":count,"reporting_ratio":round(count/len(rids),6)},
                    records=list(rids), topics=[topic_labels[tkey]],
                    caveat=f"Fewer than half of indexed records for this topic expose explicit structured {field_name} metadata. This is a metadata/reporting gap, not proof that the publications omitted {field_name}."
                ))

    # 3. Rare topic combinations as novelty-discovery leads.
    pair_records: dict[tuple[str,str], set[str]] = defaultdict(set)
    for rid, topics in record_topics.items():
        keys=sorted({t.casefold() for t in topics})
        for a,b in itertools.combinations(keys,2): pair_records[(a,b)].add(rid)
    for (a,b),rids in sorted(pair_records.items()):
        if len(rids)==1 and len(topic_records[a]) >= 2 and len(topic_records[b]) >= 2:
            novelty.append(_novelty(
                "rare-topic-combination",
                f"Rare indexed topic combination · {topic_labels[a]} × {topic_labels[b]}",
                basis={"cooccurrence_count":1,"topic_a_publication_count":len(topic_records[a]),"topic_b_publication_count":len(topic_records[b])},
                records=list(rids), topics=[topic_labels[a],topic_labels[b]]
            ))

    # 4. Corpus-relative staleness / recent emergence based only on explicit publication years.
    years=[y for y in (_year(r) for r in records) if y is not None]
    corpus_max=max(years) if years else None
    if corpus_max:
        for tkey,rids in sorted(topic_records.items()):
            tys=[_year(by_id[r]) for r in rids if _year(by_id[r]) is not None]
            if not tys: continue
            latest=max(tys)
            if len(rids)>=2 and corpus_max-latest >= 5:
                gaps.append(_gap(
                    "corpus-relative-temporal-coverage-gap",
                    f"Older indexed temporal coverage · {topic_labels[tkey]}",
                    scope="topic",
                    basis={"topic_latest_publication_year":latest,"corpus_latest_publication_year":corpus_max,"lag_years":corpus_max-latest,"publication_count":len(rids)},
                    records=list(rids), topics=[topic_labels[tkey]],
                    caveat="This topic is older relative to the newest indexed publication in this corpus. It does not establish that newer external research does not exist."
                ))
            if len(rids) <= 2 and min(tys) >= corpus_max-1:
                novelty.append(_novelty(
                    "recent-emergence-in-corpus",
                    f"Recent indexed emergence · {topic_labels[tkey]}",
                    basis={"first_indexed_publication_year":min(tys),"latest_indexed_publication_year":max(tys),"corpus_latest_publication_year":corpus_max,"publication_count":len(rids)},
                    records=list(rids), topics=[topic_labels[tkey]],
                    caveat="This topic appears only in a small number of recent records within the analyzed corpus. External literature and earlier terminology must be checked before making any novelty claim."
                ))

    # deterministic de-duplication
    def unique(items):
        out={}
        for x in items: out[str(x.get("id"))]=x
        return [out[k] for k in sorted(out)]
    gaps=unique(gaps); novelty=unique(novelty)

    overlay_nodes=[]; overlay_edges=[]
    for item in gaps + novelty:
        overlay_nodes.append({
            "id": item["id"], "kind": "research-gap-candidate" if item["schema"]==GAP_OBJECT_SCHEMA else "novelty-candidate",
            "label": item["label"], "candidate_only": True, "requires_external_verification": True,
            "truth_determined": False, "causal_assertion": False, "default_evidence_path": False,
            "basis": item.get("basis") or {}, "topics": item.get("topics") or [], "record_ids": item.get("record_ids") or [],
        })
        for rid in item.get("record_ids") or []:
            if rid in by_id:
                overlay_edges.append({
                    "source": rid, "target": item["id"], "relationship_basis": "research-gap-signal",
                    "directed": True, "weight": 1.0, "analytical": True, "truth_assertion": False,
                    "causal_assertion": False, "default_evidence_path": False, "requires_review": True,
                })

    result={
        "schema": GAP_SCHEMA,
        "gap_candidates": gaps,
        "novelty_candidates": novelty,
        "metrics": {
            "record_count": len(records),
            "gap_candidate_count": len(gaps),
            "novelty_candidate_count": len(novelty),
            "topic_count": len(topic_records),
            "publication_year_max": corpus_max,
            "gap_kind_counts": dict(sorted(Counter(x["kind"] for x in gaps).items())),
            "novelty_kind_counts": dict(sorted(Counter(x["kind"] for x in novelty).items())),
        },
        "graph_overlay": {"nodes": overlay_nodes, "edges": overlay_edges},
        "guardrails": {
            "gap_signal_proves_global_absence": False,
            "novelty_candidate_is_novelty_claim": False,
            "low_frequency_means_unstudied": False,
            "metadata_missing_means_method_absent": False,
            "temporal_lag_means_research_stopped": False,
            "external_search_required_before_novelty_claim": True,
            "human_review_required": True,
            "platform_core_governance_changed": False,
        },
    }
    result["fingerprint_sha256"]=sha256(json.dumps({"gaps":gaps,"novelty":novelty}, sort_keys=True, separators=(",",":"), default=str).encode()).hexdigest()
    return result


def research_gap_novelty_request(payload: dict[str, Any]) -> dict[str, Any]:
    records=payload.get("records")
    if records is None and isinstance(payload.get("record"), dict): records=[payload.get("record")]
    if not isinstance(records, list): raise ValueError("records must be a list or record must be an object")
    return build_research_gap_novelty(records, nodes=payload.get("nodes") or [], edges=payload.get("edges") or [], methodology_intelligence=payload.get("methodology_intelligence") or {})
