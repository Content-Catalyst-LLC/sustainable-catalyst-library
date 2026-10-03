from pathlib import Path
import ast, json

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text()

def main():
    assert read("library-backend/app/__init__.py").strip() == '__version__ = "3.3.0"'

    svc = read("library-backend/app/saved_workspaces.py")
    ast.parse(svc)
    for needle in [
        'LIBRARY_VERSION = "6.3.0"',
        'BACKEND_VERSION = "3.3.0"',
        'WEB_VERSION = "2.3.0"',
        'SDK_VERSION = "1.3.0"',
        'CONTRACT = "sc-library-research-projects-saved-workspaces/1.0"',
        'def workspace_for_owner(',
        'def project_context(',
        'def save_project(',
        'def save_working_set(',
        'def save_search(',
        'def save_collection(',
        '"saved_workspace_is_new_persistence_authority": False',
        '"workspace_mutations_require_csrf": True',
        '"database_migration_required": False',
        '"next_release": "6.4.0"',
    ]:
        assert needle in svc, needle

    main_src = read("library-backend/app/main.py")
    for route in [
        "/api/library/v1/saved-workspaces",
        "/api/library/v1/saved-workspaces/readiness",
        "/api/library/v1/workspaces",
        "/api/library/v1/workspaces/projects/{project_id:path}",
        "/api/library/v1/workspaces/projects",
        "/api/library/v1/workspaces/projects/{project_id:path}/working-set",
        "/api/library/v1/workspaces/saved-searches",
        "/api/library/v1/workspaces/collections",
        "/api/library/v1/workspaces/collections/{collection_id:path}/items",
    ]:
        assert route in main_src, route
    for needle in [
        '"research_projects_saved_workspaces": True',
        '"saved_workspace_authority": "python-research-state-service"',
        '"saved_workspace_session_auth": True',
        '"saved_workspace_csrf_mutations": True',
        '"saved_workspace_database_migration_required": False',
        '"library_web_version": "2.3.0"',
        '"library_sdk_version": "1.3.0"',
    ]:
        assert needle in main_src, needle

    state = read("library-backend/app/research_state.py")
    assert 'LIBRARY_VERSION = "6.3.0"' in state
    assert 'BACKEND_VERSION = "3.3.0"' in state

    nav = read("library-backend/app/navigation_service.py")
    assert 'LIBRARY_VERSION = "6.3.0"' in nav
    assert 'BACKEND_VERSION = "3.3.0"' in nav
    assert 'WEB_VERSION = "2.3.0"' in nav
    assert 'SDK_VERSION = "1.3.0"' in nav
    assert '"next_release": "6.4.0"' in nav
    assert '"projects"' in nav
    assert '/account?section=workspaces' in nav

    research = read("library-backend/app/research_interface.py")
    assert 'WEB_VERSION = "2.3.0"' in research
    assert 'SDK_VERSION = "1.3.0"' in research
    assert '"next_release": "6.4.0"' in research

    product = read("library-backend/app/independent_library_product.py")
    assert 'WEB_VERSION = "2.3.0"' in product
    assert 'SDK_VERSION = "1.3.0"' in product
    assert '"next_release": "6.4.0"' in product

    webapp = read("library-backend/app/web_application.py")
    assert 'WEB_VERSION = "2.3.0"' in webapp
    assert '"library_version": "6.3.0"' in webapp
    assert '"backend_version": "3.3.0"' in webapp
    assert '{"id":"workspaces","label":"Workspaces","route":"/account?section=workspaces"' in webapp

    domain = read("library-backend/app/domain_authority.py")
    assert '"research-projects-saved-workspaces": {' in domain
    assert '"web_version": "2.3.0"' in domain
    assert '"sdk_version": "1.3.0"' in domain

    api = read("library-backend/app/independent_api.py")
    assert '"library_version": "6.3.0"' in api
    assert '"backend_version": "3.3.0"' in api
    assert '"saved-workspaces": {"resources":' in api
    assert '"access":"library-session"' in api

    framework = read("library-backend/app/client_framework.py")
    assert 'SDK_VERSION = "1.3.0"' in framework
    assert '"research_projects_saved_workspaces_client": True' in framework

    py_client = read("clients/python/sustainable_catalyst_library/client.py")
    for needle in [
        "def saved_workspaces(self)",
        "def saved_workspaces_readiness(self)",
    ]:
        assert needle in py_client, needle

    js = read("clients/javascript/src/index.js")
    assert "savedWorkspaces()" in js
    assert "savedWorkspacesReadiness()" in js

    dts = read("clients/javascript/src/index.d.ts")
    assert "savedWorkspaces():Promise<any>;" in dts

    index = read("library-web/index.html")
    for needle in [
        'data-research-mode-link="projects"',
        'id="working-set-project"',
        'id="working-set-save"',
        'id="research-save-search"',
        'id="workspace-card"',
        'id="workspace-project-form"',
        'id="workspace-project-list"',
        "Web v2.3.0 · API v1",
    ]:
        assert needle in index, needle

    app = read("library-web/assets/app.js")
    for needle in [
        'webVersion: "2.3.0"',
        "workspaceSnapshot",
        "loadSavedWorkspaces",
        "renderSavedWorkspaces",
        "createWorkspaceProject",
        "saveWorkingSetToProject",
        "saveCurrentResearchSearch",
        '"/workspaces/projects"',
        '"/workspaces/saved-searches"',
        "library-web-v2.3.0",
    ]:
        assert needle in app, needle

    css = read("library-web/assets/app.css")
    assert ".workspace-projects" in css
    assert ".workspace-project-card" in css
    assert ".working-set-save" in css

    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 6.3.0" in plugin
    assert "SC_LIBRARY_VERSION', '6.3.0'" in plugin
    assert "SC_LIBRARY_BACKEND_GENERATION', '3.3.0'" in plugin
    assert "SC_LIBRARY_WEB_GENERATION', '2.3.0'" in plugin
    assert "SC_LIBRARY_SDK_GENERATION', '1.3.0'" in plugin
    assert "SC_LIBRARY_SAVED_WORKSPACE_AUTHORITY" in plugin
    assert "SC_LIBRARY_NEXT_ARCHITECTURE_RELEASE', '6.4.0'" in plugin

    adapter = read("sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php")
    assert "public const VERSION = '6.3.0';" in adapter
    assert "saved-workspace-composition-authority" in adapter
    assert "v6.4.0-project-detail-source-bundles-collections" in adapter

    spec = json.loads(read("docs/library-api-v1-openapi.json"))
    for path in [
        "/saved-workspaces",
        "/saved-workspaces/readiness",
        "/workspaces",
        "/workspaces/projects/{project_id}",
        "/workspaces/projects",
        "/workspaces/projects/{project_id}/working-set",
        "/workspaces/saved-searches",
        "/workspaces/collections",
        "/workspaces/collections/{collection_id}/items",
    ]:
        assert path in spec["paths"], path

    print("PASS: Library v6.3.0 Research Projects & Saved Workspaces backend/API/Web/SDK/optional-WordPress contract")

if __name__ == "__main__":
    main()
