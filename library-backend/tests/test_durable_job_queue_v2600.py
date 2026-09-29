from app.durable_job_queue import build_job_package, guardrails, validate_job_payload

def fixture():
 return {'job_type':'pdf-ingest','capability':'document.ingest','requested_runtime':'auto','priority':15,'input_manifest':{'source_uri':'https://example.org/a.pdf','sha256':'a'*64},'provenance_context':{'requested_by':'test'}}

def test_deterministic_job_package_and_idempotency():
 a=build_job_package(fixture()); b=build_job_package(fixture()); assert a['job_id']==b['job_id']; assert a['idempotency_key']==b['idempotency_key']; assert a['state']=='queued'

def test_explicit_idempotency_key_controls_identity():
 p=fixture(); p['idempotency_key']='import:source:123'; a=build_job_package(p); b=build_job_package(p); assert a['job_id']==b['job_id'] and a['idempotency_key']=='import:source:123'

def test_invalid_runtime_and_governed_promotion_are_rejected():
 p=fixture(); p['requested_runtime']='magic'; p['automatic_truth_promotion']=True; v=validate_job_payload(p); assert not v['valid']; assert 'invalid-requested-runtime' in v['errors']; assert 'automatic-governed-promotion-prohibited' in v['errors']

def test_guardrails_keep_redis_non_authoritative():
 g=guardrails(); assert g['postgresql_is_authoritative_job_state'] is True; assert g['redis_is_authoritative_job_state'] is False; assert g['redis_dispatch_loss_loses_job'] is False

def test_worker_fleet_is_not_faked_by_foundation_release():
 g=guardrails(); assert g['worker_fleet_activated_by_this_release'] is False; assert g['specialized_worker_activation_target']=='5.50.0'
