from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]


def test_post_corpus_transport_route_and_manifest_contract_are_present():
    main=(ROOT/'library-backend/app/main.py').read_text()
    assert '@app.post("/v1/publication-knowledge-maps/corpus")' in main
    assert 'def publication_corpus_knowledge_maps_post(payload: dict[str, Any])' in main
    assert 'record_ids_raw = payload.get("record_ids") or []' in main
    assert 'record_ids=record_ids[:1000]' in main
    assert 'max_publications=int(payload.get("max_publications", 250))' in main
    assert 'max_topics_per_publication=int(payload.get("max_topics_per_publication", 36))' in main


def test_backend_health_advertises_async_post_transport():
    main=(ROOT/'library-backend/app/main.py').read_text()
    assert '"publication_async_corpus_transport": True' in main
    assert '"publication_corpus_post_transport": True' in main
