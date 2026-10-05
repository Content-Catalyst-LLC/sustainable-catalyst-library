from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.26.0"
BACKEND_VERSION = "3.26.0"
WEB_VERSION = "2.26.0"
SDK_VERSION = "1.26.0"

CONTRACT = "sc-library-research-publication-studio/1.0"
READINESS_CONTRACT = "sc-library-research-publication-studio-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-publication-studio-bootstrap/1.0"
DRAFT_CONTRACT = "sc-library-research-publication-draft/1.0"
SECTION_INVENTORY_CONTRACT = "sc-library-research-publication-section-inventory/1.0"
ASSET_INVENTORY_CONTRACT = "sc-library-research-publication-asset-inventory/1.0"
CITATION_INVENTORY_CONTRACT = "sc-library-research-publication-citation-inventory/1.0"
EDITORIAL_AUDIT_CONTRACT = "sc-library-research-publication-editorial-audit/1.0"
READINESS_AUDIT_CONTRACT = "sc-library-research-publication-readiness-audit/1.0"
EXPORT_CONTRACT = "sc-library-research-publication-studio-export/1.0"

MAX_SECTIONS = 250
MAX_CONTRIBUTORS = 150
MAX_FIGURES = 500
MAX_TABLES = 500
MAX_CITATIONS = 5000
MAX_APPENDICES = 250

PUBLICATION_PROFILES: dict[str, dict[str, Any]] = {
    "research-report": {
        "label": "Research report",
        "required_sections": ["abstract", "introduction", "methods", "findings", "limitations", "references"],
    },
    "journal-article": {
        "label": "Journal article",
        "required_sections": ["abstract", "introduction", "methods", "results", "discussion", "references"],
    },
    "technical-report": {
        "label": "Technical report",
        "required_sections": ["executive-summary", "methods", "results", "limitations", "references"],
    },
    "policy-brief": {
        "label": "Policy brief",
        "required_sections": ["executive-summary", "findings", "recommendations", "references"],
    },
    "white-paper": {
        "label": "White paper",
        "required_sections": ["executive-summary", "background", "findings", "conclusion", "references"],
    },
    "research-note": {
        "label": "Research note",
        "required_sections": ["abstract", "methods", "findings", "references"],
    },
}

SECTION_KINDS = {
    "title-page", "abstract", "executive-summary", "introduction", "background", "methods",
    "results", "findings", "discussion", "limitations", "recommendations", "conclusion",
    "references", "appendices", "acknowledgments", "data-availability", "ethics", "funding",
    "conflicts", "custom",
}
SECTION_STATES = {"draft", "reviewed", "final", "reference", "unknown"}


def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 16000) -> str:
    return " ".join(str(value or "").split())[:limit]


def _unique_strings(value: Any, limit: int = 5000) -> list[str]:
    out: list[str] = []
    for raw in _list(value):
        item = _clean(raw, 2000)
        if item and item not in out:
            out.append(item)
        if len(out) >= limit:
            break
    return out


def _word_count(value: Any) -> int:
    return len(_clean(value, 2_000_000).split())


