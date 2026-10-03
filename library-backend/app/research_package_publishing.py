from __future__ import annotations

import base64
import csv
import io
import json
import re
import zipfile
from hashlib import sha256
from typing import Any

from .artifact_storage import artifact_storage_readiness, persist_artifact
from .research_package_service import get_package, readiness as research_package_readiness
from .scientific_literature_intelligence import readiness as scientific_literature_readiness
from .structured_evidence_objects import readiness as structured_evidence_readiness

LIBRARY_VERSION = "6.10.0"
BACKEND_VERSION = "3.10.0"
CONTRACT = "sc-library-research-package-publishing/1.0"
READINESS_CONTRACT = "sc-library-research-package-publishing-readiness/1.0"
MANIFEST_CONTRACT = "sc-library-reproducible-export-manifest/1.0"
EXPORT_CONTRACT = "sc-library-portable-research-export/1.0"
VALIDATION_CONTRACT = "sc-library-portable-research-export-validation/1.0"
FORMATS_CONTRACT = "sc-library-research-export-formats/1.0"

MAX_RECORDS = 5000
MAX_DATASETS = 100
MAX_DATASET_ROWS = 100000
MAX_INLINE_ARCHIVE_BYTES = 8 * 1024 * 1024
FIXED_ZIP_TIMESTAMP = (1980, 1, 1, 0, 0, 0)

SUPPORTED_FORMATS = (
    "manifest-json",
    "records-json",
    "records-ndjson",
    "dataset-csv",
    "scientific-literature-json",
    "research-package-json",
    "citation-cff",
    "readme-markdown",
    "sha256sums",
    "portable-zip",
)


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _pretty(value: Any) -> str:
    return json.dumps(value, sort_keys=True, indent=2, ensure_ascii=False, default=str) + "\n"


def _sha_bytes(data: bytes) -> str:
    return sha256(data).hexdigest()


def _sha(value: Any) -> str:
    return _sha_bytes(_canon(value).encode("utf-8"))


def _clean(value: Any, limit: int = 4000) -> str:
    return " ".join(str(value or "").strip().split())[:limit]


def _list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return []


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _slug(value: Any, fallback: str = "research-package") -> str:
    text = _clean(value, 180).lower()
    text = re.sub(r"[^a-z0-9]+", "-", text).strip("-")
    return text[:80] or fallback


def _safe_filename(value: Any, fallback: str) -> str:
    raw = _clean(value, 240).replace("\\", "/").split("/")[-1]
    raw = re.sub(r"[^A-Za-z0-9._-]+", "-", raw).strip(".-")
    return raw[:160] or fallback


def _yaml_quote(value: Any) -> str:
    return "'" + str(value or "").replace("'", "''") + "'"


