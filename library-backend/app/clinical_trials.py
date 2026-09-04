from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
import json
import re
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


class ClinicalTrialIntelligenceError(RuntimeError):
    """Bounded ClinicalTrials.gov upstream failure."""


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _clean(value: Any) -> str:
    if value is None:
        return ""
    return str(value).strip()


def _list(value: Any) -> list[Any]:
    return value if isinstance(value, list) else []


def _dict(value: Any) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


def _norm_text_set(values: list[str]) -> set[str]:
    return {re.sub(r"\s+", " ", str(v).strip()).casefold() for v in values if str(v).strip()}


def _safe_nct_id(value: str) -> str:
    nct = value.strip().upper()
    if not re.fullmatch(r"NCT\d{8}", nct):
        raise ValueError("nct_id must match NCT########")
    return nct


def _date_value(module: dict[str, Any], key: str) -> str | None:
    value = module.get(key)
    if isinstance(value, dict):
        value = value.get("date")
    text = _clean(value)
    return text or None


@dataclass(frozen=True)
class ClinicalTrialSourceDescriptor:
    key: str = "clinicaltrials-intelligence"
    name: str = "ClinicalTrials.gov Clinical Study & Trial Intelligence"
    steward: str = "U.S. National Library of Medicine"
    base_url: str = "https://clinicaltrials.gov/api/v2"

    def to_dict(self) -> dict[str, Any]:
        return {
            "key": self.key,
            "name": self.name,
            "steward": self.steward,
            "base_url": self.base_url,
            "capabilities": [
                "structured-search",
                "study-detail",
                "study-comparison",
                "eligibility",
                "study-design",
                "interventions-and-arms",
                "outcomes",
                "results-state",
                "linked-publications",
                "retraction-signals",
                "locations",
                "provenance",
            ],
            "governance": {
                "research_only": True,
                "clinical_decision_support": False,
                "patient_specific_diagnosis": False,
                "patient_specific_treatment": False,
                "participant_level_data_exposed": False,
                "publication_absence_claimed": False,
            },
        }


