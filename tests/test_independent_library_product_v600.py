from pathlib import Path
import ast, json

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text()

def main():
    assert read("library-backend/app/__init__.py").strip() == '__version__ = "3.0.0"'

    product = read("library-backend/app/independent_library_product.py")
    ast.parse(product)
    for needle in [
        'LIBRARY_VERSION = "6.0.0"',
        'BACKEND_VERSION = "3.0.0"',
        'WEB_VERSION = "2.0.0"',
        'SDK_VERSION = "1.0.0"',
        'API_VERSION = "1.0"',
        'CONTRACT = "sc-independent-sustainable-catalyst-knowledge-library/1.0"',
        '"application_mode": "independent-primary"',
        '"role": "optional-adapter"',
        '"api_v1_preserved": True',
        '"destructive_data_migration_required": False',
        '"next_release": "6.1.0"',
    ]:
        assert needle in product, needle

    runtime = read("library-backend/app/runtime_authority.py")
    assert '"version": "6.0.0"' in runtime
    assert '"backend_version": "3.0.0"' in runtime

    web = read("library-backend/app/web_application.py")
    assert 'WEB_VERSION = "2.0.0"' in web
    assert '"library_version": "6.0.0"' in web
    assert '"backend_version": "3.0.0"' in web
    assert '"state": "independent-primary-application"' in web

    api = read("library-backend/app/independent_api.py")
    assert '"library_version": "6.0.0"' in api
    assert '"backend_version": "3.0.0"' in api
    assert '"independent-library-product": {"resources":' in api
    for path in [
        "/api/library/v1/product",
        "/api/library/v1/product/readiness",
        "/api/library/v1/product/release",
    ]:
        assert path in api, path

    main_src = read("library-backend/app/main.py")
    for path in [
        "/api/library/v1/product",
        "/api/library/v1/product/readiness",
        "/api/library/v1/product/release",
    ]:
        assert path in main_src, path
    for needle in [
        '"independent_library_product": True',
        '"library_generation": 6',
        '"library_application_mode": "independent-primary"',
        '"wordpress_optional_adapter": True',
        '"api_v1_stable": True',
    ]:
        assert needle in main_src, needle

    framework = read("library-backend/app/client_framework.py")
    assert 'SDK_VERSION = "1.0.0"' in framework
    assert '"independent_library_product_client": True' in framework

    release = read("library-backend/app/release_engineering.py")
    assert '"web_version":"2.0.0"' in release

    py_client = read("clients/python/sustainable_catalyst_library/client.py")
    for needle in [
        "def library_product(self)",
        "def library_product_readiness(self)",
        "def library_product_release(self)",
    ]:
        assert needle in py_client, needle

    js = read("clients/javascript/src/index.js")
    for needle in [
        "libraryProduct()",
        "libraryProductReadiness()",
        "libraryProductRelease()",
    ]:
        assert needle in js, needle

    dts = read("clients/javascript/src/index.d.ts")
    assert "libraryProduct():Promise<any>;" in dts

    config = read("library-web/config.js")
    assert 'webVersion: "2.0.0"' in config

    index = read("library-web/index.html")
    assert "Web v2.0.0 · API v1" in index
    assert "Independent primary application" in index

    app = read("library-web/assets/app.js")
    assert 'webVersion: "2.0.0"' in app
    assert "library-web-v2.0.0" in app

    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 6.0.0" in plugin
    assert "SC_LIBRARY_VERSION', '6.0.0'" in plugin
    assert "SC_LIBRARY_WORDPRESS_ROLE', 'optional-adapter'" in plugin
    assert "SC_LIBRARY_APPLICATION_MODE', 'independent-primary'" in plugin
    assert "SC_LIBRARY_BACKEND_GENERATION', '3.0.0'" in plugin
    assert "SC_LIBRARY_WEB_GENERATION', '2.0.0'" in plugin
    assert "SC_LIBRARY_SDK_GENERATION', '1.0.0'" in plugin

    adapter = read("sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php")
    assert "public const VERSION = '6.0.0';" in adapter
    assert "public const ROLE = 'optional-adapter';" in adapter
    assert "'next_gate' => 'v6.1.0-independent-library-research-interface'" in adapter

    runtime_cert = read("sustainable-catalyst-library/includes/class-sc-library-runtime-certification.php")
    assert "VERSION='6.0.0'" in runtime_cert

    inventory = json.loads(read("docs/php-domain-retirement-inventory.json"))
    assert inventory["library_version"] == "6.0.0"
    product_meta = inventory.get("independent_library_product") or {}
    assert product_meta.get("application_mode") == "independent-primary"
    assert product_meta.get("wordpress_required") is False
    assert product_meta.get("php_domain_authority_files") == 0

    spec = json.loads(read("docs/library-api-v1-openapi.json"))
    for path in ["/product","/product/readiness","/product/release"]:
        assert path in spec["paths"], path

    print("PASS: Library v6.0.0 independent primary product contract, backend 3.0.0, web 2.0.0, SDK 1.0.0, API v1 compatibility and optional WordPress adapter")

if __name__ == "__main__":
    main()
