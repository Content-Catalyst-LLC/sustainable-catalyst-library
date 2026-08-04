# Homepage Spotlight Shortcode Reference
## Sustainable Catalyst Library v4.1.4

## Basic use

```text
[sc_homepage_spotlight]
```

The default remains automatic category rotation at a 14-second interval. v4.1.4 changes only the timer presentation: a solid red fill advances over a neutral gray track. Green is reserved for the small AUTO status indicator.

## Common options

```text
[sc_homepage_spotlight autoplay="false"]
```

Disables initial automatic playback.

```text
[sc_homepage_spotlight interval="18000"]
```

Changes the rotation interval to 18 seconds. Accepted interval values remain clamped between 8,000 and 60,000 milliseconds.

```text
[sc_homepage_spotlight show_thumbnail="true"]
```

Forces resolved thumbnails or neutral Library placeholders on all selected cards.

All other v4.1.3 shortcode attributes and accessibility behavior remain unchanged.
