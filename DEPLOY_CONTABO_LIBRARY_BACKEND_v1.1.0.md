# Deploy Sustainable Catalyst Library Backend v1.1.0

v1.1.0 is an in-place code upgrade of the existing Library backend. It does not require a database migration, DNS change, Caddy change, new port, or new credentials.

Existing production boundary remains:

```text
library-api.sustainablecatalyst.com
  → Caddy
  → 127.0.0.1:8087
  → sc-library-backend
  → sc-internal
  → sc-postgres / sc_library
```

Use the included `upgrade_library_backend_v1_1_0_contabo.sh`. It backs up the current application and `.env`, replaces backend code, rebuilds only `sc-library-backend`, waits for Docker health, and verifies `/health`, `/ready`, backend version `1.1.0`, and the bounded Explorer bootstrap route.

From the VPS:

```bash
chmod +x /tmp/upgrade_library_backend_v1_1_0_contabo.sh
/tmp/upgrade_library_backend_v1_1_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v1.1.0.zip
```

Expected `/health` additions:

```json
{
  "version": "1.1.0",
  "capabilities": {
    "dynamic_explorer": true,
    "progressive_discovery": true,
    "filterable_search": true,
    "progressive_record_detail": true
  }
}
```

After backend validation, update the WordPress plugin to v5.6.0 and replace the public page body with `RESEARCH_LIBRARY_PAGE_v5.6.0.html`.
