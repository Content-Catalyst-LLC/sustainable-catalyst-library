# Sustainable Catalyst Library v5.6.0 R3
## Visible Research Network, University Search & Local Library Access

R3 restores the visible breadth of the pre-v5.6 Research Library while retaining the dynamic Explorer and lazy application mounting introduced in v5.6.0.

## Design rule

Compactness may reduce repetition and initial application weight, but it may not reduce visible research capability. The restored v5.4 page remains the preservation baseline.

## Tier 1 — directly visible

- Knowledge Base / Dynamic Explorer
- Research Access search
- Institutional Research Network
- Public Library Network & Local Access
- Find a Library Near You
- Access Intelligence II
- Research Librarian
- Knowledge Pathways

These are rendered directly on the public Library page rather than existing only as capability cards.

## Research Network Console

`[sc_research_network_console]` builds its directory from the existing Library registries. It distinguishes direct search from catalog gateways, open repositories, scholarly indexes and public-library routes.

Direct/searchable research sources include Internet Archive, MIT Libraries, Harvard Library, University College Dublin, Library of Congress, OpenAlex, Crossref, DataCite, PubMed, PubMed Central, Europe PMC and arXiv.

The broader institutional registry remains visible and searchable, including UC Berkeley, Yale, Princeton, Stanford, Columbia, University of Copenhagen, Stockholm University, Wageningen, Lund, ETH Zürich, Oxford, Cambridge, IIASA, Stockholm Environment Institute and United Nations University.

University College Dublin is explicitly retained as a direct source through Research Repository UCD / DSpace discovery. Licensed UCD Library resources remain subject to UCD authentication and entitlement.

## Public Library Network

The existing registry remains visible, including New York Public Library, Chicago Public Library, St. Louis Public Library, Boston Public Library, Los Angeles Public Library, San Francisco Public Library, Seattle Public Library, Free Library of Philadelphia, Toronto Public Library, Library of Congress and WorldCat.

R3 adds a prominent Find a Library Near You route through the WorldCat Libraries directory and keeps user-connected libraries separate from passwords or entitlement claims.

## Capability visibility

The remaining capability directory uses `display="expanded"`, so Explore, Access, My Research, Evidence, Collaborate and Produce groups are visible together. Heavy applications continue to mount only when opened.

Capabilities already rendered directly on the page are excluded from duplicate Hub emission to prevent duplicate anchors and repeated applications.

## Visual system

R3 replaces beige-heavy density with white, graphite and neutral gray surfaces, red structural accents, a dark research-network console, compact horizontally scrollable source chips, denser two-column institutional/public-library listings, responsive collapse and reduced-motion behavior.

## Backend

No Python backend change is required. Library backend v1.1.0 remains the expected service version. No database migration, Caddy change, DNS change, port change or API-key rotation is part of R3.
