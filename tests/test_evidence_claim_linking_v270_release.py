from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
EVIDENCE = PLUGIN / "includes" / "class-sc-library-evidence-claim-linking.php"
MANAGER = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
CONNECTORS = PLUGIN / "includes" / "class-sc-library-scholarly-library-connectors.php"
HOLDINGS = PLUGIN / "includes" / "class-sc-library-connector-holdings-reliability.php"
OCR = PLUGIN / "includes" / "class-sc-library-document-ocr-processing.php"
JS = PLUGIN / "assets" / "js" / "sc-library-evidence-claims.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-evidence-claims.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files_exist():
    for path in (WRAPPER, EVIDENCE, MANAGER, CONNECTORS, HOLDINGS, OCR, JS, CSS):
        assert path.is_file(), path


def test_layer_loads_after_connectors_and_holdings():
    text = read(WRAPPER)
    for marker in (
        "class-sc-library-evidence-claim-linking.php",
        "new SC_Library_Evidence_Claim_Linking",
        "class-sc-library-connector-holdings-reliability.php",
    ):
        assert marker in text, marker
    assert text.index("class-sc-library-connector-holdings-reliability.php") < text.index("class-sc-library-evidence-claim-linking.php")


def test_version_and_schema_contracts():
    text = read(EVIDENCE)
    for marker in (
        "public const VERSION = '2.7.0'",
        "sc-library-evidence-note/1.0",
        "sc-library-research-claim/1.0",
        "sc-library-claim-evidence-link/1.0",
        "sc-library-evidence-packet/1.0",
    ):
        assert marker in text, marker
    assert "SC_LIBRARY_VERSION : '3.7.0'" in read(WRAPPER)


def test_private_admin_record_types():
    text = read(EVIDENCE)
    for marker in (
        "NOTE_POST_TYPE = 'sc_evidence_note'",
        "CLAIM_POST_TYPE = 'sc_research_claim'",
        "EVIDENCE_TYPE_TAXONOMY = 'sc_evidence_type'",
        "CLAIM_TYPE_TAXONOMY = 'sc_claim_type'",
        "'public'              => false",
        "'show_in_menu'        => 'sc-library'",
        "'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'author' )",
    ):
        assert marker in text, marker


def test_default_evidence_and_claim_types():
    text = read(EVIDENCE)
    for marker in (
        "'direct-quotation'",
        "'paraphrase'",
        "'data-point'",
        "'definition'",
        "'method'",
        "'observation'",
        "'counterevidence'",
        "'descriptive'",
        "'causal'",
        "'comparative'",
        "'predictive'",
        "'normative'",
        "'methodological'",
        "'legal'",
        "'interpretive'",
    ):
        assert marker in text, marker


def test_precise_locator_model():
    text = read(EVIDENCE)
    for marker in (
        "META_LOCATOR_TYPE",
        "META_LOCATOR_START",
        "META_LOCATOR_END",
        "META_LOCATOR_LABEL",
        "'page'",
        "'pages'",
        "'paragraph'",
        "'section'",
        "'chapter'",
        "'figure'",
        "'table'",
        "'timecode'",
        "'record'",
        "locator_display",
        "locator_for_citation",
    ):
        assert marker in text, marker


def test_quotation_context_analysis_and_capture_method():
    text = read(EVIDENCE)
    for marker in (
        "META_CONTEXT_BEFORE",
        "META_CONTEXT_AFTER",
        "META_ANALYSIS",
        "META_TRANSCRIPTION_METHOD",
        "'manual'",
        "'copy-paste'",
        "'ocr'",
        "'api-import'",
        "'observation'",
    ):
        assert marker in text, marker


def test_evidence_review_and_reverification():
    text = read(EVIDENCE)
    for marker in (
        "META_QUOTE_VERIFIED",
        "META_LOCATOR_VERIFIED",
        "META_REVIEW_STATUS",
        "META_CONFIDENCE",
        "META_CONTENT_HASH",
        "sc_evidence_reverified",
        "I rechecked the quotation and locator",
        "update_post_meta( $post_id, self::META_REVIEW_STATUS, 'review' )",
    ):
        assert marker in text, marker


