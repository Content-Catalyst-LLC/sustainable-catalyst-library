from __future__ import annotations

import re

from .models import ChunkPacket, RecordPacket


def server_chunks(text: str) -> list[ChunkPacket]:
    """Reproduce the WordPress v1 chunking contract for compact ingest packets."""
    normalized = re.sub(r"\s+", " ", text or "").strip()
    if not normalized:
        return []
    size = 6000
    chunks: list[ChunkPacket] = []
    for ordinal, offset in enumerate(range(0, len(normalized), size)):
        if ordinal >= 200:
            break
        piece = normalized[offset:offset + size].strip()
        if piece:
            chunks.append(
                ChunkPacket(
                    ordinal=ordinal,
                    heading="",
                    text=piece,
                    metadata={"chunker": "wordpress-text-v1"},
                )
            )
    return chunks


def ensure_record_chunks(record: RecordPacket) -> RecordPacket:
    """Fill omitted chunks without changing the canonical record semantics."""
    if record.chunks or not record.body_text.strip():
        return record
    data = record.model_dump(mode="python")
    data["chunks"] = [chunk.model_dump(mode="python") for chunk in server_chunks(record.body_text)]
    return RecordPacket.model_validate(data)
