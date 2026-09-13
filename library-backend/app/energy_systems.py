from __future__ import annotations

from dataclasses import asdict, dataclass
from hashlib import sha256
import json
from decimal import Decimal, InvalidOperation, localcontext
from typing import Any

from .energy_technologies import RenewableTechnologyResourceRegistry
from .energy_balances import EnergyBalanceSystemsModel
from .energy_economics import EnergyScenarioEconomics
from .energy_bioenergy import BiologicalCarbonBioenergyIntegration
from .energy_global import GlobalEnergyIntelligence
from .energy_decision import EnergyDecisionIntelligence
from .energy_platform import IntegratedSustainableEnergyPlatform
from .energy_runtime import EnergyCrossProductRuntimeActivation
from .energy_uncertainty import EnergyModelingUncertaintyRegistry


DOMAIN_VERSION = "1.4.0"
SCHEMA_VERSION = "sc-energy-systems-modeling-uncertainty/1.0"


@dataclass(frozen=True)
class EnergyConcept:
    key: str
    label: str
    concept_type: str
    domain: str
    definition: str
    source_keys: tuple[str, ...]
    tags: tuple[str, ...] = ()
    current_state_claim: bool = False


@dataclass(frozen=True)
class EnergyRelationship:
    subject: str
    predicate: str
    object: str
    source_keys: tuple[str, ...]
    note: str
    inference_allowed: bool = False


@dataclass(frozen=True)
class EnergySourceRecord:
    key: str
    title: str
    creator: str
    year: int | None
    source_type: str
    role: str
    provenance_note: str
    numeric_status: str = "context-only"


@dataclass(frozen=True)
class EnergyUnitRecord:
    key: str
    symbol: str
    label: str
    dimension: str
    source_key: str
    source_year: int
    status: str
    note: str


@dataclass(frozen=True)
class EnergyConversionFactorRecord:
    key: str
    from_unit: str
    to_unit: str
    factor: str
    source_key: str
    source_year: int
    geography: str
    status: str
    note: str


@dataclass(frozen=True)
class EnergyCarbonFactorRecord:
    key: str
    fuel: str
    unit: str
    kg_co2e_per_unit: str
    source_key: str
    source_year: int
    geography: str
    emissions_boundary: str
    accounting_scope: str
    status: str
    note: str


@dataclass(frozen=True)
class EnergyHeatContentFactorRecord:
    key: str
    fuel: str
    unit: str
    kwh_per_unit: str
    source_key: str
    source_year: int
    geography: str
    calorific_basis: str
    status: str
    note: str


@dataclass(frozen=True)
class EnergyIndicatorDefinition:
    code: str
    label: str
    dimension: str
    theme: str
    subtheme: str
    statement: str
    measure_type: str
    observation_fields: tuple[str, ...]
    related_concepts: tuple[str, ...]
    source_key: str
    source_year: int
    methodology_status: str
    calculation_status: str
    note: str