def test_claim_review_scope_and_uncertainty():
    text = read(EVIDENCE)
    for marker in (
        "META_CLAIM_STATUS",
        "META_CLAIM_CONFIDENCE",
        "META_CLAIM_SCOPE",
        "META_CLAIM_ASSUMPTIONS",
        "META_CLAIM_LIMITATIONS",
        "META_CLAIM_COUNTERCLAIM",
        "META_CLAIM_REVIEW_NOTES",
        "sc_claim_reverified",
    ):
        assert marker in text, marker


def test_claim_evidence_relationships():
    text = read(EVIDENCE)
    for marker in (
        "'supports'",
        "'contradicts'",
        "'qualifies'",
        "'contextualizes'",
        "'illustrates'",
        "'unresolved'",
        "'strength'",
        "'claim_id'",
        "'note'",
        "sanitize_claim_links",
        "sync_claim_reverse_links",
        "rebuild_claim_evidence_index",
    ):
        assert marker in text, marker


def test_bidirectional_deletion_cleanup():
    text = read(EVIDENCE)
    for marker in (
        "before_delete_post",
        "deleted_post",
        "deleted_note_claim_ids",
        "deleted_claim_note_ids",
        "remove_project_relationships",
        "clear_deleted_relationship",
        "array_diff",
    ):
        assert marker in text, marker


def test_source_document_and_project_relationships():
    text = read(EVIDENCE)
    for marker in (
        "META_SOURCE_ID",
        "META_DOCUMENT_ID",
        "META_PROJECT_IDS",
        "META_CLAIM_PROJECT_IDS",
        "SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE",
        "SC_Library_Foundation_Pages::POST_TYPE",
        "SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE",
    ):
        assert marker in text, marker


def test_harvard_citation_ready_exports():
    text = read(EVIDENCE)
    for marker in (
        "SC_Library_Citation_Source_Manager::format_citation",
        "citation_ready_quote",
        "evidence_markdown",
        "claim_packet_markdown",
        "project_packet_markdown",
        "Quotation with in-text citation",
        "Copy Markdown",
        "Copy Evidence Packet",
    ):
        assert marker in text, marker


def test_claim_and_project_packets():
    text = read(EVIDENCE)
    for marker in (
        "claim_packet",
        "project_packet",
        "relation_totals",
        "render_claim_packet_html",
        "Research evidence packet",
        "Project Evidence Not Yet Linked to a Claim",
    ):
        assert marker in text, marker


def test_public_private_boundaries():
    text = read(EVIDENCE)
    for marker in (
        "evidence_is_public",
        "claim_is_public",
        "project_is_public",
        "'public' !== get_post_meta",
        "'publish' !== get_post_status",
        "'retracted'",
        "'retired'",
    ):
        assert marker in text, marker


def test_public_source_page_evidence_integration():
    manager = read(MANAGER)
    evidence = read(EVIDENCE)
    assert "SC_Library_Evidence_Claim_Linking::render_public_source_evidence" in manager
    assert "render_public_source_evidence" in evidence
    assert "Evidence notes from this source" in evidence


def test_shortcodes():
    text = read(EVIDENCE)
    for marker in (
        "add_shortcode( 'sc_evidence_note'",
        "add_shortcode( 'sc_claim_evidence'",
        "add_shortcode( 'sc_project_evidence'",
        "shortcode_evidence_note",
        "shortcode_claim_evidence",
        "shortcode_project_evidence",
    ):
        assert marker in text, marker


def test_workspace_and_cross_record_meta_boxes():
    text = read(EVIDENCE)
    for marker in (
        "Evidence and Claims",
        "Quotations, Evidence Notes, and Claim Linking",
        "Source, Document, and Locator",
        "Claim and Project Links",
        "Claim Review and Scope",
        "Linked Evidence",
        "Quotations and Evidence",
        "Claims and Evidence Packet",
    ):
        assert marker in text, marker


