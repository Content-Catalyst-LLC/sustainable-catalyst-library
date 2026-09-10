from __future__ import annotations

from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import hashlib
import json
from typing import Any, Iterable


DOMAIN_VERSION = "0.1.0"
SCHEMA_VERSION = "sc-carbon-nature-knowledge-foundation/1.0"


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


class CarbonNatureKnowledgeFoundation:
    def __init__(self) -> None:
        self._concepts = {concept.key: concept for concept in CONCEPTS}
        self._relationships = RELATIONSHIPS
        self._validate()

    def _validate(self) -> None:
        if len(self._concepts) != len(CONCEPTS):
            raise RuntimeError("duplicate Carbon & Nature concept key")
        allowed_missing = set()
        dangling = [
            (rel.subject, rel.object)
            for rel in self._relationships
            if rel.subject not in self._concepts or (rel.object not in self._concepts and rel.object not in allowed_missing)
        ]
        if dangling:
            raise RuntimeError(f"dangling Carbon & Nature relationship: {dangling[0]}")

    def manifest(self) -> dict[str, Any]:
        concepts = [item.to_dict() for item in CONCEPTS]
        relationships = [item.to_dict() for item in RELATIONSHIPS]
        return {
            "schema": SCHEMA_VERSION,
            "subsystem": {
                "key": "carbon-nature-intelligence",
                "name": "Carbon & Nature Intelligence",
                "version": DOMAIN_VERSION,
                "release": "AFOLU & Nature-Based Solutions Knowledge Foundation",
                "primary_home": "Sustainable Catalyst Library",
                "library_release_line": "5.11.x",
                "backend_version": "2.2.0",
            },
            "coverage": {
                "concept_count": len(concepts),
                "relationship_count": len(relationships),
                "concept_types": sorted({item["concept_type"] for item in concepts}),
                "domains": sorted({item["domain"] for item in concepts}),
            },
            "capabilities": [
                "stable-domain-concept-identifiers",
                "afolu-concept-registry",
                "nature-based-solutions-concept-registry",
                "carbon-pool-and-greenhouse-gas-model",
                "intervention-family-model",
                "indicator-and-methodology-family-model",
                "integrity-risk-and-co-benefit-model",
                "policy-and-economic-context-model",
                "explicit-relationship-registry",
                "library-evidence-linkage-ready",
                "research-librarian-context-packet-ready",
            ],
            "governance": {
                "normative_standard_claimed": False,
                "carbon_credit_issuance": False,
                "certification_body": False,
                "automatic_nature_positive_score": False,
                "automatic_additionality_determination": False,
                "automatic_permanence_determination": False,
                "automatic_policy_equivalence": False,
                "project_specific_mrv_protocol_builder": False,
                "soc_calculation_engine": False,
                "whole_farm_ghg_calculator": False,
                "research_only_foundation": True,
                "human_review_required_for_project_claims": True,
            },
            "boundaries": {
                "v0.1.0": "Domain ontology, concept identity, relationships, discovery, and context packets.",
                "v0.2.0": "Structured Carbon Sequestration Measure Registry.",
                "v0.3.0": "Carbon Evidence & Methodology Graph.",
                "v0.4.0": "Carbon Project Object Model & Provenance.",
                "v0.5.0": "AFOLU Research Librarian Intelligence.",
            },
            "content_fingerprint": _stable_hash({"concepts": concepts, "relationships": relationships}),
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
        return {
            "schema": "sc-carbon-nature-concept/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "concept": concept.to_dict(),
            "relationships": rels,
            "neighbors": [self._concepts[item].to_dict() for item in neighbors if item in self._concepts],
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

    def research_context(self, query: str, *, limit: int = 12) -> dict[str, Any]:
        query = str(query or "").strip()
        if not query:
            raise ValueError("query is required")
        tokens = [token.casefold() for token in query.replace("/", " ").replace("-", " ").split() if len(token) >= 2]
        scored: list[tuple[int, CarbonNatureConcept]] = []
        for concept in CONCEPTS:
            haystack = " ".join([concept.key, concept.label, concept.definition, *concept.synonyms, *concept.tags]).casefold()
            score = sum(1 for token in tokens if token in haystack)
            if query.casefold() in haystack:
                score += 4
            if score:
                scored.append((score, concept))
        scored.sort(key=lambda item: (-item[0], item[1].label.casefold(), item[1].key))
        selected = [concept for _, concept in scored[: max(1, min(int(limit), 30))]]
        selected_keys = {concept.key for concept in selected}
        rels = [
            rel.to_dict() for rel in RELATIONSHIPS
            if rel.subject in selected_keys or rel.object in selected_keys
        ]
        return {
            "schema": "sc-carbon-nature-research-context/1.0",
            "subsystem_version": DOMAIN_VERSION,
            "query": query,
            "concepts": [item.to_dict() for item in selected],
            "relationships": rels,
            "evidence_retrieval": {
                "library_search_query": query,
                "recommended_domains": _unique(item.domain for item in selected),
                "evidence_object_types": ["research-evidence", "methodology-document", "policy-document"],
            },
            "handoff": {
                "research_librarian_ready": True,
                "domain_aware_reasoning_enabled": False,
                "note": "v0.1.0 supplies governed context packets; AFOLU-specific Research Librarian reasoning is reserved for v0.5.0.",
            },
            "guardrails": {
                "concept_match_is_not_evidence": True,
                "relationship_is_not_causal_proof": True,
                "co_benefit_is_not_assumed": True,
                "project_credit_eligibility_not_determined": True,
                "methodology_applicability_requires_review": True,
            },
            "content_fingerprint": _stable_hash({"query": query.casefold(), "concepts": [item.key for item in selected], "relationships": rels}),
        }
