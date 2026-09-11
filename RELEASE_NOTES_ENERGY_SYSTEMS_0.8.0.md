# Release Notes — Energy Systems Intelligence v0.8.0

## Global Energy Intelligence

This release adds the first live global-energy observation connector to Sustainable Catalyst while preserving the evidence and freshness boundaries established by earlier Energy Systems releases.

### Added

- `library-backend/app/energy_global.py`
- 9 governed global-energy metric definitions
- 4 source/connector contracts
- 1 active live connector: World Bank Indicators API v2
- country profile contract with observation-year preservation
- one-metric, up-to-eight-country comparison contract
- short-lived in-process response cache; no persistent current-value store
- WordPress Global Intelligence interface with profile lookup, comparison, source cards, and SVG sparklines
- JSON Schemas for metric definitions, observations, and country profiles
- machine-readable `global-energy-intelligence-v0.8.0.json` export
- Site Intelligence / Lab / Workbench / Decision Studio handoff references

### Version changes

- Energy Systems Intelligence: **0.7.0 → 0.8.0**
- Library backend: **2.13.0 → 2.14.0**
- Library: remains **5.11.0**
- Carbon & Nature Intelligence: remains **0.5.0**

### Data and freshness boundaries

The release contains **no embedded current country observations**. Live World Bank responses retain their observation year. Missing values are left missing; they are not interpolated. The latest available observation is not automatically treated as a current-year value. Cross-source harmonization is not assumed.

The EISD links are contextual mappings only; the World Bank indicators are not claimed to replace official EISD methodology-sheet formulas.

### Source activation

- World Bank WDI / Indicators API v2: **active, read-only, no API key**
- Ember API: contract registered, **not activated**; API key required
- EIA API v2: contract registered, **not activated**; API key required
- IEA data explorers: reference/future connector; dataset-specific access/licensing

### Deployment

- No database migration.
- No new environment variable or secret.
- Existing `.env` is preserved by the Contabo upgrader.
- The backend upgrader retains the v2.13.0 self-repair for a root-owned/unwritable shared backup directory.
- External World Bank smoke verification is non-fatal by default because provider/network availability should not invalidate an otherwise healthy deployment. Set `SC_VERIFY_LIVE_GLOBAL_ENERGY=1` for strict live-source verification.

### Validation

The focused regression suite covers the v0.8.0 global layer, all earlier Energy Systems layers, and Carbon & Nature v0.5.0 preservation. Runtime live-source parsing is tested with deterministic provider fixtures; the build environment does not perform an outbound World Bank request.
