from app.methodology_intelligence import (
    METHODOLOGY_SCHEMA, PROFILE_SCHEMA, COMPARISON_SCHEMA,
    build_methodology_intelligence, methodology_request, profile_methodology,
)


def records():
    return [
        {
            "record_id": "rct-1",
            "title": "Explicit randomized study",
            "source_key": "test",
            "content_hash": "a" * 64,
            "metadata": {
                "methodology": {
                    "study_design": "Randomized controlled trial",
                    "population": "Adults with condition X",
                    "sample_size": 240,
                    "geography": "United States",
                    "time_period": "2024-2025",
                    "intervention": "Intervention A",
                    "comparator": "Usual care",
                    "outcomes": ["Outcome 1", "Outcome 2"],
                    "variables": ["age", "baseline severity"],
                    "statistical_methods": ["linear regression", "intention-to-treat"],
                    "confidence_intervals": "95% confidence intervals",
                    "limitations": ["Single-region recruitment"],
                    "funding": "Grant ABC",
                    "preregistration": "NCT00000000",
                    "data_availability": "Repository DOI 10.1234/data",
                    "code_availability": "https://example.org/code",
                    "randomization": "computer-generated",
                    "masking": "single blind",
                }
            },
        },
        {
            "record_id": "econ-1",
            "title": "Explicit quasi-experimental study",
            "metadata": {
                "study_design": {
                    "design": "Difference-in-differences",
                    "population": "Municipalities",
                    "sample_size": "n=1,204",
                    "analytical_methods": ["difference-in-differences", "fixed effects"],
                    "uncertainty": ["clustered standard errors"],
                },
                "limitations": ["Parallel-trends assumption requires review"],
            },
        },
        {"record_id": "sparse-1", "title": "No structured methods", "metadata": {}},
    ]


def test_profile_uses_explicit_structured_fields_and_not_quality_ranking():
    p = profile_methodology(records()[0])
    assert p["schema"] == PROFILE_SCHEMA
    assert p["study_design"]["family"] == "randomized-interventional"
    assert p["study_context"]["sample_size"]["value"] == 240
    assert p["methods"]["uncertainty"]["reported"] is True
    assert p["method_reporting_coverage"]["reported_dimension_count"] >= 10
    assert p["method_reporting_coverage"]["coverage_is_quality_score"] is False
    assert p["appraisal_readiness"]["formal_quality_grade"] is None
    assert p["appraisal_readiness"]["formal_risk_of_bias_judgment"] is None
    assert p["guardrails"]["methodology_profile_determines_truth"] is False


def test_quasi_experimental_design_and_sample_normalization():
    p = profile_methodology(records()[1])
    assert p["study_design"]["family"] == "quasi-experimental"
    assert p["study_context"]["sample_size"]["value"] == 1204
    assert "difference-in-differences" in [x.lower() for x in p["methods"]["analytical_methods"]["value"]]


def test_missing_metadata_is_not_interpreted_as_method_absence():
    p = profile_methodology(records()[2])
    assert p["method_reporting_coverage"]["reported_dimension_count"] == 0
    assert p["appraisal_readiness"]["state"] == "insufficient-explicit-methodology"
    assert p["guardrails"]["missing_field_means_method_not_used"] is False


def test_corpus_overlay_is_queryable_but_not_default_evidence_path():
    d = build_methodology_intelligence(records())
    assert d["schema"] == METHODOLOGY_SCHEMA
    assert d["metrics"]["record_count"] == 3
    assert d["metrics"]["records_with_explicit_methodology"] == 2
    assert d["guardrails"]["automatic_quality_score"] is False
    assert d["graph_overlay"]["nodes"]
    assert all(x["relationship_basis"] == "describes-methodology" for x in d["graph_overlay"]["edges"])
    assert all(x["default_evidence_path"] is False for x in d["graph_overlay"]["edges"])


def test_comparison_is_descriptive_not_causal_or_quality_ranked():
    d = build_methodology_intelligence(records())
    c = d["comparison"]
    assert c["schema"] == COMPARISON_SCHEMA
    assert c["record_count"] == 3
    assert c["interpretation"]["methodological_difference_explains_outcome_difference_automatically"] is False
    assert c["interpretation"]["higher_reporting_coverage_means_higher_quality"] is False
    assert c["interpretation"]["design_family_is_quality_ranking"] is False


def test_request_can_limit_comparison_without_dropping_profiles():
    d = methodology_request({"records": records(), "compare_record_ids": ["rct-1", "econ-1"]})
    assert len(d["profiles"]) == 3
    assert d["comparison"]["record_count"] == 2
