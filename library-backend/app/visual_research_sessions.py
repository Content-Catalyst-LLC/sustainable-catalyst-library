from __future__ import annotations

from datetime import datetime, timezone
import hashlib
import json
from typing import Any

SESSION_CONTRACT = "sc-library-visual-research-session/1.0"
WORKSPACE_HANDOFF_CONTRACT = "sc-library-workspace-visual-research-handoff/1.0"


def _canonical(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False)


def corpus_fingerprint(corpus: dict[str, Any]) -> tuple[str, list[dict[str, str]]]:
    refs=[]
    for node in corpus.get("nodes") or []:
        if node.get("kind") != "publication":
            continue
        refs.append({
            "record_id": str(node.get("id") or ""),
            "content_hash": str(node.get("source_content_hash") or ""),
            "canonical_url": str(node.get("canonical_url") or ""),
        })
    refs=sorted(refs,key=lambda x:x["record_id"])
    digest=hashlib.sha256(_canonical(refs).encode("utf-8")).hexdigest()
    return digest, refs


def sanitize_visual_state(state: dict[str, Any] | None) -> dict[str, Any]:
    state=state if isinstance(state,dict) else {}
    q=state.get("visual_query") if isinstance(state.get("visual_query"),dict) else state
    selected=[str(x) for x in (q.get("selected_node_ids") or []) if str(x)][:250]
    bases=[str(x) for x in (q.get("relationship_bases") or []) if str(x)][:40]
    camera=state.get("camera") if isinstance(state.get("camera"),dict) else {}
    return {
        "schema":"sc-library-reproducible-visual-state/1.0",
        "view":str(q.get("view") or state.get("view") or "knowledge-landscape")[:80],
        "layout":str(q.get("layout") or state.get("layout") or "force")[:80],
        "selected_node_ids":selected,
        "selected_region_id":str(q.get("selected_region_id") or "")[:160] or None,
        "selected_year":int(q.get("selected_year")) if str(q.get("selected_year") or "").isdigit() else None,
        "text":str(q.get("text") or "")[:500],
        "mode":"isolate" if str(q.get("mode"))=="isolate" else "highlight",
        "relationship_bases":bases,
        "minimum_relationship_strength":max(0.0,min(1.0,float(q.get("minimum_relationship_strength") or 0.0))),
        "terrain_elevation_metric":str(q.get("terrain_elevation_metric") or state.get("terrain_elevation_metric") or "relationship_density")[:80],
        "camera":{
            "azimuth":float(camera.get("azimuth") or -0.66),
            "elevation":float(camera.get("elevation") or 0.72),
            "zoom":float(camera.get("zoom") or 1.0),
        },
        "viewport":{
            "zoom":float((state.get("viewport") or {}).get("zoom") or state.get("zoom") or 1.0),
            "pan_x":float((state.get("viewport") or {}).get("pan_x") or state.get("pan_x") or 0.0),
            "pan_y":float((state.get("viewport") or {}).get("pan_y") or state.get("pan_y") or 0.0),
        },
    }


def build_visual_research_session_package(corpus: dict[str, Any], visual_state: dict[str, Any] | None, *, session_name: str="", note: str="", target_workspace: bool=False) -> dict[str, Any]:
    fingerprint, refs=corpus_fingerprint(corpus)
    state=sanitize_visual_state(visual_state)
    core={
        "corpus_fingerprint":fingerprint,
        "visual_state":state,
        "source_record_ids":[x["record_id"] for x in refs],
    }
    sid="vrs-"+hashlib.sha256(_canonical(core).encode("utf-8")).hexdigest()[:20]
    now=datetime.now(timezone.utc).isoformat().replace('+00:00','Z')
    selected_publications=[x for x in state["selected_node_ids"] if x in {r["record_id"] for r in refs}]
    package={
        "schema":SESSION_CONTRACT,
        "session_id":sid,
        "name":str(session_name or "Visual research session")[:160],
        "note":str(note or "")[:1000],
        "created_at":now,
        "source_product":"knowledge-library",
        "corpus_snapshot":{
            "fingerprint_sha256":fingerprint,
            "selection":(corpus.get("corpus") or {}).get("selection"),
            "source_key":(corpus.get("corpus") or {}).get("source_key"),
            "publication_count":int((corpus.get("metrics") or {}).get("publication_count") or 0),
            "topic_count":int((corpus.get("metrics") or {}).get("topic_count") or 0),
            "source_records":refs,
        },
        "visual_state":state,
        "selected_publication_record_ids":selected_publications,
        "contracts":{
            "corpus":corpus.get("schema"),
            "visual_query":(corpus.get("visual_query") or {}).get("schema"),
            "terrain":(corpus.get("knowledge_terrain_4d") or {}).get("schema"),
            "session":SESSION_CONTRACT,
        },
        "reproducibility":{
            "source_hashes_preserved":True,
            "corpus_fingerprint_preserved":True,
            "query_state_preserved":True,
            "camera_state_preserved":True,
            "terrain_metric_preserved":True,
            "deterministic_region_coordinates":True,
            "source_records_are_references":True,
        },
        "boundaries":{
            "session_is_research_conclusion":False,
            "session_mutates_library_sources":False,
            "workspace_handoff_is_automatic_write":False,
            "workspace_acceptance_required":True,
            "references_only":True,
        },
    }
    package["workspace_handoff"]={
        "schema":WORKSPACE_HANDOFF_CONTRACT,
        "target_product":"workspace",
        "handoff_type":"visual-research-session",
        "session_id":sid,
        "corpus_fingerprint_sha256":fingerprint,
        "selected_publication_record_ids":selected_publications,
        "source_record_ids":[x["record_id"] for x in refs],
        "visual_state":state,
        "references_only":True,
        "requires_user_acceptance":True,
        "automatic_workspace_write":False,
        "requested":bool(target_workspace),
    }
    return package
