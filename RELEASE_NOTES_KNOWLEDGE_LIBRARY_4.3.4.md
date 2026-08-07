# Sustainable Catalyst Library v4.3.4 — Field Spotlight Data Architecture & Series Panel Registry

## Purpose

v4.3.4 establishes the durable editorial model for the new Knowledge Library Field Spotlights without replacing the current public Publications or Homepage Spotlight interfaces.

The model is:

**Major Field → flattened Series Panels → permanent Article Map hero → manually curated supporting article slots**

## Canonical registry

The release reuses the existing Publications registry rather than creating another set of Article Map routes:

- 14 major fields;
- 170 canonical Article Maps;
- one Field Spotlight model for each major field;
- one peer series panel for every canonical Article Map;
- original nested taxonomy/group information preserved as `source_group` metadata.

This means, for example, Roman Law, Common Law, Islamic Law, and the other Legal Traditions are first-class Global Governance panels while their Legal Traditions relationship remains available to the knowledge architecture.

## Article Map hero contract

Every series panel has a permanent Article Map hero identity:

- role: `article_map`;
- canonical map route inherited from the protected registry;
- display title, description, and CTA may be customized later;
- the canonical route is not editable through Field Spotlight settings.

The Article Map is position 0 and does not consume a supporting article slot.

## Supporting article slots

- Default: 4 slots per panel.
- Configurable range: 2–8 slots per panel.
- Stored fields support WordPress source ID, title, URL, and enabled state.
- Selection mode is explicitly `manual_only`.
- No latest-content, popularity, taxonomy, pathway, random, or automatic-backfill resolver is defined for Field Spotlight slots.

The visual article selector/editor follows in a later build; v4.3.4 establishes the storage and normalized model now.

## Progressive disclosure

The default Field Spotlight opening tier contains the first 8 visible panels.

Panels after the field threshold are classified as `additional`, not removed. The threshold is:

- globally configurable;
- individually overridable per major field;
- default: 8;
- architecture supports up to 24 visible-before-expansion positions without limiting the total number of panels in the field.

At the default threshold the existing registry resolves to:

- Global Governance: 13 panels / 5 additional;
- Sustainable Systems: 5 / 0;
- Technology & Systems Intelligence: 6 / 0;
- Natural Science: 7 / 0;
- Cultural Anthropology: 6 / 0;
- Literature & Cultural Memory: 18 / 10;
- Mythology: 17 / 9;
- Religious Studies: 11 / 3;
- Healing Traditions: 12 / 4;
- Psychology: 19 / 11;
- Philosophy: 27 / 19;
- Thinking: 7 / 0;
- Meaning: 8 / 0;
- Problem Solving: 14 / 6.

## Administration

New menu:

**SC Library → Field Spotlights**

The v4.3.4 administration surface provides:

- global default panel threshold;
- global default article-slot count;
- future disclosure/hero/selected-content labels;
- field title, description, order, visibility, and threshold;
- flat panel title, order, visibility, and slot count;
- source-group reference;
- protected canonical Article Map route;
- live Primary / Additional classification.

## Preserved public contracts

- `[sc_publications]` remains the v4.3.3 Dynamic Publications Spotlight.
- Homepage Spotlight remains isolated at v4.2.0.
- No public Field Spotlight shortcode is introduced in v4.3.4.
- No existing Publications or Homepage Spotlight settings are migrated or overwritten.

## Next build

The next Field Spotlight release should render the major-field Spotlight shell from this normalized model, including flat series navigation, the eight-panel opening tier, and `+ Explore additional fields` progressive disclosure.
