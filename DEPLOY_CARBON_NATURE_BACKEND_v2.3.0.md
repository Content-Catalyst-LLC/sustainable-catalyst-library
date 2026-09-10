# Deploy Carbon & Nature Intelligence backend v2.3.0

No PostgreSQL migration and no new credential are required. The deploy script preserves the current `.env`, backs up the live application, rebuilds the container, verifies backend health, verifies the Carbon & Nature v0.2.0 manifest, validates the measure registry, checks a measure comparison packet, and checks a measure-aware research-context packet.
