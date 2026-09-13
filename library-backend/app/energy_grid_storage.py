from __future__ import annotations

from copy import deepcopy
from hashlib import sha256
import json
from typing import Any

VERSION = "1.6.0"
SCHEMA = "sc-energy-grid-storage-reliability-registry/1.0"
WORKBENCH_MINIMUM_VERSION = "6.3.0"
LAB_MINIMUM_VERSION = "0.103.0"
SITE_INTELLIGENCE_MINIMUM_VERSION = "4.41.0"


class EnergyGridStorageReliabilityRegistry:
    """Governed contracts for explicit-input grid, storage and reliability analysis.

    Library publishes the contracts and evidence boundaries. Deterministic arithmetic belongs
    to Workbench; seeded uncertainty analysis belongs to Lab. Nothing in this registry infers
    real-world reliability, outages, storage performance, or adequacy from geography or labels.
    """

    def __init__(self) -> None:
        self._framework = self._build_framework()
        self._fingerprint = sha256(
            json.dumps(self._framework, sort_keys=True, separators=(",", ":")).encode()
        ).hexdigest()

    @staticmethod
    def _build_framework() -> dict[str, Any]:
        operations = [
            {
                "key": "storage-round-trip",
                "section": "grid_storage_reliability",
                "purpose": "Calculate delivered energy and round-trip efficiency from explicit charge/discharge efficiencies.",
                "required_inputs": ["charged_energy_kwh", "charge_efficiency_pct", "discharge_efficiency_pct"],
            },
            {
                "key": "storage-soc-trajectory",
                "section": "grid_storage_reliability",
                "purpose": "Step an explicit storage state-of-charge trajectory against a caller-supplied surplus/deficit series.",
                "required_inputs": ["energy_capacity_kwh", "initial_soc_kwh", "minimum_soc_kwh", "maximum_charge_kw", "maximum_discharge_kw", "charge_efficiency_pct", "discharge_efficiency_pct", "timestep_hours", "net_surplus_kw_series"],
            },
            {
                "key": "reserve-margin",
                "section": "grid_storage_reliability",
                "purpose": "Calculate reserve margin from explicit dependable capacity and peak demand.",
                "required_inputs": ["dependable_capacity_kw", "peak_demand_kw"],
            },
            {
                "key": "peak-demand-coverage",
                "section": "grid_storage_reliability",
                "purpose": "Compare explicit available generation plus storage discharge capability with peak demand.",
                "required_inputs": ["available_generation_kw", "storage_discharge_kw", "peak_demand_kw"],
            },
            {
                "key": "loss-of-load-events",
                "section": "grid_storage_reliability",
                "purpose": "Count explicit time steps and contiguous events in which demand exceeds available capacity.",
                "required_inputs": ["demand_kw_series", "available_capacity_kw_series", "timestep_hours"],
            },
            {
                "key": "energy-not-served",
                "section": "grid_storage_reliability",
                "purpose": "Integrate positive demand shortfall over an explicit time series.",
                "required_inputs": ["demand_kw_series", "available_capacity_kw_series", "timestep_hours"],
            },
            {
                "key": "adequacy-timeseries",
                "section": "grid_storage_reliability",
                "purpose": "Return a bounded adequacy summary from explicit demand and available-capacity series.",
                "required_inputs": ["demand_kw_series", "available_capacity_kw_series", "timestep_hours"],
            },
        ]
        return {
            "schema": SCHEMA,
            "version": VERSION,
            "release": "Grid, Storage & Reliability Analysis",
            "execution_authority": {
                "workbench": {
                    "minimum_version": WORKBENCH_MINIMUM_VERSION,
                    "framework_route": "/v1/energy-runtime/execution-framework",
                    "plan_route": "/v1/energy-runtime/plan",
                    "execute_route": "/v1/energy-runtime/execute",
                    "validation_route": "/v1/energy-runtime/validate-result",
                },
                "lab": {
                    "minimum_version": LAB_MINIMUM_VERSION,
                    "framework_route": "/v1/energy-reliability/framework",
                    "plan_route": "/v1/energy-reliability/plan",
                    "analyze_route": "/v1/energy-reliability/analyze",
                    "validation_route": "/v1/energy-reliability/validate-result",
                },
                "site_intelligence": {
                    "minimum_version": SITE_INTELLIGENCE_MINIMUM_VERSION,
                    "role": "spatial evidence source only; no reliability execution added in v1.6.0",
                },
            },
            "grid_topology_contract": {
                "nodes": ["node_id", "node_type", "geography_ref", "source_refs", "attributes"],
                "links": ["link_id", "from_node", "to_node", "link_type", "source_refs", "attributes"],
                "allowed_node_types": ["generation", "substation", "load", "storage", "interconnection", "bus", "other"],
                "allowed_link_types": ["transmission", "distribution", "interconnector", "logical", "other"],
                "topology_is_connectivity_evidence_not_power_flow": True,
            },
            "storage_contract": {
                "technology_label_is_descriptive_only": True,
                "required_operating_parameters_are_explicit": True,
                "embedded_efficiency_defaults": False,
                "embedded_degradation_defaults": False,
                "embedded_availability_defaults": False,
                "operating_fields": ["energy_capacity_kwh", "initial_soc_kwh", "minimum_soc_kwh", "maximum_charge_kw", "maximum_discharge_kw", "charge_efficiency_pct", "discharge_efficiency_pct", "timestep_hours"],
            },
            "reliability_evidence_contract": {
                "metrics": ["reserve_margin", "peak_demand_coverage", "loss_of_load_hours", "loss_of_load_events", "energy_not_served_kwh", "maximum_shortfall_kw"],
                "inputs_are_scenario_or_observation_bound": True,
                "metric_result_is_not_a_real_grid_reliability_declaration": True,
                "outage_observation_must_be_source_bound": True,
            },
            "operations": operations,
            "uncertainty_contract": {
                "designs": ["monte-carlo", "latin-hypercube"],
                "explicit_parameter_families": ["demand_multiplier", "renewable_output_multiplier", "storage_availability_multiplier", "firm_forced_outage_rate_pct"],
                "seed_required": True,
                "automatic_workbench_execution": False,
            },
            "guardrails": {
                "automatic_external_fetch": False,
                "automatic_power_flow": False,
                "unit_commitment_or_dispatch": False,
                "automatic_outage_prediction": False,
                "real_grid_reliability_declaration": False,
                "missing_parameter_inference": False,
                "storage_efficiency_inference": False,
                "forced_outage_rate_inference": False,
                "automatic_technology_ranking": False,
                "automatic_recommendation": False,
                "automatic_persistence": False,
            },
        }

    def framework(self) -> dict[str, Any]:
        return {"ok": True, **deepcopy(self._framework), "content_fingerprint": self._fingerprint}

    def storage_scenario_template(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-storage-scenario-template/1.0",
            "version": VERSION,
            "workbench_minimum_version": WORKBENCH_MINIMUM_VERSION,
            "template": {
                "scenario_id": "",
                "storage": {
                    "technology_label": "",
                    "energy_capacity_kwh": None,
                    "initial_soc_kwh": None,
                    "minimum_soc_kwh": None,
                    "maximum_charge_kw": None,
                    "maximum_discharge_kw": None,
                    "charge_efficiency_pct": None,
                    "discharge_efficiency_pct": None,
                    "timestep_hours": None,
                },
                "net_surplus_kw_series": [],
                "source_refs": [],
                "assumptions": [],
                "review": {"human_review_required": True, "notes": []},
            },
            "guardrail": "All storage performance parameters and time-series values must be supplied explicitly. Technology labels never activate hidden defaults.",
        }

    def reliability_scenario_template(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-reliability-scenario-template/1.0",
            "version": VERSION,
            "workbench_minimum_version": WORKBENCH_MINIMUM_VERSION,
            "lab_minimum_version": LAB_MINIMUM_VERSION,
            "template": {
                "scenario_id": "",
                "period": {"label": "", "timestep_hours": None},
                "demand_kw_series": [],
                "available_capacity_kw_series": [],
                "dependable_capacity_kw": None,
                "peak_demand_kw": None,
                "uncertainty": {
                    "design": {"method": "monte-carlo", "samples": None, "seed": None},
                    "variables": [],
                },
                "source_refs": [],
                "assumptions": [],
                "review": {"human_review_required": True, "notes": []},
            },
            "guardrail": "The template supports scenario analysis. It does not establish observed or forecast real-world grid reliability without source-bound inputs and review.",
        }
