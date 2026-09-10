from __future__ import annotations

from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import hashlib
import json
from typing import Any, Iterable


DOMAIN_VERSION = "0.5.0"
SCHEMA_VERSION = "sc-carbon-nature-knowledge-foundation/1.4"
MEASURE_SCHEMA_VERSION = "sc-carbon-sequestration-measure-registry/1.0"
EVIDENCE_SCHEMA_VERSION = "sc-carbon-evidence-registry/1.0"
METHODOLOGY_SCHEMA_VERSION = "sc-carbon-methodology-registry/1.0"
EVIDENCE_GRAPH_SCHEMA_VERSION = "sc-carbon-evidence-methodology-graph/1.0"
PROJECT_OBJECT_MODEL_SCHEMA_VERSION = "sc-carbon-project-object-model/1.0"
PROJECT_OBJECT_TYPE_SCHEMA_VERSION = "sc-carbon-project-object-type-registry/1.0"
PROJECT_PROVENANCE_SCHEMA_VERSION = "sc-carbon-project-provenance/1.0"
PROJECT_PACKET_SCHEMA_VERSION = "sc-carbon-project-packet/1.0"
PROJECT_PACKET_VALIDATION_SCHEMA_VERSION = "sc-carbon-project-packet-validation/1.0"
AFOLU_RESEARCH_LIBRARIAN_SCHEMA_VERSION = "sc-afolu-research-librarian-intelligence/1.0"
AFOLU_RESEARCH_GUIDANCE_SCHEMA_VERSION = "sc-afolu-research-guidance/1.0"
AFOLU_RESEARCH_INTENT_SCHEMA_VERSION = "sc-afolu-research-intent-registry/1.0"
AFOLU_SOURCE_ROLE_SCHEMA_VERSION = "sc-afolu-research-source-role-registry/1.0"


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


@dataclass(frozen=True)
class CarbonEvidenceRecord:
    key: str
    title: str
    record_type: str
    authority_class: str
    publication_year: int | None
    jurisdiction: str
    scope: str
    concept_keys: tuple[str, ...] = ()
    measure_keys: tuple[str, ...] = ()
    methodology_keys: tuple[str, ...] = ()
    source_identity: str = ""
    version_context: str = ""
    rights_status: str = "review-required"
    evidence_status: str = "reference-seed"
    notes: tuple[str, ...] = ()

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        for field in ("concept_keys", "measure_keys", "methodology_keys", "notes"):
            row[field] = list(row[field])
        return row


@dataclass(frozen=True)
class CarbonMethodologyProfile:
    key: str
    label: str
    method_family: str
    purpose: str
    target_outcomes: tuple[str, ...]
    applicable_measure_keys: tuple[str, ...]
    concept_keys: tuple[str, ...]
    required_inputs: tuple[str, ...]
    uncertainty_requirements: tuple[str, ...]
    verification_requirements: tuple[str, ...]
    applicability_notes: tuple[str, ...] = ()
    method_status: str = "review-required"

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        for field in (
            "target_outcomes", "applicable_measure_keys", "concept_keys", "required_inputs",
            "uncertainty_requirements", "verification_requirements", "applicability_notes",
        ):
            row[field] = list(row[field])
        return row


@dataclass(frozen=True)
class CarbonEvidenceGraphEdge:
    subject_type: str
    subject_key: str
    predicate: str
    object_type: str
    object_key: str
    provenance_requirement: str = "explicit-source-or-governed-domain-link"
    inference_allowed: bool = False

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class CarbonProjectObjectTypeProfile:
    key: str
    label: str
    purpose: str
    required_payload_fields: tuple[str, ...]
    optional_payload_fields: tuple[str, ...]
    allowed_parent_types: tuple[str, ...]
    external_reference_fields: tuple[str, ...]
    provenance_expectations: tuple[str, ...]
    lifecycle_states: tuple[str, ...] = ("draft", "review", "accepted", "superseded")

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        for field in (
            "required_payload_fields", "optional_payload_fields", "allowed_parent_types",
            "external_reference_fields", "provenance_expectations", "lifecycle_states",
        ):
            row[field] = list(row[field])
        return row


@dataclass(frozen=True)
class CarbonProvenanceEventTypeProfile:
    key: str
    label: str
    purpose: str
    required_fields: tuple[str, ...]
    evidence_expectations: tuple[str, ...]
    chain_semantics: str

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        row["required_fields"] = list(self.required_fields)
        row["evidence_expectations"] = list(self.evidence_expectations)
        return row


@dataclass(frozen=True)
class CarbonProjectLinkTypeProfile:
    key: str
    label: str
    purpose: str
    subject_types: tuple[str, ...]
    object_types: tuple[str, ...]
    inference_allowed: bool = False

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        row["subject_types"] = list(self.subject_types)
        row["object_types"] = list(self.object_types)
        return row


@dataclass(frozen=True)
class AFOLUResearchIntentProfile:
    key: str
    label: str
    purpose: str
    trigger_terms: tuple[str, ...]
    research_questions: tuple[str, ...]
    evidence_roles: tuple[str, ...]
    handoff_targets: tuple[str, ...]
    caution_flags: tuple[str, ...] = ()

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        for field in ("trigger_terms", "research_questions", "evidence_roles", "handoff_targets", "caution_flags"):
            row[field] = list(row[field])
        return row


@dataclass(frozen=True)
class AFOLUSourceRoleProfile:
    key: str
    label: str
    purpose: str
    preferred_authority_classes: tuple[str, ...]
    record_types: tuple[str, ...]
    minimum_requirements: tuple[str, ...]
    freshness_sensitive: bool = False

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        for field in ("preferred_authority_classes", "record_types", "minimum_requirements"):
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


METHODOLOGIES: tuple[CarbonMethodologyProfile, ...] = (
    CarbonMethodologyProfile(
        "soc-direct-measurement",
        "SOC Direct Measurement",
        "direct-measurement",
        "Measurement-centered estimation of soil organic carbon stocks or stock change using field sampling, laboratory analysis, explicit depth, bulk density, and sampling design.",
        ("soil-carbon-stock-change",),
        ("improved-crop-rotation", "cover-crop-system", "reduced-tillage-system", "crop-residue-management", "soil-nutrient-management-practice", "agroforestry-establishment-management", "grassland-restoration-management"),
        ("soil-organic-carbon", "soc-stock", "soc-concentration", "bulk-density", "soil-depth", "direct-measurement", "sampling-methodology", "uncertainty"),
        ("sampling frame and strata", "sample locations and depths", "SOC concentration", "bulk density", "coarse-fragment or equivalent mass context where applicable", "baseline and monitoring dates"),
        ("sampling variance must be reported", "measurement error must be separated where possible", "depth or equivalent-soil-mass comparability must be documented", "minimum detectable change should be considered for change claims"),
        ("laboratory and field methods must be traceable", "sampling design and exclusions must be retained", "calculations must be reproducible from source observations"),
        ("Method presence does not establish project eligibility.", "A direct-measurement approach may still require models or auxiliary data for spatial or temporal inference."),
    ),
    CarbonMethodologyProfile(
        "soc-sampling-design",
        "SOC Sampling Design",
        "sampling-methodology",
        "Design of spatial and temporal soil sampling used to support defensible SOC estimation, change detection, uncertainty characterization, and repeat monitoring.",
        ("soil-carbon-stock-change",),
        ("improved-crop-rotation", "cover-crop-system", "reduced-tillage-system", "crop-residue-management", "soil-nutrient-management-practice", "agroforestry-establishment-management", "grassland-restoration-management"),
        ("soil-organic-carbon", "soc-stock", "soil-depth", "sampling-methodology", "uncertainty"),
        ("project or study boundary", "stratification variables", "sample-size assumptions", "spatial allocation", "sampling depth", "repeat-sampling design"),
        ("sampling uncertainty must be explicit", "design assumptions must be retained", "power or detectable-change implications should be documented"),
        ("randomization or selection rules must be auditable", "field deviations must be recorded", "repeat sampling must preserve comparability"),
        ("This profile defines a methodology family, not a prescriptive universal sampling protocol.",),
    ),
    CarbonMethodologyProfile(
        "modeled-carbon-stock-change",
        "Modeled Carbon Stock Change",
        "modeling-methodology",
        "Model-based estimation of carbon stock or stock change using documented inputs, calibration, validation, assumptions, scenario boundaries, and uncertainty.",
        ("soil-carbon-stock-change", "biomass-carbon-stock-change"),
        tuple(item.key for item in MEASURES),
        ("modeling-methodology", "uncertainty", "soil-organic-carbon", "aboveground-biomass", "belowground-biomass"),
        ("model identity and version", "parameter set", "input data lineage", "baseline assumptions", "management scenario", "calibration data where available", "validation evidence"),
        ("parameter, input, structural, and scenario uncertainty should be distinguished where material", "out-of-domain extrapolation must be flagged"),
        ("model version and configuration must be retained", "input lineage must be reproducible", "validation scope must be stated"),
        ("A model result is not evidence of methodology eligibility by itself.", "Modeled estimates should not be presented with precision unsupported by model and input uncertainty."),
    ),
    CarbonMethodologyProfile(
        "hybrid-measurement-modeling",
        "Hybrid Measurement & Modeling MRV",
        "hybrid-mrv",
        "Combined measurement and modeling approach in which observations constrain, calibrate, validate, or update model-based estimates across space or time.",
        ("soil-carbon-stock-change", "biomass-carbon-stock-change", "ghg-flux-change", "avoided-carbon-loss"),
        tuple(item.key for item in MEASURES),
        ("hybrid-mrv", "direct-measurement", "sampling-methodology", "modeling-methodology", "uncertainty"),
        ("measurement dataset", "sampling design", "model identity and inputs", "data-model integration rule", "monitoring schedule", "baseline definition"),
        ("measurement and model uncertainty must not be collapsed without explanation", "data-model mismatch and extrapolation limits must be documented"),
        ("measurement and model versions must be traceable", "integration rules must be reproducible", "validation and review checkpoints must be retained"),
        ("Hybrid MRV can reduce some monitoring burdens but does not remove the need to characterize uncertainty.",),
    ),
    CarbonMethodologyProfile(
        "biomass-inventory-measurement",
        "Biomass Inventory Measurement",
        "direct-measurement",
        "Field-inventory and allometric measurement family for woody or herbaceous biomass carbon pools with explicit plot design, species or functional-group context, equations, and uncertainty.",
        ("biomass-carbon-stock-change",),
        ("agroforestry-establishment-management", "grassland-restoration-management", "woodland-establishment-measure", "wetland-restoration-measure"),
        ("aboveground-biomass", "belowground-biomass", "direct-measurement", "sampling-methodology", "uncertainty"),
        ("plot or transect design", "species or vegetation class", "diameter/height or relevant field measurements", "allometric equation identity", "wood density or equivalent parameters where required", "area expansion factors"),
        ("sampling and allometric uncertainty must be retained", "equation transferability should be reviewed"),
        ("field measurements and equation versions must be auditable", "pool boundaries must be explicit", "double counting between biomass pools must be prevented"),
        ("Belowground estimates may be directly measured or modeled; the method used must be explicit.",),
    ),
    CarbonMethodologyProfile(
        "wetland-multigas-monitoring",
        "Wetland & Peatland Multi-Gas Monitoring",
        "hybrid-mrv",
        "MRV family for wetland and peatland interventions requiring explicit treatment of carbon dioxide, methane, and where material nitrous oxide together with hydrology and condition.",
        ("avoided-carbon-loss", "soil-carbon-stock-change", "ghg-flux-change"),
        ("wetland-restoration-measure", "peatland-restoration-rewetting"),
        ("wetland", "peatland", "carbon-dioxide", "methane", "nitrous-oxide", "hybrid-mrv", "uncertainty"),
        ("hydrological condition", "land-use and drainage baseline", "multi-gas observations or defensible factors/models", "vegetation/condition indicators", "monitoring period"),
        ("temporal variability and gas-specific uncertainty must be retained", "methane trade-offs must not be hidden by CO2-only reporting"),
        ("gas-specific data lineage must be retained", "hydrological intervention status must be auditable", "net-climate interpretation must show included and excluded gases"),
        ("Rewetting or restoration is not assumed to produce a net climate benefit without a multi-gas assessment appropriate to the claim.",),
    ),
    CarbonMethodologyProfile(
        "whole-system-ghg-accounting",
        "Whole-System GHG Accounting",
        "modeling-methodology",
        "Boundary-based accounting approach for material CO2, CH4, and N2O sources, sinks, stock changes, and displaced activities so that a carbon-pool improvement is not interpreted in isolation from the wider system.",
        ("ghg-flux-change", "soil-carbon-stock-change", "biomass-carbon-stock-change", "avoided-carbon-loss"),
        tuple(item.key for item in MEASURES),
        ("carbon-dioxide", "methane", "nitrous-oxide", "afolu", "modeling-methodology", "uncertainty", "leakage"),
        ("system boundary", "baseline", "activity data", "emission/removal factors or models", "carbon stock changes", "material displaced activities", "GWP/version context when CO2e is used"),
        ("factor and activity-data uncertainty should be reported", "boundary and leakage uncertainty must be explicit"),
        ("included and excluded sources/sinks must be listed", "factor/model versions must be retained", "aggregation to CO2e must retain GWP context"),
        ("This profile structures accounting context; it is not a national-inventory submission or carbon-credit methodology.",),
    ),
)


