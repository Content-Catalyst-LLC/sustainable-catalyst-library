from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
CSS = (PLUGIN / "assets/css/sc-library.css").read_text()
JS = (PLUGIN / "assets/js/sc-library.js").read_text()
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
README = (PLUGIN / "readme.txt").read_text()


def test_patch_release_markers():
    assert "Version: 2.0.1" in MAIN
    assert "SC_LIBRARY_VERSION', '2.0.1'" in MAIN
    assert "Stable tag: 2.0.1" in README


def test_record_card_no_longer_uses_expanding_auto_action_column():
    assert ".sc-library-record{display:grid;grid-template-columns:minmax(0,1fr);" in CSS
    assert ".sc-library-record{display:grid;grid-template-columns:minmax(0,1fr) auto;" not in CSS
    assert "grid-template-areas:" in CSS
    assert '"meta"' in CSS and '"body"' in CSS and '"foot"' in CSS


def test_actions_wrap_in_full_width_footer():
    assert ".sc-library-record__foot{grid-column:1/-1;display:grid;grid-template-columns:minmax(0,1fr);" in CSS
    assert "flex-wrap:wrap" in CSS
    assert ".sc-library-record__actions{display:flex;width:100%;" in CSS
    assert "white-space:nowrap" not in CSS[CSS.index(".sc-library-record__actions{"):CSS.index(".sc-library-record__actions time")]
    assert ".sc-library .sc-library-record__actions button" in CSS


def test_text_direction_and_print_regressions_are_guarded():
    assert "writing-mode: horizontal-tb !important" in CSS
    assert "word-break: normal !important" in CSS
    assert "overflow-wrap: break-word !important" in CSS
    assert "@media print" in CSS
    assert ".sc-library .sc-library-record__actions button" in CSS
    assert "display: none !important" in CSS


def test_renderer_emits_semantic_layout_hooks():
    assert "sc-library-record sc-library-record--responsive" in JS
    assert "article.dataset.layoutVersion = '1.14.1'" in JS
    assert 'class="sc-library-record__excerpt"' in JS


def test_release_documentation_exists():
    assert (ROOT / "RELEASE_NOTES_1.14.1.md").exists()
    assert (ROOT / "PUBLIC_RECORD_LAYOUT_REPAIR_SETUP.md").exists()
    assert (ROOT / "tests/fixtures/public-record-layout.html").exists()
