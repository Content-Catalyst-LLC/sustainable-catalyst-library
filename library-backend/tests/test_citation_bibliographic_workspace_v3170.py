from app.citation_bibliographic_workspace import (
    bootstrap, readiness, normalize_bibliographic_item, duplicate_analysis,
    build_bibliography, export_bibliography, citation_authority_handoff_preview,
)

def test_readiness_versions_and_boundaries():
    r=readiness(); assert r['library_version']=='6.17.0'; assert r['backend_version']=='3.17.0'; assert r['web_version']=='2.17.0'; assert r['sdk_version']=='1.17.0'; assert r['ready'] is True; assert r['database_migration_required'] is False; assert r['wordpress_required'] is False; assert r['server_side_bibliography_persistence'] is False

def test_bootstrap_contract():
    b=bootstrap(); assert b['route']=='/research/citations'; assert 'article-journal' in b['item_types']; assert 'bibtex' in b['export_formats']; assert b['browser_storage_key']=='sc-library-bibliography-v1'

def test_normalization_identifier_and_guardrails():
    x=normalize_bibliographic_item({'title':'Example Study','type':'article-journal','authors':['Doe, Jane'],'year':2025,'doi':'https://doi.org/10.1000/XYZ','journal':'Journal X'})
    assert x['identifiers']['doi']=='10.1000/xyz'; assert x['authors'][0]['family']=='Doe'; assert x['csl']['DOI']=='10.1000/xyz'; assert x['truth_status'] is None; assert x['evidence_status'] is None; assert x['persisted'] is False; assert x['citation_key_globally_unique'] is False

def test_duplicate_analysis_does_not_merge():
    p={'items':[{'title':'Same Work','authors':['Doe, Jane'],'year':2020,'doi':'10.1/abc'},{'title':'Same Work','authors':['Doe, Jane'],'year':2020,'doi':'https://doi.org/10.1/abc'}]}
    d=duplicate_analysis(p); assert d['candidate_count']==1; c=d['candidates'][0]; assert 'exact-doi' in c['rules']; assert c['auto_merged'] is False; assert c['same_work_asserted'] is False; assert c['human_review_required'] is True

def test_bibliography_and_exports():
    p={'title':'Test Bibliography','items':[{'title':'Book A','type':'book','authors':['Smith, Alex'],'year':2022,'isbn':'978-1-234567-89-0'},{'title':'Paper B','type':'article-journal','authors':['Jones, Pat'],'year':2024,'doi':'10.2/paper'}]}
    b=build_bibliography(p); assert b['item_count']==2; assert b['bibliography_order_implies_rank'] is False; assert b['persisted'] is False
    for fmt in ('json','bibtex','ris'):
        e=export_bibliography({**p,'format':fmt}); assert e['format']==fmt; assert e['content']; assert len(e['content_sha256'])==64; assert e['automatic_import'] is False; assert e['persisted'] is False

def test_handoff_is_preview_only():
    h=citation_authority_handoff_preview({'citing_record_id':'record-a','cited_record_id':'record-b','item':{'title':'Linked Work','authors':['Doe, Jane'],'year':2024}})
    assert h['ready_for_durable_record_edge'] is True; assert h['automatic_persistence'] is False; assert h['requires_explicit_signed_write'] is True; assert h['workspace_did_not_create_citation'] is True
