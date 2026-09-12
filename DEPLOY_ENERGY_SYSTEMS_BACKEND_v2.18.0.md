# Energy Systems Intelligence v1.2.0 — Contabo Deployment

This release advances the shared Library backend to **v2.18.0** and activates target-side Energy Systems contract-intake consumers in five existing Sustainable Catalyst runtimes.

## Release identities

| Runtime | Release |
| --- | --- |
| Library / Energy Systems | Library 5.11.0 / Energy Systems 1.2.0 / backend 2.18.0 |
| Lab | 0.101.0 |
| Workbench | 6.1.0 |
| Site Intelligence | 4.40.0 |
| Research Librarian | 8.1.0 |
| Decision Studio | 2.3.0 |

## Deployment order

1. Apply and push the v1.2.0 multi-repository patch on macOS.
2. Update the five target WordPress plugins and the Library WordPress plugin.
3. Upload the five target backend packages, five guarded target upgraders, the Library backend package, and the Library upgrader to `/tmp` on Contabo.
4. Deploy the target runtimes first: Lab, Workbench, Site Intelligence, Research Librarian, Decision Studio.
5. Deploy Library backend v2.18.0 last and verify its five-target consumer registry.

Each target upgrader preserves runtime environment files and persistent state, backs up the live backend, overlays the new backend code, rebuilds only the named Compose service, and verifies `/v1/energy-runtime/consumer` plus `/v1/energy-runtime/consume` are registered in the target FastAPI app.

The Library upgrader preserves `.env`, keeps the backup-directory ownership repair, rebuilds the Library service, verifies the v1.2.0 runtime framework and five target baselines, and preserves the v1.0.0 20/20 structural certification baseline.

## Boundaries

The target consumers validate handoff schema, target identity, exact target payload sections, provenance, and deterministic receipt generation. They do **not** execute models or calculations, persist studies, forward credentials, infer missing assumptions, rank alternatives, select winners, or create investment/policy recommendations.

No database migration and no new secret are required by this release.
