# No Backend Redeploy Required — v5.6.1

Sustainable Catalyst Library v5.6.1 is a WordPress/public-presentation release.

The Python Library backend remains **v1.1.0**. No PostgreSQL migration, reindex, Caddy change, DNS change, port change, API-key change, or backend container restart is required.

The homepage widget reads corpus telemetry through the existing WordPress Explorer REST boundary, which already falls back to WordPress-local public data if the Python service cannot be reached.
