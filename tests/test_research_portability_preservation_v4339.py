from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text(); BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text(); MOD=(PLUGIN/'includes/class-sc-library-research-portability-preservation.php').read_text(); ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text(); PROJECT=(PLUGIN/'includes/class-sc-library-unified-research-projects-source-bundles.php').read_text(); NOTEBOOK=(PLUGIN/'includes/class-sc-library-reading-notebook-annotations.php').read_text(); MATRIX=(PLUGIN/'includes/class-sc-library-evidence-matrix-claim-intelligence.php').read_text(); LEARNING=(PLUGIN/'includes/class-sc-library-open-learning-ii.php').read_text(); PRES=(PLUGIN/'includes/class-sc-library-preservation.php').read_text(); PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.39.html').read_text(); README=(PLUGIN/'readme.txt').read_text(); DOC=(ROOT/'RESEARCH_PORTABILITY_PRESERVATION_v4.3.39.md').read_text(); NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.39.md').read_text(); JS=(PLUGIN/'assets/js/sc-library-research-portability-v4339.js').read_text(); CSS=(PLUGIN/'assets/css/sc-library-research-portability-v4339.css').read_text()

def test_release_identity_and_extension_registration():
    assert 'Version: 4.3.39' in MAIN and "define('SC_LIBRARY_VERSION', '4.3.39');" in MAIN
    assert 'class-sc-library-research-portability-preservation.php' in BOOT and 'SC_Library_Research_Portability_Preservation' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==44

def test_reuses_existing_research_and_preservation_stores():
    for marker in ['SC_Library_Unified_Research_Projects_Source_Bundles::project_state','SC_Library_Reading_Notebook_Annotations::state_for_user','SC_Library_Evidence_Matrix_Claim_Intelligence::state_for_user','SC_Library_Open_Learning_II::route_manifest','SC_Library_Preservation::SCHEMA']:
        assert marker in MOD
    assert 'register_post_type' not in MOD and 'update_user_meta' not in MOD and 'wp_insert_post' not in MOD
    assert 'sc_research_project' in PROJECT and 'sc_reading_notebook' in NOTEBOOK and 'sc_evidence_matrix' in MATRIX and "USER_META='sc_library_learning_routes_v4336'" in LEARNING and "SCHEMA = 'sc-library-preservation/1.0'" in PRES

def test_owned_project_is_required_for_export():
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' in MOD
    assert 'sc_portability_project_forbidden' in MOD and 'Choose a Research Project owned by this account.' in MOD

def test_package_schema_and_stable_preservation_manifest_are_explicit():
    assert "PACKAGE_SCHEMA = 'sc-library-research-portability-package/1.0'" in MOD
    assert "MANIFEST_SCHEMA = 'sc-library-research-preservation-manifest/1.0'" in MOD
    assert "'export_urn'" in MOD and "'project_urn'" in MOD and "'canonical_library_url'" in MOD

def test_export_has_five_independently_checksummed_sections():
    for marker in ["'project'", "'source_bundles'", "'notebooks'", "'evidence_matrices'", "'learning_routes'"]:
        assert marker in MOD
    assert "section_checksums_sha256" in MOD and "manifest_checksum_sha256" in MOD and "package_checksum_sha256" in MOD
    assert "hash( 'sha256'" in MOD

def test_complete_and_manifest_only_profiles_are_distinct():
    assert "array( 'complete', 'manifest' )" in MOD
    assert "'complete' === $profile" in MOD and 'content_omitted_by_export_profile' in MOD
    assert 'Complete research package' in MOD and 'Manifest only — identities and references' in MOD

def test_binary_credentials_and_raw_storage_are_excluded():
    for marker in ["'private_binary_files_embedded'       => false", "'credentials_embedded'                => false", "'raw_wordpress_tables_embedded'       => false"]:
        assert marker in MOD
    for blocked in ["'attachment_path'", "'file_path'", "'binary'", "'api_key'", "'access_token'", "'secret'"]:
        assert blocked in MOD
    assert 'private source binaries' in DOC.lower() and 'raw wordpress tables' in DOC.lower()

def test_validation_is_bounded_non_executing_and_non_importing():
    assert 'MAX_PACKAGE_BYTES = 8388608' in MOD
    assert "'automatic_import' => false" in MOD and "'records_created' => 0" in MOD and "'payload_executed' => false" in MOD
    assert 'wp_insert_post' not in MOD and 'update_post_meta' not in MOD and 'wp_delete_post' not in MOD

def test_validator_checks_package_manifest_and_section_integrity():
    for marker in ['package_checksum_valid','manifest_checksum_valid','section_checksums','Package checksum does not match','Preservation manifest checksum does not match','Section checksum mismatch']:
        assert marker in MOD
    assert 'hash_equals' in MOD

def test_authenticated_catalog_export_validate_rest_routes_exist():
    assert "REST_ROUTE = '/research-portability'" in MOD
    for marker in ["self::REST_ROUTE . '/catalog'", "self::REST_ROUTE . '/export'", "self::REST_ROUTE . '/validate'"]:
        assert marker in MOD
    assert MOD.count('is_user_logged_in()') >= 1 and MOD.count('WP_REST_Server::CREATABLE') >= 2

def test_shortcode_and_same_origin_download_validation_ui_exist():
    assert "add_shortcode( 'sc_research_portability'" in MOD
    assert "credentials:'same-origin'" in JS and "'X-WP-Nonce'" in JS and 'new Blob' in JS and 'JSON.parse' in JS
    assert 'data-sc-rp-output aria-live="polite"' in MOD

def test_page_keeps_portability_inside_existing_research_workspace_to_limit_bloat():
    assert '[sc_research_portability title="Research Portability & Preservation"]' in PAGE
    start=PAGE.index('id="research-workspace"'); port=PAGE.index('id="research-portability"'); end=PAGE.index('</section>',port)
    assert start < port < end
    assert PAGE.index('[sc_library_unified_workspace]',start) < port

def test_page_preserves_current_research_stack():
    for marker in ['[sc_research_librarian_ii','id="publication-research-graph-section"','id="open-learning-ii"','id="workspace-continuity"','id="evidence-matrix"','id="reading-notebooks"','id="research-projects"']:
        assert marker in PAGE

def test_identity_health_is_version_aligned_without_new_private_store():
    assert "public const VERSION = '4.3.39'" in ROUTE and 'data-sc-library-account-continuity="v4.3.39"' in ROUTE
    assert "'research_exports'" not in ROUTE and "'research_imports'" not in ROUTE

def test_readme_and_release_docs_are_current_and_truthful():
    assert 'Stable tag: 4.3.39' in README and '[sc_research_portability]' in README and '/wp-json/sc-library/v1/research-portability/validate' in README
    assert 'Validation is non-executing and non-importing' in DOC and 'No automatic publication' in NOTES

def test_front_end_is_accessible_responsive_and_clear_about_no_mutation():
    assert ':focus-visible' in CSS and '@media(max-width:760px)' in CSS and 'min-height:44px' in CSS
    assert 'No Library, publication, evidence, or Workspace record was changed.' in JS
    assert 'does not execute the payload, create records, import data, publish anything, or write to Workspace' in MOD
