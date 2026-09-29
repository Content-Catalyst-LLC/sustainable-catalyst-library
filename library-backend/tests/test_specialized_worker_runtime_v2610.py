from app.specialized_worker_runtime import validate_worker_profile, worker_profiles, guardrails

def test_profiles_validate():
 x=validate_worker_profile({'worker_class':'python.research'}); assert x['valid'] and 'entity.resolve' in x['normalized']['capabilities']
def test_standby_is_explicit():
 x=validate_worker_profile({'worker_class':'ocr.document'}); assert x['valid'] and x['normalized']['profile_active'] is False
def test_bad_capability_rejected():
 assert validate_worker_profile({'worker_class':'rust.graph','capabilities':['entity.resolve']})['valid'] is False
def test_guardrails():
 g=guardrails(); assert g['postgresql_authoritative_worker_state'] is True and g['redis_authoritative_worker_state'] is False and g['quarantined_worker_can_lease'] is False
def test_catalog():
 x=worker_profiles(); assert x['version']=='5.50.0' and x['backend_version']=='2.61.0' and len(x['profiles'])==8
