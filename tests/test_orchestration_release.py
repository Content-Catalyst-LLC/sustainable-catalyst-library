from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
ORCH = (PLUGIN / "includes/class-sc-library-orchestrator.php").read_text()
INTEGRATIONS = (PLUGIN / "includes/class-sc-library-integrations.php").read_text()
SHORTCODES = (PLUGIN / "includes/class-sc-library-shortcodes.php").read_text()
LIBRARY_JS = (PLUGIN / "assets/js/sc-library.js").read_text()
ORCH_JS = (PLUGIN / "assets/js/sc-library-orchestrator.js").read_text()
ORCH_CSS = (PLUGIN / "assets/css/sc-library-orchestrator.css").read_text()
PORTABILITY = (PLUGIN / "includes/class-sc-library-portability.php").read_text()
STATIC_SCHEMA = (ROOT / "docs/postgresql-schema.sql").read_text()
README = (PLUGIN / "readme.txt").read_text()


def test_release_markers_and_bootstrap():
    assert "Version: 1.18.1" in MAIN
    assert "SC_LIBRARY_VERSION', '1.18.1'" in MAIN
    assert "class-sc-library-orchestrator.php" in MAIN
    assert "new SC_Library_Orchestrator" in MAIN
    assert "$orchestrator->register_hooks()" in MAIN
    assert "Stable tag: 1.18.1" in README


def test_orchestration_tables_and_schema_markers():
    assert "sc_library_orchestration_sessions" in ACTIVATOR
    assert "sc_library_orchestration_events" in ACTIVATOR
    assert "dbDelta($orchestration_sessions_sql)" in ACTIVATOR
    assert "dbDelta($orchestration_events_sql)" in ACTIVATOR
    assert "sc-library-orchestration/1.0" in ORCH
    assert "sc-library-orchestration-action/1.0" in ORCH
    assert "sc-library-orchestration-session/1.0" in ORCH


def test_site_scoped_retrieval_graph_expansion_and_explanations():
    assert "orchestration_score" in ORCH
    assert "search_records" in ORCH
    assert "graph_expansion" in ORCH
    assert "why" in ORCH
    assert "site_scoped_retrieval" in ORCH
    assert "use_only_supplied_records" in ORCH
    assert "do_not_create_actions" in ORCH
    assert "remote_synthesis_can_modify_actions' => false" in ORCH


def test_actions_require_confirmation_and_do_not_publish():
    assert "user_confirmation_required' => true" in ORCH
    assert "automatic_publication' => false" in ORCH
    assert "automatic_approval' => false" in ORCH
    assert "window.confirm" in ORCH_JS
    assert "User confirmation required" in ORCH_JS
    assert "No publication or approval action has been applied" in ORCH_JS
    assert "action_applied" in ORCH_JS
    assert "sessions" in ORCH_JS


def test_routes_shortcodes_and_targets():
    for route in [
        "/library/orchestrator/schema",
        "/library/orchestrator/status",
        "/library/orchestrator/query",
        "/library/orchestrator/sessions",
        "/library/orchestrator/events",
    ]:
        assert route in ORCH
    assert "sc_research_librarian_orchestrator" in ORCH
    assert "sc_library_orchestrator" in ORCH
    for target in [
        "notebook", "translation_matrix", "whiteboard", "book_builder",
        "editorial_workflow", "workbench", "decision_studio",
        "site_intelligence", "lab",
    ]:
        assert f"'{target}'" in ORCH
    assert "'lab'" in INTEGRATIONS
    assert "orchestration_packet" in INTEGRATIONS


def test_local_workspace_actions_cover_research_workflow():
    for action in [
        "create_collection", "save_records", "create_note", "create_matrix",
        "create_board", "create_book", "create_handoff", "open_editorial",
        "export_workspace",
    ]:
        assert action in ORCH
        assert action in ORCH_JS
    assert "scLibraryWorkspaceV120" in ORCH
    assert "scLibraryWorkspaceV120" in ORCH_JS
    assert "sc-library-handoff/1.0" in ORCH_JS


def test_public_payload_trims_internal_target_fields():
    assert "private static function public_targets" in ORCH
    assert "unset($target['health_url'], $target['api_key'], $target['secret'])" in ORCH
    assert "'targets' => self::public_targets()" in ORCH
    assert "service_api_key" not in ORCH_JS


def test_accessible_responsive_native_interface():
    assert "data-orchestrator-form" in (PLUGIN / "templates/library-orchestrator.php").read_text()
    assert "data-orchestrator-output" in (PLUGIN / "templates/library-orchestrator.php").read_text()
    assert "@media(max-width:782px)" in ORCH_CSS or "@media (max-width: 782px)" in ORCH_CSS
    assert "@media print" in ORCH_CSS
    assert "<iframe" not in ORCH.lower()
    assert "<iframe" not in ORCH_JS.lower()


def test_library_record_actions_link_to_orchestrator():
    assert "orchestratorEnabled" in SHORTCODES
    assert "orchestratorPageUrl" in SHORTCODES
    assert "Ask Research Librarian" in SHORTCODES
    assert "orchestratorUrlFor" in LIBRARY_JS
    assert "Ask Research Librarian" in LIBRARY_JS


def test_portable_orchestration_entities_and_static_schema():
    assert "sc-library-portable-export/1.9" in PORTABILITY
    assert "'orchestration'" in PORTABILITY
    for entity in ["orchestration_sessions", "orchestration_events"]:
        assert entity in PORTABILITY
        assert f"CREATE TABLE IF NOT EXISTS {entity}" in STATIC_SCHEMA
    assert "REFERENCES orchestration_sessions(orchestration_session_id) ON DELETE CASCADE" in PORTABILITY
    assert "REFERENCES orchestration_sessions(orchestration_session_id) ON DELETE CASCADE" in STATIC_SCHEMA


def test_release_documentation_exists():
    assert (ROOT / "RELEASE_NOTES_1.18.0.md").exists()
    assert (ROOT / "RESEARCH_LIBRARIAN_ORCHESTRATION_SETUP.md").exists()
    setup = (ROOT / "RESEARCH_LIBRARIAN_ORCHESTRATION_SETUP.md").read_text()
    assert "Research Librarian Workspace Orchestration" in setup
    assert "explicit confirmation" in setup.lower()


def test_retained_major_features():
    for retained in [
        "class-sc-library-knowledge-graph.php",
        "class-sc-library-collaboration.php",
        "class-sc-library-multimedia.php",
        "class-sc-library-scanner.php",
        "class-sc-library-workspaces.php",
    ]:
        assert retained in MAIN
