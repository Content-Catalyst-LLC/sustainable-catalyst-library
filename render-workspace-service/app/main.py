from __future__ import annotations

import os
from contextlib import asynccontextmanager
from datetime import datetime, timezone
from typing import Any
from uuid import UUID

import psycopg
from fastapi import FastAPI, Header, HTTPException, Request, Response
from fastapi.responses import JSONResponse
from pydantic import BaseModel, ConfigDict, Field, field_validator
from psycopg.rows import dict_row
from psycopg.types.json import Jsonb

from .core import (
    SERVICE_VERSION,
    SYNC_SCHEMA,
    WORKSPACE_SCHEMA,
    constant_time_equal,
    content_hash,
    signature,
    valid_workspace_schema,
)
from .documents import create_documents_router, initialize_document_database
from .media import create_media_router, initialize_media_database

DATABASE_URL = os.getenv("DATABASE_URL", "").strip()
API_KEY = os.getenv("SC_LIBRARY_SYNC_API_KEY", "").strip()
MAX_WORKSPACE_BYTES = max(1, min(25, int(os.getenv("SC_LIBRARY_MAX_WORKSPACE_MB", "8")))) * 1024 * 1024

SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS library_account_workspaces (
    workspace_uuid uuid PRIMARY KEY,
    owner_external_id text NOT NULL,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    visibility text NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','shared','public')),
    workspace_schema text NOT NULL,
    revision bigint NOT NULL CHECK (revision > 0),
    content_hash char(64) NOT NULL,
    payload jsonb NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_account_workspaces_owner_idx ON library_account_workspaces(owner_external_id);
CREATE INDEX IF NOT EXISTS library_account_workspaces_updated_idx ON library_account_workspaces(updated_at DESC);

CREATE TABLE IF NOT EXISTS library_account_workspace_revisions (
    id bigserial PRIMARY KEY,
    workspace_uuid uuid NOT NULL REFERENCES library_account_workspaces(workspace_uuid) ON DELETE CASCADE,
    owner_external_id text NOT NULL,
    revision bigint NOT NULL,
    content_hash char(64) NOT NULL,
    payload jsonb NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(workspace_uuid, revision)
);
CREATE INDEX IF NOT EXISTS library_account_workspace_revisions_lookup_idx
    ON library_account_workspace_revisions(workspace_uuid, revision DESC);
"""


class WorkspacePacket(BaseModel):
    model_config = ConfigDict(extra="forbid")

    schema: str = Field(default=SYNC_SCHEMA)
    workspace_schema: str
    workspace_uuid: UUID
    owner_external_id: str = Field(min_length=1, max_length=191)
    title: str = Field(min_length=1, max_length=255)
    description: str = Field(default="", max_length=10000)
    visibility: str = Field(default="private")
    revision: int = Field(ge=1)
    content_hash: str = Field(min_length=64, max_length=64)
    workspace: dict[str, Any]
    updated_at: datetime | None = None

    @field_validator("schema")
    @classmethod
    def validate_sync_schema(cls, value: str) -> str:
        if value != SYNC_SCHEMA:
            raise ValueError("unsupported sync schema")
        return value

    @field_validator("workspace_schema")
    @classmethod
    def validate_workspace_schema(cls, value: str) -> str:
        if not valid_workspace_schema(value):
            raise ValueError("unsupported workspace schema")
        return WORKSPACE_SCHEMA

    @field_validator("visibility")
    @classmethod
    def validate_visibility(cls, value: str) -> str:
        if value not in {"private", "shared", "public"}:
            raise ValueError("invalid visibility")
        return value

    @field_validator("workspace")
    @classmethod
    def validate_workspace(cls, value: dict[str, Any]) -> dict[str, Any]:
        value["schema"] = WORKSPACE_SCHEMA
        return value


def connect() -> psycopg.Connection:
    if not DATABASE_URL:
        raise HTTPException(status_code=503, detail="DATABASE_URL is not configured")
    return psycopg.connect(DATABASE_URL, row_factory=dict_row)


def initialize_database() -> None:
    if not DATABASE_URL:
        return
    with connect() as conn:
        with conn.cursor() as cur:
            cur.execute(SCHEMA_SQL)
        conn.commit()


@asynccontextmanager
async def lifespan(_: FastAPI):
    initialize_database()
    initialize_document_database(connect)
    initialize_media_database(connect)
    yield


app = FastAPI(
    title="Sustainable Catalyst Library Workspace and Document Service",
    version=SERVICE_VERSION,
    lifespan=lifespan,
)


async def authorize(
    request: Request,
    authorization: str | None,
    request_signature: str | None,
    request_timestamp: str | None,
) -> bytes:
    if not API_KEY:
        raise HTTPException(status_code=503, detail="SC_LIBRARY_SYNC_API_KEY is not configured")
    supplied = ""
    if authorization and authorization.lower().startswith("bearer "):
        supplied = authorization[7:].strip()
    if not supplied or not constant_time_equal(supplied, API_KEY):
        raise HTTPException(status_code=401, detail="invalid server credential")
    if not request_timestamp or not request_timestamp.isdigit():
        raise HTTPException(status_code=401, detail="missing request timestamp")
    now = int(datetime.now(timezone.utc).timestamp())
    if abs(now - int(request_timestamp)) > 300:
        raise HTTPException(status_code=401, detail="expired request timestamp")
    body = await request.body()
    signature_base = f"{request.method.upper()}\n{request.url.path}\n{request_timestamp}\n".encode("utf-8") + body
    expected = signature(signature_base, API_KEY)
    if not request_signature or not constant_time_equal(request_signature, expected):
        raise HTTPException(status_code=401, detail="invalid request signature")
    return body


app.include_router(create_documents_router(connect, authorize))
app.include_router(create_media_router(connect, authorize))


def response_record(row: dict[str, Any]) -> dict[str, Any]:
    return {
        "schema": SYNC_SCHEMA,
        "workspace_schema": WORKSPACE_SCHEMA,
        "workspace_uuid": str(row["workspace_uuid"]),
        "owner_external_id": row["owner_external_id"],
        "title": row["title"],
        "description": row["description"],
        "visibility": row["visibility"],
        "revision": int(row["revision"]),
        "content_hash": row["content_hash"],
        "etag": row["content_hash"],
        "workspace": row["payload"],
        "created_at": row["created_at"].isoformat(),
        "updated_at": row["updated_at"].isoformat(),
    }


@app.get("/health")
def health() -> dict[str, Any]:
    database = "not_configured"
    if DATABASE_URL:
        try:
            with connect() as conn, conn.cursor() as cur:
                cur.execute("SELECT 1 AS ok")
                cur.fetchone()
            database = "online"
        except Exception:
            database = "unavailable"
    return {
        "ok": database == "online",
        "service": "sustainable-catalyst-library-service",
        "document_job_schema": "sc-library-document-job/1.0",
        "edition_schema": "sc-library-edition/1.0",
        "media_job_schema": "sc-library-media-job/1.0",
        "version": SERVICE_VERSION,
        "schema": SYNC_SCHEMA,
        "workspace_schema": WORKSPACE_SCHEMA,
        "database": database,
    }


@app.put("/api/v1/workspaces/{workspace_uuid}")
async def put_workspace(
    workspace_uuid: UUID,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_library_signature: str | None = Header(default=None),
    x_sc_library_timestamp: str | None = Header(default=None),
    x_sc_owner: str | None = Header(default=None),
) -> dict[str, Any] | JSONResponse:
    body = await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
    if len(body) > MAX_WORKSPACE_BYTES:
        raise HTTPException(status_code=413, detail="workspace packet exceeds configured size limit")
    packet = WorkspacePacket.model_validate_json(body)
    if packet.workspace_uuid != workspace_uuid:
        raise HTTPException(status_code=400, detail="workspace UUID mismatch")
    if x_sc_owner != packet.owner_external_id:
        raise HTTPException(status_code=403, detail="owner identity mismatch")
    now = datetime.now(timezone.utc)
    with connect() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_account_workspaces WHERE workspace_uuid = %s FOR UPDATE", (workspace_uuid,))
        existing = cur.fetchone()
        if existing:
            if existing["owner_external_id"] != packet.owner_external_id:
                raise HTTPException(status_code=403, detail="workspace owner mismatch")
            existing_revision = int(existing["revision"])
            if packet.revision < existing_revision:
                return JSONResponse(status_code=409, content={"detail": "remote revision is newer", **response_record(existing)})
            if packet.revision == existing_revision:
                if constant_time_equal(existing["content_hash"], packet.content_hash):
                    return response_record(existing)
                return JSONResponse(status_code=409, content={"detail": "same revision has different content", **response_record(existing)})
            cur.execute(
                "INSERT INTO library_account_workspace_revisions (workspace_uuid, owner_external_id, revision, content_hash, payload) VALUES (%s,%s,%s,%s,%s) ON CONFLICT DO NOTHING",
                (workspace_uuid, existing["owner_external_id"], existing_revision, existing["content_hash"], Jsonb(existing["payload"])),
            )
            cur.execute(
                """UPDATE library_account_workspaces
                   SET title=%s, description=%s, visibility=%s, workspace_schema=%s,
                       revision=%s, content_hash=%s, payload=%s, updated_at=%s
                   WHERE workspace_uuid=%s RETURNING *""",
                (packet.title, packet.description, packet.visibility, WORKSPACE_SCHEMA,
                 packet.revision, packet.content_hash, Jsonb(packet.workspace), now, workspace_uuid),
            )
            row = cur.fetchone()
        else:
            cur.execute(
                """INSERT INTO library_account_workspaces
                   (workspace_uuid, owner_external_id, title, description, visibility, workspace_schema,
                    revision, content_hash, payload, created_at, updated_at)
                   VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) RETURNING *""",
                (workspace_uuid, packet.owner_external_id, packet.title, packet.description, packet.visibility,
                 WORKSPACE_SCHEMA, packet.revision, packet.content_hash, Jsonb(packet.workspace), now, now),
            )
            row = cur.fetchone()
        conn.commit()
    return response_record(row)


@app.get("/api/v1/workspaces/{workspace_uuid}")
async def get_workspace(
    workspace_uuid: UUID,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_library_signature: str | None = Header(default=None),
    x_sc_library_timestamp: str | None = Header(default=None),
    x_sc_owner: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
    with connect() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_account_workspaces WHERE workspace_uuid = %s", (workspace_uuid,))
        row = cur.fetchone()
    if not row:
        raise HTTPException(status_code=404, detail="workspace not found")
    if x_sc_owner != row["owner_external_id"]:
        raise HTTPException(status_code=403, detail="workspace owner mismatch")
    return response_record(row)


@app.delete("/api/v1/workspaces/{workspace_uuid}", status_code=204)
async def delete_workspace(
    workspace_uuid: UUID,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_library_signature: str | None = Header(default=None),
    x_sc_library_timestamp: str | None = Header(default=None),
    x_sc_owner: str | None = Header(default=None),
) -> Response:
    await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
    with connect() as conn, conn.cursor() as cur:
        cur.execute("SELECT owner_external_id FROM library_account_workspaces WHERE workspace_uuid = %s FOR UPDATE", (workspace_uuid,))
        row = cur.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="workspace not found")
        if x_sc_owner != row["owner_external_id"]:
            raise HTTPException(status_code=403, detail="workspace owner mismatch")
        cur.execute("DELETE FROM library_account_workspaces WHERE workspace_uuid = %s", (workspace_uuid,))
        conn.commit()
    return Response(status_code=204)


@app.get("/api/v1/workspaces/{workspace_uuid}/history")
async def workspace_history(
    workspace_uuid: UUID,
    request: Request,
    limit: int = 25,
    authorization: str | None = Header(default=None),
    x_sc_library_signature: str | None = Header(default=None),
    x_sc_library_timestamp: str | None = Header(default=None),
    x_sc_owner: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
    limit = max(1, min(100, limit))
    with connect() as conn, conn.cursor() as cur:
        cur.execute("SELECT owner_external_id FROM library_account_workspaces WHERE workspace_uuid = %s", (workspace_uuid,))
        owner = cur.fetchone()
        if not owner:
            raise HTTPException(status_code=404, detail="workspace not found")
        if x_sc_owner != owner["owner_external_id"]:
            raise HTTPException(status_code=403, detail="workspace owner mismatch")
        cur.execute(
            "SELECT revision, content_hash, created_at FROM library_account_workspace_revisions WHERE workspace_uuid = %s ORDER BY revision DESC LIMIT %s",
            (workspace_uuid, limit),
        )
        rows = cur.fetchall()
    return {"schema": SYNC_SCHEMA, "workspace_uuid": str(workspace_uuid), "items": [
        {"revision": int(row["revision"]), "content_hash": row["content_hash"], "created_at": row["created_at"].isoformat()}
        for row in rows
    ]}
