# Sustainable Catalyst Knowledge Library v5.42.0

## Neural Reranking & Retrieval Evaluation

This release pairs WordPress plugin **v5.42.0** with Library backend **v2.53.0**.

Use `sustainable-catalyst-library-v5.42.0-wordpress.zip` for WordPress and `sustainable-catalyst-library-backend-v2.53.0.zip` for Contabo. The complete repository ZIP and release bundle are also included.

### Deployment order
1. Run the Mac validation/Git installer and push tag `v5.42.0`.
2. Transfer backend v2.53.0 and its Contabo upgrade script.
3. Deploy and verify backend v2.53.0.
4. Install/upgrade the WordPress plugin ZIP to v5.42.0.

Neural reranking is **disabled by default**. Deploying v5.42 does not call an external reranker, does not backfill embeddings, and does not change the default v5.41 search behavior.
