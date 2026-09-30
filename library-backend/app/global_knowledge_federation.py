from __future__ import annotations
from hashlib import sha256
import json
from typing import Any

CERTIFICATION_CONTRACT='sc-library-global-knowledge-federation-certification/1.0'
READINESS_CONTRACT='sc-library-global-knowledge-federation-readiness/1.0'
VALIDATION_CONTRACT='sc-library-global-knowledge-federation-validation/1.0'

REQUIRED_COMPONENTS={
 'global-source-federation':'5.44.0',
 'original-language-preservation':'5.45.0',
 'ocr-htr-transcription-lineage':'5.46.0',
 'linguistic-corpus':'5.47.0',
 'cross-language-entity-resolution':'5.48.0',
 'durable-execution-fabric':'5.49.0',
 'specialized-worker-runtime':'5.50.0',
 'artifact-storage-fabric':'5.51.0',
 'checkpointed-pipeline-engine':'5.52.0',
 'distributed-compute-broker':'5.53.0',
 'translation-transliteration-alignment':'5.54.0',
 'cross-civilizational-linking':'5.55.0',
 'source-transparency-user-trust':'5.56.0',
}

def _canon(v:Any)->str: return json.dumps(v,sort_keys=True,separators=(',',':'),ensure_ascii=False,default=str)
def _fp(v:Any)->str: return sha256(_canon(v).encode('utf-8')).hexdigest()

def guardrails()->dict[str,bool]:
 return {
  'original_language_is_canonical':True,
  'translation_is_derived_representation':True,
  'transliteration_is_derived_representation':True,
  'all_transformations_require_provenance':True,
  'source_quality_signals_separate_from_user_trust_choices':True,
  'federation_membership_implies_source_quality':False,
  'federation_membership_implies_evidence_truth':False,
  'cross_civilizational_link_implies_equivalence':False,
  'alignment_implies_semantic_identity':False,
  'compute_placement_implies_research_quality':False,
  'pipeline_completion_implies_evidence_truth':False,
  'automatic_platform_core_promotion':False,
  'library_retrieves_preserves_structures_processes_knowledge':True,
  'platform_core_defines_governed_research_meaning':True,
 }

def validate_certification_payload(payload:dict[str,Any])->dict[str,Any]:
 errors=[]
 if not isinstance(payload,dict): payload={}; errors.append('payload-must-be-object')
 supplied=payload.get('components') if isinstance(payload.get('components'),dict) else {}
 components={}
 for key,min_version in REQUIRED_COMPONENTS.items():
  raw=supplied.get(key,{})
  if isinstance(raw,bool): raw={'ready':raw}
  if not isinstance(raw,dict): raw={}
  ready=bool(raw.get('ready',False))
  version=str(raw.get('version') or min_version).strip()
  components[key]={'ready':ready,'version':version,'minimum_version':min_version,'detail':raw.get('detail')}
  if not ready: errors.append(f'component-not-ready:{key}')
 if payload.get('collapse_governance_boundaries') is True: errors.append('governance-boundary-collapse-prohibited')
 if payload.get('automatic_platform_core_promotion') is True: errors.append('automatic-platform-core-promotion-prohibited')
 normalized={
  'federation_id':str(payload.get('federation_id') or 'global-knowledge-federation:default'),
  'components':components,
  'scope':dict(payload.get('scope') or {}),
  'provenance':dict(payload.get('provenance') or {}),
 }
 return {'schema':VALIDATION_CONTRACT,'valid':not errors,'errors':errors,'normalized':normalized,'guardrails':guardrails()}

def build_certification(payload:dict[str,Any])->dict[str,Any]:
 v=validate_certification_payload(payload)
 if not v['valid']: raise ValueError(';'.join(v['errors']))
 n=v['normalized']; fp=_fp({'federation_id':n['federation_id'],'components':n['components'],'scope':n['scope']})
 return {
  'schema':CERTIFICATION_CONTRACT,
  'version':'5.57.0','backend_version':'2.68.0',
  'certification_id':'global-knowledge-federation-certification:'+fp[:32],
  'certification_fingerprint_sha256':fp,
  **n,
  'component_count':len(n['components']),
  'ready_component_count':sum(1 for x in n['components'].values() if x['ready']),
  'state':'certified',
  'guardrails':guardrails(),
  'persisted':False,
 }

def default_component_snapshot()->dict[str,dict[str,Any]]:
 return {k:{'ready':True,'version':v} for k,v in REQUIRED_COMPONENTS.items()}

def build_default_certification()->dict[str,Any]:
 return build_certification({'components':default_component_snapshot(),'provenance':{'authority':'knowledge-library','basis':'release-contract-certification'}})

def ingest_certification(payload:dict[str,Any])->dict[str,Any]:
 from psycopg.types.json import Jsonb
 from .db import get_pool
 x=build_certification(payload)
 with get_pool().connection() as conn, conn.cursor() as cur:
  cur.execute("""INSERT INTO library_global_knowledge_federation_certifications(certification_id,federation_id,state,component_snapshot,scope,certification_fingerprint,provenance,guardrails) VALUES (%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT(certification_id) DO NOTHING""",(x['certification_id'],x['federation_id'],x['state'],Jsonb(x['components']),Jsonb(x['scope']),x['certification_fingerprint_sha256'],Jsonb(x['provenance']),Jsonb(x['guardrails'])))
  cur.execute("INSERT INTO library_global_knowledge_federation_events(certification_id,event_type,details) VALUES (%s,'certified',%s)",(x['certification_id'],Jsonb({'component_count':x['component_count'],'ready_component_count':x['ready_component_count']})))
  conn.commit()
 x['persisted']=True; return x

def readiness()->dict[str,Any]:
 counts={'certifications':0,'events':0}
 db_state='unavailable'
 try:
  from .db import get_pool
  with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
   for table,key in [('library_global_knowledge_federation_certifications','certifications'),('library_global_knowledge_federation_events','events')]:
    cur.execute(f'SELECT count(*) AS n FROM {table}'); counts[key]=int(cur.fetchone()['n'])
   db_state='ready'
 except Exception: pass
 c=build_default_certification()
 return {'schema':READINESS_CONTRACT,'version':'5.57.0','backend_version':'2.68.0','state':'ready' if db_state=='ready' else 'degraded','database':db_state,'counts':counts,'component_count':c['component_count'],'ready_component_count':c['ready_component_count'],'components':c['components'],'guardrails':guardrails()}
