from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
MOD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-api-embeds-interoperability.php'
MAIN=ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php'
BOOT=ROOT/'sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php'
HARD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-hardening.php'
ROUTE=ROOT/'sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php'
HOME=ROOT/'sustainable-catalyst-library/includes/class-sc-library-unified-personal-research-environment.php'
LEGACY=ROOT/'sustainable-catalyst-library/includes/class-sc-library-public-api-export-federation.php'
FED=ROOT/'sustainable-catalyst-library/includes/class-sc-library-global-research-federation.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v4.9.0.html'
README=ROOT/'sustainable-catalyst-library/readme.txt'
def text(p): return p.read_text()

def test_release_identity_is_v490():
    s=text(MAIN); m=text(MOD)
    assert 'Version: 4.9.0' in s and "SC_LIBRARY_VERSION', '4.9.0'" in s
    assert "public const VERSION = '4.9.0'" in m
    assert "SCHEMA = 'sc-library-api-interoperability/1.0'" in m

def test_facade_reuses_canonical_api_and_federation_authorities_without_parallel_stores():
    s=text(MOD)
    for marker in ["'canonical_public_records_reused' => true","'legacy_v390_public_api_reused' => true","'v480_federation_facade_reused' => true","'creates_parallel_public_record_store' => false","'creates_parallel_token_store' => false","'creates_parallel_federation_registry' => false"]: assert marker in s
    assert 'SC_Library_Global_Research_Federation::published_manifest_ids()' in s
    assert 'sc_api_token' in text(LEGACY) and 'sc_federation_peer' in text(LEGACY)

def test_public_object_profiles_are_explicit_and_bounded():
    s=text(MOD)
    for marker in ["'foundation-document'","'publication'","'pathway'","'research-source'","'named-entity'","'concept'"]: assert marker in s
    assert 'MAX_RESULTS = 50' in s and 'DEFAULT_RESULTS = 20' in s and 'MAX_SUMMARY = 700' in s

def test_normalized_object_requires_publish_status_and_excludes_raw_meta():
    s=text(MOD)
    assert "'publish' !== $post->post_status" in s
    assert "'private_fields_included' => false" in s and "'raw_post_meta_included' => false" in s
    assert 'get_post_meta( $post->ID' not in s

def test_public_rest_facade_is_get_only_and_does_not_add_authenticated_writes():
    s=text(MOD)
    assert "REST_ROUTE = '/library-api'" in s
    assert s.count("'permission_callback' => '__return_true'") >= 5
    assert "'methods' => WP_REST_Server::READABLE" in s
    assert "'automatic_cross_site_write' => false" in s

def test_interoperability_manifest_has_sha256_and_read_only_boundary():
    s=text(MOD)
    assert "MANIFEST_SCHEMA = 'sc-library-interoperability-manifest/1.0'" in s
    assert "hash( 'sha256'" in s
    assert "'read_only' => true" in s and "'references_only' => true" in s
    assert "'credentials_included' => false" in s and "'private_content_included' => false" in s

def test_published_federation_manifests_are_reused_not_republished_from_private_team_data():
    s=text(MOD); f=text(FED)
    assert 'SC_Library_Global_Research_Federation::published_manifest_ids()' in s
    assert 'SC_Library_Global_Research_Federation::META_MANIFEST' in s
    assert "'explicit_team_governor_publish_required' => true" in f

def test_cors_is_explicit_origin_get_only_and_never_credentialed():
    s=text(MOD)
    assert 'sc_library_api_embed_allowed_origins' in s
    assert "in_array( $origin, self::allowed_origins(), true )" in s
    assert "'Access-Control-Allow-Credentials', 'false'" in s
    assert "'Access-Control-Allow-Methods', 'GET'" in s
    assert "'wildcard_write_cors' => false" in s

def test_local_and_external_embeds_use_public_object_boundary():
    s=text(MOD); js=text(ROOT/'sustainable-catalyst-library/assets/js/sc-library-api-interoperability-v490.js')
    assert 'sc_library_embed' in s and 'sc_library_api_interoperability' in s
    assert 'data-sc-library-api-embed' in s and 'data-sc-library-api-embed' in js
    assert "credentials:'omit'" in js and "method:'GET'" in js
    assert 'innerHTML' in js and 'function esc' in js

def test_front_end_is_accessible_mobile_and_reduced_motion_aware():
    css=text(ROOT/'sustainable-catalyst-library/assets/css/sc-library-api-interoperability-v490.css'); s=text(MOD)
    assert 'focus-visible' in css and '@media(max-width:760px)' in css and 'prefers-reduced-motion' in css
    assert 'aria-busy' in text(ROOT/'sustainable-catalyst-library/assets/js/sc-library-api-interoperability-v490.js')
    assert 'Public integration layer' in s

def test_extension_bootstrap_registers_v490_without_replacing_prior_layers():
    s=text(BOOT)
    assert 'MODULE_COUNT = 50' in s
    assert 'class-sc-library-api-embeds-interoperability.php' in s
    assert 'SC_Library_Global_Research_Federation' in s and 'SC_Library_Public_API_Export_Federation' in s

def test_identity_is_version_aligned_without_new_private_account_store():
    s=text(ROUTE)
    assert "public const VERSION = '4.9.0'" in s
    assert 'data-sc-library-account-continuity="v4.9.0"' in s
    assert 'sc_api_interoperability' not in s

def test_production_gate_certifies_module_and_assets_without_making_public_route_private():
    s=text(HARD)
    assert "BRANCH_VERSION = '4.9.0'" in s and "BRANCH_SCHEMA = 'sc-library-v49-production-certification/1.0'" in s
    assert 'SC_Library_API_Embeds_Interoperability' in s
    assert 'assets/js/sc-library-api-interoperability-v490.js' in s and 'assets/css/sc-library-api-interoperability-v490.css' in s
    assert '/sc-library/v1/library-api' not in s.split('private const V43_PRIVATE_ROUTES = [',1)[1].split('];',1)[0]
    assert '4.9 release gate' in s

def test_unified_personal_environment_links_to_api_without_exposing_private_state():
    s=text(HOME); m=text(MOD)
    assert '#library-api-interoperability' in s and 'API & Interoperability' in s
    assert "'private_research_exposed' => false" in m and "'public_facade_only' => true" in m

def test_research_library_places_api_after_federation_before_access():
    s=text(PAGE)
    assert '[sc_library_api_interoperability title="Library API, Embeds &amp; Interoperability"]' in s
    assert s.index('id="global-research-federation"') < s.index('id="library-api-interoperability"') < s.index('id="research-access"')
    assert '<li><a href="#library-api-interoperability">Library API, Embeds &amp; Interoperability</a></li>' in s

def test_readme_docs_and_php_fixture_state_public_only_boundary():
    s=text(README); d=text(ROOT/'LIBRARY_API_EMBEDS_INTEROPERABILITY_v4.9.0.md'); n=text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.9.0.md')
    assert 'Stable tag: 4.9.0' in s
    for blob in [s,d,n]: assert 'Library API, Embeds & Interoperability' in blob
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v490_contract.php')],capture_output=True,text=True)
    assert result.returncode==0,result.stderr
    assert 'PASS - v4.9.0 Library API, Embeds & Interoperability contract fixture' in result.stdout
