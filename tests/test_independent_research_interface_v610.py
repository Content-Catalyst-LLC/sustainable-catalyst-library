from pathlib import Path
import ast, json

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text()

def main():
    assert read("library-backend/app/__init__.py").strip() == '__version__ = "3.1.0"'

    service = read("library-backend/app/research_interface.py")
    ast.parse(service)
    for needle in [
        'LIBRARY_VERSION = "6.1.0"',
        'BACKEND_VERSION = "3.1.0"',
        'WEB_VERSION = "2.1.0"',
        'SDK_VERSION = "1.1.0"',
        'CONTRACT = "sc-library-independent-research-interface/1.0"',
        'def bootstrap()',
        'def search(',
        'def record_context(',
        'def owner_workspace(',
        '"research_interface_is_composition_layer": True',
        '"working_set_browser_state_is_authoritative": False',
        '"saved_projects_use_python_research_state_service": True',
        '"next_release": "6.2.0"',
    ]:
        assert needle in service, needle

    main_src = read("library-backend/app/main.py")
    for route in [
        "/api/library/v1/research-interface",
        "/api/library/v1/research-interface/readiness",
        "/api/library/v1/research-interface/bootstrap",
        "/api/library/v1/research-interface/search",
        "/api/library/v1/research-interface/records/{record_id:path}",
        "/api/library/v1/admin/research-interface/owners/{owner_identity_id:path}",
    ]:
        assert route in main_src, route
    for needle in [
        '"independent_library_research_interface": True',
        '"research_interface_authority": "python-backend"',
        '"library_web_research_route": "/research"',
        '"research_interface_wordpress_required": False',
    ]:
        assert needle in main_src, needle

    domain = read("library-backend/app/domain_authority.py")
    assert '"independent-research-interface": {' in domain
    assert '"web_version": "2.1.0"' in domain
    assert '"sdk_version": "1.1.0"' in domain

    api = read("library-backend/app/independent_api.py")
    assert '"library_version": "6.1.0"' in api
    assert '"backend_version": "3.1.0"' in api
    assert '"research-interface": {"resources":' in api

    product = read("library-backend/app/independent_library_product.py")
    assert 'LIBRARY_VERSION = "6.1.0"' in product
    assert 'BACKEND_VERSION = "3.1.0"' in product
    assert 'WEB_VERSION = "2.1.0"' in product
    assert 'SDK_VERSION = "1.1.0"' in product
    assert '"next_release": "6.2.0"' in product

    webapp = read("library-backend/app/web_application.py")
    assert 'WEB_VERSION = "2.1.0"' in webapp
    assert '{"id":"research","label":"Research","route":"/research","api":"/api/library/v1/research-interface","public":True,"index":False}' in webapp

    framework = read("library-backend/app/client_framework.py")
    assert 'SDK_VERSION = "1.1.0"' in framework
    assert '"independent_library_research_interface_client": True' in framework

    py_client = read("clients/python/sustainable_catalyst_library/client.py")
    for needle in [
        "def research_interface(self)",
        "def research_interface_readiness(self)",
        "def research_interface_bootstrap(self)",
        "def research_interface_search(",
        "def research_interface_record(",
        "def research_interface_owner_state(",
    ]:
        assert needle in py_client, needle

    js = read("clients/javascript/src/index.js")
    for needle in [
        "researchInterface()",
        "researchInterfaceReadiness()",
        "researchInterfaceBootstrap()",
        "researchInterfaceSearch(",
        "researchInterfaceRecord(",
        "researchInterfaceOwnerState(",
    ]:
        assert needle in js, needle

    dts = read("clients/javascript/src/index.d.ts")
    assert "researchInterface():Promise<any>;" in dts
    assert "researchInterfaceRecord(id:string" in dts

    index = read("library-web/index.html")
    assert 'data-nav="research"' in index
    assert 'data-view="research"' in index
    assert 'id="research-form"' in index
    assert 'id="working-set"' in index
    assert "Web v2.1.0 · API v1" in index

    app = read("library-web/assets/app.js")
    for needle in [
        'webVersion: "2.1.0"',
        '"research"',
        "loadResearchBootstrap",
        "researchSearch",
        "renderWorkingSet",
        "sc-library-research-working-set-v1",
        "research-interface/records/",
        "library-web-v2.1.0",
    ]:
        assert needle in app, needle

    css = read("library-web/assets/app.css")
    assert ".research-layout" in css
    assert ".working-set" in css

    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 6.1.0" in plugin
    assert "SC_LIBRARY_VERSION', '6.1.0'" in plugin
    assert "SC_LIBRARY_BACKEND_GENERATION', '3.1.0'" in plugin
    assert "SC_LIBRARY_WEB_GENERATION', '2.1.0'" in plugin
    assert "SC_LIBRARY_SDK_GENERATION', '1.1.0'" in plugin
    assert "SC_LIBRARY_RESEARCH_INTERFACE_AUTHORITY" in plugin
    assert "SC_LIBRARY_NEXT_ARCHITECTURE_RELEASE', '6.2.0'" in plugin

    adapter = read("sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php")
    assert "public const VERSION = '6.1.0';" in adapter
    assert "research-interface-composition-authority" in adapter
    assert "v6.2.0-unified-discovery-research-navigation" in adapter

    spec = json.loads(read("docs/library-api-v1-openapi.json"))
    for path in [
        "/research-interface",
        "/research-interface/readiness",
        "/research-interface/bootstrap",
        "/research-interface/search",
        "/research-interface/records/{record_id}",
        "/admin/research-interface/owners/{owner_identity_id}",
    ]:
        assert path in spec["paths"], path

    print("PASS: Library v6.1.0 Independent Library Research Interface backend/API/Web/SDK/optional-WordPress contract")

if __name__ == "__main__":
    main()
