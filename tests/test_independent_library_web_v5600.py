from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]


def test_release_identity_and_artifacts():
    assert '__version__ = "2.71.0"' in (ROOT/'library-backend/app/__init__.py').read_text()
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    assert 'Version: 5.60.0' in plugin and "SC_LIBRARY_VERSION', '5.60.0'" in plugin
    assert (ROOT/'library-web/index.html').is_file()
    assert (ROOT/'library-web/Dockerfile').is_file()
    assert (ROOT/'library-web/compose.yml').is_file()


def test_web_is_independent_of_wordpress():
    app=(ROOT/'library-web/assets/app.js').read_text()
    nginx=(ROOT/'library-web/nginx.conf').read_text()
    compose=(ROOT/'library-web/compose.yml').read_text()
    assert '/api/library/v1' in app
    assert 'sc-library-backend:8080' in nginx
    assert 'sc-library-web:' in compose
    assert 'wordpress' not in app.lower()
    assert 'wp-json' not in nginx.lower()


def test_foundation_surfaces_exist():
    html=(ROOT/'library-web/index.html').read_text()
    js=(ROOT/'library-web/assets/app.js').read_text()
    for surface in ['data-view="search"','data-view="record"','data-view="discover"','data-view="system"']:
        assert surface in html
    for route in ['/search','/records/','/capabilities','/runtime-authority','/web-application/readiness']:
        assert route in js


def test_web_security_baseline():
    nginx=(ROOT/'library-web/nginx.conf').read_text()
    for header in ['Content-Security-Policy','X-Content-Type-Options','Referrer-Policy','Permissions-Policy']:
        assert header in nginx
    config=(ROOT/'library-web/config.js').read_text().lower()
    assert 'api_key' not in config and 'secret' not in config and 'token' not in config


def test_manifest_and_backend_contract():
    manifest=json.loads((ROOT/'library-web/manifest.webmanifest').read_text())
    assert manifest['start_url']=='/#/search' and manifest['display']=='standalone'
    main=(ROOT/'library-backend/app/main.py').read_text()
    assert '/api/library/v1/web-application' in main
    assert '/api/library/v1/web-application/readiness' in main
    assert '"independent_library_web_application": True' in main


def test_wordpress_is_adapter_not_app_runtime():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    assert "SC_LIBRARY_WORDPRESS_ROLE', 'publishing-routing-embed-adapter'" in plugin
    assert 'class-sc-library-web-application.php' in plugin


def test_clean_root():
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
