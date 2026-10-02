from pathlib import Path
import ast, json

ROOT = Path(__file__).resolve().parents[1]
def read(rel): return (ROOT / rel).read_text()

def main():
    assert read("library-backend/app/__init__.py").strip() == '__version__ = "3.2.0"'

    nav = read("library-backend/app/navigation_service.py")
    ast.parse(nav)
    for needle in [
        'LIBRARY_VERSION = "6.2.0"',
        'BACKEND_VERSION = "3.2.0"',
        'WEB_VERSION = "2.2.0"',
        'SDK_VERSION = "1.2.0"',
        'CONTRACT = "sc-library-unified-discovery-research-navigation/1.0"',
        'def navigation_model()',
        'def bootstrap()',
        'def resolve_route(',
        '"legacy_search_route_remains_supported": True',
        '"legacy_discover_route_remains_supported": True',
        '"legacy_routes_are_primary_navigation": False',
        '"next_release": "6.3.0"',
    ]:
        assert needle in nav, needle

    main_src = read("library-backend/app/main.py")
    for route in [
        "/api/library/v1/navigation",
        "/api/library/v1/navigation/readiness",
        "/api/library/v1/navigation/bootstrap",
        "/api/library/v1/navigation/resolve",
    ]:
        assert route in main_src, route
    for needle in [
        '"unified_discovery_research_navigation": True',
        '"navigation_authority": "python-backend-composition"',
        '"canonical_research_route": "/research"',
        '"legacy_search_discover_routes_supported": True',
    ]:
        assert needle in main_src, needle

    api = read("library-backend/app/independent_api.py")
    assert '"library_version": "6.2.0"' in api
    assert '"backend_version": "3.2.0"' in api
    assert '"unified-navigation": {"resources":' in api

    domain = read("library-backend/app/domain_authority.py")
    assert '"unified-discovery-research-navigation": {' in domain
    assert '"web_version": "2.2.0"' in domain
    assert '"sdk_version": "1.2.0"' in domain

    research = read("library-backend/app/research_interface.py")
    assert 'LIBRARY_VERSION = "6.2.0"' in research
    assert 'BACKEND_VERSION = "3.2.0"' in research
    assert 'WEB_VERSION = "2.2.0"' in research
    assert 'SDK_VERSION = "1.2.0"' in research
    assert '"next_release": "6.3.0"' in research

    product = read("library-backend/app/independent_library_product.py")
    assert 'LIBRARY_VERSION = "6.2.0"' in product
    assert 'BACKEND_VERSION = "3.2.0"' in product
    assert 'WEB_VERSION = "2.2.0"' in product
    assert 'SDK_VERSION = "1.2.0"' in product
    assert '"next_release": "6.3.0"' in product

    webapp = read("library-backend/app/web_application.py")
    assert 'WEB_VERSION = "2.2.0"' in webapp
    assert '"library_version": "6.2.0"' in webapp
    assert '"backend_version": "3.2.0"' in webapp
    assert '"alias_of":"research"' in webapp

    framework = read("library-backend/app/client_framework.py")
    assert 'SDK_VERSION = "1.2.0"' in framework
    assert '"unified_discovery_research_navigation_client": True' in framework

    py_client = read("clients/python/sustainable_catalyst_library/client.py")
    for needle in ["def navigation(self)", "def navigation_readiness(self)", "def navigation_bootstrap(self)", "def resolve_navigation("]:
        assert needle in py_client, needle

    js = read("clients/javascript/src/index.js")
    for needle in ["navigation()", "navigationReadiness()", "navigationBootstrap()", "resolveNavigation("]:
        assert needle in js, needle

    dts = read("clients/javascript/src/index.d.ts")
    assert "navigation():Promise<any>;" in dts
    assert "resolveNavigation(path:string):Promise<any>;" in dts

    index = read("library-web/index.html")
    assert '<a data-nav="research" href="/research">Research</a>' in index
    assert '<a data-nav="search"' not in index
    assert '<a data-nav="discover"' not in index
    assert 'id="research-navigation"' in index
    assert 'id="research-pathways"' in index
    assert "Web v2.2.0 · API v1" in index

    app = read("library-web/assets/app.js")
    for needle in [
        'webVersion: "2.2.0"',
        "loadUnifiedNavigation",
        "resolveUnifiedResearchMode",
        "renderResearchPathways",
        'name === "search" || name === "discover"',
        'library-web-v2.2.0',
    ]:
        assert needle in app, needle

    css = read("library-web/assets/app.css")
    assert ".research-navigation" in css
    assert ".research-pathways" in css

    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 6.2.0" in plugin
    assert "SC_LIBRARY_VERSION', '6.2.0'" in plugin
    assert "SC_LIBRARY_BACKEND_GENERATION', '3.2.0'" in plugin
    assert "SC_LIBRARY_WEB_GENERATION', '2.2.0'" in plugin
    assert "SC_LIBRARY_SDK_GENERATION', '1.2.0'" in plugin
    assert "SC_LIBRARY_NAVIGATION_AUTHORITY" in plugin
    assert "SC_LIBRARY_NEXT_ARCHITECTURE_RELEASE', '6.3.0'" in plugin

    adapter = read("sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php")
    assert "public const VERSION = '6.2.0';" in adapter
    assert "discovery-navigation-authority" in adapter
    assert "v6.3.0-research-projects-saved-workspaces" in adapter

    spec = json.loads(read("docs/library-api-v1-openapi.json"))
    for path in ["/navigation", "/navigation/readiness", "/navigation/bootstrap", "/navigation/resolve"]:
        assert path in spec["paths"], path

    print("PASS: Library v6.2.0 Unified Discovery & Research Navigation backend/API/Web/SDK/optional-WordPress contract")

if __name__ == "__main__":
    main()
