from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
MOD=(PLUGIN/'includes/class-sc-library-evidence-matrix-claim-intelligence.php').read_text()
ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text()
READING=(PLUGIN/'includes/class-sc-library-reading-notebook-annotations.php').read_text()
PROJECTS=(PLUGIN/'includes/class-sc-library-unified-research-projects-source-bundles.php').read_text()
EVIDENCE=(PLUGIN/'includes/class-sc-library-evidence-claim-linking.php').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.32.html').read_text()
README=(PLUGIN/'readme.txt').read_text()
JS=(PLUGIN/'assets/js/sc-library-evidence-matrix-v4332.js').read_text()
CSS=(PLUGIN/'assets/css/sc-library-evidence-matrix-v4332.css').read_text()
DOC=(ROOT/'EVIDENCE_MATRIX_CLAIM_INTELLIGENCE_v4.3.32.md').read_text()
NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.32.md').read_text()
STACK=(PLUGIN/'templates/field-spotlights.php').read_text()


def test_release_identity_and_extension_registration():
    assert 'Version: 4.3.32' in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.32');" in MAIN
    assert 'class-sc-library-evidence-matrix-claim-intelligence.php' in BOOT
    assert 'SC_Library_Evidence_Matrix_Claim_Intelligence' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT)
    assert m and int(m.group(1))==37


def test_private_account_owned_matrix_record_type():
    assert "POST_TYPE = 'sc_evidence_matrix'" in MOD
    assert "'public'=>false" in MOD
    assert "'show_in_rest'=>false" in MOD
    assert "'post_author'=>$user_id" in MOD
    assert 'user_owns_matrix' in MOD
    assert 'MAX_MATRICES_PER_USER = 40' in MOD


def test_stable_matrix_claim_and_link_schemas_and_urns():
    for marker in [
        "SCHEMA = 'sc-library-evidence-matrix/1.0'",
        "CLAIM_SCHEMA = 'sc-library-matrix-claim/1.0'",
        "LINK_SCHEMA = 'sc-library-matrix-evidence-link/1.0'",
        "DIAGNOSTIC_SCHEMA = 'sc-library-claim-intelligence-diagnostics/1.0'",
        "'urn'=>'urn:sc:evidence-matrix:'",
        "'urn'=>$id?'urn:sc:matrix-claim:'",
        "'urn'=>$id?'urn:sc:matrix-evidence-link:'",
    ]:
        assert marker in MOD


def test_explicit_relationship_taxonomy_covers_support_counterevidence_and_context():
    for relation in ['supports','qualifies','contradicts','contextualizes','unresolved']:
        assert f"'{relation}'" in MOD
    assert 'Why this evidence bears on the claim' in MOD
    assert 'Wording / transcription checked' in MOD
    assert 'Locator checked' in MOD


def test_claim_fields_keep_interpretive_assumptions_and_confidence_explicit():
    for field in ['statement','status','confidence','scope','assumptions','limitations','counterclaim','tags','position']:
        assert f"'{field}'" in MOD
    assert 'user declared' in MOD.lower()
    assert 'Supported (user assessment)' in MOD
    assert 'Mixed (user assessment)' in MOD


def test_reading_notes_and_annotations_require_explicit_linking():
    assert "'reading_note'=>'Reading note / excerpt'" in MOD
    assert "'source_annotation'=>'Source annotation'" in MOD
    assert 'SC_Library_Reading_Notebook_Annotations::notebook_state' in MOD
    assert 'find_reading_record' in MOD
    assert "'explicit_evidence_promotion_only'=>true" in MOD
    assert "'automatic_evidence_promotion'=>false" in MOD
    assert "'automatic_evidence_promotion'  => false" in READING


def test_v270_canonical_evidence_system_is_preserved_and_resolvable():
    assert "VERSION = '2.7.0'" in EVIDENCE
    assert "NOTE_POST_TYPE = 'sc_evidence_note'" in EVIDENCE
    assert "CLAIM_POST_TYPE = 'sc_research_claim'" in EVIDENCE
    assert "'canonical_v270_evidence_preserved'=>true" in MOD
    assert 'SC_Library_Evidence_Claim_Linking::get_evidence_data' in MOD
    assert "'canonical_evidence'=>'Canonical v2.7 Evidence Note'" in MOD


def test_v4330_project_and_source_bundle_context_is_reused():
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' in MOD
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::bundles_for_project' in MOD
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::reference_catalog_for_user' in MOD
    assert "VERSION = '4.3.30'" in PROJECTS
    assert "'references_only'                => true" in PROJECTS


