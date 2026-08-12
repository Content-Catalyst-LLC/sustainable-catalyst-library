# Publications Runtime Recovery v4.3.21.1

## Failure signature
- Publications renders the first field/panel.
- Remaining Field Spotlight field or panel controls do not work or are missing from the effective public model.

## Recovery layers
1. **Upgrade/activation integrity repair** clears Publications/Field Spotlight transients and repairs implausibly collapsed visibility state.
2. **Render-time integrity guard** detects a canonical multi-field/multi-panel registry that has collapsed to one visible public field/panel and reruns the bounded repair.
3. **Runtime cache bust** changes Field Spotlight public asset version from 4.3.13 to 4.3.21.1.
4. **Progressive enhancement** makes field/panel controls ordinary URLs first; JavaScript intercepts them only when the shared-stage runtime successfully initializes.
5. **No-JavaScript fallback** exposes field navigation and additional panels rather than leaving the page stranded on the first server-rendered stage.

The repair never deletes Publications or Field Spotlight settings and does not rewrite editorial content.
