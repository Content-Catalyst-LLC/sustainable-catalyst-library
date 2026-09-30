from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity_and_versions():
    assert '__version__ = "2.74.0"' in (ROOT/'library-backend/app/__init__.py').read_text()
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    assert 'Version: 5.63.0' in plugin and "SC_LIBRARY_VERSION', '5.63.0'" in plugin
    assert 'webVersion: "1.2.0"' in (ROOT/'library-web/config.js').read_text()

def test_clean_public_routes_replace_hash_only_navigation():
    js=(ROOT/'library-web/assets/app.js').read_text(); html=(ROOT/'library-web/index.html').read_text()
    assert 'location.pathname' in js and 'history.pushState' in js and 'data-app-link' in js
    assert 'href="/search"' in html and 'href="/discover"' in html
    assert '#/record/' not in js
    assert '/seo/records/' in js and "embed-mode" in js

def test_backend_public_contract_and_routes():
    main=(ROOT/'library-backend/app/main.py').read_text(); mod=(ROOT/'library-backend/app/public_routing.py').read_text()
    for path in ['/api/library/v1/public-routing','/api/library/v1/public-routing/readiness','/api/library/v1/seo/records/{record_id:path}','/api/library/v1/embed/records/{record_id:path}']:
        assert path in main
    assert 'https://library.sustainablecatalyst.com' in mod and 'SC_LIBRARY_PUBLIC_ORIGIN' in mod

def test_wordpress_is_bridge_not_proxy():
    bridge=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-public-routing-bridge.php').read_text()
    assert 'thin-adapter-public-bridge' in bridge
    assert "WP_REST_Server::READABLE" in bridge
    assert 'sc_library_public_launch' in bridge and 'sc_library_public_embed' in bridge
    assert 'sandbox="allow-scripts allow-same-origin allow-popups"' in bridge
    assert "'authoritative'=>false" in bridge and "'proxy_required'=>false" in bridge

def test_seo_and_embed_schemas_exist():
    for n in ['public-routing-seo-embed-bridge.json','public-routing-readiness.json','public-seo-descriptor.json','public-embed-descriptor.json']:
        assert (ROOT/'docs/schemas'/n).exists()
    assert (ROOT/'docs/architecture/public-routing-seo-embed-bridge.md').exists()

def test_independent_web_still_calls_backend_directly():
    nginx=(ROOT/'library-web/nginx.conf').read_text()
    assert 'sc-library-backend:8080' in nginx and 'wp-json' not in nginx.lower()

def test_clean_root():
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
