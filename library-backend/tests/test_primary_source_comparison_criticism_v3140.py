from app.primary_source_comparison_criticism import analyze_source, comparison_matrix, corroboration_ledger, readiness


def sample(title, year, creator):
    return {"title":title,"source_type":"personal-papers","creators":[creator],"date":str(year),"repository":{"name":"Archive"},"archival_context":{"collection":"Papers","shelfmark":title},"original_language":"en"}


def test_readiness_and_no_scoring():
    r=readiness(); assert r["library_version"]=="6.14.0"; assert r["backend_version"]=="3.14.0"; assert r["wordpress_required"] is False
    a=analyze_source({"source":sample("A",1971,"A. Person")}); assert a["truth_score"] is None; assert a["reliability_score"] is None; assert a["authenticity_certified"] is False


def test_matrix_and_corroboration_preserve_judgment():
    a=sample("A",1971,"A. Person"); b=sample("B",1972,"B. Person")
    m=comparison_matrix({"sources":[a,b]}); assert m["source_count"]==2; assert m["automatic_ranking"] is False; assert m["disagreement_auto_resolved"] is False
    sid=m["sources"][0]["primary_source_id"]
    ledger=corroboration_ledger({"sources":[a,b],"claims":[{"text":"Test claim","observations":[{"source_id":sid,"relation":"supports","basis":"explicit human observation"}]}]})
    assert ledger["claims"][0]["adjudicated"] is False; assert ledger["truth_determination"] is None
