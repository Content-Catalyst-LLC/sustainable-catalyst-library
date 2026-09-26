from __future__ import annotations

from collections import Counter
from hashlib import sha256
import json
import re
from typing import Any, Iterable

METHODOLOGY_SCHEMA = "sc-library-methodology-intelligence/1.0"
PROFILE_SCHEMA = "sc-library-methodology-profile/1.0"
COMPARISON_SCHEMA = "sc-library-methodology-comparison/1.0"

# These are descriptive design families. They are not a hierarchy of quality.
DESIGN_RULES: tuple[tuple[str, tuple[str, ...], str], ...] = (
    ("evidence-synthesis", ("systematic review", "meta-analysis", "meta analysis", "scoping review"), "Evidence synthesis"),
    ("randomized-interventional", ("randomized controlled trial", "randomised controlled trial", "randomized trial", "randomised trial", "rct"), "Randomized interventional study"),
    ("quasi-experimental", ("difference-in-differences", "difference in differences", "regression discontinuity", "interrupted time series", "synthetic control", "instrumental variable", "quasi-experimental", "quasi experimental"), "Quasi-experimental study"),
    ("controlled-interventional", ("controlled trial", "nonrandomized trial", "non-randomized trial", "nonrandomised trial", "interventional study"), "Controlled / interventional study"),
    ("observational-cohort", ("prospective cohort", "retrospective cohort", "cohort study", "longitudinal study"), "Cohort / longitudinal observational study"),
    ("case-control", ("case-control", "case control"), "Case-control study"),
    ("cross-sectional", ("cross-sectional", "cross sectional"), "Cross-sectional study"),
    ("qualitative", ("qualitative", "interview study", "focus group", "ethnography", "thematic analysis", "grounded theory"), "Qualitative study"),
    ("mixed-methods", ("mixed methods", "mixed-methods"), "Mixed-methods study"),
    ("computational-modeling", ("computational model", "mathematical model", "simulation study", "monte carlo", "agent-based model", "agent based model", "system dynamics"), "Computational / modeling study"),
    ("econometric", ("econometric", "panel regression", "fixed effects", "random effects", "time series regression"), "Econometric study"),
    ("descriptive", ("case report", "case series", "descriptive study", "case study"), "Descriptive study"),
)

REPORTING_DIMENSIONS = (
    "study_design", "population", "sample_size", "geography", "time_period",
    "interventions_or_exposures", "comparators", "outcomes", "variables",
    "analytical_methods", "uncertainty", "limitations", "funding_conflicts",
    "reproducibility_materials",
)


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


def _clean(value: Any) -> str:
    return " ".join(str(value or "").strip().split())


def _compact_list(value: Any) -> list[str]:
    out: list[str] = []
    seen: set[str] = set()
    for raw in _list(value):
        if isinstance(raw, dict):
            text = _clean(raw.get("label") or raw.get("name") or raw.get("value") or raw.get("method") or raw.get("outcome"))
        else:
            text = _clean(raw)
        if not text or text.casefold() in seen:
            continue
        seen.add(text.casefold())
        out.append(text)
    return out


def _first_with_path(sources: list[tuple[str, dict[str, Any]]], keys: tuple[str, ...]) -> tuple[Any, str | None]:
    for prefix, source in sources:
        for key in keys:
            if key in source and source.get(key) not in (None, "", [], {}):
                return source.get(key), f"{prefix}.{key}" if prefix else key
    return None, None


def _record_sources(record: dict[str, Any]) -> list[tuple[str, dict[str, Any]]]:
    meta = _dict(record.get("metadata"))
    methodology = _dict(meta.get("methodology"))
    methods = _dict(meta.get("methods"))
    study = _dict(meta.get("study_design"))
    research = _dict(meta.get("research_design"))
    analysis = _dict(meta.get("analysis"))
    transparency = _dict(meta.get("transparency"))
    # Prefer the most explicit structured subobjects. Generic metadata is last.
    return [
        ("metadata.methodology", methodology),
        ("metadata.methods", methods),
        ("metadata.study_design", study),
        ("metadata.research_design", research),
        ("metadata.analysis", analysis),
        ("metadata.transparency", transparency),
        ("metadata", meta),
    ]


