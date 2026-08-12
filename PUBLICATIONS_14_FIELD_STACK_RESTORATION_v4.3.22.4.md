# Publications 14-Field Stack Restoration — v4.3.22.4

## Historical cause

Field Spotlight v4.3.5 introduced `[sc_field_spotlights]` as the complete major-field stack. Releases v4.3.7–v4.3.12 refined that stacked architecture with autoplay, thumbnails, the eight-panel opening tier, progressive disclosure, and durable panel-content persistence.

v4.3.13 intentionally changed `[sc_field_spotlights]` to a **single shared editorial stage** with a 14-field selector. That presentation decision—not missing registry data—caused the later Publications page to show only one complete major-field surface at a time.

## v4.3.22.4 architecture

The canonical stack is restored:

```text
Publications
├── 01 Global Governance
│   └── independent Field Spotlight stage
├── 02 Sustainable Systems
│   └── independent Field Spotlight stage
├── 03 Technology & Systems Intelligence
│   └── independent Field Spotlight stage
├── ...
└── 14 Problem Solving
    └── independent Field Spotlight stage
```

Each stage receives its own normalized field payload and its own `sc-field-spotlight__data` JSON node. Existing Field Spotlight JavaScript therefore initializes each field independently using the already-tested single-field runtime rather than multiplexing 14 fields through one shared stage.

## Rendering contract

- `templates/field-spotlights.php` loops over the complete public field model.
- The template includes `field-spotlight-stage.php` once **per field**.
- No master-stage JSON payload is used by the canonical stack.
- No field dropdown or field-switching JavaScript is required to reveal another major field.
- The top field index is anchor navigation only.
- A server query selecting a field or Article Map can still mark/target the relevant stacked field while all other fields remain rendered.

## Compatibility contract

The single-field shortcode remains available for legitimate embedded use:

```text
[sc_field_spotlight field="global-governance"]
```

On `/publications/`, stale single-field or legacy `[sc_publications]` content is promoted to the complete stack to prevent page-content drift from recreating the one-field presentation.

## Performance boundary

The stack intentionally increases server-rendered markup because all 14 fields are visible at once. It does not multiply the canonical data registry or duplicate persistence. Supporting article images continue to use lazy loading, while Article Map hero imagery remains eager for the currently server-selected panel inside each field.
