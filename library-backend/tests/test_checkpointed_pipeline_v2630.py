from app.checkpointed_pipeline import build_pipeline_package, build_run_plan, checkpoint_manifest, resumable_stage_ids, guardrails

def pipeline():
 return {'name':'demo','stages':[{'stage_id':'one','capability':'corpus.kwic'},{'stage_id':'two','capability':'graph.query','depends_on':['one'],'requested_runtime':'rust'}]}

def test_package():
 x=build_pipeline_package(pipeline()); assert x['schema']=='sc-library-research-pipeline/1.0' and x['version']=='5.52.0' and x['backend_version']=='2.63.0'

def test_run_plan_and_idempotency():
 a=build_run_plan(pipeline(),{'a':1},idempotency_key='k'); b=build_run_plan(pipeline(),{'a':1},idempotency_key='k'); assert a['run_id']==b['run_id'] and a['initial_ready_stage_ids']==['one']

def test_resume_skips_complete():
 r=build_run_plan(pipeline(),{},idempotency_key='k'); assert resumable_stage_ids(r,{'one':'complete','two':'failed'})==['two']

def test_checkpoint_fingerprint():
 c=checkpoint_manifest(run_id='r',stage_id='one',job_id='j',output_manifest={'x':1}); assert len(c['checkpoint_fingerprint_sha256'])==64

def test_guardrails():
 g=guardrails(); assert g['postgresql_authoritative_pipeline_state'] is True and g['pipeline_engine_is_second_job_scheduler'] is False and g['automatic_platform_core_promotion'] is False
