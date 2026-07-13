from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
COLLAB = (PLUGIN / "includes/class-sc-library-collaboration.php").read_text()
PORTABILITY = (PLUGIN / "includes/class-sc-library-portability.php").read_text()
JS = (PLUGIN / "assets/js/sc-library-collaboration.js").read_text()
CSS = (PLUGIN / "assets/css/sc-library-collaboration.css").read_text()
README = (PLUGIN / "readme.txt").read_text()


def test_release_markers_and_bootstrap():
    assert "Version: 1.18.0" in MAIN
    assert "SC_LIBRARY_VERSION', '1.18.0'" in MAIN
    assert "class-sc-library-collaboration.php" in MAIN
    assert "new SC_Library_Collaboration" in MAIN
    assert "Stable tag: 1.18.0" in README


def test_normalized_editorial_tables_are_installed():
    for table in [
        "sc_library_reviews",
        "sc_library_review_participants",
        "sc_library_review_comments",
        "sc_library_review_suggestions",
        "sc_library_review_events",
    ]:
        assert table in ACTIVATOR
    assert "review_uuid" in ACTIVATOR
    assert "current_revision" in ACTIVATOR
    assert "lock_expires_at" in ACTIVATOR


def test_roles_workflow_and_permissions_are_explicit():
    assert "sc-library-editorial-workflow/1.0" in COLLAB
    for role in ["observer", "reviewer", "editor", "approver"]:
        assert f"'{role}'" in COLLAB
    for status in ["intake", "internal_review", "fact_check", "accessibility_review", "approval_pending", "approved", "published"]:
        assert f"'{status}'" in COLLAB
    assert "can_access" in COLLAB
    assert "revision_conflict" in COLLAB
    assert "review_locked" in COLLAB


def test_comments_suggestions_invitations_and_attribution_routes():
    for fragment in [
        "/comments",
        "/suggestions",
        "/participants",
        "/lock",
        "/unlock",
        "/activity",
        "/invitations/accept",
        "/attribution",
        "/public/",
    ]:
        assert fragment in COLLAB
    assert "wp_mail" in COLLAB
    assert "token_hash" in COLLAB
    assert "log_event" in COLLAB
    assert "sync_workspace_collaborator" in COLLAB
    assert "public_review" in COLLAB
    assert "attribution_manifest" in COLLAB


def test_portable_export_contains_editorial_entities():
    assert "sc-library-portable-export/1.8" in PORTABILITY
    for entity in [
        "editorial_reviews",
        "editorial_participants",
        "editorial_comments",
        "editorial_suggestions",
        "editorial_events",
    ]:
        assert entity in PORTABILITY
    assert "REFERENCES editorial_reviews(review_id) ON DELETE CASCADE" in PORTABILITY


def test_interface_is_native_responsive_and_no_iframe():
    assert "data-sc-library-editorial" in COLLAB
    assert "[sc_library_editorial_workflow" not in COLLAB  # registered natively, not rendered as pasted shortcode text
    assert "sc_library_editorial_workflow" in COLLAB
    assert "fetch(" in JS
    assert "X-WP-Nonce" in JS
    assert "<iframe" not in JS.lower()
    assert "grid-template-columns" in CSS
    assert "@media (max-width: 820px)" in CSS
    assert "@media print" in CSS


def test_release_documentation_exists():
    assert (ROOT / "RELEASE_NOTES_1.15.0.md").exists()
    assert (ROOT / "EDITORIAL_WORKFLOW_SETUP.md").exists()
