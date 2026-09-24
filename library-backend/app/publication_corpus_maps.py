from __future__ import annotations

from collections import defaultdict
from typing import Any
import hashlib
import json

from .db import get_pool
from .publication_knowledge_maps import _add_edge, _add_node, _as_list, _cosine, _topic_id

CORPUS_KNOWLEDGE_MAP_CONTRACT = "sc-library-publication-corpus-knowledge-map/1.0"


def _eligible_records(
    cur: Any, *, source_key: str = "wordpress-main", object_type: str = "",
    record_ids: list[str] | tuple[str, ...] | None = None, max_publications: int = 250,
) -> tuple[dict[str, dict[str, Any]], int, str]:
    source_key = str(source_key or "").strip()
    object_type = str(object_type or "").strip()
    max_publications = max(1, min(1000, int(max_publications)))
    manifest_ids = list(dict.fromkeys(str(x).strip() for x in (record_ids or []) if str(x).strip()))[:1000]
    where = ["visibility='public'", "publication_status='published'"]
    params: list[Any] = []
    if source_key:
        where.append("source_key=%s")
        params.append(source_key)
    if manifest_ids:
        where.append("record_id=ANY(%s)")
        params.append(manifest_ids)
        selection_mode = "publication-library-manifest"
    else:
        # Safe backend fallback: generic wordpress-main calls analyze editorial
        # posts only. Pages, Foundation documents, support content, and other
        # indexed object types are not silently treated as publications.
        object_type = object_type or "post"
        where.append("object_type=%s")
        params.append(object_type)
        selection_mode = "wordpress-post-fallback"
    clause = " AND ".join(where)
    cur.execute(f"SELECT count(*) AS n FROM library_records WHERE {clause}", tuple(params))
    total = int(cur.fetchone()["n"])
    cur.execute(
        f"""
        SELECT record_id,source_key,title,canonical_url,object_type,content_hash,authors,topics,tags,identifiers,
               visibility,publication_status,published_at,indexed_at,metadata
          FROM library_records
         WHERE {clause}
         ORDER BY published_at DESC NULLS LAST,indexed_at DESC,record_id ASC
         LIMIT %s
        """,
        tuple(params + [max_publications]),
    )
    records: dict[str, dict[str, Any]] = {}
    for row in cur.fetchall():
        item = dict(row)
        records[str(item["record_id"])] = item
    return records, total, selection_mode


def _year(value: Any) -> int | None:
    text = str(value or "").strip()
    if len(text) >= 4 and text[:4].isdigit():
        year = int(text[:4])
        if 1000 <= year <= 3000:
            return year
    return None




