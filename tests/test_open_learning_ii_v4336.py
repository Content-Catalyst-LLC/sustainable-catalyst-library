from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text(); BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text(); MOD=(PLUGIN/'includes/class-sc-library-open-learning-ii.php').read_text(); COURSE=(PLUGIN/'includes/class-sc-library-open-course-finder.php').read_text(); ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text(); ACCESS=(PLUGIN/'includes/class-sc-library-access-intelligence-ii.php').read_text(); PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.36.html').read_text(); README=(PLUGIN/'readme.txt').read_text(); JS=(PLUGIN/'assets/js/sc-library-open-learning-v2-v4336.js').read_text(); CSS=(PLUGIN/'assets/css/sc-library-open-learning-v2-v4336.css').read_text(); DOC=(ROOT/'OPEN_LEARNING_II_v4.3.36.md').read_text(); NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.36.md').read_text(); STACK=(PLUGIN/'templates/field-spotlights.php').read_text()

def test_release_identity_and_extension_registration():
 assert 'Version: 4.3.36' in MAIN and "define('SC_LIBRARY_VERSION', '4.3.36');" in MAIN and 'class-sc-library-open-learning-ii.php' in BOOT and 'SC_Library_Open_Learning_II' in BOOT; m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==41

def test_existing_open_course_finder_and_course_plan_are_reused_not_replaced():
 assert 'SC_Library_Open_Course_Finder::launch_catalog()' in MOD and 'SC_Library_Open_Course_Finder::provider_registry()' in MOD and 'SC_Library_Open_Course_Finder::pathway_registry()' in MOD and "COURSE_PLAN_META='sc_library_course_plan_v4321'" in MOD
 assert "get_user_meta($uid,self::COURSE_PLAN_META,true)" in MOD and 'update_user_meta($uid,self::COURSE_PLAN_META' not in MOD
 assert 'public static function launch_catalog()' in COURSE and 'public static function provider_registry()' in COURSE and 'public static function pathway_registry()' in COURSE

def test_learning_routes_use_separate_account_owned_storage_and_stable_identity():
 assert "USER_META='sc_library_learning_routes_v4336'" in MOD and "'route_id'=>'learning-route-'.$uuid" in MOD and "'route_urn'=>'urn:sc:learning-route:'.$uuid" in MOD and 'MAX_ROUTES=50' in MOD and 'MAX_COURSES_PER_ROUTE=8' in MOD

def test_route_manifest_is_references_only_and_checksummed():
 for marker in ["MANIFEST_SCHEMA='sc-library-learning-route-manifest/1.0'", "'references_only'=>true", "'manifest_sha256'", "hash('sha256'", "'catalog_verified_on'=>self::catalog_verified_on()"]: assert marker in MOD

def test_planner_is_deterministic_and_not_an_inferred_prerequisite_graph():
 for marker in ["'sequencing_basis'=>'declared-level-label-then-match-score'", 'selected-knowledge-pathway', 'course-metadata-match:', "stage_for_level", "usort($ranked", "usort($selected"]: assert marker in MOD
 assert 'random' not in MOD.lower() and 'embedding' not in MOD.lower() and 'inferred prerequisite graph' in MOD

def test_missing_prerequisite_and_duration_fields_remain_unknown():
 assert "'missing_prerequisites_mean'=>'not-recorded-not-none'" in MOD and "'missing_duration_mean'=>'not-recorded-not-zero'" in MOD and "'prerequisites_known'" in MOD and "'duration_known'" in MOD
 assert 'An empty prerequisite means **not recorded**' in DOC and 'An empty duration means **not recorded**' in DOC

def test_provider_terms_and_access_labels_do_not_claim_current_enrollment():
 for marker in ["'course_page_is_current_authority'=>true", "'provider_page_is_current_authority'=>true", "'access_label_is_not_enrollment_guarantee'=>true", "'certificate_label_is_not_credential_award'=>true", "'saved_route_is_not_provider_enrollment'=>true"]: assert marker in MOD
 assert 'provider/course page remains authoritative' in NOTES.lower()

