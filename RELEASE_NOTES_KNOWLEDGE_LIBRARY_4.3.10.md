# Sustainable Catalyst Library v4.3.10

## Supporting Article Slot Persistence & Public Binding Repair

This release repairs the supporting article positions beneath Major Field Spotlight Article Map heroes.

### Fixed

- A selected supporting article now becomes active automatically when the Field Spotlight panel is saved.
- The public renderer no longer suppresses a populated slot because of a stale or missing legacy `enabled` flag.
- Slot positions are sanitized deterministically and retained in editorial order.
- Selected articles resolve by saved WordPress post ID first, then canonical URL, then URL-slug fallback.
- Existing v4.3.9 selections containing a source ID or URL are treated as configured even if their old enabled flag is false.
- The admin editor now reports `Publishes on save` instead of requiring a second Enable-this-slot action.
- Clearing a slot remains the explicit way to remove a supporting article.

### Preserved

- Article Map remains permanent hero position 0.
- Four supporting article slots remain the default.
- No automatic article backfill.
- First eight panels visible initially; panel 9+ behind the additional-fields accordion.
- White active panel treatment, sharp edges, autoplay, thumbnails, and Homepage Spotlight interaction parity from v4.3.9.
- Homepage Spotlight v4.2.0 and Publications v4.3.3 remain isolated.
