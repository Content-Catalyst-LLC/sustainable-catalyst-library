from __future__ import annotations

from dataclasses import asdict, dataclass
from hashlib import sha256
import json
from typing import Any, Iterable, Mapping

REGISTRY_SCHEMA = "sc-library-global-source-federation-registry/1.0"
INSTITUTION_SCHEMA = "sc-library-global-source-institution/1.0"
SOURCE_SCHEMA = "sc-library-global-source/1.0"
COLLECTION_SCHEMA = "sc-library-global-source-collection/1.0"
CONNECTOR_SCHEMA = "sc-library-global-source-connector-contract/1.0"
VALIDATION_SCHEMA = "sc-library-global-source-connector-validation/1.0"
READINESS_SCHEMA = "sc-library-global-source-federation-readiness/1.0"


def _canonical_json(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False)


def _stable_fingerprint(value: Any) -> str:
    return sha256(_canonical_json(value).encode("utf-8")).hexdigest()


def _normalize_terms(values: Iterable[str]) -> tuple[str, ...]:
    return tuple(sorted({str(value).strip().lower() for value in values if str(value).strip()}))


@dataclass(frozen=True)
class InstitutionDescriptor:
    institution_id: str
    name: str
    organization_type: str
    country_or_scope: str
    homepage: str

    def to_dict(self) -> dict[str, Any]:
        return {"schema": INSTITUTION_SCHEMA, **asdict(self)}


@dataclass(frozen=True)
class SourceDescriptor:
    source_id: str
    name: str
    institution_id: str
    source_family: str
    homepage: str
    access_mode: str
    coverage_scope: tuple[str, ...]
    content_types: tuple[str, ...]
    collection_ids: tuple[str, ...]
    connector_id: str
    compatibility_aliases: tuple[str, ...] = ()
    status: str = "active"

    def to_dict(self) -> dict[str, Any]:
        payload = asdict(self)
        for key in ("coverage_scope", "content_types", "collection_ids", "compatibility_aliases"):
            payload[key] = list(payload[key])
        return {"schema": SOURCE_SCHEMA, **payload}


@dataclass(frozen=True)
class ConnectorContract:
    connector_id: str
    source_id: str
    execution_authority: str
    transport: str
    capabilities: tuple[str, ...]
    authentication: str
    pagination: str
    rate_limit_policy: str
    provenance_fields: tuple[str, ...]
    language_policy: str = "preserve-as-received"
    translation_behavior: str = "none"
    raw_source_preservation: bool = True
    automatic_import: bool = False
    automatic_evidence_promotion: bool = False
    automatic_truth_promotion: bool = False
    source_membership_implies_endorsement: bool = False

    def to_dict(self) -> dict[str, Any]:
        payload = asdict(self)
        payload["capabilities"] = list(payload["capabilities"])
        payload["provenance_fields"] = list(payload["provenance_fields"])
        return {"schema": CONNECTOR_SCHEMA, **payload}


@dataclass(frozen=True)
class CollectionDescriptor:
    collection_id: str
    name: str
    description: str
    scope: str

    def to_dict(self) -> dict[str, Any]:
        return {"schema": COLLECTION_SCHEMA, **asdict(self)}


COLLECTIONS: tuple[CollectionDescriptor, ...] = (
    CollectionDescriptor("scholarly-metadata", "Scholarly Metadata", "Cross-disciplinary scholarly metadata, identifiers, authorship and citation discovery.", "global"),
    CollectionDescriptor("biomedical-clinical", "Biomedical & Clinical", "Biomedical literature, terminology, clinical trials and life-science evidence discovery.", "global"),
    CollectionDescriptor("institutional-repositories", "Institutional Repositories", "University and research-institution repositories and public research collections.", "global"),
    CollectionDescriptor("archives-libraries", "Archives & Libraries", "Library catalogs, archives, digitized collections and preservation-oriented discovery.", "global"),
    CollectionDescriptor("books-access", "Books & Access", "Books, editions, legal open-access locations and library discovery handoffs.", "global"),
    CollectionDescriptor("regulatory-public-data", "Regulatory & Public Data", "Government regulatory, approval, safety, enforcement and public-reference sources.", "global"),
    CollectionDescriptor("global-open-scholarship", "Global Open Scholarship", "Open scholarly indexes, repositories, journals, preprints, research data and public research outputs across regions and languages.", "global"),
    CollectionDescriptor("national-regional-repositories", "National & Regional Repositories", "National, regional and language-specific scholarly repositories and discovery systems.", "global"),
    CollectionDescriptor("multilingual-cultural-heritage", "Multilingual Cultural Heritage", "Digitized cultural heritage, national-library, archival and historical collections across languages and scripts.", "global"),
    CollectionDescriptor("public-statistics-development", "Public Statistics & Development Data", "Official and intergovernmental public statistics, indicators and development data.", "global"),
)