EVIDENCE_RECORDS: tuple[CarbonEvidenceRecord, ...] = (
    CarbonEvidenceRecord(
        "eu-carbon-farming-technical-guidance-2021",
        "Technical Guidance Handbook: Setting Up and Implementing Result-Based Carbon Farming Mechanisms in the EU",
        "technical-guidance",
        "public-technical-guidance",
        2021,
        "European Union",
        "Carbon farming feasibility, indicators, MRV, result-based payment, permanence, co-benefits, national-inventory context, and scheme design.",
        ("carbon-farming", "mrv", "additionality", "leakage", "permanence", "uncertainty", "result-based-payment", "national-ghg-inventory"),
        ("improved-crop-rotation", "cover-crop-system", "reduced-tillage-system", "crop-residue-management", "soil-nutrient-management-practice", "agroforestry-establishment-management", "grassland-restoration-management", "peatland-restoration-rewetting"),
        ("soc-direct-measurement", "soc-sampling-design", "modeled-carbon-stock-change", "hybrid-measurement-modeling", "wetland-multigas-monitoring", "whole-system-ghg-accounting"),
        "European Commission DG Climate Action technical guidance handbook",
        "January 2021 edition",
        "review-required",
        "reference-seed",
        ("Use as technical guidance and historical scheme-design context, not as a current regulatory entitlement determination.",),
    ),
    CarbonEvidenceRecord(
        "ipcc-2006-guidelines-volume-4-afolu",
        "2006 IPCC Guidelines for National Greenhouse Gas Inventories — Volume 4: Agriculture, Forestry and Other Land Use",
        "inventory-guidance",
        "intergovernmental-methodological-guidance",
        2006,
        "Global",
        "National greenhouse-gas inventory methods and AFOLU accounting framework.",
        ("afolu", "national-ghg-inventory", "carbon-dioxide", "methane", "nitrous-oxide", "soil-organic-carbon", "aboveground-biomass", "belowground-biomass", "dead-wood", "litter"),
        tuple(item.key for item in MEASURES),
        ("modeled-carbon-stock-change", "biomass-inventory-measurement", "whole-system-ghg-accounting"),
        "Intergovernmental Panel on Climate Change 2006 Guidelines, Volume 4",
        "2006 Guidelines",
        "review-required",
        "reference-seed",
        ("National inventory guidance and project-credit methodologies are distinct governance contexts.",),
    ),
    CarbonEvidenceRecord(
        "ipcc-2019-refinement-afolu",
        "2019 Refinement to the 2006 IPCC Guidelines for National Greenhouse Gas Inventories — AFOLU",
        "inventory-guidance",
        "intergovernmental-methodological-guidance",
        2019,
        "Global",
        "Refinements to inventory methods, factors, and methodological treatment under the 2006 IPCC framework.",
        ("afolu", "national-ghg-inventory", "carbon-dioxide", "methane", "nitrous-oxide", "uncertainty"),
        tuple(item.key for item in MEASURES),
        ("modeled-carbon-stock-change", "whole-system-ghg-accounting"),
        "Intergovernmental Panel on Climate Change 2019 Refinement",
        "2019 Refinement",
        "review-required",
        "reference-seed",
        ("Applicability depends on the accounting purpose, tier, available data, and national methodological choices.",),
    ),
    CarbonEvidenceRecord(
        "measure-specific-research-evidence-bundle",
        "Measure-Specific Research Evidence Bundle",
        "evidence-template",
        "sustainable-catalyst-governed-template",
        None,
        "Context-specific",
        "Template for linking peer-reviewed studies, systematic reviews, field experiments, observational evidence, datasets, and contradictory findings to a measure and claim context.",
        ("research-evidence", "evidence", "uncertainty"),
        tuple(item.key for item in MEASURES),
        (),
        "Sustainable Catalyst evidence-object template",
        "Carbon & Nature v0.3.0",
        "not-applicable-template",
        "template",
        ("This is a graph template, not an external evidentiary source.", "Each populated evidence object must preserve source identity, publication date, geography, methods, and claim context."),
    ),
)



PROJECT_OBJECT_TYPES: tuple[CarbonProjectObjectTypeProfile, ...] = (
    CarbonProjectObjectTypeProfile(
        "project", "Carbon Project", "Root research/project identity that defines scope, jurisdiction, boundary, and lineage.",
        ("name", "jurisdiction", "boundary_statement"),
        ("description", "program_ref", "owner_ref", "start_date", "end_date", "status_note"),
        (), ("source_refs", "evidence_refs", "methodology_refs", "measure_refs"),
        ("record project creation source", "retain boundary changes as superseding versions", "do not overwrite prior accepted project state"),
    ),
    CarbonProjectObjectTypeProfile(
        "farm", "Farm / Management Unit", "Managed agricultural unit used to group parcels, practices, observations, and monitoring context.",
        ("name", "management_scope"),
        ("jurisdiction", "operator_ref", "land_use_summary", "area_value", "area_unit"),
        ("project",), ("source_refs", "evidence_refs"),
        ("record source of management-unit identity", "version material boundary or operator-scope changes"),
    ),
    CarbonProjectObjectTypeProfile(
        "parcel", "Parcel / Spatial Unit", "Stable spatial research unit for land-use, intervention, sampling, and monitoring linkage.",
        ("name", "land_use_system", "spatial_reference"),
        ("area_value", "area_unit", "soil_context", "hydrology_context", "geometry_ref", "administrative_area"),
        ("project", "farm"), ("source_refs", "evidence_refs"),
        ("retain spatial source and geometry reference", "version material boundary changes", "do not silently replace parcel identity"),
    ),
    CarbonProjectObjectTypeProfile(
        "baseline", "Baseline", "Explicit pre-intervention or counterfactual state with bounded period, basis, and supporting objects.",
        ("baseline_period_start", "baseline_period_end", "baseline_basis"),
        ("counterfactual_statement", "indicator_refs", "observation_refs", "model_run_refs", "uncertainty_note"),
        ("project", "farm", "parcel"), ("source_refs", "evidence_refs", "methodology_refs"),
        ("identify baseline basis and evidence", "record revisions rather than overwrite", "retain uncertainty and counterfactual assumptions"),
    ),
    CarbonProjectObjectTypeProfile(
        "intervention", "Intervention", "Project-specific implementation record linked to a governed Carbon Sequestration Measure Registry entry.",
        ("measure_key", "start_date", "implementation_status"),
        ("end_date", "practice_description", "extent_value", "extent_unit", "implementation_evidence_refs", "deviation_note"),
        ("project", "farm", "parcel"), ("source_refs", "evidence_refs", "methodology_refs", "measure_refs"),
        ("retain measure registry key", "record implementation evidence", "version material practice changes"),
    ),
    CarbonProjectObjectTypeProfile(
        "observation", "Observation", "Timestamped measured or reported value with unit, indicator, method context, and source lineage.",
        ("indicator", "value", "unit", "observed_at"),
        ("methodology_key", "instrument_ref", "quality_flag", "uncertainty_value", "uncertainty_unit", "notes"),
        ("project", "farm", "parcel", "baseline", "intervention", "monitoring-record"), ("source_refs", "methodology_refs"),
        ("retain observation time and source", "retain units exactly", "record corrections as new versions or superseding events"),
    ),
    CarbonProjectObjectTypeProfile(
        "sample", "Sample", "Physical or analytical sample identity linking collection context, depth/horizon, laboratory results, and custody.",
        ("sample_type", "collected_at", "sampling_method"),
        ("depth_or_horizon", "location_ref", "laboratory_ref", "chain_of_custody_ref", "result_observation_refs", "storage_note"),
        ("project", "farm", "parcel", "monitoring-record"), ("source_refs", "methodology_refs"),
        ("preserve sample identifier", "record collection event", "retain chain-of-custody reference when available"),
    ),
    CarbonProjectObjectTypeProfile(
        "model-run", "Model Run", "Reproducible model execution record preserving model identity, inputs, assumptions, outputs, and software context.",
        ("model_name", "model_version", "run_at", "input_object_ids", "output_summary"),
        ("code_ref", "environment_ref", "parameter_set", "assumption_refs", "output_object_ids", "uncertainty_summary"),
        ("project", "baseline", "intervention", "monitoring-record"), ("source_refs", "evidence_refs", "methodology_refs"),
        ("retain model and version", "retain input object fingerprints", "retain assumptions and software/code reference", "never replace prior model-run outputs in place"),
    ),
    CarbonProjectObjectTypeProfile(
        "monitoring-record", "Monitoring Record", "Bounded monitoring-period record linking indicators, observations, samples, methodologies, and QA/QC context.",
        ("monitoring_period_start", "monitoring_period_end", "indicators"),
        ("observation_refs", "sample_refs", "methodology_refs", "qa_qc_note", "deviation_note", "review_status"),
        ("project", "farm", "parcel", "intervention"), ("source_refs", "evidence_refs", "methodology_refs"),
        ("retain monitoring period", "retain method versions", "record deviations and QA/QC review"),
    ),
    CarbonProjectObjectTypeProfile(
        "verification-record", "Verification / Review Record", "Human review record capturing scope, reviewer identity reference, finding, evidence considered, and limitations.",
        ("review_scope", "reviewed_at", "reviewer_ref", "finding"),
        ("evidence_refs", "object_refs", "limitations", "follow_up_actions", "verification_standard_ref"),
        ("project", "baseline", "intervention", "monitoring-record", "model-run"), ("source_refs", "evidence_refs", "methodology_refs"),
        ("retain reviewer reference and review time", "retain evidence considered", "do not convert review presence into certification or credit issuance"),
    ),
)

PROVENANCE_EVENT_TYPES: tuple[CarbonProvenanceEventTypeProfile, ...] = (
    CarbonProvenanceEventTypeProfile("created", "Created", "Initial creation of a project object.", ("event_id", "object_id", "occurred_at", "actor_ref"), ("creation source or responsible system",), "May begin an object provenance chain."),
    CarbonProvenanceEventTypeProfile("imported", "Imported", "Object or source data imported from an external system or file.", ("event_id", "object_id", "occurred_at", "actor_ref", "source_refs"), ("source identity", "import mechanism or file reference"), "Preserve source identity and import time; do not imply source endorsement."),
    CarbonProvenanceEventTypeProfile("observed", "Observed", "Measurement or reported observation captured.", ("event_id", "object_id", "occurred_at", "actor_ref"), ("method/instrument or source reference",), "Links observation state to the event that produced it."),
    CarbonProvenanceEventTypeProfile("sampled", "Sampled", "Physical or analytical sample collected.", ("event_id", "object_id", "occurred_at", "actor_ref"), ("sampling method", "collection source or custody reference"), "Preserve sample identity and collection lineage."),
    CarbonProvenanceEventTypeProfile("transformed", "Transformed", "Derived object created from one or more input objects.", ("event_id", "object_id", "occurred_at", "actor_ref", "input_object_ids"), ("input object fingerprints", "transformation method"), "Derived state must reference inputs; transformation is not evidence validation."),
    CarbonProvenanceEventTypeProfile("modeled", "Modeled", "Model execution or model-derived object recorded.", ("event_id", "object_id", "occurred_at", "actor_ref", "input_object_ids"), ("model identity/version", "input fingerprints", "assumptions"), "Model output remains distinct from direct observation."),
    CarbonProvenanceEventTypeProfile("reviewed", "Reviewed", "Human or governed workflow review recorded.", ("event_id", "object_id", "occurred_at", "actor_ref"), ("review scope", "evidence considered"), "Review does not imply certification unless a separate authoritative record establishes it."),
    CarbonProvenanceEventTypeProfile("verified", "Verified", "Verification activity recorded with explicit scope and authority context.", ("event_id", "object_id", "occurred_at", "actor_ref"), ("verification scope", "authority/standard reference when applicable"), "Verification event alone does not issue credits or establish regulatory eligibility."),
    CarbonProvenanceEventTypeProfile("superseded", "Superseded", "Object version superseded by a later version without deleting prior state.", ("event_id", "object_id", "occurred_at", "actor_ref"), ("superseding object/version reference",), "Preserve prior version and chain continuity."),
)

