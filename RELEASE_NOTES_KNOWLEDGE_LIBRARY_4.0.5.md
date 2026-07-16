# Release Notes — Sustainable Catalyst Library v4.0.5

## Fixed

- Corrected the invalid Connected Research Environment project-post-type
  constant used by Public API / Export / Federation.
- Updated federation catalog and export scope mappings to use the canonical
  Citation Source Manager project post type.
- Added a backward-compatible project-post-type alias.
- Added complete public institutional portal fatal containment.
- Added a protected, direct-query fallback catalog.

## Result

The public Research Library no longer returns WordPress's critical-error screen
when an institutional record serializer throws an Error or Exception.
