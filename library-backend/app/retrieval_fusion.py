from __future__ import annotations

from collections import defaultdict
from typing import Any


def as_float_or_none(value: Any) -> float | None:
    try:
        return float(value) if value is not None else None
    except (TypeError, ValueError):
        return None


def reciprocal_rank_fusion(
    lexical: list[dict[str, Any]],
    semantic: list[dict[str, Any]],
    *,
    lexical_weight: float = 1.0,
    semantic_weight: float = 1.0,
    rrf_k: int = 60,
) -> list[dict[str, Any]]:
    """Fuse heterogeneous rankings without pretending raw scores share a scale."""
    rows: dict[str, dict[str, Any]] = {}
    scores: defaultdict[str, float] = defaultdict(float)
    lexical_meta: dict[str, tuple[int, float | None]] = {}
    semantic_meta: dict[str, tuple[int, float | None]] = {}

    for rank, row in enumerate(lexical, start=1):
        record_id = str(row.get("record_id") or "")
        if not record_id:
            continue
        rows.setdefault(record_id, dict(row))
        scores[record_id] += float(lexical_weight) / (int(rrf_k) + rank)
        lexical_meta[record_id] = (rank, as_float_or_none(row.get("score")))

    for rank, row in enumerate(semantic, start=1):
        record_id = str(row.get("record_id") or "")
        if not record_id:
            continue
        if record_id not in rows:
            rows[record_id] = dict(row)
        else:
            for key, value in row.items():
                rows[record_id].setdefault(key, value)
        scores[record_id] += float(semantic_weight) / (int(rrf_k) + rank)
        semantic_meta[record_id] = (rank, as_float_or_none(row.get("semantic_score")))

    ordered = sorted(rows, key=lambda record_id: (-scores[record_id], record_id))
    output: list[dict[str, Any]] = []
    for rank, record_id in enumerate(ordered, start=1):
        row = dict(rows[record_id])
        lex = lexical_meta.get(record_id)
        sem = semantic_meta.get(record_id)
        row["hybrid_score"] = round(scores[record_id], 12)
        row["hybrid_rank"] = rank
        row["retrieval_signals"] = {
            "lexical_rank": lex[0] if lex else None,
            "lexical_score": lex[1] if lex else None,
            "semantic_rank": sem[0] if sem else None,
            "semantic_score": sem[1] if sem else None,
        }
        output.append(row)
    return output
