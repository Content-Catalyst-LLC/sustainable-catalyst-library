# Checkpointed Ingestion & Research Pipelines

Knowledge Library v5.52.0 adds a PostgreSQL-authoritative pipeline layer on top of the existing durable research-job fabric.

## Principle

The pipeline engine is **not a second scheduler**. A pipeline definition describes a DAG of research stages. When a stage becomes ready, the engine compiles it into the same durable research-job object introduced in v5.49.0. The specialized worker runtime introduced in v5.50.0 leases and executes those jobs, and v5.51.0 artifact identities can be attached to stage checkpoints.

## Durable objects

- `library_research_pipeline_definitions` — normalized DAG definitions and fingerprints.
- `library_research_pipeline_runs` — one idempotent execution of a definition and input manifest.
- `library_research_pipeline_stage_runs` — stage state, linked research job, dependencies, checkpoint, output artifact IDs, and failures.
- `library_research_pipeline_events` — append-oriented pipeline/stage lineage.

## Resume behavior

Completed stages are reused. Resume does not silently rerun a completed checkpoint. Failed or blocked stages are re-queued only when their dependencies are complete or explicitly skipped. Dependency checkpoints are injected into the resumed stage input so downstream work can use the exact earlier outputs.

## Example

```text
acquire
  ↓
checksum / preserve
  ↓
parse
  ↓
OCR / language processing
  ↓
segment / corpus construction
  ↓
entity / citation extraction
  ↓
embed / index
  ↓
graph link
  ↓
Core handoff
```

If `embed` fails, acquisition, preservation, parsing, OCR, segmentation, and extraction remain complete checkpoints. Resume starts from the failed stage rather than repeating the entire pipeline.

## Governance

Pipeline completion records execution completion only. It does not establish source validity, evidence truth, scholarly consensus, model correctness, or automatic Platform Core promotion.
