from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAGE = (ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.19.1.html').read_text()
MAIN = (ROOT / 'sustainable-catalyst-library' / 'sustainable-catalyst-library.php').read_text()
README = (ROOT / 'sustainable-catalyst-library' / 'readme.txt').read_text()


def test_release_identity():
    assert 'Version: 4.3.19.1' in MAIN
    assert "SC_LIBRARY_VERSION', '4.3.19.1'" in MAIN
    assert 'Stable tag: 4.3.19.1' in README
    assert 'Research Library v4.3.19.1 — Access-First Layout & Editorial Compression' in PAGE


def test_access_first_order_and_core_surfaces():
    ids = [
        'id="research-access"',
        'id="research-front-door"',
        'id="research-flow"',
        'id="knowledge-explorer"',
        'id="explore-knowledge"',
        'id="research-librarian"',
        'id="research-workspace"',
        'id="connected-platform"',
        'id="research-infrastructure"',
    ]
    positions = [PAGE.index(x) for x in ids]
    assert positions == sorted(positions)


def test_legacy_explanatory_sections_are_removed_from_main_page():
    removed_ids = [
        'what-this-library-does', 'how-the-library-works', 'reader-pathways',
        'featured-pathways', 'core-libraries', 'symbols-code-data',
        'signature-formats', 'technical-knowledge-systems', 'methods-code',
        'research-layer', 'documents-archive', 'institutional-research',
        'research-library-standards', 'principles',
    ]
    for section_id in removed_ids:
        assert f'id="{section_id}"' not in PAGE


def test_functional_shortcodes_remain():
    required = [
        '[sc_research_access ',
        '[sc_research_librarian_orchestrator mode="front-door"',
        '[sc_library mode="full"',
        '[sc_knowledge_relationship_browser ',
        '[sc_pathway_recommendations ',
        '[sc_research_librarian_orchestrator]',
        '[sc_library_unified_workspace]',
        '[sc_institutional_research_portal ',
    ]
    for shortcode in required:
        assert shortcode in PAGE


def test_page_is_materially_compressed():
    old = (ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.19.html').read_text()
    assert len(PAGE.splitlines()) < len(old.splitlines()) * 0.45
    assert len(PAGE) < len(old) * 0.55


def test_publications_and_applied_tool_routes_remain_visible():
    for route in ['/publications/', '/workbench/', '/decision-studio/', '/site-intelligence/', '/lab/']:
        assert route in PAGE


def test_research_access_and_workspace_safety_language_remain():
    assert 'Public discovery remains open to everyone' in PAGE
    assert 'without giving Sustainable Catalyst library passwords' in PAGE
    assert 'user-confirmed Workspace actions' in PAGE


def test_removed_content_is_consolidated_not_claimed_deleted():
    assert 'Documents, Methods, Standards, and Institutional Memory' in PAGE
    assert 'Research &amp; Editorial Commitments' in PAGE
    assert 'Code &amp; Reproducible Learning' in PAGE
