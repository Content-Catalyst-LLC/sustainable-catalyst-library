from app.scientific_document_intelligence import (
    SCIENTIFIC_DOCUMENT_CONTRACT,
    SCIENTIFIC_GRAPH_OVERLAY_CONTRACT,
    SCIENTIFIC_OBJECT_CONTRACT,
    build_scientific_corpus_overlay,
    build_scientific_document_intelligence,
)


def document():
    return {
        "record_id": "pub:science",
        "title": "Scientific document",
        "source_content_hash": "a" * 64,
        "scientific_objects": [
            {
                "kind": "table",
                "label": "Table 1",
                "caption": "Observed results",
                "page": 3,
                "bbox": [10, 20, 300, 420],
                "columns": ["Year", "Value"],
                "rows": [[2024, 10.2], [2025, 11.1]],
                "human_reviewed": True,
            },
            {
                "kind": "chart",
                "label": "Figure 2",
                "caption": "Measured trend",
                "page": 4,
                "data_series": [{"name": "Observed", "points": [[2024, 10.2], [2025, 11.1]]}],
            },
            {"kind": "equation", "label": "Equation 3", "page": 5, "latex": "E=mc^2"},
            {"kind": "appendix", "label": "Appendix A", "page": 20},
        ],
        "chunks": [
            {
                "ordinal": 5,
                "heading": "Results",
                "text": "Table 1 reports the observations. Figure 2 presents the supplied series. Equation 3 is defined above. Appendix A contains the protocol.",
                "metadata": {},
            }
        ],
    }


def test_scientific_document_contract_and_object_types():
    result = build_scientific_document_intelligence(document())
    assert result["schema"] == SCIENTIFIC_DOCUMENT_CONTRACT
    assert result["metrics"]["scientific_object_count"] == 4
    assert result["metrics"]["reference_count"] == 4
    assert result["metrics"]["tables_with_structured_cells"] == 1
    assert result["metrics"]["equations_with_source_form"] == 1
    assert {x["kind"] for x in result["objects"]} == {"table", "chart", "equation", "appendix"}
    assert all(x["schema"] == SCIENTIFIC_OBJECT_CONTRACT for x in result["objects"])


def test_pixel_values_are_never_inferred_and_ocr_is_not_auto_verified():
    result = build_scientific_document_intelligence(document())
    assert result["boundaries"]["values_inferred_from_pixels"] is False
    assert result["boundaries"]["chart_trends_inferred"] is False
    assert result["boundaries"]["ocr_text_automatically_treated_as_verified"] is False
    chart = next(x for x in result["objects"] if x["kind"] == "chart")
    assert chart["data_series"][0]["values_inferred_from_pixels"] is False
    assert chart["interpretation"]["claims_inferred_from_object"] is False


def test_table_and_equation_preserve_only_supplied_structure():
    result = build_scientific_document_intelligence(document())
    table = next(x for x in result["objects"] if x["kind"] == "table")
    equation = next(x for x in result["objects"] if x["kind"] == "equation")
    assert table["table"]["columns"] == ["Year", "Value"]
    assert table["table"]["row_count"] == 2
    assert table["table"]["values_inferred_from_pixels"] is False
    assert equation["equation"]["latex"] == "E=mc^2"
    assert equation["equation"]["equation_solved"] is False
    assert equation["equation"]["symbol_semantics_inferred"] is False


def test_exact_document_references_become_source_grounded_edges():
    result = build_scientific_document_intelligence(document())
    overlay = result["graph_overlay"]
    assert overlay["schema"] == SCIENTIFIC_GRAPH_OVERLAY_CONTRACT
    bases = [x["relationship_basis"] for x in overlay["edges"]]
    assert bases.count("contains-scientific-object") == 4
    assert bases.count("explicit-scientific-cross-reference") == 4
    assert bases.count("contains-source-span") == 4
    assert all(x.get("analytical") is False for x in overlay["edges"])


def test_unknown_visual_kind_is_not_silently_promoted():
    result = build_scientific_document_intelligence({
        "record_id": "pub:x",
        "title": "X",
        "scientific_objects": [{"kind": "mystery-visual", "label": "Unknown 1"}],
    })
    assert result["objects"] == []
    assert result["metrics"]["scientific_object_count"] == 0


def test_corpus_overlay_keeps_document_objects_non_claims():
    records = {
        "pub:science": {
            "record_id": "pub:science",
            "title": "Scientific document",
            "content_hash": "a" * 64,
            "metadata": {"scientific_objects": document()["scientific_objects"]},
        }
    }
    chunks = {"pub:science": document()["chunks"]}
    result = build_scientific_corpus_overlay(records, chunks)
    assert result["metrics"]["scientific_object_count"] == 4
    assert result["boundaries"]["structured_objects_only"] is True
    assert result["boundaries"]["claims_inferred_from_scientific_objects"] is False
    assert result["boundaries"]["automatic_truth_promotion"] is False
