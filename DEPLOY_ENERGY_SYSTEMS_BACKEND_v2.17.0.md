# Deploy Library backend v2.17.0

Upload:

- `sustainable-catalyst-library-backend-v2.17.0.zip`
- `upgrade_library_backend_v2_17_0_contabo.sh`

to `/tmp` on the Contabo VPS, then run the upgrader as `catalystadmin`.

The upgrader:

1. verifies the v2.17.0 / Energy Systems v1.1.0 payload;
2. repairs the shared backup directory when necessary;
3. backs up the current Library backend;
4. preserves `.env`;
5. rebuilds and recreates `sc-library-backend`;
6. verifies backend health and the v1.1.0 runtime activation surface;
7. confirms the preserved v1.0.0 20/20 platform certification baseline;
8. confirms no target runtime consumption or outbound delivery is claimed.

No database migration or new secret is required.