def guardrails() -> dict[str, bool]:
    return {
        "publication_studio_is_new_source_authority": False,
        "publication_studio_is_new_evidence_authority": False,
        "publication_studio_is_research_package_authority": False,
        "publication_studio_is_publishing_persistence_authority": False,
        "research_package_composer_remains_composition_authority": True,
        "research_package_publishing_remains_export_authority": True,
        "research_package_reproducibility_service_remains_authority": True,
        "artifact_store_remains_persisted_byte_authority": True,
        "citation_service_remains_citation_authority": True,
        "publication_profile_implies_quality": False,
        "publication_readiness_implies_truth": False,
        "publication_readiness_implies_peer_review": False,
        "publication_readiness_implies_acceptance": False,
        "editorial_completeness_implies_evidence_strength": False,
        "citation_count_implies_quality": False,
        "section_order_implies_importance": False,
        "source_component_payloads_rewritten_automatically": False,
        "source_component_semantics_merged_automatically": False,
        "automatic_text_generation": False,
        "automatic_external_publication": False,
        "automatic_doi_registration": False,
        "automatic_artifact_persistence": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "server_side_studio_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "publication-profiles",
        "research-package-composition-input",
        "structured-publication-sections",
        "contributor-inventory",
        "figure-table-appendix-inventory",
        "citation-reference-inventory",
        "editorial-audit",
        "publication-readiness-audit",
        "portable-publishing-handoff-preview",
        "deterministic-publication-draft-export",
    ]
    basis = {"resources": resources, "profiles": PUBLICATION_PROFILES, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "studio_id": "research-publication-studio:" + _fp(basis)[:32],
        "studio_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/publication",
        "api_base": "/api/library/v1/research-publication",
        "resources": resources,
        "existing_authorities": {
            "research_package_composition": "research-package-composer-v6.25.0",
            "portable_export": "research-package-publishing",
            "reproducibility_package": "python-research-package-reproducibility-service",
            "citations": "python-provenance-citation-service",
            "persisted_bytes": "content-addressed-artifact-store",
            "structured_state": "postgresql",
        },
        "limits": {
            "sections": MAX_SECTIONS,
            "contributors": MAX_CONTRIBUTORS,
            "figures": MAX_FIGURES,
            "tables": MAX_TABLES,
            "citations": MAX_CITATIONS,
            "appendices": MAX_APPENDICES,
        },
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready",
        "ready": True,
        "blocking": [],
        "degraded": [],
        "authority": "python-backend-composition",
        "input_authority": "/api/library/v1/research-package-composer/compose",
        "handoff_authority": "/api/library/v1/research-package-publishing/preview",
        "server_side_studio_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "route": "/research/publication",
        "readiness": readiness(),
        "publication_profiles": PUBLICATION_PROFILES,
        "section_kinds": sorted(SECTION_KINDS),
        "section_states": sorted(SECTION_STATES),
        "operations": [
            "draft", "section-inventory", "asset-inventory", "citation-inventory",
            "editorial-audit", "readiness-audit", "publishing-handoff-preview", "export",
        ],
        "browser_storage_key": "sc-library-research-publication-studio-v1",
        "guardrails": guardrails(),
    }


def _source_package(payload: dict[str, Any]) -> dict[str, Any]:
    package = _dict(payload.get("package_composition"))
    if not package:
        package = _dict(payload.get("research_package"))
    if not package:
        package = _dict(payload.get("composition"))
    components = [dict(x) for x in _list(package.get("components")) if isinstance(x, dict)][:2000]
    return {
        "composition_id": _clean(package.get("composition_id"), 2000) or None,
        "composition_fingerprint_sha256": _clean(package.get("composition_fingerprint_sha256"), 128) or None,
        "schema": _clean(package.get("schema"), 1000) or None,
        "title": _clean(package.get("title"), 4000) or None,
        "project_id": _clean(package.get("project_id"), 1000) or None,
        "package_state": _clean(package.get("package_state"), 1000) or None,
        "components": components,
        "section_order": _unique_strings(package.get("section_order"), 2000),
        "references": _dict(package.get("references")),
        "dependency_map": _dict(package.get("dependency_map")),
        "completeness_audit": _dict(package.get("completeness_audit")),
        "provenance_audit": _dict(package.get("provenance_audit")),
        "raw": package,
    }


