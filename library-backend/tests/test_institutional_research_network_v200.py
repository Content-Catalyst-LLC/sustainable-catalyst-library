from __future__ import annotations

from app.institutional_research_network import (
    DSpaceNetworkConnector,
    DataverseNetworkConnector,
    InstitutionalNetworkSourceDescriptor,
    InstitutionalResearchNetwork,
    InstitutionalResearchNetworkError,
    OAIPMHNetworkConnector,
    build_network_connectors,
    normalize_doi,
)


class FakeConnector:
    def __init__(self, key: str, records: list[dict], *, fail: bool = False, mode: str = "native-search") -> None:
        self.descriptor = InstitutionalNetworkSourceDescriptor(
            key=key,
            institution=f"Institution {key}",
            repository=f"Repository {key}",
            source_family="fake",
            base_url=f"https://{key}.example.test",
            search_mode=mode,
            capabilities=("search", "metadata", "provenance"),
        )
        self.records = records
        self.fail = fail

    def search(self, query: str, *, limit: int = 8):
        if self.fail:
            raise InstitutionalResearchNetworkError("contained")
        return {
            "records": self.records[:limit],
            "total": len(self.records),
            "search_mode": self.descriptor.search_mode,
            "search_limitations": [],
        }


def record(key: str, *, title: str, doi: str | None = None, pid: str | None = None, url: str | None = None):
    return {
        "source_key": key,
        "institution": f"Institution {key}",
        "repository": f"Repository {key}",
        "source_family": "fake",
        "record_type": "dataset",
        "title": title,
        "persistent_id": pid,
        "doi": doi,
        "authors": ["A. Researcher"],
        "description": "Description",
        "subjects": ["Climate"],
        "keywords": [],
        "published_at": "2025-01-01",
        "updated_at": None,
        "source_url": url,
        "citation": None,
        "license": {"name": "CC BY 4.0", "url": None, "commercial_reuse": True, "reuse_requires_review": False},
        "access_state": "public-metadata",
        "provenance": {"source_key": key, "retrieved_from": f"https://{key}.example.test"},
    }


def test_default_registry_exposes_four_governed_sources():
    rows = [connector.descriptor.to_dict() for connector in build_network_connectors(3)]
    assert [row["key"] for row in rows] == [
        "mit-dspace",
        "harvard-dataverse",
        "johns-hopkins-dataverse",
        "ucd-research-repository",
    ]
    assert rows[0]["source_family"] == "dspace-rest"
    assert rows[-1]["search_mode"] == "bounded-metadata-harvest"
    assert all(row["affiliation_asserted"] is False for row in rows)


def test_manifest_declares_identity_rights_and_affiliation_boundaries():
    network = InstitutionalResearchNetwork(connectors=[FakeConnector("one", [])])
    payload = network.manifest()
    gov = payload["framework"]["governance"]
    assert gov["repository_discovery_is_entitlement"] is False
    assert gov["metadata_visibility_is_reuse_permission"] is False
    assert gov["title_only_identity_merge"] is False
    assert gov["cross_source_author_identity_inferred"] is False
    assert gov["partnership_asserted"] is False
    assert payload["identity_policy"]["priority"][0] == "exact-normalized-doi"


def test_normalize_doi_handles_url_and_prefix():
    assert normalize_doi("https://doi.org/10.1234/ABC.1") == "10.1234/abc.1"
    assert normalize_doi("doi:10.5555/Test-2") == "10.5555/test-2"
    assert normalize_doi("not-a-doi") is None


def test_dataverse_search_normalizes_record(monkeypatch):
    descriptor = InstitutionalNetworkSourceDescriptor(
        key="harvard-dataverse", institution="Harvard University", repository="Harvard Dataverse",
        source_family="dataverse", base_url="https://dataverse.harvard.edu", search_mode="native-search",
        capabilities=("search", "metadata"),
    )
    connector = DataverseNetworkConnector(descriptor, timeout_seconds=3)
    monkeypatch.setattr(connector, "_get_json", lambda path, params=None: {
        "status": "OK",
        "data": {"total_count": 1, "items": [{
            "type": "dataset", "name": "Urban heat data", "global_id": "doi:10.7910/DVN/TEST",
            "authors": ["A. Author"], "subjects": ["Earth and Environmental Sciences"],
            "description": "Dataset metadata", "url": "https://doi.org/10.7910/DVN/TEST",
        }]},
    })
    row = connector.search("heat", limit=5)["records"][0]
    assert row["doi"] == "10.7910/dvn/test"
    assert row["institution"] == "Harvard University"
    assert row["source_family"] == "dataverse"
    assert row["access_state"] == "public-metadata"


def test_dspace_search_reads_discovery_object_metadata(monkeypatch):
    descriptor = InstitutionalNetworkSourceDescriptor(
        key="mit-dspace", institution="Massachusetts Institute of Technology", repository="DSpace@MIT",
        source_family="dspace-rest", base_url="https://dspace.mit.edu", search_mode="native-search",
        capabilities=("search", "metadata"),
    )
    connector = DSpaceNetworkConnector(descriptor, timeout_seconds=3)
    monkeypatch.setattr(connector, "_get_json", lambda path, params=None: {
        "_embedded": {"searchResult": {"_embedded": {"objects": [{"indexableObject": {
            "type": "item", "uuid": "abc", "name": "Climate resilience",
            "metadata": {
                "dc.contributor.author": [{"value": "M. Scholar"}],
                "dc.identifier.uri": [{"value": "https://hdl.handle.net/1721.1/12345"}],
                "dc.identifier.doi": [{"value": "10.1000/MIT.TEST"}],
                "dc.description.abstract": [{"value": "Abstract"}],
                "dc.subject": [{"value": "Climate"}],
                "dc.date.issued": [{"value": "2025"}],
            },
        }}]}}},
        "page": {"totalElements": 1},
    })
    row = connector.search("climate", limit=5)["records"][0]
    assert row["doi"] == "10.1000/mit.test"
    assert row["persistent_id"] == "https://hdl.handle.net/1721.1/12345"
    assert row["authors"] == ["M. Scholar"]
    assert row["repository"] == "DSpace@MIT"


