from __future__ import annotations

from collections import Counter
from dataclasses import dataclass
from datetime import datetime, timezone
import hashlib
import re
from typing import Any


@dataclass(frozen=True)
class BiomedicalEvidenceGraphDescriptor:
    key: str = "biomedical-evidence-graph"
    name: str = "Biomedical Evidence Graph & Evidence Synthesis"
    version: str = "1.0"

    def to_dict(self) -> dict[str, Any]:
        return {
            "key": self.key,
            "name": self.name,
            "version": self.version,
            "capabilities": [
                "provenance-backed-evidence-graph",
                "trial-publication-linkage",
                "condition-intervention-outcome-relationships",
                "terminology-candidate-context",
                "regulatory-evidence-family-integration",
                "descriptive-evidence-synthesis",
                "integrity-signal-propagation",
                "research-librarian-handoff",
                "lab-aggregate-results-handoff",
            ],
            "governance": {
                "research_only": True,
                "clinical_decision_support": False,
                "patient_specific_diagnosis": False,
                "patient_specific_treatment": False,
                "semantic_equivalence_asserted": False,
                "causal_relationship_inferred": False,
                "formal_grade_generated": False,
                "pooled_effect_generated": False,
                "comparative_effectiveness_conclusion_generated": False,
                "clinical_recommendation_generated": False,
                "human_review_required": True,
            },
        }


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _dict(value: Any) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return value if isinstance(value, list) else []


def _clean(value: Any) -> str:
    return "" if value is None else re.sub(r"\s+", " ", str(value)).strip()


def _slug(value: str) -> str:
    cleaned = re.sub(r"[^a-z0-9]+", "-", value.casefold()).strip("-")
    return cleaned[:72] or "item"


def _digest(value: str, length: int = 16) -> str:
    return hashlib.sha256(value.encode("utf-8")).hexdigest()[:length]


def _node_id(kind: str, identifier: Any = None, label: Any = None) -> str:
    ident = _clean(identifier)
    if ident:
        return f"{kind}:{_slug(ident)}:{_digest(ident, 10)}"
    text = _clean(label) or kind
    return f"{kind}:{_slug(text)}:{_digest(text, 10)}"


def _pmid(value: Any) -> str:
    text = _clean(value).upper()
    if text.startswith("PMID:"):
        text = text[5:]
    return re.sub(r"\D", "", text)


