# Deploy Library Backend v2.58.0

Backend v2.58.0 adds Linguistic Corpus Objects, Concordance & KWIC.

## Transfer from Mac

```bash
cd ~/Downloads/sc-library-v5.47.0-release
scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.58.0.zip \
  upgrade_library_backend_v2_58_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes catalystadmin@94.72.113.77
```

## Deploy on Contabo

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_58_0_contabo.sh
sudo ./upgrade_library_backend_v2_58_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.58.0.zip
```

The verifier checks backend identity, corpus/document/token schema, readiness, deterministic tokenization, KWIC/frequency behavior, linguistic guardrails, v5.46/v5.45 lineage continuity, v5.44 federation continuity, and Python/Go/Rust runtime health.
