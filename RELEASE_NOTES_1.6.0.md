# Library v1.6.0 — Annotation Studio and Handwriting

## Release purpose

Add an annotation layer that can be reused in the Research Notebook and later included in custom PDF books without modifying canonical Library content.

## New capabilities

- Handwriting with mouse, touch, Apple Pencil, and compatible styluses
- Pressure-sensitive line capture when the browser provides pointer pressure
- Pen, pencil, highlighter, eraser, rectangle, ellipse, arrow, and typed-note tools
- Independent annotation layers with visibility controls
- Source-aware annotations for Library records and personal research objects
- Movable notes with passage, page, section, figure, equation, or timestamp anchors
- Accessible transcription and private editorial notes
- JSON, SVG, PNG, and print/PDF-ready exports
- Notebook collection membership and full-workspace portability
- Connected-tool handoff support for annotation records

## Data schemas

- Annotation object: `sc-library-annotation/1.0`
- Browser workspace: `sc-library-workspace/1.4`

## Compatibility

Compatible browser-local workspaces from Library v1.2.0 through v1.5.0 are migrated in place using the existing `scLibraryWorkspaceV120` storage key.

## Known boundary

This release produces print/PDF-ready annotation pages through the browser. Full custom-book assembly and server-side PDF generation remain part of the later Book Builder release.