def _parse_sample_size(value: Any) -> int | None:
    if isinstance(value, bool):
        return None
    if isinstance(value, int):
        return value if value >= 0 else None
    if isinstance(value, float) and value.is_integer() and value >= 0:
        return int(value)
    text = _clean(value)
    if not text:
        return None
    match = re.search(r"(?:^|\b)(?:n\s*=\s*)?([0-9][0-9,]*)\b", text, flags=re.I)
    if not match:
        return None
    try:
        return int(match.group(1).replace(",", ""))
    except ValueError:
        return None


def _classify_design(reported: Any) -> dict[str, Any]:
    values = _compact_list(reported)
    joined = " | ".join(values).casefold()
    for family, tokens, label in DESIGN_RULES:
        if any(token in joined for token in tokens):
            return {
                "family": family,
                "label": label,
                "reported_values": values,
                "classification_state": "explicit-methodology-field-mapped",
                "quality_rank": None,
            }
    return {
        "family": "unclassified",
        "label": values[0] if values else "Unclassified / not explicitly reported",
        "reported_values": values,
        "classification_state": "explicit-value-unmapped" if values else "not-explicitly-reported",
        "quality_rank": None,
    }


def _field(value: Any, path: str | None, *, list_value: bool = False) -> dict[str, Any]:
    normalized: Any
    if list_value:
        normalized = _compact_list(value)
    else:
        normalized = value
    present = bool(normalized not in (None, "", [], {}))
    return {
        "reported": present,
        "value": normalized if present else None,
        "source_path": path if present else None,
        "extraction_state": "explicit-structured-field" if present else "not-reported-in-supported-structured-fields",
    }


