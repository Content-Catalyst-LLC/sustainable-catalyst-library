# Deploy Library Backend v2.5.0 — Carbon & Nature Intelligence v0.4.0

This upgrade preserves the live Library backend `.env`, creates an application backup, installs backend v2.5.0, rebuilds/recreates the existing Docker service, and verifies the Carbon Project Object Model & Provenance surfaces.

No PostgreSQL migration and no new credentials are required.

## macOS upload

```bash
cd ~/Downloads

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.5.0.zip \
  upgrade_library_backend_v2_5_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## Contabo upgrade

```bash
chmod +x /tmp/upgrade_library_backend_v2_5_0_contabo.sh
/tmp/upgrade_library_backend_v2_5_0_contabo.sh
```

The upgrader verifies `/health`, the Carbon & Nature manifest, project object model, object-type registry, provenance-event registry, packet template, retained evidence graph, and Research Librarian project-object handoff. It also runs the stateless packet validator inside the rebuilt backend container against the packaged template.
