from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
SRC=(PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php').read_text()
REL=(PLUGIN/'includes/class-sc-library-connector-holdings-reliability.php').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.18.html').read_text()
JS=(PLUGIN/'assets/js/sc-library-connectors.js').read_text()
CSS=(PLUGIN/'assets/css/sc-library-connectors.css').read_text()
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()

def test_version_and_page_front_door():
    assert 'Version: 4.3.18' in MAIN
    assert 'Search Libraries, Universities, and Scholarly Research' in PAGE
    assert 'providers="internetarchive,mit,harvard,loc,ucd,crossref,openalex,datacite,pubmed,pmc,europepmc,arxiv"' in PAGE

def test_direct_scholarly_connectors_exist():
    for provider in ('ucd','arxiv','europepmc'):
        assert f"private function search_{provider}" in SRC
        assert f"'{provider}' => array(" in SRC
    for provider in ('crossref','openalex','datacite','pubmed','pmc'):
        assert f"private function search_{provider}" in SRC

def test_public_allowlist_and_research_access_gate():
    assert "'researchrepository.ucd.ie'" in REL
    assert "'export.arxiv.org'" in REL
    assert "'www.ebi.ac.uk'" in REL
    assert "'ucd', 'crossref', 'openalex', 'datacite', 'pubmed', 'pmc', 'europepmc', 'arxiv'" in SRC

def test_google_scholar_is_gateway_not_scraper():
    assert 'Sustainable Catalyst does not scrape Google Scholar' in SRC
    assert 'data-sc-google-scholar-handoff' in SRC
    assert "private function search_google_scholar" not in SRC

def test_university_sustainability_network_visible():
    for name in ('Stanford University','Yale University','Princeton University','Columbia University','UC Berkeley','University of Copenhagen','Stockholm University','Wageningen University & Research','Lund University','ETH Zurich','University of Oxford','University of Cambridge','IIASA','United Nations University','Stockholm Environment Institute'):
        assert name in SRC
    assert 'data-sc-research-gateway' in SRC
    assert 'sc-research-access__institution-grid' in CSS
    assert "[data-sc-research-gateway]" in JS

def test_openalex_public_tier():
    block=SRC.split("'openalex' => array(",1)[1].split("'datacite' => array(",1)[0]
    assert "'available'         => true" in block
    assert 'Free public API tier' in block
    search=SRC.split('private function search_openalex',1)[1].split('private function search_datacite',1)[0]
    assert "if ( ! empty( $settings['openalex_api_key'] ) )" in search

def test_ucd_uses_dspace_discovery_contract():
    block=SRC.split('private function search_ucd',1)[1].split('private function search_europepmc',1)[0]
    assert 'researchrepository.ucd.ie/server/api/discover/search/objects' in block
    assert 'dspace_values' in SRC
    assert 'public-repository-record' in block

def test_arxiv_and_europepmc_open_access_signals():
    assert 'https://export.arxiv.org/api/query' in SRC
    assert 'https://www.ebi.ac.uk/europepmc/webservices/rest/search' in SRC
    assert "'full_text_status' => 'open-access'" in SRC
