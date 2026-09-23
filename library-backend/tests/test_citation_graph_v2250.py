from __future__ import annotations

import sys
import types
from pathlib import Path

import pytest
from pydantic import ValidationError

if "psycopg" not in sys.modules:
    psycopg = types.ModuleType("psycopg"); psycopg.__path__ = []
    rows = types.ModuleType("psycopg.rows"); rows.dict_row = object()
    types_pkg = types.ModuleType("psycopg.types"); types_pkg.__path__ = []
    json_pkg = types.ModuleType("psycopg.types.json")
    class Jsonb:
        def __init__(self, value): self.value = value
    json_pkg.Jsonb = Jsonb
    pool_pkg = types.ModuleType("psycopg_pool")
    class ConnectionPool: pass
    pool_pkg.ConnectionPool = ConnectionPool
    sys.modules.update({"psycopg":psycopg,"psycopg.rows":rows,"psycopg.types":types_pkg,"psycopg.types.json":json_pkg,"psycopg_pool":pool_pkg})

from app.citation_graph import CitationCreateRequest, normalize_identifier, stable_citation_key
from app.platform_core import CORE_OPERATIONS


def test_identifier_normalization_is_exact_and_deterministic():
    assert normalize_identifier("doi", "https://doi.org/10.1000/ABC") == "10.1000/abc"
    assert normalize_identifier("pmid", "PMID: 12345") == "12345"
    assert normalize_identifier("pmcid", "pmcid: pmc123") == "PMC123"
    assert normalize_identifier("isbn", "978-1-4028-9462-6") == "9781402894626"


def test_citation_contract_rejects_invented_empty_targets():
    with pytest.raises(ValidationError):
        CitationCreateRequest(citing_record_id="record:1")


def test_citation_contract_preserves_declared_confidence_and_relation():
    item = CitationCreateRequest(citing_record_id="record:1", identifier_type="doi", identifier_value="doi:10.1/Test", relation_type="extends", extraction_method="metadata", confidence=0.7)
    assert item.identifier_value == "10.1/test"
    assert item.relation_type == "extends"
    assert item.confidence == 0.7


def test_stable_citation_keys_are_order_sensitive_and_repeatable():
    a = stable_citation_key("a", "b", "doi", "10.1/x", "", "cites", "")
    b = stable_citation_key("a", "b", "doi", "10.1/x", "", "cites", "")
    c = stable_citation_key("b", "a", "doi", "10.1/x", "", "cites", "")
    assert a == b and a != c and len(a) == 64


def test_core_scholarly_citation_operation_is_allowlisted():
    assert CORE_OPERATIONS["scholarly-citation.create"] == ("POST", "/v1/research/scholarly-packages/citations")


def test_release_identity_and_schema():
    repo = Path(__file__).resolve().parents[2]
    plugin = (repo / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    schema = (repo / "library-backend/app/schema.sql").read_text()
    main = (repo / "library-backend/app/main.py").read_text()
    assert "Plugin Name: Sustainable Catalyst Library" in plugin
    assert "SC_LIBRARY_VERSION" in plugin
    assert "__version__" in (repo / "library-backend/app/__init__.py").read_text()
    assert "CREATE TABLE IF NOT EXISTS library_citations" in schema
    assert '@app.get("/v1/citations/readiness")' in main
    assert '@app.post("/v1/citations/core-handoff")' in main


def test_platform_core_boundary_is_explicit():
    repo = Path(__file__).resolve().parents[2]
    source = (repo / "library-backend/app/citation_graph.py").read_text()
    assert '"platform_core_owns_governed_lineage": True' in source
    assert '"llm_inferred_citations": False' in source
    assert '"explicit_governed_promotion": True' in source
