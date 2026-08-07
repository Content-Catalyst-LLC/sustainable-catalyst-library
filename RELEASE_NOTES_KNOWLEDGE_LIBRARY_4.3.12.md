# Sustainable Catalyst Knowledge Library v4.3.12

## Dedicated Field Spotlight Panel-Content Persistence

v4.3.12 replaces the fragile partial settings merge used by the Field Spotlight content editor with a dedicated durable panel-content store.

### What changed

- Article Map hero display title, description, CTA, and supporting article slots now save to `sc_library_field_spotlight_panel_content_v4312`.
- Panel content no longer passes through the large `sc_library_field_spotlights_settings_v434` partial settings transaction.
- The legacy settings option continues to own structural configuration such as field titles, field visibility, panel order, panel visibility, and slot counts.
- Legacy hero/supporting-article content remains readable until a panel is saved into the new store.
- The dedicated store is authoritative for newly saved hero/supporting content.
- Save confirmation reports the number of supporting articles that were read back successfully.
- Public/model caches are cleared after every intentional save.
- The supporting article remains active whenever a source ID or URL is populated.
- No automatic article backfill is introduced.

### Regression target

The release includes an International Law runtime save test that writes one supporting article through the actual panel save transaction and verifies:

1. the dedicated option exists;
2. the International Law panel record is present;
3. source ID and canonical URL persist;
4. the slot is active;
5. the redirect reports one persisted supporting article.

### Preserved behavior

- v4.3.9 sharp-edge presentation
- white currently playing panel
- first eight panels visible by default
- panel 9+ progressive-disclosure accordion
- Homepage Spotlight-style playback
- Article Map hero + supporting articles
- v4.3.3 Publications shortcode
- v4.2.0 Homepage Spotlight isolation