def profile_methodology(record: dict[str, Any]) -> dict[str, Any]:
    rid = _clean(record.get("record_id") or record.get("id"))
    sources = _record_sources(record)

    design_raw, design_path = _first_with_path(sources, ("design", "design_type", "study_design", "study_type", "methodology_type", "publication_type"))
    population_raw, population_path = _first_with_path(sources, ("population", "study_population", "participants", "cohort", "sample_population"))
    sample_raw, sample_path = _first_with_path(sources, ("sample_size", "participant_count", "participants_count", "n", "sample_n"))
    geography_raw, geography_path = _first_with_path(sources, ("geography", "location", "country", "countries", "study_sites", "sites"))
    time_raw, time_path = _first_with_path(sources, ("time_period", "study_period", "observation_period", "follow_up", "followup", "years"))
    intervention_raw, intervention_path = _first_with_path(sources, ("interventions", "intervention", "exposures", "exposure", "treatments", "treatment"))
    comparator_raw, comparator_path = _first_with_path(sources, ("comparators", "comparator", "control_group", "controls", "reference_group"))
    outcomes_raw, outcomes_path = _first_with_path(sources, ("outcomes", "outcome", "endpoints", "endpoint", "dependent_variables"))
    variables_raw, variables_path = _first_with_path(sources, ("variables", "covariates", "predictors", "independent_variables", "features"))
    analytical_raw, analytical_path = _first_with_path(sources, ("statistical_methods", "analytical_methods", "analysis_methods", "methods", "models", "estimators"))
    uncertainty_raw, uncertainty_path = _first_with_path(sources, ("uncertainty", "confidence_intervals", "credible_intervals", "standard_errors", "p_values", "sensitivity_analysis", "sensitivity_analyses"))
    limitations_raw, limitations_path = _first_with_path(sources, ("limitations", "study_limitations", "caveats"))
    funding_raw, funding_path = _first_with_path(sources, ("funding", "funders", "funding_source", "conflicts", "conflict_of_interest", "competing_interests"))
    prereg_raw, prereg_path = _first_with_path(sources, ("preregistration", "pre_registration", "registration", "trial_registration", "protocol_registration"))
    data_raw, data_path = _first_with_path(sources, ("data_availability", "dataset", "datasets", "data_repository", "data_url"))
    code_raw, code_path = _first_with_path(sources, ("code_availability", "code_repository", "code_url", "software", "software_environment"))
    materials_raw, materials_path = _first_with_path(sources, ("materials", "supplementary_materials", "replication_materials", "reproduction_package"))
    replication_raw, replication_path = _first_with_path(sources, ("replication", "replication_status", "replicated_by", "replication_materials_available"))
    randomization_raw, randomization_path = _first_with_path(sources, ("randomization", "randomisation", "allocation"))
    masking_raw, masking_path = _first_with_path(sources, ("masking", "blinding", "blinded"))
    ethics_raw, ethics_path = _first_with_path(sources, ("ethics", "ethics_approval", "irb", "institutional_review_board"))

    design = _classify_design(design_raw)
    design["source_path"] = design_path

    sample_size = _parse_sample_size(sample_raw)
    fields = {
        "study_design": _field(design_raw, design_path, list_value=True),
        "population": _field(population_raw, population_path, list_value=True),
        "sample_size": _field(sample_size if sample_size is not None else sample_raw, sample_path),
        "geography": _field(geography_raw, geography_path, list_value=True),
        "time_period": _field(time_raw, time_path, list_value=True),
        "interventions_or_exposures": _field(intervention_raw, intervention_path, list_value=True),
        "comparators": _field(comparator_raw, comparator_path, list_value=True),
        "outcomes": _field(outcomes_raw, outcomes_path, list_value=True),
        "variables": _field(variables_raw, variables_path, list_value=True),
        "analytical_methods": _field(analytical_raw, analytical_path, list_value=True),
        "uncertainty": _field(uncertainty_raw, uncertainty_path, list_value=True),
        "limitations": _field(limitations_raw, limitations_path, list_value=True),
        "funding_conflicts": _field(funding_raw, funding_path, list_value=True),
        "preregistration": _field(prereg_raw, prereg_path, list_value=True),
        "data_availability": _field(data_raw, data_path, list_value=True),
        "code_availability": _field(code_raw, code_path, list_value=True),
        "materials": _field(materials_raw, materials_path, list_value=True),
        "replication": _field(replication_raw, replication_path, list_value=True),
        "randomization": _field(randomization_raw, randomization_path, list_value=True),
        "masking": _field(masking_raw, masking_path, list_value=True),
        "ethics": _field(ethics_raw, ethics_path, list_value=True),
    }

    coverage_fields = {
        "study_design": fields["study_design"]["reported"],
        "population": fields["population"]["reported"],
        "sample_size": fields["sample_size"]["reported"],
        "geography": fields["geography"]["reported"],
        "time_period": fields["time_period"]["reported"],
        "interventions_or_exposures": fields["interventions_or_exposures"]["reported"],
        "comparators": fields["comparators"]["reported"],
        "outcomes": fields["outcomes"]["reported"],
        "variables": fields["variables"]["reported"],
        "analytical_methods": fields["analytical_methods"]["reported"],
        "uncertainty": fields["uncertainty"]["reported"],
        "limitations": fields["limitations"]["reported"],
        "funding_conflicts": fields["funding_conflicts"]["reported"],
        "reproducibility_materials": any(fields[k]["reported"] for k in ("preregistration", "data_availability", "code_availability", "materials", "replication")),
    }
    reported_count = sum(1 for key in REPORTING_DIMENSIONS if coverage_fields.get(key))
    coverage = round(reported_count / len(REPORTING_DIMENSIONS), 6)

    design_signals: list[str] = []
    if fields["randomization"]["reported"]:
        design_signals.append("randomization-reported")
    if fields["masking"]["reported"]:
        design_signals.append("masking-reported")
    if fields["comparators"]["reported"]:
        design_signals.append("comparator-reported")
    if fields["uncertainty"]["reported"]:
        design_signals.append("uncertainty-reporting-present")
    if fields["limitations"]["reported"]:
        design_signals.append("limitations-explicitly-reported")
    if fields["preregistration"]["reported"]:
        design_signals.append("registration-or-preregistration-reported")
    if fields["data_availability"]["reported"]:
        design_signals.append("data-availability-reported")
    if fields["code_availability"]["reported"]:
        design_signals.append("code-or-software-availability-reported")
    if fields["funding_conflicts"]["reported"]:
        design_signals.append("funding-or-conflict-disclosure-reported")

    return {
        "schema": PROFILE_SCHEMA,
        "record_id": rid,
        "title": _clean(record.get("title") or rid),
        "study_design": design,
        "study_context": {
            "population": fields["population"],
            "sample_size": fields["sample_size"],
            "geography": fields["geography"],
            "time_period": fields["time_period"],
        },
        "research_structure": {
            "interventions_or_exposures": fields["interventions_or_exposures"],
            "comparators": fields["comparators"],
            "outcomes": fields["outcomes"],
            "variables": fields["variables"],
        },
        "methods": {
            "analytical_methods": fields["analytical_methods"],
            "randomization": fields["randomization"],
            "masking": fields["masking"],
            "uncertainty": fields["uncertainty"],
        },
        "transparency": {
            "limitations": fields["limitations"],
            "funding_conflicts": fields["funding_conflicts"],
            "preregistration": fields["preregistration"],
            "data_availability": fields["data_availability"],
            "code_availability": fields["code_availability"],
            "materials": fields["materials"],
            "replication": fields["replication"],
            "ethics": fields["ethics"],
        },
        "method_reporting_coverage": {
            "reported_dimension_count": reported_count,
            "dimension_count": len(REPORTING_DIMENSIONS),
            "coverage_ratio": coverage,
            "reported_dimensions": [k for k in REPORTING_DIMENSIONS if coverage_fields.get(k)],
            "missing_dimensions": [k for k in REPORTING_DIMENSIONS if not coverage_fields.get(k)],
            "coverage_is_quality_score": False,
        },
        "appraisal_readiness": {
            "state": "structured-review-ready" if reported_count >= 7 else ("partial" if reported_count else "insufficient-explicit-methodology"),
            "reported_signals": design_signals,
            "formal_quality_grade": None,
            "formal_risk_of_bias_judgment": None,
            "causal_validity_judgment": None,
            "human_review_required": True,
            "domains": {
                "design_integrity": "requires-human-appraisal",
                "selection_and_sampling": "requires-human-appraisal",
                "measurement_validity": "requires-human-appraisal",
                "confounding_and_bias": "requires-human-appraisal",
                "statistical_precision": "requires-human-appraisal",
                "external_validity": "requires-human-appraisal",
                "reproducibility": "requires-human-appraisal",
            },
        },
        "provenance": {
            "record_id": rid,
            "source_content_hash": _clean(record.get("content_hash")) or None,
            "source_key": _clean(record.get("source_key")) or None,
            "explicit_structured_fields_only": True,
        },
        "guardrails": {
            "method_reporting_coverage_is_quality_score": False,
            "study_design_family_is_evidence_hierarchy": False,
            "methodology_profile_determines_truth": False,
            "methodology_profile_proves_causality": False,
            "formal_risk_of_bias_generated": False,
            "missing_field_means_method_not_used": False,
            "human_review_required": True,
            "platform_core_governance_changed": False,
        },
    }


