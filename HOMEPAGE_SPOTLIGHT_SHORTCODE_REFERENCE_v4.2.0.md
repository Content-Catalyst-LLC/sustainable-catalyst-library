# Homepage Spotlight Shortcode Reference
## Sustainable Catalyst Library v4.2.0

### Default

```text
[sc_homepage_spotlight]
```

### Attributes

| Attribute | Default | Purpose |
|---|---:|---|
| `autoplay` | `true` | Enables automatic topic rotation. |
| `rotate` | empty | Compatibility alias that overrides `autoplay` when supplied. |
| `interval` | `14000` | Rotation interval in milliseconds, clamped from 8000 to 60000. |
| `controls` | `true` | Shows previous, pause/play, position, and next controls. |
| `tabs` | `true` | Shows topic navigation. |
| `loop` | `true` | Wraps sequential navigation. |
| `pause_on_hover` | `true` | Holds rotation during pointer hover. Keyboard focus always holds rotation. |
| `category_limit` | `0` | Limits active topic pages; `0` means no limit. |
| `secondary_topics` | `true` | Places Secondary-tier topics behind progressive disclosure. |
| `secondary_open` | `false` | Opens the Secondary tier on initial load. |
| `secondary_label` | `Explore additional topics` | Changes the closed Secondary-tier label. |
| `show_thumbnail` | empty | Overrides per-card thumbnail settings when set. |
| `show_metadata` | empty | Overrides per-card metadata settings when set. |
| `title` | `Explore the Knowledge Library` | Console heading. |
| `intro` | default editorial introduction | Console introduction. |
| `empty` | `hide` | Hides the widget when no topic has at least four valid cards. |

### Recommended homepage form

```text
[sc_homepage_spotlight autoplay="true" interval="14000" secondary_topics="true" secondary_open="false"]
```
