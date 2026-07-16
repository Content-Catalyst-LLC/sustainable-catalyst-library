---
document_id: "SC-FND-011"
slug: "public-data-indicators-source-methodology-standard"
title: "Public Data, Indicators, and Source Methodology Standard"
subtitle: "Requirements for connectors, indicators, maps, comparisons, and live evidence"
record_type: "institutional-standard"
authority_level: "methodology"
status: "under-review"
version: "1.0.0"
effective_date: null
last_reviewed: "2026-07-16"
review_cycle: "Annual"
owner: "Sustainable Catalyst, stewarded by Content Catalyst LLC"
canonical_record: "living-html"
---

# Public Data, Indicators, and Source Methodology Standard

*Requirements for connectors, indicators, maps, comparisons, and live evidence*

> **Status:** Under Review. This first-edition record is complete for institutional review but does not become current authority until approved and published.

## 1. Purpose

This standard governs public data, indicators, source connectors, geospatial layers, monitored events, comparisons, forecasts, and exported intelligence records.

## 2. Source registry

Each connector or manually maintained source should identify publisher, dataset or endpoint, access method, coverage, update pattern, terms, known limits, transformation method, and operational health.

## 3. Publisher and data authority

A source publisher may be authoritative for its own reported data while still using methods that require scrutiny. Official, academic, intergovernmental, commercial, civil-society, and community sources should be evaluated according to purpose and methodology rather than a single prestige ranking.

## 4. Indicator definition

An indicator should identify concept, numerator and denominator where applicable, unit, population, geography, period, frequency, method, revisions, and interpretation limits. Labels must not hide methodological differences.

## 5. Time

Observation period, release date, retrieval date, revision date, and forecast horizon are different fields. Dashboards should not present old observations as current merely because the connector is online.

## 6. Geography

Records should identify geographic level, boundary version, coordinate reference system where relevant, coverage gaps, aggregation method, and treatment of disputed or changing boundaries. Maps are analytical representations, not neutral territory.

## 7. Harmonization

Cross-source harmonization should record mappings, conversions, deflators, rebasing, interpolation, imputation, boundary reconciliation, and category changes. Harmonized series should retain the original source values or references.

## 8. Missing and unavailable data

Missing, suppressed, not applicable, zero, not reported, delayed, and connector failure are distinct states. Interfaces must not replace missing values with zero or carry forward stale values without disclosure.

## 9. Revisions

Public datasets may revise historical values. The system should preserve retrieval or release version where feasible and identify when a current chart differs from an earlier export because the source changed.

## 10. Comparisons and rankings

Rankings should be used cautiously. Comparable definitions, years, populations, and methods are required. Small differences should not be overinterpreted when uncertainty or revision exceeds the gap.

## 11. Composite indices

Composite measures should disclose component selection, weighting, normalization, aggregation, missing-data rules, directionality, and sensitivity. A composite score should not conceal distributional or domain-specific tradeoffs.

## 12. Live events and monitoring

Event records may be incomplete, rapidly changing, duplicated, or contested. Live interfaces should show timestamp, source, verification state, and update behavior. Alerts should describe a threshold or condition rather than imply confirmed causation.

## 13. Earth observation and remote sensing

Satellite, radar, model, and derived geospatial products require attention to spatial resolution, temporal resolution, cloud or sensor limits, classification uncertainty, ground truth, and the distinction between observation and inference.

## 14. Forecasts and early warning

Forecasts should identify model, issue date, horizon, scenario or probability interpretation, training and validation context, and known failure modes. Early-warning thresholds should be reviewable and should not be presented as certainty.

## 15. Source health and fallback

Connector health should distinguish online, delayed, stale, schema-changed, rate-limited, partially available, and unavailable states. Fallback sources should be identified; cached values should retain their timestamp.

## 16. Exports and citations

Exports should include source names, URLs or identifiers, retrieval time, indicator definitions, transformations, geography, period, method, and limitations sufficient to reconstruct the displayed result.

## 17. Privacy and harm

Public availability does not eliminate privacy, security, dignity, or harm concerns. Sensitive locations, vulnerable populations, personal data, and conflict or humanitarian information may require aggregation, delay, restriction, or nonpublication.

## 18. Review

A visualization is not validated merely because it renders. Source methodology, transformations, and interpretive claims require review proportionate to consequence.

## Related Foundation Documents

- SC-FND-003
- SC-FND-005
- SC-FND-010

## Revision History

| Version | Date | Status | Summary |
|---|---|---|---|
| 1.0.0 | 2026-07-16 | Under Review | Institutional Foundations First Edition draft prepared for review and publication. |

## Authority Statement

This living HTML document is the proposed first-edition record within its defined scope. Fixed PDF editions preserve review snapshots. Earlier records remain available for historical reference.
