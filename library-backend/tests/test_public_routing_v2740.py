from app.public_routing import contract, readiness, canonical_url, record_seo_descriptor, record_embed_descriptor

def test_contract_identity_and_origin():
    c=contract(); assert c['schema']=='sc-library-public-routing-seo-embed-bridge/1.0'
    assert c['library_version']=='5.63.0' and c['backend_version']=='2.74.0' and c['web_version']=='1.2.0'
    assert c['public_origin'].startswith('https://')

def test_route_policy():
    c=contract(); routes={x['name']:x for x in c['routes']}
    assert routes['record']['index'] is True and routes['search']['index'] is False
    assert routes['account']['index'] is False and routes['system']['index'] is False

def test_canonical_record_url_is_encoded():
    url=canonical_url('record',record_id='doc:abc/123')
    assert url.endswith('/record/doc%3Aabc%2F123')

def test_record_seo_descriptor_is_metadata_not_truth():
    d=record_seo_descriptor('r1',{'title':'Test Record','summary':'A research summary.'})
    assert d['robots']=='index,follow' and d['canonical_url'].endswith('/record/r1')
    assert d['guardrails']['descriptor_implies_research_truth'] is False

def test_embed_descriptor_has_no_write_authority():
    d=record_embed_descriptor('r1',{'title':'Record'})
    assert d['src'].endswith('/record/r1?embed=1') and d['write_authority'] is False
    assert 'allow-scripts' in d['sandbox']

def test_readiness():
    r=readiness(); assert r['state']=='ready' and r['wordpress_required'] is False and r['route_count']==6
