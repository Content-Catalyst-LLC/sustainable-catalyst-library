# Homepage Spotlight Shortcode Reference
## Sustainable Catalyst Library v4.1.0

## Basic use

```text
[sc_homepage_spotlight]
```

Default behavior:

- all enabled category pages with at least four valid cards;
- subject tabs enabled;
- previous, play, position, and next controls enabled;
- autoplay disabled;
- 16-second interval if a visitor starts playback;
- looping enabled;
- pause on hover and keyboard focus enabled;
- per-card thumbnail and metadata settings respected;
- empty output hidden.

## Recommended homepage use

```text
[sc_homepage_spotlight autoplay="false" interval="16000"]
```

## Attributes

### `autoplay`

Controls initial automatic category rotation.

```text
[sc_homepage_spotlight autoplay="true"]
```

Default: `false`

The alias `rotate` is also accepted. When both are present, `rotate` takes precedence.

### `interval`

Rotation interval in milliseconds. Values are clamped between 8,000 and 60,000 milliseconds.

```text
[sc_homepage_spotlight autoplay="true" interval="18000"]
```

Default: `16000`

### `controls`

Shows or hides previous, pause/play, position, and next controls.

```text
[sc_homepage_spotlight controls="false"]
```

Default: `true`

Controls are automatically omitted when only one public category is available.

### `tabs`

Shows or hides the visible category selector.

```text
[sc_homepage_spotlight tabs="false"]
```

Default: `true`

### `loop`

Controls whether navigation wraps from the final category to the first.

```text
[sc_homepage_spotlight loop="false"]
```

Default: `true`

### `pause_on_hover`

Pauses autoplay while a pointer is over the widget. Keyboard focus always pauses autoplay.

```text
[sc_homepage_spotlight pause_on_hover="false"]
```

Default: `true`

### `category_limit`

Limits the number of enabled category pages rendered, preserving administrator-defined order.

```text
[sc_homepage_spotlight category_limit="5"]
```

Default: `0` for no shortcode-level limit.

This attribute never selects categories by taxonomy or popularity.

### `show_thumbnail`

Overrides every card’s saved thumbnail setting.

```text
[sc_homepage_spotlight show_thumbnail="true"]
```

Default: empty, which respects each card’s editorial setting.

### `show_metadata`

Overrides every card’s saved metadata setting.

```text
[sc_homepage_spotlight show_metadata="false"]
```

Default: empty, which respects each card’s editorial setting.

### `title`

Changes the public component heading.

```text
[sc_homepage_spotlight title="Explore Current Research"]
```

Default: `Explore the Knowledge Library`

Set `title=""` to omit the heading.

### `intro`

Changes the short introductory sentence.

```text
[sc_homepage_spotlight intro="Selected research and documents from across the Library."]
```

Set `intro=""` to omit it.

### `empty`

Controls output when no category has at least four valid cards.

```text
[sc_homepage_spotlight empty="hide"]
```

Default: `hide`

Any other value returns an empty placeholder element for advanced theme integration.

## Accessibility behavior

- Visible category tabs expose selected state.
- Hidden category panels are removed from the reading order.
- Keyboard focus pauses rotation.
- Reduced-motion preferences disable autoplay and disable the play control.
- Touch users may swipe between categories.
- Controls use descriptive accessible names.
- Mobile presentation collapses to one column.
