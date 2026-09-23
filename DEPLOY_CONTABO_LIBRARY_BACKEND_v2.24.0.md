# Deploy Sustainable Catalyst Library Backend v2.24.0

This backend is required by Knowledge Library v5.13.0.

## Scope

- Hybrid lexical + semantic retrieval
- weighted Reciprocal Rank Fusion
- Library-owned semantic vector store
- idempotent embedding job queue and bounded worker
- Core-aware search results using v5.12.0 durable Platform Core bindings
- explicit lexical fallback if no embedding provider is configured

## Copy from macOS

```bash
cd ~/Downloads/sc-library-v5.13.0-release

scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes sustainable-catalyst-library-backend-v2.24.0.zip upgrade_library_backend_v2_24_0_contabo.sh catalystadmin@94.72.113.77:/tmp/
```

## Deploy on Contabo

```bash
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes catalystadmin@94.72.113.77

cd /tmp
chmod +x upgrade_library_backend_v2_24_0_contabo.sh
./upgrade_library_backend_v2_24_0_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.24.0.zip
```

The installer preserves the existing backend `.env`, the Platform Core write key, database state, and prior Core bindings/outbox. It applies only additive schema objects.

## Semantic provider

A new embedding credential is not required for deployment. Without one, `/v1/search?mode=hybrid` reports `lexical-fallback` and remains operational.

To activate Gemini embeddings, edit `/opt/sustainable-catalyst/library-backend/.env` and set:

```bash
SC_LIBRARY_EMBEDDING_PROVIDER=gemini
SC_LIBRARY_EMBEDDING_API_KEY=<server-side Gemini API key>
SC_LIBRARY_EMBEDDING_MODEL=gemini-embedding-2
SC_LIBRARY_EMBEDDING_DIMENSIONS=768
```

Then recreate the Library backend:

```bash
cd /opt/sustainable-catalyst/library-backend
docker compose up -d --force-recreate
```

Do not place the embedding key in WordPress or browser JavaScript.