def test_dynamic_claim_link_editor_and_media_attachment():
    text = read(JS)
    for marker in (
        "data-sc-add-evidence-link",
        "data-sc-remove-evidence-link",
        "renumberLinks",
        "sc_evidence_claim_links",
        "wp.media",
        "data-sc-select-evidence-attachment",
        "data-sc-remove-evidence-attachment",
    ):
        assert marker in text, marker


def test_copy_and_export_client():
    text = read(JS)
    for marker in (
        "navigator.clipboard",
        "document.execCommand('copy')",
        "data-sc-copy-evidence-value",
        "data-sc-copy-value",
    ):
        assert marker in text, marker


def test_rest_record_endpoints():
    text = read(EVIDENCE)
    for marker in (
        "'/evidence-notes'",
        "'/evidence-notes/(?P<id>\\d+)'",
        "'/evidence-notes/(?P<id>\\d+)/links'",
        "'/claims'",
        "'/claims/(?P<id>\\d+)'",
        "'/claims/(?P<id>\\d+)/evidence'",
        "'/projects/(?P<id>\\d+)/evidence'",
        "'/evidence/export'",
    ):
        assert marker in text, marker


def test_rest_permissions_and_public_read_rules():
    text = read(EVIDENCE)
    for marker in (
        "rest_can_read_evidence",
        "rest_can_edit_evidence",
        "rest_can_read_claim",
        "rest_can_edit_claim",
        "rest_can_read_project_packet",
        "current_user_can( 'edit_post'",
        "current_user_can( 'edit_posts' )",
    ):
        assert marker in text, marker


def test_rest_filters_use_serialized_integer_relationships():
    text = read(EVIDENCE)
    assert "'i:' . $project_id . ';'" in text
    assert "'\"claim_id\";i:' . $claim_id . ';'" in text


def test_list_columns_and_review_notices():
    text = read(EVIDENCE)
    for marker in (
        "note_columns",
        "claim_columns",
        "Evidence text, locators, and claim relationships",
        "A claim is not treated as verified merely because evidence is linked",
    ):
        assert marker in text, marker


def test_responsive_spartan_styles():
    text = read(CSS)
    for marker in (
        ".sc-evidence-workspace",
        ".sc-evidence-link-row",
        ".sc-evidence-card-grid",
        ".sc-evidence-card",
        ".sc-claim-packet",
        ".sc-source-evidence-section",
        "@media (max-width: 760px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_prior_layers_are_retained():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-document-ocr-processing.php",
        "class-sc-library-citation-source-manager.php",
        "class-sc-library-scholarly-library-connectors.php",
        "class-sc-library-connector-holdings-reliability.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '2.6.1'" in read(CONNECTORS)
    assert "public const VERSION = '2.6.1'" in read(HOLDINGS)


def main():
    tests = [
        test_required_files_exist,
        test_layer_loads_after_connectors_and_holdings,
        test_version_and_schema_contracts,
        test_private_admin_record_types,
        test_default_evidence_and_claim_types,
        test_precise_locator_model,
        test_quotation_context_analysis_and_capture_method,
        test_evidence_review_and_reverification,
        test_claim_review_scope_and_uncertainty,
        test_claim_evidence_relationships,
        test_bidirectional_deletion_cleanup,
        test_source_document_and_project_relationships,
        test_harvard_citation_ready_exports,
        test_claim_and_project_packets,
        test_public_private_boundaries,
        test_public_source_page_evidence_integration,
        test_shortcodes,
        test_workspace_and_cross_record_meta_boxes,
        test_dynamic_claim_link_editor_and_media_attachment,
        test_copy_and_export_client,
        test_rest_record_endpoints,
        test_rest_permissions_and_public_read_rules,
        test_rest_filters_use_serialized_integer_relationships,
        test_list_columns_and_review_notices,
        test_responsive_spartan_styles,
        test_prior_layers_are_retained,
    ]
    for test in tests:
        test()
    print(f"Quotations, Evidence Notes, and Claim Linking checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
