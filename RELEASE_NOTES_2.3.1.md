# Sustainable Catalyst Library v2.3.1

## Repository Interface and Accessibility Hardening

This patch stabilizes and hardens the v2.3.0 public PDF Document Repository without changing its document records, URLs, families, conversion pipeline, or bulk-import jobs.

## Accessibility and navigation

The repository now provides:

- A keyboard-visible skip link to document results
- Unique IDs for every repository instance, including shortcode instances
- Explicit heading and landmark relationships
- A live result summary with an accessible focus destination
- Fieldset and legend structure around search and filters
- Explicit labels and IDs for every form control
- Help text explaining filter behavior
- Result anchors after filtering and pagination
- `aria-current="page"` on the active result page
- Accessible Previous and Next navigation labels
- Per-document action navigation labeled with the document title
- Screen-reader announcements when Open PDF launches a new tab
- Download size announcements when file size is available

## Featured document paging repair

Featured records are now excluded consistently from the standard result query on every page. They no longer reappear on page two or later.

The result summary includes featured and standard records, and a repository containing only featured records no longer displays a contradictory empty state.

## Mobile and preference support

The public repository adds:

- Minimum 44-pixel interactive targets
- Strong keyboard focus indicators
- More legible mobile metadata
- Full-width mobile document actions
- Compact mobile pagination
- Reduced-motion behavior
- Windows High Contrast and forced-colors support
- Improved print page-break handling

The visual system remains restrained, square-edged, and consistent with the Sustainable Catalyst Knowledge Library.

## Repository performance

Generation-based transient caches now cover:

- Repository document, family, lifecycle, and update metrics
- Document-family indexes
- Publication-year filter values
- Version filter values

Caches invalidate automatically when PDF Documents, Document Families, or Document Types change. The Public Repository administration screen displays the active cache generation and includes a manual **Clear Repository Cache** control.

Search results themselves are not cached, ensuring active queries and filters always reflect the requested URL.
