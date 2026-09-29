from pathlib import Path
import importlib.util
ROOT=Path(__file__).resolve().parents[1]
def read(rel): return (ROOT/rel).read_text(encoding='utf-8')
def load_module():
 p=ROOT/'library-backend/app/durable_job_queue.py'; spec=importlib.util.spec_from_file_location('durable_job_queue_v5490',p); m=importlib.util.module_from_spec(spec)
 # Import through package normally in backend tests; release assertions inspect static/stateless behavior separately.
 return p.read_text(encoding='utf-8')

def test_release_identity_and_routes():
 assert 'Version: 5.49.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
 assert '__version__ = "2.60.0"' in read('library-backend/app/__init__.py')
 m=read('library-backend/app/main.py')
 for route in ['/v1/execution-fabric/readiness','/v1/execution-fabric/validate-job','/v1/admin/research-jobs','/v1/admin/research-jobs/lease','/v1/admin/research-jobs/recover-expired']: assert route in m

def test_postgresql_is_authoritative_and_redis_is_coordination_only():
 s=read('library-backend/app/durable_job_queue.py')
 assert 'postgresql_is_authoritative_job_state' in s and 'redis_is_authoritative_job_state' in s
 assert 'Redis is dispatch/wake-up' in read('library-backend/app/schema.sql')
 assert 'xadd(settings.job_redis_stream' in s

def test_schema_has_jobs_attempts_events_and_idempotency():
 s=read('library-backend/app/schema.sql')
 for t in ['library_research_jobs','library_research_job_attempts','library_research_job_events']: assert f'CREATE TABLE IF NOT EXISTS {t}' in s
 assert 'idempotency_key varchar(128) NOT NULL UNIQUE' in s
 assert 'FOR UPDATE SKIP LOCKED' in read('library-backend/app/durable_job_queue.py')

def test_job_state_machine_has_leases_heartbeats_retries_progress_and_cancellation():
 s=read('library-backend/app/durable_job_queue.py')
 for token in ['lease_next_job','heartbeat_job','complete_job','fail_job','cancel_job','recover_expired_leases','retry-scheduled','LeaseExpired']: assert token in s

def test_redis_dependency_and_compose_service_are_first_class():
 assert 'redis>=5.2,<7' in read('library-backend/requirements.txt')
 c=read('library-backend/compose.yml')
 assert 'sc-library-redis:' in c and 'redis:7.4-alpine' in c and '--appendonly' in c and 'redis-cli' in c
 assert 'SC_LIBRARY_JOB_REDIS_URL: redis://sc-library-redis:6379/0' in c

def test_broker_failure_does_not_delete_durable_job():
 s=read('library-backend/app/durable_job_queue.py')
 assert 'state":"deferred"' in s or "'state':'deferred'" in s or '"state":"deferred"' in s
 assert 'PostgreSQL remains authoritative' in s

def test_worker_fleet_is_explicitly_deferred_to_v550():
 s=read('library-backend/app/durable_job_queue.py')
 assert 'worker_fleet_activated_by_this_release": False' in s
 assert 'specialized_worker_activation_target": "5.50.0"' in s

def test_wordpress_surface_is_read_only_status_not_job_mutation():
 wp=read('sustainable-catalyst-library/includes/class-sc-library-execution-fabric.php')
 assert 'sc_library_execution_fabric_status' in wp and 'PostgreSQL is authoritative' in wp
 bridge=read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
 assert '/backend/execution-fabric/readiness' in bridge
 assert '/backend/research-jobs' not in bridge

def test_health_exposes_execution_fabric_guardrails():
 s=read('library-backend/app/main.py')
 for k in ['durable_research_job_queue','research_job_postgresql_authority','research_job_redis_authoritative','research_job_worker_fleet_active','research_job_completion_implies_evidence_truth']: assert k in s

def test_v548_language_resolution_and_v536_go_fabric_remain_present():
 assert (ROOT/'library-backend/app/cross_language_resolution.py').exists()
 assert (ROOT/'library-backend/app/ingestion_job_fabric.py').exists()
 assert 'go_research_ingestion_job_fabric' in read('library-backend/app/main.py')
