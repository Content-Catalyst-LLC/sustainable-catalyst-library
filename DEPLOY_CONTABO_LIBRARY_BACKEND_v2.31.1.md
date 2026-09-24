# Deploy Library Backend v2.31.1

Copy `sustainable-catalyst-library-backend-v2.31.1.zip` and `upgrade_library_backend_v2_31_1_contabo.sh` to `/tmp` on the Contabo VPS, then run the installer. It preserves `.env`, backs up the prior backend, rebuilds the Docker service, waits for health, and verifies both the legacy GET corpus contract and the new POST JSON corpus transport.

Expected completion:

`PASS: Library backend v2.31.1 Asynchronous Publication Corpus Loading & Transport Repair deployed.`
