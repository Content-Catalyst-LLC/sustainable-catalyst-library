# Deploy Library backend v2.25.1

Copy `sustainable-catalyst-library-backend-v2.25.1.zip` and `upgrade_library_backend_v2_25_1_contabo.sh` to `/tmp` on Contabo, then run:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_25_1_contabo.sh
./upgrade_library_backend_v2_25_1_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.25.1.zip
```

The installer requires the exact v5.14 citation graph URL that failed in v2.25.0 to return HTTP 200 with the original record ID preserved.
