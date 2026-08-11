from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'


def text(path):
    return path.read_text(encoding='utf-8')


def test_patch_identity_and_upgrade_hook():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    activator = text(PLUGIN / 'includes/class-sc-library-activator.php')
    assert 'Version: 4.3.18.1' in main
    assert "SC_LIBRARY_VERSION', '4.3.18.1" in main
    assert 'Stable tag: 4.3.18.1' in readme
    assert 'repair_publication_surface_integrity' in activator
    assert activator.count('self::repair_publication_surface_integrity();') == 3


def test_canonical_registry_still_has_full_publications_model():
    reg = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    assert reg.count("'url' =>") == 170
    fields = re.findall(r"'field' => '([^']+)'", reg)
    assert len(set(fields)) == 14
    assert fields.count('Global Governance') == 13
    assert fields.count('Literature & Cultural Memory') == 18
    assert fields.count('Philosophy') == 27


def test_repair_is_bounded_and_preserves_editorial_payloads():
    src = text(PLUGIN / 'includes/class-sc-library-activator.php')
    for token in [
        'visible_fields <= 1',
        'visible_field_count <= 1',
        'configured_visibility > 1 && $visible_panels <= 1',
        "sc_library_publications_integrity_repair_v43181",
        "sc_library_publications_topics_v433",
        "sc_library_field_spotlights_model_v4313",
        "sc_library_field_spotlights_public_v4313",
    ]:
        assert token in src
    assert "unset( $fs_panels" not in src
    assert "delete_option( $pub_option" not in src
    assert "delete_option( $fs_option" not in src


def test_php_fixture_recovers_collapsed_state():
    result = subprocess.run(
        ['php', str(ROOT / 'tests/test_publications_integrity_recovery_v43181.php')],
        cwd=ROOT,
        text=True,
        capture_output=True,
    )
    assert result.returncode == 0, result.stderr + result.stdout
    assert 'PASS:' in result.stdout
