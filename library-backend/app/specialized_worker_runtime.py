from __future__ import annotations
from hashlib import sha256
import json, socket
from typing import Any
from .settings import settings

WORKER_PROFILE_CONTRACT='sc-library-worker-profile/1.0'
WORKER_CONTRACT='sc-library-worker-registration/1.0'
DEAD_LETTER_CONTRACT='sc-library-job-dead-letter/1.0'
READINESS_CONTRACT='sc-library-specialized-worker-readiness/1.0'
FAILURE_ISOLATION_CONTRACT='sc-library-worker-failure-isolation/1.0'
PROFILES={
 'python.research':{'runtimes':['python'],'capabilities':['entity.resolve','corpus.kwic','corpus.frequency','artifact.persist','artifact.verify','language.align','knowledge.link.cross-civilizational','source.transparency.assess','federation.certify.global'],'execution_mode':'local','active':True,'default_concurrency':2},
 'go.ingestion':{'runtimes':['go'],'capabilities':['ingestion.submit'],'execution_mode':'handoff','active':True,'default_concurrency':2},
 'rust.graph':{'runtimes':['rust'],'capabilities':['graph.query'],'execution_mode':'local-native','active':True,'default_concurrency':2},
 'ocr.document':{'runtimes':['ocr'],'capabilities':['document.ocr'],'execution_mode':'provider','active':False,'default_concurrency':1},
 'htr.document':{'runtimes':['htr'],'capabilities':['document.htr'],'execution_mode':'provider','active':False,'default_concurrency':1},
 'speech.transcription':{'runtimes':['speech'],'capabilities':['media.transcribe'],'execution_mode':'provider','active':False,'default_concurrency':1},
 'neural.inference':{'runtimes':['neural'],'capabilities':['embedding.compute','retrieval.rerank'],'execution_mode':'provider','active':False,'default_concurrency':1},
 'workspace.compute':{'runtimes':['workspace'],'capabilities':['workspace.compute'],'execution_mode':'handoff','active':False,'default_concurrency':1},
}
def _clean(x): return str(x or '').strip()
def _hash(x): return sha256(json.dumps(x,sort_keys=True,separators=(',',':'),default=str).encode()).hexdigest()
def guardrails(): return {'postgresql_authoritative_worker_state':True,'redis_authoritative_worker_state':False,'worker_failure_isolated':True,'quarantined_worker_can_lease':False,'dead_letter_implies_evidence_truth':False,'job_completion_implies_evidence_truth':False,'automatic_platform_core_promotion':False,'standby_profiles_execute_without_adapter':False}
def worker_profiles(): return {'schema':WORKER_PROFILE_CONTRACT,'version':'5.51.0','backend_version':'2.62.0','profiles':[{'worker_class':k,**v} for k,v in PROFILES.items()],'guardrails':guardrails()}
def validate_worker_profile(payload:dict[str,Any]):
    wc=_clean(payload.get('worker_class')); errors=[]; p=PROFILES.get(wc,{})
    if wc not in PROFILES: errors.append('unknown-worker-class')
    req=[_clean(x) for x in payload.get('capabilities',[]) if _clean(x)]
    if any(x not in p.get('capabilities',[]) for x in req): errors.append('capability-not-permitted-for-worker-class')
    try: concurrency=int(payload.get('concurrency_limit') or p.get('default_concurrency',1))
    except Exception: concurrency=1; errors.append('concurrency-limit-must-be-integer')
    if not 1<=concurrency<=32: errors.append('concurrency-limit-out-of-range')
    n={'worker_class':wc,'runtimes':p.get('runtimes',[]),'capabilities':req or p.get('capabilities',[]),'concurrency_limit':concurrency,'execution_mode':p.get('execution_mode'),'profile_active':bool(p.get('active'))}; n['profile_fingerprint_sha256']=_hash(n)
    return {'schema':'sc-library-worker-profile-validation/1.0','valid':not errors,'errors':errors,'normalized':n,'guardrails':guardrails()}
