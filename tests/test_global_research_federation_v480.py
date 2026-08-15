from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
MOD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-global-research-federation.php'
MAIN=ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php'
BOOT=ROOT/'sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php'
HARD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-hardening.php'
ROUTE=ROOT/'sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php'
HOME=ROOT/'sustainable-catalyst-library/includes/class-sc-library-unified-personal-research-environment.php'
LEGACY=ROOT/'sustainable-catalyst-library/includes/class-sc-library-public-api-export-federation.php'
TEAM=ROOT/'sustainable-catalyst-library/includes/class-sc-library-institutional-team-libraries.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v4.8.0.html'
README=ROOT/'sustainable-catalyst-library/readme.txt'
def text(p): return p.read_text()

def test_release_identity_is_v480():
    s=text(MAIN); m=text(MOD)
    assert 'Version: 4.8.0' in s and "SC_LIBRARY_VERSION', '4.8.0'" in s
    assert "public const VERSION = '4.8.0'" in m
    assert "POST_TYPE = 'sc_federation_share'" in m

def test_federation_reuses_v390_and_v470_authorities_without_parallel_registries():
    s=text(MOD)
    for marker in ["'legacy_v390_federation_reused' => true","'team_library_authority_reused' => true","'creates_parallel_peer_registry' => false","'creates_parallel_import_quarantine' => false","'creates_parallel_institution_registry' => false","'creates_parallel_research_source_store' => false"]:
        assert marker in s
    assert 'SC_Library_Public_API_Export_Federation::quarantine_import' in s
    assert 'SC_Library_Institutional_Team_Libraries::contribute_reference' in s

def test_versioned_manifest_storage_is_private_and_bounded():
    s=text(MOD)
    for marker in ['_sc_federation_share_urn_v480','_sc_federation_share_team_library_id_v480','_sc_federation_share_reference_ids_v480','_sc_federation_share_status_v480','_sc_federation_share_manifest_v480','_sc_federation_share_sha256_v480']:
        assert marker in s
    assert "'public' => false" in s and "'show_ui' => false" in s
    for marker in ['MAX_MANIFESTS_PER_TEAM_LIBRARY = 120','MAX_REFERENCES_PER_MANIFEST = 200','MAX_PUBLIC_MANIFESTS = 250','MAX_INBOUND_RECORDS = 200']:
        assert marker in s

def test_outbound_manifest_requires_governor_and_explicit_reference_selection():
    s=text(MOD)
    assert "SC_Library_Institutional_Team_Libraries::can( $library_id, $actor_id, 'govern' )" in s
    assert "'explicit_team_governor_publish_required' => true" in s
    assert "'reference_ids'" in s and 'Select at least one Team Library reference or collection.' in s
    assert "'published' === sanitize_key" in s and "array( 'draft', 'published', 'revoked' )" in s

def test_public_manifest_is_metadata_only_and_excludes_private_research():
    s=text(MOD)
    for marker in ["'public_exchange_metadata_only' => true","'personal_library_exported' => false","'private_project_data_exported' => false","'research_room_membership_exported' => false","'notebook_bodies_exported' => false","'matrix_bodies_exported' => false","'source_binaries_exported' => false","'credentials_exported' => false"]:
        assert marker in s
    assert "'private_content_included' => false" in s and "'credentials_included' => false" in s

def test_manifests_use_stable_identity_and_sha256_integrity():
    s=text(MOD)
    assert "MANIFEST_SCHEMA = 'sc-library-research-federation-manifest/1.0'" in s
    assert "self::urn( 'federation-share' )" in s
    assert "hash( 'sha256', self::canonical_json" in s
    assert "'id' => $canonical_id ?: ( $reference_id ?: $url )" in s
    assert "'type' => $kind" in s
    assert 'hash_equals( $expected, self::manifest_hash( $payload ) )' in s
    assert "$errors[] = 'version-incompatible'" in s

def test_inbound_manifest_is_quarantined_and_not_auto_imported():
    s=text(MOD)
    assert "'explicit_remote_import_review_required' => true" in s
    assert "'approved_metadata_does_not_auto_import' => true" in s
    assert 'SC_Library_Public_API_Export_Federation::quarantine_import' in s
    assert "'automatic_import_allowed' => false" in s
    assert 'SC_Library_Public_API_Export_Federation::decide_import' in s