def _knowledge_terrain_analysis(
    nodes: dict[str, dict[str, Any]],
    records: dict[str, dict[str, Any]],
    multi: dict[str, Any],
) -> dict[str, Any]:
    """Build a deterministic renderer-neutral 4D terrain contract.

    X/Y are topological coordinates derived from measured topic regions and
    deterministic within-region placement. Z is a selectable normalized
    analytical metric. T is publication year. No terrain feature is an
    inferred factual or causal claim.
    """
    import hashlib
    import math

    regions = list(multi.get("topic_regions") or [])
    trajectories = {str(x.get("topic_id")): x for x in (multi.get("temporal_dynamics", {}).get("topic_trajectories") or [])}
    years = [int(y) for y in (multi.get("temporal_dynamics", {}).get("years") or [])]

    def norm_map(values: dict[str, float]) -> dict[str, float]:
        if not values:
            return {}
        lo, hi = min(values.values()), max(values.values())
        if hi <= lo:
            return {k: (1.0 if hi > 0 else 0.0) for k in values}
        return {k: round((v-lo)/(hi-lo), 6) for k,v in values.items()}

    topic_nodes = {str(nid): node for nid,node in nodes.items() if node.get("kind") == "topic"}
    weighted = {tid: float((n.get("metrics") or {}).get("weighted_degree") or 0.0) for tid,n in topic_nodes.items()}
    pubdens = {tid: float((n.get("metrics") or {}).get("publication_count") or 0.0) for tid,n in topic_nodes.items()}
    weighted_n, pubdens_n = norm_map(weighted), norm_map(pubdens)

    region_centers: dict[str, tuple[float,float]] = {}
    region_anchors = []
    count=max(1,len(regions))
    golden=math.pi*(3-math.sqrt(5))
    for i,r in enumerate(regions):
        radius=0.18+0.68*math.sqrt((i+0.5)/count)
        angle=i*golden
        x=round(math.cos(angle)*radius,6); y=round(math.sin(angle)*radius,6)
        rid=str(r.get("id")); region_centers[rid]=(x,y)
        region_anchors.append({
            "region_id":rid, "label":r.get("representative_label") or rid,
            "x":x, "y":y, "topic_count":int(r.get("topic_count") or 0),
            "publication_count":int(r.get("publication_count") or 0),
        })

    topic_anchors=[]
    for tid,n in topic_nodes.items():
        rid=str(n.get("region_id") or "")
        cx,cy=region_centers.get(rid,(0.0,0.0))
        h=int(hashlib.sha256(tid.encode('utf-8')).hexdigest()[:16],16)
        angle=(h%100000)/100000*2*math.pi
        local_r=0.025+((h>>17)%1000)/1000*0.105
        x=max(-1,min(1,cx+math.cos(angle)*local_r)); y=max(-1,min(1,cy+math.sin(angle)*local_r))
        tr=trajectories.get(tid,{})
        topic_anchors.append({
            "topic_id":tid, "label":n.get("label") or tid, "region_id":rid or None,
            "x":round(x,6), "y":round(y,6),
            "relationship_density":weighted_n.get(tid,0.0),
            "publication_density":pubdens_n.get(tid,0.0),
            "weighted_degree":round(weighted.get(tid,0.0),6),
            "publication_count":int(pubdens.get(tid,0.0)),
            "first_year":tr.get("first_year"), "last_year":tr.get("last_year"),
            "trajectory_slope":tr.get("trajectory_slope"),
        })
    topic_anchors.sort(key=lambda x:(x["publication_density"],x["relationship_density"]),reverse=True)
    topic_anchors=topic_anchors[:250]

    # Publication anchors are placed at the centroid of their attached topics.
    pub_topic_edges={}
    # Recover topic memberships from analysis relationships where possible.
    for rel in multi.get("publication_relationships") or []:
        for pid in (str(rel.get("source")),str(rel.get("target"))):
            pub_topic_edges.setdefault(pid,set()).update(str(t) for t in (rel.get("shared_topics") or []))
    by_topic={x["topic_id"]:x for x in topic_anchors}
    publication_anchors=[]
    for pid,rec in records.items():
        tids=[t for t in pub_topic_edges.get(pid,set()) if t in by_topic]
        if tids:
            x=sum(by_topic[t]["x"] for t in tids)/len(tids); y=sum(by_topic[t]["y"] for t in tids)/len(tids)
        else:
            h=int(hashlib.sha256(pid.encode()).hexdigest()[:16],16); a=(h%100000)/100000*2*math.pi; rr=.72+((h>>11)%1000)/1000*.18; x=math.cos(a)*rr; y=math.sin(a)*rr
        publication_anchors.append({
            "record_id":pid,"title":rec.get("title") or pid,"x":round(x,6),"y":round(y,6),
            "year":_year(rec.get("published_at")),"canonical_url":rec.get("canonical_url"),
        })

    # Compact temporal keyframes. Heights are publication frequencies for topic/year,
    # normalized within the full corpus so playback is comparable across years.
    max_count=max([int(p.get("publication_count") or 0) for p in topic_anchors] or [1])
    keyframes=[]
    for y in years:
        heights={}
        for t in topic_anchors:
            tr=trajectories.get(t["topic_id"],{})
            series={int(x.get("year")):int(x.get("publication_count") or 0) for x in (tr.get("series") or [])}
            c=series.get(y,0)
            if c:
                heights[t["topic_id"]]=round(c/max_count,6)
        keyframes.append({"year":y,"topic_heights":heights})

    return {
        "schema":"sc-library-4d-knowledge-terrain/1.0",
        "coordinate_system":{
            "x":"deterministic topological region separation",
            "y":"deterministic within-region semantic/topic placement",
            "z":"selectable normalized analytical elevation",
            "t":"publication year",
            "unit_domain":"normalized analytical coordinates",
        },
        "topic_anchors":topic_anchors,
        "region_anchors":region_anchors,
        "publication_anchors":publication_anchors[:500],
        "temporal_keyframes":keyframes,
        "elevation_metrics":[
            {"key":"relationship_density","label":"Relationship density","basis":"normalized weighted graph degree"},
            {"key":"publication_density","label":"Publication density","basis":"normalized publication membership count"},
            {"key":"temporal_activity","label":"Temporal activity","basis":"normalized topic publication frequency at selected year"},
        ],
        "default_elevation_metric":"relationship_density",
        "surface_model":{"kernel":"gaussian-radial","sigma":0.14,"grid_columns":44,"grid_rows":30,"interpolation":"weighted analytical surface"},
        "camera":{"azimuth_degrees":-38,"elevation_degrees":42,"perspective":0.82},
        "time_playback":{"available":len(years)>=2,"years":years,"interpolation":"step-year","default_year":years[-1] if years else None},
        "interpretation":{
            "terrain_height_is_evidence_of_truth":False,
            "spatial_proximity_is_causality":False,
            "time_is_publication_time":True,
            "all_surface_peaks_trace_to_topic_anchors":True,
        },
    }

