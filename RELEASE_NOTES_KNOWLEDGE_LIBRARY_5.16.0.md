# Sustainable Catalyst Knowledge Library v5.16.0

**Release:** Publication Visualization Foundations  
**WordPress:** 5.16.0  
**Python backend:** 2.27.0

## Added

- Library-owned publication visualization registry and immutable specification hashes.
- Citation Network, Concept Map, Finding Map, and Claim Map specification builders.
- Content-hash invalidation/supersession when publication source content changes.
- Human review gate before visualization publication or Platform Core handoff.
- Public read API for reviewed publication visualizations.
- Platform Core `visual-research-object.create` handoff using the existing cross-product visual research contract.
- WordPress Research Library visualization bridge and `[sc_library_publication_visualizations]` renderer with accessible fallback.

## Architecture

Knowledge Library owns source parsing, citation/candidate extraction, visualization-spec composition, review state, and Research Library delivery. Platform Core owns governed visual research objects, provenance, visual reasoning, reproducibility, cross-product composition, and exchange.

No inference of truth, causation, scholarly validity, claim correctness, or citation relationships is introduced by this release.