PROJECT_LINK_TYPES: tuple[CarbonProjectLinkTypeProfile, ...] = (
    CarbonProjectLinkTypeProfile("contains", "Contains", "Hierarchical containment of project research objects.", ("project", "farm", "parcel"), ("farm", "parcel", "baseline", "intervention", "monitoring-record", "verification-record")),
    CarbonProjectLinkTypeProfile("baseline-for", "Baseline For", "Links a baseline to the scoped project unit or intervention it contextualizes.", ("baseline",), ("project", "farm", "parcel", "intervention")),
    CarbonProjectLinkTypeProfile("intervention-on", "Intervention On", "Links an intervention to a managed or spatial unit.", ("intervention",), ("farm", "parcel")),
    CarbonProjectLinkTypeProfile("observation-of", "Observation Of", "Links an observation to the object/context it describes.", ("observation",), ("farm", "parcel", "baseline", "intervention", "monitoring-record")),
    CarbonProjectLinkTypeProfile("sample-of", "Sample Of", "Links a sample to its project/spatial/monitoring context.", ("sample",), ("farm", "parcel", "monitoring-record")),
    CarbonProjectLinkTypeProfile("input-to-model-run", "Input To Model Run", "Links governed project objects used as model inputs.", ("baseline", "intervention", "observation", "sample", "monitoring-record"), ("model-run",)),
    CarbonProjectLinkTypeProfile("derived-from", "Derived From", "Explicit non-causal derivation lineage between project objects.", ("observation", "model-run", "monitoring-record", "verification-record"), ("baseline", "intervention", "observation", "sample", "model-run", "monitoring-record")),
    CarbonProjectLinkTypeProfile("monitoring-for", "Monitoring For", "Links a monitoring record to the scoped project or intervention.", ("monitoring-record",), ("project", "farm", "parcel", "intervention")),
    CarbonProjectLinkTypeProfile("verification-of", "Verification Of", "Links a review/verification record to the reviewed object.", ("verification-record",), ("project", "baseline", "intervention", "model-run", "monitoring-record")),
)


AFOLU_SOURCE_ROLES: tuple[AFOLUSourceRoleProfile, ...] = (
    AFOLUSourceRoleProfile(
        "authoritative-methodology-guidance", "Authoritative Methodology & Technical Guidance",
        "Establish definitions, accounting rules, method boundaries, measurement requirements, and versioned technical context.",
        ("intergovernmental-guidance", "government-guidance", "standards-body", "technical-guidance"),
        ("methodology-document", "inventory-guidance", "technical-guidance", "standard"),
        ("capture publisher and version", "retain publication/revision date", "distinguish guidance from project eligibility"), True,
    ),
    AFOLUSourceRoleProfile(
        "peer-reviewed-research", "Peer-Reviewed Research",
        "Assess empirical effects, mechanisms, heterogeneity, uncertainty, co-benefits, harms, and external validity.",
        ("peer-reviewed-research", "systematic-review", "research-institution"),
        ("research-evidence", "review", "meta-analysis", "dataset-publication"),
        ("capture study design", "retain geography and land-system context", "record uncertainty and limitations"), False,
    ),
    AFOLUSourceRoleProfile(
        "national-inventory-policy", "National Inventory & Policy Sources",
        "Interpret jurisdiction-specific GHG inventory treatment, land-use categories, mitigation targets, and policy context.",
        ("national-inventory-authority", "government-policy", "intergovernmental-guidance"),
        ("inventory-guidance", "national-inventory-report", "policy-document", "target-document"),
        ("record jurisdiction", "record reporting year/version", "do not equate project claims with national inventory accounting"), True,
    ),
    AFOLUSourceRoleProfile(
        "program-market-rules", "Program, Market & Payment Rules",
        "Interpret result-based payment, carbon-market, crediting, buffer, permanence, and transaction requirements for a named program.",
        ("program-owner", "registry", "regulator", "market-standard"),
        ("program-rulebook", "methodology-document", "registry-rule", "market-guidance"),
        ("name the program", "record current rule version", "separate market eligibility from scientific plausibility"), True,
    ),
    AFOLUSourceRoleProfile(
        "project-primary-data", "Project Primary Data",
        "Ground parcel, baseline, intervention, observation, sample, model-run, monitoring, and verification questions in project-specific records.",
        ("project-owner", "laboratory", "monitoring-system", "verifier"),
        ("project-object", "observation", "sample", "model-run", "monitoring-record", "verification-record"),
        ("retain stable object identity", "retain units and methods", "retain provenance and version history"), False,
    ),
    AFOLUSourceRoleProfile(
        "spatial-environmental-data", "Spatial & Environmental Context",
        "Characterize land cover, soils, climate, hydrology, peat/wetland status, disturbance risk, and other spatial constraints.",
        ("earth-observation", "government-dataset", "research-dataset"),
        ("geospatial-dataset", "soil-dataset", "climate-dataset", "hydrology-dataset"),
        ("record spatial resolution", "record observation period", "record uncertainty and coverage gaps"), True,
    ),
    AFOLUSourceRoleProfile(
        "economic-finance-data", "Economic & Finance Evidence",
        "Support cost, price, transaction-cost, adoption, opportunity-cost, payment, and financial viability analysis.",
        ("official-statistics", "program-owner", "peer-reviewed-research", "market-data"),
        ("economic-dataset", "market-data", "cost-study", "program-payment-schedule"),
        ("record currency and price year", "separate observed prices from scenarios", "retain transaction and MRV costs"), True,
    ),
    AFOLUSourceRoleProfile(
        "safeguards-stakeholder-evidence", "Safeguards & Stakeholder Evidence",
        "Assess biodiversity, water, food security, livelihoods, rights, implementation burden, social acceptability, and unintended effects.",
        ("local-authority", "community-evidence", "peer-reviewed-research", "conservation-authority"),
        ("safeguard-assessment", "stakeholder-record", "biodiversity-data", "water-data", "socioeconomic-study"),
        ("do not assume co-benefits", "record affected groups and geography", "seek negative and distributional effects"), False,
    ),
)

AFOLU_RESEARCH_INTENTS: tuple[AFOLUResearchIntentProfile, ...] = (
    AFOLUResearchIntentProfile(
        "measure-identification", "Identify AFOLU / NbS Measures",
        "Identify governed measure families relevant to a stated land system or research problem without ranking or declaring suitability.",
        ("identify", "options", "measure", "practice", "intervention", "sequestration option", "what can", "what could"),
        ("What land-use system and project boundary are in scope?", "Which carbon pools and greenhouse gases could plausibly be affected?", "Which governed measures match that scope and what evidence is required for each?"),
        ("authoritative-methodology-guidance", "peer-reviewed-research", "spatial-environmental-data"),
        ("library", "site-intelligence", "research-librarian"),
        ("measure-match-is-not-suitability",),
    ),
    AFOLUResearchIntentProfile(
        "viability-assessment", "Assess Measure Viability",
        "Frame scientific, operational, measurement, integrity, and adoption evidence needed to assess viability.",
        ("viable", "viability", "feasible", "feasibility", "suitable", "scalable", "practical", "barrier", "adoption"),
        ("What baseline and counterfactual define the question?", "What biophysical and operational constraints could limit performance?", "What MRV burden, uncertainty, permanence, leakage, and stakeholder constraints need evidence?"),
        ("peer-reviewed-research", "project-primary-data", "spatial-environmental-data", "safeguards-stakeholder-evidence"),
        ("research-librarian", "site-intelligence", "lab", "decision-studio"),
        ("viability-requires-project-context", "no-automatic-ranking"),
    ),
    AFOLUResearchIntentProfile(
        "measure-comparison", "Compare Measures",
        "Structure a non-ranking comparison of mechanisms, pools, gases, evidence, MRV burden, uncertainty, risks, and co-benefit contexts.",
        ("compare", "comparison", "versus", " vs ", "trade-off", "tradeoff", "which measure", "better"),
        ("Are the candidate measures being compared on the same boundary and outcome?", "How do mechanisms, target pools/gases, MRV requirements, uncertainty, risks, and co-benefit evidence differ?", "Which evidence gaps prevent a defensible comparison?"),
        ("peer-reviewed-research", "authoritative-methodology-guidance", "safeguards-stakeholder-evidence"),
        ("research-librarian", "lab", "decision-studio"),
        ("comparison-is-not-ranking", "common-boundary-required"),
    ),
    AFOLUResearchIntentProfile(
        "mrv-methodology", "Interpret MRV & Methodology",
        "Route monitoring, measurement, reporting, verification, sampling, modeling, and detectability questions to versioned method evidence.",
        ("mrv", "monitoring", "measurement", "reporting", "verification", "sampling", "methodology", "method", "detectable", "uncertainty"),
        ("What outcome and reporting unit must be measured?", "Is direct measurement, modeling, proxy, or hybrid evidence appropriate to investigate?", "What sampling, QA/QC, uncertainty, frequency, and verification requirements must be resolved?"),
        ("authoritative-methodology-guidance", "peer-reviewed-research", "project-primary-data"),
        ("research-librarian", "lab"),
        ("methodology-match-is-not-eligibility", "verification-record-is-not-certification"),
    ),
    AFOLUResearchIntentProfile(
        "evidence-assessment", "Assess Evidence & Uncertainty",
        "Identify evidence types, contradictory findings, scope conditions, uncertainty, provenance, and source gaps needed for a research conclusion.",
        ("evidence", "research", "study", "studies", "support", "uncertainty", "confidence", "proof", "literature"),
        ("What claim is actually being investigated?", "What evidence supports, qualifies, contradicts, or fails to address the claim?", "How transferable are results across soils, climates, management systems, and time horizons?"),
        ("peer-reviewed-research", "authoritative-methodology-guidance"),
        ("library", "research-librarian"),
        ("evidence-match-is-not-claim-validation", "seek-counterevidence"),
    ),
    AFOLUResearchIntentProfile(
        "inventory-accounting", "Interpret National GHG Inventory Accounting",
        "Frame project results against current jurisdiction-specific inventory categories, gases, pools, methods, factors, and reporting years without asserting equivalence.",
        ("inventory", "ipcc", "national greenhouse", "ghg inventory", "emission factor", "tier", "land-use category", "nir", "crf"),
        ("Which jurisdiction, reporting year, land-use category, carbon pool, and gas are in scope?", "Which current inventory method and factor set applies?", "How should project-scale evidence be kept distinct from national inventory reporting and target accounting?"),
        ("national-inventory-policy", "authoritative-methodology-guidance"),
        ("research-librarian", "decision-studio"),
        ("project-credit-is-not-national-inventory-reduction", "current-rule-check-required"),
    ),
    AFOLUResearchIntentProfile(
        "policy-target-contribution", "Assess Policy / Mitigation Target Contribution",
        "Structure research on whether and how a measure or program could contribute to a named policy or mitigation target.",
        ("policy", "target", "mitigation target", "ndc", "national target", "climate plan", "contribution"),
        ("What policy instrument and target definition are in scope?", "What accounting boundary and implementation pathway connects the intervention to the target?", "What current legal, inventory, and program evidence is required before claiming contribution?"),
        ("national-inventory-policy", "authoritative-methodology-guidance", "peer-reviewed-research"),
        ("research-librarian", "decision-studio"),
        ("policy-equivalence-not-automatic", "current-rule-check-required"),
    ),
    AFOLUResearchIntentProfile(
        "monetisation-finance", "Interpret Monetisation & Carbon Finance",
        "Route carbon-price, result-based payment, crediting, buffer, permanence, transaction-cost, and financial viability questions to named current rules and economic evidence.",
        ("monet", "credit", "carbon market", "payment", "price", "finance", "result-based", "result based", "buffer", "revenue"),
        ("Which program, market, payment instrument, or scenario is being considered?", "What quantified outcome, eligibility rule, MRV cost, transaction cost, buffer/risk treatment, and payment timing are required?", "Which assumptions belong in scenario analysis rather than factual claims?"),
        ("program-market-rules", "economic-finance-data", "authoritative-methodology-guidance"),
        ("research-librarian", "workbench", "decision-studio"),
        ("market-rule-version-required", "crediting-not-implied"),
    ),
    AFOLUResearchIntentProfile(
        "nature-based-co-benefits", "Assess Nature-Based Solution Co-Benefits & Safeguards",
        "Frame evidence for biodiversity, water, food security, health, disaster-risk reduction, adaptation, livelihoods, trade-offs, and do-no-harm safeguards.",
        ("nature-based", "nature based", "nbs", "biodiversity", "water security", "food security", "health", "disaster", "co-benefit", "cobenefit", "livelihood"),
        ("Which societal challenge and ecosystem outcome are being claimed?", "What indicators and counterfactual evidence would demonstrate the co-benefit?", "What negative, distributional, biodiversity, water, or livelihood effects must also be investigated?"),
        ("peer-reviewed-research", "safeguards-stakeholder-evidence", "spatial-environmental-data"),
        ("research-librarian", "site-intelligence", "decision-studio"),
        ("co-benefit-is-not-assumed", "do-no-harm-review-required"),
    ),
    AFOLUResearchIntentProfile(
        "project-provenance", "Structure Project Data & Provenance",
        "Route baseline, parcel, intervention, observation, sample, model-run, monitoring, verification, and lineage questions into the governed project object model.",
        ("baseline", "parcel", "project object", "provenance", "sample", "observation", "model run", "monitoring record", "verification record", "lineage"),
        ("Which project objects are required and how are they linked?", "Which source/provenance event establishes each object state?", "Which object versions and fingerprints must be retained for reproducibility?"),
        ("project-primary-data", "authoritative-methodology-guidance"),
        ("library", "workspace", "research-librarian"),
        ("structural-validation-is-not-scientific-verification",),
    ),
    AFOLUResearchIntentProfile(
        "negative-emissions-scope", "Compare Natural & Engineered Removal Scope",
        "Recognize negative-emissions questions while explicitly routing engineered-removal comparison to a later governed registry rather than fabricating missing coverage.",
        ("negative emissions", "carbon removal", "engineered removal", "dac", "direct air capture", "beccs", "bioenergy with carbon capture"),
        ("Is the question limited to AFOLU/NbS or does it require engineered removal technologies?", "Which common comparison dimensions are required: permanence, land, energy, cost, maturity, uncertainty, biodiversity, water, scalability?", "Which evidence must be added before cross-technology comparison is defensible?"),
        ("peer-reviewed-research", "authoritative-methodology-guidance", "economic-finance-data"),
        ("research-librarian", "lab", "decision-studio"),
        ("engineered-removal-registry-not-yet-present", "do-not-fill-missing-registry-with-assumptions"),
    ),
)

