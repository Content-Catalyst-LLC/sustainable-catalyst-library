from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text(); BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text(); MOD=(PLUGIN/'includes/class-sc-library-metadata-quality-entity-resolution.php').read_text(); ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text(); SOURCE=(PLUGIN/'includes/class-sc-library-citation-source-manager.php').read_text(); REL=(PLUGIN/'includes/class-sc-library-citation-source-reliability.php').read_text(); ENTITY=(PLUGIN/'includes/class-sc-library-topics-concepts-relationships.php').read_text(); PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.34.html').read_text(); README=(PLUGIN/'readme.txt').read_text(); JS=(PLUGIN/'assets/js/sc-library-metadata-quality-v4334.js').read_text(); CSS=(PLUGIN/'assets/css/sc-library-metadata-quality-v4334.css').read_text(); DOC=(ROOT/'METADATA_QUALITY_ENTITY_RESOLUTION_v4.3.34.md').read_text(); NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.34.md').read_text(); STACK=(PLUGIN/'templates/field-spotlights.php').read_text(); CONT=(PLUGIN/'includes/class-sc-library-workspace-bidirectional-continuity.php').read_text()

def test_release_identity_and_extension_registration():
 assert 'Version: 4.3.34' in MAIN and "define('SC_LIBRARY_VERSION', '4.3.34');" in MAIN and 'class-sc-library-metadata-quality-entity-resolution.php' in BOOT and 'SC_Library_Metadata_Quality_Entity_Resolution' in BOOT; m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==39
def test_reuses_existing_source_normalization_and_canonical_source_contract():
 assert 'META_NORMALIZED_DOI' in SOURCE and 'META_NORMALIZED_ISBN' in SOURCE and 'META_NORMALIZED_URL' in SOURCE and 'META_DUPLICATES' in SOURCE and 'META_PROVENANCE' in SOURCE and 'META_CANONICAL_ID' in REL and "'citation_source_normalization_reused'=>true" in MOD
def test_reuses_v320_named_entity_authority_instead_of_new_entity_store():
 assert "ENTITY_POST_TYPE = 'sc_named_entity'" in ENTITY and 'META_ENTITY_ALIASES' in ENTITY and 'META_ENTITY_URI' in ENTITY and 'META_ENTITY_VOCABULARY_ID' in ENTITY and "'v320_named_entity_authority_reused'=>true" in MOD and "POST_TYPE = 'sc_metadata_review'" in MOD
def test_quality_reports_are_diagnostic_and_non_mutating():
 assert "REPORT_SCHEMA = 'sc-library-metadata-quality-report/1.0'" in MOD and 'quality_score_is_diagnostic' in MOD and "'quality_scores_are_diagnostics_not_truth'=>true" in MOD and "'automatic_metadata_overwrite'=>false" in MOD
def test_source_quality_checks_core_metadata_and_normalized_identifiers():
 for marker in ["'title'=>array('ok'=>", "'creator'=>array('ok'=>", "'date_or_year'=>array('ok'=>", "'source_type'=>array('ok'=>", "'resolvable_identifier'=>array('ok'=>", "'provenance'=>array('ok'=>", "normalize_doi", "normalize_isbn", "normalize_url"]: assert marker in MOD
def test_entity_candidates_are_deterministic_exact_or_alias_matches_only():
 for marker in ['exact-authority-uri','exact-normalized-label','label-alias-match','alias-label-match','shared-normalized-alias','proposal_only']: assert marker in MOD
 assert 'levenshtein' not in MOD.lower() and 'embedding' not in MOD.lower()
def test_entity_resolution_requires_editor_and_explicit_accept_or_reject():
 assert "user_can($user_id,'edit_posts')" in MOD and "array('accept','reject')" in MOD and 'explicit_reviewer_acceptance_required' in MOD
def test_accepted_resolution_is_non_destructive_and_preserves_before_state():
 assert 'candidate_before' in MOD and 'canonical_before' in MOD and "META_ENTITY_CANONICAL" in MOD and "META_ENTITY_HISTORY" in MOD and "'record_deleted'=>false" in MOD and "'assignments_rewritten'=>false" in MOD and "'automatic_record_deletion'=>false" in MOD and "'automatic_assignment_rewrite'=>false" in MOD
def test_resolution_adds_aliases_but_does_not_copy_descriptions_or_delete_entity():
 assert 'META_ENTITY_ALIASES' in MOD and "$before_candidate['title']" in MOD and 'wp_delete_post' not in MOD and 'post_content' not in MOD
def test_resolution_chain_is_bounded_and_cycles_are_rejected():
 assert 'for($i=0;$i<8&&$current;$i++)' in MOD and "sc_metadata_cycle" in MOD
def test_rest_api_has_public_contract_and_editor_only_record_review_routes():
 assert "REST_ROUTE = '/metadata-quality'" in MOD and "'permission_callback'=>'__return_true'" in MOD and "'/sources/(?P<id>" in MOD and "'/entities/(?P<id>" in MOD and "/resolve'" in MOD and "/reviews'" in MOD and 'rest_can_review' in MOD
def test_front_end_is_accessible_mobile_and_truthful_about_editor_boundary():
 assert '[sc_metadata_quality_center' in PAGE and 'aria-live="polite"' in MOD and ':focus-visible' in CSS and 'min-height:44px' in CSS and '@media(max-width:700px)' in CSS and '@media(prefers-reduced-motion:reduce)' in CSS and "credentials:'same-origin'" in JS and 'Candidates are proposals only' in JS and 'data-sc-entity-decision' in JS and 'window.confirm' in JS
def test_identity_health_version_alignment_and_private_review_history():
 assert "public const VERSION = '4.3.34'" in ROUTE and "'metadata_reviews'     => 'sc_metadata_review:post_author'" in ROUTE and 'private metadata-review history remain attached to this account' in ROUTE and 'data-sc-library-account-continuity="v4.3.34"' in ROUTE
def test_page_places_metadata_after_workspace_continuity_before_courses():
 assert 'id="metadata-quality"' in PAGE and PAGE.index('id="workspace-continuity"') < PAGE.index('id="metadata-quality"') < PAGE.index('id="open-course-finder"') and PAGE.count('href="#metadata-quality"')>=3
def test_workspace_evidence_projects_and_publications_boundaries_remain_preserved():
 assert "VERSION = '4.3.33'" in CONT and '[sc_library_workspace_continuity title="Library ↔ Workspace Continuity"]' in PAGE and '[sc_evidence_matrix_workspace title="Evidence Matrix &amp; Claim Intelligence"]' in PAGE and '[sc_unified_research_projects title="Research Projects & Source Bundles"]' in PAGE and 'data-sc-field-stack="v4.3.22.4"' in STACK
def test_readme_release_docs_truthfully_describe_no_silent_merge_boundary():
 assert 'Stable tag: 4.3.34' in README and '[sc_metadata_quality_center]' in README and '/wp-json/sc-library/v1/metadata-quality' in README and 'no automatic merge' in README.lower() and 'non-destructive entity resolution' in DOC.lower() and 'does not delete duplicate entities' in NOTES.lower()
