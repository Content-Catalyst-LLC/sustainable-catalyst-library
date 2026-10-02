from pathlib import Path
import ast, json

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text()

def main():
    assert read("library-backend/app/__init__.py").strip() == '__version__ = "2.91.0"'

    cert = read("library-backend/app/independent_application_certification.py")
    ast.parse(cert)
    for needle in [
        'LIBRARY_VERSION = "5.80.0"',
        'BACKEND_VERSION = "2.91.0"',
        'CONTRACT = "sc-library-independent-application-certification-service/1.0"',
        'CERTIFICATION_CONTRACT = "sc-library-independent-application-certification/1.0"',
        'def live_probe_snapshot()',
        'def build_certification(',
        '"wordpress_required_for_library_runtime": False',
        '"library_api_v1_is_application_boundary": True',
        '"legacy_php_compatibility_is_domain_authority": False',
        '"next_release": "6.0.0"',
    ]:
        assert needle in cert, needle

    runtime_authority = read("library-backend/app/runtime_authority.py")
    assert '"version": "5.80.0"' in runtime_authority
    assert '"backend_version": "2.91.0"' in runtime_authority
    assert "v5.80-runtime-authority-contract" in runtime_authority
    assert '"version": "5.58.0"' not in runtime_authority
    assert '"backend_version": "2.69.0"' not in runtime_authority

    web = read("library-backend/app/web_application.py")
    assert '"library_version": "5.80.0"' in web
    assert '"backend_version": "2.91.0"' in web
    assert '"library_version": "5.66.0"' not in web
    assert '"backend_version": "2.77.0"' not in web

    main_src = read("library-backend/app/main.py")
    for route in [
        "/api/library/v1/independent-application",
        "/api/library/v1/independent-application/readiness",
        "/api/library/v1/independent-application/certification",
        "/api/library/v1/admin/independent-application/certify",
    ]:
        assert route in main_src, route
    for needle in [
        '"independent_application_certification": True',
        '"independent_application_certification_authority": "python-backend"',
        '"wordpress_required_for_independent_application": False',
        '"independent_application_next_release": "6.0.0"',
    ]:
        assert needle in main_src, needle

    domain = read("library-backend/app/domain_authority.py")
    assert '"independent-application-certification": {' in domain
    assert '"5.80.0", "Independent Library Application Certification"' in domain

    api = read("library-backend/app/independent_api.py")
    assert '"library_version": "5.80.0"' in api
    assert '"backend_version": "2.91.0"' in api
    assert '"independent-application-certification": {"resources":' in api

    framework = read("library-backend/app/client_framework.py")
    assert 'SDK_VERSION = "0.12.0"' in framework
    assert '"independent_application_certification_client": True' in framework

    py_client = read("clients/python/sustainable_catalyst_library/client.py")
    for needle in [
        "def independent_application(self)",
        "def independent_application_readiness(self)",
        "def independent_application_certification(self)",
        "def certify_independent_application(",
    ]:
        assert needle in py_client, needle

    js = read("clients/javascript/src/index.js")
    for needle in [
        "independentApplication()",
        "independentApplicationReadiness()",
        "independentApplicationCertification()",
        "certifyIndependentApplication(payload={})",
    ]:
        assert needle in js, needle

    dts = read("clients/javascript/src/index.d.ts")
    assert "independentApplication():Promise<any>;" in dts
    assert "certifyIndependentApplication(payload?:Record<string,unknown>):Promise<any>;" in dts

    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.80.0" in plugin
    assert "SC_LIBRARY_VERSION', '5.80.0'" in plugin
    assert "SC_LIBRARY_INDEPENDENT_APPLICATION_CERTIFICATION_AUTHORITY" in plugin
    assert "SC_LIBRARY_INDEPENDENT_APPLICATION_WORDPRESS_REQUIRED" in plugin
    assert "SC_LIBRARY_NEXT_ARCHITECTURE_RELEASE" in plugin

    wp_adapter = read("sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php")
    assert "public const VERSION = '5.80.0';" in wp_adapter
    assert "v6.0.0-independent-sustainable-catalyst-knowledge-library" in wp_adapter

    runtime_cert = read("sustainable-catalyst-library/includes/class-sc-library-runtime-certification.php")
    assert "VERSION='5.80.0'" in runtime_cert

    inventory = json.loads(read("docs/php-domain-retirement-inventory.json"))
    assert inventory["library_version"] == "5.80.0"
    independence = inventory.get("independent_application_certification") or {}
    assert independence.get("wordpress_required_for_runtime") is False
    assert independence.get("php_domain_authority_files") == 0
    assert independence.get("next_release") == "6.0.0"

    spec = json.loads(read("docs/library-api-v1-openapi.json"))
    for path in [
        "/independent-application",
        "/independent-application/readiness",
        "/independent-application/certification",
        "/admin/independent-application/certify",
    ]:
        assert path in spec["paths"], path

    print("PASS: v5.80.0 independent Library application certification service, release identity repairs, API/SDK clients and WordPress-optional boundary")

if __name__ == "__main__":
    main()
