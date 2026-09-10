from __future__ import annotations

from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import hashlib
import json
from typing import Any, Iterable


DOMAIN_VERSION = "0.2.0"
SCHEMA_VERSION = "sc-carbon-nature-knowledge-foundation/1.1"
MEASURE_SCHEMA_VERSION = "sc-carbon-sequestration-measure-registry/1.0"


@dataclass(frozen=True)
class CarbonNatureConcept:
    key: str
    label: str
    concept_type: str
    domain: str
    definition: str
    synonyms: tuple[str, ...] = ()
    tags: tuple[str, ...] = ()
    authority_status: str = "sustainable-catalyst-domain-seed"

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        row["synonyms"] = list(self.synonyms)
        row["tags"] = list(self.tags)
        return row


@dataclass(frozen=True)
class CarbonNatureRelationship:
    subject: str
    predicate: str
    object: str
    evidence_requirement: str = "domain-model"
    inference_allowed: bool = False

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class CarbonSequestrationMeasure:
    key: str
    label: str
    measure_family: str
    primary_domain: str
    description: str
    intervention_concepts: tuple[str, ...]
    applicable_systems: tuple[str, ...]
    target_carbon_pools: tuple[str, ...]
    relevant_gases: tuple[str, ...]
    outcome_types: tuple[str, ...]
    mrv_method_families: tuple[str, ...]
    integrity_dimensions: tuple[str, ...]
    co_benefit_concepts: tuple[str, ...] = ()
    risk_concepts: tuple[str, ...] = ()
    evidence_requirements: tuple[str, ...] = ()
    tags: tuple[str, ...] = ()
    registry_status: str = "evidence-required"

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        for field in (
            "intervention_concepts", "applicable_systems", "target_carbon_pools", "relevant_gases",
            "outcome_types", "mrv_method_families", "integrity_dimensions", "co_benefit_concepts",
            "risk_concepts", "evidence_requirements", "tags",
        ):
            row[field] = list(row[field])
        return row


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _stable_hash(payload: Any) -> str:
    data = json.dumps(payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    return hashlib.sha256(data).hexdigest()


def _unique(values: Iterable[str]) -> list[str]:
    seen: set[str] = set()
    out: list[str] = []
    for value in values:
        item = str(value or "").strip()
        folded = item.casefold()
        if item and folded not in seen:
            seen.add(folded)
            out.append(item)
    return out


CONCEPTS: tuple[CarbonNatureConcept, ...] = (
    CarbonNatureConcept("carbon-nature", "Carbon & Nature Intelligence", "domain", "carbon-nature", "Sustainable Catalyst domain for governed AFOLU, carbon sequestration, nature-based solutions, MRV, carbon economics, and related evidence."),
    CarbonNatureConcept("afolu", "Agriculture, Forestry and Other Land Use", "domain", "afolu", "Domain covering agriculture, forestry, land-use systems, greenhouse-gas fluxes, carbon pools, and land-management interventions.", ("AFOLU",)),
    CarbonNatureConcept("nature-based-solutions", "Nature-Based Solutions", "domain", "nature-based-solutions", "Actions involving ecosystems and ecological processes that address societal challenges while requiring explicit evidence of outcomes, trade-offs, and safeguards.", ("NbS",)),
    CarbonNatureConcept("carbon-farming", "Carbon Farming", "domain", "carbon-farming", "Land-management approaches evaluated for greenhouse-gas mitigation or carbon sequestration together with additionality, leakage, permanence, uncertainty, and co-benefits."),
    CarbonNatureConcept("soil-organic-carbon", "Soil Organic Carbon", "carbon-pool", "soil-organic-carbon", "Organic carbon contained in soil organic matter and evaluated through explicit depth, bulk-density, concentration, stock, sampling, and uncertainty context.", ("SOC",), ("soil", "carbon-pool")),
    CarbonNatureConcept("aboveground-biomass", "Aboveground Biomass", "carbon-pool", "afolu", "Carbon stored in living vegetation above the soil surface."),
    CarbonNatureConcept("belowground-biomass", "Belowground Biomass", "carbon-pool", "afolu", "Carbon stored in living roots and other belowground biomass."),
    CarbonNatureConcept("dead-wood", "Dead Wood", "carbon-pool", "afolu", "Dead woody biomass considered as a distinct carbon pool where relevant to the land system."),
    CarbonNatureConcept("litter", "Litter", "carbon-pool", "afolu", "Dead plant material at or near the soil surface considered as a carbon pool where relevant."),
    CarbonNatureConcept("carbon-dioxide", "Carbon Dioxide", "greenhouse-gas", "afolu", "Greenhouse gas represented as CO2 in AFOLU accounting and mitigation analysis.", ("CO2",)),
    CarbonNatureConcept("methane", "Methane", "greenhouse-gas", "afolu", "Greenhouse gas represented as CH4 and relevant to agricultural, livestock, wetland, and other land-system analyses.", ("CH4",)),
    CarbonNatureConcept("nitrous-oxide", "Nitrous Oxide", "greenhouse-gas", "afolu", "Greenhouse gas represented as N2O and relevant to soil, nutrient, and agricultural management analyses.", ("N2O",)),
    CarbonNatureConcept("cropland", "Cropland", "land-use-system", "afolu", "Land managed primarily for crop production and associated rotations, tillage, residues, soil, and nutrient practices."),
    CarbonNatureConcept("grassland", "Grassland", "land-use-system", "afolu", "Grass-dominated land system evaluated for carbon stocks, management, livestock interactions, and restoration."),
    CarbonNatureConcept("forest-woodland", "Forest & Woodland", "land-use-system", "afolu", "Woody land system evaluated for biomass carbon, soil carbon, management, establishment, and restoration."),
    CarbonNatureConcept("wetland", "Wetland", "ecosystem", "nature-based-solutions", "Water-influenced ecosystem requiring hydrological, carbon, biodiversity, and reversal-risk context."),
    CarbonNatureConcept("peatland", "Peatland", "ecosystem", "nature-based-solutions", "Organic-soil wetland system where drainage, restoration, greenhouse-gas fluxes, and long-term carbon storage require explicit treatment."),
    CarbonNatureConcept("agroforestry-system", "Agroforestry System", "land-use-system", "afolu", "Land-use system integrating woody perennials with crops or livestock and evaluated across biomass, soil, production, and co-benefit outcomes."),
    CarbonNatureConcept("livestock-system", "Livestock System", "agricultural-system", "afolu", "Animal-production system evaluated with methane, nitrous oxide, land, feed, manure, grassland, and whole-farm greenhouse-gas context."),
    CarbonNatureConcept("improved-rotation", "Improved Crop Rotation", "intervention", "carbon-farming", "Change in crop sequencing intended to alter productivity, soil function, carbon inputs, or greenhouse-gas outcomes."),
    CarbonNatureConcept("reduced-tillage", "Reduced or Minimum Tillage", "intervention", "carbon-farming", "Reduction in soil disturbance evaluated for soil carbon, emissions, yield, soil condition, and system-specific trade-offs."),
    CarbonNatureConcept("residue-management", "Residue Management", "intervention", "carbon-farming", "Management of crop residues to change carbon inputs, soil protection, nutrient cycling, and greenhouse-gas outcomes."),
    CarbonNatureConcept("cover-crops", "Cover Crops", "intervention", "carbon-farming", "Use of non-primary crops to influence soil cover, carbon inputs, nutrient cycling, erosion, water, and biodiversity outcomes."),
    CarbonNatureConcept("soil-nutrient-management", "Soil & Nutrient Management", "intervention", "carbon-farming", "Management of soil amendments, fertility, and nutrient practices evaluated across carbon, productivity, and greenhouse-gas outcomes."),
    CarbonNatureConcept("agroforestry-intervention", "Agroforestry", "intervention", "carbon-farming", "Introduction or management of woody vegetation within agricultural systems for biomass, soil, resilience, production, and co-benefit outcomes."),
    CarbonNatureConcept("grassland-restoration", "Grassland Restoration", "intervention", "nature-based-solutions", "Restoration or improved management of grassland condition evaluated for carbon, biodiversity, water, erosion, and production outcomes."),
    CarbonNatureConcept("woodland-establishment", "Woodland Establishment", "intervention", "nature-based-solutions", "Establishment of woody vegetation evaluated for biomass carbon, soil carbon, biodiversity, land-use trade-offs, and permanence."),
    CarbonNatureConcept("wetland-restoration", "Wetland Restoration", "intervention", "nature-based-solutions", "Restoration of wetland structure or hydrology evaluated for carbon, greenhouse-gas fluxes, water, biodiversity, and resilience."),
    CarbonNatureConcept("peatland-restoration", "Peatland Restoration", "intervention", "nature-based-solutions", "Restoration of peatland hydrology and ecological condition evaluated for avoided losses, greenhouse-gas fluxes, storage, and permanence."),
    CarbonNatureConcept("soc-stock", "SOC Stock", "indicator", "soil-organic-carbon", "Mass of soil organic carbon per unit area for an explicitly defined soil depth and calculation basis."),
    CarbonNatureConcept("soc-concentration", "SOC Concentration", "indicator", "soil-organic-carbon", "Concentration or proportion of organic carbon measured in a soil sample, distinct from area-based stock."),
    CarbonNatureConcept("bulk-density", "Bulk Density", "indicator", "soil-organic-carbon", "Soil mass per unit bulk volume used with carbon concentration, depth, and coarse-fragment context in stock calculations."),
    CarbonNatureConcept("soil-depth", "Soil Sampling Depth", "indicator", "soil-organic-carbon", "Defined vertical interval over which soil observations are collected and interpreted."),
    CarbonNatureConcept("mrv", "Monitoring, Reporting and Verification", "domain", "mrv", "Domain for monitoring plans, measurements, models, reporting, verification evidence, uncertainty, and auditability.", ("MRV",)),
    CarbonNatureConcept("policy", "Carbon Markets & Policy", "domain", "policy", "Policy, market, national-accounting, payment, and regulatory context for carbon and nature interventions."),
    CarbonNatureConcept("evidence", "Carbon & Nature Evidence", "domain", "evidence", "Evidence domain linking research, methodologies, protocols, standards, and policy records to governed Carbon & Nature concepts."),
    CarbonNatureConcept("direct-measurement", "Direct Measurement", "methodology-family", "mrv", "MRV methodology family based primarily on direct field, laboratory, or instrument observations."),
    CarbonNatureConcept("sampling-methodology", "Sampling", "methodology-family", "mrv", "MRV methodology family defining spatial, temporal, and statistical selection of observations."),
    CarbonNatureConcept("proxy-methodology", "Proxy Method", "methodology-family", "mrv", "MRV methodology family that uses an observable proxy for a target carbon or greenhouse-gas quantity."),
    CarbonNatureConcept("modeling-methodology", "Modeling", "methodology-family", "mrv", "MRV methodology family using explicit models, inputs, assumptions, calibration, and validation to estimate target quantities."),
    CarbonNatureConcept("hybrid-mrv", "Hybrid MRV", "methodology-family", "mrv", "MRV approach combining measurement, sampling, proxy, remote-sensing, or modeling components."),
    CarbonNatureConcept("additionality", "Additionality", "integrity-dimension", "carbon-farming", "Assessment of whether claimed mitigation or removals are additional to an appropriate baseline or counterfactual."),
    CarbonNatureConcept("leakage", "Leakage", "integrity-dimension", "carbon-farming", "Assessment of whether an intervention displaces emissions, activities, or impacts outside the project boundary."),
    CarbonNatureConcept("permanence", "Permanence", "integrity-dimension", "carbon-farming", "Assessment of durability of carbon storage or mitigation outcomes and exposure to reversal."),
    CarbonNatureConcept("reversal-risk", "Reversal Risk", "risk", "carbon-farming", "Risk that stored carbon or avoided emissions are later reversed through management change, disturbance, fire, drought, erosion, or other processes."),
    CarbonNatureConcept("uncertainty", "Uncertainty", "integrity-dimension", "mrv", "Quantified or qualitative limitation in measurements, models, assumptions, sampling, spatial variability, and reported outcomes."),
    CarbonNatureConcept("water-security", "Water Security", "co-benefit", "nature-based-solutions", "Water-related outcome requiring explicit indicators rather than assumed benefit."),
    CarbonNatureConcept("biodiversity", "Biodiversity", "co-benefit", "nature-based-solutions", "Biological diversity outcome requiring explicit measurement or evidence rather than generic nature-positive scoring."),
    CarbonNatureConcept("erosion-control", "Erosion Control", "co-benefit", "nature-based-solutions", "Outcome related to reduced soil loss or improved soil protection, requiring explicit evidence and context."),
    CarbonNatureConcept("flood-drought-resilience", "Flood & Drought Resilience", "co-benefit", "nature-based-solutions", "Resilience outcome related to hydrology, water storage, drought, or flood exposure, requiring explicit causal and spatial context."),
    CarbonNatureConcept("farm-income", "Farm Income & Diversification", "co-benefit", "carbon-farming", "Economic outcome related to farm income or diversification that must be evaluated independently of carbon-credit claims."),
    CarbonNatureConcept("rural-employment", "Rural Employment", "co-benefit", "nature-based-solutions", "Socio-economic employment outcome requiring explicit evidence, geography, and distributional context."),
    CarbonNatureConcept("national-ghg-inventory", "National GHG Inventory", "policy-instrument", "policy", "National greenhouse-gas accounting and reporting context to which project or sector evidence may later be cross-walked without assuming direct credit equivalence."),
    CarbonNatureConcept("carbon-market", "Carbon Market", "economic-mechanism", "policy", "Market mechanism in which carbon-related units may be transacted subject to program-specific rules, accounting, eligibility, and verification."),
    CarbonNatureConcept("result-based-payment", "Result-Based Payment", "economic-mechanism", "policy", "Payment approach linked to measured or verified outcomes under an explicit methodology and contractual framework."),
    CarbonNatureConcept("action-based-scheme", "Action-Based Scheme", "economic-mechanism", "policy", "Scheme in which payment or participation is linked primarily to implementation of specified practices rather than quantified outcomes alone."),
    CarbonNatureConcept("hybrid-scheme", "Hybrid Scheme", "economic-mechanism", "policy", "Scheme combining action-based and result-based elements."),
    CarbonNatureConcept("research-evidence", "Research Evidence", "evidence-type", "evidence", "Published or curated evidence used to support, challenge, or contextualize carbon and nature claims."),
    CarbonNatureConcept("methodology-document", "Methodology or Protocol", "evidence-type", "evidence", "Document defining measurement, accounting, monitoring, reporting, verification, or project rules."),
    CarbonNatureConcept("policy-document", "Policy or Regulatory Document", "evidence-type", "evidence", "Policy, regulation, inventory guidance, or public-program document that must retain jurisdiction and version context."),
)


RELATIONSHIPS: tuple[CarbonNatureRelationship, ...] = (
    CarbonNatureRelationship("afolu", "part-of", "carbon-nature"),
    CarbonNatureRelationship("nature-based-solutions", "part-of", "carbon-nature"),
    CarbonNatureRelationship("carbon-farming", "part-of", "carbon-nature"),
    CarbonNatureRelationship("soil-organic-carbon", "part-of", "carbon-nature"),
    CarbonNatureRelationship("soil-organic-carbon", "part-of", "afolu"),
    CarbonNatureRelationship("soc-stock", "measures", "soil-organic-carbon"),
    CarbonNatureRelationship("soc-concentration", "characterizes", "soil-organic-carbon"),
    CarbonNatureRelationship("bulk-density", "supports-calculation-of", "soc-stock"),
    CarbonNatureRelationship("soil-depth", "qualifies", "soc-stock"),
    CarbonNatureRelationship("improved-rotation", "applies-to", "cropland"),
    CarbonNatureRelationship("reduced-tillage", "applies-to", "cropland"),
    CarbonNatureRelationship("residue-management", "applies-to", "cropland"),
    CarbonNatureRelationship("cover-crops", "applies-to", "cropland"),
    CarbonNatureRelationship("soil-nutrient-management", "applies-to", "cropland"),
    CarbonNatureRelationship("agroforestry-intervention", "creates-or-modifies", "agroforestry-system"),
    CarbonNatureRelationship("grassland-restoration", "applies-to", "grassland"),
    CarbonNatureRelationship("woodland-establishment", "creates-or-modifies", "forest-woodland"),
    CarbonNatureRelationship("wetland-restoration", "applies-to", "wetland"),
    CarbonNatureRelationship("peatland-restoration", "applies-to", "peatland"),
    CarbonNatureRelationship("direct-measurement", "part-of", "mrv"),
    CarbonNatureRelationship("sampling-methodology", "supports", "uncertainty"),
    CarbonNatureRelationship("modeling-methodology", "requires-explicit", "uncertainty"),
    CarbonNatureRelationship("hybrid-mrv", "combines", "direct-measurement"),
    CarbonNatureRelationship("hybrid-mrv", "combines", "modeling-methodology"),
    CarbonNatureRelationship("carbon-farming", "requires-assessment-of", "additionality"),
    CarbonNatureRelationship("carbon-farming", "requires-assessment-of", "leakage"),
    CarbonNatureRelationship("carbon-farming", "requires-assessment-of", "permanence"),
    CarbonNatureRelationship("carbon-farming", "requires-assessment-of", "uncertainty"),
    CarbonNatureRelationship("permanence", "constrained-by", "reversal-risk"),
    CarbonNatureRelationship("cover-crops", "may-affect", "erosion-control"),
    CarbonNatureRelationship("agroforestry-intervention", "may-affect", "biodiversity"),
    CarbonNatureRelationship("agroforestry-intervention", "may-affect", "farm-income"),
    CarbonNatureRelationship("wetland-restoration", "may-affect", "water-security"),
    CarbonNatureRelationship("peatland-restoration", "may-affect", "water-security"),
    CarbonNatureRelationship("grassland-restoration", "may-affect", "biodiversity"),
    CarbonNatureRelationship("nature-based-solutions", "requires-explicit-outcome-evidence", "biodiversity"),
    CarbonNatureRelationship("nature-based-solutions", "requires-explicit-outcome-evidence", "water-security"),
    CarbonNatureRelationship("result-based-payment", "requires", "methodology-document"),
    CarbonNatureRelationship("carbon-market", "requires-program-specific-review-of", "additionality"),
    CarbonNatureRelationship("carbon-market", "requires-program-specific-review-of", "permanence"),
    CarbonNatureRelationship("national-ghg-inventory", "uses-evidence-from", "research-evidence"),
)


MEASURES: tuple[CarbonSequestrationMeasure, ...] = (
    CarbonSequestrationMeasure(
        "improved-crop-rotation", "Improved Crop Rotation", "cropland-soil-carbon", "carbon-farming",
        "Crop-sequence changes registered as a potential soil-carbon and whole-system management measure. Outcomes depend on crop mix, climate, soil, baseline management, yields, residue flows, and other interacting practices.",
        ("improved-rotation",), ("cropland",), ("soil-organic-carbon",), ("carbon-dioxide", "nitrous-oxide"),
        ("soil-carbon-stock-change", "ghg-flux-change"), ("sampling-methodology", "direct-measurement", "modeling-methodology", "hybrid-mrv"),
        ("additionality", "leakage", "permanence", "uncertainty"), ("erosion-control", "water-security", "farm-income"), ("uncertainty",),
        ("Document baseline rotation and management history.", "Retain soil, climate, crop, yield, residue, and nutrient context.", "Quantified claims require an applicable measurement or modeling methodology and explicit uncertainty."),
        ("rotation", "cropland", "soil carbon", "management"),
    ),
    CarbonSequestrationMeasure(
        "cover-crop-system", "Cover Crop System", "cropland-soil-carbon", "carbon-farming",
        "Use of cover crops registered as a measure that may alter soil carbon inputs, nutrient cycling, soil protection, water behavior, and greenhouse-gas fluxes. Direction and magnitude are context dependent.",
        ("cover-crops",), ("cropland",), ("soil-organic-carbon",), ("carbon-dioxide", "nitrous-oxide"),
        ("soil-carbon-stock-change", "ghg-flux-change"), ("sampling-methodology", "direct-measurement", "modeling-methodology", "hybrid-mrv"),
        ("additionality", "leakage", "permanence", "uncertainty"), ("erosion-control", "water-security", "biodiversity"), ("uncertainty",),
        ("Record species or mixture, timing, termination, biomass handling, and baseline practice.", "Retain nutrient, water, yield, and soil observations needed to assess trade-offs.", "Do not infer sequestration from practice adoption alone."),
        ("cover crops", "cropland", "soil carbon", "soil health"),
    ),
    CarbonSequestrationMeasure(
        "reduced-tillage-system", "Reduced or Minimum Tillage", "cropland-soil-carbon", "carbon-farming",
        "Reduced soil disturbance registered as a management measure requiring depth-aware soil-carbon accounting and whole-system review. Carbon redistribution within the profile must not be confused with net stock gain.",
        ("reduced-tillage",), ("cropland",), ("soil-organic-carbon",), ("carbon-dioxide", "nitrous-oxide"),
        ("soil-carbon-stock-change", "ghg-flux-change"), ("sampling-methodology", "direct-measurement", "modeling-methodology", "hybrid-mrv"),
        ("additionality", "leakage", "permanence", "uncertainty"), ("erosion-control", "water-security"), ("uncertainty",),
        ("Record tillage depth, frequency, equipment, duration, and prior management.", "Use explicit soil depth and bulk-density context when comparing stocks.", "Evaluate fuel, yield, residue, weed-control, and nutrient effects where relevant."),
        ("tillage", "cropland", "soil carbon", "soil depth"),
    ),
    CarbonSequestrationMeasure(
        "crop-residue-management", "Crop Residue Management", "cropland-soil-carbon", "carbon-farming",
        "Retention, removal, incorporation, or other management of crop residues registered as a measure affecting carbon inputs, soil protection, nutrient cycling, and potentially greenhouse-gas fluxes.",
        ("residue-management",), ("cropland",), ("soil-organic-carbon",), ("carbon-dioxide", "nitrous-oxide"),
        ("soil-carbon-stock-change", "ghg-flux-change"), ("sampling-methodology", "direct-measurement", "modeling-methodology", "hybrid-mrv"),
        ("additionality", "leakage", "permanence", "uncertainty"), ("erosion-control", "water-security"), ("leakage", "uncertainty"),
        ("Record residue quantity, disposition, incorporation, competing uses, and baseline.", "Account for displaced residue uses when leakage could occur.", "Quantified benefit requires system-specific evidence rather than a fixed default assumption."),
        ("residue", "cropland", "soil carbon", "nutrient cycling"),
    ),
    CarbonSequestrationMeasure(
        "soil-nutrient-management-practice", "Soil & Nutrient Management", "cropland-soil-carbon", "carbon-farming",
        "Soil amendments and nutrient-management changes registered as a measure family that can affect productivity, soil carbon, nitrous oxide, and other system outcomes. Carbon and non-CO2 effects must be assessed together when material.",
        ("soil-nutrient-management",), ("cropland",), ("soil-organic-carbon",), ("carbon-dioxide", "nitrous-oxide"),
        ("soil-carbon-stock-change", "ghg-flux-change"), ("direct-measurement", "sampling-methodology", "modeling-methodology", "hybrid-mrv"),
        ("additionality", "leakage", "permanence", "uncertainty"), ("water-security", "farm-income"), ("uncertainty",),
        ("Record amendment or nutrient type, quantity, timing, placement, source, and baseline.", "Retain yield and nitrogen-management context for whole-system interpretation.", "Do not report SOC change without reviewing potentially material N2O effects."),
        ("nutrient management", "soil amendment", "cropland", "N2O"),
    ),
    CarbonSequestrationMeasure(
        "agroforestry-establishment-management", "Agroforestry Establishment & Management", "woody-biomass-and-agroforestry", "carbon-farming",
        "Integration or management of woody vegetation within agricultural systems registered as a multi-pool measure spanning aboveground biomass, belowground biomass, litter, dead wood where relevant, and soil organic carbon.",
        ("agroforestry-intervention",), ("agroforestry-system", "cropland", "grassland"),
        ("aboveground-biomass", "belowground-biomass", "soil-organic-carbon", "litter", "dead-wood"), ("carbon-dioxide", "nitrous-oxide"),
        ("biomass-carbon-stock-change", "soil-carbon-stock-change", "ghg-flux-change"), ("direct-measurement", "sampling-methodology", "modeling-methodology", "hybrid-mrv"),
        ("additionality", "leakage", "permanence", "uncertainty"), ("biodiversity", "erosion-control", "water-security", "farm-income"), ("reversal-risk", "leakage", "uncertainty"),
        ("Record species, density, age or establishment date, management, land-use baseline, and affected production system.", "Separate biomass and soil carbon pools and avoid double counting.", "Assess land-use displacement, harvest, mortality, fire, and other reversal pathways where relevant."),
        ("agroforestry", "woody biomass", "farm trees", "multi-pool"),
    ),
    CarbonSequestrationMeasure(
        "grassland-restoration-management", "Grassland Restoration & Improved Management", "grassland", "nature-based-solutions",
        "Restoration or improved management of grassland condition registered as a measure potentially affecting soil carbon, biomass, erosion, water, biodiversity, livestock interactions, and greenhouse-gas fluxes.",
        ("grassland-restoration",), ("grassland", "livestock-system"), ("soil-organic-carbon", "aboveground-biomass", "belowground-biomass"),
        ("carbon-dioxide", "methane", "nitrous-oxide"), ("soil-carbon-stock-change", "biomass-carbon-stock-change", "ghg-flux-change"),
        ("direct-measurement", "sampling-methodology", "modeling-methodology", "hybrid-mrv"), ("additionality", "leakage", "permanence", "uncertainty"),
        ("biodiversity", "erosion-control", "water-security", "farm-income"), ("leakage", "reversal-risk", "uncertainty"),
        ("Record grazing regime, stocking, vegetation condition, inputs, restoration actions, and baseline.", "Where livestock is material, retain methane and nitrous-oxide context rather than treating soil carbon in isolation.", "Assess displacement of grazing or production outside the project boundary."),
        ("grassland", "grazing", "restoration", "soil carbon"),
    ),
    CarbonSequestrationMeasure(
        "woodland-establishment-measure", "Woodland Establishment", "woody-biomass-and-forest", "nature-based-solutions",
        "Establishment of woodland registered as a land-use measure affecting biomass and soil carbon while requiring explicit treatment of prior land use, biodiversity, production displacement, permanence, and long time horizons.",
        ("woodland-establishment",), ("forest-woodland", "cropland", "grassland"),
        ("aboveground-biomass", "belowground-biomass", "soil-organic-carbon", "litter", "dead-wood"), ("carbon-dioxide",),
        ("biomass-carbon-stock-change", "soil-carbon-stock-change"), ("direct-measurement", "sampling-methodology", "modeling-methodology", "hybrid-mrv"),
        ("additionality", "leakage", "permanence", "uncertainty"), ("biodiversity", "erosion-control", "water-security"), ("reversal-risk", "leakage", "uncertainty"),
        ("Record prior land use, establishment method, species, density, management plan, and carbon-pool boundaries.", "Assess displaced agricultural or other land-use activity when relevant.", "Permanence and disturbance exposure require explicit review."),
        ("woodland", "forest", "biomass carbon", "land-use change"),
    ),
    CarbonSequestrationMeasure(
        "wetland-restoration-measure", "Wetland Restoration", "wetland-and-peatland", "nature-based-solutions",
        "Restoration of wetland structure or hydrology registered as a measure that can affect carbon storage, carbon dioxide, methane, water, biodiversity, and resilience. Net climate effects require multi-gas assessment where material.",
        ("wetland-restoration",), ("wetland",), ("soil-organic-carbon", "aboveground-biomass", "belowground-biomass"),
        ("carbon-dioxide", "methane", "nitrous-oxide"), ("avoided-carbon-loss", "soil-carbon-stock-change", "ghg-flux-change"),
        ("direct-measurement", "sampling-methodology", "modeling-methodology", "hybrid-mrv"), ("additionality", "leakage", "permanence", "uncertainty"),
        ("water-security", "biodiversity", "flood-drought-resilience"), ("reversal-risk", "uncertainty"),
        ("Record hydrological baseline, restoration action, water regime, vegetation, soil conditions, and project boundary.", "Measure or model material methane changes rather than assuming a uniformly positive climate outcome.", "Retain biodiversity and hydrological indicators separately from carbon claims."),
        ("wetland", "restoration", "hydrology", "methane"),
    ),
    CarbonSequestrationMeasure(
        "peatland-restoration-rewetting", "Peatland Restoration & Rewetting", "wetland-and-peatland", "nature-based-solutions",
        "Restoration or rewetting of peatland registered primarily as an avoided-loss and greenhouse-gas-flux measure, with long-lived soil carbon stocks, hydrology, methane, land management, and reversal risk requiring explicit treatment.",
        ("peatland-restoration",), ("peatland",), ("soil-organic-carbon",), ("carbon-dioxide", "methane", "nitrous-oxide"),
        ("avoided-carbon-loss", "ghg-flux-change", "soil-carbon-stock-change"), ("direct-measurement", "sampling-methodology", "proxy-methodology", "modeling-methodology", "hybrid-mrv"),
        ("additionality", "leakage", "permanence", "uncertainty"), ("water-security", "biodiversity", "flood-drought-resilience"), ("reversal-risk", "uncertainty"),
        ("Record drainage state, water table or hydrological proxies, peat condition, vegetation, land use, and restoration action.", "Assess CO2 and methane together where material to the claimed climate outcome.", "Treat continued drainage, drought, fire, and management change as potential reversal pathways."),
        ("peatland", "rewetting", "avoided loss", "hydrology", "methane"),
    ),
)


class CarbonNatureKnowledgeFoundation:
    def __init__(self) -> None:
        self._concepts = {concept.key: concept for concept in CONCEPTS}
        self._relationships = RELATIONSHIPS
        self._measures = {measure.key: measure for measure in MEASURES}
        self._validate()

    def _validate(self) -> None:
        if len(self._concepts) != len(CONCEPTS):
            raise RuntimeError("duplicate Carbon & Nature concept key")
        if len(self._measures) != len(MEASURES):
            raise RuntimeError("duplicate Carbon & Nature measure key")
        dangling = [
            (rel.subject, rel.object)
            for rel in self._relationships
            if rel.subject not in self._concepts or rel.object not in self._concepts
        ]
        if dangling:
            raise RuntimeError(f"dangling Carbon & Nature relationship: {dangling[0]}")
        concept_fields = (
            "intervention_concepts", "applicable_systems", "target_carbon_pools", "relevant_gases",
            "mrv_method_families", "integrity_dimensions", "co_benefit_concepts", "risk_concepts",
        )
        for measure in MEASURES:
            for field in concept_fields:
                for key in getattr(measure, field):
                    if key not in self._concepts:
                        raise RuntimeError(f"measure {measure.key} has unknown {field} concept: {key}")

    def manifest(self) -> dict[str, Any]:
        concepts = [item.to_dict() for item in CONCEPTS]
        relationships = [item.to_dict() for item in RELATIONSHIPS]
        measures = [item.to_dict() for item in MEASURES]
        return {
            "schema": SCHEMA_VERSION,
            "subsystem": {
                "key": "carbon-nature-intelligence",
                "name": "Carbon & Nature Intelligence",
                "version": DOMAIN_VERSION,
                "release": "Carbon Sequestration Measure Registry",
                "primary_home": "Sustainable Catalyst Library",
                "library_release_line": "5.11.x",
                "backend_version": "2.3.0",
            },
            "coverage": {
                "concept_count": len(concepts),
                "relationship_count": len(relationships),
                "measure_count": len(measures),
                "concept_types": sorted({item["concept_type"] for item in concepts}),
                "domains": sorted({item["domain"] for item in concepts}),
                "measure_families": sorted({item["measure_family"] for item in measures}),
                "outcome_types": sorted({value for item in measures for value in item["outcome_types"]}),
            },
            "capabilities": [
                "stable-domain-concept-identifiers",
                "afolu-concept-registry",
                "nature-based-solutions-concept-registry",
                "carbon-pool-and-greenhouse-gas-model",
                "structured-carbon-sequestration-measure-registry",
                "measure-applicability-metadata",
                "measure-carbon-pool-and-gas-linkage",
                "measure-mrv-method-family-linkage",
                "measure-integrity-risk-metadata",
                "measure-co-benefit-and-tradeoff-context",
                "bounded-measure-comparison-packets",
                "explicit-relationship-registry",
                "library-evidence-linkage-ready",
                "research-librarian-measure-context-ready",
            ],
            "governance": {
                "normative_standard_claimed": False,
                "carbon_credit_issuance": False,
                "certification_body": False,
                "automatic_nature_positive_score": False,
                "automatic_measure_ranking": False,
                "automatic_measure_suitability_determination": False,
                "automatic_additionality_determination": False,
                "automatic_permanence_determination": False,
                "automatic_policy_equivalence": False,
                "project_specific_mrv_protocol_builder": False,
                "quantified_sequestration_potential": False,
                "soc_calculation_engine": False,
                "whole_farm_ghg_calculator": False,
                "research_only_foundation": True,
                "human_review_required_for_project_claims": True,
            },
            "boundaries": {
                "v0.1.0": "Domain ontology, concept identity, relationships, discovery, and context packets.",
                "v0.2.0": "Structured Carbon Sequestration Measure Registry with bounded filtering and comparison.",
                "v0.3.0": "Carbon Evidence & Methodology Graph.",
                "v0.4.0": "Carbon Project Object Model & Provenance.",
                "v0.5.0": "AFOLU Research Librarian Intelligence.",
            },
            "content_fingerprint": _stable_hash({"concepts": concepts, "relationships": relationships, "measures": measures}),
            "retrieved_at": _now(),
        }

    def concepts(self, *, concept_type: str | None = None, domain: str | None = None, q: str | None = None) -> dict[str, Any]:
        needle = str(q or "").strip().casefold()
        ctype = str(concept_type or "").strip().casefold()
        domain_key = str(domain or "").strip().casefold()
        rows: list[dict[str, Any]] = []
        for concept in CONCEPTS:
            if ctype and concept.concept_type.casefold() != ctype:
                continue
            if domain_key and concept.domain.casefold() != domain_key:
                continue
            haystack = " ".join([concept.key, concept.label, concept.definition, *concept.synonyms, *concept.tags]).casefold()
            if needle and needle not in haystack:
                continue
            rows.append(concept.to_dict())
        return {
            "schema": "sc-carbon-nature-concepts/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "filters": {"concept_type": concept_type, "domain": domain, "q": q},
            "count": len(rows),
            "concepts": rows,
        }

    def concept(self, key: str) -> dict[str, Any]:
        concept = self._concepts.get(str(key or "").strip())
        if concept is None:
            raise KeyError(key)
        rels = [rel.to_dict() for rel in RELATIONSHIPS if rel.subject == concept.key or rel.object == concept.key]
        neighbors = _unique([
            rel.object if rel.subject == concept.key else rel.subject
            for rel in RELATIONSHIPS
            if rel.subject == concept.key or rel.object == concept.key
        ])
        measure_rows = [
            measure.to_dict() for measure in MEASURES
            if concept.key in self._measure_concept_keys(measure)
        ]
        return {
            "schema": "sc-carbon-nature-concept/1.1",
            "subsystem_version": DOMAIN_VERSION,
            "concept": concept.to_dict(),
            "relationships": rels,
            "neighbors": [self._concepts[item].to_dict() for item in neighbors if item in self._concepts],
            "measures": measure_rows,
        }

    def relationships(self, *, subject: str | None = None, predicate: str | None = None, object_key: str | None = None) -> dict[str, Any]:
        rows: list[dict[str, Any]] = []
        for rel in RELATIONSHIPS:
            if subject and rel.subject != subject:
                continue
            if predicate and rel.predicate != predicate:
                continue
            if object_key and rel.object != object_key:
                continue
            rows.append(rel.to_dict())
        return {
            "schema": "sc-carbon-nature-relationships/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "count": len(rows),
            "relationships": rows,
        }

    @staticmethod
    def _measure_concept_keys(measure: CarbonSequestrationMeasure) -> list[str]:
        return _unique([
            *measure.intervention_concepts, *measure.applicable_systems, *measure.target_carbon_pools,
            *measure.relevant_gases, *measure.mrv_method_families, *measure.integrity_dimensions,
            *measure.co_benefit_concepts, *measure.risk_concepts,
        ])

    def measures(
        self,
        *,
        q: str | None = None,
        family: str | None = None,
        system: str | None = None,
        pool: str | None = None,
        gas: str | None = None,
        mrv_family: str | None = None,
        limit: int = 50,
    ) -> dict[str, Any]:
        needle = str(q or "").strip().casefold()
        family_key = str(family or "").strip().casefold()
        system_key = str(system or "").strip().casefold()
        pool_key = str(pool or "").strip().casefold()
        gas_key = str(gas or "").strip().casefold()
        mrv_key = str(mrv_family or "").strip().casefold()
        rows: list[dict[str, Any]] = []
        for measure in MEASURES:
            if family_key and measure.measure_family.casefold() != family_key:
                continue
            if system_key and system_key not in {value.casefold() for value in measure.applicable_systems}:
                continue
            if pool_key and pool_key not in {value.casefold() for value in measure.target_carbon_pools}:
                continue
            if gas_key and gas_key not in {value.casefold() for value in measure.relevant_gases}:
                continue
            if mrv_key and mrv_key not in {value.casefold() for value in measure.mrv_method_families}:
                continue
            concept_text = " ".join(
                self._concepts[key].label for key in self._measure_concept_keys(measure) if key in self._concepts
            )
            haystack = " ".join([
                measure.key, measure.label, measure.measure_family, measure.primary_domain, measure.description,
                *measure.outcome_types, *measure.evidence_requirements, *measure.tags, concept_text,
            ]).casefold()
            query_tokens = [token for token in needle.replace("/", " ").replace("-", " ").split() if token]
            if query_tokens and not all(token in haystack for token in query_tokens):
                continue
            rows.append(measure.to_dict())
        rows = rows[: max(1, min(int(limit), 100))]
        return {
            "schema": MEASURE_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "filters": {"q": q, "family": family, "system": system, "pool": pool, "gas": gas, "mrv_family": mrv_family},
            "facets": {
                "measure_families": sorted({item.measure_family for item in MEASURES}),
                "systems": sorted({value for item in MEASURES for value in item.applicable_systems}),
                "carbon_pools": sorted({value for item in MEASURES for value in item.target_carbon_pools}),
                "gases": sorted({value for item in MEASURES for value in item.relevant_gases}),
                "mrv_method_families": sorted({value for item in MEASURES for value in item.mrv_method_families}),
            },
            "count": len(rows),
            "measures": rows,
            "guardrails": {
                "registry_entry_is_not_project_suitability": True,
                "practice_adoption_is_not_quantified_sequestration": True,
                "measure_presence_is_not_methodology_eligibility": True,
            },
        }

    def measure(self, key: str) -> dict[str, Any]:
        measure = self._measures.get(str(key or "").strip())
        if measure is None:
            raise KeyError(key)
        concept_keys = self._measure_concept_keys(measure)
        return {
            "schema": "sc-carbon-sequestration-measure/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "measure": measure.to_dict(),
            "linked_concepts": [self._concepts[key].to_dict() for key in concept_keys if key in self._concepts],
            "guardrails": {
                "quantified_potential_not_provided": True,
                "project_suitability_not_determined": True,
                "credit_eligibility_not_determined": True,
                "methodology_selection_requires_evidence_and_review": True,
            },
            "content_fingerprint": _stable_hash(measure.to_dict()),
        }

    def compare_measures(self, keys: Iterable[str]) -> dict[str, Any]:
        ordered = _unique(keys)
        if len(ordered) < 2:
            raise ValueError("at least two measure keys are required")
        if len(ordered) > 6:
            raise ValueError("measure comparison is limited to six measures")
        missing = [key for key in ordered if key not in self._measures]
        if missing:
            raise KeyError(missing[0])
        measures = [self._measures[key] for key in ordered]

        def common(field: str) -> list[str]:
            sets = [set(getattr(measure, field)) for measure in measures]
            return sorted(set.intersection(*sets)) if sets else []

        return {
            "schema": "sc-carbon-sequestration-measure-comparison/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "measure_keys": ordered,
            "measures": [measure.to_dict() for measure in measures],
            "common": {
                "applicable_systems": common("applicable_systems"),
                "target_carbon_pools": common("target_carbon_pools"),
                "relevant_gases": common("relevant_gases"),
                "mrv_method_families": common("mrv_method_families"),
                "integrity_dimensions": common("integrity_dimensions"),
                "co_benefit_concepts": common("co_benefit_concepts"),
                "risk_concepts": common("risk_concepts"),
            },
            "governance": {
                "ranking_performed": False,
                "preferred_measure_selected": False,
                "project_suitability_determined": False,
                "quantified_climate_benefit_compared": False,
                "note": "v0.2.0 comparison exposes structured differences; it does not rank measures or determine project suitability.",
            },
            "content_fingerprint": _stable_hash({"keys": ordered, "measures": [measure.to_dict() for measure in measures]}),
        }

    @staticmethod
    def _score_text(query: str, text: str) -> int:
        tokens = [token.casefold() for token in query.replace("/", " ").replace("-", " ").split() if len(token) >= 2]
        haystack = text.casefold()
        score = sum(1 for token in tokens if token in haystack)
        if query.casefold() in haystack:
            score += 4
        return score

    def research_context(self, query: str, *, limit: int = 12) -> dict[str, Any]:
        query = str(query or "").strip()
        if not query:
            raise ValueError("query is required")
        scored_concepts: list[tuple[int, CarbonNatureConcept]] = []
        for concept in CONCEPTS:
            text = " ".join([concept.key, concept.label, concept.definition, *concept.synonyms, *concept.tags])
            score = self._score_text(query, text)
            if score:
                scored_concepts.append((score, concept))
        scored_concepts.sort(key=lambda item: (-item[0], item[1].label.casefold(), item[1].key))
        selected = [concept for _, concept in scored_concepts[: max(1, min(int(limit), 30))]]
        selected_keys = {concept.key for concept in selected}
        rels = [rel.to_dict() for rel in RELATIONSHIPS if rel.subject in selected_keys or rel.object in selected_keys]

        scored_measures: list[tuple[int, CarbonSequestrationMeasure]] = []
        for measure in MEASURES:
            concept_labels = [self._concepts[key].label for key in self._measure_concept_keys(measure) if key in self._concepts]
            text = " ".join([
                measure.key, measure.label, measure.measure_family, measure.primary_domain, measure.description,
                *measure.outcome_types, *measure.evidence_requirements, *measure.tags, *concept_labels,
            ])
            score = self._score_text(query, text)
            if score:
                scored_measures.append((score, measure))
        scored_measures.sort(key=lambda item: (-item[0], item[1].label.casefold(), item[1].key))
        selected_measures = [measure for _, measure in scored_measures[: max(1, min(int(limit), 20))]]

        return {
            "schema": "sc-carbon-nature-research-context/1.1",
            "subsystem_version": DOMAIN_VERSION,
            "query": query,
            "concepts": [item.to_dict() for item in selected],
            "relationships": rels,
            "measures": [item.to_dict() for item in selected_measures],
            "evidence_retrieval": {
                "library_search_query": query,
                "recommended_domains": _unique([item.domain for item in selected] + [item.primary_domain for item in selected_measures]),
                "recommended_measure_keys": [item.key for item in selected_measures],
                "evidence_object_types": ["research-evidence", "methodology-document", "policy-document"],
            },
            "handoff": {
                "research_librarian_ready": True,
                "measure_registry_context_enabled": True,
                "domain_aware_reasoning_enabled": False,
                "note": "v0.2.0 supplies governed concept and measure packets; AFOLU-specific Research Librarian reasoning is reserved for v0.5.0.",
            },
            "guardrails": {
                "concept_match_is_not_evidence": True,
                "measure_match_is_not_project_suitability": True,
                "relationship_is_not_causal_proof": True,
                "co_benefit_is_not_assumed": True,
                "project_credit_eligibility_not_determined": True,
                "methodology_applicability_requires_review": True,
                "quantified_sequestration_not_inferred": True,
            },
            "content_fingerprint": _stable_hash({
                "query": query.casefold(),
                "concepts": [item.key for item in selected],
                "relationships": rels,
                "measures": [item.key for item in selected_measures],
            }),
        }
