from __future__ import annotations

from dataclasses import dataclass
from decimal import Decimal, InvalidOperation, localcontext
from hashlib import sha256
import json
from typing import Any, Iterable


MODEL_VERSION = "0.5.0"
SCHEMA_VERSION = "sc-energy-balance-systems-model/1.0"


@dataclass(frozen=True)
class EnergyBalanceModelDefinition:
    key: str
    label: str
    model_type: str
    inputs: tuple[str, ...]
    outputs: tuple[str, ...]
    source_keys: tuple[str, ...]
    execution_status: str
    boundary: str


class EnergyBalanceSystemsModel:
    """Deterministic, provenance-aware energy balance and systems arithmetic.

    v0.5.0 activates bounded calculations that use explicit user-supplied inputs.
    It does not infer technology performance, dispatch, reliability, storage physics,
    demand growth, costs, emissions, resource availability, or preferred scenarios.
    """

    def __init__(self, *, technology_keys: Iterable[str]) -> None:
        self._technology_keys = tuple(sorted(set(technology_keys)))
        self._models = self._build_models()
        self._validate()
        self._fingerprint = self._content_fingerprint()

    @staticmethod
    def _build_models() -> dict[str, EnergyBalanceModelDefinition]:
        M = EnergyBalanceModelDefinition
        rows = [
            M(
                "conversion-chain",
                "Conversion chain",
                "deterministic-stage-balance",
                ("input_kwh", "stage_efficiencies_pct", "optional_stage_labels"),
                ("stage_outputs", "stage_losses", "final_output_kwh", "total_loss_kwh", "overall_efficiency_pct"),
                ("ucd-module-sustainable-energy", "mcdonnell-lecture-1"),
                "active-user-input-arithmetic",
                "Efficiencies are explicit scenario inputs. No technology-specific efficiency is supplied or inferred by this model.",
            ),
            M(
                "supply-demand-balance",
                "Supply–demand balance",
                "accounting-identity",
                (
                    "domestic_supply_kwh", "imports_kwh", "storage_discharge_kwh",
                    "final_demand_kwh", "exports_kwh", "storage_charge_kwh", "losses_kwh", "tolerance_kwh",
                ),
                ("available_supply_kwh", "accounted_outflows_kwh", "residual_kwh", "residual_pct_of_supply", "balanced"),
                ("ucd-module-sustainable-energy",),
                "active-user-input-arithmetic",
                "This is an energy accounting identity, not a grid dispatch, adequacy, reliability, or market-clearing model.",
            ),
            M(
                "capacity-factor-generation",
                "Capacity-factor generation estimate",
                "deterministic-generation-arithmetic",
                ("capacity_kw", "capacity_factor_pct", "hours"),
                ("generation_kwh", "average_output_kw"),
                ("ucd-module-sustainable-energy",),
                "active-user-input-arithmetic",
                "Capacity factor and hours are explicit scenario inputs. The calculation is not a forecast and does not infer a technology-specific capacity factor.",
            ),
            M(
                "balance-scenario-contract",
                "Energy balance scenario contract",
                "scenario-object-contract",
                ("identity", "scope", "supply", "conversion", "demand", "accounting", "evidence", "uncertainty"),
                ("portable_scenario_packet",),
                ("ucd-module-sustainable-energy", "vera-langlois-2007"),
                "contract-active-no-persistence",
                "The contract organizes a scenario for later Lab/Workbench/Decision Studio use; v0.5.0 does not persist, optimize, rank, or recommend scenarios.",
            ),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _decimal(value: str | int | float | Decimal, *, label: str, allow_zero: bool = True) -> Decimal:
        try:
            number = Decimal(str(value).strip())
        except (InvalidOperation, AttributeError, ValueError) as exc:
            raise ValueError(f"{label} must be a finite decimal number") from exc
        if not number.is_finite():
            raise ValueError(f"{label} must be a finite decimal number")
        if number < 0 or (not allow_zero and number == 0):
            comparator = "greater than zero" if not allow_zero else "zero or greater"
            raise ValueError(f"{label} must be {comparator}")
        return number

    @staticmethod
    def _decimal_text(value: Decimal, *, places: int | None = 12) -> str:
        if places is not None:
            quantum = Decimal(1).scaleb(-places)
            value = value.quantize(quantum)
        text = format(value.normalize(), "f")
        if "." in text:
            text = text.rstrip("0").rstrip(".")
        return text or "0"

    @staticmethod
    def _percent(value: str | int | float | Decimal, *, label: str) -> Decimal:
        number = EnergyBalanceSystemsModel._decimal(value, label=label)
        if number > 100:
            raise ValueError(f"{label} must be between 0 and 100 percent")
        return number

    def _validate(self) -> None:
        if set(self._models) != {"conversion-chain", "supply-demand-balance", "capacity-factor-generation", "balance-scenario-contract"}:
            raise ValueError("Energy balance model registry is incomplete")
        if not self._technology_keys:
            raise ValueError("Energy balance scenario contract requires renewable technology references")
        for model in self._models.values():
            if model.execution_status.startswith("active") and not model.inputs:
                raise ValueError(f"Executable model {model.key} must declare inputs")

    def _content_fingerprint(self) -> str:
        payload = {
            "version": MODEL_VERSION,
            "schema": SCHEMA_VERSION,
            "models": [model.__dict__ for model in self._models.values()],
            "technology_keys": self._technology_keys,
            "guardrails": self.guardrails(),
        }
        return sha256(json.dumps(payload, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    def guardrails(self) -> dict[str, Any]:
        return {
            "explicit_user_inputs_required": True,
            "technology_specific_efficiency_inference": False,
            "capacity_factor_inference": False,
            "resource_availability_inference": False,
            "time_series_dispatch_simulation": False,
            "storage_physics_simulation": False,
            "grid_reliability_or_adequacy_model": False,
            "economic_optimization": False,
            "technology_ranking": False,
            "policy_recommendation": False,
            "scenario_persistence": False,
            "result_is_not_forecast": True,
            "result_is_not_suitability_determination": True,
        }

    def framework(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "version": MODEL_VERSION,
            "release": "Energy Balance & Systems Modeling",
            "counts": {
                "models": len(self._models),
                "executable_models": sum(1 for x in self._models.values() if x.execution_status.startswith("active")),
                "scenario_contracts": 1,
                "renewable_technology_references": len(self._technology_keys),
            },
            "system_boundary": {
                "flow": ["supply", "conversion", "distribution/accounting", "final demand/end use"],
                "canonical_energy_unit": "kWh-equivalent for v0.5.0 arithmetic",
                "balance_identity": "available supply = accounted outflows + residual",
                "conversion_identity": "stage output = stage input × efficiency; stage loss = stage input − stage output",
                "generation_identity": "generation = capacity × hours × capacity factor",
            },
            "models": [model.__dict__ for model in self._models.values()],
            "guardrails": self.guardrails(),
            "content_fingerprint": self._fingerprint,
        }

    def conversion_chain(self, *, input_kwh: str, efficiencies: str | Iterable[str], labels: str | Iterable[str] = "") -> dict[str, Any]:
        start = self._decimal(input_kwh, label="input_kwh", allow_zero=False)
        if isinstance(efficiencies, str):
            raw_efficiencies = [x.strip() for x in efficiencies.split(",") if x.strip()]
        else:
            raw_efficiencies = [str(x).strip() for x in efficiencies if str(x).strip()]
        if not raw_efficiencies:
            raise ValueError("at least one stage efficiency is required")
        if len(raw_efficiencies) > 20:
            raise ValueError("a conversion chain may contain at most 20 stages")
        effs = [self._percent(x, label=f"stage_efficiency_{i+1}") for i, x in enumerate(raw_efficiencies)]

        if isinstance(labels, str):
            stage_labels = [x.strip() for x in labels.split(",")]
        else:
            stage_labels = [str(x).strip() for x in labels]
        if len(stage_labels) > len(effs):
            stage_labels = stage_labels[: len(effs)]
        while len(stage_labels) < len(effs):
            stage_labels.append("")

        stages: list[dict[str, Any]] = []
        current = start
        with localcontext() as ctx:
            ctx.prec = 40
            for idx, eff in enumerate(effs, start=1):
                output = current * eff / Decimal("100")
                loss = current - output
                stages.append({
                    "stage": idx,
                    "label": stage_labels[idx - 1] or f"Stage {idx}",
                    "input_kwh": self._decimal_text(current),
                    "efficiency_pct": self._decimal_text(eff),
                    "output_kwh": self._decimal_text(output),
                    "loss_kwh": self._decimal_text(loss),
                })
                current = output
            total_loss = start - current
            overall = current / start * Decimal("100")

        return {
            "ok": True,
            "schema": "sc-energy-conversion-chain-result/1.0",
            "model_version": MODEL_VERSION,
            "model_key": "conversion-chain",
            "input": {
                "input_kwh": self._decimal_text(start),
                "stage_efficiencies_pct": [self._decimal_text(x) for x in effs],
                "stage_labels": [x["label"] for x in stages],
            },
            "stages": stages,
            "output": {
                "final_output_kwh": self._decimal_text(current),
                "total_loss_kwh": self._decimal_text(total_loss),
                "overall_efficiency_pct": self._decimal_text(overall),
            },
            "provenance": {
                "input_origin": "explicit-user-supplied-scenario-input",
                "technology_performance_inferred": False,
                "source_keys": ["ucd-module-sustainable-energy", "mcdonnell-lecture-1"],
            },
            "boundary": self._models["conversion-chain"].boundary,
        }

    def supply_demand_balance(
        self,
        *,
        domestic_supply_kwh: str = "0",
        imports_kwh: str = "0",
        storage_discharge_kwh: str = "0",
        final_demand_kwh: str = "0",
        exports_kwh: str = "0",
        storage_charge_kwh: str = "0",
        losses_kwh: str = "0",
        tolerance_kwh: str = "0.001",
    ) -> dict[str, Any]:
        fields = {
            "domestic_supply_kwh": domestic_supply_kwh,
            "imports_kwh": imports_kwh,
            "storage_discharge_kwh": storage_discharge_kwh,
            "final_demand_kwh": final_demand_kwh,
            "exports_kwh": exports_kwh,
            "storage_charge_kwh": storage_charge_kwh,
            "losses_kwh": losses_kwh,
            "tolerance_kwh": tolerance_kwh,
        }
        d = {key: self._decimal(value, label=key) for key, value in fields.items()}
        with localcontext() as ctx:
            ctx.prec = 40
            available = d["domestic_supply_kwh"] + d["imports_kwh"] + d["storage_discharge_kwh"]
            outflows = d["final_demand_kwh"] + d["exports_kwh"] + d["storage_charge_kwh"] + d["losses_kwh"]
            residual = available - outflows
            residual_pct = (residual / available * Decimal("100")) if available else Decimal("0")
            balanced = abs(residual) <= d["tolerance_kwh"]

        return {
            "ok": True,
            "schema": "sc-energy-supply-demand-balance-result/1.0",
            "model_version": MODEL_VERSION,
            "model_key": "supply-demand-balance",
            "input": {key: self._decimal_text(value) for key, value in d.items()},
            "output": {
                "available_supply_kwh": self._decimal_text(available),
                "accounted_outflows_kwh": self._decimal_text(outflows),
                "residual_kwh": self._decimal_text(residual),
                "residual_pct_of_supply": self._decimal_text(residual_pct),
                "balanced": balanced,
            },
            "accounting": {
                "supply_components": ["domestic_supply_kwh", "imports_kwh", "storage_discharge_kwh"],
                "outflow_components": ["final_demand_kwh", "exports_kwh", "storage_charge_kwh", "losses_kwh"],
                "residual_sign": "positive = unallocated surplus; negative = unaccounted deficit",
            },
            "provenance": {
                "input_origin": "explicit-user-supplied-scenario-input",
                "source_keys": ["ucd-module-sustainable-energy"],
            },
            "boundary": self._models["supply-demand-balance"].boundary,
        }

    def generation_estimate(self, *, capacity_kw: str, capacity_factor_pct: str, hours: str = "8760") -> dict[str, Any]:
        capacity = self._decimal(capacity_kw, label="capacity_kw", allow_zero=False)
        capacity_factor = self._percent(capacity_factor_pct, label="capacity_factor_pct")
        hours_value = self._decimal(hours, label="hours", allow_zero=False)
        with localcontext() as ctx:
            ctx.prec = 40
            generation = capacity * hours_value * capacity_factor / Decimal("100")
            average_output = capacity * capacity_factor / Decimal("100")
        return {
            "ok": True,
            "schema": "sc-energy-generation-estimate-result/1.0",
            "model_version": MODEL_VERSION,
            "model_key": "capacity-factor-generation",
            "input": {
                "capacity_kw": self._decimal_text(capacity),
                "capacity_factor_pct": self._decimal_text(capacity_factor),
                "hours": self._decimal_text(hours_value),
            },
            "output": {
                "generation_kwh": self._decimal_text(generation),
                "average_output_kw": self._decimal_text(average_output),
            },
            "provenance": {
                "input_origin": "explicit-user-supplied-scenario-input",
                "capacity_factor_inferred": False,
                "source_keys": ["ucd-module-sustainable-energy"],
            },
            "boundary": self._models["capacity-factor-generation"].boundary,
        }

    def scenario_template(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-balance-scenario/1.0",
            "model_version": MODEL_VERSION,
            "contract": {
                "required_structure": [
                    "scenario_id", "name", "geography", "period", "energy_unit",
                    "supply", "conversion", "demand", "accounting", "evidence", "uncertainty",
                ],
                "canonical_unit": "kwh",
                "technology_references": list(self._technology_keys),
                "persistence_status": "not-implemented",
                "optimization_status": "not-implemented",
                "ranking_status": "not-implemented",
                "provenance_required": True,
            },
            "template": {
                "scenario_id": None,
                "name": None,
                "geography": None,
                "period": {"start": None, "end": None, "hours": None},
                "energy_unit": "kwh",
                "supply": {
                    "domestic_supply_kwh": None,
                    "imports_kwh": None,
                    "storage_discharge_kwh": None,
                    "technology_entries": [
                        {"technology_key": None, "capacity_kw": None, "capacity_factor_pct": None, "hours": None, "generation_kwh": None, "evidence_refs": []}
                    ],
                },
                "conversion": {
                    "chains": [
                        {"name": None, "input_kwh": None, "stages": [{"label": None, "efficiency_pct": None}], "result_ref": None}
                    ],
                    "losses_kwh": None,
                },
                "demand": {
                    "final_demand_kwh": None,
                    "exports_kwh": None,
                    "storage_charge_kwh": None,
                    "end_use_breakdown": [],
                },
                "accounting": {
                    "tolerance_kwh": "0.001",
                    "available_supply_kwh": None,
                    "accounted_outflows_kwh": None,
                    "residual_kwh": None,
                    "balanced": None,
                },
                "evidence": {
                    "source_refs": [],
                    "methodology_refs": [],
                    "factor_refs": [],
                    "resource_observation_refs": [],
                    "indicator_observation_refs": [],
                },
                "uncertainty": {
                    "assumptions": [],
                    "sensitivity_parameters": [],
                    "quality_notes": [],
                },
            },
            "guardrails": self.guardrails(),
        }

    def export(self) -> dict[str, Any]:
        return {
            "schema": "sc-energy-balance-model-export/1.0",
            "version": MODEL_VERSION,
            "framework": self.framework(),
            "scenario_template": self.scenario_template(),
        }
