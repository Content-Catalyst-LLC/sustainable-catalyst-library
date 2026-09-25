from __future__ import annotations

from collections import defaultdict, deque
from typing import Any

SYNTHESIS_CONTRACT = "sc-library-cross-publication-evidence-synthesis/1.0"


def _weight(item: dict[str, Any]) -> float:
    try:
        return max(0.0, min(1.0, float(item.get("evidence_traceability_weight") or 0.0)))
    except Exception:
        return 0.0


def _meta(item: dict[str, Any]) -> dict[str, Any]:
    value = item.get("metadata")
    return value if isinstance(value, dict) else {}


def _hypothesis_identity(item: dict[str, Any]) -> tuple[str, str, str] | None:
    """Return only explicitly authored/reviewed hypothesis metadata.

    v5.24 never manufactures hypotheses from similarity, support components, or
    contradiction topology. A hypothesis appears only when the accepted source-
    bound candidate already carries an explicit hypothesis key/id in metadata.
    """
    meta = _meta(item)
    key = str(meta.get("hypothesis_key") or meta.get("hypothesis_id") or "").strip()
    if not key:
        return None
    label = str(meta.get("hypothesis_label") or meta.get("hypothesis") or key).strip() or key
    set_key = str(meta.get("competing_hypothesis_set") or meta.get("hypothesis_set") or "").strip()
    return key, label, set_key


def _hypothesis_stance(item: dict[str, Any]) -> str:
    meta = _meta(item)
    raw = str(meta.get("hypothesis_stance") or meta.get("hypothesis_relation") or "").strip().lower()
    if raw in {"support", "supports", "supporting", "consistent-with"}:
        return "support"
    if raw in {"challenge", "challenges", "contradict", "contradicts", "opposes", "rebuttal"}:
        return "challenge"
    if raw in {"neutral", "context", "background"}:
        return "neutral"
    return "unspecified"


def _support_components(items: list[dict[str, Any]], relations: list[dict[str, Any]]) -> list[dict[str, Any]]:
    by_id = {str(item.get("id")): item for item in items if item.get("id")}
    adjacency: dict[str, set[str]] = defaultdict(set)
    support_edges: list[dict[str, Any]] = []
    contradiction_by_node: dict[str, int] = defaultdict(int)

    for relation in relations:
        source = str(relation.get("source") or "")
        target = str(relation.get("target") or "")
        family = str(relation.get("relationship_family") or "")
        if source not in by_id or target not in by_id:
            continue
        if family == "support":
            adjacency[source].add(target)
            adjacency[target].add(source)
            support_edges.append(relation)
        elif family == "contradiction":
            contradiction_by_node[source] += 1
            contradiction_by_node[target] += 1

    seen: set[str] = set()
    components: list[dict[str, Any]] = []
    for node_id in sorted(adjacency):
        if node_id in seen:
            continue
        queue = deque([node_id])
        seen.add(node_id)
        members: list[str] = []
        while queue:
            current = queue.popleft()
            members.append(current)
            for neighbor in sorted(adjacency.get(current, set())):
                if neighbor not in seen:
                    seen.add(neighbor)
                    queue.append(neighbor)
        if len(members) < 2:
            continue
        member_set = set(members)
        member_items = [by_id[x] for x in members]
        pubs = sorted({str(x.get("record_id") or "") for x in member_items if x.get("record_id")})
        rel_count = sum(1 for rel in support_edges if str(rel.get("source")) in member_set and str(rel.get("target")) in member_set)
        components.append({
            "id": f"support-structure:{len(components)+1}",
            "member_ids": members,
            "publication_record_ids": pubs,
            "member_count": len(members),
            "publication_count": len(pubs),
            "finding_count": sum(1 for x in member_items if x.get("kind") == "finding"),
            "claim_count": sum(1 for x in member_items if x.get("kind") == "claim"),
            "explicit_support_relation_count": rel_count,
            "external_contradiction_touch_count": sum(contradiction_by_node.get(x, 0) for x in members),
            "mean_traceability_weight": round(sum(_weight(x) for x in member_items) / max(1, len(member_items)), 6),
            "interpretation": "support-connected reviewed structure; not consensus",
        })
    return components


