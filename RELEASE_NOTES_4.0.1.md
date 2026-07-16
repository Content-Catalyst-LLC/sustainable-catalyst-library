# Sustainable Catalyst Knowledge Library v4.0.1

## Discovery Interface Reliability Patch

v4.0.1 folds the proven Library Interface Repair into the main Knowledge Library repository.

### Fixed

- Restores clicks on Browse the Knowledge Architecture domain cards and their plus/minus controls.
- Uses a capture-phase disclosure handler so theme or plugin listeners cannot cancel the native `<details>` interaction.
- Synchronizes `aria-expanded`, open-state classes, and plus/minus indicators.
- Restores child topic grids when a domain is open.
- Reapplies the interaction repair after asynchronous Library discovery refreshes.
- Converts repeated ampersand encodings such as `&amp;amp;` to a visible `&` in display fields.
- Leaves URLs, identifiers, hashes, and unrelated HTML entities unchanged.
- Stabilizes the Live Knowledge Architecture note layout on desktop and mobile.

### Standalone repair transition

When the temporary **Sustainable Catalyst Library Interface Repair** plugin remains active, v4.0.1 does not enqueue its embedded JavaScript or CSS. This avoids two capture handlers toggling a card twice.

After v4.0.1 is installed and verified, deactivate and delete the standalone repair plugin. The main Knowledge Library will then supply the repair directly.

### Compatibility

v4.0.1 preserves the complete v4.0.0 Connected Institutional Knowledge and Research Platform and all earlier Knowledge Library systems.
