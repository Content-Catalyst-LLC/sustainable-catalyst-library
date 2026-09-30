from app.identity_access import boundary_contract, build_identity, access_decision, password_hash, password_verify, new_session_material

def test_contract_identity_and_authority():
    c=boundary_contract()
    assert c['schema']=='sc-library-identity-access-boundary/1.0'
    assert c['library_version']=='5.61.0' and c['backend_version']=='2.72.0'
    assert c['identity_authority']==c['session_authority']==c['access_authority']=='library-service'
    assert c['wordpress']['authoritative'] is False and c['wordpress']['session_owner'] is False

def test_identity_is_deterministic_by_handle_and_type():
    a=build_identity('Researcher.Example',display_name='Researcher Example')
    b=build_identity('researcher.example')
    assert a['identity_id']==b['identity_id'] and a['handle']=='researcher.example'

def test_password_hash_is_argon2id_and_verifies():
    encoded=password_hash('correct horse battery staple')
    assert encoded.startswith('$argon2id$')
    assert password_verify(encoded,'correct horse battery staple') is True
    assert password_verify(encoded,'wrong password value') is False

def test_session_material_stores_hash_separately():
    s=new_session_material()
    assert s['token'] not in {s['token_sha256'],s['csrf_sha256']}
    assert len(s['token_sha256'])==64 and len(s['csrf_sha256'])==64

def test_access_roles_and_deny_precedence():
    allow=access_decision(identity_id='identity:x',roles=['researcher'],required_scope='projects:write')
    assert allow['allowed'] is True
    deny=access_decision(identity_id='identity:x',roles=['researcher'],required_scope='projects:write',resource_type='project',resource_id='p1',grants=[{'resource_type':'project','resource_id':'p1','action':'projects:write','effect':'deny'}])
    assert deny['allowed'] is False and deny['reason']=='explicit-deny'

def test_public_read_does_not_require_identity():
    d=access_decision(identity_id=None,roles=[],required_scope='library:read',public_read=True)
    assert d['allowed'] is True and d['reason']=='public-read'