def test_no_automatic_enrollment_purchase_completion_certificate_or_workspace_write():
 for marker in ["'automatic_purchase'=>false", "'automatic_enrollment'=>false", "'automatic_completion'=>false", "'automatic_certificate_claim'=>false", "'automatic_workspace_write'=>false", "'automatic_publication'=>false", "'external_provider_credentials_stored'=>false"]: assert marker in MOD
 assert 'type="password"' not in MOD.lower() and "$_post['password']" not in MOD.lower()

def test_saved_route_context_is_permission_checked_against_owned_projects_bundles_and_notebooks():
 assert 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' in MOD and 'SC_Library_Unified_Research_Projects_Source_Bundles::bundles_for_project' in MOD and 'SC_Library_Reading_Notebook_Annotations::user_owns_notebook' in MOD and "new WP_Error('sc_learning_context'" in MOD

def test_public_planner_and_authenticated_saved_route_rest_contracts_exist():
 assert "REST_ROUTE='/open-learning-v2'" in MOD and "'permission_callback'=>'__return_true'" in MOD and "self::REST_ROUTE.'/routes'" in MOD and 'rest_signed_in' in MOD and 'WP_REST_Server::CREATABLE' in MOD and 'WP_REST_Server::DELETABLE' in MOD

def test_front_end_is_accessible_mobile_same_origin_and_account_aware():
 assert 'aria-live="polite"' in MOD and ':focus-visible' in CSS and 'min-height:44px' in CSS and '@media(max-width:700px)' in CSS and '@media(prefers-reduced-motion:reduce)' in CSS and "credentials:'same-origin'" in JS and 'X-WP-Nonce' in JS and 'Sign in with your Sustainable Catalyst / Workspace account' in MOD

def test_page_embeds_open_learning_inside_existing_open_course_section_without_bloat():
 assert '[sc_open_course_finder title="Find Free and Open Courses" show_providers="true"]' in PAGE and '[sc_open_learning_ii title="Open Learning II — Build a Learning Route"]' in PAGE and 'id="open-learning-ii"' in PAGE
 assert PAGE.index('id="metadata-quality"') < PAGE.index('id="open-course-finder"') < PAGE.index('id="open-learning-ii"') < PAGE.index('id="research-front-door"')
 assert PAGE.count('href="#access-intelligence-ii">Plan Access</a>')==1

def test_identity_health_is_version_aligned_and_tracks_private_learning_routes():
 assert "public const VERSION = '4.3.36'" in ROUTE and "'learning_routes'    => 'sc_library_learning_routes_v4336'" in ROUTE and 'data-sc-library-account-continuity="v4.3.36"' in ROUTE and 'Open Learning II can save private learning-route manifests without enrolling the user' in ROUTE

def test_access_intelligence_metadata_workspace_evidence_and_publications_boundaries_remain_preserved():
 assert "VERSION = '4.3.35'" in ACCESS and '[sc_access_intelligence_ii title="Access Intelligence II"]' in PAGE and '[sc_metadata_quality_center title="Metadata Quality &amp; Entity Resolution"]' in PAGE and '[sc_library_workspace_continuity title="Library ↔ Workspace Continuity"]' in PAGE and '[sc_evidence_matrix_workspace title="Evidence Matrix &amp; Claim Intelligence"]' in PAGE and 'data-sc-field-stack="v4.3.22.4"' in STACK

def test_readme_release_docs_truthfully_describe_open_learning_boundaries():
 assert 'Stable tag: 4.3.36' in README and '[sc_open_learning_ii]' in README and '/wp-json/sc-library/v1/open-learning-v2' in README and 'Missing prerequisite or duration metadata remains unknown' in README and 'does not enroll' in README
 assert 'not third-party enrollment' in NOTES and 'does not create a second course record database' in DOC

def test_catalog_verification_date_is_exposed_not_presented_as_live_provider_state():
 assert 'SC_Library_Open_Course_Finder::VERIFIED_ON' in MOD and "'catalog_verified_on'=>self::catalog_verified_on()" in MOD and 'catalog reviewed' in JS and 'Provider/course pages remain authoritative' in DOC