def _multi_publication_analysis(
    nodes: dict[str, dict[str, Any]],
    edge_items: list[dict[str, Any]],
    records: dict[str, dict[str, Any]],
) -> dict[str, Any]:
    """Compute deterministic cross-publication structures for linked scientific views.

    This layer deliberately uses only relationships already present in the corpus
    graph: metadata/reviewed concept membership, explicit citations and real
    stored-embedding similarity. It does not infer facts, causality or truth.
    """
    publication_ids = set(records)
    topic_ids = {nid for nid, node in nodes.items() if node.get("kind") == "topic"}
    pub_topics: dict[str, set[str]] = defaultdict(set)
    topic_pubs: dict[str, set[str]] = defaultdict(set)
    citation_pairs: set[tuple[str, str]] = set()
    semantic_pairs: dict[tuple[str, str], float] = {}

    for edge in edge_items:
        source, target = str(edge.get("source")), str(edge.get("target"))
        basis = str(edge.get("relationship_basis") or "")
        if basis in {"metadata-association", "reviewed-concept-association"}:
            if source in publication_ids and target in topic_ids:
                pub_topics[source].add(target); topic_pubs[target].add(source)
            elif target in publication_ids and source in topic_ids:
                pub_topics[target].add(source); topic_pubs[source].add(target)
        elif basis == "explicit-citation" and source in publication_ids and target in publication_ids:
            citation_pairs.add((source, target))
        elif basis == "embedding-cosine-similarity" and source in publication_ids and target in publication_ids:
            semantic_pairs[tuple(sorted((source, target)))] = float(edge.get("similarity") or edge.get("weight") or 0.0)

    # Pairwise publication overlap from shared observed/reviewed topic membership.
    publication_relationships: list[dict[str, Any]] = []
    ids = sorted(publication_ids)
    for i, a in enumerate(ids):
        ta = pub_topics.get(a, set())
        if not ta:
            continue
        for b in ids[i + 1:]:
            tb = pub_topics.get(b, set())
            if not tb:
                continue
            shared = ta & tb
            if not shared:
                continue
            union = ta | tb
            jaccard = len(shared) / max(1, len(union))
            semantic = semantic_pairs.get((a, b))
            cited = (a, b) in citation_pairs or (b, a) in citation_pairs
            publication_relationships.append({
                "source": a, "target": b,
                "shared_topic_count": len(shared),
                "topic_jaccard": round(jaccard, 6),
                "semantic_similarity": round(semantic, 6) if semantic is not None else None,
                "explicit_citation": cited,
                "shared_topics": sorted(shared)[:16],
            })
    publication_relationships.sort(key=lambda x: (x["topic_jaccard"], x["shared_topic_count"], bool(x["explicit_citation"])), reverse=True)
    publication_relationships = publication_relationships[:750]

    # Topic regions are deterministic connected components over repeated corpus
    # co-occurrence. A single co-occurrence is not enough to merge regions.
    parent = {tid: tid for tid in topic_ids}
    def find(x: str) -> str:
        while parent[x] != x:
            parent[x] = parent[parent[x]]
            x = parent[x]
        return x
    def union(a: str, b: str) -> None:
        ra, rb = find(a), find(b)
        if ra != rb:
            parent[max(ra, rb)] = min(ra, rb)
    for edge in edge_items:
        if edge.get("relationship_basis") not in {"publication-topic-cooccurrence", "source-span-cooccurrence"}:
            continue
        a, b = str(edge.get("source")), str(edge.get("target"))
        evidence = int(edge.get("evidence_count") or round(float(edge.get("weight") or 0.0)))
        if a in topic_ids and b in topic_ids and evidence >= 2:
            union(a, b)
    components: dict[str, list[str]] = defaultdict(list)
    for tid in topic_ids:
        components[find(tid)].append(tid)
    region_sets = sorted(components.values(), key=lambda group: (-sum(len(topic_pubs.get(t, set())) for t in group), min(group)))
    topic_regions: list[dict[str, Any]] = []
    topic_region_lookup: dict[str, str] = {}
    for idx, group in enumerate(region_sets, 1):
        pubs = set().union(*(topic_pubs.get(t, set()) for t in group)) if group else set()
        labels = sorted((nodes[t].get("label", t) for t in group), key=str.casefold)
        rid = f"region:{idx}"
        for t in group:
            topic_region_lookup[t] = rid
            nodes[t]["region_id"] = rid
        topic_regions.append({
            "id": rid, "topic_count": len(group), "publication_count": len(pubs),
            "topic_ids": sorted(group), "labels": labels[:20],
            "representative_label": labels[0] if labels else rid,
        })

    # Temporal dynamics by publication year and topic-year frequency.
    year_publications: dict[int, set[str]] = defaultdict(set)
    topic_year_counts: dict[str, dict[int, int]] = defaultdict(lambda: defaultdict(int))
    for rid, rec in records.items():
        y = _year(rec.get("published_at"))
        if y is None:
            continue
        year_publications[y].add(rid)
        nodes[rid]["time_year"] = y
        for tid in pub_topics.get(rid, set()):
            topic_year_counts[tid][y] += 1
    years = sorted(year_publications)
    temporal_bins = [{"year": y, "publication_count": len(year_publications[y])} for y in years]
    topic_trajectories = []
    for tid, counts in topic_year_counts.items():
        series = [{"year": y, "publication_count": counts[y]} for y in sorted(counts)]
        if not series:
            continue
        first, last = series[0], series[-1]
        span = max(1, int(last["year"]) - int(first["year"]))
        slope = (int(last["publication_count"]) - int(first["publication_count"])) / span
        topic_trajectories.append({
            "topic_id": tid, "label": nodes.get(tid, {}).get("label", tid),
            "series": series, "first_year": first["year"], "last_year": last["year"],
            "trajectory_slope": round(slope, 6),
            "publication_count": len(topic_pubs.get(tid, set())),
        })
    topic_trajectories.sort(key=lambda x: (x["publication_count"], abs(x["trajectory_slope"])), reverse=True)

    # Cross-publication bridge candidates: high weighted degree connecting many
    # topics or publications. This is a graph structural measure only.
    bridges = []
    for nid, node in nodes.items():
        m = node.get("metrics") or {}
        bridges.append({
            "node_id": nid, "kind": node.get("kind"), "label": node.get("label"),
            "weighted_degree": float(m.get("weighted_degree") or 0.0),
            "publication_count": int(m.get("publication_count") or 0),
            "region_id": node.get("region_id"),
        })
    bridges.sort(key=lambda x: (x["weighted_degree"], x["publication_count"]), reverse=True)

    # Sparse relationship matrix for linked-view rendering.
    matrix_entries = []
    for rel in publication_relationships[:500]:
        matrix_entries.append({
            "row": rel["source"], "column": rel["target"],
            "topic_overlap": rel["topic_jaccard"],
            "shared_topic_count": rel["shared_topic_count"],
            "semantic_similarity": rel["semantic_similarity"],
            "explicit_citation": rel["explicit_citation"],
        })

    return {
        "topic_regions": topic_regions,
        "publication_relationships": publication_relationships,
        "temporal_dynamics": {
            "years": years, "bins": temporal_bins,
            "topic_trajectories": topic_trajectories[:250],
            "time_dimension_available": len(years) >= 2,
        },
        "bridge_nodes": bridges[:40],
        "linked_views": {
            "relationship_matrix": {"sparse": True, "entries": matrix_entries},
            "temporal": {"bins": temporal_bins},
            "regions": {"items": topic_regions},
        },
        "analytical_dimensions": {
            "x": "topic-region / structural separation",
            "y": "relationship density",
            "z": "selectable prominence or publication density",
            "t": "publication time",
            "four_dimensional_ready": len(years) >= 2 and bool(topic_regions),
        },
    }



