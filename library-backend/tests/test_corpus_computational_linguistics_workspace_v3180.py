from app.corpus_computational_linguistics_workspace import (
    cooccurrence_analysis, export_analysis, frequency_analysis, guardrails,
    ngram_analysis, persistence_handoff_preview, preview_corpus, kwic_analysis,
)

SAMPLE = {
    "title": "Small multilingual-aware corpus",
    "documents": [
        {"representation_id":"rep:original:1","source_kind":"original","language_bcp47":"en","script_iso15924":"Latn","text":"Carbon systems change. Carbon systems adapt. Evidence requires context."},
        {"representation_id":"rep:ocr:2","source_kind":"ocr","language_bcp47":"en","script_iso15924":"Latn","derivation_run_id":"ocr:run:2","text":"Carbon evidence changes with context."},
    ],
}

def test_guardrails():
    g=guardrails()
    assert g["existing_v547_linguistic_corpus_is_durable_authority"] is True
    assert g["workspace_creates_parallel_corpus_store"] is False
    assert g["frequency_implies_importance"] is False
    assert g["kwic_context_establishes_meaning_or_intent"] is False
    assert g["cooccurrence_establishes_semantic_relationship"] is False

def test_preview():
    out=preview_corpus(SAMPLE)
    assert out["mode"]=="preview"
    assert out["workspace_persisted"] is False
    assert out["corpus"]["document_count"]==2
    assert out["corpus"]["source_kind_distribution"]["original"]==1
    assert out["corpus"]["source_kind_distribution"]["ocr"]==1

def test_frequency_and_kwic():
    freq=frequency_analysis({**SAMPLE,"limit":20})
    rows={x["normalized_text"]:x["count"] for x in freq["rows"]}
    assert rows["carbon"]==3
    kwic=kwic_analysis({**SAMPLE,"query":"carbon","window_tokens":3})
    assert kwic["total_matches"]==3
    assert all(x["representation_id"] for x in kwic["matches"])

def test_ngram_and_cooccurrence():
    ng=ngram_analysis({**SAMPLE,"n":2,"limit":30})
    assert any(row["ngram"]=="carbon systems" for row in ng["rows"])
    co=cooccurrence_analysis({**SAMPLE,"term":"carbon","window_tokens":3})
    assert co["target_occurrences"]==3
    assert co["guardrails"]["cooccurrence_count_is_statistical_significance"] is False

def test_persistence_handoff_is_preview_only():
    h=persistence_handoff_preview(SAMPLE)
    assert h["preview_only"] is True
    assert h["automatic_persistence"] is False
    assert h["signed_request_required"] is True
    assert h["create_endpoint"]=="/api/library/v1/admin/language/corpora"

def test_export():
    out=export_analysis({**SAMPLE,"query":"context","n":2,"term":"carbon"})
    assert out["automatic_import"] is False
    assert out["workspace_persisted"] is False
    assert out["media_type"]=="application/json"
    assert out["content"]
