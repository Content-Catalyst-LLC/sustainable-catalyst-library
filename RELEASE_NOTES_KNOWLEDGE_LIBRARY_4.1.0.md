# Sustainable Catalyst Library v4.1.0
## Curated Homepage Spotlight

Release date: August 4, 2026

## Summary

v4.1.0 adds a Knowledge Library-owned homepage discovery console. Administrators define the subject pages, choose every article or announcement, control card positions, and decide whether the public widget rotates. No category or card is selected automatically.

## Main capabilities

### Configurable subject pages

- Create any number of Homepage Spotlight category pages.
- Rename, reorder, enable, disable, or replace categories without editing code.
- Configure each page for four or five card positions.
- Require at least four valid cards before a category page becomes public.
- Use an optional starter set containing:
  - Sustainable Development
  - Planetary Boundaries
  - International Law
  - Biology
  - Systems Thinking

The starter set is a convenience only. Every starter page remains editable and removable.

### Manual content curation

- Select existing published Knowledge Library records.
- Add controlled site-announcement cards.
- Assign every card to an administrator-created category page.
- Set card positions from 1 through 5.
- Add scheduled replacements to the same position without selecting unapproved content.
- Override the homepage label, headline, summary, action text, image behavior, metadata behavior, and destination.
- Keep the canonical Library record unchanged.

### Public console

- Subject tabs expose the configured categories.
- Four-card pages use a compact two-by-two grid.
- Five-card pages use one lead card plus four supporting cards.
- Previous, pause/play, next, and page-position controls follow the Release Console interaction language.
- Autoplay is optional and disabled by default.
- Rotation pauses on hover, keyboard focus, browser-tab hiding, and user request.
- Swipe navigation is supported on touch devices.
- Reduced-motion preferences disable automatic rotation.
- Mobile layouts collapse to one column.

### Editorial safeguards

The public widget contains no automatic path based on:

- recency;
- popularity;
- page views;
- taxonomy membership;
- random selection;
- modified date;
- collection membership; or
- unselected fallback content.

Taxonomies may assist the administrator while searching for a source record, but they never create a category or populate a card.

### Scheduling and validation

- Optional start and end timestamps are evaluated during public rendering.
- Cache expiration respects the nearest scheduling boundary.
- Deleted, unpublished, password-protected, or unavailable sources do not render.
- Invalid cards are not replaced with unselected content.
- A category with fewer than four valid cards remains hidden.
- Empty Spotlight configurations return no public markup.

### Containment

The Spotlight module loads inside a `Throwable` boundary. A missing or broken Spotlight module records a diagnostic state without terminating the existing Research Library or institutional portal.

## Administration

Open:

**SC Library → Homepage Spotlight**

The manager is divided into:

1. Category-page configuration
2. Card selection and scheduling
3. Grouped card-position queues
4. Unassigned-card recovery

## Shortcode

```text
[sc_homepage_spotlight]
```

Recommended homepage placement: directly below the homepage hero.

## Compatibility

- WordPress 6.4 or later
- PHP 8.1 or later
- Existing Knowledge Library, Foundation Document, PDF, Research Library, institutional portal, and public REST surfaces remain intact.
- No database table migration is required.