class ClinicalTrialIntelligence:
    descriptor = ClinicalTrialSourceDescriptor()

    def __init__(self, timeout_seconds: int = 8, user_agent: str = "SustainableCatalystLibrary/1.7") -> None:
        self.timeout_seconds = max(2, min(int(timeout_seconds), 30))
        self.user_agent = user_agent

    def _get_json(self, path: str, params: dict[str, Any] | None = None) -> Any:
        query = urlencode({k: v for k, v in (params or {}).items() if v not in (None, "")}, doseq=True)
        target = self.descriptor.base_url + path + (("?" + query) if query else "")
        req = Request(target, headers={"Accept": "application/json", "User-Agent": self.user_agent})
        try:
            with urlopen(req, timeout=self.timeout_seconds) as response:
                return json.loads(response.read().decode("utf-8"))
        except HTTPError as exc:
            if exc.code == 404:
                raise KeyError("clinical trial not found") from exc
            raise ClinicalTrialIntelligenceError(f"ClinicalTrials.gov request failed: HTTP {exc.code}") from exc
        except (URLError, TimeoutError, json.JSONDecodeError) as exc:
            raise ClinicalTrialIntelligenceError(f"ClinicalTrials.gov request failed: {exc.__class__.__name__}") from exc

    def manifest(self) -> dict[str, Any]:
        return {
            "schema": "sc-clinical-trial-intelligence-sources/1.0",
            "source": self.descriptor.to_dict(),
            "comparison_limit": 8,
            "search_limit": 50,
            "governance": {
                "registry_records_are_not_equivalent_to_peer_reviewed_publications": True,
                "absence_of_linked_publication_does_not_prove_unpublished": True,
                "results_posted_state_preserved": True,
                "retraction_signals_preserved": True,
                "human_review_required": True,
            },
        }

    @staticmethod
    def _advanced_filter(phase: str = "", study_type: str = "") -> str:
        clauses: list[str] = []
        phase = phase.strip().upper().replace(" ", "")
        if phase:
            valid_phase = {
                "EARLY_PHASE1", "PHASE1", "PHASE2", "PHASE3", "PHASE4", "NA",
            }
            phase = phase.replace("EARLYPHASE1", "EARLY_PHASE1")
            if phase not in valid_phase:
                raise ValueError("unsupported trial phase")
            clauses.append(f"AREA[Phase]{phase}")
        study_type = study_type.strip().upper().replace("-", "_").replace(" ", "_")
        if study_type:
            valid_types = {"INTERVENTIONAL", "OBSERVATIONAL", "EXPANDED_ACCESS"}
            if study_type not in valid_types:
                raise ValueError("unsupported study_type")
            clauses.append(f"AREA[StudyType]{study_type}")
        return " AND ".join(clauses)

    def search(
        self,
        *,
        query: str = "",
        condition: str = "",
        intervention: str = "",
        sponsor: str = "",
        location: str = "",
        status: str = "",
        phase: str = "",
        study_type: str = "",
        limit: int = 10,
        cursor: str = "",
    ) -> dict[str, Any]:
        if not any(x.strip() for x in (query, condition, intervention, sponsor, location, status, phase, study_type)):
            raise ValueError("at least one clinical-trial search criterion is required")
        limit = max(1, min(int(limit), 50))
        params: dict[str, Any] = {"pageSize": limit, "format": "json", "countTotal": "true"}
        if query.strip(): params["query.term"] = query.strip()
        if condition.strip(): params["query.cond"] = condition.strip()
        if intervention.strip(): params["query.intr"] = intervention.strip()
        if sponsor.strip(): params["query.spons"] = sponsor.strip()
        if location.strip(): params["query.locn"] = location.strip()
        if status.strip():
            statuses = [re.sub(r"[^A-Z_]", "", x.strip().upper().replace(" ", "_")) for x in status.split(",")]
            statuses = [x for x in statuses if x]
            if statuses: params["filter.overallStatus"] = ",".join(statuses)
        advanced = self._advanced_filter(phase, study_type)
        if advanced: params["filter.advanced"] = advanced
        if cursor.strip(): params["pageToken"] = cursor.strip()
        payload = self._get_json("/studies", params)
        rows = [self._normalize_study(study, include_results_summary=False) for study in _list(_dict(payload).get("studies"))]
        return {
            "schema": "sc-clinical-trial-search/1.0",
            "source": self.descriptor.to_dict(),
            "criteria": {
                "query": query or None,
                "condition": condition or None,
                "intervention": intervention or None,
                "sponsor": sponsor or None,
                "location": location or None,
                "status": status or None,
                "phase": phase or None,
                "study_type": study_type or None,
            },
            "total": _dict(payload).get("totalCount"),
            "limit": limit,
            "next_cursor": _clean(_dict(payload).get("nextPageToken")) or None,
            "results": rows,
            "retrieved_at": _now(),
        }

    def get_study(self, nct_id: str) -> dict[str, Any]:
        nct = _safe_nct_id(nct_id)
        payload = self._get_json(f"/studies/{nct}", {"format": "json"})
        return self._normalize_study(_dict(payload), include_results_summary=True)

    def compare(self, nct_ids: list[str]) -> dict[str, Any]:
        ids: list[str] = []
        for raw in nct_ids:
            nct = _safe_nct_id(raw)
            if nct not in ids:
                ids.append(nct)
        if len(ids) < 2:
            raise ValueError("at least two unique NCT IDs are required")
        if len(ids) > 8:
            raise ValueError("no more than eight trials may be compared at once")
        trials = [self.get_study(nct) for nct in ids]
        condition_sets = [_norm_text_set([str(x) for x in t.get("conditions", [])]) for t in trials]
        intervention_sets = [_norm_text_set([str(x.get("name") or "") for x in t.get("interventions", []) if isinstance(x, dict)]) for t in trials]
        common_conditions = sorted(set.intersection(*condition_sets)) if condition_sets and all(condition_sets) else []
        common_interventions = sorted(set.intersection(*intervention_sets)) if intervention_sets and all(intervention_sets) else []
        matrix = []
        for t in trials:
            result_state = _dict(t.get("results_state"))
            eligibility = _dict(t.get("eligibility"))
            pubs = _dict(t.get("publications"))
            matrix.append({
                "nct_id": t.get("nct_id"),
                "title": t.get("title"),
                "overall_status": t.get("overall_status"),
                "study_type": _dict(t.get("study_design")).get("study_type"),
                "phases": _dict(t.get("study_design")).get("phases", []),
                "enrollment": _dict(t.get("study_design")).get("enrollment"),
                "lead_sponsor": _dict(t.get("sponsors")).get("lead", {}).get("name") if isinstance(_dict(t.get("sponsors")).get("lead"), dict) else None,
                "sex": eligibility.get("sex"),
                "minimum_age": eligibility.get("minimum_age"),
                "maximum_age": eligibility.get("maximum_age"),
                "has_results": result_state.get("has_results"),
                "results_first_posted": result_state.get("results_first_posted"),
                "linked_results_publications": pubs.get("results_reference_count", 0),
                "retraction_signal_count": pubs.get("retraction_signal_count", 0),
                "primary_outcome_count": len(t.get("primary_outcomes", [])),
            })
        return {
            "schema": "sc-clinical-trial-comparison/1.0",
            "source": self.descriptor.to_dict(),
            "nct_ids": ids,
            "common": {"conditions": common_conditions, "interventions": common_interventions},
            "matrix": matrix,
            "trials": trials,
            "governance": {
                "descriptive_comparison_only": True,
                "comparative_effectiveness_conclusion_generated": False,
                "registry_record_differences_are_not_treatment_recommendations": True,
                "human_review_required": True,
            },
            "retrieved_at": _now(),
        }

    def _normalize_study(self, study: dict[str, Any], *, include_results_summary: bool) -> dict[str, Any]:
        ps = _dict(study.get("protocolSection"))
        ident = _dict(ps.get("identificationModule"))
        status = _dict(ps.get("statusModule"))
        sponsors = _dict(ps.get("sponsorCollaboratorsModule"))
        desc = _dict(ps.get("descriptionModule"))
        conditions = _dict(ps.get("conditionsModule"))
        design = _dict(ps.get("designModule"))
        design_info = _dict(design.get("designInfo"))
        masking = _dict(design_info.get("maskingInfo"))
        arms = _dict(ps.get("armsInterventionsModule"))
        outcomes = _dict(ps.get("outcomesModule"))
        eligibility = _dict(ps.get("eligibilityModule"))
        contacts = _dict(ps.get("contactsLocationsModule"))
        refs = _dict(ps.get("referencesModule"))
        derived = _dict(study.get("derivedSection"))
        condition_browse = _dict(derived.get("conditionBrowseModule"))
        intervention_browse = _dict(derived.get("interventionBrowseModule"))
        results = _dict(study.get("resultsSection"))
        nct = _clean(ident.get("nctId"))

        interventions = []
        for item in _list(arms.get("interventions")):
            row = _dict(item)
            interventions.append({
                "type": row.get("type"), "name": row.get("name"), "description": row.get("description"),
                "arm_group_labels": _list(row.get("armGroupLabels")), "other_names": _list(row.get("otherNames")),
            })

        arm_groups = []
        for item in _list(arms.get("armGroups")):
            row = _dict(item)
            arm_groups.append({"label": row.get("label"), "type": row.get("type"), "description": row.get("description"), "intervention_names": _list(row.get("interventionNames"))})

        references = []
        result_refs = 0
        retractions = 0
        for item in _list(refs.get("references")):
            row = _dict(item)
            ref_type = _clean(row.get("type")) or None
            retract_rows = []
            for r in _list(row.get("retractions")):
                rr = _dict(r)
                retract_rows.append({"pmid": rr.get("pmid"), "source": rr.get("source")})
            retractions += len(retract_rows)
            if (ref_type or "").upper() == "RESULT": result_refs += 1
            references.append({"pmid": row.get("pmid"), "type": ref_type, "citation": row.get("citation"), "retractions": retract_rows})

        locations = []
        for item in _list(contacts.get("locations")):
            row = _dict(item)
            locations.append({
                "facility": row.get("facility"), "city": row.get("city"), "state": row.get("state"),
                "zip": row.get("zip"), "country": row.get("country"),
                "geo_point": _dict(row.get("geoPoint")) or None,
            })

        lead = _dict(sponsors.get("leadSponsor"))
        collaborators = [{"name": _dict(x).get("name"), "class": _dict(x).get("class")} for x in _list(sponsors.get("collaborators"))]
        enrollment = _dict(design.get("enrollmentInfo"))
        has_results = bool(study.get("hasResults") or results)
        results_first_posted = _date_value(status, "resultsFirstPostDate")
        publication_state = "linked-results-publication" if result_refs else ("related-publications-only" if references else "no-linked-publication")
        if has_results and result_refs:
            aggregate_state = "registry-results-and-linked-publication"
        elif has_results:
            aggregate_state = "registry-results-posted"
        elif result_refs:
            aggregate_state = "linked-results-publication-no-registry-results"
        else:
            aggregate_state = "registered-no-posted-results"

        normalized: dict[str, Any] = {
            "schema": "sc-clinical-trial-intelligence/1.0",
            "source_key": "clinicaltrials-intelligence",
            "record_type": "clinical-trial",
            "nct_id": nct or None,
            "title": _clean(ident.get("briefTitle")) or None,
            "official_title": _clean(ident.get("officialTitle")) or None,
            "acronym": _clean(ident.get("acronym")) or None,
            "organization": _dict(ident.get("organization")),
            "brief_summary": _clean(desc.get("briefSummary")) or None,
            "detailed_description": _clean(desc.get("detailedDescription")) or None,
            "overall_status": status.get("overallStatus"),
            "why_stopped": status.get("whyStopped"),
            "dates": {
                "study_start": _date_value(status, "startDateStruct"),
                "primary_completion": _date_value(status, "primaryCompletionDateStruct"),
                "study_completion": _date_value(status, "completionDateStruct"),
                "study_first_posted": _date_value(status, "studyFirstPostDateStruct"),
                "results_first_submitted": _date_value(status, "resultsFirstSubmitDate"),
                "results_first_posted": results_first_posted,
                "last_update_posted": _date_value(status, "lastUpdatePostDateStruct"),
            },
            "conditions": _list(conditions.get("conditions")),
            "keywords": _list(conditions.get("keywords")),
            "study_design": {
                "study_type": design.get("studyType"),
                "phases": _list(design.get("phases")),
                "enrollment": enrollment.get("count"),
                "enrollment_type": enrollment.get("type"),
                "allocation": design_info.get("allocation"),
                "intervention_model": design_info.get("interventionModel"),
                "primary_purpose": design_info.get("primaryPurpose"),
                "observational_model": design_info.get("observationalModel"),
                "time_perspective": design_info.get("timePerspective"),
                "masking": masking.get("masking"),
                "who_masked": _list(masking.get("whoMasked")),
            },
            "arm_groups": arm_groups,
            "interventions": interventions,
            "primary_outcomes": _list(outcomes.get("primaryOutcomes")),
            "secondary_outcomes": _list(outcomes.get("secondaryOutcomes")),
            "other_outcomes": _list(outcomes.get("otherOutcomes")),
            "eligibility": {
                "criteria": eligibility.get("eligibilityCriteria"),
                "healthy_volunteers": eligibility.get("healthyVolunteers"),
                "sex": eligibility.get("sex"),
                "minimum_age": eligibility.get("minimumAge"),
                "maximum_age": eligibility.get("maximumAge"),
                "standard_ages": _list(eligibility.get("stdAges")),
                "study_population": eligibility.get("studyPopulation"),
                "sampling_method": eligibility.get("samplingMethod"),
            },
            "sponsors": {
                "lead": {"name": lead.get("name"), "class": lead.get("class")},
                "collaborators": collaborators,
            },
            "locations": locations,
            "publications": {
                "references": references,
                "reference_count": len(references),
                "results_reference_count": result_refs,
                "retraction_signal_count": retractions,
                "publication_link_state": publication_state,
                "absence_notice": "No linked publication in the registry does not prove that no publication exists." if not references else None,
            },
            "terminology": {
                "condition_mesh": _list(condition_browse.get("meshes")),
                "intervention_mesh": _list(intervention_browse.get("meshes")),
            },
            "results_state": {
                "has_results": has_results,
                "results_first_posted": results_first_posted,
                "aggregate_evidence_state": aggregate_state,
                "registry_results_are_not_peer_review": True,
            },
            "source_url": f"https://clinicaltrials.gov/study/{nct}" if nct else None,
            "provenance": {
                "steward": self.descriptor.steward,
                "api": self.descriptor.base_url,
                "retrieved_at": _now(),
            },
            "handoffs": {
                "research_librarian": {"eligible": True, "mode": "clinical-trial-evidence-context"},
                "lab": {
                    "eligible": has_results,
                    "mode": "aggregate-trial-results" if has_results else None,
                    "participant_level_data": False,
                    "reason": None if has_results else "No posted aggregate registry results are available for analysis handoff.",
                },
            },
            "governance": {
                "research_only": True,
                "clinical_decision_support": False,
                "patient_specific_diagnosis": False,
                "patient_specific_treatment": False,
                "human_review_required": True,
            },
        }
        if include_results_summary:
            normalized["results_summary"] = self._normalize_results(results)
        return normalized

    @staticmethod
    def _normalize_results(results: dict[str, Any]) -> dict[str, Any]:
        flow = _dict(results.get("participantFlowModule"))
        baseline = _dict(results.get("baselineCharacteristicsModule"))
        outcomes = _dict(results.get("outcomeMeasuresModule"))
        adverse = _dict(results.get("adverseEventsModule"))
        outcome_rows = []
        for item in _list(outcomes.get("outcomeMeasures")):
            row = _dict(item)
            analyses = []
            for a in _list(row.get("analyses")):
                aa = _dict(a)
                analyses.append({
                    "param_type": aa.get("paramType"), "param_value": aa.get("paramValue"),
                    "dispersion_type": aa.get("dispersionType"), "dispersion_value": aa.get("dispersionValue"),
                    "ci_percent": aa.get("ciPctValue"),
                    "ci_lower_limit": aa.get("ciLowerLimit"),
                    "ci_upper_limit": aa.get("ciUpperLimit"),
                    "p_value": aa.get("pValue"), "statistical_method": aa.get("statisticalMethod"),
                })
            outcome_rows.append({
                "type": row.get("type"), "title": row.get("title"), "description": row.get("description"),
                "time_frame": row.get("timeFrame"), "units": row.get("units"), "param_type": row.get("paramType"),
                "dispersion_type": row.get("dispersionType"), "analyses": analyses,
            })
        event_groups = []
        for item in _list(adverse.get("eventGroups")):
            row = _dict(item)
            event_groups.append({"id": row.get("id"), "title": row.get("title"), "description": row.get("description")})
        return {
            "participant_flow": {
                "recruitment_details": flow.get("recruitmentDetails"),
                "pre_assignment_details": flow.get("preAssignmentDetails"),
                "group_count": len(_list(flow.get("groups"))),
                "period_count": len(_list(flow.get("periods"))),
            },
            "baseline": {
                "population_description": baseline.get("populationDescription"),
                "group_count": len(_list(baseline.get("groups"))),
                "measure_count": len(_list(baseline.get("measures"))),
            },
            "outcome_measures": outcome_rows,
            "adverse_events": {
                "time_frame": adverse.get("timeFrame"),
                "description": adverse.get("description"),
                "frequency_threshold": adverse.get("frequencyThreshold"),
                "group_count": len(event_groups),
                "groups": event_groups,
                "serious_event_term_count": len(_list(adverse.get("seriousEvents"))),
                "other_event_term_count": len(_list(adverse.get("otherEvents"))),
            },
            "participant_level_data_exposed": False,
        }
