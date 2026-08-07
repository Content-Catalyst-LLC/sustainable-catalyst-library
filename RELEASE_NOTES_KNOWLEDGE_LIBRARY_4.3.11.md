# Sustainable Catalyst Knowledge Library v4.3.11

## Field Spotlight Save Transaction Repair

v4.3.11 repairs the Field Spotlight Console save path after supporting article and hero-copy selections could appear in the editor but fail to persist.

### Changes

- Replaces Field Spotlight `options.php` form submissions with a dedicated authenticated `admin-post.php` transaction.
- Preserves `general`, `field`, and `panel` save context explicitly.
- Prevents double sanitization of partial panel/field payloads.
- Performs read-after-write verification against the normalized expected option value.
- Clears both Field Spotlight model and public caches after every intentional save.
- Returns the editor to the same field/panel after save.
- Displays an explicit success or verification-failure notice.
- Retains the durable `sc_library_field_spotlights_settings_v434` option so existing curation is preserved.
- Preserves v4.3.10 supporting-article binding behavior and v4.3.9 public presentation behavior.

### Public behavior unchanged

- first eight panels visible before disclosure
- panel 9+ behind the additional-fields accordion
- white currently playing panel
- sharp square edges
- Article Map hero + curated supporting articles
- 14-second autoplay default
