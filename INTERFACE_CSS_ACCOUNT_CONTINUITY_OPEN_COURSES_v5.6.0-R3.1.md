# v5.6.0 R3.1 Architecture

## Design rule
R3.1 repairs presentation without reducing visible Library substance. The R3 research network and access architecture remain the source of truth.

## Front-door CSS boundary
The public Research Library embeds real Research Access and Research Librarian applications. Equal-width one-third cards were too narrow for those applications. R3.1 uses an unequal three-column desktop grid, a two-column intermediate layout, and a one-column mobile layout. Research Access is forced to a one-column query/button layout inside its featured card so its controls cannot collapse under global CSS.

All repair selectors are scoped below `.cc-research-library-brand.cc-rl-v560r3` or `.sc-library-capability-hub`; the global Sustainable Catalyst stylesheet is not changed.

## Capability actions
Capability cards continue to use real `<button>` elements with `data-open-capability`. R3.1 makes them visually explicit with a minimum target size, border, foreground/background contrast, hover/focus state, and active state. The existing JavaScript and same-origin bounded workspace behavior remain unchanged.

## Account continuity
The canonical shared-account contract remains WordPress-owned. The shortcode now presents a concise status layer by default and places detailed governance in a native disclosure. The detailed explanation remains in the DOM and can be opened by the user; no privacy or account contracts are removed.

## Featured Open Courses
The existing Open Course Finder remains the canonical course-discovery system. `mode="featured"` changes presentation only:
1. six cross-institution courses are promoted to the first view;
2. initial unfiltered display is limited to the configured featured limit;
3. any query/filter searches the entire launch catalog;
4. users can explicitly expand all launch-catalog courses;
5. provider gateways remain available for wider discovery.

Featured order: MIT, Harvard, Yale, Princeton, Stanford, University of Copenhagen.

## Compatibility
The visible Research Flow section is removed, but its two public anchors remain as zero-size compatibility anchors at the Open Courses transition point. The original Open Course Finder anchor becomes a direct page section and is excluded from duplicate Capability Hub emission.
