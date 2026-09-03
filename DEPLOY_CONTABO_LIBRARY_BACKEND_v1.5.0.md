# Deploy Library Backend v1.5.0 to Contabo

This is a backend-bearing release with **no database migration**.

1. Upload `sustainable-catalyst-library-backend-v1.5.0.zip` and `upgrade_library_backend_v1_5_0_contabo.sh` to `/tmp`.
2. Run the upgrader as the Catalyst admin user.
3. The existing `/opt/sustainable-catalyst/library-backend/.env` is preserved.
4. Add WHO ICD credentials to the preserved `.env` when available:

```env
SC_LIBRARY_WHO_ICD_CLIENT_ID=...
SC_LIBRARY_WHO_ICD_CLIENT_SECRET=...
SC_LIBRARY_WHO_ICD_RELEASE_ID=2026-01
SC_LIBRARY_WHO_ICD_LANGUAGE=en
```

Without WHO credentials, backend v1.5.0 remains healthy and MeSH/RxNorm terminology resolution remains available; the ICD-11 group reports a contained configuration error until credentials are supplied.

For a future locally deployed WHO ICD API, set:

```env
SC_LIBRARY_WHO_ICD_BASE_URL=http://sc-icd11
SC_LIBRARY_WHO_ICD_LOCAL_MODE=true
```
