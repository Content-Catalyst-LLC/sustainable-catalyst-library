# Knowledge Library Progress Indicator Guide
## Sustainable Catalyst Library v4.1.4

## Visual contract

The rotation timer now uses:

- **Filled elapsed segment:** Sustainable Catalyst red (`#e00000`)
- **Remaining track:** neutral medium gray (`#8f8f8a`)
- **AUTO status:** green remains limited to the small operational indicator

The progress element contains no gradient and does not transition from red to green.

## Behavior

The timer retains the existing 14-second default category interval. It restarts when a category screen changes and pauses under the same conditions as v4.1.3:

- pointer hover;
- keyboard focus;
- touch interaction;
- manual pause;
- hidden browser tab; and
- reduced-motion preference.

## Editorial impact

None. Categories, selected records, source thumbnails, summaries, ordering, scheduling, and activation remain unchanged.

## Deployment check

After upgrading, purge all relevant caches and verify that the progress line grows only in red over a gray track. The small AUTO status may remain green.
