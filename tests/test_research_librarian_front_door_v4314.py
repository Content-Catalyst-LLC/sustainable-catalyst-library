from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
PAGE = ROOT / "RESEARCH_LIBRARY_PAGE_v4.3.14.html"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_and_readme_contract():
    main = text(PLUGIN / "sustainable-catalyst-library.php")
    readme = text(PLUGIN / "readme.txt")
    assert "Version: 4.3.14" in main
    assert "SC_LIBRARY_VERSION', '4.3.14" in main
    assert "Stable tag: 4.3.14" in readme
    assert 'mode="front-door"' in readme
    # Publications Field Spotlight is intentionally not migrated in this release.
    field = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    assert "public const VERSION = '4.3.13'" in field
    assert "PANEL_CONTENT_OPTION = 'sc_library_field_spotlight_panel_content_v4312'" in field


def test_front_door_mode_is_backward_compatible_and_bounded():
    src = text(PLUGIN / "includes/class-sc-library-orchestrator.php")
    template = text(PLUGIN / "templates/library-orchestrator.php")
    js = text(PLUGIN / "assets/js/sc-library-orchestrator.js")
    css = text(PLUGIN / "assets/css/sc-library-orchestrator.css")

    for token in [
        "'mode' => 'standard'",
        "['standard', 'compact', 'front-door']",
        "$orchestrator_front_door = $orchestrator_mode === 'front-door'",
        "'examples' =>",
        "'full_url' => ''",
    ]:
        assert token in src

    assert 'data-orchestrator-mode="<?php echo esc_attr($orchestrator_mode); ?>"' in template
    assert 'class="sc-orchestrator__examples"' in template
    assert 'name="max_records" value="5"' in template
    assert 'Open the full Research Librarian' in template
    assert 'if ($orchestrator_front_door)' in template
    assert '<select name="intent"></select>' in template  # full mode remains available

    assert "frontDoor = root.dataset.orchestratorMode === 'front-door'" in js
    assert ".slice(0, 3)" in js
    assert ".slice(0, 2)" in js
    assert "data-orchestrator-example" in js
    assert "if (select && select.tagName === 'SELECT')" in js
    assert "Continue in the full Research Librarian" in js
    assert ".sc-orchestrator.is-front-door" in css
    assert ".cc-rl-research-flow-grid" in css


def test_front_door_does_not_apply_workspace_actions_silently():
    template = text(PLUGIN / "templates/library-orchestrator.php")
    js = text(PLUGIN / "assets/js/sc-library-orchestrator.js")
    # Front-door results intentionally omit action buttons; action application remains in full mode.
    front_block = js[js.index("if (frontDoor) {"):js.index("output.dataset.response", js.index("if (frontDoor) {"))]
    assert "data-apply-action" not in front_block
    assert "Apply to workspace" in js
    assert "window.confirm(cfg.strings?.confirmAction" in js
    assert "User confirmation required" in js
    assert "user-confirmed workspace actions" in template


def test_research_library_page_promotes_librarian_and_reorders_institutional_material():
    page = text(PAGE)
    assert 'id="research-front-door"' in page
    assert 'id="research-flow"' in page
    assert '[sc_research_librarian_orchestrator mode="front-door"' in page
    assert 'href="#research-front-door">Ask the Research Librarian</a>' in page
    assert 'href="#knowledge-explorer">Search the Library</a>' in page
    assert 'href="#reader-pathways">Explore Knowledge Pathways</a>' in page
    assert 'Continue with the Research Librarian' in page
    assert '[sc_research_librarian_orchestrator]' in page
    assert '[sc_library_unified_workspace]' in page

    # Guided discovery leads; institutional administration is intentionally later.
    assert page.index('id="research-front-door"') < page.index('id="what-this-library-does"')
    assert page.index('id="research-flow"') < page.index('id="knowledge-explorer"')
    assert page.index('id="documents-archive"') < page.index('id="institutional-research"')
    assert page.index('id="institutional-research"') < page.index('id="research-library-standards"')


def test_page_retains_core_research_architecture_and_unique_top_level_ids():
    page = text(PAGE)
    required = [
        'what-this-library-does', 'knowledge-explorer', 'how-the-library-works',
        'reader-pathways', 'featured-pathways', 'core-libraries', 'symbols-code-data',
        'signature-formats', 'technical-knowledge-systems', 'methods-code',
        'research-layer', 'research-librarian', 'research-workspace', 'connected-platform',
        'documents-archive', 'institutional-research', 'research-library-standards', 'principles'
    ]
    for section_id in required:
        assert f'id="{section_id}"' in page
    ids = re.findall(r'<section[^>]+id="([^"]+)"', page)
    assert len(ids) == len(set(ids))


def test_brand_geometry_and_mobile_flow_are_restrained():
    css = text(PLUGIN / "assets/css/sc-library-orchestrator.css")
    for token in [
        "border-top:6px solid #000",
        "border-radius:0",
        "grid-template-columns:repeat(4,minmax(0,1fr))",
        "@media(max-width:900px)",
        "@media(max-width:560px)",
    ]:
        assert token in css
