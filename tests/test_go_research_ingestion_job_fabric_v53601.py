from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel): return (ROOT / rel).read_text(encoding="utf-8")


def test_release_identity_and_go_runtime():
    assert "Version: 5.36.0.1" in read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert '__version__ = "2.47.1"' in read("library-backend/app/__init__.py")
    assert 'const (\n\tversion  = "0.1.0"' in read("library-backend/go-ingestion-runtime/main.go")
    assert 'contract = "sc-library-go-ingestion-runtime/1.0"' in read("library-backend/go-ingestion-runtime/main.go")


def test_go_fabric_operations_present():
    src=read("library-backend/go-ingestion-runtime/main.go")
    for token in ['"queued"','"running"','"retry_wait"','"completed"','"failed"','"cancelled"','IdempotencyKey','maxInFlight','maxQueue']:
        assert token in src
    assert 'job_state_implies_research_validity' in src


def test_python_api_and_wordpress_surfaces():
    main=read("library-backend/app/main.py")
    for route in ['/v1/runtime/ingestion-fabric/status','/v1/ingestion-jobs','/v1/ingestion-jobs/{job_id}','/v1/ingestion-jobs/{job_id}/cancel']:
        assert route in main
    assert 'await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)' in main
    bridge=read("sustainable-catalyst-library/includes/class-sc-library-python-backend.php")
    assert '/backend/ingestion-fabric-status' in bridge
    assert '/backend/ingestion-jobs' in bridge
    plugin=read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert 'class-sc-library-ingestion-job-fabric.php' in plugin
    assert 'SC_Library_Ingestion_Job_Fabric' in plugin
    assert "sc_library_ingestion_job_fabric" in read("sustainable-catalyst-library/includes/class-sc-library-ingestion-job-fabric.php")


def test_runtime_boundary_guardrails():
    adapter=read("library-backend/app/ingestion_job_fabric.py")
    assert 'job_completion_implies_source_validity' in adapter
    assert 'job_completion_implies_evidence_truth' in adapter
    assert 'ingestion_automatically_promotes_core_objects' in adapter
    assert 'worker_result_requires_python_validation' in adapter
    compose=read("library-backend/compose.yml")
    assert 'sc-library-ingestion:' in compose
    assert 'SC_LIBRARY_GO_INGESTION_URL' in compose
