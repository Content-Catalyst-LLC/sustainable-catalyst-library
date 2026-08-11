# Sustainable Catalyst Library v4.3.20.1
## Knowledge Pathway Alignment & Layout Repair

### Purpose
Repairs the visible alignment regression in the Research Library's **Knowledge Pathways / Explore by Question or Field** section introduced by inherited legacy layout rules.

### Changes
- Adds the version-scoped `cc-rl-v43201` page marker.
- Adds `assets/css/sc-library-research-library-page.css` as a dedicated Research Library page override.
- Forces the eight question-based pathway cards into a stable two-column desktop/tablet grid.
- Collapses the pathway grid to one column at 760px and below.
- Removes inherited staggering, translation, width, margin, and positional offsets from pathway cards.
- Forces the Knowledge Pathways heading, intro, and grid onto the same left/right rails.
- Preserves the Core Library cards below the question pathways as a separate layout.

### Scope
This is a layout-only patch. It does not modify:
- Open Course Finder data or access labels
- Research Access connectors
- My Libraries / Research Libraries
- Digital Access Resolver
- Research Librarian orchestration
- Workspace
- Publications or Field Spotlight data/recovery

### Deployment
Upload `sustainable-catalyst-library-v4.3.20.1.zip` in WordPress, then replace the Research Library page body with `RESEARCH_LIBRARY_PAGE_v4.3.20.1.html` and clear page/browser caches.

The Publications page does not require replacement.
