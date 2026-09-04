from __future__ import annotations

from collections import Counter
from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Any


@dataclass(frozen=True)
class EvidenceFrameworkDescriptor:
    key: str = "biomedical-evidence-grading"
    name: str = "Biomedical Evidence Grading & Study Design Intelligence"
    version: str = "1.0"

    def to_dict(self) -> dict[str, Any]:
        return {
            "key": self.key,
            "name": self.name,
            "version": self.version,
            "capabilities": [
                "publication-type-design-classification",
                "clinical-trial-design-profiling",
                "integrity-signal-preservation",
                "evidence-body-mapping",
                "certainty-domain-readiness",
                "human-review-handoff",
            ],
            "governance": {
                "research_only": True,
                "clinical_decision_support": False,
                "patient_specific_diagnosis": False,
                "patient_specific_treatment": False,
                "formal_grade_generated": False,
                "formal_risk_of_bias_judgment_generated": False,
                "automated_metadata_score_presented_as_certainty": False,
                "human_review_required": True,
            },
        }


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _clean(value: Any) -> str:
    return "" if value is None else str(value).strip()


def _dict(value: Any) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return value if isinstance(value, list) else []


def _normalized_labels(values: list[Any]) -> list[str]:
    return [str(x).strip() for x in values if str(x).strip()]


