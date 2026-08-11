from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
PAGE = ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.16.html'

def text(path): return path.read_text(encoding='utf-8')

def test_release_identity_and_merged_page_contract():
    main=text(PLUGIN/'sustainable-catalyst-library.php'); readme=text(PLUGIN/'readme.txt'); page=text(PAGE)
    assert 'Version: 4.3.16' in main
    assert "SC_LIBRARY_VERSION', '4.3.16" in main
    assert 'Stable tag: 4.3.16' in readme
    assert '<h2 id="research-front-door-title">Have a Question?</h2>' in page
    assert 'button_label="Ask the Research Librarian"' in page
    assert 'Ask the Library' not in page
    assert 'show_librarian="true" librarian_target="#research-front-door"' in page
    assert 'library_url="#knowledge-explorer"' in page
    assert 'Search the Library Without Losing the Pathways' in page

def test_orchestrator_adds_pathway_recommendations_without_new_engine():
    src=text(PLUGIN/'includes/class-sc-library-orchestrator.php')
    assert '$pathways = $this->pathway_recommendations($prompt, $records, 4);' in src
    assert "class_exists('SC_Library_Knowledge_Pathways_Article_Maps')" in src
    assert 'SC_Library_Knowledge_Pathways_Article_Maps::recommend_pathways' in src
    assert "SC_Library_Topics_Concepts_Relationships::post_kind" in src
    assert "'node_keys' => array_values(array_unique($node_keys))" in src
    assert "'pathways' => $pathways" in src
    assert "'pathway_recommendation_count' => count($pathways)" in src

def test_pathway_payload_is_bounded_and_ordered():
    src=text(PLUGIN/'includes/class-sc-library-orchestrator.php')
    assert 'min(6, max(1, $limit))' in src
    assert "array_slice((array) ($pathway['steps'] ?? []), 0, 5)" in src
    for field in ["'title' =>", "'url' =>", "'level_label' =>", "'step_count' =>", "'reasons' =>", "'steps' =>"]:
        assert field in src
    assert "'order' => absint($step['order'] ?? 0) + 1" in src

def test_front_door_and_full_librarian_render_pathways():
    js=text(PLUGIN/'assets/js/sc-library-orchestrator.js')
    css=text(PLUGIN/'assets/css/sc-library-orchestrator.css')
    assert 'Recommended knowledge pathway' in js
    assert 'Recommended Knowledge Pathways' in js
    assert '(response.pathways || []).slice(0, 1)' in js
    assert '(pathway.steps || []).slice(0,4)' in js
    assert '(pathway.steps || []).slice(0,5)' in js
    assert 'Open pathway' in js
    assert '.sc-orchestrator__pathway' in css
    assert '.sc-orchestrator__pathway-steps' in css

def test_remote_synthesis_is_supplied_bounded_pathways_only():
    src=text(PLUGIN/'includes/class-sc-library-orchestrator.php')
    assert 'array $routes, array $pathways, string $fallback' in src
    assert "'pathways' => array_map(static fn($pathway) => [" in src
    assert "'use_only_supplied_records' => true" in src
    assert "'do_not_create_actions' => true" in src

def test_workspace_and_field_spotlight_boundaries_remain():
    js=text(PLUGIN/'assets/js/sc-library-orchestrator.js')
    field=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    assert 'window.confirm(cfg.strings?.confirmAction' in js
    front=js[js.index('if (frontDoor) {'):js.index('output.dataset.response', js.index('if (frontDoor) {'))]
    assert 'data-apply-action' not in front
    assert "public const VERSION = '4.3.13'" in field
    assert "PANEL_CONTENT_OPTION = 'sc_library_field_spotlight_panel_content_v4312'" in field
