# Sustainable Catalyst Library v4.3.22.4 — Publications 14-Field Stack Restoration

## Purpose

Restore the complete Publications field stack that existed before v4.3.13 changed `[sc_field_spotlights]` into a single shared stage. The canonical Publications experience once again renders every major field on the same page while preserving the durable Field Spotlight content model and later visual/interaction refinements.

## Public behavior

- `[sc_field_spotlights]` renders all 14 visible major fields in one continuous stack.
- Every field owns an independent Field Spotlight stage and independent Article Map state.
- A compact field index at the top is jump navigation only; it never hides or replaces another field.
- Each field exposes its first eight Article Map panels initially.
- Panel 9+ remains behind the existing `Explore additional fields` disclosure.
- Article Map hero content, supporting articles, thumbnails, playback controls, progress, and current editorial styling are preserved.
- Direct Article Map links remain server-authoritative and bookmarkable.

## Canonical-page drift protection

Two historical page-body variants are handled:

1. A stale `[sc_field_spotlight field="global-governance"]` on `/publications/` is promoted to the full stack.
2. A stale `[sc_publications]` on `/publications/` is promoted server-side to the full Field Spotlight stack.

Standalone single-field embeds away from the canonical Publications page remain supported.

## Data preservation

This release does not migrate, delete, or rewrite:

- `sc_library_field_spotlights_settings_v434`
- `sc_library_field_spotlight_panel_content_v4312`
- field titles/descriptions/order
- panel order/visibility
- Article Map hero copy or canonical destinations
- supporting article selections
- the 170-map canonical registry
- Citation Studio, Course Finder, Research Access, Research Librarian, or Workspace data

## Validation

The release adds a dedicated 14-field stack contract and retains compatibility tests across Publications, Field Spotlight persistence, Citation Studio, Course Finder, Research Access, and Research Librarian systems.
