# Library v6.8.0.1 — Living Research Readiness Repair

This repair corrects the v6.7 Living Collections & Research Projects readiness composition used by Library v6.8.0.

Advanced Semantic & Cross-Language Discovery may intentionally report `state=degraded` while `ready=true` when multilingual embeddings are not configured and deterministic lexical/hybrid fallback remains available. Living Research previously treated any Discovery state other than literal `ready` as blocked.

The repair now honors the dependency's explicit `ready` flag and propagates `degraded` state without marking the service unusable. No schema, persistence, API route, Web, SDK, or WordPress generation changes are introduced.