class EnergySystemsKnowledgeFoundation:
    """Governed sustainable-energy knowledge and source-bound numerical registry.

    v1.0.0 certifies the integrated v0.1.0–v0.9.0 Energy Systems stack inside the Library:
    knowledge, source-bound numerical registries, sustainability indicators, renewable
    technology/resource contracts, energy-balance models, scenario economics, biological
    carbon/bioenergy integration, live dated Global Energy observations, and neutral decision
    packets. It adds governed cross-product contracts and an integrated-study contract without
    claiming execution in separate products. Certification is structural/governance-oriented,
    not scientific validation, site suitability, financial advice, or a live deployment audit.
    """

    def __init__(self) -> None:
        self._sources = self._build_sources()
        self._concepts = self._build_concepts()
        self._relationships = self._build_relationships()
        self._knowledge_domains = self._build_domains()
        self._sdgs = self._build_sdgs()
        self._units = self._build_units()
        self._conversion_factors = self._build_conversion_factors()
        self._carbon_factors = self._build_carbon_factors()
        self._heat_content_factors = self._build_heat_content_factors()
        self._indicators = self._build_indicators()
        self._technology_registry = RenewableTechnologyResourceRegistry()
        self._balance_model = EnergyBalanceSystemsModel(technology_keys=[x["key"] for x in self._technology_registry.technologies(limit=100)["items"]])
        self._economics_model = EnergyScenarioEconomics()
        self._bioenergy_model = BiologicalCarbonBioenergyIntegration()
        self._global_energy = GlobalEnergyIntelligence()
        self._decision_intelligence = EnergyDecisionIntelligence()
        self._integrated_platform = IntegratedSustainableEnergyPlatform()
        self._runtime_activation = EnergyCrossProductRuntimeActivation()
        self._modeling_uncertainty = EnergyModelingUncertaintyRegistry()
        self._methodology_rules = self._build_methodology_rules()
        self._handoffs = self._build_handoffs()
        self._guardrails = self._build_guardrails()
        self._validate()
        self._fingerprint = self._content_fingerprint()

    @staticmethod
    def _build_sources() -> dict[str, EnergySourceRecord]:
        records = [
            EnergySourceRecord(
                "ucd-module-sustainable-energy",
                "Sustainable Energy module description and learning outcomes",
                "University College Dublin module material supplied by the user",
                2026,
                "module-description",
                "Defines the requested instructional scope: energy-system history and importance; resource evaluation; sustainable energy systems; biological carbon capture/storage; renewable technologies; efficiency, energy balance, cost-benefit, and cost-efficiency analysis.",
                "scope-only",
            ),
            EnergySourceRecord(
                "world-bank-indicators-api",
                "World Bank Indicators API v2 — selected global energy indicators",
                "World Bank; underlying indicator sources vary by series",
                None,
                "live-data-api",
                "global-energy-live-observations",
                "v0.8.0 activates read-only retrieval for nine selected country energy indicators through the World Bank Indicators API v2. Observation year, provider indicator code, and source semantics are preserved; latest available does not imply current-year data.",
                "active-live-external",
            ),
            EnergySourceRecord(
                "mcdonnell-lecture-1",
                "Introduction to Sustainable Energy — Lecture 1",
                "Prof. Kevin McDonnell",
                None,
                "lecture",
                "course-concept-foundation",
                "Frames sustainable development, energy sources and consumption, environmental effects, the energy–prosperity–environment dilemma, efficiency, renewables, and technology/policy trade-offs.",
            ),
            EnergySourceRecord(
                "vera-langlois-2007",
                "Energy indicators for sustainable development",
                "Ivan Vera and Lucille Langlois",
                2007,
                "journal-article",
                "indicator-framework",
                "Provides the EISD framing, social/economic/environmental dimensions, and indicator concepts including accessibility, affordability, intensity, resource and reserve ratios, diversification, security, emissions, water, land, and waste.",
            ),
            EnergySourceRecord(
                "carbon-trust-conversion-2020",
                "Conversion factors: Energy and carbon conversions 2020 update",
                "Carbon Trust; factors based on BEIS 2020 data",
                2020,
                "technical-guide",
                "numeric-registry-source",
                "Provides the v0.2.0 source-bound energy-unit, direct kgCO2e, and gross-calorific-value records. Values retain their 2020 vintage and UK/source methodology and are not treated as current defaults.",
                "active-historical-reference-only",
            ),
            EnergySourceRecord(
                "atkisson-2009",
                "Pushing ‘Reset’ on Sustainable Development",
                "Alan AtKisson",
                2009,
                "policy-essay",
                "historical-systems-context",
                "Supports system-state and transformative-change framing for sustainability; retained as historical conceptual context rather than current policy evidence.",
            ),
            EnergySourceRecord(
                "undesa-scp-2010",
                "Trends in Sustainable Development: Towards Sustainable Consumption and Production",
                "United Nations Department of Economic and Social Affairs",
                2010,
                "un-report",
                "historical-resource-and-decoupling-context",
                "Provides historical framing for resource use, decoupling, consumption/production patterns, renewable-energy diffusion, and production- versus consumption-based environmental burdens.",
            ),
        ]
        return {record.key: record for record in records}

    @staticmethod
    def _build_concepts() -> dict[str, EnergyConcept]:
        C = EnergyConcept
        rows = [
            C("sustainable-development", "Sustainable development", "framework", "energy-sustainable-development", "Development framed around maintaining or improving human opportunities while accounting for physical, natural, and human capital and intergenerational needs.", ("mcdonnell-lecture-1", "atkisson-2009"), ("development", "systems")),
            C("historical-energy-system-evolution", "Historical evolution of energy systems", "historical-framework", "energy-sustainable-development", "A historical perspective on changing energy sources, conversion technologies, and fossil-fuel systems, included as a core module learning outcome.", ("ucd-module-sustainable-energy", "mcdonnell-lecture-1"), ("history", "fossil", "transition")),
            C("global-energy-importance", "Worldwide importance of energy systems", "development-framework", "energy-sustainable-development", "The role of energy systems, access, consumption, and associated trends in supporting economic and social development across countries and regions.", ("ucd-module-sustainable-energy", "mcdonnell-lecture-1", "vera-langlois-2007"), ("global", "development", "access")),
            C("sustainable-energy", "Sustainable energy", "framework", "energy-sustainable-development", "Energy development evaluated through equitable access, human benefit, environmental effects, and consequences for future generations.", ("mcdonnell-lecture-1",), ("sustainability", "trade-offs")),
            C("energy-prosperity-environment-dilemma", "Energy–prosperity–environment dilemma", "problem-frame", "energy-sustainable-development", "The challenge of maintaining and extending energy-derived benefits while avoiding unacceptable environmental and socioeconomic harm.", ("mcdonnell-lecture-1",), ("prosperity", "environment", "trade-offs")),
            C("energy-system", "Energy system", "system", "resources-conversion-end-use", "A connected arrangement of energy sources, conversion processes, carriers or forms, distribution, and end uses.", ("mcdonnell-lecture-1",), ("flows", "conversion")),
            C("energy-source", "Energy source", "resource-concept", "resources-conversion-end-use", "An origin from which energy can be obtained and converted into useful forms or services.", ("mcdonnell-lecture-1",), ("resource",)),
            C("energy-resource", "Energy resource", "resource-concept", "resources-conversion-end-use", "An energy-bearing natural or technical resource considered for availability, estimation, production, or conversion.", ("ucd-module-sustainable-energy", "vera-langlois-2007"), ("resource-evaluation",)),
            C("energy-resource-estimation", "Energy resource estimation and evaluation", "analysis-method", "resources-conversion-end-use", "Assessment of energy-resource availability and significance within a defined geography, technology, or planning context; v0.2.0 retains the method concept without inferring resource values.", ("ucd-module-sustainable-energy", "vera-langlois-2007"), ("resource", "estimation", "evaluation")),
            C("renewable-resource-potential", "Renewable resource potential", "resource-concept", "renewable-technologies", "Potential availability of a renewable resource for energy-system use; v0.2.0 does not calculate technical, economic, or deployable potential.", ("ucd-module-sustainable-energy",), ("resource", "potential")),
            C("energy-reserve", "Energy reserve", "resource-concept", "resources-conversion-end-use", "A reserve concept used with production to assess the relationship between available reserves and current production.", ("vera-langlois-2007",), ("reserve-to-production",)),
            C("primary-energy", "Primary energy", "energy-form", "resources-conversion-end-use", "Energy present in an original source before conversion into secondary carriers or end-use forms.", ("mcdonnell-lecture-1",), ("energy-form",)),
            C("energy-carrier", "Energy carrier", "energy-form", "resources-conversion-end-use", "A form used to transfer energy from conversion processes toward end-use applications.", ("mcdonnell-lecture-1",), ("electricity", "fuels")),
            C("energy-conversion", "Energy conversion", "process", "resources-conversion-end-use", "Transformation of energy from one form or source into another form suitable for distribution or use.", ("mcdonnell-lecture-1", "vera-langlois-2007"), ("efficiency",)),
            C("energy-service", "Energy service", "end-use-concept", "resources-conversion-end-use", "A useful service enabled by energy, including services supporting households, transport, commerce, industry, and human development.", ("mcdonnell-lecture-1", "vera-langlois-2007"), ("end-use", "access")),
            C("end-use", "Energy end use", "end-use-concept", "resources-conversion-end-use", "The point or sector in which delivered energy is used to provide useful services.", ("mcdonnell-lecture-1", "vera-langlois-2007"), ("industry", "transport", "households")),
            C("electricity", "Electricity", "energy-form", "resources-conversion-end-use", "An energy form and carrier produced by multiple conversion pathways and used across modern economic and social activity.", ("mcdonnell-lecture-1", "vera-langlois-2007"), ("carrier",)),
            C("heat", "Heat", "energy-form", "resources-conversion-end-use", "A thermal energy form occurring within conversion pathways and end-use applications.", ("mcdonnell-lecture-1",), ("thermal",)),
            C("mechanical-work", "Mechanical work", "energy-form", "resources-conversion-end-use", "A mechanical output of energy-conversion systems used directly or in further conversion.", ("mcdonnell-lecture-1",), ("mechanical",)),
            C("chemical-energy", "Chemical energy", "energy-form", "resources-conversion-end-use", "Energy stored in chemical form, including fuels and biomass pathways.", ("mcdonnell-lecture-1",), ("fuel",)),
            C("electrochemical-energy", "Electrochemical energy", "energy-form", "resources-conversion-end-use", "Energy conversion or storage involving electrochemical processes.", ("mcdonnell-lecture-1",), ("conversion",)),
            C("fossil-energy", "Fossil-fuel energy", "energy-source-class", "resources-conversion-end-use", "Energy derived from fossil fuels including coal, oil, and natural gas.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy"), ("fossil",)),
            C("coal", "Coal", "energy-source", "resources-conversion-end-use", "A fossil energy source used in energy conversion and evaluated for energy and environmental impacts.", ("mcdonnell-lecture-1", "carbon-trust-conversion-2020"), ("fossil",)),
            C("oil", "Oil", "energy-source", "resources-conversion-end-use", "A fossil energy source and feedstock represented in historical energy systems and conversion-factor material.", ("mcdonnell-lecture-1", "carbon-trust-conversion-2020"), ("fossil",)),
            C("natural-gas", "Natural gas", "energy-source", "resources-conversion-end-use", "A fossil gaseous energy source used for heat and other conversion pathways.", ("mcdonnell-lecture-1", "carbon-trust-conversion-2020"), ("fossil",)),
            C("nuclear-energy", "Nuclear energy", "energy-source-class", "resources-conversion-end-use", "Energy derived from nuclear processes and represented as a distinct source in the lecture energy-conversion system.", ("mcdonnell-lecture-1",), ("nuclear",)),
            C("renewable-energy", "Renewable energy", "energy-source-class", "renewable-technologies", "Energy supplied from renewable sources and technologies, evaluated alongside efficiency, access, costs, impacts, and system constraints.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy", "undesa-scp-2010"), ("renewable",)),
            C("renewable-technology-prospects", "Future prospects of renewable energy sources", "assessment-concept", "renewable-technologies", "Forward-looking assessment of renewable-energy sources and technologies, including physical, technological, economic, resource, and sustainability considerations; current forecasts require current evidence.", ("ucd-module-sustainable-energy",), ("future", "technology", "prospects")),
            C("solar-energy", "Solar energy", "renewable-resource", "renewable-technologies", "Solar radiation used directly for thermal applications or converted through photovoltaic technologies.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy"), ("solar",)),
            C("solar-photovoltaics", "Solar photovoltaics", "renewable-technology", "renewable-technologies", "Photovoltaic conversion of solar energy into electricity.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy", "undesa-scp-2010"), ("solar", "electricity")),
            C("solar-thermal", "Solar thermal", "renewable-technology", "renewable-technologies", "Use of solar energy for thermal conversion or heat applications.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy"), ("solar", "heat")),
            C("wind-energy", "Wind energy", "renewable-technology", "renewable-technologies", "Use of wind as a renewable source for mechanical and electrical energy conversion.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy", "undesa-scp-2010"), ("wind",)),
            C("hydropower", "Hydropower", "renewable-technology", "renewable-technologies", "Use of moving or stored water for mechanical and electrical energy conversion.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy", "undesa-scp-2010"), ("hydro",)),
            C("tidal-energy", "Tidal energy", "renewable-technology", "renewable-technologies", "Renewable energy obtained from tidal motion and represented within marine renewable-energy systems.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy"), ("marine", "ocean")),
            C("wave-energy", "Wave energy", "renewable-technology", "renewable-technologies", "Renewable energy obtained from ocean-wave motion.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy"), ("marine", "ocean")),
            C("geothermal-energy", "Geothermal energy", "energy-source", "renewable-technologies", "Heat from geothermal sources used in energy conversion; represented in the lecture energy-source diagram.", ("mcdonnell-lecture-1",), ("heat",)),
            C("biomass", "Biomass", "biological-resource", "biological-carbon-bioenergy", "Biological material that can participate in energy conversion and carbon-cycle pathways.", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy", "undesa-scp-2010"), ("bioenergy", "carbon")),
            C("bioenergy", "Bioenergy", "renewable-technology-family", "biological-carbon-bioenergy", "Energy derived from biomass and biological feedstocks through conversion pathways.", ("ucd-module-sustainable-energy", "undesa-scp-2010"), ("biomass",)),
            C("biological-carbon-capture-storage", "Biological carbon capture and storage", "carbon-pathway-family", "biological-carbon-bioenergy", "Biological and land-based pathways considered for capturing, retaining, or managing carbon in the sustainable-energy module scope.", ("ucd-module-sustainable-energy",), ("carbon", "nature")),
            C("soil-carbon", "Soil carbon", "carbon-pool", "biological-carbon-bioenergy", "Carbon stored in soils and treated as a biological carbon-management pathway in the module scope.", ("ucd-module-sustainable-energy",), ("soil", "carbon")),
            C("forest-carbon", "Forest carbon", "carbon-pool", "biological-carbon-bioenergy", "Carbon stored in forest systems and biomass, linked to forest ecology and energy-related land considerations.", ("ucd-module-sustainable-energy", "vera-langlois-2007"), ("forest", "carbon")),
            C("forest-ecology", "Forest ecology", "ecosystem-concept", "biological-carbon-bioenergy", "Ecological context for forests relevant to biological carbon storage, biomass, land use, and energy-system effects.", ("ucd-module-sustainable-energy",), ("forest", "ecosystem")),
            C("anaerobic-digestion", "Anaerobic digestion", "biological-process", "biological-carbon-bioenergy", "A biological conversion process included in the module scope and associated with digestate as a process output.", ("ucd-module-sustainable-energy",), ("AD", "bioenergy")),
            C("digestate", "Digestate", "biological-output", "biological-carbon-bioenergy", "Material output from anaerobic digestion considered in the module's biological carbon capture/storage scope.", ("ucd-module-sustainable-energy",), ("AD", "soil")),
            C("biochar", "Biochar", "carbon-pathway", "biological-carbon-bioenergy", "A biomass-derived carbon-management pathway explicitly included in the module scope.", ("ucd-module-sustainable-energy",), ("biomass", "carbon")),
            C("biomass-to-oil", "Biomass to oil", "conversion-pathway", "biological-carbon-bioenergy", "Conversion of biomass toward oil-like energy products, included in the module scope.", ("ucd-module-sustainable-energy",), ("biomass", "conversion")),
            C("co2-to-energy", "CO₂ to energy", "conversion-pathway", "biological-carbon-bioenergy", "A CO₂-to-energy pathway named in the module's biological carbon capture/storage scope; v0.2.0 records the concept without selecting a specific process or claiming performance.", ("ucd-module-sustainable-energy",), ("CO2", "conversion")),
            C("energy-efficiency", "Energy efficiency", "analysis-concept", "efficiency-economics-analysis", "The relationship between useful energy service or output and energy input, used to evaluate technologies, processes, and systems.", ("mcdonnell-lecture-1", "vera-langlois-2007", "ucd-module-sustainable-energy"), ("efficiency",)),
            C("end-use-efficiency", "End-use efficiency", "analysis-concept", "efficiency-economics-analysis", "Efficiency at the point where energy is converted into useful services in sectors such as households, industry, transport, services, and agriculture.", ("mcdonnell-lecture-1", "vera-langlois-2007"), ("efficiency", "end-use")),
            C("energy-intensity", "Energy intensity", "indicator-concept", "efficiency-economics-analysis", "Energy use relative to an activity or economic measure; aggregate values require interpretation in light of sector structure, climate, geography, technology, fuel mix, and behavior.", ("vera-langlois-2007",), ("indicator", "GDP")),
            C("energy-balance", "Energy balance", "analysis-method", "efficiency-economics-analysis", "Accounting framework for energy inputs, conversions, outputs, losses, and/or end uses within a defined system boundary.", ("ucd-module-sustainable-energy", "mcdonnell-lecture-1"), ("analysis", "flows")),
            C("cost-benefit-analysis", "Cost-benefit analysis", "analysis-method", "efficiency-economics-analysis", "Comparison of costs and benefits across an energy option or scenario, included as a required analysis approach in the module scope.", ("ucd-module-sustainable-energy",), ("economics", "scenario")),
            C("cost-efficiency-analysis", "Cost-efficiency analysis", "analysis-method", "efficiency-economics-analysis", "Comparison of costs relative to efficiency outcomes or alternative energy choices, included in the module scope.", ("ucd-module-sustainable-energy",), ("economics", "efficiency")),
            C("energy-access", "Energy access", "social-indicator-concept", "sustainability-metrics-impacts", "Availability of modern or commercial energy services to households or populations; a core social dimension of sustainable energy development.", ("mcdonnell-lecture-1", "vera-langlois-2007"), ("equity", "development")),
            C("energy-affordability", "Energy affordability", "social-indicator-concept", "sustainability-metrics-impacts", "The burden of fuel and electricity costs relative to household income and ability to meet basic energy needs.", ("vera-langlois-2007",), ("equity", "cost")),
            C("energy-security", "Energy security", "system-indicator-concept", "sustainability-metrics-impacts", "Reliability, sufficiency, and affordability of supply considered alongside import dependency and critical fuel stocks.", ("vera-langlois-2007",), ("security", "imports")),
            C("energy-mix", "Energy and fuel mix", "system-indicator-concept", "sustainability-metrics-impacts", "The shares of fuels or energy sources within primary/final energy, electricity generation, or capacity.", ("vera-langlois-2007",), ("diversification",)),
            C("renewable-energy-share", "Renewable energy share", "indicator-concept", "sustainability-metrics-impacts", "Share of renewable energy within energy or electricity, represented in the EISD diversification indicators.", ("vera-langlois-2007",), ("ECO13", "renewable")),
            C("resources-to-production", "Resources-to-production ratio", "indicator-concept", "sustainability-metrics-impacts", "Ratio relating energy resources to production, used as a production/resource indicator.", ("vera-langlois-2007",), ("ECO5", "resource")),
            C("reserves-to-production", "Reserves-to-production ratio", "indicator-concept", "sustainability-metrics-impacts", "Ratio relating energy reserves to production, used as a production/reserve indicator.", ("vera-langlois-2007",), ("ECO4", "reserve")),
            C("ghg-emissions", "Greenhouse-gas emissions from energy", "environmental-indicator-concept", "sustainability-metrics-impacts", "Greenhouse-gas emissions associated with energy production and use, considered per capita, per economic output, or other defined denominators.", ("vera-langlois-2007", "carbon-trust-conversion-2020"), ("ENV1", "climate")),
            C("co2e", "Carbon dioxide equivalent", "measurement-concept", "sustainability-metrics-impacts", "A common greenhouse-gas reporting unit that expresses combined climate effects in CO₂-equivalent terms.", ("carbon-trust-conversion-2020",), ("GHG", "measurement")),
            C("emission-factor", "Energy emission factor", "measurement-concept", "sustainability-metrics-impacts", "A factor relating a quantity of energy or fuel use to greenhouse-gas emissions under a stated source, year, geography, unit, and emissions boundary; v0.2.0 activates only explicitly source-bound historical factors and does not treat them as current defaults.", ("carbon-trust-conversion-2020",), ("conversion", "GHG", "provenance")),
            C("energy-unit-conversion", "Energy unit conversion", "measurement-method", "resources-conversion-end-use", "Conversion among energy units using explicit source and unit definitions; numerical conversion services are reserved for the versioned v0.2.0 registry.", ("mcdonnell-lecture-1", "carbon-trust-conversion-2020"), ("units", "conversion")),
            C("calorific-value", "Calorific value", "measurement-concept", "resources-conversion-end-use", "Energy content of a fuel under a stated gross or net basis; v0.2.0 activates the unambiguous historical gross-calorific-value records while keeping them source-bound and non-current.", ("carbon-trust-conversion-2020",), ("fuel", "heat-content", "gross", "net")),
            C("direct-emissions", "Direct emissions", "emissions-boundary", "sustainability-metrics-impacts", "Emissions occurring at the point of fuel use or, for electricity, at the point of generation within the historical conversion guide's stated boundary.", ("carbon-trust-conversion-2020",), ("boundary", "scope")),
            C("indirect-emissions", "Indirect emissions", "emissions-boundary", "sustainability-metrics-impacts", "Upstream or other emissions outside the historical guide's direct-emissions boundary, such as extraction or refining examples.", ("carbon-trust-conversion-2020",), ("boundary", "lifecycle")),
            C("air-quality-impact", "Air-quality impact", "environmental-impact", "sustainability-metrics-impacts", "Air-pollution effects associated with energy systems and represented by ambient concentration and emissions indicators.", ("vera-langlois-2007",), ("air", "ENV2", "ENV3")),
            C("water-quality-impact", "Water-quality impact", "environmental-impact", "sustainability-metrics-impacts", "Water-quality effects associated with contaminant discharges from energy systems.", ("vera-langlois-2007",), ("water", "ENV4")),
            C("land-impact", "Land and soil impact", "environmental-impact", "sustainability-metrics-impacts", "Land and soil consequences associated with energy systems, including soil-quality and forest-related indicators.", ("mcdonnell-lecture-1", "vera-langlois-2007"), ("land", "soil")),
            C("ecosystem-impact", "Ecosystem impact", "environmental-impact", "sustainability-metrics-impacts", "Effects of energy technologies and resource use on ecosystems, including land-use disruption and impacts on ecological capability.", ("mcdonnell-lecture-1",), ("ecosystem", "biosphere")),
            C("decoupling", "Decoupling", "systems-analysis-concept", "sustainability-metrics-impacts", "Weakening or breaking the relationship between growth in economic activity and growth in material use, fossil-energy use, waste, or environmental pressure.", ("undesa-scp-2010", "vera-langlois-2007"), ("economy", "environment")),
            C("production-based-emissions", "Production-based emissions", "accounting-boundary", "sustainability-metrics-impacts", "Emissions allocated to production occurring within a geographic boundary.", ("undesa-scp-2010",), ("trade", "boundary")),
            C("consumption-based-emissions", "Consumption-based emissions", "accounting-boundary", "sustainability-metrics-impacts", "Emissions associated with consumption, including burdens embodied in production that may occur outside the consuming geography.", ("undesa-scp-2010",), ("trade", "boundary")),
            C("sustainability-indicator", "Sustainability indicator", "indicator-framework", "sustainability-metrics-impacts", "A structured measure used with context to monitor conditions, relationships, trade-offs, and progress toward defined sustainable-energy objectives.", ("vera-langlois-2007",), ("measurement", "policy")),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_relationships() -> list[EnergyRelationship]:
        R = EnergyRelationship
        rows = [
            R("sustainable-energy", "supports", "sustainable-development", ("mcdonnell-lecture-1",), "Sustainable energy is framed as part of sustainable development."),
            R("energy-prosperity-environment-dilemma", "frames", "sustainable-energy", ("mcdonnell-lecture-1",), "Trade-offs between energy-derived benefits and impacts motivate sustainable-energy evaluation."),
            R("energy-system", "has-component", "energy-source", ("mcdonnell-lecture-1",), "Energy sources enter conversion systems."),
            R("energy-system", "has-component", "energy-conversion", ("mcdonnell-lecture-1",), "Conversion processes transform energy forms."),
            R("energy-system", "delivers", "energy-service", ("mcdonnell-lecture-1",), "Energy systems ultimately support useful end-use services."),
            R("energy-source", "can-be-evaluated-as", "energy-resource", ("ucd-module-sustainable-energy",), "The module requires estimation and evaluation of energy resources."),
            R("historical-energy-system-evolution", "contextualizes", "energy-system", ("ucd-module-sustainable-energy",), "The module requires knowledge of the historic evolution of energy systems."),
            R("global-energy-importance", "contextualizes", "energy-access", ("mcdonnell-lecture-1", "vera-langlois-2007"), "Energy access is tied to human and economic development in the source material."),
            R("energy-resource", "evaluated-by", "energy-resource-estimation", ("ucd-module-sustainable-energy",), "Resource estimation/evaluation is an explicit module requirement."),
            R("renewable-energy", "evaluated-for", "renewable-resource-potential", ("ucd-module-sustainable-energy",), "Renewable choices depend on resource availability and system context."),
            R("renewable-energy", "evaluated-for", "renewable-technology-prospects", ("ucd-module-sustainable-energy",), "The module calls for consideration of future prospects of energy sources."),
            R("energy-resource", "measured-by", "resources-to-production", ("vera-langlois-2007",), "ECO5 relates resources to production."),
            R("energy-reserve", "measured-by", "reserves-to-production", ("vera-langlois-2007",), "ECO4 relates reserves to production."),
            R("energy-conversion", "evaluated-by", "energy-efficiency", ("vera-langlois-2007", "mcdonnell-lecture-1"), "Conversion efficiency is a sustainable-energy indicator and analysis concern."),
            R("end-use", "evaluated-by", "end-use-efficiency", ("mcdonnell-lecture-1", "vera-langlois-2007"), "End-use efficiency is central to reducing energy use while preserving services."),
            R("renewable-energy", "includes", "solar-energy", ("ucd-module-sustainable-energy",), "Solar is within the module's renewable-energy scope."),
            R("solar-energy", "converted-by", "solar-photovoltaics", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy"), "PV converts solar energy to electricity."),
            R("solar-energy", "converted-by", "solar-thermal", ("mcdonnell-lecture-1", "ucd-module-sustainable-energy"), "Solar thermal converts solar energy to useful heat."),
            R("renewable-energy", "includes", "wind-energy", ("ucd-module-sustainable-energy",), "Wind is within the module's renewable-energy scope."),
            R("renewable-energy", "includes", "hydropower", ("ucd-module-sustainable-energy",), "Hydropower is within the module's renewable-energy scope."),
            R("renewable-energy", "includes", "tidal-energy", ("ucd-module-sustainable-energy",), "Tidal energy is within the module's renewable-energy scope."),
            R("renewable-energy", "includes", "wave-energy", ("ucd-module-sustainable-energy",), "Wave energy is within the module's renewable-energy scope."),
            R("renewable-energy", "includes", "bioenergy", ("ucd-module-sustainable-energy",), "Bioenergy is within the module's renewable-energy scope."),
            R("bioenergy", "uses-resource", "biomass", ("ucd-module-sustainable-energy", "undesa-scp-2010"), "Biomass is a feedstock/resource for bioenergy pathways."),
            R("biological-carbon-capture-storage", "includes", "soil-carbon", ("ucd-module-sustainable-energy",), "Soil carbon is explicitly listed in the module's biological carbon scope."),
            R("biological-carbon-capture-storage", "includes", "forest-carbon", ("ucd-module-sustainable-energy",), "Forests are explicitly listed in the module's biological carbon scope."),
            R("forest-carbon", "contextualized-by", "forest-ecology", ("ucd-module-sustainable-energy",), "Forest ecology provides system context for forest-carbon pathways."),
            R("biological-carbon-capture-storage", "includes", "digestate", ("ucd-module-sustainable-energy",), "Digestate is explicitly listed in the module scope."),
            R("anaerobic-digestion", "produces", "digestate", ("ucd-module-sustainable-energy",), "The module explicitly identifies digestate as arising from the anaerobic-digestion process."),
            R("biological-carbon-capture-storage", "includes", "biochar", ("ucd-module-sustainable-energy",), "Biochar is explicitly listed in the module scope."),
            R("biomass", "can-feed", "biomass-to-oil", ("ucd-module-sustainable-energy",), "Biomass-to-oil is explicitly named as a pathway."),
            R("biological-carbon-capture-storage", "includes", "co2-to-energy", ("ucd-module-sustainable-energy",), "CO2-to-energy is explicitly listed in the module scope without a specified process."),
            R("energy-system", "analyzed-by", "energy-balance", ("ucd-module-sustainable-energy",), "Energy balance is named as an efficiency-analysis approach."),
            R("energy-system", "analyzed-by", "cost-benefit-analysis", ("ucd-module-sustainable-energy",), "Cost-benefit analysis is named for evaluating scenarios and choices."),
            R("energy-system", "analyzed-by", "cost-efficiency-analysis", ("ucd-module-sustainable-energy",), "Cost-efficiency analysis is named for evaluating scenarios and choices."),
            R("sustainability-indicator", "can-measure", "energy-access", ("vera-langlois-2007",), "SOC1 addresses accessibility."),
            R("sustainability-indicator", "can-measure", "energy-affordability", ("vera-langlois-2007",), "SOC2 addresses affordability."),
            R("sustainability-indicator", "can-measure", "energy-intensity", ("vera-langlois-2007",), "ECO2 and sectoral ECO6–ECO10 address energy intensity."),
            R("sustainability-indicator", "can-measure", "energy-security", ("vera-langlois-2007",), "ECO15–ECO16 address supply security dimensions."),
            R("sustainability-indicator", "can-measure", "renewable-energy-share", ("vera-langlois-2007",), "ECO13 addresses renewable share."),
            R("sustainability-indicator", "can-measure", "ghg-emissions", ("vera-langlois-2007",), "ENV1 addresses greenhouse-gas emissions."),
            R("sustainability-indicator", "can-measure", "air-quality-impact", ("vera-langlois-2007",), "ENV2–ENV3 address air-quality conditions and emissions."),
            R("sustainability-indicator", "can-measure", "water-quality-impact", ("vera-langlois-2007",), "ENV4 addresses water-quality contaminant discharges."),
            R("sustainability-indicator", "can-measure", "land-impact", ("vera-langlois-2007",), "ENV5–ENV10 include land, forest, and waste dimensions."),
            R("energy-mix", "includes-share", "renewable-energy-share", ("vera-langlois-2007",), "Renewable share is one diversification view of the energy mix."),
            R("energy-efficiency", "can-affect", "energy-intensity", ("vera-langlois-2007",), "Efficiency improvement is one contributor to changing energy intensity."),
            R("energy-system", "can-cause", "ghg-emissions", ("vera-langlois-2007",), "Energy production and use are linked to greenhouse-gas emissions."),
            R("energy-system", "can-cause", "air-quality-impact", ("vera-langlois-2007",), "Energy production and use can affect air quality."),
            R("energy-system", "can-cause", "water-quality-impact", ("vera-langlois-2007",), "Energy systems can create contaminant discharges affecting water."),
            R("energy-system", "can-cause", "land-impact", ("mcdonnell-lecture-1", "vera-langlois-2007"), "Energy technologies can entail land-use and soil/forest impacts."),
            R("renewable-energy", "does-not-imply-absence-of", "ecosystem-impact", ("mcdonnell-lecture-1",), "The lecture cautions that no energy supply is impact-free; renewable classification is not a zero-impact claim."),
            R("ghg-emissions", "reported-as", "co2e", ("carbon-trust-conversion-2020",), "The conversion guide reports combined greenhouse-gas effects as kgCO2e."),
            R("emission-factor", "expresses", "ghg-emissions", ("carbon-trust-conversion-2020",), "The historical guide supplies kgCO2e-per-unit examples; values remain inactive until the v0.2.0 governed registry."),
            R("energy-unit-conversion", "supports", "energy-balance", ("mcdonnell-lecture-1", "carbon-trust-conversion-2020"), "Common units are needed when quantities and rates are compared within energy analysis."),
            R("calorific-value", "describes", "energy-source", ("carbon-trust-conversion-2020",), "Fuel heat-content values relate fuel quantities to energy content under explicit gross/net definitions."),
            R("direct-emissions", "is-distinct-from", "indirect-emissions", ("carbon-trust-conversion-2020",), "The guide explicitly distinguishes point-of-use/generation emissions from upstream examples."),
            R("decoupling", "compares", "energy-intensity", ("undesa-scp-2010", "vera-langlois-2007"), "Changes in intensity can inform decoupling analysis but do not by themselves prove absolute reductions."),
            R("production-based-emissions", "contrasts-with", "consumption-based-emissions", ("undesa-scp-2010",), "The UN report distinguishes territorial production burdens from consumption-linked burdens."),
            R("sustainable-energy", "evaluated-by", "sustainability-indicator", ("vera-langlois-2007",), "Indicators support monitoring and policy analysis across sustainable-energy dimensions."),
            R("sustainable-energy", "requires-consideration-of", "energy-access", ("mcdonnell-lecture-1", "vera-langlois-2007"), "Access to adequate modern energy services is treated as central to sustainable development."),
            R("sustainable-energy", "requires-consideration-of", "energy-affordability", ("mcdonnell-lecture-1", "vera-langlois-2007"), "Affordability is a core sustainable-energy concern."),
            R("sustainable-energy", "requires-consideration-of", "energy-security", ("vera-langlois-2007",), "Reliable, sufficient, affordable supply is part of sustainable-energy assessment."),
            R("sustainable-energy", "requires-consideration-of", "ecosystem-impact", ("mcdonnell-lecture-1",), "Technology and policy assessment must consider biosphere/ecosystem effects."),
        ]
        return rows

    @staticmethod
    def _build_domains() -> list[dict[str, Any]]:
        return [
            {"key": "energy-sustainable-development", "label": "Energy & Sustainable Development", "purpose": "Historic/current energy perspectives, human development, access, prosperity, environment, and trade-offs."},
            {"key": "resources-conversion-end-use", "label": "Resources, Conversion & End Use", "purpose": "Energy sources, resources/reserves, forms, conversion processes, carriers, services, and end uses."},
            {"key": "renewable-technologies", "label": "Renewable Energy Technologies", "purpose": "Solar, wind, hydro, tidal, wave, bioenergy and related technology concepts without v0.2.0 performance ranking."},
            {"key": "biological-carbon-bioenergy", "label": "Biological Carbon Capture, Storage & Bioenergy", "purpose": "Soil/forest carbon, forest ecology, anaerobic digestion and digestate, biochar, biomass-to-oil, and CO₂-to-energy concepts linked to Carbon & Nature where supported."},
            {"key": "efficiency-economics-analysis", "label": "Efficiency, Economics & Analysis", "purpose": "Energy efficiency, end-use efficiency, energy balance, cost-benefit, and cost-efficiency analytical concepts."},
            {"key": "sustainability-metrics-impacts", "label": "Sustainability Metrics & Environmental Impacts", "purpose": "Social/economic/environmental indicators, security, emissions boundaries, air/water/land impacts, and decoupling."},
        ]

    @staticmethod
    def _build_sdgs() -> list[dict[str, Any]]:
        # Coverage values are preserved exactly from the module description supplied by the user.
        return [
            {"goal": 4, "name": "Quality Education", "coverage": 1, "source": "ucd-module-sustainable-energy"},
            {"goal": 6, "name": "Clean Water and Sanitation", "coverage": 2, "source": "ucd-module-sustainable-energy"},
            {"goal": 7, "name": "Affordable and Clean Energy", "coverage": 1, "source": "ucd-module-sustainable-energy"},
            {"goal": 9, "name": "Industry, Innovation and Infrastructure", "coverage": 2, "source": "ucd-module-sustainable-energy"},
            {"goal": 11, "name": "Sustainable Cities and Communities", "coverage": 2, "source": "ucd-module-sustainable-energy"},
            {"goal": 12, "name": "Responsible Consumption and Production", "coverage": 2, "source": "ucd-module-sustainable-energy"},
            {"goal": 13, "name": "Climate Action", "coverage": 1, "source": "ucd-module-sustainable-energy"},
            {"goal": 14, "name": "Life Below Water", "coverage": 3, "source": "ucd-module-sustainable-energy"},
            {"goal": 15, "name": "Life on Land", "coverage": None, "source": "ucd-module-sustainable-energy", "note": "The supplied module excerpt lists the goal but does not show a coverage value; v0.2.0 does not infer one."},
        ]

    @staticmethod
    def _build_units() -> dict[str, EnergyUnitRecord]:
        U = EnergyUnitRecord
        rows = [
            U("kwh", "kWh", "kilowatt-hour", "energy", "carbon-trust-conversion-2020", 2020, "source-bound-reference", "Canonical target used by the supplied energy conversion table."),
            U("therm", "therm", "therm", "energy", "carbon-trust-conversion-2020", 2020, "source-bound-reference", "The supplied guide provides a therm-to-kWh conversion factor."),
            U("btu", "Btu", "British thermal unit", "energy", "carbon-trust-conversion-2020", 2020, "source-bound-reference", "The supplied guide provides a Btu-to-kWh conversion factor."),
            U("mj", "MJ", "megajoule", "energy", "carbon-trust-conversion-2020", 2020, "source-bound-reference", "The supplied guide provides an MJ-to-kWh conversion factor."),
            U("toe", "toe", "tonne of oil equivalent", "energy", "carbon-trust-conversion-2020", 2020, "source-bound-reference", "The supplied guide provides a toe-to-kWh conversion factor."),
            U("tonne", "tonne", "tonne", "mass", "carbon-trust-conversion-2020", 2020, "source-bound-reference", "Used as a denominator in supplied fuel carbon and heat-content factors."),
            U("litre", "L", "litre", "volume", "carbon-trust-conversion-2020", 2020, "source-bound-reference", "Used as a denominator in supplied fuel carbon and heat-content factors."),
            U("cubic-metre", "m³", "cubic metre", "volume", "carbon-trust-conversion-2020", 2020, "source-bound-reference", "Used for the supplied natural-gas carbon factor."),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_conversion_factors() -> dict[str, EnergyConversionFactorRecord]:
        F = EnergyConversionFactorRecord
        rows = [
            F("therm-to-kwh-2020", "therm", "kwh", "29.307", "carbon-trust-conversion-2020", 2020, "United Kingdom/source guide", "historical-source-bound", "Quoted by the supplied guide as therms to kWh."),
            F("btu-to-kwh-2020", "btu", "kwh", "0.0002931", "carbon-trust-conversion-2020", 2020, "United Kingdom/source guide", "historical-source-bound", "Quoted by the supplied guide as Btu to kWh."),
            F("mj-to-kwh-2020", "mj", "kwh", "0.2778", "carbon-trust-conversion-2020", 2020, "United Kingdom/source guide", "historical-source-bound", "Quoted by the supplied guide as MJ to kWh."),
            F("toe-to-kwh-2020", "toe", "kwh", "11630", "carbon-trust-conversion-2020", 2020, "United Kingdom/source guide", "historical-source-bound", "Quoted by the supplied guide as tonnes of oil equivalent to kWh."),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_carbon_factors() -> dict[str, EnergyCarbonFactorRecord]:
        F = EnergyCarbonFactorRecord
        base = dict(source_key="carbon-trust-conversion-2020", source_year=2020, geography="United Kingdom", emissions_boundary="direct", accounting_scope="fuel-use or electricity-generation boundary", status="historical-source-bound")
        rows = [
            F("uk-grid-electricity-kwh-2020", "UK grid electricity", "kwh", "0.23314", accounting_scope="Scope 2 location-based generation factor; Scope 3 separate", note="The guide identifies this as electricity generated under the location-based method.", **{k:v for k,v in base.items() if k!='accounting_scope'}),
            F("natural-gas-kwh-2020", "Natural gas", "kwh", "0.18387", note="Direct kgCO2e per kWh in the supplied 2020 guide.", **base),
            F("natural-gas-therm-2020", "Natural gas", "therm", "5.388678", note="Direct kgCO2e per therm in the supplied 2020 guide.", **base),
            F("natural-gas-cubic-metre-2020", "Natural gas", "cubic-metre", "2.02266", note="Direct kgCO2e per cubic metre in the supplied 2020 guide.", **base),
            F("lpg-kwh-2020", "LPG", "kwh", "0.21448", note="Direct kgCO2e per kWh in the supplied 2020 guide.", **base),
            F("lpg-therm-2020", "LPG", "therm", "6.285765", note="Direct kgCO2e per therm in the supplied 2020 guide.", **base),
            F("lpg-litre-2020", "LPG", "litre", "1.55537", note="Direct kgCO2e per litre in the supplied 2020 guide.", **base),
            F("gas-oil-tonne-2020", "Gas oil", "tonne", "3229.34", note="Direct kgCO2e per tonne in the supplied 2020 guide.", **base),
            F("gas-oil-kwh-2020", "Gas oil", "kwh", "0.25672", note="Direct kgCO2e per kWh in the supplied 2020 guide.", **base),
            F("gas-oil-litre-2020", "Gas oil", "litre", "2.75776", note="Direct kgCO2e per litre in the supplied 2020 guide.", **base),
            F("fuel-oil-tonne-2020", "Fuel oil", "tonne", "3221.37", note="Direct kgCO2e per tonne in the supplied 2020 guide.", **base),
            F("fuel-oil-kwh-2020", "Fuel oil", "kwh", "0.26775", note="Direct kgCO2e per kWh in the supplied 2020 guide.", **base),
            F("burning-oil-tonne-2020", "Burning oil", "tonne", "3165.32", note="Direct kgCO2e per tonne in the supplied 2020 guide.", **base),
            F("burning-oil-kwh-2020", "Burning oil", "kwh", "0.24666", note="Direct kgCO2e per kWh in the supplied 2020 guide.", **base),
            F("diesel-tonne-2020", "Diesel", "tonne", "3028.61", note="Direct kgCO2e per tonne; source footnote describes standard forecourt fuel with typical biofuel content.", **base),
            F("diesel-kwh-2020", "Diesel", "kwh", "0.24057", note="Direct kgCO2e per kWh; source footnote describes standard forecourt fuel with typical biofuel content.", **base),
            F("diesel-litre-2020", "Diesel", "litre", "2.54603", note="Direct kgCO2e per litre; source footnote describes standard forecourt fuel with typical biofuel content.", **base),
            F("petrol-tonne-2020", "Petrol", "tonne", "2942.05", note="Direct kgCO2e per tonne; source footnote describes standard forecourt fuel with typical biofuel content.", **base),
            F("petrol-kwh-2020", "Petrol", "kwh", "0.22920", note="Direct kgCO2e per kWh; source footnote describes standard forecourt fuel with typical biofuel content.", **base),
            F("petrol-litre-2020", "Petrol", "litre", "2.16802", note="Direct kgCO2e per litre; source footnote describes standard forecourt fuel with typical biofuel content.", **base),
            F("industrial-coal-tonne-2020", "Industrial coal", "tonne", "2380.01", note="Direct kgCO2e per tonne in the supplied 2020 guide.", **base),
            F("industrial-coal-kwh-2020", "Industrial coal", "kwh", "0.32040", note="Direct kgCO2e per kWh in the supplied 2020 guide.", **base),
            F("wood-pellets-tonne-2020", "Wood pellets", "tonne", "72.29731", note="The guide states that its wood-pellet factor includes methane and nitrous oxide emitted during combustion.", **base),
            F("wood-pellets-kwh-2020", "Wood pellets", "kwh", "0.01545", note="The guide states that its wood-pellet factor includes methane and nitrous oxide emitted during combustion.", **base),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_heat_content_factors() -> dict[str, EnergyHeatContentFactorRecord]:
        F = EnergyHeatContentFactorRecord
        base = dict(source_key="carbon-trust-conversion-2020", source_year=2020, geography="United Kingdom/source guide", calorific_basis="gross calorific value", status="historical-source-bound")
        rows = [
            F("fuel-oil-kwh-per-tonne-2020", "Fuel oil", "tonne", "12031", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("fuel-oil-kwh-per-litre-2020", "Fuel oil", "litre", "11.89", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("lpg-kwh-per-tonne-2020", "LPG", "tonne", "13702", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("lpg-kwh-per-litre-2020", "LPG", "litre", "7.25", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("diesel-kwh-per-tonne-2020", "Diesel", "tonne", "12589", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("diesel-kwh-per-litre-2020", "Diesel", "litre", "10.58", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("gas-oil-kwh-per-tonne-2020", "Gas oil", "tonne", "12579", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("gas-oil-kwh-per-litre-2020", "Gas oil", "litre", "10.74", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("burning-oil-kwh-per-tonne-2020", "Burning oil", "tonne", "12833", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("burning-oil-kwh-per-litre-2020", "Burning oil", "litre", "10.30", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("petrol-kwh-per-tonne-2020", "Petrol", "tonne", "12836", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("petrol-kwh-per-litre-2020", "Petrol", "litre", "9.46", note="Default gross calorific value when fuel-specific supplier values are unavailable.", **base),
            F("industrial-coal-kwh-per-tonne-2020", "Industrial coal", "tonne", "7428", note="Default gross calorific value in the supplied 2020 guide.", **base),
            F("wood-pellets-kwh-per-tonne-2020", "Wood pellets", "tonne", "5080", note="Default gross calorific value in the supplied 2020 guide.", **base),
            F("straw-kwh-per-tonne-2020", "Straw", "tonne", "4401", note="Default gross calorific value in the supplied 2020 guide.", **base),
            F("natural-gas-kwh-per-tonne-2020", "Natural gas", "tonne", "13776", note="Only the unambiguous kWh-per-tonne value from the parsed supplied table is activated; ambiguous parsed volume fields are omitted.", **base),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_indicators() -> dict[str, EnergyIndicatorDefinition]:
        """Build the 30-indicator EISD table reproduced in Vera & Langlois (2007).

        The supplied article names and classifies the indicators, but points readers to
        separate methodology sheets for exact definitions, construction methods, units,
        data issues and sources. Those methodology sheets were not supplied. Therefore
        v0.3.0 activates governed definitions and observation contracts only; it does not
        claim to implement the official EISD formulas.
        """
        I = EnergyIndicatorDefinition
        shared = ("geography", "period", "value", "unit", "data_source", "methodology_reference", "quality_note")
        rows = [
            I("SOC1", "Share of households (or population) without electricity or commercial energy, or heavily dependent on non-commercial energy", "social", "equity", "accessibility", "Accessibility of modern/commercial energy services.", "share", shared + ("population_or_household_basis", "access_definition"), ("energy-access", "global-energy-importance"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "The table supplies the indicator title; exact numerator, denominator and classification rules require the cited methodology sheet."),
            I("SOC2", "Share of household income spent on fuel and electricity", "social", "equity", "affordability", "Household energy affordability expressed through the share of income spent on fuel and electricity.", "share", shared + ("income_group", "energy_expenditure_basis", "income_basis"), ("energy-affordability", "energy-access"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Income and expenditure definitions must remain explicit; the source article does not reproduce the methodology sheet."),
            I("SOC3", "Household energy use for each income group and corresponding fuel mix", "social", "equity", "disparities", "Distribution of household energy use and fuel mix across income groups.", "disaggregated-profile", shared + ("income_group", "fuel_or_carrier", "energy_use_basis"), ("energy-access", "energy-mix"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "This is a disaggregated profile rather than a single universal scalar."),
            I("SOC4", "Accident fatalities per energy produced by fuel chain", "social", "health", "safety", "Safety burden associated with energy fuel chains relative to energy produced.", "rate", shared + ("fuel_chain", "fatality_definition", "energy_production_basis"), ("energy-system",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Fuel-chain boundary and fatality inclusion criteria require the official methodology."),
            I("ECO1", "Energy use per capita", "economic", "use-and-production-patterns", "overall-use", "Aggregate energy use normalized by population.", "intensity", shared + ("energy_use_basis", "population_basis"), ("energy-intensity", "global-energy-importance"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Primary/final energy scope and population conventions must be declared before calculation."),
            I("ECO2", "Energy use per unit of GDP", "economic", "use-and-production-patterns", "overall-productivity", "Aggregate energy use relative to economic output.", "intensity", shared + ("energy_use_basis", "gdp_basis", "currency_price_basis"), ("energy-intensity", "decoupling"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "GDP price basis, purchasing-power treatment and energy boundary require explicit methodology."),
            I("ECO3", "Efficiency of energy conversion and distribution", "economic", "use-and-production-patterns", "supply-efficiency", "Efficiency of transformation and distribution within the energy supply system.", "efficiency", shared + ("conversion_or_distribution_scope", "input_energy_basis", "output_energy_basis"), ("energy-efficiency", "energy-conversion"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "The article names the indicator but does not reproduce the official aggregation formula."),
            I("ECO4", "Reserves-to-production ratio", "economic", "use-and-production-patterns", "production", "Relation between energy reserves and production.", "ratio", shared + ("reserve_definition", "production_definition", "resource_or_fuel"), ("reserves-to-production", "energy-reserve"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Reserve classification and production period must be sourced and explicit."),
            I("ECO5", "Resources-to-production ratio", "economic", "use-and-production-patterns", "production", "Relation between energy resources and production.", "ratio", shared + ("resource_definition", "production_definition", "resource_or_fuel"), ("resources-to-production", "energy-resource"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Resource classification is methodology-sensitive and is not inferred."),
            I("ECO6", "Industrial energy intensities", "economic", "use-and-production-patterns", "end-use", "Energy intensity of industrial activity.", "sector-intensity", shared + ("sector", "energy_use_basis", "activity_or_output_basis"), ("energy-intensity", "end-use"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Sector boundaries and activity denominators must be harmonized for comparison."),
            I("ECO7", "Agricultural energy intensities", "economic", "use-and-production-patterns", "end-use", "Energy intensity of agricultural activity.", "sector-intensity", shared + ("sector", "energy_use_basis", "activity_or_output_basis"), ("energy-intensity", "end-use"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Sector boundaries and activity denominators must be harmonized for comparison."),
            I("ECO8", "Service/commercial energy intensities", "economic", "use-and-production-patterns", "end-use", "Energy intensity of service and commercial activity.", "sector-intensity", shared + ("sector", "energy_use_basis", "activity_or_output_basis"), ("energy-intensity", "end-use"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Sector boundaries and activity denominators must be harmonized for comparison."),
            I("ECO9", "Household energy intensities", "economic", "use-and-production-patterns", "end-use", "Energy intensity of household activity or services.", "sector-intensity", shared + ("household_basis", "energy_use_basis", "activity_or_service_basis"), ("energy-intensity", "end-use"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Household denominator and service basis require the official methodology."),
            I("ECO10", "Transport energy intensities", "economic", "use-and-production-patterns", "end-use", "Energy intensity of transport activity.", "sector-intensity", shared + ("transport_mode", "energy_use_basis", "activity_basis"), ("energy-intensity", "end-use"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Passenger/freight and activity-unit definitions must be explicit."),
            I("ECO11", "Fuel shares in energy and electricity", "economic", "use-and-production-patterns", "diversification-fuel-mix", "Fuel shares within energy use and electricity generation/capacity contexts.", "share-profile", shared + ("fuel_or_carrier", "energy_or_electricity_basis", "denominator_scope"), ("energy-mix",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "The observation contract supports multiple fuel shares; denominator scope must be declared."),
            I("ECO12", "Non-carbon energy share in energy and electricity", "economic", "use-and-production-patterns", "diversification-fuel-mix", "Share of non-carbon energy within energy and electricity contexts.", "share", shared + ("non_carbon_definition", "energy_or_electricity_basis", "denominator_scope"), ("energy-mix",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "The source title is retained; classification of non-carbon sources requires the official methodology."),
            I("ECO13", "Renewable energy share in energy and electricity", "economic", "use-and-production-patterns", "diversification-fuel-mix", "Share of renewable energy within energy and electricity contexts.", "share", shared + ("renewable_definition", "energy_or_electricity_basis", "denominator_scope"), ("renewable-energy-share", "renewable-energy", "energy-mix"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Renewable classification and denominator scope must be explicit and source-governed."),
            I("ECO14", "End-use energy prices by fuel and by sector", "economic", "use-and-production-patterns", "prices", "End-use energy prices disaggregated by fuel and sector.", "price-profile", shared + ("fuel_or_carrier", "sector", "price_basis", "currency"), ("energy-affordability",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Taxes, subsidies, currency and real/nominal price treatment must be preserved."),
            I("ECO15", "Net energy import dependency", "economic", "security", "imports", "Dependence of an energy system on net energy imports.", "dependency-ratio", shared + ("import_export_boundary", "energy_supply_basis", "fuel_or_carrier"), ("energy-security",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "The article does not reproduce the official denominator or treatment of negative net imports."),
            I("ECO16", "Stocks of critical fuels per corresponding fuel consumption", "economic", "security", "strategic-fuel-stocks", "Availability of critical fuel stocks relative to corresponding fuel consumption.", "stock-to-use-ratio", shared + ("critical_fuel", "stock_definition", "consumption_basis"), ("energy-security",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Critical-fuel designation and stock coverage require explicit methodology."),
            I("ENV1", "GHG emissions from energy production and use per capita and per unit of GDP", "environmental", "atmosphere", "climate-change", "Greenhouse-gas emissions from energy production and use normalized by population and/or GDP.", "emissions-intensity", shared + ("emissions_boundary", "gas_accounting_basis", "normalization_basis"), ("ghg-emissions", "co2e"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "v0.2.0 historical direct-carbon factors are not silently substituted for this indicator's full methodology."),
            I("ENV2", "Ambient concentrations of air pollutants in urban areas", "environmental", "atmosphere", "air-quality", "Ambient urban air-pollutant concentrations relevant to energy-system impacts.", "concentration-profile", shared + ("pollutant", "monitoring_location", "concentration_basis"), ("air-quality-impact",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Monitoring design, averaging time and pollutant definitions require source methodology."),
            I("ENV3", "Air-pollutant emissions from energy systems", "environmental", "atmosphere", "air-quality", "Air-pollutant emissions attributable to energy systems.", "emissions-profile", shared + ("pollutant", "energy_system_boundary", "emissions_basis"), ("air-quality-impact", "energy-system"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Pollutant inventory boundary and attribution method must be declared."),
            I("ENV4", "Contaminant discharges in liquid effluents from energy systems", "environmental", "water", "water-quality", "Liquid-effluent contaminant discharges attributable to energy systems.", "discharge-profile", shared + ("contaminant", "effluent_boundary", "discharge_basis"), ("water-quality-impact", "energy-system"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Contaminant, discharge and system boundaries require explicit methodology."),
            I("ENV5", "Soil area where acidification exceeds critical load", "environmental", "land", "soil-quality", "Soil area exceeding an acidification critical-load threshold.", "area-threshold", shared + ("critical_load_definition", "area_basis", "threshold_source"), ("land-impact",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Critical-load threshold and mapped-area methodology are not inferred."),
            I("ENV6", "Rate of deforestation attributed to energy use", "environmental", "land", "forest", "Deforestation rate attributed to energy use.", "rate", shared + ("forest_definition", "attribution_method", "area_change_basis"), ("land-impact", "forest-ecology", "forest-carbon"), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Attribution of deforestation to energy use requires an explicit causal/accounting method."),
            I("ENV7", "Ratio of solid-waste generation to units of energy produced", "environmental", "land", "solid-waste-generation-and-management", "Solid-waste generation relative to energy produced.", "waste-intensity", shared + ("solid_waste_boundary", "energy_production_basis"), ("energy-system",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Waste classification and energy-production denominator require the official methodology."),
            I("ENV8", "Ratio of solid waste properly disposed of to total generated solid waste", "environmental", "land", "solid-waste-generation-and-management", "Share or ratio of generated solid waste that is properly disposed of.", "waste-management-ratio", shared + ("proper_disposal_definition", "solid_waste_boundary"), ("energy-system",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Proper-disposal classification must be source-defined."),
            I("ENV9", "Ratio of solid radioactive waste to units of energy produced", "environmental", "land", "solid-waste-generation-and-management", "Solid radioactive waste relative to energy produced.", "radioactive-waste-intensity", shared + ("radioactive_waste_boundary", "energy_production_basis"), ("energy-system",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Radioactive-waste classification and energy denominator require the official methodology."),
            I("ENV10", "Ratio of solid radioactive waste awaiting disposal to total generated solid radioactive waste", "environmental", "land", "solid-waste-generation-and-management", "Share or ratio of generated radioactive waste awaiting disposal.", "radioactive-waste-management-ratio", shared + ("awaiting_disposal_definition", "radioactive_waste_boundary"), ("energy-system",), "vera-langlois-2007", 2007, "methodology-sheet-required", "not-implemented", "Awaiting-disposal and total-generation definitions require the official methodology."),
        ]
        return {row.code: row for row in rows}

    @staticmethod
    def _build_methodology_rules() -> list[dict[str, Any]]:
        return [
            {"key": "direct-emissions-boundary", "source_key": "carbon-trust-conversion-2020", "source_year": 2020, "rule": "Energy carbon factors in the guide are total direct kgCO2e per unit of fuel; direct emissions occur at fuel use or at electricity generation.", "current_default": False},
            {"key": "indirect-emissions-excluded", "source_key": "carbon-trust-conversion-2020", "source_year": 2020, "rule": "The supplied guide states that these factors do not include indirect emissions such as extraction or refining.", "current_default": False},
            {"key": "co2e-combined-gases", "source_key": "carbon-trust-conversion-2020", "source_year": 2020, "rule": "The guide expresses the combined greenhouse effect of CO2, CH4 and N2O as kgCO2e using global warming potential.", "current_default": False},
            {"key": "renewable-electricity-accounting", "source_key": "carbon-trust-conversion-2020", "source_year": 2020, "rule": "For green-tariff electricity, the guide directs location-based reporting to the grid factor and market-based reporting to supplier-specific or residual-grid factors when applicable; v0.2.0 does not fabricate a universal renewable-electricity factor.", "current_default": False},
            {"key": "gross-calorific-basis", "source_key": "carbon-trust-conversion-2020", "source_year": 2020, "rule": "Fuel heat-content records are gross calorific values; the guide explains that net values exclude energy associated with water evaporation.", "current_default": False},
            {"key": "eisd-table-definition-boundary", "source_key": "vera-langlois-2007", "source_year": 2007, "rule": "The supplied article identifies and classifies 30 EISD indicators across social, economic, and environmental dimensions; v0.3.0 preserves those names and classifications as source-grounded definitions.", "current_default": False},
            {"key": "eisd-methodology-sheet-boundary", "source_key": "vera-langlois-2007", "source_year": 2007, "rule": "The article states that separate methodology sheets provide definitions, methods, components, units, construction instructions, data issues, sources, availability, and sustainable-development relevance. Those sheets were not supplied, so exact official formulas are not implemented.", "current_default": False},
            {"key": "indicator-comparison-boundary", "source_key": "vera-langlois-2007", "source_year": 2007, "rule": "Indicator observations should retain geography, period, unit, denominator or disaggregation basis, source, and methodology so comparisons do not silently mix incompatible definitions or contexts.", "current_default": False},
            {"key": "renewable-technology-profile-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "The module identifies renewable technology families and requires understanding of physical/technological principles and future prospects, but the supplied material does not provide a current quantitative performance database. v0.4.0 therefore structures technology objects without universal efficiency, cost, capacity-factor, lifecycle-emissions, or maturity values.", "current_default": False},
            {"key": "renewable-resource-potential-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "Resource estimation and evaluation require geography-, period-, metric-, method-, and source-specific evidence. A resource observation is not automatically gross, technical, economic, or sustainable potential and does not establish project suitability.", "current_default": False},
            {"key": "energy-economics-formula-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "The supplied module scope requires energy balance, cost-benefit, and cost-efficiency analysis but does not provide a current price database or complete financial methodology. v0.6.0 therefore treats prices, costs, lifetimes, discount rates, benefits, savings, and outcomes as explicit scenario assumptions and labels its formulas as Sustainable Catalyst implementation arithmetic rather than source-attributed universal defaults.", "current_default": False},
            {"key": "biological-carbon-bioenergy-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "The supplied module explicitly includes soil carbon, CO2-to-energy, forests/forest ecology, digestate from anaerobic digestion, biochar, biomass-to-oil, and bioenergy. It does not supply universal yields, carbon fractions, stable fractions, lifecycle factors, avoided-emissions factors, or carbon-credit rules; v0.7.0 therefore requires explicit quantitative assumptions and whole-system carbon boundaries.", "current_default": False},
            {"key": "global-energy-live-observation-boundary", "source_key": "world-bank-indicators-api", "source_year": 2026, "rule": "v0.8.0 may retrieve live country observations for selected World Bank indicator codes, but the observation's own year and source semantics remain authoritative. Latest available is not relabeled as current-year data; missing values are not interpolated; World Bank metric semantics are not asserted to be official EISD formula equivalents.", "current_default": False},
            {"key": "energy-decision-comparison-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "The module scope calls for multidisciplinary energy-system analysis, efficiency, cost-benefit, cost-efficiency, renewable technologies, biological carbon, and sustainability context. v0.9.0 uses those dimensions to organize evidence but treats the decision packet, comparison matrix, readiness inspection, and no-ranking/no-hidden-weighting rules as Sustainable Catalyst implementation governance rather than source-attributed decision methodology.", "current_default": False},
            {"key": "integrated-platform-certification-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "v1.0.0 integrates the previously implemented Energy Systems layers into a single governed platform contract and repository-coherence certification. The certification validates expected release identities, counts, contracts, and guardrails; it is Sustainable Catalyst implementation governance, not scientific validation, live deployment certification, site suitability, or financial advice.", "current_default": False},
            {"key": "cross-product-runtime-activation-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "v1.2.0 activates target-side contract-intake consumers in Research Librarian, Lab, Workbench, Site Intelligence, and Decision Studio. Certification covers validation and deterministic receipt only; it does not certify model execution, persistence, scientific validity, ranking, or recommendation.", "current_default": False},
            {"key": "energy-workbench-runtime-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "v1.3.0 certifies explicit-input arithmetic execution in Workbench v6.2.0 for unit conversion, energy balances, generation, scenario economics, and bioenergy/carbon calculations. The runtime does not infer missing inputs, fetch market data, persist studies, rank alternatives, recommend a winner, or convert stoichiometric carbon equivalence into a carbon-credit claim.", "current_default": False},
            {"key": "energy-modeling-uncertainty-boundary", "source_key": "ucd-module-sustainable-energy", "source_year": 2026, "rule": "v1.4.0 certifies seeded uncertainty design and statistical analysis in Lab v0.102.0 around explicit Workbench v6.2.0 results. Probability distributions, seeds, result paths, and calculation inputs must be explicit. Sensitivity ranks input influence only and is not technology ranking.", "current_default": False},
        ]

    @staticmethod
    def _build_handoffs() -> list[dict[str, Any]]:
        return [
            {"key": "soil-carbon-to-carbon-nature", "source_concepts": ["soil-carbon"], "target": "Carbon & Nature Intelligence", "target_refs": ["soil-organic-carbon", "whole-system-ghg-accounting"], "status": "cross-domain-contract-available", "boundary": "v0.8.0 preserves the v0.7.0 Energy Systems soil-carbon bridge to Carbon & Nature; it does not quantify sequestration, permanence, additionality, or project suitability."},
            {"key": "forest-to-carbon-nature", "source_concepts": ["forest-carbon", "forest-ecology", "biomass"], "target": "Carbon & Nature Intelligence", "target_refs": ["forest-woodland", "aboveground-biomass", "belowground-biomass", "biomass-inventory-measurement", "whole-system-ghg-accounting"], "status": "cross-domain-contract-available", "boundary": "Forest/biomass energy questions preserve governed land-system, biomass-pool, and accounting contexts; biomass carbon neutrality, stock change, permanence, harvest/regrowth balance, leakage, and project eligibility are not inferred."},
            {"key": "bioenergy-carbon-nature-extension", "source_concepts": ["anaerobic-digestion", "digestate", "biochar", "biomass-to-oil", "co2-to-energy", "bioenergy", "biomass"], "target": "Carbon & Nature Intelligence", "target_refs": ["soil-organic-carbon", "forest-woodland", "aboveground-biomass", "belowground-biomass", "carbon-dioxide", "methane", "nitrous-oxide", "whole-system-ghg-accounting"], "status": "cross-domain-contract-available", "boundary": "v0.8.0 preserves the validated v0.7.0 semantic/accounting bridges without fabricating quantitative carbon benefits, lifecycle results, avoided emissions, or crediting claims."},
            {"key": "energy-to-research-librarian", "source_concepts": ["energy-system", "sustainable-energy", "energy-access", "renewable-energy-share", "energy-security", "energy-indicator"], "target": "Research Librarian", "target_refs": ["energy-runtime-research-librarian-handoff", "energy-source-provenance-registry", "energy-indicator-observation-contract", "global-energy-country-profile-contract", "energy-decision-packet-contract"], "status": "runtime-gateway-active-target-consumer-certified", "boundary": "v1.2.0 certifies Research Librarian v8.1.0 contract intake. The consumer treats the packet as research context, not verified evidence, truth, or publication approval."},
            {"key": "energy-to-workbench", "source_concepts": ["energy-balance", "energy-efficiency", "cost-benefit-analysis", "cost-efficiency-analysis", "co2e", "bioenergy", "anaerobic-digestion", "biochar", "biomass-to-oil"], "target": "Workbench", "target_refs": ["energy-runtime-workbench-handoff", "energy-workbench-runtime/1.0", "energy-unit-conversion-registry", "historical-carbon-factor-registry", "energy-balance-calculation-contract", "energy-scenario-economics-calculation-contract", "bioenergy-explicit-input-calculation-contract", "global-energy-country-profile-contract"], "status": "runtime-gateway-active-explicit-execution-certified", "boundary": "v1.3.0 certifies Workbench v6.2.0 explicit-input ephemeral arithmetic execution. /consume remains non-executing; /execute must be called explicitly. Hidden defaults, automatic persistence, ranking, recommendations, market-data substitution, avoided-emissions inference, and carbon-credit claims remain prohibited."},
            {"key": "energy-to-lab", "source_concepts": ["energy-system", "renewable-energy", "energy-balance", "energy-efficiency", "bioenergy", "biological-carbon-capture-storage", "energy-security"], "target": "Lab", "target_refs": ["energy-runtime-lab-handoff", "energy-modeling-uncertainty/1.0", "energy-balance-scenario-contract", "energy-economic-scenario-contract", "bioenergy-carbon-scenario-contract", "renewable-resource-observation-contract", "global-energy-country-profile-contract"], "status": "runtime-gateway-active-modeling-uncertainty-certified", "boundary": "v1.4.0 certifies Lab v0.102.0 seeded uncertainty planning and statistical analysis around explicit Workbench v6.2.0 results. Lab does not infer missing distributions, call Workbench automatically, rank technologies, recommend a winner, or persist studies automatically."},
            {"key": "energy-to-site-intelligence", "source_concepts": ["energy-access", "energy-mix", "renewable-energy-share", "energy-security", "renewable-resource-potential", "bioenergy", "forest-carbon"], "target": "Site Intelligence", "target_refs": ["global-energy-country-profile-contract", "global-energy-metric-observation", "world-bank-wdi-live-connector", "renewable-resource-observation-contract", "biomass-feedstock-observation-contract", "bioenergy-geography-contract"], "status": "runtime-gateway-active-target-consumer-certified", "boundary": "v1.2.0 certifies Site Intelligence v4.40.0 contract intake for dated country-energy and resource observations. Intake does not infer project suitability or hide observation-year lag."},
            {"key": "energy-to-decision-studio", "source_concepts": ["energy-prosperity-environment-dilemma", "cost-benefit-analysis", "cost-efficiency-analysis", "bioenergy", "biological-carbon-capture-storage", "energy-security", "energy-access"], "target": "Decision Studio", "target_refs": ["energy-decision-packet-contract", "energy-decision-comparison-matrix", "energy-decision-readiness-contract", "energy-economic-scenario-contract", "energy-cost-benefit-result", "energy-cost-efficiency-result", "energy-npv-result", "bioenergy-carbon-scenario-contract", "carbon-nature-accounting-handoff", "global-energy-country-profile-contract", "global-energy-comparison-contract"], "status": "runtime-gateway-active-target-consumer-certified", "boundary": "v1.2.0 certifies Decision Studio v2.3.0 contract intake around the governed Energy Decision Intelligence packet. Intake does not normalize unlike evidence, assign hidden weights, rank alternatives, select a winner, recommend investments, or make policy choices."},
        ]

    @staticmethod
    def _build_guardrails() -> dict[str, Any]:
        return {
            "knowledge_foundation_preserved": True,
            "concept_match_is_not_evidence": True,
            "relationship_is_not_causal_proof": True,
            "technology_presence_is_not_technology_suitability": True,
            "renewable_label_is_not_zero_impact_claim": True,
            "source_vintage_must_be_preserved": True,
            "historical_source_is_not_current_state": True,
            "current_policy_price_grid_or_emission_data_require_current_sources": True,
            "resource_or_reserve_values_not_inferred": True,
            "conversion_factors_activated": True,
            "carbon_factor_calculation_activated": True,
            "heat_content_factor_calculation_activated": True,
            "current_factor_defaults_activated": False,
            "historical_calculation_is_not_current_inventory": True,
            "calculation_requires_explicit_source_bound_inputs": True,
            "workbench_execution_activated": True,
            "workbench_explicit_input_execution_only": True,
            "workbench_automatic_execution_activated": False,
            "workbench_automatic_persistence_activated": False,
            "workbench_automatic_ranking_activated": False,
            "workbench_automatic_recommendation_activated": False,
            "energy_indicator_definition_registry_activated": True,
            "energy_indicator_observation_contracts_activated": True,
            "official_eisd_methodology_sheets_loaded": False,
            "energy_indicator_calculation_activated": False,
            "indicator_definition_is_not_observed_value": True,
            "indicator_value_is_not_sustainability_score": True,
            "cross_geography_comparison_requires_harmonized_methodology": True,
            "renewable_technology_registry_activated": True,
            "renewable_resource_class_registry_activated": True,
            "renewable_technology_assessment_contracts_activated": True,
            "renewable_resource_observation_contracts_activated": True,
            "quantitative_technology_profiles_loaded": False,
            "live_resource_potential_datasets_loaded": False,
            "renewable_suitability_assessment_activated": False,
            "scenario_modeling_activated": True,
            "deterministic_energy_balance_calculation_activated": True,
            "conversion_chain_model_activated": True,
            "supply_demand_balance_model_activated": True,
            "capacity_factor_generation_estimate_activated": True,
            "energy_balance_scenario_contract_activated": True,
            "scenario_inputs_must_be_explicit": True,
            "time_series_dispatch_simulation_activated": False,
            "grid_reliability_or_adequacy_model_activated": False,
            "storage_physics_simulation_activated": False,
            "economic_optimization_activated": False,
            "scenario_persistence_activated": False,
            "energy_scenario_economics_activated": True,
            "energy_cost_comparison_activated": True,
            "energy_simple_payback_activated": True,
            "energy_npv_activated": True,
            "energy_cost_benefit_activated": True,
            "energy_cost_efficiency_activated": True,
            "energy_levelized_cost_estimate_activated": True,
            "energy_economic_scenario_contract_activated": True,
            "external_energy_price_feed_loaded": False,
            "technology_cost_database_loaded": False,
            "discount_rate_inference_activated": False,
            "technology_lifetime_inference_activated": False,
            "tax_subsidy_financing_model_activated": False,
            "investment_recommendation_activated": False,
            "biological_carbon_bioenergy_integration_activated": True,
            "bioenergy_feedstock_registry_activated": True,
            "bioenergy_pathway_registry_activated": True,
            "carbon_nature_bridge_registry_activated": True,
            "bioenergy_explicit_input_calculators_activated": True,
            "bioenergy_carbon_scenario_contract_activated": True,
            "biomass_carbon_neutrality_assumed": False,
            "bioenergy_lifecycle_emissions_inferred": False,
            "bioenergy_avoided_emissions_inferred": False,
            "digestate_climate_benefit_inferred": False,
            "biochar_permanence_or_credit_eligibility_inferred": False,
            "soil_or_forest_carbon_change_inferred_from_bioenergy": False,
            "global_energy_intelligence_activated": True,
            "world_bank_live_connector_activated": True,
            "global_energy_metric_registry_activated": True,
            "global_energy_country_profile_contract_activated": True,
            "global_energy_country_comparison_contract_activated": True,
            "latest_available_observation_is_current_year_assumed": False,
            "missing_global_energy_values_interpolated": False,
            "cross_source_harmonization_assumed": False,
            "provider_failure_fabricated_fallback": False,
            "world_bank_metric_is_official_eisd_formula_assumed": False,
            "embedded_current_country_values": False,
            "energy_decision_intelligence_activated": True,
            "energy_decision_criterion_registry_activated": True,
            "energy_decision_packet_contract_activated": True,
            "energy_decision_comparison_matrix_activated": True,
            "energy_decision_readiness_inspection_activated": True,
            "energy_decision_automatic_normalization": False,
            "energy_decision_automatic_weight_assignment": False,
            "energy_decision_composite_score": False,
            "energy_decision_automatic_ranking": False,
            "energy_decision_winner_selection": False,
            "energy_decision_studio_execution": False,
            "integrated_platform_activated": True,
            "cross_product_runtime_activation_gateway_activated": True,
            "cross_product_target_runtime_consumers_activated": True,
            "cross_product_target_model_execution_certified": False,
            "cross_product_target_packet_builders_activated": True,
            "cross_product_pull_handoff_transport_activated": True,
            "cross_product_target_runtime_consumption_certified": True,
            "cross_product_outbound_push_delivery_activated": False,
            "cross_product_persistence_activated": False,
            "integrated_study_contract_activated": True,
            "platform_structural_certification_activated": True,
            "cross_product_contract_registry_activated": True,
            "cross_product_execution_claimed_by_this_release": False,
            "target_product_runtimes_modified_by_this_release": False,
            "platform_certification_is_scientific_validation": False,
            "platform_certification_is_live_deployment_audit": False,
            "automatic_technology_ranking": False,
            "automatic_policy_recommendation": False,
            "automatic_sustainability_score": False,
            "source_pdf_redistribution": False,
        }

    def _validate(self) -> None:
        allowed_sources = set(self._sources)
        keys = set(self._concepts)
        if len(keys) != len(self._concepts):
            raise ValueError("Energy concept keys must be unique")
        for concept in self._concepts.values():
            missing = set(concept.source_keys) - allowed_sources
            if missing:
                raise ValueError(f"Unknown source(s) for {concept.key}: {sorted(missing)}")
        for rel in self._relationships:
            if rel.subject not in keys or rel.object not in keys:
                raise ValueError(f"Relationship contains unknown concept: {rel}")
            missing = set(rel.source_keys) - allowed_sources
            if missing:
                raise ValueError(f"Unknown relationship source(s): {sorted(missing)}")
            if rel.inference_allowed:
                raise ValueError("Energy-system relationships must remain non-inferential")
        for unit in self._units.values():
            if unit.source_key not in allowed_sources:
                raise ValueError(f"Unknown unit source: {unit.source_key}")
        for factor in self._conversion_factors.values():
            if factor.source_key not in allowed_sources or factor.from_unit not in self._units or factor.to_unit not in self._units:
                raise ValueError(f"Invalid conversion factor: {factor.key}")
            self._positive_decimal(factor.factor, factor.key)
        for factor in self._carbon_factors.values():
            if factor.source_key not in allowed_sources or factor.unit not in self._units:
                raise ValueError(f"Invalid carbon factor: {factor.key}")
            self._positive_decimal(factor.kg_co2e_per_unit, factor.key)
        for factor in self._heat_content_factors.values():
            if factor.source_key not in allowed_sources or factor.unit not in self._units:
                raise ValueError(f"Invalid heat-content factor: {factor.key}")
            self._positive_decimal(factor.kwh_per_unit, factor.key)
        if len(self._indicators) != 30:
            raise ValueError("Energy indicator registry must contain the 30 EISD indicators represented in the supplied article table")
        expected_codes = {f"SOC{i}" for i in range(1, 5)} | {f"ECO{i}" for i in range(1, 17)} | {f"ENV{i}" for i in range(1, 11)}
        if set(self._indicators) != expected_codes:
            raise ValueError("Energy indicator codes do not match the source table")
        for indicator in self._indicators.values():
            if indicator.source_key not in allowed_sources:
                raise ValueError(f"Unknown indicator source: {indicator.source_key}")
            missing_concepts = set(indicator.related_concepts) - keys
            if missing_concepts:
                raise ValueError(f"Unknown related concepts for {indicator.code}: {sorted(missing_concepts)}")
            if indicator.calculation_status != "not-implemented" or indicator.methodology_status != "methodology-sheet-required":
                raise ValueError("v0.5.0 must not claim official EISD formula implementation")
        technology_framework = self._technology_registry.framework()
        if technology_framework["counts"]["technologies"] != 7 or technology_framework["counts"]["resource_classes"] != 6:
            raise ValueError("Renewable technology/resource registry is incomplete")
        balance_framework = self._balance_model.framework()
        if balance_framework["counts"]["models"] != 4 or balance_framework["counts"]["executable_models"] != 3:
            raise ValueError("Energy balance/systems model registry is incomplete")
        economics_framework = self._economics_model.framework()
        if economics_framework["counts"]["models"] != 7 or economics_framework["counts"]["executable_models"] != 6:
            raise ValueError("Energy scenario economics model registry is incomplete")
        bioenergy_framework = self._bioenergy_model.framework()
        if bioenergy_framework["counts"]["feedstock_classes"] != 5 or bioenergy_framework["counts"]["pathways"] != 6:
            raise ValueError("Biological carbon/bioenergy integration registry is incomplete")
        global_framework = self._global_energy.framework()
        if global_framework["counts"]["metrics"] != 9 or global_framework["counts"]["sources"] != 4 or global_framework["counts"]["live_connectors"] != 1:
            raise ValueError("Global Energy Intelligence registry is incomplete")
        decision_framework = self._decision_intelligence.framework()
        if decision_framework["counts"]["criteria"] != 12 or decision_framework["counts"]["decision_packet_contracts"] != 1:
            raise ValueError("Energy Decision Intelligence registry is incomplete")
        runtime_framework = self._runtime_activation.framework()
        if runtime_framework["counts"]["external_runtime_targets"] != 5 or runtime_framework["counts"]["target_packet_builders"] != 5:
            raise ValueError("Cross-product runtime activation gateway is incomplete")
        uncertainty_framework = self._modeling_uncertainty.framework()
        if len(uncertainty_framework["sampling_designs"]) != 2 or len(uncertainty_framework["distributions"]) != 4:
            raise ValueError("Energy modeling & uncertainty registry is incomplete")

    def _content_fingerprint(self) -> str:
        content = {
            "version": DOMAIN_VERSION,
            "sources": [asdict(v) for v in self._sources.values()],
            "concepts": [asdict(v) for v in self._concepts.values()],
            "relationships": [asdict(v) for v in self._relationships],
            "domains": self._knowledge_domains,
            "sdgs": self._sdgs,
            "units": [asdict(v) for v in self._units.values()],
            "conversion_factors": [asdict(v) for v in self._conversion_factors.values()],
            "carbon_factors": [asdict(v) for v in self._carbon_factors.values()],
            "heat_content_factors": [asdict(v) for v in self._heat_content_factors.values()],
            "indicators": [asdict(v) for v in self._indicators.values()],
            "renewable_technology_resource_model": self._technology_registry.export(),
            "energy_balance_systems_model": self._balance_model.export(),
            "energy_scenario_economics": self._economics_model.export(),
            "biological_carbon_bioenergy_integration": self._bioenergy_model.export(),
            "global_energy_intelligence": self._global_energy.export(),
            "energy_decision_intelligence": self._decision_intelligence.export(),
            "integrated_sustainable_energy_platform": self._integrated_platform.export(),
            "cross_product_runtime_activation": self._runtime_activation.export(),
            "energy_modeling_uncertainty": self._modeling_uncertainty.framework(),
            "methodology_rules": self._methodology_rules,
            "handoffs": self._handoffs,
            "guardrails": self._guardrails,
        }
        return sha256(json.dumps(content, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    def manifest(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "subsystem": {
                "name": "Energy Systems Intelligence",
                "version": DOMAIN_VERSION,
                "release": "Energy Modeling & Uncertainty",
                "library_version": "5.11.0",
                "backend_version": "2.20.0",
                "read_only": True,
                "calculation_mode": "integrated-provenance-bound-energy-platform-with-explicit-workbench-execution-and-lab-uncertainty-analysis",
            },
            "counts": {
                "concepts": len(self._concepts),
                "relationships": len(self._relationships),
                "sources": len(self._sources),
                "knowledge_domains": len(self._knowledge_domains),
                "sdg_mappings": len(self._sdgs),
                "handoffs": len(self._handoffs),
                "units": len(self._units),
                "conversion_factors": len(self._conversion_factors),
                "carbon_factors": len(self._carbon_factors),
                "heat_content_factors": len(self._heat_content_factors),
                "indicators": len(self._indicators),
                "indicator_dimensions": 3,
                "indicator_themes": 7,
                "indicator_subthemes": 19,
                "indicator_observation_contracts": len(self._indicators),
                "renewable_technologies": self._technology_registry.framework()["counts"]["technologies"],
                "renewable_technology_families": self._technology_registry.framework()["counts"]["technology_families"],
                "renewable_resource_classes": self._technology_registry.framework()["counts"]["resource_classes"],
                "renewable_technology_assessment_contracts": self._technology_registry.framework()["counts"]["technology_assessment_contracts"],
                "renewable_resource_observation_contracts": self._technology_registry.framework()["counts"]["resource_observation_contracts"],
                "energy_balance_models": self._balance_model.framework()["counts"]["models"],
                "energy_balance_executable_models": self._balance_model.framework()["counts"]["executable_models"],
                "energy_balance_scenario_contracts": self._balance_model.framework()["counts"]["scenario_contracts"],
                "energy_economic_models": self._economics_model.framework()["counts"]["models"],
                "energy_economic_executable_models": self._economics_model.framework()["counts"]["executable_models"],
                "energy_economic_scenario_contracts": self._economics_model.framework()["counts"]["scenario_contracts"],
                "bioenergy_feedstock_classes": self._bioenergy_model.framework()["counts"]["feedstock_classes"],
                "bioenergy_pathways": self._bioenergy_model.framework()["counts"]["pathways"],
                "carbon_nature_bridges": self._bioenergy_model.framework()["counts"]["carbon_nature_bridges"],
                "bioenergy_executable_models": self._bioenergy_model.framework()["counts"]["executable_models"],
                "bioenergy_scenario_contracts": self._bioenergy_model.framework()["counts"]["scenario_contracts"],
                "global_energy_metrics": self._global_energy.framework()["counts"]["metrics"],
                "global_energy_sources": self._global_energy.framework()["counts"]["sources"],
                "global_energy_live_connectors": self._global_energy.framework()["counts"]["live_connectors"],
                "global_energy_profile_contracts": self._global_energy.framework()["counts"]["profile_contracts"],
                "global_energy_comparison_contracts": self._global_energy.framework()["counts"]["comparison_contracts"],
                "energy_decision_criteria": self._decision_intelligence.framework()["counts"]["criteria"],
                "energy_decision_dimensions": self._decision_intelligence.framework()["counts"]["dimensions"],
                "energy_decision_packet_contracts": self._decision_intelligence.framework()["counts"]["decision_packet_contracts"],
                "energy_decision_comparison_matrix_models": self._decision_intelligence.framework()["counts"]["comparison_matrix_models"],
                "energy_decision_readiness_models": self._decision_intelligence.framework()["counts"]["readiness_models"],
                "integrated_platform_release_layers": self._integrated_platform.framework()["counts"]["release_layers"],
                "integrated_platform_cross_product_contracts": self._integrated_platform.framework()["counts"]["cross_product_contracts"],
                "integrated_platform_study_contracts": self._integrated_platform.framework()["counts"]["integrated_study_contracts"],
                "integrated_platform_structural_certification_models": self._integrated_platform.framework()["counts"]["structural_certification_models"],
                "runtime_activation_targets": self._runtime_activation.framework()["counts"]["external_runtime_targets"],
                "runtime_handoff_packet_builders": self._runtime_activation.framework()["counts"]["target_packet_builders"],
                "runtime_pull_handoff_contracts": self._runtime_activation.framework()["counts"]["pull_handoff_contracts"],
                "runtime_target_runtimes_certified_active": self._runtime_activation.framework()["counts"]["target_runtimes_certified_active"],
                "energy_uncertainty_sampling_designs": len(self._modeling_uncertainty.framework()["sampling_designs"]),
                "energy_uncertainty_distributions": len(self._modeling_uncertainty.framework()["distributions"]),
                "methodology_rules": len(self._methodology_rules),
            },
            "knowledge_domains": self._knowledge_domains,
            "sdg_mappings": self._sdgs,
            "guardrails": self._guardrails,
            "renewable_technology_resource_model": self._technology_registry.framework(),
            "energy_balance_systems_model": self._balance_model.framework(),
            "energy_scenario_economics": self._economics_model.framework(),
            "biological_carbon_bioenergy_integration": self._bioenergy_model.framework(),
            "global_energy_intelligence": self._global_energy.framework(),
            "energy_decision_intelligence": self._decision_intelligence.framework(),
            "integrated_sustainable_energy_platform": self._integrated_platform.framework(),
            "cross_product_runtime_activation": self._runtime_activation.framework(),
            "energy_modeling_uncertainty": self._modeling_uncertainty.framework(),
            "roadmap": [
                {"version": "1.0.0", "name": "Integrated Sustainable Energy Systems Platform", "status": "certified-baseline"},
                {"version": "1.1.0", "name": "Cross-Product Runtime Activation Gateway", "status": "complete"},
                {"version": "1.2.0", "name": "Target-Side Runtime Consumers", "status": "complete"},
                {"version": "1.3.0", "name": "Energy Workbench Runtime", "status": "complete"},
                {"version": "1.4.0", "name": "Energy Modeling & Uncertainty", "status": "current"},
            ],
            "content_fingerprint": self._fingerprint,
        }

    def concepts(self, *, q: str = "", concept_type: str = "", domain: str = "", source: str = "", limit: int = 100) -> dict[str, Any]:
        qn = q.strip().lower()
        rows: list[dict[str, Any]] = []
        for concept in self._concepts.values():
            if concept_type and concept.concept_type != concept_type:
                continue
            if domain and concept.domain != domain:
                continue
            if source and source not in concept.source_keys:
                continue
            haystack = " ".join((concept.key, concept.label, concept.definition, concept.concept_type, concept.domain, *concept.tags)).lower()
            if qn and qn not in haystack:
                continue
            rows.append(asdict(concept))
            if len(rows) >= max(1, min(limit, 250)):
                break
        return {"ok": True, "schema": "sc-energy-concepts/1.0", "count": len(rows), "items": rows, "guardrail": "A concept match is not evidence, technology suitability, or a current-state claim."}

    def concept(self, key: str) -> dict[str, Any]:
        concept = self._concepts.get(key)
        if concept is None:
            raise KeyError(key)
        edges = [asdict(rel) for rel in self._relationships if rel.subject == key or rel.object == key]
        return {"ok": True, "schema": "sc-energy-concept/1.0", "concept": asdict(concept), "relationships": edges, "content_fingerprint": self._fingerprint}

    def relationships(self, *, subject: str = "", predicate: str = "", object_key: str = "", limit: int = 250) -> dict[str, Any]:
        rows = []
        for rel in self._relationships:
            if subject and rel.subject != subject:
                continue
            if predicate and rel.predicate != predicate:
                continue
            if object_key and rel.object != object_key:
                continue
            rows.append(asdict(rel))
            if len(rows) >= max(1, min(limit, 500)):
                break
        return {"ok": True, "schema": "sc-energy-relationships/1.0", "count": len(rows), "items": rows, "guardrail": "Typed relationships preserve source-grounded associations; they are not causal proof."}

    def sources(self) -> dict[str, Any]:
        return {"ok": True, "schema": "sc-energy-sources/1.0", "count": len(self._sources), "items": [asdict(v) for v in self._sources.values()], "guardrail": "Source year and role are preserved. Historical material is not treated as current quantitative truth."}

    def source(self, key: str) -> dict[str, Any]:
        source = self._sources.get(key)
        if source is None:
            raise KeyError(key)
        concepts = [c.key for c in self._concepts.values() if key in c.source_keys]
        relationships = [asdict(r) for r in self._relationships if key in r.source_keys]
        indicators = [i.code for i in self._indicators.values() if i.source_key == key]
        technologies = [t["key"] for t in self._technology_registry.technologies(limit=100)["items"] if key in t["source_keys"]]
        resources = [r["key"] for r in self._technology_registry.resource_classes()["items"] if key in r["source_keys"]]
        return {"ok": True, "schema": "sc-energy-source/1.0", "source": asdict(source), "concept_keys": concepts, "relationship_count": len(relationships), "indicator_codes": indicators, "technology_keys": technologies, "resource_class_keys": resources, "content_fingerprint": self._fingerprint}

    def knowledge_map(self) -> dict[str, Any]:
        grouped: dict[str, list[dict[str, Any]]] = {d["key"]: [] for d in self._knowledge_domains}
        for concept in self._concepts.values():
            grouped.setdefault(concept.domain, []).append({"key": concept.key, "label": concept.label, "concept_type": concept.concept_type})
        return {
            "ok": True,
            "schema": "sc-energy-knowledge-map/1.0",
            "domains": [{**domain, "concepts": grouped.get(domain["key"], [])} for domain in self._knowledge_domains],
            "relationships": [asdict(rel) for rel in self._relationships],
            "sdg_mappings": self._sdgs,
            "renewable_technology_resource_model": self._technology_registry.framework(),
            "content_fingerprint": self._fingerprint,
        }

    @staticmethod
    def _positive_decimal(value: str, label: str = "value") -> Decimal:
        try:
            number = Decimal(str(value))
        except (InvalidOperation, ValueError) as exc:
            raise ValueError(f"{label} must be numeric") from exc
        if not number.is_finite() or number < 0:
            raise ValueError(f"{label} must be a finite non-negative number")
        return number

    @staticmethod
    def _decimal_text(value: Decimal) -> str:
        if value == 0:
            return "0"
        text = format(value.normalize(), "f")
        if "." in text:
            text = text.rstrip("0").rstrip(".")
        return text

    def registry(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-numeric-registry/1.0",
            "domain_version": DOMAIN_VERSION,
            "source_binding": {
                "source_key": "carbon-trust-conversion-2020",
                "source_year": 2020,
                "geography": "United Kingdom/source guide",
                "status": "historical-source-bound",
                "current_default": False,
            },
            "counts": {
                "units": len(self._units),
                "conversion_factors": len(self._conversion_factors),
                "carbon_factors": len(self._carbon_factors),
                "heat_content_factors": len(self._heat_content_factors),
                "indicators": len(self._indicators),
                "methodology_rules": len(self._methodology_rules),
            },
            "methodology_rules": self._methodology_rules,
            "guardrail": "These records reproduce the supplied 2020 source within its stated boundary. They are not current grid, fuel, corporate-inventory, lifecycle, or policy defaults.",
            "content_fingerprint": self._fingerprint,
        }

    def units(self) -> dict[str, Any]:
        return {"ok": True, "schema": "sc-energy-units/1.0", "count": len(self._units), "items": [asdict(v) for v in self._units.values()], "guardrail": "Only units explicitly needed by the source-bound v0.2.0 registry are activated."}

    def conversion_factors(self) -> dict[str, Any]:
        return {"ok": True, "schema": "sc-energy-conversion-factors/1.0", "count": len(self._conversion_factors), "items": [asdict(v) for v in self._conversion_factors.values()], "guardrail": "Factors retain their 2020 source vintage; reverse conversions are derived arithmetically from the quoted source factor."}

    def carbon_factors(self, *, q: str = "", fuel: str = "", unit: str = "", limit: int = 100) -> dict[str, Any]:
        qn, fn = q.strip().lower(), fuel.strip().lower()
        rows: list[dict[str, Any]] = []
        for factor in self._carbon_factors.values():
            if fn and factor.fuel.lower() != fn:
                continue
            if unit and factor.unit != unit:
                continue
            haystack = " ".join((factor.key, factor.fuel, factor.unit, factor.note, factor.emissions_boundary, factor.accounting_scope)).lower()
            if qn and qn not in haystack:
                continue
            rows.append(asdict(factor))
            if len(rows) >= max(1, min(limit, 250)):
                break
        return {"ok": True, "schema": "sc-energy-carbon-factors/1.0", "count": len(rows), "items": rows, "guardrail": "All listed values are source-bound 2020 direct-emissions factors, not current defaults or lifecycle factors."}

    def heat_content_factors(self, *, q: str = "", fuel: str = "", unit: str = "", limit: int = 100) -> dict[str, Any]:
        qn, fn = q.strip().lower(), fuel.strip().lower()
        rows: list[dict[str, Any]] = []
        for factor in self._heat_content_factors.values():
            if fn and factor.fuel.lower() != fn:
                continue
            if unit and factor.unit != unit:
                continue
            haystack = " ".join((factor.key, factor.fuel, factor.unit, factor.note, factor.calorific_basis)).lower()
            if qn and qn not in haystack:
                continue
            rows.append(asdict(factor))
            if len(rows) >= max(1, min(limit, 250)):
                break
        return {"ok": True, "schema": "sc-energy-heat-content-factors/1.0", "count": len(rows), "items": rows, "guardrail": "These are gross calorific values from the supplied guide; supplier-specific values should take precedence where the source instructs."}

    def methodology_rules(self) -> dict[str, Any]:
        return {"ok": True, "schema": "sc-energy-methodology-rules/1.0", "count": len(self._methodology_rules), "items": self._methodology_rules}

    def convert_energy(self, *, value: str, from_unit: str, to_unit: str) -> dict[str, Any]:
        amount = self._positive_decimal(value, "value")
        if from_unit not in self._units or to_unit not in self._units:
            raise KeyError("unit")
        if self._units[from_unit].dimension != "energy" or self._units[to_unit].dimension != "energy":
            raise ValueError("energy conversion supports only the activated energy units")
        if from_unit == to_unit:
            result = amount
            path = []
        else:
            factor_by_from = {row.from_unit: Decimal(row.factor) for row in self._conversion_factors.values()}
            if from_unit != "kwh" and from_unit not in factor_by_from:
                raise KeyError(from_unit)
            if to_unit != "kwh" and to_unit not in factor_by_from:
                raise KeyError(to_unit)
            with localcontext() as ctx:
                ctx.prec = 28
                kwh = amount if from_unit == "kwh" else amount * factor_by_from[from_unit]
                result = kwh if to_unit == "kwh" else kwh / factor_by_from[to_unit]
            path = [from_unit, "kwh", to_unit] if from_unit != "kwh" and to_unit != "kwh" else [from_unit, to_unit]
        return {
            "ok": True,
            "schema": "sc-energy-conversion-result/1.0",
            "input": {"value": self._decimal_text(amount), "unit": from_unit},
            "output": {"value": self._decimal_text(result), "unit": to_unit},
            "path": path,
            "source_key": "carbon-trust-conversion-2020",
            "source_year": 2020,
            "status": "historical-source-bound-calculation",
            "guardrail": "This is an arithmetic conversion using the supplied 2020 guide's quoted factors; rounding and source-vintage limitations are preserved.",
        }

    def estimate_carbon(self, *, factor_key: str, quantity: str) -> dict[str, Any]:
        factor = self._carbon_factors.get(factor_key)
        if factor is None:
            raise KeyError(factor_key)
        amount = self._positive_decimal(quantity, "quantity")
        with localcontext() as ctx:
            ctx.prec = 28
            result = amount * Decimal(factor.kg_co2e_per_unit)
        return {
            "ok": True,
            "schema": "sc-energy-carbon-estimate/1.0",
            "input": {"quantity": self._decimal_text(amount), "unit": factor.unit, "factor_key": factor.key},
            "factor": asdict(factor),
            "output": {"kg_co2e": self._decimal_text(result)},
            "status": "historical-source-bound-calculation",
            "guardrail": "This result applies the selected 2020 direct-emissions factor only. It is not a current, indirect, lifecycle, Scope 3, or complete corporate-inventory result.",
        }

    def estimate_heat_content(self, *, factor_key: str, quantity: str) -> dict[str, Any]:
        factor = self._heat_content_factors.get(factor_key)
        if factor is None:
            raise KeyError(factor_key)
        amount = self._positive_decimal(quantity, "quantity")
        with localcontext() as ctx:
            ctx.prec = 28
            result = amount * Decimal(factor.kwh_per_unit)
        return {
            "ok": True,
            "schema": "sc-energy-heat-content-estimate/1.0",
            "input": {"quantity": self._decimal_text(amount), "unit": factor.unit, "factor_key": factor.key},
            "factor": asdict(factor),
            "output": {"kwh_gross": self._decimal_text(result)},
            "status": "historical-source-bound-calculation",
            "guardrail": "This result uses the selected default gross calorific value from the supplied guide and is not a supplier-specific or net-calorific-value result.",
        }

    def indicator_framework(self) -> dict[str, Any]:
        dimension_labels = {"social": "Social", "economic": "Economic", "environmental": "Environmental"}
        grouped: dict[str, dict[str, dict[str, list[str]]]] = {}
        for indicator in self._indicators.values():
            grouped.setdefault(indicator.dimension, {}).setdefault(indicator.theme, {}).setdefault(indicator.subtheme, []).append(indicator.code)
        dimensions = []
        for dimension in ("social", "economic", "environmental"):
            themes = []
            for theme, subthemes in grouped.get(dimension, {}).items():
                themes.append({
                    "key": theme,
                    "label": theme.replace("-", " ").title(),
                    "subthemes": [{"key": subtheme, "label": subtheme.replace("-", " ").title(), "indicator_codes": codes} for subtheme, codes in subthemes.items()],
                })
            dimensions.append({"key": dimension, "label": dimension_labels[dimension], "themes": themes})
        return {
            "ok": True,
            "schema": "sc-energy-indicator-framework/1.0",
            "source_key": "vera-langlois-2007",
            "source_year": 2007,
            "counts": {"indicators": len(self._indicators), "dimensions": 3, "themes": 7, "subthemes": 19},
            "dimensions": dimensions,
            "methodology_status": "methodology-sheets-not-supplied",
            "guardrail": "The supplied article provides the 30 indicator names and classification framework. Exact official construction methods require the separate EISD methodology sheets cited by the article; v0.3.0 does not invent those formulas.",
            "content_fingerprint": self._fingerprint,
        }

    def indicators(self, *, q: str = "", dimension: str = "", theme: str = "", subtheme: str = "", limit: int = 100) -> dict[str, Any]:
        qn = q.strip().lower()
        rows: list[dict[str, Any]] = []
        for indicator in self._indicators.values():
            if dimension and indicator.dimension != dimension:
                continue
            if theme and indicator.theme != theme:
                continue
            if subtheme and indicator.subtheme != subtheme:
                continue
            haystack = " ".join((indicator.code, indicator.label, indicator.dimension, indicator.theme, indicator.subtheme, indicator.statement, indicator.measure_type, *indicator.related_concepts)).lower()
            if qn and qn not in haystack:
                continue
            rows.append(asdict(indicator))
            if len(rows) >= max(1, min(limit, 100)):
                break
        return {
            "ok": True,
            "schema": "sc-energy-indicators/1.0",
            "count": len(rows),
            "items": rows,
            "guardrail": "Indicator definitions are source-grounded metadata, not observed values, official formula implementations, sustainability scores, or policy conclusions.",
        }

    def indicator(self, code: str) -> dict[str, Any]:
        key = code.strip().upper()
        indicator = self._indicators.get(key)
        if indicator is None:
            raise KeyError(key)
        return {
            "ok": True,
            "schema": "sc-energy-indicator/1.0",
            "indicator": asdict(indicator),
            "observation_contract": self._indicator_observation_contract(indicator),
            "content_fingerprint": self._fingerprint,
        }

    @staticmethod
    def _indicator_observation_contract(indicator: EnergyIndicatorDefinition) -> dict[str, Any]:
        return {
            "schema": "sc-energy-indicator-observation/1.0",
            "indicator_code": indicator.code,
            "required_fields": list(indicator.observation_fields),
            "provenance_required": True,
            "methodology_reference_required": True,
            "methodology_status": indicator.methodology_status,
            "calculation_status": indicator.calculation_status,
            "comparison_requirements": [
                "same or explicitly reconciled indicator definition",
                "compatible geography and period",
                "compatible unit and denominator basis",
                "documented source and methodology",
            ],
            "guardrail": "This contract structures an observation. It does not calculate the official EISD indicator and does not validate the scientific or statistical quality of submitted values.",
        }

    def indicator_observation_template(self, code: str) -> dict[str, Any]:
        key = code.strip().upper()
        indicator = self._indicators.get(key)
        if indicator is None:
            raise KeyError(key)
        contract = self._indicator_observation_contract(indicator)
        template = {field: None for field in indicator.observation_fields}
        template.update({"indicator_code": indicator.code, "indicator_label": indicator.label})
        return {
            "ok": True,
            "schema": "sc-energy-indicator-observation-template/1.0",
            "indicator": asdict(indicator),
            "contract": contract,
            "template": template,
        }


    def technology_framework(self) -> dict[str, Any]:
        return self._technology_registry.framework()

    def technologies(self, *, q: str = "", family: str = "", output: str = "", resource_class: str = "", limit: int = 100) -> dict[str, Any]:
        return self._technology_registry.technologies(q=q, family=family, output=output, resource_class=resource_class, limit=limit)

    def technology(self, key: str) -> dict[str, Any]:
        return self._technology_registry.technology(key)

    def resource_classes(self) -> dict[str, Any]:
        return self._technology_registry.resource_classes()

    def resource_class(self, key: str) -> dict[str, Any]:
        return self._technology_registry.resource_class(key)

    def technology_assessment_template(self, key: str) -> dict[str, Any]:
        return self._technology_registry.technology_assessment_template(key)

    def resource_observation_template(self, key: str) -> dict[str, Any]:
        return self._technology_registry.resource_observation_template(key)

    def technology_comparison_template(self) -> dict[str, Any]:
        return self._technology_registry.comparison_template()

    def balance_framework(self) -> dict[str, Any]:
        return self._balance_model.framework()

    def conversion_chain(self, *, input_kwh: str, efficiencies: str, labels: str = "") -> dict[str, Any]:
        return self._balance_model.conversion_chain(input_kwh=input_kwh, efficiencies=efficiencies, labels=labels)

    def supply_demand_balance(self, **kwargs: str) -> dict[str, Any]:
        return self._balance_model.supply_demand_balance(**kwargs)

    def generation_estimate(self, *, capacity_kw: str, capacity_factor_pct: str, hours: str = "8760") -> dict[str, Any]:
        return self._balance_model.generation_estimate(capacity_kw=capacity_kw, capacity_factor_pct=capacity_factor_pct, hours=hours)

    def balance_scenario_template(self) -> dict[str, Any]:
        return self._balance_model.scenario_template()

    def economics_framework(self) -> dict[str, Any]:
        return self._economics_model.framework()

    def energy_cost_comparison(self, **kwargs: str) -> dict[str, Any]:
        return self._economics_model.energy_cost_comparison(**kwargs)

    def simple_payback(self, **kwargs: str) -> dict[str, Any]:
        return self._economics_model.simple_payback(**kwargs)

    def npv(self, **kwargs: str) -> dict[str, Any]:
        return self._economics_model.npv(**kwargs)

    def cost_benefit(self, **kwargs: str) -> dict[str, Any]:
        return self._economics_model.cost_benefit(**kwargs)

    def cost_efficiency(self, **kwargs: str) -> dict[str, Any]:
        return self._economics_model.cost_efficiency(**kwargs)

    def levelized_energy_cost(self, **kwargs: str) -> dict[str, Any]:
        return self._economics_model.levelized_energy_cost(**kwargs)

    def economic_scenario_template(self) -> dict[str, Any]:
        return self._economics_model.scenario_template()

    def bioenergy_framework(self) -> dict[str, Any]:
        return self._bioenergy_model.framework()

    def bioenergy_feedstocks(self, *, q: str = "", limit: int = 100) -> dict[str, Any]:
        return self._bioenergy_model.feedstocks(q=q, limit=limit)

    def bioenergy_pathways(self, *, q: str = "", family: str = "", limit: int = 100) -> dict[str, Any]:
        return self._bioenergy_model.pathways(q=q, family=family, limit=limit)

    def bioenergy_pathway(self, key: str) -> dict[str, Any]:
        return self._bioenergy_model.pathway(key)

    def biological_carbon_bridges(self) -> dict[str, Any]:
        return self._bioenergy_model.carbon_nature_bridges()

    def feedstock_energy_estimate(self, **kwargs: str) -> dict[str, Any]:
        return self._bioenergy_model.feedstock_energy_estimate(**kwargs)

    def anaerobic_digestion_energy_estimate(self, **kwargs: str) -> dict[str, Any]:
        return self._bioenergy_model.anaerobic_digestion_energy_estimate(**kwargs)

    def biochar_carbon_estimate(self, **kwargs: str) -> dict[str, Any]:
        return self._bioenergy_model.biochar_carbon_estimate(**kwargs)

    def biomass_to_oil_energy_estimate(self, **kwargs: str) -> dict[str, Any]:
        return self._bioenergy_model.biomass_to_oil_energy_estimate(**kwargs)

    def bioenergy_scenario_template(self) -> dict[str, Any]:
        return self._bioenergy_model.scenario_template()

    def global_energy_framework(self) -> dict[str, Any]:
        return self._global_energy.framework()

    def global_energy_sources(self) -> dict[str, Any]:
        return self._global_energy.sources()

    def global_energy_metrics(self, *, q: str = "", category: str = "", limit: int = 100) -> dict[str, Any]:
        return self._global_energy.metrics(q=q, category=category, limit=limit)

    def global_energy_profile_template(self) -> dict[str, Any]:
        return self._global_energy.profile_template()

    def global_energy_country_profile(self, *, country: str, start_year: int | None = None, end_year: int | None = None, include_series: bool = True) -> dict[str, Any]:
        return self._global_energy.country_profile(country=country, start_year=start_year, end_year=end_year, include_series=include_series)

    def global_energy_compare(self, *, countries: str, metric_key: str, start_year: int | None = None, end_year: int | None = None) -> dict[str, Any]:
        return self._global_energy.compare(countries=countries, metric_key=metric_key, start_year=start_year, end_year=end_year)

    def decision_framework(self) -> dict[str, Any]:
        return self._decision_intelligence.framework()

    def decision_criteria(self, *, q: str = "", dimension: str = "", limit: int = 100) -> dict[str, Any]:
        return self._decision_intelligence.criteria(q=q, dimension=dimension, limit=limit)

    def decision_packet_template(self) -> dict[str, Any]:
        return self._decision_intelligence.packet_template()

    def decision_comparison_matrix(self, *, packet_json: str) -> dict[str, Any]:
        return self._decision_intelligence.comparison_matrix(packet_json=packet_json)

    def decision_readiness(self, *, packet_json: str) -> dict[str, Any]:
        return self._decision_intelligence.readiness(packet_json=packet_json)

    def platform_framework(self) -> dict[str, Any]:
        return self._integrated_platform.framework()

    def platform_contracts(self) -> dict[str, Any]:
        return self._integrated_platform.contracts()

    def platform_study_template(self) -> dict[str, Any]:
        return self._integrated_platform.study_template()

    def platform_certification(self) -> dict[str, Any]:
        # v1.0.0 certification remains available as the certified integration baseline.
        manifest = self.manifest()
        baseline = json.loads(json.dumps(manifest))
        baseline["subsystem"]["version"] = "1.0.0"
        baseline["subsystem"]["backend_version"] = "2.16.0"
        return self._integrated_platform.certification(baseline)

    def runtime_framework(self) -> dict[str, Any]:
        return self._runtime_activation.framework()

    def modeling_uncertainty_framework(self) -> dict[str, Any]:
        return self._modeling_uncertainty.framework()

    def uncertainty_study_template(self) -> dict[str, Any]:
        return self._modeling_uncertainty.study_template()

    def runtime_consumers(self) -> dict[str, Any]:
        return self._runtime_activation.consumers()

    def runtime_targets(self) -> dict[str, Any]:
        return self._runtime_activation.targets()

    def runtime_target(self, target_key: str) -> dict[str, Any]:
        return self._runtime_activation.target(target_key)

    def runtime_handoff_template(self, target_key: str) -> dict[str, Any]:
        return self._runtime_activation.handoff_template(target_key)

    def runtime_handoff(self, *, target_key: str, study_json: str) -> dict[str, Any]:
        try:
            study = json.loads(study_json)
        except json.JSONDecodeError as exc:
            raise ValueError("study must be valid JSON") from exc
        return self._runtime_activation.build_handoff(target_key, study)

    def runtime_readiness(self, *, target_key: str, study_json: str) -> dict[str, Any]:
        try:
            study = json.loads(study_json)
        except json.JSONDecodeError as exc:
            raise ValueError("study must be valid JSON") from exc
        return self._runtime_activation.readiness(target_key, study)

    def handoffs(self) -> dict[str, Any]:
        return {"ok": True, "schema": "sc-energy-handoffs/1.0", "count": len(self._handoffs), "items": self._handoffs, "guardrail": "Only handoffs marked available or contract-available resolve to a governed target or contract; planned handoffs do not imply current capability."}