def _linked_visual_query_analysis(
    nodes: dict[str, dict[str, Any]],
    edge_items: list[dict[str, Any]],
    records: dict[str, dict[str, Any]],
    multi: dict[str, Any],
) -> dict[str, Any]:
    """Build a portable, renderer-neutral linked-view query contract.

    The contract indexes only relationships already present in the corpus model.
    It supports deterministic cross-filtering/highlighting without inferring new
    scholarly claims, causality, or truth.
    """
    publication_ids=set(records)
    topic_ids={str(nid) for nid,node in nodes.items() if node.get("kind")=="topic"}
    pub_topics: dict[str,set[str]]=defaultdict(set)
    topic_pubs: dict[str,set[str]]=defaultdict(set)
    adjacency: dict[str,list[dict[str,Any]]]=defaultdict(list)
    relationship_bases=set()
    for edge in edge_items:
        a,b=str(edge.get("source")),str(edge.get("target"))
        basis=str(edge.get("relationship_basis") or "unknown")
        relationship_bases.add(basis)
        weight=float(edge.get("similarity") if edge.get("relationship_basis")=="embedding-cosine-similarity" and edge.get("similarity") is not None else edge.get("weight") or 1.0)
        adjacency[a].append({"node_id":b,"relationship_basis":basis,"weight":round(weight,6),"directed":bool(edge.get("directed"))})
        adjacency[b].append({"node_id":a,"relationship_basis":basis,"weight":round(weight,6),"directed":bool(edge.get("directed"))})
        if basis in {"metadata-association","reviewed-concept-association"}:
            if a in publication_ids and b in topic_ids:
                pub_topics[a].add(b); topic_pubs[b].add(a)
            elif b in publication_ids and a in topic_ids:
                pub_topics[b].add(a); topic_pubs[a].add(b)
    adjacency_compact={k:sorted(v,key=lambda x:(x["weight"],x["node_id"]),reverse=True)[:80] for k,v in adjacency.items()}
    region_topics={str(r.get("id")):sorted(str(x) for x in (r.get("topic_ids") or [])) for r in (multi.get("topic_regions") or [])}
    region_publications={}
    for rid,tids in region_topics.items():
        pubs=set()
        for tid in tids: pubs.update(topic_pubs.get(tid,set()))
        region_publications[rid]=sorted(pubs)
    year_publications: dict[str,list[str]]=defaultdict(list)
    for rid,rec in records.items():
        y=_year(rec.get("published_at"))
        if y is not None: year_publications[str(y)].append(rid)
    year_topics: dict[str,set[str]]=defaultdict(set)
    for y,pubs in year_publications.items():
        for rid in pubs: year_topics[y].update(pub_topics.get(rid,set()))
    return {
        "schema":"sc-library-linked-visual-query/1.0",
        "runtime":"deterministic-client-crossfilter-over-loaded-corpus",
        "default_state":{
            "selected_node_ids":[],"selected_region_id":None,"selected_year":None,
            "text":"","mode":"highlight","relationship_bases":sorted(relationship_bases),
            "minimum_relationship_strength":0.0,
        },
        "fields":[
            {"key":"node_id","operators":["equals","in"]},
            {"key":"node_kind","operators":["equals","in"]},
            {"key":"label","operators":["contains"]},
            {"key":"region_id","operators":["equals","in"]},
            {"key":"publication_year","operators":["equals","in","gte","lte"]},
            {"key":"relationship_basis","operators":["equals","in"]},
            {"key":"relationship_strength","operators":["gte"]},
        ],
        "modes":["highlight","isolate"],
        "selection_semantics":{
            "node_selection":"selected node plus directly observed graph neighbors",
            "region_selection":"topics in measured region plus publications attached to those topics",
            "year_selection":"publications dated to selected year plus their observed topics",
            "text_selection":"case-insensitive label match within loaded corpus",
            "combined_filters":"intersection of active query dimensions",
        },
        "indexes":{
            "publication_to_topics":{k:sorted(v) for k,v in pub_topics.items()},
            "topic_to_publications":{k:sorted(v) for k,v in topic_pubs.items()},
            "region_to_topics":region_topics,
            "region_to_publications":region_publications,
            "year_to_publications":{k:sorted(v) for k,v in year_publications.items()},
            "year_to_topics":{k:sorted(v) for k,v in year_topics.items()},
            "adjacency":adjacency_compact,
        },
        "linked_view_targets":[
            "knowledge-landscape","knowledge-terrain-4d","topic-graph","citation-overlay",
            "topic-regions","temporal-dynamics","relationship-matrix","semantic-overlay",
        ],
        "capabilities":{
            "cross_view_selection":True,"cross_view_highlighting":True,"cross_view_isolation":True,
            "terrain_peak_selection":True,"matrix_cell_selection":True,"region_selection":True,
            "time_crossfilter":True,"text_visual_query":True,"portable_query_state":True,
        },
        "boundaries":{
            "query_creates_new_research_claims":False,"selection_is_research_conclusion":False,
            "neighbor_highlight_implies_causality":False,"filters_mutate_source_records":False,
        },
    }