def _build_evidence_graph_edges() -> tuple[CarbonEvidenceGraphEdge, ...]:
    edges: list[CarbonEvidenceGraphEdge] = []
    for methodology in METHODOLOGIES:
        edges.append(CarbonEvidenceGraphEdge("methodology", methodology.key, "uses-method-family", "concept", methodology.method_family))
        for concept_key in methodology.concept_keys:
            edges.append(CarbonEvidenceGraphEdge("methodology", methodology.key, "relates-to", "concept", concept_key))
        for measure_key in methodology.applicable_measure_keys:
            edges.append(CarbonEvidenceGraphEdge("methodology", methodology.key, "candidate-method-for", "measure", measure_key))
    for evidence in EVIDENCE_RECORDS:
        for concept_key in evidence.concept_keys:
            edges.append(CarbonEvidenceGraphEdge("evidence", evidence.key, "supports-context-for", "concept", concept_key))
        for measure_key in evidence.measure_keys:
            edges.append(CarbonEvidenceGraphEdge("evidence", evidence.key, "relevant-to", "measure", measure_key))
        for methodology_key in evidence.methodology_keys:
            edges.append(CarbonEvidenceGraphEdge("evidence", evidence.key, "documents-or-informs", "methodology", methodology_key))
    # Deterministic order and duplicate removal.
    unique: dict[tuple[str, str, str, str, str], CarbonEvidenceGraphEdge] = {}
    for edge in edges:
        key = (edge.subject_type, edge.subject_key, edge.predicate, edge.object_type, edge.object_key)
        unique[key] = edge
    return tuple(unique[key] for key in sorted(unique))


EVIDENCE_GRAPH_EDGES = _build_evidence_graph_edges()


