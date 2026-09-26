# Knowledge Library v5.33.0 — Reproducible Literature Review Engine

v5.33.0 turns literature-review workflow state into a reproducible Library object without turning the Library into an automatic evidence arbiter.

## Contracts
- `sc-library-literature-review/1.0`
- `sc-library-review-protocol/1.0`
- `sc-library-review-record-decision/1.0`
- `sc-library-review-extraction/1.0`
- `sc-library-review-snapshot/1.0`
- `sc-library-review-change-set/1.0`

## Capabilities
The engine records research questions, explicit inclusion/exclusion criteria, search strategies and execution dates, screening decisions, reviewer identity when supplied, exclusion reasons, extraction fields, source spans, methodology profiles, deterministic flow counts, and stable protocol/review fingerprints. Review snapshots can be compared without rewriting earlier state.

## Governance boundaries
Screening decisions are explicit inputs. Retrieval rank, source-identity duplicate candidates, methodology profiles, gap/novelty signals, publication recency, or graph structure never become automatic inclusion/exclusion decisions. Inclusion does not mean truth; exclusion does not mean falsehood. Flow counts do not claim PRISMA compliance. No automatic meta-analysis, consensus inference, or Platform Core truth promotion occurs.

## Rust continuity
The corrected v5.32 Rust graph runtime remains part of backend v2.44.0. Rust continues to accelerate graph traversal only; Python owns research semantics and review workflow logic.
