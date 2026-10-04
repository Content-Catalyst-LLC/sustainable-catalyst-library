from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_markers_and_routes():
    main = (ROOT / "library-backend/app/main.py").read_text()
    web = (ROOT / "library-web/index.html").read_text()
    js = (ROOT / "library-web/assets/app.js").read_text()
    nav = (ROOT / "library-backend/app/navigation_service.py").read_text()
    module = (ROOT / "library-backend/app/research_annotation_notes.py").read_text()
    assert 'LIBRARY_VERSION = "6.16.0"' in module
    assert 'BACKEND_VERSION = "3.16.0"' in module
    assert '/api/library/v1/annotations/readiness' in main
    assert '/research/notes' in web
    assert 'data-view="notes"' in web
    assert 'loadResearchNotesWorkspace' in js
    assert '"library_web_version": "2.16.0"' in main
    assert '"library_sdk_version": "1.16.0"' in main
    assert '"next_release": "6.17.0"' in nav


def test_guardrail_markers():
    module = (ROOT / "library-backend/app/research_annotation_notes.py").read_text()
    for marker in [
        '"annotation_is_evidence_truth": False',
        '"selected_quote_is_verified_against_target_automatically": False',
        '"scholarly_notes_are_auto_promoted_to_citations": False',
        '"server_side_note_persistence_enabled": False',
        '"database_migration_required": False',
        '"wordpress_required": False',
    ]:
        assert marker in module