def build_cross_publication_synthesis(
    research_overlays: dict[str, Any],
    records: dict[str, dict[str, Any]],
) -> dict[str, Any]:
    """Build descriptive synthesis structures from accepted reviewed objects.

    The Library may organize explicit reviewed relations for visual analysis, but
    it does not create durable scholarly conclusions. Platform Core remains the
    governed authority for claims/findings/arguments and cross-study synthesis.
    """
    items = [dict(x) for x in (research_overlays.get("items") or []) if isinstance(x, dict)]
    relations = [dict(x) for x in (research_overlays.get("relations") or []) if isinstance(x, dict)]
    by_id = {str(item.get("id")): item for item in items if item.get("id")}

    support_structures = _support_components(items, relations)

    contradiction_pairs: list[dict[str, Any]] = []
    publication_pair_stats: dict[tuple[str, str], dict[str, Any]] = {}
    argument_paths: list[dict[str, Any]] = []
    for rel in relations:
        source_id, target_id = str(rel.get("source") or ""), str(rel.get("target") or "")
        source, target = by_id.get(source_id), by_id.get(target_id)
        if not source or not target:
            continue
        source_record = str(source.get("record_id") or "")
        target_record = str(target.get("record_id") or "")
        family = str(rel.get("relationship_family") or "")
        relation_weight = max(0.0, min(_weight(source), _weight(target)))
        path = {
            "source_publication_record_id": source_record,
            "source_research_object_id": source_id,
            "relationship_family": family,
            "relationship_basis": rel.get("relationship_basis"),
            "target_research_object_id": target_id,
            "target_publication_record_id": target_record,
            "traceability_weight": round(relation_weight, 6),
            "explicit_reviewed_relation": True,
        }
        argument_paths.append(path)
        if family == "contradiction":
            contradiction_pairs.append({
                **path,
                "source_label": source.get("label"),
                "target_label": target.get("label"),
                "cross_publication": bool(source_record and target_record and source_record != target_record),
            })

        if source_record and target_record and source_record != target_record and family in {"support", "contradiction"}:
            pair = tuple(sorted((source_record, target_record)))
            stat = publication_pair_stats.setdefault(pair, {
                "source_record_id": pair[0],
                "target_record_id": pair[1],
                "explicit_support_count": 0,
                "explicit_contradiction_count": 0,
                "support_traceability_sum": 0.0,
                "contradiction_traceability_sum": 0.0,
            })
            if family == "support":
                stat["explicit_support_count"] += 1
                stat["support_traceability_sum"] += relation_weight
            else:
                stat["explicit_contradiction_count"] += 1
                stat["contradiction_traceability_sum"] += relation_weight

    publication_synthesis = []
    for stat in publication_pair_stats.values():
        stat["support_traceability_sum"] = round(float(stat["support_traceability_sum"]), 6)
        stat["contradiction_traceability_sum"] = round(float(stat["contradiction_traceability_sum"]), 6)
        stat["evidence_balance_is_truth_score"] = False
        publication_synthesis.append(stat)
    publication_synthesis.sort(key=lambda x: (x["explicit_support_count"] + x["explicit_contradiction_count"], x["source_record_id"], x["target_record_id"]), reverse=True)

    hypotheses: dict[str, dict[str, Any]] = {}
    for item in items:
        ident = _hypothesis_identity(item)
        if not ident:
            continue
        key, label, set_key = ident
        h = hypotheses.setdefault(key, {
            "id": f"hypothesis:{key}",
            "hypothesis_key": key,
            "label": label,
            "competing_set": set_key or None,
            "member_ids": [],
            "publication_record_ids": [],
            "support_count": 0,
            "challenge_count": 0,
            "neutral_count": 0,
            "unspecified_count": 0,
            "support_traceability_sum": 0.0,
            "challenge_traceability_sum": 0.0,
            "explicit_metadata_only": True,
        })
        h["member_ids"].append(str(item.get("id")))
        rid = str(item.get("record_id") or "")
        if rid and rid not in h["publication_record_ids"]:
            h["publication_record_ids"].append(rid)
        stance = _hypothesis_stance(item)
        h[f"{stance}_count"] += 1
        if stance == "support":
            h["support_traceability_sum"] += _weight(item)
        elif stance == "challenge":
            h["challenge_traceability_sum"] += _weight(item)

    hypothesis_items = []
    competing_sets: dict[str, list[str]] = defaultdict(list)
    membership_edges: list[dict[str, Any]] = []
    for h in hypotheses.values():
        h["support_traceability_sum"] = round(float(h["support_traceability_sum"]), 6)
        h["challenge_traceability_sum"] = round(float(h["challenge_traceability_sum"]), 6)
        h["evidence_balance_is_truth_score"] = False
        hypothesis_items.append(h)
        if h.get("competing_set"):
            competing_sets[str(h["competing_set"])].append(str(h["id"]))
        for member_id in h["member_ids"]:
            item = by_id.get(member_id) or {}
            membership_edges.append({
                "source": member_id,
                "target": h["id"],
                "relationship_basis": "explicit-hypothesis-membership",
                "relationship_family": "hypothesis-membership",
                "stance": _hypothesis_stance(item),
                "weight": max(0.05, _weight(item)),
                "explicitly_encoded": True,
                "truth_assertion": False,
                "causal_assertion": False,
            })
    hypothesis_items.sort(key=lambda x: (len(x["member_ids"]), x["hypothesis_key"]), reverse=True)

    competing_hypothesis_sets = [
        {"set_key": key, "hypothesis_ids": sorted(ids), "hypothesis_count": len(ids), "explicitly_encoded": True}
        for key, ids in sorted(competing_sets.items()) if len(set(ids)) >= 2
    ]

    return {
        "schema": SYNTHESIS_CONTRACT,
        "support_structures": support_structures,
        "contradiction_pairs": contradiction_pairs,
        "publication_synthesis": publication_synthesis,
        "argument_paths": argument_paths,
        "hypotheses": hypothesis_items,
        "hypothesis_membership_edges": membership_edges,
        "competing_hypothesis_sets": competing_hypothesis_sets,
        "metrics": {
            "reviewed_research_object_count": len(items),
            "support_structure_count": len(support_structures),
            "explicit_support_relation_count": sum(1 for x in relations if x.get("relationship_family") == "support"),
            "explicit_contradiction_relation_count": len(contradiction_pairs),
            "cross_publication_relation_pair_count": len(publication_synthesis),
            "explicit_hypothesis_count": len(hypothesis_items),
            "explicit_competing_hypothesis_set_count": len(competing_hypothesis_sets),
            "argument_path_count": len(argument_paths),
        },
        "platform_core": {
            "durable_synthesis_authority": "platform-core",
            "library_role": "source-grounded descriptive synthesis and visualization",
            "core_role": "governed claims/findings/arguments and durable cross-study synthesis",
            "automatic_core_write": False,
        },
        "interpretation": {
            "accepted_reviewed_objects_only": True,
            "consensus_inferred": False,
            "hypotheses_inferred": False,
            "competing_hypotheses_require_explicit_metadata": True,
            "support_component_means_consensus": False,
            "evidence_balance_is_truth_score": False,
            "contradiction_requires_explicit_reviewed_relation": True,
            "synthesis_creates_new_claims": False,
        },
    }
