from __future__ import annotations

import hashlib
import io
import json
import os
import re
import traceback
from datetime import datetime, timezone
from html import escape
from html.parser import HTMLParser
from typing import Any, Callable
from uuid import UUID

import httpx
from fastapi import APIRouter, BackgroundTasks, Header, HTTPException, Request, Response
from fastapi.responses import JSONResponse
from pydantic import BaseModel, ConfigDict, Field, field_validator
from psycopg.rows import dict_row
from psycopg.types.json import Jsonb
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4, LETTER
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import inch
from reportlab.graphics.shapes import Drawing, Ellipse, Line, Path as GraphicsPath, Polygon, Rect, String
from reportlab.graphics.barcode.qr import QrCodeWidget
from reportlab.platypus import (
    Image,
    PageBreak,
    Paragraph,
    Preformatted,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

from .core import constant_time_equal

DOCUMENT_JOB_SCHEMA = "sc-library-document-job/1.0"
EDITION_SCHEMA = "sc-library-edition/1.0"
RENDERER_VERSION = "1.14.1"
MAX_REQUEST_BYTES = max(1, min(25, int(os.getenv("SC_LIBRARY_MAX_DOCUMENT_REQUEST_MB", "8")))) * 1024 * 1024
MAX_PDF_BYTES = max(1, min(50, int(os.getenv("SC_LIBRARY_MAX_PDF_MB", "20")))) * 1024 * 1024
MAX_ATTEMPTS = max(1, min(10, int(os.getenv("SC_LIBRARY_DOCUMENT_MAX_ATTEMPTS", "3"))))
ALLOW_REMOTE_IMAGES = os.getenv("SC_LIBRARY_ALLOW_REMOTE_IMAGES", "true").lower() in {"1", "true", "yes", "on"}

DOCUMENT_SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS library_document_jobs (
    job_uuid uuid PRIMARY KEY,
    owner_external_id text NOT NULL,
    job_schema text NOT NULL,
    book_id text NOT NULL DEFAULT '',
    title text NOT NULL,
    content_hash char(64) NOT NULL,
    request_payload jsonb NOT NULL,
    status text NOT NULL DEFAULT 'queued' CHECK (status IN ('queued','processing','completed','error','cancelled')),
    progress integer NOT NULL DEFAULT 0 CHECK (progress >= 0 AND progress <= 100),
    attempt integer NOT NULL DEFAULT 0,
    max_attempts integer NOT NULL DEFAULT 3,
    renderer_version text NOT NULL DEFAULT '',
    output_pdf bytea,
    output_sha256 char(64) NOT NULL DEFAULT '',
    output_bytes bigint NOT NULL DEFAULT 0,
    manifest jsonb NOT NULL DEFAULT '{}'::jsonb,
    diagnostics jsonb NOT NULL DEFAULT '{}'::jsonb,
    error_message text NOT NULL DEFAULT '',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_document_jobs_owner_idx ON library_document_jobs(owner_external_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_document_jobs_status_idx ON library_document_jobs(status, updated_at DESC);
CREATE INDEX IF NOT EXISTS library_document_jobs_book_idx ON library_document_jobs(book_id, created_at DESC);
"""


class DocumentSection(BaseModel):
    model_config = ConfigDict(extra="forbid")
    position: int = Field(ge=1, le=500)
    type: str = Field(default="section", max_length=64)
    title: str = Field(min_length=1, max_length=500)
    html: str = Field(default="", max_length=2_000_000)
    source_url: str = Field(default="", max_length=4000)
    citation: str = Field(default="", max_length=20000)
    alt_text: str = Field(default="", max_length=20000)
    metadata: dict[str, Any] = Field(default_factory=dict)


class BookInfo(BaseModel):
    model_config = ConfigDict(extra="forbid")
    schema: str = Field(default="sc-library-book/1.0")
    id: str = Field(default="", max_length=191)
    title: str = Field(min_length=1, max_length=500)
    subtitle: str = Field(default="", max_length=500)
    editor: str = Field(default="", max_length=500)
    edition: str = Field(default="First edition", max_length=255)
    description: str = Field(default="", max_length=20000)
    theme: str = Field(default="institutional", max_length=64)
    page_size: str = Field(default="letter", max_length=32)
    front_matter: dict[str, str] = Field(default_factory=dict)
    back_matter: dict[str, str] = Field(default_factory=dict)


class DocumentOptions(BaseModel):
    model_config = ConfigDict(extra="forbid")
    include_toc: bool = True
    include_manifest: bool = True
    include_citations: bool = True
    include_indexes: bool = True
    include_accessibility_notes: bool = True
    grayscale: bool = False
    language: str = Field(default="en-US", max_length=32)


class DocumentJobPacket(BaseModel):
    model_config = ConfigDict(extra="forbid")
    schema: str = Field(default=DOCUMENT_JOB_SCHEMA)
    job_uuid: UUID
    owner_external_id: str = Field(min_length=1, max_length=191)
    requested_at: datetime | None = None
    workspace_uuid: str = Field(default="", max_length=64)
    book: BookInfo
    sections: list[DocumentSection] = Field(min_length=1, max_length=500)
    options: DocumentOptions = Field(default_factory=DocumentOptions)

    @field_validator("schema")
    @classmethod
    def validate_schema(cls, value: str) -> str:
        if value != DOCUMENT_JOB_SCHEMA:
            raise ValueError("unsupported document job schema")
        return value


class FlowHTMLParser(HTMLParser):
    BLOCKS = {"p", "h1", "h2", "h3", "h4", "h5", "li", "pre", "blockquote"}
    IGNORE = {"script", "style", "svg", "video", "audio", "canvas", "noscript", "form", "button", "nav", "iframe"}

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.blocks: list[dict[str, Any]] = []
        self.current_tag: str | None = None
        self.current_text: list[str] = []
        self.ignore_depth = 0
        self.in_table = False
        self.table_rows: list[list[str]] = []
        self.current_row: list[str] | None = None
        self.current_cell: list[str] | None = None

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag = tag.lower()
        if tag in self.IGNORE:
            self.ignore_depth += 1
            return
        if self.ignore_depth:
            return
        attrs_dict = {k: (v or "") for k, v in attrs}
        if tag == "img":
            self.blocks.append({"tag": "img", "src": attrs_dict.get("src", ""), "alt": attrs_dict.get("alt", "")})
            return
        if tag == "table":
            self._flush()
            self.in_table = True
            self.table_rows = []
            return
        if self.in_table:
            if tag == "tr":
                self.current_row = []
            elif tag in {"td", "th"}:
                self.current_cell = []
            elif tag == "br" and self.current_cell is not None:
                self.current_cell.append("\n")
            return
        if tag in self.BLOCKS:
            self._flush()
            self.current_tag = tag
            self.current_text = []
        elif tag == "br" and self.current_tag:
            self.current_text.append("\n")

    def handle_endtag(self, tag: str) -> None:
        tag = tag.lower()
        if tag in self.IGNORE:
            if self.ignore_depth:
                self.ignore_depth -= 1
            return
        if self.ignore_depth:
            return
        if self.in_table:
            if tag in {"td", "th"} and self.current_cell is not None:
                if self.current_row is None:
                    self.current_row = []
                self.current_row.append(" ".join("".join(self.current_cell).split()))
                self.current_cell = None
            elif tag == "tr" and self.current_row is not None:
                if any(cell for cell in self.current_row):
                    self.table_rows.append(self.current_row)
                self.current_row = None
            elif tag == "table":
                if self.table_rows:
                    self.blocks.append({"tag": "table", "rows": self.table_rows})
                self.in_table = False
                self.table_rows = []
            return
        if self.current_tag == tag:
            self._flush()

    def handle_data(self, data: str) -> None:
        if self.ignore_depth:
            return
        if self.in_table and self.current_cell is not None:
            self.current_cell.append(data)
        elif self.current_tag:
            self.current_text.append(data)

    def close(self) -> None:
        self._flush()
        super().close()

    def _flush(self) -> None:
        if self.current_tag:
            text = " ".join("".join(self.current_text).split()) if self.current_tag != "pre" else "".join(self.current_text).strip()
            if text:
                self.blocks.append({"tag": self.current_tag, "text": text})
        self.current_tag = None
        self.current_text = []


def initialize_document_database(connect: Callable[[], Any]) -> None:
    try:
        with connect() as conn, conn.cursor() as cur:
            cur.execute(DOCUMENT_SCHEMA_SQL)
            cur.execute("UPDATE library_document_jobs SET status='queued', progress=0 WHERE status='processing'")
            conn.commit()
    except Exception:
        # Main health endpoint will report database failure; startup should not
        # make workspace-only use impossible when the database is unavailable.
        pass


def _clean_text(value: str) -> str:
    return " ".join((value or "").split())


def _safe_para(value: str) -> str:
    return escape(_clean_text(value)).replace("\n", "<br/>")


def _image_flowable(src: str, alt: str, diagnostics: dict[str, Any]) -> Image | Paragraph | None:
    if not src:
        return None
    if not ALLOW_REMOTE_IMAGES or not src.lower().startswith(("https://", "http://")):
        return Paragraph(f"[Image: {_safe_para(alt or 'image unavailable in server edition')}]", diagnostics["styles"]["Caption"])
    try:
        with httpx.Client(follow_redirects=True, timeout=6.0, headers={"User-Agent": "SustainableCatalystLibrary/1.13"}) as client:
            response = client.get(src)
            response.raise_for_status()
            content_type = response.headers.get("content-type", "")
            if not content_type.startswith("image/") or len(response.content) > 5 * 1024 * 1024:
                raise ValueError("unsupported image response")
            stream = io.BytesIO(response.content)
            image = Image(stream)
            max_w, max_h = 6.3 * inch, 7.2 * inch
            ratio = min(max_w / image.imageWidth, max_h / image.imageHeight, 1)
            image.drawWidth = image.imageWidth * ratio
            image.drawHeight = image.imageHeight * ratio
            image._sc_stream = stream
            diagnostics["images_embedded"] += 1
            return image
    except Exception as exc:
        diagnostics["image_warnings"].append({"src": src[:500], "error": str(exc)[:300]})
        return Paragraph(f"[Image: {_safe_para(alt or 'image could not be loaded')}]", diagnostics["styles"]["Caption"])


def _story_from_html(html: str, styles: dict[str, ParagraphStyle], diagnostics: dict[str, Any]) -> list[Any]:
    parser = FlowHTMLParser()
    parser.feed(html or "")
    parser.close()
    story: list[Any] = []
    for block in parser.blocks:
        tag = block.get("tag")
        if tag == "img":
            flow = _image_flowable(str(block.get("src", "")), str(block.get("alt", "")), diagnostics)
            if flow:
                story.extend([flow, Spacer(1, 8)])
            continue
        if tag == "table":
            rows = block.get("rows", [])[:200]
            if not rows:
                continue
            max_cols = min(8, max(len(row) for row in rows))
            data = []
            for r, row in enumerate(rows):
                data.append([Paragraph(_safe_para(cell[:4000]), styles["TableHeader"] if r == 0 else styles["TableCell"]) for cell in (row + [""] * max_cols)[:max_cols]])
            available = 6.6 * inch
            table = Table(data, repeatRows=1, colWidths=[available / max_cols] * max_cols, hAlign="LEFT")
            table.setStyle(TableStyle([
                ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#8d8d8d")),
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#ece8df")),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 4),
                ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ]))
            story.extend([table, Spacer(1, 10)])
            diagnostics["tables"] += 1
            continue
        text = str(block.get("text", ""))
        if not text:
            continue
        if tag in {"h1", "h2"}:
            story.extend([Paragraph(_safe_para(text), styles["Heading1"]), Spacer(1, 5)])
        elif tag == "h3":
            story.extend([Paragraph(_safe_para(text), styles["Heading2"]), Spacer(1, 4)])
        elif tag in {"h4", "h5"}:
            story.extend([Paragraph(_safe_para(text), styles["Heading3"]), Spacer(1, 3)])
        elif tag == "li":
            story.append(Paragraph("• " + _safe_para(text), styles["BodyText"]))
        elif tag == "pre":
            story.extend([Preformatted(text[:30000], styles["Code"]), Spacer(1, 8)])
        elif tag == "blockquote":
            story.extend([Paragraph(_safe_para(text), styles["Quote"]), Spacer(1, 8)])
        else:
            story.extend([Paragraph(_safe_para(text), styles["BodyText"]), Spacer(1, 6)])
    diagnostics["equations_detected"] += len(re.findall(r"(?:\\\(|\\\[|\$\$|\\begin\{|[A-Za-z]\s*=\s*[^<\n]{3,})", html or ""))
    return story



def _as_list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, dict):
        try:
            return [value[key] for key in sorted(value, key=lambda item: int(str(item)))]
        except (TypeError, ValueError):
            return list(value.values())
    return []


def _number(value: Any, default: float = 0.0) -> float:
    try:
        number = float(value)
        if number != number or number in (float("inf"), float("-inf")):
            return default
        return number
    except (TypeError, ValueError):
        return default


def _draw_color(value: Any, fallback: str = "#333333", opacity: float = 1.0) -> colors.Color:
    raw = str(value or fallback).strip()
    try:
        base = colors.HexColor(raw) if raw.startswith("#") else getattr(colors, raw, colors.HexColor(fallback))
    except Exception:
        base = colors.HexColor(fallback)
    return colors.Color(base.red, base.green, base.blue, alpha=max(0.0, min(1.0, opacity)))


def _short_lines(value: Any, width: int = 30, max_lines: int = 5) -> list[str]:
    words = str(value or "").split()
    lines: list[str] = []
    line = ""
    for word in words:
        candidate = word if not line else f"{line} {word}"
        if len(candidate) > width and line:
            lines.append(line)
            line = word
            if len(lines) >= max_lines:
                break
        else:
            line = candidate
    if line and len(lines) < max_lines:
        lines.append(line)
    if len(words) and len(" ".join(lines).split()) < len(words) and lines:
        lines[-1] = lines[-1].rstrip("…") + "…"
    return lines


def _svg_ml_path(d: Any, width: float, height: float, source_width: float, source_height: float) -> GraphicsPath | None:
    tokens = re.findall(r"[MLml]|-?\d+(?:\.\d+)?", str(d or ""))
    if not tokens:
        return None
    path = GraphicsPath()
    command = ""
    index = 0
    while index < len(tokens):
        token = tokens[index]
        if token in {"M", "L", "m", "l"}:
            command = token.upper()
            index += 1
            continue
        if index + 1 >= len(tokens) or not command:
            break
        x = _number(tokens[index]) / max(source_width, 1.0) * width
        y = height - (_number(tokens[index + 1]) / max(source_height, 1.0) * height)
        if command == "M":
            path.moveTo(x, y)
            command = "L"
        else:
            path.lineTo(x, y)
        index += 2
    return path


def _matrix_flowables(artifact: dict[str, Any], styles: dict[str, ParagraphStyle], diagnostics: dict[str, Any]) -> list[Any]:
    columns = [item for item in _as_list(artifact.get("columns")) if isinstance(item, dict)]
    rows = [item for item in _as_list(artifact.get("rows")) if isinstance(item, dict)]
    if not columns or not rows:
        return []
    output: list[Any] = []
    chunk_size = 5
    for offset in range(0, len(columns), chunk_size):
        subset = columns[offset:offset + chunk_size]
        if len(columns) > chunk_size:
            output.append(Paragraph(_safe_para(f"Translation columns {offset + 1}–{offset + len(subset)}"), styles["Heading3"]))
        data: list[list[Any]] = [[Paragraph("Knowledge layer", styles["TableHeader"])] + [Paragraph(_safe_para(col.get("label", "Translation")), styles["TableHeader"]) for col in subset]]
        for row in rows[:200]:
            cells = row.get("cells") if isinstance(row.get("cells"), dict) else {}
            rendered: list[Any] = [Paragraph(_safe_para(row.get("label", "Row")), styles["TableHeader"])]
            for col in subset:
                cell = cells.get(str(col.get("id", "")), {}) if isinstance(cells, dict) else {}
                if not isinstance(cell, dict):
                    cell = {"value": str(cell)}
                fragments = [_safe_para(str(cell.get("value", ""))[:10000]) or "—"]
                if cell.get("status"):
                    fragments.append(f'<font size="6.6" color="#555555">Status: {_safe_para(str(cell.get("status")))}</font>')
                if cell.get("sourceRef"):
                    fragments.append(f'<font size="6.6" color="#555555">Source: {_safe_para(str(cell.get("sourceRef")))}</font>')
                rendered.append(Paragraph("<br/>".join(fragments), styles["TableCell"]))
            data.append(rendered)
        available = 6.6 * inch
        col_count = len(subset) + 1
        table = Table(data, repeatRows=1, colWidths=[available * 0.18] + [available * 0.82 / max(1, col_count - 1)] * (col_count - 1), hAlign="LEFT")
        table.setStyle(TableStyle([
            ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#8d8d8d")),
            ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#ece8df")),
            ("BACKGROUND", (0, 1), (0, -1), colors.HexColor("#f7f4ed")),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 4),
            ("RIGHTPADDING", (0, 0), (-1, -1), 4),
            ("TOPPADDING", (0, 0), (-1, -1), 4),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ]))
        output.extend([table, Spacer(1, 10)])
        diagnostics["tables"] += 1
    diagnostics["matrices_rendered"] += 1
    return output


def _add_arrow(drawing: Drawing, x1: float, y1: float, x2: float, y2: float, color: colors.Color) -> None:
    drawing.add(Line(x1, y1, x2, y2, strokeColor=color, strokeWidth=0.8))
    angle = __import__("math").atan2(y2 - y1, x2 - x1)
    size = 5
    points = [
        x2, y2,
        x2 - size * __import__("math").cos(angle - 0.45), y2 - size * __import__("math").sin(angle - 0.45),
        x2 - size * __import__("math").cos(angle + 0.45), y2 - size * __import__("math").sin(angle + 0.45),
    ]
    drawing.add(Polygon(points, fillColor=color, strokeColor=color))


def _board_flowables(artifact: dict[str, Any], styles: dict[str, ParagraphStyle], diagnostics: dict[str, Any]) -> list[Any]:
    nodes = [item for item in _as_list(artifact.get("nodes")) if isinstance(item, dict)]
    edges = [item for item in _as_list(artifact.get("edges")) if isinstance(item, dict)]
    strokes = [item for item in _as_list(artifact.get("strokes")) if isinstance(item, dict)]
    if not nodes and not strokes:
        return []
    source_width = max(1.0, _number(artifact.get("width"), 1800))
    source_height = max(1.0, _number(artifact.get("height"), 1200))
    max_width, max_height = 6.6 * inch, 4.5 * inch
    scale = min(max_width / source_width, max_height / source_height)
    draw_width, draw_height = source_width * scale, source_height * scale
    chalk = str(artifact.get("type", "")) == "chalkboard" or "chalk" in str(artifact.get("background", ""))
    drawing = Drawing(draw_width, draw_height)
    drawing.add(Rect(0, 0, draw_width, draw_height, fillColor=colors.HexColor("#102015" if chalk else "#fbfaf6"), strokeColor=colors.HexColor("#4f5a51" if chalk else "#b7b3aa"), strokeWidth=0.8))
    node_map = {str(node.get("id", "")): node for node in nodes}
    edge_color = colors.HexColor("#b8d8c1" if chalk else "#5f5f5f")
    for edge in edges:
        left, right = node_map.get(str(edge.get("from", ""))), node_map.get(str(edge.get("to", "")))
        if not left or not right:
            continue
        x1 = (_number(left.get("x")) + _number(left.get("width"), 250) / 2) * scale
        y1 = draw_height - ((_number(left.get("y")) + _number(left.get("height"), 150) / 2) * scale)
        x2 = (_number(right.get("x")) + _number(right.get("width"), 250) / 2) * scale
        y2 = draw_height - ((_number(right.get("y")) + _number(right.get("height"), 150) / 2) * scale)
        _add_arrow(drawing, x1, y1, x2, y2, edge_color)
    for stroke in strokes[:1000]:
        path = _svg_ml_path(stroke.get("d"), draw_width, draw_height, source_width, source_height)
        if path is None:
            continue
        path.fillColor = None
        path.strokeColor = _draw_color(stroke.get("color"), "#e8ffe5" if chalk else "#721019", _number(stroke.get("opacity"), 1))
        path.strokeWidth = max(0.25, _number(stroke.get("width"), 4) * scale)
        drawing.add(path)
        diagnostics["ink_strokes_rendered"] += 1
    for node in nodes[:250]:
        x = _number(node.get("x")) * scale
        top = _number(node.get("y")) * scale
        width = max(55, _number(node.get("width"), 250) * scale)
        height = max(36, _number(node.get("height"), 150) * scale)
        y = draw_height - top - height
        fill = _draw_color(node.get("color"), "#173321" if chalk else "#fff7d6", 0.94)
        text_color = colors.HexColor("#f4fff4" if chalk else "#111111")
        drawing.add(Rect(x, y, width, height, rx=4, ry=4, fillColor=fill, strokeColor=colors.HexColor("#7da58a" if chalk else "#7d7d7d"), strokeWidth=0.6))
        title_lines = _short_lines(node.get("title"), max(12, int(width / 4.6)), 2)
        body_lines = _short_lines(node.get("body"), max(12, int(width / 4.8)), max(1, int((height - 25) / 8)))
        cursor = y + height - 11
        for line in title_lines:
            drawing.add(String(x + 5, cursor, line, fontName="Helvetica-Bold", fontSize=6.5, fillColor=text_color))
            cursor -= 8
        for line in body_lines:
            if cursor < y + 5:
                break
            drawing.add(String(x + 5, cursor, line, fontName="Helvetica", fontSize=5.5, fillColor=text_color))
            cursor -= 7
    output: list[Any] = [drawing, Spacer(1, 8)]
    if edges:
        names = {str(node.get("id", "")): str(node.get("title", node.get("id", ""))) for node in nodes}
        relationship_text = [f'{names.get(str(edge.get("from", "")), "Source")} → {edge.get("label") or edge.get("type") or "related to"} → {names.get(str(edge.get("to", "")), "Target")}' for edge in edges[:100]]
        output.append(Paragraph("<b>Relationships</b><br/>" + "<br/>".join(_safe_para(item) for item in relationship_text), styles["Small"]))
    diagnostics["boards_rendered"] += 1
    return output


def _annotation_flowables(artifact: dict[str, Any], styles: dict[str, ParagraphStyle], diagnostics: dict[str, Any]) -> list[Any]:
    strokes = [item for item in _as_list(artifact.get("strokes")) if isinstance(item, dict)]
    shapes = [item for item in _as_list(artifact.get("shapes")) if isinstance(item, dict)]
    notes = [item for item in _as_list(artifact.get("notes")) if isinstance(item, dict)]
    if not strokes and not shapes and not notes and not artifact.get("transcription"):
        return []
    source_width = max(1.0, _number(artifact.get("pageWidth"), 900))
    source_height = max(1.0, _number(artifact.get("pageHeight"), 1120))
    max_width, max_height = 6.2 * inch, 7.1 * inch
    scale = min(max_width / source_width, max_height / source_height)
    draw_width, draw_height = source_width * scale, source_height * scale
    dark = "dark" in str(artifact.get("pageStyle", ""))
    drawing = Drawing(draw_width, draw_height)
    drawing.add(Rect(0, 0, draw_width, draw_height, fillColor=colors.HexColor("#101813" if dark else "#ffffff"), strokeColor=colors.HexColor("#667069" if dark else "#a8a8a8"), strokeWidth=0.8))
    for stroke in strokes[:2500]:
        points = [point for point in _as_list(stroke.get("points")) if isinstance(point, dict)]
        if not points:
            continue
        path = GraphicsPath()
        for idx, point in enumerate(points):
            x = max(0.0, min(1.0, _number(point.get("x")))) * draw_width
            y = draw_height - (max(0.0, min(1.0, _number(point.get("y")))) * draw_height)
            if idx == 0:
                path.moveTo(x, y)
            else:
                path.lineTo(x, y)
        path.fillColor = None
        path.strokeColor = _draw_color(stroke.get("color"), "#e8ffe5" if dark else "#721019", _number(stroke.get("opacity"), 1))
        pressure = sum(_number(point.get("p"), 0.5) for point in points) / max(1, len(points))
        path.strokeWidth = max(0.35, _number(stroke.get("width"), 4) * scale * (0.7 + pressure * 0.6))
        drawing.add(path)
        diagnostics["ink_strokes_rendered"] += 1
    for shape in shapes[:500]:
        x1 = max(0.0, min(1.0, _number(shape.get("x1")))) * draw_width
        y1 = draw_height - (max(0.0, min(1.0, _number(shape.get("y1")))) * draw_height)
        x2 = max(0.0, min(1.0, _number(shape.get("x2")))) * draw_width
        y2 = draw_height - (max(0.0, min(1.0, _number(shape.get("y2")))) * draw_height)
        color = _draw_color(shape.get("color"), "#e8ffe5" if dark else "#721019", _number(shape.get("opacity"), 1))
        width = max(0.35, _number(shape.get("width"), 3) * scale)
        shape_type = str(shape.get("type", "rectangle"))
        if shape_type == "ellipse":
            drawing.add(Ellipse((x1 + x2) / 2, (y1 + y2) / 2, abs(x2 - x1) / 2, abs(y2 - y1) / 2, fillColor=None, strokeColor=color, strokeWidth=width))
        elif shape_type == "arrow":
            _add_arrow(drawing, x1, y1, x2, y2, color)
        else:
            drawing.add(Rect(min(x1, x2), min(y1, y2), abs(x2 - x1), abs(y2 - y1), fillColor=None, strokeColor=color, strokeWidth=width))
    for note in notes[:200]:
        x = max(0.0, min(0.9, _number(note.get("x")))) * draw_width
        y = draw_height - (max(0.0, min(0.95, _number(note.get("y")))) * draw_height)
        lines = _short_lines(note.get("text"), 28, 3)
        box_w, box_h = min(145, draw_width - x), max(24, 11 + len(lines) * 8)
        drawing.add(Rect(x, max(0, y - box_h), box_w, box_h, fillColor=colors.Color(1, 0.96, 0.63, alpha=0.94), strokeColor=colors.HexColor("#806b28"), strokeWidth=0.5))
        cursor = y - 10
        for line in lines:
            drawing.add(String(x + 4, cursor, line, fontName="Helvetica", fontSize=5.8, fillColor=colors.HexColor("#222222")))
            cursor -= 8
    output: list[Any] = [drawing, Spacer(1, 8)]
    if artifact.get("transcription"):
        output.extend([Paragraph("Accessible handwriting transcription", styles["Heading3"]), Paragraph(_safe_para(str(artifact.get("transcription"))), styles["BodyText"])])
    diagnostics["annotations_rendered"] += 1
    return output


def _artifact_flowables(section: DocumentSection, styles: dict[str, ParagraphStyle], diagnostics: dict[str, Any]) -> list[Any]:
    artifact = section.metadata.get("artifact") if isinstance(section.metadata, dict) else None
    if not isinstance(artifact, dict):
        return []
    kind = str(section.type or artifact.get("type") or "").lower()
    if kind == "matrix":
        return _matrix_flowables(artifact, styles, diagnostics)
    if kind == "board" or artifact.get("type") in {"whiteboard", "chalkboard"}:
        return _board_flowables(artifact, styles, diagnostics)
    if kind == "annotation" or str(artifact.get("schema", "")).startswith("sc-library-annotation/"):
        return _annotation_flowables(artifact, styles, diagnostics)
    return []

def _media_flowables(section: DocumentSection, styles: dict[str, ParagraphStyle], diagnostics: dict[str, Any]) -> list[Any]:
    """Render durable media references for print/PDF editions.

    A frozen PDF cannot embed interactive playback reliably, so media sections receive
    a human-readable description, clickable URL, transcript excerpt, and QR fallback.
    """
    metadata = section.metadata if isinstance(section.metadata, dict) else {}
    media = metadata.get("media")
    if not isinstance(media, dict):
        return []
    url = str(media.get("url") or section.source_url or "").strip()
    if not url.startswith(("https://", "http://")):
        return []

    title = str(media.get("title") or section.title or "Media source").strip()
    description = str(media.get("description") or "").strip()
    segment = str(media.get("selected_segment") or "").strip()
    transcript = str(media.get("transcript") or "").strip()
    flowables: list[Any] = [Spacer(1, 7), Paragraph("Media access", styles["Heading2"])]
    if description:
        flowables.append(Paragraph(_safe_para(description), styles["BodyText"]))
    if segment:
        flowables.append(Paragraph(_safe_para(f"Selected segment: {segment}"), styles["Small"]))
    flowables.append(Paragraph(f'<link href="{escape(url)}">{escape(url)}</link>', styles["Small"]))

    try:
        qr = QrCodeWidget(url)
        bounds = qr.getBounds()
        width = max(1.0, bounds[2] - bounds[0])
        height = max(1.0, bounds[3] - bounds[1])
        size = 86.0
        drawing = Drawing(size, size, transform=[size / width, 0, 0, size / height, -bounds[0] * size / width, -bounds[1] * size / height])
        drawing.add(qr)
        label = Paragraph(_safe_para(f"Scan to open {title}. Interactive playback is provided by the linked source."), styles["Caption"])
        qr_table = Table([[drawing, label]], colWidths=[1.32 * inch, 4.55 * inch], hAlign="LEFT")
        qr_table.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("BOX", (0, 0), (-1, -1), 0.4, colors.HexColor("#999999")),
            ("PADDING", (0, 0), (-1, -1), 7),
        ]))
        flowables.append(qr_table)
        diagnostics["qr_codes"] += 1
    except Exception as exc:  # pragma: no cover - fallback remains the clickable URL
        diagnostics["media_warnings"].append({"url": url[:500], "error": str(exc)[:300]})

    if transcript:
        excerpt = transcript[:1800] + ("…" if len(transcript) > 1800 else "")
        flowables.extend([Spacer(1, 5), Paragraph("Transcript excerpt", styles["Heading3"]), Paragraph(_safe_para(excerpt), styles["BodyText"])])
    diagnostics["media_fallbacks"] += 1
    return flowables


def _styles(theme: str, grayscale: bool) -> dict[str, ParagraphStyle]:
    base = getSampleStyleSheet()
    accent = colors.HexColor("#111111" if grayscale else "#6d1022")
    body_font = "Times-Roman" if theme in {"academic", "reader"} else "Helvetica"
    return {
        "Title": ParagraphStyle("SCTitle", parent=base["Title"], fontName="Helvetica-Bold", fontSize=28, leading=32, textColor=accent, alignment=TA_CENTER, spaceAfter=18),
        "Subtitle": ParagraphStyle("SCSubtitle", parent=base["Normal"], fontName=body_font, fontSize=14, leading=19, alignment=TA_CENTER, textColor=colors.HexColor("#444444"), spaceAfter=12),
        "Heading1": ParagraphStyle("SCHeading1", parent=base["Heading1"], fontName="Helvetica-Bold", fontSize=19, leading=23, textColor=accent, spaceBefore=10, spaceAfter=8),
        "Heading2": ParagraphStyle("SCHeading2", parent=base["Heading2"], fontName="Helvetica-Bold", fontSize=14, leading=18, textColor=colors.HexColor("#222222"), spaceBefore=8, spaceAfter=5),
        "Heading3": ParagraphStyle("SCHeading3", parent=base["Heading3"], fontName="Helvetica-Bold", fontSize=11.5, leading=15, textColor=colors.HexColor("#333333"), spaceBefore=6, spaceAfter=4),
        "BodyText": ParagraphStyle("SCBody", parent=base["BodyText"], fontName=body_font, fontSize=10.5, leading=15, spaceAfter=4),
        "Small": ParagraphStyle("SCSmall", parent=base["BodyText"], fontName="Helvetica", fontSize=8.5, leading=11, textColor=colors.HexColor("#555555")),
        "Caption": ParagraphStyle("SCCaption", parent=base["BodyText"], fontName="Helvetica-Oblique", fontSize=8.5, leading=11, textColor=colors.HexColor("#555555")),
        "Quote": ParagraphStyle("SCQuote", parent=base["BodyText"], fontName="Times-Italic", fontSize=10.5, leading=15, leftIndent=18, rightIndent=12, borderColor=accent, borderWidth=1, borderPadding=7),
        "Code": ParagraphStyle("SCCode", parent=base["Code"], fontName="Courier", fontSize=7.5, leading=9.5, backColor=colors.HexColor("#f2f2f2"), borderPadding=6),
        "TableHeader": ParagraphStyle("SCTableHeader", parent=base["BodyText"], fontName="Helvetica-Bold", fontSize=7.3, leading=9),
        "TableCell": ParagraphStyle("SCTableCell", parent=base["BodyText"], fontName="Helvetica", fontSize=7.1, leading=8.7),
    }


def render_document_pdf(packet: DocumentJobPacket) -> tuple[bytes, dict[str, Any], dict[str, Any]]:
    started = datetime.now(timezone.utc)
    book = packet.book
    options = packet.options
    styles = _styles(book.theme, options.grayscale)
    diagnostics: dict[str, Any] = {
        "renderer": "reportlab",
        "renderer_version": RENDERER_VERSION,
        "sections_requested": len(packet.sections),
        "sections_rendered": 0,
        "tables": 0,
        "images_embedded": 0,
        "image_warnings": [],
        "equations_detected": 0,
        "matrices_rendered": 0,
        "boards_rendered": 0,
        "annotations_rendered": 0,
        "ink_strokes_rendered": 0,
        "media_fallbacks": 0,
        "qr_codes": 0,
        "media_warnings": [],
        "styles": styles,
    }
    buffer = io.BytesIO()
    page_size = A4 if book.page_size.lower() == "a4" else LETTER
    doc = SimpleDocTemplate(
        buffer,
        pagesize=page_size,
        rightMargin=0.72 * inch,
        leftMargin=0.72 * inch,
        topMargin=0.75 * inch,
        bottomMargin=0.72 * inch,
        title=book.title,
        author=book.editor or "Sustainable Catalyst",
        subject=book.description or "Sustainable Catalyst Library edition",
        creator=f"Sustainable Catalyst Library Renderer {RENDERER_VERSION}",
        pageCompression=1,
    )
    story: list[Any] = []
    story.extend([Spacer(1, 1.2 * inch), Paragraph(_safe_para(book.title), styles["Title"])])
    if book.subtitle:
        story.append(Paragraph(_safe_para(book.subtitle), styles["Subtitle"]))
    if book.editor:
        story.append(Paragraph(_safe_para(f"Compiled or edited by {book.editor}"), styles["Subtitle"]))
    story.append(Paragraph(_safe_para(book.edition), styles["Subtitle"]))
    story.append(Spacer(1, 0.4 * inch))
    story.append(Paragraph(_safe_para(f"Frozen server edition generated {started.strftime('%B %d, %Y at %H:%M UTC')}"), styles["Small"]))
    story.append(PageBreak())

    if book.description:
        story.extend([Paragraph("About this edition", styles["Heading1"]), Paragraph(_safe_para(book.description), styles["BodyText"]), Spacer(1, 8)])
    preface = book.front_matter.get("preface", "")
    introduction = book.front_matter.get("introduction", "")
    if preface:
        story.extend([Paragraph("Preface", styles["Heading1"]), Paragraph(_safe_para(preface), styles["BodyText"]), Spacer(1, 8)])
    if introduction:
        story.extend([Paragraph("Introduction", styles["Heading1"]), Paragraph(_safe_para(introduction), styles["BodyText"]), Spacer(1, 8)])
    if book.description or preface or introduction:
        story.append(PageBreak())

    if options.include_toc:
        story.append(Paragraph("Table of Contents", styles["Heading1"]))
        for section in sorted(packet.sections, key=lambda x: x.position):
            story.append(Paragraph(_safe_para(f"{section.position}. {section.title}"), styles["BodyText"]))
        story.append(PageBreak())

    citations: list[str] = []
    source_urls: list[str] = []
    accessibility_notes: list[str] = []
    for index, section in enumerate(sorted(packet.sections, key=lambda x: x.position), start=1):
        story.append(Paragraph(_safe_para(f"{index}. {section.title}"), styles["Heading1"]))
        rendered = _artifact_flowables(section, styles, diagnostics)
        if not rendered:
            rendered = _story_from_html(section.html, styles, diagnostics)
        if not rendered:
            rendered = [Paragraph("This section did not contain renderable text. Its provenance remains in the edition manifest.", styles["BodyText"])]
        story.extend(rendered)
        media_rendered = _media_flowables(section, styles, diagnostics)
        if media_rendered:
            story.extend(media_rendered)
        if section.source_url:
            source_urls.append(section.source_url)
            story.extend([Spacer(1, 5), Paragraph(f'Source: <link href="{escape(section.source_url)}">{escape(section.source_url)}</link>', styles["Small"])])
        if section.citation:
            citations.append(section.citation)
        if options.include_accessibility_notes and section.alt_text:
            accessibility_notes.append(f"{section.title}: {section.alt_text}")
        diagnostics["sections_rendered"] += 1
        if index < len(packet.sections):
            story.append(PageBreak())

    conclusion = book.back_matter.get("conclusion", "")
    if conclusion:
        story.extend([PageBreak(), Paragraph("Conclusion", styles["Heading1"]), Paragraph(_safe_para(conclusion), styles["BodyText"])])

    if options.include_citations and citations:
        story.extend([PageBreak(), Paragraph("References and Source Notes", styles["Heading1"])])
        for citation in dict.fromkeys(citations):
            story.append(Paragraph(_safe_para(citation), styles["BodyText"]))

    if options.include_accessibility_notes and accessibility_notes:
        story.extend([PageBreak(), Paragraph("Accessibility Notes and Transcriptions", styles["Heading1"])])
        for note in accessibility_notes:
            story.append(Paragraph(_safe_para(note), styles["BodyText"]))

    if options.include_indexes:
        story.extend([PageBreak(), Paragraph("Document Indexes", styles["Heading1"])])
        index_rows = [
            ["Index", "Count"],
            ["Sections", str(diagnostics["sections_rendered"])],
            ["Tables", str(diagnostics["tables"])],
            ["Embedded images", str(diagnostics["images_embedded"])],
            ["Equation-like expressions", str(diagnostics["equations_detected"])],
            ["Translation matrices", str(diagnostics["matrices_rendered"])],
            ["Whiteboards and Chalkboards", str(diagnostics["boards_rendered"])],
            ["Annotated pages", str(diagnostics["annotations_rendered"])],
            ["Vector ink strokes", str(diagnostics["ink_strokes_rendered"])],
            ["Media link fallbacks", str(diagnostics["media_fallbacks"])],
            ["Media QR codes", str(diagnostics["qr_codes"])],
            ["Unique source URLs", str(len(set(source_urls)))],
            ["Citation records", str(len(set(citations)))],
        ]
        table = Table(index_rows, colWidths=[3.4 * inch, 1.2 * inch], hAlign="LEFT")
        table.setStyle(TableStyle([("GRID", (0,0), (-1,-1), .4, colors.grey), ("BACKGROUND", (0,0), (-1,0), colors.HexColor("#ece8df")), ("FONTNAME", (0,0), (-1,0), "Helvetica-Bold"), ("PADDING", (0,0), (-1,-1), 5)]))
        story.append(table)

    manifest = {
        "schema": EDITION_SCHEMA,
        "job_schema": DOCUMENT_JOB_SCHEMA,
        "job_uuid": str(packet.job_uuid),
        "book_id": book.id,
        "title": book.title,
        "subtitle": book.subtitle,
        "editor": book.editor,
        "edition": book.edition,
        "theme": book.theme,
        "page_size": book.page_size,
        "language": options.language,
        "generated_at": started.isoformat(),
        "renderer": "reportlab",
        "renderer_version": RENDERER_VERSION,
        "sections": [
            {
                "position": section.position,
                "type": section.type,
                "title": section.title,
                "source_url": section.source_url,
                "metadata": section.metadata,
            }
            for section in sorted(packet.sections, key=lambda x: x.position)
        ],
        "source_urls": list(dict.fromkeys(source_urls)),
        "accessibility": {
            "transcriptions_included": bool(options.include_accessibility_notes and accessibility_notes),
            "language": options.language,
            "pdf_ua_claimed": False,
            "note": "The renderer includes document metadata and text alternatives but does not claim full PDF/UA conformance.",
        },
    }

    if options.include_manifest:
        story.extend([PageBreak(), Paragraph("Edition Manifest", styles["Heading1"]), Preformatted(json.dumps(manifest, ensure_ascii=False, indent=2)[:100000], styles["Code"])])

    def header_footer(canvas: Any, document: Any) -> None:
        canvas.saveState()
        width, height = page_size
        canvas.setFont("Helvetica", 7.5)
        canvas.setFillColor(colors.HexColor("#555555"))
        canvas.drawString(0.72 * inch, 0.38 * inch, book.title[:90])
        canvas.drawRightString(width - 0.72 * inch, 0.38 * inch, f"Page {document.page}")
        canvas.setStrokeColor(colors.HexColor("#cccccc"))
        canvas.line(0.72 * inch, height - 0.48 * inch, width - 0.72 * inch, height - 0.48 * inch)
        canvas.restoreState()

    doc.build(story, onFirstPage=header_footer, onLaterPages=header_footer)
    pdf = buffer.getvalue()
    if len(pdf) > MAX_PDF_BYTES:
        raise ValueError(f"rendered PDF exceeds {MAX_PDF_BYTES} byte limit")
    output_sha = hashlib.sha256(pdf).hexdigest()
    manifest["output_sha256"] = output_sha
    manifest["output_bytes"] = len(pdf)
    diagnostics.pop("styles", None)
    diagnostics["elapsed_seconds"] = round((datetime.now(timezone.utc) - started).total_seconds(), 3)
    diagnostics["output_bytes"] = len(pdf)
    return pdf, manifest, diagnostics


def create_documents_router(connect: Callable[[], Any], authorize: Callable[..., Any]) -> APIRouter:
    router = APIRouter()

    def response_record(row: dict[str, Any]) -> dict[str, Any]:
        return {
            "schema": DOCUMENT_JOB_SCHEMA,
            "job_uuid": str(row["job_uuid"]),
            "book_id": row["book_id"],
            "title": row["title"],
            "status": row["status"],
            "progress": int(row["progress"]),
            "attempt": int(row["attempt"]),
            "max_attempts": int(row["max_attempts"]),
            "renderer_version": row["renderer_version"],
            "output_sha256": row["output_sha256"],
            "output_bytes": int(row["output_bytes"]),
            "manifest": row["manifest"] or {},
            "diagnostics": row["diagnostics"] or {},
            "error": row["error_message"],
            "created_at": row["created_at"].isoformat(),
            "updated_at": row["updated_at"].isoformat(),
            "completed_at": row["completed_at"].isoformat() if row["completed_at"] else None,
        }

    def process(job_uuid: UUID) -> None:
        try:
            with connect() as conn, conn.cursor(row_factory=dict_row) as cur:
                cur.execute("SELECT * FROM library_document_jobs WHERE job_uuid=%s FOR UPDATE", (job_uuid,))
                row = cur.fetchone()
                if not row or row["status"] == "cancelled":
                    return
                attempt = int(row["attempt"]) + 1
                if attempt > int(row["max_attempts"]):
                    cur.execute("UPDATE library_document_jobs SET status='error', error_message='maximum attempts reached', updated_at=now() WHERE job_uuid=%s", (job_uuid,))
                    conn.commit()
                    return
                cur.execute("UPDATE library_document_jobs SET status='processing', progress=10, attempt=%s, error_message='', updated_at=now() WHERE job_uuid=%s", (attempt, job_uuid))
                conn.commit()
                payload = row["request_payload"]
            packet = DocumentJobPacket.model_validate(payload)
            pdf, manifest, diagnostics = render_document_pdf(packet)
            output_sha = hashlib.sha256(pdf).hexdigest()
            manifest["output_sha256"] = output_sha
            manifest["output_bytes"] = len(pdf)
            with connect() as conn, conn.cursor() as cur:
                cur.execute(
                    """UPDATE library_document_jobs SET status='completed', progress=100, renderer_version=%s,
                       output_pdf=%s, output_sha256=%s, output_bytes=%s, manifest=%s, diagnostics=%s,
                       error_message='', completed_at=now(), updated_at=now() WHERE job_uuid=%s""",
                    (RENDERER_VERSION, pdf, output_sha, len(pdf), Jsonb(manifest), Jsonb(diagnostics), job_uuid),
                )
                conn.commit()
        except Exception as exc:
            diagnostic = {"exception": type(exc).__name__, "traceback": traceback.format_exc(limit=12)[-12000:]}
            try:
                with connect() as conn, conn.cursor() as cur:
                    cur.execute(
                        "UPDATE library_document_jobs SET status='error', progress=0, renderer_version=%s, error_message=%s, diagnostics=%s, updated_at=now() WHERE job_uuid=%s",
                        (RENDERER_VERSION, str(exc)[:4000], Jsonb(diagnostic), job_uuid),
                    )
                    conn.commit()
            except Exception:
                pass

    @router.post("/api/v1/documents/jobs")
    async def create_job(
        request: Request,
        background_tasks: BackgroundTasks,
        authorization: str | None = Header(default=None),
        x_sc_library_signature: str | None = Header(default=None),
        x_sc_library_timestamp: str | None = Header(default=None),
        x_sc_owner: str | None = Header(default=None),
    ) -> dict[str, Any] | JSONResponse:
        body = await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
        if len(body) > MAX_REQUEST_BYTES:
            raise HTTPException(status_code=413, detail="document packet exceeds configured size limit")
        packet = DocumentJobPacket.model_validate_json(body)
        if x_sc_owner != packet.owner_external_id:
            raise HTTPException(status_code=403, detail="owner identity mismatch")
        canonical = json.dumps(packet.model_dump(mode="json"), ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        content_hash = hashlib.sha256(canonical.encode("utf-8")).hexdigest()
        with connect() as conn, conn.cursor(row_factory=dict_row) as cur:
            cur.execute("SELECT * FROM library_document_jobs WHERE job_uuid=%s", (packet.job_uuid,))
            existing = cur.fetchone()
            if existing:
                if existing["owner_external_id"] != packet.owner_external_id:
                    raise HTTPException(status_code=403, detail="job owner mismatch")
                if not constant_time_equal(existing["content_hash"], content_hash):
                    return JSONResponse(status_code=409, content={"detail": "job UUID already exists with different content", **response_record(existing)})
                return response_record(existing)
            cur.execute(
                """INSERT INTO library_document_jobs
                (job_uuid, owner_external_id, job_schema, book_id, title, content_hash, request_payload,
                 status, progress, attempt, max_attempts, renderer_version, manifest, diagnostics)
                VALUES (%s,%s,%s,%s,%s,%s,%s,'queued',0,0,%s,%s,%s,%s) RETURNING *""",
                (packet.job_uuid, packet.owner_external_id, DOCUMENT_JOB_SCHEMA, packet.book.id, packet.book.title,
                 content_hash, Jsonb(packet.model_dump(mode="json")), MAX_ATTEMPTS, RENDERER_VERSION, Jsonb({}), Jsonb({})),
            )
            row = cur.fetchone()
            conn.commit()
        background_tasks.add_task(process, packet.job_uuid)
        return response_record(row)

    @router.get("/api/v1/documents/jobs/{job_uuid}")
    async def get_job(
        job_uuid: UUID,
        request: Request,
        authorization: str | None = Header(default=None),
        x_sc_library_signature: str | None = Header(default=None),
        x_sc_library_timestamp: str | None = Header(default=None),
        x_sc_owner: str | None = Header(default=None),
    ) -> dict[str, Any]:
        await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
        with connect() as conn, conn.cursor(row_factory=dict_row) as cur:
            cur.execute("SELECT * FROM library_document_jobs WHERE job_uuid=%s", (job_uuid,))
            row = cur.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="document job not found")
        if x_sc_owner != row["owner_external_id"]:
            raise HTTPException(status_code=403, detail="job owner mismatch")
        return response_record(row)

    @router.get("/api/v1/documents/jobs/{job_uuid}/download")
    async def download_job(
        job_uuid: UUID,
        request: Request,
        authorization: str | None = Header(default=None),
        x_sc_library_signature: str | None = Header(default=None),
        x_sc_library_timestamp: str | None = Header(default=None),
        x_sc_owner: str | None = Header(default=None),
    ) -> Response:
        await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
        with connect() as conn, conn.cursor(row_factory=dict_row) as cur:
            cur.execute("SELECT owner_external_id, title, status, output_pdf, output_sha256 FROM library_document_jobs WHERE job_uuid=%s", (job_uuid,))
            row = cur.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="document job not found")
        if x_sc_owner != row["owner_external_id"]:
            raise HTTPException(status_code=403, detail="job owner mismatch")
        if row["status"] != "completed" or not row["output_pdf"]:
            raise HTTPException(status_code=409, detail="document output is not ready")
        filename = re.sub(r"[^a-zA-Z0-9._-]+", "-", row["title"]).strip("-")[:120] or "sustainable-catalyst-book"
        return Response(
            content=bytes(row["output_pdf"]),
            media_type="application/pdf",
            headers={
                "Content-Disposition": f'attachment; filename="{filename}.pdf"',
                "X-Content-SHA256": row["output_sha256"],
            },
        )

    @router.post("/api/v1/documents/jobs/{job_uuid}/retry")
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
        with connect() as conn, conn.cursor(row_factory=dict_row) as cur:
            cur.execute("SELECT * FROM library_document_jobs WHERE job_uuid=%s FOR UPDATE", (job_uuid,))
            row = cur.fetchone()
            if not row:
                raise HTTPException(status_code=404, detail="document job not found")
            if x_sc_owner != row["owner_external_id"]:
                raise HTTPException(status_code=403, detail="job owner mismatch")
            if int(row["attempt"]) >= int(row["max_attempts"]):
                raise HTTPException(status_code=409, detail="maximum attempts reached")
            cur.execute("UPDATE library_document_jobs SET status='queued', progress=0, output_pdf=NULL, output_sha256='', output_bytes=0, error_message='', updated_at=now() WHERE job_uuid=%s RETURNING *", (job_uuid,))
            updated = cur.fetchone()
            conn.commit()
        background_tasks.add_task(process, job_uuid)
        return response_record(updated)

    @router.delete("/api/v1/documents/jobs/{job_uuid}", status_code=204)
    async def delete_job(
        job_uuid: UUID,
        request: Request,
        authorization: str | None = Header(default=None),
        x_sc_library_signature: str | None = Header(default=None),
        x_sc_library_timestamp: str | None = Header(default=None),
        x_sc_owner: str | None = Header(default=None),
    ) -> Response:
        await authorize(request, authorization, x_sc_library_signature, x_sc_library_timestamp)
        with connect() as conn, conn.cursor(row_factory=dict_row) as cur:
            cur.execute("SELECT owner_external_id FROM library_document_jobs WHERE job_uuid=%s FOR UPDATE", (job_uuid,))
            row = cur.fetchone()
            if not row:
                raise HTTPException(status_code=404, detail="document job not found")
            if x_sc_owner != row["owner_external_id"]:
                raise HTTPException(status_code=403, detail="job owner mismatch")
            cur.execute("DELETE FROM library_document_jobs WHERE job_uuid=%s", (job_uuid,))
            conn.commit()
        return Response(status_code=204)

    return router
