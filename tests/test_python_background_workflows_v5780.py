from pathlib import Path
import ast, json

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text()

def main():
    assert read("library-backend/app/__init__.py").strip() == '__version__ = "2.89.0"'

    src = read("library-backend/app/background_workflow_service.py")
    ast.parse(src)
    for needle in [
        'LIBRARY_VERSION = "5.78.0"',
        'BACKEND_VERSION = "2.89.0"',
        'CONTRACT = "sc-library-python-background-job-workflow-service/1.0"',
        'def readiness()',
        'def validate_control(',
        'def submit_background_job(',
        'def worker_snapshot(',
        'def recover_workflow_leases(',
        'def create_pipeline_workflow(',
        'def resume_pipeline_workflow(',
        '"postgresql_is_authoritative_job_state": True',
        '"redis_is_authoritative_job_state": False',
        '"workflow_service_is_second_job_scheduler": False',
        '"pipeline_completion_implies_research_truth": False',
        '"wordpress_php_is_background_workflow_authority": False',
    ]:
        assert needle in src, needle

    domain = read("library-backend/app/domain_authority.py")
    assert '"background-workflows": {' in domain
    assert '"authority": "python-backend", "state": "authoritative"' in domain
    assert '"postgresql_job_state_authority": True' in domain
    assert '"redis_job_state_authority": False' in domain

    runtime = read("library-backend/app/runtime_independence.py")
    assert '"workflow-service"' in runtime
    assert '5.78.0' in runtime and '2.89.0' in runtime

    main_src = read("library-backend/app/main.py")
    for route in [
        "/api/library/v1/workflows",
        "/api/library/v1/workflows/readiness",
        "/api/library/v1/admin/workflows/validate",
        "/api/library/v1/admin/workflows/jobs",
        "/api/library/v1/admin/workflows/jobs/{job_id}",
        "/api/library/v1/admin/workflows/jobs/{job_id}/cancel",
        "/api/library/v1/admin/workflows/workers",
        "/api/library/v1/admin/workflows/dead-letters",
        "/api/library/v1/admin/workflows/recover-expired-leases",
        "/api/library/v1/admin/workflows/pipelines/validate",
        "/api/library/v1/admin/workflows/pipelines",
        "/api/library/v1/admin/workflows/pipelines/{run_id}",
        "/api/library/v1/admin/workflows/pipelines/{run_id}/resume",
    ]:
        assert route in main_src, route

    for needle in [
        '"python_background_workflow_authority": True',
        '"research_job_authority": "python-backend"',
        '"pipeline_execution_authority": "python-backend"',
        '"worker_routing_authority": "python-backend"',
        '"postgresql_job_state_authority": True',
        '"redis_job_state_authority": False',
        '"workflow_authority":"python-backend"',
    ]:
        assert needle in main_src, needle

    api = read("library-backend/app/independent_api.py")
    assert '"library_version": "5.78.0"' in api
    assert '"backend_version": "2.89.0"' in api
    assert '"background-job-workflows": {"resources":' in api

    framework = read("library-backend/app/client_framework.py")
    assert 'SDK_VERSION = "0.10.0"' in framework
    assert '"background_job_workflow_client": True' in framework

    py_client = read("clients/python/sustainable_catalyst_library/client.py")
    for needle in [
        "def workflows(self)",
        "def workflows_readiness(self)",
        "def submit_workflow_job(",
        "def workflow_job(",
        "def workflow_workers(",
        "def recover_workflow_leases(",
        "def create_workflow_pipeline(",
        "def resume_workflow_pipeline(",
    ]:
        assert needle in py_client, needle

    js = read("clients/javascript/src/index.js")
    for needle in [
        'workflows(){return this.get("/workflows");}',
        "workflowsReadiness()",
        "submitWorkflowJob(payload)",
        "workflowJob(id)",
        "workflowWorkers()",
        "recoverWorkflowLeases(limit=100)",
        "createWorkflowPipeline(payload)",
        "resumeWorkflowPipeline(id)",
    ]:
        assert needle in js, needle

    dts = read("clients/javascript/src/index.d.ts")
    assert "workflows():Promise<any>;" in dts
    assert "submitWorkflowJob(payload:Record<string,unknown>):Promise<any>;" in dts

    adapter = read("sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php")
    for authority in [
        "background-workflow-authority",
        "worker-routing-authority",
        "pipeline-execution-authority",
    ]:
        assert authority in adapter, authority

    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.78.0" in plugin
    assert "SC_LIBRARY_VERSION', '5.78.0'" in plugin

    spec = json.loads(read("docs/library-api-v1-openapi.json"))
    for path in [
        "/workflows",
        "/workflows/readiness",
        "/admin/workflows/validate",
        "/admin/workflows/jobs",
        "/admin/workflows/jobs/{job_id}",
        "/admin/workflows/jobs/{job_id}/cancel",
        "/admin/workflows/workers",
        "/admin/workflows/dead-letters",
        "/admin/workflows/recover-expired-leases",
        "/admin/workflows/pipelines/validate",
        "/admin/workflows/pipelines",
        "/admin/workflows/pipelines/{run_id}",
        "/admin/workflows/pipelines/{run_id}/resume",
    ]:
        assert path in spec["paths"], path

    for path in [
        "docs/php-domain-retirement-inventory.json",
        "docs/schemas/library-background-workflow-service.json",
        "docs/schemas/library-workflow-control-validation.json",
    ]:
        json.load(open(ROOT / path))

    print("PASS: v5.78.0 Python background-job/workflow authority, API, SDK clients, runtime probe and WordPress boundary")

if __name__ == "__main__":
    main()
