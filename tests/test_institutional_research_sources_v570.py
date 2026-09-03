from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"


def test_plugin_version_and_module_wiring():
    main = (PLUGIN / "sustainable-catalyst-library.php").read_text()
    assert "Version: 5.7.0" in main
    assert "SC_LIBRARY_VERSION', '5.7.0" in main
    assert "class-sc-library-institutional-research-sources.php" in main
    assert "SC_Library_Institutional_Research_Sources" in main


def test_institutional_source_surface_is_jhu_first_and_fail_contained():
    module = (PLUGIN / "includes" / "class-sc-library-institutional-research-sources.php").read_text()
    assert "johns-hopkins-dataverse" in module
    assert "Johns Hopkins Research Data" in module
    assert "does not imply endorsement or partnership" in module
    assert "sc_library_institutional_source_unavailable" in module
    assert "SC_Library_Python_Backend::base_url()" in module


def test_assets_exist_and_use_bounded_search():
    js = (PLUGIN / "assets" / "institutional-research-sources-v570.js").read_text()
    css = (PLUGIN / "assets" / "institutional-research-sources-v570.css").read_text()
    assert "limit', '12'" in js
    assert "Library remains available" in js
    assert "grid-template-columns" in css
