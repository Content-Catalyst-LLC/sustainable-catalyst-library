# Release Notes — Energy Systems Intelligence v0.3.0

**Release:** Energy Sustainability Indicators  
**Library:** v5.11.0 (unchanged)  
**Carbon & Nature:** v0.5.0 (preserved)  
**Library backend:** v2.9.0

## Added

- 30 governed EISD indicator definitions from the indicator table represented in the supplied Vera & Langlois (2007) source.
- 3 dimensions, 7 themes, and 19 subthemes.
- Indicator filters by text, dimension, theme, and subtheme.
- Individual indicator detail with provenance-first observation contract.
- JSON Schemas for indicator definitions and observations.
- Machine-readable 30-record registry export.
- Sustainability Indicators WordPress explorer.
- Four new GET-only backend routes and matching WordPress proxy routes.
- Three additional methodology rules focused on indicator-definition, methodology-sheet, and comparison boundaries.

## Preserved

- 75 governed energy concepts and 63 typed relationships.
- Carbon & Nature v0.5.0 integration boundaries.
- v0.2.0 unit, conversion, direct-carbon, and gross heat-content registries and calculators.
- Library v5.11.0 application line.

## Explicit non-capabilities

- No official EISD formula implementation.
- No current country indicator ingestion.
- No sustainability composite score.
- No country, technology, or policy ranking.
- No scenario modeling.
- No automated policy recommendation.

The source article states that exact indicator construction is described in separate methodology sheets; those sheets were not supplied, so v0.3.0 does not reconstruct them from general knowledge.