def test_oai_search_is_bounded_and_declares_limitation(monkeypatch):
    descriptor = InstitutionalNetworkSourceDescriptor(
        key="ucd-research-repository", institution="University College Dublin", repository="Research Repository UCD",
        source_family="oai-pmh", base_url="https://researchrepository.ucd.ie", search_mode="bounded-metadata-harvest",
        capabilities=("metadata-harvest",),
    )
    connector = OAIPMHNetworkConnector(descriptor, timeout_seconds=3, max_harvest_records=25)
    xml = b'''<?xml version="1.0" encoding="UTF-8"?>
    <OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">
      <ListRecords><record><header><identifier>oai:ucd:1</identifier><datestamp>2026-01-01</datestamp></header>
      <metadata><oai_dc:dc><dc:title>Climate adaptation in Dublin</dc:title><dc:creator>A. UCD</dc:creator><dc:subject>Climate</dc:subject><dc:identifier>https://doi.org/10.1234/UCD.1</dc:identifier></oai_dc:dc></metadata>
      </record></ListRecords></OAI-PMH>'''
    monkeypatch.setattr(connector, "_get_xml", lambda path, params=None: xml)
    result = connector.search("climate adaptation", limit=5)
    assert result["records"][0]["doi"] == "10.1234/ucd.1"
    assert result["search_mode"] == "bounded-metadata-harvest"
    assert "not an arbitrary repository full-text search API" in result["search_limitations"][0]


def test_exact_doi_consolidates_cross_source_observations_and_preserves_provenance():
    a = record("a", title="Same work A", doi="10.1234/ABC", pid="a1")
    b = record("b", title="Same work B", doi="https://doi.org/10.1234/abc", pid="b1")
    network = InstitutionalResearchNetwork(connectors=[FakeConnector("a", [a]), FakeConnector("b", [b])])
    payload = network.search("work")
    assert payload["record_count"] == 1
    assert payload["observation_count"] == 2
    assert payload["duplicate_observation_consolidation_count"] == 1
    row = payload["records"][0]
    assert row["identity_key"] == "doi:10.1234/abc"
    assert row["source_keys"] == ["a", "b"]
    assert len(row["provenance_ledger"]) == 2


def test_same_title_without_identifier_never_merges_across_sources():
    a = record("a", title="Shared title", pid="a-1")
    b = record("b", title="Shared title", pid="b-1")
    network = InstitutionalResearchNetwork(connectors=[FakeConnector("a", [a]), FakeConnector("b", [b])])
    payload = network.search("shared")
    assert payload["record_count"] == 2
    assert payload["duplicate_observation_consolidation_count"] == 0
    assert payload["reproducibility"]["title_only_merge_used"] is False


def test_partial_source_failure_does_not_destroy_successful_results():
    good = FakeConnector("good", [record("good", title="Available", pid="1")])
    bad = FakeConnector("bad", [], fail=True)
    payload = InstitutionalResearchNetwork(connectors=[good, bad]).search("available")
    assert payload["network_state"] == "partial"
    assert payload["record_count"] == 1
    assert payload["source_status"]["bad"]["state"] == "unavailable"
    assert payload["source_status"]["good"]["state"] == "available"
    assert payload["errors"] == [{"source_key": "bad", "error": "InstitutionalResearchNetworkError"}]


def test_content_fingerprint_is_deterministic_across_retrieval_times():
    fake = FakeConnector("one", [record("one", title="Stable", doi="10.1000/stable")])
    network = InstitutionalResearchNetwork(connectors=[fake])
    first = network.search("stable")
    second = network.search("stable")
    assert first["retrieved_at"] != second["retrieved_at"] or first["source_status"]["one"]["checked_at"] != second["source_status"]["one"]["checked_at"]
    assert first["reproducibility"]["content_fingerprint"] == second["reproducibility"]["content_fingerprint"]
    assert first["reproducibility"]["retrieval_timestamps_excluded_from_fingerprint"] is True


def test_graph_is_deterministic_and_uses_bounded_relationship_types():
    fake = FakeConnector("one", [record("one", title="Graph record", doi="10.1000/graph")])
    network = InstitutionalResearchNetwork(connectors=[fake])
    first = network.graph("graph")
    second = network.graph("graph")
    assert first["reproducibility"]["graph_fingerprint"] == second["reproducibility"]["graph_fingerprint"]
    edge_types = {edge["type"] for edge in first["graph"]["edges"]}
    assert edge_types <= {"retrieved-for-question", "held-by-repository", "repository-belongs-to-institution", "licensed-under"}
    assert {node["type"] for node in first["graph"]["nodes"]} >= {"research-question", "institution", "repository", "research-record"}


def test_handoffs_do_not_infer_file_access_or_reuse_permission():
    payload = InstitutionalResearchNetwork(connectors=[FakeConnector("one", [])]).search("topic")
    lab = payload["handoffs"]["lab"]
    assert lab["dataset_metadata_only_until_access_verified"] is True
    assert lab["underlying_file_access_inferred"] is False
    assert lab["reuse_permission_inferred"] is False
