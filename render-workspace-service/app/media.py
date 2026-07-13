from __future__ import annotations

import hashlib
import ipaddress
import os
import shutil
import socket
import subprocess
import tempfile
import traceback
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Callable
from urllib.parse import urlparse
from uuid import UUID

import httpx
import imageio_ffmpeg
from fastapi import APIRouter, BackgroundTasks, Header, HTTPException, Request, Response
from pydantic import BaseModel, ConfigDict, Field, field_validator
from psycopg.types.json import Jsonb

MEDIA_JOB_SCHEMA = "sc-library-media-job/1.0"
MEDIA_PROCESSOR_VERSION = "1.14.1"
MAX_SOURCE_BYTES = max(10, min(2048, int(os.getenv("SC_LIBRARY_MEDIA_MAX_SOURCE_MB", "500")))) * 1024 * 1024
MAX_OUTPUT_BYTES = max(10, min(2048, int(os.getenv("SC_LIBRARY_MEDIA_MAX_OUTPUT_MB", "500")))) * 1024 * 1024
MAX_CLIP_SECONDS = max(60, min(14400, int(os.getenv("SC_LIBRARY_MEDIA_MAX_CLIP_MINUTES", "30")) * 60))
MEDIA_MAX_ATTEMPTS = max(1, min(10, int(os.getenv("SC_LIBRARY_MEDIA_MAX_ATTEMPTS", "3"))))
ALLOW_REMOTE_MEDIA = os.getenv("SC_LIBRARY_ALLOW_REMOTE_MEDIA", "true").lower() in {"1", "true", "yes", "on"}

