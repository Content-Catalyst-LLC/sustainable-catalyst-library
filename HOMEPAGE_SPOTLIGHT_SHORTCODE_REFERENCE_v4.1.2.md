# Homepage Spotlight Shortcode Reference
## Sustainable Catalyst Library v4.1.2

## Basic use

```text
[sc_homepage_spotlight]
```

Default behavior:

- all enabled category pages with at least four valid, manually selected records;
- black Knowledge Library Console presentation;
- category tabs enabled;
- previous, pause/play, position, and next controls enabled;
- automatic rotation enabled;
- 14-second interval;
- looping enabled;
- pause on hover, keyboard focus, touch interaction, and hidden browser tabs;
- reduced-motion preferences disable automatic rotation;
- per-record thumbnail and metadata settings respected; and
- empty output hidden.

## Static initial state

```text
[sc_homepage_spotlight autoplay="false"]
```

Visitors can still start playback with the play control.

## Custom interval

```text
[sc_homepage_spotlight autoplay="true" interval="18000"]
```

Intervals are clamped between 8,000 and 60,000 milliseconds.

## Other attributes

```text
[sc_homepage_spotlight
    controls="true"
    tabs="true"
    loop="true"
    pause_on_hover="true"
    category_limit="5"
    show_thumbnail="false"
    show_metadata="true"
    title="Explore the Knowledge Library"
    intro="Selected research across the subjects currently featured by Sustainable Catalyst."
    empty="hide"
]
```

`rotate` remains an alias for `autoplay`. When both are supplied, `rotate` takes precedence.

Shortcode attributes affect presentation only. They do not select, reorder, backfill, or recommend records.
