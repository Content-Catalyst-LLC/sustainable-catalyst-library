from app.source_identity_resolution import (
    SOURCE_IDENTITY_CONTRACT,
    SOURCE_IDENTITY_CORPUS_CONTRACT,
    build_source_identity_analysis,
    build_source_identity_resolution,
    normalize_doi,
    normalize_orcid,
    normalize_ror,
    normalize_url,
)


def record(rid, title, *, source="source-a", doi=None, url=None, content_hash=None, authors=None, year="2025-01-01", metadata=None):
    identifiers = {"doi": doi} if doi else {}
    return {
        "record_id": rid,
        "source_key": source,
        "title": title,
        "canonical_url": url,
        "content_hash": content_hash,
        "published_at": year,
        "authors": authors or [],
        "identifiers": identifiers,
        "metadata": metadata or {},
    }


def multi_clusters(result):
    return [x for x in result["source_identity_clusters"] if x["member_count"] > 1]


def test_identifier_normalizers_are_deterministic():
    assert normalize_doi("https://doi.org/10.1000/ABC.12") == "10.1000/abc.12"
    assert normalize_orcid("https://orcid.org/0000-0002-1825-0097") == "0000-0002-1825-0097"
    assert normalize_ror("https://ror.org/03vek6s52") == "https://ror.org/03vek6s52"
    assert normalize_url("https://EXAMPLE.org/paper/?utm_source=x&b=2&a=1#frag") == "https://example.org/paper?a=1&b=2"


def test_same_doi_with_different_content_is_a_version_family_not_a_merge():
    result = build_source_identity_resolution([
        record("a", "Study", doi="10.1000/xyz", content_hash="a" * 64),
        record("b", "Study revised", source="source-b", doi="https://doi.org/10.1000/XYZ", content_hash="b" * 64),
    ])
    clusters = multi_clusters(result)
    assert len(clusters) == 1
    cluster = clusters[0]
    assert cluster["classification"] == "same-work-version-family"
    assert cluster["version_family"] is True
    assert cluster["automatic_merge"] is False
    assert cluster["records_deleted"] is False
    assert set(cluster["member_record_ids"]) == {"a", "b"}


def test_exact_content_hash_can_identify_duplicate_representation():
    result = build_source_identity_resolution([
        record("a", "Copy A", content_hash="c" * 64),
        record("b", "Copy B", source="source-b", content_hash="c" * 64),
    ])
    cluster = multi_clusters(result)[0]
    assert cluster["classification"] == "exact-content-duplicate-cluster"
    assert "exact-content-hash" in cluster["identity_basis"]


def test_normalized_canonical_url_ignores_tracking_but_preserves_source_records():
    result = build_source_identity_resolution([
        record("a", "Web paper", url="https://Example.org/paper/?utm_source=x&a=1"),
        record("b", "Web paper mirror", source="source-b", url="https://example.org/paper?a=1#section"),
    ])
    cluster = multi_clusters(result)[0]
    assert cluster["classification"] == "same-normalized-url-cluster"
    assert result["boundaries"]["automatic_record_merge"] is False
    assert result["boundaries"]["automatic_record_deletion"] is False


def test_title_author_year_is_candidate_only_and_never_identity():
    result = build_source_identity_resolution([
        record("a", "Climate Adaptation", authors=["Jane Doe"], year="2025-02-01"),
        record("b", "Climate Adaptation", source="source-b", authors=["Jane Doe"], year="2025-08-10"),
    ])
    assert multi_clusters(result) == []
    assert len(result["duplicate_candidates"]) == 1
    candidate = result["duplicate_candidates"][0]
    assert candidate["requires_review"] is True
    assert candidate["identity_determined"] is False
    assert candidate["automatic_merge"] is False
    assert result["boundaries"]["title_only_identity_merge"] is False


def test_orcid_ror_and_dataset_doi_resolve_cross_source_entities():
    metadata_a = {
        "authors": [{"name": "Jane Doe", "orcid": "0000-0002-1825-0097"}],
        "institutions": [{"name": "Example University", "ror": "03vek6s52"}],
        "datasets": [{"name": "Study data", "doi": "10.5555/DATA.1"}],
    }
    metadata_b = {
        "authors": [{"name": "J. Doe", "orcid": "https://orcid.org/0000-0002-1825-0097"}],
        "institutions": [{"name": "Example Univ.", "ror": "https://ror.org/03vek6s52"}],
        "datasets": [{"name": "Data supplement", "doi": "https://doi.org/10.5555/data.1"}],
    }
    result = build_source_identity_resolution([
        record("a", "One", metadata=metadata_a),
        record("b", "Two", source="source-b", metadata=metadata_b),
    ])
    identities = result["entity_resolution"]["identities"]
    kinds = {x["kind"] for x in identities}
    assert {"author", "institution", "dataset"}.issubset(kinds)
    author = next(x for x in identities if x["kind"] == "author")
    institution = next(x for x in identities if x["kind"] == "institution")
    dataset = next(x for x in identities if x["kind"] == "dataset")
    assert set(author["record_ids"]) == {"a", "b"}
    assert author["identifier_type"] == "orcid"
    assert institution["identifier_type"] == "ror"
    assert dataset["identifier_type"] == "doi"
    assert result["boundaries"]["author_name_only_cross_source_merge"] is False


def test_graph_overlay_distinguishes_identity_from_review_candidate_edges():
    result = build_source_identity_resolution([
        record("a", "Study", doi="10.1000/x", authors=["Jane Doe"], year="2025-01-01"),
        record("b", "Study v2", source="source-b", doi="10.1000/x", content_hash="b" * 64),
        record("c", "Other", metadata={"authors": [{"name": "Jane Doe", "orcid": "0000-0002-1825-0097"}]}),
    ])
    overlay = result["graph_overlay"]
    bases = {e["relationship_basis"] for e in overlay["edges"]}
    assert "member-of-source-identity" in bases
    assert all(e.get("truth_assertion") is False for e in overlay["edges"])


def test_stateless_analysis_uses_public_contract():
    result = build_source_identity_analysis({"records": [record("a", "One")]})
    assert result["schema"] == SOURCE_IDENTITY_CONTRACT
    assert result["identity_contract"] == SOURCE_IDENTITY_CONTRACT
    assert result["platform_core"]["durable_research_object_authority"] == "platform-core"
    corpus = build_source_identity_resolution([record("a", "One")])
    assert corpus["schema"] == SOURCE_IDENTITY_CORPUS_CONTRACT
