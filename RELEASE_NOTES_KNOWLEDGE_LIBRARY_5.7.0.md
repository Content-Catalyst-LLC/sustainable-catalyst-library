# Sustainable Catalyst Library v5.7.0
## Institutional Research Sources & Johns Hopkins Data Connector

v5.7.0 establishes a reusable institutional-source layer and ships Johns Hopkins University as the first production connector through the Johns Hopkins Research Data Repository (Dataverse).

### What ships

- reusable `InstitutionalSource` contract and registry in the Python backend;
- Johns Hopkins Dataverse metadata search with bounded pagination;
- normalized institutional metadata, persistent identifiers, citation/source identity, provenance, access state and data state;
- dataset detail retrieval with bounded file manifests (maximum 100 returned file descriptors);
- license/reuse normalization that distinguishes known non-commercial terms from known permissive Creative Commons terms and flags unknown/custom terms for review;
- backend source registry endpoint and source-specific search/detail endpoints;
- WordPress REST proxy so public browsers never require direct repository integration logic;
- `[sc_institutional_research_sources]` public Library search surface;
- contained upstream failures: Johns Hopkins availability cannot take down the Library;
- explicit non-endorsement/partnership language.

### Architecture

`Johns Hopkins Dataverse -> Library backend source adapter -> normalized Catalyst institutional record -> WordPress REST bridge -> Library UI / future Librarian + Lab handoffs`

The release intentionally indexes/discovers metadata first. It does not bulk-copy Johns Hopkins research files or assume that public discoverability equals unrestricted reuse.

### Backend

Backend version advances from 1.1.0 to 1.2.0.

New endpoints:

- `GET /v1/institutional-sources`
- `GET /v1/institutional-sources/johns-hopkins-dataverse/search?q=...`
- `GET /v1/institutional-sources/johns-hopkins-dataverse/record?persistent_id=...`

New health capabilities:

- `institutional_sources`
- `johns_hopkins_dataverse`
- `license_reuse_normalization`

Optional environment variable:

- `SC_LIBRARY_INSTITUTIONAL_TIMEOUT_SECONDS` (default 8, bounded 2-30)

### No migration

- no PostgreSQL migration;
- no Caddy/DNS change;
- no bulk external data mirror;
- existing Explorer, Research Network Console, Publications Spotlight, ingestion/recovery and prior Library capabilities remain intact.

### Next natural integrations

The source contract is intentionally reusable for additional Dataverse and institutional repositories, followed by Research Librarian source awareness and governed Dataset -> Catalyst Data -> Lab handoff.
