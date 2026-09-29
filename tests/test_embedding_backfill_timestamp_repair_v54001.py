from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

def test_release_identity():
    plugin = read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.40.0.1' in plugin
    assert "define('SC_LIBRARY_VERSION', '5.40.0.1');" in plugin
    assert '__version__ = "2.51.1"' in read('library-backend/app/__init__.py')

def test_backfill_uses_real_library_record_timestamps():
    src = read('library-backend/app/embedding_governance.py')
    assert 'ORDER BY r.updated_at' not in src
    assert 'ORDER BY COALESCE(r.source_updated_at,r.indexed_at,r.created_at) ASC,r.record_id ASC' in src

def test_schema_has_referenced_timestamp_columns():
    schema = read('library-backend/app/schema.sql')
    assert 'source_updated_at timestamptz' in schema
    assert 'indexed_at timestamptz' in schema
    assert 'created_at timestamptz' in schema
