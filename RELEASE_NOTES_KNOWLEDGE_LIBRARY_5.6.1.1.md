# Sustainable Catalyst Library v5.6.1.1
## Publications Spotlight Context Repair

This patch keeps the v5.6.1 Research Network & Knowledge Discovery Console intact and makes the existing curated homepage spotlight presentation-aware.

### New shortcode context

Use:

```text
[sc_homepage_spotlight context="publications"]
```

The Publications context changes the visible/public presentation layer to:

- `PUB · Publications`
- `Featured Publications`
- Publications-specific accessible labels
- `Publication` as the fallback card label when a card had only the old default `From the Knowledge Library` label
- `PUB` as the thumbnail placeholder mark

The underlying curated spotlight pages, cards, ordering, scheduling, source selection and editorial controls are unchanged.

### Backward compatibility

The default remains:

```text
[sc_homepage_spotlight]
```

which retains the existing Knowledge Library presentation. Existing pages therefore do not change unless `context="publications"` is explicitly supplied.

`title` and `intro` remain independently configurable, for example:

```text
[sc_homepage_spotlight context="publications" title="Publications" intro="Selected public work from Sustainable Catalyst."]
```

### Homepage Library console

The v5.6.1 Library homepage console remains:

```text
[sc_library_homepage_console mode="full"]
```

No Research Library page replacement is required.

### Backend

Python backend remains v1.1.0. No backend or database migration is required.
