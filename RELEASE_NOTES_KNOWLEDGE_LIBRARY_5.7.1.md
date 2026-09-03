# Sustainable Catalyst Library v5.7.1
## Johns Hopkins Widget Integration & Source Registry Repair

v5.7.1 is a focused integration repair on the v5.7.0 institutional research-source line. It fixes the gap where the Johns Hopkins Dataverse connector existed and the standalone `[sc_institutional_research_sources]` surface rendered correctly, but the existing Research Network Console and homepage Library widget did not consume that source identity.

### What changes

- Adds a canonical `network_source()` projection to `SC_Library_Institutional_Research_Sources`.
- Makes `SC_Library_Research_Network_Console::source_registry()` consume that canonical Johns Hopkins projection.
- Surfaces **Johns Hopkins Research Data Repository** as a university/research route with **LIVE METADATA** capability labeling.
- Adds Johns Hopkins to the homepage featured-source priority immediately after MIT and Harvard, so it is visible near the top of the ticker in `full`, `compact`, and `network` modes.
- Adds a stable `data-source-id` to homepage rows for source-level testing and future interaction without duplicating visible source metadata.
- Preserves non-endorsement language and source/provenance/reuse-state boundaries from v5.7.0.

### Architecture

`Johns Hopkins Dataverse -> institutional source adapter -> canonical institutional source record -> Research Network registry -> homepage Library console`

The Research Network and homepage no longer maintain a second Johns Hopkins name/capability definition. The v5.7 institutional-source layer owns the canonical record.

### Backend

No Python backend change is made in v5.7.1. The required Library backend remains **v1.2.0** from v5.7.0. If v1.2.0 is already deployed, no backend redeploy is required for this repair. If it has not been deployed, the Johns Hopkins name will still render in WordPress, but live Dataverse search requires the v1.2.0 backend.

### No migration

- no PostgreSQL migration;
- no DNS/Caddy change;
- no bulk external data mirror;
- no new external credential;
- existing Library, Publications, Explorer, Research Librarian, and research-network capabilities remain intact.
