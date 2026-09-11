from __future__ import annotations

from dataclasses import asdict, dataclass
from hashlib import sha256
import json
from typing import Any

MODEL_VERSION = "1.1.0"
SCHEMA_VERSION = "sc-energy-cross-product-runtime-activation/1.0"


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
    boundary: str


class EnergyCrossProductRuntimeActivation:
    """Stateless cross-product handoff gateway for Energy Systems Intelligence v1.1.0.

    The Library now does more than publish static cross-product contracts: it can build
    deterministic, target-shaped handoff packets from the v1.0.0 integrated study
    contract.  This is a pull-oriented activation gateway.  It does not claim that the
    separate target products have been modified, that they consume the packets, or that
    any outbound write/delivery has occurred.
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
                "sc-energy-runtime-research-librarian-handoff/1.0", "pull-get-json", "library-gateway-active", "target-consumer-not-certified",
                "The packet provides research context only. It does not generate conclusions, citations, or source-quality judgments on behalf of Research Librarian.",
            ),
            T(
                "lab", "Lab",
                "Transfer explicit energy scenarios, resource observations, uncertainty, economics and biological-carbon context into a modeling-ready packet.",
                ("energy-balance-scenario-contract", "energy-economic-scenario-contract", "bioenergy-carbon-scenario-contract", "renewable-resource-observation-contract"),
                ("identity", "technologies_and_resources", "energy_balance", "economics", "bioenergy_and_carbon", "uncertainty", "provenance", "review"),
                "sc-energy-runtime-lab-handoff/1.0", "pull-get-json", "library-gateway-active", "target-consumer-not-certified",
                "The packet is modeling-ready evidence transport, not simulation execution. Lab remains responsible for model assumptions, diagnostics and outputs.",
            ),
            T(
                "workbench", "Workbench",
                "Transfer source-bound numerical references and explicit-input calculation scenarios for interactive calculation.",
                ("energy-conversion-contract", "conversion-chain-model", "capacity-factor-generation-estimate", "energy-npv-result", "energy-cost-efficiency-result"),
                ("identity", "numeric_registry", "energy_balance", "economics", "bioenergy_and_carbon", "provenance", "review"),
                "sc-energy-runtime-workbench-handoff/1.0", "pull-get-json", "library-gateway-active", "target-consumer-not-certified",
                "The packet carries explicit inputs and provenance. It does not authorize Workbench to substitute hidden defaults or change source boundaries.",
            ),
            T(
                "site-intelligence", "Site Intelligence",
                "Transfer dated country-energy context and renewable-resource observations for spatial presentation and geospatial joining.",
                ("global-energy-country-profile-contract", "global-energy-comparison-contract", "renewable-resource-observation-contract"),
                ("identity", "technologies_and_resources", "global_context", "provenance", "review"),
                "sc-energy-runtime-site-intelligence-handoff/1.0", "pull-get-json", "library-gateway-active", "target-consumer-not-certified",
                "Spatial display does not establish site suitability, technical potential, causal attribution or current-year status when observations are older.",
            ),
            T(
                "decision-studio", "Decision Studio",
                "Transfer neutral decision packets, economic results, indicator context, country observations, uncertainty and evidence gaps into broader decision workflows.",
                ("energy-decision-packet-contract", "energy-decision-comparison-matrix", "energy-decision-readiness-contract", "integrated-energy-study-contract"),
                ("identity", "decision", "economics", "sustainability_indicators", "global_context", "uncertainty", "provenance", "review"),
                "sc-energy-runtime-decision-studio-handoff/1.0", "pull-get-json", "library-gateway-active", "target-consumer-not-certified",
                "Decision Studio may compare evidence but the handoff must not create hidden weights, composite scores, rankings, winners, investment recommendations or policy recommendations.",
            ),
        )

    def _validate_registry(self) -> None:
        if len(self._targets) != 5:
            raise ValueError("Energy Systems v1.1.0 requires five external runtime targets")
        if len({x.key for x in self._targets}) != len(self._targets):
            raise ValueError("Runtime target keys must be unique")
        for target in self._targets:
            if target.gateway_state != "library-gateway-active":
                raise ValueError("Every v1.1.0 target must expose an active Library gateway")
            if target.target_runtime_state == "certified-active":
                raise ValueError("v1.1.0 must not certify target-product execution without target-side evidence")

    def guardrails(self) -> dict[str, Any]:
        return {
            "runtime_activation_gateway_activated": True,
            "target_specific_packet_builders_activated": True,
            "pull_oriented_handoff_transport_activated": True,
            "stateless_packet_building": True,
            "target_runtime_consumption_certified": False,
            "outbound_push_delivery_activated": False,
            "cross_product_persistence_activated": False,
            "credential_forwarding_activated": False,
            "automatic_target_execution": False,
            "automatic_model_execution": False,
            "automatic_decision_ranking": False,
            "automatic_winner_selection": False,
            "automatic_recommendation": False,
            "study_payload_mutated": False,
        }

    def _content_fingerprint(self) -> str:
        payload = {"version": MODEL_VERSION, "targets": [asdict(x) for x in self._targets], "guardrails": self.guardrails()}
        return sha256(json.dumps(payload, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    def framework(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "version": MODEL_VERSION,
            "release": "Cross-Product Runtime Activation Gateway",
            "counts": {
                "external_runtime_targets": len(self._targets),
                "target_packet_builders": len(self._targets),
                "pull_handoff_contracts": len(self._targets),
                "target_runtimes_certified_active": 0,
            },
            "targets": [x.target for x in self._targets],
            "transport": "stateless-pull-oriented-json",
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
            "guardrail": "Library gateway activation means a target-shaped handoff can be built. It does not prove the separate target runtime currently consumes or executes it.",
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
            "numeric_registry": {"conversion_refs": [], "carbon_factor_refs": [], "heat_content_refs": [], "source_years_acknowledged": True},
            "sustainability_indicators": [],
            "technologies_and_resources": {"technology_refs": [], "resource_observations": [], "site_suitability_status": "not-inferred"},
            "energy_balance": {"scenario_refs": [], "results": []},
            "economics": {"scenario_refs": [], "results": [], "assumptions": []},
            "bioenergy_and_carbon": {"pathway_refs": [], "carbon_nature_refs": [], "results": []},
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
                "target": {"key": target["key"], "product": target["target"], "consumer_contract": target["consumer_contract"]},
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
            ))
            if not has_calculation_context:
                issues.append({"key": "missing-calculation-context", "severity": "warning", "message": "No numeric registry, energy-balance, or economic calculation context is present"})
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
            "interpretation": "Runtime readiness reports packet completeness for the Library gateway only. It does not certify target-side availability, execution, scientific validity or decision quality.",
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
            "target": {"key": target_obj.key, "product": target_obj.target, "consumer_contract": target_obj.consumer_contract},
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
            "delivery": {"mode": "pull-only", "outbound_delivery_performed": False, "persistence_performed": False, "target_execution_claimed": False},
            "guardrail": target_obj.boundary,
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
