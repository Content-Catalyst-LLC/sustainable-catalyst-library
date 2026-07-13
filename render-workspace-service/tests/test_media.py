from pathlib import Path
from uuid import uuid4

import pytest
from pydantic import ValidationError

from app import media
from app.media import MediaJobPacket, build_ffmpeg_clip_args, build_ffmpeg_poster_args


def public_dns(*args, **kwargs):
    return [(2, 1, 6, "", ("93.184.216.34", 443))]


def packet_data(**overrides):
    data = {
        "schema": "sc-library-media-job/1.0",
        "job_uuid": str(uuid4()),
        "owner_external_id": "wordpress:1",
        "asset_uuid": str(uuid4()),
        "clip_uuid": str(uuid4()),
        "title": "Systems briefing excerpt",
        "source_url": "https://media.example.org/source.mp4",
        "source_kind": "attachment",
        "start_ms": 10_000,
        "end_ms": 70_000,
        "poster_time_ms": 20_000,
        "caption_text": "A caption track remains attached to the clip record.",
        "transcript_excerpt": "An excerpt of the spoken briefing.",
        "rights": {"status": "owned", "holder": "Sustainable Catalyst"},
        "options": {"format": "mp4", "create_poster": True, "retention_days": 14},
    }
    data.update(overrides)
    return data


def test_packet_requires_verified_rights(monkeypatch):
    monkeypatch.setattr(media.socket, "getaddrinfo", public_dns)
    data = packet_data(rights={"status": "unknown"})
    with pytest.raises(ValidationError):
        MediaJobPacket.model_validate(data)


def test_packet_rejects_invalid_duration_and_poster(monkeypatch):
    monkeypatch.setattr(media.socket, "getaddrinfo", public_dns)
    with pytest.raises(ValidationError):
        MediaJobPacket.model_validate(packet_data(end_ms=5_000))
    with pytest.raises(ValidationError):
        MediaJobPacket.model_validate(packet_data(poster_time_ms=90_000))


def test_ffmpeg_commands_are_bounded_argument_lists(monkeypatch, tmp_path):
    monkeypatch.setattr(media.socket, "getaddrinfo", public_dns)
    packet = MediaJobPacket.model_validate(packet_data())
    source = tmp_path / "source.mp4"
    output = tmp_path / "clip.mp4"
    poster = tmp_path / "poster.jpg"
    clip_args = build_ffmpeg_clip_args("/usr/bin/ffmpeg", source, output, packet)
    poster_args = build_ffmpeg_poster_args("/usr/bin/ffmpeg", source, poster, packet)
    assert clip_args[0] == "/usr/bin/ffmpeg"
    assert "-ss" in clip_args and "10.000" in clip_args
    assert "-t" in clip_args and "60.000" in clip_args
    assert str(output) == clip_args[-1]
    assert "shell=True" not in " ".join(clip_args)
    assert str(poster) == poster_args[-1]
