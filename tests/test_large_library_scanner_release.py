from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
SCANNER = (PLUGIN / "includes/class-sc-library-scanner.php").read_text()
INDEXER = (PLUGIN / "includes/class-sc-library-indexer.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
JS = (PLUGIN / "assets/js/sc-library-scanner.js").read_text()


def test_cursor_state_has_no_id_queue():
    assert "sc-library-index-scan/2.0" in SCANNER
    assert "cursor_id" in SCANNER
    assert "'queue'" not in SCANNER
    assert "['queue']" not in SCANNER


def test_discovery_is_direct_and_bounded():
    assert "p.ID > %d" in INDEXER
    assert "ORDER BY p.ID ASC LIMIT %d" in INDEXER
    assert "new WP_Query" not in SCANNER
    scanner_indexer_section = INDEXER[INDEXER.index("public function scan_published_count"):]
    assert "posts_per_page' => -1" not in scanner_indexer_section


def test_scan_audit_table_and_accounting_exist():
    assert "sc_library_scan_items" in ACTIVATOR
    assert "UNIQUE KEY scan_post" in ACTIVATOR
    assert "accounting_ok" in SCANNER
    assert "indexed']) + absint($state['excluded']) + absint($state['failed'])" in SCANNER
    assert "sc-library-index-scan-log/2.0" in SCANNER


def test_post_type_discovery_and_reset_controls_exist():
    assert "discoverable_post_types" in INDEXER
    assert "recommended_post_types" in INDEXER
    assert "/scanner/reset" not in SCANNER  # Routes are assembled from the action list.
    assert "'step', 'pause', 'resume', 'cancel', 'reset'" in SCANNER
    assert "data-sc-select-recommended" in (PLUGIN / "templates/library-index-scanner.php").read_text()
    assert "data-sc-reset" in JS


def test_release_markers():
    main = (PLUGIN / "sustainable-catalyst-library.php").read_text()
    readme = (PLUGIN / "readme.txt").read_text()
    assert "Version: 1.14.0" in main
    assert "SC_LIBRARY_VERSION', '1.14.0'" in main
    assert "Stable tag: 1.14.0" in readme


def test_global_database_inventory_is_independent_of_saved_scope():
    assert "database_published_post_type_counts" in INDEXER
    assert "global_published_count" in INDEXER
    assert "standard_posts_published" in SCANNER
    assert "selected_published" in SCANNER
    assert "global_indexed" in SCANNER


def test_legacy_scope_migration_and_server_fallback_exist():
    template = (PLUGIN / "templates/library-index-scanner.php").read_text()
    assert "maybe_expand_legacy_scope" in SCANNER
    assert "sc_library_scanner_1134_scope_checked" in SCANNER
    assert "server_reconcile" in SCANNER
    assert "sc_library_server_reconcile" in template
    assert "default_selected" in SCANNER


def test_stable_route_and_relative_rest_path_exist():
    assert "sc-library-index-tools" in SCANNER
    assert "sc-library-scanner" in SCANNER
    assert "'path' => '/' . self::REST_NAMESPACE . '/scanner'" in SCANNER
    assert "path: `${config.path" in JS
