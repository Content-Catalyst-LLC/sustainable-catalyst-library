# Homepage Spotlight Shortcode Reference
## Sustainable Catalyst Library v4.1.3

## Basic use

```text
[sc_homepage_spotlight]
```

Defaults:

- all enabled categories with at least four valid cards;
- visible category tabs;
- previous, pause/play, position, and next controls;
- automatic rotation enabled;
- 14-second category interval;
- looping enabled;
- pause on hover and keyboard focus;
- saved per-card thumbnail and metadata preferences;
- hidden output when no valid category is available.

## Recommended homepage use

```text
[sc_homepage_spotlight autoplay="true" interval="14000"]
```

To ensure thumbnails or Library placeholders appear for all cards:

```text
[sc_homepage_spotlight autoplay="true" interval="14000" show_thumbnail="true"]
```

## Attributes

### `autoplay`

Initial automatic category rotation. Default: `true`.

```text
[sc_homepage_spotlight autoplay="false"]
```

The alias `rotate` is also accepted and takes precedence when supplied.

### `interval`

Rotation interval in milliseconds, clamped from 8,000 to 60,000. Default: `14000`.

### `controls`

Displays previous, pause/play, position, and next controls. Default: `true`.

### `tabs`

Displays the category selector rail. Default: `true`.

### `loop`

Wraps navigation from the last category to the first. Default: `true`.

### `pause_on_hover`

Pauses rotation while a pointer is over the console. Keyboard focus always pauses. Default: `true`.

### `category_limit`

Limits the number of manually ordered categories rendered. Default: `0`, meaning no shortcode-level limit.

### `show_thumbnail`

Overrides every saved card thumbnail preference.

```text
[sc_homepage_spotlight show_thumbnail="true"]
[sc_homepage_spotlight show_thumbnail="false"]
```

Leave the attribute absent to respect each card's saved preference. When enabled, v4.1.3 displays a resolved source image or a neutral Library placeholder.

### `show_metadata`

Overrides each card's saved metadata preference.

### `title`

Changes the console heading. Default: `Explore the Knowledge Library`.

### `intro`

Changes the introductory sentence.

### `empty`

Default: `hide`. Any other value returns an empty integration placeholder when no category has four valid cards.

## Accessibility and motion

- Selected tabs expose state through `aria-selected`.
- Hidden category panels are removed from reading order.
- Keyboard focus pauses rotation.
- Reduced-motion preferences disable autoplay and the play control.
- Touch users may swipe between categories.
- Focus uses a high-contrast green outline.
- Decorative thumbnails use empty alt text because each record title is already linked beside the image.
