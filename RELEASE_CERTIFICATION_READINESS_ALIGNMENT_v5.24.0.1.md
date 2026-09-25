# Release Certification & Readiness Alignment — v5.24.0.1

The readiness dashboard previously compared current runtime identity against historical milestone versions (notably v5.3.0, v5.4.0, and the v5.0.1 production-soak component). That produced false failures after the Library advanced to v5.24.0.

v5.24.0.1 separates three concepts:
1. **Plugin release version** — the currently installed Knowledge Library release.
2. **Component lineage version** — the version at which a retained subsystem was introduced or last independently versioned.
3. **Compatibility** — whether the retained component is valid inside the current plugin release.

The canonical route health payload now reports component and plugin versions independently and exposes a compatibility flag. Production soak certification similarly validates that the current plugin release is compatible with the retained Connected Public Research component instead of demanding version equality.

The release gate remains first-party-only, performs no third-party provider requests, and does not inspect private research content.
