# Sustainable Catalyst Library v4.2.0
## Twelve-Topic Two-Tier Homepage Spotlight

### Purpose

Expand the Knowledge Library Homepage Spotlight from the original five-subject starter structure to a broader twelve-topic editorial surface without turning the opening panel into a dense directory.

### Recommended topic structure

**Primary tier — visible initially**

1. Sustainable Development
2. Planetary Boundaries
3. International Law
4. Biology
5. Systems Thinking
6. Economics
7. Artificial Intelligence
8. Physics

**Secondary tier — available through “Explore additional topics”**

9. Embedded & Edge Systems
10. Psychology
11. Decision Science
12. Data Systems & Analytics

### Public experience

- Presents the eight primary topic controls in a compact four-column desktop grid.
- Places the four additional fields in a secondary tier within the same Spotlight widget.
- Keeps the secondary tier collapsed on initial load by default.
- Uses an accessible button with `aria-expanded`, `aria-controls`, keyboard focus treatment, and a visible field count.
- Restricts automatic rotation, next/previous controls, and swipe navigation to primary topics while the secondary tier is closed.
- Includes all twelve topics in navigation once the secondary tier is opened.
- Preserves the existing five-article console page, featured first article, thumbnails, metadata, summaries, action links, progress indicator, reduced-motion behavior, and dismissible announcements.

### Editorial administration

- Adds a **Topic tier** selector to each Spotlight topic page.
- Adds Primary and Secondary tier indicators to the topic table and card queues.
- Replaces the five-page starter action with **Add or align the 12-topic library set**.
- Creates only missing topic pages and does not duplicate existing pages with matching names.
- Aligns matching existing pages to the recommended tier and five-card limit without replacing their selected articles, descriptions, enabled state, or ordering.
- Continues to hide any topic publicly until it contains at least four valid manually selected cards.

### Public shortcode

The existing shortcode remains valid:

```text
[sc_homepage_spotlight]
```

New optional controls:

```text
[sc_homepage_spotlight secondary_topics="true" secondary_open="false" secondary_label="Explore additional topics"]
```

- `secondary_topics="false"` presents every active topic in one visible navigation tier.
- `secondary_open="true"` opens the secondary tier on initial load and permits rotation across all active topics immediately.
- `secondary_label` changes the closed-tier button label.

### Data and compatibility

- Existing Spotlight pages with no tier metadata are treated as **Primary**.
- Existing article assignments and schedules do not require migration or re-entry.
- No taxonomy-driven population, latest-content fallback, popularity ordering, random selection, or automatic backfill was introduced.
- The v4.1.1 source-search repair, v4.1.2 console presentation, v4.1.3 thumbnail resolution, and v4.1.4 progress-indicator contracts remain intact.

### Upgrade procedure

1. Install the v4.2.0 WordPress plugin ZIP.
2. Open **SC Library → Homepage Spotlight**.
3. Select **Add or align the 12-topic library set**.
4. Curate five valid articles for each newly created topic.
5. Confirm the intended Primary or Secondary tier and order for every topic.
6. Purge WordPress, page-builder, hosting, CDN, and browser caches.
7. Verify desktop, keyboard, reduced-motion, and mobile behavior on the homepage.
