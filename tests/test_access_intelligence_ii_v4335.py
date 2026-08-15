from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text(); BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text(); MOD=(PLUGIN/'includes/class-sc-library-access-intelligence-ii.php').read_text(); OLD=(PLUGIN/'includes/class-sc-library-research-librarian-access-intelligence.php').read_text(); ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text(); PUBLIC=(PLUGIN/'includes/class-sc-library-public-library-network.php').read_text(); INST=(PLUGIN/'includes/class-sc-library-institutional-connector-expansion.php').read_text(); PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.35.html').read_text(); README=(PLUGIN/'readme.txt').read_text(); JS=(PLUGIN/'assets/js/sc-library-access-intelligence-ii-v4335.js').read_text(); CSS=(PLUGIN/'assets/css/sc-library-access-intelligence-ii-v4335.css').read_text(); DOC=(ROOT/'ACCESS_INTELLIGENCE_II_v4.3.35.md').read_text(); NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.35.md').read_text(); STACK=(PLUGIN/'templates/field-spotlights.php').read_text(); META=(PLUGIN/'includes/class-sc-library-metadata-quality-entity-resolution.php').read_text()

def test_release_identity_and_extension_registration():
 assert 'Version: 4.3.35' in MAIN and "define('SC_LIBRARY_VERSION', '4.3.35');" in MAIN and 'class-sc-library-access-intelligence-ii.php' in BOOT and 'SC_Library_Access_Intelligence_II' in BOOT; m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==40

def test_v4324_classifier_is_preserved_as_underlying_authority():
 assert "public const VERSION = '4.3.24';" in OLD and 'SC_Library_Research_Librarian_Access_Intelligence::evaluate_source' in MOD and 'SC_Library_Research_Librarian_Access_Intelligence::evaluate_normalized_result' in MOD and "base_access_schema" in MOD

def test_ranked_paths_are_deterministic_and_explainable():
 for marker in ['rank_paths(', "'score' => 0", "'rank_reasons'", 'direct-public-route', 'actionable-url', 'stale-route-penalty', 'connected-library-membership', 'search-does-not-confirm-holding']:
  assert marker in MOD
 assert 'random' not in MOD.lower() and 'embedding' not in MOD.lower()

def test_route_confidence_describes_evidence_not_user_entitlement():
 for marker in ['direct-route-identified','provider-route-identified','connected-library-search-path','discovery-fallback','stale-route','unconfirmed']:
  assert marker in MOD
 assert 'Confidence describes the route evidence, not whether the user is entitled' in DOC

def test_availability_holdings_membership_and_entitlement_are_separate_boundaries():
 for marker in ["'availability_is_not_entitlement' => true", "'catalog_search_is_not_a_holding' => true", "'holding_is_not_user_eligibility' => true", "'connected_library_is_user_declared_relationship' => true", "'automatic_access_claim' => false", "'automatic_subscription_claim' => false"]:
  assert marker in MOD

def test_my_libraries_reused_without_credentials_or_new_membership_store():
 assert "MY_LIBRARIES_META = 'sc_library_my_libraries_v4319'" in MOD and "get_user_meta( get_current_user_id(), self::MY_LIBRARIES_META" in MOD and 'external_library_credentials_stored' in MOD and "type=\"password\"" not in MOD.lower() and "$_post['password']" not in MOD.lower() and "$_post['pin']" not in MOD.lower()

def test_public_and_institutional_registry_contracts_are_reused():
 assert 'SC_Library_Public_Library_Network::registry()' in MOD and 'SC_Library_Public_Library_Network::resolve_search_url' in MOD and 'SC_Library_Institutional_Connector_Expansion::registry()' in MOD and 'SC_Library_Institutional_Connector_Expansion::resolve_search_url' in MOD
 assert 'public static function registry()' in PUBLIC and 'public static function registry()' in INST

def test_worldcat_and_library_of_congress_are_fallbacks_not_holdings_claims():
 assert "resolve_search_url( 'worldcat'" in MOD and "resolve_search_url( 'loc'" in MOD and 'global-holdings-search' in MOD and 'public-catalog-search' in MOD and 'search-does-not-confirm-holding' in MOD

def test_connected_library_relationship_improves_rank_but_does_not_confirm_access():
 assert "if ( 'member' === $relation )" in MOD and 'connected-library-membership' in MOD and "elseif ( 'research' === $relation )" in MOD and 'connected-research-library' in MOD and 'holding_is_not_user_eligibility' in MOD

def test_stale_routes_are_penalized_and_visible():
 assert '$score -= 22' in MOD and 'stale-route-penalty' in MOD and "return 'stale-route'" in MOD and 'Has the provider or holding changed since the last check?' in MOD

def test_rest_api_supports_public_planning_and_permissioned_source_refresh():
 assert "REST_ROUTE = '/access-intelligence-v2'" in MOD and "'permission_callback' => '__return_true'" in MOD and "'/source/(?P<id>\\d+)'" in MOD and 'rest_can_read_source' in MOD and 'rest_can_refresh_source' in MOD and "array( 'refresh' => true )" in MOD

def test_front_end_is_accessible_mobile_and_same_origin():
 assert '[sc_access_intelligence_ii title="Access Intelligence II"]' in PAGE and 'aria-live="polite"' in MOD and ':focus-visible' in CSS and 'min-height:44px' in CSS and '@media(max-width:700px)' in CSS and '@media(prefers-reduced-motion:reduce)' in CSS and "credentials:'same-origin'" in JS

def test_page_keeps_access_intelligence_ii_inside_research_access_without_bloat():
 assert 'id="access-intelligence-ii"' in PAGE and PAGE.index('id="public-library-network"') < PAGE.index('id="access-intelligence-ii"') < PAGE.index('id="personal-library"') and PAGE.count('href="#access-intelligence-ii"')>=3 and 'Know the Best Legitimate Path—and Its Limits' in PAGE

def test_identity_health_version_alignment_and_connected_library_truthfulness():
 assert "public const VERSION = '4.3.35'" in ROUTE and 'data-sc-library-account-continuity="v4.3.35"' in ROUTE and 'Connected My Libraries relationships can inform Access Intelligence II pathway ranking without storing external-library credentials' in ROUTE

def test_metadata_workspace_evidence_and_publications_boundaries_remain_preserved():
 assert "VERSION = '4.3.34'" in META and '[sc_metadata_quality_center title="Metadata Quality &amp; Entity Resolution"]' in PAGE and '[sc_library_workspace_continuity title="Library ↔ Workspace Continuity"]' in PAGE and '[sc_evidence_matrix_workspace title="Evidence Matrix &amp; Claim Intelligence"]' in PAGE and 'data-sc-field-stack="v4.3.22.4"' in STACK

def test_readme_release_docs_truthfully_describe_access_boundaries():
 assert 'Stable tag: 4.3.35' in README and '[sc_access_intelligence_ii]' in README and '/wp-json/sc-library/v1/access-intelligence-v2' in README and 'a catalog search is not a holding' in README.lower() and 'provider and library sites remain authoritative' in NOTES.lower() and 'availability is not entitlement' in DOC.lower()
