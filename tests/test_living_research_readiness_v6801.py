from pathlib import Path


def test_living_readiness_accepts_degraded_ready_discovery():
    text = Path("library-backend/app/living_collections_projects.py").read_text()
    assert 'discovery_ready = bool(discovery.get("ready", discovery.get("state") in {"ready", "degraded"}))' in text
    assert 'if not discovery_ready:' in text
    assert 'degraded = discovery.get("state") == "degraded" or graph.get("state") == "degraded"' in text
    assert 'readiness_state = "blocked" if errors else ("degraded" if degraded else "ready")' in text
    assert '"state": readiness_state' in text
    assert '"ready": not errors' in text
    assert 'if discovery.get("state") != "ready":' not in text
