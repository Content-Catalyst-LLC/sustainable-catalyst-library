# Deploy Library Backend v2.6.0 — Carbon & Nature Intelligence v0.5.0

This upgrade preserves the live Library backend `.env`, creates an application backup, installs backend v2.6.0, rebuilds/recreates the existing Docker service, and verifies AFOLU Research Librarian Intelligence together with the retained Carbon & Nature object-model and evidence-graph surfaces.

No PostgreSQL migration and no new credentials are required.

## macOS upload

```bash
cd ~/Downloads

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.6.0.zip \
  upgrade_library_backend_v2_6_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## Contabo upgrade

```bash
chmod +x /tmp/upgrade_library_backend_v2_6_0_contabo.sh
/tmp/upgrade_library_backend_v2_6_0_contabo.sh
```

The upgrader verifies `/health`, the Carbon & Nature manifest, retained project object/provenance surfaces, retained evidence graph, the AFOLU Research Librarian manifest, intent registry, source-role registry, a multi-intent MRV/inventory guidance packet, and the enriched Research Librarian context handoff. It also runs the retained stateless project-packet validator inside the rebuilt backend container.