class BiomedicalEvidenceGraphEngine:
    """Build a bounded evidence graph using explicit source relationships only.

    The graph is a research-navigation structure. It does not infer semantic
    equivalence, causality, comparative effectiveness, formal certainty ratings,
    or clinical recommendations. Every edge carries the provenance of the source
    or transformation that justified creating it.
    """

    descriptor = BiomedicalEvidenceGraphDescriptor()

    def __init__(
        self,
        evidence_grading: Any,
        clinical_trials: Any,
        terminology: Any,
        fda_registry: Any,
    ) -> None:
        self.evidence_grading = evidence_grading
        self.clinical_trials = clinical_trials
        self.terminology = terminology
        self.fda_registry = fda_registry

    def manifest(self) -> dict[str, Any]:
        return {
            "schema": "sc-biomedical-evidence-graph-manifest/1.0",
            "framework": self.descriptor.to_dict(),
            "node_types": [
                "research-question",
                "publication",
                "clinical-trial",
                "condition",
                "intervention",
                "outcome",
                "terminology-concept",
                "regulatory-record",
            ],
            "edge_types": [
                "retrieved-for-question",
                "studies-condition",
                "evaluates-intervention",
                "measures-outcome",
                "registry-links-publication",
                "candidate-concept-for-question",
                "regulatory-record-for-question",
            ],
            "edge_policy": {
                "explicit_relationships_only": True,
                "trial_publication_edges": "Created only from ClinicalTrials.gov reference PMIDs.",
                "terminology_edges": "Candidate-context edges do not assert equivalence among ICD-11, MeSH, or RxNorm.",
                "regulatory_edges": "Query-context edges preserve regulatory evidence class and do not imply clinical effect.",
            },
            "synthesis_policy": {
                "state": "descriptive-graph-derived",
                "formal_systematic_review": False,
                "formal_grade_generated": False,
                "pooled_effect_generated": False,
                "comparative_effectiveness_conclusion_generated": False,
                "clinical_recommendation_generated": False,
                "human_review_required": True,
            },
            "retrieved_at": _now(),
        }

    @staticmethod
    def _add_node(nodes: dict[str, dict[str, Any]], node: dict[str, Any]) -> str:
        node_id = str(node["id"])
        existing = nodes.get(node_id)
        if existing is None:
            nodes[node_id] = node
        else:
            # Preserve richer values without silently overwriting source identity.
            for key, value in node.items():
                if key not in existing or existing[key] in (None, "", [], {}):
                    existing[key] = value
        return node_id

    @staticmethod
    def _add_edge(edges: dict[str, dict[str, Any]], edge: dict[str, Any]) -> str:
        raw = "|".join([
            str(edge.get("source") or ""),
            str(edge.get("type") or ""),
            str(edge.get("target") or ""),
            str(_dict(edge.get("provenance")).get("source_key") or ""),
        ])
        edge_id = f"edge:{_digest(raw, 20)}"
        edge["id"] = edge_id
        edges.setdefault(edge_id, edge)
        return edge_id

    @staticmethod
    def _question_node(query: str) -> dict[str, Any]:
        return {
            "id": _node_id("question", query, query),
            "type": "research-question",
            "label": query,
            "identifier": None,
            "source_key": "user-query",
            "provenance": {"source_key": "user-query", "relationship_state": "explicit-query-context"},
        }

    def _add_publication(self, nodes: dict[str, dict[str, Any]], row: dict[str, Any]) -> str:
        identifier = row.get("identifier")
        pmid = _pmid(identifier)
        canonical = f"PMID:{pmid}" if pmid else identifier
        profile = _dict(row.get("evidence_profile"))
        return self._add_node(nodes, {
            "id": _node_id("publication", canonical, row.get("title")),
            "type": "publication",
            "label": row.get("title") or canonical or "Publication",
            "identifier": canonical,
            "source_key": row.get("source_key") or "pubmed",
            "url": row.get("source_url"),
            "attributes": {
                "journal": row.get("journal"),
                "published_at": row.get("published_at"),
                "publication_types": _list(row.get("publication_types")),
                "study_design": _dict(profile.get("study_design")),
                "integrity": _dict(profile.get("integrity")),
            },
            "provenance": row.get("provenance") or {"source_key": row.get("source_key") or "pubmed"},
        })

    def _add_trial_structure(
        self,
        nodes: dict[str, dict[str, Any]],
        edges: dict[str, dict[str, Any]],
        row: dict[str, Any],
        question_id: str | None,
    ) -> str:
        nct = _clean(row.get("nct_id") or row.get("identifier"))
        trial_id = self._add_node(nodes, {
            "id": _node_id("trial", nct, row.get("title")),
            "type": "clinical-trial",
            "label": row.get("title") or row.get("official_title") or nct or "Clinical trial",
            "identifier": nct or None,
            "source_key": row.get("source_key") or "clinicaltrials-intelligence",
            "url": row.get("source_url"),
            "attributes": {
                "overall_status": row.get("overall_status"),
                "study_design": _dict(row.get("study_design")),
                "results_state": _dict(row.get("results_state")),
                "evidence_profile": _dict(row.get("evidence_profile")),
            },
            "provenance": row.get("provenance") or {"source_key": "clinicaltrials.gov"},
        })
        if question_id:
            self._add_edge(edges, {
                "source": question_id, "target": trial_id, "type": "retrieved-for-question",
                "label": "retrieved trial",
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "query-retrieval"},
            })

        for condition in _list(row.get("conditions")):
            label = _clean(condition)
            if not label:
                continue
            cid = self._add_node(nodes, {
                "id": _node_id("condition", label, label), "type": "condition", "label": label,
                "identifier": None, "source_key": "clinicaltrials.gov",
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "registry-reported-condition"},
            })
            self._add_edge(edges, {
                "source": trial_id, "target": cid, "type": "studies-condition", "label": "studies condition",
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "registry-reported"},
            })

        for intervention in _list(row.get("interventions")):
            item = _dict(intervention)
            label = _clean(item.get("name") or intervention)
            if not label:
                continue
            iid = self._add_node(nodes, {
                "id": _node_id("intervention", label, label), "type": "intervention", "label": label,
                "identifier": None, "source_key": "clinicaltrials.gov",
                "attributes": {"intervention_type": item.get("type")},
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "registry-reported-intervention"},
            })
            self._add_edge(edges, {
                "source": trial_id, "target": iid, "type": "evaluates-intervention", "label": "evaluates intervention",
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "registry-reported"},
            })

        outcomes = list(_list(row.get("primary_outcomes")))
        outcomes.extend(_list(_dict(row.get("results_summary")).get("outcome_measures")))
        seen_outcomes: set[str] = set()
        for outcome in outcomes:
            item = _dict(outcome)
            label = _clean(item.get("measure") or item.get("title") or outcome)
            if not label:
                continue
            outcome_key = label.casefold()
            if outcome_key in seen_outcomes and not _list(item.get("analyses")):
                continue
            seen_outcomes.add(outcome_key)
            oid = self._add_node(nodes, {
                "id": _node_id("outcome", f"{nct}:{label}", label), "type": "outcome", "label": label,
                "identifier": None, "source_key": "clinicaltrials.gov",
                "attributes": {
                    "time_frame": item.get("time_frame") or item.get("timeFrame"),
                    "type": item.get("type"),
                    "units": item.get("units"),
                    "analyses": _list(item.get("analyses")),
                    "aggregate_results_only": bool(_list(item.get("analyses"))),
                },
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "registry-reported-outcome"},
            })
            self._add_edge(edges, {
                "source": trial_id, "target": oid, "type": "measures-outcome", "label": "measures outcome",
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "registry-reported"},
            })

        for ref in _list(_dict(row.get("publications")).get("references")):
            item = _dict(ref)
            pmid = _pmid(item.get("pmid"))
            if not pmid:
                continue
            pub_id = self._add_node(nodes, {
                "id": _node_id("publication", f"PMID:{pmid}", item.get("citation")),
                "type": "publication",
                "label": item.get("citation") or f"PubMed {pmid}",
                "identifier": f"PMID:{pmid}",
                "source_key": "clinicaltrials.gov-reference",
                "url": f"https://pubmed.ncbi.nlm.nih.gov/{pmid}/",
                "attributes": {"reference_type": item.get("type"), "retractions": _list(item.get("retractions"))},
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "registry-reference"},
            })
            ref_type = _clean(item.get("type")).upper()
            self._add_edge(edges, {
                "source": trial_id, "target": pub_id, "type": "registry-links-publication",
                "label": "results publication" if ref_type == "RESULT" else "related publication",
                "attributes": {"reference_type": ref_type or None, "exact_identifier_match": True},
                "provenance": {"source_key": "clinicaltrials.gov", "relationship_state": "explicit-pmid-reference"},
            })
        return trial_id

    def _add_terminology(
        self,
        nodes: dict[str, dict[str, Any]],
        edges: dict[str, dict[str, Any]],
        question_id: str,
        payload: dict[str, Any],
    ) -> int:
        count = 0
        for group in _list(payload.get("groups")):
            source = _dict(_dict(group).get("source"))
            source_key = source.get("key") or "terminology"
            for row in _list(_dict(group).get("results")):
                item = _dict(row)
                identifier = item.get("identifier") or item.get("uri")
                label = item.get("label") or identifier
                if not label:
                    continue
                node_id = self._add_node(nodes, {
                    "id": _node_id("concept", identifier, label),
                    "type": "terminology-concept",
                    "label": label,
                    "identifier": identifier,
                    "source_key": source_key,
                    "url": item.get("source_url") or item.get("uri"),
                    "attributes": {
                        "code": item.get("code"), "release_id": item.get("release_id"),
                        "rxcui": item.get("rxcui"), "candidate_only": True,
                    },
                    "provenance": item.get("provenance") or {"source_key": source_key},
                })
                self._add_edge(edges, {
                    "source": question_id, "target": node_id, "type": "candidate-concept-for-question",
                    "label": "candidate concept",
                    "attributes": {"semantic_equivalence_asserted": False},
                    "provenance": {"source_key": source_key, "relationship_state": "candidate-alignment"},
                })
                count += 1
        return count

    def _add_regulatory(
        self,
        nodes: dict[str, dict[str, Any]],
        edges: dict[str, dict[str, Any]],
        question_id: str,
        payload: dict[str, Any],
    ) -> int:
        count = 0
        for group in _list(payload.get("groups")):
            source = _dict(_dict(group).get("source"))
            source_key = source.get("key") or "fda"
            evidence_class = source.get("evidence_class")
            for row in _list(_dict(group).get("results")):
                item = _dict(row)
                identifier = item.get("identifier")
                label = item.get("title") or identifier or source.get("name") or "FDA record"
                node_id = self._add_node(nodes, {
                    "id": _node_id("regulatory", f"{source_key}:{identifier or label}", label),
                    "type": "regulatory-record",
                    "label": label,
                    "identifier": identifier,
                    "source_key": source_key,
                    "url": item.get("source_url"),
                    "attributes": {
                        "record_type": item.get("record_type"),
                        "evidence_class": evidence_class,
                        "regulatory_interpretation": _dict(item.get("regulatory_interpretation")),
                    },
                    "provenance": item.get("provenance") or {"source_key": source_key, "steward": "U.S. Food and Drug Administration"},
                })
                self._add_edge(edges, {
                    "source": question_id, "target": node_id, "type": "regulatory-record-for-question",
                    "label": "regulatory context",
                    "attributes": {"evidence_class": evidence_class, "clinical_effect_inferred": False},
                    "provenance": {"source_key": source_key, "relationship_state": "query-retrieval"},
                })
                count += 1
        return count

    def build_graph(
        self,
        query: str,
        *,
        literature_limit: int = 8,
        trial_limit: int = 8,
        concept_limit: int = 3,
        regulatory_limit: int = 2,
    ) -> dict[str, Any]:
        query = query.strip()
        if not query:
            raise ValueError("query is required")
        literature_limit = max(1, min(int(literature_limit), 20))
        trial_limit = max(1, min(int(trial_limit), 20))
        concept_limit = max(1, min(int(concept_limit), 5))
        regulatory_limit = max(1, min(int(regulatory_limit), 5))

        nodes: dict[str, dict[str, Any]] = {}
        edges: dict[str, dict[str, Any]] = {}
        errors: list[dict[str, str]] = []
        question = self._question_node(query)
        question_id = self._add_node(nodes, question)

        body: dict[str, Any]
        try:
            body = self.evidence_grading.search_body(query, literature_limit=literature_limit, trial_limit=trial_limit)
        except Exception as exc:
            body = {"summary": {}, "literature": [], "trials": [], "errors": []}
            errors.append({"source": "evidence-body", "error": exc.__class__.__name__})

        for record in _list(body.get("literature")):
            row = _dict(record)
            pub_id = self._add_publication(nodes, row)
            self._add_edge(edges, {
                "source": question_id, "target": pub_id, "type": "retrieved-for-question", "label": "retrieved publication",
                "provenance": {"source_key": row.get("source_key") or "pubmed", "relationship_state": "query-retrieval"},
            })

        for record in _list(body.get("trials")):
            self._add_trial_structure(nodes, edges, _dict(record), question_id)

        terminology_payload: dict[str, Any] = {"groups": [], "errors": []}
        try:
            terminology_payload = self.terminology.resolve(query, limit=concept_limit)
            self._add_terminology(nodes, edges, question_id, terminology_payload)
        except Exception as exc:
            errors.append({"source": "medical-terminology", "error": exc.__class__.__name__})

        regulatory_payload: dict[str, Any] = {"groups": [], "errors": []}
        try:
            regulatory_payload = self.fda_registry.unified_search(
                query,
                limit=regulatory_limit,
                source_keys=["drugsfda", "fda-labels", "fda-adverse-events", "orange-book"],
            )
            self._add_regulatory(nodes, edges, question_id, regulatory_payload)
        except Exception as exc:
            errors.append({"source": "fda-regulatory", "error": exc.__class__.__name__})

        for item in _list(body.get("errors")):
            row = _dict(item)
            errors.append({"source": str(row.get("source") or "evidence-body"), "error": str(row.get("error") or "contained-source-error")})
        for item in _list(terminology_payload.get("errors")):
            row = _dict(item)
            errors.append({"source": str(row.get("source_key") or "terminology"), "error": str(row.get("error") or "contained-source-error")})
        for item in _list(regulatory_payload.get("errors")):
            row = _dict(item)
            errors.append({"source": str(row.get("source_key") or "fda"), "error": str(row.get("error") or "contained-source-error")})

        node_rows = list(nodes.values())
        edge_rows = list(edges.values())
        type_counts = Counter(str(row.get("type") or "unknown") for row in node_rows)
        edge_counts = Counter(str(row.get("type") or "unknown") for row in edge_rows)
        exact_trial_publication_links = edge_counts.get("registry-links-publication", 0)
        result_trials = sum(1 for row in node_rows if row.get("type") == "clinical-trial" and bool(_dict(_dict(row.get("attributes")).get("results_state")).get("has_results")))
        integrity_flags = int(_dict(body.get("summary")).get("integrity_review_flag_count") or 0)

        synthesis = {
            "state": "descriptive-graph-derived",
            "question": query,
            "coverage": {
                "node_count": len(node_rows),
                "edge_count": len(edge_rows),
                "node_types": dict(sorted(type_counts.items())),
                "edge_types": dict(sorted(edge_counts.items())),
                "design_distribution": _dict(_dict(body.get("summary")).get("design_distribution")),
                "result_bearing_trial_count": result_trials,
                "exact_trial_publication_link_count": exact_trial_publication_links,
                "integrity_review_flag_count": integrity_flags,
            },
            "evidence_findings": [
                f"Retrieved {type_counts.get('publication', 0)} publication node(s) and {type_counts.get('clinical-trial', 0)} clinical-trial node(s).",
                f"Preserved {exact_trial_publication_links} explicit ClinicalTrials.gov PMID relationship(s).",
                f"Included {type_counts.get('terminology-concept', 0)} terminology candidate node(s) without asserting semantic equivalence.",
                f"Included {type_counts.get('regulatory-record', 0)} FDA regulatory-context node(s) while preserving evidence class.",
            ],
            "evidence_gaps": [],
            "boundaries": {
                "formal_systematic_review": False,
                "formal_grade_generated": False,
                "formal_risk_of_bias_judgment_generated": False,
                "pooled_effect_generated": False,
                "heterogeneity_statistic_generated": False,
                "comparative_effectiveness_conclusion_generated": False,
                "clinical_recommendation_generated": False,
                "semantic_equivalence_asserted": False,
                "causal_relationship_inferred": False,
                "human_review_required": True,
            },
        }
        if type_counts.get("publication", 0) == 0:
            synthesis["evidence_gaps"].append("No publication nodes were available from this bounded retrieval.")
        if type_counts.get("clinical-trial", 0) == 0:
            synthesis["evidence_gaps"].append("No ClinicalTrials.gov trial nodes were available from this bounded retrieval.")
        if exact_trial_publication_links == 0:
            synthesis["evidence_gaps"].append("No exact registry PMID linkage was present in this graph; this does not prove that relevant publications do not exist.")
        if type_counts.get("regulatory-record", 0) == 0:
            synthesis["evidence_gaps"].append("No FDA regulatory-context nodes were available from this bounded retrieval.")
        if errors:
            synthesis["evidence_gaps"].append("One or more upstream source families returned a contained error; graph coverage may be incomplete.")

        return {
            "schema": "sc-biomedical-evidence-graph/1.0",
            "query": query,
            "graph": {"directed": True, "nodes": node_rows, "edges": edge_rows},
            "synthesis": synthesis,
            "source_payloads": {
                "evidence_body_summary": _dict(body.get("summary")),
                "terminology_crosswalk": _dict(terminology_payload.get("crosswalk")),
                "regulatory_governance": _dict(regulatory_payload.get("governance")),
            },
            "errors": errors,
            "governance": self.descriptor.to_dict()["governance"],
            "retrieved_at": _now(),
        }

    def synthesis(
        self,
        query: str,
        *,
        literature_limit: int = 8,
        trial_limit: int = 8,
        concept_limit: int = 3,
        regulatory_limit: int = 2,
    ) -> dict[str, Any]:
        payload = self.build_graph(
            query,
            literature_limit=literature_limit,
            trial_limit=trial_limit,
            concept_limit=concept_limit,
            regulatory_limit=regulatory_limit,
        )
        return {
            "schema": "sc-biomedical-evidence-synthesis/1.0",
            "query": query,
            "synthesis": payload["synthesis"],
            "graph_summary": {
                "node_count": len(_list(_dict(payload.get("graph")).get("nodes"))),
                "edge_count": len(_list(_dict(payload.get("graph")).get("edges"))),
            },
            "errors": payload["errors"],
            "governance": payload["governance"],
            "retrieved_at": payload["retrieved_at"],
        }

    def trial_neighborhood(self, nct_id: str) -> dict[str, Any]:
        trial = self.clinical_trials.get_study(nct_id)
        trial = dict(_dict(trial))
        trial["evidence_profile"] = self.evidence_grading.profile_trial_record(trial)
        nodes: dict[str, dict[str, Any]] = {}
        edges: dict[str, dict[str, Any]] = {}
        center = self._add_trial_structure(nodes, edges, trial, None)
        return {
            "schema": "sc-biomedical-evidence-trial-neighborhood/1.0",
            "center": center,
            "graph": {"directed": True, "nodes": list(nodes.values()), "edges": list(edges.values())},
            "evidence_profile": trial["evidence_profile"],
            "governance": self.descriptor.to_dict()["governance"],
            "retrieved_at": _now(),
        }
