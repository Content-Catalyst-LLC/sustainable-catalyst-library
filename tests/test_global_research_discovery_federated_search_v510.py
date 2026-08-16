from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MOD=PLUGIN/'includes/class-sc-library-global-research-discovery-federated-search.php'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
BOOT=PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php'
HARD=PLUGIN/'includes/class-sc-library-hardening.php'
ROUTE=PLUGIN/'includes/class-sc-library-canonical-route-identity.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.1.0.html'
README=PLUGIN/'readme.txt'
def text(p): return p.read_text()

def test_release_identity_is_v510():
    s=text(MAIN); assert 'Version: 5.1.0' in s and "SC_LIBRARY_VERSION', '5.1.0'" in s
    assert "public const VERSION = '5.1.0'" in text(MOD) and "public const VERSION = '5.1.0'" in text(ROUTE)

def test_module_reuses_public_api_context_and_federation_authorities():
    s=text(MOD)
    assert 'SC_Library_API_Embeds_Interoperability::normalize_public_object' in s
    assert 'SC_Library_Connected_Public_Research_Infrastructure::build_context' in s
    assert 'SC_Library_Global_Research_Federation::published_manifest_ids' in s
    assert 'SC_Library_Global_Research_Federation::manifest_state' in s

def test_search_contract_is_public_only_non_crawling_and_non_inferential():
    s=text(MOD)
    for needle in ["'remote_network_calls_during_search' => false", "'private_projects_searched' => false", "'personal_library_searched' => false", "'semantic_inference' => false", "'truth_scoring' => false", "'access_entitlement_inferred' => false"]: assert needle in s

def test_ranking_is_deterministic_lexical_not_authority_or_truth():
    s=text(MOD)
    assert "'ranking_mode' => 'deterministic-lexical'" in s
    assert 'lexical_score' in s and 'exact-title' in s and 'title-prefix' in s
    assert "'institutional_authority_scoring' => false" in s

def test_candidate_and_result_bounds_exist():
    s=text(MOD)
    assert 'MAX_RESULTS = 50' in s and 'MAX_LOCAL_CANDIDATES = 120' in s
    assert 'MAX_FEDERATION_MANIFESTS = 120' in s and 'MAX_FEDERATED_CANDIDATES = 240' in s
    assert 'array_slice' in s

def test_published_federation_only_and_provenance_is_preserved():
    s=text(MOD)
    assert "'published' !== (string) ( $state['status'] ?? '' )" in s
    for needle in ["'node_id' => $node_id", "'manifest_id' => absint( $manifest_id )", "'manifest_urn'", "'manifest_sha256'", "'record_provenance'"]: assert needle in s

def test_rest_surface_is_get_only_and_public():
    s=text(MOD)
    assert "REST_ROUTE = '/research-discovery'" in s
    assert s.count("'methods' => WP_REST_Server::READABLE") >= 3
    assert s.count("'permission_callback' => '__return_true'") >= 3
    assert 'CREATABLE' not in s and 'EDITABLE' not in s and 'DELETABLE' not in s

def test_cors_reuses_explicit_v49_origins_and_disables_credentials():
    s=text(MOD)
    assert 'SC_Library_API_Embeds_Interoperability::allowed_origins()' in s
    assert "'Access-Control-Allow-Credentials', 'false'" in s
    assert "'Access-Control-Allow-Methods', 'GET'" in s

def test_extension_bootstrap_adds_one_module():
    s=text(BOOT)
    assert 'public const MODULE_COUNT = 52;' in s
    assert "'class-sc-library-global-research-discovery-federated-search.php' => 'SC_Library_Global_Research_Discovery_Federated_Search'" in s

def test_production_gate_certifies_module_assets_and_cache_boundary():
    s=text(HARD)
    assert "BRANCH_VERSION = '5.1.0'" in s and "BRANCH_SCHEMA = 'sc-library-v510-discovery-certification/1.0'" in s
    assert "'SC_Library_Global_Research_Discovery_Federated_Search'" in s
    assert "'/sc-library/v1/research-discovery'" in s
    assert 'sc-library-research-discovery-v510.js' in s and 'sc-library-research-discovery-v510.css' in s
    assert 'v510-discovery-contract' in s and 'v510-discovery-cache-boundary' in s

def test_private_route_cache_samples_remain_excluded():
    s=text(HARD)
    for route in ['/personal-library','/research-projects','/reading-notebooks','/evidence-matrices','/research-rooms','/team-libraries','/research-federation/catalog']:
        assert route in s
    assert "'private_research_routes_cacheable' => $private_cacheable" in s

def test_accessible_responsive_frontend_and_timeout():
    js=text(PLUGIN/'assets/js/sc-library-research-discovery-v510.js'); css=text(PLUGIN/'assets/css/sc-library-research-discovery-v510.css'); s=text(MOD)
    assert 'AbortController' in js and '12000' in js and "credentials:'omit'" in js
    assert 'role="search"' in s and 'aria-live="polite"' in s and 'minlength="2"' in s
    assert ':focus-visible' in css and 'min-height:44px' in css and '@media(max-width:760px)' in css

def test_page_places_discovery_between_public_context_and_research_access():
    s=text(PAGE)
    assert 'cc-rl-v510' in s and '[sc_global_research_discovery title="Global Research Discovery &amp; Federated Search"]' in s
    assert s.index('id="connected-public-research"') < s.index('id="global-research-discovery"') < s.index('id="research-access"')
    assert 'href="#global-research-discovery"' in s

def test_identity_and_readme_are_current_without_new_private_store():
    r=text(ROUTE); rd=text(README)
    assert 'data-sc-library-account-continuity="v5.1.0"' in r
    assert 'Stable tag: 5.1.0' in rd and 'Global Research Discovery & Federated Search' in rd
    assert 'USER_META' not in text(MOD) and 'register_post_type' not in text(MOD)

def test_release_docs_state_truthful_search_boundary():
    d=text(ROOT/'GLOBAL_RESEARCH_DISCOVERY_FEDERATED_SEARCH_v5.1.0.md'); n=text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.1.0.md'); u=text(ROOT/'RESEARCH_LIBRARY_PAGE_UPDATE_v5.1.0.md')
    for s in [d,n]:
        assert 'deterministic lexical' in s.lower() and 'private' in s.lower() and ('remote crawling' in s.lower() or 'crawl' in s.lower())
    assert '/knowledge-libraries/' in u

def test_php_fixture_proves_lexical_and_privacy_contract():
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v510_discovery.php')],cwd=ROOT,text=True,capture_output=True,check=True)
    assert 'PASS - v5.1.0 discovery contract fixture' in result.stdout
