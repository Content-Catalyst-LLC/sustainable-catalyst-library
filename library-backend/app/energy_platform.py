from __future__ import annotations

from dataclasses import asdict, dataclass
from hashlib import sha256
import json
from typing import Any

MODEL_VERSION = "1.0.0"
SCHEMA_VERSION = "sc-integrated-sustainable-energy-platform/1.0"


@dataclass(frozen=True)
class EnergyPlatformLayer:
    version: str
    key: str
    label: str
    capability: str
    primary_contracts: tuple[str, ...]
    execution_state: str
    boundary: str


@dataclass(frozen=True)
class EnergyCrossProductContract:
    key: str
    target: str
    purpose: str
    contract_refs: tuple[str, ...]
    availability: str
    execution_state: str
    boundary: str


class IntegratedSustainableEnergyPlatform:
    """Integration and certification layer for Energy Systems Intelligence v1.0.0.

    This release certifies that the v0.1.0-v0.9.0 Energy Systems layers expose a
    coherent, read-only, provenance-aware contract surface inside the Library.
    Certification is structural and governance-oriented: it is not scientific
    validation, site suitability, financial advice, a live deployment audit, or
    proof that separate Sustainable Catalyst products execute these contracts.
    """

    def __init__(self) -> None:
        self._layers = self._build_layers()
        self._contracts = self._build_contracts()
        self._validate_registry()
        self._fingerprint = self._content_fingerprint()

    @staticmethod
    def _build_layers() -> tuple[EnergyPlatformLayer, ...]:
        L = EnergyPlatformLayer
        return (
            L("0.1.0", "knowledge-foundation", "Sustainable Energy Knowledge Foundation", "Concepts, relationships, sources, knowledge domains and SDG context", ("energy-concept-registry", "energy-relationship-registry", "energy-source-provenance-registry"), "active-in-library", "Knowledge objects organize source-supported context; they are not current-state measurements or recommendations."),
            L("0.2.0", "numeric-registry", "Energy Units, Carbon Factors & Conversion Registry", "Source-bound unit conversions, historic direct carbon factors and heat-content factors", ("energy-numeric-registry", "energy-conversion-contract", "energy-carbon-estimate-contract", "energy-heat-content-estimate-contract"), "active-in-library", "Historical factors remain source-year bound and are never promoted to current defaults."),
            L("0.3.0", "sustainability-indicators", "Energy Sustainability Indicators", "Thirty EISD definitions with provenance-first observation contracts", ("energy-indicator-framework", "energy-indicator-observation-contract"), "active-in-library", "Official methodology sheets are not loaded; indicator definitions are not observed values or sustainability scores."),
            L("0.4.0", "renewable-technologies", "Renewable Technology & Resource Model", "Renewable technology families, resource classes and assessment contracts", ("renewable-technology-assessment-contract", "renewable-resource-observation-contract"), "active-in-library", "Technology presence does not imply resource availability, site suitability, performance, cost, maturity or ranking."),
            L("0.5.0", "energy-balance", "Energy Balance & Systems Modeling", "Explicit-input conversion chains, supply-demand accounting and generation estimation", ("energy-balance-scenario-contract", "conversion-chain-model", "supply-demand-balance-model", "capacity-factor-generation-estimate"), "active-in-library", "Scenario arithmetic is not dispatch, grid adequacy, storage physics or a forecast."),
            L("0.6.0", "scenario-economics", "Energy Scenario Economics", "Cost comparison, payback, NPV, cost-benefit, cost-efficiency and levelized energy cost", ("energy-economic-scenario-contract", "energy-npv-result", "energy-cost-benefit-result", "energy-cost-efficiency-result"), "active-in-library", "Prices, discount rates, lifetimes, financing assumptions and non-market values are explicit inputs, not inferred defaults."),
            L("0.7.0", "bioenergy-carbon", "Biological Carbon & Bioenergy Integration", "Bioenergy pathways, explicit-input models and Carbon & Nature bridges", ("bioenergy-carbon-scenario-contract", "carbon-nature-accounting-handoff"), "active-in-library", "Biomass carbon neutrality, avoided emissions, additionality, permanence, leakage and credit eligibility are not assumed."),
            L("0.8.0", "global-energy", "Global Energy Intelligence", "Governed country metrics and live, dated World Bank observations", ("global-energy-country-profile-contract", "global-energy-comparison-contract", "world-bank-wdi-live-connector"), "active-in-library", "Latest available observation does not mean current-year fact; missing values are not interpolated and cross-source harmonization is not assumed."),
            L("0.9.0", "decision-intelligence", "Energy Decision Intelligence", "Evidence-bound decision packets, neutral comparison matrices and readiness inspection", ("energy-decision-packet-contract", "energy-decision-comparison-matrix", "energy-decision-readiness-contract"), "active-in-library", "Comparison is not ranking: no hidden weights, composite score, winner selection, investment recommendation or policy recommendation."),
        )

    @staticmethod
    def _build_contracts() -> tuple[EnergyCrossProductContract, ...]:
        C = EnergyCrossProductContract
        return (
            C("energy-to-library", "Library", "Host the governed Energy Systems knowledge, registry, model and contract surface.", ("energy-systems-manifest", "integrated-energy-study-contract", "energy-platform-certification-report"), "available", "host-runtime-active", "Library is the active host for v1.0.0. This does not imply that every referenced external source or separate Sustainable Catalyst product is live."),
            C("energy-to-research-librarian", "Research Librarian", "Route energy research questions through sources, concepts, indicators, global context and declared evidence gaps.", ("energy-source-provenance-registry", "energy-indicator-observation-contract", "global-energy-country-profile-contract", "energy-decision-packet-contract"), "contract-available", "not-activated-by-this-release", "The contract can supply research context; v1.0.0 does not modify or certify the Research Librarian runtime."),
            C("energy-to-lab", "Lab", "Transfer explicit scenarios, uncertainty-bearing inputs and evidence references for modeling.", ("energy-balance-scenario-contract", "energy-economic-scenario-contract", "bioenergy-carbon-scenario-contract", "renewable-resource-observation-contract"), "contract-available", "not-activated-by-this-release", "The Library defines portable contracts; Lab execution, simulation and uncertainty propagation require separate integration."),
            C("energy-to-workbench", "Workbench", "Expose source-bound calculators and explicit-input model contracts for interactive calculation.", ("energy-conversion-contract", "conversion-chain-model", "capacity-factor-generation-estimate", "energy-npv-result", "energy-cost-efficiency-result"), "contract-available", "not-activated-by-this-release", "Workbench is not modified by this release and must preserve each model's source and input boundaries when integrated."),
            C("energy-to-site-intelligence", "Site Intelligence", "Expose dated country-energy observations and renewable-resource observation contracts for spatial context.", ("global-energy-country-profile-contract", "global-energy-comparison-contract", "renewable-resource-observation-contract"), "contract-available", "not-activated-by-this-release", "A contract exists for spatial presentation; v1.0.0 does not add maps, geospatial inference or Site Intelligence runtime code."),
            C("energy-to-decision-studio", "Decision Studio", "Transfer neutral energy decision packets, compatibility flags and evidence gaps into broader decision workflows.", ("energy-decision-packet-contract", "energy-decision-comparison-matrix", "energy-decision-readiness-contract", "integrated-energy-study-contract"), "contract-available", "not-activated-by-this-release", "Decision Studio may consume the packet contract later; this release does not rank alternatives or make decisions."),
        )

    def _validate_registry(self) -> None:
        if len(self._layers) != 9:
            raise ValueError("Energy Systems v1.0.0 requires the nine v0.1.0-v0.9.0 platform layers")
        if len(self._contracts) != 6:
            raise ValueError("Energy Systems v1.0.0 requires six governed cross-product contracts")
        if len({x.key for x in self._layers}) != len(self._layers):
            raise ValueError("Platform layer keys must be unique")
        if len({x.key for x in self._contracts}) != len(self._contracts):
            raise ValueError("Cross-product contract keys must be unique")

    def guardrails(self) -> dict[str, Any]:
        return {
            "integrated_platform_activated": True,
            "release_lineage_preserved": True,
            "cross_product_contract_registry_activated": True,
            "integrated_study_contract_activated": True,
            "repository_structural_certification_activated": True,
            "provenance_boundaries_preserved": True,
            "missing_values_preserved": True,
            "historical_factors_promoted_to_current_defaults": False,
            "official_eisd_methodology_inferred": False,
            "resource_suitability_inferred": False,
            "dispatch_or_grid_adequacy_inferred": False,
            "economic_assumptions_inferred": False,
            "biomass_carbon_neutrality_assumed": False,
            "automatic_normalization": False,
            "automatic_weight_assignment": False,
            "automatic_alternative_ranking": False,
            "automatic_winner_selection": False,
            "investment_recommendation": False,
            "policy_recommendation": False,
            "separate_product_runtimes_modified_by_this_release": False,
            "cross_product_execution_claimed_by_this_release": False,
            "certification_is_scientific_validation": False,
            "certification_is_site_suitability_assessment": False,
            "certification_is_financial_advice": False,
            "certification_is_live_deployment_audit": False,
        }

    def _content_fingerprint(self) -> str:
        payload = {
            "version": MODEL_VERSION,
            "schema": SCHEMA_VERSION,
            "layers": [asdict(x) for x in self._layers],
            "contracts": [asdict(x) for x in self._contracts],
            "guardrails": self.guardrails(),
        }
        return sha256(json.dumps(payload, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    def framework(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "version": MODEL_VERSION,
            "release": "Integrated Sustainable Energy Systems Platform",
            "counts": {
                "release_layers": len(self._layers),
                "cross_product_contracts": len(self._contracts),
                "integrated_study_contracts": 1,
                "structural_certification_models": 1,
            },
            "release_layers": [asdict(x) for x in self._layers],
            "cross_product_targets": [x.target for x in self._contracts],
            "guardrails": self.guardrails(),
            "content_fingerprint": self._fingerprint,
        }

    def contracts(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-cross-product-contracts/1.0",
            "version": MODEL_VERSION,
            "count": len(self._contracts),
            "items": [asdict(x) for x in self._contracts],
            "guardrail": "Contract availability describes a governed handoff shape. It does not prove that the target product runtime currently consumes or executes the contract.",
        }

    def study_template(self) -> dict[str, Any]:
        study = {
            "identity": {
                "study_id": "",
                "title": "",
                "question": "",
                "geography": "",
                "period": "",
                "created_by": "",
            },
            "research_context": {
                "concept_refs": [],
                "source_refs": [],
                "research_questions": [],
                "evidence_gaps": [],
            },
            "numeric_registry": {
                "conversion_refs": [],
                "carbon_factor_refs": [],
                "heat_content_refs": [],
                "source_years_acknowledged": True,
            },
            "sustainability_indicators": [],
            "technologies_and_resources": {
                "technology_refs": [],
                "resource_observations": [],
                "site_suitability_status": "not-inferred",
            },
            "energy_balance": {
                "scenario_refs": [],
                "results": [],
            },
            "economics": {
                "scenario_refs": [],
                "results": [],
                "assumptions": [],
            },
            "bioenergy_and_carbon": {
                "pathway_refs": [],
                "carbon_nature_refs": [],
                "results": [],
            },
            "global_context": {
                "country_profile_refs": [],
                "observation_years": [],
                "missing_value_notes": [],
            },
            "decision": {
                "decision_packet_ref": "",
                "comparison_matrix_ref": "",
                "readiness_ref": "",
            },
            "uncertainty": [],
            "provenance": [],
            "review": {
                "assumptions": [],
                "limitations": [],
                "open_questions": [],
                "reviewer_notes": "",
            },
        }
        return {
            "ok": True,
            "schema": "sc-integrated-energy-study/1.0",
            "version": MODEL_VERSION,
            "study": study,
            "contract": {
                "provenance_required": True,
                "uncertainty_visible": True,
                "source_years_preserved": True,
                "missing_values_preserved": True,
                "cross_product_handoff_ready": True,
                "automatic_ranking": False,
                "automatic_recommendation": False,
                "persistence_status": "not-implemented",
            },
            "guardrail": "The integrated study contract packages evidence and model outputs without converting them into a composite score, ranking, winner or recommendation.",
        }

    @staticmethod
    def _check(key: str, label: str, actual: Any, expected: Any) -> dict[str, Any]:
        passed = actual == expected
        return {"key": key, "label": label, "status": "pass" if passed else "fail", "actual": actual, "expected": expected}

    def certification(self, manifest: dict[str, Any]) -> dict[str, Any]:
        counts = manifest.get("counts", {})
        subsystem = manifest.get("subsystem", {})
        guardrails = manifest.get("guardrails", {})
        global_energy = manifest.get("global_energy_intelligence", {})
        decision = manifest.get("energy_decision_intelligence", {})
        bioenergy = manifest.get("biological_carbon_bioenergy_integration", {})
        checks = [
            self._check("energy-version", "Energy Systems release identity", subsystem.get("version"), "1.0.0"),
            self._check("backend-version", "Shared Library backend identity", subsystem.get("backend_version"), "2.16.0"),
            self._check("concept-registry", "Knowledge concept registry preserved", counts.get("concepts"), 75),
            self._check("relationship-registry", "Knowledge relationship registry preserved", counts.get("relationships"), 63),
            self._check("numeric-units", "Unit registry preserved", counts.get("units"), 8),
            self._check("numeric-conversions", "Conversion-factor registry preserved", counts.get("conversion_factors"), 4),
            self._check("sustainability-indicators", "EISD definition registry preserved", counts.get("indicators"), 30),
            self._check("renewable-technologies", "Renewable technology registry preserved", counts.get("renewable_technologies"), 7),
            self._check("renewable-resources", "Renewable resource classes preserved", counts.get("renewable_resource_classes"), 6),
            self._check("balance-models", "Executable energy-balance models preserved", counts.get("energy_balance_executable_models"), 3),
            self._check("economic-models", "Executable economic models preserved", counts.get("energy_economic_executable_models"), 6),
            self._check("bioenergy-pathways", "Bioenergy pathways preserved", counts.get("bioenergy_pathways"), 6),
            self._check("global-metrics", "Global Energy metric registry preserved", counts.get("global_energy_metrics"), 9),
            self._check("global-live-connectors", "Governed live Global Energy connector preserved", counts.get("global_energy_live_connectors"), 1),
            self._check("decision-criteria", "Decision criteria registry preserved", counts.get("energy_decision_criteria"), 12),
            self._check("no-current-factor-promotion", "Historic factors remain non-current defaults", guardrails.get("current_factor_defaults_activated"), False),
            self._check("no-biomass-neutrality-assumption", "Biomass neutrality remains disabled", bioenergy.get("guardrails", {}).get("biomass_carbon_neutrality_assumed"), False),
            self._check("no-decision-ranking", "Automatic decision ranking remains disabled", decision.get("guardrails", {}).get("automatic_alternative_ranking"), False),
            self._check("no-current-year-assumption", "Latest Global Energy observation is not treated as current-year fact", global_energy.get("guardrails", {}).get("latest_available_is_not_current_year"), True),
            self._check("cross-product-execution-boundary", "Separate product execution is not claimed by v1.0.0", self.guardrails().get("cross_product_execution_claimed_by_this_release"), False),
        ]
        passed = sum(1 for x in checks if x["status"] == "pass")
        return {
            "ok": passed == len(checks),
            "schema": "sc-energy-platform-certification/1.0",
            "version": MODEL_VERSION,
            "certification_scope": "repository-and-domain-contract-coherence",
            "status": "pass" if passed == len(checks) else "fail",
            "counts": {"checks": len(checks), "passed": passed, "failed": len(checks) - passed},
            "checks": checks,
            "guardrails": {
                "scientific_validation": False,
                "live_deployment_audit": False,
                "site_suitability_assessment": False,
                "financial_advice": False,
                "separate_product_runtime_certification": False,
            },
            "interpretation": "A passing report means the v1.0.0 Library repository exposes the expected Energy Systems release layers, counts, contracts and governance boundaries. It does not validate external data quality, scientific conclusions, site suitability, financial outcomes or live deployment state.",
        }

    def export(self) -> dict[str, Any]:
        return {
            "schema": "sc-integrated-sustainable-energy-platform-export/1.0",
            "version": MODEL_VERSION,
            "framework": self.framework(),
            "contracts": [asdict(x) for x in self._contracts],
            "study_template": self.study_template(),
            "guardrails": self.guardrails(),
        }
