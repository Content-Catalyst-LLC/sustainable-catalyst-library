# Sustainable Catalyst Library v5.6.0 R2
## Featured Library Access, Research Librarian & Interface Density

R2 improves the capability-preserving R1 interface without removing or replacing the restored Research Library architecture.

### Primary changes

1. **Three primary research front doors** now sit immediately below the hero:
   - Knowledge Base
   - Library Access
   - Research Librarian
2. **Library Access is functional at the front door**, using the existing connector/search stack in a compact mode. It searches a bounded set of public library, university-repository, archive and scholarly providers and links into full Research Access, My Libraries and Access Intelligence.
3. **Research Librarian is directly usable near the top of the page** through the existing front-door orchestrator, rather than being represented only as a capability card.
4. **Capability density is reduced without deletion.** Only one of the six capability groups is visible at a time. The other groups remain in the DOM and are accessible by tabs and preserved deep links.
5. **Heavy applications no longer expand the public page indefinitely.** The on-demand capability workspace has a bounded viewport and its same-origin iframe scrolls internally.

### Preservation boundary

The restored v5.4 page remains the preservation baseline. R2 continues to protect all 37 unique baseline shortcodes and 72 baseline anchors through the public page and the capability registry.

### Backend

Python backend remains **v1.1.0**. No database migration, Caddy change, DNS change, port change, credential change or backend redeployment is required when v1.1.0 is already live.
