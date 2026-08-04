# Sustainable Catalyst Library v4.1.2

## Knowledge Library Console Presentation Refresh

Released: August 4, 2026

## Summary

v4.1.2 converts Homepage Spotlight from a conventional white article-card grid into a compact, automatically rotating Knowledge Library Console. The refresh is designed to sit directly above the Release Console and Live Intelligence surface without visually duplicating either one.

## Public presentation

- Black console shell with thin gray structural borders.
- White article titles and primary headings.
- Light and medium gray summaries, metadata, numbering, and secondary information.
- Purple category and library identity accents.
- Pink featured-record and action accents.
- Restrained green automatic-rotation and active-status cues.
- Numbered airport-board rows rather than large article cards.
- Featured treatment for position 1 on five-record pages without creating an oversized marketing card.
- Compact category tabs and console telemetry.

## Rotation

- Automatic rotation is enabled by default.
- Default interval is 14 seconds.
- Previous, pause/play, and next controls remain available.
- The console shows AUTO, HOLD, PAUSED, STATIC, or REDUCED MOTION.
- Hover, keyboard focus, touch interaction, and hidden-browser-tab states pause rotation.
- Reduced-motion preferences disable automatic rotation.
- Category changes use a restrained screen-refresh transition rather than a horizontal carousel slide.

## Preserved editorial contract

- Every category is administrator-created and editable.
- Every article or announcement is selected manually.
- Four valid records remain the minimum for a public category.
- No latest-content, popularity, taxonomy, random, or automatic-backfill path was added.
- v4.1.1 title, URL, slug, and WordPress ID source discovery remains intact.
- Existing categories, cards, ordering, schedules, labels, copy, and destinations require no migration.

## Shortcode

Existing shortcode usage continues to work:

```text
[sc_homepage_spotlight]
```

The new default automatically rotates every 14 seconds. To keep the board static until a visitor starts playback:

```text
[sc_homepage_spotlight autoplay="false"]
```

To use a different automatic interval:

```text
[sc_homepage_spotlight autoplay="true" interval="16000"]
```

## Deployment

Upload the v4.1.2 WordPress ZIP and choose **Replace current with uploaded**. Purge WordPress, page-builder, host, CDN, and browser caches after activation. Existing Spotlight records are preserved.