def guardrails() -> dict[str, bool]:
    return {
        "research_package_service_remains_package_authority": True,
        "artifact_store_remains_persisted_byte_authority": True,
        "publishing_layer_is_second_artifact_store": False,
        "publishing_layer_is_external_repository": False,
        "export_generation_publishes_externally": False,
        "portable_export_is_deterministic": True,
        "portable_export_injects_wall_clock_time": False,
        "file_hash_match_implies_research_truth": False,
        "package_integrity_implies_source_validity": False,
        "citation_metadata_implies_endorsement": False,
        "export_format_implies_evidence_quality": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def formats() -> dict[str, Any]:
    return {
        "schema": FORMATS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "formats": list(SUPPORTED_FORMATS),
        "default_profile": "portable-research-package",
        "archive_media_type": "application/zip",
        "archive_compression": "stored",
        "fixed_zip_timestamp": "1980-01-01T00:00:00Z",
        "guardrails": guardrails(),
    }


def contract() -> dict[str, Any]:
    resources = [
        "portable-export-preview",
        "deterministic-export-manifest",
        "portable-zip",
        "records-json-ndjson",
        "dataset-csv",
        "citation-cff",
        "readme-markdown",
        "sha256-checksum-index",
        "explicit-artifact-store-persistence",
    ]
    basis = {"resources": resources, "formats": list(SUPPORTED_FORMATS), "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "service_id": "library-research-package-publishing:" + _sha(basis)[:32],
        "service_fingerprint_sha256": _sha(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend",
        "resources": resources,
        "existing_authorities": {
            "research_package_manifest": "python-research-package-reproducibility-service",
            "persisted_bytes": "content-addressed-artifact-store",
            "structured_state": "postgresql",
        },
        "wordpress": {"role": "optional-adapter", "required": False, "authoritative": False},
        "guardrails": guardrails(),
    }


def _component_ready(value: dict[str, Any]) -> bool:
    if "ready" in value:
        return bool(value.get("ready"))
    return str(value.get("state") or "") in {"ready", "degraded", "authoritative", "authoritative-composition"}


def readiness() -> dict[str, Any]:
    research_package = research_package_readiness()
    artifacts = artifact_storage_readiness()
    structured = structured_evidence_readiness()
    scientific = scientific_literature_readiness()
    errors: list[str] = []
    if not _component_ready(research_package):
        errors.append("research-package-service-not-ready")
    if not _component_ready(structured):
        errors.append("structured-evidence-not-ready")
    if not _component_ready(scientific):
        errors.append("scientific-literature-not-ready")
    persistence_ready = str(artifacts.get("state") or "") == "ready"
    degraded = any(str(x.get("state") or "") == "degraded" for x in (research_package, structured, scientific)) or not persistence_ready
    state = "blocked" if errors else ("degraded" if degraded else "ready")
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": state,
        "ready": not errors,
        "errors": errors,
        "preview_export_ready": not errors,
        "portable_zip_ready": not errors,
        "persistence_ready": persistence_ready,
        "database_migration_required": False,
        "wordpress_required": False,
        "components": {
            "research_package": research_package,
            "artifact_storage": artifacts,
            "structured_evidence": structured,
            "scientific_literature": scientific,
        },
        "guardrails": guardrails(),
    }


def _normalize_records(value: Any) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    seen: set[str] = set()
    for index, raw in enumerate(_list(value)[:MAX_RECORDS]):
        if not isinstance(raw, dict):
            continue
        row = dict(raw)
        rid = _clean(row.get("record_id") or row.get("id") or f"record:{index}", 500)
        key = rid or _sha(row)
        if key in seen:
            continue
        seen.add(key)
        row.setdefault("record_id", rid)
        rows.append(row)
    return rows


def _normalize_datasets(value: Any) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for index, raw in enumerate(_list(value)[:MAX_DATASETS]):
        if not isinstance(raw, dict):
            continue
        dataset = dict(raw)
        dataset.setdefault("dataset_id", f"dataset:{_sha(dataset)[:24]}")
        dataset.setdefault("title", f"Dataset {index + 1}")
        out.append(dataset)
    return out


def _dataset_csv(dataset: dict[str, Any]) -> bytes:
    rows = [dict(x) for x in _list(dataset.get("rows")) if isinstance(x, dict)][:MAX_DATASET_ROWS]
    declared = _list(dataset.get("columns"))
    columns: list[str] = []
    for item in declared:
        name = _clean(item.get("name") if isinstance(item, dict) else item, 240)
        if name and name not in columns:
            columns.append(name)
    if not columns:
        keys: set[str] = set()
        for row in rows:
            keys.update(str(k) for k in row.keys())
        columns = sorted(keys)
    stream = io.StringIO(newline="")
    writer = csv.writer(stream, lineterminator="\n")
    writer.writerow(columns)
    for row in rows:
        values: list[Any] = []
        for name in columns:
            value = row.get(name)
            if isinstance(value, (dict, list, tuple)):
                value = _canon(value)
            elif value is None:
                value = ""
            values.append(value)
        writer.writerow(values)
    return stream.getvalue().encode("utf-8")


def _citation_cff(payload: dict[str, Any]) -> bytes:
    title = _clean(payload.get("title") or "Research package", 500)
    message = _clean(payload.get("citation_message") or "If you use this research package, cite the underlying sources and this package manifest.", 1000)
    authors = _list(payload.get("authors"))
    lines = ["cff-version: 1.2.0", f"message: {_yaml_quote(message)}", f"title: {_yaml_quote(title)}", "type: dataset"]
    if authors:
        lines.append("authors:")
        for author in authors[:100]:
            if isinstance(author, dict):
                given = _clean(author.get("given-names") or author.get("given_names") or author.get("given"), 200)
                family = _clean(author.get("family-names") or author.get("family_names") or author.get("family"), 200)
                name = _clean(author.get("name"), 300)
                orcid = _clean(author.get("orcid"), 200)
                lines.append("  -" + (f" family-names: {_yaml_quote(family)}" if family else f" name: {_yaml_quote(name or given or 'Unknown') }"))
                if given and family:
                    lines.append(f"    given-names: {_yaml_quote(given)}")
                if orcid:
                    lines.append(f"    orcid: {_yaml_quote(orcid)}")
            else:
                lines.append(f"  - name: {_yaml_quote(_clean(author, 300))}")
    doi = _clean(payload.get("doi"), 300)
    if doi:
        lines.append(f"doi: {_yaml_quote(doi)}")
    version = _clean(payload.get("version"), 100)
    if version:
        lines.append(f"version: {_yaml_quote(version)}")
    return ("\n".join(lines) + "\n").encode("utf-8")


def _readme(payload: dict[str, Any], counts: dict[str, int]) -> bytes:
    title = _clean(payload.get("title") or "Research Package", 500)
    description = _clean(payload.get("description"), 4000)
    lines = [f"# {title}", ""]
    if description:
        lines += [description, ""]
    lines += [
        "This portable export was prepared by Sustainable Catalyst Library.",
        "",
        "## Contents",
        "",
        f"- Records: {counts['records']}",
        f"- Datasets: {counts['datasets']}",
        f"- Scientific literature objects: {counts['scientific_literature']}",
        f"- Research package manifests: {counts['research_packages']}",
        "",
        "Every exported file is covered by SHA-256 checksums in `SHA256SUMS` and by `manifest.json`.",
        "Export integrity does not establish source validity, evidence strength, causal meaning, or truth.",
        "",
    ]
    return "\n".join(lines).encode("utf-8")


def _research_package_from_payload(payload: dict[str, Any]) -> dict[str, Any] | None:
    package = payload.get("research_package")
    if isinstance(package, dict):
        return dict(package)
    package_id = _clean(payload.get("package_id"), 500)
    if package_id:
        return get_package(package_id, include_manifest=True)
    return None


def _base_files(payload: dict[str, Any]) -> tuple[dict[str, bytes], dict[str, int]]:
    records = _normalize_records(payload.get("records"))
    datasets = _normalize_datasets(payload.get("datasets"))
    scientific_items = [dict(x) for x in _list(payload.get("scientific_literature")) if isinstance(x, dict)][:100]
    if isinstance(payload.get("scientific_literature"), dict):
        scientific_items = [dict(payload["scientific_literature"])]
    research_package = _research_package_from_payload(payload)

    files: dict[str, bytes] = {}
    if records:
        files["records/records.json"] = _pretty(records).encode("utf-8")
        files["records/records.ndjson"] = ("\n".join(_canon(row) for row in records) + "\n").encode("utf-8")
    for index, dataset in enumerate(datasets):
        stem = _slug(dataset.get("title") or dataset.get("dataset_id"), f"dataset-{index + 1}")
        files[f"datasets/{index + 1:03d}-{stem}.json"] = _pretty(dataset).encode("utf-8")
        files[f"datasets/{index + 1:03d}-{stem}.csv"] = _dataset_csv(dataset)
    if scientific_items:
        files["literature/scientific-literature.json"] = _pretty(scientific_items).encode("utf-8")
    if research_package is not None:
        files["research-package/research-package.json"] = _pretty(research_package).encode("utf-8")

    counts = {
        "records": len(records),
        "datasets": len(datasets),
        "scientific_literature": len(scientific_items),
        "research_packages": 1 if research_package is not None else 0,
    }
    files["CITATION.cff"] = _citation_cff(payload)
    files["README.md"] = _readme(payload, counts)
    return files, counts


def _file_descriptors(files: dict[str, bytes]) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for path in sorted(files):
        data = files[path]
        if path.endswith(".json"):
            media = "application/json"
        elif path.endswith(".ndjson"):
            media = "application/x-ndjson"
        elif path.endswith(".csv"):
            media = "text/csv; charset=utf-8"
        elif path.endswith(".cff") or path.endswith(".yaml") or path.endswith(".yml"):
            media = "application/yaml"
        else:
            media = "text/markdown; charset=utf-8" if path.endswith(".md") else "text/plain; charset=utf-8"
        out.append({"path": path, "byte_length": len(data), "sha256": _sha_bytes(data), "media_type": media})
    return out


def _manifest(payload: dict[str, Any], files: dict[str, bytes], counts: dict[str, int]) -> dict[str, Any]:
    descriptors = _file_descriptors(files)
    basis = {
        "title": _clean(payload.get("title") or "Research package", 500),
        "description": _clean(payload.get("description"), 4000) or None,
        "profile": _clean(payload.get("profile") or "portable-research-package", 120),
        "version": _clean(payload.get("version"), 100) or None,
        "metadata": _dict(payload.get("metadata")),
        "counts": counts,
        "files": descriptors,
        "source_package_id": _clean(payload.get("package_id"), 500) or None,
    }
    fingerprint = _sha(basis)
    return {
        "schema": MANIFEST_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "export_fingerprint_sha256": fingerprint,
        "title": basis["title"],
        "description": basis["description"],
        "profile": basis["profile"],
        "version": basis["version"],
        "counts": counts,
        "files": descriptors,
        "source_package_id": basis["source_package_id"],
        "metadata": basis["metadata"],
        "checksum_index_path": "SHA256SUMS",
        "determinism": {
            "canonical_json": True,
            "sorted_paths": True,
            "fixed_zip_timestamp": "1980-01-01T00:00:00Z",
            "zip_compression": "stored",
            "wall_clock_time_in_export_bytes": False,
        },
        "guardrails": guardrails(),
    }


def _checksum_index(files: dict[str, bytes]) -> bytes:
    return ("\n".join(f"{_sha_bytes(files[path])}  {path}" for path in sorted(files)) + "\n").encode("utf-8")


def _zip_bytes(files: dict[str, bytes]) -> bytes:
    stream = io.BytesIO()
    with zipfile.ZipFile(stream, "w", compression=zipfile.ZIP_STORED) as archive:
        for path in sorted(files):
            info = zipfile.ZipInfo(path, FIXED_ZIP_TIMESTAMP)
            info.compress_type = zipfile.ZIP_STORED
            info.external_attr = 0o100644 << 16
            info.create_system = 3
            archive.writestr(info, files[path])
    return stream.getvalue()


def build_export(payload: dict[str, Any], *, include_archive: bool = True) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("export-payload-must-be-object")
    files, counts = _base_files(payload)
    manifest = _manifest(payload, files, counts)
    files["manifest.json"] = _pretty(manifest).encode("utf-8")
    files["SHA256SUMS"] = _checksum_index(files)
    descriptors = _file_descriptors(files)
    title = _slug(payload.get("title") or "research-package")
    archive = _zip_bytes(files)
    if len(archive) > MAX_INLINE_ARCHIVE_BYTES:
        raise ValueError("portable-export-exceeds-inline-archive-limit")
    result = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "export_fingerprint_sha256": manifest["export_fingerprint_sha256"],
        "filename": _safe_filename(payload.get("filename") or f"{title}-reproducible-export.zip", "research-package-reproducible-export.zip"),
        "media_type": "application/zip",
        "archive_sha256": _sha_bytes(archive),
        "archive_byte_length": len(archive),
        "manifest": manifest,
        "files": descriptors,
        "file_count": len(descriptors),
        "database_persisted": False,
        "published_externally": False,
        "guardrails": guardrails(),
    }
    if include_archive:
        result["archive_base64"] = base64.b64encode(archive).decode("ascii")
    return result


