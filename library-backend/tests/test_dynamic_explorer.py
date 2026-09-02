from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
QUERY = ROOT / "app/query.py"
MAIN = ROOT / "app/main.py"


def source(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_query_normalization_helpers_are_bounded():
    text = source(QUERY)
    assert 'SORTS = {"relevance", "updated", "newest", "oldest", "title"}' in text
    assert 'return "updated"' in text
    assert 'return " ".join(str(value or "").split()).strip()' in text


def test_explorer_backend_routes_exist():
    text = source(MAIN)
    assert '@app.get("/v1/explorer/bootstrap")' in text
    assert 'topic: str | None = Query' in text
    assert 'year_from: int | None = Query' in text
    assert 'year_to: int | None = Query' in text
    assert 'include_body: bool = Query(default=True)' in text


def test_progressive_detail_query_is_bounded():
    text = source(QUERY)
    assert 'left(body_text, 1600) AS body_text' in text
    assert 'row["chunks"] = []' in text
    assert 'featured_limit: int = 4' in text
    assert 'recent_limit: int = 4' in text