def test_approved_metadata_requires_second_team_acceptance_and_preserves_provenance():
    s=text(MOD)
    assert "'explicit_team_acceptance_required' => true" in s
    assert "'approved-metadata' !== (string)" in s
    assert "'federation:' . self::clean( $payload['origin_node_id']" in s
    assert "'accepted_reference_count'" in s and "'duplicate_reference_count'" in s
    assert "'automatic_content_import' => false" in s

def test_peer_trust_and_institution_context_never_imply_truth_or_entitlement():
    s=text(MOD)
    for marker in ["'peer_trust_is_transport_governance_not_truth' => true","'institutional_context_is_not_entitlement' => true","'remote_identity_is_not_local_membership' => true","'truth_scoring' => false"]:
        assert marker in s
    assert 'Trust is not truth' in s and 'subscription entitlement' in s

def test_public_and_private_rest_boundaries_are_distinct():
    s=text(MOD)
    assert "REST_ROUTE = '/research-federation'" in s
    assert "REST_ROUTE . '/node'" in s and "'permission_callback' => '__return_true'" in s
    assert "REST_ROUTE . '/catalog'" in s and "'permission_callback' => array( $this, 'rest_signed_in' )" in s
    assert "REST_ROUTE . '/imports'" in s and "'permission_callback' => array( $this, 'rest_admin' )" in s

def test_front_end_is_accessible_mobile_and_nonce_protected():
    s=text(MOD); js=text(ROOT/'sustainable-catalyst-library/assets/js/sc-library-global-research-federation-v480.js'); css=text(ROOT/'sustainable-catalyst-library/assets/css/sc-library-global-research-federation-v480.css')
    assert 'sc_global_research_federation' in s and 'data-sc-global-federation="v4.8.0"' in s
    assert 'aria-live="polite"' in s and 'X-WP-Nonce' in js
    assert 'focus-visible' in css and 'prefers-reduced-motion' in css and '@media(max-width:760px)' in css
    assert 'window.confirm' in js

def test_extension_bootstrap_registers_federation_without_replacing_prior_layers():
    s=text(BOOT)
    assert 'MODULE_COUNT = 49' in s
    assert 'class-sc-library-global-research-federation.php' in s
    assert 'SC_Library_Institutional_Team_Libraries' in s and 'SC_Library_Public_API_Export_Federation' in s

def test_identity_and_personal_environment_track_federation_governance():
    r=text(ROUTE); h=text(HOME)
    assert "public const VERSION = '4.8.0'" in r
    assert "'federation_manifests'   => 'sc_federation_share:post_author'" in r
    assert 'data-sc-library-account-continuity="v4.8.0"' in r
    assert "federation_manifests'=>__('Federation manifests'" in h
    assert '#global-research-federation' in h and 'Research Federation' in h

def test_production_gate_certifies_module_assets_and_private_catalog_route():
    s=text(HARD)
    assert "BRANCH_VERSION = '4.8.0'" in s and "BRANCH_SCHEMA = 'sc-library-v48-production-certification/1.0'" in s
    assert 'SC_Library_Global_Research_Federation' in s
    assert '/sc-library/v1/research-federation/catalog' in s
    assert 'assets/js/sc-library-global-research-federation-v480.js' in s and 'assets/css/sc-library-global-research-federation-v480.css' in s
    assert '4.8 release gate' in s

def test_research_library_places_federation_after_team_libraries_before_access():
    s=text(PAGE)
    assert '[sc_global_research_federation title="Global Research Federation"]' in s
    assert s.index('id="institutional-team-libraries"') < s.index('id="global-research-federation"') < s.index('id="research-access"')
    assert '<li><a href="#global-research-federation">Global Research Federation</a></li>' in s

def test_readme_docs_legacy_lineage_and_php_fixture_are_truthful():
    s=text(README); d=text(ROOT/'GLOBAL_RESEARCH_FEDERATION_v4.8.0.md'); n=text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.8.0.md'); legacy=text(LEGACY); team=text(TEAM)
    assert 'Stable tag: 4.8.0' in s
    for blob in [s,d,n]: assert 'Global Research Federation' in blob
    assert 'sc_federation_peer' in legacy and 'sc_federation_import' in legacy
    assert "POST_TYPE = 'sc_team_library'" in team
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v480_contract.php')],capture_output=True,text=True)
    assert result.returncode==0,result.stderr
    assert 'PASS - v4.8.0 global research federation contract fixture' in result.stdout