def compare_methodology_profiles(profiles: Iterable[dict[str, Any]]) -> dict[str, Any]:
    items = [dict(p) for p in profiles if isinstance(p, dict) and p.get("record_id")]
    rows: list[dict[str, Any]] = []
    for p in items:
        context = _dict(p.get("study_context"))
        methods = _dict(p.get("methods"))
        transparency = _dict(p.get("transparency"))
        def value(section: dict[str, Any], key: str) -> Any:
            field = _dict(section.get(key))
            return field.get("value") if field.get("reported") else None
        rows.append({
            "record_id": p.get("record_id"),
            "title": p.get("title"),
            "design_family": _dict(p.get("study_design")).get("family"),
            "population": value(context, "population"),
            "sample_size": value(context, "sample_size"),
            "geography": value(context, "geography"),
            "time_period": value(context, "time_period"),
            "analytical_methods": value(methods, "analytical_methods"),
            "uncertainty": value(methods, "uncertainty"),
            "limitations": value(transparency, "limitations"),
            "coverage_ratio": _dict(p.get("method_reporting_coverage")).get("coverage_ratio"),
        })
    design_counts = Counter(str(r.get("design_family") or "unclassified") for r in rows)
    return {
        "schema": COMPARISON_SCHEMA,
        "record_count": len(rows),
        "rows": rows,
        "design_family_counts": dict(sorted(design_counts.items())),
        "interpretation": {
            "methodological_difference_explains_outcome_difference_automatically": False,
            "higher_reporting_coverage_means_higher_quality": False,
            "design_family_is_quality_ranking": False,
            "comparison_is_descriptive": True,
        },
    }


