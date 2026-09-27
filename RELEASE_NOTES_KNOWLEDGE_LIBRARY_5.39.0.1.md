# Release Notes — Knowledge Library v5.39.0.1

**Knowledge Landscape Desktop Layout & Semantic Availability Repair**

This patch addresses two observed Research Library presentation/availability issues: the Publication Corpus Knowledge Landscape was vertically over-expanded on desktop because its grid inherited sidebar content height, while the Semantic Overlay appeared disabled without explaining that the live corpus had no current semantic vectors.

The desktop workspace is now height-bounded with independently scrolling sidebars. Responsive/mobile behavior remains stacked and automatic-height. The Semantic Overlay tab is inspectable, while unavailable similarity relationships remain disabled and are accompanied by an explicit diagnostic message.

Backend v2.50.1 enriches `semantic_analysis` with current, missing and stale embedding counts, job counts, provider/worker readiness, and an explicit availability reason/message. `semantic_readiness()` now distinguishes current embeddings from stale stored embeddings.

No semantic edge is fabricated. Existing v5.39 runtime, reproducibility, corpus, Go and Rust contracts are preserved.