def public_worker(row):
    if not row:return None
    d=dict(row); d['schema']=WORKER_CONTRACT
    for k,v in list(d.items()):
        if hasattr(v,'isoformat'): d[k]=v.isoformat().replace('+00:00','Z')
    d['guardrails']=guardrails(); return d
def register_worker(payload):
    from psycopg.types.json import Jsonb
    from .db import get_pool
    v=validate_worker_profile(payload)
    if not v['valid']: raise ValueError('; '.join(v['errors']))
    n=v['normalized']; wid=_clean(payload.get('worker_id')) or f"{n['worker_class']}:{socket.gethostname()}"; state='active' if n['profile_active'] else 'standby'; metadata=payload.get('metadata') if isinstance(payload.get('metadata'),dict) else {}
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""INSERT INTO library_research_workers(worker_id,worker_class,state,runtimes,capabilities,concurrency_limit,profile_fingerprint,metadata,registered_at,heartbeat_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,now(),now(),now()) ON CONFLICT(worker_id) DO UPDATE SET worker_class=EXCLUDED.worker_class,runtimes=EXCLUDED.runtimes,capabilities=EXCLUDED.capabilities,concurrency_limit=EXCLUDED.concurrency_limit,profile_fingerprint=EXCLUDED.profile_fingerprint,metadata=EXCLUDED.metadata,heartbeat_at=now(),updated_at=now(),state=CASE WHEN library_research_workers.state='quarantined' THEN 'quarantined' ELSE EXCLUDED.state END RETURNING *""",(wid,n['worker_class'],state,n['runtimes'],n['capabilities'],n['concurrency_limit'],n['profile_fingerprint_sha256'],Jsonb(metadata))); row=cur.fetchone(); conn.commit()
    return public_worker(row)
def get_worker(worker_id):
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur: cur.execute('SELECT * FROM library_research_workers WHERE worker_id=%s',(_clean(worker_id),)); row=cur.fetchone()
    if not row: raise KeyError('worker-not-found')
    return public_worker(row)
def list_workers():
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur: cur.execute('SELECT * FROM library_research_workers ORDER BY worker_class,worker_id'); rows=cur.fetchall()
    return {'schema':'sc-library-worker-list/1.0','count':len(rows),'workers':[public_worker(x) for x in rows],'guardrails':guardrails()}
def heartbeat_worker(worker_id,metadata=None):
    from psycopg.types.json import Jsonb
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur: cur.execute('UPDATE library_research_workers SET heartbeat_at=now(),updated_at=now(),metadata=metadata||%s WHERE worker_id=%s RETURNING *',(Jsonb(metadata or {}),_clean(worker_id))); row=cur.fetchone(); conn.commit()
    if not row: raise KeyError('worker-not-found')
    return public_worker(row)
def quarantine_worker(worker_id,reason):
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur: cur.execute("UPDATE library_research_workers SET state='quarantined',quarantine_reason=%s,quarantined_at=now(),updated_at=now() WHERE worker_id=%s RETURNING *",(_clean(reason) or 'manual-quarantine',_clean(worker_id))); row=cur.fetchone(); conn.commit()
    if not row: raise KeyError('worker-not-found')
    return public_worker(row)
def release_worker(worker_id):
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur: cur.execute("UPDATE library_research_workers SET state=CASE WHEN worker_class IN ('python.research','go.ingestion','rust.graph') THEN 'active' ELSE 'standby' END,consecutive_failures=0,quarantine_reason=NULL,quarantined_at=NULL,updated_at=now() WHERE worker_id=%s RETURNING *",(_clean(worker_id),)); row=cur.fetchone(); conn.commit()
    if not row: raise KeyError('worker-not-found')
    return public_worker(row)
def lease_for_worker(worker_id):
    from .db import get_pool
    from .durable_job_queue import lease_next_job
    w=get_worker(worker_id)
    if w['state']!='active': return {'schema':'sc-library-worker-lease/1.0','leased':False,'worker_id':worker_id,'reason':'worker-not-active','worker_state':w['state'],'guardrails':guardrails()}
    with get_pool().connection() as conn, conn.cursor() as cur: cur.execute("SELECT count(*) AS n FROM library_research_job_attempts WHERE worker_id=%s AND state IN ('leased','running')",(worker_id,)); active=int(cur.fetchone()['n'])
    if active>=int(w['concurrency_limit']): return {'schema':'sc-library-worker-lease/1.0','leased':False,'worker_id':worker_id,'reason':'concurrency-limit','active_attempts':active,'guardrails':guardrails()}
    return lease_next_job({'worker_id':worker_id,'worker_class':w['worker_class'],'capabilities':w['capabilities'],'runtimes':w['runtimes'],'runtime_id':w['worker_class'],'execution_metadata':{'worker_class':w['worker_class'],'isolation':'worker','compute_broker_affinity':True}})
def execute_job(job,worker_class):
    manifest=job.get('input_manifest') if isinstance(job.get('input_manifest'),dict) else {}; cap=_clean(job.get('capability'))
    if worker_class=='python.research' and cap=='entity.resolve':
        from .cross_language_resolution import build_resolution_case
        return {'kind':'entity-resolution','result':build_resolution_case(manifest.get('authority_payload') or {},manifest.get('query') or {},limit=int(manifest.get('limit') or 25))}
    if worker_class=='python.research' and cap in {'corpus.kwic','corpus.frequency'}:
        from .linguistic_corpus import build_corpus_package, kwic_from_package, frequency_table_from_package
        pkg=build_corpus_package(manifest.get('corpus') or {})
        return {'kind':'kwic','result':kwic_from_package(pkg,_clean(manifest.get('query')),window_tokens=int(manifest.get('window_tokens') or 8),limit=int(manifest.get('limit') or 100))} if cap=='corpus.kwic' else {'kind':'frequency','result':frequency_table_from_package(pkg,limit=int(manifest.get('limit') or 100))}
    if worker_class=='python.research' and cap=='artifact.persist':
        from .artifact_storage import persist_artifact
        return {'kind':'artifact-persist','artifact':persist_artifact(manifest.get('artifact') or manifest)}
    if worker_class=='python.research' and cap=='language.align':
        from .translation_alignment import build_alignment_matrix
        return {'kind':'translation-alignment-matrix','result':build_alignment_matrix(manifest.get('alignment') or manifest)}
    if worker_class=='python.research' and cap=='knowledge.link.cross-civilizational':
        from .cross_civilizational_linking import build_link
        return {'kind':'cross-civilizational-evidence-link','result':build_link(manifest.get('link') or manifest)}
    if worker_class=='python.research' and cap=='source.transparency.assess':
        from .source_transparency import build_profile
        return {'kind':'source-transparency-profile','result':build_profile(manifest.get('profile') or manifest)}
    if worker_class=='python.research' and cap=='federation.certify.global':
        from .global_knowledge_federation import build_certification
        return {'kind':'global-knowledge-federation-certification','result':build_certification(manifest.get('certification') or manifest)}
    if worker_class=='python.research' and cap=='artifact.verify':
        from .artifact_storage import verify_artifact
        return {'kind':'artifact-verify','verification':verify_artifact(_clean(manifest.get('artifact_id')))}
    if worker_class=='go.ingestion' and cap=='ingestion.submit':
        from .ingestion_job_fabric import submit_ingestion_job
        return {'kind':'go-ingestion-handoff','handoff':submit_ingestion_job(manifest.get('job') or manifest)}
    if worker_class=='rust.graph' and cap=='graph.query':
        from .native_graph_query import query_native_graph
        return {'kind':'rust-graph-query','result':query_native_graph(manifest.get('corpus') or {},manifest.get('query') or {})}
    raise RuntimeError('unsupported-worker-capability')
def record_worker_success(worker_id):
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur: cur.execute('UPDATE library_research_workers SET completed_jobs=completed_jobs+1,consecutive_failures=0,last_success_at=now(),updated_at=now() WHERE worker_id=%s',(worker_id,)); conn.commit()
def _record_dead_letter(job,worker_id,error_class,error_detail):
    from .db import get_pool
    dlid='dead-letter:'+_hash({'job_id':job['job_id'],'attempt':job.get('attempt_count'),'worker_id':worker_id,'error_class':error_class})[:32]
    with get_pool().connection() as conn, conn.cursor() as cur: cur.execute("""INSERT INTO library_research_dead_letters(dead_letter_id,job_id,attempt_no,worker_id,worker_class,failure_class,failure_detail,input_manifest,provenance_context,state) SELECT %s,j.job_id,j.attempt_count,%s,w.worker_class,%s,%s,j.input_manifest,j.provenance_context,'open' FROM library_research_jobs j LEFT JOIN library_research_workers w ON w.worker_id=%s WHERE j.job_id=%s ON CONFLICT(dead_letter_id) DO NOTHING RETURNING *""",(dlid,worker_id,error_class,error_detail,worker_id,job['job_id'])); row=cur.fetchone(); conn.commit()
    return dict(row) if row else {'dead_letter_id':dlid,'job_id':job['job_id'],'state':'open'}
def isolate_worker_failure(worker_id,job_id,*,error_class,error_detail,retryable=True,retry_delay_seconds=30):
    from .durable_job_queue import fail_job
    from .db import get_pool
    result=fail_job(job_id,worker_id,error_class=error_class,error_detail=error_detail,retryable=retryable,retry_delay_seconds=retry_delay_seconds)
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute('UPDATE library_research_workers SET failed_jobs=failed_jobs+1,consecutive_failures=consecutive_failures+1,last_failure_at=now(),updated_at=now() WHERE worker_id=%s RETURNING consecutive_failures',(worker_id,)); row=cur.fetchone(); failures=int(row['consecutive_failures']) if row else 0
        if failures>=settings.worker_quarantine_threshold: cur.execute("UPDATE library_research_workers SET state='quarantined',quarantine_reason='automatic-failure-threshold',quarantined_at=now(),updated_at=now() WHERE worker_id=%s",(worker_id,))
        conn.commit()
    dead=_record_dead_letter(result,worker_id,error_class,error_detail) if result.get('state')=='failed' else None
    return {'schema':FAILURE_ISOLATION_CONTRACT,'job':result,'worker_id':worker_id,'consecutive_failures':failures,'worker_quarantined':failures>=settings.worker_quarantine_threshold,'dead_letter':dead,'guardrails':guardrails()}
def list_dead_letters(state='open',limit=100):
    from .db import get_pool
    limit=max(1,min(500,int(limit)))
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute('SELECT * FROM library_research_dead_letters WHERE state=%s ORDER BY created_at DESC LIMIT %s',(state,limit)) if state else cur.execute('SELECT * FROM library_research_dead_letters ORDER BY created_at DESC LIMIT %s',(limit,)); rows=cur.fetchall()
    return {'schema':'sc-library-dead-letter-list/1.0','count':len(rows),'dead_letters':[dict(x) for x in rows],'guardrails':guardrails()}
def worker_readiness():
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute('SELECT state,count(*) AS n FROM library_research_workers GROUP BY state'); counts={str(x['state']):int(x['n']) for x in cur.fetchall()}; cur.execute("SELECT count(*) AS n FROM library_research_dead_letters WHERE state='open'"); dead=int(cur.fetchone()['n'])
    except Exception as exc: return {'schema':READINESS_CONTRACT,'version':'5.51.0','backend_version':'2.62.0','state':'unavailable','error_class':exc.__class__.__name__,'guardrails':guardrails()}
    active=sum(1 for x in PROFILES.values() if x['active'])
    return {'schema':READINESS_CONTRACT,'version':'5.51.0','backend_version':'2.62.0','state':'ready','postgresql':{'state':'ready','authoritative':True},'worker_counts':counts,'open_dead_letters':dead,'profiles':{'total':len(PROFILES),'active':active,'standby':len(PROFILES)-active},'capabilities':{'worker_registry':True,'capability_routing':True,'concurrency_limits':True,'worker_heartbeats':True,'worker_quarantine':True,'dead_letters':True,'python_research_worker':True,'go_ingestion_handoff_worker':True,'rust_graph_worker':True,'ocr_htr_speech_profiles':True,'neural_profile':True,'workspace_profile':True},'guardrails':guardrails()}
