from uuid import uuid4

from app.documents import DocumentJobPacket, render_document_pdf


def sample_packet() -> DocumentJobPacket:
    return DocumentJobPacket.model_validate({
        "schema": "sc-library-document-job/1.0",
        "job_uuid": str(uuid4()),
        "owner_external_id": "wordpress:1",
        "book": {
            "schema": "sc-library-book/1.0",
            "id": "book_test",
            "title": "Systems Research Reader",
            "subtitle": "A server-rendered test edition",
            "editor": "Sustainable Catalyst",
            "edition": "Test edition",
            "description": "A deterministic rendering test.",
            "theme": "institutional",
            "page_size": "letter",
            "front_matter": {"preface": "This is a test preface.", "introduction": "This is a test introduction."},
            "back_matter": {"conclusion": "This is a test conclusion."},
        },
        "sections": [
            {
                "position": 1,
                "type": "record",
                "title": "Feedback and Accumulation",
                "html": "<section><h2>Feedback and Accumulation</h2><p>A stock changes through inflows and outflows.</p><pre>S_next = S + I - O</pre><table><tr><th>Variable</th><th>Meaning</th></tr><tr><td>S</td><td>Stock</td></tr></table></section>",
                "source_url": "https://sustainablecatalyst.com/example/",
                "citation": "Sustainable Catalyst. Feedback and Accumulation.",
                "alt_text": "Equation and variable table.",
                "metadata": {"reference_id": "1"},
            },
            {
                "position": 2,
                "type": "matrix",
                "title": "Technical Translation Matrix",
                "html": "<section><p>Structured matrix fallback.</p></section>",
                "metadata": {
                    "artifact": {
                        "title": "Accumulation Translation",
                        "columns": [
                            {"id": "plain", "label": "Plain language"},
                            {"id": "formal", "label": "Formal form"},
                        ],
                        "rows": [
                            {"id": "concept", "label": "Concept", "cells": {
                                "plain": {"value": "A stock changes through inflows and outflows.", "status": "reviewed", "sourceRef": "Library record 1"},
                                "formal": {"value": "S_next = S + I - O", "status": "validated", "sourceRef": "Workbench"},
                            }},
                        ],
                    },
                },
            },
            {
                "position": 3,
                "type": "board",
                "title": "Systems Whiteboard",
                "html": "<section><p>Structured board fallback.</p></section>",
                "metadata": {
                    "artifact": {
                        "type": "whiteboard", "width": 1800, "height": 1200,
                        "nodes": [
                            {"id": "a", "type": "concept", "title": "Stock", "body": "Accumulated state", "x": 120, "y": 180, "width": 280, "height": 160, "color": "#fff7d6"},
                            {"id": "b", "type": "result", "title": "Change", "body": "Inflow minus outflow", "x": 900, "y": 600, "width": 320, "height": 170, "color": "#e2f5df"},
                        ],
                        "edges": [{"id": "e1", "from": "a", "to": "b", "label": "changes through"}],
                        "strokes": [{"id": "s1", "d": "M 100 100 L 500 280 L 800 220", "color": "#721019", "width": 5, "opacity": 0.9}],
                    },
                },
            },
            {
                "position": 4,
                "type": "annotation",
                "title": "Annotated Research Page",
                "html": "<section><p>Annotation fallback.</p></section>",
                "alt_text": "Handwritten note about model assumptions.",
                "metadata": {
                    "artifact": {
                        "schema": "sc-library-annotation/1.0", "pageWidth": 900, "pageHeight": 1120,
                        "strokes": [{"id": "ink1", "kind": "pen", "color": "#721019", "width": 4, "opacity": 1, "points": [
                            {"x": 0.1, "y": 0.2, "p": 0.5}, {"x": 0.3, "y": 0.25, "p": 0.7}, {"x": 0.55, "y": 0.2, "p": 0.5}
                        ]}],
                        "shapes": [{"id": "shape1", "type": "rectangle", "x1": 0.12, "y1": 0.3, "x2": 0.65, "y2": 0.48, "color": "#721019", "width": 3, "opacity": 1}],
                        "notes": [{"id": "note1", "text": "Review the boundary assumption", "x": 0.18, "y": 0.55}],
                        "transcription": "Review the boundary assumption before interpreting the model.",
                    },
                },
            },
            {
                "position": 5,
                "type": "source",
                "title": "Recorded systems briefing",
                "html": "<section><p>A recorded explanation connected to this edition.</p></section>",
                "source_url": "https://sustainablecatalyst.com/channel/example/",
                "citation": "Sustainable Catalyst. Recorded systems briefing.",
                "alt_text": "Recorded briefing with transcript excerpt and QR access.",
                "metadata": {
                    "media": {
                        "title": "Recorded systems briefing",
                        "url": "https://sustainablecatalyst.com/channel/example/",
                        "description": "A non-destructive linked media excerpt.",
                        "selected_segment": "00:02:10–00:03:05",
                        "transcript": "This excerpt explains why stocks and flows must be interpreted together.",
                    }
                },
            },
        ],
        "options": {
            "include_toc": True,
            "include_manifest": True,
            "include_citations": True,
            "include_indexes": True,
            "include_accessibility_notes": True,
            "grayscale": False,
            "language": "en-US",
        },
    })


def test_render_document_pdf():
    pdf, manifest, diagnostics = render_document_pdf(sample_packet())
    assert pdf.startswith(b"%PDF-")
    assert len(pdf) > 2000
    assert manifest["schema"] == "sc-library-edition/1.0"
    assert manifest["title"] == "Systems Research Reader"
    assert len(manifest["sections"]) == 5
    assert diagnostics["sections_rendered"] == 5
    assert diagnostics["tables"] == 2
    assert diagnostics["matrices_rendered"] == 1
    assert diagnostics["boards_rendered"] == 1
    assert diagnostics["annotations_rendered"] == 1
    assert diagnostics["ink_strokes_rendered"] >= 2
    assert diagnostics["media_fallbacks"] == 1
    assert diagnostics["qr_codes"] == 1
