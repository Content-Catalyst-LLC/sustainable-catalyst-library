# Sustainable Catalyst Knowledge Library v5.30.0

## Evidence Quality & Methodology Intelligence

**WordPress:** 5.30.0  
**Python backend:** 2.41.0

### New capabilities

- First-class source-grounded methodology profiles.
- Explicit study-design family normalization without evidence hierarchy or quality ranking.
- Structured population, sample, geography, time period, intervention/exposure, comparator, outcome, variable, method, uncertainty, limitation, disclosure and reproducibility fields.
- Method-reporting coverage diagnostics separated from scientific quality.
- Appraisal-readiness domains that preserve human-review requirements.
- Descriptive cross-study methodology comparison.
- Methodology profile nodes and `describes-methodology` graph relations.
- Methodology relations are queryable but excluded from default evidence-path traversal.
- Backend routes `/v1/methodology-intelligence/analyze` and `/v1/publication-knowledge-maps/methodology-intelligence`.
- WordPress REST proxies and `[sc_library_methodology_intelligence]`.

### Research integrity boundaries

- No automatic universal quality score.
- No automatic formal risk-of-bias verdict.
- No automatic causal-validity judgment.
- No truth promotion from study design, sample size, reporting completeness, funding disclosure, data/code availability, or replication metadata.
- Missing supported metadata is treated as **not explicitly reported in the structured record**, not as proof a method was absent.
- Platform Core remains the durable governed research-object and synthesis authority.

### Compatibility

v5.30.0 preserves v5.29 temporal research evolution, v5.28 retrieval evaluation/adaptive ranking, v5.27 source identity, v5.26 scientific-document intelligence, v5.25 graph/pathfinding, and v5.24 cross-publication synthesis.
