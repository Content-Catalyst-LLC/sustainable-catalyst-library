from __future__ import annotations

from dataclasses import asdict, dataclass
from hashlib import sha256
import json
from typing import Any

MODEL_VERSION = "1.5.0"
SCHEMA_VERSION = "sc-energy-cross-product-runtime-activation/1.4"


@dataclass(frozen=True)
class EnergyRuntimeTarget:
    key: str
    target: str
    purpose: str
    source_contracts: tuple[str, ...]
    payload_sections: tuple[str, ...]
    consumer_contract: str
    transport: str
    gateway_state: str
    target_runtime_state: str
    minimum_target_version: str
    consumer_status_route: str
    consumer_intake_route: str
    boundary: str
    execution_state: str = "not-certified"
    execution_framework_route: str = ""
    execution_plan_route: str = ""
    execution_route: str = ""
    result_validation_route: str = ""


class EnergyCrossProductRuntimeActivation:
    """Stateless cross-product handoff gateway for Energy Systems Intelligence v1.5.0.

    The Library now does more than publish static cross-product contracts: it can build
    deterministic, target-shaped handoff packets from the v1.0.0 integrated study
    contract. The Library remains a pull-oriented gateway. v1.5.0 retains Lab v0.102.0 seeded uncertainty planning and statistical analysis around explicitly executed Workbench v6.2.0 results; the Library itself does not perform outbound delivery, persistence, sampling, or execution.
    """

    def __init__(self) -> None:
        self._targets = self._build_targets()
        self._validate_registry()
        self._fingerprint = self._content_fingerprint()

    @staticmethod
    def _build_targets() -> tuple[EnergyRuntimeTarget, ...]:
        T = EnergyRuntimeTarget
        return (
            T(
                "research-librarian", "Research Librarian",
                "Route an energy research question with source references, indicator context, dated global observations, evidence gaps and review notes.",
                ("energy-source-provenance-registry", "energy-indicator-observation-contract", "global-energy-country-profile-contract", "energy-decision-packet-contract"),
                ("identity", "research_context", "sustainability_indicators", "global_context", "provenance", "review"),
                "sc-energy-runtime-research-librarian-handoff/1.0", "pull-get-json", "library-gateway-active", "certified-contract-intake", "8.1.0", "/v1/energy-runtime/consumer", "/v1/energy-runtime/consume",
                "The packet provides research context only. It does not generate conclusions, citations, or source-quality judgments on behalf of Research Librarian.",
            ),
            T(
                "lab", "Lab",
                "Transfer explicit energy scenarios, resource observations, uncertainty, economics and biological-carbon context into a modeling-ready packet.",
                ("energy-balance-scenario-contract", "energy-economic-scenario-contract", "bioenergy-carbon-scenario-contract", "renewable-resource-observation-contract"),
                ("identity", "technologies_and_resources", "energy_balance", "economics", "bioenergy_and_carbon", "uncertainty", "provenance", "review"),
                "sc-energy-runtime-lab-handoff/1.0", "pull-get-json", "library-gateway-active", "certified-contract-intake", "0.102.0", "/v1/energy-runtime/consumer", "/v1/energy-runtime/consume",
                "Lab v0.102.0 can explicitly plan and analyze seeded Energy Systems uncertainty studies around Workbench outputs. It does not execute Workbench automatically, infer distributions, rank technologies, recommend a winner, or persist the study automatically.",
                "certified-energy-modeling-and-uncertainty",
                "/v1/energy-modeling/framework",
                "/v1/energy-modeling/plan",
                "/v1/energy-modeling/analyze",
                "/v1/energy-modeling/validate-result",
            ),
            T(
                "workbench", "Workbench",
                "Transfer source-bound numerical references and explicit-input calculation scenarios for interactive calculation.",
                ("energy-conversion-contract", "conversion-chain-model", "capacity-factor-generation-estimate", "energy-npv-result", "energy-cost-efficiency-result"),
                ("identity", "numeric_registry", "energy_balance", "economics", "bioenergy_and_carbon", "provenance", "review"),
                "sc-energy-runtime-workbench-handoff/1.0", "pull-get-json", "library-gateway-active", "certified-contract-intake", "6.2.0", "/v1/energy-runtime/consumer", "/v1/energy-runtime/consume",
                "The packet carries explicit inputs and provenance. Execution requires an explicit Workbench /execute request and does not authorize hidden defaults, ranking, recommendations, persistence, or source-boundary changes.",
                "certified-explicit-input-calculation-execution",
                "/v1/energy-runtime/execution-framework",
                "/v1/energy-runtime/plan",
                "/v1/energy-runtime/execute",
                "/v1/energy-runtime/validate-result",
            ),
            T(
                "site-intelligence", "Site Intelligence",
                "Transfer dated country-energy context, renewable-resource observations, infrastructure evidence, and provenance for explicit spatial/global energy profiling and geospatial joining.",
                ("global-energy-country-profile-contract", "global-energy-comparison-contract", "renewable-resource-observation-contract"),
                ("identity", "technologies_and_resources", "global_context", "provenance", "review"),
                "sc-energy-runtime-site-intelligence-handoff/1.0", "pull-get-json", "library-gateway-active", "certified-contract-intake", "4.41.0", "/v1/energy-runtime/consumer", "/v1/energy-runtime/consume",
                "Site Intelligence v4.41.0 can explicitly build provenance-bound spatial/global energy profiles and neutral comparisons. Spatial evidence does not establish site suitability, technical potential, grid reliability, outage status, causal attribution, or current-year status.",
                "certified-spatial-global-energy-intelligence",
                "/v1/energy-spatial/framework",
                "/v1/energy-spatial/source-registry",
                "/v1/energy-spatial/profile",
                "/v1/energy-spatial/validate-result",
            ),
            T(
                "decision-studio", "Decision Studio",
                "Transfer neutral decision packets, economic results, indicator context, country observations, uncertainty and evidence gaps into broader decision workflows.",
                ("energy-decision-packet-contract", "energy-decision-comparison-matrix", "energy-decision-readiness-contract", "integrated-energy-study-contract"),
                ("identity", "decision", "economics", "sustainability_indicators", "global_context", "uncertainty", "provenance", "review"),
                "sc-energy-runtime-decision-studio-handoff/1.0", "pull-get-json", "library-gateway-active", "certified-contract-intake", "2.3.0", "/v1/energy-runtime/consumer", "/v1/energy-runtime/consume",
                "Decision Studio may compare evidence but the handoff must not create hidden weights, composite scores, rankings, winners, investment recommendations or policy recommendations.",
            ),
        )

    def _validate_registry(self) -> None:
        if len(self._targets) != 5:
            raise ValueError("Energy Systems v1.5.0 requires five external runtime targets")
        if len({x.key for x in self._targets}) != len(self._targets):
            raise ValueError("Runtime target keys must be unique")
        for target in self._targets:
            if target.gateway_state != "library-gateway-active":
                raise ValueError("Every v1.5.0 target must expose an active Library gateway")
            if target.target_runtime_state != "certified-contract-intake":
                raise ValueError("Every v1.5.0 target must expose certified contract intake")

    def guardrails(self) -> dict[str, Any]:
        return {
            "runtime_activation_gateway_activated": True,
            "target_specific_packet_builders_activated": True,
            "pull_oriented_handoff_transport_activated": True,
            "stateless_packet_building": True,
            "target_runtime_consumption_certified": True,
            "outbound_push_delivery_activated": False,
            "cross_product_persistence_activated": False,
            "credential_forwarding_activated": False,
            "automatic_target_execution": False,
            "automatic_model_execution": False,
            "automatic_decision_ranking": False,
            "automatic_winner_selection": False,
            "automatic_recommendation": False,
            "study_payload_mutated": False,
            "target_product_runtimes_modified_by_this_release": True,
            "target_contract_intake_certified": True,
            "target_model_execution_certified": True,
            "lab_energy_modeling_uncertainty_certified": True,
            "site_intelligence_spatial_global_energy_certified": True,
            "site_intelligence_minimum_runtime_version": "4.41.0",
            "automatic_site_intelligence_external_fetch": False,
            "site_suitability_scoring": False,
            "lab_minimum_runtime_version": "0.102.0",
            "automatic_lab_to_workbench_execution": False,
            "workbench_explicit_calculation_execution_certified": True,
            "workbench_minimum_runtime_version": "6.2.0",
            "automatic_workbench_execution": False,
        }

    def _content_fingerprint(self) -> str:
        payload = {"version": MODEL_VERSION, "targets": [asdict(x) for x in self._targets], "guardrails": self.guardrails()}
        return sha256(json.dumps(payload, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    def framework(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "version": MODEL_VERSION,
            "release": "Spatial & Global Energy Intelligence",
            "counts": {
                "external_runtime_targets": len(self._targets),
                "target_packet_builders": len(self._targets),
                "pull_handoff_contracts": len(self._targets),
                "target_runtimes_certified_active": len(self._targets),
                "explicit_execution_targets": 1,
                "modeling_analysis_targets": 1,
                "spatial_analysis_targets": 1,
            },
            "targets": [x.target for x in self._targets],
            "transport": "stateless-pull-oriented-json-with-target-intake-explicit-workbench-execution-and-lab-uncertainty-analysis",
            "guardrails": self.guardrails(),
            "content_fingerprint": self._fingerprint,
        }

    def targets(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-runtime-target-registry/1.0",
            "version": MODEL_VERSION,
            "count": len(self._targets),
            "items": [asdict(x) for x in self._targets],
            "guardrail": "Target-side contract intake is certified at the minimum versions listed. Intake validates and receipts packets; it does not execute models, persist studies, rank alternatives, or prove scientific validity.",
        }

    def target(self, key: str) -> dict[str, Any]:
        target = next((x for x in self._targets if x.key == key), None)
        if target is None:
            raise KeyError(key)
        return {"ok": True, "schema": "sc-energy-runtime-target/1.0", "version": MODEL_VERSION, "target": asdict(target), "guardrails": self.guardrails()}

    @staticmethod
    def _blank_study() -> dict[str, Any]:
        return {
            "identity": {"study_id": "", "title": "", "question": "", "geography": "", "period": "", "created_by": ""},
            "research_context": {"concept_refs": [], "source_refs": [], "research_questions": [], "evidence_gaps": []},
            "numeric_registry": {"conversion_refs": [], "carbon_factor_refs": [], "heat_content_refs": [], "source_years_acknowledged": True, "calculation_requests": []},
            "sustainability_indicators": [],
            "technologies_and_resources": {"technology_refs": [], "resource_observations": [], "site_suitability_status": "not-inferred"},
            "energy_balance": {"scenario_refs": [], "results": [], "calculation_requests": []},
            "economics": {"scenario_refs": [], "results": [], "assumptions": [], "calculation_requests": []},
            "bioenergy_and_carbon": {"pathway_refs": [], "carbon_nature_refs": [], "results": [], "calculation_requests": []},
            "global_context": {"country_profile_refs": [], "observation_years": [], "missing_value_notes": []},
            "decision": {"decision_packet_ref": "", "comparison_matrix_ref": "", "readiness_ref": ""},
            "uncertainty": [],
            "provenance": [],
            "review": {"assumptions": [], "limitations": [], "open_questions": [], "reviewer_notes": ""},
        }

    def handoff_template(self, key: str) -> dict[str, Any]:
        target = self.target(key)["target"]
        study = self._blank_study()
        selected = {name: study[name] for name in target["payload_sections"]}
        return {
            "ok": True,
            "schema": "sc-energy-runtime-handoff-template/1.0",
            "version": MODEL_VERSION,
            "target": target,
            "packet": {
                "handoff_id": "",
                "source": {"product": "Library", "subsystem": "Energy Systems Intelligence", "version": MODEL_VERSION},
                "target": {"key": target["key"], "product": target["target"], "consumer_contract": target["consumer_contract"], "minimum_target_version": target["minimum_target_version"]},
                "transport": target["transport"],
                "contract_refs": target["source_contracts"],
                "payload": selected,
                "validation": {"status": "template", "populated_sections": [], "empty_sections": list(target["payload_sections"]), "issues": []},
                "guardrails": self.guardrails(),
            },
            "guardrail": target["boundary"],
        }

    @staticmethod
    def _is_populated(value: Any) -> bool:
        if value is None:
            return False
        if isinstance(value, str):
            return bool(value.strip())
        if isinstance(value, (list, tuple, set)):
            return len(value) > 0
        if isinstance(value, dict):
            return any(EnergyCrossProductRuntimeActivation._is_populated(v) for v in value.values())
        return True

    @staticmethod
    def _normalize_study(study: dict[str, Any]) -> dict[str, Any]:
        if not isinstance(study, dict):
            raise ValueError("study must be a JSON object")
        blank = EnergyCrossProductRuntimeActivation._blank_study()
        unknown = sorted(set(study) - set(blank))
        if unknown:
            raise ValueError("study contains unknown top-level section(s): " + ", ".join(unknown))
        merged = {}
        for key, default in blank.items():
            value = study.get(key, default)
            if isinstance(default, dict) and not isinstance(value, dict):
                raise ValueError(f"study.{key} must be an object")
            if isinstance(default, list) and not isinstance(value, list):
                raise ValueError(f"study.{key} must be an array")
            merged[key] = value
        return merged

    def _readiness(self, target: EnergyRuntimeTarget, study: dict[str, Any]) -> dict[str, Any]:
        populated = [s for s in target.payload_sections if self._is_populated(study.get(s))]
        empty = [s for s in target.payload_sections if s not in populated]
        issues: list[dict[str, str]] = []
        identity = study.get("identity", {})
        if not str(identity.get("study_id", "")).strip():
            issues.append({"key": "missing-study-id", "severity": "warning", "message": "identity.study_id is empty"})
        if not str(identity.get("question", "")).strip():
            issues.append({"key": "missing-question", "severity": "warning", "message": "identity.question is empty"})
        if not self._is_populated(study.get("provenance")):
            issues.append({"key": "missing-provenance", "severity": "warning", "message": "No provenance records are present"})
        if target.key == "site-intelligence":
            global_context = study.get("global_context", {})
            resources = study.get("technologies_and_resources", {})
            if not self._is_populated(global_context.get("country_profile_refs")) and not self._is_populated(resources.get("resource_observations")):
                issues.append({"key": "missing-spatial-energy-context", "severity": "warning", "message": "No country-profile references or renewable-resource observations are present"})
        if target.key == "decision-studio" and not self._is_populated(study.get("decision")):
            issues.append({"key": "missing-decision-context", "severity": "warning", "message": "No decision packet/matrix/readiness references are present"})
        if target.key == "lab":
            eb = study.get("energy_balance", {})
            tr = study.get("technologies_and_resources", {})
            has_model_context = any((
                self._is_populated(eb.get("scenario_refs")), self._is_populated(eb.get("results")),
                self._is_populated(tr.get("technology_refs")), self._is_populated(tr.get("resource_observations")),
            ))
            if not has_model_context:
                issues.append({"key": "missing-model-context", "severity": "warning", "message": "No energy-balance scenario or technology/resource evidence is present"})
        if target.key == "workbench":
            nr = study.get("numeric_registry", {})
            eb = study.get("energy_balance", {})
            ec = study.get("economics", {})
            has_calculation_context = any((
                self._is_populated(nr.get("conversion_refs")), self._is_populated(nr.get("carbon_factor_refs")), self._is_populated(nr.get("heat_content_refs")),
                self._is_populated(eb.get("scenario_refs")), self._is_populated(eb.get("results")),
                self._is_populated(ec.get("scenario_refs")), self._is_populated(ec.get("results")),
                self._is_populated(nr.get("calculation_requests")), self._is_populated(eb.get("calculation_requests")),
                self._is_populated(ec.get("calculation_requests")), self._is_populated(study.get("bioenergy_and_carbon", {}).get("calculation_requests")),
            ))
            if not has_calculation_context:
                issues.append({"key": "missing-calculation-context", "severity": "warning", "message": "No numeric registry, energy-balance, economic, bioenergy, or explicit calculation-request context is present"})
        if target.key == "research-librarian" and not self._is_populated(study.get("research_context")):
            issues.append({"key": "missing-research-context", "severity": "warning", "message": "No research questions, source references, concepts or evidence gaps are present"})
        status = "ready-with-warnings" if issues else "ready"
        return {"status": status, "populated_sections": populated, "empty_sections": empty, "issues": issues}

    def readiness(self, key: str, study: dict[str, Any]) -> dict[str, Any]:
        target_obj = next((x for x in self._targets if x.key == key), None)
        if target_obj is None:
            raise KeyError(key)
        normalized = self._normalize_study(study)
        result = self._readiness(target_obj, normalized)
        return {
            "ok": True,
            "schema": "sc-energy-runtime-readiness/1.0",
            "version": MODEL_VERSION,
            "target": {"key": target_obj.key, "product": target_obj.target},
            **result,
            "interpretation": (
                "Runtime readiness reports packet completeness. Workbench v6.2.0 is certified for explicit-input Energy Systems arithmetic when execution is explicitly requested. No automatic execution, persistence, ranking, recommendation, scientific validity, or decision quality is certified."
                if target_obj.key == "workbench" else
                "Runtime readiness reports packet completeness. Lab v0.102.0 is certified for explicit seeded uncertainty planning and statistical analysis of returned Workbench results. Lab does not call Workbench automatically, infer missing distributions, rank technologies, recommend a winner, or persist automatically."
                if target_obj.key == "lab" else
                "Runtime readiness reports packet completeness. Target-side contract intake is certified at the listed minimum version; execution, persistence, scientific validity, ranking, recommendation, and decision quality are not certified."
            ),
        }

    def build_handoff(self, key: str, study: dict[str, Any]) -> dict[str, Any]:
        target_obj = next((x for x in self._targets if x.key == key), None)
        if target_obj is None:
            raise KeyError(key)
        normalized = self._normalize_study(study)
        payload = {name: normalized[name] for name in target_obj.payload_sections}
        canonical = json.dumps({"target": key, "payload": payload}, sort_keys=True, separators=(",", ":"))
        handoff_id = "es-" + sha256(canonical.encode()).hexdigest()[:20]
        readiness = self._readiness(target_obj, normalized)
        packet = {
            "handoff_id": handoff_id,
            "source": {"product": "Library", "subsystem": "Energy Systems Intelligence", "version": MODEL_VERSION},
            "target": {"key": target_obj.key, "product": target_obj.target, "consumer_contract": target_obj.consumer_contract, "minimum_target_version": target_obj.minimum_target_version},
            "transport": target_obj.transport,
            "contract_refs": list(target_obj.source_contracts),
            "payload": payload,
            "validation": readiness,
            "guardrails": self.guardrails(),
        }
        return {
            "ok": True,
            "schema": "sc-energy-runtime-handoff/1.0",
            "version": MODEL_VERSION,
            "packet": packet,
            "delivery": {"mode": "pull-only", "outbound_delivery_performed": False, "persistence_performed": False, "target_execution_claimed": key in {"workbench", "lab"}, "execution_is_automatic": False},
            "guardrail": target_obj.boundary,
        }

    def consumers(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-runtime-consumer-registry/1.0",
            "version": MODEL_VERSION,
            "count": len(self._targets),
            "items": [
                {
                    "target_key": x.key, "product": x.target,
                    "minimum_target_version": x.minimum_target_version,
                    "consumer_contract": x.consumer_contract,
                    "status_route": x.consumer_status_route,
                    "intake_route": x.consumer_intake_route,
                    "state": x.target_runtime_state,
                    "execution": {
                        "state": x.execution_state,
                        "framework_route": x.execution_framework_route,
                        "plan_route": x.execution_plan_route,
                        "execute_route": x.execution_route,
                        "validate_result_route": x.result_validation_route,
                        "automatic": False,
                    },
                } for x in self._targets
            ],
            "certification_scope": "All five targets: schema/target/payload intake, deterministic receipt, and provenance preservation. Workbench v6.2.0 certifies explicit-input ephemeral calculation execution. Lab v0.102.0 additionally certifies seeded uncertainty planning and statistical analysis around returned Workbench result packets; cross-service execution remains explicit and non-automatic, and persistence, technology ranking, recommendations, and scientific assurance remain excluded.",
        }

    def export(self) -> dict[str, Any]:
        return {
            "schema": "sc-energy-cross-product-runtime-activation-export/1.0",
            "version": MODEL_VERSION,
            "framework": self.framework(),
            "targets": [asdict(x) for x in self._targets],
            "templates": {x.key: self.handoff_template(x.key)["packet"] for x in self._targets},
            "guardrails": self.guardrails(),
        }
