# Deploy Knowledge Library backend v2.60.0

Use `upgrade_library_backend_v2_60_0_contabo.sh` on the Contabo host. The installer preserves `.env`, adds the Redis service from `compose.yml`, rebuilds Python/Go/Rust containers, waits for Redis/Go/FastAPI health, verifies the additive PostgreSQL execution tables, and performs stateless job-package validation. It does not persist a sample research job.
