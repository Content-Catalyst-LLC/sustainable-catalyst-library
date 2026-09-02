from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"


def text(path):
    return path.read_text(encoding="utf-8")


def test_release_version_and_component_bump():
    main = text(PLUGIN / "sustainable-catalyst-library.php")
    module = text(PLUGIN / "includes/class-sc-library-homepage-spotlight.php")
    assert "Version: 5.6.1.1" in main
    assert "SC_LIBRARY_VERSION', '5.6.1.1'" in main
    assert "public const VERSION = '4.2.1'" in module


def test_spotlight_context_is_configurable_and_backward_compatible():
    module = text(PLUGIN / "includes/class-sc-library-homepage-spotlight.php")
    assert "'context' => 'library'" in module
    assert "array( 'library', 'publications' )" in module
    assert "'system_id' => 'PUB'" in module
    assert "'system_label' => __( 'Publications'" in module
    assert "'title' => __( 'Featured Publications'" in module
    assert "'system_id' => 'KL'" in module
    assert "'system_label' => __( 'Knowledge Library'" in module


def test_publications_context_drives_visible_and_accessible_identity():
    template = text(PLUGIN / "templates/homepage-spotlight.php")
    for token in [
        "$system_id",
        "$system_label",
        "$console_aria",
        "$subjects_aria",
        "$controls_aria",
        "'publications' === $context",
        "$default_record_label",
    ]:
        assert token in template
    assert "Curated Knowledge Library console" not in template
    assert ">KL</span>" not in template


def test_homepage_shortcode_usage_contract():
    module = text(PLUGIN / "includes/class-sc-library-homepage-spotlight.php")
    assert "Selected public work across the subjects currently featured by Sustainable Catalyst." in module
    assert "Publications spotlight navigation" in module
    assert "Publication subjects" in module
