from pathlib import Path
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
TEST = (ROOT / "tests/test_research_document_builder_v4323.py").read_text()
FIXTURE = (ROOT / "tests/test_research_document_builder_fixture_v4323.php").read_text()
RUNNER = (ROOT / "tests/run_v4323_r1_validation.sh").read_text()
INSTALLER = (ROOT / "install_and_push_sustainable_catalyst_library_v4_3_23_r1_macos.sh").read_text()
MAIN = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
STACK = (ROOT / "sustainable-catalyst-library/templates/field-spotlights.php").read_text()


def test_product_version_remains_v4323_and_repair_is_installer_only():
    assert "Version: 4.3.23" in MAIN
    assert "Version: 4.3.23-r1" not in MAIN
    assert 'RELEASE_VERSION="4.3.23"' in INSTALLER
    assert 'REPAIR_REVISION="r1"' in INSTALLER


def test_environment_dependent_docx_size_gate_is_removed():
    assert 'payload["docx_bytes"] > 5000' not in TEST
    assert 'payload["docx_bytes"] == docx.stat().st_size' in TEST
    assert 'payload["docx_bytes"] > 0' in TEST
    assert 'zipfile.is_zipfile(docx)' in TEST
    assert 'archive.testzip() is None' in TEST


def test_ooxml_validation_checks_required_package_parts_and_content():
    for marker in [
        '"[Content_Types].xml"',
        '"_rels/.rels"',
        '"word/document.xml"',
        '"word/styles.xml"',
        '"docProps/core.xml"',
        'wordprocessingml.document.main+xml',
        'officeDocument',
        'Sustainable Systems Evidence Packet',
        '10.1017/9781009325844',
    ]:
        assert marker in TEST


def test_fixture_reports_zip_backend_for_cross_environment_diagnostics():
    assert "class_exists('ZipArchive') ? 'ZipArchive' : 'PharData'" in FIXTURE
    assert 'payload["zip_backend"] in {"ZipArchive", "PharData"}' in TEST


def test_pdf_validation_is_structural_not_size_threshold_based():
    assert 'payload["pdf_bytes"] > 1500' not in TEST
    for marker in ['b"%PDF-1.4"', 'b"/Type /Catalog"', 'b"/Type /Pages"', 'b"xref"', 'b"startxref"', 'b"%%EOF"']:
        assert marker in TEST


def test_r1_runner_executes_repair_contract_then_full_v4323_stack():
    assert "test_installer_repair_v4323_r1.py" in RUNNER
    assert "run_v4323_validation.sh" in RUNNER


def test_installer_is_safe_for_partially_applied_v4323_working_tree():
    for marker in [
        "Creating safety backup",
        "rsync -a --delete",
        "Running v4.3.23-r1 validation",
        "tests/run_v4323_r1_validation.sh",
        "git -C \"$REPO\" add -A",
        "Installer Validation Repair",
    ]:
        assert marker in INSTALLER


def test_publications_14_field_stack_contract_is_untouched():
    assert 'data-sc-field-stack="v4.3.22.4"' in STACK
    assert 'data-sc-field-stack-mode="all-fields"' in STACK


def test_recompressed_valid_docx_reproduces_original_false_negative(tmp_path):
    subprocess.run(
        ["php", str(ROOT / "tests/test_research_document_builder_fixture_v4323.php"), str(tmp_path)],
        check=True,
        capture_output=True,
        text=True,
    )
    source = tmp_path / "fixture.docx"
    deflated = tmp_path / "fixture-deflated.docx"
    with zipfile.ZipFile(source, "r") as zin, zipfile.ZipFile(
        deflated, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9
    ) as zout:
        for info in zin.infolist():
            zout.writestr(info.filename, zin.read(info.filename))

    # This reproduces the reported ZipArchive-sized output. The old >5000
    # assertion would reject it even though the package is fully valid.
    assert deflated.stat().st_size < 5000
    assert zipfile.is_zipfile(deflated)
    with zipfile.ZipFile(deflated) as archive:
        assert archive.testzip() is None
        assert {
            "[Content_Types].xml",
            "_rels/.rels",
            "word/document.xml",
            "word/styles.xml",
            "docProps/core.xml",
        } <= set(archive.namelist())
        assert "Sustainable Systems Evidence Packet" in archive.read("word/document.xml").decode("utf-8")
