# Knowledge Library v5.28.0 — Research Retrieval Evaluation & Adaptive Ranking

## Purpose
v5.28.0 makes research retrieval quality measurable and reproducible. It adds deterministic ranking evaluation and bounded adaptive reranking while preserving the Library's source-intelligence role and Platform Core's authority over governed research objects.

## New backend contracts
- `sc-library-retrieval-evaluation/1.0`
- `sc-library-retrieval-benchmark/1.0`
- `sc-library-adaptive-ranking-profile/1.0`
- `sc-library-adaptive-reranking/1.0`

## Evaluation metrics
Per judged query the backend can report precision/recall at available cutoffs, nDCG, mean reciprocal rank, average precision/MAP contribution, evidence coverage, relevant-source diversity, and unsupported-rejection rate when rejection reasons are supplied.

Recall is only treated as measured recall when the caller supplies a known-relevant set (or an explicitly judged relevant universe). Unjudged results are not silently treated as negative judgments.

## Adaptive ranking
A ranking profile is compiled from explicit researcher judgments and retrieval signals. Lexical/semantic weights are bounded to 0.75–1.25. Source and object-type multipliers are bounded to 0.90–1.10. Platform Core binding may add only a small bounded ranking multiplier. Profiles require at least three judged results before they become active.

Reranking preserves every candidate record, original rank, adaptive score components, and new rank. It does not filter sources.

## Integrity boundaries
- relevance acceptance does not mean truth;
- rejection does not mean falsehood;
- unjudged does not mean irrelevant;
- adaptive ranking does not alter claims, findings, evidence relations, or Platform Core objects;
- adaptive ranking never deletes or suppresses source records;
- evidence coverage measures traceability/coverage, not claim validity;
- user/session feedback is not automatically generalized across researchers.

## WordPress surface
The release adds `[sc_library_retrieval_evaluation]`, a compact research console for baseline search, 0–3 relevance judgments, metric inspection, bounded profile creation, and baseline/adaptive comparison. Profiles are local-browser/session scoped by default in the interface.

## Preserved capability chain
v5.28.0 preserves:
- v5.27.0 Source Identity, Deduplication & Entity Resolution;
- v5.26.0 Multimodal Scientific Document Intelligence;
- v5.25.0 Research Graph Query & Evidence Pathfinding;
- v5.24.0 Cross-Publication Evidence Synthesis & Competing Hypotheses;
- Platform Core as durable authority for governed research objects and synthesis.
