from __future__ import annotations
from hashlib import sha256
import json,re
from typing import Any

SIGNAL_CONTRACT='sc-library-source-quality-signal/1.0'
PROFILE_CONTRACT='sc-library-source-transparency-profile/1.0'
POLICY_CONTRACT='sc-library-user-trust-policy/1.0'
EVALUATION_CONTRACT='sc-library-user-trust-policy-evaluation/1.0'
READINESS_CONTRACT='sc-library-source-transparency-readiness/1.0'
VALIDATION_CONTRACT='sc-library-source-transparency-validation/1.0'

SIGNAL_TYPES={
 'provenance-completeness','source-identity-resolved','retrieval-lineage-complete',
 'original-language-preserved','transformation-lineage-complete','methodology-disclosed',
 'data-availability','citation-traceability','versioning-clarity','licensing-clarity',
 'temporal-recency','corroboration-count','contradiction-count','correction-status',
 'retraction-status','access-stability','metadata-completeness','chain-of-custody-completeness'
}
VALUE_KINDS={'boolean','count','ratio','category','timestamp','duration-days','text'}
POLICY_ACTIONS={'include','exclude','flag','prioritize-review','require-human-review','allow'}
OPERATORS={'eq','neq','gt','gte','lt','lte','in','not-in','exists','not-exists'}
POLICY_SCOPES={'personal','project','team','institutional'}
POLICY_STATES={'draft','active','disabled','archived'}
ID_RE=re.compile(r'^[A-Za-z0-9][A-Za-z0-9._:-]{0,255}$')

def _clean(v): return str(v or '').strip()
def _canon(v): return json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':'),default=str)
def _fp(v): return sha256(_canon(v).encode('utf-8')).hexdigest()

def guardrails():
 return {
  'quality_signal_is_truth':False,
  'quality_signal_is_credibility_verdict':False,
  'quality_signal_is_source_endorsement':False,
  'quality_signal_implies_evidence_weight':False,
  'quality_signal_implies_platform_core_promotion':False,
  'system_default_trust_verdict':False,
  'user_trust_policy_changes_source_signals':False,
  'user_trust_policy_changes_source_history':False,
  'user_trust_policy_implies_truth':False,
  'user_trust_policy_implies_source_quality':False,
  'user_trust_policy_is_portable_without_owner_context':False,
  'source_quality_signals_separate_from_user_trust_choices':True,
  'observable_signal_provenance_required':True,
  'policy_evaluation_is_user_preference_application':True,
 }

def _normalize_signal(raw:dict[str,Any], errors:list[str]):
 if not isinstance(raw,dict): errors.append('signal-must-be-object'); raw={}
 st=_clean(raw.get('signal_type')); vk=_clean(raw.get('value_kind')) or 'text'; sid=_clean(raw.get('source_object_id')); val=raw.get('value')
 if st not in SIGNAL_TYPES: errors.append('invalid-signal-type')
 if vk not in VALUE_KINDS: errors.append('invalid-value-kind')
 if not sid or not ID_RE.match(sid): errors.append('source-object-id-required-or-invalid')
 prov=raw.get('provenance') if isinstance(raw.get('provenance'),dict) else {}
 if not prov: errors.append('signal-provenance-required')
 if raw.get('credibility_verdict') is not None: errors.append('credibility-verdict-prohibited')
 if raw.get('trust_score') is not None: errors.append('trust-score-prohibited')
 if raw.get('promote_to_core') is True: errors.append('automatic-platform-core-promotion-prohibited')
 if vk=='boolean' and not isinstance(val,bool): errors.append('boolean-value-required')
 if vk=='count' and (not isinstance(val,int) or isinstance(val,bool) or val<0): errors.append('nonnegative-integer-count-required')
 if vk=='ratio':
  try: r=float(val); assert 0<=r<=1; val=r
  except Exception: errors.append('ratio-value-out-of-range')
 return {
  'source_object_id':sid,'source_object_type':_clean(raw.get('source_object_type')) or 'record',
  'signal_type':st,'value_kind':vk,'value':val,
  'observed_at':_clean(raw.get('observed_at')) or None,
  'observation_basis':_clean(raw.get('observation_basis')) or None,
  'provenance':prov,'metadata':dict(raw.get('metadata') or {}) if isinstance(raw.get('metadata'),dict) else {}
 }

def validate_signal(payload:dict[str,Any]):
 errors=[]; n=_normalize_signal(payload,errors)
 return {'schema':VALIDATION_CONTRACT,'kind':'quality-signal','valid':not errors,'errors':errors,'normalized':n,'guardrails':guardrails()}

def build_signal(payload:dict[str,Any]):
 v=validate_signal(payload)
 if not v['valid']: raise ValueError(';'.join(v['errors']))
 n=v['normalized']; fp=_fp(n)
 return {'schema':SIGNAL_CONTRACT,'signal_id':'source-signal:'+fp[:32],'signal_fingerprint_sha256':fp,**n,'guardrails':guardrails(),'persisted':False}

