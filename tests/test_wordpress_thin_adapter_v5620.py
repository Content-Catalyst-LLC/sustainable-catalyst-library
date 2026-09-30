from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity():
    assert '__version__ = "2.73.0"' in (ROOT/'library-backend/app/__init__.py').read_text()
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    assert 'Version: 5.62.0' in plugin and "SC_LIBRARY_VERSION', '5.62.0'" in plugin

def test_wordpress_thin_adapter_class_is_booted():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    adapter=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php').read_text()
    assert 'class-sc-library-wordpress-thin-adapter.php' in plugin
    assert 'SC_Library_WordPress_Thin_Adapter' in plugin
    assert "SC_LIBRARY_WORDPRESS_ROLE', 'thin-adapter'" in plugin
    assert "SC_LIBRARY_WORDPRESS_AUTHORITATIVE', false" in plugin
    assert "SC_LIBRARY_LEGACY_LOCAL_RESEARCH_AUTHORITY', false" in plugin
    assert "WP_REST_Server::READABLE" in adapter

def test_adapter_contract_enforces_allowlist_and_denylist():
    text=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php').read_text()
    for item in ['public-routing','seo-and-public-metadata','launch-and-embed-surfaces','health-and-status-display','optional-identity-handoff','legacy-presentation-compatibility']:
        assert item in text
    for item in ['research-object-authority','research-execution-authority','research-job-authority','artifact-authority','pipeline-authority','compute-authority','identity-authority','session-authority','credential-authority','federation-authority','trust-policy-authority','platform-core-promotion-authority']:
        assert item in text

def test_api_v1_exposes_adapter_contract_directly():
    main=(ROOT/'library-backend/app/main.py').read_text(); api=(ROOT/'library-backend/app/independent_api.py').read_text()
    assert '/api/library/v1/wordpress-adapter' in main and '/api/library/v1/wordpress-adapter/readiness' in main
    assert 'wordpress-adapter' in api and 'authority":"client-adapter"' in api

def test_independent_web_still_bypasses_wordpress():
    nginx=(ROOT/'library-web/nginx.conf').read_text(); compose=(ROOT/'library-web/compose.yml').read_text()
    assert 'sc-library-backend:8080' in nginx and 'wp-json' not in nginx.lower()
    assert '${SC_LIBRARY_WEB_BIND_PORT:-8092}' in compose

def test_schemas_and_architecture_exist():
    assert (ROOT/'docs/schemas/wordpress-thin-adapter.json').exists()
    assert (ROOT/'docs/schemas/wordpress-thin-adapter-readiness.json').exists()
    assert (ROOT/'docs/architecture/wordpress-thin-adapter.md').exists()

def test_clean_root():
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
