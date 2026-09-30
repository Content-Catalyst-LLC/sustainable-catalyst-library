from app.distributed_compute_broker import capability_catalog, guardrails, plan_compute, validate_compute_request

def job(): return {'job_type':'compute','capability':'graph.query','requested_runtime':'rust','idempotency_key':'backend-v2640','input_manifest':{}}

def snapshot(slots=1):
 return {'state':'ready','workers':[{'worker_id':'rust.graph:1','worker_class':'rust.graph','state':'active','runtimes':['rust'],'capabilities':['graph.query'],'concurrency_limit':2,'active_attempts':2-slots,'recent_failures':0,'recent_completed':2,'stale':False}], 'queues':{'queued_retry':0,'leased_running':0,'by_capability':{'graph.query':0},'by_runtime':{}}}

def test_catalog():
 x=capability_catalog(); assert x['schema']=='sc-library-compute-capability-catalog/1.0' and x['version']=='5.53.0' and x['backend_version']=='2.64.0'

def test_validation():
 x=validate_compute_request(job()); assert x['valid'] is True and x['normalized']['job']['requested_runtime']=='rust'

def test_placement():
 x=plan_compute(job(),snapshot()); assert x['schema']=='sc-library-compute-placement-decision/1.0' and x['admission']['state']=='admitted' and x['selected']['worker_class']=='rust.graph'

def test_queue_wait():
 x=plan_compute(job(),snapshot(slots=0)); assert x['admission']['state']=='admitted' and x['selected']['state']=='awaiting-worker'

def test_guardrails():
 g=guardrails(); assert g['postgresql_authoritative_job_state'] is True and g['compute_broker_is_second_job_scheduler'] is False and g['placement_is_operational_not_scientific'] is True and g['automatic_platform_core_promotion'] is False

def test_standby_provider_rejected():
 x=validate_compute_request({'job_type':'ocr','capability':'document.ocr','requested_runtime':'ocr'}); assert x['valid'] is False