def build_publication_corpus_knowledge_map(
    *,
    source_key: str = "wordpress-main",
    object_type: str = "",
    record_ids: list[str] | tuple[str, ...] | None = None,
    include_citations: bool = True,
    include_semantic_similarity: bool = True,
    semantic_threshold: float = 0.72,
    max_publications: int = 250,
    max_topics_per_publication: int = 36,
) -> dict[str, Any]:
    semantic_threshold = max(0.0, min(1.0, float(semantic_threshold)))
    max_publications = max(1, min(1000, int(max_publications)))
    max_topics_per_publication = max(1, min(100, int(max_topics_per_publication)))

    pool = get_pool()
    nodes: dict[str, dict[str, Any]] = {}
    edges: dict[tuple[str, str, str, bool], dict[str, Any]] = {}
    semantic_status = {
        "requested": bool(include_semantic_similarity),
        "available": False,
        "reason": "not-requested" if not include_semantic_similarity else "insufficient-current-embeddings",
        "threshold": semantic_threshold,
        "vector_count": 0,
        "model": None,
        "provider": None,
    }

    with pool.connection() as conn, conn.cursor() as cur:
        records, total_eligible, selection_mode = _eligible_records(
            cur, source_key=source_key, object_type=object_type, record_ids=record_ids, max_publications=max_publications
        )
        if not records:
            return {
                "schema": CORPUS_KNOWLEDGE_MAP_CONTRACT,
                "scope": "corpus",
                "title": "Publication Corpus Knowledge Landscape",
                "analysis_kind": "publication-corpus-knowledge-landscape",
                "nodes": [],
                "edges": [],
                "metrics": {"node_count": 0, "edge_count": 0, "publication_count": 0, "topic_count": 0, "relationship_counts": {}},
                "corpus": {
                    "source_key": source_key or None,
                    "object_type": object_type or (None if selection_mode == "publication-library-manifest" else "post"),
                    "selection": selection_mode,
                    "requested_manifest_count": len(record_ids or []),
                    "eligible_publication_count": 0,
                    "analyzed_publication_count": 0,
                    "truncated": False,
                },
                "semantic_analysis": semantic_status,
                "boundaries": {
                    "llm_inferred_edges": False,
                    "automatic_truth_promotion": False,
                    "semantic_edges_are_truth_claims": False,
                    "unresolved_citations_guessed": False,
                },
            }

        record_ids = list(records)
        for rid, rec in records.items():
            _add_node(
                nodes, rid, "publication", str(rec.get("title") or rid),
                root=False, corpus_member=True, canonical_url=rec.get("canonical_url"),
                published_at=str(rec.get("published_at") or ""), object_type=rec.get("object_type"),
                source_key=rec.get("source_key"), source_content_hash=rec.get("content_hash"),
                authors=_as_list(rec.get("authors")),
            )

        if include_citations and len(record_ids) >= 2:
            cur.execute(
                """
                SELECT citation_id,citing_record_id,cited_record_id,relation_type,resolution_status,
                       identifier_type,identifier_value,locator,confidence
                  FROM library_citations
                 WHERE resolution_status='resolved'
                   AND cited_record_id IS NOT NULL
                   AND citing_record_id=ANY(%s)
                   AND cited_record_id=ANY(%s)
                 ORDER BY citation_id DESC
                """,
                (record_ids, record_ids),
            )
            for row in cur.fetchall():
                item = dict(row)
                _add_edge(
                    edges, str(item["citing_record_id"]), str(item["cited_record_id"]),
                    "explicit-citation", directed=True,
                    weight=max(0.05, float(item.get("confidence") or 1.0)),
                    relation_type=str(item.get("relation_type") or "cites"),
                    citation_id=item.get("citation_id"), locator=item.get("locator"),
                    analytical=False, truth_assertion=False,
                )

        cur.execute(
            """
            SELECT candidate_id,record_id,candidate_text,entity_type,confidence,source_locator,
                   source_chunk_ordinal,char_start,char_end,source_content_hash,reviewer
              FROM library_research_candidates
             WHERE record_id=ANY(%s)
               AND candidate_type='entity'
               AND entity_type='concept'
               AND review_state='accepted'
             ORDER BY record_id,confidence DESC,candidate_id ASC
            """,
            (record_ids,),
        )
        accepted_concepts = [dict(row) for row in cur.fetchall()]

        per_record_topics: dict[str, list[str]] = defaultdict(list)
        for rid, rec in records.items():
            labels: list[tuple[str, str, float]] = []
            labels.extend((str(x), "record-topic", 1.0) for x in _as_list(rec.get("topics")))
            labels.extend((str(x), "record-tag", 0.75) for x in _as_list(rec.get("tags")))
            seen_labels: set[str] = set()
            for label, source_type, confidence in labels:
                norm = " ".join(label.strip().split())
                if not norm or norm.casefold() in seen_labels:
                    continue
                seen_labels.add(norm.casefold())
                tid = _topic_id(norm)
                _add_node(nodes, tid, "topic", norm, source_type=source_type, reviewed=True)
                _add_edge(
                    edges, rid, tid, "metadata-association", directed=False, weight=confidence,
                    evidence_count=1, source_type=source_type, analytical=False, truth_assertion=False,
                )
                per_record_topics[rid].append(tid)
                if len(seen_labels) >= max_topics_per_publication:
                    break

        chunk_topics: dict[tuple[str, int], list[str]] = defaultdict(list)
        per_record_concepts: dict[str, int] = defaultdict(int)
        for item in accepted_concepts:
            rid = str(item["record_id"])
            if rid not in records or per_record_concepts[rid] >= max_topics_per_publication:
                continue
            label = " ".join(str(item.get("candidate_text") or "").strip().split())
            if not label:
                continue
            tid = _topic_id(label)
            confidence = max(0.0, min(1.0, float(item.get("confidence") or 0.0)))
            _add_node(
                nodes, tid, "topic", label, source_type="accepted-concept", reviewed=True,
                confidence=confidence, source_locator=item.get("source_locator"),
                source_chunk_ordinal=item.get("source_chunk_ordinal"),
                source_content_hash=item.get("source_content_hash"), reviewed_by=item.get("reviewer"),
            )
            _add_edge(
                edges, rid, tid, "reviewed-concept-association", directed=False,
                weight=max(0.05, confidence), evidence_count=1,
                source_locator=item.get("source_locator"), analytical=False, truth_assertion=False,
            )
            per_record_topics[rid].append(tid)
            ordinal = item.get("source_chunk_ordinal")
            if ordinal is not None:
                chunk_topics[(rid, int(ordinal))].append(tid)
            per_record_concepts[rid] += 1

        # Measured topic proximity within a publication. The cap prevents quadratic
        # explosion on heavily tagged records while preserving the strongest local structure.
        for rid, tids in per_record_topics.items():
            unique = list(dict.fromkeys(tids))[:14]
            for i in range(len(unique)):
                for j in range(i + 1, len(unique)):
                    _add_edge(
                        edges, unique[i], unique[j], "publication-topic-cooccurrence", directed=False,
                        weight=1.0, evidence_count=1, publication_record_id=rid,
                        analytical=True, truth_assertion=False,
                    )

        # More specific concept proximity when two reviewed concepts occur in the same source chunk.
        for (rid, ordinal), tids in chunk_topics.items():
            unique = list(dict.fromkeys(tids))[:30]
            for i in range(len(unique)):
                for j in range(i + 1, len(unique)):
                    _add_edge(
                        edges, unique[i], unique[j], "source-span-cooccurrence", directed=False,
                        weight=1.0, evidence_count=1, publication_record_id=rid,
                        source_chunk_ordinal=ordinal, analytical=True, truth_assertion=False,
                    )

        if include_semantic_similarity and len(records) >= 2:
            cur.execute(
                """
                SELECT e.record_id,e.provider,e.model,e.dimensions,e.embedding
                  FROM library_record_embeddings e
                  JOIN library_records r ON r.record_id=e.record_id AND r.content_hash=e.content_hash
                 WHERE e.record_id=ANY(%s)
                 ORDER BY e.updated_at DESC
                """,
                (record_ids,),
            )
            vectors: dict[str, dict[str, Any]] = {}
            for row in cur.fetchall():
                item = dict(row)
                rid = str(item["record_id"])
                if rid not in vectors:
                    vectors[rid] = item
            semantic_status["vector_count"] = len(vectors)
            if len(vectors) >= 2:
                semantic_status.update({
                    "available": True,
                    "reason": "current-record-embeddings",
                    "provider": next(iter(vectors.values())).get("provider"),
                    "model": next(iter(vectors.values())).get("model"),
                })
                ids = list(vectors)
                for i in range(len(ids)):
                    for j in range(i + 1, len(ids)):
                        a, b = vectors[ids[i]], vectors[ids[j]]
                        if a.get("provider") != b.get("provider") or a.get("model") != b.get("model") or a.get("dimensions") != b.get("dimensions"):
                            continue
                        score = _cosine(list(a.get("embedding") or []), list(b.get("embedding") or []))
                        if score >= semantic_threshold:
                            _add_edge(
                                edges, ids[i], ids[j], "embedding-cosine-similarity", directed=False,
                                weight=score, similarity=round(score, 6), analytical=True,
                                truth_assertion=False, provider=a.get("provider"), model=a.get("model"),
                            )

    edge_items = list(edges.values())
    degree: dict[str, float] = defaultdict(float)
    citations: dict[str, int] = defaultdict(int)
    semantic_links: dict[str, int] = defaultdict(int)
    topic_publications: dict[str, set[str]] = defaultdict(set)
    for edge in edge_items:
        source, target = str(edge["source"]), str(edge["target"])
        weight = float(edge.get("weight") or 1.0)
        degree[source] += weight
        degree[target] += weight
        if edge.get("relationship_basis") == "explicit-citation":
            citations[source] += 1
            citations[target] += 1
        if edge.get("relationship_basis") == "embedding-cosine-similarity":
            semantic_links[source] += 1
            semantic_links[target] += 1
        if edge.get("relationship_basis") in {"metadata-association", "reviewed-concept-association"}:
            if source.startswith("topic:") and target in records:
                topic_publications[source].add(target)
            elif target.startswith("topic:") and source in records:
                topic_publications[target].add(source)

    for node_id, node in nodes.items():
        node["metrics"] = {
            "weighted_degree": round(degree.get(node_id, 0.0), 6),
            "citation_links": citations.get(node_id, 0),
            "semantic_links": semantic_links.get(node_id, 0),
            "publication_count": len(topic_publications.get(node_id, set())) if node.get("kind") == "topic" else 0,
        }

    node_items = list(nodes.values())
    publication_count = sum(1 for x in node_items if x.get("kind") == "publication")
    topic_count = sum(1 for x in node_items if x.get("kind") == "topic")
    relationship_counts: dict[str, int] = defaultdict(int)
    for edge in edge_items:
        relationship_counts[str(edge.get("relationship_basis") or "unknown")] += 1

    multi = _multi_publication_analysis(nodes, edge_items, records)
    terrain = _knowledge_terrain_analysis(nodes, records, multi)
    visual_query = _linked_visual_query_analysis(nodes, edge_items, records, multi)
    _refs = sorted([{"record_id": str(n.get("id") or ""), "content_hash": str(n.get("source_content_hash") or "")} for n in node_items if n.get("kind") == "publication"], key=lambda x: x["record_id"])
    _corpus_fingerprint = hashlib.sha256(json.dumps(_refs, sort_keys=True, separators=(",", ":")).encode("utf-8")).hexdigest()

    return {
        "schema": CORPUS_KNOWLEDGE_MAP_CONTRACT,
        "scope": "corpus",
        "title": "Publication Corpus Knowledge Landscape",
        "analysis_kind": "publication-corpus-knowledge-landscape",
        "nodes": node_items,
        "edges": edge_items,
        "metrics": {
            "node_count": len(node_items),
            "edge_count": len(edge_items),
            "publication_count": publication_count,
            "topic_count": topic_count,
            "relationship_counts": dict(sorted(relationship_counts.items())),
        },
        "corpus": {
            "source_key": source_key or None,
            "object_type": object_type or (None if selection_mode == "publication-library-manifest" else "post"),
            "selection": selection_mode,
            "requested_manifest_count": len(record_ids or []),
            "eligible_publication_count": total_eligible,
            "analyzed_publication_count": publication_count,
            "truncated": total_eligible > publication_count,
            "max_publications": max_publications,
        },
        "semantic_analysis": semantic_status,
        "multi_publication_analysis": multi,
        "topic_regions": multi["topic_regions"],
        "publication_relationships": multi["publication_relationships"],
        "temporal_dynamics": multi["temporal_dynamics"],
        "bridge_nodes": multi["bridge_nodes"],
        "linked_views": multi["linked_views"],
        "analytical_dimensions": multi["analytical_dimensions"],
        "knowledge_terrain_4d": terrain,
        "visual_query": visual_query,
        "reproducibility": {
            "schema": "sc-library-visual-corpus-reproducibility/1.0",
            "corpus_fingerprint_sha256": _corpus_fingerprint,
            "publication_source_hashes": _refs,
            "deterministic_layout": True,
            "portable_visual_session_ready": True,
            "workspace_handoff_package_ready": True,
        },
        "views": [
            {"key": "knowledge-landscape", "label": "Knowledge Landscape", "purpose": "Cross-publication topic and publication relationship field"},
            {"key": "knowledge-terrain-4d", "label": "4D Knowledge Terrain", "purpose": "Spatial-temporal analytical terrain with selectable elevation metrics and time playback"},
            {"key": "topic-graph", "label": "Topic Graph", "purpose": "Measured topic co-occurrence across the publication corpus"},
            {"key": "citation-overlay", "label": "Citation Overlay", "purpose": "Explicit citation structure within the selected corpus"},
            {"key": "semantic-overlay", "label": "Semantic Overlay", "purpose": "Publication similarity from current stored embeddings only"},
            {"key": "relationship-matrix", "label": "Relationship Matrix", "purpose": "Pairwise analytical relationship inspection"},
            {"key": "topic-regions", "label": "Topic Regions", "purpose": "Corpus-scale topic regions from repeated measured co-occurrence"},
            {"key": "temporal-dynamics", "label": "Temporal Dynamics", "purpose": "Publication and topic evolution through time"},
        ],
        "renderer_profile": {
            "family": "scientific-publication-corpus-landscape",
            "renderer_neutral": True,
            "preferred_runtime": "interactive-svg-canvas-webgl-capable",
            "layout": "force-directed-multilayer-with-regions-and-time",
            "node_channels": ["kind", "weighted_degree", "publication_count", "source_type"],
            "edge_channels": ["relationship_basis", "weight", "directed", "evidence_count"],
            "interactions": ["zoom", "pan", "select", "filter", "focus", "inspect-source", "toggle-layer", "drill-to-publication", "cluster-focus", "time-filter", "linked-view-selection", "relationship-matrix-inspection", "orbit-terrain", "select-elevation-metric", "play-time", "scrub-time", "visual-query", "cross-filter", "cross-highlight", "isolate-selection", "matrix-cell-select", "terrain-peak-select", "region-select", "portable-query-state"],
            "core_visual_runtime_targets": [
                "/v1/visual-runtime/unified",
                "/v1/visual-runtime/grammar",
                "/v1/visual-runtime/linked-views",
                "/v1/visual-runtime/query",
                "/v1/visualization",
            ],
        },
        "provenance": {
            "source_product": "knowledge-library",
            "corpus_source": source_key or "all-public-library-sources",
            "relationship_methods": [
                "explicit-citation",
                "record-metadata-association",
                "human-reviewed-concept-association",
                "publication-topic-cooccurrence",
                "source-span-cooccurrence",
                "stored-embedding-cosine-similarity-if-available",
                "cross-publication-topic-jaccard",
                "deterministic-topic-regions",
                "publication-time-binning",
                "deterministic-4d-knowledge-terrain",
                "linked-view-deterministic-crossfilter",
            ],
            "governed_visual_reasoning_authority": "platform-core",
        },
        "boundaries": {
            "llm_inferred_edges": False,
            "automatic_truth_promotion": False,
            "semantic_edges_are_truth_claims": False,
            "unresolved_citations_guessed": False,
            "human_review_required_for_extracted_concepts": True,
            "corpus_is_live_library_records": True,
            "publication_library_manifest_applied": selection_mode == "publication-library-manifest",
            "non_publication_wordpress_types_excluded": True,
            "visual_query_selection_is_research_conclusion": False,
        },
    }
