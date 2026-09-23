from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_identity_and_backend_version():
    plugin = (ROOT / "sustainable-catalyst-library" / "sustainable-catalyst-library.php").read_text()
    backend = (ROOT / "library-backend" / "app" / "__init__.py").read_text()
    assert "Plugin Name: Sustainable Catalyst Library" in plugin
    assert "SC_LIBRARY_VERSION" in plugin
    assert "__version__" in backend


def test_candidate_schema_is_source_bound_and_review_gated():
    schema = (ROOT / "library-backend" / "app" / "schema.sql").read_text()
    for token in (
        "CREATE TABLE IF NOT EXISTS library_research_candidates",
        "source_locator text NOT NULL",
        "char_start integer NOT NULL",
        "char_end integer NOT NULL",
        "source_content_hash char(64)",
        "review_state text NOT NULL DEFAULT 'pending'",
        "core_outbox_event_id bigint REFERENCES library_core_sync_outbox",
    ):
        assert token in schema


def test_core_operations_are_explicit_not_generic_proxy():
    core = (ROOT / "library-backend" / "app" / "platform_core.py").read_text()
    assert '"research-finding.create"' in core
    assert '"research-claim.create"' in core
    assert '/v1/research/intelligence/projects/{project_id}/findings' in core
    assert '/v1/research/intelligence/projects/{project_id}/claims' in core
    assert "if value not in CORE_OPERATIONS" in core
    assert "unsupported Platform Core operation" in core


def test_extraction_guardrails_are_declared():
    module = (ROOT / "library-backend" / "app" / "research_extraction.py").read_text()
    for token in (
        "machine_generated_candidates_only",
        "requires_human_review_before_core_promotion",
        "automatic_truth_promotion",
        "automatic_claim_promotion",
        "automatic_finding_promotion",
        "candidate must be human-reviewed and accepted before Core promotion",
    ):
        assert token in module


def test_source_changes_supersede_old_candidates():
    repo = (ROOT / "library-backend" / "app" / "repository.py").read_text()
    assert "UPDATE library_research_candidates" in repo
    assert "review_state IN ('pending','accepted')" in repo
    assert "source_content_hash" in repo


def test_wordpress_exposes_readiness_without_client_side_core_credentials():
    wp = (ROOT / "sustainable-catalyst-library" / "includes" / "class-sc-library-python-backend.php").read_text()
    assert "/backend/extraction/readiness" in wp
    assert "/v1/research-extraction/readiness" in wp
    assert "candidate-extraction-ready" in wp
    assert "X-SC-API-Key" not in wp