def preview_export(payload: dict[str, Any]) -> dict[str, Any]:
    result = build_export(payload, include_archive=False)
    result["preview_only"] = True
    return result


def validate_export(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["payload-must-be-object"], "guardrails": guardrails()}
    raw = payload.get("archive_base64")
    if not isinstance(raw, str) or not raw.strip():
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["archive-base64-required"], "guardrails": guardrails()}
    errors: list[str] = []
    try:
        archive_bytes = base64.b64decode(raw.encode("ascii"), validate=True)
    except Exception:
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["invalid-archive-base64"], "guardrails": guardrails()}
    provided_archive_sha = _clean(payload.get("archive_sha256"), 128).lower()
    actual_archive_sha = _sha_bytes(archive_bytes)
    if provided_archive_sha and provided_archive_sha != actual_archive_sha:
        errors.append("archive-sha256-mismatch")
    try:
        with zipfile.ZipFile(io.BytesIO(archive_bytes), "r") as z:
            names = z.namelist()
            if names != sorted(names):
                errors.append("archive-path-order-not-deterministic")
            if "manifest.json" not in names:
                errors.append("manifest-missing")
                manifest = {}
            else:
                manifest = json.loads(z.read("manifest.json").decode("utf-8"))
            for descriptor in _list(manifest.get("files")):
                if not isinstance(descriptor, dict):
                    continue
                path = _clean(descriptor.get("path"), 500)
                if path not in names:
                    errors.append("manifest-file-missing:" + path)
                    continue
                if _sha_bytes(z.read(path)) != _clean(descriptor.get("sha256"), 128).lower():
                    errors.append("file-sha256-mismatch:" + path)
            if "SHA256SUMS" not in names:
                errors.append("checksum-index-missing")
    except (zipfile.BadZipFile, UnicodeDecodeError, json.JSONDecodeError) as exc:
        errors.append("invalid-portable-zip:" + exc.__class__.__name__)
        manifest = {}
    return {
        "schema": VALIDATION_CONTRACT,
        "valid": not errors,
        "errors": errors,
        "archive_sha256": actual_archive_sha,
        "archive_byte_length": len(archive_bytes),
        "export_fingerprint_sha256": manifest.get("export_fingerprint_sha256") if isinstance(manifest, dict) else None,
        "guardrails": guardrails(),
    }


def persist_export(payload: dict[str, Any]) -> dict[str, Any]:
    export = build_export(payload, include_archive=True)
    archive_base64 = str(export.pop("archive_base64"))
    artifact = persist_artifact({
        "content_base64": archive_base64,
        "artifact_type": "reproducibility-package",
        "media_type": "application/zip",
        "original_filename": export["filename"],
        "derivation_operation": "research-package-reproducible-export",
        "provenance": {
            "schema": EXPORT_CONTRACT,
            "export_fingerprint_sha256": export["export_fingerprint_sha256"],
            "archive_sha256": export["archive_sha256"],
            "library_version": LIBRARY_VERSION,
            "backend_version": BACKEND_VERSION,
            "external_publication_performed": False,
            "wordpress_required": False,
        },
    })
    return {
        "schema": EXPORT_CONTRACT,
        **export,
        "database_persisted": True,
        "published_externally": False,
        "artifact": artifact,
        "guardrails": guardrails(),
    }
