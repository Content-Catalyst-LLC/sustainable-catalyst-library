from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
PAGE = ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.15.html'

def text(path): return path.read_text(encoding='utf-8')

def test_release_identity():
    main=text(PLUGIN/'sustainable-catalyst-library.php'); readme=text(PLUGIN/'readme.txt')
    assert 'Version: 4.3.15' in main
    assert "SC_LIBRARY_VERSION', '4.3.15" in main
    assert 'Stable tag: 4.3.15' in readme

def test_library_bridge_is_opt_in_and_context_preserving():
    src=text(PLUGIN/'includes/class-sc-library-shortcodes.php')
    template=text(PLUGIN/'templates/library-app.php')
    js=text(PLUGIN/'assets/js/sc-library.js')
    assert "'show_librarian' => 'false'" in src
    assert "'librarian_target' => '#research-front-door'" in src
    assert 'data-librarian-bridge=' in template
    assert 'data-ask-librarian-query' in template
    assert 'data-ask-librarian-results' in template
    assert 'Ask the Research Librarian about these results' in template
    assert "new CustomEvent('sc-library-librarian-request'" in js
    assert 'lastResultItems.slice(0, 8)' in js
    assert "url.searchParams.set('record_ids'" in js

def test_librarian_returns_to_library_search():
    src=text(PLUGIN/'includes/class-sc-library-orchestrator.php')
    template=text(PLUGIN/'templates/library-orchestrator.php')
    js=text(PLUGIN/'assets/js/sc-library-orchestrator.js')
    assert "'record_ids' => ''" in src
    assert "'initial_prompt' => ''" in src
    assert "'library_url' => '#knowledge-explorer'" in src
    assert 'data-initial-record-ids=' in template
    assert 'data-library-url=' in template
    assert 'Ask the Research Librarian' in template
    assert 'Ask the Library' not in template
    assert 'View all matching Library records' in js
    assert "new CustomEvent('sc-library-search-request'" in js
    assert 'contextualRecordIds' in js

def test_page_enables_bridge_only_where_intended():
    page=text(PAGE)
    assert 'Ask the Research Librarian' in page
    assert 'Ask the Library' not in page
    assert 'show_librarian="true" librarian_target="#research-front-door"' in page
    assert 'library_url="#knowledge-explorer"' in page
    assert 'Search the Library Without Losing the Research Thread' in page

def test_workspace_and_field_spotlight_boundaries_remain():
    js=text(PLUGIN/'assets/js/sc-library-orchestrator.js')
    field=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    assert 'window.confirm(cfg.strings?.confirmAction' in js
    front=js[js.index('if (frontDoor) {'):js.index('output.dataset.response', js.index('if (frontDoor) {'))]
    assert 'data-apply-action' not in front
    assert "public const VERSION = '4.3.13'" in field
    assert "PANEL_CONTENT_OPTION = 'sc_library_field_spotlight_panel_content_v4312'" in field
