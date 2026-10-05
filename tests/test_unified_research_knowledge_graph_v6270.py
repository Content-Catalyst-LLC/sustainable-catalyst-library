from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def text(rel):
    return (ROOT / rel).read_text()


def test_release_generations_and_surface():
    assert '__version__ = "3.27.0"' in text('library-backend/app/__init__.py')
    main = text('library-backend/app/main.py')
    assert 'unified_research_knowledge_graph' in main
    assert '"unified_research_knowledge_graph": True' in main
    assert '@app.get("/api/library/v1/research-knowledge-graph")' in main
    assert '@app.post("/api/library/v1/research-knowledge-graph/build")' in main
    assert '@app.post("/api/library/v1/research-knowledge-graph/path")' in main
    assert '@app.post("/api/library/v1/research-knowledge-graph/export")' in main


def test_independent_api_and_clients():
    api = text('library-backend/app/independent_api.py')
    assert '/api/library/v1/research-knowledge-graph/bootstrap' in api
    assert '"unified-research-knowledge-graph"' in api
    py = text('clients/python/sustainable_catalyst_library/client.py')
    assert 'def unified_research_knowledge_graph' in py
    assert 'def build_unified_research_knowledge_graph' in py
    js = text('clients/javascript/src/index.js')
    assert 'unifiedResearchKnowledgeGraph()' in js
    assert 'buildUnifiedResearchKnowledgeGraph(payload)' in js


def test_web_and_adapter():
    config = text('library-web/config.js')
    assert 'webVersion: "2.27.0"' in config
    html = text('library-web/index.html')
    assert 'data-view="research-graph"' in html
    assert '/research/graph' in html
    assert 'Web v2.27.0 · API v1' in html
    js = text('library-web/assets/app.js')
    assert 'researchKnowledgeGraphBootstrap' in js
    assert 'loadUnifiedResearchKnowledgeGraph' in js
    php = text('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert "define('SC_LIBRARY_VERSION', '6.27.0');" in php
    assert "define('SC_LIBRARY_UNIFIED_RESEARCH_KNOWLEDGE_GRAPH_ROUTE', '/research/graph');" in php


def test_next_release_is_628():
    assert '"next_release": "6.28.0"' in text('library-backend/app/research_interface.py')
    assert '"next_release": "6.28.0"' in text('library-backend/app/navigation_service.py')
    assert "define('SC_LIBRARY_NEXT_ARCHITECTURE_RELEASE', '6.28.0');" in text('sustainable-catalyst-library/sustainable-catalyst-library.php')
