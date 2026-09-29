# Deploy Library backend v2.55.0

Copy `sustainable-catalyst-library-backend-v2.55.0.zip` and `upgrade_library_backend_v2_55_0_contabo.sh` to `/tmp` on the Contabo host, then run:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_55_0_contabo.sh
sudo ./upgrade_library_backend_v2_55_0_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.55.0.zip
```

The installer backs up the current backend, preserves `.env`, rebuilds Python/Go/Rust containers, verifies backend 2.55.0 and the Global Source Federation Registry, tests a safe connector manifest and a rejected truth-promotion manifest, confirms v5.43/v5.42 continuity, and validates runtime versions.

It does **not** add or rotate connector credentials, query external sources, queue embeddings, call an embedding/reranking provider, or translate source text.
