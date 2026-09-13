from __future__ import annotations

from copy import deepcopy
from hashlib import sha256
import json
from typing import Any

VERSION = "1.4.0"
SCHEMA = "sc-energy-modeling-uncertainty-registry/1.0"
LAB_MINIMUM_VERSION = "0.102.0"
WORKBENCH_MINIMUM_VERSION = "6.2.0"


class EnergyModelingUncertaintyRegistry:
    """Library-side contract for Energy Systems uncertainty workflows.

    The Library declares explicit study structure and handoff boundaries. Sampling and
    uncertainty analysis execute in Lab; source-bound arithmetic remains in Workbench.
    """

    def __init__(self) -> None:
        self._framework = self._build_framework()
        self._fingerprint = sha256(json.dumps(self._framework, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    @staticmethod
    def _build_framework() -> dict[str, Any]:
        return {
            "schema": SCHEMA,
            "version": VERSION,
            "release": "Energy Modeling & Uncertainty",
            "execution_authorities": {
                "lab": {
                    "minimum_version": LAB_MINIMUM_VERSION,
                    "framework_route": "/v1/energy-modeling/framework",
                    "plan_route": "/v1/energy-modeling/plan",
                    "analysis_route": "/v1/energy-modeling/analyze",
                    "validation_route": "/v1/energy-modeling/validate-result",
                    "role": "seeded uncertainty design and statistical analysis of explicit Workbench results",
                },
                "workbench": {
                    "minimum_version": WORKBENCH_MINIMUM_VERSION,
                    "execute_route": "/v1/energy-runtime/execute",
                    "role": "source-bound explicit-input energy arithmetic",
                },
            },
            "sampling_designs": ["monte-carlo", "latin-hypercube"],
            "distributions": ["uniform", "normal", "lognormal", "triangular"],
            "analysis_outputs": [
                "empirical-summary-statistics", "central-uncertainty-interval", "explicit-threshold-probabilities",
                "pearson-input-sensitivity", "spearman-input-sensitivity", "standardized-regression-input-sensitivity",
            ],
            "workflow": [
                "Library structures evidence, provenance, and explicit uncertainty study intent.",
                "Lab creates a deterministic seeded evaluation plan; it does not execute Workbench automatically.",
                "Workbench explicitly evaluates each sampled input set using the v1.3.0 calculation runtime.",
                "Lab analyzes the returned Workbench result packet and preserves provenance and review context.",
                "Human review remains required before interpretation, publication, or decision use.",
            ],
            "guardrails": {
                "explicit_distributions_required": True,
                "explicit_seed_required": True,
                "explicit_result_path_required": True,
                "hidden_defaults_allowed": False,
                "automatic_workbench_execution": False,
                "automatic_persistence": False,
                "technology_ranking": False,
                "automatic_recommendation": False,
                "market_data_fetch": False,
                "avoided_emissions_inference": False,
                "carbon_credit_claiming": False,
            },
        }

    def framework(self) -> dict[str, Any]:
        return {"ok": True, **deepcopy(self._framework), "content_fingerprint": self._fingerprint}

    def study_template(self) -> dict[str, Any]:
        template = {
            "study_id": "energy-uncertainty-study",
            "question": "",
            "calculation_request": {
                "section": "energy_balance",
                "operation": "capacity-factor-generation",
                "inputs": {"capacity_kw": 100.0, "capacity_factor_pct": 50.0, "hours": 8760.0},
                "source_refs": [],
                "assumptions": [],
            },
            "uncertainty": {
                "variables": [
                    {"name": "capacity_factor_pct", "distribution": "triangular", "low": 40.0, "mode": 50.0, "high": 60.0, "unit": "%"}
                ],
                "design": {"method": "latin-hypercube", "samples": 128, "seed": 2026},
            },
            "output": {"result_path": "generation_kwh", "unit": "kWh", "confidence": 0.95, "thresholds": []},
            "provenance": [],
            "review": {"human_review_required": True, "notes": []},
        }
        return {
            "ok": True,
            "schema": "sc-energy-modeling-uncertainty-study-template/1.0",
            "version": VERSION,
            "template": template,
            "guardrail": "Template values are illustrative structure, not recommended technical assumptions. Replace every numeric value with source-bound study inputs before analysis.",
        }