MEDIA_SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS library_media_jobs (
    job_uuid uuid PRIMARY KEY,
    owner_external_id text NOT NULL,
    job_schema text NOT NULL,
    asset_uuid uuid NOT NULL,
    clip_uuid uuid NOT NULL,
    title text NOT NULL,
    request_payload jsonb NOT NULL,
    status text NOT NULL DEFAULT 'queued' CHECK (status IN ('queued','processing','completed','error','cancelled')),
    progress integer NOT NULL DEFAULT 0 CHECK (progress >= 0 AND progress <= 100),
    attempt integer NOT NULL DEFAULT 0,
    max_attempts integer NOT NULL DEFAULT 3,
    processor_version text NOT NULL DEFAULT '',
    output_video bytea,
    output_poster bytea,
    output_sha256 char(64) NOT NULL DEFAULT '',
    poster_sha256 char(64) NOT NULL DEFAULT '',
    output_bytes bigint NOT NULL DEFAULT 0,
    poster_bytes bigint NOT NULL DEFAULT 0,
    diagnostics jsonb NOT NULL DEFAULT '{}'::jsonb,
    error_message text NOT NULL DEFAULT '',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_media_jobs_owner_idx ON library_media_jobs(owner_external_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_media_jobs_status_idx ON library_media_jobs(status, updated_at DESC);
CREATE INDEX IF NOT EXISTS library_media_jobs_clip_idx ON library_media_jobs(clip_uuid, created_at DESC);
"""

AUTHORIZED_RIGHTS = {
    "owned",
    "licensed",
    "permission_granted",
    "public_domain",
    "creative_commons",
    "fair_use_excerpt",
}


class RightsPacket(BaseModel):
    model_config = ConfigDict(extra="forbid")
    status: str
    holder: str = Field(default="", max_length=500)
    license: str = Field(default="", max_length=500)
    license_url: str = Field(default="", max_length=4000)
    note: str = Field(default="", max_length=20000)
    citation: str = Field(default="", max_length=20000)

    @field_validator("status")
    @classmethod
    def validate_status(cls, value: str) -> str:
        if value not in AUTHORIZED_RIGHTS:
            raise ValueError("media rights must be verified before processing")
        return value


class MediaOptions(BaseModel):
    model_config = ConfigDict(extra="forbid")
    format: str = "mp4"
    video_codec: str = "h264"
    audio_codec: str = "aac"
    burn_captions: bool = False
    create_poster: bool = True
    retention_days: int = Field(default=14, ge=1, le=365)

    @field_validator("format")
    @classmethod
    def validate_format(cls, value: str) -> str:
        if value != "mp4":
            raise ValueError("only mp4 output is supported")
        return value


class MediaJobPacket(BaseModel):
    model_config = ConfigDict(extra="forbid")
    schema: str = MEDIA_JOB_SCHEMA
    job_uuid: UUID
    owner_external_id: str = Field(min_length=1, max_length=191)
    asset_uuid: UUID
    clip_uuid: UUID
    title: str = Field(min_length=1, max_length=500)
    source_url: str = Field(min_length=1, max_length=8000)
    source_kind: str = Field(default="attachment", max_length=32)
    start_ms: int = Field(ge=0)
    end_ms: int = Field(gt=0)
    poster_time_ms: int = Field(ge=0)
    caption_text: str = Field(default="", max_length=100000)
    transcript_excerpt: str = Field(default="", max_length=100000)
    rights: RightsPacket
    options: MediaOptions = Field(default_factory=MediaOptions)

    @field_validator("schema")
    @classmethod
    def validate_schema(cls, value: str) -> str:
        if value != MEDIA_JOB_SCHEMA:
            raise ValueError("unsupported media job schema")
        return value

    @field_validator("source_url")
    @classmethod
    def validate_source_url(cls, value: str) -> str:
        validate_public_https_url(value)
        return value

    def model_post_init(self, __context: Any) -> None:
        if self.end_ms <= self.start_ms:
            raise ValueError("clip end must be later than clip start")
        if (self.end_ms - self.start_ms) > MAX_CLIP_SECONDS * 1000:
            raise ValueError("clip exceeds configured maximum duration")
        if self.poster_time_ms < self.start_ms or self.poster_time_ms > self.end_ms:
            raise ValueError("poster time must fall within the clip")


def validate_public_https_url(value: str) -> None:
    if not ALLOW_REMOTE_MEDIA:
        raise ValueError("remote media processing is disabled")
    parsed = urlparse(value)
    if parsed.scheme.lower() != "https" or not parsed.hostname:
        raise ValueError("source URL must use public HTTPS")
    host = parsed.hostname.lower()
    if host in {"localhost", "127.0.0.1", "::1"} or host.endswith(".local"):
        raise ValueError("private media host is not allowed")
    try:
        addresses = socket.getaddrinfo(host, parsed.port or 443, type=socket.SOCK_STREAM)
    except socket.gaierror as exc:
        raise ValueError("media host could not be resolved") from exc
    for item in addresses:
        address = ipaddress.ip_address(item[4][0])
        if address.is_private or address.is_loopback or address.is_link_local or address.is_reserved or address.is_multicast:
            raise ValueError("private media address is not allowed")


def initialize_media_database(connect: Callable[[], Any]) -> None:
    try:
        with connect() as conn, conn.cursor() as cur:
            cur.execute(MEDIA_SCHEMA_SQL)
            cur.execute("UPDATE library_media_jobs SET status='queued', progress=0 WHERE status='processing'")
            conn.commit()
    except Exception:
        pass


def _download_source(url: str, target: Path) -> dict[str, Any]:
    diagnostics: dict[str, Any] = {"source_url": url, "redirects": 0}
    current_url = url
    with httpx.Client(follow_redirects=False, timeout=httpx.Timeout(60.0, connect=10.0), headers={"User-Agent": "SustainableCatalystLibrary/1.14"}) as client:
        for redirect_count in range(6):
            validate_public_https_url(current_url)
            request = client.build_request("GET", current_url)
            response = client.send(request, stream=True)
            if response.status_code in {301, 302, 303, 307, 308}:
                location = response.headers.get("location", "")
                response.close()
                if not location:
                    raise ValueError("media redirect did not provide a destination")
                current_url = str(httpx.URL(current_url).join(location))
                diagnostics["redirects"] = redirect_count + 1
                continue
            response.raise_for_status()
            content_length = int(response.headers.get("content-length", "0") or 0)
            if content_length and content_length > MAX_SOURCE_BYTES:
                response.close()
                raise ValueError("source media exceeds configured size limit")
            total = 0
            with response, target.open("wb") as handle:
                for chunk in response.iter_bytes(1024 * 1024):
                    total += len(chunk)
                    if total > MAX_SOURCE_BYTES:
                        raise ValueError("source media exceeds configured size limit")
                    handle.write(chunk)
            diagnostics.update({"source_bytes": total, "source_content_type": response.headers.get("content-type", ""), "final_url": current_url})
            return diagnostics
    raise ValueError("media source exceeded the redirect limit")


def build_ffmpeg_clip_args(ffmpeg: str, source: Path, output: Path, packet: MediaJobPacket) -> list[str]:
    start_seconds = packet.start_ms / 1000.0
    duration_seconds = (packet.end_ms - packet.start_ms) / 1000.0
    return [
        ffmpeg,
        "-hide_banner",
        "-loglevel", "error",
        "-y",
        "-ss", f"{start_seconds:.3f}",
        "-i", str(source),
        "-t", f"{duration_seconds:.3f}",
        "-map", "0:v:0?",
        "-map", "0:a:0?",
        "-c:v", "libx264",
        "-preset", "veryfast",
        "-crf", "23",
        "-pix_fmt", "yuv420p",
        "-c:a", "aac",
        "-b:a", "160k",
        "-movflags", "+faststart",
        str(output),
    ]


def build_ffmpeg_poster_args(ffmpeg: str, source: Path, output: Path, packet: MediaJobPacket) -> list[str]:
    poster_seconds = packet.poster_time_ms / 1000.0
    return [
        ffmpeg,
        "-hide_banner",
        "-loglevel", "error",
        "-y",
        "-ss", f"{poster_seconds:.3f}",
        "-i", str(source),
        "-frames:v", "1",
        "-vf", "scale='min(1600,iw)':-2",
        "-q:v", "3",
        str(output),
    ]


def _run_command(args: list[str]) -> None:
    result = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=600, check=False)
    if result.returncode != 0:
        error = result.stderr.decode("utf-8", "replace")[-12000:]
        raise RuntimeError(error or f"ffmpeg exited with code {result.returncode}")


def process_media_job(connect: Callable[[], Any], job_uuid: UUID) -> None:
    now = datetime.now(timezone.utc)
    with connect() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_media_jobs WHERE job_uuid=%s FOR UPDATE", (job_uuid,))
        row = cur.fetchone()
        if not row or row["status"] == "cancelled":
            return
        attempt = int(row["attempt"]) + 1
        cur.execute("UPDATE library_media_jobs SET status='processing', progress=5, attempt=%s, updated_at=%s WHERE job_uuid=%s", (attempt, now, job_uuid))
        conn.commit()
        packet = MediaJobPacket.model_validate(row["request_payload"])

    diagnostics: dict[str, Any] = {"processor_version": MEDIA_PROCESSOR_VERSION, "captions_burned": False, "caption_text_preserved": bool(packet.caption_text), "burn_captions_requested": bool(packet.options.burn_captions)}
    temp_dir = Path(tempfile.mkdtemp(prefix="sc-library-media-"))
    try:
        source = temp_dir / "source-media"
        output = temp_dir / "clip.mp4"
        poster = temp_dir / "poster.jpg"
        diagnostics.update(_download_source(packet.source_url, source))
        with connect() as conn, conn.cursor() as cur:
            cur.execute("UPDATE library_media_jobs SET progress=30, updated_at=now() WHERE job_uuid=%s", (job_uuid,))
            conn.commit()

        ffmpeg = imageio_ffmpeg.get_ffmpeg_exe()
        diagnostics["ffmpeg"] = Path(ffmpeg).name
        _run_command(build_ffmpeg_clip_args(ffmpeg, source, output, packet))
        if not output.exists() or output.stat().st_size < 1:
            raise RuntimeError("ffmpeg did not create a clip output")
        if output.stat().st_size > MAX_OUTPUT_BYTES:
            raise ValueError("processed clip exceeds configured output size limit")

        with connect() as conn, conn.cursor() as cur:
            cur.execute("UPDATE library_media_jobs SET progress=75, updated_at=now() WHERE job_uuid=%s", (job_uuid,))
            conn.commit()

        poster_bytes = b""
        if packet.options.create_poster:
            try:
                _run_command(build_ffmpeg_poster_args(ffmpeg, source, poster, packet))
                if poster.exists():
                    poster_bytes = poster.read_bytes()
            except Exception as poster_error:
                diagnostics["poster_warning"] = str(poster_error)[-2000:]

        video_bytes = output.read_bytes()
        video_sha = hashlib.sha256(video_bytes).hexdigest()
        poster_sha = hashlib.sha256(poster_bytes).hexdigest() if poster_bytes else ""
        diagnostics.update({
            "start_ms": packet.start_ms,
            "end_ms": packet.end_ms,
            "duration_ms": packet.end_ms - packet.start_ms,
            "poster_time_ms": packet.poster_time_ms,
            "rights_status": packet.rights.status,
            "retention_days": packet.options.retention_days,
        })
        completed = datetime.now(timezone.utc)
        with connect() as conn, conn.cursor() as cur:
            cur.execute(
                """UPDATE library_media_jobs
                   SET status='completed', progress=100, processor_version=%s,
                       output_video=%s, output_poster=%s, output_sha256=%s, poster_sha256=%s,
                       output_bytes=%s, poster_bytes=%s, diagnostics=%s, error_message='',
                       updated_at=%s, completed_at=%s
                   WHERE job_uuid=%s""",
                (MEDIA_PROCESSOR_VERSION, video_bytes, poster_bytes or None, video_sha, poster_sha,
                 len(video_bytes), len(poster_bytes), Jsonb(diagnostics), completed, completed, job_uuid),
            )
            conn.commit()
    except Exception as exc:
        diagnostics["traceback"] = traceback.format_exc(limit=8)[-16000:]
        with connect() as conn, conn.cursor() as cur:
            cur.execute(
                "UPDATE library_media_jobs SET status='error', progress=0, diagnostics=%s, error_message=%s, updated_at=now() WHERE job_uuid=%s",
                (Jsonb(diagnostics), str(exc)[-12000:], job_uuid),
            )
            conn.commit()
    finally:
        shutil.rmtree(temp_dir, ignore_errors=True)


def media_response(row: dict[str, Any]) -> dict[str, Any]:
    return {
        "schema": MEDIA_JOB_SCHEMA,
        "job_uuid": str(row["job_uuid"]),
        "asset_uuid": str(row["asset_uuid"]),
        "clip_uuid": str(row["clip_uuid"]),
        "title": row["title"],
        "status": row["status"],
        "progress": int(row["progress"]),
        "attempt": int(row["attempt"]),
        "max_attempts": int(row["max_attempts"]),
        "processor_version": row["processor_version"],
        "output_sha256": row["output_sha256"],
        "poster_sha256": row["poster_sha256"],
        "output_bytes": int(row["output_bytes"]),
        "poster_bytes": int(row["poster_bytes"]),
        "diagnostics": row["diagnostics"] or {},
        "error": row["error_message"],
        "created_at": row["created_at"].isoformat(),
        "updated_at": row["updated_at"].isoformat(),
        "completed_at": row["completed_at"].isoformat() if row["completed_at"] else None,
    }


def create_media_router(connect: Callable[[], Any], authorize: Callable[..., Any]) -> APIRouter:
    router = APIRouter()

    @router.post("/api/v1/media/jobs", status_code=202)
    async def create_job(
        request: Request,
        background_tasks: BackgroundTasks,
        authorization: str | None = Header(default=None),
        x_sc_library_signature: str | None = Header(default=None),
        x_sc_library_timestamp: str | None = Header(default=None),
        x_sc_owner: str | None = Header(default=None),
    ) -> dict[str, Any]:
        body = await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
        packet = MediaJobPacket.model_validate_json(body)
        if x_sc_owner != packet.owner_external_id:
            raise HTTPException(status_code=403, detail="owner identity mismatch")
        with connect() as conn, conn.cursor() as cur:
            cur.execute("SELECT * FROM library_media_jobs WHERE job_uuid=%s", (packet.job_uuid,))
            existing = cur.fetchone()
            if existing:
                if existing["owner_external_id"] != packet.owner_external_id:
                    raise HTTPException(status_code=403, detail="media job owner mismatch")
                return media_response(existing)
            cur.execute(
                """INSERT INTO library_media_jobs
                   (job_uuid, owner_external_id, job_schema, asset_uuid, clip_uuid, title,
                    request_payload, status, progress, attempt, max_attempts)
                   VALUES (%s,%s,%s,%s,%s,%s,%s,'queued',0,0,%s) RETURNING *""",
                (packet.job_uuid, packet.owner_external_id, MEDIA_JOB_SCHEMA, packet.asset_uuid,
                 packet.clip_uuid, packet.title, Jsonb(packet.model_dump(mode="json")), MEDIA_MAX_ATTEMPTS),
            )
            row = cur.fetchone()
            conn.commit()
        background_tasks.add_task(process_media_job, connect, packet.job_uuid)
        return media_response(row)

    @router.get("/api/v1/media/jobs/{job_uuid}")
    async def get_job(
        job_uuid: UUID,
        request: Request,
        authorization: str | None = Header(default=None),
        x_sc_library_signature: str | None = Header(default=None),
        x_sc_library_timestamp: str | None = Header(default=None),
        x_sc_owner: str | None = Header(default=None),
    ) -> dict[str, Any]:
        await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
        with connect() as conn, conn.cursor() as cur:
            cur.execute("SELECT * FROM library_media_jobs WHERE job_uuid=%s", (job_uuid,))
            row = cur.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="media job not found")
        if x_sc_owner != row["owner_external_id"]:
            raise HTTPException(status_code=403, detail="media job owner mismatch")
        return media_response(row)

    async def binary_output(job_uuid: UUID, kind: str, request: Request, authorization: str | None, signature: str | None, timestamp: str | None, owner: str | None) -> Response:
        await authorize(request, authorization, signature, timestamp)
        column = "output_video" if kind == "video" else "output_poster"
        with connect() as conn, conn.cursor() as cur:
            cur.execute(f"SELECT owner_external_id, status, {column} AS output FROM library_media_jobs WHERE job_uuid=%s", (job_uuid,))
            row = cur.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="media job not found")
        if owner != row["owner_external_id"]:
            raise HTTPException(status_code=403, detail="media job owner mismatch")
        if row["status"] != "completed" or not row["output"]:
            raise HTTPException(status_code=409, detail=f"media {kind} output is not available")
        return Response(content=bytes(row["output"]), media_type="video/mp4" if kind == "video" else "image/jpeg")

    @router.get("/api/v1/media/jobs/{job_uuid}/video")
    async def get_video(job_uuid: UUID, request: Request, authorization: str | None = Header(default=None), x_sc_library_signature: str | None = Header(default=None), x_sc_library_timestamp: str | None = Header(default=None), x_sc_owner: str | None = Header(default=None)) -> Response:
        return await binary_output(job_uuid, "video", request, authorization, x_sc_library_signature, x_sc_library_timestamp, x_sc_owner)

    @router.get("/api/v1/media/jobs/{job_uuid}/poster")
    async def get_poster(job_uuid: UUID, request: Request, authorization: str | None = Header(default=None), x_sc_library_signature: str | None = Header(default=None), x_sc_library_timestamp: str | None = Header(default=None), x_sc_owner: str | None = Header(default=None)) -> Response:
        return await binary_output(job_uuid, "poster", request, authorization, x_sc_library_signature, x_sc_library_timestamp, x_sc_owner)

    @router.post("/api/v1/media/jobs/{job_uuid}/retry", status_code=202)
    async def retry_job(
        job_uuid: UUID,
        request: Request,
        background_tasks: BackgroundTasks,
        authorization: str | None = Header(default=None),
        x_sc_library_signature: str | None = Header(default=None),
        x_sc_library_timestamp: str | None = Header(default=None),
        x_sc_owner: str | None = Header(default=None),
    ) -> dict[str, Any]:
        await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
        with connect() as conn, conn.cursor() as cur:
            cur.execute("SELECT * FROM library_media_jobs WHERE job_uuid=%s FOR UPDATE", (job_uuid,))
            row = cur.fetchone()
            if not row:
                raise HTTPException(status_code=404, detail="media job not found")
            if x_sc_owner != row["owner_external_id"]:
                raise HTTPException(status_code=403, detail="media job owner mismatch")
            if row["status"] != "error":
                raise HTTPException(status_code=409, detail="only failed media jobs can be retried")
            if int(row["attempt"]) >= int(row["max_attempts"]):
                raise HTTPException(status_code=409, detail="media job retry limit reached")
            cur.execute("UPDATE library_media_jobs SET status='queued', progress=0, error_message='', updated_at=now() WHERE job_uuid=%s RETURNING *", (job_uuid,))
            updated = cur.fetchone()
            conn.commit()
        background_tasks.add_task(process_media_job, connect, job_uuid)
        return media_response(updated)

    @router.delete("/api/v1/media/jobs/{job_uuid}", status_code=204)
    async def delete_job(job_uuid: UUID, request: Request, authorization: str | None = Header(default=None), x_sc_library_signature: str | None = Header(default=None), x_sc_library_timestamp: str | None = Header(default=None), x_sc_owner: str | None = Header(default=None)) -> Response:
        await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
        with connect() as conn, conn.cursor() as cur:
            cur.execute("SELECT owner_external_id FROM library_media_jobs WHERE job_uuid=%s", (job_uuid,))
            row = cur.fetchone()
            if not row:
                raise HTTPException(status_code=404, detail="media job not found")
            if x_sc_owner != row["owner_external_id"]:
                raise HTTPException(status_code=403, detail="media job owner mismatch")
            cur.execute("DELETE FROM library_media_jobs WHERE job_uuid=%s", (job_uuid,))
            conn.commit()
        return Response(status_code=204)

    return router
