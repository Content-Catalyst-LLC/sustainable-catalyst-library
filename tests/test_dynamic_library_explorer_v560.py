from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
BACKEND = ROOT / "library-backend"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
BRIDGE = PLUGIN / "includes/class-sc-library-python-backend.php"
EXPLORER = PLUGIN / "includes/class-sc-library-dynamic-explorer.php"
SHORTCODES = PLUGIN / "includes/class-sc-library-shortcodes.php"
TEMPLATE = PLUGIN / "templates/library-explorer.php"
JS = PLUGIN / "assets/js/sc-library-dynamic-explorer-v560.js"
CSS = PLUGIN / "assets/css/sc-library-dynamic-explorer-v560.css"
QUERY = BACKEND / "app/query.py"
BACKEND_MAIN = BACKEND / "app/main.py"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_and_backend_version():
    main = text(MAIN)
    assert "Version: 5.6.0.3" in main
    assert "SC_LIBRARY_VERSION', '5.6.0.3'" in main
    assert "public const VERSION = '5.6.0.3'" in text(BRIDGE)
    assert '__version__ = "1.1.0"' in text(BACKEND / "app/__init__.py")


def test_default_shortcode_routes_to_dynamic_explorer_but_legacy_modes_survive():
    shortcodes = text(SHORTCODES)
    explorer = text(EXPLORER)
    assert "SC_Library_Dynamic_Explorer::should_render" in shortcodes
    assert "return $mode === '' || in_array($mode, ['explorer', 'dynamic'], true);" in explorer
    assert "library_legacy" in explorer
    for mode in ["compact", "full", "search", "domains", "pathways", "documentation", "registry", "planner"]:
        assert mode in explorer


def test_front_door_is_compact_and_progressive():
    template = text(TEMPLATE)
    for needle in [
        "data-explorer-search",
        "data-explorer-metrics",
        "data-explorer-topic-strip",
        "data-explorer-filters",
        "data-explorer-featured",
        "data-explorer-results-section",
        "data-explorer-load-more",
        "data-explorer-drawer",
    ]:
        assert needle in template
    assert "Research Notebook" not in template
    assert "data-library-workspace-root" not in template


def test_explorer_uses_python_read_model_with_wordpress_fallback():
    explorer = text(EXPLORER)
    for needle in [
        "/v1/explorer/bootstrap",
        "/v1/search",
        "wordpress-fallback",
        "fallback_search",
        "fallback_record",
        "fallback_related",
        "SC_Library_Python_Backend::configured()",
    ]:
        assert needle in explorer


def test_backend_search_supports_dynamic_filters_and_sorting():
    query = text(QUERY)
    main = text(BACKEND_MAIN)
    for needle in ["topic", "year_from", "year_to", "source_key", "object_type", "SORTS", "source_updated_at"]:
        assert needle in query
    assert '@app.get("/v1/explorer/bootstrap")' in main
    assert 'sort: str = Query(default="relevance"' in main
    assert 'include_body: bool = Query(default=True)' in main


def test_bootstrap_is_bounded_and_does_not_dump_catalog():
    query = text(QUERY)
    main = text(BACKEND_MAIN)
    assert "featured_limit: int = Query(default=4" in main
    assert "recent_limit: int = Query(default=4" in main
    assert "explorer_bootstrap(featured_limit" in main
    assert "search_records(sort=\"newest\", limit=featured_limit" in query
    assert "search_records(sort=\"updated\", limit=recent_limit" in query


def test_progressive_record_detail_does_not_ship_full_body_by_default_from_explorer():
    explorer = text(EXPLORER)
    query = text(QUERY)
    assert "['include_body' => 'false']" in explorer
    assert "left(body_text, 1600) AS body_text" in query
    assert 'row["chunks"] = []' in query


def test_browser_state_load_more_and_lazy_detail_tabs_exist():
    js = text(JS)
    for needle in [
        "library_q",
        "library_topic",
        "library_type",
        "library_source",
        "library_from",
        "library_to",
        "popstate",
        "data-explorer-load-more",
        "loadRelated",
        "loadTimeline",
        "library_record",
    ]:
        assert needle in js


def test_explorer_has_responsive_accessible_presentation():
    template = text(TEMPLATE)
    css = text(CSS)
    assert 'role="search"' in template
    assert 'aria-live="polite"' in template
    assert 'role="dialog"' in template
    assert '@media(max-width:620px)' in css
    assert 'prefers-reduced-motion' in css


def test_backend_capabilities_advertise_explorer():
    main = text(BACKEND_MAIN)
    for needle in [
        '"dynamic_explorer": True',
        '"progressive_discovery": True',
        '"filterable_search": True',
        '"progressive_record_detail": True',
    ]:
        assert needle in main


def test_v55_hardening_and_operations_are_preserved():
    main = text(BACKEND_MAIN)
    bridge = text(BRIDGE)
    assert '"adaptive_ingestion": True' in main
    assert '"operations_recovery": True' in main
    assert "/v1/admin/integrity" in main
    assert "send_records_resilient" in bridge
    assert "http_413_splits" in bridge


def test_localhost_binding_and_shared_network_remain_unchanged():
    compose = text(BACKEND / "compose.yml")
    assert '"127.0.0.1:8087:8080"' in compose
    assert "external: true" in compose
    assert "sc-internal" in compose



def test_r1_page_preserves_capabilities_through_lazy_hub():
    page = text(ROOT / "RESEARCH_LIBRARY_PAGE_v5.6.0-R1.html")
    hub = text(PLUGIN / "includes/class-sc-library-capability-hub.php")
    assert '[sc_library mode="explorer" show_header="false" per_page="12"]' in page
    assert '[sc_library_capability_hub' in page
    for old_embed in [
        '[sc_library_unified_workspace]',
        '[sc_citation_studio',
        '[sc_research_document_builder',
        '[sc_collaborative_research_rooms',
        '[sc_institutional_team_libraries',
        '[sc_global_research_federation',
    ]:
        assert old_embed not in page
        assert old_embed in hub

def test_changed_php_files_parse_and_js_parses():
    for path in [MAIN, BRIDGE, EXPLORER, SHORTCODES, TEMPLATE]:
        result = subprocess.run(["php", "-l", str(path)], text=True, capture_output=True)
        assert result.returncode == 0, result.stderr + result.stdout
    result = subprocess.run(["node", "--check", str(JS)], text=True, capture_output=True)
    assert result.returncode == 0, result.stderr + result.stdout
