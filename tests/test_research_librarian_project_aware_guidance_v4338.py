from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text(); BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text(); MOD=(PLUGIN/'includes/class-sc-library-research-librarian-project-aware-guidance.php').read_text(); ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text(); PROJECT=(PLUGIN/'includes/class-sc-library-unified-research-projects-source-bundles.php').read_text(); NOTEBOOK=(PLUGIN/'includes/class-sc-library-reading-notebook-annotations.php').read_text(); MATRIX=(PLUGIN/'includes/class-sc-library-evidence-matrix-claim-intelligence.php').read_text(); ORCH=(PLUGIN/'includes/class-sc-library-orchestrator.php').read_text(); PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.38.html').read_text(); README=(PLUGIN/'readme.txt').read_text(); DOC=(ROOT/'RESEARCH_LIBRARIAN_II_PROJECT_AWARE_GUIDANCE_v4.3.38.md').read_text(); NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.38.md').read_text(); JS=(PLUGIN/'assets/js/sc-library-research-librarian-ii-v4338.js').read_text(); CSS=(PLUGIN/'assets/css/sc-library-research-librarian-ii-v4338.css').read_text()

def test_release_identity_and_extension_registration():
    assert 'Version: 4.3.38' in MAIN and "define('SC_LIBRARY_VERSION', '4.3.38');" in MAIN
    assert 'class-sc-library-research-librarian-project-aware-guidance.php' in BOOT and 'SC_Library_Research_Librarian_Project_Aware_Guidance' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==43

def test_reuses_existing_private_research_stores_without_new_project_store():
    for marker in ['SC_Library_Unified_Research_Projects_Source_Bundles::project_state','SC_Library_Reading_Notebook_Annotations::notebook_state','SC_Library_Evidence_Matrix_Claim_Intelligence::matrix_state']:
        assert marker in MOD
    assert "sc_research_project" in PROJECT and "sc_reading_notebook" in NOTEBOOK and "sc_evidence_matrix" in MATRIX
    assert 'register_post_type' not in MOD

def test_project_context_requires_current_user_ownership():
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' in MOD
    assert "sc_librarian_v2_project_forbidden" in MOD and "'visibility'         => 'private'" in MOD

def test_bundle_notebook_matrix_context_are_validated_against_selected_project():
    assert 'bundle_manifest' in MOD and 'notebook_state' in MOD and 'matrix_state' in MOD
    assert "notebook is not attached to the selected project" in MOD and "evidence matrix is not attached to the selected project" in MOD

def test_private_context_packet_is_checksummed_and_bounded():
    assert "CONTEXT_SCHEMA = 'sc-library-project-guidance-context/1.0'" in MOD
    assert 'MAX_NOTE_PREVIEWS = 8' in MOD and 'MAX_CLAIM_PREVIEWS = 20' in MOD and 'MAX_PUBLIC_RECORD_IDS = 8' in MOD
    assert "hash( 'sha256', wp_json_encode( $context ) )" in MOD and "checksum_sha256" in MOD

def test_guidance_is_deterministic_and_descriptive_not_truth_judgment():
    assert "'guidance_mode'                           => 'deterministic-descriptive'" in MOD
    for marker in ['Strengthen the source base','Resolve broken or ambiguous references','Seek counterevidence deliberately','Verify quotations and locators','Diversify evidence sources']:
        assert marker in MOD
    assert 'workflow diagnostics, not truth judgments' in DOC.lower()

def test_remote_synthesis_boundary_is_explicit():
    assert "'private_context_sent_to_remote_synthesis'=> false" in MOD
    assert "'private_context_included' => false" in MOD
    assert 'private project context is **not sent to the optional research librarian remote-synthesis endpoint**' in DOC.lower()
    assert 'remote_synthesis' in ORCH

def test_existing_orchestrator_receives_only_prompt_and_public_source_ids():
    assert "'prompt'            => $prompt" in MOD and "'record_ids'        =>" in MOD
    assert "'source' === $family" in MOD and "'publish' === get_post_status( $post_id )" in MOD
    assert 'sc-library-librarian-request' in JS and 'recordIds:h.record_ids' in JS

def test_no_automatic_private_record_mutation_or_evidence_promotion():
    for marker in ["'automatic_project_write'                 => false","'automatic_notebook_write'                => false","'automatic_evidence_promotion'            => false","'automatic_claim_creation'                => false","'automatic_publication'                   => false","'automatic_workspace_write'               => false"]:
        assert marker in MOD
    assert 'wp_insert_post' not in MOD and 'update_post_meta' not in MOD and 'wp_delete_post' not in MOD

def test_authenticated_rest_catalog_and_guidance_routes_exist():
    assert "REST_ROUTE = '/research-librarian-v2'" in MOD
    assert "self::REST_ROUTE . '/catalog'" in MOD and "self::REST_ROUTE . '/guidance'" in MOD
    assert MOD.count("is_user_logged_in()") >= 3 and 'WP_REST_Server::CREATABLE' in MOD

def test_project_aware_shortcode_and_same_origin_front_end_exist():
    assert "add_shortcode( 'sc_research_librarian_ii'" in MOD
    assert 'credentials:\'same-origin\'' in JS and "'X-WP-Nonce'" in JS
    assert 'data-sc-research-librarian-v2' in MOD and 'data-sc-librarian-v2-output' in MOD

def test_page_places_project_aware_guidance_inside_existing_librarian_section():
    assert '[sc_research_librarian_ii title="Research Librarian II — Project-Aware Guidance"]' in PAGE
    assert '[sc_research_librarian_orchestrator]' in PAGE
    start=PAGE.index('id="research-librarian"'); ii=PAGE.index('[sc_research_librarian_ii',start); old=PAGE.index('[sc_research_librarian_orchestrator]',start)
    assert start < ii < old

def test_page_preserves_publications_graph_and_private_research_stack():
    for marker in ['id="publication-research-graph-section"','id="research-projects"','id="reading-notebooks"','id="evidence-matrix"','id="workspace-continuity"','id="open-learning-ii"']:
        assert marker in PAGE

def test_identity_health_is_version_aligned_without_new_private_store():
    assert "public const VERSION = '4.3.38'" in ROUTE and 'data-sc-library-account-continuity="v4.3.38"' in ROUTE
    assert "'research_librarian_guidance'" not in ROUTE and 'does not store a second copy' in ROUTE

def test_readme_and_release_docs_are_current_and_truthful():
    assert 'Stable tag: 4.3.38' in README and '[sc_research_librarian_ii]' in README and '/wp-json/sc-library/v1/research-librarian-v2/guidance' in README
    assert 'No automatic project/notebook/matrix write' in README or 'No automatic project/notebook/matrix write' in NOTES or 'does not automatically' in DOC
    assert 'Private project context is not forwarded to optional remote synthesis' in NOTES

def test_front_end_is_accessible_responsive_and_clear_about_privacy():
    assert 'aria-live="polite"' in MOD and ':focus-visible' in CSS and '@media(max-width:760px)' in CSS
    assert 'Private project context remains in this view.' in JS and 'No project, notebook, evidence, publication, or Workspace record is changed.' in MOD