INSTITUTIONS: tuple[InstitutionDescriptor, ...] = (
    InstitutionDescriptor("crossref", "Crossref", "research-infrastructure", "global", "https://www.crossref.org/"),
    InstitutionDescriptor("openalex", "OpenAlex", "research-infrastructure", "global", "https://openalex.org/"),
    InstitutionDescriptor("datacite", "DataCite", "research-infrastructure", "global", "https://datacite.org/"),
    InstitutionDescriptor("nlm", "U.S. National Library of Medicine", "government-library", "United States", "https://www.nlm.nih.gov/"),
    InstitutionDescriptor("europe-pmc", "Europe PMC", "research-infrastructure", "global", "https://europepmc.org/"),
    InstitutionDescriptor("arxiv", "arXiv", "research-infrastructure", "global", "https://arxiv.org/"),
    InstitutionDescriptor("internet-archive", "Internet Archive", "nonprofit-library", "global", "https://archive.org/"),
    InstitutionDescriptor("mit", "MIT Libraries", "university-library", "United States", "https://libraries.mit.edu/"),
    InstitutionDescriptor("harvard", "Harvard Library", "university-library", "United States", "https://library.harvard.edu/"),
    InstitutionDescriptor("uc-berkeley", "UC Berkeley / eScholarship", "university-repository", "United States", "https://escholarship.org/"),
    InstitutionDescriptor("ucd", "University College Dublin", "university-repository", "Ireland", "https://researchrepository.ucd.ie/"),
    InstitutionDescriptor("loc", "Library of Congress", "national-library", "United States", "https://www.loc.gov/"),
    InstitutionDescriptor("google", "Google", "commercial-information-service", "global", "https://books.google.com/"),
    InstitutionDescriptor("ourresearch", "OurResearch", "nonprofit-research-infrastructure", "global", "https://ourresearch.org/"),
    InstitutionDescriptor("oclc", "OCLC", "library-cooperative", "global", "https://www.oclc.org/"),
    InstitutionDescriptor("jhu", "Johns Hopkins University", "university-repository", "United States", "https://archive.data.jhu.edu/"),
    InstitutionDescriptor("fda", "U.S. Food and Drug Administration", "government-regulator", "United States", "https://www.fda.gov/"),
    InstitutionDescriptor("doaj", "Directory of Open Access Journals", "nonprofit-research-infrastructure", "global", "https://doaj.org/"),
    InstitutionDescriptor("zenodo", "Zenodo", "research-repository", "global", "https://zenodo.org/"),
    InstitutionDescriptor("openaire", "OpenAIRE", "research-infrastructure", "Europe/global", "https://www.openaire.eu/"),
    InstitutionDescriptor("europeana", "Europeana", "cultural-heritage-infrastructure", "Europe", "https://www.europeana.eu/"),
    InstitutionDescriptor("core", "CORE", "research-infrastructure", "global", "https://core.ac.uk/"),
    InstitutionDescriptor("hal", "HAL Open Science", "national-research-repository", "France/global", "https://hal.science/"),
    InstitutionDescriptor("scielo", "SciELO", "scholarly-publishing-infrastructure", "Latin America/global", "https://scielo.org/"),
    InstitutionDescriptor("lareferencia", "LA Referencia", "regional-repository-network", "Latin America", "https://www.lareferencia.info/"),
    InstitutionDescriptor("nii", "National Institute of Informatics", "research-infrastructure", "Japan", "https://www.nii.ac.jp/en/"),
    InstitutionDescriptor("jst", "Japan Science and Technology Agency", "research-infrastructure", "Japan", "https://www.jst.go.jp/EN/"),
    InstitutionDescriptor("ndl", "National Diet Library", "national-library", "Japan", "https://www.ndl.go.jp/en/"),
    InstitutionDescriptor("cnki", "China National Knowledge Infrastructure", "scholarly-information-service", "China", "https://www.cnki.net/"),
    InstitutionDescriptor("cyberleninka", "CyberLeninka", "open-scholarly-library", "Russia", "https://cyberleninka.ru/"),
    InstitutionDescriptor("elibrary-ru", "eLIBRARY.RU", "scholarly-information-service", "Russia", "https://elibrary.ru/"),
    InstitutionDescriptor("sid-iran", "Scientific Information Database", "scholarly-information-service", "Iran", "https://sid.ir/"),
    InstitutionDescriptor("irandoc", "IranDoc", "research-information-institute", "Iran", "https://irandoc.ac.ir/"),
    InstitutionDescriptor("dergipark", "DergiPark", "scholarly-publishing-infrastructure", "Türkiye", "https://dergipark.org.tr/"),
    InstitutionDescriptor("dri", "Digital Repository of Ireland", "national-digital-repository", "Ireland", "https://www.dri.ie/"),
    InstitutionDescriptor("nli", "National Library of Ireland", "national-library", "Ireland", "https://www.nli.ie/"),
    InstitutionDescriptor("british-library", "British Library", "national-library", "United Kingdom", "https://www.bl.uk/"),
    InstitutionDescriptor("qdl", "Qatar Digital Library", "digital-cultural-heritage-library", "Qatar/United Kingdom", "https://www.qdl.qa/en"),
    InstitutionDescriptor("nyu", "New York University Libraries", "university-library", "United States/global", "https://library.nyu.edu/"),
    InstitutionDescriptor("ajol", "African Journals Online", "scholarly-publishing-infrastructure", "Africa", "https://www.ajol.info/"),
    InstitutionDescriptor("inflibnet", "INFLIBNET Centre", "research-infrastructure", "India", "https://www.inflibnet.ac.in/"),
    InstitutionDescriptor("world-bank", "World Bank", "intergovernmental-organization", "global", "https://www.worldbank.org/"),
    InstitutionDescriptor("european-commission", "European Commission", "intergovernmental-public-administration", "Europe", "https://commission.europa.eu/"),
    InstitutionDescriptor("oecd", "OECD", "intergovernmental-organization", "global", "https://www.oecd.org/"),
    InstitutionDescriptor("un", "United Nations", "intergovernmental-organization", "global", "https://www.un.org/"),
)


def _source(
    source_id: str,
    name: str,
    institution_id: str,
    family: str,
    homepage: str,
    access_mode: str,
    collections: tuple[str, ...],
    content_types: tuple[str, ...],
    connector_id: str,
    aliases: tuple[str, ...] = (),
    coverage: tuple[str, ...] = ("global",),
) -> SourceDescriptor:
    return SourceDescriptor(
        source_id=source_id,
        name=name,
        institution_id=institution_id,
        source_family=family,
        homepage=homepage,
        access_mode=access_mode,
        coverage_scope=coverage,
        content_types=content_types,
        collection_ids=collections,
        connector_id=connector_id,
        compatibility_aliases=aliases,
    )


