# Knowledge Library Contrast and Thumbnail Guide
## Sustainable Catalyst Library v4.1.3

## Design intent

The Homepage Spotlight should complement, not duplicate, the Release Console and Live Intelligence surfaces placed immediately below it.

The visual division is:

- **Knowledge Library:** black structural frame with white, cream, and gray editorial records.
- **Release Console:** predominantly black technical and repository presentation.
- **Live Intelligence:** lighter signal surface with operational green treatment.

## Palette roles

- Black: masthead, structural frame, category rail, and controls.
- White: primary article rows.
- Cream: alternating article rows and the main editorial background.
- Gray: dividers, metadata, placeholders, and secondary surfaces.
- Red: Library identity, featured treatment, active category, and article actions.
- Green: automatic playback, keyboard focus, hover confirmation, and status.

The public component intentionally avoids purple and pink in v4.1.3.

## Thumbnail behavior

Each selected Library card can display either:

- a resolved source image; or
- a neutral `KL` placeholder when no usable image exists.

The resolver checks standard WordPress media first, then Library-specific and document-specific sources. A broken image is replaced in the browser rather than leaving an empty frame.

### Existing cards

Open **SC Library → Homepage Spotlight**, edit a card, and enable:

**Show resolved thumbnail or Library placeholder**

To force thumbnails across all selected cards from the homepage shortcode, use:

```text
[sc_homepage_spotlight show_thumbnail="true"]
```

To hide every thumbnail:

```text
[sc_homepage_spotlight show_thumbnail="false"]
```

Leaving the attribute out respects the saved setting on each card.

## Responsive behavior

- Desktop lead thumbnail: approximately 138 × 94 pixels.
- Desktop supporting thumbnail: approximately 90 × 66 pixels.
- Tablet thumbnails: approximately 82 × 62 pixels.
- Mobile thumbnails: approximately 68 × 54 pixels.

Thumbnails no longer disappear at the mobile breakpoint.

## Cache checks

v4.1.3 uses a new public cache key, preventing the v4.1.2 card payload from being reused after upgrade. Still purge external page caches after installation because WordPress, Astra, hosting, or CDN caches may retain old HTML or CSS.

## Production review checklist

- Confirm the masthead and controls remain black.
- Confirm the article rows alternate between white and cream.
- Confirm red is used for Library identity and actions.
- Confirm green is limited to status, focus, and confirmation states.
- Confirm actual images appear when records have usable media.
- Confirm `KL` placeholders appear when they do not.
- Confirm broken image URLs convert to placeholders.
- Confirm thumbnails remain visible on mobile.
- Confirm category rotation, hover pause, focus pause, and reduced motion still work.
- Confirm the component does not visually merge with the Release Console below it.
