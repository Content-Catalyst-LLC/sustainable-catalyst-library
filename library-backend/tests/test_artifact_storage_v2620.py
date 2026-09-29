import base64
from pathlib import Path
from tempfile import TemporaryDirectory
from app.artifact_storage import FilesystemArtifactStore, artifact_id_for_sha, build_artifact_manifest, content_sha256, guardrails, storage_key_for_sha, validate_artifact_payload

def payload(data=b'original research bytes'):
 return {'artifact_type':'source-document','media_type':'application/pdf','original_filename':'paper.pdf','content_base64':base64.b64encode(data).decode(),'provenance':{'source':'test'}}

def test_deterministic_identity_and_key():
 data=b'abc'; h=content_sha256(data)
 assert artifact_id_for_sha(h)==f'artifact:sha256:{h}'
 assert storage_key_for_sha(h)==f'sha256/{h[:2]}/{h[2:4]}/{h}'

def test_validation_and_manifest_do_not_echo_bytes():
 x=validate_artifact_payload(payload()); assert x['valid'] is True
 assert x['normalized']['byte_length']==len(b'original research bytes')
 assert 'content_base64' not in x['normalized']
 m=build_artifact_manifest(payload()); assert m['artifact']['artifact_id'].startswith('artifact:sha256:') and 'content_base64' not in str(m)

def test_bad_hash_and_bad_type_rejected():
 p=payload(); p['content_sha256']='0'*64
 assert validate_artifact_payload(p)['valid'] is False
 p=payload(); p['artifact_type']='truth-certificate'
 assert validate_artifact_payload(p)['valid'] is False

def test_filesystem_store_is_idempotent_and_integrity_preserving():
 data=b'bytes'; h=content_sha256(data); key=storage_key_for_sha(h)
 with TemporaryDirectory() as d:
  store=FilesystemArtifactStore(Path(d)); store.put(key,data,'application/octet-stream'); store.put(key,data,'application/octet-stream')
  assert store.exists(key) and store.get(key)==data and store.describe()['available'] is True

def test_guardrails():
 g=guardrails(); assert g['postgresql_authoritative_artifact_metadata'] is True and g['artifact_bytes_stored_outside_postgresql'] is True and g['artifact_presence_implies_evidence_truth'] is False and g['tombstone_physically_deletes_bytes'] is False
