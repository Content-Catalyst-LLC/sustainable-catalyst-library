# Knowledge Library v5.31.0 — Research Gap & Novelty Discovery

## Purpose
v5.31.0 adds a deterministic, corpus-scoped discovery layer for finding research questions worth investigating. It combines explicit research-graph relations, methodology profiles, publication topics, and publication chronology to generate reviewable candidate gap and novelty objects.

## Candidate gap classes
- indexed evidence-linkage gap
- competing-evidence review priority
- methodology-diversity gap
- geography-reporting gap
- population-reporting gap
- corpus-relative temporal coverage gap

## Novelty-discovery leads
- rare topic combinations in the analyzed corpus
- recent emergence within the analyzed corpus

## Integrity boundaries
A gap signal is not proof of a global literature gap. A novelty candidate is not a scholarly novelty claim. Low frequency is not the same as being unstudied. Missing structured methodology metadata does not prove a method or population was absent. Temporal lag does not prove research stopped. External literature search and human scholarly assessment are required before asserting novelty.

## Graph integration
Gap and novelty objects are analytical graph nodes. Their `research-gap-signal` edges are review-required, non-truth-asserting, non-causal, and excluded from default evidence-path traversal.

## Product boundary
The Knowledge Library owns discovery over the indexed corpus. Platform Core remains the durable authority for governed research objects, claims, evidence, synthesis, and cross-product exchange.