class EvidenceGradingEngine:
    """Metadata-derived evidence intelligence, not a formal certainty assessment.

    The engine deliberately separates study-design recognition from certainty-of-
    evidence judgments. It can organize signals needed for a later structured
    review, but it never emits GRADE high/moderate/low/very-low categories and
    never reproduces proprietary/non-permissive risk-of-bias questionnaires.
    """

    descriptor = EvidenceFrameworkDescriptor()

    def __init__(self, biomedical_registry: Any, clinical_trials: Any) -> None:
        self.biomedical_registry = biomedical_registry
        self.clinical_trials = clinical_trials

    def manifest(self) -> dict[str, Any]:
        return {
            "schema": "sc-biomedical-evidence-grading/1.0",
            "framework": self.descriptor.to_dict(),
            "study_design_families": [
                "evidence-synthesis",
                "randomized-interventional",
                "controlled-interventional",
                "interventional",
                "observational",
                "descriptive",
                "guideline-or-consensus",
                "narrative-secondary-research",
                "prepublication",
                "unclassified",
            ],
            "certainty_domains": [
                "risk_of_bias",
                "inconsistency",
                "indirectness",
                "imprecision",
                "publication_bias",
            ],
            "certainty_domain_policy": {
                "single_record": "Most certainty domains require structured appraisal beyond bibliographic metadata.",
                "body_of_evidence": "Evidence-body maps organize review signals but do not generate formal certainty ratings.",
            },
            "method_notes": {
                "publication_types": "NLM/PubMed publication-type metadata is used as a design signal when available.",
                "trial_design": "ClinicalTrials.gov registration fields are treated as registry metadata, not peer-reviewed validation of study conduct.",
                "risk_of_bias": "Randomization, masking, comparator, results and integrity fields are review signals only; they are not a formal RoB judgment.",
            },
            "retrieved_at": _now(),
        }

    @staticmethod
    def classify_publication_types(publication_types: list[Any]) -> dict[str, Any]:
        raw = _normalized_labels(publication_types)
        labels = {x.casefold() for x in raw}
        joined = " | ".join(sorted(labels))

        integrity: list[str] = []
        if "retracted publication" in joined or "retraction of publication" in joined:
            integrity.append("retraction-related-publication-type")
        if "corrected and republished article" in joined or "published erratum" in joined:
            integrity.append("correction-related-publication-type")
        if "preprint" in joined:
            integrity.append("prepublication-record")

        if "meta-analysis" in joined or "systematic review" in joined:
            family, label = "evidence-synthesis", "Systematic review / meta-analysis"
        elif "randomized controlled trial" in joined:
            family, label = "randomized-interventional", "Randomized controlled trial"
        elif "controlled clinical trial" in joined:
            family, label = "controlled-interventional", "Controlled clinical trial"
        elif "clinical trial" in joined:
            family, label = "interventional", "Clinical trial"
        elif any(x in joined for x in ("observational study", "cohort studies", "case-control studies", "cross-sectional studies")):
            family, label = "observational", "Observational study"
        elif any(x in joined for x in ("case reports", "case report", "case series")):
            family, label = "descriptive", "Case report / descriptive evidence"
        elif any(x in joined for x in ("practice guideline", "guideline", "consensus development conference")):
            family, label = "guideline-or-consensus", "Guideline / consensus"
        elif "review" in joined:
            family, label = "narrative-secondary-research", "Review"
        elif "preprint" in joined:
            family, label = "prepublication", "Preprint"
        else:
            family, label = "unclassified", "Unclassified publication"

        return {
            "family": family,
            "label": label,
            "publication_types": raw,
            "classification_state": "metadata-derived",
            "integrity_signals": integrity,
            "human_review_required": True,
        }

    @staticmethod
    def _empty_domains() -> dict[str, Any]:
        return {
            "risk_of_bias": {"status": "not-formally-assessed", "signals": []},
            "inconsistency": {"status": "not-assessable-from-single-record", "signals": []},
            "indirectness": {"status": "requires-explicit-review-question", "signals": []},
            "imprecision": {"status": "requires-effect-estimate-and-uncertainty-review", "signals": []},
            "publication_bias": {"status": "requires-body-of-evidence-review", "signals": []},
        }

    def profile_literature_record(self, record: dict[str, Any]) -> dict[str, Any]:
        publication_types = _list(record.get("publication_types"))
        design = self.classify_publication_types(publication_types)
        domains = self._empty_domains()
        integrity = list(design["integrity_signals"])
        if integrity:
            domains["risk_of_bias"]["signals"].extend(integrity)
        return {
            "schema": "sc-evidence-profile/1.0",
            "record_identifier": record.get("identifier"),
            "source_key": record.get("source_key"),
            "record_type": record.get("record_type"),
            "study_design": design,
            "certainty": {
                "formal_grade": None,
                "formal_grade_generated": False,
                "assessment_state": "not-formally-assessed",
                "domains": domains,
            },
            "integrity": {
                "signals": integrity,
                "requires_review": bool(integrity),
            },
            "limitations": [
                "Publication type is a metadata-derived design signal, not a complete appraisal of methods or conduct.",
                "Abstract/full text, outcome-specific effect estimates, risk-of-bias judgments, directness and publication-bias assessment are not inferred from citation metadata alone.",
            ],
            "human_review_required": True,
        }

    def profile_trial_record(self, trial: dict[str, Any]) -> dict[str, Any]:
        design = _dict(trial.get("study_design"))
        study_type = _clean(design.get("study_type")).upper()
        allocation = _clean(design.get("allocation")).upper()
        masking = _clean(design.get("masking")).upper()
        phases = _normalized_labels(_list(design.get("phases")))

        if study_type == "INTERVENTIONAL" and allocation == "RANDOMIZED":
            family, label = "randomized-interventional", "Registered randomized interventional study"
        elif study_type == "INTERVENTIONAL":
            family, label = "interventional", "Registered interventional study"
        elif study_type == "OBSERVATIONAL":
            family, label = "observational", "Registered observational study"
        elif study_type == "EXPANDED_ACCESS":
            family, label = "descriptive", "Expanded access record"
        else:
            family, label = "unclassified", "Registered study"

        domains = self._empty_domains()
        rb_signals: list[str] = []
        if allocation == "RANDOMIZED":
            rb_signals.append("randomization-reported-in-registry")
        elif study_type == "INTERVENTIONAL":
            rb_signals.append("randomization-not-reported-in-normalized-registry-design")
        if masking:
            rb_signals.append(f"masking-reported:{masking.lower()}")
        if len(_list(trial.get("arm_groups"))) >= 2:
            rb_signals.append("multiple-arm-groups-reported")
        domains["risk_of_bias"]["signals"] = rb_signals

        results_state = _dict(trial.get("results_state"))
        results_summary = _dict(trial.get("results_summary"))
        outcome_rows = _list(results_summary.get("outcome_measures"))
        precision_signals: list[str] = []
        for outcome in outcome_rows:
            for analysis in _list(_dict(outcome).get("analyses")):
                row = _dict(analysis)
                if row.get("ci_lower_limit") not in (None, "") or row.get("ci_upper_limit") not in (None, ""):
                    precision_signals.append("confidence-interval-reported")
                    break
        if results_state.get("has_results") and not precision_signals:
            precision_signals.append("posted-results-without-normalized-confidence-interval-signal")
        domains["imprecision"]["signals"] = sorted(set(precision_signals))

        pubs = _dict(trial.get("publications"))
        integrity_signals: list[str] = []
        if int(pubs.get("retraction_signal_count") or 0) > 0:
            integrity_signals.append("registry-linked-retraction-signal")
        if results_state.get("has_results"):
            integrity_signals.append("registry-results-posted")
        if int(pubs.get("results_reference_count") or 0) > 0:
            integrity_signals.append("linked-results-publication")
        if pubs.get("publication_link_state") == "no-linked-publication":
            integrity_signals.append("no-linked-publication-in-registry")

        enrollment = design.get("enrollment")
        return {
            "schema": "sc-evidence-profile/1.0",
            "record_identifier": trial.get("nct_id"),
            "source_key": trial.get("source_key"),
            "record_type": trial.get("record_type"),
            "study_design": {
                "family": family,
                "label": label,
                "study_type": study_type or None,
                "allocation": allocation or None,
                "masking": masking or None,
                "phases": phases,
                "enrollment": enrollment,
                "classification_state": "registry-metadata-derived",
                "conduct_verified": False,
                "human_review_required": True,
            },
            "certainty": {
                "formal_grade": None,
                "formal_grade_generated": False,
                "assessment_state": "not-formally-assessed",
                "domains": domains,
            },
            "integrity": {
                "signals": integrity_signals,
                "requires_review": "registry-linked-retraction-signal" in integrity_signals,
            },
            "results_state": results_state,
            "limitations": [
                "ClinicalTrials.gov registration fields describe reported design; they do not verify study conduct or eliminate bias.",
                "Registry results are not treated as equivalent to peer-reviewed publication.",
                "No linked publication in the registry does not prove that no publication exists.",
            ],
            "human_review_required": True,
        }

    def search_body(self, query: str, *, literature_limit: int = 8, trial_limit: int = 8) -> dict[str, Any]:
        query = query.strip()
        if not query:
            raise ValueError("query is required")
        literature_limit = max(1, min(int(literature_limit), 20))
        trial_limit = max(1, min(int(trial_limit), 20))

        literature_rows: list[dict[str, Any]] = []
        trial_rows: list[dict[str, Any]] = []
        errors: list[dict[str, str]] = []

        try:
            pubmed = self.biomedical_registry.get("pubmed").search(query, limit=literature_limit)
            for record in _list(_dict(pubmed).get("results")):
                row = dict(_dict(record))
                row["evidence_profile"] = self.profile_literature_record(row)
                literature_rows.append(row)
        except Exception as exc:  # fail-open by source family
            errors.append({"source": "pubmed", "error": exc.__class__.__name__})

        try:
            trials = self.clinical_trials.search(query=query, limit=trial_limit)
            for record in _list(_dict(trials).get("results")):
                row = dict(_dict(record))
                row["evidence_profile"] = self.profile_trial_record(row)
                trial_rows.append(row)
        except Exception as exc:  # fail-open by source family
            errors.append({"source": "clinicaltrials", "error": exc.__class__.__name__})

        families = Counter()
        integrity_flags = 0
        for row in literature_rows + trial_rows:
            profile = _dict(row.get("evidence_profile"))
            family = _dict(profile.get("study_design")).get("family")
            if family:
                families[str(family)] += 1
            if _dict(profile.get("integrity")).get("requires_review"):
                integrity_flags += 1

        summary = {
            "record_count": len(literature_rows) + len(trial_rows),
            "literature_count": len(literature_rows),
            "trial_count": len(trial_rows),
            "design_distribution": dict(sorted(families.items())),
            "integrity_review_flag_count": integrity_flags,
            "formal_certainty_assessment_generated": False,
            "evidence_map_state": "descriptive-metadata-derived",
            "review_requirements": [
                "Outcome-specific risk-of-bias assessment",
                "Cross-study consistency assessment",
                "Directness against an explicit review question / PICO",
                "Precision assessment using effect estimates and uncertainty intervals",
                "Missing-evidence / publication-bias assessment",
            ],
        }
        if not literature_rows:
            summary.setdefault("evidence_gaps", []).append("No PubMed literature records were available from this retrieval.")
        if not trial_rows:
            summary.setdefault("evidence_gaps", []).append("No ClinicalTrials.gov records were available from this retrieval.")

        return {
            "schema": "sc-biomedical-evidence-body/1.0",
            "query": query,
            "summary": summary,
            "literature": literature_rows,
            "trials": trial_rows,
            "errors": errors,
            "governance": self.descriptor.to_dict()["governance"],
            "retrieved_at": _now(),
        }

    def trial_profile(self, nct_id: str) -> dict[str, Any]:
        trial = self.clinical_trials.get_study(nct_id)
        return {
            "schema": "sc-biomedical-evidence-trial-profile/1.0",
            "trial": trial,
            "evidence_profile": self.profile_trial_record(trial),
            "retrieved_at": _now(),
        }