class CarbonNatureKnowledgeFoundation:
    def __init__(self) -> None:
        self._concepts = {concept.key: concept for concept in CONCEPTS}
        self._relationships = RELATIONSHIPS
        self._measures = {measure.key: measure for measure in MEASURES}
        self._methodologies = {item.key: item for item in METHODOLOGIES}
        self._evidence = {item.key: item for item in EVIDENCE_RECORDS}
        self._evidence_graph_edges = EVIDENCE_GRAPH_EDGES
        self._project_object_types = {item.key: item for item in PROJECT_OBJECT_TYPES}
        self._provenance_event_types = {item.key: item for item in PROVENANCE_EVENT_TYPES}
        self._project_link_types = {item.key: item for item in PROJECT_LINK_TYPES}
        self._research_intents = {item.key: item for item in AFOLU_RESEARCH_INTENTS}
        self._source_roles = {item.key: item for item in AFOLU_SOURCE_ROLES}
        self._validate()

    def _validate(self) -> None:
        if len(self._concepts) != len(CONCEPTS):
            raise RuntimeError("duplicate Carbon & Nature concept key")
        if len(self._measures) != len(MEASURES):
            raise RuntimeError("duplicate Carbon & Nature measure key")
        if len(self._methodologies) != len(METHODOLOGIES):
            raise RuntimeError("duplicate Carbon & Nature methodology key")
        if len(self._evidence) != len(EVIDENCE_RECORDS):
            raise RuntimeError("duplicate Carbon & Nature evidence key")
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

        for methodology in METHODOLOGIES:
            if methodology.method_family not in self._concepts:
                raise RuntimeError(f"methodology {methodology.key} has unknown method family: {methodology.method_family}")
            for key in methodology.concept_keys:
                if key not in self._concepts:
                    raise RuntimeError(f"methodology {methodology.key} has unknown concept: {key}")
            for key in methodology.applicable_measure_keys:
                if key not in self._measures:
                    raise RuntimeError(f"methodology {methodology.key} has unknown measure: {key}")
        for evidence in EVIDENCE_RECORDS:
            for key in evidence.concept_keys:
                if key not in self._concepts:
                    raise RuntimeError(f"evidence {evidence.key} has unknown concept: {key}")
            for key in evidence.measure_keys:
                if key not in self._measures:
                    raise RuntimeError(f"evidence {evidence.key} has unknown measure: {key}")
            for key in evidence.methodology_keys:
                if key not in self._methodologies:
                    raise RuntimeError(f"evidence {evidence.key} has unknown methodology: {key}")
        if len(self._project_object_types) != len(PROJECT_OBJECT_TYPES):
            raise RuntimeError("duplicate Carbon & Nature project object type key")
        if len(self._provenance_event_types) != len(PROVENANCE_EVENT_TYPES):
            raise RuntimeError("duplicate Carbon & Nature provenance event type key")
        if len(self._project_link_types) != len(PROJECT_LINK_TYPES):
            raise RuntimeError("duplicate Carbon & Nature project link type key")
        for profile in PROJECT_OBJECT_TYPES:
            if not profile.required_payload_fields:
                raise RuntimeError(f"project object type {profile.key} has no required payload fields")
            for parent_type in profile.allowed_parent_types:
                if parent_type not in self._project_object_types:
                    raise RuntimeError(f"project object type {profile.key} has unknown parent type: {parent_type}")
        for link in PROJECT_LINK_TYPES:
            if link.inference_allowed:
                raise RuntimeError("Carbon project object links must remain non-inferential")
            for kind in (*link.subject_types, *link.object_types):
                if kind not in self._project_object_types:
                    raise RuntimeError(f"project link {link.key} has unknown object type: {kind}")

        if len(self._research_intents) != len(AFOLU_RESEARCH_INTENTS):
            raise RuntimeError("duplicate AFOLU Research Librarian intent key")
        if len(self._source_roles) != len(AFOLU_SOURCE_ROLES):
            raise RuntimeError("duplicate AFOLU Research Librarian source-role key")
        for intent in AFOLU_RESEARCH_INTENTS:
            for role in intent.evidence_roles:
                if role not in self._source_roles:
                    raise RuntimeError(f"research intent {intent.key} has unknown evidence role: {role}")
            if not intent.trigger_terms or not intent.research_questions:
                raise RuntimeError(f"research intent {intent.key} is incomplete")

        valid_node_sets = {
            "concept": set(self._concepts),
            "measure": set(self._measures),
            "methodology": set(self._methodologies),
            "evidence": set(self._evidence),
        }
        for edge in self._evidence_graph_edges:
            if edge.subject_type not in valid_node_sets or edge.subject_key not in valid_node_sets[edge.subject_type]:
                raise RuntimeError(f"evidence graph has unknown subject: {edge.subject_type}:{edge.subject_key}")
            if edge.object_type not in valid_node_sets or edge.object_key not in valid_node_sets[edge.object_type]:
                raise RuntimeError(f"evidence graph has unknown object: {edge.object_type}:{edge.object_key}")
            if edge.inference_allowed:
                raise RuntimeError("Carbon & Nature evidence graph edges must remain non-inferential")

    def manifest(self) -> dict[str, Any]:
        concepts = [item.to_dict() for item in CONCEPTS]
        relationships = [item.to_dict() for item in RELATIONSHIPS]
        measures = [item.to_dict() for item in MEASURES]
        methodologies = [item.to_dict() for item in METHODOLOGIES]
        evidence = [item.to_dict() for item in EVIDENCE_RECORDS]
        graph_edges = [item.to_dict() for item in EVIDENCE_GRAPH_EDGES]
        project_object_types = [item.to_dict() for item in PROJECT_OBJECT_TYPES]
        provenance_event_types = [item.to_dict() for item in PROVENANCE_EVENT_TYPES]
        project_link_types = [item.to_dict() for item in PROJECT_LINK_TYPES]
        research_intents = [item.to_dict() for item in AFOLU_RESEARCH_INTENTS]
        source_roles = [item.to_dict() for item in AFOLU_SOURCE_ROLES]
        return {
            "schema": SCHEMA_VERSION,
            "subsystem": {
                "key": "carbon-nature-intelligence",
                "name": "Carbon & Nature Intelligence",
                "version": DOMAIN_VERSION,
                "release": "AFOLU Research Librarian Intelligence",
                "primary_home": "Sustainable Catalyst Library",
                "library_release_line": "5.11.x",
                "backend_version": "2.6.0",
            },
            "coverage": {
                "concept_count": len(concepts),
                "relationship_count": len(relationships),
                "measure_count": len(measures),
                "methodology_count": len(methodologies),
                "evidence_record_count": len(evidence),
                "evidence_graph_edge_count": len(graph_edges),
                "project_object_type_count": len(project_object_types),
                "provenance_event_type_count": len(provenance_event_types),
                "project_link_type_count": len(project_link_types),
                "research_intent_count": len(research_intents),
                "research_source_role_count": len(source_roles),
                "concept_types": sorted({item["concept_type"] for item in concepts}),
                "domains": sorted({item["domain"] for item in concepts}),
                "measure_families": sorted({item["measure_family"] for item in measures}),
                "method_families": sorted({item["method_family"] for item in methodologies}),
                "evidence_record_types": sorted({item["record_type"] for item in evidence}),
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
                "carbon-evidence-record-registry",
                "carbon-methodology-profile-registry",
                "typed-evidence-methodology-graph",
                "evidence-methodology-neighborhood-packets",
                "evidence-and-methodology-aware-research-context",
                "library-evidence-linkage-ready",
                "research-librarian-evidence-context-ready",
                "carbon-project-object-type-registry",
                "carbon-project-link-type-registry",
                "carbon-project-provenance-event-model",
                "versioned-project-object-envelope",
                "deterministic-object-and-event-fingerprints",
                "stateless-project-packet-validation",
                "provenance-chain-continuity-validation",
                "project-packet-template",
                "project-object-model-research-context-ready",
                "afolu-research-intent-classification",
                "afolu-domain-aware-research-guidance",
                "afolu-source-role-planning",
                "afolu-evidence-gap-diagnostics",
                "afolu-policy-and-market-freshness-flags",
                "afolu-cross-product-research-routing",
                "afolu-project-aware-research-librarian-handoff",
            ],
            "governance": {
                "normative_standard_claimed": False,
                "carbon_credit_issuance": False,
                "certification_body": False,
                "automatic_nature_positive_score": False,
                "automatic_measure_ranking": False,
                "automatic_measure_suitability_determination": False,
                "automatic_methodology_selection": False,
                "automatic_methodology_eligibility_determination": False,
                "automatic_evidence_quality_grade": False,
                "automatic_claim_validation": False,
                "automatic_additionality_determination": False,
                "automatic_permanence_determination": False,
                "automatic_policy_equivalence": False,
                "automatic_research_conclusion_generation": False,
                "automatic_source_authority_determination": False,
                "automatic_current_rule_assertion": False,
                "research_guidance_is_deterministic_routing": True,
                "project_specific_mrv_protocol_builder": False,
                "project_packet_persistence": False,
                "automatic_project_claim_generation": False,
                "automatic_project_eligibility_determination": False,
                "cryptographic_attestation_or_signature_service": False,
                "project_object_validation_is_not_verification": True,
                "quantified_sequestration_potential": False,
                "soc_calculation_engine": False,
                "whole_farm_ghg_calculator": False,
                "research_only_foundation": True,
                "human_review_required_for_project_claims": True,
            },
            "boundaries": {
                "v0.1.0": "Domain ontology, concept identity, relationships, discovery, and context packets.",
                "v0.2.0": "Structured Carbon Sequestration Measure Registry with bounded filtering and comparison.",
                "v0.3.0": "Typed Carbon Evidence & Methodology Graph with evidence records, methodology profiles, neighborhoods, and research-context handoff.",
                "v0.4.0": "Versioned Carbon Project Object Model, project links, provenance events, deterministic fingerprints, and stateless packet validation.",
                "v0.5.0": "Deterministic AFOLU Research Librarian intent detection, question framing, source-role planning, evidence-gap diagnostics, freshness flags, and governed handoffs.",
                "v0.6.0": "Soil Organic Carbon Lab Foundation.",
            },
            "content_fingerprint": _stable_hash({
                "concepts": concepts,
                "relationships": relationships,
                "measures": measures,
                "methodologies": methodologies,
                "evidence": evidence,
                "evidence_graph_edges": graph_edges,
                "project_object_types": project_object_types,
                "provenance_event_types": provenance_event_types,
                "project_link_types": project_link_types,
                "research_intents": research_intents,
                "source_roles": source_roles,
            }),
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
            "linked_methodologies": [
                item.to_dict() for item in METHODOLOGIES if measure.key in item.applicable_measure_keys
            ],
            "linked_evidence": [
                item.to_dict() for item in EVIDENCE_RECORDS if measure.key in item.measure_keys
            ],
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
                "note": "v0.4.0 preserves bounded comparison and evidence/methodology context without ranking measures or determining project suitability.",
            },
            "content_fingerprint": _stable_hash({"keys": ordered, "measures": [measure.to_dict() for measure in measures]}),
        }

    def evidence_records(
        self,
        *,
        q: str | None = None,
        record_type: str | None = None,
        authority_class: str | None = None,
        concept: str | None = None,
        measure: str | None = None,
        methodology: str | None = None,
        limit: int = 50,
    ) -> dict[str, Any]:
        needle = str(q or "").strip()
        rows: list[CarbonEvidenceRecord] = []
        for item in EVIDENCE_RECORDS:
            if record_type and item.record_type.casefold() != str(record_type).casefold():
                continue
            if authority_class and item.authority_class.casefold() != str(authority_class).casefold():
                continue
            if concept and str(concept) not in item.concept_keys:
                continue
            if measure and str(measure) not in item.measure_keys:
                continue
            if methodology and str(methodology) not in item.methodology_keys:
                continue
            text = " ".join([
                item.key, item.title, item.record_type, item.authority_class, item.jurisdiction,
                item.scope, item.source_identity, item.version_context, *item.concept_keys,
                *item.measure_keys, *item.methodology_keys, *item.notes,
            ])
            if needle and self._score_text(needle, text) <= 0:
                continue
            rows.append(item)
        rows = rows[: max(1, min(int(limit), 100))]
        return {
            "schema": EVIDENCE_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "filters": {
                "q": q, "record_type": record_type, "authority_class": authority_class,
                "concept": concept, "measure": measure, "methodology": methodology,
            },
            "facets": {
                "record_types": sorted({item.record_type for item in EVIDENCE_RECORDS}),
                "authority_classes": sorted({item.authority_class for item in EVIDENCE_RECORDS}),
                "jurisdictions": sorted({item.jurisdiction for item in EVIDENCE_RECORDS}),
            },
            "count": len(rows),
            "evidence": [item.to_dict() for item in rows],
            "guardrails": {
                "association_is_not_claim_validation": True,
                "reference_seed_is_not_current_regulatory_entitlement": True,
                "evidence_quality_not_automatically_graded": True,
            },
        }

    def evidence_record(self, key: str) -> dict[str, Any]:
        item = self._evidence.get(str(key or "").strip())
        if item is None:
            raise KeyError(key)
        return {
            "schema": "sc-carbon-evidence-record/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "evidence": item.to_dict(),
            "linked_concepts": [self._concepts[key].to_dict() for key in item.concept_keys if key in self._concepts],
            "linked_measures": [self._measures[key].to_dict() for key in item.measure_keys if key in self._measures],
            "linked_methodologies": [self._methodologies[key].to_dict() for key in item.methodology_keys if key in self._methodologies],
            "guardrails": {
                "record_presence_is_not_endorsement": True,
                "record_presence_is_not_methodology_eligibility": True,
                "version_and_jurisdiction_review_required": True,
            },
            "content_fingerprint": _stable_hash(item.to_dict()),
        }

    def methodologies(
        self,
        *,
        q: str | None = None,
        family: str | None = None,
        measure: str | None = None,
        outcome: str | None = None,
        limit: int = 50,
    ) -> dict[str, Any]:
        needle = str(q or "").strip()
        rows: list[CarbonMethodologyProfile] = []
        for item in METHODOLOGIES:
            if family and item.method_family.casefold() != str(family).casefold():
                continue
            if measure and str(measure) not in item.applicable_measure_keys:
                continue
            if outcome and str(outcome) not in item.target_outcomes:
                continue
            text = " ".join([
                item.key, item.label, item.method_family, item.purpose, *item.target_outcomes,
                *item.applicable_measure_keys, *item.concept_keys, *item.required_inputs,
                *item.uncertainty_requirements, *item.verification_requirements,
                *item.applicability_notes,
            ])
            if needle and self._score_text(needle, text) <= 0:
                continue
            rows.append(item)
        rows = rows[: max(1, min(int(limit), 100))]
        return {
            "schema": METHODOLOGY_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "filters": {"q": q, "family": family, "measure": measure, "outcome": outcome},
            "facets": {
                "method_families": sorted({item.method_family for item in METHODOLOGIES}),
                "target_outcomes": sorted({value for item in METHODOLOGIES for value in item.target_outcomes}),
            },
            "count": len(rows),
            "methodologies": [item.to_dict() for item in rows],
            "guardrails": {
                "profile_is_not_project_methodology_approval": True,
                "automatic_methodology_selection": False,
                "eligibility_requires_current_rule_and_project_review": True,
            },
        }

    def methodology(self, key: str) -> dict[str, Any]:
        item = self._methodologies.get(str(key or "").strip())
        if item is None:
            raise KeyError(key)
        evidence = [record.to_dict() for record in EVIDENCE_RECORDS if item.key in record.methodology_keys]
        return {
            "schema": "sc-carbon-methodology-profile/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "methodology": item.to_dict(),
            "linked_concepts": [self._concepts[key].to_dict() for key in item.concept_keys if key in self._concepts],
            "linked_measures": [self._measures[key].to_dict() for key in item.applicable_measure_keys if key in self._measures],
            "linked_evidence": evidence,
            "guardrails": {
                "methodology_profile_is_not_certification": True,
                "project_eligibility_not_determined": True,
                "version_specific_rule_review_required": True,
            },
            "content_fingerprint": _stable_hash(item.to_dict()),
        }

    def _graph_node(self, node_type: str, key: str) -> dict[str, Any] | None:
        if node_type == "concept" and key in self._concepts:
            item = self._concepts[key]
            return {"node_type": "concept", "key": key, "label": item.label, "record": item.to_dict()}
        if node_type == "measure" and key in self._measures:
            item = self._measures[key]
            return {"node_type": "measure", "key": key, "label": item.label, "record": item.to_dict()}
        if node_type == "methodology" and key in self._methodologies:
            item = self._methodologies[key]
            return {"node_type": "methodology", "key": key, "label": item.label, "record": item.to_dict()}
        if node_type == "evidence" and key in self._evidence:
            item = self._evidence[key]
            return {"node_type": "evidence", "key": key, "label": item.title, "record": item.to_dict()}
        return None

    def evidence_graph(
        self,
        *,
        node_type: str | None = None,
        node_key: str | None = None,
        predicate: str | None = None,
        limit: int = 200,
    ) -> dict[str, Any]:
        requested_type = str(node_type or "").strip()
        requested_key = str(node_key or "").strip()
        requested_predicate = str(predicate or "").strip()
        edges: list[CarbonEvidenceGraphEdge] = []
        for edge in EVIDENCE_GRAPH_EDGES:
            if requested_predicate and edge.predicate != requested_predicate:
                continue
            if requested_type and requested_key:
                if not (
                    (edge.subject_type == requested_type and edge.subject_key == requested_key)
                    or (edge.object_type == requested_type and edge.object_key == requested_key)
                ):
                    continue
            elif requested_key and requested_key not in (edge.subject_key, edge.object_key):
                continue
            elif requested_type and requested_type not in (edge.subject_type, edge.object_type):
                continue
            edges.append(edge)
        edges = edges[: max(1, min(int(limit), 500))]
        node_refs = sorted({
            (edge.subject_type, edge.subject_key) for edge in edges
        } | {
            (edge.object_type, edge.object_key) for edge in edges
        })
        nodes = [self._graph_node(kind, key) for kind, key in node_refs]
        return {
            "schema": EVIDENCE_GRAPH_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "filters": {"node_type": node_type, "node_key": node_key, "predicate": predicate},
            "node_count": len([node for node in nodes if node is not None]),
            "edge_count": len(edges),
            "nodes": [node for node in nodes if node is not None],
            "edges": [edge.to_dict() for edge in edges],
            "governance": {
                "edge_inference_enabled": False,
                "graph_association_is_not_causal_proof": True,
                "graph_association_is_not_methodology_eligibility": True,
                "graph_association_is_not_project_suitability": True,
            },
            "content_fingerprint": _stable_hash([edge.to_dict() for edge in edges]),
        }

    def evidence_graph_neighborhood(self, node_key: str, *, limit: int = 100) -> dict[str, Any]:
        key = str(node_key or "").strip()
        if not key:
            raise ValueError("node_key is required")
        matches = []
        for kind, registry in (
            ("concept", self._concepts),
            ("measure", self._measures),
            ("methodology", self._methodologies),
            ("evidence", self._evidence),
        ):
            if key in registry:
                matches.append((kind, key))
        if not matches:
            raise KeyError(key)
        # Keys are designed to be stable and non-colliding; if a future collision appears, expose all matching types.
        edges = [
            edge for edge in EVIDENCE_GRAPH_EDGES
            if edge.subject_key == key or edge.object_key == key
        ][: max(1, min(int(limit), 300))]
        node_refs = sorted({
            (edge.subject_type, edge.subject_key) for edge in edges
        } | {
            (edge.object_type, edge.object_key) for edge in edges
        } | set(matches))
        nodes = [self._graph_node(kind, item_key) for kind, item_key in node_refs]
        return {
            "schema": "sc-carbon-evidence-methodology-neighborhood/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "focus": [self._graph_node(kind, item_key) for kind, item_key in matches],
            "nodes": [node for node in nodes if node is not None],
            "edges": [edge.to_dict() for edge in edges],
            "governance": {
                "inference_enabled": False,
                "human_interpretation_required": True,
            },
            "content_fingerprint": _stable_hash({"focus": matches, "edges": [edge.to_dict() for edge in edges]}),
        }


    def project_object_types(self) -> dict[str, Any]:
        rows = [item.to_dict() for item in PROJECT_OBJECT_TYPES]
        return {
            "schema": PROJECT_OBJECT_TYPE_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "count": len(rows),
            "object_types": rows,
            "guardrails": {
                "schema_presence_is_not_project_validation": True,
                "object_type_is_not_methodology_eligibility": True,
                "object_type_is_not_credit_eligibility": True,
            },
            "content_fingerprint": _stable_hash(rows),
        }

    def project_object_type(self, key: str) -> dict[str, Any]:
        item = self._project_object_types.get(str(key or "").strip())
        if item is None:
            raise KeyError(key)
        return {
            "schema": "sc-carbon-project-object-type/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "object_type": item.to_dict(),
            "guardrails": {
                "required_fields_are_structural_not_scientific_sufficiency": True,
                "project_claims_require_evidence_and_human_review": True,
            },
            "content_fingerprint": _stable_hash(item.to_dict()),
        }

    def provenance_event_types(self) -> dict[str, Any]:
        rows = [item.to_dict() for item in PROVENANCE_EVENT_TYPES]
        return {
            "schema": PROJECT_PROVENANCE_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "count": len(rows),
            "event_types": rows,
            "chain_contract": {
                "event_fingerprint": "sha256(canonical JSON of event excluding supplied fingerprint)",
                "previous_event_fingerprint": "optional pointer to the immediately prior event fingerprint for the same object",
                "immutability": "accepted prior events are retained; corrections should be represented by later events or object versions",
            },
            "guardrails": {
                "fingerprint_is_not_digital_signature": True,
                "provenance_chain_is_not_certification": True,
                "actor_ref_is_an_identifier_not_identity_proof": True,
            },
            "content_fingerprint": _stable_hash(rows),
        }

    def project_object_model(self) -> dict[str, Any]:
        object_types = [item.to_dict() for item in PROJECT_OBJECT_TYPES]
        events = [item.to_dict() for item in PROVENANCE_EVENT_TYPES]
        links = [item.to_dict() for item in PROJECT_LINK_TYPES]
        return {
            "schema": PROJECT_OBJECT_MODEL_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "object_envelope": {
                "required_fields": ["object_id", "object_type", "project_id", "version", "status", "payload", "source_refs", "provenance_refs"],
                "optional_fields": ["parent_object_ids", "evidence_refs", "methodology_refs", "measure_refs", "created_at", "effective_at", "content_fingerprint"],
                "identity_rules": [
                    "object_id is stable across versions of the same logical object",
                    "version is monotonically increasing for superseding states",
                    "project_id resolves to the root project object_id",
                    "content_fingerprint covers canonical object content excluding the supplied fingerprint",
                ],
            },
            "project_packet": {
                "schema": PROJECT_PACKET_SCHEMA_VERSION,
                "required_fields": ["schema", "objects", "provenance", "links"],
                "validation": "stateless; validation does not persist project data or establish scientific/regulatory validity",
            },
            "object_types": object_types,
            "provenance_event_types": events,
            "link_types": links,
            "external_reference_namespaces": {
                "measure_refs": MEASURE_SCHEMA_VERSION,
                "methodology_refs": METHODOLOGY_SCHEMA_VERSION,
                "evidence_refs": EVIDENCE_SCHEMA_VERSION,
                "source_refs": "Library source/record identifiers or governed external source references",
            },
            "governance": {
                "project_packet_persistence": False,
                "automatic_project_claim_generation": False,
                "automatic_project_eligibility_determination": False,
                "automatic_mrv_protocol_approval": False,
                "fingerprints_are_integrity_checks_not_signatures": True,
                "validation_is_structural_and_provenance_validation_not_scientific_verification": True,
            },
            "content_fingerprint": _stable_hash({"objects": object_types, "events": events, "links": links}),
        }

    def project_packet_template(self) -> dict[str, Any]:
        packet = {
            "schema": PROJECT_PACKET_SCHEMA_VERSION,
            "objects": [
                {
                    "object_id": "project:example-carbon-project",
                    "object_type": "project",
                    "project_id": "project:example-carbon-project",
                    "version": 1,
                    "status": "draft",
                    "payload": {
                        "name": "Example Carbon Project",
                        "jurisdiction": "replace-with-project-jurisdiction",
                        "boundary_statement": "replace-with-explicit-project-boundary",
                    },
                    "source_refs": [], "provenance_refs": ["event:project-created"],
                    "parent_object_ids": [], "evidence_refs": [], "methodology_refs": [], "measure_refs": [],
                },
                {
                    "object_id": "parcel:example-001",
                    "object_type": "parcel",
                    "project_id": "project:example-carbon-project",
                    "version": 1,
                    "status": "draft",
                    "payload": {
                        "name": "Example Parcel",
                        "land_use_system": "cropland",
                        "spatial_reference": "replace-with-governed-spatial-reference",
                    },
                    "source_refs": [], "provenance_refs": ["event:parcel-created"],
                    "parent_object_ids": ["project:example-carbon-project"], "evidence_refs": [], "methodology_refs": [], "measure_refs": [],
                },
            ],
            "provenance": [
                {"event_id": "event:project-created", "event_type": "created", "object_id": "project:example-carbon-project", "occurred_at": "2026-01-01T00:00:00+00:00", "actor_ref": "system:replace-me", "source_refs": []},
                {"event_id": "event:parcel-created", "event_type": "created", "object_id": "parcel:example-001", "occurred_at": "2026-01-01T00:00:00+00:00", "actor_ref": "system:replace-me", "source_refs": []},
            ],
            "links": [
                {"link_id": "link:project-contains-parcel", "predicate": "contains", "subject_id": "project:example-carbon-project", "object_id": "parcel:example-001"}
            ],
        }
        return {
            "schema": "sc-carbon-project-packet-template/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "template": packet,
            "notes": [
                "Replace placeholder content before use.",
                "Add evidence_refs, methodology_refs, and measure_refs only when the references actually support the object.",
                "Use new object versions and provenance events for corrections rather than overwriting accepted historical state.",
            ],
            "guardrails": {"template_is_not_a_valid_project_claim": True, "template_is_not_an_mrv_protocol": True},
        }

    @staticmethod
    def _is_nonempty(value: Any) -> bool:
        if value is None:
            return False
        if isinstance(value, str):
            return bool(value.strip())
        if isinstance(value, (list, tuple, dict, set)):
            return bool(value)
        return True

    @staticmethod
    def _parse_iso8601(value: Any) -> bool:
        if not isinstance(value, str) or not value.strip():
            return False
        try:
            datetime.fromisoformat(value.strip().replace("Z", "+00:00"))
            return True
        except ValueError:
            return False

    @staticmethod
    def _validate_ref_list(value: Any) -> bool:
        return isinstance(value, list) and all(isinstance(item, str) and bool(item.strip()) for item in value)

    def validate_project_packet(self, packet: dict[str, Any]) -> dict[str, Any]:
        errors: list[dict[str, Any]] = []
        warnings: list[dict[str, Any]] = []
        if not isinstance(packet, dict):
            return {"schema": PROJECT_PACKET_VALIDATION_SCHEMA_VERSION, "subsystem_version": DOMAIN_VERSION, "valid": False, "errors": [{"code": "packet-not-object", "path": "$", "message": "Project packet must be a JSON object."}], "warnings": [], "guardrails": {"validation_is_not_verification": True}}
        if packet.get("schema") != PROJECT_PACKET_SCHEMA_VERSION:
            errors.append({"code": "schema-mismatch", "path": "$.schema", "message": f"Expected {PROJECT_PACKET_SCHEMA_VERSION}."})
        objects = packet.get("objects")
        provenance = packet.get("provenance")
        links = packet.get("links")
        if not isinstance(objects, list): errors.append({"code": "objects-not-list", "path": "$.objects", "message": "objects must be a list."}); objects = []
        if not isinstance(provenance, list): errors.append({"code": "provenance-not-list", "path": "$.provenance", "message": "provenance must be a list."}); provenance = []
        if not isinstance(links, list): errors.append({"code": "links-not-list", "path": "$.links", "message": "links must be a list."}); links = []
        if len(objects) > 500: errors.append({"code": "too-many-objects", "path": "$.objects", "message": "Maximum 500 objects per validation packet."})
        if len(provenance) > 1000: errors.append({"code": "too-many-events", "path": "$.provenance", "message": "Maximum 1000 provenance events per validation packet."})
        if len(links) > 2000: errors.append({"code": "too-many-links", "path": "$.links", "message": "Maximum 2000 links per validation packet."})

        object_index: dict[str, dict[str, Any]] = {}
        object_fingerprints: dict[str, str] = {}
        project_ids: list[str] = []
        for idx, obj in enumerate(objects[:500]):
            path = f"$.objects[{idx}]"
            if not isinstance(obj, dict): errors.append({"code": "object-not-object", "path": path, "message": "Project object must be a JSON object."}); continue
            object_id = str(obj.get("object_id") or "").strip()
            object_type = str(obj.get("object_type") or "").strip()
            if not object_id: errors.append({"code": "missing-object-id", "path": path + ".object_id", "message": "object_id is required."}); continue
            if object_id in object_index: errors.append({"code": "duplicate-object-id", "path": path + ".object_id", "message": f"Duplicate object_id: {object_id}."}); continue
            object_index[object_id] = obj
            profile = self._project_object_types.get(object_type)
            if profile is None:
                errors.append({"code": "unknown-object-type", "path": path + ".object_type", "message": f"Unknown object_type: {object_type}."})
            else:
                payload = obj.get("payload")
                if not isinstance(payload, dict):
                    errors.append({"code": "payload-not-object", "path": path + ".payload", "message": "payload must be a JSON object."})
                    payload = {}
                for field in profile.required_payload_fields:
                    if not self._is_nonempty(payload.get(field)):
                        errors.append({"code": "missing-required-payload-field", "path": path + f".payload.{field}", "message": f"{object_type} requires payload field {field}."})
                if object_type == "project": project_ids.append(object_id)
            project_id = str(obj.get("project_id") or "").strip()
            if not project_id: errors.append({"code": "missing-project-id", "path": path + ".project_id", "message": "project_id is required."})
            version = obj.get("version")
            if not isinstance(version, int) or isinstance(version, bool) or version < 1:
                errors.append({"code": "invalid-version", "path": path + ".version", "message": "version must be an integer >= 1."})
            status = str(obj.get("status") or "").strip()
            if profile and status not in profile.lifecycle_states:
                errors.append({"code": "invalid-status", "path": path + ".status", "message": f"status must be one of: {', '.join(profile.lifecycle_states)}."})
            for field in ("source_refs", "provenance_refs"):
                if not self._validate_ref_list(obj.get(field)):
                    errors.append({"code": "invalid-ref-list", "path": path + f".{field}", "message": f"{field} must be a list of non-empty string identifiers."})
            for field in ("parent_object_ids", "evidence_refs", "methodology_refs", "measure_refs"):
                if field in obj and not self._validate_ref_list(obj.get(field)):
                    errors.append({"code": "invalid-ref-list", "path": path + f".{field}", "message": f"{field} must be a list of non-empty string identifiers."})
            for ref in obj.get("measure_refs", []) if isinstance(obj.get("measure_refs"), list) else []:
                if ref not in self._measures: errors.append({"code": "unknown-measure-ref", "path": path + ".measure_refs", "message": f"Unknown measure reference: {ref}."})
            payload = obj.get("payload") if isinstance(obj.get("payload"), dict) else {}
            measure_key = payload.get("measure_key") if object_type == "intervention" else None
            if measure_key and measure_key not in self._measures:
                errors.append({"code": "unknown-measure-key", "path": path + ".payload.measure_key", "message": f"Unknown measure_key: {measure_key}."})
            for ref in obj.get("methodology_refs", []) if isinstance(obj.get("methodology_refs"), list) else []:
                if ref not in self._methodologies: errors.append({"code": "unknown-methodology-ref", "path": path + ".methodology_refs", "message": f"Unknown methodology reference: {ref}."})
            for ref in obj.get("evidence_refs", []) if isinstance(obj.get("evidence_refs"), list) else []:
                if ref not in self._evidence: warnings.append({"code": "unresolved-evidence-ref", "path": path + ".evidence_refs", "message": f"Evidence reference is not in the v0.3 seed registry: {ref}. It may resolve to a broader Library source."})
            canonical_obj = {k: v for k, v in obj.items() if k != "content_fingerprint"}
            computed = _stable_hash(canonical_obj)
            object_fingerprints[object_id] = computed
            supplied = str(obj.get("content_fingerprint") or "").strip()
            if supplied and supplied != computed:
                errors.append({"code": "object-fingerprint-mismatch", "path": path + ".content_fingerprint", "message": "Supplied object fingerprint does not match canonical object content."})

        if len(project_ids) != 1:
            errors.append({"code": "project-root-count", "path": "$.objects", "message": "Packet must contain exactly one project root object."})
        root_project_id = project_ids[0] if len(project_ids) == 1 else None
        if root_project_id:
            for object_id, obj in object_index.items():
                if str(obj.get("project_id") or "").strip() != root_project_id:
                    errors.append({"code": "project-id-mismatch", "path": f"$.objects[{object_id}].project_id", "message": f"project_id must resolve to root project {root_project_id}."})
            for object_id, obj in object_index.items():
                profile = self._project_object_types.get(str(obj.get("object_type") or ""))
                parents = obj.get("parent_object_ids", []) if isinstance(obj.get("parent_object_ids"), list) else []
                for parent_id in parents:
                    parent = object_index.get(parent_id)
                    if parent is None:
                        errors.append({"code": "unresolved-parent-object", "path": f"$.objects[{object_id}].parent_object_ids", "message": f"Unresolved parent object: {parent_id}."})
                    elif profile and profile.allowed_parent_types and str(parent.get("object_type") or "") not in profile.allowed_parent_types:
                        errors.append({"code": "invalid-parent-type", "path": f"$.objects[{object_id}].parent_object_ids", "message": f"Parent type {parent.get('object_type')} is not allowed for {profile.key}."})

        event_index: dict[str, dict[str, Any]] = {}
        event_fingerprints: dict[str, str] = {}
        last_event_for_object: dict[str, str] = {}
        for idx, event in enumerate(provenance[:1000]):
            path = f"$.provenance[{idx}]"
            if not isinstance(event, dict): errors.append({"code": "event-not-object", "path": path, "message": "Provenance event must be a JSON object."}); continue
            event_id = str(event.get("event_id") or "").strip()
            event_type = str(event.get("event_type") or "").strip()
            object_id = str(event.get("object_id") or "").strip()
            if not event_id: errors.append({"code": "missing-event-id", "path": path + ".event_id", "message": "event_id is required."}); continue
            if event_id in event_index: errors.append({"code": "duplicate-event-id", "path": path + ".event_id", "message": f"Duplicate event_id: {event_id}."}); continue
            event_index[event_id] = event
            profile = self._provenance_event_types.get(event_type)
            if profile is None: errors.append({"code": "unknown-event-type", "path": path + ".event_type", "message": f"Unknown provenance event_type: {event_type}."})
            if object_id not in object_index: errors.append({"code": "unresolved-event-object", "path": path + ".object_id", "message": f"Provenance event object_id does not resolve: {object_id}."})
            if not self._parse_iso8601(event.get("occurred_at")): errors.append({"code": "invalid-event-time", "path": path + ".occurred_at", "message": "occurred_at must be an ISO-8601 timestamp."})
            if not str(event.get("actor_ref") or "").strip(): errors.append({"code": "missing-actor-ref", "path": path + ".actor_ref", "message": "actor_ref is required."})
            if "source_refs" in event and not self._validate_ref_list(event.get("source_refs")): errors.append({"code": "invalid-event-source-refs", "path": path + ".source_refs", "message": "source_refs must be a list of non-empty strings."})
            canonical_event = {k: v for k, v in event.items() if k != "event_fingerprint"}
            computed = _stable_hash(canonical_event)
            event_fingerprints[event_id] = computed
            supplied = str(event.get("event_fingerprint") or "").strip()
            if supplied and supplied != computed: errors.append({"code": "event-fingerprint-mismatch", "path": path + ".event_fingerprint", "message": "Supplied event fingerprint does not match canonical event content."})
            previous = str(event.get("previous_event_fingerprint") or "").strip()
            expected_previous = last_event_for_object.get(object_id)
            if previous and previous != expected_previous:
                errors.append({"code": "provenance-chain-break", "path": path + ".previous_event_fingerprint", "message": "previous_event_fingerprint does not match the immediately prior event for this object in packet order."})
            last_event_for_object[object_id] = computed

        for object_id, obj in object_index.items():
            refs = obj.get("provenance_refs", []) if isinstance(obj.get("provenance_refs"), list) else []
            for ref in refs:
                if ref not in event_index:
                    errors.append({"code": "unresolved-provenance-ref", "path": f"$.objects[{object_id}].provenance_refs", "message": f"Unresolved provenance event: {ref}."})
                elif str(event_index[ref].get("object_id") or "") != object_id:
                    errors.append({"code": "provenance-object-mismatch", "path": f"$.objects[{object_id}].provenance_refs", "message": f"Provenance event {ref} belongs to a different object."})

        link_ids: set[str] = set()
        normalized_links: list[dict[str, Any]] = []
        for idx, link in enumerate(links[:2000]):
            path = f"$.links[{idx}]"
            if not isinstance(link, dict): errors.append({"code": "link-not-object", "path": path, "message": "Project link must be a JSON object."}); continue
            link_id = str(link.get("link_id") or "").strip()
            predicate = str(link.get("predicate") or "").strip()
            subject_id = str(link.get("subject_id") or "").strip()
            object_id = str(link.get("object_id") or "").strip()
            if not link_id: errors.append({"code": "missing-link-id", "path": path + ".link_id", "message": "link_id is required."}); continue
            if link_id in link_ids: errors.append({"code": "duplicate-link-id", "path": path + ".link_id", "message": f"Duplicate link_id: {link_id}."}); continue
            link_ids.add(link_id)
            profile = self._project_link_types.get(predicate)
            if profile is None: errors.append({"code": "unknown-link-predicate", "path": path + ".predicate", "message": f"Unknown project link predicate: {predicate}."}); continue
            subject = object_index.get(subject_id); target = object_index.get(object_id)
            if subject is None: errors.append({"code": "unresolved-link-subject", "path": path + ".subject_id", "message": f"Unresolved subject object: {subject_id}."})
            if target is None: errors.append({"code": "unresolved-link-object", "path": path + ".object_id", "message": f"Unresolved object: {object_id}."})
            if subject and str(subject.get("object_type") or "") not in profile.subject_types: errors.append({"code": "invalid-link-subject-type", "path": path + ".subject_id", "message": f"{predicate} does not allow subject type {subject.get('object_type')}."})
            if target and str(target.get("object_type") or "") not in profile.object_types: errors.append({"code": "invalid-link-object-type", "path": path + ".object_id", "message": f"{predicate} does not allow object type {target.get('object_type')}."})
            normalized_links.append({"link_id": link_id, "predicate": predicate, "subject_id": subject_id, "object_id": object_id})

        packet_fingerprint = _stable_hash({
            "schema": packet.get("schema"),
            "objects": sorted(object_fingerprints.items()),
            "events": sorted(event_fingerprints.items()),
            "links": sorted(normalized_links, key=lambda item: item["link_id"]),
        }) if objects or provenance or links else None
        return {
            "schema": PROJECT_PACKET_VALIDATION_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "valid": not errors,
            "counts": {"objects": len(objects), "provenance_events": len(provenance), "links": len(links)},
            "project_id": root_project_id,
            "object_fingerprints": object_fingerprints,
            "event_fingerprints": event_fingerprints,
            "packet_fingerprint": packet_fingerprint,
            "errors": errors,
            "warnings": warnings,
            "guardrails": {
                "validation_is_not_scientific_verification": True,
                "validation_is_not_carbon_credit_eligibility": True,
                "fingerprints_are_not_digital_signatures": True,
                "packet_not_persisted": True,
            },
        }

    @staticmethod
    def _score_text(query: str, text: str) -> int:
        tokens = [token.casefold() for token in query.replace("/", " ").replace("-", " ").split() if len(token) >= 2]
        haystack = text.casefold()
        score = sum(1 for token in tokens if token in haystack)
        if query.casefold() in haystack:
            score += 4
        return score

    def _research_context_base(self, query: str, *, limit: int = 12) -> dict[str, Any]:
        query = str(query or "").strip()
        if not query:
            raise ValueError("query is required")
        bounded = max(1, min(int(limit), 30))

        scored_concepts: list[tuple[int, CarbonNatureConcept]] = []
        for concept in CONCEPTS:
            text = " ".join([concept.key, concept.label, concept.definition, *concept.synonyms, *concept.tags])
            score = self._score_text(query, text)
            if score:
                scored_concepts.append((score, concept))
        scored_concepts.sort(key=lambda item: (-item[0], item[1].label.casefold(), item[1].key))
        selected = [concept for _, concept in scored_concepts[:bounded]]
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
        selected_measures = [measure for _, measure in scored_measures[: min(bounded, 20)]]

        scored_methods: list[tuple[int, CarbonMethodologyProfile]] = []
        for item in METHODOLOGIES:
            text = " ".join([
                item.key, item.label, item.method_family, item.purpose, *item.target_outcomes,
                *item.applicable_measure_keys, *item.concept_keys, *item.required_inputs,
                *item.uncertainty_requirements, *item.verification_requirements, *item.applicability_notes,
            ])
            score = self._score_text(query, text)
            if score:
                scored_methods.append((score, item))
        scored_methods.sort(key=lambda pair: (-pair[0], pair[1].label.casefold(), pair[1].key))
        selected_methods = [item for _, item in scored_methods[: min(bounded, 12)]]

        scored_evidence: list[tuple[int, CarbonEvidenceRecord]] = []
        for item in EVIDENCE_RECORDS:
            text = " ".join([
                item.key, item.title, item.record_type, item.authority_class, item.jurisdiction, item.scope,
                item.source_identity, item.version_context, *item.concept_keys, *item.measure_keys,
                *item.methodology_keys, *item.notes,
            ])
            score = self._score_text(query, text)
            if score:
                scored_evidence.append((score, item))
        scored_evidence.sort(key=lambda pair: (-pair[0], pair[1].title.casefold(), pair[1].key))
        selected_evidence = [item for _, item in scored_evidence[: min(bounded, 12)]]

        scored_project_types: list[tuple[int, CarbonProjectObjectTypeProfile]] = []
        for item in PROJECT_OBJECT_TYPES:
            text = " ".join([item.key, item.label, item.purpose, *item.required_payload_fields, *item.optional_payload_fields, *item.provenance_expectations])
            score = self._score_text(query, text)
            if score:
                scored_project_types.append((score, item))
        scored_project_types.sort(key=lambda pair: (-pair[0], pair[1].label.casefold(), pair[1].key))
        selected_project_types = [item for _, item in scored_project_types[: min(bounded, 10)]]

        selected_graph_keys = {
            *selected_keys,
            *(item.key for item in selected_measures),
            *(item.key for item in selected_methods),
            *(item.key for item in selected_evidence),
        }
        graph_edges = [
            edge.to_dict() for edge in EVIDENCE_GRAPH_EDGES
            if edge.subject_key in selected_graph_keys or edge.object_key in selected_graph_keys
        ][:200]

        return {
            "schema": "sc-carbon-nature-research-context/1.4",
            "subsystem_version": DOMAIN_VERSION,
            "query": query,
            "concepts": [item.to_dict() for item in selected],
            "relationships": rels,
            "measures": [item.to_dict() for item in selected_measures],
            "methodologies": [item.to_dict() for item in selected_methods],
            "evidence": [item.to_dict() for item in selected_evidence],
            "evidence_graph_edges": graph_edges,
            "project_object_types": [item.to_dict() for item in selected_project_types],
            "project_object_model": {
                "schema": PROJECT_OBJECT_MODEL_SCHEMA_VERSION,
                "packet_schema": PROJECT_PACKET_SCHEMA_VERSION,
                "provenance_schema": PROJECT_PROVENANCE_SCHEMA_VERSION,
                "stateless_validation_ready": True,
                "persistence_enabled": False,
            },
            "evidence_retrieval": {
                "library_search_query": query,
                "recommended_domains": _unique([item.domain for item in selected] + [item.primary_domain for item in selected_measures]),
                "recommended_measure_keys": [item.key for item in selected_measures],
                "recommended_methodology_keys": [item.key for item in selected_methods],
                "recommended_evidence_keys": [item.key for item in selected_evidence],
                "evidence_object_types": ["research-evidence", "methodology-document", "policy-document", "inventory-guidance", "technical-guidance"],
            },
            "handoff": {
                "research_librarian_ready": True,
                "measure_registry_context_enabled": True,
                "evidence_methodology_graph_context_enabled": True,
                "project_object_model_context_enabled": True,
                "provenance_model_context_enabled": True,
                "domain_aware_reasoning_enabled": True,
                "afolu_research_librarian_intelligence_enabled": True,
                "note": "v0.5.0 adds deterministic AFOLU intent detection, evidence/source planning, gap diagnostics, freshness review, and governed handoffs while preserving non-inference boundaries.",
            },
            "guardrails": {
                "concept_match_is_not_evidence": True,
                "measure_match_is_not_project_suitability": True,
                "relationship_is_not_causal_proof": True,
                "evidence_match_is_not_claim_validation": True,
                "methodology_match_is_not_methodology_eligibility": True,
                "co_benefit_is_not_assumed": True,
                "project_credit_eligibility_not_determined": True,
                "project_object_validation_is_not_verification": True,
                "project_packet_persistence_disabled": True,
                "methodology_applicability_requires_review": True,
                "quantified_sequestration_not_inferred": True,
            },
            "content_fingerprint": _stable_hash({
                "query": query.casefold(),
                "concepts": [item.key for item in selected],
                "relationships": rels,
                "measures": [item.key for item in selected_measures],
                "methodologies": [item.key for item in selected_methods],
                "evidence": [item.key for item in selected_evidence],
                "project_object_types": [item.key for item in selected_project_types],
                "evidence_graph_edges": graph_edges,
            }),
        }

    def _intent_score(self, query: str, profile: AFOLUResearchIntentProfile) -> int:
        folded = f" {str(query or '').casefold()} "
        score = 0
        for term in profile.trigger_terms:
            needle = str(term).casefold().strip()
            if not needle:
                continue
            if needle in folded:
                score += 5 if " " in needle else 3
        score += min(4, self._score_text(query, " ".join([profile.label, profile.purpose, *profile.research_questions])))
        return score

    def _detect_research_intents(self, query: str, *, limit: int = 4) -> list[dict[str, Any]]:
        scored: list[tuple[int, AFOLUResearchIntentProfile]] = []
        for profile in AFOLU_RESEARCH_INTENTS:
            score = self._intent_score(query, profile)
            if score:
                scored.append((score, profile))
        scored.sort(key=lambda item: (-item[0], item[1].label.casefold(), item[1].key))
        if not scored:
            fallback = self._research_intents["evidence-assessment"]
            scored = [(1, fallback)]
        maximum = max(score for score, _ in scored) or 1
        return [
            {**profile.to_dict(), "match_score": score, "relative_match": round(score / maximum, 3)}
            for score, profile in scored[: max(1, min(int(limit), 6))]
        ]

    def research_intents(self) -> dict[str, Any]:
        return {
            "schema": AFOLU_RESEARCH_INTENT_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "count": len(AFOLU_RESEARCH_INTENTS),
            "intents": [item.to_dict() for item in AFOLU_RESEARCH_INTENTS],
            "guardrails": {
                "intent_match_is_not_answer": True,
                "intent_match_is_not_project_suitability": True,
                "intent_match_is_not_methodology_eligibility": True,
            },
            "content_fingerprint": _stable_hash([item.to_dict() for item in AFOLU_RESEARCH_INTENTS]),
        }

    def research_source_roles(self) -> dict[str, Any]:
        return {
            "schema": AFOLU_SOURCE_ROLE_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "count": len(AFOLU_SOURCE_ROLES),
            "source_roles": [item.to_dict() for item in AFOLU_SOURCE_ROLES],
            "guardrails": {
                "source_role_is_not_source_endorsement": True,
                "authority_requires_source_specific_review": True,
                "freshness_flag_requires_current_source_check": True,
            },
            "content_fingerprint": _stable_hash([item.to_dict() for item in AFOLU_SOURCE_ROLES]),
        }

    def research_librarian_manifest(self) -> dict[str, Any]:
        return {
            "schema": AFOLU_RESEARCH_LIBRARIAN_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "name": "AFOLU Research Librarian Intelligence",
            "mode": "deterministic-domain-research-routing",
            "intent_count": len(AFOLU_RESEARCH_INTENTS),
            "source_role_count": len(AFOLU_SOURCE_ROLES),
            "inputs": ["research question", "governed Carbon & Nature registries", "optional project-aware Research Librarian packet"],
            "outputs": ["detected intents", "research question frame", "source-role plan", "evidence gaps", "freshness review", "cross-product handoffs", "guardrails"],
            "integration": {
                "library_research_context": True,
                "project_aware_research_librarian_packet_augmentation": True,
                "private_project_notes_sent_to_library_backend": False,
                "project_aware_augmentation_sends_question_only": True,
                "optional_remote_synthesis_receives_domain_packet": False,
            },
            "guardrails": {
                "automatic_research_conclusion_generation": False,
                "automatic_measure_ranking": False,
                "automatic_methodology_selection": False,
                "automatic_current_rule_assertion": False,
                "automatic_project_eligibility_determination": False,
                "automatic_carbon_credit_issuance": False,
            },
            "content_fingerprint": _stable_hash({
                "intents": [item.to_dict() for item in AFOLU_RESEARCH_INTENTS],
                "source_roles": [item.to_dict() for item in AFOLU_SOURCE_ROLES],
            }),
        }

    def research_guidance(self, query: str, *, limit: int = 12) -> dict[str, Any]:
        query = str(query or "").strip()
        if not query:
            raise ValueError("query is required")
        bounded = max(1, min(int(limit), 30))
        context = self._research_context_base(query, limit=bounded)
        intents = self._detect_research_intents(query, limit=4)
        intent_keys = [item["key"] for item in intents]

        source_role_keys = _unique(role for item in intents for role in item.get("evidence_roles", []))
        source_roles = [self._source_roles[key].to_dict() for key in source_role_keys if key in self._source_roles]
        questions = _unique(question for item in intents for question in item.get("research_questions", []))[:12]
        caution_flags = _unique(flag for item in intents for flag in item.get("caution_flags", []))
        handoff_keys = _unique(target for item in intents for target in item.get("handoff_targets", []))

        concepts = context.get("concepts", [])
        measures = context.get("measures", [])
        methods = context.get("methodologies", [])
        evidence = context.get("evidence", [])
        project_types = context.get("project_object_types", [])
        current_year = datetime.now(timezone.utc).year
        freshness_cutoff = current_year - 2
        freshness_sensitive = any(self._source_roles[key].freshness_sensitive for key in source_role_keys if key in self._source_roles)
        stale_records = [
            {"key": item.get("key"), "title": item.get("title"), "publication_year": item.get("publication_year"), "version_context": item.get("version_context")}
            for item in evidence
            if isinstance(item.get("publication_year"), int) and item["publication_year"] < freshness_cutoff
        ]

        gaps: list[dict[str, Any]] = []
        if not evidence:
            gaps.append({"code": "no-matched-evidence-records", "severity": "high", "message": "No governed evidence record matched the question; retrieve source-specific evidence before synthesis."})
        elif all(str(item.get("evidence_status", "")) in {"reference-seed", "template"} for item in evidence):
            gaps.append({"code": "reference-seeds-require-source-retrieval", "severity": "medium", "message": "Matched registry records are routing/reference seeds; source text and current versions still need retrieval and review."})
        if any(key in intent_keys for key in ("mrv-methodology", "inventory-accounting")) and not methods:
            gaps.append({"code": "methodology-context-missing", "severity": "high", "message": "The question requires method or accounting interpretation but no methodology profile matched."})
        if any(key in intent_keys for key in ("viability-assessment", "monetisation-finance", "policy-target-contribution")):
            gaps.append({"code": "project-or-jurisdiction-specific-evidence-required", "severity": "medium", "message": "A defensible answer requires project, jurisdiction, program, or scenario evidence beyond the domain registry."})
        if "negative-emissions-scope" in intent_keys:
            gaps.append({"code": "engineered-removal-registry-not-yet-built", "severity": "high", "message": "The current Carbon & Nature registry is AFOLU/NbS-first; engineered removal comparison remains a later governed capability."})
        if freshness_sensitive and stale_records:
            gaps.append({"code": "current-rule-or-data-check-required", "severity": "high", "message": "One or more matched sources predate the current freshness window. Verify current policy, inventory, market, program, or dataset versions before making present-tense claims."})
        if measures and not evidence:
            gaps.append({"code": "measure-match-without-evidence", "severity": "high", "message": "Measure matches are discovery context only until linked evidence is retrieved and reviewed."})

        domain_scope = {
            "domains": _unique([item.get("domain", "") for item in concepts] + [item.get("primary_domain", "") for item in measures]),
            "concept_keys": [item.get("key") for item in concepts],
            "measure_keys": [item.get("key") for item in measures],
            "methodology_keys": [item.get("key") for item in methods],
            "evidence_keys": [item.get("key") for item in evidence],
            "project_object_types": [item.get("key") for item in project_types],
            "carbon_pools": [item.get("key") for item in concepts if item.get("concept_type") == "carbon-pool"],
            "greenhouse_gases": [item.get("key") for item in concepts if item.get("concept_type") == "greenhouse-gas"],
            "land_use_systems": [item.get("key") for item in concepts if item.get("concept_type") == "land-use-system"],
        }

        handoff_purpose = {
            "library": "Retrieve and organize governed sources, evidence records, methodologies, and project-object definitions.",
            "research-librarian": "Synthesize retrieved evidence with explicit uncertainty, contradiction, provenance, and scope limits.",
            "site-intelligence": "Add parcel, land-use, soils, climate, hydrology, ecosystem, and disturbance context.",
            "lab": "Perform governed SOC/GHG modeling, uncertainty propagation, sensitivity analysis, and later MRV calculations.",
            "workbench": "Run economic, unit, financial, and scenario calculations with explicit inputs.",
            "decision-studio": "Assess feasibility, integrity, trade-offs, policy/market implications, and decision boundaries.",
            "workspace": "Persist project work, notes, source bundles, model artifacts, and reproducible analysis packets.",
        }
        handoffs = [{"target": key, "purpose": handoff_purpose.get(key, "Continue the governed research workflow.")} for key in handoff_keys]

        coverage_points = len(concepts) + len(measures) * 2 + len(methods) * 2 + len(evidence) * 2
        coverage = "strong-routing-context" if coverage_points >= 12 else ("partial-routing-context" if coverage_points >= 5 else "thin-routing-context")

        packet = {
            "schema": AFOLU_RESEARCH_GUIDANCE_SCHEMA_VERSION,
            "subsystem_version": DOMAIN_VERSION,
            "query": query,
            "reasoning_mode": "deterministic-domain-research-routing",
            "detected_intents": intents,
            "routing_context_coverage": coverage,
            "domain_scope": domain_scope,
            "research_question_frame": questions,
            "source_plan": {
                "roles": source_roles,
                "priority_role_keys": source_role_keys,
                "library_search_query": context.get("evidence_retrieval", {}).get("library_search_query", query),
                "recommended_evidence_object_types": context.get("evidence_retrieval", {}).get("evidence_object_types", []),
            },
            "matched_context": {
                "concept_count": len(concepts),
                "measure_count": len(measures),
                "methodology_count": len(methods),
                "evidence_count": len(evidence),
                "project_object_type_count": len(project_types),
                "concepts": concepts[:bounded],
                "measures": measures[: min(bounded, 12)],
                "methodologies": methods[: min(bounded, 10)],
                "evidence": evidence[: min(bounded, 10)],
                "project_object_types": project_types[: min(bounded, 10)],
            },
            "evidence_gaps": gaps,
            "freshness_review": {
                "current_year": current_year,
                "freshness_cutoff_year": freshness_cutoff,
                "freshness_sensitive_intent": freshness_sensitive,
                "older_matched_records": stale_records,
                "current_source_check_required": bool(freshness_sensitive and stale_records),
            },
            "handoffs": handoffs,
            "answer_contract": {
                "may": [
                    "identify governed domain concepts and candidate measures",
                    "frame research questions and evidence needs",
                    "distinguish measurement, modeling, evidence, policy, and project-object roles",
                    "surface uncertainty, provenance, freshness, contradiction, and scope gaps",
                    "route analysis to appropriate Sustainable Catalyst components",
                ],
                "may_not": [
                    "invent sequestration rates or project outcomes",
                    "rank or declare a measure suitable without project evidence",
                    "declare a methodology eligible or approved",
                    "assert current policy, inventory, program, or market rules without current source verification",
                    "assume nature-based co-benefits",
                    "verify or certify a carbon project",
                    "issue or imply carbon credits",
                ],
                "caution_flags": caution_flags,
            },
            "guardrails": {
                "deterministic_guidance_is_not_research_conclusion": True,
                "intent_match_is_not_answer": True,
                "measure_match_is_not_project_suitability": True,
                "methodology_match_is_not_methodology_eligibility": True,
                "evidence_match_is_not_claim_validation": True,
                "project_object_validation_is_not_verification": True,
                "policy_and_market_freshness_requires_current_sources": True,
                "co_benefit_is_not_assumed": True,
                "quantified_sequestration_not_inferred": True,
                "carbon_credit_eligibility_not_determined": True,
            },
        }
        packet["content_fingerprint"] = _stable_hash({
            "query": query.casefold(),
            "intent_keys": intent_keys,
            "domain_scope": domain_scope,
            "source_role_keys": source_role_keys,
            "questions": questions,
            "gaps": gaps,
            "handoffs": handoffs,
        })
        return packet

    def research_context(self, query: str, *, limit: int = 12) -> dict[str, Any]:
        context = self._research_context_base(query, limit=limit)
        guidance = self.research_guidance(query, limit=limit)
        context["research_librarian"] = {
            "schema": AFOLU_RESEARCH_LIBRARIAN_SCHEMA_VERSION,
            "guidance_schema": AFOLU_RESEARCH_GUIDANCE_SCHEMA_VERSION,
            "detected_intents": guidance["detected_intents"],
            "research_question_frame": guidance["research_question_frame"],
            "source_plan": guidance["source_plan"],
            "evidence_gaps": guidance["evidence_gaps"],
            "freshness_review": guidance["freshness_review"],
            "handoffs": guidance["handoffs"],
            "answer_contract": guidance["answer_contract"],
            "guardrails": guidance["guardrails"],
            "content_fingerprint": guidance["content_fingerprint"],
        }
        context["content_fingerprint"] = _stable_hash({
            "base": context.get("content_fingerprint"),
            "research_librarian": context["research_librarian"],
        })
        return context

