# Sustainable Catalyst Library v4.3.6
## Field Spotlight Administration Console & Panel Curation Workflow

### Purpose
v4.3.6 turns the v4.3.5 Field Spotlight editor into a scalable editorial console for 14 major fields and 170 Article Map panels. The public Field Spotlight presentation remains unchanged from v4.3.5.

### Administration console
- Adds a field-wide readiness dashboard for major fields, Article Map panels, ready/partial/empty panels, and configured supporting articles.
- Adds per-field completion percentages and progress indicators.
- Adds searchable/filterable panel management by title, source group, Primary/Additional tier, Ready/Partial/Empty status, and Hidden status.
- Replaces the dense panel table with thumbnail-backed editorial panel rows.
- Keeps panel order, visibility, supporting-slot count, source group, and canonical Article Map destination visible in one row.
- Adds sticky save controls for long fields.

### Supporting article curation
- Adds AJAX Library source search directly inside every supporting article slot.
- Accepts title, canonical URL, numeric ID, or slug-oriented searches against eligible published Library content.
- Displays resolved thumbnail, record type/metadata, and excerpt before selection.
- Selecting a result records source ID and canonical URL and enables the slot.
- Clear Slot removes the selection without filling it from another source.
- Direct canonical URL entry and optional title override remain available.

### Editorial integrity
- Article Map remains registry-owned permanent hero position 0.
- Supporting articles remain manual-only.
- Empty slots remain empty.
- No latest, popular, taxonomy, random, or automatic backfill is introduced.
- Existing Field Spotlight settings remain in `sc_library_field_spotlights_settings_v434`; no migration or re-entry is required.

### Compatibility
- Public `[sc_field_spotlights]` and `[sc_field_spotlight]` presentation remains the v4.3.5 shell.
- Publications remains v4.3.3.
- Homepage Spotlight remains v4.2.0.
