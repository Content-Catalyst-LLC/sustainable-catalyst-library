from __future__ import annotations

from datetime import datetime
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator


Visibility = Literal["public", "private", "shared", "internal"]
PublicationStatus = Literal["draft", "review", "published", "archived", "superseded", "withdrawn"]


class SourcePacket(BaseModel):
    model_config = ConfigDict(extra="forbid")

    source_key: str = Field(min_length=1, max_length=191)
    name: str = Field(min_length=1, max_length=255)
    source_type: str = Field(default="wordpress", min_length=1, max_length=80)
    canonical_url: str | None = Field(default=None, max_length=2000)
    metadata: dict[str, Any] = Field(default_factory=dict)


class ChunkPacket(BaseModel):
    model_config = ConfigDict(extra="forbid")

    ordinal: int = Field(ge=0, le=100000)
    heading: str = Field(default="", max_length=500)
    text: str = Field(min_length=1, max_length=200000)
    token_count: int | None = Field(default=None, ge=0, le=1000000)
    metadata: dict[str, Any] = Field(default_factory=dict)


class RecordPacket(BaseModel):
    model_config = ConfigDict(extra="forbid")

    record_id: str = Field(min_length=1, max_length=255)
    source_key: str = Field(min_length=1, max_length=191)
    object_type: str = Field(min_length=1, max_length=80)
    title: str = Field(min_length=1, max_length=1000)
    canonical_url: str | None = Field(default=None, max_length=2000)
    abstract: str = Field(default="", max_length=40000)
    body_text: str = Field(default="", max_length=2000000)
    language: str = Field(default="en", min_length=2, max_length=16)
    visibility: Visibility = "public"
    publication_status: PublicationStatus = "published"
    published_at: datetime | None = None
    source_updated_at: datetime | None = None
    authors: list[str] = Field(default_factory=list, max_length=100)
    topics: list[str] = Field(default_factory=list, max_length=200)
    tags: list[str] = Field(default_factory=list, max_length=300)
    identifiers: dict[str, str] = Field(default_factory=dict)
    metadata: dict[str, Any] = Field(default_factory=dict)
    chunks: list[ChunkPacket] = Field(default_factory=list, max_length=500)

    @field_validator("authors", "topics", "tags")
    @classmethod
    def clean_strings(cls, values: list[str]) -> list[str]:
        cleaned: list[str] = []
        seen: set[str] = set()
        for value in values:
            item = " ".join(str(value).split()).strip()
            key = item.casefold()
            if item and key not in seen:
                cleaned.append(item[:500])
                seen.add(key)
        return cleaned


class RecordBatch(BaseModel):
    model_config = ConfigDict(extra="forbid", populate_by_name=True)

    schema_id: str = Field(default="sc-library-backend-ingest/1.0", alias="schema", serialization_alias="schema")
    source: SourcePacket
    records: list[RecordPacket] = Field(min_length=1, max_length=1000)

    @field_validator("schema_id")
    @classmethod
    def supported_schema(cls, value: str) -> str:
        if value != "sc-library-backend-ingest/1.0":
            raise ValueError("unsupported ingest schema")
        return value


class EdgePacket(BaseModel):
    model_config = ConfigDict(extra="forbid")

    source_record_id: str = Field(min_length=1, max_length=255)
    target_record_id: str = Field(min_length=1, max_length=255)
    relation: str = Field(min_length=1, max_length=100)
    weight: float = Field(default=1.0, ge=0.0, le=1000.0)
    directed: bool = True
    provenance: dict[str, Any] = Field(default_factory=dict)


class EdgeBatch(BaseModel):
    model_config = ConfigDict(extra="forbid", populate_by_name=True)

    schema_id: str = Field(default="sc-library-backend-edges/1.0", alias="schema", serialization_alias="schema")
    edges: list[EdgePacket] = Field(min_length=1, max_length=5000)

    @field_validator("schema_id")
    @classmethod
    def supported_schema(cls, value: str) -> str:
        if value != "sc-library-backend-edges/1.0":
            raise ValueError("unsupported edge schema")
        return value

class ExpectedRecordState(BaseModel):
    model_config = ConfigDict(extra="forbid")

    record_id: str = Field(min_length=1, max_length=255)
    source_updated_at: datetime | None = None


class IntegrityAuditRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    source_key: str = Field(min_length=1, max_length=191)
    records: list[ExpectedRecordState] = Field(default_factory=list, max_length=10000)


class PruneRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    source_key: str = Field(min_length=1, max_length=191)
    record_ids: list[str] = Field(min_length=1, max_length=10000)

    @field_validator("record_ids")
    @classmethod
    def clean_record_ids(cls, values: list[str]) -> list[str]:
        cleaned: list[str] = []
        seen: set[str] = set()
        for value in values:
            item = str(value).strip()
            if item and item not in seen:
                cleaned.append(item)
                seen.add(item)
        return cleaned
