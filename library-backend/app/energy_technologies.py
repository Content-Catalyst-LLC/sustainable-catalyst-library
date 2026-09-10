from __future__ import annotations

from dataclasses import asdict, dataclass
from hashlib import sha256
import json
from typing import Any


REGISTRY_VERSION = "0.4.0"
SCHEMA_VERSION = "sc-energy-renewable-technology-resource-model/1.0"


@dataclass(frozen=True)
class RenewableTechnologyDefinition:
    key: str
    label: str
    family: str
    resource_class: str
    primary_outputs: tuple[str, ...]
    conversion_scope: str
    related_concepts: tuple[str, ...]
    source_keys: tuple[str, ...]
    evidence_status: str
    quantitative_profile_status: str
    suitability_status: str
    note: str


@dataclass(frozen=True)
class RenewableResourceClass:
    key: str
    label: str
    resource_kind: str
    applicable_technologies: tuple[str, ...]
    observation_fields: tuple[str, ...]
    source_keys: tuple[str, ...]
    quantitative_dataset_status: str
    suitability_status: str
    note: str


class RenewableTechnologyResourceRegistry:
    """Governed renewable technology and resource-potential object model.

    The supplied module material names the renewable technology families and
    requires resource estimation/evaluation, underlying physical/technological
    principles, future prospects, efficiency analysis, and comparison of energy
    choices. It does not supply a current technology-performance database,
    resource-potential dataset, maturity ranking, or universal suitability rules.
    v0.4.0 therefore activates typed objects and evidence/observation contracts
    while keeping quantitative profiles and ranking disabled.
    """

    def __init__(self) -> None:
        self._technologies = self._build_technologies()
        self._resources = self._build_resources()
        self._validate()
        self._fingerprint = self._content_fingerprint()

    @staticmethod
    def _build_technologies() -> dict[str, RenewableTechnologyDefinition]:
        T = RenewableTechnologyDefinition
        rows = [
            T(
                "solar-photovoltaic",
                "Solar photovoltaic",
                "solar",
                "solar-resource",
                ("electricity",),
                "Photovoltaic conversion of solar radiation to electrical output.",
                ("renewable-energy", "solar-energy", "solar-photovoltaics", "renewable-resource-potential", "renewable-technology-prospects"),
                ("ucd-module-sustainable-energy", "mcdonnell-lecture-1"),
                "module-scope-plus-normalized-principle",
                "not-populated",
                "not-assessed",
                "v0.4.0 structures the technology family and evidence boundary; it does not assign efficiency, capacity factor, cost, lifecycle emissions, maturity, or site suitability.",
            ),
            T(
                "solar-thermal",
                "Solar thermal",
                "solar",
                "solar-resource",
                ("heat",),
                "Capture of solar energy as useful heat or as thermal input to a downstream conversion process.",
                ("renewable-energy", "solar-energy", "solar-thermal", "renewable-resource-potential", "renewable-technology-prospects"),
                ("ucd-module-sustainable-energy", "mcdonnell-lecture-1"),
                "module-scope-plus-normalized-principle",
                "not-populated",
                "not-assessed",
                "The object intentionally leaves technology configuration and performance values to future source-backed profiles.",
            ),
            T(
                "wind-energy",
                "Wind energy",
                "wind",
                "wind-resource",
                ("electricity",),
                "Conversion of the kinetic energy of moving air to mechanical and typically electrical output.",
                ("renewable-energy", "wind-energy", "renewable-resource-potential", "renewable-technology-prospects"),
                ("ucd-module-sustainable-energy",),
                "module-scope-plus-normalized-principle",
                "not-populated",
                "not-assessed",
                "Onshore/offshore distinctions, turbine classes, capacity factors, costs, and grid effects are not inferred in this release.",
            ),
            T(
                "hydropower",
                "Hydropower",
                "hydro",
                "hydrological-resource",
                ("electricity",),
                "Conversion of gravitational and/or kinetic energy in water flows to useful power.",
                ("renewable-energy", "hydropower", "renewable-resource-potential", "renewable-technology-prospects"),
                ("ucd-module-sustainable-energy",),
                "module-scope-plus-normalized-principle",
                "not-populated",
                "not-assessed",
                "Run-of-river, reservoir, pumped-storage, ecological, and hydrological distinctions require explicit evidence in later releases.",
            ),
            T(
                "tidal-energy",
                "Tidal energy",
                "marine",
                "tidal-resource",
                ("electricity",),
                "Conversion of tidal range and/or tidal-current motion to useful power.",
                ("renewable-energy", "tidal-energy", "renewable-resource-potential", "renewable-technology-prospects"),
                ("ucd-module-sustainable-energy",),
                "module-scope-plus-normalized-principle",
                "not-populated",
                "not-assessed",
                "Device type, marine impacts, resource magnitude, cost, and project feasibility are not assigned by the foundation.",
            ),
            T(
                "wave-energy",
                "Wave energy",
                "marine",
                "wave-resource",
                ("electricity",),
                "Conversion of wave motion and wave-energy flux to useful power.",
                ("renewable-energy", "wave-energy", "renewable-resource-potential", "renewable-technology-prospects"),
                ("ucd-module-sustainable-energy",),
                "module-scope-plus-normalized-principle",
                "not-populated",
                "not-assessed",
                "Device architecture, survivability, resource magnitude, maturity, cost, and environmental performance require source-backed profiles.",
            ),
            T(
                "bioenergy",
                "Bioenergy",
                "bioenergy",
                "biomass-resource",
                ("heat", "electricity", "fuels"),
                "Conversion of biological feedstocks through biological, thermal, or chemical pathways to useful energy carriers or services.",
                ("renewable-energy", "bioenergy", "biomass", "anaerobic-digestion", "biomass-to-oil", "renewable-resource-potential", "renewable-technology-prospects"),
                ("ucd-module-sustainable-energy",),
                "module-scope-plus-normalized-principle",
                "not-populated",
                "not-assessed",
                "Feedstock sustainability, land-use effects, carbon accounting, conversion route, digestate/biochar co-products, and lifecycle performance remain explicit evidence questions rather than assumed benefits.",
            ),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_resources() -> dict[str, RenewableResourceClass]:
        common = (
            "geography",
            "period",
            "resource_metric",
            "value",
            "unit",
            "spatial_resolution",
            "temporal_resolution",
            "measurement_or_model_method",
            "data_source",
            "source_vintage",
            "uncertainty_or_quality_note",
            "constraints_note",
            "provenance_reference",
        )
        R = RenewableResourceClass
        rows = [
            R("solar-resource", "Solar resource", "radiative", ("solar-photovoltaic", "solar-thermal"), common, ("ucd-module-sustainable-energy",), "not-loaded", "not-assessed", "A resource observation may describe solar availability, but v0.4.0 does not select a universal metric or dataset."),
            R("wind-resource", "Wind resource", "atmospheric", ("wind-energy",), common, ("ucd-module-sustainable-energy",), "not-loaded", "not-assessed", "Wind-resource observations require explicit height, method, period, and geography before comparison or project use."),
            R("hydrological-resource", "Hydrological resource", "freshwater", ("hydropower",), common, ("ucd-module-sustainable-energy",), "not-loaded", "not-assessed", "Hydropower resource characterization requires explicit hydrological and site methodology; no flow/head assumptions are fabricated."),
            R("tidal-resource", "Tidal resource", "marine", ("tidal-energy",), common, ("ucd-module-sustainable-energy",), "not-loaded", "not-assessed", "Tidal range/current resource observations must identify the selected metric, location, period, and method."),
            R("wave-resource", "Wave resource", "marine", ("wave-energy",), common, ("ucd-module-sustainable-energy",), "not-loaded", "not-assessed", "Wave-resource observations must preserve the resource metric, location, time period, and data/model provenance."),
            R("biomass-resource", "Biomass feedstock resource", "biological", ("bioenergy",), common + ("feedstock_type", "feedstock_origin", "competing_use_note", "land_use_note"), ("ucd-module-sustainable-energy",), "not-loaded", "not-assessed", "Biomass quantity alone is not a sustainability determination; origin, competing uses, land/ecosystem context, and carbon-accounting boundaries remain required evidence."),
        ]
        return {row.key: row for row in rows}

    def _validate(self) -> None:
        if len(self._technologies) != 7:
            raise ValueError("Renewable technology registry must contain the seven module technology families")
        if len(self._resources) != 6:
            raise ValueError("Renewable resource registry must contain six normalized resource classes")
        technology_keys = set(self._technologies)
        resource_keys = set(self._resources)
        for technology in self._technologies.values():
            if technology.resource_class not in resource_keys:
                raise ValueError(f"Unknown resource class for {technology.key}")
            if technology.quantitative_profile_status != "not-populated" or technology.suitability_status != "not-assessed":
                raise ValueError("v0.4.0 must not claim populated performance profiles or suitability determinations")
        for resource in self._resources.values():
            if not set(resource.applicable_technologies).issubset(technology_keys):
                raise ValueError(f"Unknown technology reference in {resource.key}")
            if resource.quantitative_dataset_status != "not-loaded" or resource.suitability_status != "not-assessed":
                raise ValueError("v0.4.0 must not claim a live quantitative resource dataset or suitability determination")

    def _content_fingerprint(self) -> str:
        content = {
            "version": REGISTRY_VERSION,
            "technologies": [asdict(v) for v in self._technologies.values()],
            "resources": [asdict(v) for v in self._resources.values()],
        }
        return sha256(json.dumps(content, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    def framework(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "version": REGISTRY_VERSION,
            "counts": {
                "technologies": len(self._technologies),
                "technology_families": len({v.family for v in self._technologies.values()}),
                "resource_classes": len(self._resources),
                "technology_assessment_contracts": len(self._technologies),
                "resource_observation_contracts": len(self._resources),
            },
            "families": [
                {"key": family, "technology_keys": [v.key for v in self._technologies.values() if v.family == family]}
                for family in sorted({v.family for v in self._technologies.values()})
            ],
            "guardrails": {
                "technology_object_is_not_site_suitability": True,
                "resource_observation_is_not_technical_potential": True,
                "technical_potential_is_not_economic_potential": True,
                "renewable_classification_is_not_zero_impact_claim": True,
                "quantitative_technology_profiles_loaded": False,
                "live_resource_datasets_loaded": False,
                "automatic_technology_ranking": False,
            },
            "source_boundary": "The supplied module material establishes the technology families and the need for resource, principle, prospect, efficiency, and scenario analysis. It does not supply a current quantitative technology-performance or resource-potential database, so v0.4.0 activates object and evidence contracts rather than populated rankings.",
            "content_fingerprint": self._fingerprint,
        }

    def technologies(self, *, q: str = "", family: str = "", output: str = "", resource_class: str = "", limit: int = 100) -> dict[str, Any]:
        query = q.strip().lower()
        rows: list[dict[str, Any]] = []
        for technology in self._technologies.values():
            if family and technology.family != family:
                continue
            if output and output not in technology.primary_outputs:
                continue
            if resource_class and technology.resource_class != resource_class:
                continue
            haystack = " ".join((technology.key, technology.label, technology.family, technology.resource_class, technology.conversion_scope, technology.note, *technology.primary_outputs, *technology.related_concepts)).lower()
            if query and query not in haystack:
                continue
            rows.append(asdict(technology))
            if len(rows) >= max(1, min(limit, 100)):
                break
        return {
            "ok": True,
            "schema": "sc-energy-renewable-technologies/1.0",
            "count": len(rows),
            "items": rows,
            "guardrail": "A technology object describes a governed class. It is not a performance value, site recommendation, ranking, or sustainability conclusion.",
        }

    def technology(self, key: str) -> dict[str, Any]:
        technology = self._technologies.get(key)
        if technology is None:
            raise KeyError(key)
        resource = self._resources[technology.resource_class]
        return {
            "ok": True,
            "schema": "sc-energy-renewable-technology/1.0",
            "technology": asdict(technology),
            "resource_class": asdict(resource),
            "assessment_contract": self._technology_assessment_contract(technology),
            "content_fingerprint": self._fingerprint,
        }

    def resource_classes(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-renewable-resource-classes/1.0",
            "count": len(self._resources),
            "items": [asdict(v) for v in self._resources.values()],
            "guardrail": "Resource classes define observation structure only; no current resource-potential values are loaded in v0.4.0.",
        }

    def resource_class(self, key: str) -> dict[str, Any]:
        resource = self._resources.get(key)
        if resource is None:
            raise KeyError(key)
        return {
            "ok": True,
            "schema": "sc-energy-renewable-resource-class/1.0",
            "resource_class": asdict(resource),
            "observation_contract": self._resource_observation_contract(resource),
            "content_fingerprint": self._fingerprint,
        }

    @staticmethod
    def _technology_assessment_contract(technology: RenewableTechnologyDefinition) -> dict[str, Any]:
        fields = (
            "geography",
            "period",
            "technology_key",
            "technology_configuration",
            "resource_class",
            "resource_observation_reference",
            "conversion_efficiency",
            "capacity_factor",
            "annual_energy_output",
            "capital_cost",
            "operating_cost",
            "lifecycle_emissions",
            "land_requirement",
            "water_requirement",
            "reliability_or_variability_note",
            "environmental_constraints",
            "social_or_institutional_constraints",
            "grid_or_system_context",
            "methodology_reference",
            "data_sources",
            "assumptions",
            "uncertainty_or_quality_note",
        )
        return {
            "schema": "sc-energy-renewable-technology-assessment/1.0",
            "technology_key": technology.key,
            "required_structure": list(fields),
            "quantitative_profile_status": technology.quantitative_profile_status,
            "suitability_status": technology.suitability_status,
            "provenance_required": True,
            "methodology_required": True,
            "comparison_requirements": [
                "same or explicitly reconciled system boundary",
                "compatible geography and resource period",
                "compatible technology configuration and output basis",
                "compatible cost year/currency basis when economics are used",
                "explicit emissions and lifecycle boundary when carbon is compared",
                "documented uncertainty, source vintage, and methodology",
            ],
            "guardrail": "The contract structures evidence needed for assessment. It does not calculate suitability, technology rank, economic viability, lifecycle impact, or project feasibility.",
        }

    def technology_assessment_template(self, key: str) -> dict[str, Any]:
        technology = self._technologies.get(key)
        if technology is None:
            raise KeyError(key)
        contract = self._technology_assessment_contract(technology)
        template = {field: None for field in contract["required_structure"]}
        template.update({"technology_key": technology.key, "resource_class": technology.resource_class})
        return {"ok": True, "technology": asdict(technology), "contract": contract, "template": template}

    @staticmethod
    def _resource_observation_contract(resource: RenewableResourceClass) -> dict[str, Any]:
        return {
            "schema": "sc-energy-renewable-resource-observation/1.0",
            "resource_class": resource.key,
            "required_structure": list(resource.observation_fields),
            "quantitative_dataset_status": resource.quantitative_dataset_status,
            "suitability_status": resource.suitability_status,
            "provenance_required": True,
            "methodology_required": True,
            "guardrail": "A resource observation is evidence input. It is not automatically gross, technical, economic, or sustainable potential and cannot by itself establish project suitability.",
        }

    def resource_observation_template(self, key: str) -> dict[str, Any]:
        resource = self._resources.get(key)
        if resource is None:
            raise KeyError(key)
        contract = self._resource_observation_contract(resource)
        template = {field: None for field in resource.observation_fields}
        template.update({"resource_class": resource.key})
        return {"ok": True, "resource_class": asdict(resource), "contract": contract, "template": template}

    def comparison_template(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-renewable-technology-comparison/1.0",
            "version": REGISTRY_VERSION,
            "candidate_technology_keys": list(self._technologies),
            "dimensions": [
                "resource availability",
                "conversion performance",
                "energy output",
                "cost",
                "lifecycle emissions",
                "land and water",
                "reliability or variability",
                "environmental constraints",
                "social or institutional constraints",
                "system or grid context",
                "uncertainty and provenance",
            ],
            "values_loaded": False,
            "ranking_enabled": False,
            "guardrail": "This is a comparison schema only. v0.4.0 does not populate universal scores or choose a preferred renewable technology.",
        }

    def export(self) -> dict[str, Any]:
        return {
            "schema": "sc-energy-renewable-technology-resource-export/1.0",
            "version": REGISTRY_VERSION,
            "technologies": [asdict(v) for v in self._technologies.values()],
            "resource_classes": [asdict(v) for v in self._resources.values()],
            "comparison_template": self.comparison_template(),
            "content_fingerprint": self._fingerprint,
        }
