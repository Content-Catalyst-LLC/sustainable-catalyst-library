# Sustainable Catalyst Knowledge Library v5.29.0

## Temporal Knowledge & Research Evolution

**WordPress:** 5.29.0  
**Python backend:** 2.40.0

This release makes research time a first-class, provenance-aware dimension. It adds an explicit temporal event ledger, historical-availability snapshots, retrospective status annotation, reproducible change sets, and a public WordPress research console.

### New capabilities

- Explicit temporal event ledger for publication, indexing, versions, corrections, retractions, withdrawals, supersession, dataset updates, supplements, evidence review, and status change.
- `historical-availability` snapshots: only records/events available by the selected cutoff are visible.
- `retrospective-status` snapshots: records available by the cutoff may be annotated with later-known corrections/retractions/withdrawals without pretending those later events were known at the earlier date.
- Snapshot-to-snapshot change sets showing records added/removed and status transitions.
- Dated claim/finding/evidence/hypothesis/scientific-object events only when upstream objects carry explicit dates.
- Corpus integration as `temporal_knowledge_evolution` and a first-class `temporal-evolution` view.
- Backend routes `/v1/temporal-knowledge/analyze` and `/v1/publication-knowledge-maps/temporal-evolution`.
- WordPress REST proxies and `[sc_library_temporal_evolution]`.

### Research integrity boundaries

- A historical snapshot is a record-availability model, not a truth or consensus claim.
- Absence of later evidence does not imply earlier agreement.
- Retractions/corrections do not automatically invalidate every downstream claim.
- Temporal proximity is not causality.
- Record availability does not prove every researcher was aware of the record.
- Platform Core remains the durable authority for governed research/evidence objects and synthesis.

### Compatibility

v5.29 preserves the v5.28 retrieval-evaluation/adaptive-ranking layer, v5.27 source identity, v5.26 scientific-document intelligence, v5.25 research graph/pathfinding, and v5.24 cross-publication synthesis.
