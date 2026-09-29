from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')

def test_release_identity_and_routes():
 assert 'Version: 5.51.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
 assert '__version__ = "2.62.0"' in read('library-backend/app/__init__.py')
 m=read('library-backend/app/main.py')
 for route in ['/v1/artifact-storage/readiness','/v1/artifact-storage/validate','/v1/artifact-storage/manifest','/v1/admin/artifacts/{artifact_id:path}/content','/v1/admin/artifacts/{artifact_id:path}/verify','/v1/admin/artifacts/{artifact_id:path}/lifecycle']:
  assert route in m

def test_artifact_contracts_and_content_addressing():
 s=read('library-backend/app/artifact_storage.py')
 for token in ['sc-library-research-artifact/1.0','sc-library-artifact-derivation/1.0','sc-library-artifact-storage-readiness/1.0','artifact:sha256:','sha256/{digest[:2]}/{digest[2:4]}/{digest}']:
  assert token in s

def test_postgresql_metadata_and_provenance_tables():
 s=read('library-backend/app/schema.sql')
 for table in ['library_research_artifacts','library_artifact_derivations','library_artifact_events']:
  assert f'CREATE TABLE IF NOT EXISTS {table}' in s
 assert 'storage_backend' in s and 'content_sha256' in s and 'immutable boolean' in s

def test_bytes_are_outside_postgresql():
 s=read('library-backend/app/artifact_storage.py')
 assert 'artifact_bytes_stored_outside_postgresql' in s
 assert 'postgresql_authoritative_artifact_metadata' in s
 assert 'content_addressed_identity' in s and 'artifact_content_is_immutable' in s

def test_filesystem_and_s3_compatible_backends():
 s=read('library-backend/app/artifact_storage.py')
 assert 'FilesystemArtifactStore' in s and 'S3ArtifactStore' in s and 'boto3.client' in s
 assert 'SC_LIBRARY_ARTIFACT_STORE_BACKEND' in read('library-backend/.env.example')
 assert 'boto3>=1.40,<2' in read('library-backend/requirements.txt')

def test_shared_artifact_volume():
 c=read('library-backend/compose.yml')
 assert 'sc-library-artifact-data:' in c
 assert c.count('sc-library-artifact-data:/data/artifacts') >= 4

def test_lifecycle_does_not_silently_delete_bytes():
 s=read('library-backend/app/artifact_storage.py')
 assert 'tombstone_physically_deletes_bytes' in s
 assert 'LIFECYCLE_STATES = {"active", "retained", "quarantined", "tombstoned"}' in s

def test_worker_integration():
 s=read('library-backend/app/specialized_worker_runtime.py')
 assert "'artifact.persist'" in s and "'artifact.verify'" in s
 assert "kind':'artifact-persist'" in s and "kind':'artifact-verify'" in s

def test_wordpress_is_read_only_storage_status():
 b=read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
 assert '/backend/artifact-storage/readiness' in b
 assert '/backend/artifacts' not in b
 assert (ROOT/'sustainable-catalyst-library/includes/class-sc-library-artifact-storage.php').exists()

def test_health_guardrails():
 s=read('library-backend/app/main.py')
 for k in ['research_artifact_object_storage_fabric','research_artifact_content_addressed','research_artifact_sha256_integrity','research_artifact_derivation_lineage','research_artifact_postgresql_bytes','research_artifact_presence_implies_evidence_truth']:
  assert k in s

def test_clean_root():
 assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