def _section(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    section_id = _clean(r.get("section_id") or r.get("id"), 1000) or f"section:{index}"
    kind = _clean(r.get("kind") or r.get("section_kind"), 100).lower() or "custom"
    if kind not in SECTION_KINDS:
        kind = "custom"
    state = _clean(r.get("state"), 100).lower() or "draft"
    if state not in SECTION_STATES:
        state = "unknown"
    return {
        "section_id": section_id,
        "kind": kind,
        "title": _clean(r.get("title"), 4000) or kind.replace("-", " ").title(),
        "state": state,
        "content": str(r.get("content") or "")[:250000],
        "source_component_ids": _unique_strings(r.get("source_component_ids"), 2000),
        "citation_ids": _unique_strings(r.get("citation_ids"), 5000),
        "figure_ids": _unique_strings(r.get("figure_ids"), 1000),
        "table_ids": _unique_strings(r.get("table_ids"), 1000),
        "notes": _clean(r.get("notes"), 16000) or None,
    }


def _contributor(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    return {
        "contributor_id": _clean(r.get("contributor_id") or r.get("id"), 1000) or f"contributor:{index}",
        "name": _clean(r.get("name"), 4000) or f"Contributor {index}",
        "orcid": _clean(r.get("orcid"), 1000) or None,
        "affiliation": _clean(r.get("affiliation"), 8000) or None,
        "roles": _unique_strings(r.get("roles"), 100),
        "corresponding": bool(r.get("corresponding", False)),
    }


def _figure(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    return {
        "figure_id": _clean(r.get("figure_id") or r.get("id"), 1000) or f"figure:{index}",
        "title": _clean(r.get("title"), 4000) or f"Figure {index}",
        "caption": _clean(r.get("caption"), 24000) or None,
        "alt_text": _clean(r.get("alt_text"), 12000) or None,
        "artifact_id": _clean(r.get("artifact_id"), 2000) or None,
        "source_component_ids": _unique_strings(r.get("source_component_ids"), 2000),
        "rights": _clean(r.get("rights"), 8000) or None,
    }


def _table(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    return {
        "table_id": _clean(r.get("table_id") or r.get("id"), 1000) or f"table:{index}",
        "title": _clean(r.get("title"), 4000) or f"Table {index}",
        "caption": _clean(r.get("caption"), 24000) or None,
        "artifact_id": _clean(r.get("artifact_id"), 2000) or None,
        "source_component_ids": _unique_strings(r.get("source_component_ids"), 2000),
        "columns": _unique_strings(r.get("columns"), 500),
        "notes": _clean(r.get("notes"), 16000) or None,
    }


def _citation(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    return {
        "citation_id": _clean(r.get("citation_id") or r.get("id") or r.get("key"), 1000) or f"citation:{index}",
        "key": _clean(r.get("key"), 1000) or None,
        "title": _clean(r.get("title"), 8000) or None,
        "doi": _clean(r.get("doi"), 2000) or None,
        "url": _clean(r.get("url"), 8000) or None,
        "record_id": _clean(r.get("record_id"), 2000) or None,
        "bibliographic_item": _dict(r.get("bibliographic_item")),
    }


def _appendix(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    return {
        "appendix_id": _clean(r.get("appendix_id") or r.get("id"), 1000) or f"appendix:{index}",
        "title": _clean(r.get("title"), 4000) or f"Appendix {index}",
        "content": str(r.get("content") or "")[:250000],
        "artifact_ids": _unique_strings(r.get("artifact_ids"), 5000),
        "source_component_ids": _unique_strings(r.get("source_component_ids"), 2000),
    }


def normalize_studio_input(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")

    profile = _clean(payload.get("publication_profile") or payload.get("profile"), 100).lower() or "research-report"
    if profile not in PUBLICATION_PROFILES:
        raise ValueError("unknown-publication-profile")

    raw_sections = _list(payload.get("sections"))
    raw_contributors = _list(payload.get("contributors"))
    raw_figures = _list(payload.get("figures"))
    raw_tables = _list(payload.get("tables"))
    raw_citations = _list(payload.get("citations"))
    raw_appendices = _list(payload.get("appendices"))

    limits = [
        (raw_sections, MAX_SECTIONS, "section-limit-exceeded"),
        (raw_contributors, MAX_CONTRIBUTORS, "contributor-limit-exceeded"),
        (raw_figures, MAX_FIGURES, "figure-limit-exceeded"),
        (raw_tables, MAX_TABLES, "table-limit-exceeded"),
        (raw_citations, MAX_CITATIONS, "citation-limit-exceeded"),
        (raw_appendices, MAX_APPENDICES, "appendix-limit-exceeded"),
    ]
    for values, limit, code in limits:
        if len(values) > limit:
            raise ValueError(f"{code}:{limit}")

    sections = [_section(x, i) for i, x in enumerate(raw_sections, 1)]
    contributors = [_contributor(x, i) for i, x in enumerate(raw_contributors, 1)]
    figures = [_figure(x, i) for i, x in enumerate(raw_figures, 1)]
    tables = [_table(x, i) for i, x in enumerate(raw_tables, 1)]
    citations = [_citation(x, i) for i, x in enumerate(raw_citations, 1)]
    appendices = [_appendix(x, i) for i, x in enumerate(raw_appendices, 1)]

    for label, rows, key in [
        ("section", sections, "section_id"),
        ("contributor", contributors, "contributor_id"),
        ("figure", figures, "figure_id"),
        ("table", tables, "table_id"),
        ("citation", citations, "citation_id"),
        ("appendix", appendices, "appendix_id"),
    ]:
        ids = [row[key] for row in rows]
        if len(ids) != len(set(ids)):
            raise ValueError(f"duplicate-{label}-id")

    package = _source_package(payload)
    title = _clean(payload.get("title"), 4000) or package.get("title") or "Research publication"
    project_id = _clean(payload.get("project_id"), 1000) or package.get("project_id") or None
    basis = {
        "title": title,
        "profile": profile,
        "package_id": package.get("composition_id"),
        "package_fp": package.get("composition_fingerprint_sha256"),
        "sections": sections,
        "contributors": contributors,
        "figures": figures,
        "tables": tables,
        "citations": citations,
        "appendices": appendices,
    }
    return {
        "schema": "sc-library-research-publication-studio-input/1.0",
        "studio_input_id": "research-publication-input:" + _fp(basis)[:32],
        "studio_input_fingerprint_sha256": _fp(basis),
        "title": title,
        "subtitle": _clean(payload.get("subtitle"), 4000) or None,
        "publication_profile": profile,
        "project_id": project_id,
        "research_question": _clean(payload.get("research_question"), 16000) or None,
        "abstract": str(payload.get("abstract") or "")[:60000],
        "executive_summary": str(payload.get("executive_summary") or "")[:100000],
        "keywords": _unique_strings(payload.get("keywords"), 100),
        "license": _clean(payload.get("license"), 4000) or None,
        "source_package": package,
        "sections": sections,
        "contributors": contributors,
        "figures": figures,
        "tables": tables,
        "citations": citations,
        "appendices": appendices,
        "metadata": _dict(payload.get("metadata")),
    }


def section_inventory(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_studio_input(payload)
    by_kind: dict[str, int] = {}
    by_state: dict[str, int] = {}
    rows = []
    for section in w["sections"]:
        by_kind[section["kind"]] = by_kind.get(section["kind"], 0) + 1
        by_state[section["state"]] = by_state.get(section["state"], 0) + 1
        rows.append({
            "section_id": section["section_id"],
            "kind": section["kind"],
            "title": section["title"],
            "state": section["state"],
            "word_count": _word_count(section["content"]),
            "source_component_count": len(section["source_component_ids"]),
            "citation_count": len(section["citation_ids"]),
            "figure_count": len(section["figure_ids"]),
            "table_count": len(section["table_ids"]),
        })
    basis = {"input": w["studio_input_fingerprint_sha256"], "rows": rows}
    return {
        "schema": SECTION_INVENTORY_CONTRACT,
        "inventory_id": "publication-sections:" + _fp(basis)[:32],
        "inventory_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "section_count": len(rows),
        "counts_by_kind": by_kind,
        "counts_by_state": by_state,
        "total_word_count": sum(row["word_count"] for row in rows),
        "guardrails": guardrails(),
    }


def asset_inventory(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_studio_input(payload)
    basis = {
        "input": w["studio_input_fingerprint_sha256"],
        "figures": w["figures"],
        "tables": w["tables"],
        "appendices": w["appendices"],
    }
    return {
        "schema": ASSET_INVENTORY_CONTRACT,
        "inventory_id": "publication-assets:" + _fp(basis)[:32],
        "inventory_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "figures": w["figures"],
        "tables": w["tables"],
        "appendices": w["appendices"],
        "counts": {
            "figures": len(w["figures"]),
            "tables": len(w["tables"]),
            "appendices": len(w["appendices"]),
        },
        "automatic_artifact_persistence": False,
        "guardrails": guardrails(),
    }


def citation_inventory(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_studio_input(payload)
    rows = []
    for citation in w["citations"]:
        has_identifier = bool(citation["doi"] or citation["url"] or citation["record_id"] or citation["key"])
        rows.append({**citation, "has_identifier_or_record_reference": has_identifier})
    basis = {"input": w["studio_input_fingerprint_sha256"], "rows": rows}
    return {
        "schema": CITATION_INVENTORY_CONTRACT,
        "inventory_id": "publication-citations:" + _fp(basis)[:32],
        "inventory_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "citation_count": len(rows),
        "identifier_or_record_reference_count": sum(1 for row in rows if row["has_identifier_or_record_reference"]),
        "citation_count_implies_quality": False,
        "guardrails": guardrails(),
    }


def editorial_audit(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_studio_input(payload)
    findings: list[dict[str, Any]] = []
    package = w["source_package"]
    package_component_ids = {
        _clean(x.get("component_id") or x.get("id"), 2000)
        for x in package.get("components", []) if isinstance(x, dict)
    }
    package_component_ids.discard("")

    if not package.get("composition_id") and not package.get("composition_fingerprint_sha256"):
        findings.append({"kind": "source-package-identity-not-recorded"})
    if not w["sections"]:
        findings.append({"kind": "publication-has-no-sections"})
    if not w["contributors"]:
        findings.append({"kind": "contributor-inventory-empty"})

    figure_ids = {x["figure_id"] for x in w["figures"]}
    table_ids = {x["table_id"] for x in w["tables"]}
    citation_ids = {x["citation_id"] for x in w["citations"]}

    for section in w["sections"]:
        if not section["content"].strip() and section["kind"] not in {"references", "appendices"}:
            findings.append({"kind": "section-content-empty", "section_id": section["section_id"]})
        for component_id in section["source_component_ids"]:
            if package_component_ids and component_id not in package_component_ids:
                findings.append({
                    "kind": "section-source-component-not-in-package",
                    "section_id": section["section_id"],
                    "component_id": component_id,
                })
        for figure_id in section["figure_ids"]:
            if figure_id not in figure_ids:
                findings.append({"kind": "section-figure-reference-missing", "section_id": section["section_id"], "figure_id": figure_id})
        for table_id in section["table_ids"]:
            if table_id not in table_ids:
                findings.append({"kind": "section-table-reference-missing", "section_id": section["section_id"], "table_id": table_id})
        for citation_id in section["citation_ids"]:
            if citation_id not in citation_ids:
                findings.append({"kind": "section-citation-reference-missing", "section_id": section["section_id"], "citation_id": citation_id})

    for figure in w["figures"]:
        if not figure["caption"]:
            findings.append({"kind": "figure-caption-missing", "figure_id": figure["figure_id"]})
        if not figure["artifact_id"] and not figure["source_component_ids"]:
            findings.append({"kind": "figure-source-reference-missing", "figure_id": figure["figure_id"]})

    for table in w["tables"]:
        if not table["artifact_id"] and not table["source_component_ids"]:
            findings.append({"kind": "table-source-reference-missing", "table_id": table["table_id"]})

    for citation in w["citations"]:
        if not (citation["title"] or citation["doi"] or citation["url"] or citation["record_id"] or citation["bibliographic_item"]):
            findings.append({"kind": "citation-metadata-empty", "citation_id": citation["citation_id"]})

    basis = {"input": w["studio_input_fingerprint_sha256"], "findings": findings}
    return {
        "schema": EDITORIAL_AUDIT_CONTRACT,
        "audit_id": "publication-editorial-audit:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "findings": findings,
        "finding_count": len(findings),
        "editorial_findings_imply_research_invalidity": False,
        "guardrails": guardrails(),
    }


def readiness_audit(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_studio_input(payload)
    profile = PUBLICATION_PROFILES[w["publication_profile"]]
    present = {section["kind"] for section in w["sections"] if section["content"].strip() or section["kind"] == "references"}
    missing = [kind for kind in profile["required_sections"] if kind not in present]
    editorial = editorial_audit(payload)
    package = w["source_package"]
    package_completeness = _dict(package.get("completeness_audit"))
    package_provenance = _dict(package.get("provenance_audit"))
    package_observations = {
        "composer_completeness_finding_count": int(package_completeness.get("finding_count") or 0),
        "composer_provenance_finding_count": int(package_provenance.get("finding_count") or 0),
    }
    blockers = []
    if not package.get("composition_id") and not package.get("composition_fingerprint_sha256"):
        blockers.append({"kind": "source-package-identity-required"})
    for kind in missing:
        blockers.append({"kind": "profile-required-section-missing", "section_kind": kind})
    basis = {
        "input": w["studio_input_fingerprint_sha256"],
        "profile": w["publication_profile"],
        "blockers": blockers,
        "editorial_findings": editorial["findings"],
        "package_observations": package_observations,
    }
    return {
        "schema": READINESS_AUDIT_CONTRACT,
        "audit_id": "publication-readiness:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "publication_profile": w["publication_profile"],
        "profile_label": profile["label"],
        "required_sections": profile["required_sections"],
        "present_section_kinds": sorted(present),
        "missing_required_sections": missing,
        "blockers": blockers,
        "blocker_count": len(blockers),
        "editorial_finding_count": editorial["finding_count"],
        "package_observations": package_observations,
        "ready_against_selected_profile": len(blockers) == 0,
        "readiness_implies_truth": False,
        "readiness_implies_peer_review": False,
        "readiness_implies_publication_acceptance": False,
        "guardrails": guardrails(),
    }


def build_publication_draft(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_studio_input(payload)
    sections = section_inventory(payload)
    assets = asset_inventory(payload)
    citations = citation_inventory(payload)
    editorial = editorial_audit(payload)
    ready = readiness_audit(payload)
    package = w["source_package"]
    source_package_ref = {
        "composition_id": package.get("composition_id"),
        "composition_fingerprint_sha256": package.get("composition_fingerprint_sha256"),
        "schema": package.get("schema"),
        "package_state": package.get("package_state"),
        "component_count": len(package.get("components", [])),
    }
    basis = {
        "title": w["title"],
        "subtitle": w["subtitle"],
        "profile": w["publication_profile"],
        "project_id": w["project_id"],
        "source_package": source_package_ref,
        "sections": w["sections"],
        "contributors": w["contributors"],
        "figures": w["figures"],
        "tables": w["tables"],
        "citations": w["citations"],
        "appendices": w["appendices"],
    }
    fingerprint = _fp(basis)
    return {
        "schema": DRAFT_CONTRACT,
        "publication_draft_id": "research-publication-draft:" + fingerprint[:32],
        "publication_draft_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "title": w["title"],
        "subtitle": w["subtitle"],
        "publication_profile": w["publication_profile"],
        "project_id": w["project_id"],
        "research_question": w["research_question"],
        "abstract": w["abstract"],
        "executive_summary": w["executive_summary"],
        "keywords": w["keywords"],
        "license": w["license"],
        "source_package": source_package_ref,
        "sections": w["sections"],
        "contributors": w["contributors"],
        "figures": w["figures"],
        "tables": w["tables"],
        "citations": w["citations"],
        "appendices": w["appendices"],
        "section_inventory": sections,
        "asset_inventory": assets,
        "citation_inventory": citations,
        "editorial_audit": editorial,
        "readiness_audit": ready,
        "metadata": w["metadata"],
        "state": "draft",
        "published": False,
        "persisted": False,
        "peer_reviewed": False,
        "truth_adjudicated": False,
        "guardrails": guardrails(),
    }


def publishing_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_studio_input(payload)
    draft = build_publication_draft(payload)
    package = w["source_package"]
    publishing_payload = {
        "title": w["title"],
        "description": w["abstract"] or w["executive_summary"] or "Research publication draft composed in Sustainable Catalyst Library.",
        "profile": "portable-research-package",
        "research_package": package.get("raw") or {},
        "metadata": {
            **w["metadata"],
            "publication_studio_schema": DRAFT_CONTRACT,
            "publication_draft_id": draft["publication_draft_id"],
            "publication_draft_fingerprint_sha256": draft["publication_draft_fingerprint_sha256"],
            "publication_profile": w["publication_profile"],
            "publication_draft": draft,
        },
    }
    basis = {"endpoint": "/api/library/v1/research-package-publishing/preview", "payload": publishing_payload}
    return {
        "schema": "sc-library-research-publication-publishing-handoff-preview/1.0",
        "handoff_id": "research-publication-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "endpoint": "/api/library/v1/research-package-publishing/preview",
        "payload": publishing_payload,
        "preview_only": True,
        "automatic_submission": False,
        "automatic_persistence": False,
        "automatic_external_publication": False,
        "guardrails": guardrails(),
    }


def export_publication_draft(payload: dict[str, Any]) -> dict[str, Any]:
    draft = build_publication_draft(payload)
    handoff = publishing_handoff_preview(payload)
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "publication_draft": draft,
        "publishing_handoff_preview": handoff,
        "automatic_import": False,
        "automatic_publication": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }
    basis = {
        "publication_draft_id": draft["publication_draft_id"],
        "publication_draft_fingerprint_sha256": draft["publication_draft_fingerprint_sha256"],
    }
    body["export_id"] = "research-publication-export:" + _fp(basis)[:32]
    body["export_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-research-publication-draft.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
