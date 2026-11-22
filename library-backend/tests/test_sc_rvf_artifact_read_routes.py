"""Exercise actual route matching and credentials without database side effects."""
from dataclasses import replace
import hashlib
import pytest
from fastapi.testclient import TestClient
from app import main

DATA = b'Published source fixture, not a field observation.'
DIGEST = hashlib.sha256(DATA).hexdigest()
AID = 'artifact:sha256:'+DIGEST

@pytest.fixture
def client(monkeypatch):
    monkeypatch.setattr(main, 'settings', replace(main.settings, api_key='isolated-read-test-key'))
    return TestClient(main.app)

@pytest.mark.parametrize('suffix',['','/content'])
@pytest.mark.parametrize('authorization',[None,'Bearer wrong','Basic invalid'])
def test_missing_or_wrong_credentials_never_read_storage(client, monkeypatch, suffix, authorization):
    def forbidden(*args): raise AssertionError('Unauthorized storage read')
    monkeypatch.setattr(main,'get_artifact',forbidden)
    monkeypatch.setattr(main,'read_artifact_bytes',forbidden)
    headers={'Authorization':authorization} if authorization else {}
    assert client.get('/v1/admin/artifacts/'+AID+suffix,headers=headers).status_code == 401


def test_content_route_is_not_shadowed_by_path_capture(client,monkeypatch):
    def wrong_route(*args): raise AssertionError('Metadata route swallowed /content')
    monkeypatch.setattr(main,'get_artifact',wrong_route)
    seen=[]
    def read(aid):
        seen.append(aid);return {'media_type':'application/pdf','content_sha256':DIGEST},DATA
    monkeypatch.setattr(main,'read_artifact_bytes',read)
    response=client.get('/v1/admin/artifacts/'+AID+'/content',headers={'Authorization':'Bearer isolated-read-test-key'})
    assert response.status_code==200,response.text
    assert response.content==DATA and seen==[AID]
    assert response.headers['x-content-sha256']==DIGEST


def test_metadata_route_retains_original_artifact_identity(client,monkeypatch):
    monkeypatch.setattr(main,'get_artifact',lambda aid:{'artifact_id':aid,'immutable':True})
    response=client.get('/v1/admin/artifacts/'+AID,headers={'Authorization':'Bearer isolated-read-test-key'})
    assert response.status_code==200 and response.json()['artifact_id']==AID


def test_unconfigured_key_fails_closed(client,monkeypatch):
    monkeypatch.setattr(main,'settings',replace(main.settings,api_key=''))
    assert client.get('/v1/admin/artifacts/'+AID,headers={'Authorization':'Bearer anything'}).status_code==503


def test_signed_mutation_still_rejects_unsigned_bearer_only(client):
    response=client.post('/v1/admin/artifacts',json={},headers={'Authorization':'Bearer isolated-read-test-key'})
    assert response.status_code==401
    assert 'timestamp' in response.json()['detail']
