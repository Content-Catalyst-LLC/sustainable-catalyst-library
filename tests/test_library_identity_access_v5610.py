from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity_and_versions():
    assert '__version__ = "2.72.0"' in (ROOT/'library-backend/app/__init__.py').read_text()
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    assert 'Version: 5.61.0' in plugin and "SC_LIBRARY_VERSION', '5.61.0'" in plugin
    assert 'webVersion: "1.1.0"' in (ROOT/'library-web/config.js').read_text()

def test_identity_tables_are_library_owned():
    schema=(ROOT/'library-backend/app/schema.sql').read_text()
    for table in ['library_identities','library_identity_credentials','library_identity_role_bindings','library_sessions','library_access_grants','library_identity_events']:
        assert f'CREATE TABLE IF NOT EXISTS {table}' in schema

def test_session_security_contract_is_explicit():
    code=(ROOT/'library-backend/app/identity_access.py').read_text()
    assert 'argon2' in code.lower() and 'token_sha256' in code and 'csrf_sha256' in code and 'locked_until' in code
    assert 'wordpress_identity_is_authoritative": False' in code
    assert 'session_cookie_http_only": True' in code

def test_api_v1_identity_session_access_routes():
    main=(ROOT/'library-backend/app/main.py').read_text()
    for route in ['/api/library/v1/identity','/api/library/v1/identity/readiness','/api/library/v1/session/login','/api/library/v1/session/logout','/api/library/v1/access/evaluate']:
        assert route in main

def test_web_account_surface_is_service_native():
    html=(ROOT/'library-web/index.html').read_text(); js=(ROOT/'library-web/assets/app.js').read_text(); nginx=(ROOT/'library-web/nginx.conf').read_text()
    assert 'data-view="account"' in html and '/session/login' in js and '/session/logout' in js
    assert 'WordPress users and cookies are not authoritative Library sessions' in html
    assert 'proxy_pass_header Set-Cookie' in nginx

def test_wordpress_is_identity_bridge_not_authority():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    adapter=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-identity-access.php').read_text()
    assert 'class-sc-library-identity-access.php' in plugin
    assert "'wordpress_identity_authoritative' => false" in adapter
    assert "'library_session_authority' => 'library-service'" in adapter

def test_clean_root():
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
