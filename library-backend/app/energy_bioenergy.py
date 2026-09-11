from __future__ import annotations

from dataclasses import asdict, dataclass
from decimal import Decimal, InvalidOperation, localcontext
from hashlib import sha256
import json
from typing import Any

from .carbon_nature import CONCEPTS as CARBON_NATURE_CONCEPTS, METHODOLOGIES as CARBON_NATURE_METHODOLOGIES

MODEL_VERSION = "0.7.0"
SCHEMA_VERSION = "sc-energy-biological-carbon-bioenergy/1.0"


@dataclass(frozen=True)
class BioenergyFeedstockClass:
    key: str
    label: str
    physical_form: str
    moisture_context: str
    applicable_pathways: tuple[str, ...]
    required_characterization: tuple[str, ...]
    carbon_nature_context: tuple[str, ...]
    source_keys: tuple[str, ...]
    status: str
    note: str


@dataclass(frozen=True)
class BioenergyPathwayDefinition:
    key: str
    label: str
    pathway_family: str
    input_classes: tuple[str, ...]
    primary_outputs: tuple[str, ...]
    coproducts: tuple[str, ...]
    related_energy_concepts: tuple[str, ...]
    carbon_nature_targets: tuple[str, ...]
    source_keys: tuple[str, ...]
    quantitative_profile_status: str
    lifecycle_accounting_status: str
    note: str


@dataclass(frozen=True)
class BiologicalCarbonBridge:
    key: str
    energy_concepts: tuple[str, ...]
    carbon_nature_concepts: tuple[str, ...]
    carbon_nature_methodologies: tuple[str, ...]
    purpose: str
    boundary: str
    status: str = "cross-domain-contract-available"