def validate_profile(payload:dict[str,Any]):
 errors=[]
 if not isinstance(payload,dict): payload={}; errors.append('payload-must-be-object')
 source_id=_clean(payload.get('source_object_id'))
 if not source_id: errors.append('source-object-id-required')
 raw=payload.get('signals')
 if not isinstance(raw,list): errors.append('signals-must-be-array'); raw=[]
 signals=[]
 for i,s in enumerate(raw):
  try: signals.append(build_signal({**(s if isinstance(s,dict) else {}),'source_object_id':source_id,'source_object_type':_clean(payload.get('source_object_type')) or (s.get('source_object_type') if isinstance(s,dict) else 'record')}))
  except Exception as exc: errors.append(f'signal-{i}:{exc}')
 if payload.get('aggregate_quality_score') is not None: errors.append('aggregate-quality-score-prohibited')
 n={'source_object_id':source_id,'source_object_type':_clean(payload.get('source_object_type')) or 'record','signals':signals,'profile_context':dict(payload.get('profile_context') or {}) if isinstance(payload.get('profile_context'),dict) else {},'provenance':dict(payload.get('provenance') or {}) if isinstance(payload.get('provenance'),dict) else {}}
 return {'schema':VALIDATION_CONTRACT,'kind':'transparency-profile','valid':not errors,'errors':errors,'normalized':n,'guardrails':guardrails()}

def build_profile(payload:dict[str,Any]):
 v=validate_profile(payload)
 if not v['valid']: raise ValueError(';'.join(v['errors']))
 n=v['normalized']; fp=_fp({'source_object_id':n['source_object_id'],'signals':[x['signal_fingerprint_sha256'] for x in n['signals']],'profile_context':n['profile_context']})
 return {'schema':PROFILE_CONTRACT,'profile_id':'source-transparency-profile:'+fp[:32],'profile_fingerprint_sha256':fp,**n,'signal_count':len(n['signals']),'aggregate_quality_score':None,'guardrails':guardrails(),'persisted':False}

def _normalize_rule(raw:dict[str,Any],i:int,errors:list[str]):
 if not isinstance(raw,dict): errors.append(f'rule-{i}-must-be-object'); raw={}
 st=_clean(raw.get('signal_type')); op=_clean(raw.get('operator')); act=_clean(raw.get('action'))
 if st not in SIGNAL_TYPES: errors.append(f'rule-{i}-invalid-signal-type')
 if op not in OPERATORS: errors.append(f'rule-{i}-invalid-operator')
 if act not in POLICY_ACTIONS: errors.append(f'rule-{i}-invalid-action')
 return {'rule_id':_clean(raw.get('rule_id')) or f'rule-{i+1}','signal_type':st,'operator':op,'value':raw.get('value'),'action':act,'reason':_clean(raw.get('reason')) or None}

def validate_policy(payload:dict[str,Any]):
 errors=[]
 if not isinstance(payload,dict): payload={}; errors.append('payload-must-be-object')
 owner=_clean(payload.get('owner_id')); name=_clean(payload.get('name')); scope=_clean(payload.get('scope')) or 'personal'; state=_clean(payload.get('state')) or 'draft'
 if not owner: errors.append('owner-id-required')
 if not name: errors.append('policy-name-required')
 if scope not in POLICY_SCOPES: errors.append('invalid-policy-scope')
 if state not in POLICY_STATES: errors.append('invalid-policy-state')
 raw=payload.get('rules');
 if not isinstance(raw,list) or not raw: errors.append('rules-required'); raw=[]
 rules=[_normalize_rule(r,i,errors) for i,r in enumerate(raw)]
 if payload.get('default_trust_score') is not None: errors.append('default-trust-score-prohibited')
 if payload.get('source_quality_override') is not None: errors.append('source-quality-override-prohibited')
 n={'owner_id':owner,'name':name,'scope':scope,'state':state,'rules':rules,'default_action':_clean(payload.get('default_action')) or 'allow','metadata':dict(payload.get('metadata') or {}) if isinstance(payload.get('metadata'),dict) else {}}
 if n['default_action'] not in POLICY_ACTIONS: errors.append('invalid-default-action')
 return {'schema':VALIDATION_CONTRACT,'kind':'trust-policy','valid':not errors,'errors':errors,'normalized':n,'guardrails':guardrails()}

def build_policy(payload:dict[str,Any]):
 v=validate_policy(payload)
 if not v['valid']: raise ValueError(';'.join(v['errors']))
 n=v['normalized']; fp=_fp(n)
 return {'schema':POLICY_CONTRACT,'policy_id':'user-trust-policy:'+fp[:32],'policy_fingerprint_sha256':fp,**n,'guardrails':guardrails(),'persisted':False}

def _cmp(observed,operator,expected):
 if operator=='exists': return observed is not None
 if operator=='not-exists': return observed is None
 if operator=='eq': return observed==expected
 if operator=='neq': return observed!=expected
 if operator in {'in','not-in'}:
  vals=expected if isinstance(expected,list) else [expected]; ok=observed in vals; return ok if operator=='in' else not ok
 try:
  a=float(observed); b=float(expected)
  return {'gt':a>b,'gte':a>=b,'lt':a<b,'lte':a<=b}[operator]
 except Exception: return False

