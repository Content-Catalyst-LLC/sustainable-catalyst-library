# Sustainable Catalyst Library v4.1.3
## Knowledge Library Contrast and Thumbnail Repair

## Release objective

Repair missing Homepage Spotlight thumbnails and rebalance the v4.1.2 console so it remains sleek without becoming a second uninterrupted black surface above the Release Console.

## Public presentation

- Retains the compact airport-board structure and automatic 14-second category rotation.
- Limits black to the structural frame, masthead, category strip, and controls.
- Moves article rows to white, cream, and pale gray surfaces.
- Uses red for Knowledge Library identity, selected categories, featured records, and actions.
- Uses green for automatic playback, focus, hover confirmation, and operational status.
- Preserves white and gray typography in the black frame while using dark text in editorial rows.
- Keeps position 1 visually prominent on five-record pages.

## Thumbnail repair

Homepage Spotlight now resolves a public thumbnail through the following ordered sources:

1. WordPress featured image.
2. Known Sustainable Catalyst Library attachment metadata.
3. Generated WordPress PDF preview images.
4. Images attached directly to the source record.
5. WordPress image IDs embedded in article content.
6. The first public image URL embedded in article content.
7. Known Library thumbnail or cover URL metadata.
8. A neutral `KL` Library placeholder.

Additional reliability behavior:

- The first visible category screen loads images eagerly.
- Later category screens retain lazy loading.
- The lead record on the first screen receives high fetch priority.
- Broken or blocked image URLs are replaced at runtime with the neutral Library placeholder.
- New Library cards enable thumbnail display by default.
- Thumbnails remain visible in the responsive mobile layout.
- A filter, `sc_library_spotlight_thumbnail`, can provide a project-specific fallback.

## Editorial guarantees preserved

- Every category remains administrator-created and configurable.
- Every article remains manually selected and positioned.
- No taxonomy, popularity, recency, random, or automatic backfill behavior was added.
- Categories still require at least four valid active positions.
- Existing cards, category order, schedules, destinations, and text overrides remain intact.
- Autoplay, hover pause, keyboard-focus pause, touch handling, reduced-motion behavior, and manual controls remain intact.

## Shortcode

Existing homepage markup remains valid:

```text
[sc_homepage_spotlight]
```

Optional overrides remain available:

```text
[sc_homepage_spotlight autoplay="true" interval="14000" show_thumbnail="true"]
```

## Upgrade notes

1. Install the v4.1.3 WordPress ZIP and choose **Replace current with uploaded**.
2. Purge WordPress, page-builder, hosting, CDN, and browser caches.
3. Hard-refresh the homepage.
4. Confirm that Library cards have **Show resolved thumbnail or Library placeholder** enabled when per-card behavior is being respected.
5. Use `show_thumbnail="true"` in the shortcode when every selected Library card should display a thumbnail regardless of its saved card preference.

No Library index rebuild is required solely for this release.
