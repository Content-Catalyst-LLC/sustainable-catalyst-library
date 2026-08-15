from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text(); BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text(); MOD=(PLUGIN/'includes/class-sc-library-workspace-bidirectional-continuity.php').read_text(); HANDOFF=(PLUGIN/'includes/class-sc-library-cross-product-research-handoffs.php').read_text(); ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text(); PROJECTS=(PLUGIN/'includes/class-sc-library-unified-research-projects-source-bundles.php').read_text(); READING=(PLUGIN/'includes/class-sc-library-reading-notebook-annotations.php').read_text(); MATRIX=(PLUGIN/'includes/class-sc-library-evidence-matrix-claim-intelligence.php').read_text(); PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.33.html').read_text(); README=(PLUGIN/'readme.txt').read_text(); JS=(PLUGIN/'assets/js/sc-library-workspace-continuity-v4333.js').read_text(); CSS=(PLUGIN/'assets/css/sc-library-workspace-continuity-v4333.css').read_text(); DOC=(ROOT/'LIBRARY_WORKSPACE_BIDIRECTIONAL_CONTINUITY_v4.3.33.md').read_text(); NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.33.md').read_text(); STACK=(PLUGIN/'templates/field-spotlights.php').read_text()
def test_release_identity_and_extension_registration():
 assert 'Version: 4.3.33' in MAIN and "define('SC_LIBRARY_VERSION', '4.3.33');" in MAIN and 'class-sc-library-workspace-bidirectional-continuity.php' in BOOT and 'SC_Library_Workspace_Bidirectional_Continuity' in BOOT; m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==38
def test_workspace_is_first_class_existing_handoff_target_not_parallel_transport():
 assert "'workspace' => array(" in HANDOFF and "'schema'        => 'sc-platform-handoff/workspace/1.0'" in HANDOFF and "'library-continuity' =>" in HANDOFF and "case 'workspace':" in HANDOFF and "SC_Library_Cross_Product_Research_Handoffs::create_handoff" in MOD and "VERSION = '3.4.0'" in HANDOFF
def test_shared_account_and_canonical_store_boundaries_are_explicit():
 assert all(marker in MOD for marker in ["'separate_workspace_identity'=>false","'library_records_remain_canonical'=>true","'workspace_records_remain_canonical_in_workspace'=>true","'automatic_workspace_write'=>false","'automatic_library_write'=>false","'automatic_publication'=>false","'automatic_evidence_promotion'=>false"])
def test_outbound_contexts_are_project_anchored_and_cover_current_research_layers():
 assert all(x in MOD for x in ["'project'","'source_bundle'","'reading_notebook'","'evidence_matrix'",'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project','SC_Library_Reading_Notebook_Annotations::notebook_state','SC_Library_Evidence_Matrix_Claim_Intelligence::matrix_state'])
def test_project_reference_catalog_carries_personal_sources_documents_courses_and_saved_research():
 assert all(f"'{x}'" in PROJECTS for x in ['personal_library','saved_search','watchlist','research_queue','source_collection','research_document','course','pathway']) and 'reference_catalog_for_user' in MOD
def test_source_bundles_and_projects_remain_references_only():
 assert "VERSION = '4.3.30'" in PROJECTS and "'references_only'                => true" in PROJECTS and "'references_only'=>true" in MOD and "'copy_underlying_library_records'=>false" in MOD and "'copy_private_binary_files'=>false" in MOD
def test_reading_and_evidence_boundaries_remain_preserved():
 assert "VERSION = '4.3.31'" in READING and "'automatic_evidence_promotion'  => false" in READING and "VERSION = '4.3.32'" in MATRIX and "'diagnostics_are_not_conclusions'=>true" in MATRIX
def test_signed_expiring_delivery_and_fragment_launch_contract():
 assert "'transport'=>'signed-expiring-reference-handoff'" in MOD and "'issue_token'=>true" in MOD and "'token_days'=>7" in MOD and '#sc-library-continuity=' in MOD and 'delivery_url' in MOD and 'window.location.assign' in JS
def test_handoff_packet_is_checksummed_and_user_selected():
 assert "PACKET_SCHEMA = 'sc-library-workspace-reference-packet/1.0'" in MOD and "hash('sha256',wp_json_encode($c))" in MOD and "'outbound_requires_user_action'=>true" in MOD and "'inbound_requires_user_action'=>true" in MOD and 'Prepare & Open Workspace' in MOD
def test_workspace_to_library_reopen_is_authenticated_and_canonical():
 assert "REOPEN_SCHEMA = 'sc-library-workspace-reopen/1.0'" in MOD and "self::REST_ROUTE.'/resolve'" in MOD and "'permission_callback'=>array($this,'rest_signed_in')" in MOD and 'SC_Library_Canonical_Route_Identity::canonical_url()' in MOD and 'sc_library_scope' in MOD and 'sc_library_ref' in MOD
def test_workspace_return_status_reuses_existing_handoff_history_contract():
 assert '/handoffs/(?P<uuid>[a-f0-9-]+)/status' in HANDOFF and 'receive_return_event' in HANDOFF and "'workspace_returns'    => 'sc_workspace_handoff:_sc_handoff_result_url'" in ROUTE and "'workspace_handoffs'   => 'sc_workspace_handoff:_sc_handoff_created_by'" in ROUTE
def test_authenticated_rest_api_covers_state_create_and_resolve():
 assert "REST_ROUTE = '/workspace-continuity'" in MOD and "self::REST_ROUTE.'/handoffs'" in MOD and "self::REST_ROUTE.'/resolve'" in MOD and 'public function rest_state()' in MOD and 'public function rest_create_handoff' in MOD and 'public function rest_resolve' in MOD
def test_front_end_accessibility_and_mobile_contract():
 assert 'aria-live="polite"' in MOD and ':focus-visible' in CSS and 'min-height:44px' in CSS and '@media(max-width:700px)' in CSS and '@media(prefers-reduced-motion:reduce)' in CSS and "credentials:'same-origin'" in JS
def test_identity_health_version_alignment_and_continuity_history():
 assert "public const VERSION = '4.3.33'" in ROUTE and 'explicit Library ↔ Workspace handoff history remain attached to this account' in ROUTE and "wp_safe_redirect( $target, 301, 'Sustainable Catalyst Library v4.3.33' );" in ROUTE
def test_research_library_page_places_workspace_after_evidence_before_courses():
 assert '[sc_library_workspace_continuity title="Library ↔ Workspace Continuity"]' in PAGE and 'id="workspace-continuity"' in PAGE and PAGE.index('id="evidence-matrix"') < PAGE.index('id="workspace-continuity"') < PAGE.index('id="open-course-finder"') and PAGE.count('href="#workspace-continuity"')>=3
def test_readme_release_docs_and_publications_boundary_are_truthful():
 assert 'Stable tag: 4.3.33' in README and '[sc_library_workspace_continuity]' in README and '/wp-json/sc-library/v1/workspace-continuity' in README and 'no automatic cross-product write' in README.lower() and 'signed, expiring, references-only' in DOC and 'no automatic source copy' in NOTES.lower() and 'data-sc-field-stack="v4.3.22.4"' in STACK