def evaluate_policy(policy_payload:dict[str,Any],profile_payload:dict[str,Any]):
 p=build_policy(policy_payload) if policy_payload.get('schema')!=POLICY_CONTRACT else policy_payload
 prof=build_profile(profile_payload) if profile_payload.get('schema')!=PROFILE_CONTRACT else profile_payload
 signal_map={s['signal_type']:s.get('value') for s in prof.get('signals',[])}
 matched=[]
 for rule in p['rules']:
  observed=signal_map.get(rule['signal_type'])
  if _cmp(observed,rule['operator'],rule.get('value')): matched.append({**rule,'observed_value':observed})
 disposition=matched[0]['action'] if matched else p['default_action']
 fp=_fp({'policy_id':p['policy_id'],'profile_id':prof['profile_id'],'matched':[x['rule_id'] for x in matched],'disposition':disposition})
 return {'schema':EVALUATION_CONTRACT,'evaluation_id':'trust-policy-evaluation:'+fp[:32],'policy_id':p['policy_id'],'profile_id':prof['profile_id'],'source_object_id':prof['source_object_id'],'matched_rules':matched,'disposition':disposition,'source_signals_unchanged':True,'source_history_unchanged':True,'guardrails':guardrails()}

def ingest_signal(payload):
 from psycopg.types.json import Jsonb
 from .db import get_pool
 x=build_signal(payload)
 with get_pool().connection() as conn, conn.cursor() as cur:
  cur.execute("""INSERT INTO library_source_quality_signals(signal_id,source_object_id,source_object_type,signal_type,value_kind,value,observed_at,observation_basis,signal_fingerprint,provenance,metadata) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT(signal_id) DO NOTHING""",(x['signal_id'],x['source_object_id'],x['source_object_type'],x['signal_type'],x['value_kind'],Jsonb(x['value']),x['observed_at'],x['observation_basis'],x['signal_fingerprint_sha256'],Jsonb(x['provenance']),Jsonb(x['metadata']))); conn.commit()
 x['persisted']=True; return x

def ingest_profile(payload):
 from psycopg.types.json import Jsonb
 from .db import get_pool
 x=build_profile(payload)
 with get_pool().connection() as conn, conn.cursor() as cur:
  cur.execute("""INSERT INTO library_source_transparency_profiles(profile_id,source_object_id,source_object_type,signal_ids,profile_context,profile_fingerprint,provenance) VALUES (%s,%s,%s,%s,%s,%s,%s) ON CONFLICT(profile_id) DO NOTHING""",(x['profile_id'],x['source_object_id'],x['source_object_type'],[s['signal_id'] for s in x['signals']],Jsonb(x['profile_context']),x['profile_fingerprint_sha256'],Jsonb(x['provenance']))); conn.commit()
 x['persisted']=True; return x

def ingest_policy(payload):
 from psycopg.types.json import Jsonb
 from .db import get_pool
 x=build_policy(payload)
 with get_pool().connection() as conn, conn.cursor() as cur:
  cur.execute("""INSERT INTO library_user_trust_policies(policy_id,owner_id,name,scope,state,rules,default_action,policy_fingerprint,metadata) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT(policy_id) DO UPDATE SET state=EXCLUDED.state,rules=EXCLUDED.rules,default_action=EXCLUDED.default_action,metadata=EXCLUDED.metadata,updated_at=now()""",(x['policy_id'],x['owner_id'],x['name'],x['scope'],x['state'],Jsonb(x['rules']),x['default_action'],x['policy_fingerprint_sha256'],Jsonb(x['metadata'])))
  cur.execute("INSERT INTO library_user_trust_policy_events(policy_id,event_type,details) VALUES (%s,'saved',%s)",(x['policy_id'],Jsonb({'state':x['state'],'scope':x['scope'],'rule_count':len(x['rules'])})))
  conn.commit()
 x['persisted']=True; return x

def readiness():
 try:
  from .db import get_pool
  with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
   counts={}
   for table,key in [('library_source_quality_signals','signals'),('library_source_transparency_profiles','profiles'),('library_user_trust_policies','policies')]:
    cur.execute(f'SELECT count(*) AS n FROM {table}'); counts[key]=int(cur.fetchone()['n'])
  return {'schema':READINESS_CONTRACT,'version':'5.56.0','backend_version':'2.67.0','state':'ready','counts':counts,'capabilities':{'source_quality_signals':True,'source_transparency_profiles':True,'user_trust_policies':True,'stateless_policy_evaluation':True,'aggregate_quality_score':False,'system_default_trust_verdict':False},'guardrails':guardrails()}
 except Exception as exc:
  return {'schema':READINESS_CONTRACT,'version':'5.56.0','backend_version':'2.67.0','state':'unavailable','error_class':exc.__class__.__name__,'counts':{},'guardrails':guardrails()}
