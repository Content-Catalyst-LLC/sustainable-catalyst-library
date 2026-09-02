from __future__ import annotations

from datetime import datetime
from typing import Any

def classify_integrity(expected: dict[str, datetime | None], rows: list[dict[str, Any]]) -> dict[str, list[str]]:
    backend = {row["record_id"]: row for row in rows}
    expected_ids = set(expected)
    backend_ids = set(backend)

    missing = sorted(expected_ids - backend_ids)
    orphaned = sorted(backend_ids - expected_ids)
    stale: list[str] = []
    chunkless: list[str] = []

    for record_id in sorted(expected_ids & backend_ids):
        row = backend[record_id]
        expected_updated = expected[record_id]
        backend_updated = row.get("source_updated_at")
        if expected_updated is not None and (backend_updated is None or backend_updated < expected_updated):
            stale.append(record_id)
        if int(row.get("body_length") or 0) > 0 and not bool(row.get("has_chunks")):
            chunkless.append(record_id)

    return {
        "missing": missing,
        "stale": stale,
        "orphaned": orphaned,
        "chunkless": chunkless,
        "repair": sorted(set(missing) | set(stale) | set(chunkless)),
    }