def build_methodology_intelligence(records: Iterable[dict[str, Any]]) -> dict[str, Any]:
    profiles = [profile_methodology(dict(r)) for r in records if isinstance(r, dict) and _clean(r.get("record_id") or r.get("id"))]
    profiles.sort(key=lambda p: str(p.get("record_id") or ""))
    design_counts = Counter(str(_dict(p.get("study_design")).get("family") or "unclassified") for p in profiles)
    with_methods = [p for p in profiles if int(_dict(p.get("method_reporting_coverage")).get("reported_dimension_count") or 0) > 0]
    overlay_nodes: list[dict[str, Any]] = []
    overlay_edges: list[dict[str, Any]] = []
    for profile in with_methods:
        rid = str(profile.get("record_id") or "")
        mid = "methodology:" + sha256(rid.encode("utf-8")).hexdigest()[:20]
        coverage = _dict(profile.get("method_reporting_coverage"))
        design = _dict(profile.get("study_design"))
        overlay_nodes.append({
            "id": mid,
            "kind": "methodology-profile",
            "label": f"Methodology · {profile.get('title') or rid}",
            "record_id": rid,
            "design_family": design.get("family"),
            "design_label": design.get("label"),
            "reported_dimension_count": coverage.get("reported_dimension_count"),
            "coverage_ratio": coverage.get("coverage_ratio"),
            "quality_score": None,
            "formal_risk_of_bias": None,
            "truth_determined": False,
            "provenance": profile.get("provenance") or {},
        })
        overlay_edges.append({
            "source": rid,
            "target": mid,
            "relationship_basis": "describes-methodology",
            "directed": True,
            "weight": 1.0,
            "analytical": False,
            "truth_assertion": False,
            "causal_assertion": False,
            "default_evidence_path": False,
            "provenance": profile.get("provenance") or {},
        })
    avg_coverage = round(sum(float(_dict(p.get("method_reporting_coverage")).get("coverage_ratio") or 0.0) for p in profiles) / len(profiles), 6) if profiles else 0.0
    output = {
        "schema": METHODOLOGY_SCHEMA,
        "profiles": profiles,
        "comparison": compare_methodology_profiles(profiles),
        "metrics": {
            "record_count": len(profiles),
            "records_with_explicit_methodology": len(with_methods),
            "records_without_explicit_methodology": len(profiles) - len(with_methods),
            "design_family_counts": dict(sorted(design_counts.items())),
            "average_method_reporting_coverage": avg_coverage,
        },
        "graph_overlay": {"nodes": overlay_nodes, "edges": overlay_edges},
        "guardrails": {
            "automatic_quality_score": False,
            "automatic_risk_of_bias_judgment": False,
            "automatic_causal_validity_judgment": False,
            "higher_reporting_coverage_means_higher_quality": False,
            "design_family_is_evidence_hierarchy": False,
            "missing_metadata_means_method_not_used": False,
            "methodology_profile_determines_claim_truth": False,
            "human_review_required": True,
            "platform_core_governance_changed": False,
        },
    }
    output["fingerprint_sha256"] = sha256(json.dumps({"profiles": profiles}, sort_keys=True, default=str, separators=(",", ":")).encode("utf-8")).hexdigest()
    return output


def methodology_request(payload: dict[str, Any]) -> dict[str, Any]:
    records = payload.get("records")
    if records is None and isinstance(payload.get("record"), dict):
        records = [payload.get("record")]
    if not isinstance(records, list):
        raise ValueError("records must be a list or record must be an object")
    result = build_methodology_intelligence(records)
    requested = {str(x) for x in _list(payload.get("compare_record_ids")) if _clean(x)}
    if requested:
        selected = [p for p in result.get("profiles", []) if str(p.get("record_id")) in requested]
        result["comparison"] = compare_methodology_profiles(selected)
    return result
