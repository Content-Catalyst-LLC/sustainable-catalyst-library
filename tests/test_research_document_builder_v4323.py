from pathlib import Path
import json
import re
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
CLASS = (PLUGIN / "includes/class-sc-library-research-document-builder.php").read_text()
BOOT = (PLUGIN / "includes/class-sc-library-extension-bootstrap-v402.php").read_text()
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
README = (PLUGIN / "readme.txt").read_text()
JS = (PLUGIN / "assets/js/sc-library-research-document-builder.js").read_text()
CSS = (PLUGIN / "assets/css/sc-library-research-document-builder.css").read_text()
CITATION = (PLUGIN / "includes/class-sc-library-citation-studio.php").read_text()
FIELD = (PLUGIN / "templates/field-spotlights.php").read_text()
PAGE = (ROOT / "RESEARCH_LIBRARY_PAGE_v4.3.23.html").read_text()


def test_release_identity_and_builder_module_registration():
    assert "Version: 4.3.23" in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.23');" in MAIN
    assert "Stable tag: 4.3.23" in README
    assert "public const VERSION = '4.3.23';" in CLASS
    assert "class-sc-library-research-document-builder.php" in BOOT
    assert "SC_Library_Research_Document_Builder" in BOOT
    match = re.search(r"public const MODULE_COUNT = (\d+);", BOOT)
    assert match and int(match.group(1)) >= 28


def test_document_builder_is_account_owned_and_bounded():
    for marker in [
        "sc_library_research_documents_v4323",
        "MAX_DOCUMENTS = 50",
        "MAX_SOURCES = 100",
        "is_user_logged_in()",
        "get_current_user_id()",
        "check_ajax_referer( self::NONCE_ACTION, 'nonce' )",
        "SC_Library_Citation_Studio::META_OWNER",
        "array_intersect( $source_ids, $owned )",
    ]:
        assert marker in CLASS


def test_builder_exposes_six_source_aware_document_types():
    for template in [
        "reading-list",
        "annotated-bibliography",
        "literature-review-packet",
        "research-brief",
        "evidence-packet",
        "research-notes",
    ]:
        assert f"'{template}'" in CLASS
    assert "does not invent analysis or annotations" in CLASS
    assert "does not substitute generated claims for missing analysis" in CLASS


def test_real_docx_and_pdf_export_endpoints_are_registered():
    for action in [
        "sc_library_v4323_list_builder_sources",
        "sc_library_v4323_save_document",
        "sc_library_v4323_delete_document",
        "sc_library_v4323_export_document",
    ]:
        assert action in CLASS
    assert "application/vnd.openxmlformats-officedocument.wordprocessingml.document" in CLASS
    assert "application/pdf" in CLASS
    assert "build_docx_binary" in CLASS
    assert "build_pdf_binary" in CLASS
    assert "X-Sustainable-Catalyst-SHA256" in CLASS


def test_docx_generator_has_valid_ooxml_parts_and_dependency_fallback():
    for marker in [
        "[Content_Types].xml",
        "word/document.xml",
        "word/styles.xml",
        "docProps/core.xml",
        "ZipArchive",
        "PharData",
        "Phar::ZIP",
    ]:
        assert marker in CLASS


def test_pdf_generator_is_server_side_and_multi_page_capable():
    for marker in [
        "%PDF-1.4",
        "/Type /Catalog",
        "/Type /Pages",
        "/Type /Page",
        "xref",
        "startxref",
        "Sustainable Catalyst Research Library - Page",
    ]:
        assert marker in CLASS
    assert "Browser Print" not in CLASS


def test_citation_studio_sources_can_be_sent_to_builder():
    assert "data-sc-add-source-to-document" in CITATION
    assert 'href=\"#research-document-builder\"' in CITATION
    assert "data-sc-add-source-to-document" in JS
    assert "Source added to the current document selection." in JS


