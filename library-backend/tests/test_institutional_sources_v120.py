from app.institutional_sources import JohnsHopkinsDataverseConnector, build_registry


def test_registry_exposes_johns_hopkins_dataverse():
    sources = build_registry(3).list_sources()
    assert len(sources) == 1
    row = sources[0]
    assert row["key"] == "johns-hopkins-dataverse"
    assert row["institution"] == "Johns Hopkins University"
    assert "license" in row["capabilities"]


def test_search_normalizes_metadata_without_downloading_files(monkeypatch):
    connector = JohnsHopkinsDataverseConnector(timeout_seconds=3)
    monkeypatch.setattr(connector, "_get_json", lambda path, params=None: {
        "status": "OK",
        "data": {"total_count": 1, "items": [{
            "type": "dataset",
            "name": "Heat and health",
            "global_id": "doi:10.7281/T1TEST",
            "authors": ["A. Researcher"],
            "description": "Public metadata only",
            "subjects": ["Medicine, Health and Life Sciences"],
            "url": "https://archive.data.jhu.edu/dataset.xhtml?persistentId=doi:10.7281/T1TEST",
        }]},
    })
    payload = connector.search("heat", limit=10)
    assert payload["total"] == 1
    result = payload["results"][0]
    assert result["persistent_id"] == "doi:10.7281/T1TEST"
    assert result["institution"] == "Johns Hopkins University"
    assert result["access_state"] == "public-metadata"
    assert "files" not in result


def test_record_preserves_license_and_file_restriction(monkeypatch):
    connector = JohnsHopkinsDataverseConnector(timeout_seconds=3)
    monkeypatch.setattr(connector, "_get_json", lambda path, params=None: {
        "status": "OK",
        "data": {"latestVersion": {
            "datasetPersistentId": "doi:10.7281/T1TEST",
            "versionNumber": 2,
            "versionState": "RELEASED",
            "releaseTime": "2026-01-01T00:00:00Z",
            "license": {"name": "CC BY-NC 4.0", "uri": "https://creativecommons.org/licenses/by-nc/4.0/"},
            "metadataBlocks": {"citation": {"fields": [
                {"typeName": "title", "value": "Heat and health"},
                {"typeName": "subject", "value": ["Medicine, Health and Life Sciences"]},
                {"typeName": "author", "value": [{"authorName": {"value": "A. Researcher"}}]},
                {"typeName": "dsDescription", "value": [{"dsDescriptionValue": {"value": "Study description"}}]},
            ]}},
            "files": [{"restricted": True, "dataFile": {"id": 42, "filename": "data.csv", "filesize": 100}}],
        }},
    })
    row = connector.get_record("doi:10.7281/T1TEST")["record"]
    assert row["title"] == "Heat and health"
    assert row["license"]["commercial_reuse"] is False
    assert row["files"][0]["restricted"] is True
    assert row["provenance"]["source_key"] == "johns-hopkins-dataverse"
