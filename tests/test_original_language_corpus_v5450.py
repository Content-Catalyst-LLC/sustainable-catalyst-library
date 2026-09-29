from pathlib import Path
import base64
import importlib.util
import sys
import types

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def load_module():
    # Stub database/psycopg imports so the pure capture contract can be validated
    # on release workstations without backend dependencies installed.
    psycopg = types.ModuleType("psycopg")
    psycopg_types = types.ModuleType("psycopg.types")
    psycopg_json = types.ModuleType("psycopg.types.json")
    psycopg_json.Jsonb = lambda x: x
    sys.modules.setdefault("psycopg", psycopg)
    sys.modules.setdefault("psycopg.types", psycopg_types)
    sys.modules.setdefault("psycopg.types.json", psycopg_json)

    pkg = types.ModuleType("app")
    pkg.__path__ = [str(ROOT / "library-backend" / "app")]
    sys.modules["app"] = pkg
    db = types.ModuleType("app.db")
    db.get_pool = lambda: (_ for _ in ()).throw(RuntimeError("no-db-test"))
    sys.modules["app.db"] = db

    fed = types.ModuleType("app.global_source_federation")
    class Registry:
        def source(self, source_id):
            if source_id not in {"crossref", "pubmed", "internetarchive"}:
                raise KeyError(source_id)
            return {"source_id": source_id}
    fed.registry = Registry()
    sys.modules["app.global_source_federation"] = fed

    path = ROOT / "library-backend" / "app" / "original_language_corpus.py"
    spec = importlib.util.spec_from_file_location("app.original_language_corpus", path)
    mod = importlib.util.module_from_spec(spec)
    assert spec and spec.loader
    sys.modules["app.original_language_corpus"] = mod
    spec.loader.exec_module(mod)
    return mod


def test_release_identity_and_surfaces():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.45.0" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.45.0');" in plugin
    assert '__version__ = "2.56.0"' in read("library-backend/app/__init__.py")
    main = read("library-backend/app/main.py")
    assert '/v1/original-language-corpus/readiness' in main
    assert '/v1/admin/original-language-corpus/captures' in main


def test_schema_has_preservation_tables_and_no_cascade_from_capture():
    schema = read("library-backend/app/schema.sql")
    assert "CREATE TABLE IF NOT EXISTS library_original_language_captures" in schema
    assert "CREATE TABLE IF NOT EXISTS library_text_representations" in schema
    assert "CREATE TABLE IF NOT EXISTS library_text_transformations" in schema
    assert "raw_payload bytea NOT NULL" in schema
    assert "canonical_original boolean NOT NULL" in schema
    assert "ON DELETE RESTRICT" in schema


def test_original_is_canonical_and_normalization_is_derived():
    mod = load_module()
    raw = "Cafe\u0301 — Ελληνικά — العربية"
    payload = {
        "source_id": "internetarchive",
        "source_record_id": "sample-1",
        "source_uri": "https://example.test/source/1",
        "language_bcp47": "fr",
        "script_iso15924": "Latn",
        "raw_text": raw,
        "charset": "utf-8",
        "create_normalized_derivative": True,
        "normalized_form": "NFC",
    }
    package = mod.build_capture_package(payload)
    reps = package["representations"]
    assert reps[0]["representation_kind"] == "original"
    assert reps[0]["canonical_original"] is True and reps[0]["derived"] is False
    assert reps[1]["representation_kind"] == "unicode-normalized"
    assert reps[1]["canonical_original"] is False and reps[1]["derived"] is True
    assert reps[0]["text_sha256"] != reps[1]["text_sha256"]
    assert package["transformations"][0]["operation"] == "unicode-normalization"
    assert package["transformations"][0]["translation"] is False
    assert package["guardrails"]["normalization_replaces_original"] is False


def test_byte_exact_base64_and_deterministic_identity():
    mod = load_module()
    raw = "日本語の原文"
    raw_bytes = raw.encode("utf-8")
    payload = {
        "source_id": "pubmed",
        "source_record_id": "jp-1",
        "language_bcp47": "ja",
        "script_iso15924": "Jpan",
        "raw_payload_base64": base64.b64encode(raw_bytes).decode("ascii"),
        "charset": "utf-8",
    }
    a = mod.build_capture_package(payload)
    b = mod.build_capture_package(payload)
    assert a["capture_id"] == b["capture_id"]
    assert a["capture_fingerprint_sha256"] == b["capture_fingerprint_sha256"]
    assert a["raw_byte_length"] == len(raw_bytes)
    assert a["_raw_bytes"] == raw_bytes


def test_translation_and_original_replacement_are_rejected():
    mod = load_module()
    base = {"source_id":"crossref", "language_bcp47":"en", "script_iso15924":"Latn", "raw_text":"Source text"}
    x = mod.validate_capture_payload({**base, "automatic_translation": True})
    assert x["valid"] is False and "automatic-translation-prohibited" in x["errors"]
    y = mod.validate_capture_payload({**base, "replace_original_with_normalized": True})
    assert y["valid"] is False and "original-replacement-prohibited" in y["errors"]


def test_unknown_source_and_invalid_script_are_rejected():
    mod = load_module()
    x = mod.validate_capture_payload({"source_id":"not-registered", "language_bcp47":"en", "script_iso15924":"latin", "raw_text":"x"})
    assert x["valid"] is False
    assert "unknown-global-source-id" in x["errors"]
    assert "invalid-script-iso15924" in x["errors"]


def test_corpus_builder_exposes_language_lineage_fields():
    source = read("library-backend/app/research_corpus_builder.py")
    for field in ["original_language", "script_iso15924", "language_variant", "orthography_variant", "original_language_capture_id", "original_language_representation_id"]:
        assert field in source


def test_wordpress_surface_does_not_store_raw_content():
    wp = read("sustainable-catalyst-library/includes/class-sc-library-original-language-corpus.php")
    assert "Original-language source bytes and decoded text are preserved" in wp
    assert "raw_payload" not in wp
    assert "raw_text" not in wp
    backend = read("sustainable-catalyst-library/includes/class-sc-library-python-backend.php")
    assert "/backend/original-language-corpus/readiness" in backend
    assert "/backend/original-language-corpus/validate" in backend
