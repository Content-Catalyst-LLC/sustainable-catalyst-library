from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text()
def test_release_identity():
 assert 'Version: 5.50.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
 assert '__version__ = "2.61.0"' in read('library-backend/app/__init__.py')
def test_profiles():
 s=read('library-backend/app/specialized_worker_runtime.py')
 for x in ['python.research','go.ingestion','rust.graph','ocr.document','htr.document','speech.transcription','neural.inference','workspace.compute']: assert x in s
 assert 'standby_profiles_execute_without_adapter' in s
def test_persistence():
 s=read('library-backend/app/schema.sql'); assert 'library_research_workers' in s and 'library_research_dead_letters' in s
def test_failure_isolation():
 s=read('library-backend/app/specialized_worker_runtime.py'); assert 'isolate_worker_failure' in s and 'quarantine_worker' in s and 'worker_quarantine_threshold' in s
def test_capability_leasing():
 s=read('library-backend/app/specialized_worker_runtime.py'); assert 'lease_for_worker' in s and 'concurrency-limit' in s
def test_worker_services():
 c=read('library-backend/compose.yml')
 for x in ['sc-library-worker-python','sc-library-worker-go','sc-library-worker-rust']: assert x in c
def test_authority_guardrails():
 s=read('library-backend/app/specialized_worker_runtime.py'); assert 'postgresql_authoritative_worker_state' in s and 'redis_authoritative_worker_state' in s
def test_wordpress_read_only():
 b=read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php'); assert '/backend/worker-runtime/readiness' in b and '/backend/workers/register' not in b
def test_clean_root():
 assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
