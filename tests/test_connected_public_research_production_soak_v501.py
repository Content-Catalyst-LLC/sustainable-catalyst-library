from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
MOD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-connected-public-research-infrastructure.php'
HARD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-hardening.php'
MAIN=ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php'
ROUTE=ROOT/'sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php'
API=ROOT/'sustainable-catalyst-library/includes/class-sc-library-api-embeds-interoperability.php'
JS=ROOT/'sustainable-catalyst-library/assets/js/sc-library-connected-public-research-v500.js'
README=ROOT/'sustainable-catalyst-library/readme.txt'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.0.1.html'
def text(p): return p.read_text()

def test_release_identity_is_v501():
    assert 'Version: 5.0.1' in text(MAIN) and "SC_LIBRARY_VERSION', '5.0.1'" in text(MAIN)
    assert "public const VERSION = '5.0.1'" in text(MOD) and "public const VERSION = '5.0.1'" in text(ROUTE)

def test_soak_contract_has_ten_bounded_first_party_scenarios():
    s=text(MOD)
    assert 'SOAK_SCENARIO_COUNT = 10' in s and "SOAK_SCHEMA = 'sc-library-connected-public-research-soak/1.0'" in s
    assert "'external_network_calls_in_soak' => false" in s and "'private_record_content_inspected_by_soak' => false" in s
    assert s.count("array( 'id' => '") >= 10

def test_soak_routes_split_public_summary_from_admin_details():
    s=text(MOD)
    assert "SOAK_ROUTE = '/runtime/connected-public-research-soak'" in s
    assert "self::SOAK_ROUTE . '/details'" in s and "current_user_can( 'manage_options' )" in s
    assert "'permission_callback' => '__return_true'" in s

def test_malformed_requests_are_exercised_without_record_body_access():
    s=text(MOD)
    assert "index_payload( '__invalid_public_type__'" in s and "build_context( '__invalid_public_type__', 0 )" in s
    assert "'malformed_request_guards' => true" in s

def test_safe_v5_cache_is_explicit_allowlist_not_broad_namespace():
    s=text(HARD)
    assert 'V5_PUBLIC_ROUTE_PREFIXES' in s
    for route in ['/sc-library/v1/library-api','/sc-library/v1/connected-public-research','/sc-library/v1/research-federation/node','/sc-library/v1/research-federation/manifests']: assert route in s
    assert "str_starts_with($request->get_route(), '/sc-library/v1/')" not in s

def test_private_research_routes_are_never_v5_public_cache_samples():
    s=text(HARD)
    for route in ['/sc-library/v1/personal-library','/sc-library/v1/research-projects','/sc-library/v1/reading-notebooks','/sc-library/v1/evidence-matrices','/sc-library/v1/research-rooms','/sc-library/v1/team-libraries','/sc-library/v1/research-federation/catalog']: assert route in s
    assert "'private_research_routes_cacheable' => $private_cacheable" in s

def test_cache_hit_miss_bypass_and_freshness_headers_exist():
    s=text(HARD)
    for marker in ["'X-SC-Library-Cache', 'HIT'","'X-SC-Library-Cache', 'MISS'","'X-SC-Library-Cache', 'BYPASS'","'X-SC-Library-Cache-Age'","'X-SC-Library-Data-State'","'X-SC-Library-Freshness-Window'"]: assert marker in s
    assert "'stored_at' => time()" in s and "'ttl' => $ttl" in s

def test_generation_invalidation_is_preserved():
    s=text(HARD)
    assert "CACHE_GENERATION_OPTION = 'sc_library_public_cache_generation'" in s
    assert 'invalidate_public_cache' in s and "'generation_invalidation' => true" in s

def test_cors_exposes_observability_but_never_credentials():
    a=text(API); m=text(MOD)
    assert 'Access-Control-Expose-Headers' in a and 'X-SC-Library-Cache-Age' in a
    assert 'Access-Control-Expose-Headers' in m and 'X-SC-Library-Version' in m and 'X-SC-Library-Schema' in m
    assert "'Access-Control-Allow-Credentials', 'false'" in a and "'Access-Control-Allow-Credentials', 'false'" in m

def test_frontend_has_bounded_timeout_and_degraded_rate_limit_states():
    s=text(JS)
    assert 'AbortController' in s and '12000' in s and "credentials:'omit'" in s
    assert "e.status===429" in s and 'temporarily rate limited' in s and 'temporarily unavailable' in s

def test_production_readiness_certifies_soak_and_safe_cache():
    s=text(HARD)
    assert "BRANCH_VERSION = '5.0.1'" in s and "BRANCH_SCHEMA = 'sc-library-v501-production-soak-certification/1.0'" in s
    assert 'v501-public-cache-boundary' in s and 'v501-connected-public-soak' in s
    assert 'run_production_soak(false)' in s

def test_no_new_extension_module_or_storage_migration_is_added():
    boot=text(ROOT/'sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php')
    assert 'MODULE_COUNT = 51' in boot
    assert 'class-sc-library-connected-public-research-production-soak.php' not in boot
    s=text(MOD)
    assert "'creates_parallel_public_record_store' => false" in s and "'creates_parallel_graph_store' => false" in s

def test_public_graph_bounds_and_sha256_contract_are_retained():
    s=text(MOD)
    assert 'MAX_CONNECTIONS = 120' in s and "'one_hop_network_only' => true" in s and "'explicit_relationships_only' => true" in s
    assert "hash( 'sha256'" in s

def test_identity_and_page_are_version_aligned_without_new_section_bloat():
    r=text(ROUTE); p=text(PAGE)
    assert 'data-sc-library-account-continuity="v5.0.1"' in r
    assert 'cc-rl-v501' in p and 'ten-scenario first-party production soak' in p
    assert p.count('id="connected-public-research"') == 1 and p.count('id="research-infrastructure"') == 1

def test_readme_and_docs_state_truthful_hardening_boundary():
    r=text(README); d=text(ROOT/'CONNECTED_PUBLIC_RESEARCH_PRODUCTION_SOAK_v5.0.1.md'); n=text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.0.1.md')
    assert 'Stable tag: 5.0.1' in r
    for blob in [r,d,n]: assert 'production soak' in blob.lower() and 'private' in blob.lower()

def test_php_fixture_proves_soak_and_cache_contract():
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v501_soak.php')],capture_output=True,text=True)
    assert result.returncode==0,result.stderr
    assert 'PASS - v5.0.1 production soak contract fixture' in result.stdout
