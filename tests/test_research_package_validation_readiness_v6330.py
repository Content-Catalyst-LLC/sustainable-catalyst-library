from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_markers_present():
    main = (ROOT / "library-backend/app/main.py").read_text()
    web = (ROOT / "library-web/index.html").read_text()
    php = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    assert "research-package-readiness" in main
    assert "Research Package Validation &amp; Publication Readiness" in web
    assert "6.33.0" in php


def test_guardrail_markers_present():
    module = (ROOT / "library-backend/app/research_package_validation_readiness.py").read_text()
    for marker in [
        '"ready_for_handoff_implies_truth": False',
        '"ready_for_handoff_implies_scientific_validity": False',
        '"automatic_publication": False',
        '"automatic_external_submission": False',
        '"database_migration_required": False',
        '"wordpress_required": False',
    ]:
        assert marker in module


def test_web_port_preserved():
    compose = (ROOT / "library-web/compose.yml").read_text()
    assert "${SC_LIBRARY_WEB_BIND_PORT:-8095}" in compose
