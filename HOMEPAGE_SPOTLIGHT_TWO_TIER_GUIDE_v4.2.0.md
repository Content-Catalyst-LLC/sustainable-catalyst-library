# Homepage Spotlight Two-Tier Guide
## Sustainable Catalyst Library v4.2.0

## Design contract

The Homepage Spotlight supports broad subject coverage without displaying twelve equal-weight controls in the opening state.

- **Primary tier:** eight topics, visible immediately.
- **Secondary tier:** four additional fields, progressively disclosed inside the same widget.
- **Article depth:** five curated article positions per topic is the recommended setting.
- **Population:** every topic and every article remains administrator selected.

## Rotation behavior

When the secondary tier is closed, automatic rotation and manual sequential navigation cycle only through active primary topics. Direct topic selection remains available after the user opens the additional fields.

When the secondary tier is open, automatic rotation, previous/next controls, and swipe navigation include all active primary and secondary topics.

Closing the secondary tier while a secondary topic is active returns the console to the first available primary topic.

## Administrator workflow

Open **SC Library → Homepage Spotlight**.

Use **Add or align the 12-topic library set** to create missing topic pages and align matching pages to the recommended tiers. This action does not select articles. A topic is not displayed publicly until at least four valid cards are active; five cards produce the intended full page with featured treatment in position 1.

The **Topic tier** field supports:

- **Primary — visible initially**
- **Secondary — additional topics**

Order values are applied within the stable primary-first, secondary-second public grouping.

## Responsive presentation

- Wide desktop: four topic controls per row.
- Tablet and narrow desktop: two controls per row.
- Small mobile: one control per row.
- The secondary field count is hidden on very small screens while the expansion label and plus/minus state remain visible.

## Accessibility

The secondary control exposes its state with `aria-expanded` and references the controlled panel with `aria-controls`. Topic controls retain tab semantics, arrow-key navigation within each tier, focus-visible outlines, hover and focus rotation holds, reduced-motion behavior, and screen-position announcements.

## Shortcode options

```text
[sc_homepage_spotlight]
```

Default behavior uses the two-tier structure and keeps secondary topics closed initially.

```text
[sc_homepage_spotlight secondary_open="true"]
```

Starts with all active topics available to rotation.

```text
[sc_homepage_spotlight secondary_topics="false"]
```

Disables progressive disclosure and shows all topic controls in one navigation group.

```text
[sc_homepage_spotlight secondary_label="Explore more fields"]
```

Changes the expansion label while preserving the automatic “Hide additional topics” expanded-state label.