def test_page_promotes_builder_between_citation_and_workspace():
    assert "Research Library v4.3.23" in PAGE
    assert '[sc_research_document_builder limit="100" style="harvard"]' in PAGE
    assert "Turn Sources into Research Outputs" in PAGE
    assert "download real DOCX or PDF outputs" in PAGE
    assert PAGE.index('id="citation-studio"') < PAGE.index('id="research-document-builder"') < PAGE.index('id="research-workspace"')
    assert '<li><a href="#research-document-builder">Research Document Builder</a></li>' in PAGE
    assert "Find → Understand → Organize → Produce" in PAGE


def test_builder_js_has_binary_download_and_source_refresh_contract():
    for marker in [
        "response.blob()",
        "content-disposition",
        "sc:citation-source-saved",
        "data-sc-document-export",
        "data-sc-document-load",
        "data-sc-document-delete",
        "source_ids",
    ]:
        assert marker in JS
    assert "CSS.escape" not in JS


def test_builder_layout_is_responsive_and_institutionally_restrained():
    for marker in [
        ".sc-research-document-builder__source-grid",
        "grid-template-columns:repeat(2,minmax(0,1fr))",
        ".sc-research-document-builder__saved-grid",
        "@media(max-width:860px)",
        "--sc-doc-red:#b40000",
    ]:
        assert marker in CSS


def test_docx_and_pdf_fixture_binaries_are_structurally_valid(tmp_path):
    php = subprocess.run(
        ["php", str(ROOT / "tests/test_research_document_builder_fixture_v4323.php"), str(tmp_path)],
        check=True,
        capture_output=True,
        text=True,
    )
    payload = json.loads(php.stdout)
    assert payload["schema"] == "sc-research-document/1.0"
    docx = tmp_path / "fixture.docx"
    pdf = tmp_path / "fixture.pdf"

    # Compressed DOCX size varies substantially by ZIP backend (for example,
    # PHP ZipArchive vs the PharData fallback). Validate the OOXML package
    # itself instead of enforcing an environment-dependent byte threshold.
    assert payload["docx_bytes"] == docx.stat().st_size
    assert payload["pdf_bytes"] == pdf.stat().st_size
    assert payload["docx_bytes"] > 0
    assert payload["pdf_bytes"] > 0
    assert payload["zip_backend"] in {"ZipArchive", "PharData"}

    assert docx.read_bytes()[:2] == b"PK"
    assert pdf.read_bytes().startswith(b"%PDF-1.4")
    assert zipfile.is_zipfile(docx)
    with zipfile.ZipFile(docx) as archive:
        assert archive.testzip() is None
        names = set(archive.namelist())
        required = {
            "[Content_Types].xml",
            "_rels/.rels",
            "word/document.xml",
            "word/styles.xml",
            "docProps/core.xml",
        }
        assert required <= names
        for member in required:
            assert archive.getinfo(member).file_size > 0
        document_xml = archive.read("word/document.xml").decode("utf-8")
        content_types = archive.read("[Content_Types].xml").decode("utf-8")
        rels = archive.read("_rels/.rels").decode("utf-8")
        assert "Sustainable Systems Evidence Packet" in document_xml
        assert "10.1017/9781009325844" in document_xml
        assert "wordprocessingml.document.main+xml" in content_types
        assert "officeDocument" in rels

    pdf_text = pdf.read_bytes()
    assert b"/Type /Catalog" in pdf_text
    assert b"/Type /Pages" in pdf_text
    assert b"/Type /Page" in pdf_text
    assert b"xref" in pdf_text
    assert b"startxref" in pdf_text
    assert pdf_text.rstrip().endswith(b"%%EOF")


def test_publications_14_field_stack_is_preserved_unchanged():
    assert 'data-sc-field-stack="v4.3.22.4"' in FIELD
    assert 'data-sc-field-stack-mode="all-fields"' in FIELD
    assert "foreach ( $field_list as $index => $stack_field )" in FIELD
    assert "sc-field-spotlights--stack-item" in FIELD
    assert "class-sc-library-field-spotlights.php" in MAIN
