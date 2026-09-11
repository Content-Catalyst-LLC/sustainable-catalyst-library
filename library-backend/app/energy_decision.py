from __future__ import annotations

from dataclasses import asdict, dataclass
from hashlib import sha256
import json
import re
from typing import Any

MODEL_VERSION = "0.9.0"
SCHEMA_VERSION = "sc-energy-decision-intelligence/1.0"
MAX_ALTERNATIVES = 8
MAX_OBSERVATIONS_PER_ALTERNATIVE = 50
MAX_PACKET_CHARS = 7000


@dataclass(frozen=True)
class EnergyDecisionCriterion:
    key: str
    label: str
    dimension: str
    value_type: str
    canonical_unit: str
    comparison_semantics: str
    evidence_refs: tuple[str, ...]
    boundary: str


class EnergyDecisionIntelligence:
    """Evidence-bound energy decision packets without automated ranking.

    v0.9.0 structures alternatives, criteria observations, provenance, uncertainty,
    constraints, and review context. It can build a neutral comparison matrix and
    inspect packet readiness. It does not normalize unlike quantities, assign
    hidden weights, calculate a composite sustainability score, rank alternatives,
    select a winner, or make investment/policy recommendations.
    """

    def __init__(self) -> None:
        self._criteria = self._build_criteria()
        self._validate_registry()
        self._fingerprint = self._content_fingerprint()

    @staticmethod
    def _build_criteria() -> dict[str, EnergyDecisionCriterion]:
        C = EnergyDecisionCriterion
        rows = [
            C(
                "annual-energy-service",
                "Annual energy service / useful output",
                "system-performance",
                "number",
                "explicit",
                "magnitude-only",
                ("energy-balance-scenario-contract", "capacity-factor-generation-estimate"),
                "The unit and service boundary must be explicit. More output is not automatically preferable if demand, quality, cost, or impacts differ.",
            ),
            C(
                "system-efficiency",
                "System efficiency",
                "system-performance",
                "number",
                "%",
                "magnitude-only",
                ("conversion-chain-model", "supply-demand-balance-model", "ECO3"),
                "Efficiency must preserve the conversion/system boundary. It is not interchangeable with capacity factor, reliability, or total energy use.",
            ),
            C(
                "capital-cost",
                "Capital cost",
                "economics",
                "number",
                "currency",
                "magnitude-only",
                ("energy-economic-scenario-contract", "energy-cost-comparison-result"),
                "Currency, price year, included cost categories, financing treatment, and system scope must be declared before comparison.",
            ),
            C(
                "net-present-value",
                "Net present value",
                "economics",
                "number",
                "currency",
                "magnitude-only",
                ("energy-npv-result",),
                "NPV is only comparable when discount rate, lifetime, cash-flow boundary, currency, price basis, and residual-value treatment are compatible.",
            ),
            C(
                "cost-efficiency",
                "Cost efficiency",
                "economics",
                "number",
                "explicit",
                "magnitude-only",
                ("energy-cost-efficiency-result",),
                "The outcome denominator must be identical or reconciled across alternatives; cost per kWh saved and cost per tonne CO2e avoided are different criteria.",
            ),
            C(
                "energy-access-affordability",
                "Energy access / affordability context",
                "social-access",
                "number-or-text",
                "explicit",
                "context-only",
                ("SOC1", "SOC2", "electricity-access", "energy-affordability"),
                "Country access indicators and project/user affordability evidence are context inputs, not interchangeable measures or automatic equity conclusions.",
            ),
            C(
                "energy-security-import-dependency",
                "Energy security / import dependency context",
                "energy-security",
                "number-or-text",
                "explicit",
                "context-only",
                ("ECO15", "ECO16", "net-energy-import-dependency"),
                "Import dependency is one security dimension. Negative values, stock coverage, supplier concentration, infrastructure, and resilience must retain source semantics.",
            ),
            C(
                "renewable-energy-share",
                "Renewable-energy share context",
                "energy-mix",
                "number",
                "%",
                "context-only",
                ("ECO13", "renewable-final-energy-share", "renewable-electricity-share"),
                "Renewable electricity share and renewable final-energy share have different denominators and must not be silently substituted.",
            ),
            C(
                "ghg-emissions",
                "Greenhouse-gas emissions / removals",
                "environment-climate",
                "number",
                "explicit",
                "magnitude-only",
                ("ENV1", "whole-system-ghg-accounting", "bioenergy-carbon-scenario-contract"),
                "Direct, lifecycle, avoided, biogenic, land-carbon, and removal accounting boundaries must be explicit. Biomass carbon neutrality is never assumed.",
            ),
            C(
                "land-water-biodiversity",
                "Land, water, biodiversity and ecosystem context",
                "environment-resource",
                "number-or-text",
                "explicit",
                "context-only",
                ("ENV4", "ENV5", "ENV6", "renewable-resource-observation-contract", "carbon-nature-accounting-handoff"),
                "Environmental dimensions are not collapsed into a single impact score. Geography, baseline, threshold, attribution, and ecosystem context must be preserved.",
            ),
            C(
                "reliability-flexibility",
                "Reliability, adequacy and flexibility context",
                "system-integration",
                "number-or-text",
                "explicit",
                "context-only",
                ("energy-balance-scenario-contract", "renewable-technology-assessment-contract"),
                "v0.9.0 can carry supplied reliability/flexibility evidence but does not run dispatch, adequacy, grid-stability, or storage-physics models.",
            ),
            C(
                "implementation-governance",
                "Implementation, governance and delivery context",
                "implementation",
                "text",
                "n/a",
                "context-only",
                ("energy-economic-scenario-contract", "global-energy-country-profile-contract"),
                "Permitting, institutions, supply chains, workforce, acceptance, policy, and delivery risk require explicit evidence and are not inferred from technology labels.",
            ),
        ]
        return {row.key: row for row in rows}

    def _validate_registry(self) -> None:
        if len(self._criteria) != 12:
            raise ValueError("Energy Decision Intelligence v0.9.0 requires twelve governed criteria")
        if len(set(self._criteria)) != len(self._criteria):
            raise ValueError("Decision criterion keys must be unique")

    def guardrails(self) -> dict[str, Any]:
        return {
            "decision_packet_contract_activated": True,
            "neutral_comparison_matrix_activated": True,
            "readiness_inspection_activated": True,
            "explicit_alternative_inputs_required": True,
            "provenance_visibility_required": True,
            "uncertainty_visibility_required": True,
            "unit_compatibility_checked": True,
            "period_compatibility_checked": True,
            "missing_values_preserved": True,
            "automatic_normalization": False,
            "automatic_weight_assignment": False,
            "composite_sustainability_score": False,
            "automatic_alternative_ranking": False,
            "automatic_winner_selection": False,
            "investment_recommendation": False,
            "policy_recommendation": False,
            "scenario_persistence": False,
            "decision_studio_execution": False,
            "matrix_is_not_decision": True,
            "readiness_is_not_merit_score": True,
        }

    def _content_fingerprint(self) -> str:
        payload = {
            "version": MODEL_VERSION,
            "schema": SCHEMA_VERSION,
            "criteria": [asdict(item) for item in self._criteria.values()],
            "guardrails": self.guardrails(),
        }
        return sha256(json.dumps(payload, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    def framework(self) -> dict[str, Any]:
        dimensions = sorted({item.dimension for item in self._criteria.values()})
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "version": MODEL_VERSION,
            "release": "Energy Decision Intelligence",
            "counts": {
                "criteria": len(self._criteria),
                "dimensions": len(dimensions),
                "decision_packet_contracts": 1,
                "comparison_matrix_models": 1,
                "readiness_models": 1,
            },
            "dimensions": dimensions,
            "criteria": [asdict(item) for item in self._criteria.values()],
            "guardrails": self.guardrails(),
            "content_fingerprint": self._fingerprint,
        }

    def criteria(self, *, q: str = "", dimension: str = "", limit: int = 100) -> dict[str, Any]:
        qn = (q or "").strip().lower()
        rows: list[dict[str, Any]] = []
        for item in self._criteria.values():
            if dimension and item.dimension != dimension:
                continue
            haystack = " ".join((item.key, item.label, item.dimension, item.boundary, *item.evidence_refs)).lower()
            if qn and qn not in haystack:
                continue
            rows.append(asdict(item))
            if len(rows) >= max(1, min(int(limit), 100)):
                break
        return {
            "ok": True,
            "schema": "sc-energy-decision-criteria/1.0",
            "version": MODEL_VERSION,
            "count": len(rows),
            "items": rows,
            "guardrail": "Decision criteria organize evidence; they do not assign merit, weights, scores, or rankings.",
        }

    def packet_template(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-decision-packet-template/1.0",
            "version": MODEL_VERSION,
            "packet": {
                "identity": {
                    "decision_id": "",
                    "title": "",
                    "question": "",
                    "geography": "",
                    "period": "",
                },
                "decision_context": {
                    "objectives": [],
                    "constraints": [],
                    "stakeholders": [],
                    "notes": "",
                },
                "alternatives": [
                    {
                        "key": "alternative-a",
                        "label": "Alternative A",
                        "description": "",
                        "technology_refs": [],
                        "scenario_refs": [],
                        "criteria_observations": [],
                        "evidence_refs": [],
                        "uncertainty_note": "",
                    },
                    {
                        "key": "alternative-b",
                        "label": "Alternative B",
                        "description": "",
                        "technology_refs": [],
                        "scenario_refs": [],
                        "criteria_observations": [],
                        "evidence_refs": [],
                        "uncertainty_note": "",
                    },
                ],
                "review": {
                    "assumptions": [],
                    "evidence_gaps": [],
                    "open_questions": [],
                    "reviewer_notes": "",
                },
            },
            "criteria_observation_contract": {
                "criterion_key": "one of the governed decision criterion keys",
                "value": "number, text, or null",
                "unit": "explicit unit or n/a",
                "period": "observation/model period",
                "source_ref": "source, model result, dataset, or document reference",
                "methodology_ref": "method/model definition where applicable",
                "uncertainty": "uncertainty/range/quality note where known",
                "notes": "boundary or interpretation note",
            },
            "handoffs": {
                "decision_studio": "contract-ready-no-execution",
                "lab": "scenario/evidence refs portable",
                "workbench": "calculation-result refs portable",
                "site_intelligence": "country/context refs portable",
            },
            "guardrails": self.guardrails(),
        }

    @staticmethod
    def _clean_key(value: Any, *, label: str) -> str:
        token = str(value or "").strip()
        if not token or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._:-]{0,79}", token):
            raise ValueError(f"{label} must be a compact identifier")
        return token

    def _parse_packet(self, packet_json: str) -> dict[str, Any]:
        raw = str(packet_json or "")
        if not raw.strip():
            raise ValueError("packet is required")
        if len(raw) > MAX_PACKET_CHARS:
            raise ValueError(f"packet may not exceed {MAX_PACKET_CHARS} characters in the v0.9.0 GET contract")
        try:
            packet = json.loads(raw)
        except json.JSONDecodeError as exc:
            raise ValueError("packet must be valid JSON") from exc
        if not isinstance(packet, dict):
            raise ValueError("packet must be a JSON object")
        alternatives = packet.get("alternatives")
        if not isinstance(alternatives, list) or len(alternatives) < 2:
            raise ValueError("packet must contain at least two alternatives")
        if len(alternatives) > MAX_ALTERNATIVES:
            raise ValueError(f"packet may contain at most {MAX_ALTERNATIVES} alternatives")
        seen: set[str] = set()
        for index, alternative in enumerate(alternatives):
            if not isinstance(alternative, dict):
                raise ValueError("each alternative must be a JSON object")
            key = self._clean_key(alternative.get("key"), label=f"alternatives[{index}].key")
            if key in seen:
                raise ValueError("alternative keys must be unique")
            seen.add(key)
            observations = alternative.get("criteria_observations", [])
            if not isinstance(observations, list):
                raise ValueError("criteria_observations must be a list")
            if len(observations) > MAX_OBSERVATIONS_PER_ALTERNATIVE:
                raise ValueError(f"each alternative may contain at most {MAX_OBSERVATIONS_PER_ALTERNATIVE} criteria observations")
            observed_keys: set[str] = set()
            for obs in observations:
                if not isinstance(obs, dict):
                    raise ValueError("each criteria observation must be a JSON object")
                criterion_key = str(obs.get("criterion_key") or "").strip()
                if criterion_key not in self._criteria:
                    raise ValueError(f"unknown decision criterion: {criterion_key or '<blank>'}")
                if criterion_key in observed_keys:
                    raise ValueError(f"alternative {key} contains duplicate criterion {criterion_key}")
                observed_keys.add(criterion_key)
        return packet

    @staticmethod
    def _obs_map(alternative: dict[str, Any]) -> dict[str, dict[str, Any]]:
        return {
            str(item.get("criterion_key")): item
            for item in alternative.get("criteria_observations", [])
            if isinstance(item, dict) and item.get("criterion_key")
        }

    @staticmethod
    def _present(value: Any) -> bool:
        return value is not None and str(value).strip() != ""

    def comparison_matrix(self, *, packet_json: str) -> dict[str, Any]:
        packet = self._parse_packet(packet_json)
        alternatives = packet["alternatives"]
        alt_meta = [
            {
                "key": alt["key"],
                "label": str(alt.get("label") or alt["key"]),
                "description": str(alt.get("description") or ""),
            }
            for alt in alternatives
        ]
        maps = [self._obs_map(alt) for alt in alternatives]
        used_keys = [key for key in self._criteria if any(key in mapping for mapping in maps)]
        rows: list[dict[str, Any]] = []
        incompatibilities: list[dict[str, Any]] = []
        for key in used_keys:
            criterion = self._criteria[key]
            cells = []
            units = set()
            periods = set()
            for alt, mapping in zip(alternatives, maps):
                obs = mapping.get(key)
                if obs is None:
                    cells.append({"alternative_key": alt["key"], "missing": True, "value": None})
                    continue
                unit = str(obs.get("unit") or "").strip()
                period = str(obs.get("period") or "").strip()
                if unit:
                    units.add(unit)
                if period:
                    periods.add(period)
                cells.append({
                    "alternative_key": alt["key"],
                    "missing": not self._present(obs.get("value")),
                    "value": obs.get("value"),
                    "unit": unit,
                    "period": period,
                    "source_ref": str(obs.get("source_ref") or ""),
                    "methodology_ref": str(obs.get("methodology_ref") or ""),
                    "uncertainty": str(obs.get("uncertainty") or ""),
                    "notes": str(obs.get("notes") or ""),
                })
            flags = []
            if len(units) > 1:
                flags.append("unit-mismatch")
            if len(periods) > 1:
                flags.append("period-mismatch")
            if flags:
                incompatibilities.append({"criterion_key": key, "flags": flags, "units": sorted(units), "periods": sorted(periods)})
            rows.append({
                "criterion": asdict(criterion),
                "cells": cells,
                "comparison_flags": flags,
            })
        return {
            "ok": True,
            "schema": "sc-energy-decision-comparison-matrix/1.0",
            "version": MODEL_VERSION,
            "alternatives": alt_meta,
            "rows": rows,
            "incompatibilities": incompatibilities,
            "guardrails": {
                "values_not_normalized": True,
                "no_weights_applied": True,
                "no_composite_score": True,
                "no_ranking": True,
                "matrix_is_not_decision": True,
            },
        }

    def readiness(self, *, packet_json: str) -> dict[str, Any]:
        packet = self._parse_packet(packet_json)
        identity = packet.get("identity") if isinstance(packet.get("identity"), dict) else {}
        required_identity = ("decision_id", "title", "question", "geography", "period")
        missing_identity = [key for key in required_identity if not self._present(identity.get(key))]
        alternatives = packet["alternatives"]
        alternative_results = []
        all_observed: set[str] = set()
        for alt in alternatives:
            observations = list(self._obs_map(alt).values())
            all_observed.update(str(obs.get("criterion_key")) for obs in observations)
            evidence_count = sum(1 for obs in observations if self._present(obs.get("source_ref")))
            methodology_count = sum(1 for obs in observations if self._present(obs.get("methodology_ref")))
            uncertainty_count = sum(1 for obs in observations if self._present(obs.get("uncertainty")))
            value_count = sum(1 for obs in observations if self._present(obs.get("value")))
            alternative_results.append({
                "key": alt["key"],
                "label": str(alt.get("label") or alt["key"]),
                "observation_count": len(observations),
                "observations_with_values": value_count,
                "observations_with_source_ref": evidence_count,
                "observations_with_methodology_ref": methodology_count,
                "observations_with_uncertainty": uncertainty_count,
                "provenance_coverage_pct": round((evidence_count / len(observations) * 100) if observations else 0.0, 2),
                "uncertainty_coverage_pct": round((uncertainty_count / len(observations) * 100) if observations else 0.0, 2),
            })
        matrix = self.comparison_matrix(packet_json=packet_json)
        observed_criteria = [key for key in self._criteria if key in all_observed]
        missing_criteria = [key for key in self._criteria if key not in all_observed]
        review = packet.get("review") if isinstance(packet.get("review"), dict) else {}
        evidence_gaps = review.get("evidence_gaps") if isinstance(review.get("evidence_gaps"), list) else []
        return {
            "ok": True,
            "schema": "sc-energy-decision-readiness/1.0",
            "version": MODEL_VERSION,
            "identity": {
                "required_fields": list(required_identity),
                "missing_fields": missing_identity,
                "complete": not missing_identity,
            },
            "alternatives": alternative_results,
            "criteria": {
                "governed_count": len(self._criteria),
                "observed_count": len(observed_criteria),
                "observed_keys": observed_criteria,
                "unobserved_keys": missing_criteria,
            },
            "comparability": {
                "incompatibility_count": len(matrix["incompatibilities"]),
                "incompatibilities": matrix["incompatibilities"],
            },
            "declared_evidence_gap_count": len(evidence_gaps),
            "readiness_interpretation": "Readiness describes packet completeness, provenance/uncertainty visibility, and obvious unit/period compatibility only. It is not a merit score and does not determine which alternative should be chosen.",
            "guardrails": self.guardrails(),
        }

    def export(self) -> dict[str, Any]:
        framework = self.framework()
        return {
            "schema": "sc-energy-decision-intelligence-export/1.0",
            "version": MODEL_VERSION,
            "framework": framework,
            "criteria": [asdict(item) for item in self._criteria.values()],
            "decision_packet_template": self.packet_template()["packet"],
            "guardrails": self.guardrails(),
            "content_fingerprint": self._fingerprint,
        }