class BiologicalCarbonBioenergyIntegration:
    """Governed Energy Systems ↔ Carbon & Nature bioenergy integration.

    The supplied Sustainable Energy module explicitly places soil carbon, CO2-to-energy,
    forests/forest ecology, digestate from anaerobic digestion, biochar, and biomass-to-oil
    within its Biological Carbon Capture/Storage scope and separately includes bioenergy in
    its renewable-energy coverage. The source material does not supply universal yields,
    heating values, carbon-storage fractions, lifecycle factors, avoided-emissions factors,
    or project-credit rules. v0.7.0 therefore activates typed pathway/feedstock/bridge
    contracts and bounded arithmetic only when all quantitative assumptions are explicit.
    """

    def __init__(self) -> None:
        self._feedstocks = self._build_feedstocks()
        self._pathways = self._build_pathways()
        self._bridges = self._build_bridges()
        self._validate()
        self._fingerprint = self._content_fingerprint()

    @staticmethod
    def _build_feedstocks() -> dict[str, BioenergyFeedstockClass]:
        F = BioenergyFeedstockClass
        rows = [
            F(
                "biomass-generic", "Biomass — generic", "variable", "must-be-declared",
                ("biomass-energy-conversion", "biochar-production", "biomass-to-oil"),
                ("feedstock identity", "mass basis", "moisture basis", "energy content basis", "origin", "competing uses", "land-use context", "source/provenance"),
                ("aboveground-biomass", "belowground-biomass", "forest-woodland"),
                ("ucd-module-sustainable-energy",), "evidence-required",
                "Generic biomass is a transport object, not a sustainability classification. Feedstock origin, moisture, land-use context, competing uses, and accounting boundary remain explicit evidence questions.",
            ),
            F(
                "wet-organic-biomass", "Wet organic biomass", "wet-organic", "explicit-moisture-or-solids-basis",
                ("anaerobic-digestion-biogas",),
                ("feedstock identity", "wet mass or dry-solids basis", "moisture/solids content", "biogas-yield basis", "origin", "source/provenance"),
                ("methane", "nitrous-oxide"),
                ("ucd-module-sustainable-energy",), "evidence-required",
                "This normalized implementation category supports anaerobic-digestion scenario accounting; the supplied module does not define a universal feedstock taxonomy or biogas yield.",
            ),
            F(
                "woody-biomass", "Woody biomass", "solid-biological", "must-be-declared",
                ("biomass-energy-conversion", "biochar-production", "biomass-to-oil"),
                ("biomass source", "forest/land-use context", "mass basis", "moisture basis", "energy content basis", "harvest/residue status", "source/provenance"),
                ("forest-woodland", "aboveground-biomass", "belowground-biomass", "dead-wood", "litter"),
                ("ucd-module-sustainable-energy",), "evidence-required",
                "Forest or woody origin does not establish renewable, carbon-neutral, additional, or sustainable status; land, biomass-pool, harvest, displacement, and regrowth boundaries remain explicit.",
            ),
            F(
                "agricultural-biomass", "Agricultural biomass", "solid-or-wet-biological", "must-be-declared",
                ("biomass-energy-conversion", "anaerobic-digestion-biogas", "biochar-production", "biomass-to-oil"),
                ("feedstock identity", "production/residue status", "mass basis", "moisture basis", "energy or yield basis", "cropland context", "competing uses", "source/provenance"),
                ("cropland", "soil-organic-carbon", "methane", "nitrous-oxide"),
                ("ucd-module-sustainable-energy",), "evidence-required",
                "Agricultural biomass may interact with residue, soil-carbon, nutrient, methane, nitrous-oxide, and displacement accounting; no benefit is inferred from the category alone.",
            ),
            F(
                "organic-waste-biomass", "Organic waste biomass", "variable-organic", "must-be-declared",
                ("anaerobic-digestion-biogas", "biomass-energy-conversion"),
                ("waste identity", "mass basis", "moisture/solids basis", "baseline management", "energy/yield basis", "contaminants/quality context", "source/provenance"),
                ("methane", "nitrous-oxide"),
                ("ucd-module-sustainable-energy",), "evidence-required",
                "Waste classification does not by itself establish avoided emissions; a baseline/counterfactual and whole-system accounting boundary are required.",
            ),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_pathways() -> dict[str, BioenergyPathwayDefinition]:
        P = BioenergyPathwayDefinition
        rows = [
            P(
                "anaerobic-digestion-biogas", "Anaerobic digestion → biogas", "biological-conversion",
                ("wet-organic-biomass", "agricultural-biomass", "organic-waste-biomass"),
                ("biogas", "energy"), ("digestate",),
                ("anaerobic-digestion", "digestate", "bioenergy", "biomass"),
                ("methane", "nitrous-oxide", "soil-organic-carbon"),
                ("ucd-module-sustainable-energy",), "explicit-assumptions-required", "whole-system-accounting-required",
                "The module explicitly names anaerobic digestion and digestate. v0.7.0 does not supply default biogas yield, methane fraction, heating value, conversion efficiency, digestate benefit, or avoided-methane factor.",
            ),
            P(
                "digestate-management", "Digestate management", "bioenergy-coproduct-management",
                ("wet-organic-biomass",), ("managed-digestate",), (),
                ("digestate", "anaerobic-digestion", "soil-carbon"),
                ("soil-organic-carbon", "methane", "nitrous-oxide"),
                ("ucd-module-sustainable-energy",), "no-universal-profile", "whole-system-accounting-required",
                "Digestate is represented as a governed co-product pathway. Soil-carbon, nutrient, methane, nitrous-oxide, substitution, transport, storage, and application effects must be evidenced rather than assumed.",
            ),
            P(
                "biochar-production", "Biochar production", "biomass-carbon-pathway",
                ("biomass-generic", "woody-biomass", "agricultural-biomass"),
                ("biochar",), ("energy-or-gaseous-liquid-coproducts-if-declared",),
                ("biochar", "biomass", "biological-carbon-capture-storage"),
                ("soil-organic-carbon", "carbon-dioxide"),
                ("ucd-module-sustainable-energy",), "explicit-assumptions-required", "whole-system-accounting-required",
                "The module explicitly includes biochar but does not provide a production technology, carbon fraction, stable fraction, soil response, permanence rule, lifecycle factor, or crediting methodology. Those remain explicit inputs/evidence.",
            ),
            P(
                "biomass-to-oil", "Biomass to oil", "biomass-conversion",
                ("biomass-generic", "woody-biomass", "agricultural-biomass"),
                ("oil-like-energy-product",), ("conversion-coproducts-if-declared",),
                ("biomass-to-oil", "biomass", "bioenergy"),
                ("aboveground-biomass", "forest-woodland", "soil-organic-carbon"),
                ("ucd-module-sustainable-energy",), "explicit-assumptions-required", "whole-system-accounting-required",
                "The supplied module names biomass-to-oil but gives no universal yield, product heating value, conversion route, lifecycle-emissions factor, or land-use result.",
            ),
            P(
                "biomass-energy-conversion", "Biomass → useful energy", "generic-energy-conversion",
                ("biomass-generic", "woody-biomass", "agricultural-biomass", "organic-waste-biomass"),
                ("heat", "electricity", "fuel"), (),
                ("bioenergy", "biomass", "energy-conversion", "energy-efficiency"),
                ("aboveground-biomass", "forest-woodland", "soil-organic-carbon", "carbon-dioxide"),
                ("ucd-module-sustainable-energy",), "explicit-assumptions-required", "whole-system-accounting-required",
                "This normalized generic pathway links the module's bioenergy coverage to the existing energy-balance model. Biomass combustion or conversion is not assumed carbon neutral.",
            ),
            P(
                "co2-to-energy", "CO₂ to energy", "carbon-utilization-energy",
                (), ("energy-carrier-or-fuel-if-specified",), (),
                ("co2-to-energy", "biological-carbon-capture-storage", "energy-conversion"),
                ("carbon-dioxide",),
                ("ucd-module-sustainable-energy",), "technology-and-inputs-required", "whole-system-accounting-required",
                "CO₂-to-energy is explicitly named in the module scope. v0.7.0 records the pathway but does not assume a technology, energy source, conversion efficiency, permanence, or net climate benefit.",
            ),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_bridges() -> dict[str, BiologicalCarbonBridge]:
        B = BiologicalCarbonBridge
        rows = [
            B(
                "soil-carbon-bridge", ("soil-carbon",), ("soil-organic-carbon",), ("whole-system-ghg-accounting",),
                "Resolve the Energy Systems soil-carbon concept to the governed Carbon & Nature soil-organic-carbon pool and accounting context.",
                "Semantic/accounting bridge only; no SOC stock change, sequestration rate, additionality, permanence, or credit eligibility is inferred.",
            ),
            B(
                "forest-carbon-bridge", ("forest-carbon", "forest-ecology", "biomass"), ("forest-woodland", "aboveground-biomass", "belowground-biomass", "dead-wood", "litter"), ("biomass-inventory-measurement", "whole-system-ghg-accounting"),
                "Connect forest/biomass energy questions to Carbon & Nature land-system and biomass-pool objects.",
                "Forest biomass used for energy is not presumed renewable or carbon neutral; harvest, regrowth, displacement, leakage, stocks, time horizon, and system boundaries must be explicit.",
            ),
            B(
                "anaerobic-digestion-digestate-bridge", ("anaerobic-digestion", "digestate"), ("methane", "nitrous-oxide", "soil-organic-carbon"), ("whole-system-ghg-accounting",),
                "Connect anaerobic-digestion energy and digestate questions to gas, soil-carbon, and whole-system Carbon & Nature accounting.",
                "No avoided-methane, fertilizer-substitution, soil-carbon, or digestate climate benefit is inferred without an explicit baseline and evidence.",
            ),
            B(
                "biochar-bridge", ("biochar", "biomass"), ("soil-organic-carbon", "carbon-dioxide"), ("whole-system-ghg-accounting",),
                "Provide a governed cross-domain context for biochar carbon accounting using existing Carbon & Nature pools and GHG accounting methods.",
                "A stoichiometric carbon-to-CO2e estimate is not a permanence, additionality, lifecycle, soil-response, project-credit, or verified-removal determination.",
            ),
            B(
                "biomass-conversion-bridge", ("bioenergy", "biomass", "biomass-to-oil"), ("aboveground-biomass", "forest-woodland", "soil-organic-carbon", "carbon-dioxide"), ("whole-system-ghg-accounting",),
                "Link biomass conversion scenarios to source-pool, land, and whole-system greenhouse-gas accounting context.",
                "Energy output and carbon outcome are separate accounting questions; displaced activity, land-use effects, feedstock counterfactuals, and lifecycle boundaries must be stated.",
            ),
            B(
                "co2-to-energy-bridge", ("co2-to-energy",), ("carbon-dioxide",), ("whole-system-ghg-accounting",),
                "Link CO2-to-energy scenarios to explicit carbon-dioxide and whole-system accounting context.",
                "CO2 utilization does not by itself establish removal, storage, permanence, or net emissions reduction; energy input and downstream release must be included when relevant.",
            ),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _decimal(value: str, *, label: str, positive: bool = False, nonnegative: bool = False) -> Decimal:
        try:
            number = Decimal(str(value).strip())
        except (InvalidOperation, ValueError) as exc:
            raise ValueError(f"{label} must be a finite decimal") from exc
        if not number.is_finite():
            raise ValueError(f"{label} must be a finite decimal")
        if positive and number <= 0:
            raise ValueError(f"{label} must be greater than zero")
        if nonnegative and number < 0:
            raise ValueError(f"{label} must be zero or greater")
        return number

    @classmethod
    def _pct(cls, value: str, *, label: str, allow_zero: bool = True) -> Decimal:
        pct = cls._decimal(value, label=label, nonnegative=True)
        if pct > 100 or (not allow_zero and pct == 0):
            raise ValueError(f"{label} must be {'greater than zero and ' if not allow_zero else ''}at most 100")
        return pct

    @staticmethod
    def _text(value: Decimal | None) -> str | None:
        if value is None:
            return None
        normalized = value.normalize()
        if normalized == 0:
            return "0"
        text = format(normalized, "f")
        return text.rstrip("0").rstrip(".") if "." in text else text

    def _validate(self) -> None:
        if len(self._feedstocks) != 5 or len(self._pathways) != 6 or len(self._bridges) != 6:
            raise RuntimeError("Unexpected v0.7.0 biological-carbon/bioenergy registry size")
        for feedstock in self._feedstocks.values():
            for key in feedstock.applicable_pathways:
                if key not in self._pathways:
                    raise RuntimeError(f"Unknown pathway {key} in feedstock {feedstock.key}")
        for pathway in self._pathways.values():
            for key in pathway.input_classes:
                if key not in self._feedstocks:
                    raise RuntimeError(f"Unknown feedstock {key} in pathway {pathway.key}")
        carbon_concepts = {item.key for item in CARBON_NATURE_CONCEPTS}
        carbon_methods = {item.key for item in CARBON_NATURE_METHODOLOGIES}
        for bridge in self._bridges.values():
            missing_concepts = set(bridge.carbon_nature_concepts) - carbon_concepts
            missing_methods = set(bridge.carbon_nature_methodologies) - carbon_methods
            if missing_concepts or missing_methods:
                raise RuntimeError(f"Unresolved Carbon & Nature target in {bridge.key}: concepts={sorted(missing_concepts)} methods={sorted(missing_methods)}")

    def _content_fingerprint(self) -> str:
        payload = {
            "feedstocks": [asdict(x) for x in self._feedstocks.values()],
            "pathways": [asdict(x) for x in self._pathways.values()],
            "bridges": [asdict(x) for x in self._bridges.values()],
            "guardrails": self.guardrails(),
        }
        return sha256(json.dumps(payload, sort_keys=True, separators=(",", ":"), default=list).encode()).hexdigest()

    def guardrails(self) -> dict[str, Any]:
        return {
            "explicit_quantitative_inputs_required": True,
            "default_biogas_yield_loaded": False,
            "default_methane_fraction_loaded": False,
            "default_biomass_energy_content_loaded": False,
            "default_biochar_carbon_fraction_loaded": False,
            "default_biochar_stable_fraction_loaded": False,
            "default_biomass_to_oil_yield_loaded": False,
            "biomass_carbon_neutrality_assumed": False,
            "avoided_emissions_inferred": False,
            "digestate_climate_benefit_inferred": False,
            "soil_carbon_change_inferred": False,
            "forest_carbon_change_inferred": False,
            "lifecycle_emissions_inferred": False,
            "land_use_change_inferred": False,
            "additionality_determined": False,
            "permanence_determined": False,
            "leakage_determined": False,
            "carbon_credit_eligibility_determined": False,
            "biochar_co2e_estimate_is_stoichiometric_only": True,
            "cross_domain_carbon_nature_targets_validated": True,
        }

    def framework(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "version": MODEL_VERSION,
            "counts": {
                "feedstock_classes": len(self._feedstocks),
                "pathways": len(self._pathways),
                "carbon_nature_bridges": len(self._bridges),
                "executable_models": 4,
                "scenario_contracts": 1,
            },
            "capabilities": [
                "bioenergy-feedstock-class-registry",
                "bioenergy-pathway-registry",
                "energy-carbon-nature-cross-domain-bridge-registry",
                "explicit-input-feedstock-energy-estimate",
                "explicit-input-anaerobic-digestion-energy-estimate",
                "explicit-input-biochar-stoichiometric-carbon-estimate",
                "explicit-input-biomass-to-oil-energy-estimate",
                "bioenergy-carbon-scenario-contract",
            ],
            "source_boundary": "The module supplies pathway scope, not universal quantitative defaults. Arithmetic models are Sustainable Catalyst implementation formulas using explicit caller-supplied assumptions.",
            "guardrails": self.guardrails(),
            "content_fingerprint": self._fingerprint,
        }

    def feedstocks(self, *, q: str = "", limit: int = 100) -> dict[str, Any]:
        needle = q.strip().casefold()
        rows: list[dict[str, Any]] = []
        for item in self._feedstocks.values():
            haystack = " ".join((item.key, item.label, item.physical_form, item.moisture_context, item.note, *item.carbon_nature_context)).casefold()
            if needle and needle not in haystack:
                continue
            row = asdict(item)
            for field in ("applicable_pathways", "required_characterization", "carbon_nature_context", "source_keys"):
                row[field] = list(row[field])
            rows.append(row)
            if len(rows) >= max(1, min(limit, 100)):
                break
        return {"ok": True, "schema": "sc-energy-bioenergy-feedstocks/1.0", "version": MODEL_VERSION, "count": len(rows), "items": rows, "guardrail": "Feedstock class is not a sustainability, carbon-neutrality, or project-suitability determination."}

    def pathways(self, *, q: str = "", family: str = "", limit: int = 100) -> dict[str, Any]:
        needle, fam = q.strip().casefold(), family.strip().casefold()
        rows: list[dict[str, Any]] = []
        for item in self._pathways.values():
            if fam and item.pathway_family.casefold() != fam:
                continue
            haystack = " ".join((item.key, item.label, item.pathway_family, item.note, *item.primary_outputs, *item.coproducts, *item.related_energy_concepts)).casefold()
            if needle and needle not in haystack:
                continue
            row = asdict(item)
            for field in ("input_classes", "primary_outputs", "coproducts", "related_energy_concepts", "carbon_nature_targets", "source_keys"):
                row[field] = list(row[field])
            rows.append(row)
            if len(rows) >= max(1, min(limit, 100)):
                break
        return {"ok": True, "schema": "sc-energy-bioenergy-pathways/1.0", "version": MODEL_VERSION, "count": len(rows), "items": rows, "guardrail": "Pathway presence does not establish yield, lifecycle benefit, renewable status, or carbon-credit eligibility."}

    def pathway(self, key: str) -> dict[str, Any]:
        item = self._pathways.get(str(key).strip())
        if item is None:
            raise KeyError(key)
        row = asdict(item)
        for field in ("input_classes", "primary_outputs", "coproducts", "related_energy_concepts", "carbon_nature_targets", "source_keys"):
            row[field] = list(row[field])
        bridges = [asdict(x) for x in self._bridges.values() if set(x.energy_concepts) & set(item.related_energy_concepts)]
        return {"ok": True, "schema": "sc-energy-bioenergy-pathway/1.0", "version": MODEL_VERSION, "pathway": row, "carbon_nature_bridges": bridges}

    def carbon_nature_bridges(self) -> dict[str, Any]:
        rows = []
        for item in self._bridges.values():
            row = asdict(item)
            for field in ("energy_concepts", "carbon_nature_concepts", "carbon_nature_methodologies"):
                row[field] = list(row[field])
            rows.append(row)
        return {"ok": True, "schema": "sc-energy-carbon-nature-bridges/1.0", "version": MODEL_VERSION, "count": len(rows), "items": rows, "guardrail": "Cross-domain links resolve governed Carbon & Nature concepts/methodologies but do not transfer or infer quantitative carbon claims."}

    def feedstock_energy_estimate(self, *, mass_tonnes: str, energy_content_kwh_per_tonne: str, conversion_efficiency_pct: str = "100") -> dict[str, Any]:
        mass = self._decimal(mass_tonnes, label="mass_tonnes", nonnegative=True)
        energy_content = self._decimal(energy_content_kwh_per_tonne, label="energy_content_kwh_per_tonne", nonnegative=True)
        efficiency = self._pct(conversion_efficiency_pct, label="conversion_efficiency_pct")
        with localcontext() as ctx:
            ctx.prec = 40
            gross = mass * energy_content
            useful = gross * efficiency / Decimal("100")
            losses = gross - useful
        return {
            "ok": True, "schema": "sc-energy-feedstock-energy-result/1.0", "model_version": MODEL_VERSION,
            "input": {"mass_tonnes": self._text(mass), "energy_content_kwh_per_tonne": self._text(energy_content), "conversion_efficiency_pct": self._text(efficiency)},
            "output": {"gross_energy_kwh": self._text(gross), "useful_energy_kwh": self._text(useful), "conversion_loss_kwh": self._text(losses)},
            "provenance": {"assumption_origin": "explicit-user-supplied-scenario-input", "default_feedstock_heating_value_used": False, "default_efficiency_used": conversion_efficiency_pct == "100"},
            "boundary": "Energy arithmetic only. Feedstock sustainability, lifecycle emissions, land-use change, carbon neutrality, and avoided emissions are not inferred.",
        }

    def anaerobic_digestion_energy_estimate(self, *, feedstock_mass_tonnes: str, biogas_yield_m3_per_tonne: str, methane_fraction_pct: str, methane_energy_kwh_per_m3: str, conversion_efficiency_pct: str = "100") -> dict[str, Any]:
        mass = self._decimal(feedstock_mass_tonnes, label="feedstock_mass_tonnes", nonnegative=True)
        yield_factor = self._decimal(biogas_yield_m3_per_tonne, label="biogas_yield_m3_per_tonne", nonnegative=True)
        methane_fraction = self._pct(methane_fraction_pct, label="methane_fraction_pct")
        methane_energy = self._decimal(methane_energy_kwh_per_m3, label="methane_energy_kwh_per_m3", nonnegative=True)
        efficiency = self._pct(conversion_efficiency_pct, label="conversion_efficiency_pct")
        with localcontext() as ctx:
            ctx.prec = 40
            biogas = mass * yield_factor
            methane = biogas * methane_fraction / Decimal("100")
            gross = methane * methane_energy
            useful = gross * efficiency / Decimal("100")
        return {
            "ok": True, "schema": "sc-energy-anaerobic-digestion-energy-result/1.0", "model_version": MODEL_VERSION,
            "input": {"feedstock_mass_tonnes": self._text(mass), "biogas_yield_m3_per_tonne": self._text(yield_factor), "methane_fraction_pct": self._text(methane_fraction), "methane_energy_kwh_per_m3": self._text(methane_energy), "conversion_efficiency_pct": self._text(efficiency)},
            "output": {"biogas_volume_m3": self._text(biogas), "methane_volume_m3": self._text(methane), "gross_methane_energy_kwh": self._text(gross), "useful_energy_kwh": self._text(useful)},
            "provenance": {"assumption_origin": "explicit-user-supplied-scenario-input", "default_yield_used": False, "default_methane_fraction_used": False, "default_methane_energy_value_used": False},
            "boundary": "Energy-output estimate only. Digestate effects, methane leakage, baseline waste emissions, displacement, lifecycle emissions, and avoided emissions are not calculated.",
        }

    def biochar_carbon_estimate(self, *, biochar_mass_kg: str, carbon_fraction_pct: str, stable_fraction_pct: str) -> dict[str, Any]:
        mass = self._decimal(biochar_mass_kg, label="biochar_mass_kg", nonnegative=True)
        carbon_fraction = self._pct(carbon_fraction_pct, label="carbon_fraction_pct")
        stable_fraction = self._pct(stable_fraction_pct, label="stable_fraction_pct")
        with localcontext() as ctx:
            ctx.prec = 40
            carbon_mass = mass * carbon_fraction / Decimal("100")
            stable_carbon = carbon_mass * stable_fraction / Decimal("100")
            co2e_stoich = stable_carbon * Decimal("44") / Decimal("12")
        return {
            "ok": True, "schema": "sc-energy-biochar-carbon-result/1.0", "model_version": MODEL_VERSION,
            "input": {"biochar_mass_kg": self._text(mass), "carbon_fraction_pct": self._text(carbon_fraction), "stable_fraction_pct": self._text(stable_fraction)},
            "output": {"carbon_mass_kg_c": self._text(carbon_mass), "stable_carbon_mass_kg_c": self._text(stable_carbon), "stoichiometric_co2_equivalent_kg": self._text(co2e_stoich)},
            "method": {"carbon_to_co2_mass_ratio": "44/12", "ratio_role": "stoichiometric molecular-mass conversion"},
            "boundary": "Stoichiometric accounting estimate only. It is not a lifecycle removal, permanence, additionality, soil-response, avoided-emissions, verification, issuance, or carbon-credit result.",
        }

    def biomass_to_oil_energy_estimate(self, *, feedstock_mass_tonnes: str, oil_yield_mass_pct: str, oil_energy_content_kwh_per_tonne: str, downstream_conversion_efficiency_pct: str = "100") -> dict[str, Any]:
        mass = self._decimal(feedstock_mass_tonnes, label="feedstock_mass_tonnes", nonnegative=True)
        yield_pct = self._pct(oil_yield_mass_pct, label="oil_yield_mass_pct")
        energy_content = self._decimal(oil_energy_content_kwh_per_tonne, label="oil_energy_content_kwh_per_tonne", nonnegative=True)
        efficiency = self._pct(downstream_conversion_efficiency_pct, label="downstream_conversion_efficiency_pct")
        with localcontext() as ctx:
            ctx.prec = 40
            oil_mass = mass * yield_pct / Decimal("100")
            gross = oil_mass * energy_content
            useful = gross * efficiency / Decimal("100")
        return {
            "ok": True, "schema": "sc-energy-biomass-to-oil-result/1.0", "model_version": MODEL_VERSION,
            "input": {"feedstock_mass_tonnes": self._text(mass), "oil_yield_mass_pct": self._text(yield_pct), "oil_energy_content_kwh_per_tonne": self._text(energy_content), "downstream_conversion_efficiency_pct": self._text(efficiency)},
            "output": {"oil_product_mass_tonnes": self._text(oil_mass), "gross_product_energy_kwh": self._text(gross), "useful_energy_kwh": self._text(useful)},
            "provenance": {"assumption_origin": "explicit-user-supplied-scenario-input", "default_oil_yield_used": False, "default_oil_energy_content_used": False},
            "boundary": "Mass/energy scenario arithmetic only. Conversion-route chemistry, lifecycle emissions, land-use effects, feedstock counterfactuals, coproduct allocation, and fuel quality are not inferred.",
        }

    def scenario_template(self) -> dict[str, Any]:
        return {
            "ok": True, "schema": "sc-energy-bioenergy-carbon-scenario/1.0", "model_version": MODEL_VERSION,
            "contract": {
                "required_structure": ["scenario_id", "name", "pathway", "feedstock", "energy", "carbon_accounting", "carbon_nature_handoffs", "evidence", "uncertainty"],
                "provenance_required": True,
                "energy_balance_handoff_ready": True,
                "economic_scenario_handoff_ready": True,
                "carbon_nature_handoff_ready": True,
                "project_crediting_status": "not-implemented",
                "lifecycle_assessment_status": "not-implemented",
                "optimization_status": "not-implemented",
            },
            "template": {
                "scenario_id": None,
                "name": None,
                "pathway": {"pathway_key": None, "technology_or_process_detail": None, "geography": None, "period": None},
                "feedstock": {"feedstock_class": None, "feedstock_identity": None, "origin": None, "mass_basis": None, "moisture_basis": None, "quantity": None, "unit": None, "competing_use_note": None, "land_use_note": None},
                "energy": {"input_energy_kwh": None, "gross_output_energy_kwh": None, "useful_output_energy_kwh": None, "conversion_efficiency_pct": None, "energy_balance_ref": None},
                "carbon_accounting": {"included_gases": [], "included_carbon_pools": [], "baseline_or_counterfactual": None, "lifecycle_boundary": None, "direct_emissions": [], "indirect_emissions": [], "stock_changes": [], "avoided_emissions": [], "co2e_gwp_context": None},
                "carbon_nature_handoffs": {"bridge_keys": [], "concept_refs": [], "methodology_refs": [], "project_object_refs": []},
                "economics": {"economic_scenario_ref": None, "cost_result_refs": []},
                "evidence": {"source_refs": [], "factor_refs": [], "methodology_refs": [], "dataset_refs": [], "assumption_refs": []},
                "uncertainty": {"input_uncertainties": [], "model_uncertainties": [], "boundary_uncertainties": [], "sensitivity_parameters": []},
            },
            "guardrails": self.guardrails(),
        }

    def export(self) -> dict[str, Any]:
        return {
            "schema": "sc-energy-biological-carbon-bioenergy-export/1.0",
            "version": MODEL_VERSION,
            "framework": self.framework(),
            "feedstocks": self.feedstocks(limit=100),
            "pathways": self.pathways(limit=100),
            "carbon_nature_bridges": self.carbon_nature_bridges(),
            "scenario_template": self.scenario_template(),
        }
