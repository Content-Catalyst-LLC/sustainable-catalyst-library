from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'library-backend'))
from app import distributed_compute_broker as broker

def read(p): return (ROOT/p).read_text(encoding='utf-8')

def sample_job(capability='corpus.kwic', runtime='auto'):
    return {'job_type':'research-compute','capability':capability,'requested_runtime':runtime,'input_manifest':{'q':'x'},'idempotency_key':'v553-test'}

def sample_workers():
    return [
      {'worker_id':'python.research:a','worker_class':'python.research','state':'active','runtimes':['python'],'capabilities':['corpus.kwic'],'concurrency_limit':2,'active_attempts':1,'recent_failures':0,'recent_completed':8,'p50_latency_ms':50,'stale':False},
      {'worker_id':'python.research:b','worker_class':'python.research','state':'active','runtimes':['python'],'capabilities':['corpus.kwic'],'concurrency_limit':4,'active_attempts':0,'recent_failures':0,'recent_completed':3,'p50_latency_ms':90,'stale':False},
    ]

def snap(workers=None, queued=0, running=0, capq=0):
    return {'state':'ready','workers':sample_workers() if workers is None else workers,'queues':{'queued_retry':queued,'leased_running':running,'by_capability':{'corpus.kwic':capq},'by_runtime':{}}}

def test_release_identity_routes_and_wordpress_surface():
    assert 'Version: 5.53.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php') and '__version__ = "2.64.0"' in read('library-backend/app/__init__.py') and all(x in read('library-backend/app/main.py') for x in ['/v1/compute-broker/readiness','/v1/compute-broker/capabilities','/v1/compute-broker/observability','/v1/compute-broker/validate','/v1/compute-broker/plan','/v1/admin/compute-broker/submit','/v1/admin/compute-broker/observe']) and (ROOT/'sustainable-catalyst-library/includes/class-sc-library-compute-broker.php').exists()

def test_capability_catalog_preserves_active_and_standby_profiles():
    x=broker.capability_catalog(); rows={p['worker_class']:p for p in x['profiles']}; assert rows['python.research']['profile_active'] is True and rows['ocr.document']['profile_active'] is False and 'workspace.compute' in rows

def test_compute_request_validation_reuses_durable_job_contract():
    x=broker.validate_compute_request(sample_job()); assert x['valid'] is True and x['normalized']['job']['capability']=='corpus.kwic' and len(x['normalized']['compute_request_fingerprint_sha256'])==64

def test_standby_profile_is_not_silently_activated():
    x=broker.validate_compute_request(sample_job('document.ocr','ocr')); assert x['valid'] is False and 'no-active-compatible-runtime-profile' in x['errors']

def test_operational_placement_prefers_available_capacity_deterministically():
    x=broker.plan_compute(sample_job(),snap()); assert x['admission']['state']=='admitted' and x['selected']['worker_id']=='python.research:b' and x['selected']['runtime']=='python'

def test_busy_workers_still_allow_durable_queue_wait():
    busy=[{**w,'active_attempts':w['concurrency_limit']} for w in sample_workers()]; x=broker.plan_compute(sample_job(),snap(workers=busy)); assert x['admission']['state']=='admitted' and x['admission']['reason']=='admitted-durable-queue-awaiting-worker' and x['selected']['state']=='awaiting-worker'

def test_global_queue_backpressure_defers_without_claiming_scientific_failure():
    x=broker.plan_compute(sample_job(),snap(queued=broker.settings.compute_max_queued_jobs)); assert x['admission']['state']=='deferred' and x['admission']['reason']=='global-queue-backpressure' and x['guardrails']['queue_admission_implies_evidence_validity'] is False

def test_capability_queue_backpressure_is_bounded():
    x=broker.plan_compute(sample_job(),snap(capq=broker.settings.compute_max_capability_queue)); assert x['admission']['state']=='deferred' and x['admission']['reason']=='capability-queue-backpressure'

def test_runtime_metrics_are_not_quality_signals():
    g=broker.guardrails(); assert g['runtime_health_implies_research_quality'] is False and g['lower_latency_implies_better_research'] is False and g['runtime_success_implies_result_truth'] is False

def test_broker_records_worker_affinity_without_replacing_job_scheduler():
    dq=read('library-backend/app/durable_job_queue.py'); sw=read('library-backend/app/specialized_worker_runtime.py'); assert "resource_hints->'compute_broker'->>'selected_worker_class'" in dq and "'worker_class':w['worker_class']" in sw and broker.guardrails()['compute_broker_is_second_job_scheduler'] is False

def test_postgresql_observation_and_placement_tables_exist():
    s=read('library-backend/app/schema.sql'); assert 'CREATE TABLE IF NOT EXISTS library_compute_runtime_observations' in s and 'CREATE TABLE IF NOT EXISTS library_compute_placement_events' in s and 'admission_state IN' in s

def test_observability_tracks_capacity_latency_failures_and_queue_depth():
    s=read('library-backend/app/distributed_compute_broker.py'); assert 'p50_latency_ms' in s and 'recent_failures' in s and 'available_slots' in s and 'queued_retry' in s and 'leased_running' in s

def test_wordpress_observability_is_admin_only_and_status_is_public():
    s=read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php'); assert "'/backend/compute-broker/readiness'" in s and "'/backend/compute-broker/observability'" in s and "current_user_can('manage_options')" in s

def test_clean_repository_root_preserved():
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
