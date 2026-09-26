# Sustainable Catalyst Knowledge Library 5.36.0.1

**Release:** Go Research Ingestion & Job Fabric  
**Backend:** 2.47.1  
**Go runtime:** 0.1.0  
**Rust graph runtime:** 0.2.0

v5.36.0.1 introduces a dedicated Go job coordinator for research ingestion and document-processing workloads. It adds durable queued jobs, idempotent submission, priority, claim/ownership, retries, cancellation, completion, backpressure and worker-health contracts.

Python remains the research-intelligence and public API layer. Rust remains the native evidence-graph compute layer. Go coordinates execution only and cannot determine source validity, evidence truth, research quality, inclusion decisions, or Platform Core promotion.