SOURCES: tuple[SourceDescriptor, ...] = (
    _source("crossref", "Crossref", "crossref", "doi-metadata", "https://api.crossref.org/", "public-api", ("scholarly-metadata",), ("article", "book", "dataset", "report", "proceedings"), "wordpress-crossref", ("wordpress:crossref",)),
    _source("openalex", "OpenAlex", "openalex", "scholarly-graph", "https://api.openalex.org/", "public-api", ("scholarly-metadata",), ("work", "author", "institution", "topic", "source"), "wordpress-openalex", ("wordpress:openalex",)),
    _source("datacite", "DataCite", "datacite", "doi-metadata", "https://api.datacite.org/", "public-api", ("scholarly-metadata",), ("dataset", "software", "report", "book", "research-output"), "wordpress-datacite", ("wordpress:datacite",)),
    _source("pubmed", "PubMed", "nlm", "ncbi-entrez", "https://pubmed.ncbi.nlm.nih.gov/", "public-api", ("biomedical-clinical", "scholarly-metadata"), ("citation", "article"), "python-pubmed", ("wordpress:pubmed", "backend:pubmed")),
    _source("pmc", "PubMed Central", "nlm", "ncbi-entrez", "https://pmc.ncbi.nlm.nih.gov/", "public-api", ("biomedical-clinical",), ("article", "full-text"), "python-pmc", ("wordpress:pmc", "backend:pmc")),
    _source("clinicaltrials", "ClinicalTrials.gov", "nlm", "clinical-trials", "https://clinicaltrials.gov/", "public-api", ("biomedical-clinical",), ("clinical-trial", "study"), "python-clinicaltrials", ("backend:clinicaltrials",)),
    _source("mesh", "Medical Subject Headings (MeSH)", "nlm", "biomedical-terminology", "https://www.nlm.nih.gov/mesh/", "public-api", ("biomedical-clinical",), ("controlled-vocabulary", "concept"), "python-mesh", ("backend:mesh",)),
    _source("rxnorm", "RxNorm", "nlm", "biomedical-terminology", "https://www.nlm.nih.gov/research/umls/rxnorm/", "public-api", ("biomedical-clinical",), ("drug-terminology", "concept"), "python-rxnorm", ("backend:rxnorm",)),
    _source("europepmc", "Europe PMC", "europe-pmc", "life-science-literature", "https://europepmc.org/", "public-api", ("biomedical-clinical", "scholarly-metadata"), ("article", "citation", "grant", "full-text-signal"), "wordpress-europepmc", ("wordpress:europepmc",)),
    _source("arxiv", "arXiv", "arxiv", "preprint-repository", "https://arxiv.org/", "public-api", ("scholarly-metadata",), ("preprint", "article"), "wordpress-arxiv", ("wordpress:arxiv",)),
    _source("internetarchive", "Internet Archive", "internet-archive", "digital-archive", "https://archive.org/", "public-api", ("archives-libraries", "books-access"), ("book", "text", "audio", "video", "software", "image", "web-archive"), "wordpress-internetarchive", ("wordpress:internetarchive",)),
    _source("mit", "MIT Libraries", "mit", "institutional-discovery", "https://libraries.mit.edu/", "public-api", ("institutional-repositories", "archives-libraries"), ("catalog-record", "repository-item", "archive-record"), "wordpress-mit", ("wordpress:mit",), ("United States", "global-discovery")),
    _source("harvard", "Harvard Library", "harvard", "institutional-discovery", "https://library.harvard.edu/", "public-api", ("institutional-repositories", "archives-libraries"), ("catalog-record", "digitized-resource"), "wordpress-harvard", ("wordpress:harvard",), ("United States", "global-discovery")),
    _source("berkeley", "UC Berkeley / eScholarship", "uc-berkeley", "institutional-repository", "https://escholarship.org/", "public-repository", ("institutional-repositories",), ("repository-item", "article", "thesis", "report"), "wordpress-berkeley", ("wordpress:berkeley",), ("United States", "global-discovery")),
    _source("ucd", "Research Repository UCD", "ucd", "institutional-repository", "https://researchrepository.ucd.ie/", "public-api", ("institutional-repositories",), ("repository-item", "article", "thesis", "report"), "wordpress-ucd", ("wordpress:ucd",), ("Ireland", "global-discovery")),
    _source("johns-hopkins-dataverse", "Johns Hopkins Research Data Repository", "jhu", "dataverse", "https://archive.data.jhu.edu/", "public-api", ("institutional-repositories",), ("dataset", "file", "dataverse"), "python-jhu-dataverse", ("backend:johns-hopkins-dataverse",), ("United States", "global-discovery")),
    _source("loc", "Library of Congress", "loc", "national-library", "https://www.loc.gov/", "public-api", ("archives-libraries",), ("book", "map", "manuscript", "photograph", "audio", "video", "web-archive"), "wordpress-loc", ("wordpress:loc",), ("United States", "global-discovery")),
    _source("openlibrary", "Open Library", "internet-archive", "book-catalog", "https://openlibrary.org/", "public-api", ("books-access", "archives-libraries"), ("book", "edition", "author"), "wordpress-openlibrary", ("wordpress:openlibrary",)),
    _source("googlebooks", "Google Books", "google", "book-discovery", "https://books.google.com/", "api-key", ("books-access",), ("book", "edition", "preview"), "wordpress-googlebooks", ("wordpress:googlebooks",)),
    _source("unpaywall", "Unpaywall", "ourresearch", "open-access-location", "https://unpaywall.org/", "identified-public-api", ("books-access", "scholarly-metadata"), ("doi", "open-access-location"), "wordpress-unpaywall", ("wordpress:unpaywall",)),
    _source("google-scholar", "Google Scholar", "google", "scholarly-search-handoff", "https://scholar.google.com/", "browser-handoff", ("scholarly-metadata",), ("search-handoff",), "browser-google-scholar", ("wordpress:google_scholar",)),
    _source("worldcat", "WorldCat", "oclc", "library-discovery-handoff", "https://www.worldcat.org/", "browser-handoff", ("archives-libraries", "books-access"), ("library-holding", "catalog-record", "search-handoff"), "browser-worldcat", ("wordpress:worldcat",)),
    _source("drugsfda", "Drugs@FDA", "fda", "regulatory-approval", "https://open.fda.gov/apis/drug/drugsfda/", "public-api", ("regulatory-public-data", "biomedical-clinical"), ("application", "product", "submission", "approval"), "python-drugsfda", ("backend:drugsfda",), ("United States",)),
    _source("fda-labels", "FDA Drug Labeling", "fda", "regulatory-label", "https://open.fda.gov/apis/drug/label/", "public-api", ("regulatory-public-data", "biomedical-clinical"), ("drug-label", "prescribing-information"), "python-fda-labels", ("backend:fda-labels",), ("United States",)),
    _source("fda-adverse-events", "FDA Adverse Event Reporting System", "fda", "safety-report", "https://open.fda.gov/apis/drug/event/", "public-api", ("regulatory-public-data", "biomedical-clinical"), ("adverse-event-report",), "python-fda-adverse-events", ("backend:fda-adverse-events",), ("United States",)),
    _source("fda-recalls", "FDA Drug Recall Enforcement Reports", "fda", "regulatory-enforcement", "https://open.fda.gov/apis/drug/enforcement/", "public-api", ("regulatory-public-data",), ("recall", "enforcement-report"), "python-fda-recalls", ("backend:fda-recalls",), ("United States",)),
    _source("doaj", "Directory of Open Access Journals", "doaj", "open-access-journals", "https://doaj.org/", "public-discovery", ("global-open-scholarship", "scholarly-metadata"), ("journal", "article", "metadata"), "registry-doaj"),
    _source("zenodo", "Zenodo", "zenodo", "research-repository", "https://zenodo.org/", "public-discovery", ("global-open-scholarship", "institutional-repositories"), ("dataset", "software", "publication", "research-output"), "registry-zenodo"),
    _source("openaire", "OpenAIRE", "openaire", "research-graph", "https://www.openaire.eu/", "public-discovery", ("global-open-scholarship", "institutional-repositories"), ("publication", "dataset", "software", "project", "organization"), "registry-openaire"),
    _source("europeana", "Europeana", "europeana", "cultural-heritage", "https://www.europeana.eu/", "public-discovery", ("multilingual-cultural-heritage", "archives-libraries"), ("image", "text", "object", "audio", "video", "archive-record"), "registry-europeana", (), ("Europe", "global-discovery")),
    _source("core", "CORE", "core", "open-access-aggregation", "https://core.ac.uk/", "public-discovery", ("global-open-scholarship", "scholarly-metadata"), ("article", "repository-item", "metadata", "full-text-location"), "registry-core"),
    _source("hal", "HAL Open Science", "hal", "national-research-repository", "https://hal.science/", "public-repository", ("global-open-scholarship", "national-regional-repositories", "institutional-repositories"), ("article", "preprint", "thesis", "report", "research-output"), "registry-hal", (), ("France", "Europe", "global-discovery")),
    _source("scielo", "SciELO", "scielo", "regional-open-scholarship", "https://scielo.org/", "public-discovery", ("global-open-scholarship", "national-regional-repositories"), ("journal", "article", "citation", "full-text-location"), "registry-scielo", (), ("Latin America", "global-discovery")),
    _source("la-referencia", "LA Referencia", "lareferencia", "regional-repository-network", "https://www.lareferencia.info/", "public-discovery", ("national-regional-repositories", "institutional-repositories"), ("article", "thesis", "dataset", "repository-item"), "registry-lareferencia", (), ("Latin America",)),
    _source("cinii", "CiNii Research", "nii", "national-scholarly-discovery", "https://cir.nii.ac.jp/", "public-discovery", ("national-regional-repositories", "scholarly-metadata"), ("article", "book", "research-project", "researcher", "institution"), "registry-cinii", (), ("Japan", "global-discovery")),
    _source("jstage", "J-STAGE", "jst", "national-scholarly-publishing", "https://www.jstage.jst.go.jp/", "public-discovery", ("national-regional-repositories", "global-open-scholarship"), ("journal", "article", "conference-paper"), "registry-jstage", (), ("Japan", "global-discovery")),
    _source("ndl-search", "National Diet Library Search", "ndl", "national-library", "https://ndlsearch.ndl.go.jp/en/", "browser-handoff", ("national-regional-repositories", "multilingual-cultural-heritage", "archives-libraries"), ("book", "periodical", "digital-resource", "catalog-record"), "browser-ndl-search", (), ("Japan",)),
    _source("cnki", "China National Knowledge Infrastructure", "cnki", "national-scholarly-discovery", "https://www.cnki.net/", "browser-handoff", ("national-regional-repositories", "scholarly-metadata"), ("article", "thesis", "conference-paper", "reference-work"), "browser-cnki", (), ("China",)),
    _source("cyberleninka", "CyberLeninka", "cyberleninka", "open-scholarly-library", "https://cyberleninka.ru/", "public-discovery", ("global-open-scholarship", "national-regional-repositories"), ("article", "journal", "full-text-location"), "registry-cyberleninka", (), ("Russia", "global-discovery")),
    _source("elibrary-ru", "eLIBRARY.RU", "elibrary-ru", "national-scholarly-discovery", "https://elibrary.ru/", "browser-handoff", ("national-regional-repositories", "scholarly-metadata"), ("article", "journal", "citation", "author"), "browser-elibrary-ru", (), ("Russia",)),
    _source("sid-iran", "Scientific Information Database", "sid-iran", "national-scholarly-discovery", "https://sid.ir/", "browser-handoff", ("national-regional-repositories", "scholarly-metadata"), ("article", "journal", "conference-paper"), "browser-sid-iran", (), ("Iran",)),
    _source("irandoc", "IranDoc", "irandoc", "national-research-information", "https://irandoc.ac.ir/", "browser-handoff", ("national-regional-repositories", "institutional-repositories"), ("thesis", "dissertation", "research-record", "bibliographic-record"), "browser-irandoc", (), ("Iran",)),
    _source("dergipark", "DergiPark", "dergipark", "national-open-scholarship", "https://dergipark.org.tr/", "public-discovery", ("global-open-scholarship", "national-regional-repositories"), ("journal", "article", "metadata"), "registry-dergipark", (), ("Türkiye", "global-discovery")),
    _source("dri", "Digital Repository of Ireland", "dri", "national-digital-repository", "https://www.dri.ie/", "public-repository", ("national-regional-repositories", "multilingual-cultural-heritage", "archives-libraries"), ("dataset", "archive-record", "image", "text", "collection"), "registry-dri", (), ("Ireland", "global-discovery")),
    _source("nli", "National Library of Ireland", "nli", "national-library", "https://www.nli.ie/", "browser-handoff", ("national-regional-repositories", "multilingual-cultural-heritage", "archives-libraries"), ("catalog-record", "manuscript", "image", "newspaper", "digital-resource"), "browser-nli", (), ("Ireland",)),
    _source("british-library", "British Library", "british-library", "national-library", "https://www.bl.uk/", "browser-handoff", ("national-regional-repositories", "multilingual-cultural-heritage", "archives-libraries"), ("catalog-record", "manuscript", "book", "map", "sound", "archive-record"), "browser-british-library", (), ("United Kingdom", "global-discovery")),
    _source("qatar-digital-library", "Qatar Digital Library", "qdl", "digital-cultural-heritage", "https://www.qdl.qa/en", "browser-handoff", ("multilingual-cultural-heritage", "archives-libraries"), ("manuscript", "map", "photograph", "archive-record", "historical-document"), "browser-qatar-digital-library", (), ("Middle East", "Gulf")),
    _source("arabic-collections-online", "Arabic Collections Online", "nyu", "digital-text-collection", "https://dlib.nyu.edu/aco/", "browser-handoff", ("multilingual-cultural-heritage", "books-access"), ("book", "digitized-text", "historical-text"), "browser-arabic-collections-online", (), ("Middle East", "North Africa", "global-discovery")),
    _source("ajol", "African Journals Online", "ajol", "regional-scholarly-publishing", "https://www.ajol.info/", "public-discovery", ("global-open-scholarship", "national-regional-repositories"), ("journal", "article", "metadata"), "registry-ajol", (), ("Africa", "global-discovery")),
    _source("scielo-south-africa", "SciELO South Africa", "scielo", "regional-open-scholarship", "https://www.scielo.org.za/", "public-discovery", ("global-open-scholarship", "national-regional-repositories"), ("journal", "article", "metadata", "full-text-location"), "registry-scielo-south-africa", (), ("South Africa", "Africa", "global-discovery")),
    _source("shodhganga", "Shodhganga", "inflibnet", "national-thesis-repository", "https://shodhganga.inflibnet.ac.in/", "public-repository", ("national-regional-repositories", "institutional-repositories"), ("thesis", "dissertation", "repository-item"), "registry-shodhganga", (), ("India",)),
    _source("world-bank-data", "World Bank Open Data", "world-bank", "development-statistics", "https://data.worldbank.org/", "public-data", ("public-statistics-development",), ("indicator", "country-series", "development-data"), "registry-world-bank-data"),
    _source("eurostat", "Eurostat", "european-commission", "official-statistics", "https://ec.europa.eu/eurostat", "public-data", ("public-statistics-development",), ("dataset", "indicator", "time-series", "geography"), "registry-eurostat", (), ("Europe",)),
    _source("oecd-data", "OECD Data", "oecd", "intergovernmental-statistics", "https://data.oecd.org/", "public-data", ("public-statistics-development",), ("indicator", "dataset", "time-series", "country-series"), "registry-oecd-data"),
    _source("un-data", "UN Data", "un", "intergovernmental-statistics", "https://data.un.org/", "public-data", ("public-statistics-development",), ("indicator", "dataset", "time-series", "country-series"), "registry-un-data"),
)


