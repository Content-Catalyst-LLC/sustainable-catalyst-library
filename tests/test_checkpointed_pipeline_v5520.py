from pathlib import Path
import importlib.util
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')

def load():
 p=ROOT/'library-backend/app/checkpointed_pipeline.py'; spec=importlib.util.spec_from_file_location('checkpointed_pipeline_v5520',p); m=importlib.util.module_from_spec(spec); spec.loader.exec_module(m); return m

def sample():
 return {'name':'publication-ingest','stages':[
  {'stage_id':'acquire','capability':'ingestion.submit','requested_runtime':'go'},
  {'stage_id':'parse','capability':'corpus.frequency','requested_runtime':'python','depends_on':['acquire']},
  {'stage_id':'graph','capability':'graph.query','requested_runtime':'rust','depends_on':['parse']},
 ]}

def test_release_identity_routes_and_plugin():
 assert 'Version: 5.52.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
 assert '__version__ = "2.63.0"' in read('library-backend/app/__init__.py')
 s=read('library-backend/app/main.py')
 for route in ['/v1/pipeline-engine/readiness','/v1/pipeline-engine/validate','/v1/pipeline-engine/package','/v1/pipeline-engine/plan','/v1/admin/pipelines','/v1/admin/pipelines/{pipeline_id:path}/runs','/v1/admin/pipeline-runs/{run_id:path}/resume']:
  assert route in s
 assert (ROOT/'sustainable-catalyst-library/includes/class-sc-library-pipeline-engine.php').exists()

def test_pipeline_validation_and_dag_order():
 m=load(); x=m.validate_pipeline(sample()); assert x['valid'] is True; assert x['normalized']['topological_order']==['acquire','parse','graph']

def test_cycle_is_rejected():
 m=load(); p={'name':'bad','stages':[{'stage_id':'a','capability':'corpus.kwic','depends_on':['b']},{'stage_id':'b','capability':'corpus.kwic','depends_on':['a']}]}; x=m.validate_pipeline(p); assert x['valid'] is False and 'pipeline-cycle-detected' in x['errors']

def test_unknown_dependency_is_rejected():
 m=load(); p={'name':'bad','stages':[{'stage_id':'a','capability':'corpus.kwic','depends_on':['missing']}]}; x=m.validate_pipeline(p); assert x['valid'] is False and any(e.startswith('unknown-dependency:') for e in x['errors'])

def test_run_plan_is_deterministic_and_root_stage_ready():
 m=load(); a=m.build_run_plan(sample(),{'source':'x'},idempotency_key='same'); b=m.build_run_plan(sample(),{'source':'x'},idempotency_key='same'); assert a['run_id']==b['run_id'] and a['initial_ready_stage_ids']==['acquire']

def test_stage_jobs_use_existing_job_fabric():
 m=load(); run=m.build_run_plan(sample(),{},idempotency_key='x'); st=run['stages'][0]; j=m.stage_job_payload(run,st,resolved_inputs={'source':'x'}); assert j['job_type']=='pipeline-stage' and j['capability']=='ingestion.submit' and j['idempotency_key'].startswith('pipeline:')

def test_completed_checkpoints_are_not_rescheduled():
 m=load(); run=m.build_run_plan(sample(),{},idempotency_key='x'); states={'acquire':'complete','parse':'failed','graph':'pending'}; assert m.resumable_stage_ids(run,states)==['parse']

def test_checkpoint_artifact_lineage():
 m=load(); aid='artifact:sha256:'+'a'*64; c=m.checkpoint_manifest(run_id='r',stage_id='s',job_id='j',output_manifest={'ok':True},artifact_ids=[aid]); assert c['artifact_ids']==[aid] and len(c['checkpoint_fingerprint_sha256'])==64

def test_invalid_checkpoint_artifact_rejected():
 m=load()
 try: m.checkpoint_manifest(run_id='r',stage_id='s',job_id='j',artifact_ids=['bad'])
 except ValueError as exc: assert str(exc)=='invalid-artifact-id'
 else: raise AssertionError('expected invalid artifact id')

def test_postgresql_tables_and_existing_job_link():
 s=read('library-backend/app/schema.sql')
 for t in ['library_research_pipeline_definitions','library_research_pipeline_runs','library_research_pipeline_stage_runs','library_research_pipeline_events']: assert f'CREATE TABLE IF NOT EXISTS {t}' in s
 assert 'job_id text REFERENCES library_research_jobs(job_id)' in s

def test_worker_agent_checkpoint_hooks():
 s=read('library-backend/app/worker_agent.py'); assert 'record_job_completion' in s and 'record_job_failure' in s

def test_guardrails_and_clean_root():
 m=load(); g=m.guardrails(); assert g['pipeline_engine_is_second_job_scheduler'] is False and g['completed_checkpoint_is_reused_on_resume'] is True and g['pipeline_completion_implies_evidence_truth'] is False
 assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