def test_diagnostics_are_deterministic_descriptive_and_non_decisional():
    for gap in ['no_evidence','no_supporting_evidence','no_counterevidence_recorded','single_source_dependency','unresolved_references','unchecked_quote_or_locator']:
        assert gap in MOD
    for pattern in ['mixed-record','contradiction-heavy','support-only','context-or-unresolved-only','no-evidence']:
        assert pattern in MOD
    assert "'interpretation'=>'descriptive-only'" in MOD
    assert "'changes_claim_status'=>false" in MOD
    assert "'changes_confidence'=>false" in MOD
    assert "'diagnostics_are_not_conclusions'=>true" in MOD


def test_no_automatic_claim_truth_confidence_publication_or_workspace_write():
    markers=[
        "'automatic_claim_generation'=>false",
        "'automatic_claim_status_change'=>false",
        "'automatic_confidence_scoring'=>false",
        "'automatic_publication'=>false",
        "'automatic_workspace_write'=>false",
        "'copy_underlying_source_records'=>false",
        "'copy_private_binary_files'=>false",
    ]
    for marker in markers: assert marker in MOD


def test_authenticated_rest_api_covers_matrix_manifest_claims_and_links():
    assert "REST_ROUTE = '/evidence-matrices'" in MOD
    assert "self::REST_ROUTE.'/(?P<id>\\\\d+)/manifest'" in MOD
    assert "self::REST_ROUTE.'/(?P<id>\\\\d+)/claims'" in MOD
    assert "self::REST_ROUTE.'/(?P<id>\\\\d+)/links'" in MOD
    assert 'public function rest_signed_in(){return is_user_logged_in();}' in MOD
    assert 'rest_owns_matrix' in MOD


def test_matrix_manifest_is_checksummed_and_excludes_binary_copying():
    assert 'matrix_manifest' in MOD
    assert "hash('sha256',wp_json_encode($manifest))" in MOD
    assert "'underlying_source_records_referenced_not_copied'=>true" in MOD
    assert "'private_binaries_excluded'=>true" in MOD


def test_front_end_ajax_actions_assets_and_accessibility_contract():
    for action in ['sc_library_v4332_create_matrix','sc_library_v4332_update_matrix','sc_library_v4332_delete_matrix','sc_library_v4332_add_claim','sc_library_v4332_delete_claim','sc_library_v4332_add_link','sc_library_v4332_delete_link']:
        assert action in MOD and action in JS
    assert 'aria-live="polite"' in MOD
    assert ':focus-visible' in CSS
    assert 'min-height:44px' in CSS
    assert '@media(max-width:620px)' in CSS
    assert '@media(prefers-reduced-motion:reduce)' in CSS
    assert 'window.confirm' in JS


def test_identity_health_version_alignment_and_new_private_contracts():
    assert "public const VERSION = '4.3.32'" in ROUTE
    assert "'evidence_matrices'   => 'sc_evidence_matrix:post_author'" in ROUTE
    assert "'matrix_claims'       => '_sc_evidence_matrix_claims_v4332'" in ROUTE
    assert "'matrix_links'        => '_sc_evidence_matrix_links_v4332'" in ROUTE
    assert 'evidence matrices, claims, and evidence links remain attached to this account' in ROUTE


def test_research_library_page_places_matrix_after_reading_before_courses():
    assert '[sc_evidence_matrix_workspace title="Evidence Matrix &amp; Claim Intelligence"]' in PAGE
    assert 'id="evidence-matrix"' in PAGE
    assert PAGE.index('id="reading-notebooks"') < PAGE.index('id="evidence-matrix"') < PAGE.index('id="open-course-finder"')
    assert PAGE.count('href="#evidence-matrix"') >= 3


def test_readme_release_docs_and_prior_boundaries_are_truthful():
    assert 'Stable tag: 4.3.32' in README
    assert '[sc_evidence_matrix_workspace]' in README
    assert '/wp-json/sc-library/v1/evidence-matrices' in README
    assert 'do not determine truth' in DOC
    assert 'No automatic publication or Workspace write occurs' in NOTES
    assert "VERSION = '4.3.31'" in READING
    assert "CANONICAL_SLUG = 'knowledge-libraries'" in ROUTE
    assert 'data-sc-field-stack="v4.3.22.4"' in STACK
