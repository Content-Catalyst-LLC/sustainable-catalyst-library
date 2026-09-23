# Deploy Library Backend v2.26.0 to Contabo

From the Mac release directory:

```bash
scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes sustainable-catalyst-library-backend-v2.26.0.zip upgrade_library_backend_v2_26_0_contabo.sh catalystadmin@94.72.113.77:/tmp/
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes catalystadmin@94.72.113.77
```

On the VPS:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_26_0_contabo.sh
./upgrade_library_backend_v2_26_0_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.26.0.zip
```

The installer backs up the existing backend, preserves `.env`, rebuilds the Docker service, verifies backend v2.26.0, confirms the research-candidate table, checks extraction readiness, confirms Platform Core 8/8 readiness, and reruns hybrid-search and citation-graph smoke tests.