_DEFAULT_PROVENANCE = (
    "source_id",
    "source_record_id",
    "retrieved_at",
    "retrieved_from",
    "connector_id",
    "connector_contract_version",
)


def _connector(
    connector_id: str,
    source_id: str,
    authority: str,
    transport: str,
    capabilities: tuple[str, ...],
    authentication: str = "none",
    pagination: str = "source-native",
    rate_limit_policy: str = "source-policy-bounded",
) -> ConnectorContract:
    return ConnectorContract(
        connector_id=connector_id,
        source_id=source_id,
        execution_authority=authority,
        transport=transport,
        capabilities=capabilities,
        authentication=authentication,
        pagination=pagination,
        rate_limit_policy=rate_limit_policy,
        provenance_fields=_DEFAULT_PROVENANCE,
    )


CONNECTORS: tuple[ConnectorContract, ...] = (
    _connector("wordpress-crossref", "crossref", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "identifier-lookup"), "optional-contact"),
    _connector("wordpress-openalex", "openalex", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "identifier-lookup", "access-locations"), "optional-api-key"),
    _connector("wordpress-datacite", "datacite", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "identifier-lookup")),
    _connector("python-pubmed", "pubmed", "python-backend-biomedical", "ncbi-entrez", ("search", "metadata", "citation"), "optional-ncbi-api-key"),
    _connector("python-pmc", "pmc", "python-backend-biomedical", "ncbi-entrez", ("search", "metadata", "full-text-location"), "optional-ncbi-api-key"),
    _connector("python-clinicaltrials", "clinicaltrials", "python-backend-biomedical", "rest-json", ("search", "study-record", "provenance")),
    _connector("python-mesh", "mesh", "python-backend-biomedical", "rest-json", ("search", "terminology", "concept-resolution")),
    _connector("python-rxnorm", "rxnorm", "python-backend-biomedical", "rest-json", ("search", "terminology", "concept-resolution")),
    _connector("wordpress-europepmc", "europepmc", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "access-location")),
    _connector("wordpress-arxiv", "arxiv", "wordpress-legacy-scholarly-connectors", "atom-xml", ("search", "metadata", "full-text-location")),
    _connector("wordpress-internetarchive", "internetarchive", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "item-location")),
    _connector("wordpress-mit", "mit", "wordpress-legacy-scholarly-connectors", "graphql", ("search", "metadata", "item-location")),
    _connector("wordpress-harvard", "harvard", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "item-location")),
    _connector("wordpress-berkeley", "berkeley", "wordpress-legacy-scholarly-connectors", "browser-or-repository-handoff", ("locate", "metadata-handoff")),
    _connector("wordpress-ucd", "ucd", "wordpress-legacy-scholarly-connectors", "dspace-rest", ("search", "metadata", "item-location")),
    _connector("python-jhu-dataverse", "johns-hopkins-dataverse", "python-backend-institutional", "dataverse-rest", ("search", "metadata", "record", "files", "citation", "license")),
    _connector("wordpress-loc", "loc", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "item-location")),
    _connector("wordpress-openlibrary", "openlibrary", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "edition-location"), "identified-request"),
    _connector("wordpress-googlebooks", "googlebooks", "wordpress-legacy-scholarly-connectors", "rest-json", ("search", "metadata", "preview-location"), "api-key"),
    _connector("wordpress-unpaywall", "unpaywall", "wordpress-legacy-scholarly-connectors", "rest-json", ("identifier-lookup", "open-access-location"), "identified-request"),
    _connector("browser-google-scholar", "google-scholar", "browser-handoff", "browser-handoff", ("locate", "citation-discovery")),
    _connector("browser-worldcat", "worldcat", "browser-handoff", "browser-handoff", ("locate", "library-discovery")),
    _connector("python-drugsfda", "drugsfda", "python-backend-fda", "openfda-rest", ("search", "metadata", "approvals", "products", "submissions"), "optional-openfda-api-key"),
    _connector("python-fda-labels", "fda-labels", "python-backend-fda", "openfda-rest", ("search", "metadata", "prescribing-information"), "optional-openfda-api-key"),
    _connector("python-fda-adverse-events", "fda-adverse-events", "python-backend-fda", "openfda-rest", ("search", "metadata", "adverse-event-reports"), "optional-openfda-api-key"),
    _connector("python-fda-recalls", "fda-recalls", "python-backend-fda", "openfda-rest", ("search", "metadata", "recalls", "enforcement"), "optional-openfda-api-key"),
    _connector("registry-doaj", "doaj", "registry-only", "registry-reference", ("locate", "metadata-handoff")),
    _connector("registry-zenodo", "zenodo", "registry-only", "registry-reference", ("locate", "metadata-handoff", "research-output-discovery")),
    _connector("registry-openaire", "openaire", "registry-only", "registry-reference", ("locate", "metadata-handoff", "research-graph-discovery")),
    _connector("registry-europeana", "europeana", "registry-only", "registry-reference", ("locate", "metadata-handoff", "cultural-heritage-discovery")),
    _connector("registry-core", "core", "registry-only", "registry-reference", ("locate", "metadata-handoff", "open-access-discovery")),
    _connector("registry-hal", "hal", "registry-only", "registry-reference", ("locate", "metadata-handoff", "repository-discovery")),
    _connector("registry-scielo", "scielo", "registry-only", "registry-reference", ("locate", "metadata-handoff", "journal-discovery")),
    _connector("registry-lareferencia", "la-referencia", "registry-only", "registry-reference", ("locate", "metadata-handoff", "repository-discovery")),
    _connector("registry-cinii", "cinii", "registry-only", "registry-reference", ("locate", "metadata-handoff", "scholarly-discovery")),
    _connector("registry-jstage", "jstage", "registry-only", "registry-reference", ("locate", "metadata-handoff", "journal-discovery")),
    _connector("browser-ndl-search", "ndl-search", "browser-handoff", "browser-handoff", ("locate", "library-discovery")),
    _connector("browser-cnki", "cnki", "browser-handoff", "browser-handoff", ("locate", "scholarly-discovery")),
    _connector("registry-cyberleninka", "cyberleninka", "registry-only", "registry-reference", ("locate", "metadata-handoff", "open-access-discovery")),
    _connector("browser-elibrary-ru", "elibrary-ru", "browser-handoff", "browser-handoff", ("locate", "scholarly-discovery")),
    _connector("browser-sid-iran", "sid-iran", "browser-handoff", "browser-handoff", ("locate", "scholarly-discovery")),
    _connector("browser-irandoc", "irandoc", "browser-handoff", "browser-handoff", ("locate", "thesis-discovery", "research-discovery")),
    _connector("registry-dergipark", "dergipark", "registry-only", "registry-reference", ("locate", "metadata-handoff", "journal-discovery")),
    _connector("registry-dri", "dri", "registry-only", "registry-reference", ("locate", "metadata-handoff", "repository-discovery")),
    _connector("browser-nli", "nli", "browser-handoff", "browser-handoff", ("locate", "library-discovery", "archive-discovery")),
    _connector("browser-british-library", "british-library", "browser-handoff", "browser-handoff", ("locate", "library-discovery", "archive-discovery")),
    _connector("browser-qatar-digital-library", "qatar-digital-library", "browser-handoff", "browser-handoff", ("locate", "archive-discovery", "cultural-heritage-discovery")),
    _connector("browser-arabic-collections-online", "arabic-collections-online", "browser-handoff", "browser-handoff", ("locate", "book-discovery", "cultural-heritage-discovery")),
    _connector("registry-ajol", "ajol", "registry-only", "registry-reference", ("locate", "metadata-handoff", "journal-discovery")),
    _connector("registry-scielo-south-africa", "scielo-south-africa", "registry-only", "registry-reference", ("locate", "metadata-handoff", "journal-discovery")),
    _connector("registry-shodhganga", "shodhganga", "registry-only", "registry-reference", ("locate", "metadata-handoff", "thesis-discovery")),
    _connector("registry-world-bank-data", "world-bank-data", "registry-only", "registry-reference", ("locate", "dataset-discovery", "indicator-discovery")),
    _connector("registry-eurostat", "eurostat", "registry-only", "registry-reference", ("locate", "dataset-discovery", "indicator-discovery")),
    _connector("registry-oecd-data", "oecd-data", "registry-only", "registry-reference", ("locate", "dataset-discovery", "indicator-discovery")),
    _connector("registry-un-data", "un-data", "registry-only", "registry-reference", ("locate", "dataset-discovery", "indicator-discovery")),
)


