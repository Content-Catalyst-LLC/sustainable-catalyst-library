# Library v1.14.1 — Public Record Card Layout and Responsive Rendering Repair

## Purpose

Library v1.14.1 repairs the public Knowledge Records layout regression in which the action column consumed nearly the full card width and forced titles and excerpts into a one-character-wide column.

## Root cause

The v1.14.0 card used `grid-template-columns: minmax(0, 1fr) auto`. The second automatic column contained an expanding, non-wrapping action row. As Notebook, Matrix, Whiteboard, Chalkboard, Annotation, Book, and record-detail actions accumulated, the automatic column became wider than the card and compressed the content column to almost zero.

## Repairs

- Public cards now use one full-width grid column with explicit metadata, body, and footer regions.
- Resource badges and actions occupy a separate wrapping footer row.
- Titles and excerpts receive defensive horizontal writing-mode, word-breaking, sizing, and overflow rules.
- Excerpts are visually limited to five lines on screen while remaining fully available in the DOM.
- Tablet action rows wrap beneath the record body.
- Mobile action controls use a compact two-column layout and collapse to one column on narrow screens.
- Print output hides interactive buttons, expands the full excerpt, and prevents one-character wrapping.
- The renderer now emits semantic `sc-library-record__excerpt` and responsive-card hooks.

## Compatibility

No database, index, workspace, media, document, or PostgreSQL schema changes are required. Existing Library records and index data are preserved.
