# v5.6.1 — Homepage Research Network & Knowledge Discovery Console

## Purpose

v5.6.1 gives the Sustainable Catalyst Research Library a reusable homepage presentation component without creating a second homepage-specific plugin or a second source registry.

Primary shortcode:

```text
[sc_library_homepage_console mode="full"]
```

Additional modes:

```text
[sc_library_homepage_console mode="compact"]
[sc_library_homepage_console mode="network"]
```

## Public presentation

The full console exposes:

- Library identity and a compact research-oriented introduction;
- live public corpus telemetry loaded from the existing Dynamic Explorer bootstrap;
- a bounded, slow-moving Research Network source directory;
- visible signals for Research Librarian, public-library routes, Open Courses, and provenance;
- one research input with handoffs into Knowledge search, Research Access, or the Research Librarian;
- direct actions for the Research Library, Research Network, public-library discovery, and Research Librarian.

## Source-of-truth architecture

The homepage console does **not** maintain its own university/library/scholarly-source names.

`SC_Library_Research_Network_Console::source_registry()` exposes the governed Research Network registry already used by the public Research Library. The homepage console chooses a bounded presentation order by source ID while names, capability types, access modes, and descriptions remain owned by the existing registries.

Featured homepage rotation includes routes such as MIT Libraries, Harvard Library, University College Dublin, Yale University Library, Princeton University Library, Stanford University Libraries, New York Public Library, Library of Congress, Internet Archive, OpenAlex, Crossref, Europe PMC, arXiv, and WorldCat.

Capability labels remain explicit. A gateway is not represented as a direct connector, and discovery is not represented as proof of entitlement.

## Live telemetry and fail-open behavior

The browser requests the existing WordPress REST Explorer bootstrap:

```text
/wp-json/sc-library/v1/explorer/bootstrap
```

That endpoint prefers the Python/PostgreSQL backend and already falls back to the WordPress public read model if the backend is unavailable.

The homepage console therefore does not call the Contabo service directly from the browser and does not expose an error-heavy backend diagnostic surface on the homepage. If live telemetry cannot be loaded, research navigation and the complete source directory remain available.

## Query handoffs

Homepage searches use existing Library destinations:

- Knowledge → `library_q` → `#knowledge-explorer`
- Research Network → `research_query` → `#research-access`
- Research Librarian → `librarian_query` → `#research-front-door`

Research Access can accept the handed-off query and begin the existing integrated search. The Research Librarian preloads the homepage question for user review but does not automatically apply a workspace action.

## Motion and accessibility

The source directory:

- scrolls slowly within a bounded viewport;
- pauses on pointer hover and keyboard focus;
- disables automatic motion under `prefers-reduced-motion`;
- remains fully usable without motion;
- uses defensive scoped control styling so site-wide Astra/button rules cannot erase important actions.

## Research Library page baseline

v5.6.1 does **not** reintroduce Three Research Front Doors.

The current Research Library page baseline remains **v5.6.0 R3.2.1**, with that redundant section removed. Installing v5.6.1 does not require replacing the Research Library page body.