class GlobalSourceFederationRegistry:
    def __init__(self) -> None:
        self._institutions = {item.institution_id: item for item in INSTITUTIONS}
        self._sources = {item.source_id: item for item in SOURCES}
        self._connectors = {item.connector_id: item for item in CONNECTORS}
        self._collections = {item.collection_id: item for item in COLLECTIONS}
        self._assert_integrity()

    def _assert_integrity(self) -> None:
        if len(self._sources) != len(SOURCES) or len(self._connectors) != len(CONNECTORS):
            raise RuntimeError("global source federation identifiers must be unique")
        for source in SOURCES:
            if source.institution_id not in self._institutions:
                raise RuntimeError(f"unknown institution: {source.institution_id}")
            if source.connector_id not in self._connectors:
                raise RuntimeError(f"unknown connector: {source.connector_id}")
            if self._connectors[source.connector_id].source_id != source.source_id:
                raise RuntimeError(f"connector/source mismatch: {source.source_id}")
            unknown = set(source.collection_ids) - set(self._collections)
            if unknown:
                raise RuntimeError(f"unknown collections for {source.source_id}: {sorted(unknown)}")

    def _registry_core(self) -> dict[str, Any]:
        return {
            "schema": REGISTRY_SCHEMA,
            "version": "6.5.0",
            "institutions": [item.to_dict() for item in INSTITUTIONS],
            "sources": [item.to_dict() for item in SOURCES],
            "collections": [item.to_dict() for item in COLLECTIONS],
            "connectors": [item.to_dict() for item in CONNECTORS],
            "governance": self.governance(),
        }

    def fingerprint(self) -> str:
        return _stable_fingerprint(self._registry_core())

    @staticmethod
    def governance() -> dict[str, Any]:
        return {
            "legacy_v4_8_federation_transport_reused": True,
            "legacy_v2_6_scholarly_connectors_reused": True,
            "parallel_connector_execution_stack_created": False,
            "registry_membership_implies_endorsement": False,
            "registry_membership_implies_partnership": False,
            "connector_health_implies_source_quality": False,
            "connector_health_implies_evidence_truth": False,
            "source_quality_score_assigned": False,
            "user_trust_score_assigned": False,
            "automatic_import": False,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
            "automatic_platform_core_promotion": False,
            "original_language_preserved_as_received": True,
            "automatic_translation": False,
        }

    def readiness(self) -> dict[str, Any]:
        authorities: dict[str, int] = {}
        for connector in CONNECTORS:
            authorities[connector.execution_authority] = authorities.get(connector.execution_authority, 0) + 1
        return {
            "schema": READINESS_SCHEMA,
            "state": "ready",
            "version": "6.5.0",
            "registry_fingerprint_sha256": self.fingerprint(),
            "institutions": len(INSTITUTIONS),
            "sources": len(SOURCES),
            "collections": len(COLLECTIONS),
            "connector_contracts": len(CONNECTORS),
            "execution_authorities": dict(sorted(authorities.items())),
            "governance": self.governance(),
        }

    def snapshot(
        self,
        *,
        family: str = "",
        capability: str = "",
        collection: str = "",
        authority: str = "",
        q: str = "",
    ) -> dict[str, Any]:
        family = family.strip().lower()
        capability = capability.strip().lower()
        collection = collection.strip().lower()
        authority = authority.strip().lower()
        q = q.strip().lower()
        selected: list[SourceDescriptor] = []
        for source in SOURCES:
            connector = self._connectors[source.connector_id]
            haystack = " ".join((source.source_id, source.name, source.source_family, source.institution_id, *source.content_types)).lower()
            if family and source.source_family.lower() != family:
                continue
            if capability and capability not in _normalize_terms(connector.capabilities):
                continue
            if collection and collection not in _normalize_terms(source.collection_ids):
                continue
            if authority and connector.execution_authority.lower() != authority:
                continue
            if q and q not in haystack:
                continue
            selected.append(source)
        source_ids = {item.source_id for item in selected}
        institution_ids = {item.institution_id for item in selected}
        connector_ids = {item.connector_id for item in selected}
        collection_ids = {collection_id for item in selected for collection_id in item.collection_ids}
        payload = {
            "schema": REGISTRY_SCHEMA,
            "version": "6.5.0",
            "registry_fingerprint_sha256": self.fingerprint(),
            "filters": {"family": family or None, "capability": capability or None, "collection": collection or None, "authority": authority or None, "q": q or None},
            "counts": {"sources": len(selected), "institutions": len(institution_ids), "collections": len(collection_ids), "connectors": len(connector_ids)},
            "institutions": [self._institutions[key].to_dict() for key in sorted(institution_ids)],
            "sources": [self._sources[key].to_dict() for key in sorted(source_ids)],
            "collections": [self._collections[key].to_dict() for key in sorted(collection_ids)],
            "connectors": [self._connectors[key].to_dict() for key in sorted(connector_ids)],
            "governance": self.governance(),
        }
        return payload

    def source(self, source_id: str) -> dict[str, Any]:
        source_id = source_id.strip().lower()
        if source_id not in self._sources:
            raise KeyError(source_id)
        source = self._sources[source_id]
        return {
            "schema": SOURCE_SCHEMA,
            "source": source.to_dict(),
            "institution": self._institutions[source.institution_id].to_dict(),
            "collections": [self._collections[key].to_dict() for key in source.collection_ids],
            "connector": self._connectors[source.connector_id].to_dict(),
            "registry_fingerprint_sha256": self.fingerprint(),
            "governance": self.governance(),
        }

    def connector(self, connector_id: str) -> dict[str, Any]:
        connector_id = connector_id.strip().lower()
        if connector_id not in self._connectors:
            raise KeyError(connector_id)
        connector = self._connectors[connector_id]
        source = self._sources[connector.source_id]
        return {
            "schema": CONNECTOR_SCHEMA,
            "connector": connector.to_dict(),
            "source": source.to_dict(),
            "institution": self._institutions[source.institution_id].to_dict(),
            "registry_fingerprint_sha256": self.fingerprint(),
            "governance": self.governance(),
        }

    def collections(self) -> dict[str, Any]:
        counts = {item.collection_id: 0 for item in COLLECTIONS}
        for source in SOURCES:
            for collection_id in source.collection_ids:
                counts[collection_id] += 1
        return {
            "schema": COLLECTION_SCHEMA,
            "registry_fingerprint_sha256": self.fingerprint(),
            "collections": [{**item.to_dict(), "source_count": counts[item.collection_id]} for item in COLLECTIONS],
        }

    def validate_connector_manifest(self, payload: Mapping[str, Any]) -> dict[str, Any]:
        required = ("connector_id", "source_id", "execution_authority", "transport", "capabilities", "authentication", "pagination", "rate_limit_policy", "provenance_fields")
        missing = [field for field in required if field not in payload or payload.get(field) in (None, "", [])]
        errors: list[str] = []
        if missing:
            errors.append("missing-required-fields")
        capabilities = payload.get("capabilities", [])
        provenance = payload.get("provenance_fields", [])
        if capabilities and (not isinstance(capabilities, list) or not all(isinstance(value, str) and value.strip() for value in capabilities)):
            errors.append("invalid-capabilities")
        if provenance and (not isinstance(provenance, list) or not all(isinstance(value, str) and value.strip() for value in provenance)):
            errors.append("invalid-provenance-fields")
        guardrail_violations = []
        for field in ("automatic_import", "automatic_evidence_promotion", "automatic_truth_promotion", "source_membership_implies_endorsement"):
            if payload.get(field) is True:
                guardrail_violations.append(field)
        if guardrail_violations:
            errors.append("governance-guardrail-violation")
        normalized = {
            "connector_id": str(payload.get("connector_id") or "").strip().lower(),
            "source_id": str(payload.get("source_id") or "").strip().lower(),
            "execution_authority": str(payload.get("execution_authority") or "").strip().lower(),
            "transport": str(payload.get("transport") or "").strip().lower(),
            "capabilities": sorted({str(value).strip().lower() for value in capabilities if isinstance(value, str) and value.strip()}),
            "authentication": str(payload.get("authentication") or "").strip().lower(),
            "pagination": str(payload.get("pagination") or "").strip().lower(),
            "rate_limit_policy": str(payload.get("rate_limit_policy") or "").strip().lower(),
            "provenance_fields": sorted({str(value).strip() for value in provenance if isinstance(value, str) and value.strip()}),
            "language_policy": str(payload.get("language_policy") or "preserve-as-received").strip().lower(),
            "translation_behavior": str(payload.get("translation_behavior") or "none").strip().lower(),
            "raw_source_preservation": payload.get("raw_source_preservation", True) is not False,
            "automatic_import": False,
            "automatic_evidence_promotion": False,
            "automatic_truth_promotion": False,
            "source_membership_implies_endorsement": False,
        }
        return {
            "schema": VALIDATION_SCHEMA,
            "valid": not errors,
            "errors": errors,
            "missing_required_fields": missing,
            "guardrail_violations": guardrail_violations,
            "normalized_contract": normalized,
            "contract_fingerprint_sha256": _stable_fingerprint(normalized),
            "governance": self.governance(),
        }


registry = GlobalSourceFederationRegistry()
