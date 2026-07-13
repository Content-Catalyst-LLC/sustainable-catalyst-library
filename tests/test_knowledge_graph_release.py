from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
GRAPH = (PLUGIN / "includes/class-sc-library-knowledge-graph.php").read_text()
RELATIONSHIPS = (PLUGIN / "includes/class-sc-library-relationships.php").read_text()
REST = (PLUGIN / "includes/class-sc-library-rest.php").read_text()
EDITOR = (PLUGIN / "includes/class-sc-library-editor.php").read_text()
BOARDS = (PLUGIN / "includes/class-sc-library-boards.php").read_text()
BOARD_JS = (PLUGIN / "assets/js/sc-library-boards.js").read_text()
GRAPH_JS = (PLUGIN / "assets/js/sc-library-knowledge-graph.js").read_text()
GRAPH_CSS = (PLUGIN / "assets/css/sc-library-knowledge-graph.css").read_text()
PORTABILITY = (PLUGIN / "includes/class-sc-library-portability.php").read_text()
README = (PLUGIN / "readme.txt").read_text()


def test_release_markers_and_bootstrap():
    assert "Version: 2.0.1" in MAIN
    assert "SC_LIBRARY_VERSION', '2.0.1'" in MAIN
    assert "class-sc-library-knowledge-graph.php" in MAIN
    assert "new SC_Library_Knowledge_Graph" in MAIN
    assert "$knowledge_graph->register_hooks()" in MAIN
    assert "Stable tag: 2.0.1" in README


def test_graph_tables_and_relationship_fields_are_installed():
    for table in ["sc_library_graph_nodes", "sc_library_graph_edges"]:
        assert table in ACTIVATOR
    for field in [
        "node_uuid", "external_key", "node_type", "source_node_id",
        "target_node_id", "relationship_type", "confidence",
        "confidence_basis", "provenance_type", "provenance_url",
        "evidence_note", "visibility", "verified_at",
    ]:
        assert field in ACTIVATOR
    assert "dbDelta($graph_nodes_sql)" in ACTIVATOR
    assert "dbDelta($graph_edges_sql)" in ACTIVATOR


def test_graph_projection_and_diagnostics_are_explicit():
    assert "sc-library-knowledge-graph/1.0" in GRAPH
    for node_type in [
        "record", "concept", "series", "place", "method", "tool",
        "dataset", "source", "claim", "evidence", "question", "event",
    ]:
        assert f"'{node_type}'" in GRAPH
    for relationship in [
        "depends_on", "cites_source", "uses_method", "uses_tool",
        "uses_dataset", "associated_with_place", "has_concept",
        "part_of_series", "supports", "challenges",
    ]:
        assert f"'{relationship}'" in GRAPH
    for diagnostic in [
        "orphan_count", "duplicate_concept_group_count",
        "dependency_cycle_count", "provenance_gap_count",
        "low_confidence_count", "unverified_count",
    ]:
        assert diagnostic in GRAPH
    assert "source_kind NOT IN ('manual','board')" in GRAPH
    assert "REBUILD_STATE_OPTION" in GRAPH
    assert "post_id > %d" in GRAPH
    assert "/library/graph/rebuild/start" in GRAPH
    assert "/library/graph/rebuild/continue" in GRAPH
    assert "Start resumable graph rebuild" in GRAPH
    assert "_sc_library_graph_source_claims" in GRAPH
    assert "Explicit source-to-claim link" in GRAPH


def test_relationship_editor_and_public_privacy_boundary():
    for field in [
        "confidence", "confidence_basis", "provenance_type",
        "provenance_url", "evidence_note", "visibility",
    ]:
        assert field in RELATIONSHIPS
        assert field in EDITOR
    assert "($relation['visibility'] ?? 'public') !== 'public'" in REST
    assert "!current_user_can('edit_posts')" in REST
    assert "e.visibility = 'public'" in GRAPH
    assert "n.visibility = 'public'" in GRAPH


def test_graph_routes_shortcodes_and_board_promotion():
    for fragment in [
        "/library/graph/schema", "/library/graph'", "/library/graph/diagnostics",
        "/library/graph/timeline", "/library/graph/places",
        "/library/graph/rebuild", "/library/graph/board-promotions",
        "/library/graph/edges",
    ]:
        assert fragment in GRAPH
    assert "sc_library_knowledge_graph" in GRAPH
    assert "sc_library_relationship_intelligence" in GRAPH
    assert "promote_board" in GRAPH
    assert "graphEndpoint" in BOARDS
    assert "Promote to Knowledge Graph" in BOARD_JS


def test_native_accessible_responsive_graph_without_iframe():
    assert "<svg" in GRAPH_JS
    assert "role=\"img\"" in GRAPH_JS
    assert "tabindex=\"0\"" in GRAPH_JS
    assert "data-graph-list" in GRAPH
    assert "data-graph-inspector" in GRAPH
    assert "<iframe" not in GRAPH.lower()
    assert "<iframe" not in GRAPH_JS.lower()
    assert "@media(max-width:782px)" in GRAPH_CSS
    assert "@media print" in GRAPH_CSS


def test_portable_graph_entities_and_postgresql_schema():
    assert "sc-library-portable-export/3.0" in PORTABILITY
    assert "'knowledge_graph'" in PORTABILITY
    for entity in ["graph_nodes", "graph_edges"]:
        assert entity in PORTABILITY
    assert "REFERENCES graph_nodes(graph_node_id) ON DELETE CASCADE" in PORTABILITY
    assert "confidence numeric(5,4)" in PORTABILITY
    assert "provenance_type text" in PORTABILITY


def test_release_documentation_exists():
    assert (ROOT / "RELEASE_NOTES_1.16.0.md").exists()
    assert (ROOT / "KNOWLEDGE_GRAPH_SETUP.md").exists()


def test_main_library_exposes_graph_actions_without_reintroducing_card_compression():
    shortcodes = (PLUGIN / "includes/class-sc-library-shortcodes.php").read_text()
    library_js = (PLUGIN / "assets/js/sc-library.js").read_text()
    library_css = (PLUGIN / "assets/css/sc-library.css").read_text()
    assert "graphPageUrl" in shortcodes
    assert "View Relationship Graph" in shortcodes
    assert "graphUrlFor" in library_js
    assert "sc-library-record__actions a" in library_css
    assert ".sc-library-record__actions button,\n  .sc-library .sc-library-record__actions a" in library_css
