# Sustainable Catalyst Knowledge Library v5.39.0.1

## Knowledge Landscape Desktop Layout & Semantic Availability Repair

WordPress: **5.39.0.1**  
Python backend: **2.50.1**  
Go ingestion runtime: **0.1.0**  
Rust graph runtime: **0.2.0**

This focused patch repairs the Publication Corpus Knowledge Landscape desktop geometry and makes semantic-overlay availability explicit. It does not change the v5.39 cross-runtime reproducibility architecture.

### Desktop layout
- At widths above 1100px, the three-column Knowledge Landscape workspace is bounded to `--sc-kl-height` (680px by default).
- Left and right analysis panels scroll independently instead of stretching the graph to the height of all controls.
- The graph stage flexes inside that bounded workspace.
- At 1100px and below, the prior responsive/mobile flow is restored with automatic height and non-scrolling stacked panels.

### Semantic availability
- The Semantic Overlay tab remains inspectable even when similarity edges are unavailable.
- A visible status notice explains why the overlay has no semantic links.
- The corpus response now distinguishes current embeddings, missing current embeddings, stale embeddings, provider configuration, worker state, and embedding-job counts.
- Global semantic readiness distinguishes total stored embeddings from content-hash-current embeddings.
- Semantic similarity is still generated only from real stored embeddings with matching provider/model/dimensions and cosine similarity above the configured threshold. No synthetic edges are introduced.

### Guardrails
Semantic similarity remains analytical. It does not imply truth, causality, evidence quality, support, contradiction, consensus, or Platform Core promotion.
