from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity_and_assets():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    cls=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php').read_text()
    assert 'Version: 5.21.0' in plugin
    assert "public const VERSION = '5.21.0'" in cls
    assert 'sc-library-knowledge-landscape-v5210' in cls

def test_session_ui_and_workspace_package_are_present():
    php=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php').read_text()
    js=(ROOT/'sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5210.js').read_text()
    assert 'data-sc-kl-session-save' in php
    assert 'data-sc-kl-session-restore' in php
    assert 'data-sc-kl-session-download' in php
    assert 'data-sc-kl-session-workspace' in php
    assert 'localStorage' in js
    assert 'sc-library-reproducible-visual-state/1.0' in js
    assert 'sessionPackage(workspace=false)' in js

def test_wp_proxy_and_backend_endpoint_exist():
    wp=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-python-backend.php').read_text()
    main=(ROOT/'library-backend/app/main.py').read_text()
    assert '/backend/publication-visual-session-package' in wp
    assert 'proxy_publication_visual_session_package' in wp
    assert '@app.post("/v1/publication-knowledge-maps/session-package")' in main
