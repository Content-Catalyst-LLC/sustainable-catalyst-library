# Deploy Carbon & Nature Intelligence backend v2.2.0

No PostgreSQL migration and no new credential are required. The deploy script preserves the current `.env`, backs up the live application, rebuilds the container, verifies health, verifies the Carbon & Nature v0.1.0 manifest, and checks a sample research-context packet.
