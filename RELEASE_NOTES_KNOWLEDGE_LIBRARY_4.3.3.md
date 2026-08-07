# Sustainable Catalyst Library v4.3.3 — Dynamic Publications Spotlight, Field Deck & Editorial Customization

## Purpose

v4.3.3 replaces the long Publications landing page with one compact, dynamic editorial console modeled on the interaction discipline of the Homepage Knowledge Library Spotlight.

The canonical Publications hierarchy remains fourteen major fields and 170 Article Maps, but only one field and one Article Map are active on screen at a time. A field with 27 areas therefore occupies the same bounded interface as a field with 5.

## Public interface

`[sc_publications]` now renders:

- a black Knowledge Library masthead;
- a compact 14-field selector showing field number, title, and Article Map count;
- one shared field viewport;
- previous/next Article Map controls;
- a horizontal direct-jump area rail on desktop;
- a compact Article Map selector on mobile;
- one Article Map hero followed by up to four publication rows;
- keyboard left/right navigation;
- touch swipe navigation;
- reduced-motion protection;
- no autoplay;
- no Blog Roll;
- no reading-time metadata.

The public page no longer renders 170 visible Article Map boards one after another.

## Editorial customization

SC Library → Publications now controls visible Publications presentation without requiring PHP edits.

Global controls:

- eyebrow;
- title;
- introductory copy;
- Fields label;
- Areas label;
- Article Map label;
- Article Map CTA;
- previous/next control labels;
- jump-control label;
- default Article Map description.

Per-field controls:

- display title;
- description;
- order;
- visibility;
- default Article Map.

Per-Article-Map controls:

- hero display title;
- hero description;
- CTA label;
- visibility;
- optional manual publication URL/title overrides for up to four positions.

Manual publication overrides are additive: any unfilled positions continue to use the existing automatic resolver cascade.

## Preserved contracts

- Canonical registry: 14 fields / 170 Article Maps.
- Article Map hero + up to four publication rows.
- Existing resolver order: Homepage Spotlight curation → canonical Article Map links → Knowledge Pathway steps → same-slug category content → extension filter.
- Canonical Article Map routes remain protected from presentation customization.
- Homepage Spotlight remains isolated at v4.2.0.

## Shortcode

```text
[sc_publications]
```

Optional shortcode title/intro values can still override the saved Publications title and intro for a specific embed.
